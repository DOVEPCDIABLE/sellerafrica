<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\CartService;
use App\StorefrontTemplateService;

$renderer = new StorefrontTemplateService(db());
$query = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$filters = [
    'q' => $query,
    'category' => $category,
    'section' => trim((string)($_GET['section'] ?? '')),
    'brand' => trim((string)($_GET['brand'] ?? '')),
    'rating' => (int)($_GET['rating'] ?? 0),
    'min_price' => trim((string)($_GET['min_price'] ?? '')),
    'max_price' => trim((string)($_GET['max_price'] ?? '')),
    'sort' => trim((string)($_GET['sort'] ?? 'default')),
];
$brand = app_branding();
$hasFocusedCatalog = $query !== '' || $category !== '' || $filters['section'] !== ''
    || $filters['brand'] !== '' || $filters['rating'] > 0
    || $filters['min_price'] !== '' || $filters['max_price'] !== ''
    || $filters['sort'] !== 'default' || ($_GET['browse'] ?? '') === '1';

$mergeProducts = static function (array ...$groups): array {
    $seen = [];
    $merged = [];
    foreach ($groups as $group) {
        foreach ($group as $product) {
            $id = (int)($product['id'] ?? 0);
            $key = $id > 0 ? 'id:' . $id : 'name:' . (string)($product['name'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $merged[] = $product;
        }
    }

    return $merged;
};
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;
$totalProducts = $renderer->productCount($query, $category, $filters);
$offset = ($page - 1) * $perPage;
$isDefaultCatalog = $page === 1
    && $query === ''
    && $category === ''
    && trim((string)($filters['section'] ?? '')) === ''
    && trim((string)($filters['brand'] ?? '')) === ''
    && (int)($filters['rating'] ?? 0) <= 0
    && trim((string)($filters['min_price'] ?? '')) === ''
    && trim((string)($filters['max_price'] ?? '')) === ''
    && trim((string)($filters['sort'] ?? 'default')) === 'default';

if ($isDefaultCatalog && $totalProducts > $perPage) {
    $maxOffset = max(0, $totalProducts - $perPage);
    $offset = crc32(date('Ymd') . '-marketplace-window-refresh-1') % ($maxOffset + 1);
}

$catalogProducts = $renderer->products($perPage, $query, $category, $filters, $offset);
$totalPages = max(1, (int)ceil($totalProducts / $perPage));
$usedSectionProductIds = [];
$section = static function (int $limit, string $q = '', string $cat = '', array $extra = []) use ($renderer, &$usedSectionProductIds, $mergeProducts): array {
    $candidateLimit = max($limit * 25, 200);
    $extra['exclude_ids'] = array_values(array_unique(array_merge(
        array_map('intval', (array)($extra['exclude_ids'] ?? [])),
        $usedSectionProductIds
    )));
    $products = $renderer->products($candidateLimit, $q, $cat, $extra);

    $sectionSeed = date('Ymd') . '-marketplace-section-refresh-2-' . sha1($q . '|' . $cat . '|' . (string)json_encode($extra));
    if (in_array((string)($extra['sort'] ?? 'weekly'), ['weekly', 'default'], true)) {
      usort($products, static function (array $a, array $b) use ($sectionSeed): int {
        $aKey = (string)((int)($a['id'] ?? 0) > 0 ? $a['id'] : ($a['name'] ?? ''));
        $bKey = (string)((int)($b['id'] ?? 0) > 0 ? $b['id'] : ($b['name'] ?? ''));

        return crc32($aKey . '|' . $sectionSeed) <=> crc32($bKey . '|' . $sectionSeed);
      });
    }

    $products = array_slice($products, 0, $limit);

    foreach ($products as $product) {
        $productId = (int)($product['id'] ?? 0);
        if ($productId > 0) {
            $usedSectionProductIds[] = $productId;
        }
    }

    $usedSectionProductIds = array_values(array_unique($usedSectionProductIds));

    return $products;
};

render_layout('storefront-home', 'storefront/pages/shop.php', [
    'page' => 'shop',
    'title' => 'African and Caribbean Marketplace | ' . $brand['name'],
    'metaDescription' => 'African and Caribbean Marketplace',
    'products' => $catalogProducts,
    'totalProducts' => $totalProducts,
    'currentPage' => $page,
    'totalPages' => $totalPages,
    'categories' => $renderer->categories(80),
    'filters' => $filters,
    'sections' => $hasFocusedCatalog ? [] : [
        'Featured Products' => $section(8, $query, $category, $filters),
        'New Arrivals' => $renderer->products(8, '', '', ['sort' => 'newest']),
        'Best Sellers' => $renderer->products(8, '', '', ['sort' => 'top_selling', 'section' => 'Best Sellers']),
        'Suggested for You' => $section(8),
        'On Sale' => $renderer->products(8, '', '', ['sort' => 'on_sale']),
        'Gift Ideas' => $section(8, 'gift'),
        'Deals and Savings' => $renderer->products(8, '', '', ['sort' => 'on_sale']),
        'Under $10 Items' => $section(8, '', '', ['min_price' => 0, 'max_price' => 10]),
        'Handmade' => $renderer->products(8, '', 'handmade', ['sort' => 'weekly']),
        'African and Caribbean Marketplace' => $section(8, 'caribbean'),
    ],
    'cartCount' => (new CartService())->count(),
]);
