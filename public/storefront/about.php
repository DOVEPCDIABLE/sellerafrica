<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\CartService;

$cart = new CartService();
$brand = app_branding();

render_layout('storefront-home', 'storefront/pages/about.php', [
    'page' => 'about',
    'title' => 'Our Story | ' . $brand['name'],
    'metaDescription' => $brand['name'] . ' connects African and Caribbean vendors with diaspora customers through marketplace technology, logistics, distribution, and global market access.',
    'cartCount' => $cart->count(),
]);
