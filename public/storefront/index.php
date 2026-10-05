<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\CartService;
use App\StorefrontContentService;
use App\StorefrontTemplateChunkService;
use App\StorefrontTemplateService;

$renderer = new StorefrontTemplateService(db());
$cart = new CartService();
$query = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$brand = app_branding();

$storefrontAssets = static function (array $items): array {
    return array_map(static function (array $item): array {
        foreach ($item as $key => $value) {
            if (is_string($value)) {
                $item[$key] = str_replace('/template/assets/', '/storefront/assets/', $value);
            }
        }

        return $item;
    }, $items);
};

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

$products = $storefrontAssets($mergeProducts(
    $category === '' ? $renderer->products(90, $query, 'food-groceries', ['sort' => 'weekly']) : [],
    $renderer->products(120, $query, $category, ['sort' => 'weekly'])
));
$categories = $storefrontAssets($renderer->categories());
$cartItems = $storefrontAssets($renderer->cartProducts($cart->items()));

$usedHomeProductIds = [];
$collectSectionProducts = static function (int $limit, string $query = '', string $category = '', array $filters = []) use ($renderer, &$usedHomeProductIds, $mergeProducts): array {
    $candidateLimit = max($limit * 4, 32);
    $filters['exclude_ids'] = array_values(array_unique(array_merge(
        array_map('intval', (array)($filters['exclude_ids'] ?? [])),
        $usedHomeProductIds
    )));
    $products = $renderer->products($candidateLimit, $query, $category, $filters);

    if (count($products) < $limit) {
        $fillExcludeIds = array_merge(
            $usedHomeProductIds,
            array_map(static fn (array $product): int => (int)($product['id'] ?? 0), $products)
        );
        $products = $mergeProducts(
            $products,
            $renderer->products($candidateLimit, '', '', [
                'sort' => 'weekly',
                'exclude_ids' => array_values(array_unique(array_filter($fillExcludeIds))),
            ])
        );
    }

    $sectionSeed = date('Ymd') . '-home-section-refresh-1-' . sha1($query . '|' . $category . '|' . (string)json_encode($filters));
    usort($products, static function (array $a, array $b) use ($sectionSeed): int {
        $aKey = (string)((int)($a['id'] ?? 0) > 0 ? $a['id'] : ($a['name'] ?? ''));
        $bKey = (string)((int)($b['id'] ?? 0) > 0 ? $b['id'] : ($b['name'] ?? ''));

        return crc32($aKey . '|' . $sectionSeed) <=> crc32($bKey . '|' . $sectionSeed);
    });

    $products = array_slice($products, 0, $limit);

    foreach ($products as $product) {
        $productId = (int)($product['id'] ?? 0);
        if ($productId > 0) {
            $usedHomeProductIds[] = $productId;
        }
    }

    $usedHomeProductIds = array_values(array_unique($usedHomeProductIds));

    return $products;
};

$newArrivals = $storefrontAssets($collectSectionProducts(8, '', 'food-groceries', ['sort' => 'newest']));
$bestSellers = $storefrontAssets($collectSectionProducts(8, '', 'food-groceries', ['sort' => 'top_selling']));
$suggestedForYou = $storefrontAssets($collectSectionProducts(8, '', 'food-groceries', ['sort' => 'weekly']));
$onSale = $storefrontAssets($collectSectionProducts(8, '', 'food-groceries', ['sort' => 'on_sale']));
$giftIdeas = $storefrontAssets($collectSectionProducts(8, 'gift', '', ['sort' => 'weekly']));
$dealsAndSavings = $storefrontAssets($collectSectionProducts(8, '', 'food-groceries', ['sort' => 'on_sale']));
$underTen = $storefrontAssets($collectSectionProducts(8, '', 'food-groceries', ['sort' => 'weekly', 'min_price' => 0, 'max_price' => 10]));
$africaProducts = $storefrontAssets($collectSectionProducts(8, 'africa', '', ['sort' => 'weekly']));
$caribbeanProducts = $storefrontAssets($collectSectionProducts(8, 'caribbean', '', ['sort' => 'weekly']));
$hotProducts = $storefrontAssets($collectSectionProducts(12, '', 'food-groceries', ['sort' => 'weekly']));
$popularProducts = $storefrontAssets($collectSectionProducts(12, '', '', ['sort' => 'weekly']));
$heroProducts = $storefrontAssets($renderer->products(3, '', '', ['sort' => 'random']));

render_layout('storefront-home', 'storefront/pages/home.php', [
    'storefrontTemplate' => new StorefrontTemplateChunkService('index.html'),
    'page' => 'home',
    'title' => $brand['name'] . ' Storefront',
    'metaDescription' => 'African and Caribbean Marketplace',
    'products' => $products,
    'hotProducts' => $hotProducts,
    'popularProducts' => $popularProducts,
    'categories' => $categories,
    'cartItems' => $cartItems,
    'newArrivals' => $newArrivals,
    'bestSellers' => $bestSellers,
    'suggestedForYou' => $suggestedForYou,
    'onSale' => $onSale,
    'giftIdeas' => $giftIdeas,
    'dealsAndSavings' => $dealsAndSavings,
    'underTen' => $underTen,
    'africaProducts' => $africaProducts,
    'caribbeanProducts' => $caribbeanProducts,
    'heroProducts' => $heroProducts,
    'contentBlocks' => (new StorefrontContentService(db()))->all(),
    'filters' => ['q' => $query, 'category' => $category],
    'cartCount' => $cart->count(),
]);
