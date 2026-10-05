<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/core/bootstrap.php';

use App\CartService;
use App\AffiliateService;
use App\CheckoutService;
use App\CommerceSafetyService;
use App\PaymentService;
use App\StorefrontTemplateService;
use App\VendorSubscriptionService;

header('Content-Type: application/json');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('Invalid checkout request.');
    }

    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($submittedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'message' => 'Your checkout session expired. Refresh the checkout page and try again.',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db = db();
    CommerceSafetyService::ensureSchema();
    VendorSubscriptionService::ensureSchema();
    $cart = new CartService();
    $renderer = new StorefrontTemplateService($db);
    $items = $renderer->cartProducts($cart->items());
    $currencies = $renderer->currencies();

    if ($items === []) {
        throw new RuntimeException('Your cart is empty.');
    }

    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $addressLine1 = trim((string)($_POST['address_line1'] ?? ''));
    $addressLine2 = trim((string)($_POST['address_line2'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $state = trim((string)($_POST['state'] ?? ''));
    $postcode = trim((string)($_POST['postcode'] ?? ''));
    $country = CheckoutService::normalizeCountryCode((string)($_POST['country_code'] ?? ''));
    $note = trim((string)($_POST['customer_note'] ?? ''));
    $paymentMethodId = (int)($_POST['payment_method_id'] ?? 0);
    $currency = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($_POST['currency'] ?? 'USD')) ?: 'USD', 0, 3));
    $checkoutMode = (string)($_POST['checkout_mode'] ?? 'guest');
    $createAccount = $checkoutMode === 'create_account' || (string)($_POST['create_account'] ?? '') === '1';
    $password = (string)($_POST['account_password'] ?? '');
    $separateBilling = array_key_exists('billing_first_name', $_POST) && (string)($_POST['billing_same_as_shipping'] ?? '') !== '1';
    $billing = [];
    if ($separateBilling) {
        foreach (['first_name', 'last_name', 'address_line1', 'address_line2', 'city', 'state', 'postcode', 'country_code'] as $field) {
            $billing[$field] = trim((string)($_POST['billing_' . $field] ?? ''));
            if ($field !== 'address_line2' && $billing[$field] === '') {
                throw new RuntimeException('Complete your billing ' . str_replace('_', ' ', $field) . '.');
            }
        }
        $billing['country_code'] = CheckoutService::normalizeCountryCode($billing['country_code']);
    }

    if ($firstName === '' || $lastName === '') {
        throw new RuntimeException('Enter your first and last name.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address.');
    }

    if ($phone === '') {
        throw new RuntimeException('Enter your phone number.');
    }

    if ($addressLine1 === '') {
        throw new RuntimeException('Enter your delivery address.');
    }
    foreach (['city' => $city, 'state, province or region' => $state, 'postal / ZIP code' => $postcode] as $field => $value) {
        if ($value === '') {
            throw new RuntimeException('Enter your delivery ' . $field . '.');
        }
    }

    if ($createAccount && strlen($password) < 8) {
        throw new RuntimeException('Enter an account password with at least 8 characters, or continue as guest.');
    }

    $paymentMethod = $db->fetch(
        "SELECT id, code, name, provider, settings
         FROM payment_methods
         WHERE id = ?
           AND is_active = 1
           AND provider <> 'manual'
           AND LOWER(code) NOT IN ('cod', 'cash_on_delivery')
           AND LOWER(name) NOT LIKE '%cash on delivery%'
         LIMIT 1",
        [$paymentMethodId]
    );

    if (!$paymentMethod) {
        throw new RuntimeException('Select a valid online payment provider.');
    }

    $paymentProvider = strtolower((string)$paymentMethod['provider']);
    if (!in_array($paymentProvider, ['klasha', 'stripe', 'paystack'], true)) {
        throw new RuntimeException('This payment provider is not ready for secure checkout yet. Please select Stripe, Klasha, or Paystack.');
    }

    if ((string)$paymentMethod['provider'] === 'klasha' && !PaymentService::isKlashaConfigured($paymentMethod)) {
        throw new RuntimeException('Klasha payment is not fully configured yet. Please select another payment method or contact support.');
    }
    if ((string)$paymentMethod['provider'] === 'stripe' && !PaymentService::isStripeConfigured($paymentMethod)) {
        throw new RuntimeException('Stripe payment is not fully configured yet. Please select another payment method or contact support.');
    }
    if ((string)$paymentMethod['provider'] === 'paystack' && !PaymentService::isPaystackConfigured($paymentMethod)) {
        throw new RuntimeException('Paystack payment is not fully configured yet. Please select another payment method or contact support.');
    }

    $currencyAllowed = false;
    foreach ($currencies as $currencyRow) {
        if (strtoupper((string)$currencyRow['code']) === $currency) {
            $currencyAllowed = true;
            break;
        }
    }

    if (!$currencyAllowed) {
        throw new RuntimeException('Select a valid currency.');
    }

    $rateFor = static function (string $code) use ($currencies): float {
        foreach ($currencies as $currencyRow) {
            if (strtoupper((string)$currencyRow['code']) === strtoupper($code)) {
                return max(0.000001, (float)($currencyRow['exchangeRate'] ?? 1));
            }
        }

        return 1.0;
    };

    $convert = static function (float $amount, string $from, string $to) use ($rateFor): float {
        return ($amount / $rateFor($from)) * $rateFor($to);
    };

    $subtotal = 0.0;
    $totalQuantity = 0;
    foreach ($items as $item) {
        $quantity = max(1, (int)($item['quantity'] ?? 1));
        $totalQuantity += $quantity;
        $subtotal += $convert((float)($item['rawPrice'] ?? 0), (string)($item['currency'] ?? 'USD'), $currency) * $quantity;
    }

    $productIds = array_values(array_unique(array_filter(array_map(static fn (array $item): int => (int)($item['id'] ?? 0), $items))));
    $productRows = $productIds === [] ? [] : $db->fetchAll(
        'SELECT id, vendor_id, weight, manage_stock, stock_quantity, stock_status FROM products WHERE id IN (' . implode(',', array_fill(0, count($productIds), '?')) . ')',
        $productIds
    );
    $productMeta = [];
    foreach ($productRows as $row) {
        $productMeta[(int)$row['id']] = $row;
    }

    $totalWeight = 0.0;
    $vendorIds = [];
    foreach ($items as $item) {
        $productId = (int)($item['id'] ?? 0);
        $quantity = max(1, (int)($item['quantity'] ?? 1));
        $meta = $productMeta[$productId] ?? [];
        $totalWeight += (float)($meta['weight'] ?? 0) * $quantity;
        if (!empty($meta['vendor_id'])) {
            $vendorIds[] = (int)$meta['vendor_id'];
        }
    }

    $checkoutQuote = (new CheckoutService($db))->quote($cart->items(), $currency, [
        'country_code' => $country,
        'state' => $state,
        'postcode' => $postcode,
        'city' => $city,
        'address_line1' => $addressLine1,
        'address_line2' => $addressLine2,
        'name' => trim($firstName . ' ' . $lastName),
        'phone' => $phone,
        'email' => $email,
    ]);
    if (abs((float)$checkoutQuote['subtotal'] - $subtotal) > 0.01) {
        throw new RuntimeException('Your cart changed. Review your cart and try checkout again.');
    }

    $subtotal = (float)$checkoutQuote['subtotal'];
    $shippingTotal = (float)$checkoutQuote['shipping'];
    $taxTotal = (float)$checkoutQuote['tax'];
    $grandTotal = (float)$checkoutQuote['total'];
    if (isset($_POST['reviewed_total']) && (!is_numeric($_POST['reviewed_total']) || abs((float)$_POST['reviewed_total'] - $grandTotal) > 0.01)) {
        throw new RuntimeException('Your order total or shipping cost has changed. Select Edit details and review your order again before paying.');
    }
    $shippingRateSnapshot = $checkoutQuote['shipping_rate_snapshot'] ?? [];
    $customerId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $orderNumber = 'SA-' . app_date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $newCustomerSession = null;

    $db->beginTransaction();

    $reservedStock = [];
    foreach ($items as $item) {
        $productId = (int)($item['id'] ?? 0);
        $quantity = max(1, (int)($item['quantity'] ?? 1));
        if ($productId <= 0) {
            continue;
        }

        $locked = $db->fetch(
            'SELECT id, name, manage_stock, stock_quantity, stock_status FROM products WHERE id = ? FOR UPDATE',
            [$productId]
        );
        if (!$locked || (string)($locked['stock_status'] ?? '') === 'out_of_stock') {
            throw new RuntimeException('One of the products in your cart is no longer available.');
        }

        \App\ProductVariationService::assertPurchasable($productId, $quantity);

        if ((int)($locked['manage_stock'] ?? 0) === 1) {
            $available = (int)($locked['stock_quantity'] ?? 0);
            if ($available < $quantity) {
                throw new RuntimeException((string)$locked['name'] . ' has only ' . max(0, $available) . ' left in stock.');
            }

            $after = $available - $quantity;
            $db->query(
                "UPDATE products
                 SET stock_quantity = ?, stock_status = CASE WHEN ? <= 0 THEN 'out_of_stock' ELSE stock_status END, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?",
                [$after, $after, $productId]
            );
            $reservedStock[] = ['product_id' => $productId, 'quantity' => $quantity, 'after' => $after];
        }
    }

    if (!$customerId && $createAccount) {
        $existingUser = $db->fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
        if ($existingUser) {
            throw new RuntimeException('An account already exists for this email. Sign in first or continue as guest.');
        }

        $usernameBase = strtolower(preg_replace('/[^a-z0-9]+/i', '', strtok($email, '@') ?: $firstName . $lastName) ?: 'customer');
        $username = $usernameBase;
        for ($i = 1; $db->fetch('SELECT id FROM users WHERE username = ? LIMIT 1', [$username]); $i++) {
            $username = $usernameBase . $i;
        }

        $db->query(
            "INSERT INTO users (email, username, password_hash, first_name, last_name, display_name, phone, status, email_verified_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)",
            [
                $email,
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                $firstName,
                $lastName,
                trim($firstName . ' ' . $lastName),
                $phone,
                sql_now(),
                sql_now(),
                sql_now(),
            ]
        );
        $customerId = (int)$db->lastInsertId();

        $customerRole = $db->fetch("SELECT id FROM roles WHERE code = 'customer' LIMIT 1");
        if ($customerRole) {
            $db->query(
                'INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)',
                [$customerId, (int)$customerRole['id']]
            );
        }

        $newCustomerSession = [
            'user_id' => $customerId,
            'user_email' => $email,
            'display_name' => trim($firstName . ' ' . $lastName),
            'roles' => ['customer'],
        ];
    }

    if ($customerId) {
        $db->query(
            "UPDATE users
             SET first_name = COALESCE(NULLIF(first_name, ''), ?),
                 last_name = COALESCE(NULLIF(last_name, ''), ?),
                 display_name = COALESCE(NULLIF(display_name, ''), ?),
                 phone = ?,
                 updated_at = ?
             WHERE id = ?",
            [$firstName, $lastName, trim($firstName . ' ' . $lastName), $phone, sql_now(), $customerId]
        );
    }

    $addressId = null;
    if ($addressLine1 !== '') {
        if ($customerId) {
            $db->query('UPDATE addresses SET is_default = 0 WHERE user_id = ? AND type = "shipping"', [$customerId]);
        }
        $db->query(
            "INSERT INTO addresses
                (user_id, type, first_name, last_name, company, phone, email, address_line1, address_line2, city, state, postcode, country_code, is_default)
             VALUES (?, 'shipping', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $customerId,
                $firstName,
                $lastName,
                trim((string)($_POST['company'] ?? '')) ?: null,
                $phone,
                $email,
                $addressLine1,
                $addressLine2 ?: null,
                $city ?: null,
                $state ?: null,
                $postcode ?: null,
                $country,
                $customerId ? 1 : 0,
            ]
        );
        $addressId = (int)$db->lastInsertId();
    }

    $billingAddressId = $addressId;
    if ($separateBilling) {
        $db->query(
            "INSERT INTO addresses (user_id, type, first_name, last_name, phone, email, address_line1, address_line2, city, state, postcode, country_code, is_default)
             VALUES (?, 'billing', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)",
            [$customerId, $billing['first_name'], $billing['last_name'], $phone, $email, $billing['address_line1'], $billing['address_line2'] ?: null, $billing['city'], $billing['state'], $billing['postcode'], $billing['country_code']]
        );
        $billingAddressId = (int)$db->lastInsertId();
    }

    $db->query(
        "INSERT INTO orders
            (order_number, customer_id, guest_email, status, payment_status, fulfillment_status, currency,
             subtotal, discount_total, shipping_total, shipping_rate_snapshot, tax_total, fee_total, grand_total,
             billing_address_id, shipping_address_id, customer_note, ip_address, user_agent, placed_at)
         VALUES (?, ?, ?, 'pending', 'payment_pending', 'unfulfilled', ?, ?, 0, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)",
        [
            $orderNumber,
            $customerId,
            $customerId ? null : $email,
            $currency,
            $subtotal,
            $shippingTotal,
            json_encode($shippingRateSnapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $taxTotal,
            $grandTotal,
            $billingAddressId,
            $addressId,
            $note ?: null,
            client_ip(),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            sql_now(),
        ]
    );
    $orderId = (int)$db->lastInsertId();

    foreach ($items as $item) {
        $quantity = max(1, (int)($item['quantity'] ?? 1));
        $unitPrice = $convert((float)($item['rawPrice'] ?? 0), (string)($item['currency'] ?? 'USD'), $currency);
        $lineTotal = $unitPrice * $quantity;

        $db->query(
            "INSERT INTO order_items
                (order_id, product_id, vendor_id, item_type, name, sku, quantity, unit_price, subtotal, discount_total, tax_total, total, metadata)
             SELECT ?, p.id, p.vendor_id, 'product', p.name, p.sku, ?, ?, ?, 0, 0, ?,
                    JSON_OBJECT('image', ?, 'vendor', ?)
             FROM products p
             WHERE p.id = ?
             LIMIT 1",
            [
                $orderId,
                $quantity,
                $unitPrice,
                $lineTotal,
                $lineTotal,
                $item['image'] ?? null,
                $item['vendor'] ?? null,
                (int)$item['id'],
            ]
        );
    }

    if (table_exists('product_inventory_movements')) {
        foreach ($reservedStock as $movement) {
            $db->query(
                "INSERT INTO product_inventory_movements
                    (product_id, movement_type, quantity_change, quantity_after, reference_type, reference_id, note, created_by)
                 VALUES (?, 'sale', ?, ?, 'order', ?, ?, ?)",
                [
                    (int)$movement['product_id'],
                    -1 * (int)$movement['quantity'],
                    (int)$movement['after'],
                    $orderId,
                    'Reserved at checkout order creation',
                    $customerId,
                ]
            );
        }
    }

    $db->query(
        "INSERT INTO order_vendor_splits
            (order_id, vendor_id, status, item_subtotal, discount_total, shipping_total, tax_total, gross_total, vendor_earning, platform_commission, created_at, updated_at)
         SELECT
            oi.order_id,
            oi.vendor_id,
            'pending',
            COALESCE(SUM(oi.subtotal), 0),
            COALESCE(SUM(oi.discount_total), 0),
            0,
            COALESCE(SUM(oi.tax_total), 0),
            COALESCE(SUM(oi.total), 0),
            GREATEST(COALESCE(SUM(oi.total), 0) - (COALESCE(SUM(oi.total), 0) * COALESCE(vp.transaction_fee_percent, v.commission_rate, 0) / 100), 0),
            COALESCE(SUM(oi.total), 0) * COALESCE(vp.transaction_fee_percent, v.commission_rate, 0) / 100,
            ?,
            ?
         FROM order_items oi
         INNER JOIN vendors v ON v.id = oi.vendor_id
         LEFT JOIN vendor_subscriptions vs ON vs.id = (
            SELECT vs2.id
            FROM vendor_subscriptions vs2
            WHERE vs2.vendor_id = oi.vendor_id
              AND (
                vs2.status IN ('active', 'trialing')
                OR (vs2.status IN ('past_due', 'unpaid') AND vs2.grace_ends_at IS NOT NULL AND vs2.grace_ends_at >= NOW())
              )
            ORDER BY FIELD(vs2.status, 'active', 'trialing', 'past_due', 'unpaid'), vs2.updated_at DESC, vs2.id DESC
            LIMIT 1
         )
         LEFT JOIN vendor_packages vp ON vp.id = vs.package_id
         WHERE oi.order_id = ? AND oi.vendor_id IS NOT NULL
         GROUP BY oi.order_id, oi.vendor_id, v.commission_rate, vp.transaction_fee_percent
         ON DUPLICATE KEY UPDATE
            item_subtotal = VALUES(item_subtotal),
            discount_total = VALUES(discount_total),
            tax_total = VALUES(tax_total),
            gross_total = VALUES(gross_total),
            vendor_earning = VALUES(vendor_earning),
            platform_commission = VALUES(platform_commission),
            updated_at = VALUES(updated_at)",
        [sql_now(), sql_now(), $orderId]
    );

    if ($shippingTotal > 0) {
        $db->query(
            "UPDATE order_vendor_splits
             SET shipping_total = ROUND(item_subtotal / NULLIF(?, 0) * ?, 4),
                 gross_total = item_subtotal + shipping_total + tax_total - discount_total,
                 updated_at = ?
             WHERE order_id = ?",
            [$subtotal, $shippingTotal, sql_now(), $orderId]
        );
    }

    $db->query(
        "UPDATE order_items oi
         INNER JOIN order_vendor_splits ovs ON ovs.order_id = oi.order_id AND ovs.vendor_id = oi.vendor_id
         SET oi.vendor_split_id = ovs.id
         WHERE oi.order_id = ?",
        [$orderId]
    );

    $providerReference = match ((string)$paymentMethod['provider']) {
        'klasha' => PaymentService::transactionReference($orderNumber),
        'paystack' => PaymentService::paystackOrderReference($orderNumber),
        default => $orderNumber . '-' . (string)$paymentMethod['code'],
    };
    $db->query(
        "INSERT INTO payments
            (order_id, user_id, payment_method_id, provider, provider_reference, provider_status, amount, currency, status, raw_response)
         VALUES (?, ?, ?, ?, ?, 'created', ?, ?, 'pending', ?)",
        [
            $orderId,
            $customerId,
            (int)$paymentMethod['id'],
            (string)$paymentMethod['provider'],
            $providerReference,
            $grandTotal,
            $currency,
            json_encode([
                'method' => $paymentMethod['code'],
                'name' => $paymentMethod['name'],
                'created_from' => 'storefront_checkout',
                'checkout_mode' => $customerId ? ($createAccount ? 'created_account' : 'customer') : 'guest',
                'expected_order_total' => $grandTotal,
                'shipping_amount' => $shippingTotal,
                'shipping_rate_snapshot' => $shippingRateSnapshot,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]
    );
    $paymentId = (int)$db->lastInsertId();

    $db->query(
        "INSERT INTO order_status_history (order_id, old_status, new_status, note, changed_by)
         VALUES (?, NULL, 'pending', ?, ?)",
        [$orderId, 'Order created from storefront checkout.', $customerId]
    );

    AffiliateService::createReferralForOrder($orderId, $customerId, $grandTotal, $currency);

    $db->commit();

    if ($newCustomerSession) {
        $_SESSION['user_id'] = $newCustomerSession['user_id'];
        $_SESSION['user_email'] = $newCustomerSession['user_email'];
        $_SESSION['display_name'] = $newCustomerSession['display_name'];
        $_SESSION['roles'] = $newCustomerSession['roles'];
    }

    try {
        $redirectUrl = match ($paymentProvider) {
            'klasha' => app_url('storefront/klasha-pay?order=' . rawurlencode($orderNumber)),
            'stripe' => PaymentService::createStripeCheckoutSession(
                [
                    'id' => $orderId,
                    'order_number' => $orderNumber,
                    'grand_total' => $grandTotal,
                    'currency' => $currency,
                ],
                [
                    'id' => $paymentId,
                    'amount' => $grandTotal,
                    'currency' => $currency,
                    'provider_reference' => $providerReference,
                ],
                [
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ],
                $paymentMethod
            ),
            'paystack' => PaymentService::createPaystackCheckoutUrl(
                [
                    'id' => $orderId,
                    'order_number' => $orderNumber,
                    'grand_total' => $grandTotal,
                    'currency' => $currency,
                ],
                [
                    'id' => $paymentId,
                    'amount' => $grandTotal,
                    'currency' => $currency,
                    'provider_reference' => $providerReference,
                ],
                [
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ],
                $paymentMethod
            ),
            default => throw new RuntimeException('This payment provider is not ready for secure checkout yet.'),
        };
    } catch (Throwable $paymentError) {
        foreach (($reservedStock ?? []) as $movement) {
            $productId = (int)($movement['product_id'] ?? 0);
            $quantity = (int)($movement['quantity'] ?? 0);
            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $product = $db->fetch('SELECT id, manage_stock, stock_quantity FROM products WHERE id = ? LIMIT 1', [$productId]);
            if (!$product || (int)($product['manage_stock'] ?? 0) !== 1) {
                continue;
            }

            $after = max(0, (int)($product['stock_quantity'] ?? 0)) + $quantity;
            $db->query(
                "UPDATE products
                 SET stock_quantity = ?, stock_status = 'in_stock', updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?",
                [$after, $productId]
            );

            if (table_exists('product_inventory_movements')) {
                $db->query(
                    "INSERT INTO product_inventory_movements
                        (product_id, movement_type, quantity_change, quantity_after, reference_type, reference_id, note, created_by)
                     VALUES (?, 'reservation_release', ?, ?, 'order_payment_start_failed', ?, ?, ?)",
                    [$productId, $quantity, $after, $orderId, 'Restored after payment checkout failed to start', $customerId]
                );
            }
        }

        $db->query(
            "UPDATE payments
             SET status = 'failed', provider_status = 'checkout_start_failed', raw_response = JSON_SET(COALESCE(raw_response, JSON_OBJECT()), '$.checkout_start_error', ?)
             WHERE id = ?",
            [$paymentError->getMessage(), $paymentId]
        );
        $db->query(
            "UPDATE orders
             SET payment_status = 'payment_failed', status = CASE WHEN status = 'pending' THEN 'failed' ELSE status END
             WHERE id = ? AND payment_status <> 'paid'",
            [$orderId]
        );
        throw new RuntimeException('Payment failed to start. Please try again or choose another payment method.');
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Continue to secure payment to complete your order.',
        'order' => [
            'id' => $orderId,
            'number' => $orderNumber,
            'total' => StorefrontTemplateService::money($grandTotal, $currency),
            'status' => 'pending',
            'checkout_mode' => $customerId ? 'customer' : 'guest',
        ],
        'payment' => [
            'id' => $paymentId,
            'provider' => (string)$paymentMethod['provider'],
            'method' => (string)$paymentMethod['name'],
            'status' => 'pending',
        ],
        'redirect_url' => $redirectUrl,
        'items' => $items,
        'cart_count' => $cart->count(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($db) && $db->pdo()->inTransaction()) {
        $db->rollBack();
    }

    http_response_code($e instanceof RuntimeException ? 422 : 500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function calculate_checkout_shipping(App\Database $db, float $subtotal, int $quantity, float $weight, array $vendorIds, string $country, string $state, string $postcode, string $city): float
{
    if (!table_exists('shipping_methods') || !table_exists('shipping_rates')) {
        return checkout_fallback_shipping_total($subtotal, $quantity);
    }

    $zoneIds = checkout_matching_shipping_zone_ids($db, $country, $state, $postcode, $city);
    $zonePlaceholders = $zoneIds !== [] ? implode(',', array_fill(0, count($zoneIds), '?')) : '';
    $vendorIds = array_values(array_unique(array_filter($vendorIds)));
    $vendorPlaceholders = $vendorIds !== [] ? implode(',', array_fill(0, count($vendorIds), '?')) : '';

    $conditions = ['m.is_active = 1', "(m.min_order_amount IS NULL OR m.min_order_amount <= ?)"];
    $params = [$subtotal];

    if ($zoneIds !== []) {
        $conditions[] = "(m.zone_id IS NULL OR m.zone_id IN ({$zonePlaceholders}))";
        array_push($params, ...$zoneIds);
    } else {
        $conditions[] = 'm.zone_id IS NULL';
    }

    if ($vendorIds !== []) {
        $conditions[] = "(m.vendor_id IS NULL OR m.vendor_id IN ({$vendorPlaceholders}))";
        array_push($params, ...$vendorIds);
    } else {
        $conditions[] = 'm.vendor_id IS NULL';
    }

    $methods = $db->fetchAll(
        "SELECT m.id, m.calculation_type, m.base_cost, m.zone_id, m.vendor_id,
                r.condition_type, r.min_value, r.max_value, r.cost, r.per_item_cost
         FROM shipping_methods m
         LEFT JOIN shipping_rates r ON r.method_id = m.id
         WHERE " . implode(' AND ', $conditions) . "
         ORDER BY m.vendor_id IS NULL ASC, m.zone_id IS NULL ASC, m.base_cost ASC, r.sort_order ASC, m.id ASC",
        $params
    );

    $best = null;
    foreach ($methods as $method) {
        if ((string)$method['calculation_type'] === 'free_shipping') {
            return 0.0;
        }

        $conditionType = (string)($method['condition_type'] ?? 'none');
        $basis = match ($conditionType) {
            'weight' => $weight,
            'subtotal' => $subtotal,
            'quantity' => (float)$quantity,
            default => 0.0,
        };

        if ($conditionType !== 'none') {
            $min = $method['min_value'] !== null ? (float)$method['min_value'] : null;
            $max = $method['max_value'] !== null ? (float)$method['max_value'] : null;
            if (($min !== null && $basis < $min) || ($max !== null && $basis > $max)) {
                continue;
            }
        }

        $cost = (float)($method['base_cost'] ?? 0) + (float)($method['cost'] ?? 0) + ((float)($method['per_item_cost'] ?? 0) * $quantity);
        $best = $best === null ? $cost : min($best, $cost);
    }

    return round(max(0.0, (float)($best ?? checkout_fallback_shipping_total($subtotal, $quantity))), 4);
}

function checkout_fallback_shipping_total(float $subtotal, int $quantity): float
{
    if ($quantity <= 0 || $subtotal <= 0) {
        return 0.0;
    }

    $configured = trim((string)(app_setting('shipping', 'fallback_shipping_amount', '9.99') ?? '9.99'));
    $amount = is_numeric($configured) ? (float)$configured : 9.99;

    return round(max(0.0, $amount), 4);
}

function checkout_matching_shipping_zone_ids(App\Database $db, string $country, string $state, string $postcode, string $city): array
{
    if (!table_exists('shipping_zones')) {
        return [];
    }

    if (!table_exists('shipping_zone_locations')) {
        $rows = $db->fetchAll('SELECT id FROM shipping_zones WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
        return array_map(static fn (array $row): int => (int)$row['id'], $rows);
    }

    $rows = $db->fetchAll(
        "SELECT DISTINCT z.id
         FROM shipping_zones z
         LEFT JOIN shipping_zone_locations l ON l.zone_id = z.id
         WHERE z.is_active = 1
           AND (
               l.id IS NULL
               OR (l.location_type = 'country' AND UPPER(l.location_code) = ?)
               OR (l.location_type = 'state' AND UPPER(l.location_code) = ?)
               OR (l.location_type = 'postcode' AND UPPER(l.location_code) = ?)
               OR (l.location_type = 'city' AND UPPER(l.location_code) = ?)
           )
         ORDER BY z.sort_order ASC, z.id ASC",
        [
            strtoupper($country),
            strtoupper($state),
            strtoupper($postcode),
            strtoupper($city),
        ]
    );

    return array_map(static fn (array $row): int => (int)$row['id'], $rows);
}
