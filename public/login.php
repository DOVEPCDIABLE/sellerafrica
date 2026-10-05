<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/core/bootstrap.php';

use App\EmailService;
use App\NotificationService;
use App\SecurityService;

try {
    if (!super_admin_exists()) {
        flash('info', 'Create the first super admin before signing in.');
        redirect('onboarding.php');
    }
} catch (\Throwable) {
    flash('warning', 'Database setup is not complete yet.');
    redirect('onboarding.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_SESSION['toasts'])) {
    $_SESSION['toasts'] = array_values(array_filter(
        $_SESSION['toasts'],
        static function (array $toast): bool {
            $message = (string)($toast['message'] ?? '');

            return !str_contains($message, 'Create the first super admin')
                && !str_contains($message, 'Database schema could not be prepared')
                && !str_contains($message, 'Database setup is not complete');
        }
    ));
}

$errors = [];
$pendingMfa = is_array($_SESSION['pending_mfa'] ?? null) ? $_SESSION['pending_mfa'] : null;
$captchaRequired = false;
$captchaChallenge = null;

if (($_GET['as'] ?? '') === 'affiliate') {
    $_SESSION['intended_url'] = app_url('affiliate');
}
if (($_GET['as'] ?? '') === 'distributor') {
    $_SESSION['intended_url'] = app_url('distributor/register');
}

function login_role_dashboard(array $roles, ?int $userId = null): string
{
    if (in_array('super_admin', $roles, true) || in_array('admin', $roles, true)) {
        return 'dashboard';
    }

    if (in_array('vendor', $roles, true)) {
        $distribution = \App\DistributorService::forUser((int)$userId);
        if ($distribution) return 'distributor';
        if (function_exists('app_user_is_farmer_vendor') && app_user_is_farmer_vendor($userId)) {
            return 'farmer';
        }
        return 'vendor';
    }

    if (in_array('affiliate', $roles, true)) {
        return 'affiliate';
    }

    if ($userId && \App\DistributorService::forUser($userId)) return 'distributor';
    return 'buyer';
}

function login_current_user_roles(): array
{
    if (!isset($_SESSION['user_id'])) {
        return [];
    }

    $_SESSION['roles'] = login_user_role_codes((int)$_SESSION['user_id']);

    return $_SESSION['roles'];
}

function login_user_role_codes(int $userId): array
{
    $roles = db()->fetchAll(
        "SELECT r.code
         FROM user_roles ur
         INNER JOIN roles r ON r.id = ur.role_id
         WHERE ur.user_id = ?",
        [$userId]
    );

    $roleCodes = array_values(array_unique(array_map('strval', array_column($roles, 'code'))));

    if (db()->fetch('SELECT id FROM vendors WHERE user_id = ? LIMIT 1', [$userId]) && !in_array('vendor', $roleCodes, true)) {
        $roleCodes[] = 'vendor';
    }

    if (db()->fetch('SELECT id FROM affiliates WHERE user_id = ? LIMIT 1', [$userId]) && !in_array('affiliate', $roleCodes, true)) {
        $roleCodes[] = 'affiliate';
    }

    return $roleCodes !== [] ? $roleCodes : ['customer'];
}

function login_clear_auth_session(): void
{
    foreach (['user_id', 'user_email', 'display_name', 'roles', 'vendor_id', 'admin_id', 'pending_mfa'] as $key) {
        unset($_SESSION[$key]);
    }
}

function login_verify_password(string $password, ?string $storedHash): bool
{
    $storedHash = (string)$storedHash;
    if ($storedHash === '') {
        return false;
    }

    if (password_verify($password, $storedHash)) {
        return true;
    }

    if (str_starts_with($storedHash, '$wp$')) {
        $wordpressHash = substr($storedHash, 4);
        $preHash = base64_encode(hash_hmac('sha384', $password, 'wp-sha384', true));

        return password_verify($preHash, $wordpressHash);
    }

    return false;
}

function login_safe_destination(?string $intendedUrl, array $roles, ?int $userId = null): string
{
    $fallback = login_role_dashboard($roles, $userId);
    $intendedUrl = trim((string)$intendedUrl);
    if ($intendedUrl === '') {
        return $fallback;
    }

    $path = trim((string)(parse_url($intendedUrl, PHP_URL_PATH) ?? ''), '/');
    $basePath = trim((string)(parse_url(app_url(''), PHP_URL_PATH) ?? ''), '/');
    if ($basePath !== '' && str_starts_with($path, $basePath)) {
        $path = trim(substr($path, strlen($basePath)), '/');
    }

    if ($path === '' || $path === 'login' || str_starts_with($path, 'login')) {
        return $fallback;
    }

    if ($path === 'chat' || str_starts_with($path, 'chat/')) {
        return (in_array('customer', $roles, true) || in_array('vendor', $roles, true) || in_array('super_admin', $roles, true) || in_array('admin', $roles, true)) ? $intendedUrl : $fallback;
    }

    if (str_starts_with($path, 'dashboard')) {
        return (in_array('super_admin', $roles, true) || in_array('admin', $roles, true)) ? $intendedUrl : $fallback;
    }

    if (in_array($path, ['distributor','distributor/register'], true)) {
        return app_url($path);
    }

    if (str_starts_with($path, 'vendor')) {
        return (in_array('vendor', $roles, true) || in_array('super_admin', $roles, true) || in_array('admin', $roles, true)) ? $intendedUrl : $fallback;
    }

    if (str_starts_with($path, 'farmer')) {
        return (in_array('vendor', $roles, true) || in_array('super_admin', $roles, true) || in_array('admin', $roles, true)) ? $intendedUrl : $fallback;
    }

    if (str_starts_with($path, 'affiliate')) {
        return (in_array('affiliate', $roles, true) || in_array('super_admin', $roles, true) || in_array('admin', $roles, true)) ? $intendedUrl : $fallback;
    }

    if (str_starts_with($path, 'buyer')) {
        return in_array('customer', $roles, true) ? $intendedUrl : $fallback;
    }

    return $fallback;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_SESSION['user_id'])) {
    $roles = login_current_user_roles();
    $intendedUrl = $_SESSION['intended_url'] ?? null;
    unset($_SESSION['intended_url']);
    redirect(login_safe_destination(is_string($intendedUrl) ? $intendedUrl : null, $roles, (int)$_SESSION['user_id']));
}

