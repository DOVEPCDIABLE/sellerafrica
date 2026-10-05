<?php
declare(strict_types=1);

use BTCPayControl\Console;
use OTPHP\TOTP;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/Console.php';

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
$scriptNonce = bin2hex(random_bytes(16));
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'nonce-$scriptNonce'; frame-src https://sellerafrica.com:8443; form-action 'self' https://sellerafrica.com:8443; frame-ancestors 'none'; base-uri 'none'");
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Strict-Transport-Security: max-age=31536000');
if (empty($_SERVER['HTTPS']) || strtolower((string)$_SERVER['HTTPS']) === 'off') {
    http_response_code(403);
    exit('This console requires HTTPS.');
}

$private = '/var/lib/btcpay-control';
umask(0077);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('BTCPAY_CONTROL_SESSION');
session_save_path($private . '/sessions');
session_set_cookie_params(['lifetime'=>0, 'path'=>'/btcpay', 'secure'=>true, 'httponly'=>true, 'samesite'=>'Strict']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$console = new Console($private);
$owner = $console->owner();
$ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 80);
$error = '';
$notice = (string)($_SESSION['notice'] ?? '');
unset($_SESSION['notice']);
function closeRootTerminal(Console $console): void {
    if (!empty($_SESSION['terminal']['token'])) {
        $console->run('terminal-close', ['token'=>$_SESSION['terminal']['token']]);
    }
    unset($_SESSION['terminal']);
}
if (!empty($_SESSION['authenticated']) && (time() - (int)($_SESSION['seen_at'] ?? 0) > 900 || time() - (int)($_SESSION['signed_in_at'] ?? 0) > 3600)) {
    closeRootTerminal($console);
    unset($_SESSION['authenticated']);
    session_regenerate_id(true);
    $error = 'Your maintenance session expired. Sign in again.';
}
if (!empty($_SESSION['pending']) && time() - $_SESSION['pending']['created'] > 600) unset($_SESSION['pending']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) throw new RuntimeException('Your session changed. Refresh the page and try again.');
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'begin' && !$owner) {
            $console->throttle('setup', $ip);
            $bootstrapHash = trim((string)@file_get_contents($private . '/bootstrap.sha256'));
            if ($bootstrapHash === '' || !hash_equals($bootstrapHash, hash('sha256', trim((string)($_POST['bootstrap_token'] ?? ''))))) throw new RuntimeException('The setup token is incorrect. Obtain it through root SSH.');
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $phrase = (string)($_POST['phrase'] ?? '');
            if (!preg_match('/^[a-zA-Z0-9_.-]{3,40}$/D', $username)) throw new RuntimeException('Use a username with 3 to 40 letters, numbers, dots, underscores or hyphens.');
            if (strlen($password) < 12 || strlen($phrase) < 12 || strlen($password) > 64 || strlen($phrase) > 64 || str_contains($password . $phrase, "\0")) throw new RuntimeException('Use 12 to 64 characters for both your password and restart passphrase.');
            if (hash_equals($password, $phrase)) throw new RuntimeException('Your restart passphrase must be different from your login password.');
            $_SESSION['pending'] = ['username'=>$username, 'password_hash'=>password_hash($password, PASSWORD_DEFAULT), 'phrase_hash'=>password_hash($phrase, PASSWORD_DEFAULT), 'secret'=>TOTP::generate()->getSecret(), 'created'=>time()];
            session_regenerate_id(true);
        } elseif ($action === 'enroll' && !$owner && !empty($_SESSION['pending'])) {
            $console->throttle('enroll', $ip);
            $console->enroll($_SESSION['pending'], trim((string)($_POST['code'] ?? '')));
            unset($_SESSION['pending']);
            $_SESSION['authenticated'] = true;
            $_SESSION['signed_in_at'] = time();
            $_SESSION['seen_at'] = time();
            session_regenerate_id(true);
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            $console->audit('setup', 'Independent owner account created with authenticator 2FA.', $ip);
            $_SESSION['notice'] = 'Authenticator verified. Your private console is ready.';
            header('Location: /btcpay'); exit;
        } elseif ($action === 'login' && $owner) {
            $console->throttle('login', $ip);
            if (!hash_equals($owner['username'], trim((string)($_POST['username'] ?? ''))) || !password_verify((string)($_POST['password'] ?? ''), $owner['password_hash']) || !$console->claimCode($owner, trim((string)($_POST['code'] ?? '')))) {
                $console->audit('login_failed', 'Credentials or authenticator code did not match.', $ip);
                throw new RuntimeException('Check your username, password and authenticator code. Previously used codes cannot be reused.');
            }
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            $_SESSION['signed_in_at'] = time();
            $_SESSION['seen_at'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            $console->audit('login', 'Owner signed in with 2FA.', $ip);
            header('Location: /btcpay'); exit;
        } elseif (!empty($_SESSION['authenticated']) && $owner) {
            if ($action === 'logout') {
                closeRootTerminal($console);
                $_SESSION = [];
                session_destroy();
                setcookie('BTCPAY_CONTROL_SESSION', '', ['expires'=>time()-3600, 'path'=>'/btcpay', 'secure'=>true, 'httponly'=>true, 'samesite'=>'Strict']);
                header('Location: /btcpay'); exit;
            }
            if ($action === 'terminal-open') {
                $console->throttle('terminal', $ip);
                if ((string)($_POST['root_confirm'] ?? '') !== 'yes') throw new RuntimeException('Confirm that this terminal grants full root access to the entire server.');
                if (!password_verify((string)($_POST['phrase'] ?? ''), $owner['phrase_hash']) || !$console->claimCode($owner, trim((string)($_POST['code'] ?? '')))) {
                    $console->audit('terminal_denied', 'Passphrase or fresh authenticator code did not match.', $ip);
                    throw new RuntimeException('Check your passphrase and enter a fresh authenticator code to unlock the root terminal.');
                }
                $lease = $console->run('terminal-open', ['ip'=>$ip]);
                $_SESSION['terminal'] = ['token'=>$lease['token'], 'expires'=>$lease['expires'], 'new'=>true];
                $console->audit('terminal_open', 'Five-minute full-server root terminal unlocked.', $ip);
                header('Location: /btcpay#root-terminal-panel'); exit;
            } elseif ($action === 'terminal-close') {
                closeRootTerminal($console);
                $console->audit('terminal_closed', 'Root terminal closed by owner.', $ip);
                $_SESSION['notice'] = 'Root terminal closed.';
            } elseif ($action === 'restart') {
                $console->throttle('restart', $ip);
                if ((string)($_POST['confirm'] ?? '') !== 'yes') throw new RuntimeException('Confirm that you want to restart the BTCPay stack.');
                if (!password_verify((string)($_POST['phrase'] ?? ''), $owner['phrase_hash']) || !$console->claimCode($owner, trim((string)($_POST['code'] ?? '')))) {
                    $console->audit('restart_denied', 'Passphrase or fresh authenticator code did not match.', $ip);
                    throw new RuntimeException('Check your restart passphrase and enter a fresh authenticator code.');
                }
                $console->restart();
                $console->audit('restart_requested', 'BTCPay restart job queued.', $ip);
                $_SESSION['notice'] = 'Restart requested. Refresh health in a few moments to see its progress.';
            } elseif ($action !== 'refresh') throw new RuntimeException('Unsupported operation.');
            header('Location: /btcpay'); exit;
        } else throw new RuntimeException('Sign in to the independent maintenance account first.');
    } catch (Throwable $exception) {
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'The operation could not be completed. Use root SSH to inspect the console service.';
        if (!$exception instanceof RuntimeException) error_log('BTCPay control operation failed: ' . get_class($exception));
    }
}

