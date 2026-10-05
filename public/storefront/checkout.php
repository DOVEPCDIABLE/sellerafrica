<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\CartService;
use App\AuthService;
use App\StorefrontContentService;
use App\StorefrontTemplateService;

$cart = new CartService();
$renderer = new StorefrontTemplateService(db());

$mobileCheckoutToken = trim((string)($_GET['mobile_checkout'] ?? ''));
if ($mobileCheckoutToken !== '' && table_exists('mobile_checkout_sessions')) {
    $mobileCheckout = db()->fetch(
        'SELECT id, cart_json, customer_json FROM mobile_checkout_sessions WHERE token_hash = ? AND used_at IS NULL AND expires_at > ? LIMIT 1',
        [hash('sha256', $mobileCheckoutToken), sql_now()]
    );

    if ($mobileCheckout) {
        $mobileItems = json_decode((string)$mobileCheckout['cart_json'], true);
        if (is_array($mobileItems)) {
            $cart->clear();
            foreach ($mobileItems as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $productId = (int)($item['product_id'] ?? 0);
                $quantity = max(1, min(99, (int)($item['quantity'] ?? 1)));
                if ($productId > 0) {
                    $cart->add($productId, $quantity);
                }
            }
        }

        $mobileCustomer = json_decode((string)($mobileCheckout['customer_json'] ?? ''), true);
        if (is_array($mobileCustomer)) {
            $_SESSION['mobile_checkout_customer'] = $mobileCustomer;
        }
        db()->query('UPDATE mobile_checkout_sessions SET used_at = ? WHERE id = ?', [sql_now(), (int)$mobileCheckout['id']]);
        flash('success', 'Your app cart is ready. Complete checkout below.');
        redirect('storefront/checkout');
    } else {
        flash('error', 'That mobile checkout link has expired. Please start checkout from the app again.');
        redirect('storefront/checkout');
    }
}

$items = $renderer->cartProducts($cart->items());
$brand = app_branding();

if ((string)($_GET['stripe_cancelled'] ?? '') === '1') {
    $cancelledOrderNumber = trim((string)($_GET['order'] ?? ''));
    if ($cancelledOrderNumber !== '' && table_exists('orders') && table_exists('payments')) {
        $order = db()->fetch('SELECT id, payment_status FROM orders WHERE order_number = ? LIMIT 1', [$cancelledOrderNumber]);
        if ($order && (string)($order['payment_status'] ?? '') !== 'paid') {
            db()->query(
                "UPDATE payments
                 SET status = 'cancelled', provider_status = 'checkout_cancelled'
                 WHERE order_id = ? AND provider = 'stripe' AND status <> 'paid'",
                [(int)$order['id']]
            );
            db()->query(
                "UPDATE orders
                 SET payment_status = 'payment_cancelled', status = CASE WHEN status = 'pending' THEN 'failed' ELSE status END
                 WHERE id = ? AND payment_status <> 'paid'",
                [(int)$order['id']]
            );
        }
    }
    flash('error', 'Stripe payment was cancelled. Your order is not confirmed until payment is completed.');
}

$customer = class_exists(AuthService::class) ? AuthService::user() : null;
if ($customer && !empty($customer['id'])) {
    $profile = db()->fetch('SELECT first_name, last_name, display_name, email, phone FROM users WHERE id = ? LIMIT 1', [(int)$customer['id']]) ?: [];
    $address = db()->fetch(
        "SELECT *
         FROM addresses
         WHERE user_id = ? AND type = 'shipping'
         ORDER BY is_default DESC, updated_at DESC, id DESC
         LIMIT 1",
        [(int)$customer['id']]
    ) ?: [];
    $customer = array_merge($customer, [
        'first_name' => $profile['first_name'] ?? '',
        'last_name' => $profile['last_name'] ?? '',
        'display_name' => $profile['display_name'] ?? $customer['display_name'] ?? '',
        'email' => $profile['email'] ?? $customer['email'] ?? '',
        'phone' => $profile['phone'] ?? '',
        'address' => $address,
    ]);
} elseif (is_array($_SESSION['mobile_checkout_customer'] ?? null)) {
    $mobileCustomer = $_SESSION['mobile_checkout_customer'];
    $customer = [
        'first_name' => (string)($mobileCustomer['first_name'] ?? ''),
        'last_name' => (string)($mobileCustomer['last_name'] ?? ''),
        'display_name' => trim((string)($mobileCustomer['first_name'] ?? '') . ' ' . (string)($mobileCustomer['last_name'] ?? '')),
        'email' => (string)($mobileCustomer['email'] ?? ''),
        'phone' => (string)($mobileCustomer['phone'] ?? ''),
        'address' => [
            'address_line1' => (string)($mobileCustomer['address_line1'] ?? ''),
            'address_line2' => (string)($mobileCustomer['address_line2'] ?? ''),
            'city' => (string)($mobileCustomer['city'] ?? ''),
            'state' => (string)($mobileCustomer['state'] ?? ''),
            'postcode' => (string)($mobileCustomer['postcode'] ?? ''),
            'country_code' => (string)($mobileCustomer['country_code'] ?? 'US'),
        ],
    ];
}

echo $renderer->render('checkout.html', [
    'page' => 'checkout',
    'title' => 'Checkout | ' . $brand['name'],
    'cartItems' => $items,
    'products' => $renderer->products(12),
    'categories' => $renderer->categories(),
    'contentBlocks' => (new StorefrontContentService(db()))->all(),
    'cartCount' => $cart->count(),
    'customer' => $customer,
]);
