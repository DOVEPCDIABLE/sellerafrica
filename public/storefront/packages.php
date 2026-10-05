<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\AuthService;
use App\CartService;
use App\VendorSubscriptionService;

VendorSubscriptionService::ensureSchema();

function packages_current_vendor(): ?array {
    if (!AuthService::check()) {
        return null;
    }

    return db()->fetch('SELECT * FROM vendors WHERE user_id = ? LIMIT 1', [(int)($_SESSION['user_id'] ?? 0)]);
}

function packages_require_vendor(): array {
    $vendor = packages_current_vendor();
    if ($vendor) {
        return $vendor;
    }

    if (!AuthService::check()) {
        $_SESSION['intended_url'] = app_url('packages');
        flash('warning', 'Sign in with your vendor account to manage packages.');
        redirect('login');
    }

    flash('warning', 'Subscription packages are available inside the vendor dashboard.');
    redirect('vendor/register');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validateCsrf();
    $vendor = packages_require_vendor();
    if ($vendor['status'] !== 'active' || $vendor['kyc_status'] !== 'approved') {
        redirect('vendor/verification');
    }

    $packageId = max(0, (int)($_POST['package_id'] ?? 0));
    try {
        $checkoutUrl = VendorSubscriptionService::createCheckoutSession(
            (int)$vendor['id'],
            $packageId,
            app_url('packages?subscribed=1'),
            app_url('packages?cancelled=1')
        );
        redirect($checkoutUrl);
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
        redirect('packages');
    }
}

$cart = new CartService();
$brand = app_branding();
$vendor = packages_current_vendor();

if (isset($_GET['subscribed'])) {
    flash('success', 'Subscription checkout received. Stripe will confirm your package shortly.');
}
if (isset($_GET['cancelled'])) {
    flash('info', 'Subscription checkout was cancelled.');
}

render_layout('storefront-home', 'storefront/pages/packages.php', [
    'page' => 'packages',
    'title' => 'Pricing | ' . $brand['name'],
    'metaDescription' => 'Choose a Seller Africa vendor subscription package and grow your product catalog with Stripe recurring payments.',
    'packages' => VendorSubscriptionService::packages(true),
    'vendor' => $vendor,
    'vendorQuota' => $vendor ? VendorSubscriptionService::quota((int)$vendor['id']) : null,
    'graceDays' => VendorSubscriptionService::graceDays(),
    'cartCount' => $cart->count(),
]);
