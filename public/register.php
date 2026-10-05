<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/core/bootstrap.php';

use App\EmailService;
use App\AffiliateService;
use App\NotificationService;
use App\ProductService;
use App\SecurityService;
use App\PaymentService;
use App\VendorSubscriptionService;

const REGISTRATION_MAX_UPLOAD_BYTES = 5242880;

function registration_column_exists(string $table, string $column): bool {
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

function ensure_registration_schema(): void {
    db()->pdo()->exec("
        CREATE TABLE IF NOT EXISTS email_verification_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            purpose ENUM('registration', 'email_change') NOT NULL DEFAULT 'registration',
            sent_to VARCHAR(190) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email_verification_tokens_user_purpose (user_id, purpose, used_at),
            INDEX idx_email_verification_tokens_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

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

    if (function_exists('table_exists') && table_exists('vendor_applications')) {
        if (!registration_column_exists('vendor_applications', 'application_type')) {
            db()->pdo()->exec("ALTER TABLE vendor_applications ADD application_type VARCHAR(40) NOT NULL DEFAULT 'vendor' AFTER user_id");
        }
    }

    if (function_exists('table_exists') && table_exists('vendors')) {
        if (!registration_column_exists('vendors', 'origin_region')) {
            db()->pdo()->exec("ALTER TABLE vendors ADD origin_region VARCHAR(80) NULL AFTER description");
        }
        if (!registration_column_exists('vendors', 'country_of_origin')) {
            db()->pdo()->exec("ALTER TABLE vendors ADD country_of_origin VARCHAR(120) NULL AFTER origin_region");
        }
    }
}

function registration_role_id(string $code): int {
    $role = db()->fetch('SELECT id FROM roles WHERE code = ? LIMIT 1', [$code]);
    if (!$role) {
        throw new \RuntimeException('Required role is missing: ' . $code);
    }

    return (int)$role['id'];
}

function registration_unique_username(string $email): string {
    $base = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', strstr($email, '@', true) ?: 'user'), '-'));
    $base = substr($base !== '' ? $base : 'user', 0, 40);
    $username = $base;
    $counter = 2;

    while (db()->fetch('SELECT id FROM users WHERE username = ? LIMIT 1', [$username])) {
        $suffix = '-' . $counter++;
        $username = substr($base, 0, 40 - strlen($suffix)) . $suffix;
    }

    return $username;
}

function registration_slug(string $value, string $fallback): string {
    $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $value), '-'));
    return $slug !== '' ? $slug : $fallback . '-' . bin2hex(random_bytes(3));
}

function registration_unique_vendor_slug(string $storeName): string {
    $base = registration_slug($storeName, 'store');
    $slug = $base;
    $counter = 2;
    while (db()->fetch('SELECT id FROM vendors WHERE store_slug = ? LIMIT 1', [$slug])) {
        $slug = $base . '-' . $counter++;
    }

    return $slug;
}

function registration_unique_product_slug(string $name): string {
    $base = registration_slug($name, 'product');
    $slug = $base;
    $counter = 2;

    while (db()->fetch('SELECT id FROM products WHERE slug = ? LIMIT 1', [$slug])) {
        $slug = $base . '-' . $counter++;
    }

    return $slug;
}

function registration_product_sku(string $requestedSku, string $productName): string {
    $requested = ProductService::normalizeSku($requestedSku);
    if ($requested !== '' && !db()->fetch('SELECT id FROM products WHERE sku = ? LIMIT 1', [$requested])) {
        return $requested;
    }

    $namePart = ProductService::normalizeSku($productName);
    $namePart = $namePart !== '' ? substr($namePart, 0, 28) : 'PRODUCT';

    do {
        $sku = substr('SA-' . $namePart . '-' . strtoupper(bin2hex(random_bytes(3))), 0, 100);
    } while (db()->fetch('SELECT id FROM products WHERE sku = ? LIMIT 1', [$sku]));

    return $sku;
}

function registration_required_upload_error(string $field, string $label): ?string {
    $file = $_FILES[$field] ?? null;
    $error = is_array($file) ? (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;

    if ($error === UPLOAD_ERR_OK) {
        $size = (int)($file['size'] ?? 0);
        return $size > REGISTRATION_MAX_UPLOAD_BYTES ? $label . ' must be 5 MB or smaller.' : null;
    }

    if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        return $label . ' must be 5 MB or smaller.';
    }

    return $label . ' is required.';
}

function registration_optional_upload_id(string $field, int $ownerUserId, int $vendorId, string $bucket, ?array $allowed = null): ?int {
    $file = $_FILES[$field] ?? null;
    if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    return registration_store_upload($field, $ownerUserId, $vendorId, $bucket, $allowed);
}

function registration_post_text(string $key): string {
    return trim((string)($_POST[$key] ?? ''));
}

function registration_country_code(string $value, string $countryName = ''): string {
    $raw = strtoupper(trim($value));
    $compact = strtoupper((string)preg_replace('/[^A-Z0-9+]+/', '', $raw));
    $countryKey = strtolower((string)preg_replace('/[^a-z]+/i', '', $countryName));

    if (in_array($compact, ['+234', '234', 'NG', 'NGA'], true) || $countryKey === 'nigeria') {
        return 'NG';
    }

    if (in_array($compact, ['+1', '1', 'US', 'USA'], true) || in_array($countryKey, ['unitedstates', 'usa', 'america'], true)) {
        return 'US';
    }

    $letters = strtoupper(substr((string)preg_replace('/[^A-Z]/', '', $compact), 0, 2));
    return $letters !== '' ? $letters : 'US';
}

function registration_normalize_phone(string $value, string $countryHint = ''): string {
    $phone = trim($value);
    if ($phone === '') {
        return '';
    }

    $phone = str_replace(["\xC2\xA0", ' ', '-', '(', ')', '.'], '', $phone);
    if (str_starts_with($phone, '00')) {
        $phone = '+' . substr($phone, 2);
    }

    $digits = preg_replace('/\D+/', '', $phone) ?: '';
    $countryKey = strtolower((string)preg_replace('/[^a-z0-9+]+/i', '', $countryHint));

    if ($digits === '' || strlen($digits) < 7 || strlen($digits) > 15) {
        return '';
    }

    if (str_starts_with($phone, '+')) {
        return '+' . $digits;
    }

    if (in_array($countryKey, ['ng', 'nga', 'nigeria', '+234', '234'], true)) {
        if (str_starts_with($digits, '234')) {
            return '+' . $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+234' . substr($digits, 1);
        }
    }

    return $digits;
}

