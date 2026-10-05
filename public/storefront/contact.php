<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\CartService;

$cart = new CartService();
$brand = app_branding();

render_layout('storefront-home', 'storefront/pages/contact.php', [
    'page' => 'contact',
    'title' => 'Contact Us | ' . $brand['name'],
    'metaDescription' => 'Contact ' . $brand['name'] . ' for customer support, vendor onboarding, partnerships, logistics, and marketplace enquiries.',
    'cartCount' => $cart->count(),
]);
