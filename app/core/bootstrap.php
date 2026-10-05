<?php
/**
 * File Folder Path: /app/core
 * File Path: /app/core/bootstrap.php
 * Designed by Daniel Pybexai Framework
 * ==============================================================================
 * MULTIVENDOR E-COMMERCE & ERP CORE BOOTSTRAP
 * Summary: PHP 8.2+ Stateless Entry Point. Handles environment loading, 
 * global helpers, session hardening, and service-only autoloading.
 * Architecture V2: No Controllers/Models. Strict mapping to app/services/.
 */

declare(strict_types=1);

// 1. Setup Constants
define('APP_ROOT', dirname(__DIR__, 2));
define('CONFIG_PATH', APP_ROOT . '/app/config');
define('LOG_PATH', APP_ROOT . '/storage/logs');
define('VIEW_PATH', APP_ROOT . '/app/views');

// Load Environment Variables manually (bypassing autoloader since it's core)
$envLoader = APP_ROOT . '/app/core/Env.php';
if (file_exists($envLoader)) {
    require_once $envLoader;
    if (class_exists('\App\Core\Env')) {
        \App\Core\Env::load(APP_ROOT . '/.env');
    }
}

$securityService = APP_ROOT . '/app/services/SecurityService.php';
if (file_exists($securityService)) {
    require_once $securityService;
}

/**
 * 2. URL Configuration
 */
define('BASE_URL', rtrim($_ENV['APP_URL'] ?? '/public/', '/') . '/');
if (isset($_GET['sa_debug_env'])) {
    echo '<pre>';
    echo 'ENV APP_URL: ' . var_export($_ENV['APP_URL'] ?? 'NOT SET', true) . "\n";
    echo 'SERVER APP_URL: ' . var_export($_SERVER['APP_URL'] ?? 'NOT SET', true) . "\n";
    echo 'getenv APP_URL: ' . var_export(getenv('APP_URL'), true) . "\n";
    echo 'BASE_URL constant: ' . var_export(BASE_URL, true) . "\n";
    echo '</pre>';
    exit;
}

// 3. Environment & Error Handling
error_reporting(E_ALL);
ini_set('display_errors', class_exists('\App\SecurityService') && \App\SecurityService::debugEnabled() && !\App\SecurityService::isProduction() ? '1' : '0');
ini_set('log_errors', '1');
if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0777, true);
}
ini_set('error_log', LOG_PATH . '/php_errors.log');

if (class_exists('\App\SecurityService')) {
    \App\SecurityService::forceHttpsRedirect();
    \App\SecurityService::sendSecurityHeaders();
}

// 4. Autoloaders
$composerAutoload = APP_ROOT . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// Ensure TimeService exists before attempting to boot
$timeService = APP_ROOT . '/app/services/TimeService.php';
if (file_exists($timeService)) {
    require_once $timeService;
    if (class_exists('\App\TimeService')) {
        \App\TimeService::boot($_ENV['APP_TIMEZONE'] ?? 'Africa/Lagos');
    }
}

/**
 * THE V2 SERVICE AUTOLOADER
 * Maps the \App\ namespace directly to the /app/services/ folder.
 * Enforces the removal of Models, Controllers, and Middleware.
 */
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = APP_ROOT . '/app/services/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// 5. Global Helper Functions
function app_timezone(): string {
    return class_exists('\App\TimeService') ? \App\TimeService::timezone() : 'Africa/Lagos';
}

function app_now(): \DateTimeImmutable {
    return class_exists('\App\TimeService') ? \App\TimeService::now() : new \DateTimeImmutable();
}

function app_date(string $format = 'Y-m-d H:i:s', \DateTimeInterface|string|null $time = null): string {
    return class_exists('\App\TimeService') ? \App\TimeService::format($time, $format) : date($format);
}

function sql_now(): string {
    return class_exists('\App\TimeService') ? \App\TimeService::sqlNow() : date('Y-m-d H:i:s');
}

function app_env(): string {
    return class_exists('\App\SecurityService') ? \App\SecurityService::env() : strtolower((string)($_ENV['APP_ENV'] ?? 'production'));
}

