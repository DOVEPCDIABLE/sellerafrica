<?php

declare(strict_types=1);

namespace App;

use Stripe\Checkout\Session;
use Stripe\Stripe;

final class ManageStoreService
{
    public const CURRENCY = 'USD';
    public const MONTHLY_AMOUNT = 1.00;
    public const ANNUAL_AMOUNT = 12.00;
    public const SETUP_AMOUNT = 5.00;

    public static function ensureSchema(): void
    {
        \db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS vendor_store_management_services (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                vendor_id BIGINT UNSIGNED NULL,
                user_id BIGINT UNSIGNED NULL,
                customer_name VARCHAR(190) NULL,
                customer_email VARCHAR(190) NOT NULL,
                plan ENUM('monthly', 'annual', 'setup') NOT NULL DEFAULT 'monthly',
                status ENUM('active', 'trialing', 'paid', 'past_due', 'unpaid', 'cancelled', 'incomplete', 'expired', 'failed', 'pending', 'review') NOT NULL DEFAULT 'incomplete',
                provider VARCHAR(40) NOT NULL DEFAULT 'stripe',
                amount DECIMAL(12, 2) NOT NULL DEFAULT 1.00,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                stripe_customer_id VARCHAR(190) NULL,
                stripe_subscription_id VARCHAR(190) NULL UNIQUE,
                stripe_checkout_session_id VARCHAR(190) NULL UNIQUE,
                klasha_reference VARCHAR(190) NULL UNIQUE,
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
                INDEX idx_vendor_store_management_email_status (customer_email, status),
                INDEX idx_vendor_store_management_vendor_status (vendor_id, status),
                CONSTRAINT fk_vendor_store_management_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        self::addColumnIfMissing('vendor_store_management_services', 'user_id', 'BIGINT UNSIGNED NULL AFTER vendor_id');
        self::addColumnIfMissing('vendor_store_management_services', 'customer_name', 'VARCHAR(190) NULL AFTER user_id');
        self::addColumnIfMissing('vendor_store_management_services', 'customer_email', 'VARCHAR(190) NULL AFTER customer_name');
        self::addColumnIfMissing('vendor_store_management_services', 'paystack_reference', 'VARCHAR(190) NULL UNIQUE AFTER klasha_reference');
        self::addColumnIfMissing('vendor_store_management_services', 'raw_response', 'JSON NULL AFTER cancel_at_period_end');
        $planColumn = \db()->fetch("SHOW COLUMNS FROM vendor_store_management_services LIKE 'plan'");
        if (!str_contains((string)($planColumn['Type'] ?? ''), "'setup'")) {
            \db()->pdo()->exec("ALTER TABLE vendor_store_management_services MODIFY plan ENUM('monthly', 'annual', 'setup') NOT NULL DEFAULT 'monthly'");
        }

        try {
            \db()->pdo()->exec('ALTER TABLE vendor_store_management_services DROP FOREIGN KEY fk_vendor_store_management_vendor');
        } catch (\Throwable $e) {
            // Older or freshly-created installs may not have this exact key.
        }
        try {
            \db()->pdo()->exec('ALTER TABLE vendor_store_management_services MODIFY vendor_id BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {
            error_log('Manage My Store vendor_id compatibility update failed: ' . $e->getMessage());
        }
        try {
            \db()->pdo()->exec('ALTER TABLE vendor_store_management_services ADD CONSTRAINT fk_vendor_store_management_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL');
        } catch (\Throwable $e) {
            // Constraint already exists or the host does not permit this alter.
        }
    }

    public static function activeServiceForEmail(string $email, string $plan = 'annual'): ?array
    {
        self::ensureSchema();
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $row = \db()->fetch(
            "SELECT *
             FROM vendor_store_management_services
             WHERE customer_email = ? AND (CASE WHEN ? = 'setup' THEN plan = 'setup' ELSE plan <> 'setup' END)
             ORDER BY FIELD(status, 'active', 'trialing', 'paid', 'past_due', 'unpaid', 'pending', 'incomplete', 'review', 'cancelled', 'expired', 'failed'), updated_at DESC, id DESC
             LIMIT 1",
            [$email, self::normalizePlan($plan)]
        );

        return $row && in_array((string)$row['status'], ['active', 'trialing', 'paid', 'past_due', 'unpaid', 'pending', 'incomplete', 'review'], true) ? $row : null;
    }

    public static function createStripeCheckoutSession(string $name, string $email, string $plan, string $successUrl, string $cancelUrl): string
    {
        self::ensureSchema();
        $name = self::cleanName($name);
        $email = self::cleanEmail($email);
        $plan = self::normalizePlan($plan);
        $amount = self::amountForPlan($plan);
        $match = self::matchEmail($email);

        $secret = trim((string)(PaymentService::stripeSettings()['secret_key'] ?? ''));
        if ($secret === '') {
            throw new \RuntimeException('Stripe is not configured yet. Please contact support.');
        }

        Stripe::setApiKey($secret);
        $metadata = [
            'context' => 'vendor_store_management',
            'customer_email' => $email,
            'customer_name' => $name,
            'plan' => $plan,
            'vendor_id' => (string)($match['vendor_id'] ?? ''),
            'user_id' => (string)($match['user_id'] ?? ''),
            'amount' => number_format($amount, 2, '.', ''),
        ];

        $priceData = [
            'currency' => strtolower(self::CURRENCY),
            'unit_amount' => (int)round($amount * 100),
            'product_data' => [
                'name' => $plan === 'setup' ? 'Seller Africa Set Up My Store' : 'Seller Africa Manage My Store - Annual',
                'description' => $plan === 'setup' ? 'One-time store setup service. No recurring charge.' : 'Store management support for one full year.',
            ],
        ];
        if ($plan === 'monthly') {
            $priceData['recurring'] = ['interval' => 'month'];
        }

        $sessionPayload = [
            'mode' => $plan === 'monthly' ? 'subscription' : 'payment',
            'customer_email' => $email,
            'client_reference_id' => $email,
            'success_url' => $successUrl . (str_contains($successUrl, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => $priceData,
            ]],
            'metadata' => $metadata,
        ];
        if ($plan === 'monthly') {
            $sessionPayload['subscription_data'] = ['metadata' => $metadata];
        }

        $session = Session::create($sessionPayload);
        \db()->query(
            "INSERT INTO vendor_store_management_services
                (vendor_id, user_id, customer_name, customer_email, plan, status, provider, amount, currency, stripe_checkout_session_id, started_at)
             VALUES (?, ?, ?, ?, ?, 'incomplete', 'stripe', ?, ?, ?, ?)",
            [
                $match['vendor_id'],
                $match['user_id'],
                $name,
                $email,
                $plan,
                $amount,
                self::CURRENCY,
                (string)$session->id,
                \sql_now(),
            ]
        );

        $url = trim((string)($session->url ?? ''));
        if ($url === '') {
            throw new \RuntimeException('Stripe did not return a checkout URL. Please try again.');
        }

        return $url;
    }

    public static function createKlashaAnnualConfig(string $name, string $email, string $phone = '', string $plan = 'annual'): array
    {
        $plan = self::normalizePlan($plan);
        $amount = self::amountForPlan($plan);
        self::ensureSchema();
        PaymentService::ensureKlashaMethod();

        $name = self::cleanName($name);
        $email = self::cleanEmail($email);
        $phone = trim($phone);
        $match = self::matchEmail($email);
        $method = \db()->fetch("SELECT * FROM payment_methods WHERE code = 'klasha' LIMIT 1");
        $settings = PaymentService::settings($method);
        if (!PaymentService::isKlashaConfigured($method)) {
            throw new \RuntimeException('Klasha is not configured yet. Please use Stripe or contact support.');
        }

        $reference = self::uniqueKlashaReference();
        $destinationCurrency = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($settings['destination_currency'] ?? 'NGN')) ?: 'NGN', 0, 3));
        [$firstName, $lastName] = self::splitName($name);
        $raw = [
            'klasha_created' => [
                'reference' => $reference,
                'context' => 'vendor_store_management',
                'customer_email' => $email,
                'plan' => $plan,
                'created_at' => \sql_now(),
            ],
        ];

