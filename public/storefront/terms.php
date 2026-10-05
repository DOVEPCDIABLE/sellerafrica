<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\CartService;
use App\StorefrontContentService;
use App\StorefrontTemplateChunkService;
use App\StorefrontTemplateService;

$renderer = new StorefrontTemplateService(db());
$cart = new CartService();
$brand = app_branding();

render_layout('storefront', 'storefront/pages/terms.php', [
    'storefrontTemplate' => new StorefrontTemplateChunkService('index.html'),
    'page' => 'terms',
    'title' => 'Terms & Conditions | ' . $brand['name'],
    'metaDescription' => 'Seller Africa terms and conditions for using the marketplace, ordering, and vendor services.',
    'products' => $renderer->products(24),
    'hotProducts' => $renderer->products(12, '', 'food-groceries'),
    'popularProducts' => $renderer->products(24),
    'categories' => $renderer->categories(),
    'cartItems' => $renderer->cartProducts($cart->items()),
    'contentBlocks' => (new StorefrontContentService(db()))->all(),
    'cartCount' => $cart->count(),
]);
