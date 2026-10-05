<?php

declare(strict_types=1);

if (!isset($_GET['route'])) {
    $path = trim((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? ''), '/');
    $path = preg_replace('#^public/#', '', $path) ?? $path;

    $routeMap = [
        '' => 'home',
        'home' => 'home',
        'store' => 'home',
        'marketplace' => 'shop',
        'storefront' => 'storefront/index',
        'shop' => 'shop',
        'about' => 'about',
        'contact' => 'contact',
        'affiliate-registration' => 'affiliate-registration',
        'become-a-vendor' => 'become-a-vendor',
        'join-community' => 'join-community',
        'rewards-program' => 'rewards-program',
        'distribution-partner' => 'distribution-partner',
        'vendor-resources' => 'vendor-resources',
        'faqs' => 'faqs',
        'shipping-delivery' => 'shipping-delivery',
        'returns-refunds' => 'returns-refunds',
        'customer-support' => 'customer-support',
        'careers' => 'careers',
        'blog-news' => 'blog-news',
        'freshroots' => 'freshroots',
        'freshroots/join' => 'freshroots/join',
        'freshroots/privacy-policy' => 'freshroots/privacy-policy',
        'freshroots/terms' => 'freshroots/terms',
        'freshroots/legal' => 'freshroots/legal',
        'farm-fresh' => 'farm-fresh',
        'farm-users' => 'farm-users',
        'farms' => 'farms',
        'farms-near-me' => 'farms-near-me',
        'visibility-boost' => 'visibility-boost',
        'manage-store' => 'manage-store',
        'vendor-agreement' => 'vendor-agreement',
        'partner-agreement' => 'partner-agreement',
        'cookie-policy' => 'cookie-policy',
        'packages' => 'packages',
        'knowledge-base' => 'knowledge-base',
        'vendors' => 'vendors',
        'track-order' => 'track-order',
        'privacy-policy' => 'privacy-policy',
        'terms' => 'terms',
        'cart' => 'storefront/cart',
        'storefront/packages' => 'packages',
        'login' => 'login',
        'logout' => 'logout',
        'register' => 'register',
        'verify-email' => 'verify-email',
        'forgot-password' => 'forgot-password',
        'reset-password' => 'reset-password',
        'onboarding' => 'onboarding',
        'maintenance' => 'maintenance',
    ];

    if (isset($routeMap[$path])) {
        $_GET['route'] = $routeMap[$path];
    } elseif (preg_match('#^product/([a-z0-9-]+)/?$#i', $path, $matches) === 1) {
        $_GET['route'] = 'storefront/product';
        $_GET['slug'] = $matches[1];
    } elseif (preg_match('#^vendors/([a-z0-9-]+)/review/?$#i', $path, $matches) === 1) {
        $_GET['route'] = 'vendor-review';
        $_GET['slug'] = $matches[1];
    } elseif (preg_match('#^vendors/([a-z0-9-]+)/?$#i', $path, $matches) === 1) {
        $_GET['route'] = 'vendors';
        $_GET['slug'] = $matches[1];
    } elseif (preg_match('#^(storefront|api|buyer|vendor|affiliate|dashboard)(?:/(.*))?$#i', $path, $matches) === 1) {
        $_GET['route'] = trim($matches[1] . '/' . (string)($matches[2] ?? ''), '/');
    }
}

require_once __DIR__ . '/public/index.php';
