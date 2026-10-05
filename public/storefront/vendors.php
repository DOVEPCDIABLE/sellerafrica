<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\CartService;
use App\PublicVendorService;

$vendorService = new PublicVendorService();
$cart = new CartService();
$brand = app_branding();
$slug = trim((string)($_GET['slug'] ?? ''));

if ($slug === '' && !headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

$vendor = $slug !== '' ? $vendorService->vendor($slug) : null;
$vendorNotFound = $slug !== '' && !$vendor;
$vendorsPerPage = 25;
$vendorTotal = $slug === '' ? $vendorService->vendorCount() : 0;
$activeVendorTotal = $vendorService->activeVendorCount();
$allVendorTotal = $slug === '' ? $vendorService->totalVendorCount() : $activeVendorTotal;
$vendorTotalPages = max(1, (int)ceil($vendorTotal / $vendorsPerPage));
$vendorPage = min(max(1, (int)($_GET['page'] ?? 1)), $vendorTotalPages);

if ($vendorNotFound) {
    http_response_code(404);
}

render_layout('storefront-home', 'storefront/pages/vendors.php', [
    'page' => 'vendors',
    'title' => ($vendor ? $vendor['name'] . ' | ' : 'Our Vendors | ') . $brand['name'],
    'metaDescription' => $vendor
        ? 'Shop products and view details from ' . $vendor['name'] . ' on Seller Africa.'
        : 'Browse verified Seller Africa vendors and discover the African and Caribbean Marketplace.',
    'vendors' => $slug === '' ? $vendorService->vendors($vendorsPerPage, ($vendorPage - 1) * $vendorsPerPage) : [],
    'vendorTotal' => $vendorTotal,
    'activeVendorTotal' => $activeVendorTotal,
    'allVendorTotal' => $allVendorTotal,
    'vendorsPerPage' => $vendorsPerPage,
    'vendorPage' => $vendorPage,
    'vendorTotalPages' => $vendorTotalPages,
    'vendor' => $vendor,
    'vendorNotFound' => $vendorNotFound,
    'vendorProducts' => [],
    'vendorProductsEndpoint' => $vendor ? app_url('api/storefront/vendor-products.php?vendor_id=' . (int)$vendor['id'] . '&limit=5') : '',
    'vendorsEndpoint' => app_url('api/storefront/vendors.php'),
    'vendorContactEndpoint' => app_url('api/storefront/vendor-contact.php'),
    'cartCount' => $cart->count(),
]);
