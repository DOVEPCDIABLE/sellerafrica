<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/core/bootstrap.php';

use App\StripeWebhookService;

header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {http_response_code(405);header('Allow: POST');echo json_encode(['ok'=>false]);exit;}

$payload = file_get_contents('php://input') ?: '';
$signature = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

try {
    $event=StripeWebhookService::authenticate($payload,$signature,($_GET['wc-api'] ?? '')==='wc_stripe');
} catch (\Throwable $e) {
    error_log('Stripe webhook authentication failed: '.$e->getMessage());
    http_response_code(400);echo json_encode(['ok'=>false,'message'=>'Unable to verify Stripe event.']);exit;
}
try {
    StripeWebhookService::ensureSchema();
    $processed=StripeWebhookService::process($event);
    echo json_encode(['ok'=>true,'duplicate'=>!$processed]);
} catch (\Throwable $e) {
    error_log('Stripe webhook failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Stripe webhook could not be processed.']);
}
