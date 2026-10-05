<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/core/bootstrap.php';

use App\NotificationService;

header('Content-Type: application/json');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'message' => 'Invalid request method.']);
        return;
    }

    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($submittedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Security check failed. Please refresh and try again.']);
        return;
    }

    $vendorId = max(0, (int)($_POST['vendor_id'] ?? 0));
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    if ($vendorId <= 0) {
        throw new RuntimeException('Please choose a valid vendor.');
    }
    if ($name === '' || $email === '' || $message === '') {
        throw new RuntimeException('Your name, email, and message are required.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Please enter a valid email address.');
    }

    $vendor = db()->fetch(
        "SELECT id, store_name, store_email
         FROM vendors
         WHERE id = ? AND status = 'active' AND store_email IS NOT NULL AND store_email <> ''
         LIMIT 1",
        [$vendorId]
    );
    if (!$vendor) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'This vendor cannot receive messages right now.']);
        return;
    }

    $cleanSubject = $subject !== '' ? $subject : 'New customer message from Seller Africa';
    $body = '<p>Hello ' . \e((string)$vendor['store_name']) . ',</p>'
        . '<p>You received a new message from your public Seller Africa vendor profile.</p>'
        . '<p><strong>Name:</strong> ' . \e($name) . '<br>'
        . '<strong>Email:</strong> ' . \e($email) . ($phone !== '' ? '<br><strong>Phone:</strong> ' . \e($phone) : '') . '</p>'
        . '<p><strong>Subject:</strong> ' . \e($cleanSubject) . '</p>'
        . '<p><strong>Message:</strong><br>' . nl2br(\e($message)) . '</p>';

    NotificationService::enqueueEmail(
        [['email' => (string)$vendor['store_email'], 'name' => (string)$vendor['store_name']]],
        'Vendor enquiry: ' . $cleanSubject,
        'New vendor enquiry',
        $body,
        ['tone' => 'vendor']
    );

    echo json_encode([
        'ok' => true,
        'message' => 'Your message has been queued for this vendor.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage() ?: 'Unable to send your message.',
    ]);
}
