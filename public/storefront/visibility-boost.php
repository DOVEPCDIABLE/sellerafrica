<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\AuthService;
use App\CartService;
use App\VisibilityBoostService;

VisibilityBoostService::ensureSchema();

function visibility_boost_current_vendor(): ?array {
    if (!AuthService::check()) {
        return null;
    }

    return db()->fetch('SELECT * FROM vendors WHERE user_id = ? LIMIT 1', [(int)($_SESSION['user_id'] ?? 0)]);
}

function visibility_boost_require_vendor(): array {
    $vendor = visibility_boost_current_vendor();
    if ($vendor) {
        return $vendor;
    }

    if (!AuthService::check()) {
        $_SESSION['intended_url'] = app_url('visibility-boost');
        flash('warning', 'Sign in with your vendor account to boost your product visibility.');
        redirect('login');
    }

    flash('warning', 'Visibility boost is available for vendor accounts.');
    redirect('vendor/register');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validateCsrf();
    $vendor = visibility_boost_require_vendor();

    try {
        $provider = in_array((string)($_POST['payment_provider'] ?? 'stripe'), ['stripe', 'paystack'], true)
            ? (string)$_POST['payment_provider']
            : 'stripe';
        $checkoutUrl = VisibilityBoostService::createCheckoutSession(
            (int)$vendor['id'],
            app_url('visibility-boost?subscribed=1'),
            app_url('visibility-boost?cancelled=1'),
            $provider
        );
        redirect($checkoutUrl);
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
        redirect('visibility-boost');
    }
}

$brand = app_branding();
$cart = new CartService();
$vendor = visibility_boost_current_vendor();

if (isset($_GET['subscribed'])) {
    flash('success', 'Subscription checkout received. Your homepage visibility boost will activate once payment is confirmed.');
}
if (isset($_GET['cancelled'])) {
    flash('info', 'Visibility boost checkout was cancelled.');
}

render_layout('storefront-home', 'storefront/pages/visibility-boost.php', [
    'page' => 'visibility-boost',
    'title' => 'Boost Product Visibility | ' . $brand['name'],
    'metaDescription' => 'Boost your Seller Africa product visibility and get ranked on the marketplace homepage for $5/month.',
    'vendor' => $vendor,
    'activeBoost' => $vendor ? VisibilityBoostService::activeBoost((int)$vendor['id']) : null,
    'cartCount' => $cart->count(),
]);
