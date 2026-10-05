# Independent BTCPay Console

Runs at `https://sellerafrica.com/btcpay` using a separate PHP-FPM service and Linux user. The ecommerce application, database, roles, dependencies and session are not used. CPU, memory, disk and Apache remain shared because this is hosted on the same VM and domain; separate credentials on the same origin are not equivalent to a separate security origin. A dedicated subdomain or VM is recommended for stronger isolation.

## Initial Access

From root SSH, read `/etc/btcpay-control/bootstrap-token`. Enter that token on the console setup page, choose an independent username, a password and a different restart passphrase, then add the displayed time-based key to your authenticator app and verify a code. Setup closes permanently once the owner is created. No Seller Africa admin or super-admin login grants access.

Login requires the independent password and a current authenticator code. Restart additionally requires the restart passphrase, a fresh code and explicit confirmation. Used codes cannot be reused. Sessions expire after 15 minutes idle or one hour total. Attempts are persistently rate-limited. Both application and privileged helper enforce a five-minute restart cooldown.

## Operations

Refresh reports Docker availability, compose-project containers, health-check status, restart counts, CPU/memory, available disk and the asynchronous restart job result. Container status alone does not verify blockchain synchronization, TLS/DNS, payouts or payment processing.

The embedded terminal provides unrestricted root access to the entire VM, not only BTCPay. Unlocking requires independent login, the maintenance passphrase, a fresh authenticator code and explicit confirmation. Packaged ttyd runs on a root-private Unix socket, behind a loopback-only aiohttp gateway and a dedicated HTTPS origin on port 8443. A one-time POST exchanges an IP-bound five-minute capability for a Secure/HttpOnly cookie. Origin checks protect bootstrap and WebSocket access; a shared root lock prevents bootstrap/close races. Closing, signing out, disconnecting or the systemd five-minute runtime limit ends the shell. Shell history is disabled; audit records unlock/close metadata, not commands. Protect the owner credentials and prefer an independent management domain/VM for stronger isolation. Full root commands can expose secrets, alter payments or delete data.

Restart uses the installation's existing `btcpay-restart.sh`, queued through an independent systemd oneshot. It does not upgrade, tear down, prune, delete data or restart the host. The root-owned bridge accepts only `health`, `restart`, `terminal-open` and `terminal-close`, with exact sudoers rules. The PHP process has no membership in the Docker group. Setup and testing do not restart BTCPay. WHM remains available through the server hostname and `/scripts12/terminal`, without a hardcoded expiring WHM session.

## Installation

Install the locked Composer dependencies in this folder, upload the folder as root to a private deployment directory, then run `bash deploy/install.sh`. The installer backs up Apache configuration, validates PHP-FPM, sudoers and Apache before graceful reload. This deployment targets the existing AlmaLinux/cPanel PHP 8.2 host and the BTCPay compose project `generated`.

## Recovery

For the terminal extension, install the AlmaLinux packages `ttyd`, `python3.11` and `python3.11-pip`. Create `/opt/btcpay-terminal-venv` with `python3.11 -m venv` and install `deploy/terminal-requirements.txt` there. Run `bash deploy/install-terminal.sh` as root. It backs up the existing console, bridge, sudoers and Apache include before installing the extension. Port 8443 must be reachable over HTTPS; port 8766 and ttyd remain private. The root terminal unit is not enabled at boot. Run `tests/terminal-live.py` through the isolated Python runtime for a disposable root-PTY/security test, and `tests/browser-terminal.cjs CLIENT_IP` for the real embedded-browser test without reading or changing owner credentials.

Access is recovered through root SSH only. Back up `/var/lib/btcpay-control` before resetting the independent owner or rotating credentials. Do not email authenticator secrets or put setup tokens in URLs. Root must protect the SQLite file, which contains the authenticator seed. No automatic email/password recovery or marketplace-admin bypass exists.

Inspect services using `systemctl status btcpay-control-php` and `systemctl status btcpay-control-restart`. Host logs may contain sensitive information: keep them restricted to SSH. After enrollment, delete `/etc/btcpay-control/bootstrap-token` through root SSH. The stored hash is harmless and cannot be used to log in once the owner exists.

Run `php tests/security.php` for independent-auth, OTP replay, rate-limit and command-allowlist checks.