function registration_phone_variants(string $phone): array {
    $phone = trim($phone);
    if ($phone === '') {
        return [];
    }

    $digits = preg_replace('/\D+/', '', $phone) ?: '';
    $variants = [$phone];

    if ($digits !== '') {
        $variants[] = $digits;
        if (str_starts_with($digits, '234')) {
            $local = '0' . substr($digits, 3);
            $variants[] = '+' . $digits;
            $variants[] = $local;
            $variants[] = substr($digits, 3);
        }
    }

    return array_values(array_unique(array_filter($variants, static fn (string $item): bool => trim($item) !== '')));
}

function registration_post_choice(string $key, array $allowed, string $default = ''): string {
    $value = registration_post_text($key);
    return in_array($value, $allowed, true) ? $value : $default;
}

function registration_vendor_application_payload(array $fileIds): array {
    $fields = [
        'business' => [
            'business_name' => registration_post_text('store_name'),
            'country_of_operation' => registration_post_text('country_of_operation'),
            'business_category' => registration_post_text('product_category'),
            'what_do_you_sell' => registration_post_text('product_description'),
            'instagram_or_website' => registration_post_text('social_page'),
            'is_business_registered' => registration_post_text('is_business_registered'),
        ],
        'owner' => [
            'full_name' => trim(registration_post_text('first_name') . ' ' . registration_post_text('last_name')),
            'email' => registration_post_text('email'),
            'whatsapp_number' => registration_post_text('phone'),
            'country_of_residence' => registration_post_text('owner_country_residence'),
        ],
        'readiness' => [
            'products_currently_available' => registration_post_text('products_currently_available'),
            'currently_sells_outside_country' => registration_post_text('currently_export'),
            'how_to_sell_through_sac' => registration_post_text('fulfillment_method'),
            'product_description' => registration_post_text('product_description'),
            'product_price' => registration_post_text('product_regular_price'),
            'product_weight' => registration_post_text('product_weight'),
            'package_length' => registration_post_text('product_length'),
            'package_width' => registration_post_text('product_width'),
            'package_height' => registration_post_text('product_height'),
        ],
        'declaration' => [
            'authentic_legal_source' => registration_post_text('declaration_authentic') === '1',
            'vendor_terms_and_privacy' => registration_post_text('terms_consent') === '1',
        ],
        'file_ids' => $fileIds,
    ];

    return $fields;
}

function registration_store_upload(string $field, int $ownerUserId, int $vendorId, string $bucket, ?array $allowed = null): int {
    $allowed ??= ['image/jpeg', 'image/png', 'image/webp'];
    $file = $_FILES[$field] ?? null;
    if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new \RuntimeException('Please choose a valid upload file.');
    }
    if ((int)($file['size'] ?? 0) > REGISTRATION_MAX_UPLOAD_BYTES) {
        throw new \RuntimeException('Uploaded files must be 5 MB or smaller.');
    }

    $tmp = (string)$file['tmp_name'];
    $mime = (string)(mime_content_type($tmp) ?: '');
    if (!in_array($mime, $allowed, true)) {
        throw new \RuntimeException('Unsupported file type.');
    }

    $dir = APP_ROOT . '/public/uploads/vendor/' . max(1, $vendorId) . '/' . $bucket;
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new \RuntimeException('Upload directory could not be created.');
    }
    @chmod(APP_ROOT . '/public/uploads', 0777);
    @chmod(APP_ROOT . '/public/uploads/vendor', 0777);
    @chmod(APP_ROOT . '/public/uploads/vendor/' . max(1, $vendorId), 0777);
    @chmod($dir, 0777);

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        default => strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION) ?: 'bin'),
    };
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($tmp, $target)) {
        throw new \RuntimeException('Upload could not be saved.');
    }

    $relative = 'uploads/vendor/' . max(1, $vendorId) . '/' . $bucket . '/' . $name;
    $size = filesize($target) ?: 0;
    $dimensions = str_starts_with($mime, 'image/') ? @getimagesize($target) : false;

    db()->query(
        'INSERT INTO files (owner_user_id, disk, path, original_name, mime_type, size_bytes, width, height, alt_text, created_at) VALUES (?, "public", ?, ?, ?, ?, ?, ?, ?, ?)',
        [$ownerUserId, $relative, (string)$file['name'], $mime, $size, $dimensions[0] ?? null, $dimensions[1] ?? null, pathinfo((string)$file['name'], PATHINFO_FILENAME), sql_now()]
    );

    return (int)db()->lastInsertId();
}

function registration_start_session(int $userId, string $email, string $displayName, array $roles): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = $email;
    $_SESSION['display_name'] = $displayName;
    $_SESSION['roles'] = array_values(array_unique($roles));
    unset($_SESSION['pending_mfa']);
    db()->query('UPDATE users SET last_login_at = ? WHERE id = ?', [sql_now(), $userId]);
    audit('login_success', 'users', (string)$userId, [], ['method' => 'vendor_registration_onboarding'], $userId);
}

function registration_unique_referral_code(string $firstName, string $lastName): string {
    $base = strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $firstName . $lastName) ?: 'SA', 0, 8));
    do {
        $code = $base . random_int(1000, 9999);
    } while (db()->fetch('SELECT id FROM affiliates WHERE referral_code = ? LIMIT 1', [$code]));

    return $code;
}

