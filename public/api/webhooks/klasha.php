<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/core/bootstrap.php';

use App\PaymentService;
use App\FrozenMarketService;
use App\ManageStoreService;
use App\FarmFreshMembershipService;

header('Content-Type: application/json');

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid Klasha webhook payload.']);
    exit;
}

$event = (string)($payload['event'] ?? '');
$data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
$reference = trim((string)($data['tnxRef'] ?? $data['txRef'] ?? $data['reference'] ?? ''));

if ($reference === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Missing Klasha webhook reference.']);
    exit;
}

$method = db()->fetch("SELECT * FROM payment_methods WHERE code = 'klasha' LIMIT 1");
$verified = PaymentService::verifyKlashaTransaction($reference, $method);

if (str_starts_with($reference, 'SA-FROZEN-KLASHA-')) {
    $frozen = new FrozenMarketService(db());
    $frozen->ensureSchema();
    if (($verified['ok'] ?? false) && is_array($verified['data'] ?? null)) {
        $frozen->applyEarlyBirdKlashaStatus($reference, $verified['data'], 'webhook_verification');
    } else {
        $frozen->applyEarlyBirdKlashaStatus($reference, ['event' => $event, 'data' => $data], 'webhook_unverified');
    }
    echo json_encode(['ok' => true]);
    exit;
}

if (str_starts_with($reference, 'SA-MANAGED-STORE-KLASHA-')) {
    if (($verified['ok'] ?? false) && is_array($verified['data'] ?? null)) {
        ManageStoreService::applyKlashaStatus($reference, $verified['data'], 'webhook_verification');
    } else {
        ManageStoreService::applyKlashaStatus($reference, ['event' => $event, 'data' => $data], 'webhook_unverified');
    }
    echo json_encode(['ok' => true]);
    exit;
}

if (str_starts_with($reference, 'SA-FARM-FRESH-KLASHA-')) {
    if (($verified['ok'] ?? false) && is_array($verified['data'] ?? null)) {
        FarmFreshMembershipService::applyKlashaStatus($reference, $verified['data'], 'webhook_verification');
    } else {
        FarmFreshMembershipService::applyKlashaStatus($reference, ['event' => $event, 'data' => $data], 'webhook_unverified');
    }
    echo json_encode(['ok' => true]);
    exit;
}

if (($verified['ok'] ?? false) && is_array($verified['data'] ?? null)) {
    PaymentService::applyKlashaStatus($reference, $verified['data'], 'webhook_verification');
} else {
    PaymentService::applyKlashaStatus($reference, ['event' => $event, 'data' => $data], 'webhook_unverified');
}

echo json_encode(['ok' => true]);