function login_send_alert(array $user, array $roles): void
{
    if (!SecurityService::rolesNeedMfa($roles)) {
        return;
    }

    try {
        EmailService::send(
            [['email' => (string)$user['email'], 'name' => (string)($user['display_name'] ?: $user['username'])]],
            'Seller Africa admin sign-in alert',
            '<p>Your Seller Africa admin account just signed in.</p><p>IP: ' . e(client_ip()) . '</p><p>If this was not you, reset your password immediately.</p>'
        );
    } catch (\Throwable $e) {
        error_log('Login alert failed: ' . $e->getMessage());
    }
}

function login_complete_session(array $user, array $roleCodes, ?string $intendedUrl): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['display_name'] = $user['display_name'] ?: $user['username'];
    $_SESSION['roles'] = $roleCodes;
    unset($_SESSION['pending_mfa']);

    db()->query("UPDATE users SET last_login_at = ? WHERE id = ?", [sql_now(), (int)$user['id']]);
    audit('login_success', 'users', (string)$user['id'], [], ['roles' => $roleCodes], (int)$user['id']);
    login_send_alert($user, $roleCodes);
    flash('success', 'Signed in successfully.');
    if (empty($user['email_verified_at']) && empty($user['wp_user_id'])) {
        flash('warning', 'Please confirm your email address to keep your account fully verified.');
    }
    $destination = login_safe_destination(is_string($intendedUrl) ? $intendedUrl : null, $roleCodes, (int)$user['id']);
    redirect($destination);
}

function login_failure_notice(string $reason): string
{
    return match ($reason) {
        'rate_limited' => 'Too many sign-in attempts. Please wait a few minutes, then try again or use Forgot password to reset your password.',
        'captcha_failed' => 'The security answer was incorrect. Please answer the security question and try signing in again.',
        'inactive_account' => 'This account is not active. Please contact support to reactivate it before signing in.',
        default => 'We could not sign you in. Check your email or username and password, or use Forgot password to reset your password.',
    };
}

function login_failure_detail(string $reason): string
{
    return match ($reason) {
        'rate_limited' => 'Login blocked by rate limit for this identifier.',
        'captcha_failed' => 'Security challenge answer did not match the active login captcha.',
        'inactive_account' => 'Account exists and password matched, but the user status is not allowed to sign in.',
        'password_mismatch' => 'Account exists, but the submitted password did not match the stored password hash.',
        'user_not_found' => 'No user account was found for the submitted email or username.',
        default => 'Login failed for an unclassified reason.',
    };
}