$health = null;
if (!empty($_SESSION['terminal']) && $_SESSION['terminal']['expires'] <= time()) unset($_SESSION['terminal']);
if (!empty($_SESSION['authenticated']) && $owner) {
    $_SESSION['seen_at'] = time();
    try {
        if (time() - (int)($_SESSION['health_at'] ?? 0) >= 10) {
            $_SESSION['health'] = $console->run('health');
            $_SESSION['health_at'] = time();
        }
        $health = $_SESSION['health'] ?? null;
    } catch (Throwable $exception) { $error = $exception->getMessage(); }
}
function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function token(): void { echo '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">'; }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>BTCPay Control</title>
<style>
:root{--primary:#151515;--text:#202020;--muted:#656565;--border:#dedede;--background:#f7f7f7;--surface:#fff;--radius:8px}
*{box-sizing:border-box}body{margin:0;background:var(--background);color:var(--text);font:15px/1.6 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;letter-spacing:0}
main{max-width:1120px;margin:40px auto;padding:0 24px}header{display:flex;gap:20px;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding-bottom:24px;margin-bottom:32px}
.identity{display:flex;align-items:center;gap:16px}.brand-mark{display:grid;place-items:center;background:var(--primary);color:white;width:48px;height:48px;border-radius:var(--radius);font:bold 24px/1 system-ui;flex:none}
h1{font-size:26px;line-height:1.3;margin:0 0 3px;font-weight:650}h2{font-size:21px;line-height:1.4;margin:0 0 12px;font-weight:650}header span,p{color:var(--muted)}p{margin:0 0 18px}
.auth{max-width:480px;margin:64px auto;background:var(--surface);padding:36px;border:1px solid var(--border);border-radius:var(--radius);box-shadow:0 12px 36px #00000006}.auth h2{font-size:26px}.auth form>button{width:100%;margin-top:8px}
label{display:block;margin:20px 0 8px;font-weight:600;font-size:14px}input{display:block;width:100%;min-height:52px;font:inherit;padding:12px 14px;border:1px solid var(--border);border-radius:6px;background:var(--surface);color:var(--text)}
button,.button{display:inline-flex;justify-content:center;align-items:center;gap:10px;font:inherit;font-weight:600;background:var(--primary);color:white;border:1px solid var(--primary);border-radius:6px;min-height:46px;padding:10px 18px;cursor:pointer;text-decoration:none}
button:hover,.button:hover{background:#333;color:white;border-color:#333}input:focus-visible,button:focus-visible,.button:focus-visible{outline:2px solid #151515;outline-offset:3px}.secondary{background:white;color:var(--text);border:1px solid var(--border)}.secondary:hover{background:#ededed;color:var(--text);border-color:#bdbdbd}
.error,.notice{padding:16px 20px;margin:20px 0;border:1px solid var(--border);border-left:4px solid var(--primary);border-radius:6px;background:white}.error{font-weight:600}.notice{background:#ededed}
.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.stat{border:1px solid var(--border);border-radius:var(--radius);background:var(--surface);padding:24px}.stat span{color:var(--muted);font-size:13px}.stat strong{display:block;font-size:26px;margin-top:8px;font-weight:650}.bad{border-left:3px solid var(--primary);padding-left:12px}.good{color:var(--primary)}
.section{margin-top:32px;padding-top:28px;border-top:1px solid var(--border)}.table{overflow:auto;border:1px solid var(--border);border-radius:var(--radius)}table{border-collapse:collapse;width:100%;min-width:680px;background:var(--surface)}th,td{text-align:left;padding:14px 16px;border-bottom:1px solid var(--border);overflow-wrap:anywhere;vertical-align:top}th{color:var(--muted);font-size:12px;white-space:nowrap;background:#f1f1f1}tbody tr:last-child td{border-bottom:0}tbody tr:hover{background:#fafafa}
.restart{max-width:600px}.check{display:flex;align-items:start;gap:12px;font-weight:400}.check input{width:20px;min-height:20px;flex:none;margin-top:3px;accent-color:var(--primary)}code{display:block;overflow-wrap:anywhere;background:#f0f0f0;padding:16px;border-radius:6px;font-size:14px}.actions{display:flex;gap:10px;flex-wrap:wrap}.actions form{margin:0}
.terminal{display:flex;align-items:center;justify-content:space-between;gap:24px}.terminal p{max-width:620px;margin-bottom:0}.terminal .button{flex:none}.terminal-note{font-size:13px;color:var(--muted);margin-top:12px}
.root-frame{display:block;width:100%;height:560px;border:1px solid var(--border);border-radius:8px;background:#111}.root-heading{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px}.root-unlock{max-width:600px}
@media(max-width:700px){main{margin:24px auto;padding:0 16px}.grid{grid-template-columns:1fr}.stat{padding:20px}header{align-items:start;flex-direction:column;gap:22px}.auth{margin:32px auto;padding:24px}h1{font-size:23px}.actions{width:100%}.actions form{flex:1}.actions button{width:100%;padding:10px 12px}.terminal{align-items:start;flex-direction:column}.terminal .button{width:100%}.table{max-width:100%}th,td{padding:12px;font-size:13px}}
</style></head><body><main>
<header><div class="identity"><div class="brand-mark" aria-hidden="true">B</div><div><h1>BTCPay Control</h1><span>Private maintenance console</span></div></div><?php if (!empty($_SESSION['authenticated'])): ?><div class="actions"><form method="post"><?php token(); ?><button name="action" value="refresh" class="secondary">Refresh health</button></form><form method="post"><?php token(); ?><button name="action" value="logout" class="secondary">Sign out</button></form></div><?php endif; ?></header>
<?php if ($error): ?><div class="error" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="notice" role="status"><?= h($notice) ?></div><?php endif; ?>
<?php if (!$owner && empty($_SESSION['pending'])): ?>
<section class="auth"><h2>Set up private access</h2><p>This account is independent of the marketplace.</p><form method="post"><?php token(); ?><input type="hidden" name="action" value="begin"><label for="bootstrap">Root setup token</label><input id="bootstrap" name="bootstrap_token" type="password" required autocomplete="off"><label for="username">Console username</label><input id="username" name="username" required minlength="3" maxlength="40" autocomplete="username"><label for="password">Login password</label><input id="password" name="password" type="password" minlength="12" required autocomplete="new-password"><label for="phrase">Restart passphrase</label><input id="phrase" name="phrase" type="password" minlength="12" required autocomplete="new-password"><p>Use at least 12 characters for each secret. Choose a different restart passphrase.</p><button>Set up authenticator</button></form></section>
<?php elseif (!$owner): $otp = TOTP::createFromSecret($_SESSION['pending']['secret']); $otp->setLabel($_SESSION['pending']['username']); $otp->setIssuer('BTCPay Control'); ?>
<section class="auth"><h2>Connect your authenticator</h2><p>Add a time-based account in Google Authenticator, Microsoft Authenticator or another TOTP app. Keep this key private.</p><label>Account</label><p>BTCPay Control / <?= h($_SESSION['pending']['username']) ?></p><label>Setup key</label><code><?= h($_SESSION['pending']['secret']) ?></code><p>Time-based, six digits, 30 seconds. Enter the current code to finish setup.</p><form method="post"><?php token(); ?><input type="hidden" name="action" value="enroll"><label for="code">Authenticator code</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"><p></p><button>Verify and finish</button></form></section>
<?php elseif (empty($_SESSION['authenticated'])): ?>
<section class="auth"><h2>Sign in securely</h2><p>Use your maintenance account, not your Seller Africa login.</p><form method="post"><?php token(); ?><input type="hidden" name="action" value="login"><label for="username">Username</label><input id="username" name="username" required autocomplete="username"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"><label for="code">Authenticator code</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"><p></p><button>Sign in</button></form></section>
<?php else: ?>
<?php if ($health): ?>
<div class="grid"><div class="stat"><span>Docker daemon</span><strong class="<?= $health['docker_ready'] ? 'good' : 'bad' ?>"><?= $health['docker_ready'] ? 'Available' : 'Unavailable' ?></strong></div><div class="stat"><span>BTCPay stack</span><strong><?= h($health['summary']) ?></strong></div><div class="stat"><span>Disk available</span><strong><?= h($health['disk']['free_gb']) ?> GB</strong></div></div>
<section class="section"><h2>Container diagnostics</h2><p>Checked <?= h($health['checked_at']) ?>. Container status does not confirm Bitcoin synchronization or successful payment processing.</p><div class="table"><table><thead><tr><th>Container</th><th>State / health</th><th>Restarts</th><th>CPU</th><th>Memory</th></tr></thead><tbody><?php foreach ($health['containers'] as $container): ?><tr><td><?= h($container['name']) ?></td><td><?= h($container['state']) ?> / <?= h($container['health']) ?></td><td><?= h($container['restarts']) ?></td><td><?= h($container['cpu']) ?></td><td><?= h($container['memory']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="section"><h2>Troubleshooting</h2><p>pay.itrade.africa: <strong><?= h($health['endpoint']) ?></strong></p><p>Restart job: <strong><?= h($health['job']['state']) ?></strong>. Last result: <?= h($health['job']['result']) ?>.</p><ul><?php foreach ($health['checks'] as $check): ?><li><?= h($check) ?></li><?php endforeach; ?></ul></section>
<?php endif; ?>
<section class="section" id="root-terminal-panel"><h2>Server root terminal</h2><p>Full root access to this server, including BTCPay and Seller Africa. Commands run immediately and can permanently change or delete server data.</p>
<?php if (!empty($_SESSION['terminal'])): ?>
<div class="root-heading"><span>Session ends at <?= h(gmdate('H:i:s', $_SESSION['terminal']['expires'])) ?> UTC. Closing or refreshing the terminal may end this session.</span><form method="post"><?php token(); ?><button name="action" value="terminal-close" class="secondary">Close terminal</button></form></div>
<iframe class="root-frame" name="root-shell" title="Full server root terminal" referrerpolicy="no-referrer" <?php if (empty($_SESSION['terminal']['new'])): ?>src="https://sellerafrica.com:8443/root-terminal/"<?php endif; ?>></iframe>
<?php if (!empty($_SESSION['terminal']['new'])): $_SESSION['terminal']['new'] = false; ?>
<form id="terminal-bootstrap" action="https://sellerafrica.com:8443/root-terminal/bootstrap" method="post" target="root-shell"><input type="hidden" name="token" value="<?= h($_SESSION['terminal']['token']) ?>"><noscript><button>Connect terminal</button></noscript></form>
<script nonce="<?= h($scriptNonce) ?>">document.getElementById('terminal-bootstrap').submit();</script>
<?php endif; ?>
<?php else: ?>
<form method="post" class="root-unlock"><?php token(); ?><input type="hidden" name="action" value="terminal-open"><label for="terminal-phrase">Maintenance passphrase</label><input id="terminal-phrase" name="phrase" type="password" required autocomplete="off"><label for="terminal-code">Fresh authenticator code</label><input id="terminal-code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"><label class="check"><input type="checkbox" name="root_confirm" value="yes" required>I understand this grants full root access to the entire server.</label><button>Unlock root terminal</button></form>
<?php endif; ?></section>
<section class="section"><div class="terminal"><div><h2>WHM Terminal</h2><p>You can also open WHM Terminal and sign in separately with your WHM root credentials.</p></div><a class="button secondary" href="https://236.175.178.68.host.secureserver.net:2087/scripts12/terminal" target="_blank" rel="noopener noreferrer">Open WHM Terminal <span aria-hidden="true">&#8599;</span></a></div><p class="terminal-note">The maintenance console does not store or share WHM credentials.</p></section>
<section class="section restart"><h2>Restart BTCPay</h2><p>This restarts the BTCPay Docker stack and temporarily interrupts payment availability. Use a new code if you just signed in.</p><form method="post"><?php token(); ?><input type="hidden" name="action" value="restart"><label for="phrase">Restart passphrase</label><input id="phrase" name="phrase" type="password" required autocomplete="off"><label for="code">Fresh authenticator code</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"><label class="check"><input type="checkbox" name="confirm" value="yes" required>I understand that BTCPay payments may be unavailable during restart.</label><button>Confirm restart</button></form></section>
<section class="section"><h2>Recent activity</h2><div class="table"><table><thead><tr><th>Time (UTC)</th><th>Action</th><th>Details</th></tr></thead><tbody><?php foreach ($console->db->query('SELECT * FROM events ORDER BY id DESC LIMIT 10') as $event): ?><tr><td><?= h(gmdate('Y-m-d H:i:s', $event['created'])) ?></td><td><?= h($event['action']) ?></td><td><?= h($event['detail']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php endif; ?></main></body></html>
