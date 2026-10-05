<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\CartService;
use App\AuthService;
use App\StorefrontContentService;
use App\StorefrontTemplateService;

$renderer = new StorefrontTemplateService(db());
$slug = trim((string)($_GET['slug'] ?? ''));
$product = $slug !== '' ? $renderer->productBySlug($slug) : null;
$brand = app_branding();
$user = class_exists(AuthService::class) ? AuthService::user() : null;

if ($slug === '' || !$product) {
    http_response_code(404);
}

if ($product) {
    $product['reviews'] = $renderer->productReviews((int)$product['id']);
    $product['reviewEligibility'] = $renderer->reviewEligibility((int)$product['id'], $user ? (int)$user['id'] : null);
}

$productName = (string)($product['name'] ?? 'Product');
$productImage = (string)($product['image'] ?? ($product['media'][0] ?? $brand['logo']));
$productUrl = (string)($product['url'] ?? app_url('product/' . urlencode($slug)));
$relatedProducts = [];
if ($product) {
    $relatedCategory = trim((string)($product['primaryCategorySlug'] ?? ''));
    if ($relatedCategory !== '') {
        $relatedProducts = $renderer->products(8, '', $relatedCategory, ['exclude_ids' => [(int)$product['id']]]);
    }
}

echo $renderer->render('product-details.html', [
    'page' => 'product',
    'title' => $productName . ' | ' . $brand['name'],
    'metaDescription' => 'African and Caribbean Marketplace',
    'meta' => [
        'canonical' => $productUrl,
        'og_type' => 'product',
        'og_image' => $productImage,
        'keywords' => implode(', ', array_filter([$productName, $product['brand'] ?? '', $product['vendor'] ?? '', $brand['name']])),
        'product_price' => $product['rawPrice'] ?? null,
        'product_currency' => $product['currency'] ?? 'USD',
    ],
    'product' => $product,
    'products' => $relatedProducts,
    'sellerProducts' => [],
    'spotlightVendors' => [],
    'blogPosts' => [],
    'categories' => $renderer->categories(),
    'contentBlocks' => (new StorefrontContentService(db()))->all(),
    'cartCount' => (new CartService())->count(),
]);
