<?php
$brand = app_branding();
$brandName = trim((string)($brand['name'] ?? 'Seller Africa')) ?: 'Seller Africa';
$brandLogo = trim((string)($brand['logo'] ?? ''));
$marketplaceStats = (new \App\StorefrontTemplateService(db()))->marketplaceStats();
$products = array_values(array_filter((array)($products ?? []), 'is_array'));
$categories = array_values(array_filter((array)($categories ?? []), 'is_array'));
$sectionsInput = $sections ?? [];
$filtersInput = $filters ?? [];
$sections = is_array($sectionsInput) ? $sectionsInput : [];
$filters = is_array($filtersInput) ? $filtersInput : [];
if (isset($sections['New Arrivals'])) {
    $sections = ['New Arrivals' => $sections['New Arrivals']] + $sections;
}
$cartCount = (int)($cartCount ?? 0);
$asset = static fn (string $path): string => app_url($path);
$publicImage = static function (string $name) use ($asset): string {
    foreach (['jpeg', 'jpg', 'png', 'webp'] as $ext) {
        $path = 'assets/images/' . $name . '.' . $ext;
        if (is_file(APP_ROOT . '/public/' . $path)) {
            return $asset($path);
        }
    }
    if ($name === 's1') {
        foreach (['jpeg', 'jpg', 'png', 'webp'] as $ext) {
            $path = 'assets/images/s.' . $ext;
            if (is_file(APP_ROOT . '/public/' . $path)) {
                return $asset($path);
            }
        }
    }
    return '';
};

$shopSlides = [
    ['title' => 'Shop slide 1', 'desktop' => $publicImage('sid1'), 'mobile' => $publicImage('sid1')],
    ['title' => 'Shop slide 2', 'desktop' => $publicImage('sid2'), 'mobile' => $publicImage('sid2')],
    ['title' => 'Shop slide 3', 'desktop' => $publicImage('sid3'), 'mobile' => $publicImage('sid3')],
    ['title' => 'Shop slide 4', 'desktop' => $publicImage('sid4'), 'mobile' => $publicImage('sid4')],
    ['title' => 'Shop slide 5', 'desktop' => $publicImage('sid5'), 'mobile' => $publicImage('sid5')],
    ['title' => 'Shop slide 6', 'desktop' => $publicImage('sid6'), 'mobile' => $publicImage('sid6')],
    ['title' => 'Shop slide 7', 'desktop' => $publicImage('sid7'), 'mobile' => $publicImage('sid7')],
];
$shopSlides = array_values(array_filter($shopSlides, static fn (array $slide): bool => (string)($slide['desktop'] ?? '') !== ''));
$defaultShopSlides = $shopSlides;
$shopBanners = [
    ['title' => 'Shop banner 1', 'image' => $publicImage('bs1'), 'url' => app_url('shop')],
    ['title' => 'Shop banner 2', 'image' => $publicImage('bs2'), 'url' => app_url('shop')],
];
$testimonials = [
    ['Yetunde Ajani', 'Edited 5 months ago', 'Seller Africaribbean Marketplace is a great platform with reliable service and an easy-to-use marketplace. It supports African and Caribbean businesses and delivers real value. Highly recommended.'],
    ['Joel Ayobami', '5 months ago', 'Great service is assured when dealing with Seller AfriCaribbean Marketplace. Top-notch ecommerce store to shop on.'],
    ['Seyi Dipo-Ogunleye', '5 months ago', 'Their services are top notch.'],
    ['Ajao John', 'a year ago', 'Excellent service and professional team. They were responsive, reliable, and exceeded my expectations. Highly recommend.'],
    ['Hannah Jesus', 'a month ago', 'Bags available for sale. $30.'],
    ['OJO Ayomikun', 'a year ago', 'Seller Africa is such a great platform. You guys are doing amazing.'],
    ['Emmanuel Idowu', 'a year ago', 'Such an amazing space, keep the good work going.'],
    ['Noto Ye', 'a year ago', 'Great website with great service.'],
    ['Ahmed Mohd Nezeef', '2 months ago', 'Reliable marketplace and a growing platform for the African and Caribbean Marketplace.'],
];
$partners = [
    ['Ecobank', 'https://ecobank.com/', $asset('assets/images/partners/ecobank.jpeg')],
    ['NEXIM Bank', 'https://neximbank.gov.ng/', $asset('assets/images/partners/nexim.jpeg')],
    ['Aramex', 'https://www.aramex.com/', $asset('assets/images/partners/aramex.jpeg')],
    ['DHL', 'https://www.dhl.com/', $asset('assets/images/partners/dhl.jpeg')],
    ['FedEx', 'https://www.fedex.com/', $asset('assets/images/partners/fedex.jpeg')],
    ['UPS', 'https://www.ups.com/', $asset('assets/images/partners/ups.jpeg')],
];
$uniqueSlides = static function (array $slides): array {
    $seen = [];
    $unique = [];
    foreach ($slides as $slide) {
        if (!is_array($slide)) {
            continue;
        }
        $desktop = (string)($slide['desktop'] ?? '');
        $mobile = (string)($slide['mobile'] ?? $desktop);
        $desktopKey = (string)(parse_url($desktop, PHP_URL_PATH) ?: $desktop);
        $mobileKey = (string)(parse_url($mobile, PHP_URL_PATH) ?: $mobile);
        $key = strtolower(trim($desktopKey . '|' . $mobileKey));
        if ($key === '|' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $unique[] = $slide;
    }
    return $unique;
};

try {
    if (table_exists('marketing_sliders')) {
        $rows = db()->fetchAll(
            "SELECT * FROM marketing_sliders
             WHERE status = 'active'
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at IS NULL OR ends_at >= NOW())
             ORDER BY sort_order ASC, updated_at DESC
             LIMIT 8"
        );
        if ($rows !== []) {
            $marketingSlides = array_map(static function (array $row, int $index) use ($defaultShopSlides): array {
                $fallback = $defaultShopSlides[$index % count($defaultShopSlides)];
                $desktop = trim((string)($row['image_url'] ?? ''));
                $mobile = trim((string)($row['image_url_mobile'] ?? ''));
                $desktop = $desktop !== '' ? app_brand_asset_url($desktop) : (string)$fallback['desktop'];
                return [
                    'title' => trim((string)($row['title'] ?? '')) ?: (string)$fallback['title'],
                    'desktop' => $desktop,
                    'mobile' => $mobile !== '' ? app_brand_asset_url($mobile) : $desktop,
                ];
            }, $rows, array_keys($rows));
            $shopSlides = array_merge($defaultShopSlides, $marketingSlides);
        }
    }
    if (table_exists('marketing_banners')) {
        $rows = db()->fetchAll(
            "SELECT * FROM marketing_banners
             WHERE status = 'active'
               AND placement IN ('homepage', 'homepage_section_1', 'homepage_section_2')
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at IS NULL OR ends_at >= NOW())
             ORDER BY FIELD(placement, 'homepage_section_1', 'homepage_section_2', 'homepage'), updated_at DESC
             LIMIT 2"
        );
        foreach ($rows as $index => $row) {
            $image = trim((string)($row['image_url'] ?? ''));
            if ($image === '') {
                continue;
            }
            $shopBanners[$index] = [
                'title' => (string)($row['title'] ?? $shopBanners[$index]['title']),
                'image' => app_brand_asset_url($image),
                'url' => trim((string)($row['target_url'] ?? '')) ?: app_url('shop'),
            ];
        }
    }
} catch (\Throwable) {
}

$shopSlides = $uniqueSlides($shopSlides);
if ($shopSlides === []) {
    $shopSlides = $defaultShopSlides;
}

$shopSlides = [
    ['desktop' => app_url('assets/images/sac-slide-1.jpeg'), 'mobile' => app_url('assets/images/sac-slide-1.jpeg'), 'title' => 'Seller Africa Marketplace', 'url' => app_url('shop')],
    ['desktop' => app_url('assets/images/sac-slide-2.jpeg'), 'mobile' => app_url('assets/images/sac-slide-2.jpeg'), 'title' => 'Seller Africa Marketplace', 'url' => app_url('shop')],
    ['desktop' => app_url('assets/images/sac-slide-3.jpeg'), 'mobile' => app_url('assets/images/sac-slide-3.jpeg'), 'title' => 'Seller Africa Marketplace', 'url' => app_url('shop')],
    ['desktop' => app_url('assets/images/sac-slide-4.jpeg'), 'mobile' => app_url('assets/images/sac-slide-4.jpeg'), 'title' => 'Seller Africa Marketplace', 'url' => app_url('shop')],
    ['desktop' => app_url('assets/images/sac-slide-5.jpeg'), 'mobile' => app_url('assets/images/sac-slide-5.jpeg'), 'title' => 'Seller Africa Marketplace', 'url' => app_url('shop')],
    ['desktop' => app_url('assets/images/sac-slide-6.jpeg'), 'mobile' => app_url('assets/images/sac-slide-6.jpeg'), 'title' => 'Seller Africa Marketplace', 'url' => app_url('shop')],
    ['desktop' => app_url('assets/images/sac-slide-7.jpeg'), 'mobile' => app_url('assets/images/sac-slide-7.jpeg'), 'title' => 'Seller Africa Marketplace', 'url' => app_url('shop')],
];

$categoryGroups = [
    'Fashion & clothing' => ['fashion'],
    'Food & groceries' => ['food-groceries'],
    'Natural & organic' => ['natural-organic-products'],
    'Seasonings & spices' => ['bestsellers'],
    'Handmade' => ['handmade'],
    'Snacks & ready to eat' => ['snacks-ready-to-eat'],
    'Wellness products' => ['wellness-products'],
    'Books' => ['books'],
];
$categoryIconSvg = static function (string $name): string {
    $lower = strtolower($name);
    $emoji = match (true) {
        str_contains($lower, 'fashion'), str_contains($lower, 'beauty'), str_contains($lower, 'hair') => '💄',
        str_contains($lower, 'food'), str_contains($lower, 'grocer'), str_contains($lower, 'staple') => '🥘',
        str_contains($lower, 'natural'), str_contains($lower, 'organic') => '🌿',
        str_contains($lower, 'season'), str_contains($lower, 'spice'), str_contains($lower, 'pepper') => '🌶️',
        str_contains($lower, 'drink'), str_contains($lower, 'beverage') => '🥤',
        str_contains($lower, 'snack'), str_contains($lower, 'ready') => '🍿',
        str_contains($lower, 'wellness'), str_contains($lower, 'herb') => '🌱',
        str_contains($lower, 'bbq'), str_contains($lower, 'grill') => '🔥',
        str_contains($lower, 'bean') => '🫘',
        str_contains($lower, 'book'), str_contains($lower, 'journal') => '📚',
        default => '🛍️',
    };
    return '<span class="sa-shop-cat__icon" aria-hidden="true"><span class="sa-shop-cat__emoji">' . $emoji . '</span></span>';
};
$hotDeals = array_values(array_filter((array)($sections['Hot Deals'] ?? $sections['On Sale'] ?? $sections['Deals and Savings'] ?? $products), 'is_array'));

