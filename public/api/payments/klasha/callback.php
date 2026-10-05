<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/app/core/bootstrap.php';

use App\PaymentService;

header('Content-Type: application/json');

try {
    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $reference = trim((string)($payload['reference'] ?? $payload['tnxRef'] ?? $payload['txRef'] ?? $payload['klasha']['tnxRef'] ?? $payload['klasha']['txRef'] ?? ''));
    if ($reference === '') {
        throw new RuntimeException('Missing Klasha transaction reference.');
    }

    $method = db()->fetch("SELECT * FROM payment_methods WHERE code = 'klasha' LIMIT 1");
    $verified = PaymentService::verifyKlashaTransaction($reference, $method);
    $paid = false;

    if (($verified['ok'] ?? false) && is_array($verified['data'] ?? null)) {
        $paid = PaymentService::applyKlashaStatus($reference, $verified['data'], 'verification');
    } else {
        $callbackData = is_array($payload['klasha'] ?? null) ? $payload['klasha'] : $payload;
        PaymentService::applyKlashaStatus($reference, $callbackData, 'callback_unverified');
    }

    echo json_encode([
        'ok' => true,
        'paid' => $paid,
        'message' => $paid ? 'Klasha payment verified.' : 'Klasha response received and queued for verification.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'paid' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
