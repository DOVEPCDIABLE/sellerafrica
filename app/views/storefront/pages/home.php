<?php
$brand = app_branding();
$brandName = trim((string)($brand['name'] ?? 'Seller Africa')) ?: 'Seller Africa';
$brandLogo = trim((string)($brand['logo'] ?? ''));
$searchQuery = trim((string)($filters['q'] ?? ''));
$products = array_values(array_filter((array)($products ?? []), 'is_array'));
$categories = array_values(array_filter((array)($categories ?? []), 'is_array'));
$newArrivals = array_values(array_filter((array)($newArrivals ?? []), 'is_array'));
$bestSellers = array_values(array_filter((array)($bestSellers ?? []), 'is_array'));
$onSale = array_values(array_filter((array)($onSale ?? []), 'is_array'));
$suggestedForYou = array_values(array_filter((array)($suggestedForYou ?? []), 'is_array'));
$heroProducts = array_values(array_filter((array)($heroProducts ?? []), 'is_array'));
$cartCount = (int)($cartCount ?? 0);

$asset = static fn (string $path): string => app_url($path);
$imageFallbacks = [
    $asset('assets/images/md1.png'),
    $asset('assets/images/md3.png'),
    $asset('storefront/assets/img/category/cat-1-2.jpg'),
    $asset('storefront/assets/img/banner/banner-bg-1-2.jpg'),
    $asset('storefront/assets/img/feature/feature-1-3.jpg'),
    $asset('storefront/assets/img/banner/drinks-marketplace-1.png'),
];

$heroImages = [
    ['url' => app_url('shop'), 'image' => 'https://sellerafrica.com/wp-content/uploads/2026/06/cropped-OGBONO-POWDER-600x600.jpeg', 'name' => $brandName . ' marketplace image 1', 'price' => ''],
    ['url' => app_url('shop'), 'image' => 'https://sellerafrica.com/wp-content/uploads/2026/05/CHILLI-PEPPER-100G-600x600.jpeg', 'name' => $brandName . ' marketplace image 2', 'price' => ''],
    ['url' => app_url('shop'), 'image' => 'https://sellerafrica.com/wp-content/uploads/2026/06/cropped-YAM-FLOUR-545x600.png', 'name' => $brandName . ' marketplace image 3', 'price' => ''],
];
$heroCards = array_slice(array_map(static function (array $product) use ($imageFallbacks): array {
    return [
        'url' => (string)($product['url'] ?? app_url('shop')),
        'image' => trim((string)($product['image'] ?? '')) ?: $imageFallbacks[0],
        'name' => (string)($product['name'] ?? 'Seller Africa product'),
        'price' => (string)($product['price'] ?? ''),
    ];
}, $heroProducts), 0, 3);
$heroCards = count($heroCards) === 3 ? $heroCards : $heroImages;
$homeVideo = $asset('assets/images/hom_vid.mp4');

$displayCategories = array_slice($categories, 0, 8);
$distinctCards = [
    ['High-quality Produce', 'Carefully sourced foods, staples, and everyday essentials from active sellers.', 'basket', '#006b52', '#fff6df', $asset('assets/images/High_produce_quality.jpeg')],
    ['Authenticity', 'A real African and Caribbean Marketplace for fashion, beauty, home goods, and everyday essentials in one place.', 'patch-check', '#e58a12', '#f0fbf7', $asset('assets/images/Authenticity.jpeg')],
    ['Community', 'A marketplace for shoppers, vendors, affiliates, and culture-led small businesses.', 'people', '#c2410c', '#fff4df', $asset('assets/images/community.jpeg')],
    ['Easy Payment', 'Simple shopping flows with cart, checkout, and order tracking built in.', 'credit-card', '#006b52', '#fff1e8', $asset('assets/images/easypayment.jpeg')],
];

$features = [
    ['Free shipping', 'First U.S. warehouse order', 'Enjoy free shipping on your first order from our U.S. warehouse.', '#fffbea', '#f59e0b', 'check'],
    ['Affiliate Community', 'Join now', 'Grow with Seller Africa by sharing products with your audience and earning through referrals.', '#ecfbff', '#2563eb', 'users'],
    ['Referral Affiliate', 'Join now', 'Invite shoppers, vendors, and diaspora communities into the marketplace.', '#fff4ff', '#a855f7', 'link'],
    ['Vendor Community', 'Join now', 'Open your store and reach African and Caribbean diaspora buyers worldwide.', '#ecfdf5', '#059669', 'utensils'],
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

$faqs = [
    ['How do I shop on Seller Africa?', 'Browse Marketplace, choose a product, add it to cart, and complete checkout from the secure storefront.'],
    ['Can vendors register separately?', 'Yes. User, vendor, and affiliate registrations are separate so each account gets the right onboarding path.'],
    ['How can I track my order?', 'Use Track Order with your order details to see the latest status.'],
    ['Are categories separated?', 'Yes. Food, fashion, beauty, home, and other departments are displayed in their own category paths.'],
];

$renderLogo = static function () use ($brandLogo, $brandName): void {
    if ($brandLogo !== '') {
        echo '<img src="' . e($brandLogo) . '" alt="' . e($brandName) . '">';
        return;
    }

    echo '<span class="sa-home-logo-mark" aria-hidden="true"></span><strong>' . e($brandName) . '</strong>';
};

$renderIcon = static function (string $name): string {
    $icons = [
        'bag' => '<path d="M7 8V6a5 5 0 0 1 10 0v2"/><path d="M5 8h14l-1 13H6L5 8Z"/>',
        'arrow' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        'apple' => '<path d="M15 4c-1 0-2 .6-2.8 1.4C11.4 4.5 10.5 4 9.4 4 7.2 4 5 6 5 9.6c0 4 2.8 8.4 5.1 8.4 1 0 1.2-.5 2.4-.5s1.6.5 2.6.5c2 0 4.9-4 4.9-7.5C20 6.8 17.5 4 15 4Z"/><path d="M14 3c.3-1.3 1.3-2.2 2.5-2.5.1 1.3-.7 2.5-2.2 3"/>',
        'play' => '<path d="m5 3 14 9L5 21V3Z"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1"/>',
        'ticket' => '<path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 1 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 1 0 0-4V7Z"/><path d="M9 9h6M9 15h6"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
        'utensils' => '<path d="M4 3v8"/><path d="M8 3v8"/><path d="M4 7h4"/><path d="M6 11v10"/><path d="M17 3v18"/><path d="M17 3c2 1 3 3 3 6s-1 5-3 6"/>',
        'check' => '<path d="m20 6-11 11-5-5"/>',
    ];

    return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$name] ?? $icons['check']) . '</svg>';
};

$renderDistinctIcon = static function (string $name): string {
    $paths = [
        'basket' => '<path d="M5.929 1.757a.5.5 0 1 0-.858-.514L2.217 6H.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h.623l1.844 6.456A.75.75 0 0 0 3.69 15h8.622a.75.75 0 0 0 .722-.544L14.877 8h.623a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1.717L10.93 1.243a.5.5 0 1 0-.858.514L12.617 6H3.383zM4 10a1 1 0 0 1 2 0v2a1 1 0 1 1-2 0zm3 0a1 1 0 0 1 2 0v2a1 1 0 1 1-2 0zm4-1a1 1 0 0 1 1 1v2a1 1 0 1 1-2 0v-2a1 1 0 0 1 1-1"/>',
        'patch-check' => '<path fill-rule="evenodd" d="M10.067.87a2.89 2.89 0 0 0-4.134 0l-.622.638-.89-.011a2.89 2.89 0 0 0-2.924 2.924l.01.89-.636.622a2.89 2.89 0 0 0 0 4.134l.637.622-.011.89a2.89 2.89 0 0 0 2.924 2.924l.89-.01.622.636a2.89 2.89 0 0 0 4.134 0l.622-.637.89.011a2.89 2.89 0 0 0 2.924-2.924l-.01-.89.636-.622a2.89 2.89 0 0 0 0-4.134l-.637-.622.011-.89a2.89 2.89 0 0 0-2.924-2.924l-.89.01zm.287 5.984a.5.5 0 0 0-.708-.708L7.5 8.293 6.354 7.146a.5.5 0 1 0-.708.708l1.5 1.5a.5.5 0 0 0 .708 0z"/>',
        'people' => '<path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/><path fill-rule="evenodd" d="M5.216 14A2.24 2.24 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.3 6.3 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1zM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5"/><path fill-rule="evenodd" d="M16 14s1 0 1-1-1-4-5-4a6.3 6.3 0 0 0-1.936.28C11.32 10.25 12 11.645 12 13c0 .35-.075.686-.216 1zM11.5 8a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5"/>',
        'credit-card' => '<path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2H0zm0 2h16v6a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm3 5a1 1 0 1 0 0 2h1a1 1 0 1 0 0-2z"/>',
    ];

    return '<svg viewBox="0 0 16 16" aria-hidden="true" fill="currentColor" focusable="false">' . ($paths[$name] ?? $paths['basket']) . '</svg>';
};