function app_debug(): bool {
    return class_exists('\App\SecurityService') ? \App\SecurityService::debugEnabled() : filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
}

function app_is_https(): bool {
    return class_exists('\App\SecurityService') ? \App\SecurityService::isHttps() : (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string)$_SERVER['HTTPS']) !== 'off');
}

function client_ip(): string {
    return class_exists('\App\SecurityService') ? \App\SecurityService::clientIp() : (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Database Singleton Helper
 * Points to the connection initiated by your configuration
 */
function db() {
    static $db = null;
    if ($db === null) {
        $configFile = CONFIG_PATH . '/database.php';
        if (!file_exists($configFile)) {
            throw new \Exception("Database configuration missing at: $configFile");
        }
        $config = require $configFile;
        // Requires the Database service class to be created in app/services/Database.php
        $db = new \App\Database($config);
    }
    return $db;
}

function db_config(): array {
    return require CONFIG_PATH . '/database.php';
}

function app_url(string $path = ''): string {
    $cleanPath = clean_public_path($path);

    return BASE_URL . ltrim($cleanPath, '/');
}

function asset(string $path = ''): string {
    return app_url('assets/' . ltrim($path, '/'));
}

function template_asset(string $path = '', string $template = 'admin-template'): string {
    $cleanTemplate = trim(preg_replace('/[^a-z0-9_-]+/i', '', $template) ?? 'admin-template', '/');
    $templateRoot = APP_ROOT . '/public/' . $cleanTemplate . '/assets';

    if (is_dir($templateRoot)) {
        return app_url($cleanTemplate . '/assets/' . ltrim($path, '/'));
    }

    return asset($path);
}

function clean_public_path(string $path): string {
    $path = ltrim($path, '/');

    if ($path === '' || $path === 'index.php') {
        return '';
    }

    if ($path === 'dashboard.php') {
        return 'dashboard';
    }

    if (preg_match('/^dashboard\.php\?view=([a-z0-9-]+)$/', $path, $matches)) {
        return $matches[1] === 'overview' ? 'dashboard' : 'dashboard/' . $matches[1];
    }

    if ($path === 'store' || str_starts_with($path, 'store?')) {
        return 'home' . substr($path, 5);
    }

    return match ($path) {
        'login.php' => 'login',
        'register.php' => 'register',
        'verify-email.php' => 'verify-email',
        'forgot_password.php' => 'forgot-password',
        'reset_password.php' => 'reset-password',
        'onboarding.php' => 'onboarding',
        'storefront/index.php' => 'home',
        'storefront/shop.php' => 'shop',
        'vendor/index.php' => 'vendor',
        'vendor/register.php' => 'vendor/register',
        'affiliate/join.php' => 'affiliate/join',
        'vendor/products.php' => 'vendor/products',
        'vendor/inventory.php' => 'vendor/inventory',
        'vendor/orders.php' => 'vendor/orders',
        'vendor/chats.php' => 'vendor/chats',
        'vendor/tracking.php' => 'vendor/tracking',
        'vendor/customers.php' => 'vendor/customers',
        'vendor/reviews.php' => 'vendor/reviews',
        'vendor/payouts.php' => 'vendor/payouts',
        'vendor/kyc.php' => 'vendor/kyc',
        'vendor/settings.php' => 'vendor/settings',
        'buyer/index.php' => 'buyer',
        'buyer/orders.php' => 'buyer/orders',
        'buyer/chats.php' => 'buyer/chats',
        'buyer/wishlist.php' => 'buyer/wishlist',
        'buyer/addresses.php' => 'buyer/addresses',
        'buyer/profile.php' => 'buyer/profile',
        default => $path,
    };
}

/**
 * Redirect with forced session commit
 */
function redirect(string $path): void
{
    $target = (
        str_starts_with($path, 'http://') ||
        str_starts_with($path, 'https://') ||
        str_starts_with($path, '/')
    )
        ? $path
        : app_url(ltrim($path, '/'));

    if (isset($_GET['sa_debug_redirect'])) {
        echo '<pre>INPUT PATH: ' . htmlspecialchars($path) . "\nCOMPUTED TARGET: " . htmlspecialchars($target) . '</pre>';
        exit;
    }

    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

    error_log("\n================ REDIRECT ================");
    error_log("URI: " . ($_SERVER['REQUEST_URI'] ?? ''));
    error_log("TARGET: " . $target);

    foreach ($trace as $i => $frame) {
        error_log(sprintf(
            "#%d %s:%s %s",
            $i,
            $frame['file'] ?? '',
            $frame['line'] ?? '',
            $frame['function'] ?? ''
        ));
    }

    session_write_close();
    header("Location: " . $target, true, 302);
    exit;
}
/**
 * Centralized Audit Logging
 */
function audit(string $action, ?string $resourceType = null, ?string $resourceId = null, array $old = [], array $new = [], ?int $actorId = null): void {
    try {
        db()->query(
            "INSERT INTO audit_logs 
            (actor_user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $actorId ?? ($_SESSION['user_id'] ?? null),
                $action,
                $resourceType ?? 'system',
                $resourceId !== null ? (int)$resourceId : null,
                $old === [] ? null : json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $new === [] ? null : json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                client_ip(),
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]
        );
    } catch (\Throwable $e) {
        error_log("Audit Logging Failed: " . $e->getMessage());
    }
}

function audit_detail_text(?string $json): string {
    if (!$json) {
        return '-';
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return '-';
    }

    $labels = [
        'failure_detail' => 'Detailed Reason',
        'reason_summary' => 'Reason Summary',
        'reason' => 'Reason',
        'error' => 'Error',
        'errors' => 'Form Errors',
        'reasons' => 'Form Errors',
        'internal_reasons' => 'Internal Reasons',
        'email' => 'Email',
        'identifier' => 'Identifier',
        'account_type' => 'Account Type',
        'status' => 'Status',
        'locked_until' => 'Locked Until',
        'next_step' => 'Next Step',
    ];
    $parts = [];

    foreach ($labels as $key => $label) {
        if (!array_key_exists($key, $data) || $data[$key] === '' || $data[$key] === null) {
            continue;
        }

        $value = $data[$key];
        if (is_array($value)) {
            $value = implode('; ', array_map(
                static fn ($item): string => is_scalar($item) ? (string)$item : json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $value
            ));
        } elseif (!is_scalar($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $parts[] = $label . ': ' . (string)$value;
    }

    return $parts !== [] ? implode(' | ', $parts) : '-';
}

function app_user_is_farmer_vendor(?int $userId = null): bool {
    $userId = $userId ?? (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0 || !table_exists('vendor_applications')) {
        return false;
    }

    try {
        return (bool)db()->fetch(
            'SELECT va.id
             FROM vendor_applications va
             INNER JOIN vendors v ON v.id = va.vendor_id
             WHERE va.application_type = "farmer" AND v.user_id = ?
             LIMIT 1',
            [$userId]
        );
    } catch (\Throwable) {
        return false;
    }
}

function app_dashboard_url(): string {
    $roles = array_map('strval', (array)($_SESSION['roles'] ?? []));
    if (in_array('super_admin', $roles, true) || in_array('admin', $roles, true)) {
        return app_url('dashboard');
    }
    if (in_array('vendor', $roles, true)) {
        if (app_user_is_farmer_vendor()) {
            return app_url('farmer');
        }
        return app_url('vendor');
    }
    if (in_array('affiliate', $roles, true)) {
        return app_url('affiliate');
    }
    if (isset($_SESSION['user_id'])) {
        return app_url('buyer');
    }

    return app_url('login');
}

function render_restricted_page(int $statusCode = 404, string $title = '', string $message = '', array $context = []): void {
    $statusCode = in_array($statusCode, [403, 404], true) ? $statusCode : 404;
    $title = trim($title) !== '' ? $title : ($statusCode === 403 ? 'Access needed' : 'This link may be broken');
    $message = trim($message) !== ''
        ? $message
        : ($statusCode === 403
            ? 'This area needs the right account access. Please log in with the correct account, go home, or open your dashboard.'
            : 'The link you opened may be broken or the page may have moved. We have sent this to our technical team to review.');
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $reason = (string)($context['reason'] ?? '');
    $shouldAudit = ($context['audit'] ?? true) !== false && $reason !== 'web_server_error_document';

    if ($shouldAudit) {
        try {
            audit(
                $statusCode === 403 ? 'restricted_page_viewed' : 'missing_page_viewed',
                'system',
                null,
                [],
                [
                    'status_code' => $statusCode,
                    'path' => $requestUri,
                    'route' => (string)($context['route'] ?? ''),
                    'reason' => $reason,
                    'next_step' => $statusCode === 403
                        ? 'Ask the user to log in with the right account, go home, or open their dashboard.'
                        : 'Review the broken link or missing route and guide the user to the correct page.',
                ]
            );
        } catch (\Throwable $e) {
            error_log('Restricted page audit failed: ' . $e->getMessage());
        }
    }

    http_response_code($statusCode);
    $brand = app_branding();
    $homeUrl = app_url('');
    $dashboardUrl = app_dashboard_url();
    $dashboardText = isset($_SESSION['user_id']) ? 'Go to Dashboard' : 'Login to Dashboard';

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e($title . ' | ' . (string)$brand['name']) . '</title>'
        . '<style>'
        . 'body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f6f8f7;color:#17211f;font-family:"DM Sans",system-ui,-apple-system,sans-serif;padding:24px}'
        . '.error-shell{width:min(780px,100%);text-align:center;background:#fff;border:1px solid #dfe8e5;border-radius:18px;padding:clamp(30px,6vw,64px);box-shadow:0 24px 70px rgba(17,24,39,.1)}'
        . '.brand{display:inline-flex;align-items:center;justify-content:center;margin-bottom:28px}.brand img{max-width:180px;max-height:64px;object-fit:contain}.code{color:#00684f;font-size:clamp(58px,12vw,112px);font-weight:950;line-height:.9;margin:0 0 12px}.eyebrow{margin:0 0 10px;color:#00684f;font-weight:900;text-transform:uppercase;font-size:13px}.error-shell h1{margin:0;color:#111827;font-size:clamp(32px,5vw,52px);line-height:1.02}.error-shell p{max-width:600px;margin:18px auto 0;color:#657276;font-size:18px;line-height:1.65}.actions{display:flex;flex-wrap:wrap;gap:12px;justify-content:center;margin-top:30px}.btn{min-height:48px;border-radius:8px;padding:0 20px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-weight:900}.btn.primary{background:#00684f;color:#fff}.btn.secondary{border:1px solid #00684f;color:#00684f;background:#fff}@media(max-width:520px){.actions{display:grid}.btn{width:100%}}'
        . '</style></head><body><main class="error-shell">';
    if (!empty($brand['logo'])) {
        echo '<a class="brand" href="' . e($homeUrl) . '"><img src="' . e((string)$brand['logo']) . '" alt="' . e((string)$brand['name']) . '"></a>';
    }
    echo '<p class="code">' . e((string)$statusCode) . '</p>'
        . '<p class="eyebrow">' . e($statusCode === 403 ? 'Access Required' : 'Broken Link') . '</p>'
        . '<h1>' . e($title) . '</h1>'
        . '<p>' . e($message) . '</p>'
        . '<div class="actions"><a class="btn primary" href="' . e($homeUrl) . '">Go to Home</a><a class="btn secondary" href="' . e($dashboardUrl) . '">' . e($dashboardText) . '</a></div>'
        . '</main>' . app_chat_widget_embed() . '</body></html>';
}

function flash(string $type, string $message): void {
    $_SESSION['toasts'][] = ['type' => $type, 'message' => $message];
}

function consume_toasts(): array {
    $toasts = $_SESSION['toasts'] ?? [];
    unset($_SESSION['toasts']);

    return $toasts;
}

function table_exists(string $table): bool {
    try {
        $config = db_config();
        $row = db()->fetch(
            "SELECT COUNT(*) AS total
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            [(string)($config['database'] ?? ''), $table]
        );

        return (int)($row['total'] ?? 0) > 0;
    } catch (\Throwable) {
        return false;
    }
}

function app_setting(string $scope, string $key, mixed $default = null): mixed {
    static $cache = [];

    $cacheKey = $scope . ':' . $key;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        if (!table_exists('settings')) {
            return $cache[$cacheKey] = $default;
        }

        $row = db()->fetch(
            "SELECT setting_value FROM settings WHERE scope = ? AND scope_id = 0 AND setting_key = ? LIMIT 1",
            [$scope, $key]
        );

        if (!$row) {
            return $cache[$cacheKey] = $default;
        }

        $decoded = json_decode((string)($row['setting_value'] ?? ''), true);

        return $cache[$cacheKey] = is_scalar($decoded) || $decoded === null
            ? ($decoded ?? $default)
            : $default;
    } catch (\Throwable) {
        return $cache[$cacheKey] = $default;
    }
}

function app_marketing_pixel_code(string $placement): string {
    $placement = $placement === 'body' ? 'body' : 'head';
    if ((string)app_setting('marketing_pixels', 'pixels_enabled', 'off') !== 'on') {
        return '';
    }

    $keys = $placement === 'head'
        ? ['google_head_code', 'facebook_head_code']
        : ['google_body_code', 'facebook_body_code'];

    $snippets = [];
    foreach ($keys as $key) {
        $snippet = trim((string)app_setting('marketing_pixels', $key, ''));
        if ($snippet === '') {
            continue;
        }

        $snippets[] = str_replace(["\0", '<?', '?>'], '', $snippet);
    }

    return $snippets === []
        ? ''
        : "\n<!-- Marketing pixels -->\n" . implode("\n", $snippets) . "\n<!-- /Marketing pixels -->\n";
}

function app_brand_asset_url(?string $path = null): string {
    $path = trim((string)$path);
    if ($path === '') {
        return app_url('storefront/assets/img/logo/black-logo.png');
    }

    if (preg_match_all('/https?:\/\/[^\s"\']+/i', $path, $matches) > 0) {
        $path = end($matches[0]) ?: $path;
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        $urlPath = (string)(parse_url($path, PHP_URL_PATH) ?? '');
        $basePath = trim((string)(parse_url(BASE_URL, PHP_URL_PATH) ?? ''), '/');
        $localPathPattern = $basePath !== ''
            ? '#^/' . preg_quote($basePath, '#') . '/((?:public/)?uploads/.+)$#'
            : '#^/((?:public/)?uploads/.+)$#';
        if (preg_match($localPathPattern, $urlPath, $matches) === 1) {
            return app_url(preg_replace('#^public/#', '', $matches[1]) ?? $matches[1]);
        }

        $localAssetPattern = $basePath !== ''
            ? '#^/' . preg_quote($basePath, '#') . '/public/(assets/.+)$#'
            : '#^/public/(assets/.+)$#';
        if (preg_match($localAssetPattern, $urlPath, $matches) === 1) {
            return app_url($matches[1]);
        }

        return $path;
    }

    $path = preg_replace('#^/?public/#', '', $path) ?? $path;

    return app_url(ltrim($path, '/'));
}

function app_branding(): array {
    $name = trim((string)app_setting('general', 'site_name', 'Seller Africa'));
    $logo = trim((string)app_setting('general', 'logo_path', ''));

    return [
        'name' => $name !== '' ? $name : 'Seller Africa',
        'logo' => app_brand_asset_url($logo),
        'logo_path' => $logo,
    ];
}

function app_chat_widget_embed(): string {
    static $rendered = false;

    if ($rendered) {
        return '';
    }

    $rendered = true;

    return "<script src=\"https://cdn.jotfor.ms/agent/embedjs/019d9e0af6e977eba106b195521ca074d2b8/embed.js\" defer></script>\n";
}

function app_render_chat_widget(): void {
    echo app_chat_widget_embed();
}

function install_schema_if_needed(): void {
    $requiredTables = ['users', 'roles', 'user_roles', 'audit_logs', 'settings'];
    $missingTables = array_values(array_filter($requiredTables, static fn (string $table): bool => !table_exists($table)));

    if ($missingTables === []) {
        return;
    }

    if (table_exists('users')) {
        throw new \RuntimeException('Database schema is partially installed. Missing tables: ' . implode(', ', $missingTables));
    }

    $schemaPath = APP_ROOT . '/migrations/001_initial_schema.sql';
    if (!is_file($schemaPath)) {
        throw new \RuntimeException('Initial schema file is missing.');
    }

    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        throw new \RuntimeException('Unable to read initial schema file.');
    }

    db()->pdo()->exec($sql);
}

function super_admin_exists(): bool {
    if (!table_exists('users') || !table_exists('roles') || !table_exists('user_roles')) {
        return false;
    }

    $row = db()->fetch(
        "SELECT COUNT(*) AS total
         FROM users u
         INNER JOIN user_roles ur ON ur.user_id = u.id
         INNER JOIN roles r ON r.id = ur.role_id
         WHERE r.code = 'super_admin' AND u.status <> 'deleted'"
    );

    return (int)($row['total'] ?? 0) > 0;
}

function current_user(): ?array {
    return class_exists('\App\AuthService') ? \App\AuthService::user() : null;
}

function require_auth(): void {
    if (!class_exists('\App\AuthService') || !\App\AuthService::check()) {
        flash('warning', 'Please sign in to continue.');
        redirect('login.php');
    }
}

function require_super_admin(): void {
    require_auth();

    if (!\App\AuthService::hasRole('super_admin')) {
        http_response_code(403);
        die('Forbidden');
    }
}

function render(string $template, array $data = []): void {
    extract($data, EXTR_SKIP);
    require VIEW_PATH . '/' . ltrim($template, '/');
}

function render_layout(string $layout, string $template, array $data = []): void {
    $layoutPath = VIEW_PATH . '/layouts/' . ltrim($layout, '/') . '.php';
    $templatePath = VIEW_PATH . '/' . ltrim($template, '/');

    if (!is_file($layoutPath)) {
        throw new \RuntimeException('Layout not found: ' . $layout);
    }

    if (!is_file($templatePath)) {
        throw new \RuntimeException('View not found: ' . $template);
    }

    extract($data, EXTR_SKIP);
    ob_start();
    require $templatePath;
    $content = ob_get_clean();

    require $layoutPath;
}

// 6. Start Secure Session & CSRF
function app_prepare_session_storage(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $configuredPath = trim((string)ini_get('session.save_path'));
    $candidatePath = $configuredPath;

    if ($candidatePath !== '' && str_contains($candidatePath, ';')) {
        $parts = explode(';', $candidatePath);
        $candidatePath = (string)end($parts);
    }

    if ($candidatePath === '' || !is_dir($candidatePath) || !is_writable($candidatePath)) {
        $fallbackPath = APP_ROOT . '/storage/sessions';
        if (!is_dir($fallbackPath)) {
            @mkdir($fallbackPath, 0777, true);
        }
        if (is_dir($fallbackPath) && is_writable($fallbackPath)) {
            session_save_path($fallbackPath);
        }
    }
}

function getCsrfToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        app_prepare_session_storage();
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Session Hardening
 */
$sessionLifetime = 60 * 60 * 24 * 30; // 30 days
ini_set('session.gc_maxlifetime', (string)$sessionLifetime);
session_name('ERP_MARKET_NODE'); // Unique session namespace to prevent conflicts
session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path' => '/',
    'secure' => app_is_https(),
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    app_prepare_session_storage();
    session_start();
}

/**
 * Validates the CSRF token submitted via POST requests.
 * Halts execution immediately if the token is missing or invalid.
 */
function validateCsrf(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $submittedToken = $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        // hash_equals prevents timing attacks during string comparison
        if (empty($submittedToken) || !hash_equals($sessionToken, $submittedToken)) {
            http_response_code(403);
            die('Security Error: CSRF token validation failed. Please return to the previous page, refresh, and try again.');
        }
    }
}

/**
 * HTML Output Escaper for XSS Protection
 */
function e(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if (class_exists('\App\AffiliateService')) {
    \App\AffiliateService::captureRequest();
}

if (class_exists('\App\MaintenanceService')) {
    \App\MaintenanceService::enforce();
}

// ==========================================
// TELEMETRY & METADATA INJECTION
// ==========================================
$metadataPath = __DIR__ . '/metadata.php';
if (file_exists($metadataPath)) {
    require_once $metadataPath;
}
$analyticsPath = __DIR__ . '/analytics.php';
if (file_exists($analyticsPath)) {
    require_once $analyticsPath;
}
