<?php

declare(strict_types=1);

namespace App;

use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;

final class FrozenMarketService
{
    public const EARLY_BIRD_AMOUNT = 277.00;
    public const EARLY_BIRD_CURRENCY = 'USD';

    public function __construct(private Database $db)
    {
    }

    public function ensureSchema(): void
    {
        $this->db->pdo()->exec("
            CREATE TABLE IF NOT EXISTS frozen_categories (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(190) NOT NULL,
                slug VARCHAR(190) NOT NULL UNIQUE,
                description TEXT NULL,
                image_file_id BIGINT UNSIGNED NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_frozen_categories_active (is_active, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->pdo()->exec("
            CREATE TABLE IF NOT EXISTS frozen_vendors (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NULL,
                business_name VARCHAR(190) NOT NULL,
                business_slug VARCHAR(190) NOT NULL UNIQUE,
                contact_email VARCHAR(190) NOT NULL,
                contact_phone VARCHAR(60) NULL,
                country_code CHAR(2) NULL,
                business_address TEXT NULL,
                description TEXT NULL,
                logo_file_id BIGINT UNSIGNED NULL,
                banner_file_id BIGINT UNSIGNED NULL,
                status ENUM('pending','active','rejected','suspended') NOT NULL DEFAULT 'pending',
                kyc_status ENUM('not_started','pending','approved','rejected') NOT NULL DEFAULT 'not_started',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_frozen_vendors_user (user_id),
                INDEX idx_frozen_vendors_status (status, kyc_status),
                CONSTRAINT fk_frozen_vendors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        try {
            $this->db->pdo()->exec('ALTER TABLE frozen_vendors MODIFY user_id BIGINT UNSIGNED NULL');
        } catch (\Throwable) {
            // Older installs may already be in the desired shape.
        }

        $this->db->pdo()->exec("
            CREATE TABLE IF NOT EXISTS frozen_products (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                frozen_vendor_id BIGINT UNSIGNED NOT NULL,
                frozen_category_id BIGINT UNSIGNED NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL UNIQUE,
                short_description TEXT NULL,
                description TEXT NULL,
                sku VARCHAR(100) NULL,
                regular_price DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
                sale_price DECIMAL(19,4) NULL,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                stock_status ENUM('in_stock','out_of_stock','backorder') NOT NULL DEFAULT 'in_stock',
                stock_quantity INT NULL,
                weight DECIMAL(10,4) NULL,
                frozen_storage_note VARCHAR(190) NULL,
                shelf_life_note VARCHAR(190) NULL,
                image_file_id BIGINT UNSIGNED NULL,
                status ENUM('draft','pending','active','rejected','archived') NOT NULL DEFAULT 'pending',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_frozen_products_vendor (frozen_vendor_id, status),
                INDEX idx_frozen_products_category (frozen_category_id, status),
                CONSTRAINT fk_frozen_products_vendor FOREIGN KEY (frozen_vendor_id) REFERENCES frozen_vendors(id) ON DELETE CASCADE,
                CONSTRAINT fk_frozen_products_category FOREIGN KEY (frozen_category_id) REFERENCES frozen_categories(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->pdo()->exec("
            CREATE TABLE IF NOT EXISTS frozen_vendor_applications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                frozen_vendor_id BIGINT UNSIGNED NULL,
                user_id BIGINT UNSIGNED NULL,
                business_name VARCHAR(190) NOT NULL,
                contact_name VARCHAR(190) NOT NULL,
                contact_email VARCHAR(190) NOT NULL,
                contact_phone VARCHAR(60) NOT NULL,
                location VARCHAR(190) NOT NULL,
                cuisine_origin VARCHAR(120) NOT NULL,
                status ENUM('submitted','under_review','approved','rejected') NOT NULL DEFAULT 'submitted',
                payload_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_frozen_vendor_applications_email (contact_email),
                INDEX idx_frozen_vendor_applications_status (status, created_at),
                CONSTRAINT fk_frozen_vendor_applications_vendor FOREIGN KEY (frozen_vendor_id) REFERENCES frozen_vendors(id) ON DELETE SET NULL,
                CONSTRAINT fk_frozen_vendor_applications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->pdo()->exec("
            CREATE TABLE IF NOT EXISTS frozen_buyer_profiles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL UNIQUE,
                delivery_address TEXT NULL,
                delivery_city VARCHAR(120) NULL,
                delivery_state VARCHAR(80) NULL,
                delivery_zip VARCHAR(30) NULL,
                cuisine_preferences_json LONGTEXT NULL,
                marketing_opt_in TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_frozen_buyer_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->pdo()->exec("
            CREATE TABLE IF NOT EXISTS frozen_vendor_plan_selections (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                frozen_vendor_id BIGINT UNSIGNED NULL,
                user_id BIGINT UNSIGNED NULL,
                plan_slug VARCHAR(40) NOT NULL,
                monthly_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                annual_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                commission_rate VARCHAR(40) NULL,
                status ENUM('selected','pending_payment','active','cancelled') NOT NULL DEFAULT 'selected',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_frozen_plan_user (user_id, created_at),
                INDEX idx_frozen_plan_vendor (frozen_vendor_id, created_at),
                CONSTRAINT fk_frozen_plan_vendor FOREIGN KEY (frozen_vendor_id) REFERENCES frozen_vendors(id) ON DELETE SET NULL,
                CONSTRAINT fk_frozen_plan_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->pdo()->exec("
            CREATE TABLE IF NOT EXISTS frozen_waitlist (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                audience ENUM('buyer','vendor') NOT NULL DEFAULT 'buyer',
                email VARCHAR(190) NOT NULL,
                city VARCHAR(120) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_frozen_waitlist_email_audience (email, audience),
                INDEX idx_frozen_waitlist_audience (audience, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->pdo()->exec("
            CREATE TABLE IF NOT EXISTS frozen_waitlist_payments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                waitlist_id BIGINT UNSIGNED NULL,
                audience ENUM('buyer','vendor') NOT NULL DEFAULT 'vendor',
                name VARCHAR(190) NOT NULL,
                email VARCHAR(190) NOT NULL,
                phone VARCHAR(60) NULL,
                city VARCHAR(120) NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 277.00,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                status ENUM('pending','paid','failed','cancelled','expired','review') NOT NULL DEFAULT 'pending',
                stripe_checkout_session_id VARCHAR(190) NULL UNIQUE,
                stripe_payment_intent_id VARCHAR(190) NULL,
                klasha_reference VARCHAR(190) NULL,
                raw_response LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                paid_at DATETIME NULL,
                INDEX idx_frozen_waitlist_payments_email (email),
                INDEX idx_frozen_waitlist_payments_status (status, created_at),
                INDEX idx_frozen_waitlist_payments_klasha (klasha_reference),
                CONSTRAINT fk_frozen_waitlist_payments_waitlist FOREIGN KEY (waitlist_id) REFERENCES frozen_waitlist(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        self::addColumnIfMissing('frozen_waitlist_payments', 'klasha_reference', 'VARCHAR(190) NULL AFTER stripe_payment_intent_id');
        self::addIndexIfMissing('frozen_waitlist_payments', 'idx_frozen_waitlist_payments_klasha', 'klasha_reference');
    }

    public function createVendorApplication(array $data, array $files): int
    {
        $businessName = self::cleanText($data['bizname'] ?? '');
        $contactName = self::cleanText($data['contactname'] ?? '');
        $email = strtolower(self::cleanText($data['email'] ?? ''));
        $phone = self::cleanText($data['phone'] ?? '');
        $location = self::cleanText($data['city'] ?? '');
        $origin = self::cleanText($data['origin'] ?? '');

        if ($businessName === '' || $contactName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '' || $location === '' || $origin === '') {
            throw new \InvalidArgumentException('Please complete the required business, contact, location, and product fields.');
        }

        $payload = [
            'business_structure' => self::cleanText($data['entity'] ?? ''),
            'years_operating' => self::cleanText($data['years'] ?? ''),
            'dishes' => self::cleanText($data['dishes'] ?? ''),
            'contains_meat_poultry_egg' => self::cleanText($data['meat'] ?? ''),
            'cold_chain_method' => self::cleanText($data['coldchain'] ?? ''),
            'carrier' => self::cleanText($data['carrier'] ?? ''),
            'fda_registration_number' => self::cleanText($data['fda'] ?? ''),
            'usda_fsis_number' => self::cleanText($data['usda'] ?? ''),
            'state_license_number' => self::cleanText($data['statelicense'] ?? ''),
            'insurer' => self::cleanText($data['insurer'] ?? ''),
            'policy_number' => self::cleanText($data['policy'] ?? ''),
            'agreements' => [
                'labeling_allergens' => !empty($data['labelcheck']),
                'direct_ship' => !empty($data['agree1']),
                'accuracy' => !empty($data['agree2']),
                'indemnification' => !empty($data['agree3']),
            ],
            'uploads' => [],
        ];

        foreach (['fdaFile' => 'fda_registration', 'coiFile' => 'certificate_of_insurance'] as $field => $bucket) {
            if (!empty($files[$field]) && is_array($files[$field]) && (int)($files[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $payload['uploads'][$field] = $this->storeUpload($files[$field], null, 'frozen-vendor/' . $bucket);
            }
        }

        $user = $this->db->fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
        $userId = $user ? (int)$user['id'] : null;

        $slug = $this->uniqueFrozenVendorSlug($businessName);
        $this->db->beginTransaction();
        try {
            $this->db->query(
                'INSERT INTO frozen_vendors (user_id, business_name, business_slug, contact_email, contact_phone, business_address, description, status, kyc_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, "pending", "pending", ?, ?)',
                [$userId, $businessName, $slug, $email, $phone, $location, $payload['dishes'], \sql_now(), \sql_now()]
            );
            $vendorId = (int)$this->db->lastInsertId();

            $this->db->query(
                'INSERT INTO frozen_vendor_applications (frozen_vendor_id, user_id, business_name, contact_name, contact_email, contact_phone, location, cuisine_origin, payload_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$vendorId, $userId, $businessName, $contactName, $email, $phone, $location, $origin, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), \sql_now(), \sql_now()]
            );
            $applicationId = (int)$this->db->lastInsertId();
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->pdo()->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        \audit('frozen_vendor_application_submitted', 'frozen_vendor_applications', (string)$applicationId, [], [
            'business_name' => $businessName,
            'email' => $email,
            'status' => 'submitted',
        ], $userId);

        return $applicationId;
    }

    public function createBuyer(array $data): int
    {
        $fullName = self::cleanText($data['fname'] ?? '');
        $email = strtolower(self::cleanText($data['email'] ?? ''));
        $password = (string)($data['pwd'] ?? '');
        $phone = self::cleanText($data['phone'] ?? '');

        $address = self::cleanText($data['address'] ?? '');
        $city = self::cleanText($data['city'] ?? '');
        $state = self::cleanText($data['state'] ?? '');
        $zip = self::cleanText($data['zip'] ?? '');

        if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || $address === '' || $city === '' || $state === '' || $zip === '' || empty($data['terms'])) {
            throw new \InvalidArgumentException('Please complete all required fields, accept the terms, and use a password of at least 8 characters.');
        }

        if ($this->db->fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email])) {
            throw new \InvalidArgumentException('An account already exists with this email. Please sign in instead.');
        }

        $parts = preg_split('/\s+/', $fullName, 2) ?: [];
        $firstName = trim((string)($parts[0] ?? $fullName));
        $lastName = trim((string)($parts[1] ?? ''));
        $username = $this->uniqueUsername($email);

        $this->db->beginTransaction();
        try {
            $this->db->query(
                'INSERT INTO users (email, username, password_hash, first_name, last_name, display_name, phone, status, email_verified_at) VALUES (?, ?, ?, ?, ?, ?, ?, "active", ?)',
                [$email, $username, password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT), $firstName, $lastName, $fullName, $phone !== '' ? $phone : null, \sql_now()]
            );
            $userId = (int)$this->db->lastInsertId();
            $this->assignRole($userId, 'customer');

            $preferences = array_values(array_filter(array_map('strval', (array)($data['cuisine'] ?? []))));
            $this->db->query(
                'INSERT INTO frozen_buyer_profiles (user_id, delivery_address, delivery_city, delivery_state, delivery_zip, cuisine_preferences_json, marketing_opt_in, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $userId,
                    $address,
                    $city,
                    $state,
                    $zip,
                    json_encode($preferences, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    !empty($data['marketing']) ? 1 : 0,
                    \sql_now(),
                    \sql_now(),
                ]
            );
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->pdo()->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['display_name'] = $fullName;
        $_SESSION['roles'] = ['customer'];

        \audit('frozen_buyer_registered', 'users', (string)$userId, [], ['email' => $email], $userId);

        return $userId;
    }

    public function recordPlanSelection(string $planSlug): int
    {
        $plans = self::plans();
        if (!isset($plans[$planSlug])) {
            throw new \InvalidArgumentException('Please choose a valid Frozen vendor plan.');
        }

        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $vendorId = null;
        if ($userId) {
            $vendor = $this->db->fetch('SELECT id FROM frozen_vendors WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
            $vendorId = $vendor ? (int)$vendor['id'] : null;
        }

        $plan = $plans[$planSlug];
        $this->db->query(
            'INSERT INTO frozen_vendor_plan_selections (frozen_vendor_id, user_id, plan_slug, monthly_amount, annual_amount, commission_rate, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, "selected", ?, ?)',
            [$vendorId, $userId, $planSlug, $plan['monthly'], $plan['annual'], $plan['commission'], \sql_now(), \sql_now()]
        );
        $id = (int)$this->db->lastInsertId();

        \audit('frozen_vendor_plan_selected', 'frozen_vendor_plan_selections', (string)$id, [], [
            'plan' => $planSlug,
            'annual_amount' => $plan['annual'],
        ], $userId);

        return $id;
    }

    public function joinWaitlist(array $data): int
    {
        $audience = strtolower(self::cleanText($data['audience'] ?? 'buyer'));
        if (!in_array($audience, ['buyer', 'vendor'], true)) {
            $audience = 'buyer';
        }
        $email = strtolower(self::cleanText($data['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Please enter a valid email address.');
        }
        $city = self::cleanText($data['city'] ?? '');

        $this->db->query(
            'INSERT INTO frozen_waitlist (audience, email, city, created_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE city = VALUES(city)',
            [$audience, $email, $city !== '' ? $city : null, \sql_now()]
        );
        $row = $this->db->fetch('SELECT id FROM frozen_waitlist WHERE email = ? AND audience = ? LIMIT 1', [$email, $audience]);
        $id = (int)($row['id'] ?? 0);

        \audit('frozen_waitlist_joined', 'frozen_waitlist', $id > 0 ? (string)$id : null, [], [
            'email' => $email,
            'audience' => $audience,
        ]);

        return $id;
    }

    public function createEarlyBirdCheckout(array $data): string
    {
        $name = self::cleanText($data['name'] ?? '');
        $email = strtolower(self::cleanText($data['email'] ?? ''));
        $phone = self::cleanText($data['phone'] ?? '');
        $city = self::cleanText($data['city'] ?? '');
        $audience = strtolower(self::cleanText($data['audience'] ?? 'vendor'));
        if (!in_array($audience, ['buyer', 'vendor'], true)) {
            $audience = 'vendor';
        }

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $city === '') {
            throw new \InvalidArgumentException('Please enter your name, email, and city before continuing to payment.');
        }

        $waitlistId = $this->joinWaitlist([
            'audience' => $audience,
            'email' => $email,
            'city' => $city,
        ]);

        $settings = PaymentService::stripeSettings();
        $secretKey = trim((string)($settings['secret_key'] ?? ''));
        if ($secretKey === '') {
            throw new \RuntimeException('Stripe is not configured yet. Please contact support to complete early-bird enrollment.');
        }

        $this->db->query(
            'INSERT INTO frozen_waitlist_payments (waitlist_id, audience, name, email, phone, city, amount, currency, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "pending", ?, ?)',
            [$waitlistId ?: null, $audience, $name, $email, $phone !== '' ? $phone : null, $city, self::EARLY_BIRD_AMOUNT, self::EARLY_BIRD_CURRENCY, \sql_now(), \sql_now()]
        );
        $paymentId = (int)$this->db->lastInsertId();

        Stripe::setApiKey($secretKey);
        $metadata = [
            'context' => 'sacfrozen_early_bird_waitlist',
            'payment_id' => (string)$paymentId,
            'waitlist_id' => (string)$waitlistId,
            'audience' => $audience,
        ];

        $session = Session::create([
            'mode' => 'payment',
            'client_reference_id' => 'sacfrozen-early-bird-' . $paymentId,
            'customer_email' => $email,
            'success_url' => \app_url('sacfrozen/waitlist?paid=1&session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => \app_url('sacfrozen/waitlist?cancelled=1'),
            'metadata' => $metadata,
            'payment_intent_data' => [
                'metadata' => $metadata,
            ],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower(self::EARLY_BIRD_CURRENCY),
                    'unit_amount' => self::stripeMinorAmount(self::EARLY_BIRD_AMOUNT, self::EARLY_BIRD_CURRENCY),
                    'product_data' => [
                        'name' => 'SAC Frozen Early-Bird Enrollment',
                        'description' => 'Early access waitlist enrollment for SAC Frozen.',
                    ],
                ],
            ]],
        ]);

        $url = trim((string)($session->url ?? ''));
        if ($url === '') {
            throw new \RuntimeException('Stripe did not return a checkout link. Please try again.');
        }

        $this->db->query(
            'UPDATE frozen_waitlist_payments SET stripe_checkout_session_id = ?, raw_response = ?, updated_at = ? WHERE id = ?',
            [(string)$session->id, json_encode(['checkout_session' => self::stripeObjectToArray($session)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), \sql_now(), $paymentId]
        );

        \audit('frozen_early_bird_checkout_created', 'frozen_waitlist_payments', (string)$paymentId, [], [
            'email' => $email,
            'amount' => self::EARLY_BIRD_AMOUNT,
            'currency' => self::EARLY_BIRD_CURRENCY,
        ]);

        return $url;
    }

    public function createEarlyBirdPaymentIntent(array $data): array
    {
        $name = self::cleanText($data['name'] ?? '');
        $email = strtolower(self::cleanText($data['email'] ?? ''));
        $phone = self::cleanText($data['phone'] ?? '');
        $city = self::cleanText($data['city'] ?? '');
        $audience = strtolower(self::cleanText($data['audience'] ?? 'vendor'));
        $plan = self::earlyBirdPlanFromData($data);
        if (!in_array($audience, ['buyer', 'vendor'], true)) {
            $audience = 'vendor';
        }

        $settings = PaymentService::stripeSettings();
        $secretKey = trim((string)($settings['secret_key'] ?? ''));
        if ($secretKey === '') {
            throw new \RuntimeException('Stripe is not configured yet. Please contact support to complete early-bird enrollment.');
        }

        $waitlistId = null;
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $waitlistId = $this->joinWaitlist([
                'audience' => $audience,
                'email' => $email,
                'city' => $city,
            ]);
        }

        $this->db->query(
            'INSERT INTO frozen_waitlist_payments (waitlist_id, audience, name, email, phone, city, amount, currency, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "pending", ?, ?)',
            [$waitlistId ?: null, $audience, $name, $email, $phone !== '' ? $phone : null, $city, $plan['amount'], self::EARLY_BIRD_CURRENCY, \sql_now(), \sql_now()]
        );
        $paymentId = (int)$this->db->lastInsertId();

        Stripe::setApiKey($secretKey);
        $metadata = [
            'context' => 'sacfrozen_early_bird_waitlist',
            'payment_id' => (string)$paymentId,
            'waitlist_id' => (string)$waitlistId,
            'audience' => $audience,
            'plan_slug' => $plan['slug'],
            'plan_name' => $plan['name'],
            'business_name' => self::cleanText($data['business_name'] ?? ''),
        ];

        $intentPayload = [
            'amount' => self::stripeMinorAmount((float)$plan['amount'], self::EARLY_BIRD_CURRENCY),
            'currency' => strtolower(self::EARLY_BIRD_CURRENCY),
            'description' => 'SAC Frozen ' . $plan['name'] . ' Vendor Early Access',
            'metadata' => $metadata,
            'automatic_payment_methods' => ['enabled' => true],
        ];
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $intentPayload['receipt_email'] = $email;
        }

        try {
            $intent = PaymentIntent::create($intentPayload);
        } catch (\Throwable) {
            unset($intentPayload['automatic_payment_methods']);
            $intentPayload['payment_method_types'] = ['card'];
            $intent = PaymentIntent::create($intentPayload);
        }

        $this->db->query(
            'UPDATE frozen_waitlist_payments SET stripe_payment_intent_id = ?, raw_response = ?, updated_at = ? WHERE id = ?',
            [(string)$intent->id, json_encode(['payment_intent' => self::stripeObjectToArray($intent)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), \sql_now(), $paymentId]
        );

        \audit('frozen_early_bird_payment_intent_created', 'frozen_waitlist_payments', (string)$paymentId, [], [
            'email' => $email !== '' ? $email : 'pending',
            'amount' => $plan['amount'],
            'currency' => self::EARLY_BIRD_CURRENCY,
            'plan' => $plan['slug'],
        ]);

        return [
            'payment_id' => $paymentId,
            'client_secret' => (string)$intent->client_secret,
            'amount' => $plan['amount'],
            'currency' => self::EARLY_BIRD_CURRENCY,
            'plan' => $plan['slug'],
            'publishable_key' => trim((string)($settings['public_key'] ?? '')),
        ];
    }

    public function updateEarlyBirdPaymentIntentDetails(string $paymentIntentId, array $data): array
    {
        $paymentIntentId = trim($paymentIntentId);
        $name = self::cleanText($data['name'] ?? '');
        $email = strtolower(self::cleanText($data['email'] ?? ''));
        $phone = self::cleanText($data['phone'] ?? '');
        $city = self::cleanText($data['city'] ?? '');
        $plan = self::earlyBirdPlanFromData($data);

        if ($paymentIntentId === '') {
            throw new \InvalidArgumentException('Payment could not be found. Please refresh the page and try again.');
        }
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $city === '') {
            throw new \InvalidArgumentException('Please enter your name, email, and city before paying.');
        }

        $payment = $this->db->fetch('SELECT * FROM frozen_waitlist_payments WHERE stripe_payment_intent_id = ? LIMIT 1', [$paymentIntentId]);
        if (!$payment) {
            throw new \RuntimeException('Payment could not be found. Please refresh the page and try again.');
        }

        $audience = (string)($payment['audience'] ?? 'vendor');
        if (!in_array($audience, ['buyer', 'vendor'], true)) {
            $audience = 'vendor';
        }
        $waitlistId = $this->joinWaitlist([
            'audience' => $audience,
            'email' => $email,
            'city' => $city,
        ]);

        $this->db->query(
            'UPDATE frozen_waitlist_payments SET waitlist_id = ?, name = ?, email = ?, phone = ?, city = ?, amount = ?, updated_at = ? WHERE id = ?',
            [$waitlistId ?: null, $name, $email, $phone !== '' ? $phone : null, $city, $plan['amount'], \sql_now(), (int)$payment['id']]
        );

        $settings = PaymentService::stripeSettings();
        $secretKey = trim((string)($settings['secret_key'] ?? ''));
        if ($secretKey !== '') {
            Stripe::setApiKey($secretKey);
            PaymentIntent::update($paymentIntentId, [
                'amount' => self::stripeMinorAmount((float)$plan['amount'], self::EARLY_BIRD_CURRENCY),
                'receipt_email' => $email,
                'description' => 'SAC Frozen ' . $plan['name'] . ' Vendor Early Access',
                'metadata' => [
                    'context' => 'sacfrozen_early_bird_waitlist',
                    'payment_id' => (string)$payment['id'],
                    'waitlist_id' => (string)$waitlistId,
                    'audience' => $audience,
                    'email' => $email,
                    'plan_slug' => $plan['slug'],
                    'plan_name' => $plan['name'],
                    'business_name' => self::cleanText($data['business_name'] ?? ''),
                    'cuisine' => self::cleanText($data['cuisine'] ?? ''),
                    'docs_confirmed' => self::cleanText($data['docs_confirmed'] ?? ''),
                ],
            ]);
        }

        \audit('frozen_early_bird_payment_details_updated', 'frozen_waitlist_payments', (string)$payment['id'], [], [
            'email' => $email,
            'payment_intent_id' => $paymentIntentId,
            'plan' => $plan['slug'],
        ]);

        return $this->db->fetch('SELECT * FROM frozen_waitlist_payments WHERE id = ? LIMIT 1', [(int)$payment['id']]) ?: [];
    }

    public function createEarlyBirdKlashaConfig(array $data): array
    {
        $name = self::cleanText($data['name'] ?? '');
        $email = strtolower(self::cleanText($data['email'] ?? ''));
        $phone = self::cleanText($data['phone'] ?? '');
        $city = self::cleanText($data['city'] ?? '');
        $businessName = self::cleanText($data['business_name'] ?? '');
        $plan = self::earlyBirdPlanFromData($data);

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $city === '') {
            throw new \InvalidArgumentException('Please enter your name, email, and city before paying with Klasha.');
        }

        PaymentService::ensureKlashaMethod();
        $method = $this->db->fetch("SELECT * FROM payment_methods WHERE code = 'klasha' LIMIT 1");
        $settings = PaymentService::settings($method);
        if (!PaymentService::isKlashaConfigured($method)) {
            throw new \RuntimeException('Klasha is not configured yet. Please use card payment or contact support.');
        }

        $waitlistId = $this->joinWaitlist([
            'audience' => 'vendor',
            'email' => $email,
            'city' => $city,
        ]);
        $reference = self::uniqueKlashaReference();
        $destinationCurrency = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($settings['destination_currency'] ?? 'NGN')) ?: 'NGN', 0, 3));
        $amount = round((float)$plan['amount'], 2);

        $raw = [
            'klasha_created' => [
                'reference' => $reference,
                'plan' => $plan,
                'business_name' => $businessName,
                'cuisine' => self::cleanText($data['cuisine'] ?? ''),
                'docs_confirmed' => self::cleanText($data['docs_confirmed'] ?? ''),
                'created_at' => \sql_now(),
            ],
        ];

        $this->db->query(
            'INSERT INTO frozen_waitlist_payments (waitlist_id, audience, name, email, phone, city, amount, currency, status, klasha_reference, raw_response, created_at, updated_at) VALUES (?, "vendor", ?, ?, ?, ?, ?, ?, "pending", ?, ?, ?, ?)',
            [
                $waitlistId ?: null,
                $name,
                $email,
                $phone !== '' ? $phone : null,
                $city,
                $amount,
                self::EARLY_BIRD_CURRENCY,
                $reference,
                json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                \sql_now(),
                \sql_now(),
            ]
        );
        $paymentId = (int)$this->db->lastInsertId();

        \audit('frozen_early_bird_klasha_created', 'frozen_waitlist_payments', (string)$paymentId, [], [
            'email' => $email,
            'amount' => $amount,
            'currency' => self::EARLY_BIRD_CURRENCY,
            'plan' => $plan['slug'],
            'reference' => $reference,
        ]);

        [$firstName, $lastName] = self::splitName($name);

        return [
            'payment_id' => $paymentId,
            'reference' => $reference,
            'merchantKey' => (string)$settings['public_key'],
            'businessId' => (string)$settings['business_id'],
            'environment' => (string)($settings['mode'] ?? 'test') !== 'live',
            'amount' => $amount,
            'sourceAmount' => (string)$amount,
            'currency' => self::EARLY_BIRD_CURRENCY,
            'destinationCurrency' => $destinationCurrency,
            'description' => 'SAC Frozen ' . $plan['name'] . ' Vendor Early Access',
            'fullname' => $name,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'plan' => $plan['slug'],
        ];
    }

    public function applyEarlyBirdKlashaStatus(string $reference, array $data, string $source = 'callback'): bool
    {
        $reference = trim($reference);
        if ($reference === '') {
            return false;
        }

        $payment = $this->db->fetch('SELECT * FROM frozen_waitlist_payments WHERE klasha_reference = ? LIMIT 1', [$reference]);
        if (!$payment) {
            return false;
        }

        $status = strtolower((string)($data['status'] ?? $data['data']['status'] ?? $data['paymentStatus'] ?? ''));
        $providerSuccessful = in_array($status, ['successful', 'success', 'paid'], true);
        $providerFailed = in_array($status, ['failed', 'failure', 'declined', 'error'], true);
        $providerCancelled = in_array($status, ['cancelled', 'canceled', 'abandoned'], true);
        $reportedCurrency = strtoupper((string)($data['sourceCurrency'] ?? $data['data']['sourceCurrency'] ?? $data['destinationCurrency'] ?? $data['data']['destinationCurrency'] ?? ''));
        $reportedAmount = (float)($data['sourceAmount'] ?? $data['data']['sourceAmount'] ?? $data['amount'] ?? $data['data']['amount'] ?? $data['destinationAmount'] ?? $data['data']['destinationAmount'] ?? 0);
        $expectedCurrency = strtoupper((string)($payment['currency'] ?? self::EARLY_BIRD_CURRENCY));
        $expectedAmount = (float)($payment['amount'] ?? 0);
        $amountMatches = $reportedAmount <= 0 || abs($reportedAmount - $expectedAmount) < 0.01;
        $currencyMatches = $reportedCurrency === '' || $reportedCurrency === $expectedCurrency;
        $isSuccessful = $providerSuccessful && $amountMatches && $currencyMatches;
        $requiresReview = $providerSuccessful && (!$amountMatches || !$currencyMatches);

        $raw = json_decode((string)($payment['raw_response'] ?? ''), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        $raw['klasha_' . $source] = $data;
        $raw['klasha_' . $source . '_at'] = \sql_now();
        $raw['klasha_verification'] = [
            'provider_successful' => $providerSuccessful,
            'reported_amount' => $reportedAmount,
            'expected_amount' => $expectedAmount,
            'reported_currency' => $reportedCurrency,
            'expected_currency' => $expectedCurrency,
            'amount_matches' => $amountMatches,
            'currency_matches' => $currencyMatches,
            'source' => $source,
        ];

        $newStatus = 'pending';
        if ($isSuccessful) {
            $newStatus = 'paid';
        } elseif ($requiresReview) {
            $newStatus = 'review';
        } elseif ($providerCancelled) {
            $newStatus = 'cancelled';
        } elseif ($providerFailed) {
            $newStatus = 'failed';
        } elseif ((string)($payment['status'] ?? '') === 'paid') {
            $newStatus = 'paid';
        }

        $this->db->query(
            'UPDATE frozen_waitlist_payments SET status = ?, raw_response = ?, paid_at = CASE WHEN ? = "paid" AND paid_at IS NULL THEN ? ELSE paid_at END, updated_at = ? WHERE id = ?',
            [
                $newStatus,
                json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $newStatus,
                \sql_now(),
                \sql_now(),
                (int)$payment['id'],
            ]
        );

        \audit('frozen_early_bird_klasha_status', 'frozen_waitlist_payments', (string)$payment['id'], [], [
            'reference' => $reference,
            'status' => $newStatus,
            'provider_status' => $status,
            'source' => $source,
        ]);

        return $newStatus === 'paid';
    }

    public function syncEarlyBirdPaymentIntent(string $paymentIntentId): ?array
    {
        $paymentIntentId = trim($paymentIntentId);
        if ($paymentIntentId === '') {
            return null;
        }

        $payment = $this->db->fetch('SELECT * FROM frozen_waitlist_payments WHERE stripe_payment_intent_id = ? LIMIT 1', [$paymentIntentId]);
        if (!$payment) {
            return null;
        }

        $settings = PaymentService::stripeSettings();
        $secretKey = trim((string)($settings['secret_key'] ?? ''));
        if ($secretKey === '') {
            return $payment;
        }

        Stripe::setApiKey($secretKey);
        $intent = PaymentIntent::retrieve($paymentIntentId);
        $this->applyEarlyBirdPaymentIntent($intent, 'stripe.payment_intent.refresh');

        return $this->db->fetch('SELECT * FROM frozen_waitlist_payments WHERE stripe_payment_intent_id = ? LIMIT 1', [$paymentIntentId]);
    }

    public function refreshEarlyBirdCheckout(string $sessionId): ?array
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            return null;
        }

        $payment = $this->db->fetch('SELECT * FROM frozen_waitlist_payments WHERE stripe_checkout_session_id = ? LIMIT 1', [$sessionId]);
        if (!$payment) {
            return null;
        }

        $settings = PaymentService::stripeSettings();
        $secretKey = trim((string)($settings['secret_key'] ?? ''));
        if ($secretKey === '') {
            return $payment;
        }

        Stripe::setApiKey($secretKey);
        $session = Session::retrieve($sessionId);
        $this->applyEarlyBirdCheckoutSession($session, 'stripe.session.refresh');

        return $this->db->fetch('SELECT * FROM frozen_waitlist_payments WHERE stripe_checkout_session_id = ? LIMIT 1', [$sessionId]);
    }

    public static function handleStripeWebhook(string $payload, string $signature, ?object $verifiedEvent = null): bool
    {
        $settings = PaymentService::stripeSettings();
        $webhookSecret = trim((string)($settings['webhook_secret'] ?? ''));
        if ($verifiedEvent !== null) {
            $event = $verifiedEvent;
        } elseif ($webhookSecret !== '') {
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        } else {
            $event = json_decode($payload);
            if (!is_object($event)) {
                throw new \RuntimeException('Invalid Stripe webhook payload.');
            }
        }

        $type = (string)($event->type ?? '');
        $object = $event->data->object ?? null;
        if (!is_object($object)) {
            return false;
        }

        $metadata = $object->metadata ?? (object)[];
        if ((string)($metadata->context ?? '') !== 'sacfrozen_early_bird_waitlist') {
            return false;
        }

        $service = new self(\db());
        $service->ensureSchema();
        if (str_starts_with($type, 'checkout.session.')) {
            $service->applyEarlyBirdCheckoutSession($object, $type);
            return true;
        }

        if (str_starts_with($type, 'payment_intent.')) {
            $service->applyEarlyBirdPaymentIntent($object, $type);
            return true;
        }

        return false;
    }

    public static function logoHtml(string $class = '', string $fallback = 'Seller Africa'): string
    {
        $brand = \app_branding();
        $name = (string)($brand['name'] ?? $fallback);
        $classAttr = $class !== '' ? ' class="' . \e($class) . '"' : '';

        if (!empty($brand['logo'])) {
            return '<img' . $classAttr . ' src="' . \e((string)$brand['logo']) . '" alt="' . \e($name) . '">';
        }

        return '<strong' . $classAttr . '>' . \e($name) . '</strong>';
    }

    public static function plans(): array
    {
        return [
            'basic' => ['monthly' => 10.00, 'annual' => 120.00, 'commission' => '8% per order'],
            'standard' => ['monthly' => 29.99, 'annual' => 359.88, 'commission' => '6% per order'],
            'premium' => ['monthly' => 49.99, 'annual' => 599.88, 'commission' => '5% per order'],
        ];
    }

    private static function earlyBirdPlanFromData(array $data): array
    {
        $plans = self::plans();
        $slug = strtolower(self::cleanText($data['plan'] ?? 'standard'));
        if (!isset($plans[$slug])) {
            $slug = 'standard';
        }

        $plan = $plans[$slug];
        return [
            'slug' => $slug,
            'name' => ucwords($slug),
            'amount' => (float)$plan['annual'],
            'monthly' => (float)$plan['monthly'],
            'commission' => (string)$plan['commission'],
        ];
    }

    private static function uniqueKlashaReference(): string
    {
        do {
            $reference = 'SA-FROZEN-KLASHA-' . strtoupper(bin2hex(random_bytes(6)));
        } while (\db()->fetch('SELECT id FROM frozen_waitlist_payments WHERE klasha_reference = ? LIMIT 1', [$reference]));

        return $reference;
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function splitName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['Vendor', 'Vendor'];
        }
        $parts = preg_split('/\s+/', $name) ?: [];
        $first = trim((string)($parts[0] ?? 'Vendor')) ?: 'Vendor';
        $last = trim(implode(' ', array_slice($parts, 1))) ?: $first;

        return [$first, $last];
    }

    private static function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        if (self::columnExists($table, $column)) {
            return;
        }

        try {
            \db()->pdo()->exec('ALTER TABLE ' . $table . ' ADD ' . $column . ' ' . $definition);
        } catch (\Throwable) {
            // A concurrent request may have added it already.
        }
    }

    private static function addIndexIfMissing(string $table, string $index, string $column): void
    {
        try {
            $config = \db_config();
            $row = \db()->fetch(
                'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
                [(string)($config['database'] ?? ''), $table, $index]
            );
            if ((int)($row['total'] ?? 0) > 0) {
                return;
            }
            \db()->pdo()->exec('ALTER TABLE ' . $table . ' ADD INDEX ' . $index . ' (' . $column . ')');
        } catch (\Throwable) {
            // Index creation is best-effort for older installs.
        }
    }

    private static function columnExists(string $table, string $column): bool
    {
        try {
            $config = \db_config();
            $row = \db()->fetch(
                'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [(string)($config['database'] ?? ''), $table, $column]
            );

            return (int)($row['total'] ?? 0) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function applyEarlyBirdCheckoutSession(object $session, string $eventType): void
    {
        $sessionId = (string)($session->id ?? '');
        if ($sessionId === '') {
            return;
        }

        $payment = $this->db->fetch('SELECT * FROM frozen_waitlist_payments WHERE stripe_checkout_session_id = ? LIMIT 1', [$sessionId]);
        if (!$payment) {
            return;
        }

        $paymentStatus = strtolower((string)($session->payment_status ?? ''));
        $checkoutStatus = strtolower((string)($session->status ?? ''));
        $reportedAmount = (int)($session->amount_total ?? 0);
        $reportedCurrency = strtoupper((string)($session->currency ?? ''));
        $expectedAmount = self::stripeMinorAmount((float)$payment['amount'], (string)$payment['currency']);
        $expectedCurrency = strtoupper((string)$payment['currency']);
        $amountMatches = $reportedAmount === $expectedAmount;
        $currencyMatches = $reportedCurrency === '' || $reportedCurrency === $expectedCurrency;

        $status = (string)$payment['status'];
        if ((in_array($paymentStatus, ['paid', 'no_payment_required'], true) || $eventType === 'checkout.session.async_payment_succeeded') && $amountMatches && $currencyMatches) {
            $status = 'paid';
        } elseif (in_array($eventType, ['checkout.session.expired', 'checkout.session.async_payment_failed'], true) || $checkoutStatus === 'expired') {
            $status = $checkoutStatus === 'expired' ? 'expired' : 'failed';
        } elseif (in_array($paymentStatus, ['paid', 'no_payment_required'], true) && (!$amountMatches || !$currencyMatches)) {
            $status = 'review';
        }

        $raw = json_decode((string)($payment['raw_response'] ?? ''), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        $raw['stripe_update'] = [
            'event' => $eventType,
            'payment_status' => $paymentStatus,
            'checkout_status' => $checkoutStatus,
            'amount_total' => $reportedAmount,
            'currency' => $reportedCurrency,
            'updated_at' => \sql_now(),
        ];

        $this->db->query(
            'UPDATE frozen_waitlist_payments SET status = ?, stripe_payment_intent_id = ?, raw_response = ?, paid_at = CASE WHEN ? = "paid" AND paid_at IS NULL THEN ? ELSE paid_at END, updated_at = ? WHERE id = ?',
            [
                $status,
                (string)($session->payment_intent ?? ''),
                json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $status,
                \sql_now(),
                \sql_now(),
                (int)$payment['id'],
            ]
        );
    }

    private function applyEarlyBirdPaymentIntent(object $intent, string $eventType): void
    {
        $intentId = (string)($intent->id ?? '');
        if ($intentId === '') {
            return;
        }

        $payment = $this->db->fetch('SELECT * FROM frozen_waitlist_payments WHERE stripe_payment_intent_id = ? LIMIT 1', [$intentId]);
        if (!$payment) {
            return;
        }

        $providerStatus = strtolower((string)($intent->status ?? ''));
        $reportedAmount = (int)($intent->amount_received ?? $intent->amount ?? 0);
        $reportedCurrency = strtoupper((string)($intent->currency ?? ''));
        $expectedAmount = self::stripeMinorAmount((float)$payment['amount'], (string)$payment['currency']);
        $expectedCurrency = strtoupper((string)$payment['currency']);
        $amountMatches = $reportedAmount === $expectedAmount;
        $currencyMatches = $reportedCurrency === '' || $reportedCurrency === $expectedCurrency;

        $status = (string)$payment['status'];
        if ($providerStatus === 'succeeded' && $amountMatches && $currencyMatches) {
            $status = 'paid';
        } elseif (in_array($providerStatus, ['canceled', 'requires_payment_method'], true) && str_contains($eventType, 'failed')) {
            $status = 'failed';
        } elseif ($providerStatus === 'succeeded' && (!$amountMatches || !$currencyMatches)) {
            $status = 'review';
        }

        $raw = json_decode((string)($payment['raw_response'] ?? ''), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        $raw['payment_intent_update'] = [
            'event' => $eventType,
            'provider_status' => $providerStatus,
            'amount_received' => $reportedAmount,
            'currency' => $reportedCurrency,
            'updated_at' => \sql_now(),
        ];

        $this->db->query(
            'UPDATE frozen_waitlist_payments SET status = ?, raw_response = ?, paid_at = CASE WHEN ? = "paid" AND paid_at IS NULL THEN ? ELSE paid_at END, updated_at = ? WHERE id = ?',
            [
                $status,
                json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $status,
                \sql_now(),
                \sql_now(),
                (int)$payment['id'],
            ]
        );
    }

    private static function stripeMinorAmount(float $amount, string $currency): int
    {
        $zeroDecimal = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
        return (int)round($amount * (in_array(strtoupper($currency), $zeroDecimal, true) ? 1 : 100));
    }

    private static function stripeObjectToArray(object $object): array
    {
        if (method_exists($object, 'toArray')) {
            return $object->toArray();
        }
        return json_decode(json_encode($object, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', true) ?: [];
    }

    private function storeUpload(array $file, ?int $ownerUserId, string $bucket): array
    {
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        $tmp = (string)($file['tmp_name'] ?? '');
        $mime = $tmp !== '' ? (string)(mime_content_type($tmp) ?: '') : '';
        if ($tmp === '' || !in_array($mime, $allowed, true)) {
            throw new \RuntimeException('One uploaded document is not a supported PDF or image file.');
        }

        $dir = APP_ROOT . '/public/uploads/' . trim($bucket, '/');
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException('Upload folder could not be created.');
        }

        $ext = match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
        $filename = bin2hex(random_bytes(12)) . '.' . $ext;
        $target = $dir . '/' . $filename;
        if (!move_uploaded_file($tmp, $target)) {
            throw new \RuntimeException('Uploaded document could not be saved.');
        }

        $relative = 'uploads/' . trim($bucket, '/') . '/' . $filename;
        $this->db->query(
            'INSERT INTO files (owner_user_id, disk, path, original_name, mime_type, size_bytes, alt_text, created_at) VALUES (?, "public", ?, ?, ?, ?, ?, ?)',
            [$ownerUserId, $relative, (string)($file['name'] ?? $filename), $mime, filesize($target) ?: 0, pathinfo((string)($file['name'] ?? $filename), PATHINFO_FILENAME), \sql_now()]
        );

        return [
            'file_id' => (int)$this->db->lastInsertId(),
            'path' => $relative,
            'original_name' => (string)($file['name'] ?? ''),
            'mime_type' => $mime,
        ];
    }

    private function uniqueFrozenVendorSlug(string $name): string
    {
        $base = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        $base = $base !== '' ? substr($base, 0, 160) : 'frozen-vendor';
        $slug = $base;
        $i = 2;
        while ($this->db->fetch('SELECT id FROM frozen_vendors WHERE business_slug = ? LIMIT 1', [$slug])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private function uniqueUsername(string $email): string
    {
        $base = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', strstr($email, '@', true) ?: 'buyer'), '-'));
        $base = $base !== '' ? substr($base, 0, 40) : 'buyer';
        $username = $base;
        $i = 2;
        while ($this->db->fetch('SELECT id FROM users WHERE username = ? LIMIT 1', [$username])) {
            $suffix = '-' . $i++;
            $username = substr($base, 0, 40 - strlen($suffix)) . $suffix;
        }
        return $username;
    }

    private function assignRole(int $userId, string $role): void
    {
        $row = $this->db->fetch('SELECT id FROM roles WHERE code = ? LIMIT 1', [$role]);
        if (!$row) {
            throw new \RuntimeException('Required role is missing: ' . $role);
        }
        $this->db->query('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, (int)$row['id']]);
    }

    private static function cleanText(mixed $value): string
    {
        return trim(strip_tags((string)$value));
    }
}
