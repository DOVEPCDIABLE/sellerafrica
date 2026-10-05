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

render_layout('storefront-home', 'storefront/pages/farms-near-me.php', [
    'page' => 'farms-near-me',
    'title' => 'Black Owned Farms Near You | FreshRoots',
    'metaDescription' => 'Find FreshRoots farmers near you on an interactive map.',
    'cartCount' => $cart->count(),
    'categories' => FarmFreshDiscoveryService::CATEGORIES,
    'farmers' => FarmFreshDiscoveryService::farmers($filters, 250),
    'filters' => $filters,
]);
