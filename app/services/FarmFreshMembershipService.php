<?php

declare(strict_types=1);

namespace App;

use Stripe\Checkout\Session;
use Stripe\Stripe;

final class FarmFreshMembershipService
{
    public const CURRENCY = 'USD';
    public const MONTHLY_AMOUNT = 19.99;
    public const ANNUAL_AMOUNT = 199.00;

    public static function ensureSchema(): void
    {
        \db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS farm_fresh_memberships (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NULL,
                customer_name VARCHAR(190) NOT NULL,
                customer_email VARCHAR(190) NOT NULL,
                phone VARCHAR(60) NULL,
                city VARCHAR(120) NULL,
                state VARCHAR(120) NULL,
                plan ENUM('monthly', 'annual') NOT NULL DEFAULT 'monthly',
                status ENUM('active', 'trialing', 'paid', 'past_due', 'unpaid', 'cancelled', 'incomplete', 'expired', 'failed', 'pending', 'review') NOT NULL DEFAULT 'incomplete',
                provider VARCHAR(40) NOT NULL DEFAULT 'stripe',
                amount DECIMAL(12, 2) NOT NULL DEFAULT 19.99,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                stripe_customer_id VARCHAR(190) NULL,
                stripe_subscription_id VARCHAR(190) NULL UNIQUE,
                stripe_checkout_session_id VARCHAR(190) NULL UNIQUE,
                klasha_reference VARCHAR(190) NULL UNIQUE,
                current_period_start DATETIME NULL,
                current_period_end DATETIME NULL,
                cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
                raw_response JSON NULL,
                started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                paid_at DATETIME NULL,
                last_payment_failed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_farm_fresh_memberships_user_status (user_id, status),
                INDEX idx_farm_fresh_memberships_email_status (customer_email, status),
                INDEX idx_farm_fresh_memberships_provider (provider, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public static function hasActiveAccess(?array $user = null): bool
    {
        self::ensureSchema();
        $roles = array_map('strval', (array)($_SESSION['roles'] ?? []));
        if (in_array('super_admin', $roles, true) || in_array('admin', $roles, true)) {
            return true;
        }

        $user = $user ?: (\function_exists('current_user') ? \current_user() : null);
        $userId = is_array($user) ? (int)($user['id'] ?? 0) : 0;
        $email = is_array($user) ? strtolower(trim((string)($user['email'] ?? ''))) : '';
        if ($userId <= 0 && $email === '') {
            return false;
        }

        $where = [];
        $params = [];
        if ($userId > 0) {
            $where[] = 'user_id = ?';
            $params[] = $userId;
        }
        if ($email !== '') {
            $where[] = 'customer_email = ?';
            $params[] = $email;
        }

        $row = \db()->fetch(
            "SELECT id
             FROM farm_fresh_memberships
             WHERE (" . implode(' OR ', $where) . ")
               AND status IN ('active', 'trialing', 'paid')
               AND (current_period_end IS NULL OR current_period_end >= ?)
             ORDER BY updated_at DESC, id DESC
             LIMIT 1",
            array_merge($params, [\sql_now()])
        );

        return (bool)$row;
    }

    public static function requireAccess(): void
    {
        $user = \function_exists('current_user') ? \current_user() : null;
        if (self::hasActiveAccess(is_array($user) ? $user : null)) {
            return;
        }

        \flash('info', 'FreshRoots access is for subscribed members. Create your membership to browse farms and produce.');
        \redirect('freshroots/join');
    }

    /**
     * @return array{user_id:int,email:string,name:string}
     */
    public static function ensureUser(array $data): array
    {
        $name = self::cleanName((string)($data['name'] ?? ''));
        $email = self::cleanEmail((string)($data['email'] ?? ''));
        $phone = self::cleanText((string)($data['phone'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $confirm = (string)($data['password_confirmation'] ?? $password);

        $existing = \db()->fetch('SELECT id, email, display_name, password_hash FROM users WHERE email = ? LIMIT 1', [$email]);
        if ($existing) {
            $loggedInId = (int)($_SESSION['user_id'] ?? 0);
            if ($loggedInId !== (int)$existing['id'] && !password_verify($password, (string)($existing['password_hash'] ?? ''))) {
                throw new \InvalidArgumentException('This email already has an account. Please enter the account password or sign in first.');
            }

            return ['user_id' => (int)$existing['id'], 'email' => $email, 'name' => (string)($existing['display_name'] ?: $name)];
        }

        if ($password === '') {
            throw new \InvalidArgumentException('Please create a password.');
        }
        foreach (SecurityService::passwordErrors($password, 5) as $error) {
            throw new \InvalidArgumentException($error);
        }
        if ($password !== $confirm) {
            throw new \InvalidArgumentException('Password confirmation does not match.');
        }

        [$firstName, $lastName] = self::splitName($name);
        $username = self::uniqueUsername($email);
        \db()->query(
            "INSERT INTO users (email, username, password_hash, first_name, last_name, display_name, phone, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active')",
            [
                $email,
                $username,
                password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT),
                $firstName,
                $lastName,
                $name,
                $phone !== '' ? $phone : null,
            ]
        );
        $userId = (int)\db()->lastInsertId();
        self::ensureCustomerRole($userId);

        return ['user_id' => $userId, 'email' => $email, 'name' => $name];
    }

    public static function createStripeCheckout(array $data): string
    {
        self::ensureSchema();
        $user = self::ensureUser($data);
        $plan = self::normalizePlan((string)($data['plan'] ?? 'monthly'));
        $amount = self::amountForPlan($plan);
        $settings = PaymentService::stripeSettings();
        $secret = trim((string)($settings['secret_key'] ?? ''));
        if ($secret === '') {
            throw new \RuntimeException('Stripe is not configured yet. Please use Klasha or contact support.');
        }

        Stripe::setApiKey($secret);
        $metadata = [
            'context' => 'farm_fresh_membership',
            'user_id' => (string)$user['user_id'],
            'customer_email' => $user['email'],
            'customer_name' => $user['name'],
            'plan' => $plan,
        ];
        $priceData = [
            'currency' => strtolower(self::CURRENCY),
            'unit_amount' => (int)round($amount * 100),
            'product_data' => [
                'name' => $plan === 'annual' ? 'FreshRoots Annual Membership' : 'FreshRoots Monthly Membership',
                'description' => 'Access to the Seller Africa FreshRoots farmer directory and farm shop.',
            ],
        ];
        if ($plan === 'monthly') {
            $priceData['recurring'] = ['interval' => 'month'];
        }

        $sessionPayload = [
            'mode' => $plan === 'monthly' ? 'subscription' : 'payment',
            'customer_email' => $user['email'],
            'client_reference_id' => $user['email'],
            'success_url' => \app_url('freshroots/join?paid=1&session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => \app_url('freshroots/join?cancelled=1'),
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
            "INSERT INTO farm_fresh_memberships
                (user_id, customer_name, customer_email, phone, city, state, plan, status, provider, amount, currency, stripe_checkout_session_id, started_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'incomplete', 'stripe', ?, ?, ?, ?)",
            [
                $user['user_id'],
                $user['name'],
                $user['email'],
                self::cleanText((string)($data['phone'] ?? '')) ?: null,
                self::cleanText((string)($data['city'] ?? '')) ?: null,
                self::cleanText((string)($data['state'] ?? '')) ?: null,
                $plan,
                $amount,
                self::CURRENCY,
                (string)$session->id,
                \sql_now(),
            ]
        );

        self::startSession($user['user_id'], $user['email'], $user['name']);
        $url = trim((string)($session->url ?? ''));
        if ($url === '') {
            throw new \RuntimeException('Stripe did not return a checkout URL. Please try again.');
        }

        return $url;
    }

    public static function syncStripeCheckout(string $sessionId): ?array
    {
        self::ensureSchema();
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            return null;
        }
        $secret = trim((string)(PaymentService::stripeSettings()['secret_key'] ?? ''));
        if ($secret === '') {
            return null;
        }

        Stripe::setApiKey($secret);
        $session = Session::retrieve($sessionId);
        if (in_array((string)($session->payment_status ?? ''), ['paid', 'no_payment_required'], true)) {
            self::applyCheckoutSession($session);
        }

        return \db()->fetch('SELECT * FROM farm_fresh_memberships WHERE stripe_checkout_session_id = ? LIMIT 1', [$sessionId]) ?: null;
    }

    public static function createKlashaConfig(array $data): array
    {
        self::ensureSchema();
        PaymentService::ensureKlashaMethod();
        $user = self::ensureUser($data);
        $plan = self::normalizePlan((string)($data['plan'] ?? 'monthly'));
        $amount = self::amountForPlan($plan);
        $method = \db()->fetch("SELECT * FROM payment_methods WHERE code = 'klasha' LIMIT 1");
        $settings = PaymentService::settings($method);
        if (!PaymentService::isKlashaConfigured($method)) {
            throw new \RuntimeException('Klasha is not configured yet. Please use Stripe or contact support.');
        }

        $reference = self::uniqueKlashaReference();
        [$firstName, $lastName] = self::splitName($user['name']);
        $destinationCurrency = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($settings['destination_currency'] ?? 'NGN')) ?: 'NGN', 0, 3));
        $raw = ['klasha_created' => ['reference' => $reference, 'context' => 'farm_fresh_membership', 'created_at' => \sql_now()]];

        \db()->query(
            "INSERT INTO farm_fresh_memberships
                (user_id, customer_name, customer_email, phone, city, state, plan, status, provider, amount, currency, klasha_reference, raw_response, started_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'klasha', ?, ?, ?, ?, ?)",
            [
                $user['user_id'],
                $user['name'],
                $user['email'],
                self::cleanText((string)($data['phone'] ?? '')) ?: null,
                self::cleanText((string)($data['city'] ?? '')) ?: null,
                self::cleanText((string)($data['state'] ?? '')) ?: null,
                $plan,
                $amount,
                self::CURRENCY,
                $reference,
                json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                \sql_now(),
            ]
        );

        self::startSession($user['user_id'], $user['email'], $user['name']);

        return [
            'reference' => $reference,
            'merchantKey' => (string)$settings['public_key'],
            'businessId' => (string)$settings['business_id'],
            'environment' => (string)($settings['mode'] ?? 'test') !== 'live',
            'amount' => $amount,
            'sourceAmount' => (string)$amount,
            'currency' => self::CURRENCY,
            'destinationCurrency' => $destinationCurrency,
            'description' => 'Seller Africa FreshRoots ' . ucfirst($plan) . ' Membership',
            'fullname' => $user['name'],
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $user['email'],
            'phone' => self::cleanText((string)($data['phone'] ?? '')),
            'plan' => $plan,
        ];
    }

    public static function applyKlashaStatus(string $reference, array $data, string $source = 'callback'): bool
    {
        self::ensureSchema();
        $payment = \db()->fetch('SELECT * FROM farm_fresh_memberships WHERE klasha_reference = ? LIMIT 1', [trim($reference)]);
        if (!$payment) {
            return false;
        }

        $status = strtolower((string)($data['status'] ?? $data['data']['status'] ?? $data['paymentStatus'] ?? ''));
        $providerSuccessful = in_array($status, ['successful', 'success', 'paid'], true);
        $providerFailed = in_array($status, ['failed', 'failure', 'declined', 'error'], true);
        $providerCancelled = in_array($status, ['cancelled', 'canceled', 'abandoned'], true);
        $reportedCurrency = strtoupper((string)($data['sourceCurrency'] ?? $data['data']['sourceCurrency'] ?? $data['destinationCurrency'] ?? $data['data']['destinationCurrency'] ?? ''));
        $reportedAmount = (float)($data['sourceAmount'] ?? $data['data']['sourceAmount'] ?? $data['amount'] ?? $data['data']['amount'] ?? $data['destinationAmount'] ?? $data['data']['destinationAmount'] ?? 0);
        $amountMatches = $reportedAmount <= 0 || abs($reportedAmount - (float)$payment['amount']) < 0.01;
        $currencyMatches = $reportedCurrency === '' || $reportedCurrency === strtoupper((string)$payment['currency']);
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
        $periodEnd = self::periodEnd((string)$payment['plan']);

        \db()->query(
            "UPDATE farm_fresh_memberships
             SET status = ?, raw_response = ?, current_period_start = CASE WHEN ? = 'active' THEN COALESCE(current_period_start, ?) ELSE current_period_start END,
                 current_period_end = CASE WHEN ? = 'active' THEN ? ELSE current_period_end END,
                 paid_at = CASE WHEN ? = 'active' AND paid_at IS NULL THEN ? ELSE paid_at END,
                 updated_at = ?
             WHERE id = ?",
            [
                $newStatus,
                json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $newStatus,
                \sql_now(),
                $newStatus,
                $periodEnd,
                $newStatus,
                \sql_now(),
                \sql_now(),
                (int)$payment['id'],
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
        if ($context !== 'farm_fresh_membership') {
            if ($type === 'invoice.payment_failed') {
                $subscriptionId = (string)($object->subscription ?? '');
                return $subscriptionId !== '' && self::markSubscriptionPastDue($subscriptionId);
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
            return $subscriptionId !== '' && self::markSubscriptionPastDue($subscriptionId);
        }

        return true;
    }

    private static function applyCheckoutSession(object $session): void
    {
        $sessionId = (string)($session->id ?? '');
        if ($sessionId === '') {
            return;
        }
        $plan = self::normalizePlan((string)($session->metadata->plan ?? 'monthly'));
        $periodEnd = $plan === 'annual' ? self::periodEnd('annual') : null;
        \db()->query(
            "UPDATE farm_fresh_memberships
             SET status = 'active', stripe_customer_id = ?, stripe_subscription_id = ?, current_period_start = COALESCE(current_period_start, ?),
                 current_period_end = COALESCE(?, current_period_end), paid_at = COALESCE(paid_at, ?), updated_at = ?
             WHERE stripe_checkout_session_id = ?",
            [
                (string)($session->customer ?? ''),
                (string)($session->subscription ?? ''),
                \sql_now(),
                $periodEnd,
                \sql_now(),
                \sql_now(),
                $sessionId,
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
            "UPDATE farm_fresh_memberships
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
            'UPDATE farm_fresh_memberships SET status = "past_due", last_payment_failed_at = ?, updated_at = ? WHERE stripe_subscription_id = ?',
            [\sql_now(), \sql_now(), $subscriptionId]
        );

        return (bool)\db()->fetch('SELECT id FROM farm_fresh_memberships WHERE stripe_subscription_id = ? LIMIT 1', [$subscriptionId]);
    }

    private static function startSession(int $userId, string $email, string $name): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['display_name'] = $name;
        $_SESSION['roles'] = array_values(array_unique(array_merge((array)($_SESSION['roles'] ?? []), ['customer'])));
    }

    private static function ensureCustomerRole(int $userId): void
    {
        $role = \db()->fetch("SELECT id FROM roles WHERE code = 'customer' LIMIT 1");
        if ($role) {
            \db()->query('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, (int)$role['id']]);
        }
    }

    private static function uniqueUsername(string $email): string
    {
        $base = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', strstr($email, '@', true) ?: 'farmfresh'), '-'));
        $base = substr($base !== '' ? $base : 'farmfresh', 0, 40);
        $username = $base;
        $counter = 2;
        while (\db()->fetch('SELECT id FROM users WHERE username = ? LIMIT 1', [$username])) {
            $suffix = '-' . $counter++;
            $username = substr($base, 0, 40 - strlen($suffix)) . $suffix;
        }

        return $username;
    }

    private static function normalizePlan(string $plan): string
    {
        return $plan === 'annual' ? 'annual' : 'monthly';
    }

    private static function amountForPlan(string $plan): float
    {
        return self::normalizePlan($plan) === 'annual' ? self::ANNUAL_AMOUNT : self::MONTHLY_AMOUNT;
    }

    private static function periodEnd(string $plan): string
    {
        return date('Y-m-d H:i:s', strtotime(self::normalizePlan($plan) === 'annual' ? '+1 year' : '+1 month') ?: time());
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
            $reference = 'SA-FARM-FRESH-KLASHA-' . strtoupper(bin2hex(random_bytes(8)));
        } while (\db()->fetch('SELECT id FROM farm_fresh_memberships WHERE klasha_reference = ? LIMIT 1', [$reference]));

        return $reference;
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

    private static function cleanText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [$parts[0] ?? 'Farm', $parts[1] ?? 'Fresh'];
    }
}