function send_registration_confirmation(array $user, string $token, string $accountType): void {
    $brand = app_branding();
    $verifyUrl = app_url('verify-email?token=' . urlencode($token));
    $name = trim((string)($user['display_name'] ?? ''));
    $firstLine = match ($accountType) {
        'vendor' => 'Confirm your email to continue your vendor application on ' . $brand['name'] . '.',
        'affiliate' => 'Confirm your email to activate your affiliate access on ' . $brand['name'] . '.',
        default => 'Confirm your email to activate your shopping account on ' . $brand['name'] . '.',
    };
    $safeName = e($name !== '' ? $name : 'there');
    $safeBrand = e($brand['name']);
    $safeVerifyUrl = e($verifyUrl);
    $safeFirstLine = e($firstLine);
    $html = <<<HTML
<div style="font-family:Arial,sans-serif;line-height:1.6;color:#111111;background:#f8f8f9;padding:24px">
  <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #ececec;border-radius:24px;padding:28px">
    <h1 style="margin:0 0 12px;font-size:26px;color:#111111">Confirm your email</h1>
    <p>Hello {$safeName},</p>
    <p>{$safeFirstLine}</p>
    <p><a href="{$safeVerifyUrl}" style="display:inline-block;background:#22C55E;color:#ffffff;text-decoration:none;padding:13px 20px;border-radius:999px;font-weight:700">Confirm Email</a></p>
    <p style="font-size:13px;color:#666666">This link expires in 24 hours. If the button does not work, open this URL: {$safeVerifyUrl}</p>
    <p style="font-size:13px;color:#666666">{$safeBrand}</p>
  </div>
</div>
HTML;

    EmailService::send(
        [['email' => (string)$user['email'], 'name' => $name]],
        'Confirm your ' . $brand['name'] . ' account',
        $html
    );
}

function send_vendor_application_received_email(string $email, string $displayName, string $firstName = ''): void {
    $name = trim($firstName !== '' ? $firstName : $displayName);
    $safeName = e($name !== '' ? $name : 'there');
    $dashboardUrl = app_url('vendor/verification');
    $body = '<p>Hi ' . $safeName . ',</p>'
        . '<p>Your Seller Africa account has been created. Complete payment if you selected a paid plan, then finish verification.</p>'
        . '<p>You will need your store name, store banner, at least one product photo, product name and description, price in USD, packaged weight in kg, length, width and height in cm, fulfillment method, monthly shipment Yes/No answer, and agreement to our terms.</p>'
        . '<p>Discount price, profile photo, business registration document, identity document and social media page are optional. Manage My Store and homepage ranking are optional paid services, not approval requirements.</p>'
        . '<p>Save your progress and return using the link below. Our team will review your application after you submit all required details.</p>';

    NotificationService::enqueueEmail(
        [['email' => $email, 'name' => $name]],
        'Complete your Seller Africa registration',
        'Complete your registration',
        $body,
        [
            'button_text' => 'Complete verification',
            'button_url' => $dashboardUrl,
            'metadata' => [
                'type' => 'vendor_application_received',
                'email' => $email,
            ],
        ]
    );
}

