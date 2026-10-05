<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/core/bootstrap.php';

use App\EmailService;
use App\SecurityService;

$errors = [];
$sent = false;
$captchaRequired = SecurityService::captchaRequired('forgot_password', client_ip());
$captchaChallenge = $captchaRequired ? SecurityService::ensureCaptchaChallenge('forgot_password') : null;

function forgot_password_send_direct(array $user, string $url): void {
    $brand = app_branding();
    $name = (string)($user['display_name'] ?: $user['username'] ?: 'there');
    $safeName = e($name);
    $safeUrl = e($url);
    $safeBrand = e((string)$brand['name']);
    $html = <<<HTML
<div style="font-family:Arial,sans-serif;line-height:1.6;color:#111;background:#f8f8f9;padding:24px">
  <div style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #ececec;border-radius:20px;padding:28px">
    <h1 style="margin:0 0 12px;font-size:24px;color:#111">Reset your password</h1>
    <p>Hello {$safeName},</p>
    <p>Use the secure link below to reset your {$safeBrand} password. The link expires in 1 hour.</p>
    <p><a href="{$safeUrl}" style="display:inline-block;background:#00684f;color:#fff;text-decoration:none;padding:13px 20px;border-radius:8px;font-weight:700">Reset Password</a></p>
    <p style="font-size:13px;color:#666">If the button does not work, open this URL: {$safeUrl}</p>
  </div>
</div>
HTML;

    EmailService::send(
        [['email' => (string)$user['email'], 'name' => $name]],
        'Your ' . $brand['name'] . ' password reset link',
        $html
    );
}

function forgot_password_notice(string $reason): string
{
    return match ($reason) {
        'rate_limited' => 'Too many password reset requests. Please wait a few minutes before trying again.',
        'captcha_failed' => 'The security answer was incorrect. Please answer the security question and try again.',
        'invalid_email' => 'Enter a valid email address, for example name@example.com.',
        'email_send_failed' => 'We could not send the reset email right now. Check the email address and try again, or contact support if this continues.',
        default => 'Password reset could not be started. Please try again later or contact support if this continues.',
    };
}

function forgot_password_audit(string $action, string $email, string $reason, ?array $user = null, array $details = []): void
{
    audit(
        $action,
        'users',
        $user ? (string)$user['id'] : null,
        [],
        array_merge([
            'email' => $email,
            'reason' => $reason,
            'next_step' => forgot_password_notice($reason),
        ], $details),
        $user ? (int)$user['id'] : null
    );
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validateCsrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $identifier = $email !== '' ? $email : client_ip();
    $rateStatus = SecurityService::rateLimitStatus('forgot_password', $identifier);
    $captchaRequired = (bool)$rateStatus['captcha'];

    if ($rateStatus['blocked']) {
        $errors[] = forgot_password_notice('rate_limited');
        forgot_password_audit('password_reset_rate_limited', $email, 'rate_limited', null, ['locked_until' => $rateStatus['locked_until'] ?? null]);
    } elseif ($captchaRequired && !SecurityService::verifyCaptcha('forgot_password', (string)($_POST['captcha_answer'] ?? ''))) {
        SecurityService::recordFailure('forgot_password', $identifier);
        $errors[] = forgot_password_notice('captcha_failed');
        forgot_password_audit('password_reset_captcha_failed', $email, 'captcha_failed');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        SecurityService::recordFailure('forgot_password', $identifier);
        $errors[] = forgot_password_notice('invalid_email');
        forgot_password_audit('password_reset_invalid_email', $email, 'invalid_email');
    } else {
        try {
            SecurityService::ensureSchema();
            SecurityService::recordFailure('forgot_password', $identifier);
            $user = db()->fetch(
                'SELECT id, email, username, display_name FROM users WHERE email = ? AND status <> "deleted" LIMIT 1',
                [$email]
            );

            if ($user) {
                $token = SecurityService::createPasswordResetToken((int)$user['id']);
                try {
                    forgot_password_send_direct($user, app_url('reset-password?token=' . urlencode($token)));
                } catch (\Throwable $e) {
                    forgot_password_audit('password_reset_email_failed', $email, 'email_send_failed', $user, [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                    throw $e;
                }
                audit('password_reset_requested', 'users', (string)$user['id'], [], [
                    'email' => $email,
                    'reason' => 'reset_link_sent_directly',
                    'next_step' => 'User should open the email reset link within 1 hour.',
                ], (int)$user['id']);
            } else {
                forgot_password_audit('password_reset_unknown_email', $email, 'user_not_found', null, [
                    'next_step' => 'Tell the user to check the email address or register a new account if needed.',
                ]);
            }

            $sent = true;
            flash('success', 'If that email exists, we have sent a password reset link.');
        } catch (\Throwable $e) {
            error_log('Forgot password failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            forgot_password_audit('password_reset_exception', $email, 'exception', isset($user) && is_array($user) ? $user : null, [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'next_step' => 'Check mail configuration and password reset token storage, then ask the user to try again.',
            ]);
            $errors[] = forgot_password_notice('email_send_failed');
        }
    }

    $captchaRequired = SecurityService::captchaRequired('forgot_password', $identifier);
    $captchaChallenge = $captchaRequired ? SecurityService::ensureCaptchaChallenge('forgot_password') : null;
}

foreach ($errors as $error) {
    flash('error', $error);
}

render('auth/forgot_password.php', [
    'title' => 'Forgot Password',
    'sent' => $sent,
    'captchaRequired' => $captchaRequired,
    'captchaChallenge' => $captchaChallenge,
]);
