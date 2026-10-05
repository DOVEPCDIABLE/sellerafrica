#!/usr/bin/env python3
"""Authenticated HTTP/WebSocket proxy to a private, short-lived ttyd socket."""
import asyncio
import hashlib
import hmac
import json
import os
import time

from aiohttp import ClientError, ClientSession, ClientTimeout, UnixConnector, WSMsgType, web

HOST = "sellerafrica.com:8443"
ORIGIN = "https://" + HOST
PARENT = "https://sellerafrica.com"
BASE = "/root-terminal"
LEASE = "/var/lib/btcpay-control-root/terminal.json"
COOKIE = "BTCPAY_ROOT_TERMINAL"
SOCKET = "/run/btcpay-control-terminal/ttyd.sock"


def read_lease():
    try:
        with open(LEASE, encoding="utf8") as source:
            lease = json.load(source)
        if lease["expires"] <= time.time():
            return None
        return lease
    except (OSError, ValueError, KeyError):
        return None


def authorized(request):
    lease = read_lease()
    token = request.cookies.get(COOKIE, "")
    ip = request.headers.get("X-BTCPay-Client-IP", "")
    if not lease or not token or not lease.get("bootstrapped"):
        return None
    if not hmac.compare_digest(lease["token_hash"], hashlib.sha256(token.encode()).hexdigest()):
        return None
    if not hmac.compare_digest(lease["ip"], ip):
        return None
    return lease


def secure_headers():
    return {"Cache-Control": "no-store, private", "Referrer-Policy": "no-referrer", "X-Content-Type-Options": "nosniff", "X-Robots-Tag": "noindex, nofollow", "Content-Security-Policy": "default-src 'self' data: blob:; script-src 'self' 'unsafe-inline' 'wasm-unsafe-eval'; style-src 'self' 'unsafe-inline'; connect-src 'self'; frame-ancestors https://sellerafrica.com; base-uri 'self'"}


async def bootstrap(request):
    if request.host != HOST or request.headers.get("Origin") != PARENT:
        raise web.HTTPForbidden(text="Terminal origin rejected.")
    data = await request.post()
    token = str(data.get("token", ""))
    # Share the bridge lock so closing a lease cannot race its bootstrap.
    import fcntl
    with open("/var/lib/btcpay-control-root/terminal.lock", "a+") as lock:
        fcntl.flock(lock, fcntl.LOCK_EX)
        lease = read_lease()
        if not lease or lease.get("bootstrapped") or not hmac.compare_digest(lease["ip"], request.headers.get("X-BTCPay-Client-IP", "")) or not hmac.compare_digest(lease["token_hash"], hashlib.sha256(token.encode()).hexdigest()):
            raise web.HTTPForbidden(text="Terminal access expired or has already been used. Unlock it again in the console.")
        lease["bootstrapped"] = True
        temporary = LEASE + ".tmp"
        with open(temporary, "w", encoding="utf8") as target:
            json.dump(lease, target)
        os.chmod(temporary, 0o600)
        os.replace(temporary, LEASE)
    response = web.HTTPSeeOther(BASE + "/", headers=secure_headers())
    response.set_cookie(COOKIE, token, max_age=max(1, int(lease["expires"] - time.time())), path=BASE, secure=True, httponly=True, samesite="Strict")
    return response


async def proxy(request):
    if request.host != HOST:
        raise web.HTTPForbidden(text="Terminal host rejected.")
    if request.method != "GET":
        raise web.HTTPMethodNotAllowed(request.method, ["GET"])
    lease = authorized(request)
    if not lease:
        return web.Response(status=401, text="Root terminal is locked. Return to BTCPay Control and unlock a new session.", headers=secure_headers())
    if request.headers.get("Upgrade", "").lower() == "websocket":
        if request.headers.get("Origin") != ORIGIN:
            raise web.HTTPForbidden(text="Terminal WebSocket origin rejected.")
        return await websocket(request, lease)
    headers = {"Host": HOST, "X-BTCPay-Owner": "console-owner"}
    try:
        async with request.app["client"].get("http://localhost" + request.path, headers=headers) as upstream:
            body = await upstream.read()
            safe = secure_headers()
            safe["Content-Type"] = upstream.headers.get("Content-Type", "application/octet-stream")
            return web.Response(status=upstream.status, body=body, headers=safe)
    except (ClientError, OSError, asyncio.TimeoutError):
        return web.Response(status=503, text="Terminal session ended. Close it and unlock a fresh session in BTCPay Control.", headers=secure_headers())


async def websocket(request, lease):
    connector = UnixConnector(path=SOCKET)
    async with ClientSession(connector=connector, timeout=ClientTimeout(total=None)) as client:
        try:
            remote = await client.ws_connect("http://localhost" + request.path, headers={"Host": HOST, "X-BTCPay-Owner": "console-owner", "Origin": ORIGIN}, protocols=["tty"], max_msg_size=2**20)
        except Exception:
            return web.Response(status=503, text="Terminal is unavailable. Close and unlock a fresh session.", headers=secure_headers())
        ws = web.WebSocketResponse(protocols=["tty"], heartbeat=15, max_msg_size=2**20)
        await ws.prepare(request)

        async def forward(source, destination):
            async for message in source:
                if message.type == WSMsgType.BINARY:
                    await destination.send_bytes(message.data)
                elif message.type == WSMsgType.TEXT:
                    await destination.send_str(message.data)
                else:
                    break

        async def expiration():
            while time.time() < lease["expires"]:
                current = read_lease()
                if not current or current["token_hash"] != lease["token_hash"]:
                    break
                await asyncio.sleep(1)

        tasks = [asyncio.create_task(forward(ws, remote)), asyncio.create_task(forward(remote, ws)), asyncio.create_task(expiration())]
        try:
            await asyncio.wait(tasks, return_when=asyncio.FIRST_COMPLETED)
        finally:
            for task in tasks:
                task.cancel()
            await asyncio.gather(*tasks, return_exceptions=True)
            await remote.close()
            await ws.close(code=1000, message=b"Root terminal session ended.")
        return ws


async def session(app):
    app["client"] = ClientSession(connector=UnixConnector(path=SOCKET), timeout=ClientTimeout(total=10))
    yield
    await app["client"].close()


def application():
    app = web.Application(client_max_size=4096)
    app.cleanup_ctx.append(session)
    app.router.add_post(BASE + "/bootstrap", bootstrap)
    app.router.add_route("*", BASE + "/{tail:.*}", proxy)
    return app


if __name__ == "__main__":
    os.umask(0o077)
    web.run_app(application(), host="127.0.0.1", port=8766, access_log=None)
