<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/core/bootstrap.php';

$errors = [];
$schemaReady = false;

try {
    install_schema_if_needed();
    $schemaReady = true;
} catch (\Throwable $e) {
    $errors[] = 'Database schema could not be prepared: ' . $e->getMessage();
}

if ($schemaReady && super_admin_exists()) {
    redirect('login.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $schemaReady) {
    validateCsrf();

    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $username = trim((string)($_POST['username'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['password_confirmation'] ?? '');

    if ($firstName === '') {
        $errors[] = 'First name is required.';
    }

    if ($lastName === '') {
        $errors[] = 'Last name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }

    if ($username === '') {
        $errors[] = 'Username is required.';
    }

    if (strlen($password) < 5) {
        $errors[] = 'Password must be at least 5 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    if ($errors === []) {
        try {
            $db = db();
            $db->beginTransaction();

            $displayName = trim($firstName . ' ' . $lastName);
            $db->query(
                "INSERT INTO users
                (email, username, password_hash, first_name, last_name, display_name, phone, status, email_verified_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)",
                [
                    $email,
                    $username,
                    password_hash($password, PASSWORD_DEFAULT),
                    $firstName,
                    $lastName,
                    $displayName,
                    $phone !== '' ? $phone : null,
                    sql_now(),
                ]
            );

            $userId = (int) $db->lastInsertId();
            $role = $db->fetch("SELECT id FROM roles WHERE code = 'super_admin' LIMIT 1");

            if (!$role) {
                throw new \RuntimeException('Super admin role is missing from roles table.');
            }

            $db->query(
                "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
                [$userId, (int)$role['id']]
            );

            $db->query(
                "INSERT INTO settings (scope, setting_key, setting_value) VALUES
                ('system', 'onboarding_completed_at', JSON_QUOTE(?)),
                ('system', 'first_super_admin_user_id', JSON_QUOTE(?))",
                [sql_now(), (string)$userId]
            );

            $db->commit();

            $_SESSION['user_id'] = $userId;
            $_SESSION['user_email'] = $email;
            $_SESSION['display_name'] = $displayName;
            $_SESSION['roles'] = ['super_admin'];

            audit(
                'first_super_admin_created',
                'users',
                (string)$userId,
                [],
                ['email' => $email, 'roles' => ['super_admin']],
                $userId
            );
            audit('onboarding_completed', 'system', null, [], ['completed_at' => sql_now()], $userId);

            flash('success', 'First super admin created. Welcome to the control room.');
            redirect('dashboard.php');
        } catch (\Throwable $e) {
            if (isset($db) && $db->pdo()->inTransaction()) {
                $db->rollBack();
            }

            $errors[] = 'Could not create the super admin: ' . $e->getMessage();
        }
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        flash('error', $error);
    }
}

render('auth/onboarding.php', [
    'title' => 'Create First Super Admin',
    'errors' => $errors,
]);