$renderProductCard = static function (array $product, bool $forceCompare = false) use ($imageFallbacks): void {
    $productId = (int)($product['id'] ?? 0);
    $name = (string)($product['name'] ?? 'Seller Africa product');
    $url = (string)($product['url'] ?? app_url('shop'));
    $image = trim((string)($product['image'] ?? '')) ?: $imageFallbacks[0];
    $price = (string)($product['price'] ?? '');
    $regularPrice = (string)($product['regularPrice'] ?? '');
    if ($forceCompare && $regularPrice === '' && isset($product['rawPrice'])) {
        $rawPrice = (float)$product['rawPrice'];
        if ($rawPrice > 0) {
            $regularPrice = \App\StorefrontTemplateService::money(round($rawPrice * 1.2, 2), (string)($product['currency'] ?? 'USD'));
        }
    }
    $vendor = (string)($product['vendor'] ?? 'Seller Africa vendor');
    $category = (string)($product['primaryCategory'] ?? 'Marketplace');
    $rating = (float)($product['rating'] ?? 0);
    $ratingStars = str_repeat('★', (int)round($rating)) . str_repeat('☆', 5 - (int)round($rating));
    $badgeLabel = '';
    $badgeClass = '';
    if ($regularPrice !== '') {
        $badgeLabel = 'Deal';
        $badgeClass = 'sa-home-product__badge--deal';
    } elseif (!empty($product['isNew'])) {
        $badgeLabel = 'New';
        $badgeClass = 'sa-home-product__badge--new';
    } elseif (!empty($product['isBestSeller'])) {
        $badgeLabel = 'Best Seller';
        $badgeClass = 'sa-home-product__badge--best';
    }
    echo '<article class="sa-home-product" data-reveal>';
    echo '<a class="sa-home-product__image" href="' . e($url) . '">';
    if ($badgeLabel !== '') {
        echo '<span class="sa-home-product__badge ' . e($badgeClass) . '">' . e($badgeLabel) . '</span>';
    }
    echo '<img src="' . e($image) . '" alt="' . e($name) . '" loading="lazy"></a>';
    echo '<div class="sa-home-product__body"><span>' . e($category) . '</span><h3><a href="' . e($url) . '">' . e($name) . '</a></h3>';
    echo '<span class="sa-home-product__stars" aria-label="' . e((string)$rating) . ' out of 5 stars">' . $ratingStars . '</span>';
    echo '<p>' . e($vendor) . '</p>';
    echo '<div class="sa-home-product__price">';
    if ($regularPrice !== '') {
        echo '<del>' . e($regularPrice) . '</del>';
    }
    echo '<strong>' . e($price !== '' ? $price : 'View price') . '</strong></div>';
    echo '<div class="sa-home-product__actions">';
    echo '<button type="button" data-add-cart="' . e((string)$productId) . '"' . ($productId <= 0 ? ' disabled' : '') . '>Add to Cart</button>';
    echo '<button type="button" class="is-secondary" data-buy-now="' . e((string)$productId) . '"' . ($productId <= 0 ? ' disabled' : '') . '>Buy Now</button>';
    echo '</div></div></article>';
};
?>

