<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\NotificationService;
use App\SecurityService;
use App\VendorSubscriptionService;

function farmer_column_exists(string $table, string $column): bool {
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

function farmer_ensure_schema(): void {
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

    if (table_exists('vendor_applications') && !farmer_column_exists('vendor_applications', 'application_type')) {
        db()->pdo()->exec("ALTER TABLE vendor_applications ADD application_type VARCHAR(40) NOT NULL DEFAULT 'vendor' AFTER user_id");
    }
    if (table_exists('vendors')) {
        if (!farmer_column_exists('vendors', 'origin_region')) {
            db()->pdo()->exec("ALTER TABLE vendors ADD origin_region VARCHAR(80) NULL AFTER description");
        }
        if (!farmer_column_exists('vendors', 'country_of_origin')) {
            db()->pdo()->exec("ALTER TABLE vendors ADD country_of_origin VARCHAR(120) NULL AFTER origin_region");
        }
    }
}

function farmer_role_id(string $code): int {
    $role = db()->fetch('SELECT id FROM roles WHERE code = ? LIMIT 1', [$code]);
    if (!$role) {
        throw new \RuntimeException('Required role is missing: ' . $code);
    }

    return (int)$role['id'];
}

function farmer_text(string $key): string {
    return trim((string)($_POST[$key] ?? ''));
}

function farmer_array(string $key): array {
    $value = $_POST[$key] ?? [];
    if (!is_array($value)) {
        $value = [$value];
    }

    return array_values(array_filter(array_map(static fn ($item): string => trim((string)$item), $value), static fn (string $item): bool => $item !== ''));
}

function farmer_slug(string $value): string {
    $base = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $value), '-'));
    $base = $base !== '' ? $base : 'farm';
    $slug = $base;
    $counter = 2;
    while (db()->fetch('SELECT id FROM vendors WHERE store_slug = ? LIMIT 1', [$slug])) {
        $slug = $base . '-' . $counter++;
    }

    return $slug;
}

function farmer_username(string $email): string {
    $base = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', strstr($email, '@', true) ?: 'farmer'), '-'));
    $base = substr($base !== '' ? $base : 'farmer', 0, 40);
    $username = $base;
    $counter = 2;
    while (db()->fetch('SELECT id FROM users WHERE username = ? LIMIT 1', [$username])) {
        $suffix = '-' . $counter++;
        $username = substr($base, 0, 40 - strlen($suffix)) . $suffix;
    }

    return $username;
}

function farmer_phone(string $value): string {
    $phone = trim($value);
    if ($phone === '') {
        return '';
    }
    $phone = str_replace(["\xC2\xA0", ' ', '-', '(', ')', '.'], '', $phone);
    if (str_starts_with($phone, '00')) {
        $phone = '+' . substr($phone, 2);
    }
    $digits = preg_replace('/\D+/', '', $phone) ?: '';
    if ($digits === '' || strlen($digits) < 7 || strlen($digits) > 15) {
        return '';
    }

    return str_starts_with($phone, '+') ? '+' . $digits : $digits;
}

function farmer_file_error(string $field, string $label, bool $multiple = false): ?string {
    $file = $_FILES[$field] ?? null;
    if (!is_array($file)) {
        return $label . ' is required.';
    }
    if ($multiple && is_array($file['error'] ?? null)) {
        foreach ($file['error'] as $error) {
            if ((int)$error === UPLOAD_ERR_OK) {
                return null;
            }
        }
        return $label . ' is required.';
    }

    return (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK ? null : $label . ' is required.';
}

function farmer_upload_count(string $field): int {
    $file = $_FILES[$field] ?? null;
    if (!is_array($file) || !is_array($file['error'] ?? null)) {
        return 0;
    }

    $count = 0;
    foreach ($file['error'] as $error) {
        if ((int)$error === UPLOAD_ERR_OK) {
            $count++;
        }
    }

    return $count;
}

function farmer_store_upload_array(array $file, int $ownerUserId, int $vendorId, string $bucket, array $allowed): int {
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new \RuntimeException('Please choose a valid upload file.');
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

function farmer_store_upload(string $field, int $ownerUserId, int $vendorId, string $bucket, array $allowed): ?int {
    $file = $_FILES[$field] ?? null;
    if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    return farmer_store_upload_array($file, $ownerUserId, $vendorId, $bucket, $allowed);
}

function farmer_store_uploads(string $field, int $ownerUserId, int $vendorId, string $bucket, array $allowed): array {
    $file = $_FILES[$field] ?? null;
    if (!is_array($file) || !is_array($file['error'] ?? null)) {
        return [];
    }

    $ids = [];
    foreach ($file['error'] as $index => $error) {
        if ((int)$error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $ids[] = farmer_store_upload_array([
            'name' => $file['name'][$index] ?? 'upload',
            'type' => $file['type'][$index] ?? '',
            'tmp_name' => $file['tmp_name'][$index] ?? '',
            'error' => $error,
            'size' => $file['size'][$index] ?? 0,
        ], $ownerUserId, $vendorId, $bucket, $allowed);
    }

    return $ids;
}

function farmer_payload(array $fileIds): array {
    return [
        'farm_owner' => [
            'farm_business_name' => farmer_text('farm_name'),
            'owner_farmer_full_name' => farmer_text('owner_name'),
            'year_established' => farmer_text('year_established'),
            'role_on_farm' => farmer_text('role_on_farm'),
            'business_email' => farmer_text('email'),
            'phone_number' => farmer_text('phone'),
            'farm_address' => farmer_text('address_line1'),
            'city' => farmer_text('city'),
            'state' => farmer_text('state'),
            'zip_code' => farmer_text('postcode'),
            'website' => farmer_text('farm_website'),
            'social_media' => farmer_text('social_page'),
            'black_owned' => farmer_text('black_owned'),
            'farm_story' => farmer_text('description'),
        ],
        'what_do_you_grow' => [
            'sell_categories' => farmer_array('sell_categories'),
            'main_crops_products' => farmer_text('main_crops'),
            'certifications' => farmer_text('certifications'),
            'grown_by_farmer' => farmer_text('grown_by_farmer'),
            'sourced_explanation' => farmer_text('sourced_explanation'),
            'availability' => farmer_text('produce_availability'),
            'weekly_capacity' => farmer_text('weekly_capacity'),
            'buyer_types' => farmer_array('buyer_types'),
        ],
        'farming_practices' => [
            'growing_methods' => farmer_array('growing_methods'),
            'has_licenses' => farmer_text('has_licenses'),
            'license_details' => farmer_text('license_details'),
        ],
        'fulfillment' => [
            'options' => farmer_array('fulfillment_options'),
            'service_areas' => farmer_text('service_areas'),
            'delivery_radius_miles' => farmer_text('delivery_radius'),
            'carriers' => farmer_array('shipping_carriers'),
            'fresh_packaging_ready' => farmer_text('packaging_ready'),
            'fulfillment_speed' => farmer_text('fulfillment_speed'),
            'minimum_order' => farmer_text('minimum_order'),
            'minimum_order_amount' => farmer_text('minimum_order_amount'),
            'shipping_charges_paid_by' => farmer_text('shipping_charges_paid_by'),
        ],
        'store_readiness' => [
            'sells_online' => farmer_text('sells_online'),
            'online_sales_where' => farmer_text('online_sales_where'),
            'sells_farmers_markets' => farmer_text('sells_farmers_markets'),
            'updates_inventory' => farmer_text('updates_inventory'),
            'updates_sold_out' => farmer_text('updates_sold_out'),
            'fulfills_timeframe' => farmer_text('fulfills_timeframe'),
        ],
        'declaration' => [
            'accurate_information' => farmer_text('declaration_accurate') === '1',
            'independent_vendor' => farmer_text('declaration_independent') === '1',
            'responsible_products' => farmer_text('declaration_products') === '1',
            'responsible_fulfillment' => farmer_text('declaration_fulfillment') === '1',
            'accurate_origin' => farmer_text('declaration_origin') === '1',
            'no_false_claims' => farmer_text('declaration_claims') === '1',
            'no_certification_endorsement' => farmer_text('declaration_endorsement') === '1',
            'legal_compliance' => farmer_text('declaration_compliance') === '1',
            'platform_fee' => farmer_text('declaration_fee') === '1',
            'vendor_terms' => farmer_text('terms_consent') === '1',
            'electronic_signature_name' => farmer_text('signature_name'),
            'electronic_signature' => farmer_text('signature'),
            'signature_date' => farmer_text('signature_date'),
        ],
        'file_ids' => $fileIds,
    ];
}

function farmer_current_user(): ?array {
    if (!\App\AuthService::check()) {
        return null;
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    $user = db()->fetch('SELECT * FROM users WHERE id = ? LIMIT 1', [$userId]);
    return is_array($user) ? $user : null;
}

function farmer_vendor_for_user(int $userId): ?array {
    $vendor = db()->fetch('SELECT * FROM vendors WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
    return is_array($vendor) ? $vendor : null;
}

function farmer_has_application(int $vendorId): bool {
    try {
        return (bool)db()->fetch('SELECT id FROM vendor_applications WHERE vendor_id = ? AND application_type = "farmer" LIMIT 1', [$vendorId]);
    } catch (\Throwable) {
        return false;
    }
}

function farmer_ensure_user_role(int $userId, string $roleCode): void {
    $roleId = farmer_role_id($roleCode);
    $hasRole = db()->fetch('SELECT 1 FROM user_roles WHERE user_id = ? AND role_id = ? LIMIT 1', [$userId, $roleId]);
    if (!$hasRole) {
        db()->query('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, $roleId]);
    }
}

function farmer_request_country_code(): string {
    foreach ([
        'HTTP_CF_IPCOUNTRY',
        'HTTP_CLOUDFRONT_VIEWER_COUNTRY',
        'HTTP_X_APPENGINE_COUNTRY',
        'HTTP_X_COUNTRY_CODE',
        'HTTP_X_GEOIP_COUNTRY_CODE',
        'GEOIP_COUNTRY_CODE',
    ] as $key) {
        $country = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($_SERVER[$key] ?? '')) ?: '', 0, 2));
        if ($country !== '' && !in_array($country, ['XX', 'T1'], true)) {
            return $country;
        }
    }

    $ip = client_ip();
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return '';
    }

    $cache = $_SESSION['farmer_country_lookup'] ?? [];
    if (
        is_array($cache)
        && (string)($cache['ip'] ?? '') === $ip
        && (int)($cache['expires_at'] ?? 0) > time()
    ) {
        return strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($cache['country_code'] ?? '')) ?: '', 0, 2));
    }

    $country = farmer_lookup_country_by_ip($ip);
    $_SESSION['farmer_country_lookup'] = [
        'ip' => $ip,
        'country_code' => $country,
        'expires_at' => time() + 21600,
    ];

    return $country;
}