function registration_csrf_is_valid(): bool {
    $submittedToken = (string)($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');

    return $submittedToken !== '' && $sessionToken !== '' && hash_equals($sessionToken, $submittedToken);
}

function registration_route_for_type(string $accountType): string {
    if (defined('DISTRIBUTOR_SIGNUP') && DISTRIBUTOR_SIGNUP) return 'distributor/register';
    return match ($accountType) {
        'vendor' => 'vendor/register',
        'affiliate' => 'affiliate/join',
        default => 'buyer/register',
    };
}

ensure_registration_schema();
VendorSubscriptionService::ensureSchema();
PaymentService::ensurePaystackMethod();

$errors = [];
$internalReasons = [];
$captchaRequired = SecurityService::captchaRequired('registration', client_ip());
$captchaChallenge = $captchaRequired ? SecurityService::ensureCaptchaChallenge('registration') : null;
$accountTypes = ['customer', 'vendor', 'affiliate'];
$accountType = (string)($_GET['type'] ?? $_POST['account_type'] ?? 'customer');
if (!in_array($accountType, $accountTypes, true)) {
    $accountType = 'customer';
}
$vendorPackages = VendorSubscriptionService::packages(true);
$requestedPackageId = max(0, (int)($_POST['package_id'] ?? $_GET['package'] ?? 0));
$selectedVendorPackage = null;
foreach ($vendorPackages as $packageOption) {
    if ((int)($packageOption['id'] ?? 0) === $requestedPackageId) {
        $selectedVendorPackage = $packageOption;
        break;
    }
}
if (!$selectedVendorPackage) {
    $selectedVendorPackage = $vendorPackages[0] ?? VendorSubscriptionService::freePackage();
}
$selectedVendorPackageId = (int)($selectedVendorPackage['id'] ?? 0);
$paymentProvider = in_array((string)($_POST['payment_provider'] ?? 'stripe'), ['stripe', 'paystack'], true)
    ? (string)($_POST['payment_provider'] ?? 'stripe')
    : 'stripe';
$paystackMethod = table_exists('payment_methods') ? db()->fetch("SELECT * FROM payment_methods WHERE code = 'paystack' LIMIT 1") : null;
$paystackAvailable = $paystackMethod && (int)($paystackMethod['is_active'] ?? 0) === 1 && PaymentService::isPaystackConfigured($paystackMethod);
$referralInput = trim((string)($_POST['referral_code'] ?? $_GET['ref'] ?? ($_SESSION['affiliate_referral']['token'] ?? $_SESSION['affiliate_referral']['code'] ?? '')));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $accountType = (string)($_POST['account_type'] ?? 'customer');
    if (!in_array($accountType, $accountTypes, true)) {
        $accountType = 'customer';
    }

    if (!registration_csrf_is_valid()) {
        audit('registration_csrf_failed', 'users', null, [], [
            'email' => strtolower(trim((string)($_POST['email'] ?? ''))),
            'account_type' => $accountType,
            'reason' => 'csrf_token_invalid_or_expired',
            'next_step' => 'Ask the user to refresh the registration page and submit the form again.',
        ]);
        flash('error', 'Your registration session expired for security. Please refresh the page, review the form, and submit again.');
        redirect(registration_route_for_type($accountType));
    }

    $loggedInUser = \App\AuthService::check() ? \App\AuthService::user() : null;

    if ($loggedInUser) {
        $firstName = trim((string)($_POST['first_name'] ?? '')) !== '' ? trim((string)$_POST['first_name']) : (string)($loggedInUser['first_name'] ?? '');
        $lastName = trim((string)($_POST['last_name'] ?? '')) !== '' ? trim((string)$_POST['last_name']) : (string)($loggedInUser['last_name'] ?? '');
        $email = (string)($loggedInUser['email'] ?? '');
        $phone = trim((string)($_POST['phone'] ?? '')) !== '' ? trim((string)$_POST['phone']) : (string)($loggedInUser['phone'] ?? '');
        $password = 'bypassed';
        $confirmPassword = 'bypassed';
        $userToUpgrade = $loggedInUser;
        
        if ($accountType === 'vendor' && db()->fetch('SELECT id FROM vendors WHERE user_id = ? LIMIT 1', [$userToUpgrade['id']])) {
            $errors[] = 'You are already registered as a vendor.';
        } elseif ($accountType === 'affiliate' && db()->fetch('SELECT id FROM affiliates WHERE user_id = ? LIMIT 1', [$userToUpgrade['id']])) {
            $errors[] = 'You are already registered as an affiliate. Click here to go to your dashboard.';
        }
    } else {
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['password_confirmation'] ?? '');
    }

    $storeName = $accountType === 'vendor' ? trim($firstName . ' ' . $lastName) : trim((string)($_POST['store_name'] ?? ''));
    $legalBusinessName = registration_post_text('legal_business_name');
    $countryOfOperation = registration_post_text('country_of_operation');
    $businessWebsite = registration_post_text('business_website');
    $socialPage = registration_post_text('social_page');
    $yearStarted = registration_post_text('year_started');
    $businessRegistrationNumber = registration_post_text('business_registration_number');
    $businessType = registration_post_choice('business_type', ['Individual', 'Registered Business', 'Cooperative', 'Manufacturer', 'Farmer or Producer', 'Distributor']);
    $isBusinessRegistered = registration_post_choice('is_business_registered', ['Yes', 'No']);
    $ownerPosition = registration_post_text('owner_position');
    $ownerEmail = strtolower(registration_post_text('owner_email'));
    $ownerPhone = registration_post_text('owner_phone');
    $ownerCountryResidence = registration_post_text('owner_country_residence');
    $productCategory = registration_post_choice('product_category', ['Food & Grocery', 'Fashion', 'Beauty & Wellness', 'Home & Lifestyle', 'Agriculture', 'Other', 'Beauty & Personal Care', 'Fashion & Accessories', 'Agriculture/Produce']);
    $productIntendedCount = registration_post_text('product_intended_count');
    $productsCurrentlyAvailable = registration_post_choice('products_currently_available', ['Yes', 'No']);
    $manufacturesProducts = registration_post_choice('manufactures_products', ['Yes', 'No']);
    $manufacturedWhere = registration_post_text('manufactured_where');
    $professionalLabels = registration_post_choice('professional_labels', ['Yes', 'No']);
    $labelsShowIngredients = registration_post_choice('labels_show_ingredients', ['Yes', 'No', 'N/A']);
    $labelsShowNetWeight = registration_post_choice('labels_show_net_weight', ['Yes', 'No']);
    $batchLotNumbers = registration_post_choice('batch_lot_numbers', ['Yes', 'No']);
    $expirationDates = registration_post_choice('expiration_dates', ['Yes', 'No', 'N/A']);
    $upcBarcodes = registration_post_choice('upc_barcodes', ['Yes', 'No']);
    $traceableToProducer = registration_post_choice('traceable_to_producer', ['Yes', 'No']);
    $currentlyExport = registration_post_choice('currently_export', ['Yes', 'No']);
    $fulfillmentMethod = registration_post_choice('fulfillment_method', ['Ship orders yourself', 'Send inventory to SAC U.S. warehouse', 'Join SAC consolidated export shipments', "I'm not sure yet", 'Ship inventory to SAC’s U.S. warehouse', 'Fulfill orders directly', 'Participate in SAC consolidated shipping', 'Not sure — I need assistance']);
    $usInventory = registration_post_choice('us_inventory', ['Yes', 'No']);
    $vendorStoreEmail = strtolower(trim((string)($_POST['store_email'] ?? '')));
    $vendorStorePhone = trim((string)($_POST['store_phone'] ?? ''));
    $vendorDescription = trim((string)($_POST['description'] ?? ''));
    $vendorOriginRegion = (string)($_POST['origin_region'] ?? '');
    if (!in_array($vendorOriginRegion, ['West Africa', 'East Africa', 'Caribbean', 'Other'], true)) {
        $vendorOriginRegion = '';
    }
    $vendorCountryOfOrigin = trim((string)($_POST['country_of_origin'] ?? ''));
    $vendorAddressLine1 = trim((string)($_POST['address_line1'] ?? ''));
    $vendorCity = trim((string)($_POST['city'] ?? ''));
    $vendorState = trim((string)($_POST['state'] ?? ''));
    $vendorPostcode = trim((string)($_POST['postcode'] ?? ''));
    $vendorCountryCode = registration_country_code((string)($_POST['country_code'] ?? 'US'), $countryOfOperation);
    $phoneRaw = $phone;
    $vendorStorePhoneRaw = $vendorStorePhone;
    $ownerPhoneRaw = $ownerPhone;
    $phone = registration_normalize_phone($phone, $vendorCountryCode);
    $vendorStorePhone = registration_normalize_phone($vendorStorePhone, $vendorCountryCode);
    $ownerPhone = registration_normalize_phone($ownerPhone, $vendorCountryCode);
    $vendorKycType = (string)($_POST['verification_type'] ?? 'business_registration');
    if (!in_array($vendorKycType, ['business_registration', 'identity', 'tax', 'address'], true)) {
        $vendorKycType = 'business_registration';
    }
    $vendorKycNumber = trim((string)($_POST['verification_document_number'] ?? ''));
    $firstProduct = [
        'name' => trim((string)($_POST['product_name'] ?? '')),
        'sku' => trim((string)($_POST['product_sku'] ?? '')),
        'regular_price' => (float)($_POST['product_regular_price'] ?? 0),
        'sale_price' => trim((string)($_POST['product_sale_price'] ?? '')),
        'stock_quantity' => max(0, (int)($_POST['product_stock_quantity'] ?? 0)),
        'short_description' => trim((string)($_POST['product_short_description'] ?? '')),
        'description' => trim((string)($_POST['product_description'] ?? '')),
        'weight' => (float)($_POST['product_weight'] ?? 0),
        'length' => (float)($_POST['product_length'] ?? 0),
        'width' => (float)($_POST['product_width'] ?? 0),
        'height' => (float)($_POST['product_height'] ?? 0),
    ];
    $paymentEmail = strtolower(trim((string)($_POST['payment_email'] ?? '')));
    $referralInput = trim((string)($_POST['referral_code'] ?? ''));
    $honeypot = trim((string)($_POST['website'] ?? ''));
    $startedAt = (int)($_POST['started_at'] ?? 0);
    $acceptedTerms = (string)($_POST['terms_consent'] ?? '') === '1';
    $rateStatus = SecurityService::rateLimitStatus('registration', $email !== '' ? $email : client_ip());
    $captchaRequired = (bool)$rateStatus['captcha'];

    if ($rateStatus['blocked']) {
        $errors[] = 'Registration could not be completed. Please try again later.';
        $internalReasons[] = 'registration_rate_limited_until_' . (string)($rateStatus['locked_until'] ?? 'unknown');
    }
    if ($captchaRequired && !SecurityService::verifyCaptcha('registration', (string)($_POST['captcha_answer'] ?? ''))) {
        $errors[] = 'Registration could not be completed. Please try again later.';
        $internalReasons[] = 'registration_captcha_failed';
    }
    if ($honeypot !== '') {
        $errors[] = 'Registration could not be completed. Please try again later.';
        $internalReasons[] = 'registration_honeypot_filled';
    }
    if (!$loggedInUser && $startedAt > 0 && time() - $startedAt < 3) {
        $errors[] = 'Registration was submitted too quickly. Please wait a few seconds, review the form, and submit again.';
        $internalReasons[] = 'registration_submitted_too_quickly';
    }
    if ($firstName === '') {
        $errors[] = 'First name is required.';
        $internalReasons[] = 'missing_first_name';
    }
    if ($lastName === '') {
        $errors[] = 'Last name is required.';
        $internalReasons[] = 'missing_last_name';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
        $internalReasons[] = 'invalid_email';
    }
    if ($phoneRaw !== '' && $phone === '') {
        $errors[] = 'Phone number must be valid. Nigerian numbers like +2348012345678 are accepted.';
        $internalReasons[] = 'invalid_phone';
    }
    if (defined('DISTRIBUTOR_SIGNUP') && DISTRIBUTOR_SIGNUP && $phone === '') {
        $errors[] = 'Enter a valid phone number with your country code.';
        $internalReasons[] = 'missing_distributor_phone';
    }
    if (!$loggedInUser && $email !== '' && SecurityService::isDisposableEmail($email)) {
        $errors[] = 'Please use a permanent email address.';
        $internalReasons[] = 'disposable_email_blocked';
    }
    if (!$loggedInUser) {
        foreach (SecurityService::passwordErrors($password, 5) as $passwordError) {
            $errors[] = $passwordError;
            $internalReasons[] = 'password_policy_failed';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
            $internalReasons[] = 'password_confirmation_mismatch';
        }
    }
    if (!$acceptedTerms) {
        $errors[] = match ($accountType) {
            'vendor' => 'You must accept the Seller Africa Vendor Terms and Conditions, Vendor Agreement, and Privacy Policy.',
            'affiliate' => 'You must accept the Seller Africa Affiliate Terms and Conditions and Privacy Policy.',
            default => 'You must accept the terms and privacy policy.',
        };
        $internalReasons[] = 'terms_not_accepted';
    }
    if ($accountType === 'vendor' && $storeName === '') {
        $errors[] = 'Store name is required for vendor registration.';
        $internalReasons[] = 'missing_store_name';
    }
    if ($accountType === 'vendor') {
        if ($selectedVendorPackageId <= 0) {
            $errors[] = 'Please select a vendor plan.';
            $internalReasons[] = 'missing_vendor_plan';
        }
        if ((float)($selectedVendorPackage['price'] ?? 0) > 0 && $paymentProvider === 'paystack') {
            if (!$paystackAvailable) {
                $errors[] = 'Paystack is not available yet. Please choose Stripe or contact support.';
                $internalReasons[] = 'paystack_not_configured';
            }
        }
        foreach (['Country' => $countryOfOperation, 'Category of products' => $productCategory, 'Phone' => $phone] as $label => $value) {
            if ($value === '') $errors[] = $label . ' is required.';
        }
    }
    if ($paymentEmail !== '' && !filter_var($paymentEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Affiliate payment email must be valid.';
        $internalReasons[] = 'invalid_affiliate_payment_email';
    }
    
    if (!$loggedInUser) {
        $existingUserByEmail = $email !== '' ? db()->fetch('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1', [$email]) : null;
        $phoneVariants = registration_phone_variants($phone);
        $existingUserByPhone = $phoneVariants !== []
            ? db()->fetch('SELECT id, password_hash FROM users WHERE phone IN (' . implode(',', array_fill(0, count($phoneVariants), '?')) . ') LIMIT 1', $phoneVariants)
            : null;
        $userToUpgrade = null;

        if ($existingUserByEmail) {
            if ($accountType === 'vendor') {
                $existingVendor = db()->fetch('SELECT id, status, kyc_status FROM vendors WHERE user_id = ? LIMIT 1', [(int)$existingUserByEmail['id']]);
                $errors[] = $existingVendor
                    ? 'A vendor application already exists for this email. Please sign in to continue from your vendor dashboard. Click here to sign in.'
                    : 'This email is already registered. Please sign in, then return to Apply as a Vendor to complete the full application with your existing email.';
                $internalReasons[] = $existingVendor ? 'existing_vendor_for_email' : 'existing_user_email_vendor_application_login_required';
            } elseif ($accountType === 'affiliate' && password_verify($password, (string)$existingUserByEmail['password_hash'])) {
                $userToUpgrade = $existingUserByEmail;
                
                if (db()->fetch('SELECT id FROM affiliates WHERE user_id = ? LIMIT 1', [$userToUpgrade['id']])) {
                    $errors[] = 'You are already registered as an affiliate. Click here to log in to your dashboard.';
                    $internalReasons[] = 'existing_affiliate_for_email';
                }
            } else {
                $errors[] = in_array($accountType, ['vendor', 'affiliate'], true)
                    ? 'An account with this email already exists. Click here to log in to your dashboard.'
                    : 'An account with this email already exists.';
                $internalReasons[] = 'existing_user_email';
            }
        }

        if ($existingUserByPhone && (!$userToUpgrade || $existingUserByPhone['id'] !== $userToUpgrade['id'])) {
            $errors[] = $accountType === 'vendor'
                ? 'This phone number is already registered. Please sign in, then return to Apply as a Vendor to complete the full application.'
                : 'An account with this phone already exists.';
            $internalReasons[] = 'existing_user_phone';
        }
    }
    if (SecurityService::registrationLooksSpammy([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'phone' => $phone,
        'store_name' => $storeName,
    ])) {
        $errors[] = 'Registration could not be completed. Please try again later.';
        $internalReasons[] = 'registration_spam_pattern_detected';
    }

    if ($errors === []) {
        $db = db();
        $registrationCommitted = false;
        $verificationEmail = null;
        $createdUserId = null;
        try {
            $rememberedReferral = $referralInput !== '' ? AffiliateService::rememberReferral($referralInput) : AffiliateService::currentReferral();
            $parentAffiliateId = $rememberedReferral ? (int)($rememberedReferral['affiliate_id'] ?? 0) : null;
            if ($parentAffiliateId !== null && $parentAffiliateId <= 0) {
                $parentAffiliateId = null;
            }

            $db->beginTransaction();

            $displayName = trim($firstName . ' ' . $lastName);

            if ($userToUpgrade) {
                $userId = (int)$userToUpgrade['id'];
                $createdUserId = $userId;
                
                $roles = [];
                if ($accountType === 'vendor') {
                    $roles[] = 'vendor';
                } elseif ($accountType === 'affiliate') {
                    $roles[] = 'affiliate';
                }
                
                foreach (array_unique($roles) as $roleCode) {
                    $roleId = registration_role_id($roleCode);
                    $hasRole = $db->fetch('SELECT 1 FROM user_roles WHERE user_id = ? AND role_id = ? LIMIT 1', [$userId, $roleId]);
                    if (!$hasRole) {
                        $db->query('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, $roleId]);
                    }
                }
                if ($loggedInUser && (int)$loggedInUser['id'] === $userId) {
                    $_SESSION['roles'] = array_values(array_unique(array_merge($_SESSION['roles'] ?? [], $roles)));
                }
            } else {
                $username = registration_unique_username($email);
                $userStatus = 'active';
                $emailVerifiedAt = $accountType === 'vendor' ? sql_now() : null;
                $db->query(
                    "INSERT INTO users (email, username, password_hash, first_name, last_name, display_name, phone, status, email_verified_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $email,
                        $username,
                        password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT),
                        $firstName,
                        $lastName,
                        $displayName,
                        $phone !== '' ? $phone : null,
                        $userStatus,
                        $emailVerifiedAt,
                    ]
                );
                $userId = (int)$db->lastInsertId();
                $createdUserId = $userId;

                $roles = ['customer'];
                if ($accountType === 'vendor') {
                    $roles[] = 'vendor';
                } elseif ($accountType === 'affiliate') {
                    $roles[] = 'affiliate';
                }
                foreach (array_unique($roles) as $roleCode) {
                    $db->query('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, registration_role_id($roleCode)]);
                }
            }

            if ($accountType === 'vendor') {
                $vendorColumns = ['user_id', 'store_name', 'store_slug', 'store_email', 'store_phone', 'description'];
                $vendorValues = [
                    $userId,
                    $storeName,
                    registration_unique_vendor_slug($storeName),
                    $email,
                    $phone !== '' ? $phone : null,
                    $firstProduct['description'],
                ];
                if (registration_column_exists('vendors', 'origin_region')) {
                    $vendorColumns[] = 'origin_region';
                    $vendorValues[] = null;
                }
                if (registration_column_exists('vendors', 'country_of_origin')) {
                    $vendorColumns[] = 'country_of_origin';
                    $vendorValues[] = $countryOfOperation;
                }
                $vendorColumns = array_merge($vendorColumns, ['address_id', 'status', 'kyc_status', 'created_at', 'updated_at']);
                $vendorValues = array_merge($vendorValues, [null, 'pending', 'not_started', sql_now(), sql_now()]);
                $db->query(
                    'INSERT INTO vendors (' . implode(', ', $vendorColumns) . ') VALUES (' . implode(', ', array_fill(0, count($vendorColumns), '?')) . ')',
                    $vendorValues
                );
                $vendorId = (int)$db->lastInsertId();

                $productImageId = null;
                $logoFileId = null;
                $bannerFileId = null;

                $vendorMediaUpdates = [];
                $vendorMediaValues = [];
                if (registration_column_exists('vendors', 'logo_file_id')) {
                    $vendorMediaUpdates[] = 'logo_file_id = ?';
                    $vendorMediaValues[] = $logoFileId;
                }
                if (registration_column_exists('vendors', 'banner_file_id')) {
                    $vendorMediaUpdates[] = 'banner_file_id = ?';
                    $vendorMediaValues[] = $bannerFileId;
                }
                if ($vendorMediaUpdates !== []) {
                    $vendorMediaUpdates[] = 'updated_at = ?';
                    $vendorMediaValues[] = sql_now();
                    $vendorMediaValues[] = $vendorId;
                    $db->query('UPDATE vendors SET ' . implode(', ', $vendorMediaUpdates) . ' WHERE id = ?', $vendorMediaValues);
                }

                $applicationFileIds = [
                    'product_image' => $productImageId,
                    'store_profile_image' => $logoFileId,
                    'store_banner' => $bannerFileId,
                ];
                $applicationPayload = registration_vendor_application_payload($applicationFileIds);
                $applicationPayload['onboarding'] = ['version' => 2, 'package_id' => $selectedVendorPackageId, 'submitted' => false, 'registration_fee_required' => true, 'registration_fee_policy' => \App\VendorRegistrationPaymentService::POLICY];
                $db->query(
                    'INSERT INTO vendor_applications (vendor_id, user_id, application_type, business_name, owner_name, product_category, application_data, submitted_at) VALUES (?, ?, "vendor", ?, ?, ?, ?, ?)',
                    [
                        $vendorId,
                        $userId,
                        $storeName,
                        $displayName,
                        $productCategory,
                        json_encode($applicationPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        sql_now(),
                    ]
                );

                if ((float)($selectedVendorPackage['price'] ?? 0) <= 0 && $selectedVendorPackageId > 0) {
                    try {
                        VendorSubscriptionService::assignFreePackage($vendorId, $selectedVendorPackageId);
                    } catch (\Throwable $subscriptionError) {
                        error_log('Vendor free subscription failed during registration: ' . $subscriptionError->getMessage());
                    }
                }
            } elseif ($accountType === 'affiliate') {
                $db->query(
                    "INSERT INTO affiliates (user_id, parent_affiliate_id, referral_code, payment_email, rate_type, commission_rate, status, registered_at)
                     VALUES (?, ?, ?, ?, 'percentage', 0, 'pending', ?)",
                    [$userId, $parentAffiliateId, registration_unique_referral_code($firstName, $lastName), $paymentEmail !== '' ? $paymentEmail : $email, sql_now()]
                );
            }

            if ($userToUpgrade) {
                $userRec = $db->fetch('SELECT email_verified_at FROM users WHERE id = ? LIMIT 1', [$userId]);
                if (empty($userRec['email_verified_at'])) {
                    $token = bin2hex(random_bytes(32));
                    $db->query(
                        "INSERT INTO email_verification_tokens (user_id, token_hash, purpose, sent_to, expires_at)
                         VALUES (?, ?, 'registration', ?, DATE_ADD(?, INTERVAL 24 HOUR))",
                        [$userId, hash('sha256', $token), $email, sql_now()]
                    );
                    $verificationEmail = ['user' => ['email' => $email, 'display_name' => $displayName], 'token' => $token];
                }
            } elseif ($accountType !== 'vendor') {
                $token = bin2hex(random_bytes(32));
                $db->query(
                    "INSERT INTO email_verification_tokens (user_id, token_hash, purpose, sent_to, expires_at)
                     VALUES (?, ?, 'registration', ?, DATE_ADD(?, INTERVAL 24 HOUR))",
                    [$userId, hash('sha256', $token), $email, sql_now()]
                );
                $verificationEmail = ['user' => ['email' => $email, 'display_name' => $displayName], 'token' => $token];
            }

            $db->commit();
            $registrationCommitted = true;
            $vendorCheckoutUrl = null;
            $vendorCheckoutError = null;

            if ($accountType === 'vendor' && isset($vendorId)) {
                try {
                    $vendorCheckoutUrl = \App\VendorRegistrationPaymentService::checkout((int)$vendorId);
                } catch (\Throwable $subscriptionError) {
                    $vendorCheckoutError = $subscriptionError->getMessage();
                    error_log('Vendor paid subscription checkout failed during registration: ' . $subscriptionError->getMessage());
                }
            }

            try {
                SecurityService::clearAttempts('registration', $email);
                audit('registration_created', 'users', (string)$userId, [], ['email' => $email, 'account_type' => $accountType], $userId);
            } catch (\Throwable $postCommitError) {
                error_log('Registration post-commit logging failed: ' . $postCommitError->getMessage());
            }

            if ($verificationEmail) {
                try {
                    send_registration_confirmation($verificationEmail['user'], (string)$verificationEmail['token'], $accountType);
                } catch (\Throwable $emailError) {
                    error_log('Registration verification email failed (account still created): ' . $emailError->getMessage());
                }
            }

            if ($accountType === 'vendor') {
                try {
                    send_vendor_application_received_email($email, $displayName, $firstName);
                } catch (\Throwable $emailError) {
                    error_log('Vendor application received email failed (application still created): ' . $emailError->getMessage());
                }
            }

            try {
                NotificationService::registrationCreated(['email' => $email, 'display_name' => $displayName], $accountType);
            } catch (\Throwable $emailError) {
                // The account was already created successfully - a failed
                // confirmation email should never undo a valid registration.
                error_log('Registration confirmation email failed (account still created): ' . $emailError->getMessage());
            }

            $vendorApplicationMessage = 'Your account is saved. Pay the compulsory NGN 1,500 registration fee, then complete any paid-plan subscription and verification so our team can review your application.';

            if (!$userToUpgrade && $accountType === 'vendor') {
                registration_start_session($userId, $email, $displayName, $roles ?? ['customer', 'vendor']);
                flash('success', $vendorApplicationMessage);
                if ($vendorCheckoutUrl) {
                    redirect($vendorCheckoutUrl);
                }
                if ($vendorCheckoutError) {
                    flash('warning', 'Your application was submitted, but payment checkout could not start: ' . $vendorCheckoutError);
                }
                if ((float)($selectedVendorPackage['price'] ?? 0) > 0) {
                    redirect('vendor/verification');
                }
                redirect('vendor/verification');
            } elseif (!$userToUpgrade) {
                registration_start_session($userId, $email, $displayName, $roles ?? ['customer']);
                if ($verificationEmail) {
                    flash('warning', 'Please confirm your email address to keep your account fully verified.');
                }
                flash('success', 'Registration completed successfully.');
                if ($accountType === 'customer' && !empty($_SESSION['distributor_registration_return'])) {
                    unset($_SESSION['distributor_registration_return']);
                    redirect('distributor/register');
                }
                redirect($accountType === 'affiliate' ? 'affiliate' : 'buyer');
            } elseif ($userToUpgrade) {
                if ($accountType === 'vendor') {
                    flash('success', $vendorApplicationMessage);
                } elseif (empty($userRec['email_verified_at'])) {
                    flash('success', 'Account updated. Check your email for the confirmation link.');
                } elseif (!$loggedInUser) {
                    flash('success', 'Your account has been updated successfully. Please log in.');
                } else {
                    flash('success', 'Your account has been updated successfully.');
                }
            } else {
                flash('success', 'Registration completed successfully.');
            }

            if ($loggedInUser && $userToUpgrade && (int)$loggedInUser['id'] === (int)$userToUpgrade['id']) {
                if ($accountType === 'vendor' && !empty($vendorCheckoutUrl)) {
                    redirect($vendorCheckoutUrl);
                }
                if ($accountType === 'vendor' && !empty($vendorCheckoutError)) {
                    flash('warning', 'Your application was submitted, but payment checkout could not start: ' . $vendorCheckoutError);
                }
                if ($accountType === 'vendor' && (float)($selectedVendorPackage['price'] ?? 0) > 0) {
                    redirect('vendor/verification');
                }
                redirect($accountType === 'vendor' ? 'vendor/verification' : ($accountType === 'affiliate' ? 'affiliate' : 'buyer'));
            } else {
                redirect('login.php');
            }
        } catch (\Throwable $e) {
            if (!$registrationCommitted && $db->pdo()->inTransaction()) {
                $db->rollBack();
            }
            error_log('Registration failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            audit('registration_exception', 'users', $createdUserId !== null ? (string)$createdUserId : null, [], [
                'email' => $email,
                'account_type' => $accountType,
                'committed' => $registrationCommitted,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'next_step' => $registrationCommitted
                    ? 'Ask the user to log in and continue onboarding. Check the failed post-registration action in the error details.'
                    : 'Review the form data, uploaded files, and database constraints, then ask the user to submit again.',
            ], $createdUserId);
            $errors[] = $registrationCommitted
                ? 'Registration was created, but one follow-up step failed. Please log in and continue from your dashboard. If anything is missing, contact support with the time of this attempt.'
                : 'Registration could not be completed. Please review the form, make sure uploaded files are supported, and try again. If it continues, contact support with the time of this attempt.';
        }
    } else {
        SecurityService::recordFailure('registration', $email !== '' ? $email : client_ip());
        audit('registration_failed', 'users', null, [], [
            'email' => $email,
            'account_type' => $accountType,
            'reason_summary' => $errors[0] ?? 'Registration validation failed.',
            'reasons' => $errors,
            'internal_reasons' => array_values(array_unique($internalReasons)),
            'ip' => client_ip(),
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'next_step' => 'Fix the listed form errors and submit the registration again.',
        ]);
        $captchaRequired = SecurityService::captchaRequired('registration', $email !== '' ? $email : client_ip());
        $captchaChallenge = $captchaRequired ? SecurityService::ensureCaptchaChallenge('registration') : null;
    }
}

