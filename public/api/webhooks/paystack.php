<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/core/bootstrap.php';

use App\PaymentService;
use App\VendorSubscriptionService;
use App\ManageStoreService;
use App\VisibilityBoostService;

header('Content-Type: application/json');

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid Paystack webhook payload.']);
    exit;
}

$method = db()->fetch("SELECT * FROM payment_methods WHERE code = 'paystack' LIMIT 1");
$settings = PaymentService::paystackSettings($method ?: null);
$secret = trim((string)($settings['webhook_secret'] ?? $settings['secret_key'] ?? ''));
$signature = (string)($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '');
if ($secret !== '' && $signature !== '') {
    $expected = hash_hmac('sha512', $raw, $secret);
    if (!hash_equals($expected, $signature)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Invalid Paystack signature.']);
        exit;
    }
}

$data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
$reference = trim((string)($data['reference'] ?? ''));
if (str_starts_with($reference, \App\PriorityPaystackService::PREFIX)) {
    if ($secret === '' || $signature === '') {http_response_code(401);echo json_encode(['ok'=>false]);exit;}
    try {\App\PriorityPaystackService::verify($reference);echo json_encode(['ok'=>true]);}
    catch (\Throwable $e) {error_log('Priority Paystack webhook: '.$e->getMessage());http_response_code(500);echo json_encode(['ok'=>false]);}
    exit;
}

if (str_starts_with($reference, \App\DistributorPaymentService::PREFIX)) {
    if ($secret === '' || $signature === '') { http_response_code(401); echo json_encode(['ok'=>false]); exit; }
    try {
        \App\DistributorPaymentService::verify($reference);
        echo json_encode(['ok'=>true]);
    } catch (\Throwable $e) {
        error_log('Retail placement webhook: ' . $e->getMessage());
        http_response_code(500); echo json_encode(['ok'=>false]);
    }
    exit;
}

if (str_starts_with($reference, \App\VendorRegistrationPaymentService::PREFIX)) {
    if ($secret === '' || $signature === '') {
        http_response_code(401);
        echo json_encode(['ok'=>false]);
        exit;
    }
    try {
        \App\VendorRegistrationPaymentService::verify($reference);
        echo json_encode(['ok'=>true]);
    } catch (\Throwable $e) {
        error_log('Registration payment webhook failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok'=>false]);
    }
    exit;
}

if ($reference !== '' && str_starts_with($reference, 'SA-VENDOR-PAYSTACK-')) {
    VendorSubscriptionService::applyPaystackCheckout($reference);
    echo json_encode(['ok' => true]);
    exit;
}

if ($reference !== '' && str_starts_with($reference, 'SA-ORDER-PAYSTACK-')) {
    PaymentService::applyPaystackStatus($reference, $payload, 'webhook');
    echo json_encode(['ok' => true]);
    exit;
}

if ($reference !== '' && str_starts_with($reference, 'SA-MANAGED-STORE-PAYSTACK-')) {
    if ($secret === '' || $signature === '') {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'A verified Paystack signature is required.']);
        exit;
    }
    ManageStoreService::applyPaystackStatus($reference, $payload, 'webhook');
    echo json_encode(['ok' => true]);
    exit;
}

if ($reference !== '' && str_starts_with($reference, 'SA-VISIBILITY-PAYSTACK-')) {
    VisibilityBoostService::applyPaystackStatus($reference, $payload, 'webhook');
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => true, 'handled' => false]);
