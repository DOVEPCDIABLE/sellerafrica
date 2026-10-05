<?php
/**
 * File Folder Path: /public
 * File Path: /public/index.php
 * Designed by Daniel Pybexai Framework
 * ==============================================================================
 * MULTIVENDOR ERP - CENTRAL ROUTER & MODULE DISPATCHER
 * Summary: Intercepts all requests, handles URL cleaning, enforces auth guards, 
 * and maps routes directly to physical files within the public folder.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/core/bootstrap.php';

// --- GLOBAL URL PATH CLEANER ---
// Intercepts and corrects Laragon/Deep-Path URL doubling errors
$uri = $_SERVER['REQUEST_URI'];
if (strpos($uri, 'erp-market/public/erp-market/public/') !== false) {
    $cleanUri = str_replace('erp-market/public/erp-market/public/', 'erp-market/public/', $uri);
    header("Location: " . $cleanUri);
    exit;
}

// 1. INITIALIZATION CHECK
// If no Super Admin exists, redirect to onboarding script (unless we are already there)
try {
    $routeParam = $_GET['route'] ?? '';
    
    if (!super_admin_exists()) {
        if ($routeParam !== 'onboarding') {
            redirect('onboarding.php');
        }
    }
} catch (\Throwable $e) {
    // If the database connection fails or tables don't exist yet, force onboarding
    if (($_GET['route'] ?? '') !== 'onboarding') {
        redirect('onboarding.php');
    }
}

// 2. EXTRACT ROUTE
// Serve the distribution homepage at the root without a browser redirect.
$route = $_GET['route'] ?? 'distributors';
$route = trim($route, '/');
if ($route === '') $route = 'distributors';

if (preg_match('#^api/mobile(?:/v1)?(?:/.*)?$#', $route) === 1) {
    require_once __DIR__ . '/api/mobile.php';
    exit;
}

// 3. CORE PUBLIC ROUTES (WHITELIST)
// These routes bypass the Authentication Guard.
$publicRoutes = [
    'distributor' => 'distributor.php',
    'distributor/register' => 'distributor.php',
    'dashboard/distributors' => 'distributor.php',
    'distributors' => 'storefront/distributors.php',
    // Authentication
    'login'               => 'login.php',
    'logout'              => 'logout.php',
    'register'            => 'register.php',
    'verify-email'        => 'verify-email.php',
    'forgot_password'     => 'forgot_password.php',
    'forgot-password'     => 'forgot_password.php',
    'reset_password'      => 'reset_password.php',
    'reset-password'      => 'reset_password.php',
    'onboarding'          => 'onboarding.php',
    'maintenance'         => 'maintenance.php',
    
    // Vendor Onboarding (KYC form is public, dashboard is protected)
    'vendor/register'     => 'vendor/register.php',
    'farmer/register'     => 'farmer/register.php',
    'buyer/register'      => 'buyer/register.php',
    
    // Affiliate Portal
    'affiliate/join'      => 'affiliate/join.php',

    // Public E-Commerce Storefront
    'home'                => 'storefront/index.php',
    'store'               => 'storefront/index.php',
    'marketplace'         => 'storefront/shop.php',
    'shop'                => 'storefront/shop.php',
    'about'               => 'storefront/about.php',
    'contact'             => 'storefront/contact.php',
    'affiliate-registration' => 'storefront/affiliate-registration.php',
    'become-a-vendor'     => 'storefront/info-page.php',
    'freshroots'          => 'storefront/farm-fresh.php',
    'freshroots/join'     => 'storefront/farm-users.php',
    'freshroots/privacy-policy' => 'storefront/farm-fresh-legal.php',
    'freshroots/terms'    => 'storefront/farm-fresh-legal.php',
    'freshroots/legal'    => 'storefront/farm-fresh-legal.php',
    'farm-fresh'          => 'storefront/farm-fresh.php',
    'farm-users'          => 'storefront/farm-users.php',
    'farm-fresh/privacy-policy' => 'storefront/farm-fresh-legal.php',
    'farm-fresh/terms'    => 'storefront/farm-fresh-legal.php',
    'farm-fresh/legal'    => 'storefront/farm-fresh-legal.php',
    'farm-privacy-policy' => 'storefront/farm-fresh-legal.php',
    'farm-terms'          => 'storefront/farm-fresh-legal.php',
    'farm-legal'          => 'storefront/farm-fresh-legal.php',
    'farms'               => 'storefront/farms.php',
    'farms-near-me'       => 'storefront/farms-near-me.php',
    'visibility-boost'    => 'storefront/visibility-boost.php',
    'manage-store'        => 'storefront/manage-store.php',
    'priority'           => 'storefront/priority.php',
    'setup-store'         => 'storefront/manage-store.php',
    'join-community'      => 'storefront/info-page.php',
    'rewards-program'     => 'storefront/info-page.php',
    'distribution-partner' => 'storefront/distributors.php',
    'vendor-resources'    => 'storefront/info-page.php',
    'faqs'                => 'storefront/info-page.php',
    'shipping-delivery'   => 'storefront/info-page.php',
    'returns-refunds'     => 'storefront/info-page.php',
    'customer-support'    => 'storefront/info-page.php',
    'careers'             => 'storefront/info-page.php',
    'blog-news'           => 'storefront/info-page.php',
    'gallery'             => 'storefront/gallery.php',
    'albums'              => 'storefront/gallery.php',
    'vendor-agreement'    => 'storefront/info-page.php',
    'partner-agreement'   => 'storefront/info-page.php',
    'cookie-policy'       => 'storefront/info-page.php',
    'packages'            => 'storefront/packages.php',
    'storefront/packages' => 'storefront/packages.php',
    'knowledge-base'      => 'storefront/info-page.php',
    'vendors'             => 'storefront/vendors.php',
    'vendor-review'       => 'storefront/vendor-review.php',
    'storefront'          => 'storefront/index.php',
    'storefront/index'    => 'storefront/index.php',
    'storefront/product'  => 'storefront/product.php',
    'storefront/category' => 'storefront/category.php',
    'storefront/cart'     => 'storefront/cart.php',
    'storefront/checkout' => 'storefront/checkout.php', // Guest checkout is allowed
    'storefront/klasha-pay' => 'storefront/klasha-pay.php',
    'storefront/order-success' => 'storefront/order-success.php',
    'storefront/order-pdf' => 'storefront/order-pdf.php',
    'track-order' => 'storefront/track-order.php',
    'privacy-policy' => 'storefront/privacy-policy.php',
    'terms' => 'storefront/terms.php',

    // Public API Endpoints & Webhooks
    'api/cart/add'        => 'api/cart/add.php',
    'api/admin/product-duplicates' => 'api/admin/product-duplicates.php',
    'api/checkout/create' => 'api/checkout/create.php',
    'api/checkout/quote' => 'api/checkout/quote.php',
    'api/payments/klasha/callback' => 'api/payments/klasha/callback.php',
    'api/payments/paystack/callback' => 'api/payments/paystack/callback.php',
    'api/payments/manage-store' => 'api/payments/manage-store.php',
    'api/farm-fresh/membership' => 'api/farm-fresh/membership.php',
    'api/storefront/reviews' => 'api/storefront/reviews.php',
    'api/storefront/search' => 'api/storefront/search.php',
    'api/storefront/category-products' => 'api/storefront/category-products.php',
    'api/storefront/product-shipping-quote' => 'api/storefront/product-shipping-quote.php',
    'api/storefront/vendor-products' => 'api/storefront/vendor-products.php',
    'api/webhooks/stripe' => 'api/webhooks/stripe.php',
    'api/webhooks/paypal' => 'api/webhooks/paypal.php',
    'api/webhooks/klasha' => 'api/webhooks/klasha.php',
    'api/webhooks/paystack' => 'api/webhooks/paystack.php'
];

if (isset($publicRoutes[$route])) {
    require_once __DIR__ . '/' . $publicRoutes[$route];
    exit;
}

// 4. AUTHENTICATION GUARD
// Checks if the session holds a valid user login, or falls back to an AuthService if implemented.
$isAuthenticated = false;
if (class_exists('\App\AuthService')) {
    $isAuthenticated = \App\AuthService::check();
} else {
    // Fallback simple check
    $isAuthenticated = isset($_SESSION['user_id']) || isset($_SESSION['vendor_id']) || isset($_SESSION['admin_id']);
}

if (!$isAuthenticated) {
    // Store intended URL for redirect after login
    $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
    redirect('login');
}

// 5. MODULE DISPATCHER
// Safely maps the verified, authenticated route to the corresponding PHP file.
$directoryIndexRoutes = [
    'buyer' => 'buyer/index.php',
    'vendor' => 'vendor/index.php',
    'farmer' => 'farmer/index.php',
    'affiliate' => 'affiliate/index.php',
];

$physicalFile = __DIR__ . '/' . ($directoryIndexRoutes[$route] ?? ($route . '.php'));

// Ensure the resolved path stays within the public directory to prevent directory traversal
$realBase = realpath(__DIR__);
$realFile = realpath($physicalFile);

if ($realFile && str_starts_with($realFile, $realBase) && is_file($realFile)) {
    require_once $realFile;
} else {
    render_restricted_page(404, 'This link may be broken', 'The link you opened may be broken or the page may have moved. We have sent this to our technical team to review.', ['route' => $route, 'reason' => 'module_not_found']);
}
