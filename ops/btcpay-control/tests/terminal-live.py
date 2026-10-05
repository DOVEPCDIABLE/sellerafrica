"""Run as root on the host. Uses a disposable lease, never owner credentials."""
import asyncio
import json
import subprocess
import time
from aiohttp import ClientSession, WSMsgType

BRIDGE = "/usr/local/sbin/btcpay-control"
BASE = "http://127.0.0.1:8766/root-terminal/"
HEADERS = {"Host": "sellerafrica.com:8443", "X-BTCPay-Client-IP": "127.0.0.1"}


def bridge(action, data):
    result = subprocess.run([BRIDGE, action], input=json.dumps(data), capture_output=True, text=True, check=True)
    response = json.loads(result.stdout)
    assert response["ok"], action
    return response


async def main():
    async with ClientSession() as client:
        async with client.get(BASE, headers=HEADERS) as response:
            assert response.status == 401
        print("PASS Unauthenticated access blocked")
        lease = bridge("terminal-open", {"ip": "127.0.0.1"})
        try:
            token = lease["token"]
            origin = {**HEADERS, "Origin": "https://sellerafrica.com"}
            async with client.post(BASE + "bootstrap", data={"token": token}, headers={**origin, "Origin": "https://attacker.invalid"}, allow_redirects=False) as response:
                assert response.status == 403
            async with client.post(BASE + "bootstrap", data={"token": token}, headers=origin, allow_redirects=False) as response:
                assert response.status == 303
                assert "HttpOnly" in response.headers["Set-Cookie"] and "Secure" in response.headers["Set-Cookie"]
            async with client.post(BASE + "bootstrap", data={"token": token}, headers=origin, allow_redirects=False) as response:
                assert response.status == 403
            print("PASS Bootstrap origin, one-time use and secure cookie")
            authenticated = {**HEADERS, "Cookie": "BTCPAY_ROOT_TERMINAL=" + token}
            async with client.get(BASE, headers={**authenticated, "X-BTCPay-Client-IP": "192.0.2.1"}) as response:
                assert response.status == 401
            async with client.get(BASE, headers=authenticated) as response:
                assert response.status == 200
            print("PASS IP binding and authenticated terminal assets")
            async with client.get(BASE + "ws", headers={**authenticated, "Upgrade": "websocket", "Origin": "https://attacker.invalid"}) as response:
                assert response.status == 403
            async with client.get(BASE + "token", headers=authenticated) as response:
                auth = await response.json()
            async with client.ws_connect(BASE + "ws", headers={**authenticated, "Origin": "https://sellerafrica.com:8443"}, protocols=["tty"]) as ws:
                await ws.send_str(json.dumps({"AuthToken": auth.get("token", ""), "columns": 100, "rows": 24}))
                await ws.send_bytes(b"0printf 'ROOT_CHECK=%s\\n' \"$(whoami)\"; pwd\r")
                output = b""
                until = time.time() + 10
                while time.time() < until:
                    message = await ws.receive(timeout=10)
                    if message.type == WSMsgType.BINARY:
                        output += message.data
                    if b"ROOT_CHECK=root" in output and b"/root/btcpayserver-docker" in output:
                        break
                assert b"ROOT_CHECK=root" in output and b"/root/btcpayserver-docker" in output, "Root PTY output missing"
                print("PASS Interactive root PTY and BTCPay working directory")
                bridge("terminal-close", {"token": token})
                await ws.receive(timeout=5)
            async with client.get(BASE, headers=authenticated) as response:
                assert response.status == 401
            print("PASS Revocation locks access immediately")
        finally:
            bridge("terminal-close", {"token": lease["token"]})


asyncio.run(main())
