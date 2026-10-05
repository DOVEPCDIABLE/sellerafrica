<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/core/bootstrap.php';

use App\CartService;
use App\CheckoutService;
use App\StorefrontTemplateService;

header('Content-Type: application/json');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('Invalid quote request.');
    }

    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($submittedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Your checkout session expired.']);
        exit;
    }

    $cart = new CartService();
    $quote = (new CheckoutService(db()))->quote($cart->items(), (string)($_POST['currency'] ?? 'USD'), [
        'country_code' => $_POST['country_code'] ?? 'US',
        'state' => $_POST['state'] ?? '',
        'postcode' => $_POST['postcode'] ?? '',
        'city' => $_POST['city'] ?? '',
        'address_line1' => $_POST['address_line1'] ?? '',
        'address_line2' => $_POST['address_line2'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'email' => $_POST['email'] ?? '',
    ]);

    echo json_encode([
        'ok' => true,
        'subtotal' => StorefrontTemplateService::money((float)$quote['subtotal'], (string)$quote['currency']),
        'shipping' => StorefrontTemplateService::money((float)$quote['shipping'], (string)$quote['currency']),
        'tax' => StorefrontTemplateService::money((float)$quote['tax'], (string)$quote['currency']),
        'total' => StorefrontTemplateService::money((float)$quote['total'], (string)$quote['currency']),
        'raw' => $quote,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code($e instanceof RuntimeException ? 422 : 500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
