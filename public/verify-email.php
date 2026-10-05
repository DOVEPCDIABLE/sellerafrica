<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/core/bootstrap.php';

$token = trim((string)($_GET['token'] ?? ''));

if ($token === '' || strlen($token) < 40) {
    flash('error', 'Verification link is invalid.');
    redirect('login.php');
}

try {
    if (!table_exists('email_verification_tokens')) {
        throw new \RuntimeException('Verification table is not available.');
    }

    $row = db()->fetch(
        "SELECT evt.id, evt.user_id, evt.sent_to, evt.expires_at, evt.used_at, u.email, u.status, u.email_verified_at
         FROM email_verification_tokens evt
         INNER JOIN users u ON u.id = evt.user_id
         WHERE evt.token_hash = ? AND evt.purpose = 'registration'
         LIMIT 1",
        [hash('sha256', $token)]
    );

    if (!$row) {
        throw new \RuntimeException('Verification link was not found.');
    }
    if ($row['used_at'] !== null) {
        flash('info', 'This email has already been confirmed. Please sign in.');
        redirect('login.php');
    }
    if (strtotime((string)$row['expires_at']) < time()) {
        throw new \RuntimeException('Verification link has expired. Please register again or contact support.');
    }

    db()->beginTransaction();
    db()->query('UPDATE users SET status = "active", email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?', [sql_now(), (int)$row['user_id']]);
    db()->query('UPDATE email_verification_tokens SET used_at = ? WHERE id = ?', [sql_now(), (int)$row['id']]);
    db()->commit();

    audit('email_verified', 'users', (string)$row['user_id'], [], ['email' => $row['email']], (int)$row['user_id']);
    flash('success', 'Email confirmed. You can now sign in.');
} catch (\Throwable $e) {
    if (isset($row) && db()->pdo()->inTransaction()) {
        db()->rollBack();
    }
    flash('error', $e->getMessage());
}

redirect('login.php');
