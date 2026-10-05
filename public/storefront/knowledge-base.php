<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\CartService;

$cart = new CartService();
$brand = app_branding();

render_layout('storefront-home', 'storefront/pages/knowledge-base.php', [
    'page' => 'knowledge-base',
    'title' => 'Knowledge Base | ' . $brand['name'],
    'metaDescription' => 'Seller Africa help center, FAQs, vendor guides, affiliate guides, shipping support, and trust resources.',
    'cartCount' => $cart->count(),
]);
