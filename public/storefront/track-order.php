<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\CartService;
use App\OrderPublicService;
use App\StorefrontContentService;
use App\StorefrontTemplateChunkService;
use App\StorefrontTemplateService;

$brand = app_branding();
$renderer = new StorefrontTemplateService(db());
$cart = new CartService();
$orderNumber = trim((string)($_GET['order'] ?? ''));
$record = $orderNumber !== '' ? (new OrderPublicService(db()))->find($orderNumber) : null;

render_layout('storefront', 'storefront/pages/track-order.php', [
    'storefrontTemplate' => new StorefrontTemplateChunkService('index.html'),
    'page' => 'track-order',
    'title' => 'Track Order | ' . $brand['name'],
    'metaDescription' => 'Track a Seller Africa order using the order number.',
    'products' => $renderer->products(12),
    'categories' => $renderer->categories(),
    'cartItems' => $renderer->cartProducts($cart->items()),
    'contentBlocks' => (new StorefrontContentService(db()))->all(),
    'cartCount' => $cart->count(),
    'orderNumber' => $orderNumber,
    'record' => $record,
]);