function farmer_lookup_country_by_ip(string $ip): string {
    try {
        $url = 'https://ipwho.is/' . rawurlencode($ip) . '?fields=success,country_code';
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: SellerAfrica-FreshRoots/1.0\r\n",
            ],
        ]);
        $json = @file_get_contents($url, false, $context);
        if (!is_string($json) || $json === '') {
            return '';
        }
        $payload = json_decode($json, true);
        if (!is_array($payload) || empty($payload['success'])) {
            return '';
        }

        return strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($payload['country_code'] ?? '')) ?: '', 0, 2));
    } catch (\Throwable $e) {
        error_log('FreshRoots country lookup failed: ' . $e->getMessage());
    }

    return '';
}

function farmer_registration_country_blocked(?string $countryCode = null): bool {
    $countryCode = strtoupper((string)($countryCode ?? farmer_request_country_code()));

    return $countryCode === 'NG';
}

function farmer_render_country_block(array $brand, string $countryCode): void {
    $brandName = (string)($brand['name'] ?? 'Seller Africa');
    $logo = trim((string)($brand['logo'] ?? ''));
    http_response_code(403);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>FreshRoots unavailable | ' . e($brandName) . '</title>';
    echo '<style>body{margin:0;font-family:system-ui,-apple-system,Segoe UI,sans-serif;background:#f4faf5;color:#17211f}.wrap{min-height:100vh;display:grid;place-items:center;padding:28px}.card{width:min(620px,100%);background:#fff;border:1px solid #dfe8e5;border-radius:18px;padding:34px;box-shadow:0 20px 60px rgba(17,24,39,.10)}img{max-width:170px;max-height:64px;object-fit:contain;margin-bottom:24px}h1{margin:0 0 12px;font-size:clamp(30px,6vw,48px);line-height:1.02}p{color:#667276;font-size:17px;line-height:1.6;margin:0 0 18px}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:24px}.btn{min-height:46px;border-radius:10px;padding:0 18px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-weight:900}.primary{background:#00684f;color:#fff}.secondary{border:1px solid #dfe8e5;color:#00684f;background:#fff}.note{font-size:13px;color:#7a8588}</style></head><body>';
    echo '<main class="wrap"><section class="card">';
    if ($logo !== '') {
        echo '<img src="' . e($logo) . '" alt="' . e($brandName) . '">';
    }
    echo '<h1>FreshRoots farmer registration is not available in your country.</h1>';
    echo '<p>This feature is currently available only for eligible U.S. farmers and farm businesses. Based on your location, the farmer application cannot be submitted from your country.</p>';
    echo '<p class="note">Detected country: ' . e($countryCode !== '' ? $countryCode : 'Unavailable') . '</p>';
    echo '<div class="actions"><a class="btn primary" href="' . e(app_url('farm-fresh')) . '">Back to FreshRoots</a><a class="btn secondary" href="' . e(app_url('contact')) . '">Contact support</a></div>';
    echo '</section></main></body></html>';
    exit;
}

farmer_ensure_schema();
VendorSubscriptionService::ensureSchema();