foreach ($errors as $error) {
    flash('error', $error);
}

$initialVendorStep = 0;
if ($accountType === 'vendor' && $errors !== []) {
    $stepBusinessSignals = [
        'Store name',
        'Country of operation',
        'Business category',
        'Business registration answer',
    ];
    $stepContactSignals = [
        'First name',
        'Last name',
        'valid email',
        'Phone number',
        'Password',
        'Owner country of residence',
        'already registered',
    ];
    $stepSubmitSignals = [
        'products currently available',
        'sell outside your country',
        'sell through SAC',
        'Product description',
        'What you sell',
        'Product price',
        'Product weight',
        'Package length',
        'Store profile image',
        'Store banner',
        'Product image',
        'authentic and legally sourced',
        'Vendor Terms',
        'Vendor Terms and Conditions',
        'Privacy Policy',
    ];

    foreach ($errors as $error) {
        foreach ($stepSubmitSignals as $signal) {
            if (stripos((string)$error, $signal) !== false) {
                $initialVendorStep = 3;
                break 2;
            }
        }
    }

    if ($initialVendorStep === 0) {
        foreach ($errors as $error) {
            foreach ($stepContactSignals as $signal) {
                if (stripos((string)$error, $signal) !== false) {
                    $initialVendorStep = 2;
                    break 2;
                }
            }
        }
    }

    if ($initialVendorStep === 0) {
        foreach ($errors as $error) {
            foreach ($stepBusinessSignals as $signal) {
                if (stripos((string)$error, $signal) !== false) {
                    $initialVendorStep = 1;
                    break 2;
                }
            }
        }
    }
}

