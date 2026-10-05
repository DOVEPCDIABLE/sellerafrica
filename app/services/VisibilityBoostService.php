<?php

declare(strict_types=1);

namespace App;

use Stripe\Checkout\Session;
use Stripe\Stripe;

final class VisibilityBoostService
{
    public const AMOUNT = 5.00;
    public const CURRENCY = 'USD';
    public const INTERVAL = 'month';

    public static function ensureSchema(): void
    {
        \db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS vendor_visibility_boosts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                vendor_id BIGINT UNSIGNED NOT NULL,
                status ENUM('active', 'trialing', 'past_due', 'unpaid', 'cancelled', 'incomplete', 'expired', 'failed', 'review') NOT NULL DEFAULT 'incomplete',
                provider VARCHAR(40) NOT NULL DEFAULT 'stripe',
                amount DECIMAL(12, 2) NOT NULL DEFAULT 5.00,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                stripe_customer_id VARCHAR(190) NULL,
                stripe_subscription_id VARCHAR(190) NULL UNIQUE,
                stripe_checkout_session_id VARCHAR(190) NULL UNIQUE,
                paystack_reference VARCHAR(190) NULL UNIQUE,
                current_period_start DATETIME NULL,
                current_period_end DATETIME NULL,
                cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
                raw_response JSON NULL,
                started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                paid_at DATETIME NULL,
                last_payment_failed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_vendor_visibility_boosts_vendor_status (vendor_id, status),
                CONSTRAINT fk_vendor_visibility_boosts_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        self::extendStatusEnum();
        self::addColumnIfMissing('vendor_visibility_boosts', 'paystack_reference', 'VARCHAR(190) NULL UNIQUE AFTER stripe_checkout_session_id');
        self::addColumnIfMissing('vendor_visibility_boosts', 'raw_response', 'JSON NULL AFTER cancel_at_period_end');
    }

    public static function activeBoost(int $vendorId): ?array
    {
        self::ensureSchema();
        $row = \db()->fetch(
            "SELECT *
             FROM vendor_visibility_boosts
             WHERE vendor_id = ?
             ORDER BY FIELD(status, 'active', 'trialing', 'past_due', 'unpaid', 'incomplete', 'cancelled', 'expired'), updated_at DESC, id DESC
             LIMIT 1",
            [$vendorId]
        );

        if (!$row) {
            return null;
        }

        return in_array((string)$row['status'], ['active', 'trialing', 'past_due', 'unpaid', 'incomplete'], true) ? $row : null;
    }

    public static function createCheckoutSession(int $vendorId, string $successUrl, string $cancelUrl, string $provider = 'stripe'): string
    {
        self::ensureSchema();

        $provider = in_array($provider, ['stripe', 'paystack'], true) ? $provider : 'stripe';
        if ($provider === 'paystack') {
            return self::createPaystackCheckout($vendorId);
        }

        $secret = trim((string)(PaymentService::stripeSettings()['secret_key'] ?? ''));
        if ($secret === '') {
            throw new \RuntimeException('Stripe is not configured yet. Please contact support.');
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
            throw new \RuntimeException('Vendor account was not found.');
        }

        Stripe::setApiKey($secret);
        $metadata = [
            'context' => 'vendor_visibility_boost',
            'vendor_id' => (string)$vendorId,
            'amount' => number_format(self::AMOUNT, 2, '.', ''),
        ];

        $session = Session::create([
            'mode' => 'subscription',
            'customer_email' => (string)$vendor['email'],
            'client_reference_id' => (string)$vendorId,
            'success_url' => $successUrl . (str_contains($successUrl, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower(self::CURRENCY),
                    'unit_amount' => 500,
                    'recurring' => ['interval' => self::INTERVAL],
                    'product_data' => [
                        'name' => 'Seller Africa Homepage Visibility Boost',
                        'description' => 'Homepage ranking and increased product visibility for vendor listings.',
                    ],
                ],
            ]],
            'metadata' => $metadata,
            'subscription_data' => ['metadata' => $metadata],
        ]);

        \db()->query(
            "INSERT INTO vendor_visibility_boosts
                (vendor_id, status, provider, amount, currency, stripe_checkout_session_id, started_at)
             VALUES (?, 'incomplete', 'stripe', ?, ?, ?, ?)",
            [$vendorId, self::AMOUNT, self::CURRENCY, (string)$session->id, \sql_now()]
        );

        $url = trim((string)($session->url ?? ''));
        if ($url === '') {
            throw new \RuntimeException('Stripe did not return a checkout URL. Please try again.');
        }