$errors = [];
$success = false;
$brand = app_branding();
$detectedCountryCode = farmer_request_country_code();
if (farmer_registration_country_blocked($detectedCountryCode)) {
    farmer_render_country_block($brand, $detectedCountryCode);
}
$loggedInUser = farmer_current_user();
$loggedInVendor = $loggedInUser ? farmer_vendor_for_user((int)$loggedInUser['id']) : null;
$isUpgradeApplication = is_array($loggedInUser);
$upgradeNotice = $isUpgradeApplication
    ? 'You are signed in. Complete the FreshRoots details below to upgrade your existing account for farmer selling.'
    : '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $loggedInUser) {
    $_POST['owner_name'] = $_POST['owner_name'] ?? (string)($loggedInUser['display_name'] ?: trim((string)($loggedInUser['first_name'] ?? '') . ' ' . (string)($loggedInUser['last_name'] ?? '')));
    $_POST['email'] = $_POST['email'] ?? (string)($loggedInVendor['store_email'] ?? $loggedInUser['email'] ?? '');
    $_POST['phone'] = $_POST['phone'] ?? (string)($loggedInVendor['store_phone'] ?? $loggedInUser['phone'] ?? '');
    if ($loggedInVendor) {
        $_POST['farm_name'] = $_POST['farm_name'] ?? (string)($loggedInVendor['store_name'] ?? '');
        $_POST['description'] = $_POST['description'] ?? (string)($loggedInVendor['description'] ?? '');
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $_POST['signature_date'] = app_date('Y-m-d');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $loggedInVendor && farmer_has_application((int)$loggedInVendor['id'])) {
    flash('info', 'Your FreshRoots farmer application is already on file. Continue from your FreshRoots dashboard.');
    redirect('farmer');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = strtolower(farmer_text('email') ?: (string)($loggedInUser['email'] ?? ''));
    $phoneRaw = farmer_text('phone');
    $phone = farmer_phone($phoneRaw);
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirmation'] ?? '');
    $farmName = farmer_text('farm_name');
    $ownerName = farmer_text('owner_name');
    $nameParts = preg_split('/\s+/', $ownerName) ?: [];
    $firstName = (string)($nameParts[0] ?? '');
    $lastName = trim(implode(' ', array_slice($nameParts, 1))) ?: $firstName;

    $_POST['signature_date'] = farmer_text('signature_date') ?: app_date('Y-m-d');

    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($submittedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        $errors[] = 'Your registration session expired. Please refresh the page and submit again.';
    }
    foreach ([
        'Farm/Business Name' => $farmName,
        'Owner/Farmer Full Name' => $ownerName,
        'Business Email' => $email,
        'Phone Number' => $phone,
        'City' => farmer_text('city'),
        'State' => farmer_text('state'),
        'Farm Address' => farmer_text('address_line1'),
        'ZIP Code' => farmer_text('postcode'),
        'Farm Story' => farmer_text('description'),
        'Main crops/products' => farmer_text('main_crops'),
        'Produce availability' => farmer_text('produce_availability'),
        'Grown by farmer' => farmer_text('grown_by_farmer'),
        'Service areas' => farmer_text('service_areas'),
        'Packaging readiness' => farmer_text('packaging_ready'),
        'Fulfillment speed' => farmer_text('fulfillment_speed'),
        'Online selling status' => farmer_text('sells_online'),
        'Farmers market status' => farmer_text('sells_farmers_markets'),
        'Inventory update ability' => farmer_text('updates_inventory'),
        'Sold-out update ability' => farmer_text('updates_sold_out'),
        'Fulfillment timeframe ability' => farmer_text('fulfills_timeframe'),
        'Full legal name' => farmer_text('signature_name'),
        'Signature' => farmer_text('signature'),
        'Signature date' => farmer_text('signature_date'),
    ] as $label => $value) {
        if ($value === '') {
            $errors[] = $label . ' is required.';
        }
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid business email is required.';
    }
    if ($phoneRaw !== '' && $phone === '') {
        $errors[] = 'Please enter a valid phone number.';
    }
    if (!$isUpgradeApplication) {
        foreach (SecurityService::passwordErrors($password, 5) as $passwordError) {
            $errors[] = $passwordError;
        }
        if ($password !== $passwordConfirm) {
            $errors[] = 'Password confirmation does not match.';
        }
    }
    foreach (['sell_categories', 'buyer_types', 'growing_methods', 'fulfillment_options'] as $multiField) {
        if (farmer_array($multiField) === []) {
            $errors[] = ucwords(str_replace('_', ' ', $multiField)) . ' is required.';
        }
    }
    foreach ([
        'declaration_accurate' => 'Please confirm that the information provided is accurate.',
        'declaration_independent' => 'Please confirm that you operate as an independent vendor.',
        'declaration_products' => 'Please confirm that you are responsible for your products.',
        'declaration_fulfillment' => 'Please confirm that you are responsible for fulfillment.',
        'declaration_origin' => 'Please confirm that you will disclose product origin accurately.',
        'declaration_claims' => 'Please confirm that you will not make false or misleading claims.',
        'declaration_endorsement' => 'Please confirm that acceptance does not constitute certification or inspection.',
        'declaration_compliance' => 'Please confirm that you will comply with applicable laws.',
        'declaration_fee' => 'Please confirm that you understand applicable platform fees.',
        'terms_consent' => 'Please agree to Seller Africa terms.',
    ] as $field => $message) {
        if (farmer_text($field) !== '1') {
            $errors[] = $message;
        }
    }
    $farmPhotoCount = farmer_upload_count('farm_photos');
    if ($farmPhotoCount < 2) {
        $errors[] = 'Please upload at least 2 recent photographs of your farm and produce.';
    }
    if ($farmPhotoCount > 5) {
        $errors[] = 'Please upload no more than 5 recent photographs of your farm and produce.';
    }
    if ($isUpgradeApplication) {
        $emailOwner = db()->fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
        if ($emailOwner && (int)$emailOwner['id'] !== (int)$loggedInUser['id']) {
            $errors[] = 'This business email belongs to another account. Please use the email on your current account or contact support.';
        }
        if ($loggedInVendor && farmer_has_application((int)$loggedInVendor['id'])) {
            $errors[] = 'Your FreshRoots farmer application is already on file. Please continue from your FreshRoots dashboard.';
        }
    } elseif (db()->fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email])) {
        $errors[] = 'An account with this email already exists. Please log in and continue from your dashboard.';
    }

    if ($errors === []) {
        $db = db();
        try {
            $db->beginTransaction();
            $displayName = $ownerName;

            if ($isUpgradeApplication) {
                $userId = (int)$loggedInUser['id'];
                $db->query(
                    'UPDATE users SET first_name = ?, last_name = ?, display_name = ?, phone = COALESCE(NULLIF(?, ""), phone), updated_at = ? WHERE id = ?',
                    [$firstName, $lastName, $displayName, $phone, sql_now(), $userId]
                );
                foreach (['customer', 'vendor'] as $roleCode) {
                    farmer_ensure_user_role($userId, $roleCode);
                }
                $_SESSION['roles'] = array_values(array_unique(array_merge((array)($_SESSION['roles'] ?? []), ['customer', 'vendor'])));
            } else {
                $db->query(
                    "INSERT INTO users (email, username, password_hash, first_name, last_name, display_name, phone, status, email_verified_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)",
                    [$email, farmer_username($email), password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT), $firstName, $lastName, $displayName, $phone, sql_now()]
                );
                $userId = (int)$db->lastInsertId();
                foreach (['customer', 'vendor'] as $roleCode) {
                    $db->query('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, farmer_role_id($roleCode)]);
                }
            }

            $addressId = null;
            if (farmer_column_exists('addresses', 'postcode')) {
                $db->query(
                    'INSERT INTO addresses (user_id, type, address_line1, city, state, postcode, country_code, is_default, created_at, updated_at) VALUES (?, "store", ?, ?, ?, ?, "US", 1, ?, ?)',
                    [$userId, farmer_text('address_line1'), farmer_text('city'), farmer_text('state'), farmer_text('postcode'), sql_now(), sql_now()]
                );
            } else {
                $db->query(
                    'INSERT INTO addresses (user_id, type, address_line1, city, state, country_code, is_default, created_at, updated_at) VALUES (?, "store", ?, ?, ?, "US", 1, ?, ?)',
                    [$userId, farmer_text('address_line1'), farmer_text('city'), farmer_text('state'), sql_now(), sql_now()]
                );
            }
            $addressId = (int)$db->lastInsertId();

            if ($isUpgradeApplication && $loggedInVendor) {
                $vendorId = (int)$loggedInVendor['id'];
                $db->query(
                    'UPDATE vendors SET store_name = ?, store_email = ?, store_phone = ?, description = ?, origin_region = "United States", country_of_origin = "United States", address_id = ?, kyc_status = CASE WHEN kyc_status IN ("approved", "verified") THEN kyc_status ELSE "pending" END, updated_at = ? WHERE id = ?',
                    [$farmName, $email, $phone, farmer_text('description'), $addressId, sql_now(), $vendorId]
                );
            } else {
                $db->query(
                    'INSERT INTO vendors (user_id, store_name, store_slug, store_email, store_phone, description, origin_region, country_of_origin, address_id, status, kyc_status, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, "United States", "United States", ?, "pending", "pending", ?, ?)',
                    [$userId, $farmName, farmer_slug($farmName), $email, $phone, farmer_text('description'), $addressId, sql_now(), sql_now()]
                );
                $vendorId = (int)$db->lastInsertId();
            }

            $certificationId = farmer_store_upload('certification_file', $userId, $vendorId, 'kyc', ['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);
            if ($certificationId !== null) {
                $db->query('INSERT INTO vendor_kyc_documents (vendor_id, document_type, document_number, file_id, status, submitted_at) VALUES (?, "farm_certification_or_license", NULL, ?, "pending", ?)', [$vendorId, $certificationId, sql_now()]);
            }
            $photoIds = farmer_store_uploads('farm_photos', $userId, $vendorId, 'farm', ['image/jpeg', 'image/png', 'image/webp']);

            if ($photoIds !== []) {
                $db->query('UPDATE vendors SET banner_file_id = ?, logo_file_id = ?, updated_at = ? WHERE id = ?', [$photoIds[0], $photoIds[0], sql_now(), $vendorId]);
            }

            $payload = farmer_payload(['certification_or_license' => $certificationId, 'farm_photos' => $photoIds]);
            $db->query(
                'INSERT INTO vendor_applications (vendor_id, user_id, application_type, business_name, owner_name, product_category, application_data, submitted_at)
                 VALUES (?, ?, "farmer", ?, ?, "FreshRoots Produce", ?, ?)',
                [$vendorId, $userId, $farmName, $ownerName, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), sql_now()]
            );

            $package = $db->fetch("SELECT * FROM vendor_packages WHERE slug = 'free' LIMIT 1");
            $hasSubscription = db()->fetch('SELECT id FROM vendor_subscriptions WHERE vendor_id = ? LIMIT 1', [$vendorId]);
            if (!$hasSubscription && $package && (int)($package['id'] ?? 0) > 0) {
                $db->query(
                    "INSERT INTO vendor_subscriptions (vendor_id, package_id, status, provider, started_at, paid_at)
                     VALUES (?, ?, 'active', 'free', ?, ?)",
                    [$vendorId, (int)$package['id'], sql_now(), sql_now()]
                );
            }

            $db->commit();
            audit('farmer_registration_created', 'vendors', (string)$vendorId, [], ['email' => $email, 'application_type' => 'farmer'], $userId);
            NotificationService::registrationCreated(['email' => $email, 'display_name' => $displayName], 'farmer');

            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_email'] = $email;
            $_SESSION['display_name'] = $displayName;
            $_SESSION['roles'] = array_values(array_unique(array_merge((array)($_SESSION['roles'] ?? []), ['customer', 'vendor'])));
            flash('success', $isUpgradeApplication ? 'Your account has been upgraded with a FreshRoots farmer application. Our team will review the new details before activation.' : 'FreshRoots application submitted. Our team will review your farm profile and contact you about activation.');
            redirect('farmer');
        } catch (\Throwable $e) {
            if ($db->pdo()->inTransaction()) {
                $db->rollBack();
            }
            error_log('Farmer registration failed: ' . $e->getMessage());
            audit('farmer_registration_failed', 'vendors', null, [], ['email' => $email, 'error' => $e->getMessage()]);
            $errors[] = 'Your farmer application could not be completed. Please review the form and try again.';
        }
    }
}