<style>
  body.sa-home-page {
    background: #fff;
  }

  .sa-home {
    --font-unageo: "Unageo", "DM Sans", system-ui, -apple-system, sans-serif;
    --font-aileron: "Aileron", "DM Sans", Arial, Helvetica, sans-serif;
    --sa-sans: "DM Sans", system-ui, -apple-system, sans-serif;
    --page-gutter: clamp(32px, 6.25vw, 128px);
    --page-max: 1536px;
    --radius: .625rem;
    --green: #006b52;
    --green-2: #0d7c63;
    --mint: #e7fbf5;
    --ink: #202326;
    --neutral-700: #535f5d;
    --muted: #68716e;
    --soft: #fffaf2;
    --line: #e7e4dc;
    --border: #e5e5e5;
    --ring: #a1a1a1;
    --card: #fff;
    --secondary: #f5f5f5;
    --gold: #d8951a;
    --coral: #e8734a;
    box-sizing: border-box;
    scroll-behavior: smooth;
    font-family: var(--font-aileron);
    color: var(--neutral-700);
    background: var(--card);
    letter-spacing: 0;
    overflow: hidden;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    -webkit-text-size-adjust: 100%;
    text-rendering: geometricPrecision;
  }

  .sa-home *,
  .sa-home *::before,
  .sa-home *::after {
    box-sizing: border-box;
    border-color: var(--border);
    outline-color: var(--ring);
  }

  .sa-home a {
    color: inherit;
    text-decoration: none;
  }

  .sa-home img {
    max-width: 100%;
  }

  .sa-home__announce {
    min-height: 38px;
    display: grid;
    place-items: center;
    padding: 6px 16px;
    background: var(--mint);
    color: #153d35;
    font-size: 16px;
    font-family: var(--font-unageo);
    font-weight: 600;
    text-align: center;
  }

  .sa-home__header {
    position: sticky;
    top: 0;
    z-index: 60;
    background: rgba(255, 255, 255, .96);
    backdrop-filter: blur(14px);
    border-bottom: 1px solid rgba(231, 228, 220, .55);
  }

  .sa-home__nav {
    width: min(var(--page-max), calc(100% - var(--page-gutter)));
    min-height: 92px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 32px;
  }

  .sa-home__logo {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: var(--green);
    font-size: 20px;
    font-family: var(--font-unageo);
    font-weight: 800;
    line-height: 1;
  }

  .sa-home__logo img {
    display: block;
    width: auto;
    max-width: 178px;
    max-height: 58px;
    object-fit: contain;
  }

  .sa-home-logo-mark {
    width: 38px;
    height: 38px;
    display: inline-block;
    border: 5px solid #ffb13d;
    border-radius: calc(var(--radius) * 1.2);
    box-shadow: -8px 8px 0 -5px #ffb13d;
  }

  .sa-home__links {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: clamp(24px, 4vw, 72px);
  }

  .sa-home__links a {
    color: #5f6b67;
    font-size: 16px;
    font-family: var(--font-unageo);
    font-weight: 500;
    transition: color .2s ease, transform .2s ease;
  }

  .sa-home__links a:hover {
    color: var(--green);
    transform: translateY(-1px);
  }

  .sa-home__links .sa-home-menu-auth-link {
    display: none;
  }

  .sa-home__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 14px;
  }

  .sa-home__cart {
    width: 46px;
    height: 42px;
    display: inline-grid;
    place-items: center;
    color: #65716d;
    position: relative;
    border: 1px solid var(--green);
    border-radius: var(--radius);
    background: #fff;
  }

  .sa-home__cart:hover {
    color: var(--green);
  }

  .sa-home__cart svg,
  .sa-home-btn svg,
  .sa-home-feature__icon svg,
  .sa-home-app li svg {
    width: 22px;
    height: 22px;
  }

  .sa-home__cart span {
    position: absolute;
    right: -1px;
    top: 0;
    min-width: 18px;
    height: 18px;
    display: grid;
    place-items: center;
    border-radius: 999px;
    background: var(--green);
    color: #fff;
    font-size: 11px;
    font-weight: 800;
  }

  .sa-home-btn {
    min-height: 46px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    border: 1px solid var(--green);
    border-radius: var(--radius);
    padding: 0 22px;
    background: var(--green);
    color: #fff !important;
    font-family: var(--font-unageo);
    font-size: 16px;
    font-weight: 700;
    white-space: nowrap;
    transition: transform .2s ease, background .2s ease, color .2s ease, border-color .2s ease;
  }

  .sa-home-btn:hover {
    transform: translateY(-2px);
    background: #004f3d;
    border-color: #004f3d;
    color: #fff !important;
  }

  .sa-home-btn svg {
    color: currentColor;
    stroke: currentColor;
  }

  .sa-home-btn--ghost {
    background: var(--card);
    color: var(--green) !important;
  }

  .sa-home-btn--ghost:hover {
    background: var(--green);
    color: #fff !important;
  }

  .sa-home-btn--register,
  .sa-home-btn--register:visited,
  .sa-home-btn--register:hover,
  .sa-home-btn--register:focus {
    background: var(--green) !important;
    border-color: var(--green) !important;
    color: #fff !important;
  }

  .sa-home-btn--register * {
    color: inherit !important;
  }

  .sa-home__menu {
    display: none;
    width: 46px;
    height: 44px;
    border: 1px solid var(--green);
    border-radius: var(--radius);
    background: var(--card);
    color: var(--green);
  }

  .sa-home__menu span,
  .sa-home__menu::before,
  .sa-home__menu::after {
    content: "";
    display: block;
    width: 18px;
    height: 2px;
    margin: 4px auto;
    border-radius: 999px;
    background: currentColor;
  }

  .sa-home-section {
    width: min(var(--page-max), calc(100% - var(--page-gutter)));
    margin-inline: auto;
    padding: clamp(76px, 8vw, 128px) 0;
  }

  .sa-home-hero {
    width: 100%;
    max-width: none;
    display: grid;
    place-items: center;
    min-height: clamp(460px, 58vh, 680px);
    padding: clamp(70px, 9vw, 118px) max(24px, calc((100vw - var(--page-max)) / 2));
    background: #004b2f;
    text-align: center;
  }

  .sa-home-hero h1 {
    max-width: 860px;
    margin: 18px auto 0;
    color: #fffdf7;
    font-size: clamp(48px, 7vw, 86px);
    line-height: 1.28;
    font-family: Georgia, 'Iowan Old Style', 'Palatino Linotype', serif;
    font-weight: 500;
    letter-spacing: 0;
  }

  .sa-home-hero h1 span {
    display: block;
    color: inherit;
  }

  .sa-home-hero p {
    max-width: 650px;
    margin: 30px auto 0;
    color: rgba(255, 255, 255, .72);
    font-size: clamp(20px, 2.6vw, 30px);
    line-height: 1.55;
    font-weight: 400;
  }

  .sa-home-hero__eyebrow {
    display: inline-block;
    color: var(--gold);
    font-size: clamp(15px, 1.6vw, 22px);
    font-weight: 900;
    letter-spacing: .13em;
    text-transform: uppercase;
  }

  .sa-home-hero__buttons {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 24px;
    margin-top: 72px;
  }

  .sa-home-hero__buttons .sa-home-btn {
    min-width: min(100%, 278px);
    min-height: 92px;
    border-radius: 12px;
    border-color: rgba(255, 255, 255, .18);
    padding: 0 34px;
    background: rgba(255, 255, 255, .12);
    color: #fff !important;
    font-size: clamp(20px, 2vw, 26px);
    font-weight: 800;
  }

  .sa-home-hero__buttons .sa-home-btn:hover {
    background: rgba(255, 255, 255, .18);
    border-color: rgba(255, 255, 255, .28);
  }

  .sa-home-hero__buttons .sa-home-btn--gold {
    border-color: var(--gold);
    background: var(--gold);
    color: #1d241f !important;
  }

  .sa-home-hero__buttons .sa-home-btn--gold:hover {
    background: #c99100;
    border-color: #c99100;
    color: #1d241f !important;
  }

  .sa-home-hero__media {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(260px, .96fr);
    grid-template-rows: 230px 300px;
    gap: clamp(20px, 2vw, 34px);
  }

  .sa-home-hero__image {
    display: block;
    position: relative;
    overflow: hidden;
    border: 9px solid #fff;
    border-radius: calc(var(--radius) * 1.8);
    background: var(--soft);
    box-shadow: 0 18px 40px rgba(32, 35, 38, .13);
  }

  .sa-home-hero__image img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    object-position: center;
    transition: transform .7s ease;
  }

  .sa-home-hero__image:hover img {
    transform: scale(1.06);
  }

  .sa-home-hero__caption {
    position: absolute;
    left: 14px;
    right: 14px;
    bottom: 14px;
    display: grid;
    gap: 4px;
    padding: 12px 14px;
    border-radius: 8px;
    background: rgba(255, 255, 255, .92);
    color: var(--ink);
    box-shadow: 0 12px 30px rgba(32, 35, 38, .16);
  }

  .sa-home-hero__caption strong,
  .sa-home-hero__caption span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .sa-home-hero__caption strong {
    font-size: 15px;
    font-weight: 850;
  }

  .sa-home-hero__caption span {
    color: var(--green);
    font-size: 13px;
    font-weight: 900;
  }

  .sa-home-hero__image:nth-child(2) {
    grid-row: 1 / 3;
    grid-column: 2;
  }

  .sa-home-hero__image:nth-child(2) img {
    object-position: center;
  }

  .sa-home-soft {
    background: #fffbf5;
  }

  .sa-home-video {
    padding-top: 0;
  }

  .sa-home-video__player {
    overflow: hidden;
    border-radius: calc(var(--radius) * 1.8);
    background: #101816;
    box-shadow: 0 22px 54px rgba(32, 35, 38, .14);
  }

  .sa-home-video__player video {
    width: 100%;
    aspect-ratio: 16 / 9;
    display: block;
    object-fit: cover;
    background: #101816;
  }

  .sa-home-section__head {
    margin-bottom: 34px;
  }

  .sa-home-section__head h2 {
    max-width: 900px;
    margin: 0;
    color: var(--ink);
    font-size: clamp(36px, 4.2vw, 58px);
    line-height: 1.08;
    font-family: Georgia, 'Iowan Old Style', 'Palatino Linotype', serif;
    font-weight: 700;
    letter-spacing: -0.01em;
  }

  .sa-home-section__head p {
    max-width: 680px;
    margin: 20px 0 0;
    color: var(--neutral-700);
    font-size: 18px;
    line-height: 1.55;
  }

  .sa-home-distinct__grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 24px;
  }

  .sa-home-distinct-card {
    min-height: 100%;
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 28px;
    border: 1px solid #f1e7d4;
    border-radius: var(--radius);
    background: #fff4df;
    box-shadow: 0 8px 18px rgba(32, 35, 38, .06);
  }

  .sa-home-distinct-card__icon {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    border-radius: 999px;
    background: #ffe2b5;
    color: #e58a12;
    font-weight: 800;
  }

  .sa-home-distinct-card h3,
  .sa-home-feature h3,
  .sa-home-product h3,
  .sa-home-faq h3 {
    margin: 0;
    color: var(--ink);
    font-family: var(--font-unageo);
    font-weight: 800;
    letter-spacing: 0;
  }

  .sa-home-distinct-card h3 {
    font-size: 27px;
    line-height: 1.1;
  }

  .sa-home-distinct-card p {
    margin: 0;
    color: var(--neutral-700);
    font-size: 17px;
    line-height: 1.45;
  }

  .sa-home-distinct-card__visual {
    width: 100%;
    aspect-ratio: 1.22;
    margin-top: auto;
    border-radius: var(--radius);
    display: block;
    position: relative;
    overflow: hidden;
    background: var(--distinct-bg);
    color: var(--distinct-color);
    border: 1px solid rgba(0, 107, 82, .1);
  }

  .sa-home-distinct-card__visual img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    object-position: center;
    transition: transform .55s ease;
  }

  .sa-home-distinct-card:hover .sa-home-distinct-card__visual img {
    transform: scale(1.05);
  }

  .sa-home-features__grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    grid-template-areas:
      "coins coupon referral"
      "coins recipe recipe";
    gap: 26px;
  }

  .sa-home-feature {
    min-height: 260px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: clamp(28px, 4vw, 46px);
    border: 1px solid rgba(32, 35, 38, .12);
    border-radius: 14px;
    background: var(--feature-bg);
    box-shadow: 0 18px 44px rgba(32, 35, 38, .08);
  }

  .sa-home-feature:first-child {
    grid-area: coins;
    min-height: 540px;
  }

  .sa-home-feature:nth-child(2) {
    grid-area: coupon;
  }

  .sa-home-feature:nth-child(3) {
    grid-area: referral;
  }

  .sa-home-feature:nth-child(4) {
    grid-area: recipe;
  }

  .sa-home-feature__icon {
    width: 52px;
    height: 52px;
    display: grid;
    place-items: center;
    margin-bottom: 28px;
    border-radius: calc(var(--radius) * 1.2);
    background: rgba(255, 255, 255, .72);
    color: var(--feature-color);
  }

  .sa-home-feature h3 {
    font-size: clamp(30px, 3vw, 42px);
    line-height: 1.05;
    font-family: var(--font-unageo);
  }

  .sa-home-feature small {
    display: block;
    margin-top: 10px;
    color: var(--feature-color);
    font-size: 16px;
    font-weight: 600;
  }

  .sa-home-feature p {
    max-width: 640px;
    margin: 22px 0 0;
    color: var(--neutral-700);
    font-size: 18px;
    line-height: 1.55;
  }

  .sa-home-app {
    background: #f6f8fa;
  }

  .sa-home-app__grid {
    display: grid;
    grid-template-columns: minmax(320px, .78fr) minmax(0, 1.22fr);
    gap: clamp(60px, 8vw, 110px);
    align-items: center;
  }

  .sa-home-app__phone {
    max-width: 390px;
    justify-self: center;
    overflow: hidden;
    border-radius: 36px;
    background: #fff8d8;
    box-shadow: 0 18px 46px rgba(32, 35, 38, .09);
  }

  .sa-home-app__phone img {
    width: 100%;
    min-height: 560px;
    display: block;
    object-fit: cover;
  }

  .sa-home-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 24px;
    color: var(--green);
    font-size: 18px;
    font-weight: 600;
  }

  .sa-home-app h2 {
    max-width: 760px;
    margin: 0;
    color: var(--ink);
    font-size: clamp(42px, 5vw, 68px);
    line-height: 1.12;
    font-family: var(--font-unageo);
    font-weight: 800;
  }

  .sa-home-app p {
    max-width: 800px;
    margin: 26px 0 0;
    color: var(--neutral-700);
    font-size: 21px;
    line-height: 1.55;
  }

  .sa-home-app ul {
    display: grid;
    gap: 22px;
    margin: 42px 0 0;
    padding: 0;
    list-style: none;
  }

  .sa-home-app li {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    color: var(--green);
    font-size: 21px;
    font-weight: 500;
  }

  .sa-home-app li strong,
  .sa-home-app li small {
    display: block;
  }

  .sa-home-app li small {
    margin-top: 6px;
    color: var(--neutral-700);
    font-size: 15px;
    line-height: 1.45;
    font-weight: 500;
  }

  .sa-home-app .sa-home-btn {
    margin-top: 64px;
  }

  .sa-home-categories__grid,
  .sa-home-product-grid {
    display: grid;
    gap: 18px;
  }

  .sa-home-categories__grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .sa-home-category {
    min-height: 138px;
    padding: 22px;
    border: 1px solid #eadfce;
    border-radius: var(--radius);
    background: #fffbf5;
    transition: transform .2s ease, border-color .2s ease, background .2s ease;
  }

  .sa-home-category:hover {
    transform: translateY(-4px);
    border-color: rgba(0, 107, 82, .28);
    background: #f2fbf7;
  }

  .sa-home-category strong {
    display: block;
    color: var(--ink);
    font-size: 22px;
    font-family: var(--font-unageo);
    font-weight: 800;
  }

  .sa-home-category span {
    display: block;
    margin-top: 12px;
    color: var(--neutral-700);
    font-weight: 600;
  }

  .sa-home-product-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .sa-home-product {
    position: relative;
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--card);
    box-shadow: 0 12px 32px rgba(32, 35, 38, .06);
    transition: transform .22s ease, box-shadow .22s ease;
  }

  .sa-home-product__badge {
    position: absolute;
    top: 10px;
    left: 10px;
    z-index: 2;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    color: #fff;
    text-transform: uppercase;
    letter-spacing: .02em;
  }

  .sa-home-product__badge--deal { background: var(--coral); }
  .sa-home-product__badge--new { background: var(--green); }
  .sa-home-product__badge--best { background: var(--gold); }

  .sa-home-product__stars {
    display: block;
    margin-bottom: 4px;
    color: var(--gold);
    font-size: 13px;
    letter-spacing: 1px;
  }

  .sa-home-product:hover {
    transform: translateY(-5px);
    box-shadow: 0 18px 42px rgba(32, 35, 38, .1);
  }

  .sa-home-product__image {
    display: block;
    position: relative;
    aspect-ratio: 1 / .9;
    background: linear-gradient(135deg, #f8faf6, #fff8e8);
  }

  .sa-home-product__image img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: contain;
    padding: 10px;
  }

  .sa-home-product__body {
    padding: 18px;
  }

  .sa-home-product__body span,
  .sa-home-product__body p {
    color: var(--neutral-700);
  }

  .sa-home-product__body span {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 800;
    text-transform: uppercase;
  }

  .sa-home-product h3 {
    min-height: 48px;
    display: -webkit-box;
    overflow: hidden;
    font-size: 18px;
    line-height: 1.35;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
  }

  .sa-home-product__body p {
    margin: 10px 0 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .sa-home-product__price {
    display: flex;
    align-items: baseline;
    gap: 10px;
    margin-top: 16px;
  }

  .sa-home-product__price del {
    color: #9ca3af;
    font-size: 14px;
  }

  .sa-home-product__price strong {
    color: var(--green);
    font-size: 20px;
    font-weight: 850;
  }

  .sa-home-product__actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 18px;
  }

  .sa-home-product__actions button {
    min-height: 42px;
    border: 1px solid var(--green);
    border-radius: var(--radius);
    background: var(--green);
    color: #fff;
    font-family: var(--font-unageo);
    font-size: 14px;
    font-weight: 800;
    transition: transform .18s ease, background .18s ease, color .18s ease;
  }

  .sa-home-product__actions button:hover {
    transform: translateY(-1px);
    background: #004f3d;
  }

  .sa-home-product__actions button.is-secondary {
    background: #fff;
    color: var(--green);
  }

  .sa-home-product__actions button.is-secondary:hover {
    background: #f0fdf4;
  }

  .sa-home-reviews {
    position: relative;
  }

  .sa-home-reviews__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 44px;
  }

  .sa-home-trust {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 20px;
    border: 1px solid #bdf3d2;
    border-radius: var(--radius);
    background: #effff6;
    color: #202326;
    font-size: 26px;
    font-family: var(--font-unageo);
    font-weight: 800;
  }

  .sa-home-trust span {
    color: #00b67a;
  }

  .sa-home-review-track {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: minmax(340px, 1fr);
    gap: 20px;
    overflow: hidden;
    mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);
  }

  .sa-home-review {
    min-height: 210px;
    padding: 30px;
    border: 1px solid var(--border);
    border-radius: calc(var(--radius) * 1.6);
    background: var(--card);
  }

  .sa-home-review p {
    color: var(--neutral-700);
    font-size: 17px;
    line-height: 1.55;
  }

  .sa-home-review__stars {
    display: flex;
    gap: 3px;
    margin-bottom: 14px;
    color: #f5b301;
    font-size: 19px;
    letter-spacing: 0;
  }

  .sa-home-review small {
    display: block;
    margin-top: 5px;
    color: #8b9491;
    font-weight: 650;
  }

  .sa-home-review strong {
    display: block;
    margin-top: 24px;
    color: var(--ink);
  }

  .sa-home-partners {
    overflow: hidden;
  }

  .sa-home-partner-marquee {
    margin-top: 34px;
    overflow: hidden;
    mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);
  }

  .sa-home-partner-track {
    width: max-content;
    display: flex;
    gap: 18px;
    animation: sa-home-partner-loop 30s linear infinite;
  }

  .sa-home-partner-marquee:hover .sa-home-partner-track {
    animation-play-state: paused;
  }

  .sa-home-partner-logo {
    flex: 0 0 clamp(270px, 24vw, 380px);
    min-height: 164px;
    display: grid;
    place-items: center;
    padding: 18px 28px;
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    transition: transform .2s ease;
  }

  .sa-home-partner-logo:hover {
    transform: translateY(-3px);
  }

  .sa-home-partner-logo img {
    width: auto;
    max-width: 280px;
    max-height: 112px;
    display: block;
    object-fit: contain;
  }

  @keyframes sa-home-partner-loop {
    from {
      transform: translateX(0);
    }

    to {
      transform: translateX(-50%);
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .sa-home-partner-marquee {
      mask-image: none;
    }

    .sa-home-partner-track {
      width: auto;
      flex-wrap: wrap;
      justify-content: center;
      animation: none;
    }
  }

  .sa-home-partner-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
  }

  .sa-home-partner-card {
    min-height: 260px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 24px;
    padding: clamp(26px, 4vw, 48px);
    border: 1px solid rgba(0, 107, 82, .14);
    border-radius: var(--radius);
    background: #f5fbf8;
    box-shadow: 0 14px 34px rgba(32, 35, 38, .06);
  }

  .sa-home-partner-card:nth-child(2) {
    background: #fffaf0;
    border-color: rgba(216, 149, 26, .18);
  }

  .sa-home-partner-card h3 {
    margin: 0;
    color: var(--ink);
    font-family: var(--font-unageo);
    font-size: clamp(30px, 3.6vw, 48px);
    line-height: 1.08;
  }

  .sa-home-partner-card p {
    max-width: 620px;
    margin: 16px 0 0;
    color: var(--neutral-700);
    font-size: 18px;
    line-height: 1.55;
  }

  .sa-home-faq__grid {
    display: grid;
    grid-template-columns: minmax(300px, .44fr) minmax(0, .56fr);
    gap: 20px;
    margin-top: 34px;
  }

  .sa-home-faq__label,
  .sa-home-faq__answers {
    border: 1px solid var(--border);
    border-radius: calc(var(--radius) * 1.2);
    background: var(--card);
  }

  .sa-home-faq__label {
    padding: 28px;
  }

  .sa-home-faq__label h3 {
    font-size: 30px;
    font-family: var(--font-unageo);
  }

  .sa-home-faq__answers {
    overflow: hidden;
  }

  .sa-home-faq button {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    border: 0;
    border-bottom: 1px solid var(--border);
    padding: 22px 26px;
    background: var(--card);
    color: var(--ink);
    text-align: left;
    font-size: 18px;
    font-weight: 800;
  }

  .sa-home-faq p {
    display: none;
    margin: 0;
    padding: 0 26px 24px;
    color: var(--neutral-700);
    line-height: 1.6;
  }

  .sa-home-faq.is-open p {
    display: block;
  }

  .sa-home-community {
    display: grid;
    grid-template-columns: minmax(0, .78fr) minmax(420px, 1.02fr);
    gap: clamp(40px, 7vw, 110px);
    align-items: center;
  }

  .sa-home-community h2 {
    margin: 0;
    color: #303538;
    font-size: clamp(36px, 4vw, 58px);
    line-height: 1.15;
    font-family: var(--font-unageo);
    font-weight: 800;
  }

  .sa-home-community p {
    max-width: 680px;
    margin: 28px 0 0;
    color: var(--neutral-700);
    font-size: 18px;
    line-height: 1.55;
  }

  .sa-home-community form {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    max-width: 640px;
    margin-top: 72px;
    border: 1px solid #d5d9dc;
    border-radius: 4px;
    overflow: hidden;
  }

  .sa-home-community input {
    min-height: 64px;
    border: 0;
    padding: 0 18px;
    color: var(--ink);
    font: inherit;
    outline: 0;
  }

  .sa-home-community button {
    min-width: 150px;
    border: 0;
    background: var(--green);
    color: #fff;
    font-family: var(--font-unageo);
    font-size: 18px;
    font-weight: 800;
  }

  .sa-home-community small {
    display: block;
    max-width: 600px;
    margin-top: 12px;
    color: #9aa1a0;
    line-height: 1.35;
  }

  .sa-home-community__banner {
    min-height: 438px;
    display: grid;
    grid-template-columns: minmax(220px, .62fr) minmax(0, 1.18fr);
    align-items: center;
    gap: clamp(22px, 3vw, 46px);
    overflow: hidden;
    border-radius: var(--radius);
    background: #fff6da;
    position: relative;
    isolation: isolate;
    padding-right: clamp(22px, 4vw, 58px);
  }

  .sa-home-community__banner::after {
    content: "";
    position: absolute;
    right: -170px;
    bottom: -190px;
    width: clamp(240px, 24vw, 330px);
    height: clamp(240px, 24vw, 330px);
    border-radius: 999px;
    background: var(--green);
    z-index: 0;
  }

  .sa-home-community__banner img {
    align-self: end;
    max-height: 438px;
    position: relative;
    z-index: 1;
    object-fit: contain;
  }

  .sa-home-community__copy {
    position: relative;
    z-index: 2;
    min-width: 0;
    max-width: 660px;
    padding: 34px 0;
    color: var(--green);
  }

  .sa-home-community__copy strong {
    display: block;
    font-size: clamp(28px, 3vw, 44px);
    line-height: .95;
    font-family: var(--font-unageo);
    font-weight: 900;
  }

  .sa-home-community__copy span {
    display: inline-flex;
    max-width: 100%;
    margin: 28px 0 0;
    padding: 12px 16px;
    border: 2px solid var(--green);
    font-size: 24px;
    font-weight: 900;
    line-height: 1;
    overflow-wrap: anywhere;
  }

  .sa-home-footer {
    background: var(--green);
    color: #fff;
  }

  .sa-home-footer__grid {
    width: min(var(--page-max), calc(100% - var(--page-gutter)));
    margin: 0 auto;
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(260px, .85fr);
    gap: clamp(38px, 6vw, 90px);
    padding: 56px 0 76px;
  }

  .sa-home-footer__brand img {
    max-width: 190px;
    max-height: 70px;
    object-fit: contain;
    padding: 8px 10px;
    border-radius: var(--radius);
    background: #fff;
  }

  .sa-home-footer p,
  .sa-home-footer a {
    color: rgba(255, 255, 255, .88);
  }

  .sa-home-footer p {
    max-width: 620px;
    margin: 28px 0 0;
    font-size: 17px;
    line-height: 1.6;
  }

  .sa-home-footer h3 {
    margin: 0 0 28px;
    color: #fff;
    font-size: 23px;
    font-family: var(--font-unageo);
    font-weight: 800;
  }

  .sa-home-footer ul {
    display: grid;
    gap: 26px;
    margin: 0;
    padding: 0;
    list-style: none;
  }

  .sa-home-footer a {
    font-size: 17px;
    transition: color .2s ease;
  }

  .sa-home-footer a:hover {
    color: #fff;
  }

  .sa-home-trust-float {
    position: fixed;
    left: 8px;
    bottom: 18px;
    z-index: 80;
    min-width: 138px;
    min-height: 70px;
    display: grid;
    place-items: center;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--card);
    box-shadow: 0 4px 15px rgba(32, 35, 38, .12);
    color: #202326;
    font-size: 14px;
    font-weight: 850;
  }

  .sa-home-floating-cart {
    position: fixed;
    right: clamp(18px, 2vw, 34px);
    top: 50%;
    z-index: 82;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    min-height: 54px;
    padding: 0 16px;
    border: 1px solid rgba(0, 107, 82, .16);
    border-radius: 999px;
    background: var(--card);
    color: var(--green);
    box-shadow: 0 18px 45px rgba(32, 35, 38, .16);
    transform: translateY(-50%);
    transition: transform .2s ease, box-shadow .2s ease;
  }

  .sa-home-floating-cart:hover {
    transform: translateY(-50%) translateX(-3px);
    box-shadow: 0 22px 54px rgba(32, 35, 38, .2);
  }

  .sa-home-floating-cart svg {
    width: 22px;
    height: 22px;
  }

  .sa-home-floating-cart span {
    min-width: 24px;
    height: 24px;
    display: grid;
    place-items: center;
    border-radius: 999px;
    background: var(--green);
    color: #fff;
    font-size: 12px;
    font-weight: 800;
  }

  .sa-home-floating-cart small {
    color: var(--neutral-700);
    font-family: var(--font-unageo);
    font-size: 13px;
    font-weight: 700;
  }

  .sa-home-trust-float span {
    color: #00b67a;
  }

  [data-reveal] {
    opacity: 0;
    transform: translateY(22px);
    transition: opacity .65s ease, transform .65s ease;
  }

  [data-reveal].is-visible {
    opacity: 1;
    transform: translateY(0);
  }

  @media (max-width: 1399px) {
    .sa-home__nav,
    .sa-home-section,
    .sa-home-footer__grid {
      width: min(1180px, calc(100% - clamp(36px, 5vw, 72px)));
    }

    .sa-home-distinct__grid,
    .sa-home-product-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 1023px) {
    .sa-home__nav {
      min-height: 78px;
      grid-template-columns: auto auto;
    }

    .sa-home__links {
      display: none;
      grid-column: 1 / -1;
      width: 100%;
      flex-direction: column;
      align-items: stretch;
      gap: 0;
      padding: 10px 0 18px;
    }

    .sa-home.is-menu-open .sa-home__links {
      display: flex;
    }

    .sa-home__links a {
      padding: 14px 0;
      border-top: 1px solid #eef0f2;
    }

    .sa-home__links .sa-home-menu-auth-link {
      display: flex;
      color: var(--green);
      font-weight: 800;
    }

    .sa-home__actions {
      justify-self: end;
    }

    .sa-home__actions .sa-home-btn {
      display: inline-flex;
      min-height: 44px;
      padding: 0 16px;
    }

    .sa-home__menu {
      display: inline-block;
    }

    .sa-home-hero,
    .sa-home-app__grid,
    .sa-home-community,
    .sa-home-faq__grid {
      grid-template-columns: 1fr;
    }

    .sa-home-hero {
      min-height: 0;
      padding-top: 70px;
      padding-bottom: 78px;
    }

    .sa-home-hero__media {
      grid-template-rows: 210px 240px;
    }

    .sa-home-features__grid {
      grid-template-columns: 1fr;
      grid-template-areas: none;
    }

    .sa-home-feature,
    .sa-home-feature:first-child,
    .sa-home-feature:nth-child(2),
    .sa-home-feature:nth-child(3),
    .sa-home-feature:nth-child(4) {
      grid-area: auto;
      min-height: 240px;
    }

    .sa-home-categories__grid,
    .sa-home-footer__grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .sa-home-community__banner {
      min-height: 340px;
      grid-template-columns: 1fr;
      padding: 28px;
    }

    .sa-home-community__banner::after {
      display: none;
    }

    .sa-home-community__banner img {
      display: none;
    }

    .sa-home-community__copy {
      padding: 0;
      max-width: none;
    }
  }

  @media (max-width: 640px) {
    .sa-home__announce {
      font-size: 12px;
    }

    .sa-home__nav,
    .sa-home-section,
    .sa-home-footer__grid {
      width: min(100% - 28px, 1180px);
    }

    .sa-home__logo img {
      max-width: 148px;
    }

    .sa-home__nav {
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 12px 14px;
    }

    .sa-home__actions {
      display: grid;
      grid-template-columns: repeat(2, minmax(104px, 1fr));
      gap: 12px;
      grid-column: 2;
    }

    .sa-home__actions .sa-home-btn {
      grid-row: 1;
      min-height: 52px;
      font-size: 16px;
    }

    .sa-home__cart,
    .sa-home__menu {
      grid-row: 2;
      width: 58px;
      height: 58px;
      justify-self: end;
    }

    .sa-home-hero h1 {
      font-size: clamp(44px, 12vw, 58px);
      line-height: 1.32;
    }

    .sa-home-app p {
      font-size: 17px;
    }

    .sa-home-hero p {
      font-size: clamp(20px, 6vw, 27px);
    }

    .sa-home-hero {
      text-align: center;
    }

    .sa-home-hero__buttons {
      display: grid;
      grid-template-columns: 1fr;
      gap: 16px;
      margin-top: 54px;
    }

    .sa-home-hero__buttons .sa-home-btn {
      width: 100%;
      min-height: 78px;
      border-radius: 12px;
      font-size: 23px;
    }

    .sa-home-hero__media {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      grid-template-rows: 224px 224px;
      gap: 18px;
    }

    .sa-home-hero__image:nth-child(2) {
      grid-row: 1 / span 2;
      grid-column: 2;
    }

    .sa-home-distinct__grid,
    .sa-home-product-grid,
    .sa-home-partner-grid,
    .sa-home-categories__grid,
    .sa-home-footer__grid {
      grid-template-columns: 1fr;
    }

    .sa-home-community form {
      grid-template-columns: 1fr;
    }

    .sa-home-community button {
      min-height: 58px;
    }

    .sa-home-community__banner {
      min-height: auto;
    }

    .sa-home-community__copy strong {
      font-size: 34px;
      line-height: 1.05;
    }

    .sa-home-community__copy span {
      width: 100%;
      justify-content: center;
      font-size: 20px;
      line-height: 1.15;
      text-align: center;
    }

    .sa-home-product__actions {
      grid-template-columns: 1fr;
    }

    .sa-home-partner-marquee {
      mask-image: none;
    }

    .sa-home-partner-logo {
      flex-basis: 240px;
      min-height: 130px;
      padding: 14px 20px;
    }

    .sa-home-partner-logo img {
      max-width: 214px;
      max-height: 88px;
    }

    .sa-home-trust-float {
      display: none;
    }

    .sa-home-floating-cart {
      right: 14px;
      top: auto;
      bottom: 88px;
      min-height: 48px;
      padding: 0 12px;
      transform: none;
    }

    .sa-home-floating-cart:hover {
      transform: translateY(-2px);
    }

    .sa-home-floating-cart small {
      display: none;
    }
  }