        return $url;
    }

    private static function createPaystackCheckout(int $vendorId): string
    {
        PaymentService::ensurePaystackMethod();

        $method = \db()->fetch("SELECT * FROM payment_methods WHERE code = 'paystack' LIMIT 1");
        if (!$method || (int)($method['is_active'] ?? 0) !== 1 || !PaymentService::isPaystackConfigured($method)) {
            throw new \RuntimeException('Paystack is not configured yet. Please use Stripe.');
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
            throw new \RuntimeException('Vendor account was not found.');
        }

        $settings = PaymentService::paystackSettings($method);
        $chargeCurrency = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($settings['charge_currency'] ?? 'NGN')) ?: 'NGN', 0, 3));
        $chargeAmount = PaymentService::convertAmount(self::AMOUNT, self::CURRENCY, $chargeCurrency);
        $reference = self::uniquePaystackReference();
        $raw = [
            'paystack_checkout' => [
                'reference' => $reference,
                'context' => 'vendor_visibility_boost',
                'vendor_id' => $vendorId,
                'order_amount' => self::AMOUNT,
                'order_currency' => self::CURRENCY,
                'charge_amount' => $chargeAmount,
                'charge_currency' => $chargeCurrency,
                'created_at' => \sql_now(),
            ],
        ];

        \db()->query(
            "INSERT INTO vendor_visibility_boosts
                (vendor_id, status, provider, amount, currency, paystack_reference, raw_response, started_at)
             VALUES (?, 'incomplete', 'paystack', ?, ?, ?, ?, ?)",
            [
                $vendorId,
                self::AMOUNT,
                self::CURRENCY,
                $reference,
                json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                \sql_now(),
            ]
        );

        $response = PaymentService::initializePaystackTransaction(
            $chargeAmount,
            $chargeCurrency,
            (string)$vendor['email'],
            $reference,
            \app_url('paystack_callback.php?context=visibility_boost'),
            [
                'context' => 'vendor_visibility_boost',
                'vendor_id' => (string)$vendorId,
                'order_amount' => number_format(self::AMOUNT, 2, '.', ''),
                'order_currency' => self::CURRENCY,
                'charge_amount' => number_format($chargeAmount, 2, '.', ''),
                'charge_currency' => $chargeCurrency,
            ],
            $method
        );

        $url = trim((string)($response['data']['authorization_url'] ?? ''));
        if ($url === '') {
            throw new \RuntimeException('Paystack did not return a checkout URL. Please try again.');
        }

        $raw['paystack_checkout']['authorization_url'] = $url;
        $raw['paystack_checkout']['access_code'] = (string)($response['data']['access_code'] ?? '');
        \db()->query(
            'UPDATE vendor_visibility_boosts SET raw_response = ?, updated_at = ? WHERE paystack_reference = ?',
            [json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), \sql_now(), $reference]
        );

        return $url;
    }

    public static function applyPaystackStatus(string $reference, array $data, string $source = 'callback'): bool
    {
        self::ensureSchema();
        $boost = \db()->fetch('SELECT * FROM vendor_visibility_boosts WHERE paystack_reference = ? LIMIT 1', [trim($reference)]);
        if (!$boost) {
            return false;
        }

        $transaction = is_array($data['data'] ?? null) ? $data['data'] : $data;
        $status = strtolower((string)($transaction['status'] ?? $data['event'] ?? ''));
        $providerSuccessful = in_array($status, ['success', 'successful', 'paid', 'charge.success'], true);
        $providerFailed = in_array($status, ['failed', 'failure', 'declined', 'abandoned', 'reversed'], true);
        $raw = json_decode((string)($boost['raw_response'] ?? ''), true);
        $raw = is_array($raw) ? $raw : [];

        $reportedCurrency = strtoupper((string)($transaction['currency'] ?? ''));
        $reportedAmountMinor = (int)($transaction['amount'] ?? 0);
        $expectedCurrency = strtoupper((string)($raw['paystack_checkout']['charge_currency'] ?? $boost['currency'] ?? self::CURRENCY));
        $expectedAmount = (float)($raw['paystack_checkout']['charge_amount'] ?? PaymentService::convertAmount((float)$boost['amount'], (string)$boost['currency'], $expectedCurrency));
        $expectedAmountMinor = PaymentService::paystackMinorAmount($expectedAmount, $expectedCurrency);
        $amountMatches = $reportedAmountMinor > 0 && $reportedAmountMinor === $expectedAmountMinor;
        $currencyMatches = $reportedCurrency === '' || $reportedCurrency === $expectedCurrency;

        $newStatus = 'incomplete';
        if ($providerSuccessful && $amountMatches && $currencyMatches) {
            $newStatus = 'active';
        } elseif ($providerSuccessful) {
            $newStatus = 'review';
        } elseif ($providerFailed) {
            $newStatus = 'failed';
        }

        $raw['paystack_' . $source] = $data;
        $raw['paystack_' . $source . '_at'] = \sql_now();
        $raw['paystack_verification'] = [
            'reported_amount_minor' => $reportedAmountMinor,
            'expected_amount_minor' => $expectedAmountMinor,
            'reported_currency' => $reportedCurrency,
            'expected_currency' => $expectedCurrency,
            'amount_matches' => $amountMatches,
            'currency_matches' => $currencyMatches,
            'source' => $source,
        ];

        \db()->query(
            'UPDATE vendor_visibility_boosts SET status = ?, raw_response = ?, paid_at = CASE WHEN ? = "active" AND paid_at IS NULL THEN ? ELSE paid_at END, updated_at = ? WHERE id = ?',
            [
                $newStatus,
                json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $newStatus,
                \sql_now(),
                \sql_now(),
                (int)$boost['id'],
            ]
        );

        return $newStatus === 'active';
    }

    public static function handleStripeWebhook(string $payload, string $signature, ?object $verifiedEvent = null): bool
    {
        self::ensureSchema();

        $settings = PaymentService::stripeSettings();
        $secret = trim((string)($settings['webhook_secret'] ?? ''));
        $event = $verifiedEvent ?? ($secret !== ''
            ? \Stripe\Webhook::constructEvent($payload, $signature, $secret)
            : json_decode($payload, false, 512, JSON_THROW_ON_ERROR));

        $type = (string)$event->type;
        $object = $event->data->object;
        $context = (string)($object->metadata->context ?? '');
        if ($context !== 'vendor_visibility_boost') {
            if ($type === 'invoice.payment_failed') {
                $subscriptionId = (string)($object->subscription ?? '');
                if ($subscriptionId !== '' && self::markSubscriptionPastDue($subscriptionId)) {
                    return true;
                }
            }

            return false;
        }

        if ($type === 'checkout.session.completed') {
            self::applyCheckoutSession($object);
            return true;
        }

        if (str_starts_with($type, 'customer.subscription.')) {
            self::applyStripeSubscription($object);
            return true;
        }

        if ($type === 'invoice.payment_failed') {
            $subscriptionId = (string)($object->subscription ?? '');
            if ($subscriptionId !== '' && self::markSubscriptionPastDue($subscriptionId)) {
                return true;
            }
        }

        return true;
    }

    private static function applyCheckoutSession(object $session): void
    {
        $vendorId = (int)($session->metadata->vendor_id ?? $session->client_reference_id ?? 0);
        if ($vendorId <= 0) {
            return;
        }

        \db()->query(
            "UPDATE vendor_visibility_boosts
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
        $subscriptionId = (string)($subscription->id ?? '');
        if ($subscriptionId === '') {
            return;
        }

        $status = self::normalizeStatus((string)($subscription->status ?? 'incomplete'));
        \db()->query(
            "UPDATE vendor_visibility_boosts
             SET status = ?, stripe_customer_id = ?, current_period_start = ?, current_period_end = ?,
                 cancel_at_period_end = ?, updated_at = ?, paid_at = CASE WHEN ? IN ('active','trialing') THEN COALESCE(paid_at, ?) ELSE paid_at END
             WHERE stripe_subscription_id = ?",
            [
                $status,
                (string)($subscription->customer ?? ''),
                self::stripeTimestamp($subscription->current_period_start ?? null),
                self::stripeTimestamp($subscription->current_period_end ?? null),
                !empty($subscription->cancel_at_period_end) ? 1 : 0,
                \sql_now(),
                $status,
                \sql_now(),
                $subscriptionId,
            ]
        );
    }

    private static function markSubscriptionPastDue(string $subscriptionId): bool
    {
        \db()->query(
            'UPDATE vendor_visibility_boosts SET status = "past_due", last_payment_failed_at = ?, updated_at = ? WHERE stripe_subscription_id = ?',
            [\sql_now(), \sql_now(), $subscriptionId]
        );

        return (bool)\db()->fetch(
            'SELECT id FROM vendor_visibility_boosts WHERE stripe_subscription_id = ? LIMIT 1',
            [$subscriptionId]
        );
    }

    private static function normalizeStatus(string $status): string
    {
        return in_array($status, ['active', 'trialing', 'past_due', 'unpaid', 'cancelled', 'incomplete', 'failed', 'review'], true)
            ? $status
            : ($status === 'canceled' ? 'cancelled' : 'expired');
    }

    private static function stripeTimestamp(mixed $timestamp): ?string
    {
        $value = (int)$timestamp;
        return $value > 0 ? date('Y-m-d H:i:s', $value) : null;
    }

    private static function uniquePaystackReference(): string
    {
        do {
            $reference = 'SA-VISIBILITY-PAYSTACK-' . strtoupper(bin2hex(random_bytes(8)));
        } while (\db()->fetch('SELECT id FROM vendor_visibility_boosts WHERE paystack_reference = ? LIMIT 1', [$reference]));

        return $reference;
    }

    private static function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        if (!self::columnExists($table, $column)) {
            \db()->pdo()->exec("ALTER TABLE {$table} ADD {$column} {$definition}");
        }
    }

    private static function columnExists(string $table, string $column): bool
    {
        $config = \db_config();
        $row = \db()->fetch(
            'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [(string)($config['database'] ?? ''), $table, $column]
        );

        return (int)($row['total'] ?? 0) > 0;
    }

    private static function extendStatusEnum(): void
    {
        try {
            \db()->pdo()->exec(
                "ALTER TABLE vendor_visibility_boosts
                 MODIFY status ENUM('active', 'trialing', 'past_due', 'unpaid', 'cancelled', 'incomplete', 'expired', 'failed', 'review') NOT NULL DEFAULT 'incomplete'"
            );
        } catch (\Throwable $e) {
            error_log('Visibility boost status enum compatibility update failed: ' . $e->getMessage());
        }
    }
}
