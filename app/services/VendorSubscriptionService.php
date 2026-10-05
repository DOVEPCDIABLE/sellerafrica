<?php

declare(strict_types=1);

namespace App;

use Stripe\Checkout\Session;
use Stripe\Stripe;
use Stripe\Webhook;

final class VendorSubscriptionService
{
    public static function ensureSchema(): void
    {
        \db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS vendor_packages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(140) NOT NULL UNIQUE,
                description TEXT NULL,
                price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                billing_interval ENUM('month', 'year') NOT NULL DEFAULT 'month',
                product_limit INT NULL,
                transaction_fee_percent DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
                features JSON NULL,
                stripe_price_id VARCHAR(190) NULL,
                is_featured TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_vendor_packages_active_sort (is_active, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        \db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS vendor_subscriptions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                vendor_id BIGINT UNSIGNED NOT NULL,
                package_id BIGINT UNSIGNED NOT NULL,
                status ENUM('active', 'trialing', 'past_due', 'unpaid', 'cancelled', 'incomplete', 'expired') NOT NULL DEFAULT 'active',
                provider VARCHAR(40) NOT NULL DEFAULT 'manual',
                stripe_customer_id VARCHAR(190) NULL,
                stripe_subscription_id VARCHAR(190) NULL UNIQUE,
                stripe_checkout_session_id VARCHAR(190) NULL,
                paystack_reference VARCHAR(190) NULL UNIQUE,
                current_period_start DATETIME NULL,
                current_period_end DATETIME NULL,
                grace_ends_at DATETIME NULL,
                cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
                started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                paid_at DATETIME NULL,
                last_payment_failed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_vendor_subscriptions_vendor_status (vendor_id, status),
                INDEX idx_vendor_subscriptions_package (package_id),
                CONSTRAINT fk_vendor_subscriptions_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
                CONSTRAINT fk_vendor_subscriptions_package FOREIGN KEY (package_id) REFERENCES vendor_packages(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if (!self::columnExists('vendor_subscriptions', 'paystack_reference')) {
            \db()->pdo()->exec("ALTER TABLE vendor_subscriptions ADD paystack_reference VARCHAR(190) NULL UNIQUE AFTER stripe_checkout_session_id");
        }
        if (!self::columnExists('vendor_subscriptions', 'paystack_charge_amount')) {
            \db()->pdo()->exec("ALTER TABLE vendor_subscriptions ADD paystack_charge_amount DECIMAL(16, 2) NULL");
        }
        if (!self::columnExists('vendor_subscriptions', 'paystack_charge_currency')) {
            \db()->pdo()->exec("ALTER TABLE vendor_subscriptions ADD paystack_charge_currency CHAR(3) NULL");
        }

        self::seedFreePackage();
    }

    public static function seedFreePackage(): void
    {
        $exists = \db()->fetch("SELECT id FROM vendor_packages WHERE slug = 'free' LIMIT 1");
        if ($exists) {
            return;
        }

        \db()->query(
            "INSERT INTO vendor_packages
                (name, slug, description, price, currency, billing_interval, product_limit, transaction_fee_percent, features, is_active, sort_order)
             VALUES
                ('Free', 'free', 'Start selling with up to 5 products.', 0, 'USD', 'month', 5, 0, ?, 1, 0)",
            [json_encode(['Up to 5 products', 'Seller microstore', 'Basic marketplace support'], JSON_UNESCAPED_SLASHES)]
        );
    }

    public static function graceDays(): int
    {
        return max(0, min(90, (int)\app_setting('subscriptions', 'grace_days', 7)));
    }

    public static function saveGraceDays(int $days): void
    {
        if (!\table_exists('settings')) {
            return;
        }

        \db()->query(
            "INSERT INTO settings (scope, scope_id, setting_key, setting_value)
             VALUES ('subscriptions', 0, 'grace_days', ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP",
            [json_encode(max(0, min(90, $days)))]
        );
    }

    public static function packages(bool $activeOnly = true): array
    {
        self::ensureSchema();
        $sql = 'SELECT * FROM vendor_packages' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order ASC, price ASC, id ASC';
        return \db()->fetchAll($sql);
    }

    public static function package(int $packageId): ?array
    {
        self::ensureSchema();
        return \db()->fetch('SELECT * FROM vendor_packages WHERE id = ? LIMIT 1', [$packageId]);
    }

    public static function freePackage(): array
    {
        self::ensureSchema();
        return \db()->fetch("SELECT * FROM vendor_packages WHERE slug = 'free' LIMIT 1")
            ?: ['id' => 0, 'name' => 'Free', 'product_limit' => 5, 'price' => 0, 'currency' => 'USD'];
    }

    public static function activeSubscription(int $vendorId): ?array
    {
        self::ensureSchema();
        $row = \db()->fetch(
            "SELECT s.*, p.name package_name, p.slug package_slug, p.product_limit, p.price, p.currency, p.billing_interval, p.features
             FROM vendor_subscriptions s
             INNER JOIN vendor_packages p ON p.id = s.package_id
             WHERE s.vendor_id = ?
             ORDER BY FIELD(s.status, 'active', 'trialing', 'past_due', 'unpaid', 'incomplete', 'cancelled', 'expired'), s.updated_at DESC, s.id DESC
             LIMIT 1",
            [$vendorId]
        );

        if ($row && self::isSubscriptionUsable($row)) {
            return $row;
        }

        return self::ensureFreeSubscription($vendorId);
    }

    public static function ensureFreeSubscription(int $vendorId): array
    {
        $free = self::freePackage();
        $existing = \db()->fetch(
            "SELECT s.*, p.name package_name, p.slug package_slug, p.product_limit, p.price, p.currency, p.billing_interval, p.features
             FROM vendor_subscriptions s
             INNER JOIN vendor_packages p ON p.id = s.package_id
             WHERE s.vendor_id = ? AND p.slug = 'free'
             LIMIT 1",
            [$vendorId]
        );
        if ($existing) {
            return $existing;
        }

        \db()->query(
            "INSERT INTO vendor_subscriptions (vendor_id, package_id, status, provider, started_at, paid_at)
             VALUES (?, ?, 'active', 'free', ?, ?)",
            [$vendorId, (int)$free['id'], \sql_now(), \sql_now()]
        );

        return self::activeSubscription($vendorId) ?? [
            'package_name' => 'Free',
            'product_limit' => 5,
            'status' => 'active',
        ];
    }

    public static function quota(int $vendorId): array
    {
        $subscription = self::activeSubscription($vendorId);
        $count = (int)(\db()->fetch(
            "SELECT COUNT(*) total
             FROM products
             WHERE vendor_id = ?
               AND type <> 'variation'
               AND status IN ('active', 'private')",
            [$vendorId]
        )['total'] ?? 0);
        $limit = $subscription ? $subscription['product_limit'] : 5;
        $distribution = DistributorService::allowance($vendorId);
        if ($distribution !== null) {
            $limit = $distribution['product_limit'];
            $subscription['package_name'] = 'Distributor catalog';
        }
        $limit = $limit === null ? null : (int)$limit;

        return [
            'subscription' => $subscription,
            'used' => $count,
            'limit' => $limit,
            'remaining' => $limit === null ? null : max(0, $limit - $count),
            'can_upload' => $limit === null || $count < $limit,
        ];
    }

    public static function assertCanUploadProduct(int $vendorId): void
    {
        $quota = self::quota($vendorId);
        if ($quota['can_upload']) {
            return;
        }

        $plan = (string)($quota['subscription']['package_name'] ?? 'Free');
        if ($plan === 'Distributor catalog') throw new \RuntimeException('Your distributor catalog allowance is full. Contact the Seller Africa team to increase it.');
        $limit = $quota['limit'] === null ? 'unlimited' : number_format((int)$quota['limit']);
        throw new \RuntimeException("Your {$plan} package allows {$limit} products. Subscribe to a higher package before uploading more products.");
    }

    public static function upsertPackage(array $input): int
    {
        self::ensureSchema();
        $id = max(0, (int)($input['id'] ?? 0));
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('Package name is required.');
        }

        $slug = self::uniqueSlug($name, $id ?: null);
        $productLimitRaw = trim((string)($input['product_limit'] ?? ''));
        $productLimit = $productLimitRaw === '' ? null : max(0, (int)$productLimitRaw);
        $features = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($input['features'] ?? '')) ?: [])));
        $params = [
            $name,
            $slug,
            trim((string)($input['description'] ?? '')),
            max(0, (float)($input['price'] ?? 0)),
            strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($input['currency'] ?? 'USD')) ?: 'USD', 0, 3)),
            in_array((string)($input['billing_interval'] ?? 'month'), ['month', 'year'], true) ? (string)$input['billing_interval'] : 'month',
            $productLimit,
            max(0, min(100, (float)($input['transaction_fee_percent'] ?? 0))),
            json_encode($features, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            trim((string)($input['stripe_price_id'] ?? '')) ?: null,
            !empty($input['is_featured']) ? 1 : 0,
            !empty($input['is_active']) ? 1 : 0,
            (int)($input['sort_order'] ?? 0),
        ];

        if ($id > 0) {
            \db()->query(
                "UPDATE vendor_packages
                 SET name = ?, slug = ?, description = ?, price = ?, currency = ?, billing_interval = ?, product_limit = ?,
                     transaction_fee_percent = ?, features = ?, stripe_price_id = ?, is_featured = ?, is_active = ?, sort_order = ?
                 WHERE id = ?",
                [...$params, $id]
            );
            return $id;
        }

        \db()->query(
            "INSERT INTO vendor_packages
                (name, slug, description, price, currency, billing_interval, product_limit, transaction_fee_percent, features,
                 stripe_price_id, is_featured, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            $params
        );

        return (int)\db()->lastInsertId();
    }

    public static function createCheckoutSession(int $vendorId, int $packageId, string $successUrl, string $cancelUrl, string $provider = 'stripe'): string
    {
        $package = self::package($packageId);
        if (!$package || (int)$package['is_active'] !== 1) {
            throw new \RuntimeException('Package is not available.');
        }

        if ((float)$package['price'] <= 0) {
            $current = self::activeSubscription($vendorId);
            if ($current && (string)($current['provider'] ?? '') === 'stripe' && (float)($current['price'] ?? 0) > 0) {
                throw new \RuntimeException('A paid Stripe subscription is active. Cancel it in Stripe before switching to the free package.');
            }
            self::assignFreePackage($vendorId, $packageId);
            return $successUrl;
        }

        $provider = in_array($provider, ['stripe', 'paystack'], true) ? $provider : 'stripe';
        if ($provider === 'paystack') {
            return self::createPaystackCheckout($vendorId, $package, $successUrl, $cancelUrl);
        }

        $secret = self::stripeSecretKey();
        if ($secret === '') {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

        $vendor = \db()->fetch(
            "SELECT v.*, u.email, u.display_name
             FROM vendors v
             INNER JOIN users u ON u.id = v.user_id
             WHERE v.id = ?
             LIMIT 1",
            [$vendorId]
        );
        if (!$vendor) {
            throw new \RuntimeException('Vendor was not found.');
        }

        Stripe::setApiKey($secret);
        $lineItem = ['quantity' => 1];
        if (trim((string)($package['stripe_price_id'] ?? '')) !== '') {
            $lineItem['price'] = (string)$package['stripe_price_id'];
        } else {
            $lineItem['price_data'] = [
                'currency' => strtolower((string)$package['currency']),
                'unit_amount' => (int)round(((float)$package['price']) * 100),
                'recurring' => ['interval' => (string)$package['billing_interval']],
                'product_data' => [
                    'name' => (string)$package['name'],
                ],
            ];
            $description = trim((string)($package['description'] ?? ''));
            if ($description !== '') {
                $lineItem['price_data']['product_data']['description'] = $description;
            }
        }

        $session = Session::create([
            'mode' => 'subscription',
            'customer_email' => (string)$vendor['email'],
            'client_reference_id' => (string)$vendorId,
            'success_url' => $successUrl . (str_contains($successUrl, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'line_items' => [$lineItem],
            'metadata' => [
                'vendor_id' => (string)$vendorId,
                'package_id' => (string)$packageId,
            ],
            'subscription_data' => [
                'metadata' => [
                    'vendor_id' => (string)$vendorId,
                    'package_id' => (string)$packageId,
                ],
            ],
        ]);

        \db()->query(
            "INSERT INTO vendor_subscriptions
                (vendor_id, package_id, status, provider, stripe_checkout_session_id, started_at)
             VALUES (?, ?, 'incomplete', 'stripe', ?, ?)",
            [$vendorId, $packageId, (string)$session->id, \sql_now()]
        );

        return (string)$session->url;
    }

    /**
     * @param array<string, mixed> $package
     */
    private static function createPaystackCheckout(int $vendorId, array $package, string $successUrl, string $cancelUrl): string
    {
        $method = \table_exists('payment_methods')
            ? \db()->fetch("SELECT * FROM payment_methods WHERE code = 'paystack' LIMIT 1")
            : null;
        if (!$method || (int)($method['is_active'] ?? 0) !== 1 || !PaymentService::isPaystackConfigured($method)) {
            throw new \RuntimeException('Paystack is not configured.');
        }

        $vendor = \db()->fetch(
            "SELECT v.*, u.email, u.display_name
             FROM vendors v
             INNER JOIN users u ON u.id = v.user_id
             WHERE v.id = ?
             LIMIT 1",
            [$vendorId]
        );
        if (!$vendor) {
            throw new \RuntimeException('Vendor was not found.');
        }

        $sourceCurrency = strtoupper((string)$package['currency']);
        $chargeCurrency = 'NGN';
        if ($sourceCurrency !== $chargeCurrency) {
            $rates = \db()->fetchAll(
                'SELECT code FROM currencies WHERE code IN (?, ?) AND is_enabled = 1 AND exchange_rate > 0',
                [$sourceCurrency, $chargeCurrency]
            );
            if (count($rates) !== 2) {
                throw new \RuntimeException('Naira conversion is unavailable. Please choose another payment method or contact support.');
            }
        }
        $chargeAmount = PaymentService::convertAmount((float)$package['price'], $sourceCurrency, $chargeCurrency);
        if (!is_finite($chargeAmount) || $chargeAmount <= 0) {
            throw new \RuntimeException('The naira payment amount could not be calculated.');
        }

        $reference = 'SA-VENDOR-PAYSTACK-' . strtoupper(bin2hex(random_bytes(8)));
        \db()->query(
            "INSERT INTO vendor_subscriptions
                (vendor_id, package_id, status, provider, paystack_reference, started_at, paystack_charge_amount, paystack_charge_currency)
             VALUES (?, ?, 'incomplete', 'paystack', ?, ?, ?, ?)",
            [$vendorId, (int)$package['id'], $reference, \sql_now(), $chargeAmount, $chargeCurrency]
        );

        $response = PaymentService::initializePaystackTransaction(
            $chargeAmount,
            $chargeCurrency,
            (string)$vendor['email'],
            $reference,
            \app_url('paystack_callback.php?context=vendor_subscription'),
            [
                'context' => 'vendor_subscription',
                'plan_amount' => number_format((float)$package['price'], 2, '.', ''),
                'plan_currency' => $sourceCurrency,
                'charge_amount' => number_format($chargeAmount, 2, '.', ''),
                'charge_currency' => $chargeCurrency,
                'vendor_id' => (string)$vendorId,
                'package_id' => (string)$package['id'],
                'cancel_url' => $cancelUrl,
            ],
            $method ?: null
        );

        $url = trim((string)($response['data']['authorization_url'] ?? ''));
        if ($url === '') {
            throw new \RuntimeException('Paystack did not return a checkout page. Please try again.');
        }

        return $url;
    }

    public static function applyPaystackCheckout(string $reference): bool
    {
        $reference = trim($reference);
        if ($reference === '') {
            return false;
        }

        $subscription = \db()->fetch(
            "SELECT s.*, p.price, p.currency
             FROM vendor_subscriptions s
             INNER JOIN vendor_packages p ON p.id = s.package_id
             WHERE s.provider = 'paystack' AND s.paystack_reference = ?
             LIMIT 1",
            [$reference]
        );
        if (!$subscription) {
            return false;
        }

        $method = \table_exists('payment_methods')
            ? \db()->fetch("SELECT * FROM payment_methods WHERE code = 'paystack' LIMIT 1")
            : null;
        $verified = PaymentService::verifyPaystackTransaction($reference, $method ?: null);
        if (!($verified['ok'] ?? false) || !is_array($verified['data'] ?? null)) {
            return false;
        }

        $data = (array)($verified['data']['data'] ?? []);
        $status = strtolower((string)($data['status'] ?? ''));
        $reportedAmount = ((float)($data['amount'] ?? 0)) / 100;
        $reportedCurrency = strtoupper((string)($data['currency'] ?? ''));
        $expectedAmount = (float)($subscription['paystack_charge_amount'] ?? $subscription['price'] ?? 0);
        $expectedCurrency = strtoupper((string)($subscription['paystack_charge_currency'] ?? $subscription['currency'] ?? ''));
        $amountMatches = (int)round($reportedAmount * 100) === (int)round($expectedAmount * 100) && $expectedAmount > 0;
        $currencyMatches = $reportedCurrency === $expectedCurrency;
        $paid = $status === 'success' && $amountMatches && $currencyMatches;

        \db()->query(
            "UPDATE vendor_subscriptions
             SET status = ?, paid_at = CASE WHEN ? = 1 THEN COALESCE(paid_at, ?) ELSE paid_at END, updated_at = ?
             WHERE id = ?",
            [
                $paid ? 'active' : ($status === 'failed' ? 'past_due' : 'incomplete'),
                $paid ? 1 : 0,
                \sql_now(),
                \sql_now(),
                (int)$subscription['id'],
            ]
        );

        \audit('vendor_subscription.paystack_verified', 'vendor_subscriptions', (string)$subscription['id'], [], [
            'reference' => $reference,
            'status' => $status,
            'paid' => $paid,
            'reported_amount' => $reportedAmount,
            'expected_amount' => $expectedAmount,
            'reported_currency' => $reportedCurrency,
            'expected_currency' => $expectedCurrency,
        ]);

        return $paid;
    }

    public static function outstandingPayment(int $vendorId): ?array
    {
        self::ensureSchema();

        $row = \db()->fetch(
            "SELECT s.*, p.name AS package_name, p.slug AS package_slug, p.price, p.currency, p.billing_interval, p.product_limit, p.features,
                    v.store_name, v.store_slug, COALESCE(NULLIF(v.store_email, ''), u.email) AS owner_email,
                    COALESCE(NULLIF(u.display_name, ''), u.username, u.email) AS owner_name
             FROM vendor_subscriptions s
             INNER JOIN vendor_packages p ON p.id = s.package_id
             INNER JOIN vendors v ON v.id = s.vendor_id
             INNER JOIN users u ON u.id = v.user_id
             WHERE s.vendor_id = ?
               AND p.price > 0
               AND s.status IN ('incomplete', 'past_due', 'unpaid')
               AND s.paid_at IS NULL
               AND NOT EXISTS (
                    SELECT 1
                    FROM vendor_subscriptions active_s
                    INNER JOIN vendor_packages active_p ON active_p.id = active_s.package_id
                    WHERE active_s.vendor_id = s.vendor_id
                      AND active_p.price > 0
                      AND active_s.status IN ('active', 'trialing')
               )
             ORDER BY s.updated_at DESC, s.id DESC
             LIMIT 1",
            [$vendorId]
        );

        return $row ?: null;
    }

    public static function createOutstandingPaymentLink(int $subscriptionId, ?string $provider = null): string
    {
        self::ensureSchema();

        $subscription = \db()->fetch(
            "SELECT s.*, p.price
             FROM vendor_subscriptions s
             INNER JOIN vendor_packages p ON p.id = s.package_id
             WHERE s.id = ? AND p.price > 0 AND s.status IN ('incomplete', 'past_due', 'unpaid')
             LIMIT 1",
            [$subscriptionId]
        );
        if (!$subscription) {
            throw new \RuntimeException('Outstanding vendor plan payment was not found.');
        }

        $provider = $provider !== null && in_array($provider, ['stripe', 'paystack'], true)
            ? $provider
            : (string)($subscription['provider'] ?? 'stripe');
        if ($provider === 'paystack') {
            $method = \table_exists('payment_methods')
                ? \db()->fetch("SELECT * FROM payment_methods WHERE code = 'paystack' LIMIT 1")
                : null;
            if (!$method || (int)($method['is_active'] ?? 0) !== 1 || !PaymentService::isPaystackConfigured($method)) {
                $provider = 'stripe';
            }
        }

        return self::createCheckoutSession(
            (int)$subscription['vendor_id'],
            (int)$subscription['package_id'],
            \app_url('vendor/plans?subscribed=1'),
            \app_url('vendor/plans?cancelled=1'),
            $provider
        );
    }

    public static function processOutstandingPaymentReminders(array $options = []): array
    {
        self::ensureSchema();
        NotificationService::ensureSchema();

        $limit = max(1, min(1000, (int)($options['limit'] ?? 500)));
        $subject = 'Complete your Seller Africa vendor plan payment';
        $rows = [];

        try {
            $rows = \db()->fetchAll(
                "SELECT s.*, p.name AS package_name, p.price, p.currency, p.billing_interval,
                        v.store_name, v.store_slug, COALESCE(NULLIF(v.store_email, ''), u.email) AS owner_email,
                        COALESCE(NULLIF(u.display_name, ''), u.username, u.email) AS owner_name
                 FROM vendor_subscriptions s
                 INNER JOIN vendor_packages p ON p.id = s.package_id
                 INNER JOIN vendors v ON v.id = s.vendor_id
                 INNER JOIN users u ON u.id = v.user_id
                 WHERE p.price > 0
                   AND s.status IN ('incomplete', 'past_due', 'unpaid')
                   AND s.paid_at IS NULL
                   AND COALESCE(NULLIF(v.store_email, ''), u.email) IS NOT NULL
                   AND COALESCE(NULLIF(v.store_email, ''), u.email) <> ''
                   AND NOT EXISTS (
                        SELECT 1
                        FROM vendor_subscriptions active_s
                        INNER JOIN vendor_packages active_p ON active_p.id = active_s.package_id
                        WHERE active_s.vendor_id = s.vendor_id
                          AND active_p.price > 0
                          AND active_s.status IN ('active', 'trialing')
                   )
                   AND NOT EXISTS (
                        SELECT 1
                        FROM notifications n
                        WHERE n.channel = 'email'
                          AND n.recipient_email = COALESCE(NULLIF(v.store_email, ''), u.email)
                          AND n.title = ?
                          AND n.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                   )
                 ORDER BY s.started_at ASC, s.id ASC
                 LIMIT {$limit}",
                [$subject]
            );
        } catch (\Throwable $e) {
            \error_log('Outstanding vendor payment lookup failed: ' . $e->getMessage());
            return ['queued' => 0, 'skipped' => 0, 'error' => $e->getMessage()];
        }

        $queued = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $email = trim((string)($row['owner_email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            try {
                $paymentUrl = self::createOutstandingPaymentLink((int)$row['id'], (string)($row['provider'] ?? 'stripe'));
            } catch (\Throwable $e) {
                \error_log('Outstanding vendor payment link failed for subscription #' . (int)$row['id'] . ': ' . $e->getMessage());
                $skipped++;
                continue;
            }

            $name = trim((string)($row['owner_name'] ?? 'there')) ?: 'there';
            $store = trim((string)($row['store_name'] ?? 'your store')) ?: 'your store';
            $amount = strtoupper((string)($row['currency'] ?? 'USD')) . ' ' . number_format((float)($row['price'] ?? 0), 2);
            $interval = (string)($row['billing_interval'] ?? 'month');
            $body = '<p>Hello ' . \e($name) . ',</p>'
                . '<p>Your Seller Africa vendor application for <strong>' . \e($store) . '</strong> was received and remains pending while your selected plan payment is outstanding.</p>'
                . '<p><strong>Plan:</strong> ' . \e((string)($row['package_name'] ?? 'Vendor plan')) . '<br>'
                . '<strong>Amount:</strong> ' . \e($amount . ' / ' . $interval) . '</p>'
                . '<p>Please complete the payment to keep your application moving through review. Your registration is saved even if you did not complete checkout immediately.</p>';

            NotificationService::enqueueEmail(
                [['email' => $email, 'name' => $name]],
                $subject,
                'Your vendor plan payment is pending',
                $body,
                [
                    'button_text' => 'Pay Vendor Plan',
                    'button_url' => $paymentUrl,
                    'metadata' => [
                        'type' => 'vendor_plan_payment_reminder',
                        'vendor_id' => (int)($row['vendor_id'] ?? 0),
                        'subscription_id' => (int)($row['id'] ?? 0),
                        'package_id' => (int)($row['package_id'] ?? 0),
                    ],
                ]
            );

            if (function_exists('audit')) {
                \audit('vendor_subscription.payment_reminder_queued', 'vendor_subscriptions', (string)$row['id'], [], [
                    'vendor_id' => (int)($row['vendor_id'] ?? 0),
                    'email' => $email,
                    'package' => (string)($row['package_name'] ?? ''),
                ]);
            }
            $queued++;
        }

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    public static function assignFreePackage(int $vendorId, int $packageId): void
    {
        \db()->query(
            "UPDATE vendor_subscriptions
             SET status = 'cancelled', updated_at = ?
             WHERE vendor_id = ? AND status IN ('active', 'trialing', 'past_due', 'unpaid', 'incomplete')",
            [\sql_now(), $vendorId]
        );
        \db()->query(
            "INSERT INTO vendor_subscriptions (vendor_id, package_id, status, provider, started_at, paid_at)
             VALUES (?, ?, 'active', 'free', ?, ?)",
            [$vendorId, $packageId, \sql_now(), \sql_now()]
        );
    }

    public static function handleStripeWebhook(string $payload, string $signature, ?object $verifiedEvent = null): void
    {
        $secret = self::stripeWebhookSecret();
        $event = $verifiedEvent ?? ($secret !== ''
            ? Webhook::constructEvent($payload, $signature, $secret)
            : json_decode($payload, false, 512, JSON_THROW_ON_ERROR));

        $type = (string)$event->type;
        $object = $event->data->object;

        if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            self::applyCheckoutSession($object);
            return;
        }

        if (str_starts_with($type, 'customer.subscription.')) {
            self::applyStripeSubscription($object);
            return;
        }

        if ($type === 'invoice.payment_failed') {
            $subscriptionId = (string)($object->subscription ?? '');
            if ($subscriptionId !== '') {
                self::markPaymentFailed($subscriptionId);
            }
        }
    }

    private static function applyCheckoutSession(object $session): void
    {
        if (($session->payment_status ?? '') !== 'paid') {
            return;
        }
        $vendorId = (int)($session->metadata->vendor_id ?? $session->client_reference_id ?? 0);
        $packageId = (int)($session->metadata->package_id ?? 0);
        if ($vendorId <= 0 || $packageId <= 0) {
            return;
        }

        \db()->query(
            "UPDATE vendor_subscriptions
             SET status = 'active', stripe_customer_id = ?, stripe_subscription_id = ?, paid_at = ?, updated_at = ?
             WHERE stripe_checkout_session_id = ?",
            [
                (string)($session->customer ?? ''),
                (string)($session->subscription ?? ''),
                \sql_now(),
                \sql_now(),
                (string)$session->id,
            ]
        );
    }

    private static function applyStripeSubscription(object $subscription): void
    {
        $vendorId = (int)($subscription->metadata->vendor_id ?? 0);
        $packageId = (int)($subscription->metadata->package_id ?? 0);
        $subscriptionId = (string)($subscription->id ?? '');
        if ($subscriptionId === '') {
            return;
        }

        $status = self::normalizeStatus((string)($subscription->status ?? 'incomplete'));
        $periodStart = self::stripeTimestamp($subscription->current_period_start ?? null);
        $periodEnd = self::stripeTimestamp($subscription->current_period_end ?? null);
        $graceEnds = in_array($status, ['past_due', 'unpaid'], true)
            ? self::datePlusDays($periodEnd ?: \sql_now(), self::graceDays())
            : null;

        $existing = \db()->fetch('SELECT * FROM vendor_subscriptions WHERE stripe_subscription_id = ? LIMIT 1', [$subscriptionId]);
        if ($existing) {
            \db()->query(
                "UPDATE vendor_subscriptions
                 SET status = ?, current_period_start = ?, current_period_end = ?, grace_ends_at = ?,
                     cancel_at_period_end = ?, updated_at = ?, paid_at = CASE WHEN ? IN ('active','trialing') THEN COALESCE(paid_at, ?) ELSE paid_at END
                 WHERE stripe_subscription_id = ?",
                [$status, $periodStart, $periodEnd, $graceEnds, !empty($subscription->cancel_at_period_end) ? 1 : 0, \sql_now(), $status, \sql_now(), $subscriptionId]
            );
            return;
        }

        if ($vendorId > 0 && $packageId > 0) {
            \db()->query(
                "INSERT INTO vendor_subscriptions
                    (vendor_id, package_id, status, provider, stripe_customer_id, stripe_subscription_id, current_period_start, current_period_end, grace_ends_at, cancel_at_period_end, started_at, paid_at)
                 VALUES (?, ?, ?, 'stripe', ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $vendorId,
                    $packageId,
                    $status,
                    (string)($subscription->customer ?? ''),
                    $subscriptionId,
                    $periodStart,
                    $periodEnd,
                    $graceEnds,
                    !empty($subscription->cancel_at_period_end) ? 1 : 0,
                    \sql_now(),
                    in_array($status, ['active', 'trialing'], true) ? \sql_now() : null,
                ]
            );
        }
    }

    private static function markPaymentFailed(string $subscriptionId): void
    {
        $graceEnds = self::datePlusDays(\sql_now(), self::graceDays());
        \db()->query(
            "UPDATE vendor_subscriptions
             SET status = 'past_due', last_payment_failed_at = ?, grace_ends_at = ?, updated_at = ?
             WHERE stripe_subscription_id = ?",
            [\sql_now(), $graceEnds, \sql_now(), $subscriptionId]
        );
    }

    private static function isSubscriptionUsable(array $subscription): bool
    {
        $status = (string)($subscription['status'] ?? '');
        if (in_array($status, ['active', 'trialing'], true)) {
            return true;
        }
        if (in_array($status, ['past_due', 'unpaid'], true)) {
            $grace = trim((string)($subscription['grace_ends_at'] ?? ''));
            return $grace !== '' && strtotime($grace) >= time();
        }
        return false;
    }

    private static function normalizeStatus(string $status): string
    {
        return in_array($status, ['active', 'trialing', 'past_due', 'unpaid', 'cancelled', 'incomplete'], true)
            ? $status
            : ($status === 'canceled' ? 'cancelled' : 'expired');
    }

    private static function stripeTimestamp(mixed $timestamp): ?string
    {
        $value = (int)$timestamp;
        return $value > 0 ? date('Y-m-d H:i:s', $value) : null;
    }

    private static function datePlusDays(string $date, int $days): string
    {
        return (new \DateTimeImmutable($date))->modify('+' . max(0, $days) . ' days')->format('Y-m-d H:i:s');
    }

    private static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $name), '-')) ?: 'package';
        $slug = $base;
        $counter = 2;
        while (true) {
            $params = [$slug];
            $sql = 'SELECT id FROM vendor_packages WHERE slug = ?';
            if ($ignoreId) {
                $sql .= ' AND id <> ?';
                $params[] = $ignoreId;
            }
            if (!\db()->fetch($sql . ' LIMIT 1', $params)) {
                return $slug;
            }
            $slug = $base . '-' . $counter++;
        }
    }

    private static function stripeSecretKey(): string
    {
        $settings = require APP_ROOT . '/app/config/payment_gateways.php';
        return trim((string)($settings['stripe']['secret_key'] ?? $_ENV['STRIPE_SECRET_KEY'] ?? ''));
    }

    private static function stripeWebhookSecret(): string
    {
        $settings = require APP_ROOT . '/app/config/payment_gateways.php';
        return trim((string)($settings['stripe']['webhook_secret'] ?? $_ENV['STRIPE_WEBHOOK_SECRET'] ?? ''));
    }

    private static function columnExists(string $table, string $column): bool
    {
        try {
            $config = \db_config();
            $row = \db()->fetch(
                "SELECT COUNT(*) AS total
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [(string)($config['database'] ?? ''), $table, $column]
            );

            return (int)($row['total'] ?? 0) > 0;
        } catch (\Throwable) {
            return true;
        }
    }
}
