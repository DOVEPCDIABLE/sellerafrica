<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/core/bootstrap.php';

use App\CheckoutService;
use App\StorefrontTemplateService;

header('Content-Type: application/json');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('Invalid shipping estimate request.');
    }

    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = max(1, min(99, (int)($_POST['quantity'] ?? 1)));
    if ($productId <= 0) {
        throw new RuntimeException('Select a valid product.');
    }

    $currency = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($_POST['currency'] ?? 'USD')) ?: 'USD', 0, 3));
    $quote = (new CheckoutService(db()))->quote([$productId => $quantity], $currency, [
        'country_code' => $_POST['country_code'] ?? '',
        'state' => $_POST['state'] ?? '',
        'postcode' => $_POST['postcode'] ?? '',
        'city' => $_POST['city'] ?? '',
        'address_line1' => $_POST['address_line1'] ?? '',
        'address_line2' => $_POST['address_line2'] ?? '',
    ]);

    $shipping = StorefrontTemplateService::money((float)$quote['shipping'], (string)$quote['currency']);
    $snapshot = is_array($quote['shipping_rate_snapshot'] ?? null) ? $quote['shipping_rate_snapshot'] : [];
    $source = (string)($snapshot['source'] ?? 'shipping');

    echo json_encode([
        'ok' => true,
        'shipping' => $shipping,
        'raw' => $quote,
        'message' => 'Estimated shipping: ' . $shipping . ($source === 'aramex' ? ' via Aramex.' : '.'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code($e instanceof RuntimeException ? 422 : 500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
