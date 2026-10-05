<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/Console.php';
use BTCPayControl\Console;
use OTPHP\TOTP;
function check(bool $condition, string $name): void { if (!$condition) throw new RuntimeException($name); echo "PASS $name\n"; }
$dir = sys_get_temp_dir() . '/btcpay-console-test-' . bin2hex(random_bytes(6));
mkdir($dir, 0700);
try {
    $console = new Console($dir);
    $secret = TOTP::generate()->getSecret();
    $otp = TOTP::createFromSecret($secret);
    check(Console::matchingStep($secret, 'invalid') === null, 'Invalid OTP rejected');
    check(Console::matchingStep($secret, $otp->now()) !== null, 'Authenticator code accepted');
    $console->enroll(['username'=>'owner','password_hash'=>password_hash('login-password', PASSWORD_DEFAULT),'phrase_hash'=>password_hash('separate-phrase', PASSWORD_DEFAULT),'secret'=>$secret], $otp->now());
    check(!$console->claimCode($console->owner(), $otp->now()), 'Enrollment OTP cannot be replayed');
    try { $console->enroll([], '123456'); check(false,'Duplicate owner blocked'); } catch (RuntimeException) { echo "PASS Duplicate owner blocked\n"; }
    for ($i = 0; $i < 5; $i++) $console->throttle('login', '127.0.0.1');
    $blocked = false;
    try { $console->throttle('login', '127.0.0.1'); } catch (RuntimeException) { $blocked = true; }
    check($blocked, 'Persistent login rate limit');
    try { $console->run('arbitrary-command'); check(false,'Command allowlist'); } catch (RuntimeException) { echo "PASS Command allowlist\n"; }
    $page = file_get_contents(dirname(__DIR__) . '/public/index.php');
    check(!str_contains($page, 'app/core/bootstrap.php') && !str_contains($page, 'AuthService') && str_contains($page, "session_name('BTCPAY_CONTROL_SESSION')"), 'Independent application and session');
} finally { unset($console); foreach (glob($dir . '/*') as $file) unlink($file); rmdir($dir); }