</style>

<div class="sa-home" id="sa-home">
  <div class="sa-home__announce"><strong>Free shipping on your first order from our U.S. warehouse</strong></div>
  <header class="sa-home__header" aria-label="<?= e($brandName) ?> home header">
    <nav class="sa-home__nav">
      <a class="sa-home__logo" href="<?= e(app_url('store')) ?>" aria-label="<?= e($brandName) ?> home">
        <?php $renderLogo(); ?>
      </a>
      <div class="sa-home__links" id="sa-home-menu">
        <a href="<?= e(app_url('store')) ?>">Home</a>
        <a href="<?= e(app_url('shop')) ?>">Marketplace</a>
        <a href="<?= e(app_url('shop?browse=1')) ?>">Shop</a>
        <a href="<?= e(app_url('vendors')) ?>">Our Vendors</a>
        <a href="<?= e(app_url('about')) ?>">About Us</a>
        <a href="<?= e(app_url('contact')) ?>">Contact Us</a>
        <a class="sa-home-menu-auth-link" href="<?= e(app_url('affiliate/join')) ?>">Affiliate Sign Up</a>
        <a class="sa-home-menu-auth-link" href="<?= e(app_url('vendor/register')) ?>">Vendor Register</a>
      </div>
      <div class="sa-home__actions">
        <a class="sa-home__cart" href="<?= e(app_url('cart')) ?>" aria-label="Cart">
          <?= $renderIcon('bag') ?>
          <span data-cart-count><?= number_format($cartCount) ?></span>
        </a>
        <a class="sa-home-btn sa-home-btn--ghost" href="<?= e(app_url('login')) ?>">Login</a>
        <a class="sa-home-btn sa-home-btn--register" href="<?= e(app_url('buyer/register')) ?>">Register</a>
        <button class="sa-home__menu" type="button" aria-controls="sa-home-menu" aria-expanded="false"><span></span></button>
      </div>
    </nav>
  </header>

  <section class="sa-home-section sa-home-hero">
    <div data-reveal>
      <span class="sa-home-hero__eyebrow"></span>
      <h1>Start shopping or <span>start selling.</span></h1>
      <p>Two sides of the same marketplace. Pick yours.</p>
      <div class="sa-home-hero__buttons">
        <a class="sa-home-btn sa-home-btn--gold" href="<?= e(app_url('shop')) ?>">Shop now <?= $renderIcon('arrow') ?></a>
        <a class="sa-home-btn" href="<?= e(app_url('vendor/register')) ?>">Become a vendor</a>
      </div>
    </div>
  </section>

  <section class="sa-home-section sa-home-video" aria-label="<?= e($brandName) ?> video">
    <div class="sa-home-section__head" data-reveal>
      <h2>Experience <?= e($brandName) ?></h2>
      <p>See how the marketplace connects shoppers, vendors, and authentic products across the global African and Caribbean community.</p>
    </div>
    <div class="sa-home-video__player" data-reveal>
      <video controls preload="metadata" playsinline>
        <source src="<?= e($homeVideo) ?>" type="video/mp4">
        Your browser does not support the video tag.
      </video>
    </div>
  </section>

  <div class="sa-home-soft">
    <section class="sa-home-section">
      <div class="sa-home-section__head" data-reveal>
        <h2>Built for the global African community.</h2>
        <p>From Lagos to London, Accra to Atlanta, Kingston to Toronto, <?= e($brandName) ?> connects the diaspora with authentic products, trusted vendors, and the brands that feel like home.</p>
      </div>
      <div class="sa-home-distinct__grid">
        <?php foreach ($distinctCards as $index => $card): ?>
          <article class="sa-home-distinct-card" data-reveal style="--distinct-color: <?= e((string)$card[3]) ?>; --distinct-bg: <?= e((string)$card[4]) ?>; transition-delay: <?= (int)($index * 80) ?>ms">
            <span class="sa-home-distinct-card__icon"><?= (int)($index + 1) ?></span>
            <h3><?= e($card[0]) ?></h3>
            <p><?= e($card[1]) ?></p>
            <div class="sa-home-distinct-card__visual" aria-label="<?= e($card[0]) ?>">
              <img src="<?= e((string)$card[5]) ?>" alt="<?= e($card[0]) ?>" loading="lazy" decoding="async">
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <section class="sa-home-section">
    <div class="sa-home-section__head" data-reveal>
      <h2>Why Seller Africa</h2>
      <p>Built for the diaspora. Powered by trusted vendors. Shop, sell, and stay connected to home through one marketplace.</p>
    </div>
    <div class="sa-home-product-grid">
      <?php foreach (array_slice($newArrivals !== [] ? $newArrivals : $products, 0, 8) as $product): ?>
        <?php $renderProductCard($product); ?>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="sa-home-section">
    <div class="sa-home-section__head" data-reveal>
      <h2>Grow your business beyond borders.</h2>
      <p>Open your store on <?= e($brandName) ?> and reach customers across North America and beyond. We provide the platform, visibility, and distribution support to help your brand expand globally.</p>
    </div>
    <div class="sa-home-features__grid">
      <?php foreach ($features as $feature): ?>
        <article class="sa-home-feature" data-reveal style="--feature-bg: <?= e($feature[3]) ?>; --feature-color: <?= e($feature[4]) ?>;">
          <span class="sa-home-feature__icon"><?= $renderIcon($feature[5]) ?></span>
          <h3><?= e($feature[0]) ?></h3>
          <small><?= e($feature[1]) ?></small>
          <p><?= e($feature[2]) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="sa-home-section">
    <div class="sa-home-partner-grid">
      <article class="sa-home-partner-card" data-reveal>
        <div>
          <h3>Register as a vendor.</h3>
          <p>List your products, manage pricing, and reach African and Caribbean diaspora buyers through a marketplace built for global trade.</p>
        </div>
        <a class="sa-home-btn" href="<?= e(app_url('vendor/register')) ?>">Open Your Store <?= $renderIcon('arrow') ?></a>
      </article>
      <article class="sa-home-partner-card" data-reveal>
        <div>
          <h3>Join as an affiliate.</h3>
          <p>Share Seller Africa with your community, refer shoppers and vendors, and grow with the marketplace as it expands.</p>
        </div>
        <a class="sa-home-btn" href="<?= e(app_url('affiliate/join')) ?>">Join Affiliate Community <?= $renderIcon('arrow') ?></a>
      </article>
    </div>
  </section>

  <section class="sa-home-app">
    <div class="sa-home-section sa-home-app__grid">
      <div class="sa-home-app__phone" data-reveal>
        <img src="<?= e($asset('assets/images/intutive.jpeg')) ?>" alt="<?= e($brandName) ?> vendor marketplace preview" loading="lazy">
      </div>
      <div data-reveal>
        <span class="sa-home-eyebrow"><?= $renderIcon('check') ?> Intuitive</span>
        <h2>Reach the diaspora. Grow beyond borders.</h2>
        <p>Open your store on <?= e($brandName) ?> and connect with millions of African and Caribbean consumers across North America and beyond. List your products, manage your pricing, and reach customers worldwide.</p>
        <ul>
          <li><?= $renderIcon('check') ?><span><strong>Reach African &amp; Caribbean Buyers</strong><small>Connect with customers across the U.S., Canada, and beyond.</small></span></li>
          <li><?= $renderIcon('check') ?><span><strong>Sell Through Our Marketplace</strong><small>List your products, receive orders, and grow your brand globally.</small></span></li>
          <li><?= $renderIcon('check') ?><span><strong>U.S. Warehousing &amp; Fulfillment</strong><small>Store inventory closer to your customers for faster delivery.</small></span></li>
          <li><?= $renderIcon('check') ?><span><strong>Export &amp; Logistics Support</strong><small>We help simplify shipping, customs, and cross-border distribution.</small></span></li>
          <li><?= $renderIcon('check') ?><span><strong>Marketing &amp; Product Visibility</strong><small>Increase exposure through homepage features, promotions, and digital campaigns.</small></span></li>
          <li><?= $renderIcon('check') ?><span><strong>Secure Payments in Dollars</strong><small>Receive payments securely while expanding into international markets.</small></span></li>
        </ul>
        <a class="sa-home-btn" href="<?= e(app_url('vendor/register')) ?>">Become a Vendor <?= $renderIcon('arrow') ?></a>
      </div>
    </div>
  </section>

  <section class="sa-home-section">
    <div class="sa-home-section__head" data-reveal>
      <h2>Sales &amp; Deals</h2>
      <p>Save more on the products you love. Compare original prices and discounted prices instantly shop smart, every time.</p>
    </div>
    <div class="sa-home-product-grid">
      <?php foreach (array_slice($onSale !== [] ? $onSale : $bestSellers, 0, 8) as $product): ?>
        <?php $renderProductCard($product, true); ?>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="sa-home-section sa-home-reviews">
    <div class="sa-home-reviews__top" data-reveal>
      <div class="sa-home-section__head" style="margin:0">
        <h2>What our customers say</h2>
        <p>Verified customer-style feedback from marketplace shoppers.</p>
      </div>
      <div class="sa-home-trust"><span>★</span> Trustpilot</div>
    </div>
    <div class="sa-home-review-track" data-home-marquee>
      <?php foreach ($testimonials as $testimonial): ?>
        <article class="sa-home-review">
          <div class="sa-home-review__stars" aria-label="5 star review">★★★★★</div>
          <p><?= e($testimonial[2]) ?></p>
          <strong><?= e($testimonial[0]) ?></strong>
          <small><?= e($testimonial[1]) ?> · 1 review</small>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="sa-home-section sa-home-partners">
    <div class="sa-home-section__head" data-reveal>
      <h2>Our Partners</h2>
      <p>Finance, export, and logistics partners helping African and Caribbean commerce move further.</p>
    </div>
    <div class="sa-home-partner-marquee" aria-label="<?= e($brandName) ?> partners">
      <div class="sa-home-partner-track">
        <?php for ($loop = 0; $loop < 2; $loop++): ?>
          <?php foreach ($partners as $partner): ?>
              <a class="sa-home-partner-logo" href="<?= e((string)$partner[1]) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e((string)$partner[0]) ?>">
                <img src="<?= e((string)$partner[2]) ?>" alt="<?= e((string)$partner[0]) ?> logo" loading="lazy" decoding="async">
              </a>
          <?php endforeach; ?>
        <?php endfor; ?>
      </div>
    </div>
  </section>

  <section class="sa-home-section sa-home-faq">
    <div class="sa-home-section__head" data-reveal>
      <h2>Frequently Asked Questions</h2>
      <p>Have a question? Find quick answers to common questions.</p>
    </div>
    <div class="sa-home-faq__grid">
      <div class="sa-home-faq__label"><h3>Question type</h3></div>
      <div class="sa-home-faq__answers">
        <?php foreach ($faqs as $index => $faq): ?>
          <article class="sa-home-faq<?= $index === 0 ? ' is-open' : '' ?>">
            <button type="button" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>"><?= e($faq[0]) ?><span>+</span></button>
            <p><?= e($faq[1]) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="sa-home-section sa-home-community">
    <div data-reveal>
      <h2>Stay Connected with Our Community</h2>
      <p>Join our community to receive exclusive updates, insights, and opportunities to connect with shoppers, vendors, and affiliates who share your passion.</p>
      <form action="<?= e(app_url('buyer/register')) ?>" method="get">
        <input type="email" name="email" placeholder="Enter email address" aria-label="Email address">
        <button type="submit">Join Us</button>
      </form>
      <small>We promise to keep your information safe and send only relevant marketplace updates.</small>
    </div>
    <div class="sa-home-community__banner" data-reveal>
      <img src="<?= e($asset('assets/images/md1.png')) ?>" alt="Fresh delivery from <?= e($brandName) ?>" loading="lazy">
      <div class="sa-home-community__copy">
        <strong>New customer?</strong>
        <p>Sign up and get fresh African products delivered right to your doorstep.</p>
        <span>Order above $100</span>
      </div>
    </div>
  </section>

  <?php $footerClass = 'sa-home-footer'; $footerGridClass = 'sa-home-footer__grid'; include __DIR__ . '/../partials/standard-footer.php'; ?>

  <div class="sa-home-trust-float"><span>★</span>&nbsp;Trustpilot</div>
  <a class="sa-home-floating-cart" href="<?= e(app_url('cart')) ?>" aria-label="Open cart">
    <?= $renderIcon('bag') ?>
    <span data-cart-count><?= number_format($cartCount) ?></span>
    <small>Cart</small>
  </a>
