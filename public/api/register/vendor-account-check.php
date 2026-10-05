<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/app/core/bootstrap.php';

header('Content-Type: application/json');

function account_check_phone_variants(string $phone): array {
    $phone = trim($phone);
    if ($phone === '') {
        return [];
    }

    $clean = str_replace(["\xC2\xA0", ' ', '-', '(', ')', '.'], '', $phone);
    if (str_starts_with($clean, '00')) {
        $clean = '+' . substr($clean, 2);
    }

    $digits = preg_replace('/\D+/', '', $clean) ?: '';
    if ($digits === '') {
        return [$phone];
    }

    $variants = [$phone, $clean, $digits];
    if (str_starts_with($clean, '+')) {
        $variants[] = '+' . $digits;
    }
    if (str_starts_with($digits, '234')) {
        $variants[] = '+' . $digits;
        $variants[] = '0' . substr($digits, 3);
        $variants[] = substr($digits, 3);
    }
    if (str_starts_with($digits, '0') && strlen($digits) >= 10) {
        $variants[] = '+234' . substr($digits, 1);
        $variants[] = '234' . substr($digits, 1);
    }

    return array_values(array_unique(array_filter($variants, static fn (string $item): bool => trim($item) !== '')));
}

try {
    $submittedToken = (string)($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($submittedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        audit('vendor_registration_account_check_csrf_failed', 'users', null, [], [
            'email' => strtolower(trim((string)($_POST['email'] ?? ''))),
            'reason' => 'csrf_token_invalid_or_expired',
            'next_step' => 'Ask the user to refresh the vendor registration page and try again.',
        ]);
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'csrf' => true,
            'message' => 'Your registration session expired. Please refresh the page and try again.',
        ]);
        exit;
    }

    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $matches = [];

    $existingVendor = null;

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $user = db()->fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
        if ($user) {
            $matches[] = 'email';
            $existingVendor = db()->fetch('SELECT id, status, kyc_status FROM vendors WHERE user_id = ? LIMIT 1', [(int)$user['id']]);
        }
    }

    $phoneVariants = account_check_phone_variants($phone);
    if ($phoneVariants !== []) {
        $user = db()->fetch('SELECT id FROM users WHERE phone IN (' . implode(',', array_fill(0, count($phoneVariants), '?')) . ') LIMIT 1', $phoneVariants);
        if ($user) {
            $matches[] = 'phone number';
        }
    }

    echo json_encode([
        'ok' => true,
        'exists' => $matches !== [],
        'matches' => array_values(array_unique($matches)),
        'login_url' => app_url('login'),
        'message' => $matches === []
            ? ''
            : ($existingVendor
                ? 'A vendor application already exists for this email. Please sign in to continue from your vendor dashboard.'
                : 'This ' . implode(' and ', array_values(array_unique($matches))) . ' is already registered. Please sign in, then return to Apply as a Vendor to complete the full application.'),
    ]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'We could not check this account right now. Please try again.']);
}
