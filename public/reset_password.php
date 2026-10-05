<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/core/bootstrap.php';

use App\SecurityService;

$errors = [];
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$resetUser = SecurityService::passwordResetUser($token);

if (!$resetUser) {
    flash('error', 'This password reset link is invalid or expired.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $resetUser) {
    validateCsrf();
    $password = (string)($_POST['password'] ?? '');
    $confirmation = (string)($_POST['password_confirmation'] ?? '');

    foreach (SecurityService::passwordErrors($password) as $passwordError) {
        $errors[] = $passwordError;
    }
    if ($password !== $confirmation) {
        $errors[] = 'Password confirmation does not match.';
    }

    if ($errors === []) {
        try {
            SecurityService::completePasswordReset($token, $password);
            audit('password_reset_completed', 'users', (string)$resetUser['user_id'], [], ['email' => $resetUser['email']], (int)$resetUser['user_id']);
            flash('success', 'Password reset complete. Please sign in with your new password.');
            redirect('login');
        } catch (\Throwable $e) {
            error_log('Password reset failed: ' . $e->getMessage());
            $errors[] = 'Password reset could not be completed. Please try again.';
        }
    }
}

foreach ($errors as $error) {
    flash('error', $error);
}

render('auth/reset_password.php', [
    'title' => 'Reset Password',
    'token' => $token,
    'resetUser' => $resetUser,
]);