$isLoggedIn = \App\AuthService::check();
$loggedInUser = $isLoggedIn ? \App\AuthService::user() : null;

if ($isLoggedIn && $accountType === 'vendor' && $loggedInUser && db()->fetch('SELECT id FROM vendors WHERE user_id = ? LIMIT 1', [(int)$loggedInUser['id']])) {
    flash('info', 'You are already registered as a vendor.');
    redirect('vendor/verification');
}

$dashboardUrl = app_url('buyer');
if ($isLoggedIn && $loggedInUser) {
    if ($accountType === 'affiliate' && db()->fetch('SELECT id FROM affiliates WHERE user_id = ? LIMIT 1', [(int)$loggedInUser['id']])) {
        $dashboardUrl = app_url('affiliate');
    } elseif ($accountType === 'vendor' && db()->fetch('SELECT id FROM vendors WHERE user_id = ? LIMIT 1', [(int)$loggedInUser['id']])) {
        $dashboardUrl = app_url('vendor/verification');
    }
}

if (defined('DISTRIBUTOR_SIGNUP') && DISTRIBUTOR_SIGNUP) {
    render_layout('vendor-onboarding', 'distributor/page.php', [
        'title'=>'Apply for Distribution | Seller Africa', 'route'=>'distributor/register',
        'user'=>null, 'application'=>null, 'data'=>[], 'errors'=>$errors, 'saved'=>false,
        'captchaRequired'=>$captchaRequired, 'captchaChallenge'=>$captchaChallenge,
    ]);
    exit;
}

render('auth/register.php', [
    'title' => 'Create Account',
    'accountType' => $accountType,
    'errors' => $errors,
    'captchaRequired' => $captchaRequired,
    'captchaChallenge' => $captchaChallenge,
    'referralInput' => $referralInput,
    'isLoggedIn' => $isLoggedIn,
    'loggedInUser' => $loggedInUser,
    'dashboardUrl' => $dashboardUrl,
    'initialVendorStep' => $initialVendorStep,
    'vendorPackages' => $vendorPackages,
    'selectedVendorPackageId' => $selectedVendorPackageId,
    'paymentProvider' => $paymentProvider,
    'paystackAvailable' => $paystackAvailable,
    'maxUploadBytes' => REGISTRATION_MAX_UPLOAD_BYTES,
]);