</div>

<?= app_chat_widget_embed() ?>

<script>
(() => {
  const root = document.getElementById('sa-home');
  if (!root) return;
  const cartEndpoint = <?= json_encode(app_url('api/cart/add'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const checkoutUrl = <?= json_encode(app_url('storefront/checkout'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

  const setCartCount = (value) => {
    const count = Number.parseInt(value, 10);
    if (!Number.isFinite(count) || count < 0) return;
    root.querySelectorAll('[data-cart-count]').forEach((badge) => {
      badge.textContent = count.toLocaleString();
      badge.dataset.count = String(count);
    });
  };

  const refreshCartCount = () => {
    const body = new URLSearchParams();
    body.set('action', 'list');
    fetch(cartEndpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
      credentials: 'same-origin'
    })
      .then((response) => response.ok ? response.json() : null)
      .then((payload) => {
        if (payload?.redirect) { window.location.assign(payload.redirect); return payload; }
        if (payload && typeof payload.cart_count !== 'undefined') {
          setCartCount(payload.cart_count);
        }
      })
      .catch(() => {});
  };

  const addToCart = (productId, quantity = 1) => {
    const body = new URLSearchParams();
    body.set('product_id', String(productId));
    body.set('quantity', String(quantity));
    return fetch(cartEndpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
      credentials: 'same-origin'
    })
      .then((response) => response.ok ? response.json() : Promise.reject(new Error('Cart request failed')))
      .then((payload) => {
        if (payload && typeof payload.cart_count !== 'undefined') {
          setCartCount(payload.cart_count);
          window.dispatchEvent(new CustomEvent('seller-africa:cart-updated', { detail: payload }));
        }
        return payload;
      });
  };

  const menuButton = root.querySelector('.sa-home__menu');
  menuButton?.addEventListener('click', () => {
    const open = root.classList.toggle('is-menu-open');
    menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  const revealItems = root.querySelectorAll('[data-reveal]');
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    revealItems.forEach((item) => observer.observe(item));
  } else {
    revealItems.forEach((item) => item.classList.add('is-visible'));
  }

  root.querySelectorAll('.sa-home-faq button').forEach((button) => {
    button.addEventListener('click', () => {
      const item = button.closest('.sa-home-faq');
      const open = item?.classList.toggle('is-open') ?? false;
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  root.addEventListener('click', (event) => {
    const addButton = event.target.closest('[data-add-cart]');
    const buyButton = event.target.closest('[data-buy-now]');
    const button = addButton || buyButton;
    if (!button || button.disabled) return;
    const productId = Number.parseInt(button.dataset.addCart || button.dataset.buyNow || '0', 10);
    if (!productId) return;
    event.preventDefault();
    const original = button.textContent;
    button.disabled = true;
    button.textContent = buyButton ? 'Opening...' : 'Adding...';
    addToCart(productId)
      .then((payload) => {
        if (payload?.redirect) return;
        if (!payload?.ok) throw new Error(payload?.message || 'Unable to add product');
        if (buyButton) {
          window.location.assign(checkoutUrl);
          return;
        }
        button.textContent = 'Added';
        window.setTimeout(() => { button.textContent = original; button.disabled = false; }, 1100);
      })
      .catch(() => {
        button.textContent = 'Try again';
        window.setTimeout(() => { button.textContent = original; button.disabled = false; }, 1400);
      });
  });

  const track = root.querySelector('[data-home-marquee]');
  if (track && window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
    let position = 0;
    const tick = () => {
      position = (position + 0.22) % Math.max(1, track.scrollWidth / 2);
      track.scrollLeft = position;
      requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }

  window.addEventListener('storage', (event) => {
    if (event.key && /cart/i.test(event.key) && event.newValue !== null) {
      const parsed = Number.parseInt(event.newValue, 10);
      if (Number.isFinite(parsed)) setCartCount(parsed);
      refreshCartCount();
    }
  });

  ['seller-africa:cart-updated', 'sellerAfricaCartUpdated', 'cart:updated'].forEach((eventName) => {
    window.addEventListener(eventName, (event) => {
      const detail = event.detail || {};
      if (typeof detail.cart_count !== 'undefined') setCartCount(detail.cart_count);
      else if (typeof detail.count !== 'undefined') setCartCount(detail.count);
      else refreshCartCount();
    });
  });

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) refreshCartCount();
  });

  refreshCartCount();
})();
</script>