$findCategory = static function (array $slugs) use ($categories): ?array {
    foreach ($slugs as $slug) {
        foreach ($categories as $category) {
            if (($category['slug'] ?? '') === $slug) {
                return $category;
            }
        }
    }
    return null;
};

$categoryUrl = static function (array $slugs, array $extra = []) use ($findCategory): string {
    $primarySlug = (string)($slugs[0] ?? '');
    $url = (string)($findCategory($slugs)['url'] ?? app_url('shop?category=' . rawurlencode($primarySlug)));
    if ($extra === []) {
        return $url;
    }
    $separator = str_contains($url, '?') ? '&' : '?';
    return $url . $separator . http_build_query($extra);
};

$shopUrl = static function (array $params): string {
    $params = array_filter($params, static fn (mixed $value): bool => trim((string)$value) !== '');
    return app_url('shop' . ($params !== [] ? '?' . http_build_query($params) : ''));
};

$sectionViewAllUrl = static function (string $title) use ($categoryUrl, $shopUrl): string {
    return match ($title) {
        'Gift Ideas' => $shopUrl(['section' => $title, 'q' => 'gift']),
        'Handmade' => $shopUrl(['category' => 'handmade']),
        'African and Caribbean Marketplace' => $shopUrl(['section' => $title, 'q' => 'caribbean']),
        'On Sale', 'Deals and Savings', 'Hot Deals', 'Special Offers' => $shopUrl(['section' => $title, 'sort' => 'on_sale']),
        'Under $10 Items' => $shopUrl(['section' => $title, 'max_price' => '10']),
        'New Arrivals' => $shopUrl(['section' => $title, 'sort' => 'newest']),
        'Best Sellers' => $shopUrl(['section' => $title, 'sort' => 'top_selling']),
        default => $shopUrl(['section' => $title]),
    };
};

$activeSection = trim((string)($filters['section'] ?? ''));
$hasFocusedCatalog = trim((string)($filters['q'] ?? '')) !== ''
    || trim((string)($filters['category'] ?? '')) !== ''
    || trim((string)($filters['brand'] ?? '')) !== ''
    || trim((string)($filters['min_price'] ?? '')) !== ''
    || trim((string)($filters['max_price'] ?? '')) !== ''
    || trim((string)($filters['sort'] ?? 'default')) !== 'default'
    || $activeSection !== ''
    || (($_GET['browse'] ?? '') === '1');

$renderLogo = static function () use ($brandLogo, $brandName): void {
    if ($brandLogo !== '') {
        echo '<img src="' . e($brandLogo) . '" alt="' . e($brandName) . '">';
        return;
    }
    echo '<strong>' . e($brandName) . '</strong>';
};

$icon = static function (string $name): string {
    $paths = [
        'bag' => '<path d="M7 8V6a5 5 0 0 1 10 0v2"/><path d="M5 8h14l-1 13H6L5 8Z"/>',
        'arrow' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/>',
    ];
    return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ($paths[$name] ?? $paths['arrow']) . '</svg>';
};

$moneyFallback = static function (array $product): string {
    return (string)($product['price'] ?? 'View price');
};

$renderProduct = static function (array $product, bool $deal = false) use ($asset, $icon, $moneyFallback): void {
    $id = (int)($product['id'] ?? 0);
    $name = (string)($product['name'] ?? 'Seller Africa product');
    $url = (string)($product['url'] ?? app_url('shop'));
    $image = trim((string)($product['image'] ?? '')) ?: $asset('assets/images/product-placeholder.svg');
    $price = $moneyFallback($product);
    $regular = (string)($product['regularPrice'] ?? '');
    $description = trim(strip_tags((string)($product['excerpt'] ?? $product['description'] ?? 'Authentic marketplace product from a Seller Africa vendor.')));
    if ($description !== '') {
        $description = function_exists('mb_strimwidth') ? mb_strimwidth($description, 0, 110, '...') : substr($description, 0, 107) . (strlen($description) > 107 ? '...' : '');
    } else {
        $description = 'Authentic marketplace product from a Seller Africa vendor.';
    }
    echo '<article class="sa-shop-card">';
    if ($regular !== '') echo '<span class="sa-shop-card__badge">Deal</span>';
    echo '<button class="sa-shop-card__heart" type="button" aria-label="Save product">' . $icon('heart') . '</button>';
    echo '<a class="sa-shop-card__image" href="' . e($url) . '"><img src="' . e($image) . '" alt="' . e($name) . '" loading="lazy">';
    if (!empty($product['shipsFromUsWarehouse'])) {
        echo '<span class="sa-shop-card__warehouse" title="This item ships from the U.S. warehouse">🚚 Ships from U.S. Warehouse</span>';
    }
    echo '</a>';
    echo '<div class="sa-shop-card__body"><h3><a href="' . e($url) . '">' . e($name) . '</a></h3>';
    echo '<div class="sa-shop-stars">☆☆☆☆☆ <span>0.0</span></div><p>' . e($description) . '</p><div class="sa-shop-price">';
    if ($regular !== '') echo '<del>' . e($regular) . '</del>';
    echo '<strong>' . e($price) . '</strong></div>';
    echo '<div class="sa-shop-actions"><button data-add-cart="' . e((string)$id) . '"' . ($id <= 0 ? ' disabled' : '') . '>Add to Basket</button><button class="is-secondary" data-buy-now="' . e((string)$id) . '"' . ($id <= 0 ? ' disabled' : '') . '>Buy Now</button></div></div></article>';
};
?>

