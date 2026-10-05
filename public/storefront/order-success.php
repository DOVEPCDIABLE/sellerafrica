<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\CartService;
use App\OrderPublicService;
use App\PaymentService;
use App\StorefrontContentService;
use App\StorefrontTemplateChunkService;
use App\StorefrontTemplateService;

$brand = app_branding();
$renderer = new StorefrontTemplateService(db());
$cart = new CartService();
$orderNumber = trim((string)($_GET['order'] ?? ''));
$stripeSessionId = trim((string)($_GET['session_id'] ?? ''));

if ($stripeSessionId !== '') {
    try {
        PaymentService::verifyStripeCheckoutSession($stripeSessionId);
    } catch (Throwable $e) {
        error_log('Stripe checkout refresh failed: ' . $e->getMessage());
    }
}

$order = null;
$items = [];
$payment = null;
$timeline = [];

if ($orderNumber !== '') {
    $record = (new OrderPublicService(db()))->find($orderNumber);
    $order = $record['order'] ?? null;
    $items = $record['items'] ?? [];
    $payment = $record['payment'] ?? null;
    $timeline = $record['timeline'] ?? [];
}

if ($order && (string)($order['payment_status'] ?? '') !== 'paid') {
    header('Location: ' . app_url('track-order?order=' . rawurlencode((string)$order['order_number'])));
    exit;
}

if ($order) {
    $cart->clear();
}

render_layout('storefront', 'storefront/pages/order-success.php', [
    'storefrontTemplate' => new StorefrontTemplateChunkService('index.html'),
    'page' => 'order-success',
    'title' => ($order ? 'Order Confirmed' : 'Order Not Found') . ' | ' . $brand['name'],
    'metaDescription' => 'Review your Seller Africa order confirmation and payment summary.',
    'products' => $renderer->products(12),
    'categories' => $renderer->categories(),
    'cartItems' => $renderer->cartProducts($cart->items()),
    'contentBlocks' => (new StorefrontContentService(db()))->all(),
    'cartCount' => $cart->count(),
    'order' => $order,
    'orderItems' => $items,
    'payment' => $payment,
    'timeline' => $timeline,
]);