foreach ($errors as $error) {
    flash('error', $error);
}

$toasts = function_exists('consume_toasts') ? consume_toasts() : [];
$stateOptions = ['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY','DC'];
$checked = static fn (string $field, string $value): string => in_array($value, farmer_array($field), true) ? ' checked' : '';
$selected = static fn (string $field, string $value): string => farmer_text($field) === $value ? ' selected' : '';

$templatePath = __DIR__ . '/farmers_registration_template.html';
$templateHtml = is_file($templatePath) ? (string)file_get_contents($templatePath) : '';
if (trim($templateHtml) !== '') {
    $fieldValue = static fn (string $field): string => e(farmer_text($field));
    $fillInputValue = static function (string $html, string $field, string $value): string {
        $pattern = '/<input\b(?=[^>]*\bname="' . preg_quote($field, '/') . '"(?![^>]*\btype="(?:file|password)")[^>]*)(?![^>]*\bvalue=)([^>]*)>/i';

        return preg_replace_callback(
            $pattern,
            static fn (array $match): string => '<input' . $match[1] . ' value="' . e($value) . '">',
            $html
        ) ?? $html;
    };
    $fillTextareaValue = static function (string $html, string $field, string $value): string {
        $pattern = '/(<textarea\b(?=[^>]*\bname="' . preg_quote($field, '/') . '"[^>]*>)(.*?)(<\/textarea>)/is';

        return preg_replace_callback(
            $pattern,
            static fn (array $match): string => $match[1] . e($value) . $match[3],
            $html
        ) ?? $html;
    };
    $selectOptionValue = static function (string $optionAttrs, string $optionText): string {
        if (preg_match('/\bvalue=(["\'])(.*?)\1/i', $optionAttrs, $valueMatch)) {
            return html_entity_decode((string)$valueMatch[2], ENT_QUOTES, 'UTF-8');
        }

        return trim(html_entity_decode(strip_tags($optionText), ENT_QUOTES, 'UTF-8'));
    };
    $fillSelectValue = static function (string $html, string $field, string $value) use ($selectOptionValue): string {
        if ($value === '') {
            return $html;
        }
        $pattern = '/(<select\b[^>]*\bname="' . preg_quote($field, '/') . '"[^>]*>)(.*?)(<\/select>)/is';

        return preg_replace_callback(
            $pattern,
            static function (array $match) use ($value, $selectOptionValue): string {
                $options = preg_replace_callback(
                    '/<option\b([^>]*)>(.*?)<\/option>/is',
                    static function (array $optionMatch) use ($value, $selectOptionValue): string {
                        $attrs = preg_replace('/\sselected\b/i', '', (string)$optionMatch[1]) ?? (string)$optionMatch[1];
                        if ($selectOptionValue($attrs, (string)$optionMatch[2]) === $value) {
                            $attrs .= ' selected';
                        }

                        return '<option' . $attrs . '>' . $optionMatch[2] . '</option>';
                    },
                    (string)$match[2]
                ) ?? (string)$match[2];

                return $match[1] . $options . $match[3];
            },
            $html
        ) ?? $html;
    };
    $fillCheckboxValues = static function (string $html, string $field, array $values): string {
        if ($values === []) {
            return $html;
        }
        $pattern = '/<input\b(?=[^>]*\btype="checkbox")(?=[^>]*\bname="' . preg_quote($field, '/') . '(?:\[\])?")(?=[^>]*\bvalue="([^"]*)")([^>]*)>/i';

        return preg_replace_callback(
            $pattern,
            static function (array $match) use ($values): string {
                $attrs = preg_replace('/\schecked\b/i', '', (string)$match[2]) ?? (string)$match[2];
                if (in_array(html_entity_decode((string)$match[1], ENT_QUOTES, 'UTF-8'), $values, true)) {
                    $attrs .= ' checked';
                }

                return '<input' . $attrs . '>';
            },
            $html
        ) ?? $html;
    };
    $toastHtml = '';
    if ($upgradeNotice !== '') {
        $toastHtml .= '<p class="intro" style="padding:12px 14px;background:#f4faf5;border:1px solid rgba(13,61,37,0.15);color:#0D3D25;margin-top:-18px">' . e($upgradeNotice) . '</p>';
    }
    foreach ($toasts as $toast) {
        $toastHtml .= '<p class="intro" style="padding:12px 14px;background:#fff7e6;border:1px solid rgba(201,146,42,0.35);color:#6B4226;margin-top:-18px">' . e((string)($toast['message'] ?? '')) . '</p>';
    }

    $templateHtml = str_replace(
        '<title>Apply as a Farmer — FreshRoots by Seller Africa</title>',
        '<title>Apply as a Farmer — FreshRoots by ' . e((string)$brand['name']) . '</title>',
        $templateHtml
    );
    $templateHtml = str_replace(
        [
            'href="black-farms-home.html#farms"',
            'href="black-farms-home.html#why"',
            'href="black-farms-home.html#how"',
        ],
        [
            'href="' . e(app_url('farms')) . '"',
            'href="' . e(app_url('freshroots')) . '#why"',
            'href="' . e(app_url('freshroots')) . '#how"',
        ],
        $templateHtml
    );
    $templateHtml = str_replace(
        '<div class="form-card">',
        '<form class="form-card" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="' . e(getCsrfToken()) . '">',
        $templateHtml
    );
    $templateHtml = str_replace(
        '<p class="intro">Fields marked with an asterisk are required. You can save and come back to finish later.</p>',
        '<p class="intro">Fields marked with an asterisk are required. You can save and come back to finish later.</p>' . $toastHtml,
        $templateHtml
    );

    $replacements = [
        '<input type="text" placeholder="e.g. Cedar Bend Farm">' => '<input type="text" name="farm_name" required placeholder="e.g. Cedar Bend Farm" value="' . $fieldValue('farm_name') . '">',
        '<input type="text" placeholder="e.g. 1987">' => '<input type="text" name="year_established" placeholder="e.g. 1987" value="' . $fieldValue('year_established') . '">',
        '<input type="text" placeholder="City">' => '<input type="text" name="city" required placeholder="City" value="' . $fieldValue('city') . '">',
        '<input type="text" placeholder="State">' => '<input type="text" name="state" required placeholder="State" value="' . $fieldValue('state') . '">',
        '<textarea placeholder="Tell buyers who you are, what you grow or raise, and what makes your farm yours."></textarea>' => '<textarea name="description" required placeholder="Tell buyers who you are, what you grow or raise, and what makes your farm yours.">' . e(farmer_text('description')) . '</textarea>',
        '<select>' => '<select name="fulfillment_options" required><option value="">Choose</option>',
        '<input type="text" placeholder="e.g. USDA Organic, Certified Naturally Grown">' => '<input type="text" name="certifications" placeholder="e.g. USDA Organic, Certified Naturally Grown" value="' . $fieldValue('certifications') . '">',
        '<input type="text" placeholder="Full name">' => '<input type="text" name="owner_name" required placeholder="Full name" value="' . $fieldValue('owner_name') . '">',
        '<input type="text" placeholder="e.g. Owner, Farm manager">' => '<input type="text" name="role_on_farm" placeholder="e.g. Owner, Farm manager" value="' . $fieldValue('role_on_farm') . '">',
        '<input type="email" placeholder="you@example.com">' => '<input type="email" name="email" required placeholder="you@example.com" value="' . $fieldValue('email') . '">',
        '<input type="tel" placeholder="(555) 555-5555">' => '<input type="tel" name="phone" required placeholder="(555) 555-5555" value="' . $fieldValue('phone') . '">',
        '<button class="submit-btn">Submit application</button>' => '<button class="submit-btn" type="submit">Submit application</button>',
    ];
    $templateHtml = str_replace(array_keys($replacements), array_values($replacements), $templateHtml);

    foreach (['Produce', 'Eggs', 'Honey', 'Meat and poultry', 'Dairy', 'Herbs and spices', 'Other'] as $category) {
        $templateHtml = str_replace(
            '<label class="check-pill"><input type="checkbox"> ' . $category . '</label>',
            '<label class="check-pill"><input type="checkbox" name="sell_categories[]" value="' . e($category) . '"' . $checked('sell_categories', $category) . '> ' . $category . '</label>',
            $templateHtml
        );
    }
    foreach (['Ship nationwide', 'Regional shipping only', 'Local delivery', 'Pickup only', 'A mix of the above'] as $option) {
        $templateHtml = str_replace(
            '<option>' . $option . '</option>',
            '<option value="' . e($option) . '"' . $selected('fulfillment_options', $option) . '>' . $option . '</option>',
            $templateHtml
        );
    }
    foreach ([
        'address_line1',
        'postcode',
        'farm_website',
        'social_page',
        'weekly_capacity',
        'delivery_radius',
        'minimum_order_amount',
        'online_sales_where',
        'signature_name',
        'signature',
        'signature_date',
    ] as $field) {
        $templateHtml = $fillInputValue($templateHtml, $field, $field === 'signature_date' ? (farmer_text($field) ?: app_date('Y-m-d')) : farmer_text($field));
    }
    foreach ([
        'main_crops',
        'sourced_explanation',
        'license_details',
        'service_areas',
    ] as $field) {
        $templateHtml = $fillTextareaValue($templateHtml, $field, farmer_text($field));
    }
    foreach ([
        'black_owned',
        'produce_availability',
        'grown_by_farmer',
        'has_licenses',
        'packaging_ready',
        'fulfillment_speed',
        'minimum_order',
        'shipping_charges_paid_by',
        'sells_online',
        'sells_farmers_markets',
        'updates_inventory',
        'updates_sold_out',
        'fulfills_timeframe',
    ] as $field) {
        $templateHtml = $fillSelectValue($templateHtml, $field, farmer_text($field));
    }
    foreach ([
        'buyer_types' => farmer_array('buyer_types'),
        'growing_methods' => farmer_array('growing_methods'),
        'shipping_carriers' => farmer_array('shipping_carriers'),
    ] as $field => $values) {
        $templateHtml = $fillCheckboxValues($templateHtml, $field, $values);
    }
    foreach ([
        'declaration_accurate',
        'declaration_independent',
        'declaration_products',
        'declaration_fulfillment',
        'declaration_origin',
        'declaration_claims',
        'declaration_endorsement',
        'declaration_compliance',
        'declaration_fee',
        'terms_consent',
    ] as $field) {
        $templateHtml = $fillCheckboxValues($templateHtml, $field, farmer_text($field) === '1' ? ['1'] : []);
    }
    $templateHtml = str_replace(
        "      </div>\n    </div>\n\n  </div>\n</div>\n\n<footer>",
        "      </div>\n    </form>\n\n  </div>\n</div>\n\n<footer>",
        $templateHtml
    );

    echo $templateHtml;
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Apply as a FreshRoots Vendor | <?= e((string)$brand['name']) ?></title>
    <style>
        :root { --green:#00684f; --leaf:#2f7d32; --gold:#d99a00; --ink:#17211f; --muted:#667276; --line:#dfe8e5; --soft:#f4faf5; --gutter:clamp(18px,5vw,76px); }
        * { box-sizing:border-box; }
        body { margin:0; color:var(--ink); background:#fff; font-family:"DM Sans",system-ui,-apple-system,sans-serif; }
        a { color:var(--green); text-decoration:none; font-weight:900; }
        .farm-reg { min-height:100vh; background:linear-gradient(180deg,#f4faf5 0,#fff 360px); }
        .farm-reg__header { width:min(1180px,100% - var(--gutter)); margin:0 auto; padding:24px 0 14px; display:flex; align-items:center; justify-content:space-between; gap:16px; }
        .farm-reg__brand img { max-width:170px; max-height:62px; object-fit:contain; display:block; }
        .farm-reg__nav { display:flex; gap:10px; flex-wrap:wrap; }
        .farm-reg__nav a { min-height:42px; border:1px solid var(--line); border-radius:8px; padding:0 14px; display:inline-flex; align-items:center; background:#fff; }
        .farm-reg__nav a.is-active { background:var(--green); color:#fff; border-color:var(--green); }
        .farm-reg__hero { width:min(1180px,100% - var(--gutter)); margin:0 auto; padding:34px 0 22px; display:grid; grid-template-columns:minmax(0,1fr) minmax(300px,.58fr); gap:28px; align-items:center; }
        .farm-reg__eyebrow { margin:0 0 10px; color:var(--green); font-weight:950; text-transform:uppercase; font-size:13px; }
        h1 { margin:0; font-size:clamp(38px,6vw,72px); line-height:1; letter-spacing:0; }
        .farm-reg__hero p { max-width:760px; margin:18px 0 0; color:var(--muted); font-size:18px; line-height:1.6; }
        .farm-reg__photo { min-height:310px; border-radius:10px; overflow:hidden; box-shadow:0 20px 50px rgba(17,24,39,.12); background:#dfe8e5; }
        .farm-reg__photo img { width:100%; height:100%; display:block; object-fit:cover; }
        .farm-reg__shell { width:min(1180px,100% - var(--gutter)); margin:0 auto 60px; display:grid; grid-template-columns:280px minmax(0,1fr); gap:22px; align-items:start; }
        .farm-reg__aside, .farm-reg__form { border:1px solid var(--line); border-radius:10px; background:#fff; box-shadow:0 16px 42px rgba(17,24,39,.06); }
        .farm-reg__aside { position:sticky; top:18px; padding:20px; display:grid; gap:12px; }
        .farm-reg__aside strong { font-size:18px; }
        .farm-reg__aside span { color:var(--muted); line-height:1.45; }
        .farm-reg__form { padding:clamp(20px,4vw,34px); }
        .farm-reg__alerts { display:grid; gap:10px; margin-bottom:18px; }
        .farm-reg__alert { padding:12px 14px; border-radius:8px; background:#fff1f2; border:1px solid #fecaca; color:#9f1239; font-weight:800; }
        .farm-reg__alert.is-info { background:#ecfdf3; border-color:#bbf7d0; color:#14532d; }
        .farm-section { padding:24px 0; border-top:1px solid var(--line); }
        .farm-section:first-of-type { border-top:0; padding-top:0; }
        .farm-section h2 { margin:0 0 6px; font-size:clamp(24px,3vw,34px); }
        .farm-section p { margin:0 0 18px; color:var(--muted); line-height:1.55; }
        .farm-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
        label { display:grid; gap:8px; color:#34413e; font-size:14px; font-weight:900; }
        label.is-full { grid-column:1/-1; }
        input, select, textarea { width:100%; min-height:54px; border:1px solid var(--line); border-radius:8px; background:#f8faf9; color:#17211f; padding:0 14px; font:700 15px/1.35 inherit; outline:0; }
        textarea { min-height:118px; padding-top:14px; resize:vertical; }
        input[type="file"] { padding:14px; }
        input:focus, select:focus, textarea:focus { border-color:var(--green); background:#fff; box-shadow:0 0 0 4px rgba(0,104,79,.1); }
        .farm-checks { grid-column:1/-1; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .farm-checks label { min-height:42px; border:1px solid var(--line); border-radius:8px; padding:10px 12px; display:flex; gap:9px; align-items:center; background:#fbfdfc; font-weight:800; }
        .farm-checks input { width:18px; min-height:18px; accent-color:var(--green); }
        .farm-submit { width:100%; min-height:62px; border:0; border-radius:8px; background:var(--green); color:#fff; font-weight:950; font-size:17px; cursor:pointer; box-shadow:0 15px 34px rgba(0,104,79,.2); }
        @media (max-width:900px) { .farm-reg__hero, .farm-reg__shell { grid-template-columns:1fr; } .farm-reg__aside { position:static; } }
        @media (max-width:620px) { .farm-reg__header { align-items:flex-start; flex-direction:column; } .farm-grid, .farm-checks { grid-template-columns:1fr; } .farm-reg__photo { min-height:220px; } }
    </style>
</head>
<body>
<main class="farm-reg">
    <header class="farm-reg__header">
        <a class="farm-reg__brand" href="<?= e(app_url('')) ?>"><?php if (!empty($brand['logo'])): ?><img src="<?= e((string)$brand['logo']) ?>" alt="<?= e((string)$brand['name']) ?>"><?php else: ?><strong><?= e((string)$brand['name']) ?></strong><?php endif; ?></a>
        <nav class="farm-reg__nav" aria-label="Application types">
            <a href="<?= e(app_url('vendor/register')) ?>">Apply as Vendor</a>
            <a class="is-active" href="<?= e(app_url('farmer/register')) ?>">Apply as Farmer</a>
        </nav>
    </header>

    <section class="farm-reg__hero">
        <div>
            <p class="farm-reg__eyebrow">FreshRoots by Seller Africa</p>
            <h1>Become a FreshRoots Vendor</h1>
            <p>You grow it. You list it. You fulfill it. Join FreshRoots by Seller Africa and put your fresh produce in front of customers looking to buy directly from American farmers.</p>
        </div>
        <div class="farm-reg__photo" aria-hidden="true"><img src="<?= e(app_url('assets/images/farmers.jpeg')) ?>" alt=""></div>
    </section>

    <div class="farm-reg__shell">
        <aside class="farm-reg__aside">
            <strong>Know Your Farmer. Know Your Food.</strong>
            <span>Seller Africa provides the marketplace. You manage your farm, listings, inventory, packaging, and fulfillment.</span>
            <span>Applications are reviewed before FreshRoots store activation.</span>
            <?php if ($isUpgradeApplication): ?><span><strong>Account upgrade:</strong> keep using your current login after this application is submitted.</span><?php endif; ?>
            <a href="<?= e(app_url('freshroots')) ?>">View FreshRoots page</a>
        </aside>

        <form class="farm-reg__form" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <?php if ($upgradeNotice !== '' || $toasts !== []): ?><div class="farm-reg__alerts"><?php if ($upgradeNotice !== ''): ?><div class="farm-reg__alert is-info"><?= e($upgradeNotice) ?></div><?php endif; ?><?php foreach ($toasts as $toast): ?><div class="farm-reg__alert"><?= e((string)($toast['message'] ?? '')) ?></div><?php endforeach; ?></div><?php endif; ?>

            <section class="farm-section">
                <h2>Farm & Owner Information</h2>
                <p>Tell us who you are and where your farm operates.</p>
                <div class="farm-grid">
                    <label>Farm/Business Name *<input name="farm_name" required value="<?= e(farmer_text('farm_name')) ?>"></label>
                    <label>Owner/Farmer Full Name *<input name="owner_name" required value="<?= e(farmer_text('owner_name')) ?>"></label>
                    <label>Business Email *<input type="email" name="email" required value="<?= e(farmer_text('email')) ?>"></label>
                    <label>Phone Number *<input type="tel" inputmode="tel" name="phone" required value="<?= e(farmer_text('phone')) ?>"></label>
                    <label class="is-full">Farm Address *<input name="address_line1" required value="<?= e(farmer_text('address_line1')) ?>"></label>
                    <label>City *<input name="city" required value="<?= e(farmer_text('city')) ?>"></label>
                    <label>State *<select name="state" required><option value="">Select State</option><?php foreach ($stateOptions as $state): ?><option value="<?= e($state) ?>"<?= $selected('state', $state) ?>><?= e($state) ?></option><?php endforeach; ?></select></label>
                    <label>ZIP Code *<input name="postcode" required value="<?= e(farmer_text('postcode')) ?>"></label>
                    <label>Website<input name="farm_website" value="<?= e(farmer_text('farm_website')) ?>"></label>
                    <label>Social Media<input name="social_page" value="<?= e(farmer_text('social_page')) ?>"></label>
                    <label>Is your farm Black-owned?<select name="black_owned"><option value="">Choose</option><option<?= $selected('black_owned', 'Yes') ?>>Yes</option><option<?= $selected('black_owned', 'No') ?>>No</option><option<?= $selected('black_owned', 'Prefer not to say') ?>>Prefer not to say</option></select></label>
                    <label class="is-full">Tell us briefly about your farm *<textarea name="description" required placeholder="What do you grow, how long have you been farming, and what makes your farm unique?"><?= e(farmer_text('description')) ?></textarea></label>
                    <?php if (!$isUpgradeApplication): ?>
                        <label>Password *<input type="password" name="password" required minlength="5" autocomplete="new-password"></label>
                        <label>Confirm Password *<input type="password" name="password_confirmation" required minlength="5" autocomplete="new-password"></label>
                    <?php else: ?>
                        <label class="is-full">Account Login<input value="You are signed in. No new password is needed for this FreshRoots upgrade." readonly></label>
                    <?php endif; ?>
                </div>
            </section>

            <section class="farm-section">
                <h2>What Do You Grow?</h2>
                <p>Select every category you would like to sell on FreshRoots.</p>
                <div class="farm-grid">
                    <div class="farm-checks"><?php foreach (['Fresh vegetables','Fresh fruits','Leafy greens','Fresh herbs','Root crops','African produce','Caribbean produce','Specialty produce','Grains','Other farm-grown produce'] as $item): ?><label><input type="checkbox" name="sell_categories[]" value="<?= e($item) ?>"<?= $checked('sell_categories', $item) ?>><?= e($item) ?></label><?php endforeach; ?></div>
                    <label class="is-full">List your main crops/products *<textarea name="main_crops" required><?= e(farmer_text('main_crops')) ?></textarea></label>
                    <label>Do you grow the produce you intend to list yourself? *<select name="grown_by_farmer" required><option value="">Choose</option><?php foreach (['Yes','Some products','No'] as $item): ?><option<?= $selected('grown_by_farmer', $item) ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
                    <label>If sourced from other farms, explain<textarea name="sourced_explanation"><?= e(farmer_text('sourced_explanation')) ?></textarea></label>
                    <label>When is your produce available? *<select name="produce_availability" required><option value="">Choose</option><?php foreach (['Available year-round','Seasonal','Currently harvesting','Coming soon'] as $item): ?><option<?= $selected('produce_availability', $item) ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
                    <label>Weekly produce capacity<input name="weekly_capacity" value="<?= e(farmer_text('weekly_capacity')) ?>"></label>
                    <div class="farm-checks"><?php foreach (['Households','Restaurants','Grocery stores','Food businesses','Wholesale buyers','All of the above'] as $item): ?><label><input type="checkbox" name="buyer_types[]" value="<?= e($item) ?>"<?= $checked('buyer_types', $item) ?>><?= e($item) ?></label><?php endforeach; ?></div>
                </div>
            </section>

            <section class="farm-section">
                <h2>Farming Practices</h2>
                <div class="farm-grid">
                    <div class="farm-checks"><?php foreach (['Conventional','Organic','USDA Certified Organic','Hydroponic','Greenhouse grown','Regenerative agriculture','Other'] as $item): ?><label><input type="checkbox" name="growing_methods[]" value="<?= e($item) ?>"<?= $checked('growing_methods', $item) ?>><?= e($item) ?></label><?php endforeach; ?></div>
                    <label>Agricultural licenses, registrations or certifications?<select name="has_licenses"><option value="">Choose</option><?php foreach (['Yes','No','Not applicable / Unsure'] as $item): ?><option<?= $selected('has_licenses', $item) ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
                    <label>If yes, list them<textarea name="license_details"><?= e(farmer_text('license_details')) ?></textarea></label>
                    <label>Upload applicable certifications or licenses<input type="file" name="certification_file" accept="application/pdf,image/jpeg,image/png,image/webp"></label>
                    <label>Upload 2-5 recent photographs of your farm and produce *<input type="file" name="farm_photos[]" required multiple accept="image/jpeg,image/png,image/webp"></label>
                </div>
            </section>

            <section class="farm-section">
                <h2>How Will You Fulfill Orders?</h2>
                <div class="farm-grid">
                    <div class="farm-checks"><?php foreach (['Nationwide shipping','Regional shipping','Local delivery','Farm pickup','Farmers market pickup'] as $item): ?><label><input type="checkbox" name="fulfillment_options[]" value="<?= e($item) ?>"<?= $checked('fulfillment_options', $item) ?>><?= e($item) ?></label><?php endforeach; ?></div>
                    <label class="is-full">Which states or areas can you currently serve? *<textarea name="service_areas" required><?= e(farmer_text('service_areas')) ?></textarea></label>
                    <label>Delivery radius, if local<input type="number" min="0" name="delivery_radius" value="<?= e(farmer_text('delivery_radius')) ?>"></label>
                    <div class="farm-checks"><?php foreach (['USPS','UPS','FedEx','Other','I do not currently ship'] as $item): ?><label><input type="checkbox" name="shipping_carriers[]" value="<?= e($item) ?>"<?= $checked('shipping_carriers', $item) ?>><?= e($item) ?></label><?php endforeach; ?></div>
                    <label>Can you package fresh produce appropriately? *<select name="packaging_ready" required><option value="">Choose</option><option<?= $selected('packaging_ready', 'Yes') ?>>Yes</option><option<?= $selected('packaging_ready', 'No') ?>>No</option></select></label>
                    <label>How quickly can you fulfill an order? *<select name="fulfillment_speed" required><option value="">Choose</option><?php foreach (['Same day','Next day','1-2 business days','2-3 business days','Based on harvest schedule'] as $item): ?><option<?= $selected('fulfillment_speed', $item) ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
                    <label>Do you have a minimum order?<select name="minimum_order"><option value="">Choose</option><option<?= $selected('minimum_order', 'No') ?>>No</option><option<?= $selected('minimum_order', 'Yes') ?>>Yes</option></select></label>
                    <label>Minimum order amount<input type="number" min="0" step="0.01" name="minimum_order_amount" value="<?= e(farmer_text('minimum_order_amount')) ?>"></label>
                    <label>Who pays shipping/delivery charges?<select name="shipping_charges_paid_by"><option value="">Choose</option><?php foreach (['Customer','Included in my product price','Depends on the order'] as $item): ?><option<?= $selected('shipping_charges_paid_by', $item) ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
                </div>
            </section>

            <section class="farm-section">
                <h2>Your Seller Africa Store</h2>
                <div class="farm-grid">
                    <?php foreach (['sells_online' => 'Do you currently sell produce online?', 'sells_farmers_markets' => 'Do you currently sell at farmers markets?', 'updates_inventory' => 'Can you regularly update inventory?', 'updates_sold_out' => 'Can you update sold out products?', 'fulfills_timeframe' => 'Can you fulfill within the store timeframe?'] as $field => $label): ?>
                        <label><?= e($label) ?> *<select name="<?= e($field) ?>" required><option value="">Choose</option><option<?= $selected($field, 'Yes') ?>>Yes</option><option<?= $selected($field, 'No') ?>>No</option></select></label>
                    <?php endforeach; ?>
                    <label class="is-full">If you sell online, where?<input name="online_sales_where" value="<?= e(farmer_text('online_sales_where')) ?>"></label>
                </div>
            </section>

            <section class="farm-section">
                <h2>Marketplace Responsibilities</h2>
                <p>FreshRoots vendors are responsible for listings, pricing, inventory, product quality, origin claims, packaging, shipping or delivery, fulfillment times, tracking, and compliance with applicable food, agricultural, labeling, licensing and legal requirements.</p>
                <div class="farm-checks">
                    <?php foreach ([
                        'declaration_accurate' => 'I confirm that the information provided is accurate.',
                        'declaration_independent' => 'I understand that I operate as an independent vendor.',
                        'declaration_products' => 'I understand that I am responsible for the products I list and sell.',
                        'declaration_fulfillment' => 'I understand that I am responsible for packaging and fulfilling orders.',
                        'declaration_origin' => 'I agree to accurately disclose where my products are grown or sourced.',
                        'declaration_claims' => 'I agree not to make false or misleading claims.',
                        'declaration_endorsement' => 'I understand acceptance does not constitute certification or inspection.',
                        'declaration_compliance' => 'I agree to comply with federal, state and local requirements.',
                        'declaration_fee' => 'I understand Seller Africa deducts applicable platform fees.',
                        'terms_consent' => 'I agree to Seller Africa Vendor Terms, FreshRoots policy, and fee schedule.',
                    ] as $field => $label): ?><label><input type="checkbox" name="<?= e($field) ?>" value="1" required <?= farmer_text($field) === '1' ? 'checked' : '' ?>><?= e($label) ?></label><?php endforeach; ?>
                </div>
                <div class="farm-grid" style="margin-top:16px">
                    <label>Full Legal Name *<input name="signature_name" required value="<?= e(farmer_text('signature_name')) ?>"></label>
                    <label>Signature *<input name="signature" required value="<?= e(farmer_text('signature')) ?>"></label>
                    <label>Date *<input type="date" name="signature_date" required readonly aria-readonly="true" value="<?= e(farmer_text('signature_date') ?: app_date('Y-m-d')) ?>"></label>
                    <label class="is-full"><button class="farm-submit" type="submit">Submit Farmer Application</button></label>
                </div>
            </section>
        </form>
    </div>
</main>
</body>
</html>
