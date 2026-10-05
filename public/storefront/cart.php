<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\CartService;
use App\StorefrontContentService;
use App\StorefrontTemplateService;

$cart = new CartService();
$renderer = new StorefrontTemplateService(db());
$items = $renderer->cartProducts($cart->items());
$brand = app_branding();

echo $renderer->render('checkout.html', [
    'page' => 'cart',
    'title' => 'Cart | ' . $brand['name'],
    'cartItems' => $items,
    'products' => $renderer->products(12),
    'categories' => $renderer->categories(),
    'contentBlocks' => (new StorefrontContentService(db()))->all(),
    'cartCount' => $cart->count(),
]);
