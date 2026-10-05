<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\CartService;
use App\FarmFreshDiscoveryService;
use App\FarmFreshMembershipService;

FarmFreshMembershipService::requireAccess();
$brand = app_branding();
$cart = new CartService();
$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'category' => trim((string)($_GET['category'] ?? '')),
];

render_layout('storefront-home', 'storefront/pages/farms.php', [
    'page' => 'farms',
    'title' => 'FreshRoots Farmers & Produce | ' . $brand['name'],
    'metaDescription' => 'Browse FreshRoots farmers, farms, and produce on Seller Africa.',
    'cartCount' => $cart->count(),
    'categories' => FarmFreshDiscoveryService::CATEGORIES,
    'farmers' => FarmFreshDiscoveryService::farmers($filters, 120),
    'produce' => FarmFreshDiscoveryService::produce($filters, 48),
    'filters' => $filters,
]);
