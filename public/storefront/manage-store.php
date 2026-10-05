<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\CartService;
use App\ManageStoreService;

ManageStoreService::ensureSchema();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        validateCsrf();
        $name = (string)($_POST['name'] ?? '');
        $email = (string)($_POST['email'] ?? '');
        $checkoutUrl = ManageStoreService::createStripeCheckoutSession(
            $name,
            $email,
            (string)($_POST['plan'] ?? 'annual'),
            app_url('manage-store?subscribed=1'),
            app_url('manage-store?cancelled=1')
        );
        header('Location: ' . $checkoutUrl, true, 303);
        exit;
    } catch (\Throwable $e) {
        error_log('Manage My Store checkout failed: ' . $e->getMessage());
        flash('error', $e->getMessage());
        redirect('manage-store');
    }
}

if (isset($_GET['subscribed'])) {
    flash('info', 'Thank you. Your service will be confirmed once your payment is verified. Our team will contact you using your checkout email.');
}
if (isset($_GET['cancelled'])) {
    flash('info', 'Manage My Store checkout was cancelled. You can activate it whenever you are ready.');
}

$cart = new CartService();
$selectedPlan = ($_GET['route'] ?? '') === 'setup-store' || ($_GET['plan'] ?? '') === 'setup' ? 'setup' : 'annual';

render_layout('manage-store', 'storefront/pages/manage-store.php', [
    'page' => 'manage-store',
    'title' => ($selectedPlan === 'setup' ? 'Set Up My Store' : 'Manage My Store') . ' | ' . app_branding()['name'],
    'selectedPlan' => $selectedPlan,
    'meta_description' => $selectedPlan === 'setup' ? 'Get help setting up your Seller Africa store for a one-time $5 payment. No recurring charge.' : 'Choose one-time store setup for $5 or ongoing store management for $12/year.',
    'cartCount' => $cart->count(),
]);