        \db()->query(
            "INSERT INTO vendor_store_management_services
                (vendor_id, user_id, customer_name, customer_email, plan, status, provider, amount, currency, klasha_reference, raw_response, started_at)
             VALUES (?, ?, ?, ?, ?, 'pending', 'klasha', ?, ?, ?, ?, ?)",
            [
                $match['vendor_id'],
                $match['user_id'],
                $name,
                $email,
                $plan,
                $amount,
                self::CURRENCY,
                $reference,
                json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                \sql_now(),
            ]
        );

        return [
            'reference' => $reference,
            'merchantKey' => (string)$settings['public_key'],
            'businessId' => (string)$settings['business_id'],
            'environment' => (string)($settings['mode'] ?? 'test') !== 'live',
            'amount' => $amount,
            'sourceAmount' => (string)$amount,
            'currency' => self::CURRENCY,
            'destinationCurrency' => $destinationCurrency,
            'description' => $plan === 'setup' ? 'Seller Africa Set Up My Store - One-time' : 'Seller Africa Manage My Store Annual Plan',
            'fullname' => $name,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'phone' => $phone,
        ];
    }

    public static function createPaystackAnnualCheckout(string $name, string $email, string $phone = '', string $plan = 'annual'): string
    {
        $plan = self::normalizePlan($plan);
        $amount = self::amountForPlan($plan);
        self::ensureSchema();
        PaymentService::ensurePaystackMethod();

        $name = self::cleanName($name);
        $email = self::cleanEmail($email);
        $phone = trim($phone);
        $match = self::matchEmail($email);
        $method = \db()->fetch("SELECT * FROM payment_methods WHERE code = 'paystack' LIMIT 1");
        if (!$method || (int)($method['is_active'] ?? 0) !== 1 || !PaymentService::isPaystackConfigured($method)) {
            throw new \RuntimeException('Paystack is not configured yet. Please use Stripe or Klasha.');
        }

        $settings = PaymentService::paystackSettings($method);
        $chargeCurrency = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($settings['charge_currency'] ?? 'NGN')) ?: 'NGN', 0, 3));
        $chargeAmount = PaymentService::convertAmount($amount, self::CURRENCY, $chargeCurrency);
        $reference = self::uniquePaystackReference();
        $raw = [
            'paystack_checkout' => [
                'reference' => $reference,
                'context' => 'vendor_store_management',
                'customer_email' => $email,
                'plan' => $plan,
                'order_amount' => $amount,
                'order_currency' => self::CURRENCY,
                'charge_amount' => $chargeAmount,
                'charge_currency' => $chargeCurrency,
                'created_at' => \sql_now(),
            ],
        ];

        \db()->query(
            "INSERT INTO vendor_store_management_services
                (vendor_id, user_id, customer_name, customer_email, plan, status, provider, amount, currency, paystack_reference, raw_response, started_at)
             VALUES (?, ?, ?, ?, ?, 'pending', 'paystack', ?, ?, ?, ?, ?)",
            [
                $match['vendor_id'],
                $match['user_id'],
                $name,
                $email,
                $plan,
                $amount,
                self::CURRENCY,
                $reference,
                json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                \sql_now(),
            ]
        );

        $response = PaymentService::initializePaystackTransaction(
            $chargeAmount,
            $chargeCurrency,
            $email,
            $reference,
            \app_url('paystack_callback.php?context=manage_store'),
            [
                'context' => 'vendor_store_management',
                'customer_email' => $email,
                'customer_name' => $name,
                'phone' => $phone,
                'plan' => $plan,
                'order_amount' => number_format($amount, 2, '.', ''),
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

        $stored = \db()->fetch('SELECT raw_response FROM vendor_store_management_services WHERE paystack_reference = ? LIMIT 1', [$reference]);
        $storedRaw = json_decode((string)($stored['raw_response'] ?? ''), true);
        $storedRaw = is_array($storedRaw) ? $storedRaw : [];
        $storedRaw['paystack_checkout']['authorization_url'] = $url;
        $storedRaw['paystack_checkout']['access_code'] = (string)($response['data']['access_code'] ?? '');
        \db()->query(
            'UPDATE vendor_store_management_services SET raw_response = ?, updated_at = ? WHERE paystack_reference = ?',
            [json_encode($storedRaw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), \sql_now(), $reference]
        );

        return $url;
    }

    public static function applyKlashaStatus(string $reference, array $data, string $source = 'callback'): bool
    {
        self::ensureSchema();
        $payment = \db()->fetch('SELECT * FROM vendor_store_management_services WHERE klasha_reference = ? LIMIT 1', [trim($reference)]);
        if (!$payment) {
            return false;
        }
        if (!in_array($source, ['verification', 'webhook_verification'], true)) return false;
        if (!empty($payment['paid_at'])) return true;

        $status = strtolower((string)($data['status'] ?? $data['data']['status'] ?? $data['paymentStatus'] ?? ''));
        $providerSuccessful = in_array($status, ['successful', 'success', 'paid'], true);
        $providerFailed = in_array($status, ['failed', 'failure', 'declined', 'error'], true);
        $providerCancelled = in_array($status, ['cancelled', 'canceled', 'abandoned'], true);
        $reportedCurrency = strtoupper((string)($data['sourceCurrency'] ?? $data['data']['sourceCurrency'] ?? $data['destinationCurrency'] ?? $data['data']['destinationCurrency'] ?? ''));
        $reportedAmount = (float)($data['sourceAmount'] ?? $data['data']['sourceAmount'] ?? $data['amount'] ?? $data['data']['amount'] ?? $data['destinationAmount'] ?? $data['data']['destinationAmount'] ?? 0);
        $amountMatches = $reportedAmount > 0 && abs($reportedAmount - (float)$payment['amount']) < 0.01;
        $currencyMatches = $reportedCurrency === strtoupper((string)$payment['currency']);
        $newStatus = 'pending';
        if ($providerSuccessful && $amountMatches && $currencyMatches) {
            $newStatus = 'active';
        } elseif ($providerSuccessful) {
            $newStatus = 'review';
        } elseif ($providerCancelled) {
            $newStatus = 'cancelled';
        } elseif ($providerFailed) {
            $newStatus = 'failed';
        }

        $raw = json_decode((string)($payment['raw_response'] ?? ''), true);
        $raw = is_array($raw) ? $raw : [];
        $raw['klasha_' . $source] = $data;
        $raw['klasha_' . $source . '_at'] = \sql_now();

        $statement = \db()->query(
            'UPDATE vendor_store_management_services SET status = ?, raw_response = ?, paid_at = CASE WHEN ? = "active" AND paid_at IS NULL THEN ? ELSE paid_at END, updated_at = ? WHERE id = ? AND paid_at IS NULL',
            [
                $newStatus,
                json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $newStatus,
                \sql_now(),
                \sql_now(),
                (int)$payment['id'],
            ]
        );

        if ($newStatus === 'active' && $statement->rowCount() > 0) {
            $updated = \db()->fetch('SELECT * FROM vendor_store_management_services WHERE id = ? LIMIT 1', [(int)$payment['id']]);
            self::notifyAdmins(($updated ?: $payment) + ['provider' => 'klasha', 'status' => $newStatus]);
        }

        return $newStatus === 'active';
    }

    public static function applyPaystackStatus(string $reference, array $data, string $source = 'callback'): bool
    {
        self::ensureSchema();
        $payment = \db()->fetch('SELECT * FROM vendor_store_management_services WHERE paystack_reference = ? LIMIT 1', [trim($reference)]);
        if (!$payment) {
            return false;
        }
        if (!in_array($source, ['verification', 'webhook'], true)) return false;
        if (!empty($payment['paid_at'])) return true;

        $transaction = is_array($data['data'] ?? null) ? $data['data'] : $data;
        $status = strtolower((string)($transaction['status'] ?? $data['event'] ?? ''));
        $providerSuccessful = in_array($status, ['success', 'successful', 'paid', 'charge.success'], true);
        $providerFailed = in_array($status, ['failed', 'failure', 'declined', 'abandoned', 'reversed'], true);
        $raw = json_decode((string)($payment['raw_response'] ?? ''), true);
        $raw = is_array($raw) ? $raw : [];

        $reportedCurrency = strtoupper((string)($transaction['currency'] ?? ''));
        $reportedAmountMinor = (int)($transaction['amount'] ?? 0);
        $expectedCurrency = strtoupper((string)($raw['paystack_checkout']['charge_currency'] ?? $payment['currency'] ?? self::CURRENCY));
        $expectedAmount = (float)($raw['paystack_checkout']['charge_amount'] ?? PaymentService::convertAmount((float)$payment['amount'], (string)$payment['currency'], $expectedCurrency));
        $expectedAmountMinor = PaymentService::paystackMinorAmount($expectedAmount, $expectedCurrency);
        $amountMatches = $reportedAmountMinor > 0 && $reportedAmountMinor === $expectedAmountMinor;
        $currencyMatches = $reportedCurrency === $expectedCurrency;
        $newStatus = 'pending';
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

        $statement = \db()->query(
            'UPDATE vendor_store_management_services SET status = ?, raw_response = ?, paid_at = CASE WHEN ? = "active" AND paid_at IS NULL THEN ? ELSE paid_at END, updated_at = ? WHERE id = ? AND paid_at IS NULL',
            [
                $newStatus,
                json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $newStatus,
                \sql_now(),
                \sql_now(),
                (int)$payment['id'],
            ]
        );

        if ($newStatus === 'active' && $statement->rowCount() > 0) {
            $updated = \db()->fetch('SELECT * FROM vendor_store_management_services WHERE id = ? LIMIT 1', [(int)$payment['id']]);
            self::notifyAdmins(($updated ?: $payment) + ['provider' => 'paystack', 'status' => $newStatus]);
        }

        return $newStatus === 'active';
    }

    public static function handleStripeWebhook(string $payload, string $signature, ?object $verifiedEvent = null): bool
    {
        self::ensureSchema();
        $settings = PaymentService::stripeSettings();
        $secret = trim((string)($settings['webhook_secret'] ?? ''));
        if ($verifiedEvent === null && $secret === '') throw new \RuntimeException('Stripe webhook signing secret is not configured.');
        $event = $verifiedEvent ?? ($secret !== ''
            ? \Stripe\Webhook::constructEvent($payload, $signature, $secret)
            : json_decode($payload, false, 512, JSON_THROW_ON_ERROR));

        $type = (string)$event->type;
        $object = $event->data->object;
        $context = (string)($object->metadata->context ?? '');
        if ($context !== 'vendor_store_management') {
            if ($type === 'invoice.payment_failed') {
                $subscriptionId = (string)($object->subscription ?? '');
                return $subscriptionId !== '' && self::markSubscriptionPastDue($subscriptionId);
            }

            return false;
        }

        if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            self::applyCheckoutSession($object);
            return true;
        }

        if (str_starts_with($type, 'customer.subscription.')) {
            self::applyStripeSubscription($object);
            return true;
        }

        if ($type === 'invoice.payment_failed') {
            $subscriptionId = (string)($object->subscription ?? '');
            return $subscriptionId !== '' && self::markSubscriptionPastDue($subscriptionId);
        }

        return true;
    }

    private static function applyCheckoutSession(object $session): void
    {
        $existing = \db()->fetch('SELECT * FROM vendor_store_management_services WHERE stripe_checkout_session_id = ? LIMIT 1', [(string)$session->id]);
        if (!$existing || !empty($existing['paid_at']) || ($session->payment_status ?? '') !== 'paid') return;
        if ((int)($session->amount_total ?? 0) !== (int)round((float)$existing['amount'] * 100)
            || strtoupper((string)($session->currency ?? '')) !== strtoupper((string)$existing['currency'])) {
            throw new \RuntimeException('Stripe service payment amount or currency does not match.');
        }
        $email = strtolower(trim((string)($session->customer_details->email ?? $session->customer_email ?? $session->metadata->customer_email ?? '')));
        $name = trim((string)($session->customer_details->name ?? $session->metadata->customer_name ?? ''));
        $match = $email !== '' ? self::matchEmail($email) : ['vendor_id' => null, 'user_id' => null];

        $statement = \db()->query(
            "UPDATE vendor_store_management_services
             SET status = 'active', vendor_id = COALESCE(vendor_id, ?), user_id = COALESCE(user_id, ?),
                 customer_name = COALESCE(NULLIF(customer_name, ''), ?), customer_email = COALESCE(NULLIF(customer_email, ''), ?),
                 stripe_customer_id = ?, stripe_subscription_id = ?, paid_at = ?, updated_at = ?
             WHERE stripe_checkout_session_id = ? AND paid_at IS NULL",
            [
                $match['vendor_id'],
                $match['user_id'],
                $name !== '' ? $name : null,
                $email !== '' ? $email : null,
                (string)($session->customer ?? ''),
                !empty($session->subscription) ? (string)$session->subscription : null,
                \sql_now(),
                \sql_now(),
                (string)$session->id,
            ]
        );

        $payment = \db()->fetch('SELECT * FROM vendor_store_management_services WHERE stripe_checkout_session_id = ? LIMIT 1', [(string)$session->id]);
        if ($payment && $statement->rowCount() > 0) {
            self::notifyAdmins($payment + ['provider' => 'stripe', 'status' => 'active']);
        }
    }

    private static function applyStripeSubscription(object $subscription): void
    {
        $subscriptionId = (string)($subscription->id ?? '');
        if ($subscriptionId === '') {
            return;
        }

        $status = self::normalizeStatus((string)($subscription->status ?? 'incomplete'));
        \db()->query(
            "UPDATE vendor_store_management_services
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
            'UPDATE vendor_store_management_services SET status = "past_due", last_payment_failed_at = ?, updated_at = ? WHERE stripe_subscription_id = ?',
            [\sql_now(), \sql_now(), $subscriptionId]
        );

        return (bool)\db()->fetch('SELECT id FROM vendor_store_management_services WHERE stripe_subscription_id = ? LIMIT 1', [$subscriptionId]);
    }

    /**
     * @return array{vendor_id:?int,user_id:?int}
     */
    private static function matchEmail(string $email): array
    {
        $user = \db()->fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
        $userId = $user ? (int)$user['id'] : null;
        $vendor = $userId ? \db()->fetch('SELECT id FROM vendors WHERE user_id = ? LIMIT 1', [$userId]) : null;

        return [
            'vendor_id' => $vendor ? (int)$vendor['id'] : null,
            'user_id' => $userId,
        ];
    }

    private static function notifyAdmins(array $payment): void
    {
        $serviceName = ($payment['plan'] ?? '') === 'setup' ? 'Set Up My Store' : 'Manage My Store';
        try {
            NotificationService::notifyAdmins(
                $serviceName . ' payment received',
                $serviceName . ' payment received',
                '<p>A ' . $serviceName . ' payment has been received. Please contact the customer to arrange the service.</p>'
                    . '<p><strong>Email:</strong> ' . \e((string)($payment['customer_email'] ?? '')) . '<br>'
                    . '<strong>Name:</strong> ' . \e((string)($payment['customer_name'] ?? '')) . '<br>'
                    . '<strong>Plan:</strong> ' . \e((string)($payment['plan'] ?? '')) . '<br>'
                    . '<strong>Provider:</strong> ' . \e((string)($payment['provider'] ?? '')) . '<br>'
                    . '<strong>Amount:</strong> ' . \e((string)($payment['currency'] ?? self::CURRENCY)) . ' ' . number_format((float)($payment['amount'] ?? 0), 2) . '<br>'
                    . '<strong>Matched vendor ID:</strong> ' . \e((string)($payment['vendor_id'] ?? 'Not matched yet')) . '</p>',
                ['metadata' => ['type' => 'manage_store_payment', 'payment_id' => (int)($payment['id'] ?? 0)]]
            );
        } catch (\Throwable $e) {
            error_log('Manage My Store admin notification failed: ' . $e->getMessage());
        }
    }

    private static function amountForPlan(string $plan): float
    {
        return self::normalizePlan($plan) === 'setup' ? self::SETUP_AMOUNT : self::ANNUAL_AMOUNT;
    }

    private static function normalizePlan(string $plan): string
    {
        if (!in_array($plan, ['annual', 'setup'], true)) {
            throw new \InvalidArgumentException('Please select annual store management or one-time store setup.');
        }
        return $plan;
    }

    private static function cleanEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Please enter a valid email address.');
        }

        return $email;
    }

    private static function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') {
            throw new \InvalidArgumentException('Please enter your name.');
        }

        return substr($name, 0, 190);
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

    private static function uniqueKlashaReference(): string
    {
        do {
            $reference = 'SA-MANAGED-STORE-KLASHA-' . strtoupper(bin2hex(random_bytes(8)));
        } while (\db()->fetch('SELECT id FROM vendor_store_management_services WHERE klasha_reference = ? LIMIT 1', [$reference]));

        return $reference;
    }

    private static function uniquePaystackReference(): string
    {
        do {
            $reference = 'SA-MANAGED-STORE-PAYSTACK-' . strtoupper(bin2hex(random_bytes(8)));
        } while (\db()->fetch('SELECT id FROM vendor_store_management_services WHERE paystack_reference = ? LIMIT 1', [$reference]));

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

    /**
     * @return array{0:string,1:string}
     */
    private static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [$parts[0] ?? 'Seller', $parts[1] ?? 'Africa'];
    }
}