function login_audit_failure(?array $user, string $identifier, string $reason, array $details = []): void
{
    $detail = login_failure_detail($reason);
    audit(
        'login_failure',
        'users',
        $user ? (string)$user['id'] : null,
        [],
        array_merge([
            'identifier' => $identifier,
            'reason' => $reason,
            'failure_detail' => $detail,
            'reason_summary' => $detail,
            'status' => $user['status'] ?? 'not_found',
            'ip' => client_ip(),
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'next_step' => login_failure_notice($reason),
        ], $details),
        $user ? (int)$user['id'] : null
    );
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validateCsrf();
    $intent = (string)($_POST['intent'] ?? 'password');

    if ($intent === 'mfa') {
        $pendingMfa = is_array($_SESSION['pending_mfa'] ?? null) ? $_SESSION['pending_mfa'] : null;
        $code = trim((string)($_POST['mfa_code'] ?? ''));

        if (!$pendingMfa || !SecurityService::verifyMfaCode((int)$pendingMfa['user']['id'], $code)) {
            $errors[] = 'Invalid login details.';
            audit('mfa_failure', 'users', isset($pendingMfa['user']['id']) ? (string)$pendingMfa['user']['id'] : null, [], ['ip' => client_ip()], isset($pendingMfa['user']['id']) ? (int)$pendingMfa['user']['id'] : null);
        } else {
            audit('mfa_success', 'users', (string)$pendingMfa['user']['id'], [], ['ip' => client_ip()], (int)$pendingMfa['user']['id']);
            login_complete_session($pendingMfa['user'], $pendingMfa['roles'], $pendingMfa['intended_url'] ?? null);
        }
    } else {
        $intendedUrl = $_SESSION['intended_url'] ?? null;
        unset($_SESSION['intended_url']);
        login_clear_auth_session();
        $pendingMfa = null;

        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $identifier = $email !== '' ? $email : client_ip();
        $limit = SecurityService::rateLimitStatus('login', $identifier);
        $captchaRequired = (bool)$limit['captcha'];

        if ($limit['blocked']) {
            $errors[] = login_failure_notice('rate_limited');
            login_audit_failure(null, $identifier, 'rate_limited', ['locked_until' => $limit['locked_until']]);
        } elseif ($captchaRequired && !SecurityService::verifyCaptcha('login', (string)($_POST['captcha_answer'] ?? ''))) {
            SecurityService::recordFailure('login', $identifier);
            $errors[] = login_failure_notice('captcha_failed');
            login_audit_failure(null, $identifier, 'captcha_failed');
        } else {
            $user = db()->fetch(
                "SELECT id, wp_user_id, email, username, password_hash, display_name, status, email_verified_at, legacy_password_reset_at
                 FROM users
                 WHERE email = ? OR username = ?
                 LIMIT 1",
                [$email, $email]
            );

            $passwordMatches = $user && login_verify_password($password, (string)$user['password_hash']);
            $statusAllowed = $user && in_array((string)$user['status'], ['active', 'pending'], true);
            $valid = $user && $passwordMatches && $statusAllowed;

            if (!$valid) {
                SecurityService::recordFailure('login', $identifier);
                $reason = !$user ? 'user_not_found' : (!$passwordMatches ? 'password_mismatch' : 'inactive_account');
                $errors[] = login_failure_notice($reason);
                login_audit_failure($user ?: null, $identifier, $reason);
            } else {
                SecurityService::clearAttempts('login', $identifier);
                $roleCodes = login_user_role_codes((int)$user['id']);
                if (SecurityService::legacyPasswordResetRequired($user)) {
                    $token = SecurityService::createPasswordResetToken((int)$user['id'], 'legacy_reset');
                    try {
                        NotificationService::passwordReset($user, app_url('reset-password?token=' . urlencode($token)), true);
                    } catch (\Throwable $e) {
                        audit('legacy_password_reset_email_failed', 'users', (string)$user['id'], [], [
                            'email' => $user['email'],
                            'reason' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'next_step' => 'Ask the user to use Forgot password again or contact support if email delivery continues to fail.',
                        ], (int)$user['id']);
                        flash('error', 'We could not send the required reset email. Please try Forgot password again in a few minutes or contact support.');
                        redirect('login');
                    }
                    audit('legacy_password_reset_required', 'users', (string)$user['id'], [], ['email' => $user['email'], 'reason' => 'legacy_password_requires_reset'], (int)$user['id']);
                    flash('warning', 'For security, please reset your password before signing in. We sent a reset link to your email.');
                    redirect('login');
                }

                if (SecurityService::rolesNeedMfa($roleCodes)) {
                    SecurityService::createMfaChallenge((int)$user['id'], (string)$user['email'], (string)($user['display_name'] ?: $user['username']));
                    $_SESSION['pending_mfa'] = [
                        'user' => $user,
                        'roles' => $roleCodes,
                        'intended_url' => is_string($intendedUrl) ? $intendedUrl : null,
                        'created_at' => time(),
                    ];
                    audit('mfa_required', 'users', (string)$user['id'], [], ['roles' => $roleCodes], (int)$user['id']);
                    flash('info', 'Enter the security code sent to your email.');
                    redirect('login');
                }

                login_complete_session($user, $roleCodes, is_string($intendedUrl) ? $intendedUrl : null);
            }
        }
    }
}

if (!$pendingMfa) {
    $identifier = strtolower(trim((string)($_POST['email'] ?? '')));
    $identifier = $identifier !== '' ? $identifier : client_ip();
    $captchaRequired = SecurityService::captchaRequired('login', $identifier);
    $captchaChallenge = $captchaRequired ? SecurityService::ensureCaptchaChallenge('login') : null;
}

if ($errors !== []) {
    foreach ($errors as $error) {
        flash('error', $error);
    }
}

render('auth/login.php', [
    'title' => 'Login',
    'errors' => $errors,
    'pendingMfa' => $pendingMfa,
    'captchaRequired' => $captchaRequired,
    'captchaChallenge' => $captchaChallenge,
]);
