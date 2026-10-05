<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/core/bootstrap.php';

use App\ManageStoreService;
use App\PaymentService;

header('Content-Type: application/json');

try {
    validateCsrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create_klasha') {
        echo json_encode([
            'ok' => true,
            'klasha' => ManageStoreService::createKlashaAnnualConfig(
                (string)($_POST['name'] ?? ''),
                (string)($_POST['email'] ?? ''),
                (string)($_POST['phone'] ?? ''),
                (string)($_POST['plan'] ?? 'annual')
            ),
        ]);
        exit;
    }

    if ($action === 'create_paystack') {
        echo json_encode([
            'ok' => true,
            'redirect_url' => ManageStoreService::createPaystackAnnualCheckout(
                (string)($_POST['name'] ?? ''),
                (string)($_POST['email'] ?? ''),
                (string)($_POST['phone'] ?? ''),
                (string)($_POST['plan'] ?? 'annual')
            ),
        ]);
        exit;
    }

    if ($action === 'klasha_callback') {
        $reference = trim((string)($_POST['reference'] ?? $_POST['txRef'] ?? $_POST['tnxRef'] ?? ''));
        if ($reference === '') {
            throw new RuntimeException('Missing Klasha transaction reference.');
        }

        $method = db()->fetch("SELECT * FROM payment_methods WHERE code = 'klasha' LIMIT 1");
        $verified = PaymentService::verifyKlashaTransaction($reference, $method);
        $paid = ($verified['ok'] ?? false) && is_array($verified['data'] ?? null)
            ? ManageStoreService::applyKlashaStatus($reference, $verified['data'], 'verification')
            : ManageStoreService::applyKlashaStatus($reference, $_POST, 'callback_unverified');

        echo json_encode([
            'ok' => true,
            'paid' => $paid,
            'message' => $paid ? 'Your service payment is confirmed.' : 'Payment verification is pending. Your service has not been activated yet.',
        ]);
        exit;
    }

    throw new RuntimeException('Unsupported payment action.');
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
