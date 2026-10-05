<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/core/bootstrap.php';

try {
    if (!super_admin_exists()) {
        flash('info', 'Create the first super admin before opening the dashboard.');
        redirect('onboarding.php');
    }
} catch (\Throwable) {
    flash('warning', 'Database setup is not complete yet.');
    redirect('onboarding.php');
}

use App\MigrationService;
use App\PaymentService;
use App\EmailService;
use App\SecurityService;
use App\StorefrontTemplateService;
use App\VendorSubscriptionService;

\App\RoleDashboardService::requireRole('admin');

$requestPath = trim(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '', '/');
$pathParts = explode('/', $requestPath);
$pathView = null;
$dashboardIndex = array_search('dashboard', $pathParts, true);
if ($dashboardIndex !== false && isset($pathParts[$dashboardIndex + 1])) {
    $pathView = $pathParts[$dashboardIndex + 1];
}

$view = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['view'] ?? $pathView ?? 'overview')) ?: 'overview';
\App\AdminAccessService::enforce($view);
VendorSubscriptionService::ensureSchema();
(new StorefrontTemplateService(db()))->ensureReviewMediaSchema();
$navigation = require VIEW_PATH . '/partials/admin_navigation.php';
$settingsPages = [
    'general-settings' => [
        'summary' => 'Company identity, support channels, default operating region and admin-facing platform state.',
        'group' => 'general',
        'fields' => [
            ['key' => 'site_name', 'label' => 'Platform Name', 'type' => 'text', 'default' => 'Seller Africa'],
            ['key' => 'logo_path', 'label' => 'Application Logo', 'type' => 'file', 'accept' => 'image/png,image/jpeg,image/webp', 'default' => ''],
            ['key' => 'support_email', 'label' => 'Support Email', 'type' => 'email', 'default' => 'support@sellerafrica.com'],
            ['key' => 'support_phone', 'label' => 'Support Phone', 'type' => 'text', 'default' => '+1'],
            ['key' => 'default_country', 'label' => 'Default Country', 'type' => 'text', 'default' => 'United States'],
            ['key' => 'maintenance_mode', 'label' => 'Maintenance Mode', 'type' => 'select', 'options' => ['off' => 'Off', 'on' => 'On'], 'default' => 'off'],
            ['key' => 'maintenance_message', 'label' => 'Maintenance Message', 'type' => 'textarea', 'default' => 'We are making improvements and will be back shortly.'],
            ['key' => 'maintenance_starts_at', 'label' => 'Maintenance Starts At', 'type' => 'datetime-local', 'default' => ''],
            ['key' => 'maintenance_ends_at', 'label' => 'Maintenance Ends At', 'type' => 'datetime-local', 'default' => ''],
            ['key' => 'maintenance_ip_whitelist', 'label' => 'IP Whitelist', 'type' => 'textarea', 'default' => ''],
            ['key' => 'maintenance_user_whitelist', 'label' => 'User ID Whitelist', 'type' => 'textarea', 'default' => ''],
        ],
    ],
    'store-settings' => [
        'summary' => 'Marketplace selling rules, vendor onboarding behavior, catalog review and fulfillment defaults.',
        'group' => 'store',
        'fields' => [
            ['key' => 'vendor_approval', 'label' => 'Vendor Approval', 'type' => 'select', 'options' => ['manual' => 'Manual review', 'automatic' => 'Automatic'], 'default' => 'manual'],
            ['key' => 'product_approval', 'label' => 'Product Approval', 'type' => 'select', 'options' => ['manual' => 'Manual review', 'trusted_vendor' => 'Auto-approve trusted vendors'], 'default' => 'manual'],
            ['key' => 'default_fulfillment', 'label' => 'Default Fulfillment', 'type' => 'select', 'options' => ['vendor' => 'Fulfillment by vendor', 'seller_africa' => 'Fulfillment by Seller Africa'], 'default' => 'vendor'],
            ['key' => 'low_stock_threshold', 'label' => 'Low Stock Threshold', 'type' => 'number', 'default' => '5'],
            ['key' => 'guest_checkout', 'label' => 'Guest Checkout', 'type' => 'select', 'options' => ['enabled' => 'Enabled', 'disabled' => 'Disabled'], 'default' => 'enabled'],
        ],
    ],
    'currency-settings' => [
        'summary' => 'Default storefront currency, allowed currencies and formatting rules.',
        'group' => 'currency',
        'fields' => [
            ['key' => 'default_currency', 'label' => 'Default Currency', 'type' => 'select', 'options' => ['USD' => 'USD', 'NGN' => 'NGN', 'GBP' => 'GBP', 'EUR' => 'EUR', 'CAD' => 'CAD'], 'default' => 'USD'],
            ['key' => 'enabled_currencies', 'label' => 'Enabled Currencies', 'type' => 'text', 'default' => 'USD,NGN,GBP,EUR'],
            ['key' => 'exchange_rate_source', 'label' => 'Rate Source', 'type' => 'select', 'options' => ['manual' => 'Manual', 'gateway' => 'Payment gateway'], 'default' => 'manual'],
            ['key' => 'price_rounding', 'label' => 'Price Rounding', 'type' => 'select', 'options' => ['none' => 'No rounding', 'nearest_99' => 'Nearest .99', 'whole' => 'Whole numbers'], 'default' => 'none'],
        ],
    ],
    'tax-settings' => [
        'summary' => 'Tax calculation defaults for marketplace sales, vendor invoices and checkout display.',
        'group' => 'tax',
        'fields' => [
            ['key' => 'tax_enabled', 'label' => 'Tax Collection', 'type' => 'select', 'options' => ['off' => 'Off', 'on' => 'On'], 'default' => 'off'],
            ['key' => 'prices_include_tax', 'label' => 'Prices Include Tax', 'type' => 'select', 'options' => ['no' => 'No', 'yes' => 'Yes'], 'default' => 'no'],
            ['key' => 'default_tax_rate', 'label' => 'Default Tax Rate %', 'type' => 'number', 'default' => '0'],
            ['key' => 'tax_label', 'label' => 'Tax Label', 'type' => 'text', 'default' => 'Tax'],
        ],
    ],
    'seo-settings' => [
        'summary' => 'Global SEO metadata, social sharing previews, favicon and Google Analytics defaults.',
        'group' => 'seo',
        'fields' => [
            ['key' => 'meta_title', 'label' => 'Default Meta Title', 'type' => 'text', 'default' => 'Seller Africa'],
            ['key' => 'meta_description', 'label' => 'Default Meta Description', 'type' => 'textarea', 'default' => 'Shop the African and Caribbean Marketplace from trusted vendors.'],
            ['key' => 'meta_keywords', 'label' => 'Default Meta Keywords', 'type' => 'text', 'default' => 'African marketplace, Caribbean marketplace, diaspora shopping, Seller Africa'],
            ['key' => 'google_analytics_id', 'label' => 'Google Analytics / Tag ID', 'type' => 'text', 'default' => ''],
            ['key' => 'favicon_path', 'label' => 'Favicon Image', 'type' => 'file', 'accept' => 'image/png,image/jpeg,image/webp', 'default' => ''],
            ['key' => 'og_image_path', 'label' => 'Default Social Share Image', 'type' => 'file', 'accept' => 'image/png,image/jpeg,image/webp', 'default' => ''],
            ['key' => 'twitter_handle', 'label' => 'Twitter / X Handle', 'type' => 'text', 'default' => ''],
            ['key' => 'robots_index', 'label' => 'Search Indexing', 'type' => 'select', 'options' => ['index' => 'Index storefront', 'noindex' => 'Noindex storefront'], 'default' => 'index'],
            ['key' => 'canonical_domain', 'label' => 'Canonical Domain', 'type' => 'url', 'default' => ''],
        ],
    ],
    'mail-settings' => [
        'summary' => 'Transactional email sender identity, SMTP, Brevo API, ZeptoMail API and delivery safeguards.',
        'group' => 'mail',
        'fields' => [
            ['key' => 'from_name', 'label' => 'From Name', 'type' => 'text', 'default' => 'Seller Africa'],
            ['key' => 'from_email', 'label' => 'From Email', 'type' => 'email', 'default' => 'no-reply@sellerafrica.com'],
            ['key' => 'mailer', 'label' => 'Mailer Provider', 'type' => 'select', 'options' => ['smtp' => 'SMTP', 'brevo' => 'Brevo API', 'zeptomail' => 'ZeptoMail API', 'sendmail' => 'Sendmail', 'log' => 'Log only'], 'default' => 'smtp'],
            ['key' => 'smtp_host', 'label' => 'SMTP Host', 'type' => 'text', 'default' => ''],
            ['key' => 'smtp_port', 'label' => 'SMTP Port', 'type' => 'number', 'default' => '587'],
            ['key' => 'smtp_encryption', 'label' => 'SMTP Encryption', 'type' => 'select', 'options' => ['tls' => 'TLS / STARTTLS', 'ssl' => 'SSL', 'none' => 'None'], 'default' => 'tls'],
            ['key' => 'smtp_username', 'label' => 'SMTP Username', 'type' => 'text', 'default' => ''],
            ['key' => 'smtp_password', 'label' => 'SMTP Password', 'type' => 'password', 'secret' => true, 'default' => ''],
            ['key' => 'brevo_api_key', 'label' => 'Brevo API Key', 'type' => 'password', 'secret' => true, 'default' => ''],
            ['key' => 'brevo_endpoint', 'label' => 'Brevo API Endpoint', 'type' => 'url', 'default' => 'https://api.brevo.com/v3/smtp/email'],
            ['key' => 'zeptomail_api_key', 'label' => 'ZeptoMail API Key', 'type' => 'password', 'secret' => true, 'default' => ''],
            ['key' => 'zeptomail_endpoint', 'label' => 'ZeptoMail API Endpoint', 'type' => 'url', 'default' => 'https://api.zeptomail.com/v1.1/email'],
            ['key' => 'reply_to_email', 'label' => 'Reply-To Email', 'type' => 'email', 'default' => ''],
        ],
    ],
    'security-settings' => [
        'summary' => 'Session, authentication and administrative protection controls.',
        'group' => 'security',
        'fields' => [
            ['key' => 'session_timeout_minutes', 'label' => 'Session Timeout Minutes', 'type' => 'number', 'default' => '43200'],
            ['key' => 'login_rate_limit', 'label' => 'Login Rate Limit / 15 min', 'type' => 'number', 'default' => '8'],
            ['key' => 'password_min_length', 'label' => 'Minimum Password Length', 'type' => 'number', 'default' => '5'],
            ['key' => 'audit_sensitive_actions', 'label' => 'Audit Sensitive Actions', 'type' => 'select', 'options' => ['on' => 'On', 'off' => 'Off'], 'default' => 'on'],
        ],
    ],
];

function dashboard_setting_values(string $group, array $fields): array {
    $values = [];
    foreach ($fields as $field) {
        $values[$field['key']] = (string)($field['default'] ?? '');
    }

    if (!table_exists('settings')) {
        return $values;
    }

    $rows = db()->fetchAll("SELECT setting_key, setting_value FROM settings WHERE scope = ? AND scope_id = 0", [$group]);
    foreach ($rows as $row) {
        $decoded = json_decode((string)($row['setting_value'] ?? ''), true);
        $values[(string)$row['setting_key']] = is_scalar($decoded) ? (string)$decoded : (string)($row['setting_value'] ?? '');
    }

    return $values;
}

function dashboard_uploaded_setting_file(string $key, array $field, string $currentValue): string {
    $upload = $_FILES['settings_file'] ?? null;
    $error = is_array($upload['error'] ?? null) ? (int)($upload['error'][$key] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;

    if ($error === UPLOAD_ERR_NO_FILE) {
        return $currentValue;
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new \RuntimeException('Image upload failed. Please choose a valid image and try again.');
    }

    $tmpName = (string)($upload['tmp_name'][$key] ?? '');
    $size = (int)($upload['size'][$key] ?? 0);
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new \RuntimeException('Uploaded image could not be read.');
    }

    if ($size <= 0 || $size > 3 * 1024 * 1024) {
        throw new \RuntimeException('Image must be smaller than 3MB.');
    }

    $allowed = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)finfo_file($finfo, $tmpName);
            finfo_close($finfo);
        }
    }

    if ($mime === '' && function_exists('mime_content_type')) {
        $mime = (string)mime_content_type($tmpName);
    }

    if (!isset($allowed[$mime])) {
        throw new \RuntimeException('Image must be a PNG, JPG, or WebP file.');
    }

    $uploadDir = APP_ROOT . '/public/uploads/settings';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new \RuntimeException('Image upload directory could not be created.');
    }

    @chmod(APP_ROOT . '/public/uploads', 0777);
    @chmod($uploadDir, 0777);

    if (!is_writable($uploadDir)) {
        throw new \RuntimeException('Image upload directory is not writable: public/uploads/settings');
    }

    $prefix = preg_replace('/[^a-z0-9_-]+/i', '-', $key) ?: 'setting-image';
    $filename = strtolower($prefix) . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmpName, $target)) {
        throw new \RuntimeException('Image could not be saved.');
    }

    return 'uploads/settings/' . $filename;
}

function dashboard_save_settings(string $group, array $fields): void {
    validateCsrf();
    if (!table_exists('settings')) {
        flash('error', 'Settings table is not available.');
        return;
    }

    $currentValues = dashboard_setting_values($group, $fields);

    foreach ($fields as $field) {
        $key = (string)$field['key'];
        if (($field['type'] ?? '') === 'file') {
            $value = dashboard_uploaded_setting_file($key, $field, (string)($currentValues[$key] ?? ''));
        } elseif (!empty($field['secret']) && trim((string)($_POST['settings'][$key] ?? '')) === '') {
            $value = (string)($currentValues[$key] ?? '');
        } else {
            $value = trim((string)($_POST['settings'][$key] ?? ''));
        }

        db()->query(
            "INSERT INTO settings (scope, scope_id, setting_key, setting_value)
             VALUES (?, 0, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP",
            [$group, $key, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
        );

        if ($group === 'general' && $key === 'maintenance_mode' && $value === 'on') {
            db()->query(
                "INSERT INTO settings (scope, scope_id, setting_key, setting_value)
                 VALUES ('general', 0, 'maintenance_started_at', ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP",
                [json_encode(sql_now())]
            );
        }
    }

    audit('settings_updated', 'settings', $group, [], ['group' => $group, 'keys' => array_column($fields, 'key')]);
    flash('success', 'Settings saved.');
}

function dashboard_marketing_pixel_fields(): array {
    return [
        ['key' => 'pixels_enabled', 'label' => 'Enable Pixels', 'type' => 'select', 'options' => ['off' => 'Off', 'on' => 'On'], 'default' => 'off'],
        ['key' => 'google_head_code', 'label' => 'Google Pixel / Tag Head Code', 'type' => 'textarea', 'default' => ''],
        ['key' => 'google_body_code', 'label' => 'Google Body Code', 'type' => 'textarea', 'default' => ''],
        ['key' => 'facebook_head_code', 'label' => 'Facebook Pixel Head Code', 'type' => 'textarea', 'default' => ''],
        ['key' => 'facebook_body_code', 'label' => 'Facebook Noscript / Body Code', 'type' => 'textarea', 'default' => ''],
    ];
}

function dashboard_save_payment_gateway(): void {
    validateCsrf();
    if (!table_exists('payment_methods')) {
        throw new \RuntimeException('Payment methods table is not available.');
    }

    $gatewayId = max(0, (int)($_POST['gateway_id'] ?? 0));
    $gateway = db()->fetch('SELECT * FROM payment_methods WHERE id = ? LIMIT 1', [$gatewayId]);
    if (!$gateway) {
        throw new \RuntimeException('Payment gateway was not found.');
    }

    $code = (string)$gateway['code'];
    $name = trim((string)($_POST['name'] ?? $gateway['name']));
    $isActive = (int)($_POST['is_active'] ?? 0) === 1 ? 1 : 0;
    $settings = json_decode((string)($gateway['settings'] ?? ''), true);
    if (!is_array($settings)) {
        $settings = match ($code) {
            'klasha' => PaymentService::defaultKlashaSettings(),
            'paystack' => PaymentService::defaultPaystackSettings(),
            'stripe' => PaymentService::defaultStripeSettings(),
            default => [],
        };
    }

    if ($code === 'klasha') {
        $settings = array_merge(PaymentService::defaultKlashaSettings(), $settings, [
            'mode' => in_array((string)($_POST['mode'] ?? 'test'), ['test', 'live'], true) ? (string)$_POST['mode'] : 'test',
            'public_key' => trim((string)($_POST['public_key'] ?? '')),
            'business_id' => trim((string)($_POST['business_id'] ?? '')),
            'destination_currency' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($_POST['destination_currency'] ?? 'NGN')) ?: 'NGN', 0, 3)),
            'status_endpoint' => trim((string)($_POST['status_endpoint'] ?? '')) ?: (string)PaymentService::defaultKlashaSettings()['status_endpoint'],
        ]);

        foreach (['secret_key', 'webhook_secret'] as $secretKey) {
            $submitted = trim((string)($_POST[$secretKey] ?? ''));
            if ($submitted !== '') {
                $settings[$secretKey] = $submitted;
            }
        }

        if ($isActive === 1 && !PaymentService::isKlashaConfigured(['settings' => json_encode($settings)])) {
            throw new \RuntimeException('Enter Klasha public key and business ID before activating Klasha.');
        }
    } elseif ($code === 'stripe') {
        $settings = array_merge(PaymentService::defaultStripeSettings(), $settings, [
            'mode' => in_array((string)($_POST['mode'] ?? 'live'), ['test', 'live'], true) ? (string)$_POST['mode'] : 'live',
            'public_key' => trim((string)($_POST['public_key'] ?? '')),
        ]);

        foreach (['secret_key', 'webhook_secret'] as $secretKey) {
            $submitted = trim((string)($_POST[$secretKey] ?? ''));
            if ($submitted !== '') {
                $settings[$secretKey] = $submitted;
            }
        }

        if ($isActive === 1 && !PaymentService::isStripeConfigured(['settings' => json_encode($settings)])) {
            throw new \RuntimeException('Enter a Stripe secret key before activating Stripe.');
        }
    } elseif ($code === 'paystack') {
        $settings = array_merge(PaymentService::defaultPaystackSettings(), $settings, [
            'mode' => in_array((string)($_POST['mode'] ?? 'test'), ['test', 'live'], true) ? (string)$_POST['mode'] : 'test',
            'public_key' => trim((string)($_POST['public_key'] ?? '')),
            'charge_currency' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($_POST['charge_currency'] ?? 'NGN')) ?: 'NGN', 0, 3)),
            'initialize_endpoint' => trim((string)($_POST['initialize_endpoint'] ?? '')) ?: (string)PaymentService::defaultPaystackSettings()['initialize_endpoint'],
            'verify_endpoint' => trim((string)($_POST['verify_endpoint'] ?? '')) ?: (string)PaymentService::defaultPaystackSettings()['verify_endpoint'],
        ]);

        foreach (['secret_key', 'webhook_secret'] as $secretKey) {
            $submitted = trim((string)($_POST[$secretKey] ?? ''));
            if ($submitted !== '') {
                $settings[$secretKey] = $submitted;
            }
        }

        if ($isActive === 1 && !PaymentService::isPaystackConfigured(['settings' => json_encode($settings)])) {
            throw new \RuntimeException('Enter a Paystack secret key before activating Paystack.');
        }
    }

    db()->query(
        "UPDATE payment_methods
         SET name = ?, is_active = ?, settings = ?
         WHERE id = ?",
        [
            $name !== '' ? $name : (string)$gateway['name'],
            $isActive,
            json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $gatewayId,
        ]
    );

    audit('payment_gateway_updated', 'payment_methods', (string)$gatewayId, $gateway, ['code' => $code, 'is_active' => $isActive]);
    flash('success', 'Payment gateway settings saved.');
}

function dashboard_column_exists(string $table, string $column): bool {
    try {
        $config = db_config();
        $row = db()->fetch(
            "SELECT COUNT(*) AS total
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [(string)($config['database'] ?? ''), $table, $column]
        );

        return (int)($row['total'] ?? 0) > 0;
    } catch (\Throwable) {
        return false;
    }
}

function dashboard_ensure_vendor_origin_schema(): void {
    try {
        if (!table_exists('vendors')) {
            return;
        }
        if (!dashboard_column_exists('vendors', 'origin_region')) {
            db()->pdo()->exec('ALTER TABLE vendors ADD origin_region VARCHAR(80) NULL AFTER description');
        }
        if (!dashboard_column_exists('vendors', 'country_of_origin')) {
            db()->pdo()->exec('ALTER TABLE vendors ADD country_of_origin VARCHAR(120) NULL AFTER origin_region');
        }
    } catch (\Throwable $e) {
        error_log('Vendor origin schema setup failed: ' . $e->getMessage());
    }
}

function dashboard_ensure_vendor_application_schema(): void {
    try {
        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS vendor_applications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                vendor_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                application_type VARCHAR(40) NOT NULL DEFAULT 'vendor',
                business_name VARCHAR(190) NOT NULL,
                owner_name VARCHAR(190) NOT NULL,
                product_category VARCHAR(120) NOT NULL,
                application_data LONGTEXT NOT NULL,
                submitted_at DATETIME NOT NULL,
                reviewed_at DATETIME NULL,
                reviewed_by BIGINT UNSIGNED NULL,
                INDEX idx_vendor_applications_vendor (vendor_id),
                INDEX idx_vendor_applications_user (user_id),
                INDEX idx_vendor_applications_type (application_type),
                INDEX idx_vendor_applications_submitted (submitted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        if (!dashboard_column_exists('vendor_applications', 'application_type')) {
            db()->pdo()->exec("ALTER TABLE vendor_applications ADD application_type VARCHAR(40) NOT NULL DEFAULT 'vendor' AFTER user_id");
        }
    } catch (\Throwable $e) {
        error_log('Vendor application schema setup failed: ' . $e->getMessage());
    }
}

function dashboard_ensure_marketing_schema(): void {
    try {
        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS marketing_popups (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(190) NOT NULL,
                placement VARCHAR(80) NOT NULL DEFAULT 'homepage',
                headline VARCHAR(190) NULL,
                body TEXT NULL,
                cta_label VARCHAR(80) NULL,
                cta_url VARCHAR(500) NULL,
                image_url VARCHAR(500) NULL,
                starts_at DATETIME NULL,
                ends_at DATETIME NULL,
                status ENUM('draft', 'active', 'paused', 'expired') NOT NULL DEFAULT 'draft',
                impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
                clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_marketing_popups_status_dates (status, starts_at, ends_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS marketing_sliders (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(190) NOT NULL,
                headline VARCHAR(190) NULL,
                subheadline VARCHAR(255) NULL,
                image_url VARCHAR(500) NULL,
                image_url_mobile VARCHAR(500) NULL,
                cta_label VARCHAR(80) NULL,
                cta_url VARCHAR(500) NULL,
                sort_order INT NOT NULL DEFAULT 0,
                starts_at DATETIME NULL,
                ends_at DATETIME NULL,
                status ENUM('draft', 'active', 'paused', 'expired') NOT NULL DEFAULT 'draft',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_marketing_sliders_status_order (status, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        if (!dashboard_column_exists('marketing_sliders', 'image_url_mobile')) {
            db()->pdo()->exec('ALTER TABLE marketing_sliders ADD image_url_mobile VARCHAR(500) NULL AFTER image_url');
        }

        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS marketing_banners (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(190) NOT NULL,
                placement VARCHAR(80) NOT NULL DEFAULT 'homepage',
                image_url VARCHAR(500) NULL,
                target_url VARCHAR(500) NULL,
                starts_at DATETIME NULL,
                ends_at DATETIME NULL,
                status ENUM('draft', 'active', 'paused', 'expired') NOT NULL DEFAULT 'draft',
                impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
                clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_marketing_banners_status_placement (status, placement)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS marketing_announcements (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(190) NOT NULL,
                message TEXT NOT NULL,
                audience ENUM('all', 'buyers', 'vendors', 'affiliates') NOT NULL DEFAULT 'all',
                channel ENUM('storefront', 'dashboard', 'email', 'all') NOT NULL DEFAULT 'storefront',
                starts_at DATETIME NULL,
                ends_at DATETIME NULL,
                status ENUM('draft', 'active', 'paused', 'expired') NOT NULL DEFAULT 'draft',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_marketing_announcements_status_audience (status, audience)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS marketing_email_campaigns (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(190) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                audience ENUM('buyers', 'vendors', 'affiliates', 'all') NOT NULL DEFAULT 'buyers',
                body_html LONGTEXT NULL,
                image_url VARCHAR(500) NULL,
                status ENUM('draft', 'scheduled', 'sending', 'sent', 'paused', 'cancelled') NOT NULL DEFAULT 'draft',
                scheduled_at DATETIME NULL,
                sent_at DATETIME NULL,
                total_recipients INT UNSIGNED NOT NULL DEFAULT 0,
                opened_count INT UNSIGNED NOT NULL DEFAULT 0,
                clicked_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_marketing_email_campaigns_status_schedule (status, scheduled_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        foreach ([
            'body_html' => 'ALTER TABLE marketing_email_campaigns ADD body_html LONGTEXT NULL AFTER audience',
            'image_url' => 'ALTER TABLE marketing_email_campaigns ADD image_url VARCHAR(500) NULL AFTER body_html',
        ] as $column => $sql) {
            if (!dashboard_column_exists('marketing_email_campaigns', $column)) {
                db()->pdo()->exec($sql);
            }
        }

        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS marketing_abandoned_cart_emails (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(190) NOT NULL,
                delay_minutes INT UNSIGNED NOT NULL DEFAULT 60,
                subject VARCHAR(255) NOT NULL,
                coupon_id BIGINT UNSIGNED NULL,
                status ENUM('draft', 'active', 'paused') NOT NULL DEFAULT 'draft',
                sent_count INT UNSIGNED NOT NULL DEFAULT 0,
                recovered_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_abandoned_cart_email_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL,
                INDEX idx_abandoned_cart_email_status_delay (status, delay_minutes)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\Throwable $e) {
        error_log('Marketing schema setup failed: ' . $e->getMessage());
    }
}

function dashboard_marketing_datetime(?string $value): ?string {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    return str_replace('T', ' ', $value) . (strlen($value) === 16 ? ':00' : '');
}

function dashboard_uploaded_marketing_image(string $key): string {
    $upload = $_FILES[$key] ?? null;
    if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ((int)($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new \RuntimeException('Campaign image upload failed.');
    }

    $tmpName = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new \RuntimeException('Campaign image could not be read.');
    }

    if ($size <= 0 || $size > 4 * 1024 * 1024) {
        throw new \RuntimeException('Campaign image must be smaller than 4MB.');
    }

    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    $mime = function_exists('mime_content_type') ? (string)mime_content_type($tmpName) : '';
    if (!isset($allowed[$mime])) {
        throw new \RuntimeException('Campaign image must be a PNG, JPG, or WebP file.');
    }

    $uploadDir = APP_ROOT . '/public/uploads/marketing';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new \RuntimeException('Campaign upload directory could not be created.');
    }
    @chmod(APP_ROOT . '/public/uploads', 0777);
    @chmod($uploadDir, 0777);
    if (!is_writable($uploadDir)) {
        throw new \RuntimeException('Campaign upload directory is not writable.');
    }

    $filename = 'campaign-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmpName, $uploadDir . '/' . $filename)) {
        throw new \RuntimeException('Campaign image could not be saved.');
    }

    return 'uploads/marketing/' . $filename;
}

function dashboard_marketing_recipients(string $audience): array {
    $audience = in_array($audience, ['buyers', 'vendors', 'affiliates', 'all'], true) ? $audience : 'buyers';

    if ($audience === 'vendors') {
        return db()->fetchAll(
            "SELECT DISTINCT COALESCE(NULLIF(v.store_email, ''), u.email) AS email,
                    COALESCE(NULLIF(v.store_name, ''), NULLIF(u.display_name, ''), u.username, u.email) AS name
             FROM vendors v
             INNER JOIN users u ON u.id = v.user_id
             WHERE u.status = 'active' AND COALESCE(NULLIF(v.store_email, ''), u.email) IS NOT NULL
             ORDER BY name ASC"
        );
    }

    if ($audience === 'affiliates') {
        return db()->fetchAll(
            "SELECT DISTINCT u.email, COALESCE(NULLIF(u.display_name, ''), u.username, u.email) AS name
             FROM affiliates a
             INNER JOIN users u ON u.id = a.user_id
             WHERE u.status = 'active' AND u.email IS NOT NULL
             ORDER BY name ASC"
        );
    }

    if ($audience === 'buyers') {
        return db()->fetchAll(
            "SELECT DISTINCT u.email, COALESCE(NULLIF(u.display_name, ''), u.username, u.email) AS name
             FROM users u
             WHERE u.status = 'active'
               AND u.email IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM vendors v WHERE v.user_id = u.id)
               AND NOT EXISTS (SELECT 1 FROM affiliates a WHERE a.user_id = u.id)
               AND NOT EXISTS (
                    SELECT 1
                    FROM user_roles ur
                    INNER JOIN roles r ON r.id = ur.role_id
                    WHERE ur.user_id = u.id AND r.code IN ('super_admin', 'admin', 'support', 'staff')
               )
             ORDER BY name ASC"
        );
    }

    return db()->fetchAll(
        "SELECT DISTINCT u.email, COALESCE(NULLIF(u.display_name, ''), u.username, u.email) AS name
         FROM users u
         WHERE u.status = 'active' AND u.email IS NOT NULL
         ORDER BY name ASC"
    );
}

function dashboard_queue_marketing_campaign(): void {
    validateCsrf();
    \App\NotificationService::ensureSchema();

    $name = trim((string)($_POST['name'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $audience = (string)($_POST['audience'] ?? 'buyers');
    $body = trim((string)($_POST['body_html'] ?? ''));
    $scheduledAt = dashboard_marketing_datetime($_POST['scheduled_at'] ?? null);
    $paceSeconds = max(10, min(3600, (int)($_POST['pace_seconds'] ?? 60)));
    $status = $scheduledAt !== null && strtotime($scheduledAt) !== false && strtotime($scheduledAt) > time() ? 'scheduled' : 'sending';

    if ($name === '' || $subject === '' || $body === '') {
        throw new \RuntimeException('Campaign name, subject and message are required.');
    }
    if (!in_array($audience, ['buyers', 'vendors', 'affiliates', 'all'], true)) {
        throw new \RuntimeException('Invalid campaign audience.');
    }

    $imageUrl = dashboard_uploaded_marketing_image('campaign_image');
    $recipients = array_values(array_filter(
        dashboard_marketing_recipients($audience),
        static fn (array $row): bool => filter_var((string)($row['email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false
    ));
    if ($recipients === []) {
        throw new \RuntimeException('No active recipients found for this audience.');
    }

    db()->query(
        "INSERT INTO marketing_email_campaigns (name, subject, audience, body_html, image_url, status, scheduled_at, total_recipients)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$name, $subject, $audience, $body, $imageUrl !== '' ? $imageUrl : null, $status, $scheduledAt, count($recipients)]
    );
    $campaignId = (int)db()->lastInsertId();

    $brand = app_branding();
    $imageHtml = $imageUrl !== ''
        ? '<p><img src="' . e(app_brand_asset_url($imageUrl)) . '" alt="" style="max-width:100%;border-radius:12px;display:block;margin:0 0 18px;"></p>'
        : '';
    $startTime = $scheduledAt !== null && strtotime($scheduledAt) !== false ? strtotime($scheduledAt) : time();

    foreach ($recipients as $index => $recipient) {
        $availableAt = date('Y-m-d H:i:s', $startTime + ($index * $paceSeconds));
        \App\NotificationService::enqueueEmail(
            [['email' => (string)$recipient['email'], 'name' => (string)($recipient['name'] ?? '')]],
            $subject,
            $name,
            $imageHtml . $body . '<p style="font-size:12px;color:#64748b;margin-top:24px">You are receiving this message from ' . e((string)$brand['name']) . '.</p>',
            [
                'available_at' => $availableAt,
                'metadata' => ['type' => 'marketing_campaign', 'campaign_id' => $campaignId, 'audience' => $audience],
            ]
        );
    }

    audit('marketing_campaign_queued', 'marketing_email_campaigns', (string)$campaignId, [], ['audience' => $audience, 'recipients' => count($recipients), 'pace_seconds' => $paceSeconds]);
    flash('success', 'Campaign queued for ' . number_format(count($recipients)) . ' recipient(s).');
    redirect('dashboard/email-campaigns');
}

function dashboard_save_marketing_slider(): void {
    validateCsrf();
    $sliderId = max(0, (int)($_POST['slider_id'] ?? 0));
    $old = $sliderId > 0 ? db()->fetch('SELECT * FROM marketing_sliders WHERE id = ? LIMIT 1', [$sliderId]) : null;
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
        throw new \RuntimeException('Slider title is required.');
    }
    $status = in_array(($_POST['status'] ?? 'active'), ['draft', 'active', 'paused', 'expired'], true) ? (string)$_POST['status'] : 'draft';
    $desktopImage = dashboard_uploaded_marketing_image('slider_image');
    $mobileImage = dashboard_uploaded_marketing_image('slider_mobile_image');
    $params = [
        $title,
        trim((string)($_POST['headline'] ?? '')) ?: null,
        trim((string)($_POST['subheadline'] ?? '')) ?: null,
        $desktopImage !== '' ? $desktopImage : (($old['image_url'] ?? '') ?: null),
        $mobileImage !== '' ? $mobileImage : (($old['image_url_mobile'] ?? '') ?: null),
        trim((string)($_POST['cta_label'] ?? '')) ?: null,
        trim((string)($_POST['cta_url'] ?? '')) ?: null,
        (int)($_POST['sort_order'] ?? 0),
        dashboard_marketing_datetime($_POST['starts_at'] ?? null),
        dashboard_marketing_datetime($_POST['ends_at'] ?? null),
        $status,
    ];
    if ($sliderId > 0 && $old) {
        db()->query('UPDATE marketing_sliders SET title = ?, headline = ?, subheadline = ?, image_url = ?, image_url_mobile = ?, cta_label = ?, cta_url = ?, sort_order = ?, starts_at = ?, ends_at = ?, status = ? WHERE id = ?', [...$params, $sliderId]);
    } else {
        db()->query('INSERT INTO marketing_sliders (title, headline, subheadline, image_url, image_url_mobile, cta_label, cta_url, sort_order, starts_at, ends_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $params);
        $sliderId = (int)db()->lastInsertId();
    }
    audit($old ? 'marketing_slider_updated' : 'marketing_slider_created', 'marketing_sliders', (string)$sliderId, $old ?: [], ['title' => $title, 'status' => $status]);
    flash('success', 'Slider saved.');
    redirect('dashboard/sliders?edit=' . $sliderId);
}

function dashboard_save_marketing_banner(): void {
    validateCsrf();
    $bannerId = max(0, (int)($_POST['banner_id'] ?? 0));
    $old = $bannerId > 0 ? db()->fetch('SELECT * FROM marketing_banners WHERE id = ? LIMIT 1', [$bannerId]) : null;
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
        throw new \RuntimeException('Banner title is required.');
    }
    $status = in_array(($_POST['status'] ?? 'active'), ['draft', 'active', 'paused', 'expired'], true) ? (string)$_POST['status'] : 'draft';
    $placement = in_array(($_POST['placement'] ?? 'homepage_section_1'), ['homepage', 'homepage_section_1', 'homepage_section_2'], true) ? (string)$_POST['placement'] : 'homepage';
    $image = dashboard_uploaded_marketing_image('banner_image');
    $params = [
        $title,
        $placement,
        $image !== '' ? $image : (($old['image_url'] ?? '') ?: null),
        trim((string)($_POST['target_url'] ?? '')) ?: null,
        dashboard_marketing_datetime($_POST['starts_at'] ?? null),
        dashboard_marketing_datetime($_POST['ends_at'] ?? null),
        $status,
    ];
    if ($bannerId > 0 && $old) {
        db()->query('UPDATE marketing_banners SET title = ?, placement = ?, image_url = ?, target_url = ?, starts_at = ?, ends_at = ?, status = ? WHERE id = ?', [...$params, $bannerId]);
    } else {
        db()->query('INSERT INTO marketing_banners (title, placement, image_url, target_url, starts_at, ends_at, status) VALUES (?, ?, ?, ?, ?, ?, ?)', $params);
        $bannerId = (int)db()->lastInsertId();
    }
    audit($old ? 'marketing_banner_updated' : 'marketing_banner_created', 'marketing_banners', (string)$bannerId, $old ?: [], ['title' => $title, 'placement' => $placement, 'status' => $status]);
    flash('success', 'Banner saved.');
    redirect('dashboard/ads-banners?edit=' . $bannerId);
}

function dashboard_save_coupon(): void {
    validateCsrf();
    if (!table_exists('coupons')) {
        throw new \RuntimeException('Coupons table is not available.');
    }

    $couponId = max(0, (int)($_POST['coupon_id'] ?? 0));
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    if ($code === '') {
        throw new \RuntimeException('Coupon code is required.');
    }

    if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,99}$/', $code)) {
        throw new \RuntimeException('Coupon code must be 3-100 characters using letters, numbers, dash or underscore.');
    }

    $discountType = (string)($_POST['discount_type'] ?? 'percentage');
    if (!in_array($discountType, ['percentage', 'fixed_cart', 'fixed_product', 'free_shipping'], true)) {
        throw new \RuntimeException('Invalid discount type.');
    }

    $amount = $discountType === 'free_shipping' ? 0.0 : max(0, (float)($_POST['amount'] ?? 0));
    if ($discountType === 'percentage' && ($amount <= 0 || $amount > 100)) {
        throw new \RuntimeException('Percentage coupons must be between 0.01 and 100.');
    }
    if (in_array($discountType, ['fixed_cart', 'fixed_product'], true) && $amount <= 0) {
        throw new \RuntimeException('Fixed discount coupons must have an amount greater than zero.');
    }

    $status = (string)($_POST['status'] ?? 'active');
    if (!in_array($status, ['active', 'inactive', 'expired'], true)) {
        $status = 'inactive';
    }

    $existing = db()->fetch('SELECT id FROM coupons WHERE code = ? AND id <> ? LIMIT 1', [$code, $couponId]);
    if ($existing) {
        throw new \RuntimeException('A coupon with this code already exists.');
    }

    $old = $couponId > 0 ? db()->fetch('SELECT * FROM coupons WHERE id = ? LIMIT 1', [$couponId]) : null;
    $params = [
        (int)($_POST['vendor_id'] ?? 0) ?: null,
        $code,
        trim((string)($_POST['description'] ?? '')) ?: null,
        $discountType,
        $amount,
        trim((string)($_POST['minimum_amount'] ?? '')) === '' ? null : max(0, (float)$_POST['minimum_amount']),
        trim((string)($_POST['maximum_discount'] ?? '')) === '' ? null : max(0, (float)$_POST['maximum_discount']),
        trim((string)($_POST['usage_limit'] ?? '')) === '' ? null : max(1, (int)$_POST['usage_limit']),
        trim((string)($_POST['usage_limit_per_user'] ?? '')) === '' ? null : max(1, (int)$_POST['usage_limit_per_user']),
        dashboard_marketing_datetime($_POST['starts_at'] ?? null),
        dashboard_marketing_datetime($_POST['expires_at'] ?? null),
        $status,
    ];

    if ($couponId > 0 && $old) {
        db()->query(
            'UPDATE coupons
             SET vendor_id = ?, code = ?, description = ?, discount_type = ?, amount = ?, minimum_amount = ?,
                 maximum_discount = ?, usage_limit = ?, usage_limit_per_user = ?, starts_at = ?, expires_at = ?, status = ?
             WHERE id = ?',
            [...$params, $couponId]
        );
    } else {
        db()->query(
            'INSERT INTO coupons
                (vendor_id, code, description, discount_type, amount, minimum_amount, maximum_discount,
                 usage_limit, usage_limit_per_user, starts_at, expires_at, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $params
        );
        $couponId = (int)db()->lastInsertId();
    }

    audit($old ? 'coupon_updated' : 'coupon_created', 'coupons', (string)$couponId, $old ?: [], ['code' => $code, 'status' => $status, 'discount_type' => $discountType]);
    flash('success', 'Coupon saved.');
    redirect('dashboard/coupons?edit=' . $couponId);
}

function dashboard_update_coupon_status(): void {
    validateCsrf();
    $couponId = max(0, (int)($_POST['coupon_id'] ?? 0));
    $status = (string)($_POST['status'] ?? 'inactive');
    if ($couponId <= 0 || !in_array($status, ['active', 'inactive', 'expired'], true)) {
        throw new \RuntimeException('Invalid coupon status update.');
    }

    $old = db()->fetch('SELECT * FROM coupons WHERE id = ? LIMIT 1', [$couponId]);
    if (!$old) {
        throw new \RuntimeException('Coupon was not found.');
    }

    db()->query('UPDATE coupons SET status = ? WHERE id = ?', [$status, $couponId]);
    audit('coupon_status_updated', 'coupons', (string)$couponId, $old, ['status' => $status]);
    flash('success', 'Coupon status updated.');
    redirect('dashboard/coupons');
}

function dashboard_delete_coupon(): void {
    validateCsrf();
    $couponId = max(0, (int)($_POST['coupon_id'] ?? 0));
    if ($couponId <= 0) {
        throw new \RuntimeException('Invalid coupon.');
    }

    $old = db()->fetch('SELECT * FROM coupons WHERE id = ? LIMIT 1', [$couponId]);
    if (!$old) {
        throw new \RuntimeException('Coupon was not found.');
    }

    $redemptions = (int)(db()->fetch('SELECT COUNT(*) c FROM coupon_redemptions WHERE coupon_id = ?', [$couponId])['c'] ?? 0);
    if ($redemptions > 0) {
        db()->query("UPDATE coupons SET status = 'inactive' WHERE id = ?", [$couponId]);
        audit('coupon_deactivated_with_redemptions', 'coupons', (string)$couponId, $old, ['redemptions' => $redemptions]);
        flash('warning', 'Coupon has redemptions, so it was deactivated instead of deleted.');
    } else {
        db()->query('DELETE FROM coupons WHERE id = ?', [$couponId]);
        audit('coupon_deleted', 'coupons', (string)$couponId, $old, []);
        flash('success', 'Coupon deleted.');
    }

    redirect('dashboard/coupons');
}

dashboard_ensure_vendor_origin_schema();
dashboard_ensure_vendor_application_schema();
dashboard_ensure_marketing_schema();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'disable_maintenance') {
    try {
        validateCsrf();
        \App\MaintenanceService::disable();
        audit('maintenance_disabled', 'settings', 'general');
        flash('success', 'Maintenance mode disabled.');
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
    }

    redirect('dashboard/' . $view);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'save_product') {
    try {
        dashboard_save_product();
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
        $returnVendorId = max(0, (int)($_POST['return_vendor_id'] ?? 0));
        if ($returnVendorId > 0) {
            redirect('dashboard/vendor-edit?vendor=' . $returnVendorId . ((int)($_POST['product_id'] ?? 0) > 0 ? '&edit_product=' . (int)$_POST['product_id'] : ''));
        }
        redirect('dashboard/products' . ((int)($_POST['product_id'] ?? 0) > 0 ? '?edit=' . (int)$_POST['product_id'] : '?new=1'));
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'update_product_status') {
    $returnView = preg_replace('/[^a-z0-9-]/', '', (string)($_POST['return_view'] ?? 'pending-products')) ?: 'pending-products';
    try {
        dashboard_update_product_status();
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
        redirect('dashboard/' . $returnView);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'delete_product_review') {
    try {
        dashboard_delete_product_review();
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
        redirect('dashboard/product-reviews');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'save_taxonomy') {
    $targetView = match ((string)($_POST['taxonomy_type'] ?? 'category')) {
        'subcategory' => 'subcategories',
        'brand' => 'brands',
        'attribute' => 'attributes',
        default => 'categories',
    };

    try {
        dashboard_save_taxonomy();
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
        $recordId = max(0, (int)($_POST['record_id'] ?? 0));
        redirect('dashboard/' . $targetView . ($recordId > 0 ? '?edit=' . $recordId : '?new=1'));
    }
}

$marketingActionTargets = [
    'save_coupon' => 'coupons',
    'update_coupon_status' => 'coupons',
    'delete_coupon' => 'coupons',
    'queue_marketing_campaign' => 'email-campaigns',
    'save_marketing_slider' => 'sliders',
    'save_marketing_banner' => 'ads-banners',
    'save_marketing_pixels' => 'marketing-pixels',
];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($marketingActionTargets[(string)($_POST['action'] ?? '')])) {
    $action = (string)$_POST['action'];
    try {
        match ($action) {
            'save_coupon' => dashboard_save_coupon(),
            'update_coupon_status' => dashboard_update_coupon_status(),
            'delete_coupon' => dashboard_delete_coupon(),
            'queue_marketing_campaign' => dashboard_queue_marketing_campaign(),
            'save_marketing_slider' => dashboard_save_marketing_slider(),
            'save_marketing_banner' => dashboard_save_marketing_banner(),
            'save_marketing_pixels' => dashboard_save_settings('marketing_pixels', dashboard_marketing_pixel_fields()),
        };
        redirect('dashboard/' . $marketingActionTargets[$action]);
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
        $recordId = max(0, (int)($_POST['coupon_id'] ?? $_POST['slider_id'] ?? $_POST['banner_id'] ?? 0));
        redirect('dashboard/' . $marketingActionTargets[$action] . ($recordId > 0 ? '?edit=' . $recordId : '?new=1'));
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'update_withdrawal_status') {
    $returnView = preg_replace('/[^a-z0-9-]/', '', (string)($_POST['return_view'] ?? 'pending-withdrawals')) ?: 'pending-withdrawals';
    try {
        dashboard_update_withdrawal_status();
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
        redirect('dashboard/' . $returnView);
    }
}

function dashboard_wants_json(): bool {
    return str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function dashboard_json(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$vendorAdminActionTargets = [
    'update_vendor_approval' => 'pending-vendors',
    'save_vendor_admin' => 'vendor-edit',
    'save_vendor_kyc_document_admin' => 'vendor-edit',
    'save_vendor_subscription_admin' => 'vendor-edit',
    'update_vendor_product_status_admin' => 'vendor-edit',
    'delete_vendor_product_admin' => 'vendor-edit',
    'update_product_media_admin' => 'vendor-edit',
    'delete_vendor_file_admin' => 'vendor-edit',
];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($vendorAdminActionTargets[(string)($_POST['action'] ?? '')])) {
    $action = (string)$_POST['action'];
    $vendorId = max(0, (int)($_POST['vendor_id'] ?? 0));
    $fallback = $vendorAdminActionTargets[$action] . ($vendorId > 0 && $vendorAdminActionTargets[$action] === 'vendor-edit' ? '?vendor=' . $vendorId : '');
    try {
        match ($action) {
            'update_vendor_approval' => dashboard_update_vendor_approval(),
            'save_vendor_admin' => dashboard_save_vendor_admin(),
            'save_vendor_kyc_document_admin' => dashboard_save_vendor_kyc_document_admin(),
            'save_vendor_subscription_admin' => dashboard_save_vendor_subscription_admin(),
            'update_vendor_product_status_admin' => dashboard_update_vendor_product_status_admin(),
            'delete_vendor_product_admin' => dashboard_delete_vendor_product_admin(),
            'update_product_media_admin' => dashboard_update_product_media_admin(),
            'delete_vendor_file_admin' => dashboard_delete_vendor_file_admin(),
        };
    } catch (\Throwable $e) {
        error_log('Dashboard content action failed: action=' . $action . ' view=' . $view . ' uri=' . (string)($_SERVER['REQUEST_URI'] ?? '') . ' message=' . $e->getMessage());
        if (dashboard_wants_json()) {
            dashboard_json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
        flash('error', $e->getMessage());
        redirect('dashboard/' . (string)($_POST['return_view'] ?? $fallback));
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && in_array((string)($_POST['action'] ?? ''), ['save_vendor_package', 'save_subscription_settings'], true)) {
    try {
        match ((string)$_POST['action']) {
            'save_vendor_package' => dashboard_save_vendor_package(),
            'save_subscription_settings' => dashboard_save_subscription_settings(),
        };
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
        redirect('dashboard/' . ((string)($_POST['action'] ?? '') === 'save_subscription_settings' ? 'vendor-subscriptions' : 'vendor-packages'));
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'create_manage_store_payment') {
    validateCsrf();
    $targetUserId = max(0, (int)($_POST['user_id'] ?? 0));
    try {
        $targetUser = db()->fetch('SELECT id, email, display_name, first_name, last_name, phone FROM users WHERE id = ? AND status <> "deleted" LIMIT 1', [$targetUserId]);
        if (!$targetUser) {
            throw new \RuntimeException('User account was not found.');
        }
        $servicePlan = (string)($_POST['service_plan'] ?? 'annual');
        if (!in_array($servicePlan, ['annual', 'setup'], true)) throw new \RuntimeException('Invalid store service.');
        $service = \App\ManageStoreService::activeServiceForEmail((string)$targetUser['email'], $servicePlan);
        if ($service && in_array((string)$service['status'], ['active', 'paid', 'trialing'], true)) {
            throw new \RuntimeException('This store service has already been paid for by this user.');
        }
        $raw = json_decode((string)($service['raw_response'] ?? '{}'), true);
        $existingLink = (string)($raw['paystack_checkout']['authorization_url'] ?? '');
        if (!$service || $service['provider'] !== 'paystack' || !in_array($service['status'], ['pending', 'incomplete'], true) || $existingLink === '') {
            \App\ManageStoreService::createPaystackAnnualCheckout(
                trim((string)$targetUser['display_name']) ?: trim($targetUser['first_name'] . ' ' . $targetUser['last_name']),
                (string)$targetUser['email'],
                (string)($targetUser['phone'] ?? ''),
                $servicePlan
            );
            audit('manage_store.admin_payment_link_created', 'users', (string)$targetUserId, [], ['provider' => 'paystack', 'plan' => $servicePlan]);
        }
        flash('success', 'Paystack payment link is ready. Copy the link below and send it to the customer.');
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('dashboard/users?user=' . $targetUserId);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'save_user_admin') {
    try {
        dashboard_save_user_admin();
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
        redirect('dashboard/users' . ((int)($_POST['user_id'] ?? 0) > 0 ? '?user=' . (int)$_POST['user_id'] : ''));
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'save_admin_profile') {
    try {
        dashboard_save_admin_profile();
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
        redirect('dashboard/admin-profile');
    }
}

$shippingActionTargets = [
    'save_shipping_zone' => 'shipping-zones',
    'save_shipping_method' => 'shipping-rates',
    'save_delivery_partner' => 'delivery-partners',
    'save_aramex_settings' => 'aramex-api-settings',
    'update_shipment_tracking' => 'shipment-tracking',
];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($shippingActionTargets[(string)($_POST['action'] ?? '')])) {
    $action = (string)$_POST['action'];
    try {
        match ($action) {
            'save_shipping_zone' => dashboard_save_shipping_zone(),
            'save_shipping_method' => dashboard_save_shipping_method(),
            'save_delivery_partner' => dashboard_save_delivery_partner(),
            'save_aramex_settings' => dashboard_save_aramex_settings(),
            'update_shipment_tracking' => dashboard_update_shipment_tracking(),
        };
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
        redirect('dashboard/' . $shippingActionTargets[$action]);
    }
}

$contentActionTargets = [
    'save_content_album' => 'albums',
    'save_gallery_item' => 'gallery',
    'save_content_post' => 'blog-posts',
    'delete_content_record' => 'content',
];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($contentActionTargets[(string)($_POST['action'] ?? '')])) {
    $action = (string)$_POST['action'];
    try {
        dashboard_ensure_content_admin_schema();
        match ($action) {
            'save_content_album' => dashboard_save_content_album(),
            'save_gallery_item' => dashboard_save_gallery_item(),
            'save_content_post' => dashboard_save_content_post(),
            'delete_content_record' => dashboard_delete_content_record(),
        };
    } catch (\Throwable $e) {
        error_log('Dashboard content action failed: action=' . $action . ' view=' . $view . ' uri=' . (string)($_SERVER['REQUEST_URI'] ?? '') . ' message=' . $e->getMessage());
        if (dashboard_wants_json()) {
            dashboard_json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
        flash('error', $e->getMessage());
        redirect('dashboard/' . $contentActionTargets[$action]);
    }
}

$affiliateActionTargets = [
    'update_affiliate_status' => 'affiliates',
    'save_commission_rule' => 'commission-rules',
    'update_affiliate_referral_status' => 'pending-commissions',
    'update_affiliate_payout_status' => 'affiliate-payouts',
];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($affiliateActionTargets[(string)($_POST['action'] ?? '')])) {
    $action = (string)$_POST['action'];
    try {
        match ($action) {
            'update_affiliate_status' => dashboard_update_affiliate_status(),
            'save_commission_rule' => dashboard_save_commission_rule(),
            'update_affiliate_referral_status' => dashboard_update_affiliate_referral_status(),
            'update_affiliate_payout_status' => dashboard_update_affiliate_payout_status(),
        };
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
        redirect('dashboard/' . $affiliateActionTargets[$action]);
    }
}

$refundDisputeActionTargets = [
    'update_refund_status' => 'refund-requests',
    'update_return_status' => 'return-requests',
    'save_vendor_dispute' => 'vendor-disputes',
    'update_vendor_dispute_status' => 'vendor-disputes',
    'save_buyer_complaint' => 'buyer-complaints',
    'update_buyer_complaint_status' => 'buyer-complaints',
];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($refundDisputeActionTargets[(string)($_POST['action'] ?? '')])) {
    $action = (string)$_POST['action'];
    try {
        match ($action) {
            'update_refund_status' => dashboard_update_refund_status(),
            'update_return_status' => dashboard_update_return_status(),
            'save_vendor_dispute' => dashboard_save_vendor_dispute(),
            'update_vendor_dispute_status' => dashboard_update_vendor_dispute_status(),
            'save_buyer_complaint' => dashboard_save_buyer_complaint(),
            'update_buyer_complaint_status' => dashboard_update_buyer_complaint_status(),
        };
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
        redirect('dashboard/' . $refundDisputeActionTargets[$action]);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'save_payment_gateway') {
    try {
        dashboard_save_payment_gateway();
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('dashboard/payment-gateways');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'clear_queue') {
    try {
        validateCsrf();
        if (!table_exists('jobs_queue')) {
            throw new \RuntimeException('Queue table is not available.');
        }

        $total = (int)(db()->fetch("SELECT COUNT(*) AS total FROM jobs_queue")['total'] ?? 0);
        db()->query("DELETE FROM jobs_queue");
        flash('success', 'Queue cleared. Removed ' . number_format($total) . ' job(s).');
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('dashboard/queue-worker-monitor');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'test_mail') {
    try {
        if ($view !== 'mail-settings') {
            throw new \RuntimeException('Test mail can only be sent from Mail Settings.');
        }

        dashboard_save_settings($settingsPages['mail-settings']['group'], $settingsPages['mail-settings']['fields']);

        $user = current_user() ?? [];
        $recipient = trim((string)($user['email'] ?? $_SESSION['user_email'] ?? ''));
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $recipient = trim((string)app_setting('mail', 'from_email', ''));
        }
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('No valid admin or from email is available for the test message.');
        }

        $brand = app_branding();
        $result = EmailService::send(
            [['email' => $recipient, 'name' => (string)($user['display_name'] ?? 'Admin')]],
            'Seller Africa test email',
            '<p>This is a test email from ' . e((string)($brand['name'] ?? 'Seller Africa')) . '.</p><p>If you received this, your mail setup is working.</p><p>Sent at ' . e(sql_now()) . '.</p>',
            'This is a test email from ' . (string)($brand['name'] ?? 'Seller Africa') . '. If you received this, your mail setup is working. Sent at ' . sql_now() . '.'
        );

        flash('success', 'Test email sent to ' . $recipient . ' via ' . strtoupper((string)($result['provider'] ?? 'mail')) . '.');
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('dashboard/mail-settings');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($settingsPages[$view])) {
    try {
        dashboard_save_settings($settingsPages[$view]['group'], $settingsPages[$view]['fields']);
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('dashboard/' . $view);
}

$activeLabel = 'Overview';
foreach ($navigation as $sectionItems) {
    foreach ($sectionItems as $item) {
        if ($item['key'] === $view) {
            $activeLabel = $item['label'];
            break 2;
        }
    }
}

function dashboard_count(string $table, ?string $where = null): int {
    if (!table_exists($table)) {
        return 0;
    }

    $sql = 'SELECT COUNT(*) AS total FROM ' . $table;
    if ($where) {
        $sql .= ' WHERE ' . $where;
    }

    $row = db()->fetch($sql);

    return (int)($row['total'] ?? 0);
}

function dashboard_table_rows(string $sql, array $params = [], string $requiredTable = ''): array {
    if ($requiredTable !== '' && !table_exists($requiredTable)) {
        return [];
    }

    try {
        return db()->fetchAll($sql, $params);
    } catch (\Throwable) {
        return [];
    }
}

function dashboard_vendor_readiness(int $vendorId): array {
    $vendor = db()->fetch('SELECT id, logo_file_id, banner_file_id FROM vendors WHERE id = ? LIMIT 1', [$vendorId]);
    if (!$vendor) {
        return ['complete' => false, 'percent' => 0, 'checks' => []];
    }

    $productReady = (bool)db()->fetch(
        "SELECT p.id
         FROM products p
         WHERE p.vendor_id = ?
           AND p.regular_price > 0
           AND COALESCE(p.weight, 0) > 0
           AND COALESCE(p.length, 0) > 0
           AND COALESCE(p.width, 0) > 0
           AND COALESCE(p.height, 0) > 0
           AND TRIM(COALESCE(p.description, '')) <> ''
           AND EXISTS (SELECT 1 FROM product_media pm WHERE pm.product_id = p.id AND pm.role IN ('primary', 'gallery'))
         LIMIT 1",
        [$vendorId]
    );
    $application = db()->fetch(
        "SELECT application_data
         FROM vendor_applications
         WHERE vendor_id = ?
         ORDER BY submitted_at DESC, id DESC
         LIMIT 1",
        [$vendorId]
    );
    $applicationData = json_decode((string)($application['application_data'] ?? ''), true);
    $applicationData = is_array($applicationData) ? $applicationData : [];
    $readiness = is_array($applicationData['readiness'] ?? null) ? $applicationData['readiness'] : [];
    $fileIds = is_array($applicationData['file_ids'] ?? null) ? $applicationData['file_ids'] : [];
    $applicationProductImageReady = (int)($fileIds['product_image'] ?? 0) > 0;
    $applicationProductDetailsReady = trim((string)($readiness['product_description'] ?? '')) !== ''
        && (float)($readiness['product_price'] ?? 0) > 0
        && (float)($readiness['product_weight'] ?? 0) > 0
        && (float)($readiness['package_length'] ?? 0) > 0
        && (float)($readiness['package_width'] ?? 0) > 0
        && (float)($readiness['package_height'] ?? 0) > 0;

    $checks = [
        ['key' => 'profile_photo', 'label' => 'Store profile photo/logo uploaded', 'done' => !empty($vendor['logo_file_id'])],
        ['key' => 'store_banner', 'label' => 'Store banner uploaded', 'done' => !empty($vendor['banner_file_id'])],
        ['key' => 'product_image', 'label' => 'Product image uploaded', 'done' => $productReady || $applicationProductImageReady],
        ['key' => 'product_details', 'label' => 'Product description, price, weight and package dimensions provided', 'done' => $productReady || $applicationProductDetailsReady],
    ];
    $done = count(array_filter($checks, static fn (array $check): bool => (bool)$check['done']));

    return [
        'complete' => $done === count($checks),
        'percent' => (int)round(($done / max(1, count($checks))) * 100),
        'checks' => $checks,
    ];
}

function dashboard_update_vendor_approval(): void {
    validateCsrf();

    $vendorId = max(0, (int)($_POST['vendor_id'] ?? 0));
    $decision = (string)($_POST['decision'] ?? '');
    $returnView = preg_replace('/[^a-z0-9-]/', '', (string)($_POST['return_view'] ?? 'pending-vendors')) ?: 'pending-vendors';
    $vendor = db()->fetch('SELECT * FROM vendors WHERE id = ? LIMIT 1', [$vendorId]);
    if (!$vendor) {
        flash('error', 'Vendor record was not found.');
        redirect('dashboard/' . $returnView);
    }

    if ($decision === 'approve') {
        \App\VendorOnboardingService::assertApprovable($vendorId);
        db()->query('UPDATE vendors SET status = "active", kyc_status = "approved", updated_at = ? WHERE id = ?', [sql_now(), $vendorId]);
        audit('vendor.approved', 'vendors', (string)$vendorId, ['status' => $vendor['status'], 'kyc_status' => $vendor['kyc_status']], ['status' => 'active', 'kyc_status' => 'approved']);
        if ($vendor['status'] !== 'active' || $vendor['kyc_status'] !== 'approved') \App\NotificationService::vendorApproved($vendorId);
        flash('success', 'Vendor approved.');
        redirect('dashboard/' . $returnView);
    }

    if ($decision === 'reject') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        db()->query('UPDATE vendors SET status = "rejected", kyc_status = "rejected", updated_at = ? WHERE id = ?', [sql_now(), $vendorId]);
        audit('vendor.rejected', 'vendors', (string)$vendorId, ['status' => $vendor['status'], 'kyc_status' => $vendor['kyc_status']], ['status' => 'rejected', 'kyc_status' => 'rejected', 'reason' => $reason]);
        if ($vendor['status'] !== 'rejected' && $vendor['kyc_status'] !== 'rejected') {
            \App\NotificationService::vendorRejected($vendorId, $reason);
        }
        flash('success', 'Vendor rejected.');
        redirect('dashboard/' . $returnView);
    }

    flash('error', 'Invalid vendor approval decision.');
    redirect('dashboard/' . $returnView);
}

function dashboard_unique_vendor_slug(string $source, ?int $ignoreId = null): string {
    $base = dashboard_slug($source, 'vendor');
    $slug = $base;
    $counter = 2;

    while (true) {
        $params = [$slug];
        $sql = 'SELECT id FROM vendors WHERE store_slug = ?';
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        if (!db()->fetch($sql . ' LIMIT 1', $params)) {
            return $slug;
        }
        $slug = $base . '-' . $counter++;
    }
}

function dashboard_uploaded_vendor_file(string $field, int $vendorId, int $ownerUserId, string $bucket, array $allowed): ?int {
    $upload = $_FILES[$field] ?? null;
    if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int)$upload['error'] !== UPLOAD_ERR_OK) {
        throw new \RuntimeException('Vendor upload failed.');
    }

    $tmpName = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new \RuntimeException('Uploaded vendor file could not be read.');
    }
    if ($size <= 0 || $size > 8 * 1024 * 1024) {
        throw new \RuntimeException('Vendor file must be smaller than 8MB.');
    }

    $mime = function_exists('mime_content_type') ? (string)mime_content_type($tmpName) : '';
    if (!isset($allowed[$mime])) {
        throw new \RuntimeException('Unsupported vendor file type.');
    }

    $uploadDir = APP_ROOT . '/public/uploads/vendor/' . max(1, $vendorId) . '/' . $bucket;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new \RuntimeException('Vendor upload directory could not be created.');
    }
    @chmod(APP_ROOT . '/public/uploads', 0777);
    @chmod(APP_ROOT . '/public/uploads/vendor', 0777);
    @chmod(APP_ROOT . '/public/uploads/vendor/' . max(1, $vendorId), 0777);
    @chmod($uploadDir, 0777);

    $filename = $bucket . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $target = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmpName, $target)) {
        throw new \RuntimeException('Vendor upload could not be saved.');
    }

    $relativePath = 'uploads/vendor/' . max(1, $vendorId) . '/' . $bucket . '/' . $filename;
    [$width, $height] = str_starts_with($mime, 'image/') ? (@getimagesize($target) ?: [null, null]) : [null, null];
    db()->query(
        "INSERT INTO files (owner_user_id, disk, path, original_name, mime_type, size_bytes, width, height, alt_text)
         VALUES (?, 'public', ?, ?, ?, ?, ?, ?, ?)",
        [$ownerUserId ?: null, $relativePath, (string)($upload['name'] ?? $filename), $mime, $size, $width, $height, pathinfo((string)($upload['name'] ?? $filename), PATHINFO_FILENAME)]
    );

    return (int)db()->lastInsertId();
}

function dashboard_save_vendor_admin(): void {
    validateCsrf();

    $vendorId = max(0, (int)($_POST['vendor_id'] ?? 0));
    $old = db()->fetch('SELECT * FROM vendors WHERE id = ? LIMIT 1', [$vendorId]);
    if (!$old) {
        throw new \RuntimeException('Vendor was not found.');
    }

    $storeName = trim((string)($_POST['store_name'] ?? ''));
    if ($storeName === '') {
        throw new \RuntimeException('Store name is required.');
    }
    $status = in_array((string)($_POST['status'] ?? 'pending'), ['pending', 'active', 'suspended', 'rejected', 'closed'], true) ? (string)$_POST['status'] : 'pending';
    $kycStatus = in_array((string)($_POST['kyc_status'] ?? 'not_started'), ['not_started', 'pending', 'approved', 'rejected', 'expired'], true) ? (string)$_POST['kyc_status'] : 'not_started';
    if ($status === 'active' || $kycStatus === 'approved') {
        \App\VendorOnboardingService::assertApprovable($vendorId);
    }
    $commissionType = in_array((string)($_POST['commission_type'] ?? 'percentage'), ['percentage', 'flat', 'hybrid'], true) ? (string)$_POST['commission_type'] : 'percentage';

    $ownerUserId = (int)($old['user_id'] ?? 0);
    $logoId = dashboard_uploaded_vendor_file('logo', $vendorId, $ownerUserId, 'store', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']);
    $bannerId = dashboard_uploaded_vendor_file('banner', $vendorId, $ownerUserId, 'store', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']);

    $line1 = trim((string)($_POST['address_line1'] ?? ''));
    $addressId = (int)($old['address_id'] ?? 0);
    if ($line1 !== '' || $addressId > 0) {
        $addressParams = [
            $line1 !== '' ? $line1 : 'Store address',
            trim((string)($_POST['city'] ?? '')) ?: null,
            trim((string)($_POST['state'] ?? '')) ?: null,
            trim((string)($_POST['postcode'] ?? '')) ?: null,
            strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($_POST['country_code'] ?? '')) ?: '', 0, 2)) ?: null,
        ];
        if ($addressId > 0) {
            db()->query('UPDATE addresses SET address_line1 = ?, city = ?, state = ?, postcode = ?, country_code = ?, updated_at = ? WHERE id = ?', [...$addressParams, sql_now(), $addressId]);
        } else {
            db()->query('INSERT INTO addresses (user_id, type, address_line1, city, state, postcode, country_code, is_default, created_at, updated_at) VALUES (?, "store", ?, ?, ?, ?, ?, 1, ?, ?)', [$ownerUserId ?: null, ...$addressParams, sql_now(), sql_now()]);
            $addressId = (int)db()->lastInsertId();
        }
    }

    $sets = [
        'store_name = ?', 'store_slug = ?', 'store_email = ?', 'store_phone = ?', 'description = ?',
        'status = ?', 'kyc_status = ?', 'commission_type = ?', 'commission_rate = ?',
        'payout_method = ?', 'payout_details = ?', 'address_id = ?', 'updated_at = ?',
    ];
    $params = [
        $storeName,
        dashboard_unique_vendor_slug((string)($_POST['store_slug'] ?? $storeName), $vendorId),
        trim((string)($_POST['store_email'] ?? '')) ?: null,
        trim((string)($_POST['store_phone'] ?? '')) ?: null,
        trim((string)($_POST['description'] ?? '')) ?: null,
        $status,
        $kycStatus,
        $commissionType,
        max(0, (float)($_POST['commission_rate'] ?? 0)),
        trim((string)($_POST['payout_method'] ?? '')) ?: null,
        trim((string)($_POST['payout_details'] ?? '')) !== '' ? json_encode(['details' => trim((string)$_POST['payout_details'])], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        $addressId > 0 ? $addressId : null,
        sql_now(),
    ];
    if (dashboard_column_exists('vendors', 'origin_region')) {
        $sets[] = 'origin_region = ?';
        $originRegion = (string)($_POST['origin_region'] ?? '');
        $params[] = in_array($originRegion, ['West Africa', 'East Africa', 'Caribbean', 'Other'], true) ? $originRegion : null;
    }
    if (dashboard_column_exists('vendors', 'country_of_origin')) {
        $sets[] = 'country_of_origin = ?';
        $params[] = trim((string)($_POST['country_of_origin'] ?? '')) ?: null;
    }
    if ($logoId !== null) {
        $sets[] = 'logo_file_id = ?';
        $params[] = $logoId;
    }
    if ($bannerId !== null) {
        $sets[] = 'banner_file_id = ?';
        $params[] = $bannerId;
    }
    $params[] = $vendorId;
    db()->query('UPDATE vendors SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);

    audit('vendor.admin_updated', 'vendors', (string)$vendorId, $old, ['status' => $status, 'kyc_status' => $kycStatus]);
    if ($status === 'active' && $kycStatus === 'approved' && ($old['status'] !== 'active' || $old['kyc_status'] !== 'approved')) \App\NotificationService::vendorApproved($vendorId);
    if (($status === 'rejected' || $kycStatus === 'rejected') && $old['status'] !== 'rejected' && $old['kyc_status'] !== 'rejected') {
        \App\NotificationService::vendorRejected($vendorId);
    }
    flash('success', 'Vendor profile saved.');
    redirect('dashboard/vendor-edit?vendor=' . $vendorId);
}

function dashboard_save_vendor_kyc_document_admin(): void {
    validateCsrf();

    $vendorId = max(0, (int)($_POST['vendor_id'] ?? 0));
    $documentId = max(0, (int)($_POST['document_id'] ?? 0));
    $vendor = db()->fetch('SELECT id, user_id FROM vendors WHERE id = ? LIMIT 1', [$vendorId]);
    if (!$vendor) {
        throw new \RuntimeException('Vendor was not found.');
    }

    $status = in_array((string)($_POST['status'] ?? 'pending'), ['pending', 'approved', 'rejected'], true) ? (string)$_POST['status'] : 'pending';
    $fileId = dashboard_uploaded_vendor_file('document_file', $vendorId, (int)$vendor['user_id'], 'kyc', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf']);
    $old = $documentId > 0 ? db()->fetch('SELECT * FROM vendor_kyc_documents WHERE id = ? AND vendor_id = ? LIMIT 1', [$documentId, $vendorId]) : null;
    $reviewedAt = in_array($status, ['approved', 'rejected'], true) ? sql_now() : null;
    $reviewedBy = in_array($status, ['approved', 'rejected'], true) ? ($_SESSION['user_id'] ?? null) : null;

    if ($old) {
        db()->query(
            'UPDATE vendor_kyc_documents SET document_type = ?, document_number = ?, status = ?, rejection_reason = ?, reviewed_by = COALESCE(?, reviewed_by), reviewed_at = COALESCE(?, reviewed_at), file_id = COALESCE(?, file_id) WHERE id = ?',
            [trim((string)($_POST['document_type'] ?? 'business_registration')) ?: 'business_registration', trim((string)($_POST['document_number'] ?? '')) ?: null, $status, trim((string)($_POST['rejection_reason'] ?? '')) ?: null, $reviewedBy, $reviewedAt, $fileId, $documentId]
        );
    } else {
        db()->query(
            'INSERT INTO vendor_kyc_documents (vendor_id, document_type, document_number, file_id, status, rejection_reason, submitted_at, reviewed_by, reviewed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$vendorId, trim((string)($_POST['document_type'] ?? 'business_registration')) ?: 'business_registration', trim((string)($_POST['document_number'] ?? '')) ?: null, $fileId, $status, trim((string)($_POST['rejection_reason'] ?? '')) ?: null, sql_now(), $reviewedBy, $reviewedAt]
        );
        $documentId = (int)db()->lastInsertId();
    }

    audit($old ? 'vendor_document.admin_updated' : 'vendor_document.admin_created', 'vendor_kyc_documents', (string)$documentId, $old ?: [], ['vendor_id' => $vendorId, 'status' => $status]);
    flash('success', 'Vendor document saved.');
    redirect('dashboard/vendor-edit?vendor=' . $vendorId);
}

function dashboard_save_vendor_subscription_admin(): void {
    validateCsrf();
    VendorSubscriptionService::ensureSchema();

    $vendorId = max(0, (int)($_POST['vendor_id'] ?? 0));
    $packageId = max(0, (int)($_POST['package_id'] ?? 0));
    $status = in_array((string)($_POST['subscription_status'] ?? 'active'), ['active', 'trialing', 'past_due', 'unpaid', 'cancelled', 'incomplete', 'expired'], true) ? (string)$_POST['subscription_status'] : 'active';
    $package = VendorSubscriptionService::package($packageId);
    if (!db()->fetch('SELECT id FROM vendors WHERE id = ? LIMIT 1', [$vendorId]) || !$package) {
        throw new \RuntimeException('Vendor or package was not found.');
    }

    $adminDateTime = static function (string $key): ?string {
        $value = trim((string)($_POST[$key] ?? ''));
        return $value === '' ? null : str_replace('T', ' ', $value) . (strlen($value) === 16 ? ':00' : '');
    };

    db()->query("UPDATE vendor_subscriptions SET status = 'cancelled', updated_at = ? WHERE vendor_id = ? AND status IN ('active', 'trialing', 'past_due', 'unpaid', 'incomplete')", [sql_now(), $vendorId]);
    db()->query(
        'INSERT INTO vendor_subscriptions (vendor_id, package_id, status, provider, current_period_start, current_period_end, grace_ends_at, started_at, paid_at)
         VALUES (?, ?, ?, "manual", ?, ?, ?, ?, ?)',
        [
            $vendorId,
            $packageId,
            $status,
            $adminDateTime('current_period_start'),
            $adminDateTime('current_period_end'),
            $adminDateTime('grace_ends_at'),
            sql_now(),
            in_array($status, ['active', 'trialing'], true) ? sql_now() : null,
        ]
    );

    audit('vendor_subscription.admin_assigned', 'vendor_subscriptions', (string)db()->lastInsertId(), [], ['vendor_id' => $vendorId, 'package_id' => $packageId, 'status' => $status]);
    flash('success', 'Vendor plan updated.');
    redirect('dashboard/vendor-edit?vendor=' . $vendorId);
}

function dashboard_vendor_product(int $productId, int $vendorId): array {
    $product = db()->fetch('SELECT * FROM products WHERE id = ? AND vendor_id = ? LIMIT 1', [$productId, $vendorId]);
    if (!$product) {
        throw new \RuntimeException('Vendor product was not found.');
    }
    return $product;
}

function dashboard_update_vendor_product_status_admin(): void {
    validateCsrf();

    $vendorId = max(0, (int)($_POST['vendor_id'] ?? 0));
    $productId = max(0, (int)($_POST['product_id'] ?? 0));
    $status = (string)($_POST['status'] ?? '');
    if (!in_array($status, ['draft', 'pending', 'active', 'private', 'archived', 'rejected'], true)) {
        throw new \RuntimeException('Invalid product status.');
    }

    $product = dashboard_vendor_product($productId, $vendorId);
    if ($status === 'active' && !in_array((string)($product['status'] ?? ''), ['active', 'private'], true)) {
        $quota = VendorSubscriptionService::quota($vendorId);
        if (!$quota['can_upload']) {
            $limit = $quota['limit'] === null ? 'unlimited' : number_format((int)$quota['limit']);
            throw new \RuntimeException('This vendor has reached the ' . $limit . ' published product slot limit for the current plan.');
        }
    }

    db()->query(
        'UPDATE products SET published_at = CASE WHEN ? = "active" AND status <> "active" THEN ? ELSE published_at END, status = ?, updated_at = ? WHERE id = ?',
        [$status, sql_now(), $status, sql_now(), $productId]
    );
    audit('vendor_product.admin_status_updated', 'products', (string)$productId, ['status' => $product['status']], ['status' => $status, 'vendor_id' => $vendorId]);
    flash('success', 'Product status updated.');
    redirect('dashboard/vendor-edit?vendor=' . $vendorId);
}

function dashboard_delete_vendor_product_admin(): void {
    validateCsrf();

    $vendorId = max(0, (int)($_POST['vendor_id'] ?? 0));
    $productId = max(0, (int)($_POST['product_id'] ?? 0));
    $product = dashboard_vendor_product($productId, $vendorId);
    $orderItems = (int)(db()->fetch('SELECT COUNT(*) AS total FROM order_items WHERE product_id = ?', [$productId])['total'] ?? 0);

    if ($orderItems > 0) {
        db()->query('UPDATE products SET status = "archived", updated_at = ? WHERE id = ?', [sql_now(), $productId]);
        audit('vendor_product.admin_archived_instead_of_deleted', 'products', (string)$productId, $product, ['vendor_id' => $vendorId, 'order_items' => $orderItems]);
        if (dashboard_wants_json()) {
            dashboard_json(['ok' => true, 'message' => 'Product has order history, so it was archived.', 'mode' => 'archived', 'status' => 'archived']);
        }
        flash('warning', 'Product has order history, so it was archived instead of deleted.');
        redirect('dashboard/vendor-edit?vendor=' . $vendorId);
    }

    db()->query('DELETE FROM products WHERE id = ? AND vendor_id = ?', [$productId, $vendorId]);
    audit('vendor_product.admin_deleted', 'products', (string)$productId, $product, ['vendor_id' => $vendorId]);
    if (dashboard_wants_json()) {
        dashboard_json(['ok' => true, 'message' => 'Product deleted.', 'mode' => 'deleted']);
    }
    flash('success', 'Product deleted.');
    redirect('dashboard/vendor-edit?vendor=' . $vendorId);
}

function dashboard_delete_file_row(int $fileId): void {
    $file = db()->fetch('SELECT * FROM files WHERE id = ? LIMIT 1', [$fileId]);
    if (!$file) {
        return;
    }
    $path = trim((string)($file['path'] ?? ''));
    db()->query('DELETE FROM files WHERE id = ?', [$fileId]);
    if ($path !== '' && !preg_match('/^https?:\/\//i', $path)) {
        $fullPath = APP_ROOT . '/public/' . ltrim($path, '/');
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}

function dashboard_update_product_media_admin(): void {
    validateCsrf();

    $vendorId = max(0, (int)($_POST['vendor_id'] ?? 0));
    $productId = max(0, (int)($_POST['product_id'] ?? 0));
    $mediaId = max(0, (int)($_POST['media_id'] ?? 0));
    $mediaAction = (string)($_POST['media_action'] ?? 'save');
    dashboard_vendor_product($productId, $vendorId);

    $media = db()->fetch(
        'SELECT pm.*, f.path FROM product_media pm INNER JOIN files f ON f.id = pm.file_id WHERE pm.id = ? AND pm.product_id = ? LIMIT 1',
        [$mediaId, $productId]
    );
    if (!$media) {
        throw new \RuntimeException('Product image was not found.');
    }

    if ($mediaAction === 'delete') {
        db()->query('DELETE FROM product_media WHERE id = ? AND product_id = ?', [$mediaId, $productId]);
        $remaining = (int)(db()->fetch('SELECT COUNT(*) AS total FROM product_media WHERE file_id = ?', [(int)$media['file_id']])['total'] ?? 0);
        if ($remaining === 0) {
            dashboard_delete_file_row((int)$media['file_id']);
        }
        audit('product_media.admin_deleted', 'product_media', (string)$mediaId, $media, ['vendor_id' => $vendorId, 'product_id' => $productId]);
        if (dashboard_wants_json()) {
            dashboard_json(['ok' => true, 'message' => 'Product image deleted.', 'mode' => 'deleted', 'file_id' => (int)$media['file_id'], 'media_id' => $mediaId]);
        }
        flash('success', 'Product image deleted.');
        redirect('dashboard/vendor-edit?vendor=' . $vendorId . '&edit_product=' . $productId);
    }

    $role = in_array((string)($_POST['role'] ?? 'gallery'), ['primary', 'gallery', 'download', 'document'], true) ? (string)$_POST['role'] : 'gallery';
    if ($mediaAction === 'primary') {
        $role = 'primary';
    }
    if ($role === 'primary') {
        db()->query("UPDATE product_media SET role = 'gallery' WHERE product_id = ? AND role = 'primary' AND id <> ?", [$productId, $mediaId]);
    }
    db()->query('UPDATE product_media SET role = ?, sort_order = ? WHERE id = ? AND product_id = ?', [$role, (int)($_POST['sort_order'] ?? 0), $mediaId, $productId]);
    audit('product_media.admin_updated', 'product_media', (string)$mediaId, $media, ['vendor_id' => $vendorId, 'product_id' => $productId, 'role' => $role]);
    flash('success', 'Product image updated.');
    redirect('dashboard/vendor-edit?vendor=' . $vendorId . '&edit_product=' . $productId);
}

function dashboard_delete_vendor_file_admin(): void {
    validateCsrf();

    $vendorId = max(0, (int)($_POST['vendor_id'] ?? 0));
    $fileId = max(0, (int)($_POST['file_id'] ?? 0));
    $vendor = db()->fetch('SELECT id, user_id FROM vendors WHERE id = ? LIMIT 1', [$vendorId]);
    $file = db()->fetch('SELECT * FROM files WHERE id = ? LIMIT 1', [$fileId]);
    if (!$vendor || !$file) {
        throw new \RuntimeException('Vendor file was not found.');
    }

    $path = (string)($file['path'] ?? '');
    $attachedToVendorProduct = (bool)db()->fetch(
        'SELECT pm.id FROM product_media pm INNER JOIN products p ON p.id = pm.product_id WHERE pm.file_id = ? AND p.vendor_id = ? LIMIT 1',
        [$fileId, $vendorId]
    );
    $attachedToVendorDocument = (bool)db()->fetch('SELECT id FROM vendor_kyc_documents WHERE file_id = ? AND vendor_id = ? LIMIT 1', [$fileId, $vendorId]);
    $usedAsStoreBranding = (bool)db()->fetch('SELECT id FROM vendors WHERE id = ? AND (logo_file_id = ? OR banner_file_id = ?) LIMIT 1', [$vendorId, $fileId, $fileId]);
    $isVendorOwned = (int)($file['owner_user_id'] ?? 0) === (int)$vendor['user_id']
        || str_starts_with($path, 'uploads/vendor/' . $vendorId . '/')
        || $attachedToVendorProduct
        || $attachedToVendorDocument
        || $usedAsStoreBranding;
    if (!$isVendorOwned || !str_starts_with((string)($file['mime_type'] ?? ''), 'image/')) {
        throw new \RuntimeException('This image does not belong to the selected vendor.');
    }

    db()->query('UPDATE product_media pm INNER JOIN products p ON p.id = pm.product_id SET pm.role = "gallery" WHERE pm.file_id = ? AND p.vendor_id = ? AND pm.role = "primary"', [$fileId, $vendorId]);
    dashboard_delete_file_row($fileId);
    audit('vendor_file.admin_deleted', 'files', (string)$fileId, $file, ['vendor_id' => $vendorId]);
    if (dashboard_wants_json()) {
        dashboard_json(['ok' => true, 'message' => 'Vendor image deleted.', 'mode' => 'deleted', 'file_id' => $fileId]);
    }
    flash('success', 'Vendor image deleted.');
    redirect('dashboard/vendor-edit?vendor=' . $vendorId);
}

function dashboard_save_vendor_package(): void {
    validateCsrf();
    VendorSubscriptionService::upsertPackage($_POST);
    audit('vendor_package.saved', 'vendor_packages', (string)($_POST['package_id'] ?? ''));
    flash('success', 'Vendor package saved.');
    redirect('dashboard/vendor-packages');
}

function dashboard_save_subscription_settings(): void {
    validateCsrf();
    VendorSubscriptionService::saveGraceDays((int)($_POST['grace_days'] ?? 7));
    audit('vendor_subscription_settings.saved', 'settings', 'subscriptions');
    flash('success', 'Subscription settings saved.');
    redirect('dashboard/vendor-subscriptions');
}

function dashboard_save_user_admin(): void {
    validateCsrf();

    $userId = max(0, (int)($_POST['user_id'] ?? 0));
    $targetRoles = db()->fetchAll('SELECT r.id, r.code FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?', [$userId]);
    if (!\App\AdminAccessService::canEditUser($_SESSION['roles'] ?? [], array_column($targetRoles, 'code'))) {
        throw new \RuntimeException('Only a super administrator can edit a super administrator account.');
    }
    if (!\App\AuthService::hasRole('super_admin')) {
        $existingRoleIds = array_map('intval', array_column($targetRoles, 'id'));
        $requestedRoleIds = array_values(array_unique(array_map('intval', (array)($_POST['roles'] ?? $existingRoleIds))));
        sort($existingRoleIds);
        sort($requestedRoleIds);
        if ($existingRoleIds !== $requestedRoleIds) {
            throw new \RuntimeException('Only a super administrator can change account roles.');
        }
        $_POST['roles'] = $existingRoleIds;
    }
    $user = db()->fetch('SELECT * FROM users WHERE id = ? LIMIT 1', [$userId]);
    if (!$user) {
        flash('error', 'User account was not found.');
        redirect('dashboard/users');
    }

    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'A valid email address is required.');
        redirect('dashboard/users?user=' . $userId);
    }

    $duplicate = db()->fetch('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1', [$email, $userId]);
    if ($duplicate) {
        flash('error', 'Another account already uses that email address.');
        redirect('dashboard/users?user=' . $userId);
    }

    $status = (string)($_POST['status'] ?? 'active');
    if (!in_array($status, ['active', 'pending', 'suspended', 'deleted'], true)) {
        $status = 'pending';
    }

    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['new_password_confirmation'] ?? '');
    if ($newPassword !== '' || $confirmPassword !== '') {
        foreach (SecurityService::passwordErrors($newPassword) as $passwordError) {
            flash('error', $passwordError);
            redirect('dashboard/users?user=' . $userId);
        }
        if ($newPassword !== $confirmPassword) {
            flash('error', 'Password confirmation does not match.');
            redirect('dashboard/users?user=' . $userId);
        }
    }

    db()->query(
        'UPDATE users SET email = ?, first_name = ?, last_name = ?, display_name = ?, phone = ?, status = ?, email_verified_at = ?, updated_at = ? WHERE id = ?',
        [
            $email,
            trim((string)($_POST['first_name'] ?? '')),
            trim((string)($_POST['last_name'] ?? '')),
            trim((string)($_POST['display_name'] ?? '')),
            trim((string)($_POST['phone'] ?? '')),
            $status,
            isset($_POST['email_verified']) ? (($user['email_verified_at'] ?? null) ?: sql_now()) : null,
            sql_now(),
            $userId,
        ]
    );

    if ($newPassword !== '') {
        db()->query(
            'UPDATE users SET password_hash = ?, legacy_password_reset_at = COALESCE(legacy_password_reset_at, ?), email_verified_at = COALESCE(email_verified_at, ?), updated_at = ? WHERE id = ?',
            [SecurityService::passwordHash($newPassword), sql_now(), sql_now(), sql_now(), $userId]
        );
    }

    $roleIds = array_values(array_unique(array_map('intval', (array)($_POST['roles'] ?? []))));
    $superAdminRole = db()->fetch("SELECT id FROM roles WHERE code = 'super_admin' LIMIT 1");
    $superAdminRoleId = (int)($superAdminRole['id'] ?? 0);
    if ($userId === (int)($_SESSION['user_id'] ?? 0) && \App\AuthService::hasRole('super_admin')) {
        if ($status !== 'active') {
            flash('error', 'You cannot deactivate your own super admin account.');
            redirect('dashboard/users?user=' . $userId);
        }

        if ($superAdminRoleId > 0 && !in_array($superAdminRoleId, $roleIds, true)) {
            flash('error', 'You cannot remove your own super admin role.');
            redirect('dashboard/users?user=' . $userId);
        }
    }

    db()->query('DELETE FROM user_roles WHERE user_id = ?', [$userId]);
    foreach ($roleIds as $roleId) {
        if ($roleId > 0) {
            db()->query('INSERT IGNORE INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, ?)', [$userId, $roleId, sql_now()]);
        }
    }

    audit('user.updated', 'users', (string)$userId, ['email' => $user['email'], 'status' => $user['status']], ['email' => $email, 'status' => $status, 'roles' => $roleIds, 'password_changed' => $newPassword !== '']);
    flash('success', 'User details saved.');
    redirect('dashboard/users?user=' . $userId);
}

function dashboard_save_admin_profile(): void {
    validateCsrf();

    $userId = (int)($_SESSION['user_id'] ?? 0);
    $user = db()->fetch('SELECT * FROM users WHERE id = ? LIMIT 1', [$userId]);
    if (!$user) {
        flash('error', 'Admin account was not found.');
        redirect('dashboard');
    }

    $intent = (string)($_POST['intent'] ?? 'save_profile');
    if ($intent === 'save_profile') {
        $username = trim((string)($_POST['username'] ?? ''));
        if ($username !== '') {
            $duplicate = db()->fetch('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1', [$username, $userId]);
            if ($duplicate) {
                throw new \RuntimeException('Another account already uses that username.');
            }
        }

        db()->query(
            'UPDATE users SET username = ?, first_name = ?, last_name = ?, display_name = ?, phone = ?, updated_at = ? WHERE id = ?',
            [
                $username !== '' ? $username : null,
                trim((string)($_POST['first_name'] ?? '')),
                trim((string)($_POST['last_name'] ?? '')),
                trim((string)($_POST['display_name'] ?? '')),
                trim((string)($_POST['phone'] ?? '')),
                sql_now(),
                $userId,
            ]
        );
        $_SESSION['display_name'] = trim((string)($_POST['display_name'] ?? '')) ?: ($_SESSION['display_name'] ?? 'Admin');
        audit('admin.profile.updated', 'users', (string)$userId, [], [], $userId);
        flash('success', 'Profile saved.');
        redirect('dashboard/admin-profile');
    }

    if ($intent === 'change_password') {
        if (!password_verify((string)($_POST['current_password'] ?? ''), (string)($user['password_hash'] ?? ''))) {
            throw new \RuntimeException('Current password is incorrect.');
        }
        $newPassword = (string)($_POST['new_password'] ?? '');
        foreach (SecurityService::passwordErrors($newPassword) as $passwordError) {
            throw new \RuntimeException($passwordError);
        }
        if ($newPassword !== (string)($_POST['new_password_confirmation'] ?? '')) {
            throw new \RuntimeException('Password confirmation does not match.');
        }

        db()->query(
            'UPDATE users SET password_hash = ?, legacy_password_reset_at = COALESCE(legacy_password_reset_at, ?), email_verified_at = COALESCE(email_verified_at, ?), updated_at = ? WHERE id = ?',
            [SecurityService::passwordHash($newPassword), sql_now(), sql_now(), sql_now(), $userId]
        );
        audit('admin.password.changed', 'users', (string)$userId, [], [], $userId);
        flash('success', 'Password changed.');
        redirect('dashboard/admin-profile');
    }

    flash('error', 'The requested profile action was not recognized.');
    redirect('dashboard/admin-profile');
}

function dashboard_report_range(): array {
    $period = preg_replace('/[^a-z_]/', '', (string)($_GET['period'] ?? 'month')) ?: 'month';
    if (!in_array($period, ['today', 'week', 'month', 'year', 'custom'], true)) {
        $period = 'month';
    }

    $now = app_now();
    $end = $now->setTime(23, 59, 59);
    $start = match ($period) {
        'today' => $now->setTime(0, 0, 0),
        'week' => $now->modify('-6 days')->setTime(0, 0, 0),
        'year' => $now->modify('first day of january this year')->setTime(0, 0, 0),
        'custom' => new DateTimeImmutable((string)($_GET['start'] ?? $now->modify('first day of this month')->format('Y-m-d')), new DateTimeZone(app_timezone())),
        default => $now->modify('first day of this month')->setTime(0, 0, 0),
    };

    if ($period === 'custom') {
        $start = $start->setTime(0, 0, 0);
        $endInput = trim((string)($_GET['end'] ?? ''));
        if ($endInput !== '') {
            $end = (new DateTimeImmutable($endInput, new DateTimeZone(app_timezone())))->setTime(23, 59, 59);
        }
    }

    if ($start > $end) {
        [$start, $end] = [$end->setTime(0, 0, 0), $start->setTime(23, 59, 59)];
    }

    return [
        'period' => $period,
        'start' => $start,
        'end' => $end,
        'startSql' => $start->format('Y-m-d H:i:s'),
        'endSql' => $end->format('Y-m-d H:i:s'),
        'label' => $start->format('M j, Y') . ' - ' . $end->format('M j, Y'),
    ];
}

function dashboard_report_money(mixed $amount, string $currency = 'USD'): string {
    return strtoupper($currency) . ' ' . number_format((float)$amount, 2);
}

function dashboard_report_scalar(string $sql, array $params = [], string $key = 'value', string $requiredTable = 'orders'): float {
    if ($requiredTable !== '' && !table_exists($requiredTable)) {
        return 0.0;
    }

    try {
        $row = db()->fetch($sql, $params);
        return (float)($row[$key] ?? 0);
    } catch (\Throwable) {
        return 0.0;
    }
}

function dashboard_report_rows(string $sql, array $params = [], string $requiredTable = 'orders'): array {
    return dashboard_table_rows($sql, $params, $requiredTable);
}

function dashboard_build_reporting_data(array $range): array {
    $start = $range['startSql'];
    $end = $range['endSql'];
    $params = [$start, $end];
    $validOrderStatus = "status NOT IN ('cancelled', 'failed', 'refunded')";

    $orderSummary = table_exists('orders')
        ? (db()->fetch(
            "SELECT
                COUNT(*) AS total_orders,
                COALESCE(SUM(grand_total), 0) AS gross_sales,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN grand_total ELSE 0 END), 0) AS paid_revenue,
                COALESCE(SUM(discount_total), 0) AS discounts,
                COALESCE(SUM(shipping_total), 0) AS shipping,
                COALESCE(SUM(tax_total), 0) AS taxes,
                COALESCE(AVG(NULLIF(grand_total, 0)), 0) AS average_order_value,
                COUNT(DISTINCT COALESCE(customer_id, guest_email)) AS customers
             FROM orders
             WHERE created_at BETWEEN ? AND ? AND {$validOrderStatus}",
            $params
        ) ?: [])
        : [];

    $paymentSummary = table_exists('payments')
        ? (db()->fetch(
            "SELECT
                COUNT(*) AS payments,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS captured,
                COALESCE(SUM(CASE WHEN status = 'failed' THEN amount ELSE 0 END), 0) AS failed_amount,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count
             FROM payments
             WHERE created_at BETWEEN ? AND ?",
            $params
        ) ?: [])
        : [];

    $trend = dashboard_report_rows(
        "SELECT DATE(created_at) AS report_date,
                COUNT(*) AS orders,
                COALESCE(SUM(grand_total), 0) AS gross_sales,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN grand_total ELSE 0 END), 0) AS paid_revenue
         FROM orders
         WHERE created_at BETWEEN ? AND ? AND {$validOrderStatus}
         GROUP BY DATE(created_at)
         ORDER BY report_date ASC",
        $params
    );

    $maxTrend = max(1.0, ...array_map(static fn (array $row): float => (float)$row['gross_sales'], $trend ?: [['gross_sales' => 1]]));
    foreach ($trend as &$row) {
        $row['height'] = max(4, min(100, (int)round(((float)$row['gross_sales'] / $maxTrend) * 100)));
    }
    unset($row);

    $currencyBreakdown = dashboard_report_rows(
        "SELECT currency, COUNT(*) AS orders, COALESCE(SUM(grand_total), 0) AS gross_sales, COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN grand_total ELSE 0 END), 0) AS paid_revenue
         FROM orders
         WHERE created_at BETWEEN ? AND ? AND {$validOrderStatus}
         GROUP BY currency
         ORDER BY gross_sales DESC",
        $params
    );

    $statusBreakdown = dashboard_report_rows(
        "SELECT status, COUNT(*) AS total, COALESCE(SUM(grand_total), 0) AS gross_sales
         FROM orders
         WHERE created_at BETWEEN ? AND ?
         GROUP BY status
         ORDER BY total DESC",
        $params
    );

    $topProducts = dashboard_report_rows(
        "SELECT COALESCE(p.name, oi.name) AS product_name, p.sku, COUNT(DISTINCT oi.order_id) AS orders,
                COALESCE(SUM(oi.quantity), 0) AS quantity, COALESCE(SUM(oi.total), 0) AS revenue
         FROM order_items oi
         LEFT JOIN products p ON p.id = oi.product_id
         INNER JOIN orders o ON o.id = oi.order_id
         WHERE o.created_at BETWEEN ? AND ? AND oi.item_type = 'product' AND o.{$validOrderStatus}
         GROUP BY oi.product_id, COALESCE(p.name, oi.name), p.sku
         ORDER BY revenue DESC, quantity DESC
         LIMIT 10",
        $params,
        'order_items'
    );

    $vendorRows = dashboard_report_rows(
        "SELECT v.id, v.store_name, v.status, COUNT(DISTINCT ovs.order_id) AS orders,
                COALESCE(SUM(ovs.gross_total), 0) AS gross_sales,
                COALESCE(SUM(ovs.vendor_earning), 0) AS vendor_earning,
                COALESCE(SUM(ovs.platform_commission), 0) AS platform_commission,
                COALESCE(SUM(ovs.gateway_fee), 0) AS gateway_fee
         FROM order_vendor_splits ovs
         INNER JOIN orders o ON o.id = ovs.order_id
         INNER JOIN vendors v ON v.id = ovs.vendor_id
         WHERE o.created_at BETWEEN ? AND ? AND o.{$validOrderStatus}
         GROUP BY v.id, v.store_name, v.status
         ORDER BY gross_sales DESC
         LIMIT 25",
        $params,
        'order_vendor_splits'
    );

    $affiliateRows = dashboard_report_rows(
        "SELECT a.id, a.referral_code, a.status, u.display_name, u.email,
                COUNT(r.id) AS referrals,
                COALESCE(SUM(r.amount), 0) AS commission,
                COALESCE(SUM(r.order_total), 0) AS order_total,
                SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) AS pending_referrals,
                SUM(CASE WHEN r.status IN ('unpaid', 'paid') THEN 1 ELSE 0 END) AS approved_referrals
         FROM affiliates a
         LEFT JOIN users u ON u.id = a.user_id
         LEFT JOIN affiliate_referrals r ON r.affiliate_id = a.id AND r.created_at BETWEEN ? AND ?
         GROUP BY a.id, a.referral_code, a.status, u.display_name, u.email
         ORDER BY commission DESC, referrals DESC
         LIMIT 25",
        $params,
        'affiliates'
    );

    $affiliateVisitSummary = table_exists('affiliate_visits')
        ? (db()->fetch(
            "SELECT COUNT(*) AS visits, SUM(CASE WHEN converted_at IS NOT NULL THEN 1 ELSE 0 END) AS conversions
             FROM affiliate_visits
             WHERE created_at BETWEEN ? AND ?",
            $params
        ) ?: ['visits' => 0, 'conversions' => 0])
        : ['visits' => 0, 'conversions' => 0];

    $recentOrders = dashboard_report_rows(
        "SELECT o.id, o.order_number, o.status, o.payment_status, o.currency, o.grand_total, o.created_at,
                COALESCE(u.display_name, u.email, o.guest_email, 'Guest') AS customer_name
         FROM orders o
         LEFT JOIN users u ON u.id = o.customer_id
         WHERE o.created_at BETWEEN ? AND ?
         ORDER BY o.created_at DESC
         LIMIT 10",
        $params
    );

    $managementRevenue = 0.0;
    foreach (dashboard_report_rows(
        "SELECT currency, SUM(amount) AS amount FROM vendor_store_management_services
         WHERE paid_at BETWEEN ? AND ? AND status IN ('active', 'paid', 'expired', 'cancelled')
         GROUP BY currency",
        $params,
        'vendor_store_management_services'
    ) as $serviceSales) {
        $managementRevenue += \App\PaymentService::convertAmount((float)$serviceSales['amount'], (string)$serviceSales['currency'], 'USD');
    }

    $gross = (float)($orderSummary['gross_sales'] ?? 0);
    $orders = (int)($orderSummary['total_orders'] ?? 0);
    $captured = (float)($paymentSummary['captured'] ?? 0);
    $visits = (int)($affiliateVisitSummary['visits'] ?? 0);
    $conversions = (int)($affiliateVisitSummary['conversions'] ?? 0);

    return [
        'views' => ['sales-analytics', 'revenue-report', 'vendor-performance', 'affiliate-performance'],
        'range' => $range,
        'summary' => [
            'total_orders' => $orders,
            'gross_sales' => $gross,
            'paid_revenue' => (float)($orderSummary['paid_revenue'] ?? 0) + $managementRevenue,
            'manage_store_revenue' => $managementRevenue,
            'captured_payments' => $captured,
            'discounts' => (float)($orderSummary['discounts'] ?? 0),
            'shipping' => (float)($orderSummary['shipping'] ?? 0),
            'taxes' => (float)($orderSummary['taxes'] ?? 0),
            'average_order_value' => (float)($orderSummary['average_order_value'] ?? 0),
            'customers' => (int)($orderSummary['customers'] ?? 0),
            'failed_payments' => (int)($paymentSummary['failed_count'] ?? 0),
            'failed_payment_amount' => (float)($paymentSummary['failed_amount'] ?? 0),
            'conversion_rate' => $visits > 0 ? ($conversions / $visits) * 100 : 0,
            'affiliate_visits' => $visits,
            'affiliate_conversions' => $conversions,
        ],
        'trend' => $trend,
        'currencyBreakdown' => $currencyBreakdown,
        'statusBreakdown' => $statusBreakdown,
        'topProducts' => $topProducts,
        'vendors' => $vendorRows,
        'affiliates' => $affiliateRows,
        'recentOrders' => $recentOrders,
    ];
}

$dashboardSearch = trim((string)($_GET['q'] ?? ''));
$dashboardPerPageOptions = [25, 50, 100];
$dashboardPerPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($dashboardPerPage, $dashboardPerPageOptions, true)) {
    $dashboardPerPage = 25;
}

function dashboard_file_size(string $path): int {
    return is_file($path) ? (int)filesize($path) : 0;
}

function dashboard_human_size(int $bytes): string {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    }

    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }

    return number_format($bytes) . ' B';
}

function dashboard_slug(string $value, string $fallback = 'item'): string {
    $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $value), '-'));
    return $slug !== '' ? $slug : $fallback . '-' . time();
}

function dashboard_unique_slug(string $table, string $slug, string $fallback, ?int $ignoreId = null): string {
    $base = dashboard_slug($slug, $fallback);
    $candidate = $base;
    $counter = 2;

    while (true) {
        $params = [$candidate];
        $sql = "SELECT id FROM {$table} WHERE slug = ?";
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        if (!db()->fetch($sql . ' LIMIT 1', $params)) {
            return $candidate;
        }
        $candidate = $base . '-' . $counter++;
    }
}

function dashboard_uploaded_content_media(string $field, array $allowed, string $folder): string {
    $upload = $_FILES[$field] ?? null;
    if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if (is_array($upload['error'] ?? null)) {
        $files = dashboard_uploaded_content_media_many($field, $allowed, $folder, 1);
        return (string)($files[0] ?? '');
    }

    return dashboard_store_content_upload($upload, $allowed, $folder);
}

function dashboard_uploaded_content_media_many(string $field, array $allowed, string $folder, int $limit = 10): array {
    $upload = $_FILES[$field] ?? null;
    if (!is_array($upload)) {
        return [];
    }

    if (!is_array($upload['error'] ?? null)) {
        $path = dashboard_uploaded_content_media($field, $allowed, $folder);
        return $path !== '' ? [$path] : [];
    }

    $paths = [];
    $count = count((array)$upload['error']);
    for ($i = 0; $i < $count && count($paths) < $limit; $i++) {
        $file = [
            'name' => $upload['name'][$i] ?? '',
            'type' => $upload['type'][$i] ?? '',
            'tmp_name' => $upload['tmp_name'][$i] ?? '',
            'error' => $upload['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $upload['size'][$i] ?? 0,
        ];
        if ((int)$file['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $paths[] = dashboard_store_content_upload($file, $allowed, $folder);
    }

    if ($count > $limit) {
        flash('warning', 'Only the first ' . $limit . ' media files were uploaded.');
    }

    return $paths;
}

function dashboard_content_media_type_from_path(string $path): string {
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($extension, ['mp4', 'webm', 'mov'], true) ? 'video' : 'image';
}

function dashboard_store_content_upload(array $upload, array $allowed, string $folder): string {
    if ((int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ((int)($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new \RuntimeException('Upload failed. Please choose a valid file and try again.');
    }

    $tmpName = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new \RuntimeException('Uploaded file could not be read.');
    }
    if ($size <= 0 || $size > 40 * 1024 * 1024) {
        throw new \RuntimeException('Media uploads must be smaller than 40MB.');
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)finfo_file($finfo, $tmpName);
            finfo_close($finfo);
        }
    }
    if ($mime === '' && function_exists('mime_content_type')) {
        $mime = (string)mime_content_type($tmpName);
    }
    if (!isset($allowed[$mime])) {
        throw new \RuntimeException('Unsupported media type.');
    }

    $uploadDir = APP_ROOT . '/public/uploads/' . trim($folder, '/');
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new \RuntimeException('Media upload directory could not be created.');
    }
    @chmod(APP_ROOT . '/public/uploads', 0777);
    @chmod($uploadDir, 0777);

    $filename = date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmpName, $uploadDir . '/' . $filename)) {
        throw new \RuntimeException('Media file could not be saved.');
    }

    return 'uploads/' . trim($folder, '/') . '/' . $filename;
}

function dashboard_unique_product_slug(string $name, ?int $ignoreId = null): string {
    $base = dashboard_slug($name, 'product');
    $slug = $base;
    $counter = 2;

    while (true) {
        $params = [$slug];
        $sql = 'SELECT id FROM products WHERE slug = ?';
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        $row = db()->fetch($sql . ' LIMIT 1', $params);
        if (!$row) {
            return $slug;
        }
        $slug = $base . '-' . $counter++;
    }
}

function dashboard_unique_admin_slug(string $table, string $source, ?int $ignoreId = null): string {
    $allowedTables = ['categories', 'brands', 'product_attributes'];
    if (!in_array($table, $allowedTables, true)) {
        throw new \RuntimeException('Invalid slug target.');
    }

    $base = dashboard_slug($source, 'item');
    $slug = $base;
    $counter = 2;

    while (true) {
        $params = [$slug];
        $sql = "SELECT id FROM {$table} WHERE slug = ?";
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }

        $row = db()->fetch($sql . ' LIMIT 1', $params);
        if (!$row) {
            return $slug;
        }

        $slug = $base . '-' . $counter++;
    }
}

function dashboard_taxonomy_file_upload(string $field, string $prefix): ?int {
    $upload = $_FILES[$field] ?? null;
    if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int)$upload['error'] !== UPLOAD_ERR_OK) {
        throw new \RuntimeException('Image upload failed.');
    }

    $tmpName = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new \RuntimeException('Uploaded image could not be read.');
    }

    if ($size <= 0 || $size > 8 * 1024 * 1024) {
        throw new \RuntimeException('Image must be smaller than 8MB.');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)finfo_file($finfo, $tmpName);
            finfo_close($finfo);
        }
    }
    if ($mime === '' && function_exists('mime_content_type')) {
        $mime = (string)mime_content_type($tmpName);
    }
    if (!isset($allowed[$mime])) {
        throw new \RuntimeException('Image must be a JPG, PNG, or WebP file.');
    }

    $uploadDir = APP_ROOT . '/public/uploads/taxonomy/' . date('Y/m');
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new \RuntimeException('Taxonomy upload directory could not be created.');
    }
    @chmod(APP_ROOT . '/public/uploads', 0777);
    @chmod(APP_ROOT . '/public/uploads/taxonomy', 0777);
    @chmod(dirname($uploadDir), 0777);
    @chmod($uploadDir, 0777);

    $filename = dashboard_slug($prefix, 'taxonomy') . '-' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
    $target = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmpName, $target)) {
        throw new \RuntimeException('Image could not be saved.');
    }

    $relativePath = 'uploads/taxonomy/' . date('Y/m') . '/' . $filename;
    [$width, $height] = @getimagesize($target) ?: [null, null];
    db()->query(
        "INSERT INTO files (owner_user_id, disk, path, original_name, mime_type, size_bytes, width, height, alt_text)
         VALUES (?, 'public', ?, ?, ?, ?, ?, ?, ?)",
        [$_SESSION['user_id'] ?? null, $relativePath, (string)($upload['name'] ?? $filename), $mime, $size, $width, $height, $prefix]
    );

    return (int)db()->lastInsertId();
}

function dashboard_save_taxonomy(): void {
    validateCsrf();
    $taxonomyType = (string)($_POST['taxonomy_type'] ?? '');
    $recordId = max(0, (int)($_POST['record_id'] ?? 0));

    if (in_array($taxonomyType, ['category', 'subcategory'], true)) {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('Category name is required.');
        }

        $parentId = $taxonomyType === 'subcategory' ? ((int)($_POST['parent_id'] ?? 0) ?: null) : null;
        if ($taxonomyType === 'subcategory' && !$parentId) {
            throw new \RuntimeException('Subcategory parent category is required.');
        }

        $old = $recordId > 0 ? db()->fetch('SELECT * FROM categories WHERE id = ? LIMIT 1', [$recordId]) : null;
        $slug = dashboard_unique_admin_slug('categories', (string)($_POST['slug'] ?? $name), $recordId ?: null);
        $imageFileId = dashboard_taxonomy_file_upload('image', $name);
        $params = [
            $parentId,
            $name,
            $slug,
            trim((string)($_POST['description'] ?? '')) ?: null,
            (int)($_POST['sort_order'] ?? 0),
            (string)($_POST['is_active'] ?? '0') === '1' ? 1 : 0,
        ];

        if ($recordId > 0 && $old) {
            db()->query(
                "UPDATE categories
                 SET parent_id = ?, name = ?, slug = ?, description = ?, sort_order = ?, is_active = ?, image_file_id = COALESCE(?, image_file_id)
                 WHERE id = ?",
                [...$params, $imageFileId, $recordId]
            );
        } else {
            db()->query(
                "INSERT INTO categories (parent_id, name, slug, description, sort_order, is_active, image_file_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [...$params, $imageFileId]
            );
            $recordId = (int)db()->lastInsertId();
        }

        audit($old ? 'taxonomy_category_updated' : 'taxonomy_category_created', 'categories', (string)$recordId, $old ?: [], ['name' => $name, 'parent_id' => $parentId]);
        flash('success', $taxonomyType === 'subcategory' ? 'Subcategory saved.' : 'Category saved.');
        redirect('dashboard/' . ($taxonomyType === 'subcategory' ? 'subcategories' : 'categories') . '?edit=' . $recordId);
    }

    if ($taxonomyType === 'brand') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('Brand name is required.');
        }

        $old = $recordId > 0 ? db()->fetch('SELECT * FROM brands WHERE id = ? LIMIT 1', [$recordId]) : null;
        $slug = dashboard_unique_admin_slug('brands', (string)($_POST['slug'] ?? $name), $recordId ?: null);
        $logoFileId = dashboard_taxonomy_file_upload('logo', $name);

        if ($recordId > 0 && $old) {
            db()->query(
                "UPDATE brands SET name = ?, slug = ?, description = ?, logo_file_id = COALESCE(?, logo_file_id) WHERE id = ?",
                [$name, $slug, trim((string)($_POST['description'] ?? '')) ?: null, $logoFileId, $recordId]
            );
        } else {
            db()->query(
                "INSERT INTO brands (name, slug, description, logo_file_id) VALUES (?, ?, ?, ?)",
                [$name, $slug, trim((string)($_POST['description'] ?? '')) ?: null, $logoFileId]
            );
            $recordId = (int)db()->lastInsertId();
        }

        audit($old ? 'taxonomy_brand_updated' : 'taxonomy_brand_created', 'brands', (string)$recordId, $old ?: [], ['name' => $name]);
        flash('success', 'Brand saved.');
        redirect('dashboard/brands?edit=' . $recordId);
    }

    if ($taxonomyType === 'attribute') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('Attribute name is required.');
        }

        $type = (string)($_POST['type'] ?? 'select');
        if (!in_array($type, ['select', 'text', 'color', 'image', 'button'], true)) {
            $type = 'select';
        }

        $old = $recordId > 0 ? db()->fetch('SELECT * FROM product_attributes WHERE id = ? LIMIT 1', [$recordId]) : null;
        $slug = dashboard_unique_admin_slug('product_attributes', (string)($_POST['slug'] ?? $name), $recordId ?: null);

        if ($recordId > 0 && $old) {
            db()->query(
                "UPDATE product_attributes SET name = ?, slug = ?, type = ?, is_global = ? WHERE id = ?",
                [$name, $slug, $type, isset($_POST['is_global']) ? 1 : 0, $recordId]
            );
        } else {
            db()->query(
                "INSERT INTO product_attributes (name, slug, type, is_global) VALUES (?, ?, ?, ?)",
                [$name, $slug, $type, isset($_POST['is_global']) ? 1 : 0]
            );
            $recordId = (int)db()->lastInsertId();
        }

        $values = preg_split('/\r\n|\r|\n|,/', (string)($_POST['values'] ?? '')) ?: [];
        $sort = 0;
        db()->query('DELETE FROM product_attribute_values WHERE attribute_id = ?', [$recordId]);
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value === '') {
                continue;
            }
            $valueSlug = dashboard_slug($value, 'value');
            db()->query(
                "INSERT INTO product_attribute_values (attribute_id, value, slug, sort_order)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value), sort_order = VALUES(sort_order)",
                [$recordId, $value, $valueSlug, $sort++]
            );
        }

        audit($old ? 'taxonomy_attribute_updated' : 'taxonomy_attribute_created', 'product_attributes', (string)$recordId, $old ?: [], ['name' => $name, 'type' => $type]);
        flash('success', 'Attribute saved.');
        redirect('dashboard/attributes?edit=' . $recordId);
    }

    throw new \RuntimeException('Unsupported taxonomy type.');
}

function dashboard_update_withdrawal_status(): void {
    validateCsrf();
    $type = (string)($_POST['withdrawal_type'] ?? '');
    $withdrawalId = max(0, (int)($_POST['withdrawal_id'] ?? 0));
    $status = (string)($_POST['status'] ?? '');
    $note = trim((string)($_POST['note'] ?? ''));

    if (!in_array($type, ['vendor', 'affiliate'], true)) {
        throw new \RuntimeException('Invalid withdrawal type.');
    }
    if ($withdrawalId <= 0) {
        throw new \RuntimeException('Invalid withdrawal record.');
    }
    if (!in_array($status, ['approved', 'rejected', 'paid', 'cancelled'], true)) {
        throw new \RuntimeException('Invalid withdrawal status.');
    }

    $table = $type === 'vendor' ? 'vendor_withdrawals' : 'affiliate_withdrawals';
    if (!table_exists($table)) {
        throw new \RuntimeException('Withdrawal table is not available.');
    }

    $old = db()->fetch("SELECT * FROM {$table} WHERE id = ? LIMIT 1", [$withdrawalId]);
    if (!$old) {
        throw new \RuntimeException('Withdrawal record was not found.');
    }

    db()->query(
        "UPDATE {$table}
         SET status = ?, note = ?, processed_by = ?, processed_at = ?
         WHERE id = ?",
        [$status, $note !== '' ? $note : ($old['note'] ?? null), $_SESSION['user_id'] ?? null, sql_now(), $withdrawalId]
    );

    audit('withdrawal_status_updated', $table, (string)$withdrawalId, $old, ['status' => $status, 'note' => $note]);
    flash('success', 'Withdrawal status updated.');
    redirect('dashboard/' . (string)($_POST['return_view'] ?? 'pending-withdrawals'));
}

function dashboard_save_shipping_zone(): void {
    validateCsrf();
    $zoneId = max(0, (int)($_POST['zone_id'] ?? 0));
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        throw new \RuntimeException('Shipping zone name is required.');
    }

    $old = $zoneId > 0 ? db()->fetch('SELECT * FROM shipping_zones WHERE id = ? LIMIT 1', [$zoneId]) : null;
    $params = [
        (int)($_POST['vendor_id'] ?? 0) ?: null,
        $name,
        (int)($_POST['sort_order'] ?? 0),
        (string)($_POST['is_active'] ?? '0') === '1' ? 1 : 0,
    ];

    if ($zoneId > 0 && $old) {
        db()->query('UPDATE shipping_zones SET vendor_id = ?, name = ?, sort_order = ?, is_active = ? WHERE id = ?', [...$params, $zoneId]);
    } else {
        db()->query('INSERT INTO shipping_zones (vendor_id, name, sort_order, is_active) VALUES (?, ?, ?, ?)', $params);
        $zoneId = (int)db()->lastInsertId();
    }

    audit($old ? 'shipping_zone_updated' : 'shipping_zone_created', 'shipping_zones', (string)$zoneId, $old ?: [], ['name' => $name]);
    flash('success', 'Shipping zone saved.');
    redirect('dashboard/shipping-zones?edit=' . $zoneId);
}

function dashboard_save_shipping_method(): void {
    validateCsrf();
    $methodId = max(0, (int)($_POST['method_id'] ?? 0));
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        throw new \RuntimeException('Shipping method name is required.');
    }

    $calculationType = (string)($_POST['calculation_type'] ?? 'flat_rate');
    if (!in_array($calculationType, ['flat_rate', 'free_shipping', 'table_rate', 'distance_rate', 'carrier_api', 'local_pickup'], true)) {
        $calculationType = 'flat_rate';
    }

    $old = $methodId > 0 ? db()->fetch('SELECT * FROM shipping_methods WHERE id = ? LIMIT 1', [$methodId]) : null;
    $settings = json_encode([
        'delivery_estimate' => trim((string)($_POST['delivery_estimate'] ?? '')),
        'notes' => trim((string)($_POST['notes'] ?? '')),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $params = [
        (int)($_POST['zone_id'] ?? 0) ?: null,
        (int)($_POST['carrier_id'] ?? 0) ?: null,
        (int)($_POST['vendor_id'] ?? 0) ?: null,
        dashboard_slug((string)($_POST['code'] ?? $name), 'shipping-method'),
        $name,
        $calculationType,
        max(0, (float)($_POST['base_cost'] ?? 0)),
        trim((string)($_POST['min_order_amount'] ?? '')) === '' ? null : max(0, (float)$_POST['min_order_amount']),
        $settings,
        (string)($_POST['is_active'] ?? '0') === '1' ? 1 : 0,
    ];

    if ($methodId > 0 && $old) {
        db()->query(
            'UPDATE shipping_methods SET zone_id = ?, carrier_id = ?, vendor_id = ?, code = ?, name = ?, calculation_type = ?, base_cost = ?, min_order_amount = ?, settings = ?, is_active = ? WHERE id = ?',
            [...$params, $methodId]
        );
    } else {
        db()->query(
            'INSERT INTO shipping_methods (zone_id, carrier_id, vendor_id, code, name, calculation_type, base_cost, min_order_amount, settings, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $params
        );
        $methodId = (int)db()->lastInsertId();
    }

    $conditionType = (string)($_POST['condition_type'] ?? 'none');
    if (!in_array($conditionType, ['none', 'weight', 'subtotal', 'quantity', 'distance'], true)) {
        $conditionType = 'none';
    }
    db()->query('DELETE FROM shipping_rates WHERE method_id = ?', [$methodId]);
    db()->query(
        'INSERT INTO shipping_rates (method_id, condition_type, min_value, max_value, cost, per_item_cost, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [
            $methodId,
            $conditionType,
            trim((string)($_POST['min_value'] ?? '')) === '' ? null : (float)$_POST['min_value'],
            trim((string)($_POST['max_value'] ?? '')) === '' ? null : (float)$_POST['max_value'],
            max(0, (float)($_POST['cost'] ?? $_POST['base_cost'] ?? 0)),
            max(0, (float)($_POST['per_item_cost'] ?? 0)),
            (int)($_POST['rate_sort_order'] ?? 0),
        ]
    );

    audit($old ? 'shipping_method_updated' : 'shipping_method_created', 'shipping_methods', (string)$methodId, $old ?: [], ['name' => $name]);
    flash('success', 'Shipping rate saved.');
    redirect('dashboard/shipping-rates?edit=' . $methodId);
}

function dashboard_save_delivery_partner(): void {
    validateCsrf();
    $partnerId = max(0, (int)($_POST['partner_id'] ?? 0));
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        throw new \RuntimeException('Delivery partner name is required.');
    }

    $old = $partnerId > 0 ? db()->fetch('SELECT * FROM delivery_partners WHERE id = ? LIMIT 1', [$partnerId]) : null;
    $params = [
        (int)($_POST['carrier_id'] ?? 0) ?: null,
        $name,
        dashboard_slug((string)($_POST['code'] ?? $name), 'partner'),
        trim((string)($_POST['contact_email'] ?? '')) ?: null,
        trim((string)($_POST['contact_phone'] ?? '')) ?: null,
        trim((string)($_POST['service_regions'] ?? '')) ?: null,
        trim((string)($_POST['tracking_url_template'] ?? '')) ?: null,
        (string)($_POST['is_active'] ?? '0') === '1' ? 1 : 0,
    ];

    if ($partnerId > 0 && $old) {
        db()->query('UPDATE delivery_partners SET carrier_id = ?, name = ?, code = ?, contact_email = ?, contact_phone = ?, service_regions = ?, tracking_url_template = ?, is_active = ? WHERE id = ?', [...$params, $partnerId]);
    } else {
        db()->query('INSERT INTO delivery_partners (carrier_id, name, code, contact_email, contact_phone, service_regions, tracking_url_template, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $params);
        $partnerId = (int)db()->lastInsertId();
    }

    audit($old ? 'delivery_partner_updated' : 'delivery_partner_created', 'delivery_partners', (string)$partnerId, $old ?: [], ['name' => $name]);
    flash('success', 'Delivery partner saved.');
    redirect('dashboard/delivery-partners?edit=' . $partnerId);
}

function dashboard_save_aramex_settings(): void {
    validateCsrf();
    $fields = [
        'enabled', 'environment', 'account_number', 'account_pin', 'account_entity', 'account_country_code',
        'username', 'password', 'api_base_url', 'shipper_name', 'shipper_phone', 'shipper_email',
        'shipper_address', 'shipper_city', 'shipper_state', 'shipper_postcode', 'shipper_country_code',
    ];
    foreach ($fields as $field) {
        $value = trim((string)($_POST[$field] ?? ''));
        db()->query(
            "INSERT INTO settings (scope, scope_id, setting_key, setting_value)
             VALUES ('shipping_aramex', 0, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP",
            [$field, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
        );
    }
    audit('aramex_settings_updated', 'settings', 'shipping_aramex', [], ['keys' => $fields]);
    flash('success', 'Aramex settings saved.');
    redirect('dashboard/aramex-api-settings');
}

function dashboard_save_content_album(): void {
    validateCsrf();
    $albumId = max(0, (int)($_POST['album_id'] ?? 0));
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
        throw new \RuntimeException('Album title is required.');
    }
    $old = $albumId > 0 ? db()->fetch('SELECT * FROM content_albums WHERE id = ? LIMIT 1', [$albumId]) : null;
    $status = in_array(($_POST['status'] ?? 'draft'), ['draft', 'published'], true) ? (string)$_POST['status'] : 'draft';
    $cover = dashboard_uploaded_content_media('cover_media', ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'], 'gallery');
    $slug = dashboard_unique_slug('content_albums', (string)($_POST['slug'] ?? $title), 'album', $albumId ?: null);
    $params = [$title, $slug, trim((string)($_POST['description'] ?? '')) ?: null, $cover !== '' ? $cover : (($old['cover_media_url'] ?? '') ?: null), $status, (int)($_POST['sort_order'] ?? 0)];
    if ($albumId > 0 && $old) {
        db()->query('UPDATE content_albums SET title = ?, slug = ?, description = ?, cover_media_url = ?, status = ?, sort_order = ? WHERE id = ?', [...$params, $albumId]);
    } else {
        db()->query('INSERT INTO content_albums (title, slug, description, cover_media_url, status, sort_order) VALUES (?, ?, ?, ?, ?, ?)', $params);
        $albumId = (int)db()->lastInsertId();
    }
    $albumUploads = dashboard_uploaded_content_media_many('album_media', ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'], 'gallery', 10);
    $createdMedia = 0;
    foreach ($albumUploads as $index => $path) {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $itemType = in_array($extension, ['mp4', 'webm', 'mov'], true) ? 'video' : 'image';
        db()->query(
            'INSERT INTO content_gallery_items (album_id, title, media_type, media_url, status, sort_order) VALUES (?, ?, ?, ?, ?, ?)',
            [$albumId, $title . ' ' . ($index + 1), $itemType, $path, $status, (int)($_POST['sort_order'] ?? 0) + $index + 1]
        );
        $createdMedia++;
    }
    audit($old ? 'content_album_updated' : 'content_album_created', 'content_albums', (string)$albumId, $old ?: [], ['title' => $title, 'status' => $status]);
    $message = $createdMedia > 0 ? 'Album saved and ' . $createdMedia . ' media files uploaded.' : 'Album saved.';
    flash('success', $message);
    if (dashboard_wants_json()) {
        dashboard_json(['ok' => true, 'message' => $message, 'id' => $albumId, 'uploaded' => $createdMedia, 'redirectPath' => 'albums?edit=' . $albumId]);
    }
    redirect('dashboard/albums?edit=' . $albumId);
}

function dashboard_save_gallery_item(): void {
    validateCsrf();
    $itemId = max(0, (int)($_POST['item_id'] ?? 0));
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
        throw new \RuntimeException('Gallery item title is required.');
    }
    $old = $itemId > 0 ? db()->fetch('SELECT * FROM content_gallery_items WHERE id = ? LIMIT 1', [$itemId]) : null;
    $selectedMediaType = in_array(($_POST['media_type'] ?? 'image'), ['image', 'video'], true) ? (string)$_POST['media_type'] : 'image';
    $allowed = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
    ];
    $mediaUploads = dashboard_uploaded_content_media_many('media_file', $allowed, 'gallery', 10);
    $media = (string)($mediaUploads[0] ?? '');
    if ($media === '' && !$old) {
        throw new \RuntimeException('Please upload an image or video.');
    }
    $thumb = dashboard_uploaded_content_media('thumbnail_file', ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'], 'gallery');
    $status = in_array(($_POST['status'] ?? 'draft'), ['draft', 'published'], true) ? (string)$_POST['status'] : 'draft';
    $mediaType = $media !== '' ? dashboard_content_media_type_from_path($media) : (string)($old['media_type'] ?? $selectedMediaType);
    $params = [(int)($_POST['album_id'] ?? 0) ?: null, $title, $mediaType, $media !== '' ? $media : ($old['media_url'] ?? ''), $thumb !== '' ? $thumb : (($old['thumbnail_url'] ?? '') ?: null), trim((string)($_POST['caption'] ?? '')) ?: null, $status, (int)($_POST['sort_order'] ?? 0)];
    if ($itemId > 0 && $old) {
        db()->query('UPDATE content_gallery_items SET album_id = ?, title = ?, media_type = ?, media_url = ?, thumbnail_url = ?, caption = ?, status = ?, sort_order = ? WHERE id = ?', [...$params, $itemId]);
    } else {
        db()->query('INSERT INTO content_gallery_items (album_id, title, media_type, media_url, thumbnail_url, caption, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $params);
        $itemId = (int)db()->lastInsertId();
    }
    $createdMedia = $media !== '' ? 1 : 0;
    if (count($mediaUploads) > 1) {
        foreach (array_slice($mediaUploads, 1) as $index => $path) {
            $extraMediaType = dashboard_content_media_type_from_path($path);
            db()->query(
                'INSERT INTO content_gallery_items (album_id, title, media_type, media_url, thumbnail_url, caption, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [(int)($_POST['album_id'] ?? 0) ?: null, $title . ' ' . ($index + 2), $extraMediaType, $path, $thumb !== '' ? $thumb : null, trim((string)($_POST['caption'] ?? '')) ?: null, $status, (int)($_POST['sort_order'] ?? 0) + $index + 1]
            );
            $createdMedia++;
        }
    }
    audit($old ? 'gallery_item_updated' : 'gallery_item_created', 'content_gallery_items', (string)$itemId, $old ?: [], ['title' => $title, 'status' => $status]);
    $message = $createdMedia > 1 ? $createdMedia . ' gallery items uploaded.' : 'Gallery item saved.';
    flash('success', $message);
    if (dashboard_wants_json()) {
        dashboard_json(['ok' => true, 'message' => $message, 'id' => $itemId, 'uploaded' => $createdMedia, 'redirectPath' => 'gallery?edit=' . $itemId]);
    }
    redirect('dashboard/gallery?edit=' . $itemId);
}

function dashboard_save_content_post(): void {
    validateCsrf();
    $postId = max(0, (int)($_POST['post_id'] ?? 0));
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
        throw new \RuntimeException('Post title is required.');
    }
    $old = $postId > 0 ? db()->fetch('SELECT * FROM content_posts WHERE id = ? LIMIT 1', [$postId]) : null;
    $image = dashboard_uploaded_content_media('featured_image', ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'], 'blog');
    $status = in_array(($_POST['status'] ?? 'draft'), ['draft', 'published', 'archived'], true) ? (string)$_POST['status'] : 'draft';
    $publishedAt = trim((string)($_POST['published_at'] ?? ''));
    $publishedAt = $publishedAt !== '' ? str_replace('T', ' ', $publishedAt) . (strlen($publishedAt) === 16 ? ':00' : '') : ($status === 'published' ? (($old['published_at'] ?? null) ?: sql_now()) : null);
    $slug = dashboard_unique_slug('content_posts', (string)($_POST['slug'] ?? $title), 'post', $postId ?: null);
    $params = [$title, $slug, trim((string)($_POST['excerpt'] ?? '')) ?: null, trim((string)($_POST['body'] ?? '')) ?: null, $image !== '' ? $image : (($old['featured_image_url'] ?? '') ?: null), trim((string)($_POST['category'] ?? '')) ?: null, trim((string)($_POST['author_name'] ?? '')) ?: 'Seller Africa', $status, $publishedAt];
    if ($postId > 0 && $old) {
        db()->query('UPDATE content_posts SET title = ?, slug = ?, excerpt = ?, body = ?, featured_image_url = ?, category = ?, author_name = ?, status = ?, published_at = ? WHERE id = ?', [...$params, $postId]);
    } else {
        db()->query('INSERT INTO content_posts (title, slug, excerpt, body, featured_image_url, category, author_name, status, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', $params);
        $postId = (int)db()->lastInsertId();
    }
    audit($old ? 'content_post_updated' : 'content_post_created', 'content_posts', (string)$postId, $old ?: [], ['title' => $title, 'status' => $status]);
    flash('success', 'Blog post saved.');
    if (dashboard_wants_json()) {
        dashboard_json(['ok' => true, 'message' => 'Blog post saved.', 'id' => $postId, 'redirectPath' => 'blog-posts?edit=' . $postId]);
    }
    redirect('dashboard/blog-posts?edit=' . $postId);
}

function dashboard_delete_content_record(): void {
    validateCsrf();
    $type = (string)($_POST['content_type'] ?? '');
    $id = max(0, (int)($_POST['record_id'] ?? 0));
    $map = ['album' => ['content_albums', 'albums'], 'gallery' => ['content_gallery_items', 'gallery'], 'post' => ['content_posts', 'blog-posts']];
    if ($id <= 0 || !isset($map[$type])) {
        throw new \RuntimeException('Invalid content delete request.');
    }
    [$table, $returnView] = $map[$type];
    $old = db()->fetch("SELECT * FROM {$table} WHERE id = ? LIMIT 1", [$id]);
    if ($old) {
        db()->query("DELETE FROM {$table} WHERE id = ?", [$id]);
        audit('content_record_deleted', $table, (string)$id, $old, []);
    }
    flash('success', 'Content deleted.');
    redirect('dashboard/' . $returnView);
}

function dashboard_update_shipment_tracking(): void {
    validateCsrf();
    $shipmentId = max(0, (int)($_POST['shipment_id'] ?? 0));
    $status = (string)($_POST['status'] ?? 'pending');
    if (!in_array($status, ['pending','label_created','picked_up','in_transit','out_for_delivery','delivered','failed','returned','cancelled'], true)) {
        $status = 'pending';
    }
    $old = db()->fetch('SELECT * FROM shipments WHERE id = ? LIMIT 1', [$shipmentId]);
    if (!$old) {
        throw new \RuntimeException('Shipment not found.');
    }
    db()->query(
        'UPDATE shipments SET carrier_id = ?, tracking_number = ?, tracking_url = ?, status = ?, shipped_at = ?, delivered_at = ?, cost = ? WHERE id = ?',
        [
            (int)($_POST['carrier_id'] ?? 0) ?: null,
            trim((string)($_POST['tracking_number'] ?? '')) ?: null,
            trim((string)($_POST['tracking_url'] ?? '')) ?: null,
            $status,
            trim((string)($_POST['shipped_at'] ?? '')) ?: null,
            trim((string)($_POST['delivered_at'] ?? '')) ?: null,
            trim((string)($_POST['cost'] ?? '')) === '' ? null : (float)$_POST['cost'],
            $shipmentId,
        ]
    );
    $orderNotice = db()->fetch(
        "SELECT o.order_number, COALESCE(u.email, o.guest_email) AS email, COALESCE(NULLIF(u.display_name, ''), o.guest_email) AS name
         FROM shipments s
         INNER JOIN orders o ON o.id = s.order_id
         LEFT JOIN users u ON u.id = o.customer_id
         WHERE s.id = ?
         LIMIT 1",
        [$shipmentId]
    );
    if ($orderNotice) {
        \App\NotificationService::orderUpdated(
            $orderNotice,
            'Shipment update',
            [
                'Shipment status' => ucwords(str_replace('_', ' ', $status)),
                'Previous shipment status' => ucwords(str_replace('_', ' ', (string)($old['status'] ?? ''))),
                'Carrier tracking' => trim((string)($_POST['tracking_number'] ?? '')) ?: 'Not added',
            ],
            trim((string)($_POST['tracking_number'] ?? '')),
            trim((string)($_POST['tracking_url'] ?? ''))
        );
    }
    audit('shipment_tracking_updated', 'shipments', (string)$shipmentId, $old, ['status' => $status]);
    flash('success', 'Shipment tracking updated.');
    redirect('dashboard/shipment-tracking?edit=' . $shipmentId);
}

function dashboard_update_affiliate_status(): void {
    validateCsrf();
    $affiliateId = max(0, (int)($_POST['affiliate_id'] ?? 0));
    $status = (string)($_POST['status'] ?? '');
    if ($affiliateId <= 0) {
        throw new \RuntimeException('Invalid affiliate record.');
    }
    if (!in_array($status, ['pending', 'active', 'rejected', 'inactive', 'blocked'], true)) {
        throw new \RuntimeException('Invalid affiliate status.');
    }

    $old = db()->fetch('SELECT * FROM affiliates WHERE id = ? LIMIT 1', [$affiliateId]);
    if (!$old) {
        throw new \RuntimeException('Affiliate was not found.');
    }

    db()->query('UPDATE affiliates SET status = ? WHERE id = ?', [$status, $affiliateId]);
    audit('affiliate_status_updated', 'affiliates', (string)$affiliateId, $old, ['status' => $status]);
    flash('success', 'Affiliate status updated.');
    redirect('dashboard/affiliates');
}

function dashboard_save_commission_rule(): void {
    validateCsrf();
    $ruleId = max(0, (int)($_POST['rule_id'] ?? 0));
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        throw new \RuntimeException('Commission rule name is required.');
    }

    $ruleType = (string)($_POST['rule_type'] ?? 'global');
    if (!in_array($ruleType, ['global', 'affiliate', 'vendor', 'category', 'campaign'], true)) {
        $ruleType = 'global';
    }
    $rateType = (string)($_POST['rate_type'] ?? 'percentage');
    if (!in_array($rateType, ['percentage', 'flat'], true)) {
        $rateType = 'percentage';
    }
    $startsAt = trim((string)($_POST['starts_at'] ?? ''));
    $endsAt = trim((string)($_POST['ends_at'] ?? ''));

    $old = $ruleId > 0 ? db()->fetch('SELECT * FROM affiliate_commission_rules WHERE id = ? LIMIT 1', [$ruleId]) : null;
    $params = [
        $name,
        $ruleType,
        (int)($_POST['target_id'] ?? 0) ?: null,
        $rateType,
        max(0, (float)($_POST['commission_rate'] ?? 0)),
        strtoupper(substr(trim((string)($_POST['currency'] ?? 'USD')), 0, 3)) ?: 'USD',
        trim((string)($_POST['min_order_amount'] ?? '')) === '' ? null : max(0, (float)$_POST['min_order_amount']),
        (int)($_POST['priority'] ?? 10),
        $startsAt !== '' ? str_replace('T', ' ', $startsAt) : null,
        $endsAt !== '' ? str_replace('T', ' ', $endsAt) : null,
        (string)($_POST['is_active'] ?? '0') === '1' ? 1 : 0,
        trim((string)($_POST['notes'] ?? '')) ?: null,
    ];

    if ($ruleId > 0 && $old) {
        db()->query(
            'UPDATE affiliate_commission_rules
             SET name = ?, rule_type = ?, target_id = ?, rate_type = ?, commission_rate = ?, currency = ?, min_order_amount = ?, priority = ?, starts_at = ?, ends_at = ?, is_active = ?, notes = ?
             WHERE id = ?',
            [...$params, $ruleId]
        );
    } else {
        db()->query(
            'INSERT INTO affiliate_commission_rules (name, rule_type, target_id, rate_type, commission_rate, currency, min_order_amount, priority, starts_at, ends_at, is_active, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $params
        );
        $ruleId = (int)db()->lastInsertId();
    }

    audit($old ? 'affiliate_commission_rule_updated' : 'affiliate_commission_rule_created', 'affiliate_commission_rules', (string)$ruleId, $old ?: [], ['name' => $name]);
    flash('success', 'Commission rule saved.');
    redirect('dashboard/commission-rules?edit=' . $ruleId);
}

function dashboard_update_affiliate_referral_status(): void {
    validateCsrf();
    $referralId = max(0, (int)($_POST['referral_id'] ?? 0));
    $status = (string)($_POST['status'] ?? '');
    if ($referralId <= 0) {
        throw new \RuntimeException('Invalid commission record.');
    }
    if (!in_array($status, ['pending', 'unpaid', 'paid', 'rejected', 'cancelled'], true)) {
        throw new \RuntimeException('Invalid commission status.');
    }

    $old = db()->fetch('SELECT * FROM affiliate_referrals WHERE id = ? LIMIT 1', [$referralId]);
    if (!$old) {
        throw new \RuntimeException('Commission record was not found.');
    }

    db()->query(
        'UPDATE affiliate_referrals SET status = ?, paid_at = CASE WHEN ? = "paid" THEN COALESCE(paid_at, ?) ELSE paid_at END WHERE id = ?',
        [$status, $status, sql_now(), $referralId]
    );
    audit('affiliate_commission_status_updated', 'affiliate_referrals', (string)$referralId, $old, ['status' => $status]);
    flash('success', 'Commission status updated.');
    redirect('dashboard/' . (string)($_POST['return_view'] ?? 'pending-commissions'));
}

function dashboard_update_affiliate_payout_status(): void {
    validateCsrf();
    $payoutId = max(0, (int)($_POST['payout_id'] ?? 0));
    $status = (string)($_POST['status'] ?? '');
    $reference = trim((string)($_POST['payment_reference'] ?? ''));
    if ($payoutId <= 0) {
        throw new \RuntimeException('Invalid payout record.');
    }
    if (!in_array($status, ['pending', 'processing', 'paid', 'failed', 'cancelled'], true)) {
        throw new \RuntimeException('Invalid payout status.');
    }

    $old = db()->fetch('SELECT * FROM affiliate_payouts WHERE id = ? LIMIT 1', [$payoutId]);
    if (!$old) {
        throw new \RuntimeException('Affiliate payout was not found.');
    }

    db()->query(
        'UPDATE affiliate_payouts SET status = ?, payment_reference = COALESCE(NULLIF(?, ""), payment_reference), paid_at = CASE WHEN ? = "paid" THEN COALESCE(paid_at, ?) ELSE paid_at END WHERE id = ?',
        [$status, $reference, $status, sql_now(), $payoutId]
    );
    audit('affiliate_payout_status_updated', 'affiliate_payouts', (string)$payoutId, $old, ['status' => $status, 'payment_reference' => $reference]);
    flash('success', 'Affiliate payout status updated.');
    redirect('dashboard/affiliate-payouts');
}

function dashboard_update_refund_status(): void {
    validateCsrf();
    $refundId = max(0, (int)($_POST['refund_id'] ?? 0));
    $status = (string)($_POST['status'] ?? '');
    if ($refundId <= 0) {
        throw new \RuntimeException('Invalid refund request.');
    }
    if (!in_array($status, ['requested', 'approved', 'rejected', 'processed', 'failed'], true)) {
        throw new \RuntimeException('Invalid refund status.');
    }

    $old = db()->fetch('SELECT * FROM refunds WHERE id = ? LIMIT 1', [$refundId]);
    if (!$old) {
        throw new \RuntimeException('Refund request was not found.');
    }

    $reason = trim((string)($_POST['reason'] ?? ''));
    db()->query(
        'UPDATE refunds SET status = ?, reason = COALESCE(NULLIF(?, ""), reason), processed_by = ?, processed_at = CASE WHEN ? IN ("approved", "rejected", "processed", "failed") THEN COALESCE(processed_at, ?) ELSE processed_at END WHERE id = ?',
        [$status, $reason, $_SESSION['user_id'] ?? null, $status, sql_now(), $refundId]
    );
    audit('refund_status_updated', 'refunds', (string)$refundId, $old, ['status' => $status, 'reason' => $reason]);
    flash('success', 'Refund request updated.');
    redirect('dashboard/refund-requests');
}

function dashboard_update_return_status(): void {
    validateCsrf();
    $returnId = max(0, (int)($_POST['return_id'] ?? 0));
    $status = (string)($_POST['status'] ?? '');
    if ($returnId <= 0) {
        throw new \RuntimeException('Invalid return request.');
    }
    if (!in_array($status, ['requested', 'approved', 'rejected', 'received', 'resolved', 'cancelled'], true)) {
        throw new \RuntimeException('Invalid return status.');
    }

    $old = db()->fetch('SELECT * FROM return_requests WHERE id = ? LIMIT 1', [$returnId]);
    if (!$old) {
        throw new \RuntimeException('Return request was not found.');
    }

    $details = trim((string)($_POST['details'] ?? ''));
    db()->query(
        'UPDATE return_requests SET status = ?, details = COALESCE(NULLIF(?, ""), details) WHERE id = ?',
        [$status, $details, $returnId]
    );
    audit('return_request_status_updated', 'return_requests', (string)$returnId, $old, ['status' => $status, 'details' => $details]);
    flash('success', 'Return request updated.');
    redirect('dashboard/return-requests');
}

function dashboard_save_vendor_dispute(): void {
    validateCsrf();
    $disputeId = max(0, (int)($_POST['dispute_id'] ?? 0));
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
        throw new \RuntimeException('Dispute title is required.');
    }

    $status = (string)($_POST['status'] ?? 'open');
    if (!in_array($status, ['open', 'under_review', 'awaiting_vendor', 'awaiting_buyer', 'resolved', 'rejected', 'closed'], true)) {
        $status = 'open';
    }
    $priority = (string)($_POST['priority'] ?? 'normal');
    if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
        $priority = 'normal';
    }

    $old = $disputeId > 0 ? db()->fetch('SELECT * FROM vendor_disputes WHERE id = ? LIMIT 1', [$disputeId]) : null;
    $params = [
        (int)($_POST['vendor_id'] ?? 0) ?: null,
        (int)($_POST['order_id'] ?? 0) ?: null,
        (int)($_POST['opened_by'] ?? 0) ?: ($_SESSION['user_id'] ?? null),
        $title,
        trim((string)($_POST['description'] ?? '')) ?: null,
        $status,
        $priority,
        trim((string)($_POST['admin_note'] ?? '')) ?: null,
        $_SESSION['user_id'] ?? null,
        in_array($status, ['resolved', 'rejected', 'closed'], true) ? sql_now() : null,
    ];

    if ($disputeId > 0 && $old) {
        db()->query(
            'UPDATE vendor_disputes SET vendor_id = ?, order_id = ?, opened_by = ?, title = ?, description = ?, status = ?, priority = ?, admin_note = ?, assigned_to = ?, resolved_at = COALESCE(?, resolved_at) WHERE id = ?',
            [...$params, $disputeId]
        );
    } else {
        db()->query(
            'INSERT INTO vendor_disputes (vendor_id, order_id, opened_by, title, description, status, priority, admin_note, assigned_to, resolved_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $params
        );
        $disputeId = (int)db()->lastInsertId();
    }

    audit($old ? 'vendor_dispute_updated' : 'vendor_dispute_created', 'vendor_disputes', (string)$disputeId, $old ?: [], ['title' => $title, 'status' => $status]);
    flash('success', 'Vendor dispute saved.');
    redirect('dashboard/vendor-disputes?edit=' . $disputeId);
}

function dashboard_update_vendor_dispute_status(): void {
    validateCsrf();
    $disputeId = max(0, (int)($_POST['dispute_id'] ?? 0));
    $status = (string)($_POST['status'] ?? '');
    if ($disputeId <= 0 || !in_array($status, ['open', 'under_review', 'awaiting_vendor', 'awaiting_buyer', 'resolved', 'rejected', 'closed'], true)) {
        throw new \RuntimeException('Invalid vendor dispute status.');
    }

    $old = db()->fetch('SELECT * FROM vendor_disputes WHERE id = ? LIMIT 1', [$disputeId]);
    if (!$old) {
        throw new \RuntimeException('Vendor dispute was not found.');
    }
    db()->query(
        'UPDATE vendor_disputes SET status = ?, admin_note = COALESCE(NULLIF(?, ""), admin_note), assigned_to = ?, resolved_at = CASE WHEN ? IN ("resolved", "rejected", "closed") THEN COALESCE(resolved_at, ?) ELSE resolved_at END WHERE id = ?',
        [$status, trim((string)($_POST['admin_note'] ?? '')), $_SESSION['user_id'] ?? null, $status, sql_now(), $disputeId]
    );
    audit('vendor_dispute_status_updated', 'vendor_disputes', (string)$disputeId, $old, ['status' => $status]);
    flash('success', 'Vendor dispute status updated.');
    redirect('dashboard/vendor-disputes');
}

function dashboard_save_buyer_complaint(): void {
    validateCsrf();
    $complaintId = max(0, (int)($_POST['complaint_id'] ?? 0));
    $subject = trim((string)($_POST['subject'] ?? ''));
    if ($subject === '') {
        throw new \RuntimeException('Complaint subject is required.');
    }

    $status = (string)($_POST['status'] ?? 'open');
    if (!in_array($status, ['open', 'under_review', 'awaiting_buyer', 'awaiting_vendor', 'resolved', 'rejected', 'closed'], true)) {
        $status = 'open';
    }
    $priority = (string)($_POST['priority'] ?? 'normal');
    if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
        $priority = 'normal';
    }

    $old = $complaintId > 0 ? db()->fetch('SELECT * FROM buyer_complaints WHERE id = ? LIMIT 1', [$complaintId]) : null;
    $params = [
        (int)($_POST['user_id'] ?? 0) ?: null,
        (int)($_POST['order_id'] ?? 0) ?: null,
        (int)($_POST['vendor_id'] ?? 0) ?: null,
        $subject,
        trim((string)($_POST['message'] ?? '')) ?: null,
        $status,
        $priority,
        trim((string)($_POST['admin_note'] ?? '')) ?: null,
        $_SESSION['user_id'] ?? null,
        in_array($status, ['resolved', 'rejected', 'closed'], true) ? sql_now() : null,
    ];

    if ($complaintId > 0 && $old) {
        db()->query(
            'UPDATE buyer_complaints SET user_id = ?, order_id = ?, vendor_id = ?, subject = ?, message = ?, status = ?, priority = ?, admin_note = ?, assigned_to = ?, resolved_at = COALESCE(?, resolved_at) WHERE id = ?',
            [...$params, $complaintId]
        );
    } else {
        db()->query(
            'INSERT INTO buyer_complaints (user_id, order_id, vendor_id, subject, message, status, priority, admin_note, assigned_to, resolved_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $params
        );
        $complaintId = (int)db()->lastInsertId();
    }

    audit($old ? 'buyer_complaint_updated' : 'buyer_complaint_created', 'buyer_complaints', (string)$complaintId, $old ?: [], ['subject' => $subject, 'status' => $status]);
    flash('success', 'Buyer complaint saved.');
    redirect('dashboard/buyer-complaints?edit=' . $complaintId);
}

function dashboard_update_buyer_complaint_status(): void {
    validateCsrf();
    $complaintId = max(0, (int)($_POST['complaint_id'] ?? 0));
    $status = (string)($_POST['status'] ?? '');
    if ($complaintId <= 0 || !in_array($status, ['open', 'under_review', 'awaiting_buyer', 'awaiting_vendor', 'resolved', 'rejected', 'closed'], true)) {
        throw new \RuntimeException('Invalid buyer complaint status.');
    }

    $old = db()->fetch('SELECT * FROM buyer_complaints WHERE id = ? LIMIT 1', [$complaintId]);
    if (!$old) {
        throw new \RuntimeException('Buyer complaint was not found.');
    }
    db()->query(
        'UPDATE buyer_complaints SET status = ?, admin_note = COALESCE(NULLIF(?, ""), admin_note), assigned_to = ?, resolved_at = CASE WHEN ? IN ("resolved", "rejected", "closed") THEN COALESCE(resolved_at, ?) ELSE resolved_at END WHERE id = ?',
        [$status, trim((string)($_POST['admin_note'] ?? '')), $_SESSION['user_id'] ?? null, $status, sql_now(), $complaintId]
    );
    audit('buyer_complaint_status_updated', 'buyer_complaints', (string)$complaintId, $old, ['status' => $status]);
    flash('success', 'Buyer complaint status updated.');
    redirect('dashboard/buyer-complaints');
}

function dashboard_product_image_upload(int $productId, ?int $ownerUserId = null): ?int {
    $upload = $_FILES['product_image'] ?? null;
    if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int)$upload['error'] !== UPLOAD_ERR_OK) {
        throw new \RuntimeException('Product image upload failed.');
    }

    $tmpName = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new \RuntimeException('Uploaded product image could not be read.');
    }

    if ($size <= 0 || $size > 8 * 1024 * 1024) {
        throw new \RuntimeException('Product image must be smaller than 8MB.');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)finfo_file($finfo, $tmpName);
            finfo_close($finfo);
        }
    }
    if ($mime === '' && function_exists('mime_content_type')) {
        $mime = (string)mime_content_type($tmpName);
    }
    if (!isset($allowed[$mime])) {
        throw new \RuntimeException('Product image must be a JPG, PNG, or WebP image.');
    }

    $uploadDir = APP_ROOT . '/public/uploads/products/' . date('Y/m');
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new \RuntimeException('Product upload directory could not be created.');
    }
    @chmod(APP_ROOT . '/public/uploads', 0777);
    @chmod(APP_ROOT . '/public/uploads/products', 0777);
    @chmod(dirname($uploadDir), 0777);
    @chmod($uploadDir, 0777);

    $filename = 'product-' . $productId . '-' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
    $target = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmpName, $target)) {
        throw new \RuntimeException('Product image could not be saved.');
    }

    $relativePath = 'uploads/products/' . date('Y/m') . '/' . $filename;
    [$width, $height] = @getimagesize($target) ?: [null, null];
    db()->query(
        "INSERT INTO files (owner_user_id, disk, path, original_name, mime_type, size_bytes, width, height, alt_text)
         VALUES (?, 'public', ?, ?, ?, ?, ?, ?, ?)",
        [$ownerUserId, $relativePath, (string)($upload['name'] ?? $filename), $mime, $size, $width, $height, 'Product image']
    );
    $fileId = (int)db()->lastInsertId();

    db()->query("UPDATE product_media SET role = 'gallery' WHERE product_id = ? AND role = 'primary'", [$productId]);
    db()->query(
        "INSERT INTO product_media (product_id, file_id, role, sort_order) VALUES (?, ?, 'primary', 0)",
        [$productId, $fileId]
    );

    return $fileId;
}

function dashboard_product_gallery_uploads(int $productId, ?int $ownerUserId = null): int {
    $uploads = $_FILES['product_gallery'] ?? null;
    if (!is_array($uploads) || !is_array($uploads['error'] ?? null)) {
        return 0;
    }

    $saved = 0;
    $count = count($uploads['error']);
    for ($index = 0; $index < $count; $index++) {
        if ((int)($uploads['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $_FILES['product_gallery_single'] = [
            'name' => $uploads['name'][$index] ?? '',
            'type' => $uploads['type'][$index] ?? '',
            'tmp_name' => $uploads['tmp_name'][$index] ?? '',
            'error' => $uploads['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $uploads['size'][$index] ?? 0,
        ];

        $upload = $_FILES['product_gallery_single'];
        if ((int)$upload['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('One gallery image failed to upload.');
        }

        $tmpName = (string)($upload['tmp_name'] ?? '');
        $size = (int)($upload['size'] ?? 0);
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new \RuntimeException('Uploaded gallery image could not be read.');
        }
        if ($size <= 0 || $size > 8 * 1024 * 1024) {
            throw new \RuntimeException('Gallery images must be smaller than 8MB.');
        }

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = function_exists('mime_content_type') ? (string)mime_content_type($tmpName) : '';
        if (!isset($allowed[$mime])) {
            throw new \RuntimeException('Gallery images must be JPG, PNG, or WebP.');
        }

        $uploadDir = APP_ROOT . '/public/uploads/products/' . date('Y/m');
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            throw new \RuntimeException('Product upload directory could not be created.');
        }
        @chmod(APP_ROOT . '/public/uploads', 0777);
        @chmod(APP_ROOT . '/public/uploads/products', 0777);
        @chmod(dirname($uploadDir), 0777);
        @chmod($uploadDir, 0777);

        $filename = 'product-' . $productId . '-gallery-' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
        $target = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($tmpName, $target)) {
            throw new \RuntimeException('Gallery image could not be saved.');
        }

        $relativePath = 'uploads/products/' . date('Y/m') . '/' . $filename;
        [$width, $height] = @getimagesize($target) ?: [null, null];
        db()->query(
            "INSERT INTO files (owner_user_id, disk, path, original_name, mime_type, size_bytes, width, height, alt_text)
             VALUES (?, 'public', ?, ?, ?, ?, ?, ?, ?)",
            [$ownerUserId, $relativePath, (string)($upload['name'] ?? $filename), $mime, $size, $width, $height, 'Product gallery image']
        );
        $fileId = (int)db()->lastInsertId();
        $sortOrder = (int)(db()->fetch('SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM product_media WHERE product_id = ?', [$productId])['next_sort'] ?? 1);
        db()->query("INSERT INTO product_media (product_id, file_id, role, sort_order) VALUES (?, ?, 'gallery', ?)", [$productId, $fileId, $sortOrder]);
        $saved++;
    }

    unset($_FILES['product_gallery_single']);
    return $saved;
}

function dashboard_save_product(): void {
    validateCsrf();
    if (!table_exists('products')) {
        flash('error', 'Products table is not available.');
        return;
    }

    $productId = max(0, (int)($_POST['product_id'] ?? 0));
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        throw new \RuntimeException('Product name is required.');
    }

    $vendorId = (int)($_POST['vendor_id'] ?? 0) ?: null;
    $brandId = (int)($_POST['brand_id'] ?? 0) ?: null;
    $status = (string)($_POST['status'] ?? 'draft');
    $allowedStatuses = ['draft', 'pending', 'active', 'private', 'archived', 'rejected'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'draft';
    }

    $type = (string)($_POST['type'] ?? 'simple');
    $allowedTypes = ['simple', 'variable', 'variation', 'grouped', 'external', 'digital'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'simple';
    }

    $stockStatus = (string)($_POST['stock_status'] ?? 'in_stock');
    $allowedStockStatuses = ['in_stock', 'out_of_stock', 'on_backorder'];
    if (!in_array($stockStatus, $allowedStockStatuses, true)) {
        $stockStatus = 'in_stock';
    }

    $currency = strtoupper(substr(preg_replace('/[^A-Z]/', '', strtoupper((string)($_POST['currency'] ?? 'USD'))) ?: 'USD', 0, 3));
    $regularPrice = max(0, (float)($_POST['regular_price'] ?? 0));
    $salePriceValue = trim((string)($_POST['sale_price'] ?? ''));
    $salePrice = $salePriceValue === '' ? null : max(0, (float)$salePriceValue);
    $stockQuantity = trim((string)($_POST['stock_quantity'] ?? ''));
    $lowStockThreshold = trim((string)($_POST['low_stock_threshold'] ?? ''));
    $taxStatus = (string)($_POST['tax_status'] ?? 'taxable');
    if (!in_array($taxStatus, ['taxable', 'shipping', 'none'], true)) {
        $taxStatus = 'taxable';
    }
    $weight = trim((string)($_POST['weight'] ?? ''));
    $length = trim((string)($_POST['length'] ?? ''));
    $width = trim((string)($_POST['width'] ?? ''));
    $height = trim((string)($_POST['height'] ?? ''));
    $shippingClass = trim((string)($_POST['shipping_class'] ?? ''));
    $manageStock = isset($_POST['manage_stock']) ? 1 : 0;
    $featured = isset($_POST['featured']) ? 1 : 0;
    $publishedAt = $status === 'active' ? sql_now() : null;
    $slug = dashboard_unique_product_slug((string)($_POST['slug'] ?? $name), $productId ?: null);
    $old = $productId > 0 ? db()->fetch('SELECT * FROM products WHERE id = ? LIMIT 1', [$productId]) : null;
    $sku = \App\ProductService::requireUniqueSku((string)($_POST['sku'] ?? ''), $productId ?: null);

    $params = [
        $vendorId,
        $brandId,
        $type,
        $name,
        $slug,
        $sku,
        trim((string)($_POST['short_description'] ?? '')) ?: null,
        trim((string)($_POST['description'] ?? '')) ?: null,
        $status,
        $regularPrice,
        $salePrice,
        $currency,
        $taxStatus,
        $stockStatus,
        $stockQuantity === '' ? null : (int)$stockQuantity,
        $manageStock,
        $lowStockThreshold === '' ? null : (int)$lowStockThreshold,
        $weight === '' ? null : max(0, (float)$weight),
        $length === '' ? null : max(0, (float)$length),
        $width === '' ? null : max(0, (float)$width),
        $height === '' ? null : max(0, (float)$height),
        $shippingClass === '' ? null : $shippingClass,
        $featured,
    ];

    if ($productId > 0 && $old) {
        db()->query(
            "UPDATE products
             SET vendor_id = ?, brand_id = ?, type = ?, name = ?, slug = ?, sku = ?, short_description = ?, description = ?,
                 status = ?, regular_price = ?, sale_price = ?, currency = ?, tax_status = ?, stock_status = ?, stock_quantity = ?,
                 manage_stock = ?, low_stock_threshold = ?, weight = ?, length = ?, width = ?, height = ?, shipping_class = ?, featured = ?, published_at = COALESCE(published_at, ?)
             WHERE id = ?",
            [...$params, $publishedAt, $productId]
        );
    } else {
        db()->query(
            "INSERT INTO products
             (vendor_id, brand_id, type, name, slug, sku, short_description, description, status, regular_price, sale_price,
              currency, tax_status, stock_status, stock_quantity, manage_stock, low_stock_threshold, weight, length, width, height, shipping_class, featured, published_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [...$params, $publishedAt]
        );
        $productId = (int)db()->lastInsertId();
    }

    if ($manageStock && $stockQuantity !== '') {
        db()->query(
            "INSERT INTO product_inventory_movements (product_id, movement_type, quantity_change, quantity_after, reference_type, reference_id, note, created_by)
             VALUES (?, 'adjustment', 0, ?, 'admin_product_save', ?, 'Admin product stock save', ?)",
            [$productId, (int)$stockQuantity, $productId, $_SESSION['user_id'] ?? null]
        );
    }

    dashboard_product_image_upload($productId, $_SESSION['user_id'] ?? null);
    dashboard_product_gallery_uploads($productId, $_SESSION['user_id'] ?? null);
    audit($old ? 'product_updated' : 'product_created', 'products', (string)$productId, $old ?: [], ['name' => $name, 'status' => $status]);
    if (!$old || (string)($old['status'] ?? '') !== $status) {
        $productNotice = db()->fetch(
            "SELECT p.name, u.email AS vendor_email, COALESCE(NULLIF(u.display_name, ''), v.store_name, u.username) AS vendor_name
             FROM products p
             LEFT JOIN vendors v ON v.id = p.vendor_id
             LEFT JOIN users u ON u.id = v.user_id
             WHERE p.id = ?
             LIMIT 1",
            [$productId]
        );
        if ($productNotice) {
            \App\NotificationService::productStatus($productNotice, $status);
        }
        if ($status === 'active') {
            $customerProductNotice = db()->fetch('SELECT name, slug, sale_price FROM products WHERE id = ? LIMIT 1', [$productId]);
            if ($customerProductNotice) {
                \App\NotificationService::productPublished($customerProductNotice);
            }
        }
    }
    flash('success', $old ? 'Product updated.' : 'Product created.');
    $returnVendorId = max(0, (int)($_POST['return_vendor_id'] ?? 0));
    if ($returnVendorId > 0) {
        redirect('dashboard/vendor-edit?vendor=' . $returnVendorId . '&edit_product=' . $productId);
    }
    redirect('dashboard/products?edit=' . $productId);
}

function dashboard_update_product_status(): void {
    validateCsrf();

    $productId = max(0, (int)($_POST['product_id'] ?? 0));
    $status = (string)($_POST['status'] ?? '');
    $returnView = preg_replace('/[^a-z0-9-]/', '', (string)($_POST['return_view'] ?? 'pending-products')) ?: 'pending-products';
    if ($productId <= 0 || !in_array($status, ['active', 'rejected'], true)) {
        throw new \RuntimeException('Invalid product approval decision.');
    }

    $product = db()->fetch(
        "SELECT p.id, p.vendor_id, p.name, p.slug, p.status, p.sale_price,
                COALESCE(NULLIF(u.email, ''), NULLIF(v.store_email, '')) AS vendor_email,
                COALESCE(NULLIF(u.display_name, ''), v.store_name, u.username, u.email) AS vendor_name
         FROM products p
         LEFT JOIN vendors v ON v.id = p.vendor_id
         LEFT JOIN users u ON u.id = v.user_id
         WHERE p.id = ?
         LIMIT 1",
        [$productId]
    );
    if (!$product) {
        throw new \RuntimeException('Product record was not found.');
    }

    $oldStatus = (string)($product['status'] ?? '');
    if ($status === 'active' && !in_array($oldStatus, ['active', 'private'], true)) {
        $quota = VendorSubscriptionService::quota((int)$product['vendor_id']);
        if (!$quota['can_upload']) {
            $limit = $quota['limit'] === null ? 'unlimited' : number_format((int)$quota['limit']);
            throw new \RuntimeException('This vendor has reached the ' . $limit . ' published product slot limit for the current plan.');
        }
    }

    db()->query(
        'UPDATE products SET published_at = CASE WHEN ? = "active" AND status <> "active" THEN ? ELSE published_at END, status = ?, updated_at = ? WHERE id = ?',
        [$status, sql_now(), $status, sql_now(), $productId]
    );

    audit('product_status_updated', 'products', (string)$productId, ['status' => $oldStatus], ['status' => $status]);
    \App\NotificationService::productStatus($product, $status);

    if ($status === 'active') {
        \App\NotificationService::productPublished($product);
        flash('success', 'Product approved and vendor notification queued.');
    } else {
        flash('success', 'Product rejected and vendor notification queued.');
    }

    redirect('dashboard/' . $returnView);
}

function dashboard_delete_product_review(): void {
    validateCsrf();

    $reviewId = max(0, (int)($_POST['review_id'] ?? 0));
    if ($reviewId <= 0) {
        throw new \RuntimeException('Invalid review.');
    }

    $review = db()->fetch('SELECT * FROM reviews WHERE id = ? LIMIT 1', [$reviewId]);
    if (!$review) {
        throw new \RuntimeException('Review was not found.');
    }

    $productId = (int)($review['product_id'] ?? 0);
    try {
        db()->beginTransaction();
        db()->query('DELETE FROM reviews WHERE id = ?', [$reviewId]);
        dashboard_recalculate_product_reviews($productId);
        db()->commit();
    } catch (\Throwable $e) {
        if (db()->pdo()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }

    audit('product_review_deleted', 'reviews', (string)$reviewId, $review, ['product_id' => $productId]);
    flash('success', 'Product review deleted.');
    redirect('dashboard/product-reviews');
}

function dashboard_recalculate_product_reviews(int $productId): void {
    if ($productId <= 0) {
        return;
    }

    db()->query(
        "UPDATE products p
         SET review_count = (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id AND r.status = 'approved'),
             average_rating = COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.product_id = p.id AND r.status = 'approved'), 0),
             updated_at = ?
         WHERE p.id = ?",
        [sql_now(), $productId]
    );
}

function dashboard_ensure_notifications_schema(): void {
    try {
        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                channel ENUM('in_app', 'email', 'sms', 'push') NOT NULL DEFAULT 'in_app',
                recipient_user_id BIGINT UNSIGNED NULL,
                recipient_email VARCHAR(190) NULL,
                recipient_phone VARCHAR(80) NULL,
                title VARCHAR(190) NOT NULL,
                body TEXT NULL,
                status ENUM('draft', 'pending', 'sent', 'delivered', 'read', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
                priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
                scheduled_at DATETIME NULL,
                sent_at DATETIME NULL,
                delivered_at DATETIME NULL,
                read_at DATETIME NULL,
                last_error TEXT NULL,
                metadata JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_notifications_channel_status (channel, status, created_at),
                INDEX idx_notifications_recipient (recipient_user_id, channel, read_at),
                INDEX idx_notifications_schedule (status, scheduled_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS notification_templates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                channel ENUM('in_app', 'email', 'sms', 'push') NOT NULL DEFAULT 'in_app',
                template_key VARCHAR(120) NOT NULL,
                name VARCHAR(190) NOT NULL,
                subject VARCHAR(190) NULL,
                body LONGTEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_notification_templates_channel_key (channel, template_key),
                INDEX idx_notification_templates_channel_active (channel, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS notification_preferences (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                channel ENUM('in_app', 'email', 'sms', 'push') NOT NULL,
                category VARCHAR(120) NOT NULL DEFAULT 'general',
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_notification_preferences_user_channel_category (user_id, channel, category),
                INDEX idx_notification_preferences_channel (channel, is_enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS push_subscriptions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NULL,
                endpoint VARCHAR(500) NOT NULL,
                public_key VARCHAR(255) NULL,
                auth_token VARCHAR(255) NULL,
                platform VARCHAR(80) NULL,
                user_agent VARCHAR(500) NULL,
                status ENUM('active', 'expired', 'revoked') NOT NULL DEFAULT 'active',
                last_seen_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_push_subscriptions_endpoint (endpoint),
                INDEX idx_push_subscriptions_user_status (user_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\Throwable $e) {
        error_log('Notification schema setup failed: ' . $e->getMessage());
    }
}

function dashboard_ensure_payments_admin_schema(): void {
    try {
        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS payment_idempotency_keys (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                idem_key VARCHAR(190) NOT NULL,
                provider VARCHAR(80) NULL,
                order_id BIGINT UNSIGNED NULL,
                payment_id BIGINT UNSIGNED NULL,
                request_hash VARCHAR(128) NULL,
                response_code SMALLINT NULL,
                status ENUM('started', 'completed', 'failed', 'expired') NOT NULL DEFAULT 'started',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_payment_idem_key (idem_key),
                INDEX idx_payment_idem_status (status, created_at),
                INDEX idx_payment_idem_order (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS currencies (
                code CHAR(3) NOT NULL PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                symbol VARCHAR(12) NOT NULL,
                exchange_rate DECIMAL(19,8) NOT NULL DEFAULT 1,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                decimal_places TINYINT UNSIGNED NOT NULL DEFAULT 2,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        db()->query("INSERT IGNORE INTO currencies (code, name, symbol, exchange_rate, is_default, is_enabled) VALUES
            ('USD', 'US Dollar', '$', 1, 1, 1),
            ('NGN', 'Nigerian Naira', '₦', 1500, 0, 1),
            ('GBP', 'British Pound', '£', 0.79, 0, 1),
            ('EUR', 'Euro', '€', 0.92, 0, 1),
            ('CAD', 'Canadian Dollar', 'C$', 1.36, 0, 1)");
        PaymentService::ensureKlashaMethod();
        PaymentService::ensurePaystackMethod();
    } catch (\Throwable $e) {
        error_log('Payments admin schema setup failed: ' . $e->getMessage());
    }
}

function dashboard_ensure_taxonomy_admin_schema(): void {
    try {
        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS product_attributes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                wp_attribute_id BIGINT UNSIGNED NULL,
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(120) NOT NULL,
                type ENUM('select','text','color','image','button') NOT NULL DEFAULT 'select',
                is_global TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_product_attributes_slug (slug),
                INDEX idx_product_attributes_global (is_global)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS product_attribute_values (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                attribute_id BIGINT UNSIGNED NOT NULL,
                wp_term_id BIGINT UNSIGNED NULL,
                value VARCHAR(190) NOT NULL,
                slug VARCHAR(190) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                UNIQUE KEY uq_product_attribute_values_attribute_slug (attribute_id, slug),
                INDEX idx_product_attribute_values_attribute (attribute_id, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\Throwable $e) {
        error_log('Taxonomy admin schema setup failed: ' . $e->getMessage());
    }
}

function dashboard_ensure_withdrawals_admin_schema(): void {
    try {
        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS affiliate_withdrawals (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                affiliate_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                amount DECIMAL(19,4) NOT NULL DEFAULT 0,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                method VARCHAR(80) NULL,
                status ENUM('pending','approved','rejected','paid','cancelled') NOT NULL DEFAULT 'pending',
                note TEXT NULL,
                requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                processed_by BIGINT UNSIGNED NULL,
                processed_at DATETIME NULL,
                INDEX idx_affiliate_withdrawals_status_requested (status, requested_at),
                INDEX idx_affiliate_withdrawals_affiliate (affiliate_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\Throwable $e) {
        error_log('Withdrawals admin schema setup failed: ' . $e->getMessage());
    }
}

function dashboard_ensure_shipping_admin_schema(): void {
    try {
        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS delivery_partners (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                carrier_id SMALLINT UNSIGNED NULL,
                name VARCHAR(190) NOT NULL,
                code VARCHAR(80) NOT NULL,
                contact_email VARCHAR(190) NULL,
                contact_phone VARCHAR(60) NULL,
                service_regions TEXT NULL,
                tracking_url_template VARCHAR(500) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_delivery_partners_code (code),
                INDEX idx_delivery_partners_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\Throwable $e) {
        error_log('Shipping admin schema setup failed: ' . $e->getMessage());
    }
}

function dashboard_ensure_content_admin_schema(): void {
    try {
        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS content_albums (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(190) NOT NULL,
                slug VARCHAR(220) NOT NULL,
                description TEXT NULL,
                cover_media_url VARCHAR(500) NULL,
                status ENUM('draft','published') NOT NULL DEFAULT 'draft',
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_content_albums_slug (slug),
                INDEX idx_content_albums_status_sort (status, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS content_gallery_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                album_id BIGINT UNSIGNED NULL,
                title VARCHAR(190) NOT NULL,
                media_type ENUM('image','video') NOT NULL DEFAULT 'image',
                media_url VARCHAR(500) NOT NULL,
                thumbnail_url VARCHAR(500) NULL,
                caption TEXT NULL,
                status ENUM('draft','published') NOT NULL DEFAULT 'draft',
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_gallery_items_album FOREIGN KEY (album_id) REFERENCES content_albums(id) ON DELETE SET NULL,
                INDEX idx_gallery_items_album_status (album_id, status, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS content_posts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                wp_post_id BIGINT UNSIGNED NULL UNIQUE,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                excerpt TEXT NULL,
                body LONGTEXT NULL,
                featured_image_url VARCHAR(500) NULL,
                category VARCHAR(120) NULL,
                author_name VARCHAR(190) NULL,
                status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
                published_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_content_posts_slug (slug),
                INDEX idx_content_posts_status_date (status, published_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\Throwable $e) {
        error_log('Content admin schema setup failed: ' . $e->getMessage());
    }
}

function dashboard_ensure_affiliate_admin_schema(): void {
    try {
        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS affiliate_commission_rules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(190) NOT NULL,
                rule_type ENUM('global','affiliate','vendor','category','campaign') NOT NULL DEFAULT 'global',
                target_id BIGINT UNSIGNED NULL,
                rate_type ENUM('percentage','flat') NOT NULL DEFAULT 'percentage',
                commission_rate DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                min_order_amount DECIMAL(19,4) NULL,
                priority INT NOT NULL DEFAULT 10,
                starts_at DATETIME NULL,
                ends_at DATETIME NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_affiliate_commission_rules_active (is_active, priority),
                INDEX idx_affiliate_commission_rules_type_target (rule_type, target_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\Throwable $e) {
        error_log('Affiliate admin schema setup failed: ' . $e->getMessage());
    }
}

function dashboard_ensure_refunds_disputes_schema(): void {
    try {
        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS vendor_disputes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                vendor_id BIGINT UNSIGNED NULL,
                order_id BIGINT UNSIGNED NULL,
                opened_by BIGINT UNSIGNED NULL,
                title VARCHAR(190) NOT NULL,
                description TEXT NULL,
                status ENUM('open','under_review','awaiting_vendor','awaiting_buyer','resolved','rejected','closed') NOT NULL DEFAULT 'open',
                priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
                admin_note TEXT NULL,
                assigned_to BIGINT UNSIGNED NULL,
                resolved_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_vendor_disputes_vendor_status (vendor_id, status),
                INDEX idx_vendor_disputes_order (order_id),
                INDEX idx_vendor_disputes_status_priority (status, priority)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS buyer_complaints (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NULL,
                order_id BIGINT UNSIGNED NULL,
                vendor_id BIGINT UNSIGNED NULL,
                subject VARCHAR(190) NOT NULL,
                message TEXT NULL,
                status ENUM('open','under_review','awaiting_buyer','awaiting_vendor','resolved','rejected','closed') NOT NULL DEFAULT 'open',
                priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
                admin_note TEXT NULL,
                assigned_to BIGINT UNSIGNED NULL,
                resolved_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_buyer_complaints_user_status (user_id, status),
                INDEX idx_buyer_complaints_order (order_id),
                INDEX idx_buyer_complaints_vendor_status (vendor_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\Throwable $e) {
        error_log('Refunds and disputes schema setup failed: ' . $e->getMessage());
    }
}

$automationViews = ['cron-jobs', 'queues', 'worker-monitor', 'failed-jobs', 'email-scheduler', 'cart-checker', 'cleanup-tasks'];
$notificationViews = ['in-app-notifications', 'email-notifications', 'sms-notifications', 'push-notifications'];
$userViews = ['users', 'buyers', 'guest-buyers', 'blocked-users', 'user-activity-logs'];
$vendorViews = ['vendors', 'vendor-edit', 'vendor-packages', 'vendor-subscriptions', 'pending-vendors', 'verified-vendors', 'rejected-vendors', 'vendor-kyc-documents', 'vendor-stores', 'vendor-ratings', 'vendor-payout-accounts'];
$productViews = ['products', 'pending-products', 'approved-products', 'rejected-products', 'featured-products', 'ranked-products', 'low-stock-products', 'out-of-stock-products', 'duplicate-products', 'product-comments', 'product-reviews', 'product-meta-tags'];
$orderViews = ['orders', 'pending-orders', 'processing-orders', 'shipped-orders', 'delivered-orders', 'cancelled-orders', 'returned-orders'];
$paymentViews = ['transactions', 'payment-gateways', 'failed-payments', 'idempotency-logs', 'multi-currency-settings'];
$taxonomyViews = ['categories', 'subcategories', 'brands', 'attributes', 'variations'];
$withdrawalViews = ['vendor-withdrawals', 'affiliate-withdrawals', 'pending-withdrawals', 'approved-withdrawals', 'rejected-withdrawals'];
$shippingViews = ['shipping-zones', 'shipping-rates', 'aramex-api-settings', 'shipment-tracking', 'delivery-partners'];
$contentViews = ['albums', 'gallery', 'blog-posts'];
$affiliateViews = ['affiliates', 'referral-links', 'commission-rules', 'pending-commissions', 'approved-commissions', 'affiliate-payouts'];
$refundDisputeViews = ['refund-requests', 'vendor-disputes', 'buyer-complaints', 'return-requests'];
dashboard_ensure_notifications_schema();
dashboard_ensure_payments_admin_schema();
dashboard_ensure_taxonomy_admin_schema();
dashboard_ensure_withdrawals_admin_schema();
dashboard_ensure_shipping_admin_schema();
dashboard_ensure_content_admin_schema();
dashboard_ensure_affiliate_admin_schema();
dashboard_ensure_refunds_disputes_schema();

$dashboardReportRange = dashboard_report_range();
$dashboardReportsData = dashboard_build_reporting_data($dashboardReportRange);

$stats = [
    'users' => dashboard_count('users'),
    'vendors' => dashboard_count('vendors'),
    'active_vendors' => dashboard_count('vendors', "status = 'active'"),
    'products' => dashboard_count('products'),
    'orders' => dashboard_count('orders'),
    'gross_sales' => $dashboardReportsData['summary']['gross_sales'] ?? 0,
    'paid_revenue' => $dashboardReportsData['summary']['paid_revenue'] ?? 0,
    'average_order_value' => $dashboardReportsData['summary']['average_order_value'] ?? 0,
    'range_orders' => $dashboardReportsData['summary']['total_orders'] ?? 0,
    'pending_vendors' => dashboard_count('vendors', "(status = 'pending' OR kyc_status = 'pending') AND status <> 'rejected' AND kyc_status <> 'rejected'"),
    'pending_products' => dashboard_count('products', "status = 'pending'"),
    'failed_payments' => dashboard_count('payments', "status = 'failed'"),
    'failed_jobs' => dashboard_count('jobs_queue', "status = 'failed'"),
];

$recentAudit = table_exists('audit_logs')
    ? db()->fetchAll(
        "SELECT al.action, al.entity_type, al.entity_id, al.new_values, al.created_at, u.display_name
         FROM audit_logs al
         LEFT JOIN users u ON u.id = al.actor_user_id
         WHERE al.new_values IS NULL OR al.new_values NOT LIKE '%web_server_error_document%'
         ORDER BY al.created_at DESC
         LIMIT 8"
    )
    : [];

$workerRows = table_exists('worker_heartbeats')
    ? db()->fetchAll(
        "SELECT worker_name, queue_name, status, current_job_id, last_seen_at
         FROM worker_heartbeats
         ORDER BY last_seen_at DESC
         LIMIT 6"
    )
    : [];

$settingPage = $settingsPages[$view] ?? null;
$settingValues = $settingPage ? dashboard_setting_values($settingPage['group'], $settingPage['fields']) : [];

$roleRows = table_exists('roles')
    ? db()->fetchAll(
        "SELECT r.id, r.code, r.name, r.description, COUNT(ur.user_id) AS user_count
         FROM roles r
         LEFT JOIN user_roles ur ON ur.role_id = r.id
         GROUP BY r.id, r.code, r.name, r.description
         ORDER BY r.id ASC"
    )
    : [];

$auditRows = [];
$auditPagination = [
    'page' => 1,
    'perPage' => 50,
    'total' => 0,
    'totalPages' => 1,
    'from' => 0,
    'to' => 0,
    'search' => '',
    'perPageOptions' => [25, 50, 100, 200],
];
if (table_exists('audit_logs')) {
    $auditSearch = trim((string)($_GET['q'] ?? ''));
    $auditPerPageOptions = [25, 50, 100, 200];
    $auditPerPage = (int)($_GET['per_page'] ?? 50);
    if (!in_array($auditPerPage, $auditPerPageOptions, true)) {
        $auditPerPage = 50;
    }
    $auditPage = max(1, (int)($_GET['page'] ?? 1));
    $auditWhereParts = [];
    $auditParams = [];

    if ($auditSearch !== '') {
        $auditNeedle = '%' . $auditSearch . '%';
        $auditWhereParts[] = "(
            al.action LIKE ?
            OR al.entity_type LIKE ?
            OR al.entity_id LIKE ?
            OR al.new_values LIKE ?
            OR al.ip_address LIKE ?
            OR al.user_agent LIKE ?
            OR u.display_name LIKE ?
            OR u.email LIKE ?
            OR u.username LIKE ?
            OR CONCAT_WS(' ', u.first_name, u.last_name) LIKE ?
        )";
        $auditParams = array_fill(0, 10, $auditNeedle);
    } else {
        $auditWhereParts[] = "(al.new_values IS NULL OR al.new_values NOT LIKE '%web_server_error_document%')";
    }
    $auditWhere = $auditWhereParts !== [] ? 'WHERE ' . implode(' AND ', $auditWhereParts) : '';

    $auditTotalRow = db()->fetch(
        "SELECT COUNT(*) AS total
         FROM audit_logs al
         LEFT JOIN users u ON u.id = al.actor_user_id
         {$auditWhere}",
        $auditParams
    );
    $auditTotal = (int)($auditTotalRow['total'] ?? 0);
    $auditTotalPages = max(1, (int)ceil($auditTotal / $auditPerPage));
    $auditPage = min($auditPage, $auditTotalPages);
    $auditOffset = ($auditPage - 1) * $auditPerPage;

    $auditRows = db()->fetchAll(
        "SELECT al.action, al.entity_type, al.entity_id, al.new_values, al.ip_address, al.user_agent, al.created_at, u.display_name, u.email
         FROM audit_logs al
         LEFT JOIN users u ON u.id = al.actor_user_id
         {$auditWhere}
         ORDER BY al.created_at DESC
         LIMIT {$auditPerPage} OFFSET {$auditOffset}",
        $auditParams
    );

    $auditPagination = [
        'page' => $auditPage,
        'perPage' => $auditPerPage,
        'total' => $auditTotal,
        'totalPages' => $auditTotalPages,
        'from' => $auditTotal > 0 ? $auditOffset + 1 : 0,
        'to' => min($auditTotal, $auditOffset + count($auditRows)),
        'search' => $auditSearch,
        'perPageOptions' => $auditPerPageOptions,
    ];
}

$jobRows = table_exists('jobs_queue')
    ? db()->fetchAll(
        "SELECT id,
                queue_name,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.action')), queue_name) AS job_type,
                status,
                attempts,
                1 AS max_attempts,
                NULL AS last_error,
                available_at,
                NULL AS started_at,
                updated_at AS completed_at,
                created_at,
                updated_at
         FROM jobs_queue
         ORDER BY FIELD(status, 'failed', 'processing', 'pending', 'cancelled', 'completed'), created_at DESC
         LIMIT 50"
    )
    : [];

$jobSummary = table_exists('jobs_queue')
    ? db()->fetchAll("SELECT status, COUNT(*) AS total FROM jobs_queue GROUP BY status")
    : [];

$jobStatusTotals = [];
foreach ($jobSummary as $row) {
    $jobStatusTotals[(string)$row['status']] = (int)$row['total'];
}

$queueRows = dashboard_table_rows(
    "SELECT queue_name,
            COUNT(*) AS total_jobs,
            SUM(status = 'pending') AS pending_jobs,
            SUM(status = 'processing') AS processing_jobs,
            SUM(status = 'failed') AS failed_jobs,
            SUM(status = 'completed') AS completed_jobs,
            MIN(CASE WHEN status = 'pending' THEN available_at END) AS next_available_at,
            MAX(updated_at) AS last_activity_at
     FROM jobs_queue
     GROUP BY queue_name
     ORDER BY queue_name ASC",
    [],
    'jobs_queue'
);

$failedJobRows = dashboard_table_rows(
    "SELECT id,
            queue_name,
            COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.action')), queue_name) AS job_type,
            status,
            attempts,
            1 AS max_attempts,
            NULL AS last_error,
            available_at,
            created_at,
            updated_at
     FROM jobs_queue
     WHERE status = 'failed'
     ORDER BY updated_at DESC
     LIMIT 80",
    [],
    'jobs_queue'
);

$emailJobRows = dashboard_table_rows(
    "SELECT id,
            queue_name,
            COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.action')), queue_name) AS job_type,
            status,
            attempts,
            1 AS max_attempts,
            NULL AS last_error,
            available_at,
            created_at,
            updated_at
     FROM jobs_queue
     WHERE queue_name = 'emails' OR payload LIKE '%email%' OR payload LIKE '%mail%'
     ORDER BY created_at DESC
     LIMIT 80",
    [],
    'jobs_queue'
);

$cartSummaryRows = dashboard_table_rows(
    "SELECT status, COUNT(*) AS total, COALESCE(SUM(grand_total), 0) AS value_total, MAX(updated_at) AS last_activity_at
     FROM carts
     GROUP BY status",
    [],
    'carts'
);
$cartSummary = [];
foreach ($cartSummaryRows as $row) {
    $cartSummary[(string)$row['status']] = $row;
}

$cartRows = dashboard_table_rows(
    "SELECT c.id, c.user_id, c.session_key, c.status, c.currency, c.grand_total, c.created_at, c.updated_at, c.expires_at,
            u.email, COUNT(ci.id) AS item_count
     FROM carts c
     LEFT JOIN users u ON u.id = c.user_id
     LEFT JOIN cart_items ci ON ci.cart_id = c.id
     GROUP BY c.id, c.user_id, c.session_key, c.status, c.currency, c.grand_total, c.created_at, c.updated_at, c.expires_at, u.email
     ORDER BY c.updated_at DESC
     LIMIT 80",
    [],
    'carts'
);

$latestMigrationRuns = dashboard_table_rows(
    "SELECT id, source_type, source_label, status, current_step, batch_size, created_at, started_at, completed_at, updated_at
     FROM migration_runs
     ORDER BY id DESC
     LIMIT 10",
    [],
    'migration_runs'
);

$cronDefinitions = [
    ['name' => 'Shared Host Cron', 'frequency' => 'Every minute', 'command' => 'php ' . APP_ROOT . '/app/core/cron.php', 'purpose' => 'Recommended cPanel cron. Runs the scheduler once and processes a small queue batch, including weekly vendor verification reminders and the daily 9PM admin report.'],
    ['name' => 'Queue Worker Batch', 'frequency' => 'Every minute', 'command' => 'php ' . APP_ROOT . '/app/core/worker.php --once --max-jobs=10 --max-runtime=50', 'purpose' => 'Processes queued emails, notifications, billing and system jobs without a permanent daemon.'],
    ['name' => 'Daily Admin Report', 'frequency' => 'Daily at 9:00 PM', 'command' => 'queued by php ' . APP_ROOT . '/app/core/scheduler.php', 'purpose' => 'Emails all active admin and super-admin accounts with registrations, new products, vendor/KYC totals, sales and affiliate performance.'],
    ['name' => 'Core Scheduler Only', 'frequency' => 'Every 5 minutes', 'command' => 'php ' . APP_ROOT . '/app/core/scheduler.php', 'purpose' => 'Queues abandoned cart, inactivity, cleanup, weekly vendor verification reminders, daily admin reports and recurring automation jobs.'],
    ['name' => 'Queue Worker Daemon', 'frequency' => 'VPS only', 'command' => 'php ' . APP_ROOT . '/app/core/worker.php', 'purpose' => 'Optional long-running worker for Supervisor, systemd or launchd environments.'],
    ['name' => 'Migration Runner', 'frequency' => 'Manual / supervised', 'command' => app_url('dashboard/data-migration'), 'purpose' => 'Runs source database and SQL dump migration batches from the admin console.'],
];

$cleanupTargets = [
    ['label' => 'Failed jobs older than 7 days', 'count' => dashboard_count('jobs_queue', "status = 'failed' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"), 'risk' => 'Medium', 'action' => 'Review before deleting, preserve recent errors for debugging.'],
    ['label' => 'Completed jobs older than 30 days', 'count' => dashboard_count('jobs_queue', "status = 'completed' AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"), 'risk' => 'Low', 'action' => 'Safe to archive or purge after reporting export.'],
    ['label' => 'Expired carts', 'count' => dashboard_count('carts', "status IN ('expired', 'abandoned') OR (expires_at IS NOT NULL AND expires_at < NOW())"), 'risk' => 'Low', 'action' => 'Can be marked expired after abandoned cart campaigns finish.'],
    ['label' => 'Old migration logs', 'count' => dashboard_count('migration_logs', "created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"), 'risk' => 'Medium', 'action' => 'Export before cleanup if migration audit evidence is needed.'],
];

$automation = [
    'views' => $automationViews,
    'stats' => [
        'pending_jobs' => (int)($jobStatusTotals['pending'] ?? 0),
        'processing_jobs' => (int)($jobStatusTotals['processing'] ?? 0),
        'failed_jobs' => (int)($jobStatusTotals['failed'] ?? 0),
        'completed_jobs' => (int)($jobStatusTotals['completed'] ?? 0),
        'queue_count' => count($queueRows),
        'worker_count' => count($workerRows),
        'active_carts' => (int)($cartSummary['active']['total'] ?? 0),
        'abandoned_carts' => (int)($cartSummary['abandoned']['total'] ?? 0),
        'mail_provider' => (string)app_setting('mail', 'mailer', 'smtp'),
        'php_error_log_size' => dashboard_human_size(dashboard_file_size(LOG_PATH . '/php_errors.log')),
        'cron_log_size' => dashboard_human_size(dashboard_file_size(LOG_PATH . '/cron.log')),
        'worker_log_size' => dashboard_human_size(dashboard_file_size(LOG_PATH . '/worker.log')),
        'mail_log_size' => dashboard_human_size(dashboard_file_size(LOG_PATH . '/mail.log')),
    ],
    'queues' => $queueRows,
    'failedJobs' => $failedJobRows,
    'emailJobs' => $emailJobRows,
    'carts' => $cartRows,
    'cartSummary' => $cartSummary,
    'cronDefinitions' => $cronDefinitions,
    'cleanupTargets' => $cleanupTargets,
    'migrationRuns' => $latestMigrationRuns,
];

$notificationChannels = [
    'in-app-notifications' => [
        'channel' => 'in_app',
        'label' => 'In-App Notifications',
        'description' => 'Messages shown inside buyer, vendor and admin dashboards.',
        'provider' => 'Database inbox',
        'queue' => 'notifications',
        'configured' => table_exists('notifications'),
    ],
    'email-notifications' => [
        'channel' => 'email',
        'label' => 'Email Notifications',
        'description' => 'Transactional and lifecycle emails delivered through the configured mail provider.',
        'provider' => strtoupper((string)app_setting('mail', 'mailer', 'smtp')),
        'queue' => 'emails',
        'configured' => (string)app_setting('mail', 'mailer', 'smtp') !== '',
    ],
    'sms-notifications' => [
        'channel' => 'sms',
        'label' => 'SMS Notifications',
        'description' => 'Phone-based alerts for order, security and delivery events.',
        'provider' => (string)app_setting('notifications', 'sms_provider', 'Not configured'),
        'queue' => 'notifications',
        'configured' => (string)app_setting('notifications', 'sms_provider', '') !== '',
    ],
    'push-notifications' => [
        'channel' => 'push',
        'label' => 'Push Notifications',
        'description' => 'Browser and device push notifications for subscribed users.',
        'provider' => (string)app_setting('notifications', 'push_provider', 'Web Push'),
        'queue' => 'notifications',
        'configured' => table_exists('push_subscriptions'),
    ],
];

$notificationChannel = $notificationChannels[$view]['channel'] ?? 'in_app';
$notificationSummaryRows = dashboard_table_rows(
    "SELECT channel, status, COUNT(*) AS total
     FROM notifications
     GROUP BY channel, status",
    [],
    'notifications'
);
$notificationSummary = [];
foreach ($notificationSummaryRows as $row) {
    $notificationSummary[(string)$row['channel']][(string)$row['status']] = (int)$row['total'];
}

$notificationRows = dashboard_table_rows(
    "SELECT n.id, n.channel, n.title, n.status, n.priority, n.recipient_email, n.recipient_phone,
            n.scheduled_at, n.sent_at, n.read_at, n.last_error, n.created_at, n.updated_at,
            u.display_name, u.email AS user_email
     FROM notifications n
     LEFT JOIN users u ON u.id = n.recipient_user_id
     WHERE n.channel = ?
     ORDER BY n.created_at DESC
     LIMIT 80",
    [$notificationChannel],
    'notifications'
);

$notificationTemplateRows = dashboard_table_rows(
    "SELECT id, channel, template_key, name, subject, is_active, updated_at
     FROM notification_templates
     WHERE channel = ?
     ORDER BY is_active DESC, name ASC
     LIMIT 80",
    [$notificationChannel],
    'notification_templates'
);

$notificationJobRows = dashboard_table_rows(
    "SELECT id,
            queue_name,
            COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.action')), queue_name) AS job_type,
            status,
            attempts,
            1 AS max_attempts,
            NULL AS last_error,
            available_at,
            created_at,
            updated_at
     FROM jobs_queue
     WHERE queue_name = ? OR payload LIKE ?
     ORDER BY created_at DESC
     LIMIT 50",
    [$notificationChannels[$view]['queue'] ?? 'notifications', '%' . str_replace('_', '-', $notificationChannel) . '%'],
    'jobs_queue'
);

$preferenceRows = dashboard_table_rows(
    "SELECT channel, COUNT(*) AS total, SUM(is_enabled = 1) AS enabled_total, SUM(is_enabled = 0) AS disabled_total
     FROM notification_preferences
     GROUP BY channel",
    [],
    'notification_preferences'
);
$preferenceSummary = [];
foreach ($preferenceRows as $row) {
    $preferenceSummary[(string)$row['channel']] = $row;
}

$pushSubscriptionStats = dashboard_table_rows(
    "SELECT status, COUNT(*) AS total, MAX(last_seen_at) AS last_seen_at
     FROM push_subscriptions
     GROUP BY status",
    [],
    'push_subscriptions'
);

$notificationsData = [
    'views' => $notificationViews,
    'channels' => $notificationChannels,
    'active' => $notificationChannels[$view] ?? $notificationChannels['in-app-notifications'],
    'summary' => $notificationSummary,
    'rows' => $notificationRows,
    'templates' => $notificationTemplateRows,
    'jobs' => $notificationJobRows,
    'preferences' => $preferenceSummary,
    'pushSubscriptionStats' => $pushSubscriptionStats,
];

$paymentSearchSql = '';
$paymentSearchParams = [];
if ($dashboardSearch !== '') {
    $paymentSearchSql = " WHERE (p.provider LIKE ? OR p.provider_reference LIKE ? OR p.provider_status LIKE ? OR p.status LIKE ? OR o.order_number LIKE ? OR u.email LIKE ? OR u.display_name LIKE ?)";
    $like = '%' . $dashboardSearch . '%';
    $paymentSearchParams = [$like, $like, $like, $like, $like, $like, $like];
}
$paymentWhere = $view === 'failed-payments'
    ? ($paymentSearchSql === '' ? " WHERE p.status = 'failed'" : $paymentSearchSql . " AND p.status = 'failed'")
    : $paymentSearchSql;
$paymentCountRow = db()->fetch(
    "SELECT COUNT(*) AS total
     FROM payments p
     LEFT JOIN orders o ON o.id = p.order_id
     LEFT JOIN users u ON u.id = p.user_id
     {$paymentWhere}",
    $paymentSearchParams
);
$paymentTotal = (int)($paymentCountRow['total'] ?? 0);
$paymentTotalPages = max(1, (int)ceil($paymentTotal / $dashboardPerPage));
$paymentPage = max(1, min((int)($_GET['page'] ?? 1), $paymentTotalPages));
$paymentOffset = ($paymentPage - 1) * $dashboardPerPage;
$paymentRows = dashboard_table_rows(
    "SELECT p.id, p.order_id, p.user_id, p.provider, p.provider_reference, p.provider_status, p.amount, p.currency,
            p.status, p.paid_at, p.created_at, p.updated_at,
            o.order_number, o.status AS order_status,
            u.display_name, u.email
     FROM payments p
     LEFT JOIN orders o ON o.id = p.order_id
     LEFT JOIN users u ON u.id = p.user_id
     {$paymentWhere}
     ORDER BY p.created_at DESC
     LIMIT {$dashboardPerPage} OFFSET {$paymentOffset}",
    $paymentSearchParams,
    'payments'
);
$paymentGatewayRows = dashboard_table_rows(
    "SELECT pm.id, pm.code, pm.name, pm.provider, pm.settings, pm.is_active,
            COUNT(p.id) AS transaction_count,
            COALESCE(SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END), 0) AS paid_amount,
            MAX(p.created_at) AS latest_transaction_at
     FROM payment_methods pm
     LEFT JOIN payments p ON p.payment_method_id = pm.id
     GROUP BY pm.id, pm.code, pm.name, pm.provider, pm.settings, pm.is_active
     ORDER BY pm.is_active DESC, pm.name ASC",
    [],
    'payment_methods'
);
$idempotencyRows = dashboard_table_rows(
    "SELECT ik.id, ik.idem_key, ik.provider, ik.order_id, ik.payment_id, ik.request_hash, ik.response_code,
            ik.status, ik.created_at, ik.updated_at, o.order_number
     FROM payment_idempotency_keys ik
     LEFT JOIN orders o ON o.id = ik.order_id
     ORDER BY ik.created_at DESC
     LIMIT 120",
    [],
    'payment_idempotency_keys'
);
$currencyRows = dashboard_table_rows(
    "SELECT code, name, symbol, exchange_rate, is_default, is_enabled, decimal_places, updated_at
     FROM currencies
     ORDER BY is_default DESC, is_enabled DESC, code ASC",
    [],
    'currencies'
);
$paymentStatusRows = dashboard_table_rows("SELECT status, COUNT(*) AS total, COALESCE(SUM(amount), 0) AS amount FROM payments GROUP BY status", [], 'payments');
$paymentStatusTotals = [];
$paymentStatusAmounts = [];
foreach ($paymentStatusRows as $row) {
    $paymentStatusTotals[(string)$row['status']] = (int)$row['total'];
    $paymentStatusAmounts[(string)$row['status']] = (float)$row['amount'];
}
$paymentsData = [
    'views' => $paymentViews,
    'transactions' => $paymentRows,
    'gateways' => $paymentGatewayRows,
    'idempotency' => $idempotencyRows,
    'currencies' => $currencyRows,
    'statusTotals' => $paymentStatusTotals,
    'statusAmounts' => $paymentStatusAmounts,
    'pagination' => [
        'page' => $paymentPage,
        'perPage' => $dashboardPerPage,
        'perPageOptions' => $dashboardPerPageOptions,
        'total' => $paymentTotal,
        'totalPages' => $paymentTotalPages,
        'from' => $paymentTotal === 0 ? 0 : $paymentOffset + 1,
        'to' => min($paymentOffset + count($paymentRows), $paymentTotal),
        'search' => $dashboardSearch,
    ],
    'stats' => [
        'total_transactions' => dashboard_count('payments'),
        'paid_transactions' => (int)($paymentStatusTotals['paid'] ?? 0),
        'failed_transactions' => (int)($paymentStatusTotals['failed'] ?? 0),
        'refunded_transactions' => (int)($paymentStatusTotals['refunded'] ?? 0),
        'gateway_count' => dashboard_count('payment_methods'),
    ],
];

$withdrawalConfig = match ($view) {
    'affiliate-withdrawals' => ['sources' => ['affiliate'], 'statuses' => []],
    'pending-withdrawals' => ['sources' => ['vendor', 'affiliate'], 'statuses' => ['pending']],
    'approved-withdrawals' => ['sources' => ['vendor', 'affiliate'], 'statuses' => ['approved', 'paid']],
    'rejected-withdrawals' => ['sources' => ['vendor', 'affiliate'], 'statuses' => ['rejected']],
    default => ['sources' => ['vendor'], 'statuses' => []],
};
$withdrawalSelects = [];
$withdrawalParams = [];
$withdrawalSearchLike = $dashboardSearch !== '' ? '%' . $dashboardSearch . '%' : null;
$withdrawalStatusSql = '';
if ($withdrawalConfig['statuses'] !== []) {
    $withdrawalStatusSql = 'status IN (' . implode(',', array_fill(0, count($withdrawalConfig['statuses']), '?')) . ')';
}

if (in_array('vendor', $withdrawalConfig['sources'], true)) {
    $where = [];
    $params = [];
    if ($withdrawalStatusSql !== '') {
        $where[] = 'vw.' . $withdrawalStatusSql;
        $params = array_merge($params, $withdrawalConfig['statuses']);
    }
    if ($withdrawalSearchLike !== null) {
        $where[] = '(v.store_name LIKE ? OR v.store_email LIKE ? OR u.email LIKE ? OR u.display_name LIKE ? OR vw.method LIKE ? OR vw.status LIKE ?)';
        $params = array_merge($params, array_fill(0, 6, $withdrawalSearchLike));
    }
    $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', array_map(static fn ($part): string => '(' . $part . ')', $where)) : '';
    $withdrawalSelects[] = "SELECT 'vendor' AS withdrawal_type, vw.id, vw.vendor_id AS owner_id, v.store_name AS owner_name,
                COALESCE(v.store_email, u.email) AS owner_email, vw.amount, vw.currency, vw.method, vw.status, vw.note,
                vw.requested_at, vw.processed_at, processor.display_name AS processed_by_name,
                v.payout_method, v.payout_details
         FROM vendor_withdrawals vw
         LEFT JOIN vendors v ON v.id = vw.vendor_id
         LEFT JOIN users u ON u.id = v.user_id
         LEFT JOIN users processor ON processor.id = vw.processed_by
         {$whereSql}";
    $withdrawalParams = array_merge($withdrawalParams, $params);
}

if (in_array('affiliate', $withdrawalConfig['sources'], true)) {
    $where = [];
    $params = [];
    if ($withdrawalStatusSql !== '') {
        $where[] = 'aw.' . $withdrawalStatusSql;
        $params = array_merge($params, $withdrawalConfig['statuses']);
    }
    if ($withdrawalSearchLike !== null) {
        $where[] = '(a.referral_code LIKE ? OR a.payment_email LIKE ? OR u.email LIKE ? OR u.display_name LIKE ? OR aw.method LIKE ? OR aw.status LIKE ?)';
        $params = array_merge($params, array_fill(0, 6, $withdrawalSearchLike));
    }
    $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', array_map(static fn ($part): string => '(' . $part . ')', $where)) : '';
    $withdrawalSelects[] = "SELECT 'affiliate' AS withdrawal_type, aw.id, aw.affiliate_id AS owner_id,
                COALESCE(u.display_name, u.email, a.referral_code) AS owner_name,
                COALESCE(a.payment_email, u.email) AS owner_email, aw.amount, aw.currency, aw.method, aw.status, aw.note,
                aw.requested_at, aw.processed_at, processor.display_name AS processed_by_name,
                'affiliate' AS payout_method, a.payment_email AS payout_details
         FROM affiliate_withdrawals aw
         LEFT JOIN affiliates a ON a.id = aw.affiliate_id
         LEFT JOIN users u ON u.id = COALESCE(aw.user_id, a.user_id)
         LEFT JOIN users processor ON processor.id = aw.processed_by
         {$whereSql}";
    $withdrawalParams = array_merge($withdrawalParams, $params);
}

$withdrawalUnionSql = $withdrawalSelects !== [] ? implode(' UNION ALL ', $withdrawalSelects) : "SELECT 'vendor' AS withdrawal_type, 0 AS id, 0 AS owner_id, '' AS owner_name, '' AS owner_email, 0 AS amount, 'USD' AS currency, '' AS method, 'pending' AS status, '' AS note, NULL AS requested_at, NULL AS processed_at, '' AS processed_by_name, '' AS payout_method, '' AS payout_details WHERE 1 = 0";
$withdrawalCountRow = db()->fetch("SELECT COUNT(*) AS total FROM ({$withdrawalUnionSql}) withdrawals", $withdrawalParams);
$withdrawalTotal = (int)($withdrawalCountRow['total'] ?? 0);
$withdrawalTotalPages = max(1, (int)ceil($withdrawalTotal / $dashboardPerPage));
$withdrawalPage = max(1, min((int)($_GET['page'] ?? 1), $withdrawalTotalPages));
$withdrawalOffset = ($withdrawalPage - 1) * $dashboardPerPage;
$withdrawalRows = dashboard_table_rows(
    "SELECT * FROM ({$withdrawalUnionSql}) withdrawals
     ORDER BY requested_at DESC, id DESC
     LIMIT {$dashboardPerPage} OFFSET {$withdrawalOffset}",
    $withdrawalParams
);

$vendorWithdrawalStatusRows = dashboard_table_rows("SELECT status, COUNT(*) AS total, COALESCE(SUM(amount), 0) AS amount FROM vendor_withdrawals GROUP BY status", [], 'vendor_withdrawals');
$affiliateWithdrawalStatusRows = dashboard_table_rows("SELECT status, COUNT(*) AS total, COALESCE(SUM(amount), 0) AS amount FROM affiliate_withdrawals GROUP BY status", [], 'affiliate_withdrawals');
$withdrawalStatusTotals = [];
$withdrawalStatusAmounts = [];
foreach (array_merge($vendorWithdrawalStatusRows, $affiliateWithdrawalStatusRows) as $row) {
    $status = (string)$row['status'];
    $withdrawalStatusTotals[$status] = ($withdrawalStatusTotals[$status] ?? 0) + (int)$row['total'];
    $withdrawalStatusAmounts[$status] = ($withdrawalStatusAmounts[$status] ?? 0) + (float)$row['amount'];
}
$withdrawalsData = [
    'views' => $withdrawalViews,
    'rows' => $withdrawalRows,
    'statusTotals' => $withdrawalStatusTotals,
    'statusAmounts' => $withdrawalStatusAmounts,
    'pagination' => [
        'page' => $withdrawalPage,
        'perPage' => $dashboardPerPage,
        'perPageOptions' => $dashboardPerPageOptions,
        'total' => $withdrawalTotal,
        'totalPages' => $withdrawalTotalPages,
        'from' => $withdrawalTotal === 0 ? 0 : $withdrawalOffset + 1,
        'to' => min($withdrawalOffset + count($withdrawalRows), $withdrawalTotal),
        'search' => $dashboardSearch,
    ],
    'stats' => [
        'vendor_withdrawals' => dashboard_count('vendor_withdrawals'),
        'affiliate_withdrawals' => dashboard_count('affiliate_withdrawals'),
        'pending_withdrawals' => (int)($withdrawalStatusTotals['pending'] ?? 0),
        'approved_withdrawals' => (int)($withdrawalStatusTotals['approved'] ?? 0) + (int)($withdrawalStatusTotals['paid'] ?? 0),
        'rejected_withdrawals' => (int)($withdrawalStatusTotals['rejected'] ?? 0),
        'pending_amount' => (float)($withdrawalStatusAmounts['pending'] ?? 0),
    ],
];

$shippingSearchLike = $dashboardSearch !== '' ? '%' . $dashboardSearch . '%' : null;
$shippingWhere = '';
$shippingParams = [];
$shippingBaseCountSql = '';
$shippingRowsSql = '';

if ($view === 'shipping-zones') {
    if ($shippingSearchLike !== null) {
        $shippingWhere = ' WHERE z.name LIKE ? OR v.store_name LIKE ?';
        $shippingParams = [$shippingSearchLike, $shippingSearchLike];
    }
    $shippingBaseCountSql = "SELECT COUNT(DISTINCT z.id) AS total FROM shipping_zones z LEFT JOIN vendors v ON v.id = z.vendor_id {$shippingWhere}";
    $shippingRowsSql = "SELECT z.id, z.wp_zone_id, z.vendor_id, z.name, z.sort_order, z.is_active, z.created_at, z.updated_at,
                v.store_name, COALESCE(method_stats.method_count, 0) AS method_count
         FROM shipping_zones z
         LEFT JOIN vendors v ON v.id = z.vendor_id
         LEFT JOIN (
            SELECT zone_id, COUNT(*) AS method_count
            FROM shipping_methods
            GROUP BY zone_id
         ) method_stats ON method_stats.zone_id = z.id
         {$shippingWhere}
         ORDER BY z.sort_order ASC, z.name ASC";
} elseif ($view === 'shipping-rates') {
    if ($shippingSearchLike !== null) {
        $shippingWhere = ' WHERE m.name LIKE ? OR m.code LIKE ? OR z.name LIKE ? OR c.name LIKE ? OR v.store_name LIKE ?';
        $shippingParams = array_fill(0, 5, $shippingSearchLike);
    }
    $shippingBaseCountSql = "SELECT COUNT(DISTINCT m.id) AS total FROM shipping_methods m LEFT JOIN shipping_zones z ON z.id = m.zone_id LEFT JOIN shipping_carriers c ON c.id = m.carrier_id LEFT JOIN vendors v ON v.id = m.vendor_id {$shippingWhere}";
    $shippingRowsSql = "SELECT m.id, m.zone_id, m.carrier_id, m.vendor_id, m.code, m.name, m.calculation_type, m.base_cost,
                m.min_order_amount, m.settings, m.is_active, m.created_at, m.updated_at,
                z.name AS zone_name, c.name AS carrier_name, v.store_name,
                r.condition_type, r.min_value, r.max_value, r.cost, r.per_item_cost, r.sort_order AS rate_sort_order
         FROM shipping_methods m
         LEFT JOIN shipping_zones z ON z.id = m.zone_id
         LEFT JOIN shipping_carriers c ON c.id = m.carrier_id
         LEFT JOIN vendors v ON v.id = m.vendor_id
         LEFT JOIN shipping_rates r ON r.method_id = m.id
         {$shippingWhere}
         ORDER BY z.sort_order ASC, m.name ASC, r.sort_order ASC";
} elseif ($view === 'shipment-tracking') {
    if ($shippingSearchLike !== null) {
        $shippingWhere = ' WHERE s.tracking_number LIKE ? OR s.status LIKE ? OR o.order_number LIKE ? OR c.name LIKE ? OR v.store_name LIKE ?';
        $shippingParams = array_fill(0, 5, $shippingSearchLike);
    }
    $shippingBaseCountSql = "SELECT COUNT(DISTINCT s.id) AS total FROM shipments s LEFT JOIN orders o ON o.id = s.order_id LEFT JOIN shipping_carriers c ON c.id = s.carrier_id LEFT JOIN vendors v ON v.id = s.vendor_id {$shippingWhere}";
    $shippingRowsSql = "SELECT s.id, s.order_id, s.vendor_id, s.carrier_id, s.shipping_method_id, s.tracking_number, s.tracking_url,
                s.status, s.shipped_at, s.delivered_at, s.cost, s.created_at, s.updated_at,
                o.order_number, o.currency, c.name AS carrier_name, c.tracking_url_template, v.store_name, m.name AS method_name
         FROM shipments s
         LEFT JOIN orders o ON o.id = s.order_id
         LEFT JOIN shipping_carriers c ON c.id = s.carrier_id
         LEFT JOIN vendors v ON v.id = s.vendor_id
         LEFT JOIN shipping_methods m ON m.id = s.shipping_method_id
         {$shippingWhere}
         ORDER BY s.updated_at DESC, s.id DESC";
} elseif ($view === 'delivery-partners') {
    if ($shippingSearchLike !== null) {
        $shippingWhere = ' WHERE dp.name LIKE ? OR dp.code LIKE ? OR dp.contact_email LIKE ? OR dp.contact_phone LIKE ? OR c.name LIKE ?';
        $shippingParams = array_fill(0, 5, $shippingSearchLike);
    }
    $shippingBaseCountSql = "SELECT COUNT(DISTINCT dp.id) AS total FROM delivery_partners dp LEFT JOIN shipping_carriers c ON c.id = dp.carrier_id {$shippingWhere}";
    $shippingRowsSql = "SELECT dp.id, dp.carrier_id, dp.name, dp.code, dp.contact_email, dp.contact_phone, dp.service_regions,
                dp.tracking_url_template, dp.is_active, dp.created_at, dp.updated_at, c.name AS carrier_name
         FROM delivery_partners dp
         LEFT JOIN shipping_carriers c ON c.id = dp.carrier_id
         {$shippingWhere}
         ORDER BY dp.is_active DESC, dp.name ASC";
}

$shippingTotal = 0;
$shippingRows = [];
if ($shippingBaseCountSql !== '') {
    $row = db()->fetch($shippingBaseCountSql, $shippingParams);
    $shippingTotal = (int)($row['total'] ?? 0);
    $shippingTotalPages = max(1, (int)ceil($shippingTotal / $dashboardPerPage));
    $shippingPage = max(1, min((int)($_GET['page'] ?? 1), $shippingTotalPages));
    $shippingOffset = ($shippingPage - 1) * $dashboardPerPage;
    $shippingRows = dashboard_table_rows($shippingRowsSql . " LIMIT {$dashboardPerPage} OFFSET {$shippingOffset}", $shippingParams);
} else {
    $shippingTotalPages = 1;
    $shippingPage = 1;
    $shippingOffset = 0;
}

$aramexFields = [
    ['key' => 'enabled', 'default' => 'off'],
    ['key' => 'environment', 'default' => 'test'],
    ['key' => 'account_number', 'default' => ''],
    ['key' => 'account_pin', 'default' => ''],
    ['key' => 'account_entity', 'default' => ''],
    ['key' => 'account_country_code', 'default' => 'US'],
    ['key' => 'username', 'default' => ''],
    ['key' => 'password', 'default' => ''],
    ['key' => 'api_base_url', 'default' => 'https://ws.aramex.net/ShippingAPI.V2/Shipping/Service_1_0.svc'],
    ['key' => 'shipper_name', 'default' => 'Seller Africa'],
    ['key' => 'shipper_phone', 'default' => ''],
    ['key' => 'shipper_email', 'default' => ''],
    ['key' => 'shipper_address', 'default' => ''],
    ['key' => 'shipper_city', 'default' => ''],
    ['key' => 'shipper_state', 'default' => ''],
    ['key' => 'shipper_postcode', 'default' => ''],
    ['key' => 'shipper_country_code', 'default' => 'US'],
];
$shippingEdit = null;
$shippingEditId = max(0, (int)($_GET['edit'] ?? 0));
if ($shippingEditId > 0) {
    if ($view === 'shipping-zones') {
        $shippingEdit = db()->fetch('SELECT * FROM shipping_zones WHERE id = ? LIMIT 1', [$shippingEditId]);
    } elseif ($view === 'shipping-rates') {
        $shippingEdit = db()->fetch(
            "SELECT m.*, r.condition_type, r.min_value, r.max_value, r.cost, r.per_item_cost, r.sort_order AS rate_sort_order
             FROM shipping_methods m
             LEFT JOIN shipping_rates r ON r.method_id = m.id
             WHERE m.id = ?
             LIMIT 1",
            [$shippingEditId]
        );
    } elseif ($view === 'shipment-tracking') {
        $shippingEdit = db()->fetch('SELECT * FROM shipments WHERE id = ? LIMIT 1', [$shippingEditId]);
    } elseif ($view === 'delivery-partners') {
        $shippingEdit = db()->fetch('SELECT * FROM delivery_partners WHERE id = ? LIMIT 1', [$shippingEditId]);
    }
}
$shippingCarrierRows = dashboard_table_rows('SELECT id, code, name, tracking_url_template, is_active FROM shipping_carriers ORDER BY is_active DESC, name ASC', [], 'shipping_carriers');
$shippingZoneRows = dashboard_table_rows('SELECT id, name FROM shipping_zones ORDER BY sort_order ASC, name ASC', [], 'shipping_zones');
$shippingVendorRows = dashboard_table_rows('SELECT id, store_name FROM vendors ORDER BY store_name ASC LIMIT 1000', [], 'vendors');
$shipmentStatusRows = dashboard_table_rows("SELECT status, COUNT(*) AS total FROM shipments GROUP BY status", [], 'shipments');
$shipmentStatusTotals = [];
foreach ($shipmentStatusRows as $row) {
    $shipmentStatusTotals[(string)$row['status']] = (int)$row['total'];
}
$shippingData = [
    'views' => $shippingViews,
    'rows' => $shippingRows,
    'edit' => $shippingEdit,
    'showForm' => isset($_GET['new']) || $shippingEdit !== null,
    'carriers' => $shippingCarrierRows,
    'zones' => $shippingZoneRows,
    'vendors' => $shippingVendorRows,
    'aramex' => dashboard_setting_values('shipping_aramex', $aramexFields),
    'pagination' => [
        'page' => $shippingPage,
        'perPage' => $dashboardPerPage,
        'perPageOptions' => $dashboardPerPageOptions,
        'total' => $shippingTotal,
        'totalPages' => $shippingTotalPages,
        'from' => $shippingTotal === 0 ? 0 : $shippingOffset + 1,
        'to' => min($shippingOffset + count($shippingRows), $shippingTotal),
        'search' => $dashboardSearch,
    ],
    'stats' => [
        'zones' => dashboard_count('shipping_zones'),
        'methods' => dashboard_count('shipping_methods'),
        'rates' => dashboard_count('shipping_rates'),
        'carriers' => dashboard_count('shipping_carriers'),
        'partners' => dashboard_count('delivery_partners'),
        'shipments' => dashboard_count('shipments'),
        'in_transit' => (int)($shipmentStatusTotals['in_transit'] ?? 0) + (int)($shipmentStatusTotals['out_for_delivery'] ?? 0),
        'delivered' => (int)($shipmentStatusTotals['delivered'] ?? 0),
    ],
];

$contentRows = [];
$contentEdit = null;
$contentAlbums = dashboard_table_rows('SELECT id, title FROM content_albums ORDER BY sort_order ASC, title ASC', [], 'content_albums');
$contentSearchLike = $dashboardSearch !== '' ? '%' . $dashboardSearch . '%' : null;
if (in_array($view, $contentViews, true)) {
    $contentEditId = max(0, (int)($_GET['edit'] ?? 0));
    if ($view === 'albums') {
        $contentRows = dashboard_table_rows(
            "SELECT a.*, COALESCE(item_stats.item_count, 0) AS item_count
             FROM content_albums a
             LEFT JOIN (
                SELECT album_id, COUNT(*) AS item_count
                FROM content_gallery_items
                GROUP BY album_id
             ) item_stats ON item_stats.album_id = a.id
             " . ($contentSearchLike !== null ? 'WHERE a.title LIKE ? OR a.description LIKE ?' : '') . "
             ORDER BY a.sort_order ASC, a.updated_at DESC",
            $contentSearchLike !== null ? [$contentSearchLike, $contentSearchLike] : [],
            'content_albums'
        );
        $contentEdit = $contentEditId > 0 ? db()->fetch('SELECT * FROM content_albums WHERE id = ? LIMIT 1', [$contentEditId]) : null;
    } elseif ($view === 'gallery') {
        $contentRows = dashboard_table_rows(
            "SELECT g.*, a.title AS album_title
             FROM content_gallery_items g
             LEFT JOIN content_albums a ON a.id = g.album_id
             " . ($contentSearchLike !== null ? 'WHERE g.title LIKE ? OR g.caption LIKE ? OR a.title LIKE ?' : '') . "
             ORDER BY COALESCE(a.sort_order, 9999) ASC, g.sort_order ASC, g.updated_at DESC",
            $contentSearchLike !== null ? [$contentSearchLike, $contentSearchLike, $contentSearchLike] : [],
            'content_gallery_items'
        );
        $contentEdit = $contentEditId > 0 ? db()->fetch('SELECT * FROM content_gallery_items WHERE id = ? LIMIT 1', [$contentEditId]) : null;
    } else {
        $contentRows = dashboard_table_rows(
            "SELECT *
             FROM content_posts
             " . ($contentSearchLike !== null ? 'WHERE title LIKE ? OR excerpt LIKE ? OR category LIKE ?' : '') . "
             ORDER BY COALESCE(published_at, created_at) DESC, id DESC",
            $contentSearchLike !== null ? [$contentSearchLike, $contentSearchLike, $contentSearchLike] : [],
            'content_posts'
        );
        $contentEdit = $contentEditId > 0 ? db()->fetch('SELECT * FROM content_posts WHERE id = ? LIMIT 1', [$contentEditId]) : null;
    }
}
$contentData = [
    'rows' => $contentRows,
    'albums' => $contentAlbums,
    'edit' => $contentEdit,
    'showForm' => isset($_GET['new']) || $contentEdit !== null,
    'stats' => [
        'albums' => dashboard_count('content_albums'),
        'gallery' => dashboard_count('content_gallery_items'),
        'posts' => dashboard_count('content_posts'),
        'published_posts' => dashboard_count('content_posts', "status = 'published'"),
    ],
];

$affiliateSearchLike = $dashboardSearch !== '' ? '%' . $dashboardSearch . '%' : null;
$affiliateWhere = '';
$affiliateParams = [];
$affiliateBaseCountSql = '';
$affiliateRowsSql = '';

if ($view === 'affiliates') {
    if ($affiliateSearchLike !== null) {
        $affiliateWhere = ' WHERE a.referral_code LIKE ? OR a.payment_email LIKE ? OR a.status LIKE ? OR u.email LIKE ? OR u.display_name LIKE ?';
        $affiliateParams = array_fill(0, 5, $affiliateSearchLike);
    }
    $affiliateBaseCountSql = "SELECT COUNT(DISTINCT a.id) AS total FROM affiliates a LEFT JOIN users u ON u.id = a.user_id {$affiliateWhere}";
    $affiliateRowsSql = "SELECT a.id, a.user_id, a.parent_affiliate_id, a.referral_code, a.payment_email, a.rate_type,
                a.commission_rate, a.status, a.visits_count, a.referrals_count, a.total_earnings, a.unpaid_earnings,
                a.registered_at, a.created_at, a.updated_at, u.display_name, u.email, u.status AS user_status,
                parent.referral_code AS parent_referral_code
         FROM affiliates a
         LEFT JOIN users u ON u.id = a.user_id
         LEFT JOIN affiliates parent ON parent.id = a.parent_affiliate_id
         {$affiliateWhere}
         ORDER BY a.created_at DESC, a.id DESC";
} elseif ($view === 'referral-links') {
    if ($affiliateSearchLike !== null) {
        $affiliateWhere = ' WHERE l.target_url LIKE ? OR l.token LIKE ? OR c.name LIKE ? OR a.referral_code LIKE ? OR u.email LIKE ?';
        $affiliateParams = array_fill(0, 5, $affiliateSearchLike);
    }
    $affiliateBaseCountSql = "SELECT COUNT(DISTINCT l.id) AS total FROM affiliate_links l LEFT JOIN affiliates a ON a.id = l.affiliate_id LEFT JOIN affiliate_campaigns c ON c.id = l.campaign_id LEFT JOIN users u ON u.id = a.user_id {$affiliateWhere}";
    $affiliateRowsSql = "SELECT l.id, l.affiliate_id, l.campaign_id, l.target_url, l.token, l.clicks_count, l.created_at,
                a.referral_code, a.status AS affiliate_status, c.name AS campaign_name, c.slug AS campaign_slug,
                u.display_name, u.email
         FROM affiliate_links l
         LEFT JOIN affiliates a ON a.id = l.affiliate_id
         LEFT JOIN affiliate_campaigns c ON c.id = l.campaign_id
         LEFT JOIN users u ON u.id = a.user_id
         {$affiliateWhere}
         ORDER BY l.created_at DESC, l.id DESC";
} elseif ($view === 'commission-rules') {
    if ($affiliateSearchLike !== null) {
        $affiliateWhere = ' WHERE name LIKE ? OR rule_type LIKE ? OR rate_type LIKE ? OR notes LIKE ?';
        $affiliateParams = array_fill(0, 4, $affiliateSearchLike);
    }
    $affiliateBaseCountSql = "SELECT COUNT(*) AS total FROM affiliate_commission_rules {$affiliateWhere}";
    $affiliateRowsSql = "SELECT id, name, rule_type, target_id, rate_type, commission_rate, currency, min_order_amount,
                priority, starts_at, ends_at, is_active, notes, created_at, updated_at
         FROM affiliate_commission_rules
         {$affiliateWhere}
         ORDER BY is_active DESC, priority ASC, updated_at DESC";
} elseif (in_array($view, ['pending-commissions', 'approved-commissions'], true)) {
    $statusFilter = $view === 'pending-commissions' ? ['pending'] : ['unpaid', 'paid'];
    $whereParts = ['r.status IN (' . implode(',', array_fill(0, count($statusFilter), '?')) . ')'];
    $affiliateParams = $statusFilter;
    if ($affiliateSearchLike !== null) {
        $whereParts[] = '(r.reference LIKE ? OR r.description LIKE ? OR r.status LIKE ? OR a.referral_code LIKE ? OR u.email LIKE ? OR o.order_number LIKE ?)';
        $affiliateParams = array_merge($affiliateParams, array_fill(0, 6, $affiliateSearchLike));
    }
    $affiliateWhere = ' WHERE ' . implode(' AND ', array_map(static fn ($part): string => '(' . $part . ')', $whereParts));
    $affiliateBaseCountSql = "SELECT COUNT(DISTINCT r.id) AS total FROM affiliate_referrals r LEFT JOIN affiliates a ON a.id = r.affiliate_id LEFT JOIN users u ON u.id = a.user_id LEFT JOIN orders o ON o.id = r.order_id {$affiliateWhere}";
    $affiliateRowsSql = "SELECT r.id, r.affiliate_id, r.order_id, r.customer_id, r.status, r.amount, r.order_total,
                r.currency, r.description, r.reference, r.created_at, r.paid_at,
                a.referral_code, a.payment_email, u.display_name, u.email, o.order_number
         FROM affiliate_referrals r
         LEFT JOIN affiliates a ON a.id = r.affiliate_id
         LEFT JOIN users u ON u.id = a.user_id
         LEFT JOIN orders o ON o.id = r.order_id
         {$affiliateWhere}
         ORDER BY r.created_at DESC, r.id DESC";
} elseif ($view === 'affiliate-payouts') {
    if ($affiliateSearchLike !== null) {
        $affiliateWhere = ' WHERE p.payment_method LIKE ? OR p.payment_reference LIKE ? OR p.status LIKE ? OR a.referral_code LIKE ? OR u.email LIKE ?';
        $affiliateParams = array_fill(0, 5, $affiliateSearchLike);
    }
    $affiliateBaseCountSql = "SELECT COUNT(DISTINCT p.id) AS total FROM affiliate_payouts p LEFT JOIN affiliates a ON a.id = p.affiliate_id LEFT JOIN users u ON u.id = a.user_id {$affiliateWhere}";
    $affiliateRowsSql = "SELECT p.id, p.affiliate_id, p.amount, p.currency, p.payment_method, p.payment_reference,
                p.status, p.requested_at, p.paid_at, p.created_at,
                a.referral_code, a.payment_email, u.display_name, u.email,
                COALESCE(pr.referral_count, 0) AS referral_count
         FROM affiliate_payouts p
         LEFT JOIN affiliates a ON a.id = p.affiliate_id
         LEFT JOIN users u ON u.id = a.user_id
         LEFT JOIN (
            SELECT payout_id, COUNT(*) AS referral_count
            FROM affiliate_payout_referrals
            GROUP BY payout_id
         ) pr ON pr.payout_id = p.id
         {$affiliateWhere}
         ORDER BY p.created_at DESC, p.id DESC";
}

$affiliateTotal = 0;
$affiliateRows = [];
if ($affiliateBaseCountSql !== '') {
    $row = db()->fetch($affiliateBaseCountSql, $affiliateParams);
    $affiliateTotal = (int)($row['total'] ?? 0);
    $affiliateTotalPages = max(1, (int)ceil($affiliateTotal / $dashboardPerPage));
    $affiliatePage = max(1, min((int)($_GET['page'] ?? 1), $affiliateTotalPages));
    $affiliateOffset = ($affiliatePage - 1) * $dashboardPerPage;
    $affiliateRows = dashboard_table_rows($affiliateRowsSql . " LIMIT {$dashboardPerPage} OFFSET {$affiliateOffset}", $affiliateParams);
} else {
    $affiliateTotalPages = 1;
    $affiliatePage = 1;
    $affiliateOffset = 0;
}

$affiliateRuleEdit = null;
$affiliateRuleEditId = max(0, (int)($_GET['edit'] ?? 0));
if ($view === 'commission-rules' && $affiliateRuleEditId > 0) {
    $affiliateRuleEdit = db()->fetch('SELECT * FROM affiliate_commission_rules WHERE id = ? LIMIT 1', [$affiliateRuleEditId]);
}

$affiliateStatusRows = dashboard_table_rows("SELECT status, COUNT(*) AS total FROM affiliates GROUP BY status", [], 'affiliates');
$affiliateStatusTotals = [];
foreach ($affiliateStatusRows as $row) {
    $affiliateStatusTotals[(string)$row['status']] = (int)$row['total'];
}
$affiliateReferralStatusRows = dashboard_table_rows("SELECT status, COUNT(*) AS total, COALESCE(SUM(amount), 0) AS amount FROM affiliate_referrals GROUP BY status", [], 'affiliate_referrals');
$affiliateReferralStatusTotals = [];
$affiliateReferralStatusAmounts = [];
foreach ($affiliateReferralStatusRows as $row) {
    $affiliateReferralStatusTotals[(string)$row['status']] = (int)$row['total'];
    $affiliateReferralStatusAmounts[(string)$row['status']] = (float)$row['amount'];
}
$affiliatePayoutStatusRows = dashboard_table_rows("SELECT status, COUNT(*) AS total, COALESCE(SUM(amount), 0) AS amount FROM affiliate_payouts GROUP BY status", [], 'affiliate_payouts');
$affiliatePayoutStatusTotals = [];
$affiliatePayoutStatusAmounts = [];
foreach ($affiliatePayoutStatusRows as $row) {
    $affiliatePayoutStatusTotals[(string)$row['status']] = (int)$row['total'];
    $affiliatePayoutStatusAmounts[(string)$row['status']] = (float)$row['amount'];
}
$affiliateData = [
    'views' => $affiliateViews,
    'rows' => $affiliateRows,
    'ruleEdit' => $affiliateRuleEdit,
    'showRuleForm' => $view === 'commission-rules' && (isset($_GET['new']) || $affiliateRuleEdit !== null),
    'pagination' => [
        'page' => $affiliatePage,
        'perPage' => $dashboardPerPage,
        'perPageOptions' => $dashboardPerPageOptions,
        'total' => $affiliateTotal,
        'totalPages' => $affiliateTotalPages,
        'from' => $affiliateTotal === 0 ? 0 : $affiliateOffset + 1,
        'to' => min($affiliateOffset + count($affiliateRows), $affiliateTotal),
        'search' => $dashboardSearch,
    ],
    'statusTotals' => $affiliateStatusTotals,
    'referralStatusTotals' => $affiliateReferralStatusTotals,
    'referralStatusAmounts' => $affiliateReferralStatusAmounts,
    'payoutStatusTotals' => $affiliatePayoutStatusTotals,
    'payoutStatusAmounts' => $affiliatePayoutStatusAmounts,
    'stats' => [
        'affiliates' => dashboard_count('affiliates'),
        'active_affiliates' => (int)($affiliateStatusTotals['active'] ?? 0),
        'pending_affiliates' => (int)($affiliateStatusTotals['pending'] ?? 0),
        'referral_links' => dashboard_count('affiliate_links'),
        'pending_commissions' => (int)($affiliateReferralStatusTotals['pending'] ?? 0),
        'approved_commissions' => (int)($affiliateReferralStatusTotals['unpaid'] ?? 0) + (int)($affiliateReferralStatusTotals['paid'] ?? 0),
        'unpaid_commission_amount' => (float)($affiliateReferralStatusAmounts['unpaid'] ?? 0),
        'payouts' => dashboard_count('affiliate_payouts'),
        'paid_payout_amount' => (float)($affiliatePayoutStatusAmounts['paid'] ?? 0),
        'rules' => dashboard_count('affiliate_commission_rules'),
    ],
];

$marketingViews = ['coupons', 'popups', 'sliders', 'ads-banners', 'announcements', 'email-campaigns', 'abandoned-cart-emails', 'marketing-pixels'];
$marketingLinks = [
    'coupons' => 'Coupons',
    'popups' => 'Popups',
    'sliders' => 'Sliders',
    'ads-banners' => 'Ads Banners',
    'announcements' => 'Announcements',
    'email-campaigns' => 'Email Campaigns',
    'abandoned-cart-emails' => 'Abandoned Cart Emails',
    'marketing-pixels' => 'Pixels',
];
$marketingSearchLike = $dashboardSearch !== '' ? '%' . $dashboardSearch . '%' : null;
$marketingRows = [];
$marketingTotal = 0;
$marketingPage = 1;
$marketingOffset = 0;
$marketingTotalPages = 1;
$couponEdit = null;
$couponEditId = max(0, (int)($_GET['edit'] ?? 0));
$showCampaignForm = $view === 'email-campaigns' && isset($_GET['new']);
$marketingEdit = null;
$marketingEditId = max(0, (int)($_GET['edit'] ?? 0));
$showMarketingForm = in_array($view, ['sliders', 'ads-banners'], true) && (isset($_GET['new']) || $marketingEditId > 0);
$marketingPixelFields = dashboard_marketing_pixel_fields();
$marketingPixelValues = dashboard_setting_values('marketing_pixels', $marketingPixelFields);

if ($view === 'coupons') {
    $couponWhere = '';
    $couponParams = [];
    if ($marketingSearchLike !== null) {
        $couponWhere = 'WHERE c.code LIKE ? OR c.description LIKE ? OR c.discount_type LIKE ? OR c.status LIKE ? OR v.store_name LIKE ?';
        $couponParams = array_fill(0, 5, $marketingSearchLike);
    }

    $row = db()->fetch(
        "SELECT COUNT(DISTINCT c.id) AS total
         FROM coupons c
         LEFT JOIN vendors v ON v.id = c.vendor_id
         {$couponWhere}",
        $couponParams
    );
    $marketingTotal = (int)($row['total'] ?? 0);
    $marketingTotalPages = max(1, (int)ceil($marketingTotal / $dashboardPerPage));
    $marketingPage = max(1, min((int)($_GET['page'] ?? 1), $marketingTotalPages));
    $marketingOffset = ($marketingPage - 1) * $dashboardPerPage;
    $marketingRows = dashboard_table_rows(
        "SELECT c.id, c.vendor_id, c.code, c.description, c.discount_type, c.amount, c.minimum_amount,
                c.maximum_discount, c.usage_limit, c.usage_limit_per_user, c.used_count, c.starts_at,
                c.expires_at, c.status, c.created_at, c.updated_at, v.store_name,
                COALESCE(r.redemption_count, 0) AS redemption_count,
                COALESCE(r.discount_total, 0) AS discount_total
         FROM coupons c
         LEFT JOIN vendors v ON v.id = c.vendor_id
         LEFT JOIN (
            SELECT coupon_id, COUNT(*) AS redemption_count, SUM(discount_amount) AS discount_total
            FROM coupon_redemptions
            GROUP BY coupon_id
         ) r ON r.coupon_id = c.id
         {$couponWhere}
         ORDER BY FIELD(c.status, 'active', 'inactive', 'expired'), c.updated_at DESC, c.id DESC
         LIMIT {$dashboardPerPage} OFFSET {$marketingOffset}",
        $couponParams,
        'coupons'
    );

    if ($couponEditId > 0) {
        $couponEdit = db()->fetch('SELECT * FROM coupons WHERE id = ? LIMIT 1', [$couponEditId]);
    }
} elseif (in_array($view, $marketingViews, true) && $view !== 'marketing-pixels') {
	    $marketingMap = [
        'popups' => ['table' => 'marketing_popups', 'label' => 'Popup', 'order' => 'updated_at DESC'],
        'sliders' => ['table' => 'marketing_sliders', 'label' => 'Slider', 'order' => 'sort_order ASC, updated_at DESC'],
        'ads-banners' => ['table' => 'marketing_banners', 'label' => 'Ads Banner', 'order' => 'updated_at DESC'],
        'announcements' => ['table' => 'marketing_announcements', 'label' => 'Announcement', 'order' => 'updated_at DESC'],
        'email-campaigns' => ['table' => 'marketing_email_campaigns', 'label' => 'Email Campaign', 'order' => 'updated_at DESC'],
        'abandoned-cart-emails' => ['table' => 'marketing_abandoned_cart_emails', 'label' => 'Abandoned Cart Email', 'order' => 'delay_minutes ASC, updated_at DESC'],
    ];
    $map = $marketingMap[$view] ?? null;
	    if ($map && table_exists($map['table'])) {
	        $where = '';
	        $params = [];
	        if ($marketingSearchLike !== null) {
	            if (in_array($view, ['popups', 'sliders', 'ads-banners', 'announcements'], true)) {
	                $where = 'WHERE title LIKE ? OR status LIKE ?';
	                $params = [$marketingSearchLike, $marketingSearchLike];
	            } elseif ($view === 'email-campaigns') {
	                $where = 'WHERE name LIKE ? OR subject LIKE ? OR audience LIKE ? OR status LIKE ?';
	                $params = array_fill(0, 4, $marketingSearchLike);
	            } else {
	                $where = 'WHERE name LIKE ? OR subject LIKE ? OR status LIKE ?';
	                $params = array_fill(0, 3, $marketingSearchLike);
	            }
	        }
        $row = db()->fetch("SELECT COUNT(*) AS total FROM {$map['table']} {$where}", $params);
        $marketingTotal = (int)($row['total'] ?? 0);
        $marketingTotalPages = max(1, (int)ceil($marketingTotal / $dashboardPerPage));
        $marketingPage = max(1, min((int)($_GET['page'] ?? 1), $marketingTotalPages));
        $marketingOffset = ($marketingPage - 1) * $dashboardPerPage;
        $marketingRows = dashboard_table_rows(
            "SELECT * FROM {$map['table']} {$where} ORDER BY {$map['order']} LIMIT {$dashboardPerPage} OFFSET {$marketingOffset}",
            $params,
            $map['table']
        );

        if ($marketingEditId > 0 && in_array($view, ['sliders', 'ads-banners'], true)) {
            $marketingEdit = db()->fetch("SELECT * FROM {$map['table']} WHERE id = ? LIMIT 1", [$marketingEditId]);
            $showMarketingForm = $marketingEdit !== null;
        }
    }
}

$couponStatusRows = dashboard_table_rows("SELECT status, COUNT(*) AS total FROM coupons GROUP BY status", [], 'coupons');
$couponStatusTotals = [];
foreach ($couponStatusRows as $row) {
    $couponStatusTotals[(string)$row['status']] = (int)$row['total'];
}
$couponRedemptionStats = table_exists('coupon_redemptions')
    ? (db()->fetch('SELECT COUNT(*) AS total, COALESCE(SUM(discount_amount), 0) AS amount FROM coupon_redemptions') ?: ['total' => 0, 'amount' => 0])
    : ['total' => 0, 'amount' => 0];
$marketingData = [
    'views' => $marketingViews,
    'links' => $marketingLinks,
    'rows' => $marketingRows,
	    'couponEdit' => $couponEdit,
	    'showCouponForm' => $view === 'coupons' && (isset($_GET['new']) || $couponEdit !== null),
	    'showCampaignForm' => $showCampaignForm,
	    'showMarketingForm' => $showMarketingForm,
	    'marketingEdit' => $marketingEdit,
        'pixelFields' => $marketingPixelFields,
        'pixelValues' => $marketingPixelValues,
	    'pendingEmailJobs' => dashboard_count('jobs_queue', "queue_name = 'emails' AND status = 'pending'"),
    'vendors' => dashboard_table_rows("SELECT id, store_name FROM vendors WHERE status IN ('active', 'pending') ORDER BY store_name ASC LIMIT 1000", [], 'vendors'),
    'recentRedemptions' => dashboard_table_rows(
        "SELECT cr.id, cr.discount_amount, cr.created_at, c.code, o.order_number, u.email, u.display_name
         FROM coupon_redemptions cr
         INNER JOIN coupons c ON c.id = cr.coupon_id
         LEFT JOIN orders o ON o.id = cr.order_id
         LEFT JOIN users u ON u.id = cr.user_id
         ORDER BY cr.created_at DESC
         LIMIT 12",
        [],
        'coupon_redemptions'
    ),
    'pagination' => [
        'page' => $marketingPage,
        'perPage' => $dashboardPerPage,
        'perPageOptions' => $dashboardPerPageOptions,
        'total' => $marketingTotal,
        'totalPages' => $marketingTotalPages,
        'from' => $marketingTotal === 0 ? 0 : $marketingOffset + 1,
        'to' => min($marketingOffset + count($marketingRows), $marketingTotal),
        'search' => $dashboardSearch,
    ],
    'stats' => [
        'coupons' => dashboard_count('coupons'),
        'active_coupons' => (int)($couponStatusTotals['active'] ?? 0),
        'inactive_coupons' => (int)($couponStatusTotals['inactive'] ?? 0),
        'expired_coupons' => (int)($couponStatusTotals['expired'] ?? 0),
        'redemptions' => (int)($couponRedemptionStats['total'] ?? 0),
        'discount_total' => (float)($couponRedemptionStats['amount'] ?? 0),
        'popups' => dashboard_count('marketing_popups'),
        'sliders' => dashboard_count('marketing_sliders'),
        'banners' => dashboard_count('marketing_banners'),
        'announcements' => dashboard_count('marketing_announcements'),
        'campaigns' => dashboard_count('marketing_email_campaigns'),
        'abandoned_cart_emails' => dashboard_count('marketing_abandoned_cart_emails'),
    ],
];

$refundDisputeSearchLike = $dashboardSearch !== '' ? '%' . $dashboardSearch . '%' : null;
$refundDisputeWhere = '';
$refundDisputeParams = [];
$refundDisputeCountSql = '';
$refundDisputeRowsSql = '';

if ($view === 'refund-requests') {
    if ($refundDisputeSearchLike !== null) {
        $refundDisputeWhere = ' WHERE rf.status LIKE ? OR rf.reason LIKE ? OR o.order_number LIKE ? OR u.email LIKE ? OR u.display_name LIKE ? OR p.provider_reference LIKE ?';
        $refundDisputeParams = array_fill(0, 6, $refundDisputeSearchLike);
    }
    $refundDisputeCountSql = "SELECT COUNT(DISTINCT rf.id) AS total FROM refunds rf LEFT JOIN orders o ON o.id = rf.order_id LEFT JOIN users u ON u.id = rf.requested_by LEFT JOIN payments p ON p.id = rf.payment_id {$refundDisputeWhere}";
    $refundDisputeRowsSql = "SELECT rf.id, rf.order_id, rf.payment_id, rf.amount, rf.currency, rf.reason, rf.status,
                rf.requested_by, rf.processed_by, rf.processed_at, rf.created_at,
                o.order_number, o.status AS order_status, o.grand_total, p.provider, p.provider_reference,
                requester.display_name AS requester_name, requester.email AS requester_email,
                processor.display_name AS processor_name
         FROM refunds rf
         LEFT JOIN orders o ON o.id = rf.order_id
         LEFT JOIN payments p ON p.id = rf.payment_id
         LEFT JOIN users requester ON requester.id = rf.requested_by
         LEFT JOIN users processor ON processor.id = rf.processed_by
         LEFT JOIN users u ON u.id = rf.requested_by
         {$refundDisputeWhere}
         ORDER BY rf.created_at DESC, rf.id DESC";
} elseif ($view === 'return-requests') {
    if ($refundDisputeSearchLike !== null) {
        $refundDisputeWhere = ' WHERE rr.status LIKE ? OR rr.type LIKE ? OR rr.reason LIKE ? OR rr.details LIKE ? OR o.order_number LIKE ? OR u.email LIKE ? OR v.store_name LIKE ?';
        $refundDisputeParams = array_fill(0, 7, $refundDisputeSearchLike);
    }
    $refundDisputeCountSql = "SELECT COUNT(DISTINCT rr.id) AS total FROM return_requests rr LEFT JOIN orders o ON o.id = rr.order_id LEFT JOIN users u ON u.id = rr.user_id LEFT JOIN vendors v ON v.id = rr.vendor_id {$refundDisputeWhere}";
    $refundDisputeRowsSql = "SELECT rr.id, rr.order_id, rr.order_item_id, rr.user_id, rr.vendor_id, rr.type, rr.reason,
                rr.details, rr.status, rr.created_at, rr.updated_at, o.order_number, oi.name AS item_name,
                u.display_name, u.email, v.store_name
         FROM return_requests rr
         LEFT JOIN orders o ON o.id = rr.order_id
         LEFT JOIN order_items oi ON oi.id = rr.order_item_id
         LEFT JOIN users u ON u.id = rr.user_id
         LEFT JOIN vendors v ON v.id = rr.vendor_id
         {$refundDisputeWhere}
         ORDER BY rr.created_at DESC, rr.id DESC";
} elseif ($view === 'vendor-disputes') {
    if ($refundDisputeSearchLike !== null) {
        $refundDisputeWhere = ' WHERE vd.title LIKE ? OR vd.description LIKE ? OR vd.status LIKE ? OR vd.priority LIKE ? OR v.store_name LIKE ? OR o.order_number LIKE ?';
        $refundDisputeParams = array_fill(0, 6, $refundDisputeSearchLike);
    }
    $refundDisputeCountSql = "SELECT COUNT(DISTINCT vd.id) AS total FROM vendor_disputes vd LEFT JOIN vendors v ON v.id = vd.vendor_id LEFT JOIN orders o ON o.id = vd.order_id {$refundDisputeWhere}";
    $refundDisputeRowsSql = "SELECT vd.id, vd.vendor_id, vd.order_id, vd.opened_by, vd.title, vd.description, vd.status,
                vd.priority, vd.admin_note, vd.assigned_to, vd.resolved_at, vd.created_at, vd.updated_at,
                v.store_name, o.order_number, opener.display_name AS opener_name, opener.email AS opener_email,
                assignee.display_name AS assignee_name
         FROM vendor_disputes vd
         LEFT JOIN vendors v ON v.id = vd.vendor_id
         LEFT JOIN orders o ON o.id = vd.order_id
         LEFT JOIN users opener ON opener.id = vd.opened_by
         LEFT JOIN users assignee ON assignee.id = vd.assigned_to
         {$refundDisputeWhere}
         ORDER BY FIELD(vd.priority, 'urgent', 'high', 'normal', 'low'), vd.created_at DESC, vd.id DESC";
} elseif ($view === 'buyer-complaints') {
    if ($refundDisputeSearchLike !== null) {
        $refundDisputeWhere = ' WHERE bc.subject LIKE ? OR bc.message LIKE ? OR bc.status LIKE ? OR bc.priority LIKE ? OR u.email LIKE ? OR u.display_name LIKE ? OR v.store_name LIKE ? OR o.order_number LIKE ?';
        $refundDisputeParams = array_fill(0, 8, $refundDisputeSearchLike);
    }
    $refundDisputeCountSql = "SELECT COUNT(DISTINCT bc.id) AS total FROM buyer_complaints bc LEFT JOIN users u ON u.id = bc.user_id LEFT JOIN vendors v ON v.id = bc.vendor_id LEFT JOIN orders o ON o.id = bc.order_id {$refundDisputeWhere}";
    $refundDisputeRowsSql = "SELECT bc.id, bc.user_id, bc.order_id, bc.vendor_id, bc.subject, bc.message, bc.status,
                bc.priority, bc.admin_note, bc.assigned_to, bc.resolved_at, bc.created_at, bc.updated_at,
                u.display_name, u.email, v.store_name, o.order_number, assignee.display_name AS assignee_name
         FROM buyer_complaints bc
         LEFT JOIN users u ON u.id = bc.user_id
         LEFT JOIN vendors v ON v.id = bc.vendor_id
         LEFT JOIN orders o ON o.id = bc.order_id
         LEFT JOIN users assignee ON assignee.id = bc.assigned_to
         {$refundDisputeWhere}
         ORDER BY FIELD(bc.priority, 'urgent', 'high', 'normal', 'low'), bc.created_at DESC, bc.id DESC";
}

$refundDisputeTotal = 0;
$refundDisputeRows = [];
if ($refundDisputeCountSql !== '') {
    $row = db()->fetch($refundDisputeCountSql, $refundDisputeParams);
    $refundDisputeTotal = (int)($row['total'] ?? 0);
    $refundDisputeTotalPages = max(1, (int)ceil($refundDisputeTotal / $dashboardPerPage));
    $refundDisputePage = max(1, min((int)($_GET['page'] ?? 1), $refundDisputeTotalPages));
    $refundDisputeOffset = ($refundDisputePage - 1) * $dashboardPerPage;
    $refundDisputeRows = dashboard_table_rows($refundDisputeRowsSql . " LIMIT {$dashboardPerPage} OFFSET {$refundDisputeOffset}", $refundDisputeParams);
} else {
    $refundDisputeTotalPages = 1;
    $refundDisputePage = 1;
    $refundDisputeOffset = 0;
}

$refundDisputeEdit = null;
$refundDisputeEditId = max(0, (int)($_GET['edit'] ?? 0));
if ($refundDisputeEditId > 0) {
    if ($view === 'vendor-disputes') {
        $refundDisputeEdit = db()->fetch('SELECT * FROM vendor_disputes WHERE id = ? LIMIT 1', [$refundDisputeEditId]);
    } elseif ($view === 'buyer-complaints') {
        $refundDisputeEdit = db()->fetch('SELECT * FROM buyer_complaints WHERE id = ? LIMIT 1', [$refundDisputeEditId]);
    }
}

$refundStatusRows = dashboard_table_rows("SELECT status, COUNT(*) AS total, COALESCE(SUM(amount), 0) AS amount FROM refunds GROUP BY status", [], 'refunds');
$refundStatusTotals = [];
$refundStatusAmounts = [];
foreach ($refundStatusRows as $row) {
    $refundStatusTotals[(string)$row['status']] = (int)$row['total'];
    $refundStatusAmounts[(string)$row['status']] = (float)$row['amount'];
}
$returnStatusRows = dashboard_table_rows("SELECT status, COUNT(*) AS total FROM return_requests GROUP BY status", [], 'return_requests');
$returnStatusTotals = [];
foreach ($returnStatusRows as $row) {
    $returnStatusTotals[(string)$row['status']] = (int)$row['total'];
}
$vendorDisputeStatusRows = dashboard_table_rows("SELECT status, COUNT(*) AS total FROM vendor_disputes GROUP BY status", [], 'vendor_disputes');
$vendorDisputeStatusTotals = [];
foreach ($vendorDisputeStatusRows as $row) {
    $vendorDisputeStatusTotals[(string)$row['status']] = (int)$row['total'];
}
$buyerComplaintStatusRows = dashboard_table_rows("SELECT status, COUNT(*) AS total FROM buyer_complaints GROUP BY status", [], 'buyer_complaints');
$buyerComplaintStatusTotals = [];
foreach ($buyerComplaintStatusRows as $row) {
    $buyerComplaintStatusTotals[(string)$row['status']] = (int)$row['total'];
}

$refundDisputesData = [
    'views' => $refundDisputeViews,
    'rows' => $refundDisputeRows,
    'edit' => $refundDisputeEdit,
    'showForm' => in_array($view, ['vendor-disputes', 'buyer-complaints'], true) && (isset($_GET['new']) || $refundDisputeEdit !== null),
    'orders' => dashboard_table_rows('SELECT id, order_number, grand_total, currency FROM orders ORDER BY created_at DESC LIMIT 250', [], 'orders'),
    'vendors' => dashboard_table_rows('SELECT id, store_name FROM vendors ORDER BY store_name ASC LIMIT 1000', [], 'vendors'),
    'users' => dashboard_table_rows('SELECT id, display_name, email FROM users WHERE status <> "deleted" ORDER BY created_at DESC LIMIT 500', [], 'users'),
    'refundStatusTotals' => $refundStatusTotals,
    'refundStatusAmounts' => $refundStatusAmounts,
    'returnStatusTotals' => $returnStatusTotals,
    'vendorDisputeStatusTotals' => $vendorDisputeStatusTotals,
    'buyerComplaintStatusTotals' => $buyerComplaintStatusTotals,
    'pagination' => [
        'page' => $refundDisputePage,
        'perPage' => $dashboardPerPage,
        'perPageOptions' => $dashboardPerPageOptions,
        'total' => $refundDisputeTotal,
        'totalPages' => $refundDisputeTotalPages,
        'from' => $refundDisputeTotal === 0 ? 0 : $refundDisputeOffset + 1,
        'to' => min($refundDisputeOffset + count($refundDisputeRows), $refundDisputeTotal),
        'search' => $dashboardSearch,
    ],
    'stats' => [
        'refunds' => dashboard_count('refunds'),
        'refund_requested' => (int)($refundStatusTotals['requested'] ?? 0),
        'refund_amount_requested' => (float)($refundStatusAmounts['requested'] ?? 0),
        'returns' => dashboard_count('return_requests'),
        'return_requested' => (int)($returnStatusTotals['requested'] ?? 0),
        'vendor_disputes' => dashboard_count('vendor_disputes'),
        'open_vendor_disputes' => (int)($vendorDisputeStatusTotals['open'] ?? 0) + (int)($vendorDisputeStatusTotals['under_review'] ?? 0),
        'buyer_complaints' => dashboard_count('buyer_complaints'),
        'open_buyer_complaints' => (int)($buyerComplaintStatusTotals['open'] ?? 0) + (int)($buyerComplaintStatusTotals['under_review'] ?? 0),
    ],
];

$taxonomyListConfig = [
    'categories' => [
        'dataKey' => 'categories',
        'table' => 'categories',
        'where' => 'c.parent_id IS NULL',
        'search' => "(c.name LIKE ? OR c.slug LIKE ? OR c.description LIKE ?)",
        'order' => 'ORDER BY c.sort_order ASC, c.name ASC',
    ],
    'subcategories' => [
        'dataKey' => 'subcategories',
        'table' => 'categories',
        'where' => 'c.parent_id IS NOT NULL',
        'search' => "(c.name LIKE ? OR c.slug LIKE ? OR c.description LIKE ? OR parent.name LIKE ?)",
        'order' => 'ORDER BY parent.name ASC, c.sort_order ASC, c.name ASC',
    ],
    'brands' => [
        'dataKey' => 'brands',
        'table' => 'brands',
        'where' => '',
        'search' => "(b.name LIKE ? OR b.slug LIKE ? OR b.description LIKE ?)",
        'order' => 'ORDER BY b.name ASC',
    ],
    'attributes' => [
        'dataKey' => 'attributes',
        'table' => 'product_attributes',
        'where' => '',
        'search' => "(pa.name LIKE ? OR pa.slug LIKE ? OR pa.type LIKE ?)",
        'order' => 'ORDER BY pa.is_global DESC, pa.name ASC',
    ],
    'variations' => [
        'dataKey' => 'variations',
        'table' => 'products',
        'where' => "p.type = 'variation' OR p.parent_product_id IS NOT NULL",
        'search' => "(p.name LIKE ? OR p.sku LIKE ? OR p.slug LIKE ? OR parent.name LIKE ? OR v.store_name LIKE ?)",
        'order' => 'ORDER BY p.updated_at DESC',
    ],
];
$currentTaxonomyConfig = $taxonomyListConfig[$view] ?? $taxonomyListConfig['categories'];
$taxonomySearchParams = [];
$taxonomySearchSql = '';
if ($dashboardSearch !== '') {
    $like = '%' . $dashboardSearch . '%';
    $placeholderCount = substr_count($currentTaxonomyConfig['search'], '?');
    $taxonomySearchParams = array_fill(0, $placeholderCount, $like);
    $taxonomySearchSql = $currentTaxonomyConfig['search'];
}
$taxonomyWhereParts = array_filter([$currentTaxonomyConfig['where'], $taxonomySearchSql], static fn ($part): bool => (string)$part !== '');
$taxonomyWhereSql = $taxonomyWhereParts !== [] ? ' WHERE ' . implode(' AND ', array_map(static fn ($part): string => '(' . $part . ')', $taxonomyWhereParts)) : '';
$taxonomyTotal = 0;
$taxonomyRows = [];

if ($currentTaxonomyConfig['dataKey'] === 'categories' || $currentTaxonomyConfig['dataKey'] === 'subcategories') {
    $countRow = db()->fetch("SELECT COUNT(DISTINCT c.id) AS total FROM categories c LEFT JOIN categories parent ON parent.id = c.parent_id {$taxonomyWhereSql}", $taxonomySearchParams);
    $taxonomyTotal = (int)($countRow['total'] ?? 0);
} elseif ($currentTaxonomyConfig['dataKey'] === 'brands') {
    $countRow = db()->fetch("SELECT COUNT(DISTINCT b.id) AS total FROM brands b {$taxonomyWhereSql}", $taxonomySearchParams);
    $taxonomyTotal = (int)($countRow['total'] ?? 0);
} elseif ($currentTaxonomyConfig['dataKey'] === 'attributes') {
    $countRow = db()->fetch("SELECT COUNT(DISTINCT pa.id) AS total FROM product_attributes pa {$taxonomyWhereSql}", $taxonomySearchParams);
    $taxonomyTotal = (int)($countRow['total'] ?? 0);
} elseif ($currentTaxonomyConfig['dataKey'] === 'variations') {
    $countRow = db()->fetch("SELECT COUNT(DISTINCT p.id) AS total FROM products p LEFT JOIN products parent ON parent.id = p.parent_product_id LEFT JOIN vendors v ON v.id = p.vendor_id {$taxonomyWhereSql}", $taxonomySearchParams);
    $taxonomyTotal = (int)($countRow['total'] ?? 0);
}

$taxonomyTotalPages = max(1, (int)ceil($taxonomyTotal / $dashboardPerPage));
$taxonomyPage = max(1, min((int)($_GET['page'] ?? 1), $taxonomyTotalPages));
$taxonomyOffset = ($taxonomyPage - 1) * $dashboardPerPage;

if ($currentTaxonomyConfig['dataKey'] === 'categories' || $currentTaxonomyConfig['dataKey'] === 'subcategories') {
    $taxonomyRows = dashboard_table_rows(
        "SELECT c.id, c.wp_term_id, c.parent_id, c.name, c.slug, c.description, c.sort_order, c.is_active, c.created_at, c.updated_at,
                parent.name AS parent_name, f.path AS image_path,
                COALESCE(product_stats.product_count, 0) AS product_count,
                COALESCE(child_stats.child_count, 0) AS child_count
         FROM categories c
         LEFT JOIN categories parent ON parent.id = c.parent_id
         LEFT JOIN files f ON f.id = c.image_file_id
         LEFT JOIN (
            SELECT category_id, COUNT(*) AS product_count
            FROM product_categories
            GROUP BY category_id
         ) product_stats ON product_stats.category_id = c.id
         LEFT JOIN (
            SELECT parent_id, COUNT(*) AS child_count
            FROM categories
            WHERE parent_id IS NOT NULL
            GROUP BY parent_id
         ) child_stats ON child_stats.parent_id = c.id
         {$taxonomyWhereSql}
         {$currentTaxonomyConfig['order']}
         LIMIT {$dashboardPerPage} OFFSET {$taxonomyOffset}",
        $taxonomySearchParams,
        'categories'
    );
} elseif ($currentTaxonomyConfig['dataKey'] === 'brands') {
    $taxonomyRows = dashboard_table_rows(
        "SELECT b.id, b.wp_term_id, b.name, b.slug, b.description, b.created_at, b.updated_at, f.path AS logo_path,
                COALESCE(product_stats.product_count, 0) AS product_count,
                COALESCE(product_stats.active_product_count, 0) AS active_product_count
         FROM brands b
         LEFT JOIN files f ON f.id = b.logo_file_id
         LEFT JOIN (
            SELECT brand_id, COUNT(*) AS product_count, SUM(status = 'active') AS active_product_count
            FROM products
            WHERE brand_id IS NOT NULL
            GROUP BY brand_id
         ) product_stats ON product_stats.brand_id = b.id
         {$taxonomyWhereSql}
         {$currentTaxonomyConfig['order']}
         LIMIT {$dashboardPerPage} OFFSET {$taxonomyOffset}",
        $taxonomySearchParams,
        'brands'
    );
} elseif ($currentTaxonomyConfig['dataKey'] === 'attributes') {
    $taxonomyRows = dashboard_table_rows(
        "SELECT pa.id, pa.wp_attribute_id, pa.name, pa.slug, pa.type, pa.is_global, pa.created_at,
                COUNT(pav.id) AS value_count,
                GROUP_CONCAT(pav.value ORDER BY pav.sort_order ASC, pav.value ASC SEPARATOR ', ') AS sample_values
         FROM product_attributes pa
         LEFT JOIN product_attribute_values pav ON pav.attribute_id = pa.id
         {$taxonomyWhereSql}
         GROUP BY pa.id, pa.wp_attribute_id, pa.name, pa.slug, pa.type, pa.is_global, pa.created_at
         {$currentTaxonomyConfig['order']}
         LIMIT {$dashboardPerPage} OFFSET {$taxonomyOffset}",
        $taxonomySearchParams,
        'product_attributes'
    );
} elseif ($currentTaxonomyConfig['dataKey'] === 'variations') {
    $taxonomyRows = dashboard_table_rows(
        "SELECT p.id, p.wp_post_id, p.parent_product_id, p.vendor_id, p.brand_id, p.sku, p.name, p.slug, p.status, p.stock_status,
                p.stock_quantity, p.regular_price, p.sale_price, p.currency, p.updated_at,
                parent.name AS parent_name, v.store_name, b.name AS brand_name, f.path AS image_path
         FROM products p
         LEFT JOIN products parent ON parent.id = p.parent_product_id
         LEFT JOIN vendors v ON v.id = p.vendor_id
         LEFT JOIN brands b ON b.id = p.brand_id
         LEFT JOIN (
            SELECT product_id, MIN(file_id) AS file_id
            FROM product_media
            WHERE role = 'primary'
            GROUP BY product_id
         ) primary_media ON primary_media.product_id = p.id
         LEFT JOIN files f ON f.id = primary_media.file_id
         {$taxonomyWhereSql}
         {$currentTaxonomyConfig['order']}
         LIMIT {$dashboardPerPage} OFFSET {$taxonomyOffset}",
        $taxonomySearchParams,
        'products'
    );
}

$categoryStatusRows = dashboard_table_rows("SELECT is_active, COUNT(*) AS total FROM categories GROUP BY is_active", [], 'categories');
$categoryActiveTotals = [];
foreach ($categoryStatusRows as $row) {
    $categoryActiveTotals[(int)$row['is_active']] = (int)$row['total'];
}
$taxonomyEdit = null;
$taxonomyAttributeValues = [];
$taxonomyEditId = max(0, (int)($_GET['edit'] ?? 0));
if ($taxonomyEditId > 0) {
    if (in_array($view, ['categories', 'subcategories'], true)) {
        $taxonomyEdit = db()->fetch('SELECT * FROM categories WHERE id = ? LIMIT 1', [$taxonomyEditId]);
    } elseif ($view === 'brands') {
        $taxonomyEdit = db()->fetch('SELECT * FROM brands WHERE id = ? LIMIT 1', [$taxonomyEditId]);
    } elseif ($view === 'attributes') {
        $taxonomyEdit = db()->fetch('SELECT * FROM product_attributes WHERE id = ? LIMIT 1', [$taxonomyEditId]);
        $taxonomyAttributeValues = dashboard_table_rows(
            'SELECT id, value, slug, sort_order FROM product_attribute_values WHERE attribute_id = ? ORDER BY sort_order ASC, value ASC',
            [$taxonomyEditId],
            'product_attribute_values'
        );
    }
}
$taxonomyParentCategories = dashboard_table_rows(
    'SELECT id, name, slug FROM categories WHERE parent_id IS NULL ORDER BY sort_order ASC, name ASC',
    [],
    'categories'
);
$taxonomyData = [
    'views' => $taxonomyViews,
    'activeKey' => $currentTaxonomyConfig['dataKey'],
    'rows' => $taxonomyRows,
    'edit' => $taxonomyEdit,
    'editValues' => $taxonomyAttributeValues,
    'showForm' => isset($_GET['new']) || $taxonomyEdit !== null,
    'parentCategories' => $taxonomyParentCategories,
    'pagination' => [
        'page' => $taxonomyPage,
        'perPage' => $dashboardPerPage,
        'perPageOptions' => $dashboardPerPageOptions,
        'total' => $taxonomyTotal,
        'totalPages' => $taxonomyTotalPages,
        'from' => $taxonomyTotal === 0 ? 0 : $taxonomyOffset + 1,
        'to' => min($taxonomyOffset + count($taxonomyRows), $taxonomyTotal),
        'search' => $dashboardSearch,
    ],
    'stats' => [
        'total_categories' => dashboard_count('categories'),
        'active_categories' => (int)($categoryActiveTotals[1] ?? 0),
        'subcategories' => dashboard_count('categories', 'parent_id IS NOT NULL'),
        'brands' => dashboard_count('brands'),
        'attributes' => dashboard_count('product_attributes'),
        'attribute_values' => dashboard_count('product_attribute_values'),
        'variations' => dashboard_count('products', "type = 'variation' OR parent_product_id IS NOT NULL"),
    ],
];

$userSearchSql = '';
$userSearchParams = [];
if ($dashboardSearch !== '') {
    $userSearchSql = "(u.email LIKE ? OR u.username LIKE ? OR u.display_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone LIKE ?)";
    $like = '%' . $dashboardSearch . '%';
    $userSearchParams = [$like, $like, $like, $like, $like, $like];
}
$userBaseFilters = [
    'users' => '',
    'buyers' => "(r.code = 'customer' OR os.order_count > 0)",
    'blocked-users' => "u.status IN ('suspended', 'deleted')",
];
$currentUserFilter = $userBaseFilters[$view] ?? '';
$currentUserWhereParts = array_filter([$currentUserFilter, $userSearchSql], static fn ($part): bool => (string)$part !== '');
$currentUserWhereSql = $currentUserWhereParts !== [] ? ' WHERE ' . implode(' AND ', array_map(static fn ($part): string => '(' . $part . ')', $currentUserWhereParts)) : '';
$currentUserTotal = 0;
if (in_array($view, ['users', 'buyers', 'blocked-users'], true)) {
    $row = db()->fetch(
        "SELECT COUNT(DISTINCT u.id) AS total
         FROM users u
         LEFT JOIN user_roles ur ON ur.user_id = u.id
         LEFT JOIN roles r ON r.id = ur.role_id
         LEFT JOIN (
            SELECT customer_id, COUNT(*) AS order_count
            FROM orders
            WHERE customer_id IS NOT NULL
            GROUP BY customer_id
         ) os ON os.customer_id = u.id
         {$currentUserWhereSql}",
        $userSearchParams
    );
    $currentUserTotal = (int)($row['total'] ?? 0);
}
$userTotalPages = max(1, (int)ceil($currentUserTotal / $dashboardPerPage));
$userPage = max(1, min((int)($_GET['page'] ?? 1), $userTotalPages));
$userOffset = ($userPage - 1) * $dashboardPerPage;

$userRows = dashboard_table_rows(
    "SELECT u.id, u.wp_user_id, u.email, u.username, u.first_name, u.last_name, u.display_name, u.phone, u.avatar_url,
            u.status, u.email_verified_at, u.last_login_at, u.created_at, u.updated_at,
            GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_names,
            GROUP_CONCAT(DISTINCT r.code ORDER BY r.code SEPARATOR ',') AS role_codes,
            COALESCE(os.order_count, 0) AS order_count,
            COALESCE(os.lifetime_value, 0) AS lifetime_value,
            os.last_order_at
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN roles r ON r.id = ur.role_id
     LEFT JOIN (
        SELECT customer_id, COUNT(*) AS order_count, SUM(grand_total) AS lifetime_value, MAX(created_at) AS last_order_at
        FROM orders
        WHERE customer_id IS NOT NULL
        GROUP BY customer_id
     ) os ON os.customer_id = u.id
     " . ($userSearchSql !== '' ? 'WHERE ' . $userSearchSql : '') . "
     GROUP BY u.id, u.wp_user_id, u.email, u.username, u.first_name, u.last_name, u.display_name, u.phone, u.avatar_url,
              u.status, u.email_verified_at, u.last_login_at, u.created_at, u.updated_at, os.order_count, os.lifetime_value, os.last_order_at
     ORDER BY u.created_at DESC
     LIMIT {$dashboardPerPage} OFFSET {$userOffset}",
    $userSearchParams,
    'users'
);

$buyerRows = dashboard_table_rows(
    "SELECT u.id, u.wp_user_id, u.email, u.username, u.first_name, u.last_name, u.display_name, u.phone, u.avatar_url,
            u.status, u.email_verified_at, u.last_login_at, u.created_at, u.updated_at,
            GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_names,
            GROUP_CONCAT(DISTINCT r.code ORDER BY r.code SEPARATOR ',') AS role_codes,
            COALESCE(os.order_count, 0) AS order_count,
            COALESCE(os.lifetime_value, 0) AS lifetime_value,
            os.last_order_at
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN roles r ON r.id = ur.role_id
     LEFT JOIN (
        SELECT customer_id, COUNT(*) AS order_count, SUM(grand_total) AS lifetime_value, MAX(created_at) AS last_order_at
        FROM orders
        WHERE customer_id IS NOT NULL
        GROUP BY customer_id
     ) os ON os.customer_id = u.id
     WHERE (r.code = 'customer' OR os.order_count > 0)" . ($userSearchSql !== '' ? ' AND ' . $userSearchSql : '') . "
     GROUP BY u.id, u.wp_user_id, u.email, u.username, u.first_name, u.last_name, u.display_name, u.phone, u.avatar_url,
              u.status, u.email_verified_at, u.last_login_at, u.created_at, u.updated_at, os.order_count, os.lifetime_value, os.last_order_at
     ORDER BY last_order_at DESC, u.created_at DESC
     LIMIT {$dashboardPerPage} OFFSET {$userOffset}",
    $userSearchParams,
    'users'
);

$blockedUserRows = dashboard_table_rows(
    "SELECT u.id, u.wp_user_id, u.email, u.username, u.first_name, u.last_name, u.display_name, u.phone, u.avatar_url,
            u.status, u.email_verified_at, u.last_login_at, u.created_at, u.updated_at,
            GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_names,
            GROUP_CONCAT(DISTINCT r.code ORDER BY r.code SEPARATOR ',') AS role_codes,
            COALESCE(os.order_count, 0) AS order_count,
            COALESCE(os.lifetime_value, 0) AS lifetime_value,
            os.last_order_at
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN roles r ON r.id = ur.role_id
     LEFT JOIN (
        SELECT customer_id, COUNT(*) AS order_count, SUM(grand_total) AS lifetime_value, MAX(created_at) AS last_order_at
        FROM orders
        WHERE customer_id IS NOT NULL
        GROUP BY customer_id
     ) os ON os.customer_id = u.id
     WHERE u.status IN ('suspended', 'deleted')" . ($userSearchSql !== '' ? ' AND ' . $userSearchSql : '') . "
     GROUP BY u.id, u.wp_user_id, u.email, u.username, u.first_name, u.last_name, u.display_name, u.phone, u.avatar_url,
              u.status, u.email_verified_at, u.last_login_at, u.created_at, u.updated_at, os.order_count, os.lifetime_value, os.last_order_at
     ORDER BY u.updated_at DESC
     LIMIT {$dashboardPerPage} OFFSET {$userOffset}",
    $userSearchParams,
    'users'
);

$guestBuyerRows = dashboard_table_rows(
    "SELECT guest_email, COUNT(*) AS order_count, COALESCE(SUM(grand_total), 0) AS lifetime_value,
            MIN(created_at) AS first_order_at, MAX(created_at) AS last_order_at,
            MAX(status) AS latest_status, MAX(payment_status) AS latest_payment_status
     FROM orders
     WHERE customer_id IS NULL AND guest_email IS NOT NULL AND guest_email <> ''
     GROUP BY guest_email
     ORDER BY last_order_at DESC
     LIMIT 120",
    [],
    'orders'
);

$userActivityRows = dashboard_table_rows(
    "SELECT al.id, al.action, al.entity_type, al.entity_id, al.new_values, al.ip_address, al.user_agent, al.created_at,
            u.id AS user_id, u.display_name, u.email, u.status
     FROM audit_logs al
     LEFT JOIN users u ON u.id = al.actor_user_id
     WHERE al.actor_user_id IS NOT NULL OR al.entity_type = 'users'
     ORDER BY al.created_at DESC
     LIMIT 120",
    [],
    'audit_logs'
);

$roleSummaryRows = dashboard_table_rows(
    "SELECT r.code, r.name, COUNT(ur.user_id) AS total
     FROM roles r
     LEFT JOIN user_roles ur ON ur.role_id = r.id
     GROUP BY r.code, r.name
     ORDER BY total DESC, r.name ASC",
    [],
    'roles'
);
$allRoleRows = dashboard_table_rows('SELECT id, code, name FROM roles ORDER BY name ASC', [], 'roles');

$userStatusRows = dashboard_table_rows(
    "SELECT status, COUNT(*) AS total FROM users GROUP BY status",
    [],
    'users'
);
$userStatusTotals = [];
foreach ($userStatusRows as $row) {
    $userStatusTotals[(string)$row['status']] = (int)$row['total'];
}
$registeredBuyerTotalRow = db()->fetch(
    "SELECT COUNT(DISTINCT u.id) AS total
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN roles r ON r.id = ur.role_id
     LEFT JOIN (
        SELECT customer_id, COUNT(*) AS order_count
        FROM orders
        WHERE customer_id IS NOT NULL
        GROUP BY customer_id
     ) os ON os.customer_id = u.id
     WHERE r.code = 'customer' OR os.order_count > 0"
);
$guestBuyerTotalRow = db()->fetch("SELECT COUNT(DISTINCT guest_email) AS total FROM orders WHERE customer_id IS NULL AND guest_email IS NOT NULL AND guest_email <> ''");
$currentUserRows = match ($view) {
    'buyers' => $buyerRows,
    'blocked-users' => $blockedUserRows,
    default => $userRows,
};

$selectedUserId = max(0, (int)($_GET['user'] ?? 0));
$selectedUser = null;
$selectedUserOrders = [];
$selectedUserVendors = [];
$selectedUserRoleIds = [];
if ($selectedUserId > 0) {
    $selectedUser = db()->fetch(
        "SELECT u.id, u.wp_user_id, u.email, u.username, u.first_name, u.last_name, u.display_name, u.phone, u.avatar_url,
                u.status, u.email_verified_at, u.last_login_at, u.created_at, u.updated_at,
                GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_names
         FROM users u
         LEFT JOIN user_roles ur ON ur.user_id = u.id
         LEFT JOIN roles r ON r.id = ur.role_id
         WHERE u.id = ?
         GROUP BY u.id, u.wp_user_id, u.email, u.username, u.first_name, u.last_name, u.display_name, u.phone, u.avatar_url,
                  u.status, u.email_verified_at, u.last_login_at, u.created_at, u.updated_at
         LIMIT 1",
        [$selectedUserId]
    );
    if ($selectedUser) {
        $selectedUserRoleIds = array_map(
            static fn (array $row): int => (int)$row['role_id'],
            dashboard_table_rows('SELECT role_id FROM user_roles WHERE user_id = ?', [$selectedUserId], 'user_roles')
        );
        $selectedUserOrders = dashboard_table_rows(
            'SELECT id, order_number, status, payment_status, fulfillment_status, currency, grand_total, created_at
             FROM orders
             WHERE customer_id = ?
             ORDER BY created_at DESC
             LIMIT 10',
            [$selectedUserId],
            'orders'
        );
        $selectedUserVendors = dashboard_table_rows(
            'SELECT id, store_name, store_slug, status, kyc_status, created_at
             FROM vendors
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT 10',
            [$selectedUserId],
            'vendors'
        );
    }
}

$selectedUserManagement = $selectedUser
    ? \App\ManageStoreService::activeServiceForEmail((string)$selectedUser['email'])
    : null;
$selectedUserSetup = $selectedUser && \App\AuthService::hasRole('super_admin')
    ? \App\ManageStoreService::activeServiceForEmail((string)$selectedUser['email'], 'setup')
    : null;

$usersData = [
    'selectedUserManagement' => $selectedUserManagement,
    'selectedUserSetup' => $selectedUserSetup,
    'views' => $userViews,
    'users' => $userRows,
    'buyers' => $buyerRows,
    'blockedUsers' => $blockedUserRows,
    'guestBuyers' => $guestBuyerRows,
    'activity' => $userActivityRows,
    'roles' => $roleSummaryRows,
    'allRoles' => $allRoleRows,
    'selectedUser' => $selectedUser,
    'selectedUserRoleIds' => $selectedUserRoleIds,
    'selectedUserOrders' => $selectedUserOrders,
    'selectedUserVendors' => $selectedUserVendors,
    'statusTotals' => $userStatusTotals,
    'pagination' => [
        'page' => $userPage,
        'perPage' => $dashboardPerPage,
        'perPageOptions' => $dashboardPerPageOptions,
        'total' => $currentUserTotal,
        'totalPages' => $userTotalPages,
        'from' => $currentUserTotal === 0 ? 0 : $userOffset + 1,
        'to' => min($userOffset + count($currentUserRows), $currentUserTotal),
        'search' => $dashboardSearch,
    ],
    'stats' => [
        'total_users' => dashboard_count('users'),
        'active_users' => (int)($userStatusTotals['active'] ?? 0),
        'pending_users' => (int)($userStatusTotals['pending'] ?? 0),
        'blocked_users' => (int)($userStatusTotals['suspended'] ?? 0) + (int)($userStatusTotals['deleted'] ?? 0),
        'registered_buyers' => (int)($registeredBuyerTotalRow['total'] ?? 0),
        'guest_buyers' => (int)($guestBuyerTotalRow['total'] ?? 0),
        'activity_events' => count($userActivityRows),
    ],
];

$vendorBaseSql = "SELECT v.id, v.wp_user_id, v.user_id, v.store_name, v.store_slug, v.store_email, v.store_phone, v.description, v.origin_region, v.country_of_origin,
            v.logo_file_id, v.banner_file_id, v.status, v.kyc_status, v.commission_type, v.commission_rate, v.payout_method, v.payout_details, v.created_at, v.updated_at,
            u.display_name, u.email AS owner_email, u.phone AS owner_phone,
            COALESCE(ps.product_count, 0) AS product_count,
            COALESCE(ps.active_product_count, 0) AS active_product_count,
            COALESCE(ps.pending_product_count, 0) AS pending_product_count,
            COALESCE(ps.rejected_product_count, 0) AS rejected_product_count,
            COALESCE(ps.imaged_product_count, 0) AS imaged_product_count,
            COALESCE(ps.ready_product_count, 0) AS ready_product_count,
            COALESCE(ps.product_summary, '') AS product_summary,
            COALESCE(ds.kyc_document_count, 0) AS kyc_document_count,
            COALESCE(ds.uploaded_document_count, 0) AS uploaded_document_count,
            COALESCE(ds.approved_document_count, 0) AS approved_document_count,
            COALESCE(ds.document_summary, '') AS document_summary,
            va.submitted_at AS application_submitted_at,
            va.application_data AS latest_application_data,
            COALESCE(os.order_count, 0) AS order_count,
            COALESCE(os.gross_total, 0) AS gross_total,
            COALESCE(os.vendor_earning, 0) AS vendor_earning,
            COALESCE(rs.review_count, 0) AS review_count,
            COALESCE(rs.avg_rating, 0) AS avg_rating,
            COALESCE(ws.pending_withdrawals, 0) AS pending_withdrawals,
            COALESCE(ws.total_withdrawn, 0) AS total_withdrawn
     FROM vendors v
     INNER JOIN users u ON u.id = v.user_id
     LEFT JOIN (
        SELECT p.vendor_id,
               COUNT(*) AS product_count,
               SUM(p.status = 'active') AS active_product_count,
               COUNT(DISTINCT CASE WHEN p.status = 'pending' THEN p.id END) AS pending_product_count,
               COUNT(DISTINCT CASE WHEN p.status = 'rejected' THEN p.id END) AS rejected_product_count,
               COUNT(DISTINCT CASE WHEN pm.id IS NOT NULL THEN p.id END) AS imaged_product_count,
               COUNT(DISTINCT CASE
                    WHEN p.regular_price > 0
                     AND COALESCE(p.weight, 0) > 0
                     AND COALESCE(p.length, 0) > 0
                     AND COALESCE(p.width, 0) > 0
                     AND COALESCE(p.height, 0) > 0
                     AND TRIM(COALESCE(p.description, '')) <> ''
                     AND pm.id IS NOT NULL
                    THEN p.id END) AS ready_product_count
               , GROUP_CONCAT(DISTINCT CONCAT_WS(' :: ', p.name, NULLIF(p.sku, ''), p.status) ORDER BY p.created_at DESC SEPARATOR '||') AS product_summary
        FROM products p
        LEFT JOIN product_media pm ON pm.product_id = p.id AND pm.role IN ('primary', 'gallery')
        GROUP BY p.vendor_id
     ) ps ON ps.vendor_id = v.id
     LEFT JOIN (
        SELECT d.vendor_id,
               COUNT(*) AS kyc_document_count,
               SUM(d.file_id IS NOT NULL) AS uploaded_document_count,
               SUM(d.status = 'approved') AS approved_document_count,
               GROUP_CONCAT(CONCAT_WS(' :: ', d.document_type, d.status, COALESCE(f.original_name, 'No file')) ORDER BY d.submitted_at DESC SEPARATOR '||') AS document_summary
        FROM vendor_kyc_documents d
        LEFT JOIN files f ON f.id = d.file_id
        GROUP BY d.vendor_id
     ) ds ON ds.vendor_id = v.id
     LEFT JOIN vendor_applications va ON va.id = (
        SELECT va2.id
        FROM vendor_applications va2
        WHERE va2.vendor_id = v.id
        ORDER BY va2.submitted_at DESC, va2.id DESC
        LIMIT 1
     )
     LEFT JOIN (
        SELECT vendor_id, COUNT(*) AS order_count, SUM(gross_total) AS gross_total, SUM(vendor_earning) AS vendor_earning
        FROM order_vendor_splits
        GROUP BY vendor_id
     ) os ON os.vendor_id = v.id
     LEFT JOIN (
        SELECT p.vendor_id, COUNT(r.id) AS review_count, AVG(r.rating) AS avg_rating
        FROM reviews r
        INNER JOIN products p ON p.id = r.product_id
        WHERE r.status = 'approved'
        GROUP BY p.vendor_id
     ) rs ON rs.vendor_id = v.id
     LEFT JOIN (
        SELECT vendor_id,
               SUM(status = 'pending') AS pending_withdrawals,
               SUM(CASE WHEN status IN ('approved', 'paid') THEN amount ELSE 0 END) AS total_withdrawn
        FROM vendor_withdrawals
        GROUP BY vendor_id
	     ) ws ON ws.vendor_id = v.id";

$vendorSearchSql = '';
$vendorSearchParams = [];
if ($dashboardSearch !== '') {
    $vendorSearchSql = "(v.store_name LIKE ? OR v.store_slug LIKE ? OR v.store_email LIKE ? OR v.store_phone LIKE ? OR u.display_name LIKE ? OR u.email LIKE ?)";
    $like = '%' . $dashboardSearch . '%';
    $vendorSearchParams = [$like, $like, $like, $like, $like, $like];
}
$vendorListConfig = [
    'vendors' => ['dataKey' => 'vendors', 'where' => '', 'order' => 'ORDER BY v.created_at DESC'],
    'pending-vendors' => ['dataKey' => 'pending', 'where' => "(v.status = 'pending' OR v.kyc_status = 'pending') AND v.status <> 'rejected' AND v.kyc_status <> 'rejected'", 'order' => 'ORDER BY v.updated_at DESC'],
    'verified-vendors' => ['dataKey' => 'verified', 'where' => "v.status = 'active' AND v.kyc_status = 'approved'", 'order' => 'ORDER BY v.updated_at DESC'],
    'rejected-vendors' => ['dataKey' => 'rejected', 'where' => "v.status = 'rejected' OR v.kyc_status = 'rejected'", 'order' => 'ORDER BY v.updated_at DESC'],
    'vendor-stores' => ['dataKey' => 'stores', 'where' => '', 'order' => 'ORDER BY active_product_count DESC, gross_total DESC'],
];
$currentVendorConfig = $vendorListConfig[$view] ?? $vendorListConfig['vendors'];
$vendorWhereParts = array_filter([$currentVendorConfig['where'], $vendorSearchSql], static fn ($part): bool => (string)$part !== '');
$vendorWhereSql = $vendorWhereParts !== [] ? ' WHERE ' . implode(' AND ', array_map(static fn ($part): string => '(' . $part . ')', $vendorWhereParts)) : '';
$vendorTotalRow = db()->fetch("SELECT COUNT(DISTINCT v.id) AS total FROM vendors v INNER JOIN users u ON u.id = v.user_id {$vendorWhereSql}", $vendorSearchParams);
$vendorTotal = (int)($vendorTotalRow['total'] ?? 0);
$vendorCompletion = ['summary' => [], 'filter' => ''];
if ($view === 'pending-vendors') {
    // Score the full search result before pagination, using the same checks as the review cards.
    $vendorCompletion = \App\VendorCompletionService::listing(
        dashboard_table_rows($vendorBaseSql . $vendorWhereSql, $vendorSearchParams, 'vendors'),
        is_string($_GET['completion'] ?? null) ? $_GET['completion'] : ''
    );
    $vendorTotal = count($vendorCompletion['rows']);
}
$vendorTotalPages = max(1, (int)ceil($vendorTotal / $dashboardPerPage));
$vendorPage = max(1, min((int)($_GET['page'] ?? 1), $vendorTotalPages));
$vendorOffset = ($vendorPage - 1) * $dashboardPerPage;
$currentVendorRows = $view === 'pending-vendors'
    ? array_slice($vendorCompletion['rows'], $vendorOffset, $dashboardPerPage)
    : dashboard_table_rows($vendorBaseSql . $vendorWhereSql . ' ' . $currentVendorConfig['order'] . " LIMIT {$dashboardPerPage} OFFSET {$vendorOffset}", $vendorSearchParams, 'vendors');
$vendorRows = [];
$pendingVendorRows = [];
$verifiedVendorRows = [];
$rejectedVendorRows = [];
$vendorStoreRows = [];
switch ($currentVendorConfig['dataKey']) {
    case 'pending':
        $pendingVendorRows = $currentVendorRows;
        break;
    case 'verified':
        $verifiedVendorRows = $currentVendorRows;
        break;
    case 'rejected':
        $rejectedVendorRows = $currentVendorRows;
        break;
    case 'stores':
        $vendorStoreRows = $currentVendorRows;
        break;
    default:
        $vendorRows = $currentVendorRows;
        break;
}

$reviewVendorIds = $view === 'pending-vendors' ? array_values(array_filter(array_map(static fn (array $row): int => (int)$row['id'], $currentVendorRows))) : [];
$reviewDocumentFilter = $reviewVendorIds !== [] ? ' WHERE d.vendor_id IN (' . implode(',', $reviewVendorIds) . ')' : '';
$vendorKycRows = dashboard_table_rows(
    "SELECT d.id, d.vendor_id, d.document_type, d.document_number, d.status, d.rejection_reason, d.submitted_at, d.reviewed_at,
            v.store_name, v.store_slug, u.display_name AS reviewer_name, f.path AS file_path, f.original_name, f.mime_type
     FROM vendor_kyc_documents d
     INNER JOIN vendors v ON v.id = d.vendor_id
     LEFT JOIN users u ON u.id = d.reviewed_by
     LEFT JOIN files f ON f.id = d.file_id
     {$reviewDocumentFilter}
     ORDER BY FIELD(d.status, 'pending', 'rejected', 'approved'), d.submitted_at DESC
     " . ($reviewVendorIds === [] ? ' LIMIT 120' : ''),
    [],
    'vendor_kyc_documents'
);

$vendorRatingRows = dashboard_table_rows(
    "SELECT v.id, v.store_name, v.store_slug, v.status, v.kyc_status,
            COUNT(r.id) AS review_count,
            COALESCE(AVG(r.rating), 0) AS avg_rating,
            SUM(r.status = 'pending') AS pending_reviews,
            SUM(r.status = 'approved') AS approved_reviews,
            MAX(r.created_at) AS latest_review_at
     FROM vendors v
     LEFT JOIN products p ON p.vendor_id = v.id
     LEFT JOIN reviews r ON r.product_id = p.id
     GROUP BY v.id, v.store_name, v.store_slug, v.status, v.kyc_status
     ORDER BY avg_rating DESC, review_count DESC
     LIMIT 120",
    [],
    'vendors'
);

$vendorPayoutRows = dashboard_table_rows(
    "SELECT v.id, v.store_name, v.store_slug, v.status, v.kyc_status, v.payout_method, v.payout_details,
            COUNT(w.id) AS withdrawal_count,
            COALESCE(SUM(CASE WHEN w.status = 'pending' THEN w.amount ELSE 0 END), 0) AS pending_amount,
            COALESCE(SUM(CASE WHEN w.status IN ('approved', 'paid') THEN w.amount ELSE 0 END), 0) AS paid_amount,
            MAX(w.requested_at) AS latest_request_at
     FROM vendors v
     LEFT JOIN vendor_withdrawals w ON w.vendor_id = v.id
     GROUP BY v.id, v.store_name, v.store_slug, v.status, v.kyc_status, v.payout_method, v.payout_details
     ORDER BY pending_amount DESC, latest_request_at DESC
     LIMIT 120",
    [],
    'vendors'
);

$vendorStatusRows = dashboard_table_rows("SELECT status, COUNT(*) AS total FROM vendors GROUP BY status", [], 'vendors');
$vendorKycStatusRows = dashboard_table_rows("SELECT kyc_status, COUNT(*) AS total FROM vendors GROUP BY kyc_status", [], 'vendors');
$vendorStatusTotals = [];
foreach ($vendorStatusRows as $row) {
    $vendorStatusTotals[(string)$row['status']] = (int)$row['total'];
}
$vendorKycTotals = [];
foreach ($vendorKycStatusRows as $row) {
    $vendorKycTotals[(string)$row['kyc_status']] = (int)$row['total'];
}

$vendorPackageRows = VendorSubscriptionService::packages(false);
db()->pdo()->exec("
    CREATE TABLE IF NOT EXISTS vendor_applications (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        vendor_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        application_type VARCHAR(40) NOT NULL DEFAULT 'vendor',
        business_name VARCHAR(190) NOT NULL,
        owner_name VARCHAR(190) NOT NULL,
        product_category VARCHAR(120) NOT NULL,
        application_data LONGTEXT NOT NULL,
        submitted_at DATETIME NOT NULL,
        reviewed_at DATETIME NULL,
        reviewed_by BIGINT UNSIGNED NULL,
        INDEX idx_vendor_applications_vendor (vendor_id),
        INDEX idx_vendor_applications_user (user_id),
        INDEX idx_vendor_applications_type (application_type),
        INDEX idx_vendor_applications_submitted (submitted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$vendorSubscriptionRows = dashboard_table_rows(
    "SELECT s.*, p.name AS package_name, p.product_limit, p.price, p.currency, p.billing_interval,
            v.store_name, v.store_slug, u.email AS owner_email
     FROM vendor_subscriptions s
     INNER JOIN vendor_packages p ON p.id = s.package_id
     INNER JOIN vendors v ON v.id = s.vendor_id
     INNER JOIN users u ON u.id = v.user_id
     ORDER BY FIELD(s.status, 'past_due', 'unpaid', 'incomplete', 'active', 'trialing', 'cancelled', 'expired'), s.updated_at DESC
     LIMIT 150",
    [],
    'vendor_subscriptions'
);
$vendorSubscriptionStatusRows = dashboard_table_rows('SELECT status, COUNT(*) AS total FROM vendor_subscriptions GROUP BY status', [], 'vendor_subscriptions');
$vendorSubscriptionTotals = [];
foreach ($vendorSubscriptionStatusRows as $row) {
    $vendorSubscriptionTotals[(string)$row['status']] = (int)$row['total'];
}

$vendorEdit = null;
$vendorEditProducts = [];
$vendorEditDocuments = [];
$vendorEditSubscription = null;
$vendorEditProduct = null;
$vendorEditApplication = null;
$vendorEditMedia = [];
$vendorEditProductMedia = [];
$vendorEditProductStatus = preg_replace('/[^a-z_]/', '', (string)($_GET['product_status'] ?? 'all')) ?: 'all';
$editVendorId = max(0, (int)($_GET['vendor'] ?? 0));
if ($view === 'vendor-edit' && $editVendorId > 0) {
    $vendorEdit = db()->fetch(
        "SELECT v.*, u.email AS owner_email, u.username, u.first_name, u.last_name, u.display_name, u.phone AS owner_phone, u.status AS owner_status,
                a.address_line1, a.city, a.state, a.postcode, a.country_code,
                lf.path AS logo_path, bf.path AS banner_path
         FROM vendors v
         INNER JOIN users u ON u.id = v.user_id
         LEFT JOIN addresses a ON a.id = v.address_id
         LEFT JOIN files lf ON lf.id = v.logo_file_id
         LEFT JOIN files bf ON bf.id = v.banner_file_id
         WHERE v.id = ?
         LIMIT 1",
        [$editVendorId]
    );
    if ($vendorEdit) {
        $productStatusWhere = $vendorEditProductStatus !== 'all' && in_array($vendorEditProductStatus, ['draft', 'pending', 'active', 'private', 'archived', 'rejected'], true)
            ? ' AND p.status = ?'
            : '';
        $productStatusParams = $productStatusWhere !== '' ? [$editVendorId, $vendorEditProductStatus] : [$editVendorId];
        $vendorEditProducts = dashboard_table_rows(
            "SELECT p.id, p.name, p.slug, p.sku, p.status, p.regular_price, p.sale_price, p.currency, p.stock_status,
                    p.stock_quantity, p.manage_stock, p.low_stock_threshold, p.featured, p.total_sales, p.updated_at,
                    COALESCE(media_stats.media_count, 0) AS media_count,
                    f.path AS image_path
             FROM products p
             LEFT JOIN product_media pm ON pm.id = (
                SELECT pm2.id FROM product_media pm2 WHERE pm2.product_id = p.id ORDER BY pm2.role = 'primary' DESC, pm2.sort_order ASC, pm2.id ASC LIMIT 1
             )
             LEFT JOIN files f ON f.id = pm.file_id
             LEFT JOIN (
                SELECT product_id, COUNT(*) AS media_count
                FROM product_media
                GROUP BY product_id
             ) media_stats ON media_stats.product_id = p.id
             WHERE p.vendor_id = ?{$productStatusWhere}
             ORDER BY p.updated_at DESC, p.id DESC
             LIMIT 100",
            $productStatusParams,
            'products'
        );
        $vendorEditMedia = dashboard_table_rows(
            "SELECT DISTINCT f.id, f.path, f.original_name, f.mime_type, f.size_bytes, f.width, f.height, f.created_at,
                    CASE
                        WHEN v.logo_file_id = f.id THEN 'store_logo'
                        WHEN v.banner_file_id = f.id THEN 'store_banner'
                        WHEN pm.id IS NOT NULL THEN 'product_media'
                        WHEN d.id IS NOT NULL THEN 'kyc_document'
                        ELSE 'vendor_upload'
                    END AS usage_type,
                    p.id AS product_id,
                    p.name AS product_name,
                    pm.id AS media_id,
                    pm.role AS media_role,
                    d.document_type
             FROM files f
             INNER JOIN vendors v ON v.id = ?
             LEFT JOIN product_media pm ON pm.file_id = f.id
             LEFT JOIN products p ON p.id = pm.product_id AND p.vendor_id = v.id
             LEFT JOIN vendor_kyc_documents d ON d.file_id = f.id AND d.vendor_id = v.id
             WHERE f.mime_type LIKE 'image/%'
               AND (
                    f.owner_user_id = v.user_id
                    OR f.path LIKE CONCAT('uploads/vendor/', v.id, '/%')
                    OR p.id IS NOT NULL
                    OR d.id IS NOT NULL
                    OR v.logo_file_id = f.id
                    OR v.banner_file_id = f.id
               )
             ORDER BY f.created_at DESC, f.id DESC
             LIMIT 240",
            [$editVendorId],
            'files'
        );
        $vendorEditDocuments = dashboard_table_rows(
            "SELECT d.*, f.path AS file_path, f.original_name, f.mime_type
             FROM vendor_kyc_documents d
             LEFT JOIN files f ON f.id = d.file_id
             WHERE d.vendor_id = ?
             ORDER BY d.submitted_at DESC, d.id DESC",
            [$editVendorId],
            'vendor_kyc_documents'
        );
        $vendorEditApplication = db()->fetch(
            "SELECT *
             FROM vendor_applications
             WHERE vendor_id = ?
             ORDER BY submitted_at DESC, id DESC
             LIMIT 1",
            [$editVendorId]
        ) ?: null;
        $vendorEditSubscription = db()->fetch(
            "SELECT s.*, p.name AS package_name, p.product_limit, p.price, p.currency, p.billing_interval
             FROM vendor_subscriptions s
             INNER JOIN vendor_packages p ON p.id = s.package_id
             WHERE s.vendor_id = ?
             ORDER BY FIELD(s.status, 'active', 'trialing', 'past_due', 'unpaid', 'incomplete', 'cancelled', 'expired'), s.updated_at DESC, s.id DESC
             LIMIT 1",
            [$editVendorId]
        ) ?: VendorSubscriptionService::activeSubscription($editVendorId);
        $editVendorProductId = max(0, (int)($_GET['edit_product'] ?? 0));
        if ($editVendorProductId > 0) {
            $vendorEditProduct = db()->fetch('SELECT * FROM products WHERE id = ? AND vendor_id = ? LIMIT 1', [$editVendorProductId, $editVendorId]) ?: null;
            if ($vendorEditProduct) {
                $vendorEditProductMedia = dashboard_table_rows(
                    "SELECT pm.*, f.path, f.original_name, f.mime_type, f.size_bytes, f.width, f.height
                     FROM product_media pm
                     INNER JOIN files f ON f.id = pm.file_id
                     WHERE pm.product_id = ?
                     ORDER BY pm.role = 'primary' DESC, pm.sort_order ASC, pm.id ASC",
                    [$editVendorProductId],
                    'product_media'
                );
            }
        }
    }
}

$vendorsData = [
    'completion' => ['summary' => $vendorCompletion['summary'], 'filter' => $vendorCompletion['filter']],
    'views' => $vendorViews,
    'vendors' => $vendorRows,
    'editVendor' => $vendorEdit,
    'editProducts' => $vendorEditProducts,
    'editDocuments' => $vendorEditDocuments,
    'editSubscription' => $vendorEditSubscription,
    'editProduct' => $vendorEditProduct,
    'editApplication' => $vendorEditApplication,
    'editMedia' => $vendorEditMedia,
    'editProductMedia' => $vendorEditProductMedia,
    'editProductStatus' => $vendorEditProductStatus,
    'packages' => $vendorPackageRows,
    'subscriptions' => $vendorSubscriptionRows,
    'subscriptionTotals' => $vendorSubscriptionTotals,
    'graceDays' => VendorSubscriptionService::graceDays(),
    'pending' => $pendingVendorRows,
    'verified' => $verifiedVendorRows,
    'rejected' => $rejectedVendorRows,
    'stores' => $vendorStoreRows,
    'kycDocuments' => $vendorKycRows,
    'ratings' => $vendorRatingRows,
    'payouts' => $vendorPayoutRows,
    'statusTotals' => $vendorStatusTotals,
    'kycTotals' => $vendorKycTotals,
    'pagination' => [
        'page' => $vendorPage,
        'perPage' => $dashboardPerPage,
        'perPageOptions' => $dashboardPerPageOptions,
        'total' => $vendorTotal,
        'totalPages' => $vendorTotalPages,
        'from' => $vendorTotal === 0 ? 0 : $vendorOffset + 1,
        'to' => min($vendorOffset + count($currentVendorRows), $vendorTotal),
        'search' => $dashboardSearch,
    ],
    'stats' => [
        'total_vendors' => dashboard_count('vendors'),
        'pending_vendors' => dashboard_count('vendors', "(status = 'pending' OR kyc_status = 'pending') AND status <> 'rejected' AND kyc_status <> 'rejected'"),
        'verified_vendors' => (int)($vendorStatusTotals['active'] ?? 0),
        'rejected_vendors' => dashboard_count('vendors', "status = 'rejected' OR kyc_status = 'rejected'"),
        'kyc_documents' => dashboard_count('vendor_kyc_documents'),
        'payout_accounts' => dashboard_count('vendors', "payout_method IS NOT NULL AND payout_method <> ''"),
    ],
];

$orderBaseSql = "SELECT o.id, o.wp_order_id, o.order_number, o.customer_id, o.guest_email, o.status, o.payment_status,
            o.fulfillment_status, o.currency, o.subtotal, o.discount_total, o.shipping_total, o.tax_total, o.fee_total,
            o.grand_total, o.customer_note, o.placed_at, o.paid_at, o.completed_at, o.created_at, o.updated_at,
            u.display_name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
            COALESCE(item_stats.item_count, 0) AS item_count,
            COALESCE(item_stats.total_quantity, 0) AS total_quantity,
            COALESCE(vendor_stats.vendor_count, 0) AS vendor_count,
            COALESCE(payment_stats.payment_count, 0) AS payment_count,
            COALESCE(payment_stats.paid_amount, 0) AS paid_amount,
            COALESCE(shipment_stats.shipment_count, 0) AS shipment_count,
            shipment_stats.latest_tracking_number,
            shipment_stats.latest_tracking_url,
            shipment_stats.latest_shipment_status
     FROM orders o
     LEFT JOIN users u ON u.id = o.customer_id
     LEFT JOIN (
        SELECT order_id, COUNT(*) AS item_count, COALESCE(SUM(quantity), 0) AS total_quantity
        FROM order_items
        WHERE item_type = 'product'
        GROUP BY order_id
     ) item_stats ON item_stats.order_id = o.id
     LEFT JOIN (
        SELECT order_id, COUNT(DISTINCT vendor_id) AS vendor_count
        FROM (
            SELECT order_id, vendor_id
            FROM order_vendor_splits
            WHERE vendor_id IS NOT NULL
            UNION
            SELECT oi.order_id, COALESCE(oi.vendor_id, p.vendor_id) AS vendor_id
            FROM order_items oi
            LEFT JOIN products p ON p.id = oi.product_id
            WHERE oi.item_type = 'product' AND COALESCE(oi.vendor_id, p.vendor_id) IS NOT NULL
        ) order_vendors
        GROUP BY order_id
     ) vendor_stats ON vendor_stats.order_id = o.id
     LEFT JOIN (
        SELECT order_id, COUNT(*) AS payment_count, COALESCE(SUM(CASE WHEN status IN ('paid', 'authorized') THEN amount ELSE 0 END), 0) AS paid_amount
        FROM payments
        GROUP BY order_id
     ) payment_stats ON payment_stats.order_id = o.id
     LEFT JOIN (
        SELECT s.order_id,
               COUNT(*) AS shipment_count,
               SUBSTRING_INDEX(GROUP_CONCAT(s.tracking_number ORDER BY s.updated_at DESC, s.id DESC SEPARATOR '||'), '||', 1) AS latest_tracking_number,
               SUBSTRING_INDEX(GROUP_CONCAT(s.tracking_url ORDER BY s.updated_at DESC, s.id DESC SEPARATOR '||'), '||', 1) AS latest_tracking_url,
               SUBSTRING_INDEX(GROUP_CONCAT(s.status ORDER BY s.updated_at DESC, s.id DESC SEPARATOR '||'), '||', 1) AS latest_shipment_status
        FROM shipments s
        GROUP BY s.order_id
     ) shipment_stats ON shipment_stats.order_id = o.id";

$orderListConfig = [
    'orders' => [
        'dataKey' => 'orders',
        'where' => '',
        'countWhere' => null,
        'orderBy' => 'ORDER BY o.placed_at DESC, o.created_at DESC',
    ],
    'pending-orders' => [
        'dataKey' => 'pending',
        'where' => "o.status = 'pending'",
        'countWhere' => "status = 'pending'",
        'orderBy' => 'ORDER BY o.placed_at DESC, o.created_at DESC',
    ],
    'processing-orders' => [
        'dataKey' => 'processing',
        'where' => "o.status IN ('processing', 'on_hold', 'paid')",
        'countWhere' => "status IN ('processing', 'on_hold', 'paid')",
        'orderBy' => 'ORDER BY o.updated_at DESC',
    ],
    'shipped-orders' => [
        'dataKey' => 'shipped',
        'where' => "o.status IN ('partially_shipped', 'shipped') OR o.fulfillment_status = 'partial'",
        'countWhere' => "status IN ('partially_shipped', 'shipped') OR fulfillment_status = 'partial'",
        'orderBy' => 'ORDER BY o.updated_at DESC',
    ],
    'delivered-orders' => [
        'dataKey' => 'delivered',
        'where' => "o.status = 'completed' OR o.fulfillment_status = 'fulfilled'",
        'countWhere' => "status = 'completed' OR fulfillment_status = 'fulfilled'",
        'orderBy' => 'ORDER BY o.completed_at DESC, o.updated_at DESC',
    ],
    'cancelled-orders' => [
        'dataKey' => 'cancelled',
        'where' => "o.status IN ('cancelled', 'failed')",
        'countWhere' => "status IN ('cancelled', 'failed')",
        'orderBy' => 'ORDER BY o.updated_at DESC',
    ],
    'returned-orders' => [
        'dataKey' => 'returned',
        'where' => "o.status = 'refunded' OR o.fulfillment_status = 'returned' OR EXISTS (SELECT 1 FROM shipments s WHERE s.order_id = o.id AND s.status = 'returned')",
        'countWhere' => "status = 'refunded' OR fulfillment_status = 'returned'",
        'orderBy' => 'ORDER BY o.updated_at DESC',
    ],
];
$currentOrderConfig = $orderListConfig[$view] ?? $orderListConfig['orders'];
$orderPerPageOptions = [25, 50, 100];
$orderPerPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($orderPerPage, $orderPerPageOptions, true)) {
    $orderPerPage = 25;
}
$orderSearchSql = '';
$orderSearchParams = [];
if ($dashboardSearch !== '') {
    $orderSearchSql = "(o.order_number LIKE ? OR o.guest_email LIKE ? OR u.email LIKE ? OR u.display_name LIKE ? OR o.status LIKE ? OR o.payment_status LIKE ?)";
    $like = '%' . $dashboardSearch . '%';
    $orderSearchParams = [$like, $like, $like, $like, $like, $like];
}
$orderWhereParts = array_filter([$currentOrderConfig['where'], $orderSearchSql], static fn ($part): bool => (string)$part !== '');
$orderWhereSql = $orderWhereParts !== [] ? ' WHERE ' . implode(' AND ', array_map(static fn ($part): string => '(' . $part . ')', $orderWhereParts)) : '';
$orderTotal = dashboard_count('orders', $currentOrderConfig['countWhere']);
if ($orderWhereSql !== '') {
    $row = db()->fetch("SELECT COUNT(DISTINCT o.id) AS total FROM orders o LEFT JOIN users u ON u.id = o.customer_id {$orderWhereSql}", $orderSearchParams);
    $orderTotal = (int)($row['total'] ?? 0);
}
$orderTotalPages = max(1, (int)ceil($orderTotal / $orderPerPage));
$orderPage = max(1, min((int)($_GET['page'] ?? 1), $orderTotalPages));
$orderOffset = ($orderPage - 1) * $orderPerPage;
$currentOrderRows = dashboard_table_rows(
    $orderBaseSql . $orderWhereSql . ' ' . $currentOrderConfig['orderBy'] . ' LIMIT ' . $orderPerPage . ' OFFSET ' . $orderOffset,
    $orderSearchParams,
    'orders'
);

$orderRows = [];
$pendingOrderRows = [];
$processingOrderRows = [];
$shippedOrderRows = [];
$deliveredOrderRows = [];
$cancelledOrderRows = [];
$returnedOrderRows = [];
switch ($currentOrderConfig['dataKey']) {
    case 'pending':
        $pendingOrderRows = $currentOrderRows;
        break;
    case 'processing':
        $processingOrderRows = $currentOrderRows;
        break;
    case 'shipped':
        $shippedOrderRows = $currentOrderRows;
        break;
    case 'delivered':
        $deliveredOrderRows = $currentOrderRows;
        break;
    case 'cancelled':
        $cancelledOrderRows = $currentOrderRows;
        break;
    case 'returned':
        $returnedOrderRows = $currentOrderRows;
        break;
    default:
        $orderRows = $currentOrderRows;
        break;
}

$orderStatusRows = dashboard_table_rows("SELECT status, COUNT(*) AS total FROM orders GROUP BY status", [], 'orders');
$orderPaymentRows = dashboard_table_rows("SELECT payment_status, COUNT(*) AS total FROM orders GROUP BY payment_status", [], 'orders');
$orderStatusTotals = [];
foreach ($orderStatusRows as $row) {
    $orderStatusTotals[(string)$row['status']] = (int)$row['total'];
}
$orderPaymentTotals = [];
foreach ($orderPaymentRows as $row) {
    $orderPaymentTotals[(string)$row['payment_status']] = (int)$row['total'];
}

$orderDetail = null;
$orderItems = [];
$orderPayments = [];
$orderSplits = [];
$orderShipments = [];
$orderBillingAddress = null;
$orderShippingAddress = null;
$detailOrderRef = trim((string)($_GET['order'] ?? ''));
$detailOrderId = max(0, (int)$detailOrderRef);
if ($detailOrderRef !== '') {
    if ($detailOrderId > 0) {
        $orderDetail = db()->fetch(
            $orderBaseSql . ' WHERE o.id = ? LIMIT 1',
            [$detailOrderId]
        );
    }
    if (!$orderDetail) {
        $orderDetail = db()->fetch(
            $orderBaseSql . ' WHERE o.order_number = ? OR o.wp_order_id = ? LIMIT 1',
            [$detailOrderRef, $detailOrderId]
        );
        $detailOrderId = (int)($orderDetail['id'] ?? 0);
    }
    if ($orderDetail) {
        $orderItems = dashboard_table_rows(
            "SELECT oi.id, COALESCE(oi.product_id, matched_product.id) AS product_id,
                    COALESCE(oi.vendor_id, matched_product.vendor_id) AS vendor_id, oi.item_type, oi.name,
                    COALESCE(NULLIF(oi.sku, ''), matched_product.sku) AS sku, oi.quantity, oi.unit_price,
                    oi.subtotal, oi.discount_total, oi.tax_total, oi.total, matched_product.slug AS product_slug,
                    COALESCE(item_vendor.store_name, product_vendor.store_name) AS store_name,
                    f.path AS image_path
             FROM order_items oi
             LEFT JOIN (
                SELECT name, MIN(id) AS product_id
                FROM products
                GROUP BY name
                HAVING COUNT(*) = 1
             ) unique_product_names ON unique_product_names.name = oi.name
             LEFT JOIN products matched_product ON matched_product.id = COALESCE(
                oi.product_id,
                (SELECT p2.id FROM products p2 WHERE p2.sku = oi.sku AND oi.sku IS NOT NULL AND TRIM(oi.sku) <> '' LIMIT 1),
                unique_product_names.product_id
             )
             LEFT JOIN vendors item_vendor ON item_vendor.id = oi.vendor_id
             LEFT JOIN vendors product_vendor ON product_vendor.id = matched_product.vendor_id
             LEFT JOIN (
                SELECT product_id, MIN(file_id) AS file_id
                FROM product_media
                WHERE role = 'primary'
                GROUP BY product_id
             ) primary_media ON primary_media.product_id = matched_product.id
             LEFT JOIN files f ON f.id = primary_media.file_id
             WHERE oi.order_id = ?
             ORDER BY FIELD(oi.item_type, 'product', 'shipping', 'fee', 'tax', 'coupon'), oi.id ASC",
            [$detailOrderId],
            'order_items'
        );
        $orderPayments = dashboard_table_rows(
            "SELECT id, provider, provider_reference, provider_status, amount, currency, status, paid_at, created_at, updated_at
             FROM payments
             WHERE order_id = ?
             ORDER BY created_at DESC",
            [$detailOrderId],
            'payments'
        );
        $orderSplits = dashboard_table_rows(
            "SELECT ovs.id, ovs.vendor_id, ovs.status, ovs.item_subtotal, ovs.shipping_total, ovs.tax_total, ovs.gross_total,
                    ovs.vendor_earning, ovs.platform_commission, ovs.gateway_fee, ovs.created_at, ovs.updated_at, v.store_name
             FROM order_vendor_splits ovs
             LEFT JOIN vendors v ON v.id = ovs.vendor_id
             WHERE ovs.order_id = ?
             ORDER BY ovs.gross_total DESC, ovs.id ASC",
            [$detailOrderId],
            'order_vendor_splits'
        );
        $orderShipments = dashboard_table_rows(
            "SELECT s.id, s.vendor_id, s.tracking_number, s.tracking_url, s.status, s.shipped_at, s.delivered_at, s.cost,
                    s.created_at, s.updated_at, v.store_name
             FROM shipments s
             LEFT JOIN vendors v ON v.id = s.vendor_id
             WHERE s.order_id = ?
             ORDER BY s.updated_at DESC, s.id DESC",
            [$detailOrderId],
            'shipments'
        );
        $orderBillingAddress = !empty($orderDetail['billing_address_id'])
            ? db()->fetch('SELECT * FROM addresses WHERE id = ? LIMIT 1', [(int)$orderDetail['billing_address_id']])
            : null;
        $orderShippingAddress = !empty($orderDetail['shipping_address_id'])
            ? db()->fetch('SELECT * FROM addresses WHERE id = ? LIMIT 1', [(int)$orderDetail['shipping_address_id']])
            : null;
    }
}

$ordersData = [
    'views' => $orderViews,
    'orders' => $orderRows,
    'pending' => $pendingOrderRows,
    'processing' => $processingOrderRows,
    'shipped' => $shippedOrderRows,
    'delivered' => $deliveredOrderRows,
    'cancelled' => $cancelledOrderRows,
    'returned' => $returnedOrderRows,
    'statusTotals' => $orderStatusTotals,
    'paymentTotals' => $orderPaymentTotals,
    'detail' => $orderDetail,
    'items' => $orderItems,
    'payments' => $orderPayments,
    'splits' => $orderSplits,
    'shipments' => $orderShipments,
    'billingAddress' => $orderBillingAddress,
    'shippingAddress' => $orderShippingAddress,
    'pagination' => [
        'page' => $orderPage,
        'perPage' => $orderPerPage,
        'perPageOptions' => $orderPerPageOptions,
        'total' => $orderTotal,
        'totalPages' => $orderTotalPages,
        'from' => $orderTotal === 0 ? 0 : $orderOffset + 1,
        'to' => min($orderOffset + count($currentOrderRows), $orderTotal),
        'isOrderList' => in_array($view, $orderViews, true),
        'search' => $dashboardSearch,
    ],
    'stats' => [
        'total_orders' => dashboard_count('orders'),
        'pending_orders' => (int)($orderStatusTotals['pending'] ?? 0),
        'processing_orders' => (int)($orderStatusTotals['processing'] ?? 0) + (int)($orderStatusTotals['on_hold'] ?? 0) + (int)($orderStatusTotals['paid'] ?? 0),
        'shipped_orders' => (int)($orderStatusTotals['partially_shipped'] ?? 0) + (int)($orderStatusTotals['shipped'] ?? 0),
        'delivered_orders' => (int)($orderStatusTotals['completed'] ?? 0),
        'cancelled_orders' => (int)($orderStatusTotals['cancelled'] ?? 0) + (int)($orderStatusTotals['failed'] ?? 0),
        'returned_orders' => (int)($orderStatusTotals['refunded'] ?? 0),
        'paid_orders' => (int)($orderPaymentTotals['paid'] ?? 0),
    ],
];

$productBaseSql = "SELECT p.id, p.wp_post_id, p.vendor_id, p.brand_id, p.type, p.sku, p.name, p.slug, p.status, p.visibility,
            p.regular_price, p.sale_price, p.currency, p.stock_status, p.stock_quantity, p.manage_stock, p.low_stock_threshold,
            p.average_rating, p.review_count, p.total_sales, p.featured, p.published_at, p.created_at, p.updated_at,
            v.store_name, b.name AS brand_name, f.path AS image_path,
            COALESCE(cs.category_names, '') AS category_names
     FROM products p
     LEFT JOIN vendors v ON v.id = p.vendor_id
     LEFT JOIN brands b ON b.id = p.brand_id
     LEFT JOIN (
        SELECT product_id, MIN(file_id) AS file_id
        FROM product_media
        WHERE role = 'primary'
        GROUP BY product_id
     ) primary_media ON primary_media.product_id = p.id
     LEFT JOIN files f ON f.id = primary_media.file_id
     LEFT JOIN (
        SELECT pc.product_id, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS category_names
        FROM product_categories pc
        INNER JOIN categories c ON c.id = pc.category_id
        GROUP BY pc.product_id
     ) cs ON cs.product_id = p.id";

$productListConfig = [
    'products' => [
        'dataKey' => 'products',
        'where' => '',
        'countWhere' => null,
        'order' => 'ORDER BY p.created_at DESC',
    ],
    'pending-products' => [
        'dataKey' => 'pending',
        'where' => "p.status = 'pending'",
        'countWhere' => "status = 'pending'",
        'order' => 'ORDER BY p.updated_at DESC',
    ],
    'approved-products' => [
        'dataKey' => 'approved',
        'where' => "p.status = 'active'",
        'countWhere' => "status = 'active'",
        'order' => 'ORDER BY p.published_at DESC, p.updated_at DESC',
    ],
    'rejected-products' => [
        'dataKey' => 'rejected',
        'where' => "p.status = 'rejected'",
        'countWhere' => "status = 'rejected'",
        'order' => 'ORDER BY p.updated_at DESC',
    ],
    'featured-products' => [
        'dataKey' => 'featured',
        'where' => 'p.featured = 1',
        'countWhere' => 'featured = 1',
        'order' => 'ORDER BY p.updated_at DESC',
    ],
    'ranked-products' => [
        'dataKey' => 'ranked',
        'where' => '',
        'countWhere' => null,
        'order' => 'ORDER BY p.total_sales DESC, p.average_rating DESC, p.review_count DESC',
    ],
    'low-stock-products' => [
        'dataKey' => 'lowStock',
        'where' => 'p.manage_stock = 1 AND p.stock_quantity IS NOT NULL AND p.stock_quantity <= COALESCE(p.low_stock_threshold, 5) AND p.stock_quantity > 0',
        'countWhere' => 'manage_stock = 1 AND stock_quantity IS NOT NULL AND stock_quantity <= COALESCE(low_stock_threshold, 5) AND stock_quantity > 0',
        'order' => 'ORDER BY p.stock_quantity ASC',
    ],
    'out-of-stock-products' => [
        'dataKey' => 'outOfStock',
        'where' => "p.stock_status = 'out_of_stock' OR (p.manage_stock = 1 AND COALESCE(p.stock_quantity, 0) <= 0)",
        'countWhere' => "stock_status = 'out_of_stock' OR (manage_stock = 1 AND COALESCE(stock_quantity, 0) <= 0)",
        'order' => 'ORDER BY p.updated_at DESC',
    ],
];
$productListViews = array_keys($productListConfig);
$currentProductConfig = $productListConfig[$view] ?? $productListConfig['products'];
$productPerPageOptions = [25, 50, 100];
$productPerPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($productPerPage, $productPerPageOptions, true)) {
    $productPerPage = 25;
}
$productSearchSql = '';
$productSearchParams = [];
if ($dashboardSearch !== '') {
    $productSearchSql = "(p.name LIKE ? OR p.sku LIKE ? OR p.slug LIKE ? OR v.store_name LIKE ? OR b.name LIKE ?)";
    $like = '%' . $dashboardSearch . '%';
    $productSearchParams = [$like, $like, $like, $like, $like];
}
$productWhereParts = array_filter([$currentProductConfig['where'], $productSearchSql], static fn ($part): bool => (string)$part !== '');
$productWhereSql = $productWhereParts !== [] ? ' WHERE ' . implode(' AND ', array_map(static fn ($part): string => '(' . $part . ')', $productWhereParts)) : '';
$productTotal = dashboard_count('products', $currentProductConfig['countWhere']);
if ($productWhereSql !== '') {
    $row = db()->fetch("SELECT COUNT(DISTINCT p.id) AS total FROM products p LEFT JOIN vendors v ON v.id = p.vendor_id LEFT JOIN brands b ON b.id = p.brand_id {$productWhereSql}", $productSearchParams);
    $productTotal = (int)($row['total'] ?? 0);
}
$productTotalPages = max(1, (int)ceil($productTotal / $productPerPage));
$productPage = max(1, min((int)($_GET['page'] ?? 1), $productTotalPages));
$productOffset = ($productPage - 1) * $productPerPage;
$currentProductRows = dashboard_table_rows(
    $productBaseSql . $productWhereSql . ' ' . $currentProductConfig['order'] . ' LIMIT ' . $productPerPage . ' OFFSET ' . $productOffset,
    $productSearchParams,
    'products'
);

$productRows = [];
$pendingProductRows = [];
$approvedProductRows = [];
$rejectedProductRows = [];
$featuredProductRows = [];
$rankedProductRows = [];
$lowStockProductRows = [];
$outOfStockProductRows = [];
switch ($currentProductConfig['dataKey']) {
    case 'pending':
        $pendingProductRows = $currentProductRows;
        break;
    case 'approved':
        $approvedProductRows = $currentProductRows;
        break;
    case 'rejected':
        $rejectedProductRows = $currentProductRows;
        break;
    case 'featured':
        $featuredProductRows = $currentProductRows;
        break;
    case 'ranked':
        $rankedProductRows = $currentProductRows;
        break;
    case 'lowStock':
        $lowStockProductRows = $currentProductRows;
        break;
    case 'outOfStock':
        $outOfStockProductRows = $currentProductRows;
        break;
    default:
        $productRows = $currentProductRows;
        break;
}

$productCommentRows = dashboard_table_rows(
    "SELECT q.id, q.product_id, q.user_id, q.question, q.status, q.created_at, p.name AS product_name, u.display_name, u.email,
            COUNT(qa.id) AS answer_count
     FROM questions q
     INNER JOIN products p ON p.id = q.product_id
     LEFT JOIN users u ON u.id = q.user_id
     LEFT JOIN question_answers qa ON qa.question_id = q.id
     GROUP BY q.id, q.product_id, q.user_id, q.question, q.status, q.created_at, p.name, u.display_name, u.email
     ORDER BY q.created_at DESC
     LIMIT 160",
    [],
    'questions'
);

$productReviewRows = dashboard_table_rows(
    "SELECT r.id, r.product_id, r.user_id, r.rating, r.title, r.body, r.status, r.created_at, r.updated_at,
            p.name AS product_name, COALESCE(NULLIF(u.display_name, ''), NULLIF(r.guest_name, '')) AS display_name,
            COALESCE(NULLIF(u.email, ''), NULLIF(r.guest_email, '')) AS email
     FROM reviews r
     INNER JOIN products p ON p.id = r.product_id
     LEFT JOIN users u ON u.id = r.user_id
     ORDER BY r.created_at DESC
     LIMIT 160",
    [],
    'reviews'
);

$productMetaRows = dashboard_table_rows(
    "SELECT p.id, p.name, p.slug, p.sku, p.status, p.visibility, p.short_description, p.description,
            p.published_at, p.updated_at, b.name AS brand_name, v.store_name,
            COALESCE(cs.category_names, '') AS category_names
     FROM products p
     LEFT JOIN vendors v ON v.id = p.vendor_id
     LEFT JOIN brands b ON b.id = p.brand_id
     LEFT JOIN (
        SELECT pc.product_id, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS category_names
        FROM product_categories pc
        INNER JOIN categories c ON c.id = pc.category_id
        GROUP BY pc.product_id
     ) cs ON cs.product_id = p.id
     ORDER BY p.updated_at DESC
     LIMIT 160",
    [],
    'products'
);

$productStatusRows = dashboard_table_rows("SELECT status, COUNT(*) AS total FROM products GROUP BY status", [], 'products');
$productStatusTotals = [];
foreach ($productStatusRows as $row) {
    $productStatusTotals[(string)$row['status']] = (int)$row['total'];
}

$productEdit = null;
$editProductId = max(0, (int)($_GET['edit'] ?? 0));
if ($editProductId > 0) {
    $productEdit = db()->fetch("SELECT * FROM products WHERE id = ? LIMIT 1", [$editProductId]);
}

$productsData = [
    'views' => $productViews,
    'products' => $productRows,
    'pending' => $pendingProductRows,
    'approved' => $approvedProductRows,
    'rejected' => $rejectedProductRows,
    'featured' => $featuredProductRows,
    'ranked' => $rankedProductRows,
    'lowStock' => $lowStockProductRows,
    'outOfStock' => $outOfStockProductRows,
    'comments' => $productCommentRows,
    'reviews' => $productReviewRows,
    'meta' => $productMetaRows,
    'statusTotals' => $productStatusTotals,
    'pagination' => [
        'page' => $productPage,
        'perPage' => $productPerPage,
        'perPageOptions' => $productPerPageOptions,
        'total' => $productTotal,
        'totalPages' => $productTotalPages,
        'from' => $productTotal === 0 ? 0 : $productOffset + 1,
        'to' => min($productOffset + count($currentProductRows), $productTotal),
        'isProductList' => in_array($view, $productListViews, true),
        'search' => $dashboardSearch,
    ],
    'edit' => $productEdit,
    'showForm' => isset($_GET['new']) || $productEdit !== null,
    'vendors' => dashboard_table_rows("SELECT id, store_name FROM vendors ORDER BY store_name ASC LIMIT 1000", [], 'vendors'),
    'brands' => dashboard_table_rows("SELECT id, name FROM brands ORDER BY name ASC LIMIT 1000", [], 'brands'),
    'stats' => [
        'total_products' => dashboard_count('products'),
        'pending_products' => (int)($productStatusTotals['pending'] ?? 0),
        'approved_products' => (int)($productStatusTotals['active'] ?? 0),
        'rejected_products' => (int)($productStatusTotals['rejected'] ?? 0),
        'featured_products' => dashboard_count('products', 'featured = 1'),
        'low_stock_products' => dashboard_count('products', "manage_stock = 1 AND stock_quantity IS NOT NULL AND stock_quantity <= COALESCE(low_stock_threshold, 5) AND stock_quantity > 0"),
        'out_of_stock_products' => dashboard_count('products', "stock_status = 'out_of_stock' OR (manage_stock = 1 AND COALESCE(stock_quantity, 0) <= 0)"),
    ],
];

$migrationSummary = null;
if ($view === 'data-migration') {
    $migrationService = new MigrationService(db());
    $latestMigrationRun = $migrationService->latestRun();
    $migrationSummary = $latestMigrationRun ? $migrationService->summary((int)$latestMigrationRun['id']) : null;
}

$adminProfile = db()->fetch(
    'SELECT id, email, username, first_name, last_name, display_name, phone, status, last_login_at, created_at FROM users WHERE id = ? LIMIT 1',
    [(int)($_SESSION['user_id'] ?? 0)]
) ?: [];

render_layout('admin', 'admin/dashboard.php', [
    'title' => 'Super Admin Dashboard',
    'pageTitle' => $activeLabel,
    'activeLabel' => $activeLabel,
    'active' => $view,
    'view' => $view,
    'navigation' => $navigation,
    'stats' => $stats,
    'recentAudit' => $recentAudit,
    'workerRows' => $workerRows,
    'settingsPages' => $settingsPages,
    'settingPage' => $settingPage,
    'settingValues' => $settingValues,
    'roleRows' => $roleRows,
    'auditRows' => $auditRows,
    'auditPagination' => $auditPagination,
    'jobRows' => $jobRows,
    'jobSummary' => $jobSummary,
    'failedJobRows' => $failedJobRows,
    'emailJobRows' => $emailJobRows,
    'automation' => $automation,
    'dashboardReportsData' => $dashboardReportsData,
    'notificationsData' => $notificationsData,
    'paymentsData' => $paymentsData,
    'withdrawalsData' => $withdrawalsData,
    'shippingData' => $shippingData,
    'contentData' => $contentData,
    'affiliateData' => $affiliateData,
    'marketingData' => $marketingData,
    'refundDisputesData' => $refundDisputesData,
    'usersData' => $usersData,
    'vendorsData' => $vendorsData,
    'ordersData' => $ordersData,
    'productsData' => $productsData,
    'taxonomyData' => $taxonomyData,
    'migrationSummary' => $migrationSummary,
    'adminProfile' => $adminProfile,
    'notificationCount' => count($recentAudit),
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'href' => 'dashboard.php'],
        ['label' => $activeLabel],
    ],
]);