<style>
  body.sa-shop-page { margin: 0; background: #fff; }
  .sa-shop { --green:#006b52; --gold:#d8951a; --coral:#e8734a; --ink:#202326; --muted:#535f5d; --line:#e5e5e5; --mint:#e7fbf5; --surface:#f8faf6; --shimmer:#f3f7f5; --shadow-sm:0 5px 12px rgba(32,35,38,.08); --shadow-md:0 10px 24px rgba(32,35,38,.08); --shadow-lg:0 16px 34px rgba(32,35,38,.12); --shadow-xl:0 20px 50px rgba(32,35,38,.12); --radius:.625rem; --max:1536px; --gutter:clamp(24px,6.25vw,128px); --font-unageo:"Unageo","DM Sans",system-ui,sans-serif; --font-aileron:"Aileron","DM Sans",Arial,sans-serif; font-family:var(--font-aileron); color:var(--muted); -webkit-font-smoothing:antialiased; overflow:hidden; }
  .sa-shop * { box-sizing: border-box; }
  .sa-shop a { color: inherit; text-decoration: none; }
  .sa-shop svg { width: 22px; height: 22px; }
  .sa-shop-rolling-banner { overflow:hidden; background:var(--green); }
  .sa-shop-rolling-banner__track { display:flex; width:max-content; gap:60px; padding:10px 0; animation:sa-shop-rolling-scroll 18s linear infinite; }
  .sa-shop-rolling-banner__track span { flex-shrink:0; color:#fff; font:800 14px/1 var(--font-unageo); white-space:nowrap; }
  @keyframes sa-shop-rolling-scroll { from { transform:translateX(0); } to { transform:translateX(-50%); } }
  @media (prefers-reduced-motion: reduce) { .sa-shop-rolling-banner__track { animation:none; } }
  .sa-shop-cookie-banner { position:fixed; left:16px; right:16px; bottom:16px; z-index:150; display:none; align-items:center; gap:16px; flex-wrap:wrap; max-width:640px; margin:0 auto; padding:18px 22px; border-radius:16px; background:#fff; box-shadow:var(--shadow-xl); border:1px solid var(--line); }
  .sa-shop-cookie-banner.is-visible { display:flex; }
  .sa-shop-cookie-banner p { flex:1; min-width:220px; margin:0; color:var(--muted); font-size:13.5px; line-height:1.5; }
  .sa-shop-cookie-banner button { min-height:42px; padding:0 22px; border:0; border-radius:var(--radius); background:var(--green); color:#fff; font:800 14px/1 var(--font-unageo); cursor:pointer; }
  .sa-shop-results-filters { display:flex; gap:12px; margin:-6px 0 26px; }
  .sa-shop-results-filters select { min-height:44px; padding:0 14px; border:1px solid var(--line); border-radius:var(--radius); background:#fff; color:var(--ink); font:600 14px/1 var(--font-aileron); }
  .sa-shop-pagination { display:flex; align-items:center; justify-content:center; gap:18px; margin:40px 0 10px; }
  .sa-shop-pagination a { padding:11px 20px; border:1px solid var(--green); border-radius:999px; color:var(--green); font:800 14px/1 var(--font-unageo); }
  .sa-shop-pagination a:hover { background:var(--green); color:#fff; }
  .sa-shop-pagination__status { color:var(--muted); font-weight:700; font-size:14px; }
  @media (max-width:620px){.sa-shop-results-filters{flex-wrap:wrap}.sa-shop-results-filters select{flex:1 1 45%}}

  #shop-categories { scroll-margin-top: 100px; }

  .sa-shop-header-search { margin:0 0 18px; margin-left:calc((100% - min(var(--max),calc(100% - var(--gutter)))) / 2 + 20px); width:calc(min(var(--max),calc(100% - var(--gutter))) - 40px); display:flex; border:2px solid var(--green); border-radius:var(--radius); overflow:hidden; }
  .sa-shop-header-search input[type="search"] { flex:1; min-height:46px; border:0; padding:0 18px; outline:0; font:500 15px/1 var(--font-aileron); color:var(--ink); background:transparent; }
  .sa-shop-header-search button { min-width:110px; min-height:46px; border:0; background:var(--gold); color:var(--ink); font:800 15px/1 var(--font-unageo); cursor:pointer; }
  .sa-shop-header-search__icon { display:none; font-size:18px; }
  .sa-shop-header-search__text { display:inline; }
  @media (max-width:699px){.sa-shop-header-search__icon{display:inline}.sa-shop-header-search__text{display:none}.sa-shop-header-search button{min-width:54px}}

  .sa-shop-header { position:sticky; top:0; z-index:50; background:rgba(255,255,255,.96); border-bottom:1px solid rgba(229,229,229,.7); backdrop-filter:blur(14px); }
  .sa-shop-nav { width:min(var(--max),calc(100% - var(--gutter))); min-height:92px; margin:auto; display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:30px; }
  .sa-shop-logo img { max-width:178px; max-height:58px; display:block; object-fit:contain; }
  .sa-shop-links { display:flex; justify-content:center; gap:clamp(22px,4vw,70px); font:600 16px/1 var(--font-unageo); }
  .sa-shop-links a.is-active { color:var(--green); border-bottom:2px solid currentColor; padding-bottom:6px; }
  .sa-shop-links .sa-shop-menu-auth-link { display:none; }
  .sa-shop-head-actions { display:flex; align-items:center; gap:12px; }
  .sa-shop-menu-toggle { display:none; width:46px; height:44px; border:1px solid var(--green); border-radius:var(--radius); background:#fff; color:var(--green); padding:0; cursor:pointer; }
  .sa-shop-menu-toggle span,.sa-shop-menu-toggle::before,.sa-shop-menu-toggle::after { content:""; display:block; width:18px; height:2px; margin:4px auto; border-radius:999px; background:currentColor; }
  .sa-shop-cart { position:relative; display:grid; place-items:center; width:46px; height:44px; border:1px solid var(--green); border-radius:var(--radius); background:#fff; color:var(--muted); }
  .sa-shop-cart span,.sa-shop-floating-cart span { display:grid; place-items:center; min-width:20px; height:20px; border-radius:999px; background:var(--green); color:#fff; font:800 11px/1 var(--font-unageo); }
  .sa-shop-cart span { position:absolute; right:0; top:0; }
  .sa-shop-btn { min-height:46px; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--green); border-radius:var(--radius); padding:0 20px; color:#fff !important; background:var(--green); font:800 15px/1 var(--font-unageo); }
  .sa-shop-btn.is-ghost { background:#fff !important; color:var(--green) !important; }
  .sa-shop-btn.is-register,
  .sa-shop-btn.is-register:visited,
  .sa-shop-btn.is-register:hover,
  .sa-shop-btn.is-register:focus { background:var(--green) !important; border-color:var(--green) !important; color:#fff !important; }
  .sa-shop-btn.is-register * { color:inherit !important; }
  .sa-shop-wrap { width:min(var(--max),calc(100% - var(--gutter))); margin:auto; }
  .sa-shop-regions { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:18px; }
  .sa-shop-region { display:flex; flex-direction:column; align-items:center; gap:10px; padding:26px 18px; border:1px solid var(--line); border-radius:16px; background:#fff; text-align:center; transition:transform .18s ease, border-color .18s ease, box-shadow .18s ease; }
  .sa-shop-region:hover { transform:translateY(-4px); border-color:var(--gold); box-shadow:var(--shadow-lg); }
  .sa-shop-region__flag { font-size:36px; }
  .sa-shop-region__label { color:var(--ink); font:700 15px/1.2 var(--font-unageo); }
  @media (max-width:620px){.sa-shop-regions{grid-template-columns:repeat(2,minmax(0,1fr))}}

  .sa-shop-stats { width:min(var(--max),calc(100% - var(--gutter))); margin:0 auto 50px; }
  .sa-shop-stats__grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:20px; background:var(--green); border-radius:20px; padding:36px 24px; }
  .sa-shop-stats__item { text-align:center; color:#fff; }
  .sa-shop-stats__item strong { display:block; font:800 clamp(28px,3.5vw,42px)/1 var(--font-unageo); margin-bottom:6px; color:var(--gold); }
  .sa-shop-stats__item span { font-size:13px; opacity:.9; text-transform:uppercase; letter-spacing:.04em; }
  @media (max-width:620px){.sa-shop-stats__grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:24px 16px}}

  .sa-shop-shipping__grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:20px; }
  .sa-shop-shipping__item { padding:28px 22px; border:1px solid var(--line); border-radius:16px; background:#fff; text-align:center; }
  .sa-shop-shipping__flag { font-size:38px; display:block; margin-bottom:14px; }
  .sa-shop-shipping__item h3 { margin:0 0 8px; font-size:16px; font-weight:700; color:var(--ink); }
  .sa-shop-shipping__item p { margin:0; font-size:13.5px; color:var(--muted); line-height:1.55; }
  @media (max-width:768px){.sa-shop-shipping__grid{grid-template-columns:1fr}}

  .sa-shop-story { background:var(--mint); border-radius:24px; }
  .sa-shop-story__inner { max-width:720px; margin:0 auto; text-align:center; padding:54px 24px; }
  .sa-shop-story__eyebrow { display:inline-block; padding:6px 14px; border-radius:999px; background:#fff; color:var(--green); font:700 12px/1 var(--font-unageo); text-transform:uppercase; letter-spacing:.04em; margin-bottom:18px; }
  .sa-shop-story h2 { margin:0 0 16px; color:var(--ink); font:700 clamp(24px,3vw,34px)/1.2 Georgia, 'Iowan Old Style', 'Palatino Linotype', serif; letter-spacing:-0.01em; }
  .sa-shop-story p { margin:0; color:var(--muted); font-size:15.5px; line-height:1.7; }

  .sa-shop-newsletter { background:var(--coral); border-radius:24px; }
  .sa-shop-newsletter__inner { max-width:560px; margin:0 auto; text-align:center; padding:56px 24px; }
  .sa-shop-newsletter h2 { margin:0; color:#fff; font:700 clamp(26px,3vw,36px)/1.15 Georgia, 'Iowan Old Style', 'Palatino Linotype', serif; letter-spacing:-0.01em; }
  .sa-shop-newsletter p { margin:12px 0 0; color:rgba(255,255,255,.92); font-size:15px; line-height:1.5; }
  .sa-shop-newsletter__form { display:flex; gap:10px; max-width:420px; margin:26px auto 0; }
  .sa-shop-newsletter__form input { flex:1; min-height:50px; border:0; border-radius:var(--radius); padding:0 16px; font:500 15px/1 var(--font-aileron); }
  .sa-shop-newsletter__form button { min-height:46px; padding:0 26px; border:0; border-radius:var(--radius); background:var(--green); color:#fff; font:800 15px/1 var(--font-unageo); cursor:pointer; }
  .sa-shop-newsletter__note { margin-top:16px; color:#fff; font:700 14px/1 var(--font-unageo); }
  @media (max-width:620px){.sa-shop-newsletter__form{flex-direction:column}}

  .sa-shop-hero { width:100%; margin:0; padding:clamp(72px,9vw,118px) max(var(--gutter),calc((100vw - var(--max)) / 2)); background:#004b2f; text-align:center; }
  .sa-shop-hero__inner { max-width:900px; margin:0 auto; }
  .sa-shop-hero__eyebrow { display:inline-block; color:var(--gold); font:900 clamp(15px,1.6vw,22px)/1 var(--font-unageo); text-transform:uppercase; letter-spacing:.13em; margin-bottom:24px; }
  .sa-shop-hero__title { max-width:860px; margin:0 auto; color:#fffdf7; font:500 clamp(48px,7vw,86px)/1.28 Georgia, 'Iowan Old Style', 'Palatino Linotype', serif; letter-spacing:0; }
  .sa-shop-hero__title em { display:block; color:inherit; font-style:normal; }
  .sa-shop-hero__subtitle { max-width:650px; margin:30px auto 0; color:rgba(255,255,255,.72); font-size:clamp(20px,2.6vw,30px); line-height:1.55; }
  .sa-shop-hero__ctas { display:flex; flex-wrap:wrap; justify-content:center; gap:24px; margin-top:72px; }
  .sa-shop-hero__ctas .sa-shop-btn { min-width:min(100%,278px); min-height:92px; border-radius:12px; border-color:rgba(255,255,255,.18); padding:0 34px; background:rgba(255,255,255,.12); color:#fff !important; font-size:clamp(20px,2vw,26px); }
  .sa-shop-hero__ctas .sa-shop-btn:hover { background:rgba(255,255,255,.18); border-color:rgba(255,255,255,.28); }
  .sa-shop-hero__ctas .sa-shop-btn.is-gold { border-color:var(--gold); background:var(--gold); color:#1d241f !important; }
  .sa-shop-hero__ctas .sa-shop-btn.is-gold:hover { border-color:#c99100; background:#c99100; color:#1d241f !important; }
  @media (max-width:620px){.sa-shop-hero{padding:70px var(--gutter) 78px}.sa-shop-hero__eyebrow{margin-bottom:22px}.sa-shop-hero__title{font-size:clamp(44px,12vw,58px);line-height:1.32}.sa-shop-hero__subtitle{font-size:clamp(20px,6vw,27px)}.sa-shop-hero__ctas{display:grid;width:100%;gap:16px;margin-top:54px}.sa-shop-hero__ctas .sa-shop-btn{width:100%;min-height:78px;border-radius:12px;font-size:23px}}

  .sa-shop-slider { width:min(var(--max),calc(100% - var(--gutter))); margin:clamp(26px,5vw,70px) auto 48px; position:relative; overflow:hidden; border-radius:28px; background:var(--surface); box-shadow:var(--shadow-xl); }
  .sa-shop-slider__track { display:flex; transition:transform .55s ease; }
  .sa-shop-slider__slide { min-width:100%; aspect-ratio:16/9; position:relative; }
  .sa-shop-slider__slide picture,.sa-shop-slider__slide img { position:absolute; inset:0; width:100%; height:100%; }
  .sa-shop-slider__slide img { object-fit:contain; object-position:center; display:block; background:var(--surface); }
  .sa-shop-slider__dots { position:absolute; left:50%; bottom:22px; transform:translateX(-50%); display:flex; gap:10px; z-index:2; }
  .sa-shop-slider__dots button { width:10px; height:10px; padding:0; border:0; border-radius:999px; background:rgba(255,255,255,.55); box-shadow:var(--shadow-sm); transition:width .25s ease, background .25s ease; }
  .sa-shop-slider__dots button.is-active { width:26px; background:var(--gold); }
  .sa-shop-signup-strip { width:min(var(--max),calc(100% - var(--gutter))); margin:0 auto 34px; display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:22px; padding:24px; border:1px solid rgba(0,107,82,.16); border-radius:18px; background:#f3fbf8; box-shadow:var(--shadow-lg); }
  .sa-shop-signup-strip h2 { margin:0; color:var(--ink); font:700 clamp(24px,2.3vw,34px)/1.12 Georgia, 'Iowan Old Style', 'Palatino Linotype', serif; letter-spacing:-0.01em; }
  .sa-shop-signup-strip p { margin:8px 0 0; color:var(--muted); font-size:16px; line-height:1.5; }
  .sa-shop-signup-strip__actions { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:12px; }
  .sa-shop-signup-strip__actions .sa-shop-btn { min-width:210px; }
  .sa-shop-signup-strip__actions .sa-shop-btn.is-vendor { background:#fff !important; border-color:var(--green) !important; color:var(--green) !important; }
  .sa-shop-signup-strip__actions .sa-shop-btn.is-vendor:hover { background:var(--green) !important; color:#fff !important; }
  .sa-shop-signup-strip__actions .sa-shop-btn.is-ghost { background:#fff !important; border-color:var(--green) !important; color:var(--green) !important; }
  .sa-shop-signup-strip__actions .sa-shop-btn.is-ghost:hover { background:var(--green) !important; color:#fff !important; }
  .sa-shop-search { display:grid; grid-template-columns:minmax(0,1fr) auto; margin:48px 0 42px; border:1px solid var(--line); border-radius:var(--radius); box-shadow:var(--shadow-sm); overflow:hidden; }
  .sa-shop-search--focused { margin-top:clamp(28px,5vw,64px); }
  .sa-shop-search input { min-height:58px; border:0; padding:0 24px; outline:0; font:500 17px/1 var(--font-aileron); color:var(--ink); }
  .sa-shop-search button { min-width:110px; border:0; background:var(--green); color:#fff; font:800 15px/1 var(--font-unageo); }
  .sa-shop-focused-page { min-height:calc(100vh - 92px); padding-bottom:80px; }
  .sa-shop-title-row { display:flex; align-items:center; justify-content:space-between; gap:18px; margin:0 0 28px; }
  .sa-shop-title-row h1,.sa-shop-title-row h2 { margin:0; color:var(--ink); font:700 clamp(28px,3vw,40px)/1.1 Georgia, 'Iowan Old Style', 'Palatino Linotype', serif; letter-spacing:-0.01em; }
  .sa-shop-title-row a { color:var(--green); font:800 16px/1 var(--font-unageo); display:inline-flex; align-items:center; gap:6px; }
  .sa-shop-cats { display:grid; grid-auto-flow:column; grid-auto-columns:minmax(116px,1fr); gap:20px; overflow-x:auto; padding:8px 0 30px; scroll-snap-type:x mandatory; }
  .sa-shop-cat { scroll-snap-align:start; display:grid; justify-items:center; gap:12px; min-width:116px; color:var(--green); text-align:center; font:700 15px/1.25 var(--font-aileron); transition:transform .18s ease; }
  .sa-shop-cat:hover { transform:translateY(-3px); }
  .sa-shop-cat img { width:92px; height:92px; border-radius:999px; object-fit:cover; background:var(--surface); }
  .sa-shop-cat__icon { width:92px; height:92px; display:grid; place-items:center; border-radius:999px; background:#f3fbf8; color:var(--green); border:1px solid rgba(0,107,82,.12); box-shadow:var(--shadow-md); transition:border-color .18s ease, box-shadow .18s ease; }
  .sa-shop-cat:hover .sa-shop-cat__icon { border-color:var(--gold); box-shadow:0 14px 28px rgba(216,149,26,.18); }
  .sa-shop-cat__emoji { font-size:38px; line-height:1; display:block; }
  .sa-shop-swipe-note { margin:-18px 0 24px; color:var(--muted); text-align:center; font:700 13px/1 var(--font-aileron); }
  .sa-shop-banner-break { display:block; position:relative; overflow:hidden; width:100%; min-height:clamp(180px,26vw,340px); margin:8px 0 40px; border-radius:22px; background:var(--surface); }
  .sa-shop-intersection-banner { display:block; width:100%; margin:8px 0 40px; border-radius:22px; overflow:hidden; }
  .sa-shop-intersection-banner img { display:block; width:100%; height:auto; }
  .sa-shop-banner-break img { width:100%; height:100%; min-height:inherit; display:block; object-fit:cover; object-position:center; }
  .sa-shop-section { padding:42px 0 74px; }
  .sa-shop-tabs { display:flex; gap:30px; overflow-x:auto; margin:-6px 0 26px; padding-bottom:12px; }
  .sa-shop-tabs a { flex:0 0 auto; padding-bottom:10px; color:var(--muted); font:700 16px/1 var(--font-aileron); }
  .sa-shop-tabs a:first-child,.sa-shop-tabs a.is-active { color:var(--green); border-bottom:2px solid currentColor; }
  .sa-shop-live-panel { display:none; border:1px solid rgba(0,107,82,.15); border-radius:18px; background:var(--surface); padding:clamp(18px,3vw,28px); margin:0 0 36px; box-shadow:var(--shadow-md); }
  .sa-shop-live-panel.is-visible { display:block; }
  .sa-shop-live-panel.is-loading { opacity:.72; pointer-events:none; }
  .sa-shop-live-panel__meta { margin:8px 0 0; color:var(--muted); font:700 14px/1.4 var(--font-aileron); }
  .sa-shop-live-panel__empty { margin:0; color:var(--muted); font:700 16px/1.5 var(--font-aileron); }
  .sa-shop-live-panel .sa-shop-grid { margin-top:22px; }
  .sa-shop-skeleton { min-height:340px; border:1px solid var(--line); border-radius:12px; background:linear-gradient(90deg,var(--shimmer) 0%,#fff 45%,var(--shimmer) 90%); background-size:240% 100%; animation:sa-shop-skeleton 1.1s linear infinite; }
  @keyframes sa-shop-skeleton { from { background-position:100% 0; } to { background-position:-100% 0; } }
  .sa-shop-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:24px; }
  .sa-shop-card { position:relative; overflow:hidden; border:1px solid var(--line); border-radius:12px; background:#fff; box-shadow:var(--shadow-md); transition:transform .22s ease, box-shadow .22s ease; }
  .sa-shop-card:hover { transform:translateY(-5px); box-shadow:var(--shadow-lg); }
  .sa-shop-card__badge { position:absolute; z-index:2; left:18px; top:18px; padding:8px 12px; border-radius:999px; background:var(--coral); color:#fff; font:900 12px/1 var(--font-unageo); }
  .sa-shop-card__warehouse { position:absolute; z-index:2; left:10px; right:10px; bottom:10px; padding:6px 10px; border-radius:10px; background:rgba(0,107,82,.94); color:#fff; font:800 11px/1.3 var(--font-aileron); text-align:center; }
  .sa-shop-card__heart { position:absolute; z-index:2; right:18px; top:18px; width:46px; height:46px; border:0; border-radius:999px; display:grid; place-items:center; background:#fff; color:#cbd5d8; box-shadow:var(--shadow-md); transition:color .18s ease, transform .18s ease; }
  .sa-shop-card__heart:hover { color:var(--coral); transform:scale(1.08); }
  .sa-shop-card__image { display:block; position:relative; aspect-ratio:1/.82; background:linear-gradient(90deg,var(--shimmer) 0 14%,#fff 14% 86%,var(--shimmer) 86%); }
  .sa-shop-card__image img { width:100%; height:100%; object-fit:contain; padding:10px; display:block; }
  .sa-shop-card__body { padding:22px; }
  .sa-shop-card h3 { min-height:50px; margin:0; color:var(--ink); font:800 19px/1.3 var(--font-unageo); display:-webkit-box; line-clamp:2; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
  .sa-shop-stars { margin:10px 0; color:var(--gold); font-size:14px; }
  .sa-shop-stars span { color:var(--muted); }
  .sa-shop-card p { min-height:54px; margin:0 0 12px; color:var(--muted); font-size:14px; line-height:1.35; border-bottom:1px solid #edf2f1; padding-bottom:12px; }
  .sa-shop-price { display:flex; align-items:baseline; gap:10px; min-height:30px; }
  .sa-shop-price del { color:var(--muted); font-weight:700; }
  .sa-shop-price strong { color:var(--green); font:900 24px/1 var(--font-unageo); }
  .sa-shop-actions { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:18px; }
  .sa-shop-actions button { min-height:46px; border:1px solid var(--green); border-radius:var(--radius); background:var(--green); color:#fff; font:800 15px/1 var(--font-unageo); transition:transform .15s ease, background .15s ease; }
  .sa-shop-actions button:hover { transform:translateY(-1px); background:#004f3d; }
  .sa-shop-actions .is-secondary { background:#fff; color:var(--green); }
  .sa-shop-actions .is-secondary:hover { background:#f0fdf4; }
  .sa-shop-reviews { position:relative; }
  .sa-shop-reviews__top { display:flex; align-items:center; justify-content:space-between; gap:24px; margin-bottom:44px; }
  .sa-shop-reviews__head h2,.sa-shop-partners__head h2 { margin:0; color:var(--ink); font:700 clamp(30px,4vw,52px)/1.06 Georgia, 'Iowan Old Style', 'Palatino Linotype', serif; letter-spacing:-0.01em; }
  .sa-shop-reviews__head p,.sa-shop-partners__head p { max-width:720px; margin:14px 0 0; color:var(--muted); font-size:18px; line-height:1.6; }
  .sa-shop-trust { display:inline-flex; align-items:center; gap:10px; padding:14px 20px; border:1px solid #bdf3d2; border-radius:var(--radius); background:#effff6; color:var(--ink); font:800 26px/1 var(--font-unageo); white-space:nowrap; }
  .sa-shop-trust span { color:#00b67a; }
  .sa-shop-review-track { display:grid; grid-auto-flow:column; grid-auto-columns:minmax(340px,1fr); gap:20px; overflow-x:auto; scroll-snap-type:x mandatory; padding-bottom:8px; mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent); }
  .sa-shop-review { min-height:210px; padding:30px; border:1px solid var(--line); border-radius:calc(var(--radius) * 1.6); background:#fff; scroll-snap-align:start; }
  .sa-shop-review p { margin:0; color:var(--muted); font-size:17px; line-height:1.55; }
  .sa-shop-review__stars { display:flex; gap:3px; margin-bottom:14px; color:var(--gold); font-size:19px; letter-spacing:0; }
  .sa-shop-review strong { display:block; margin-top:24px; color:var(--ink); font:850 16px/1.2 var(--font-unageo); }
  .sa-shop-review small { display:block; margin-top:5px; color:var(--muted); font-weight:650; }
  .sa-shop-partners { overflow:hidden; }
  .sa-shop-partner-marquee { margin-top:34px; overflow:hidden; mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent); }
  .sa-shop-partner-track { width:max-content; display:flex; gap:18px; animation:sa-shop-partner-loop 30s linear infinite; }
  .sa-shop-partner-marquee:hover .sa-shop-partner-track { animation-play-state:paused; }
  .sa-shop-partner-logo { flex:0 0 clamp(270px,24vw,380px); min-height:164px; display:grid; place-items:center; padding:18px 28px; border:0; border-radius:0; background:transparent; box-shadow:none; transition:transform .2s ease; }
  .sa-shop-partner-logo:hover { transform:translateY(-3px); }
  .sa-shop-partner-logo img { width:auto; max-width:280px; max-height:112px; display:block; object-fit:contain; }
  @keyframes sa-shop-partner-loop { from { transform:translateX(0); } to { transform:translateX(-50%); } }
  @media (prefers-reduced-motion:reduce){.sa-shop-partner-marquee{mask-image:none}.sa-shop-partner-track{width:auto;flex-wrap:wrap;justify-content:center;animation:none}}
  .sa-shop-footer { margin-top:28px; background:var(--green); color:#fff; }
  .sa-shop-footer__grid { width:min(var(--max),calc(100% - var(--gutter))); margin:0 auto; display:grid; grid-template-columns:minmax(0,1.15fr) minmax(260px,.85fr); gap:clamp(28px,5vw,76px); padding:58px 0 72px; }
  .sa-shop-footer__brand img { max-width:190px; max-height:70px; object-fit:contain; padding:8px 10px; border-radius:var(--radius); background:#fff; }
  .sa-shop-footer h3,.sa-shop-footer strong { margin:0; color:#fff; font:900 22px/1.15 var(--font-unageo); }
  .sa-shop-footer p { max-width:620px; margin:22px 0 0; color:rgba(255,255,255,.82); font-size:16px; line-height:1.6; }
  .sa-shop-footer ul { display:grid; gap:15px; margin:18px 0 0; padding:0; list-style:none; }
  .sa-shop-footer a { color:rgba(255,255,255,.86); font:700 15px/1.25 var(--font-aileron); }
  .sa-shop-footer a:hover { color:#fff; }
  .sa-shop-footer__bottom { border-top:1px solid rgba(255,255,255,.16); }
  .sa-shop-footer__bottom-inner { width:min(var(--max),calc(100% - var(--gutter))); min-height:54px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; gap:18px; color:rgba(255,255,255,.72); font-size:13px; }
  .sa-shop-floating-cart { position:fixed; right:24px; bottom:24px; z-index:70; display:flex; align-items:center; gap:10px; min-height:54px; padding:0 16px; border:1px solid rgba(0,107,82,.18); border-radius:999px; background:#fff; color:var(--green); box-shadow:var(--shadow-xl); font:800 14px/1 var(--font-unageo); }
  @media (max-width:1100px){.sa-shop-nav{grid-template-columns:auto minmax(0,1fr) auto}.sa-shop-links{display:none;grid-column:1/-1;width:100%;flex-direction:column;align-items:stretch;gap:0;padding:8px 0 18px}.sa-shop.is-menu-open .sa-shop-links{display:flex}.sa-shop-links a{padding:14px 0;border-top:1px solid #eef0f2}.sa-shop-links .sa-shop-menu-auth-link{display:inline-flex;color:var(--green);font-weight:800}.sa-shop-menu-toggle{display:inline-block}.sa-shop-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media (max-width:900px){.sa-shop-signup-strip{grid-template-columns:1fr}.sa-shop-signup-strip__actions{justify-content:flex-start}.sa-shop-reviews__top{align-items:flex-start;flex-direction:column}.sa-shop-review-track{overflow-x:auto;mask-image:none;padding-bottom:8px;scroll-snap-type:x mandatory}.sa-shop-review{scroll-snap-align:start}.sa-shop-footer__grid{grid-template-columns:repeat(2,minmax(0,1fr))}.sa-shop-footer__brand{grid-column:1/-1}}
  @media (max-width:620px){.sa-shop{--gutter:28px}.sa-shop-nav{min-height:170px;grid-template-columns:minmax(0,1fr) auto;gap:10px 14px;padding:18px 0}.sa-shop-logo{grid-row:1/3;align-self:center}.sa-shop-logo img{max-width:148px}.sa-shop-head-actions{display:grid;grid-template-columns:repeat(2,minmax(94px,1fr));gap:12px;grid-column:2}.sa-shop-head-actions .sa-shop-btn{display:inline-flex;grid-row:1;min-height:52px;padding:0 14px;font-size:16px}.sa-shop-head-actions .sa-shop-btn.is-ghost{grid-column:1}.sa-shop-head-actions .sa-shop-btn.is-register{grid-column:2}.sa-shop-cart{grid-row:2;grid-column:1;width:58px;height:58px;justify-self:end}.sa-shop-menu-toggle{grid-row:2;grid-column:2;width:58px;height:58px;justify-self:end}.sa-shop-menu-panel{width:100%;padding:22px}.sa-shop-slider{margin:30px auto 24px;border-radius:18px}.sa-shop-slider__slide{aspect-ratio:4/5}.sa-shop-slider__dots{bottom:18px}.sa-shop-signup-strip{padding:20px;margin-bottom:24px}.sa-shop-signup-strip__actions{display:grid}.sa-shop-signup-strip__actions .sa-shop-btn{width:100%;min-width:0}.sa-shop-search{margin:34px 0 36px}.sa-shop-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.sa-shop-card{scroll-snap-align:unset}.sa-shop-card__image{aspect-ratio:1/1}.sa-shop-card__image img{padding:10px}.sa-shop-card__body{padding:12px}.sa-shop-card h3{min-height:40px;font-size:14px}.sa-shop-card p{display:none}.sa-shop-stars{font-size:12px;margin:8px 0}.sa-shop-price{display:grid;gap:4px;min-height:44px}.sa-shop-price strong{font-size:18px}.sa-shop-actions{grid-template-columns:1fr;gap:8px;margin-top:12px}.sa-shop-actions button{min-height:40px;font-size:13px}.sa-shop-title-row{flex-direction:row;align-items:center}.sa-shop-title-row h1,.sa-shop-title-row h2{font-size:27px}.sa-shop-banner-break{border-radius:18px;margin-bottom:34px}.sa-shop-review-track{grid-auto-columns:minmax(280px,86vw)}.sa-shop-review{padding:22px}.sa-shop-trust{font-size:20px}.sa-shop-partner-marquee{mask-image:none}.sa-shop-partner-logo{flex-basis:240px;min-height:130px;padding:14px 20px}.sa-shop-partner-logo img{max-width:214px;max-height:88px}.sa-shop-footer__grid{grid-template-columns:1fr;padding:42px 0 56px}.sa-shop-footer__bottom-inner{min-height:70px;align-items:flex-start;justify-content:center;flex-direction:column}.sa-shop-floating-cart{right:14px;bottom:86px}}
</style>

<div class="sa-shop">
  <div class="sa-shop-rolling-banner">
    <div class="sa-shop-rolling-banner__track">
      <span>🚚 Free shipping on your first order from our U.S. warehouse</span>
      <span>🚚 Free shipping on your first order from our U.S. warehouse</span>
      <span>🚚 Free shipping on your first order from our U.S. warehouse</span>
      <span>🚚 Free shipping on your first order from our U.S. warehouse</span>
    </div>
  </div>
  <header class="sa-shop-header">
    <nav class="sa-shop-nav">
      <a class="sa-shop-logo" href="<?= e(app_url('')) ?>"><?php $renderLogo(); ?></a>
      <?php $isShopBrowse = ($_GET['browse'] ?? '') === '1'; $isMarketplaceRoute = !$isShopBrowse; ?>
      <div class="sa-shop-links" id="sa-shop-menu">
        <a href="<?= e(app_url('')) ?>">Home</a>
        <a<?= $isMarketplaceRoute ? ' class="is-active"' : '' ?> href="<?= e(app_url('shop')) ?>">Marketplace</a>
        <a<?= $isShopBrowse ? ' class="is-active" aria-current="page"' : '' ?> href="<?= e(app_url('shop?browse=1')) ?>">Shop</a>
        <a href="<?= e(app_url('vendors')) ?>">Our Vendors</a>
        <a href="<?= e(app_url('about')) ?>">About Us</a>
        <a href="<?= e(app_url('contact')) ?>">Contact Us</a>
        <?php if (!empty($_SESSION['user_id'])): ?>
          <a class="sa-shop-menu-auth-link is-register" href="<?= e(app_url('affiliate/join')) ?>">Affiliate Sign Up</a>
          <a class="sa-shop-menu-auth-link is-login" href="<?= e(app_url('vendor/register')) ?>">Vendor Register</a>
        <?php else: ?>
          <a class="sa-shop-menu-auth-link is-register" href="<?= e(app_url('affiliate/join')) ?>">Affiliate Sign Up</a>
          <a class="sa-shop-menu-auth-link is-login" href="<?= e(app_url('vendor/register')) ?>">Vendor Register</a>
        <?php endif; ?>
      </div>
      <div class="sa-shop-head-actions">
        <a class="sa-shop-cart" href="<?= e(app_url('cart')) ?>"><?= $icon('bag') ?><span data-cart-count><?= number_format($cartCount) ?></span></a>
        <?php if (!empty($_SESSION['user_id'])): ?>
          <a class="sa-shop-btn is-ghost" href="<?= e(app_url('buyer')) ?>">My Account</a>
          <a class="sa-shop-btn is-register" href="<?= e(app_url('logout')) ?>">Sign Out</a>
        <?php else: ?>
          <a class="sa-shop-btn is-ghost" href="<?= e(app_url('login')) ?>">Login</a>
          <a class="sa-shop-btn is-register" href="<?= e(app_url('buyer/register')) ?>">Register</a>
        <?php endif; ?>
        <button class="sa-shop-menu-toggle" type="button" aria-controls="sa-shop-menu" aria-expanded="false"><span></span></button>
      </div>
    </nav>
    <form class="sa-shop-header-search" action="<?= e(app_url('shop')) ?>" method="get">
      <?php foreach (['brand', 'min_price', 'max_price', 'section', 'category', 'sort'] as $hiddenFilter): ?>
        <?php $hiddenValue = trim((string)($filters[$hiddenFilter] ?? '')); ?>
        <?php if ($hiddenValue !== '' && !($hiddenFilter === 'sort' && $hiddenValue === 'default')): ?>
          <input type="hidden" name="<?= e($hiddenFilter) ?>" value="<?= e($hiddenValue) ?>">
        <?php endif; ?>
      <?php endforeach; ?>
      <input type="search" name="q" value="<?= e((string)($filters['q'] ?? '')) ?>" placeholder="Search for garri, ankara fabric, hibiscus tea...">
      <button type="submit">
        <span class="sa-shop-header-search__icon">🔍</span>
        <span class="sa-shop-header-search__text">Search</span>
      </button>
    </form>
  </header>

  <?php if (!$hasFocusedCatalog): ?>
    <section class="sa-shop-hero" aria-label="<?= e($brandName) ?> hero">
      <div class="sa-shop-hero__inner">
        <span class="sa-shop-hero__eyebrow"></span>
        <h1 class="sa-shop-hero__title">Start shopping or <em>start selling.</em></h1>
        <p class="sa-shop-hero__subtitle">Two sides of the same marketplace. Pick yours.</p>
        <div class="sa-shop-hero__ctas">
          <a class="sa-shop-btn is-gold" href="#shop-categories">Shop now <?= $icon('arrow') ?></a>
          <a class="sa-shop-btn" href="<?= e(app_url('vendor/register')) ?>">Become a vendor</a>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!$hasFocusedCatalog && $shopSlides !== []): ?>
    <section class="sa-shop-slider" data-shop-slider aria-label="Shop slider">
      <div class="sa-shop-slider__track">
        <?php foreach ($shopSlides as $index => $slide): ?>
          <article class="sa-shop-slider__slide" aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>">
            <picture>
              <source media="(max-width: 620px)" srcset="<?= e((string)$slide['mobile']) ?>">
              <img src="<?= e((string)$slide['desktop']) ?>" alt="<?= e((string)$slide['title']) ?>" <?= $index === 0 ? 'loading="eager"' : 'loading="lazy"' ?> decoding="async">
            </picture>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="sa-shop-slider__dots" aria-label="Slider controls">
        <?php foreach ($shopSlides as $index => $_slide): ?>
          <button type="button" class="<?= $index === 0 ? 'is-active' : '' ?>" data-shop-slide="<?= (int)$index ?>" aria-label="Show slide <?= (int)($index + 1) ?>"></button>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!$hasFocusedCatalog): ?>
  <section class="sa-shop-signup-strip" aria-label="Join Seller Africa">
    <div>
      <h2>Sell or earn with <?= e($brandName) ?>.</h2>
      <p>Open a vendor store or join the affiliate community and help more shoppers discover authentic marketplace products.</p>
    </div>
    <div class="sa-shop-signup-strip__actions">
      <a class="sa-shop-btn is-vendor" href="<?= e(app_url('vendor/register')) ?>">Sign up as a Vendor</a>
      <a class="sa-shop-btn is-ghost" href="<?= e(app_url('affiliate/join')) ?>">Sign up as an Affiliate</a>
    </div>
  </section>
  <?php endif; ?>

  <main class="sa-shop-wrap<?= $hasFocusedCatalog ? ' sa-shop-focused-page' : '' ?>">

    <?php if ($hasFocusedCatalog): ?>
      <section class="sa-shop-section" id="shop-results">
        <div class="sa-shop-title-row">
          <h1><?= e($activeSection !== '' ? 'All ' . $activeSection : 'Shop Results') ?></h1>
          <a href="<?= e(app_url('shop')) ?>">Clear Filters <?= $icon('arrow') ?></a>
        </div>
        <form class="sa-shop-results-filters" action="<?= e(app_url('shop')) ?>" method="get">
          <input type="hidden" name="q" value="<?= e((string)($filters['q'] ?? '')) ?>">
          <input type="hidden" name="section" value="<?= e($activeSection) ?>">
          <?php foreach (['min_price', 'max_price', 'brand', 'rating'] as $filterKey): ?>
            <input type="hidden" name="<?= e($filterKey) ?>" value="<?= e((string)($filters[$filterKey] ?? '')) ?>">
          <?php endforeach; ?>
          <select name="category" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php $activeCategory = (string)($filters['category'] ?? ''); ?>
            <?php foreach ($categories as $catOpt): ?>
              <?php $catOptSlug = (string)$catOpt['slug']; ?>
              <option value="<?= e($catOptSlug) ?>" <?= $activeCategory === $catOptSlug ? 'selected' : '' ?>><?= e((string)$catOpt['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="sort" onchange="this.form.submit()">
            <?php $activeSort = (string)($filters['sort'] ?? 'default'); ?>
            <option value="default" <?= $activeSort === 'default' ? 'selected' : '' ?>>Sort: Relevance</option>
            <option value="newest" <?= $activeSort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="on_sale" <?= $activeSort === 'on_sale' ? 'selected' : '' ?>>On Sale</option>
            <option value="top_selling" <?= $activeSort === 'top_selling' ? 'selected' : '' ?>>Best Selling</option>
            <option value="price_asc" <?= $activeSort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
            <option value="price_desc" <?= $activeSort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
          </select>
        </form>
        <?php if ($products !== []): ?>
          <div class="sa-shop-grid">
            <?php foreach ($products as $product): ?>
              <?php $renderProduct($product, in_array((string)($filters['sort'] ?? ''), ['on_sale'], true) || str_contains(strtolower($activeSection), 'sale') || str_contains(strtolower($activeSection), 'deal')); ?>
            <?php endforeach; ?>
          </div>
          <?php $currentPage = (int)($currentPage ?? 1); $totalPages = (int)($totalPages ?? 1); ?>
          <?php if ($totalPages > 1): ?>
            <nav class="sa-shop-pagination" aria-label="Product pages">
              <?php if ($currentPage > 1): ?>
                <a href="<?= e($shopUrl(array_merge($filters, ['page' => $currentPage - 1]))) ?>">&larr; Previous</a>
              <?php endif; ?>
              <span class="sa-shop-pagination__status">Page <?= $currentPage ?> of <?= $totalPages ?></span>
              <?php if ($currentPage < $totalPages): ?>
                <a href="<?= e($shopUrl(array_merge($filters, ['page' => $currentPage + 1]))) ?>">Next &rarr;</a>
              <?php endif; ?>
            </nav>
          <?php endif; ?>
        <?php else: ?>
          <p>No products matched this selection yet.</p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if (!$hasFocusedCatalog): ?>
    <section class="sa-shop-section sa-shop-live-panel" data-shop-live-panel aria-live="polite">
      <div class="sa-shop-title-row">
        <div>
          <h2 data-shop-live-title>Category products</h2>
        </div>
        <a href="<?= e(app_url('shop')) ?>" data-shop-live-view-all>View All <?= $icon('arrow') ?></a>
      </div>
      <div class="sa-shop-grid" data-shop-live-grid></div>
    </section>

    <section class="sa-shop-section">
      <div class="sa-shop-title-row" id="shop-categories"><h1>Shop categories</h1></div>
      <div class="sa-shop-cats">
        <?php foreach ($categoryGroups as $label => $slugs): ?>
          <?php $cat = $findCategory($slugs); ?>
          <?php $categorySlug = (string)($cat['slug'] ?? $slugs[0] ?? ''); ?>
          <a class="sa-shop-cat" href="<?= e($categoryUrl($slugs)) ?>" data-shop-category-link data-category="<?= e($categorySlug) ?>" data-category-label="<?= e($label) ?>">
            <?= $categoryIconSvg($label) ?>
            <span><?= e($label) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="sa-shop-swipe-note">Swipe to see more</div>
    </section>

    <section class="sa-shop-section">
      <div class="sa-shop-title-row"><h2>Shop by Region</h2></div>
      <div class="sa-shop-regions">
        <a class="sa-shop-region" href="<?= e($shopUrl(['q' => 'nigeria'])) ?>">
          <span class="sa-shop-region__flag">🇳🇬</span>
          <span class="sa-shop-region__label">West Africa</span>
        </a>
        <a class="sa-shop-region" href="<?= e($shopUrl(['q' => 'jamaica'])) ?>">
          <span class="sa-shop-region__flag">🇯🇲</span>
          <span class="sa-shop-region__label">Caribbean</span>
        </a>
        <a class="sa-shop-region" href="<?= e($shopUrl(['q' => 'kenya'])) ?>">
          <span class="sa-shop-region__flag">🇰🇪</span>
          <span class="sa-shop-region__label">East Africa</span>
        </a>
        <a class="sa-shop-region" href="<?= e(app_url('freshroots')) ?>">
          <span class="sa-shop-region__flag">🇺🇸</span>
          <span class="sa-shop-region__label">U.S. Farm fresh</span>
        </a>
      </div>
    </section>

    <?php if (!empty($shopBanners[0]['image'])): ?>
      <a class="sa-shop-banner-break" href="<?= e((string)$shopBanners[0]['url']) ?>">
        <img src="<?= e((string)$shopBanners[0]['image']) ?>" alt="<?= e((string)$shopBanners[0]['title']) ?>" loading="lazy">
      </a>
    <?php endif; ?>

    <a class="sa-shop-intersection-banner" href="<?= e(app_url('shop')) ?>">
      <img src="<?= e(app_url('assets/images/sac-banner-customers.jpeg?v=20261002-restored')) ?>" alt="Customers are already searching for authentic African products" loading="lazy">
    </a>

    <?php $renderedProductSectionCount = 0; ?>
    <?php $papBannerShown = false; ?>
    <?php foreach ($sections as $title => $items): ?>
      <?php $items = array_values(array_filter((array)$items, 'is_array')); if ($items === []) continue; ?>
      <?php if (!$papBannerShown && (string)$title === 'Deals and Savings'): ?>
        <?php $papBannerShown = true; ?>
        <a class="sa-shop-intersection-banner" href="<?= e(app_url('shop')) ?>">
          <img src="<?= e(app_url('assets/images/sac-banner-pap.jpeg?v=20261002-restored')) ?>" alt="Get the taste of home, browse our marketplace" loading="lazy">
        </a>
      <?php endif; ?>
      <?php if ((string)$title === 'Best Sellers'): ?>
        <a class="sa-shop-intersection-banner" href="mailto:hello@sellerafrica.com">
          <img src="<?= e(app_url('assets/images/sac-banner-advert.jpeg?v=20261002-restored')) ?>" alt="Advertise on SAC Marketplace" loading="lazy">
        </a>
      <?php endif; ?>
      <section class="sa-shop-section">
        <div class="sa-shop-title-row">
          <h2><?= e((string)$title) ?></h2>
          <a href="<?= e($sectionViewAllUrl((string)$title)) ?>">View All <?= $icon('arrow') ?></a>
        </div>
        <?php if (in_array($title, ['Featured Products', 'Best Sellers'], true)): ?>
          <div class="sa-shop-tabs">
            <?php foreach (array_slice($categoryGroups, 0, 8, true) as $label => $slugs): ?>
              <?php $cat = $findCategory($slugs); ?>
              <?php $categorySlug = (string)($cat['slug'] ?? $slugs[0] ?? ''); ?>
              <a href="<?= e($shopUrl(['section' => $title, 'category' => $categorySlug, 'sort' => $title === 'Best Sellers' ? 'top_selling' : 'default'])) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="sa-shop-swipe-note">Swipe to see more</div>
        <div class="sa-shop-grid">
          <?php foreach (array_slice($items, 0, 8) as $product): ?>
            <?php $renderProduct($product, in_array($title, ['On Sale', 'Deals and Savings', 'Under $10 Items', 'Hot Deals', 'Special Offers'], true)); ?>
          <?php endforeach; ?>
        </div>
      </section>
      <?php $renderedProductSectionCount++; ?>
      <?php if ($renderedProductSectionCount === 1): ?>
        <?php if (!empty($shopBanners[1]['image'])): ?>
          <a class="sa-shop-banner-break" href="<?= e((string)$shopBanners[1]['url']) ?>">
            <img src="<?= e((string)$shopBanners[1]['image']) ?>" alt="<?= e((string)$shopBanners[1]['title']) ?>" loading="lazy">
          </a>
        <?php endif; ?>
        <?php if ($hotDeals !== []): ?>
          <section class="sa-shop-section">
            <div class="sa-shop-title-row">
              <div>
                <h2>Sales &amp; Deals</h2>
              </div>
              <a href="<?= e($sectionViewAllUrl('Hot Deals')) ?>">View All <?= $icon('arrow') ?></a>
            </div>
            <div class="sa-shop-swipe-note">Swipe to see more</div>
            <div class="sa-shop-grid">
              <?php foreach (array_slice($hotDeals, 0, 8) as $product): ?>
                <?php $renderProduct($product, true); ?>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>
      <?php endif; ?>
    <?php endforeach; ?>

    <a class="sa-shop-intersection-banner" href="<?= e(app_url('affiliate-registration')) ?>">
      <img src="<?= e(app_url('assets/images/sac-banner-affiliate.jpeg')) ?>" alt="Become a vendor or affiliate with Seller Africa" loading="lazy">
    </a>

    <section class="sa-shop-section sa-shop-shipping">
      <div class="sa-shop-title-row"><h2>Shipping Africa, the Caribbean &amp; beyond</h2></div>
      <div class="sa-shop-shipping__grid">
        <div class="sa-shop-shipping__item">
          <span class="sa-shop-shipping__flag">🇺🇸</span>
          <h3>U.S. Warehouse</h3>
          <p>Vendors store and ship from our stateside warehouse for faster delivery across North America.</p>
        </div>
        <div class="sa-shop-shipping__item">
          <span class="sa-shop-shipping__flag">🌍</span>
          <h3>Africa &amp; Caribbean Sourcing</h3>
          <p>Authentic products sourced directly from vetted vendors across Africa and the Caribbean.</p>
        </div>
        <div class="sa-shop-shipping__item">
          <span class="sa-shop-shipping__flag">📦</span>
          <h3>Aramex-Powered Delivery</h3>
          <p>Real-time shipping estimates and reliable international courier partnerships on every order.</p>
        </div>
      </div>
    </section>

    <section class="sa-shop-section sa-shop-story">
      <div class="sa-shop-story__inner">
        <span class="sa-shop-story__eyebrow">Our Story</span>
        <h2>Building the bridge between Africa, the Caribbean &amp; the world</h2>
        <p><?= e($brandName) ?> exists to connect African and Caribbean producers with buyers everywhere, giving vendors real market access and giving customers a trusted marketplace for the taste, craft, and culture of home.</p>
      </div>
    </section>

    <section class="sa-shop-section sa-shop-reviews">
      <div class="sa-shop-reviews__top">
        <div class="sa-shop-reviews__head">
          <h2>What our customers say</h2>
          <p>Verified customer-style feedback from marketplace shoppers.</p>
        </div>
        <div class="sa-shop-trust"><span>★</span> Trustpilot</div>
      </div>
      <div class="sa-shop-review-track">
        <?php foreach ($testimonials as $testimonial): ?>
          <article class="sa-shop-review">
            <div class="sa-shop-review__stars" aria-label="5 star review">★★★★★</div>
            <p><?= e($testimonial[2]) ?></p>
            <strong><?= e($testimonial[0]) ?></strong>
            <small><?= e($testimonial[1]) ?> · 1 review</small>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="sa-shop-section sa-shop-partners">
      <div class="sa-shop-partners__head">
        <h2>Our Partners</h2>
        <p>Finance, export, and logistics partners helping African and Caribbean commerce move further.</p>
      </div>
      <div class="sa-shop-partner-marquee" aria-label="<?= e($brandName) ?> partners">
        <div class="sa-shop-partner-track">
          <?php for ($loop = 0; $loop < 2; $loop++): ?>
            <?php foreach ($partners as $partner): ?>
              <a class="sa-shop-partner-logo" href="<?= e((string)$partner[1]) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e((string)$partner[0]) ?>">
                <img src="<?= e((string)$partner[2]) ?>" alt="<?= e((string)$partner[0]) ?> logo" loading="lazy" decoding="async">
              </a>
            <?php endforeach; ?>
          <?php endfor; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <section class="sa-shop-section sa-shop-newsletter">
      <div class="sa-shop-newsletter__inner">
        <h2>Get 10% off your first order</h2>
        <p>Join the community for early access to new vendors, flash deals, and diaspora favorites.</p>
        <form class="sa-shop-newsletter__form" data-shop-newsletter-form>
          <input type="email" name="email" placeholder="Enter your email" required>
          <button type="submit">Subscribe</button>
        </form>
        <p class="sa-shop-newsletter__note" data-shop-newsletter-note hidden>Thanks! We'll be in touch.</p>
      </div>
    </section>
  </main>
  <?php if (!$hasFocusedCatalog): ?>
  <?php $footerClass = 'sa-shop-footer'; $footerGridClass = 'sa-shop-footer__grid'; include __DIR__ . '/../partials/standard-footer.php'; ?>
  <?php endif; ?>
  <a class="sa-shop-floating-cart" href="<?= e(app_url('cart')) ?>"><?= $icon('bag') ?><span data-cart-count><?= number_format($cartCount) ?></span> Cart</a>
  <div class="sa-shop-cookie-banner" data-sa-cookie-banner>
    <p>We use cookies to improve your experience and keep your account secure. By using Seller Africa, you agree to our use of cookies.</p>
    <button type="button" data-sa-cookie-accept>Accept</button>
  </div>
</div>

<?= app_chat_widget_embed() ?>

<script>
(() => {
  const root = document.querySelector('.sa-shop');
  if (!root) return;
  const cartEndpoint = <?= json_encode(app_url('api/cart/add'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const categoryEndpoint = <?= json_encode(app_url('api/storefront/category-products'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const checkoutUrl = <?= json_encode(app_url('storefront/checkout'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const fallbackImage = <?= json_encode(app_url('assets/images/product-placeholder.svg'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const replaceFailedProductImage = (img) => {
    if (!(img instanceof HTMLImageElement) || !img.closest('.sa-shop-card__image') || img.dataset.fallbackApplied) return;
    img.dataset.fallbackApplied = '1';
    img.src = fallbackImage;
  };
  document.addEventListener('error', (event) => replaceFailedProductImage(event.target), true);
  document.querySelectorAll('.sa-shop-card__image img').forEach((img) => {
    if (img.complete && img.naturalWidth === 0) replaceFailedProductImage(img);
  });
  const iconHeart = <?= json_encode($icon('heart'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const iconArrow = <?= json_encode($icon('arrow'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const menuToggle = root.querySelector('.sa-shop-menu-toggle');
  const menu = root.querySelector('#sa-shop-menu');
  const livePanel = root.querySelector('[data-shop-live-panel]');
  const liveTitle = root.querySelector('[data-shop-live-title]');
  const liveMeta = root.querySelector('[data-shop-live-meta]');
  const liveGrid = root.querySelector('[data-shop-live-grid]');
  const liveViewAll = root.querySelector('[data-shop-live-view-all]');
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char]));

  menuToggle?.addEventListener('click', () => {
    const isOpen = root.classList.toggle('is-menu-open');
    menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  menu?.addEventListener('click', (event) => {
    if (!event.target.closest('a')) return;
    root.classList.remove('is-menu-open');
    menuToggle?.setAttribute('aria-expanded', 'false');
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 1100) {
      root.classList.remove('is-menu-open');
      menuToggle?.setAttribute('aria-expanded', 'false');
    }
  });

  const statsGrid = root.querySelector('[data-sa-stats-grid]');
  if (statsGrid) {
    let statsAnimated = false;
    const animateCount = (el) => {
      const target = parseInt(el.getAttribute('data-sa-count-target') || '0', 10);
      const suffix = el.getAttribute('data-sa-count-suffix') || '';
      const duration = 1400;
      const start = performance.now();
      const step = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        const value = Math.floor(eased * target);
        el.textContent = value.toLocaleString('en-US') + suffix;
        if (progress < 1) {
          requestAnimationFrame(step);
        } else {
          el.textContent = target.toLocaleString('en-US') + suffix;
        }
      };
      requestAnimationFrame(step);
    };
    const statsObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting && !statsAnimated) {
          statsAnimated = true;
          statsGrid.querySelectorAll('[data-sa-count-target]').forEach(animateCount);
          statsObserver.disconnect();
        }
      });
    }, { threshold: 0.4 });
    statsObserver.observe(statsGrid);
  }

  const cookieBanner = document.querySelector('[data-sa-cookie-banner]');
  if (cookieBanner && !localStorage.getItem('sa_cookie_consent')) {
    setTimeout(() => {
      cookieBanner.classList.add('is-visible');
    }, 800);
    cookieBanner.querySelector('[data-sa-cookie-accept]')?.addEventListener('click', () => {
      localStorage.setItem('sa_cookie_consent', 'accepted');
      cookieBanner.classList.remove('is-visible');
    });
  }

  const productCardHtml = (product) => {
    const id = Number.parseInt(product?.id || '0', 10);
    const name = String(product?.name || 'Seller Africa product');
    const url = String(product?.url || <?= json_encode(app_url('shop'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>);
    const image = String(product?.image || product?.fallbackImage || fallbackImage);
    const descriptionRaw = String(product?.excerpt || product?.description || 'Authentic marketplace product from a Seller Africa vendor.').replace(/<[^>]*>/g, '').trim();
    const description = descriptionRaw.length > 112 ? `${descriptionRaw.slice(0, 109)}...` : descriptionRaw;
    const price = String(product?.price || 'View price');
    const regular = String(product?.regularPrice || '');
    return `<article class="sa-shop-card">
      <button class="sa-shop-card__heart" type="button" aria-label="Save product">${iconHeart}</button>
      <a class="sa-shop-card__image" href="${escapeHtml(url)}"><img src="${escapeHtml(image)}" alt="${escapeHtml(name)}" loading="lazy" decoding="async"></a>
      <div class="sa-shop-card__body">
        <h3><a href="${escapeHtml(url)}">${escapeHtml(name)}</a></h3>
        <div class="sa-shop-stars">☆☆☆☆☆ <span>0.0</span></div>
        <p>${escapeHtml(description || 'Authentic marketplace product from a Seller Africa vendor.')}</p>
        <div class="sa-shop-price">${regular ? `<del>${escapeHtml(regular)}</del>` : ''}<strong>${escapeHtml(price)}</strong></div>
        <div class="sa-shop-actions">
          <button data-add-cart="${escapeHtml(id)}"${id <= 0 ? ' disabled' : ''}>Add to Basket</button>
          <button class="is-secondary" data-buy-now="${escapeHtml(id)}"${id <= 0 ? ' disabled' : ''}>Buy Now</button>
        </div>
      </div>
    </article>`;
  };
  const categoryFromUrl = (url) => {
    try {
      return new URL(url, window.location.href).searchParams.get('category') || '';
    } catch (_error) {
      return '';
    }
  };
  const setActiveCategoryLink = (category) => {
    root.querySelectorAll('[data-shop-category-link]').forEach((link) => {
      const linkCategory = link.dataset.category || categoryFromUrl(link.href);
      link.classList.toggle('is-active', Boolean(category) && linkCategory === category);
    });
  };
  const loadCategory = async (link) => {
    const category = link.dataset.category || categoryFromUrl(link.href);
    if (!category || !livePanel || !liveGrid || !liveTitle || !liveViewAll) {
      window.location.assign(link.href);
      return;
    }
    const label = link.dataset.categoryLabel || link.textContent.trim() || 'Category';
    const params = new URLSearchParams({ category, label, limit: '12' });
    const targetUrl = new URL(link.href, window.location.href);
    ['q', 'brand', 'rating', 'min_price', 'max_price', 'sort'].forEach((key) => {
      const value = targetUrl.searchParams.get(key);
      if (value) params.set(key, value);
    });
    livePanel.classList.add('is-visible', 'is-loading');
    liveTitle.textContent = label;
    if (liveMeta) liveMeta.textContent = '';
    liveGrid.innerHTML = '<div class="sa-shop-skeleton"></div><div class="sa-shop-skeleton"></div><div class="sa-shop-skeleton"></div><div class="sa-shop-skeleton"></div>';
    livePanel.scrollIntoView({ behavior: 'smooth', block: 'start' });

    try {
      const response = await fetch(`${categoryEndpoint}?${params.toString()}`, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok) throw new Error(payload.message || 'Category failed');
      const products = Array.isArray(payload.products) ? payload.products : [];
      liveTitle.textContent = label;
      if (liveMeta) liveMeta.textContent = '';
      liveViewAll.href = payload.viewAllUrl || link.href;
      liveViewAll.innerHTML = `View All ${iconArrow}`;
      liveGrid.innerHTML = products.length
        ? products.map(productCardHtml).join('')
        : '<p class="sa-shop-live-panel__empty">No products are available in this category yet.</p>';
      setActiveCategoryLink(category);
      window.history.pushState({ shopCategory: category }, '', link.href);
    } catch (_error) {
      liveGrid.innerHTML = '<p class="sa-shop-live-panel__empty">This category could not load right now. Please try again.</p>';
      if (liveMeta) liveMeta.textContent = '';
      liveViewAll.href = link.href;
    } finally {
      livePanel.classList.remove('is-loading');
    }
  };
  const setCartCount = (value) => {
    const count = Number.parseInt(value, 10);
    if (!Number.isFinite(count) || count < 0) return;
    root.querySelectorAll('[data-cart-count]').forEach((node) => { node.textContent = count.toLocaleString(); });
  };
  const slider = document.querySelector('[data-shop-slider]');
  const sliderTrack = slider?.querySelector('.sa-shop-slider__track');
  const sliderSlides = Array.from(slider?.querySelectorAll('.sa-shop-slider__slide') || []);
  const sliderDots = Array.from(slider?.querySelectorAll('[data-shop-slide]') || []);
  let activeSlide = 0;
  let sliderTimer = null;
  const showSlide = (index) => {
    if (!sliderTrack || sliderSlides.length === 0) return;
    activeSlide = (index + sliderSlides.length) % sliderSlides.length;
    sliderTrack.style.transform = `translateX(-${activeSlide * 100}%)`;
    sliderSlides.forEach((slide, slideIndex) => slide.setAttribute('aria-hidden', slideIndex === activeSlide ? 'false' : 'true'));
    sliderDots.forEach((dot, dotIndex) => dot.classList.toggle('is-active', dotIndex === activeSlide));
  };
  const restartSlider = () => {
    if (sliderTimer) window.clearInterval(sliderTimer);
    if (sliderSlides.length > 1 && window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
      sliderTimer = window.setInterval(() => showSlide(activeSlide + 1), 5200);
    }
  };
  sliderDots.forEach((dot) => {
    dot.addEventListener('click', () => {
      showSlide(Number.parseInt(dot.dataset.shopSlide || '0', 10));
      restartSlider();
    });
  });
  let touchStartX = null;
  slider?.addEventListener('touchstart', (event) => {
    touchStartX = event.touches[0]?.clientX ?? null;
  }, { passive: true });
  slider?.addEventListener('touchend', (event) => {
    if (touchStartX === null) return;
    const delta = (event.changedTouches[0]?.clientX ?? touchStartX) - touchStartX;
    if (Math.abs(delta) > 42) {
      showSlide(activeSlide + (delta < 0 ? 1 : -1));
      restartSlider();
    }
    touchStartX = null;
  }, { passive: true });
  restartSlider();
  const addToCart = (productId) => {
    const body = new URLSearchParams();
    body.set('product_id', String(productId));
    body.set('quantity', '1');
    return fetch(cartEndpoint, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body, credentials:'same-origin' })
      .then((response) => response.ok ? response.json() : Promise.reject(new Error('Cart failed')))
      .then((payload) => { if (payload?.redirect) { window.location.assign(payload.redirect); return payload; } if (payload && typeof payload.cart_count !== 'undefined') setCartCount(payload.cart_count); return payload; });
  };
  root.addEventListener('click', (event) => {
    const categoryLink = event.target.closest('[data-shop-category-link]');
    if (categoryLink && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey) {
      event.preventDefault();
      loadCategory(categoryLink);
      return;
    }

    const add = event.target.closest('[data-add-cart]');
    const buy = event.target.closest('[data-buy-now]');
    const button = add || buy;
    if (!button || button.disabled) return;
    event.preventDefault();
    const id = Number.parseInt(button.dataset.addCart || button.dataset.buyNow || '0', 10);
    if (!id) return;
    const label = button.textContent;
    button.disabled = true;
    button.textContent = buy ? 'Opening...' : 'Adding...';
    addToCart(id).then((payload) => {
      if (payload?.redirect) return;
      if (!payload?.ok) throw new Error(payload?.message || 'Unable to add product');
      if (buy) { window.location.assign(checkoutUrl); return; }
      button.textContent = 'Added';
      window.setTimeout(() => { button.textContent = label; button.disabled = false; }, 1000);
    }).catch(() => {
      button.textContent = 'Try again';
      window.setTimeout(() => { button.textContent = label; button.disabled = false; }, 1200);
    });
  });

  const newsletterForm = document.querySelector('[data-shop-newsletter-form]');
  const newsletterNote = document.querySelector('[data-shop-newsletter-note]');
  newsletterForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    if (newsletterNote) {
      newsletterNote.hidden = false;
    }
    newsletterForm.reset();
  });
})();
</script>
