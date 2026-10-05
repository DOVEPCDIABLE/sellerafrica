<?php
/** @var \App\StorefrontTemplateChunkService $storefrontTemplate */
$brand = app_branding();
$payload = [
    'products' => $products ?? [],
    'hotProducts' => $hotProducts ?? [],
    'popularProducts' => $popularProducts ?? [],
    'categories' => $categories ?? [],
    'banners' => $banners ?? [],
    'vendors' => $vendors ?? [],
    'testimonials' => $testimonials ?? [],
    'cartItems' => $cartItems ?? [],
    'contentBlocks' => $contentBlocks ?? [],
    'isSuperAdmin' => class_exists('\App\AuthService') && \App\AuthService::hasRole('super_admin'),
    'csrf' => getCsrfToken(),
    'endpoints' => [
        'cart' => app_url('api/cart/add.php'),
        'checkoutQuote' => app_url('api/checkout/quote'),
        'content' => app_url('api/storefront/content.php'),
        'reviews' => app_url('api/storefront/reviews'),
    ],
    'urls' => [
        'store' => app_url('store'),
        'shop' => app_url('shop'),
        'cart' => app_url('cart'),
        'checkout' => app_url('storefront/checkout'),
        'trackOrder' => app_url('track-order'),
        'about' => app_url('about'),
        'contact' => app_url('contact'),
        'affiliateRegistration' => app_url('affiliate-registration'),
        'becomeVendor' => app_url('become-a-vendor'),
        'knowledgeBase' => app_url('knowledge-base'),
        'vendors' => app_url('vendors'),
        'login' => app_url('login'),
        'register' => app_url('buyer/register'),
        'affiliateLogin' => app_url('login?as=affiliate'),
    ],
    'filters' => $filters ?? [],
    'shopMeta' => $shopMeta ?? [],
    'cartCount' => (int)($cartCount ?? 0),
    'currencies' => (new \App\StorefrontTemplateService(db()))->currencies(),
    'page' => $page ?? 'home',
    'title' => $title ?? $brand['name'] . ' Storefront',
    'brand' => $brand,
];

echo $storefrontTemplate->scripts($payload);
app_render_chat_widget();
