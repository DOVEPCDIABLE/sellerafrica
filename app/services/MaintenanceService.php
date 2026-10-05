<?php

declare(strict_types=1);

namespace App;

final class MaintenanceService
{
    public static function status(): array
    {
        $enabled = self::setting('maintenance_mode', 'off') === 'on';
        $startsAt = self::setting('maintenance_starts_at', '');
        $endsAt = self::setting('maintenance_ends_at', '');
        $now = time();

        if ($startsAt !== '' && ($startTime = strtotime($startsAt)) !== false && $startTime > $now) {
            $enabled = false;
        }

        if ($endsAt !== '' && ($endTime = strtotime($endsAt)) !== false && $endTime <= $now) {
            $enabled = false;
        }

        return [
            'enabled' => $enabled,
            'started_at' => self::setting('maintenance_started_at', $startsAt !== '' ? $startsAt : \sql_now()),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'message' => self::setting('maintenance_message', 'We are making improvements and will be back shortly.'),
            'ip_whitelist' => self::csv(self::setting('maintenance_ip_whitelist', '')),
            'user_whitelist' => array_map('intval', self::csv(self::setting('maintenance_user_whitelist', ''))),
        ];
    }

    public static function enforce(): void
    {
        $status = self::status();
        if (empty($status['enabled']) || self::isAllowedPath()) {
            return;
        }

        if (!self::isStorefrontRequest() && self::canBypass($status)) {
            return;
        }

        if (self::isApiRequest()) {
            http_response_code(503);
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => false,
                'message' => (string)$status['message'],
                'maintenance' => [
                    'started_at' => $status['started_at'],
                    'ends_at' => $status['ends_at'],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        session_write_close();
        header('Retry-After: 900');
        header('Location: /maintenance', true, 302);
        exit;
    }

    public static function canBypass(array $status = []): bool
    {
        $roles = $_SESSION['roles'] ?? [];
        if (in_array('super_admin', $roles, true) || in_array('admin', $roles, true)) {
            return true;
        }

        $status = $status !== [] ? $status : self::status();
        if (in_array(\client_ip(), $status['ip_whitelist'] ?? [], true)) {
            return true;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        return $userId > 0 && in_array($userId, $status['user_whitelist'] ?? [], true);
    }

    public static function disable(): void
    {
        if (!\table_exists('settings')) {
            return;
        }

        \db()->query(
            "INSERT INTO settings (scope, scope_id, setting_key, setting_value)
             VALUES ('general', 0, 'maintenance_mode', ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP",
            [json_encode('off')]
        );
    }

    private static function setting(string $key, string $default = ''): string
    {
        return trim((string)\app_setting('general', $key, $default));
    }

    private static function csv(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $value) ?: []), static fn (string $item): bool => $item !== ''));
    }

    private static function isAllowedPath(): bool
    {
        $path = trim(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '', '/');
        $path = preg_replace('#^seller_africa/public/#', '', $path) ?? $path;
        $path = preg_replace('#^public/#', '', $path) ?? $path;

        return $path === 'maintenance'
            || $path === 'maintenance.php'
            || $path === 'login'
            || $path === 'login.php'
            || $path === 'logout'
            || $path === 'logout.php'
            || $path === 'visibility-boost'
            || $path === 'manage-store'
            || str_starts_with($path, 'dashboard')
            || $path === 'dashboard.php'
            || str_starts_with($path, 'api/webhooks/');
    }

    private static function isStorefrontRequest(): bool
    {
        $route = trim((string)($_GET['route'] ?? ''), '/');

        if ($route !== '' && self::isStorefrontRoute($route)) {
            return true;
        }

        $path = trim(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '', '/');
        $path = preg_replace('#^seller_africa/public/#', '', $path) ?? $path;
        $path = preg_replace('#^public/#', '', $path) ?? $path;

        return self::isStorefrontRoute($path);
    }

    private static function isStorefrontRoute(string $route): bool
    {
        $route = trim($route, '/');

        return $route === ''
            || $route === 'index.php'
            || $route === 'store'
            || $route === 'home'
            || $route === 'shop'
            || $route === 'marketplace'
            || $route === 'about'
            || $route === 'contact'
            || $route === 'affiliate-registration'
            || $route === 'become-a-vendor'
            || $route === 'farm-fresh'
            || $route === 'farms'
            || $route === 'farms-near-me'
            || $route === 'farmer/register'
            || $route === 'visibility-boost'
            || $route === 'manage-store'
            || $route === 'packages'
            || $route === 'knowledge-base'
            || $route === 'vendors'
            || $route === 'track-order'
            || $route === 'privacy-policy'
            || $route === 'terms'
            || str_starts_with($route, 'storefront')
            || str_starts_with($route, 'product/')
            || str_starts_with($route, 'api/cart/')
            || str_starts_with($route, 'api/checkout/')
            || str_starts_with($route, 'api/storefront/')
            || $route === 'api/payments/manage-store'
            || $route === 'api/payments/klasha/callback';
    }

    private static function isApiRequest(): bool
    {
        $path = (string)($_SERVER['REQUEST_URI'] ?? '');
        return str_contains($path, '/api/') || str_starts_with((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }
}
