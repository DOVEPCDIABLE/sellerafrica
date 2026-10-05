<?php

declare(strict_types=1);

namespace App;

final class SecurityService
{
    private const LOGIN_WINDOW_MINUTES = 15;
    private const LOGIN_LIMIT = 8;
    private const LOGIN_LOCK_MINUTES = 20;
    private const REGISTER_WINDOW_MINUTES = 30;
    private const REGISTER_LIMIT = 4;

    public static function env(): string
    {
        return strtolower(trim((string)($_ENV['APP_ENV'] ?? 'production')));
    }

    public static function debugEnabled(): bool
    {
        return filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public static function isProduction(): bool
    {
        return in_array(self::env(), ['prod', 'production'], true);
    }

    public static function isHttps(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }

        if (self::forwardedHeaderHas('HTTP_X_FORWARDED_PROTO', 'https')) {
            return true;
        }

        if (self::forwardedHeaderHas('HTTP_X_FORWARDED_SCHEME', 'https')
            || self::forwardedHeaderHas('HTTP_X_URL_SCHEME', 'https')
            || self::forwardedHeaderHas('HTTP_CLOUDFRONT_FORWARDED_PROTO', 'https')
            || self::forwardedHeaderHas('HTTP_X_FORWARDED_SSL', 'on')
            || self::forwardedHeaderHas('HTTP_FRONT_END_HTTPS', 'on')
            || self::forwardedHeaderHas('HTTP_X_FORWARDED_PORT', '443')
        ) {
            return true;
        }

        $forwarded = strtolower((string)($_SERVER['HTTP_FORWARDED'] ?? ''));
        if (preg_match('/(?:^|[;,]\s*)proto=https(?:[;,]|$)/', $forwarded) === 1) {
            return true;
        }

        return str_contains(strtolower((string)($_SERVER['HTTP_CF_VISITOR'] ?? '')), '"scheme":"https"');
    }

    private static function forwardedHeaderHas(string $header, string $expected): bool
    {
        $value = strtolower((string)($_SERVER[$header] ?? ''));
        if ($value === '') {
            return false;
        }

        $tokens = preg_split('/[\s,;]+/', $value) ?: [];
        return in_array(strtolower($expected), array_filter($tokens), true);
    }

    public static function clientIp(): string
    {
        $cloudflareIp = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if ($cloudflareIp !== '' && filter_var($cloudflareIp, FILTER_VALIDATE_IP)) {
            return $cloudflareIp;
        }

        $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    public static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        // Chrome also checks form-action when a submitted form redirects to hosted checkout.
        $csp = "default-src 'self'; base-uri 'self'; form-action 'self' https://checkout.paystack.com https://checkout.stripe.com; frame-ancestors 'self'; img-src 'self' data: https:; font-src 'self' data: https://fonts.gstatic.com; style-src 'self' 'unsafe-inline' https:; script-src 'self' 'unsafe-inline' https:; connect-src 'self' https:; frame-src 'self' https:; child-src 'self' https:; object-src 'none'";
        if (self::isHttps()) {
            $csp .= '; upgrade-insecure-requests';
        }
        header('Content-Security-Policy: ' . $csp);
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(self "https://js.stripe.com" "https://checkout.stripe.com" "https://js.klasha.com" "https://*.klasha.com")');
        header('X-Permitted-Cross-Domain-Policies: none');

        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }

    public static function shouldForceHttps(): bool
    {
        if (!self::isProduction()) {
            return false;
        }

        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '' || str_starts_with($host, 'localhost') || str_starts_with($host, '127.0.0.1')) {
            return false;
        }

        return !self::isHttps();
    }

    public static function forceHttpsRedirect(): void
    {
        if (!self::shouldForceHttps() || headers_sent()) {
            return;
        }

        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }

    public static function ensureSchema(): void
    {
        try {
            \db()->pdo()->exec("
                CREATE TABLE IF NOT EXISTS security_attempts (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    purpose VARCHAR(40) NOT NULL,
                    identifier_hash CHAR(64) NOT NULL,
                    ip_address VARCHAR(64) NOT NULL,
                    attempts INT UNSIGNED NOT NULL DEFAULT 0,
                    locked_until DATETIME NULL,
                    last_attempt_at DATETIME NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_security_attempts_scope (purpose, identifier_hash, ip_address),
                    INDEX idx_security_attempts_lock (purpose, locked_until),
                    INDEX idx_security_attempts_last (purpose, last_attempt_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            \db()->pdo()->exec("
                CREATE TABLE IF NOT EXISTS mfa_challenges (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT UNSIGNED NOT NULL,
                    code_hash CHAR(64) NOT NULL,
                    ip_address VARCHAR(64) NULL,
                    user_agent VARCHAR(500) NULL,
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_mfa_challenges_user (user_id, used_at, expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            \db()->pdo()->exec("
                CREATE TABLE IF NOT EXISTS password_reset_tokens (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT UNSIGNED NOT NULL,
                    token_hash CHAR(64) NOT NULL UNIQUE,
                    purpose ENUM('forgot_password', 'legacy_reset') NOT NULL DEFAULT 'forgot_password',
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_password_reset_user (user_id, used_at, expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            if (!self::columnExists('users', 'legacy_password_reset_at')) {
                \db()->pdo()->exec('ALTER TABLE users ADD legacy_password_reset_at DATETIME NULL AFTER last_login_at');
            }
        } catch (\Throwable $e) {
            error_log('Security schema could not be prepared: ' . $e->getMessage());
        }
    }

    public static function legacyPasswordResetRequired(array $user): bool
    {
        if (!empty($user['legacy_password_reset_at'])) {
            return false;
        }

        $storedHash = (string)($user['password_hash'] ?? '');
        if ($storedHash === '') {
            return !empty($user['wp_user_id']);
        }

        if (str_starts_with($storedHash, '$wp$')) {
            return true;
        }

        $hashInfo = password_get_info($storedHash);
        if (($hashInfo['algo'] ?? 0) !== 0) {
            return false;
        }

        return !empty($user['wp_user_id']);
    }

    public static function createPasswordResetToken(int $userId, string $purpose = 'forgot_password'): string
    {
        self::ensureSchema();
        $token = bin2hex(random_bytes(32));
        \db()->query(
            "INSERT INTO password_reset_tokens (user_id, token_hash, purpose, expires_at)
             VALUES (?, ?, ?, DATE_ADD(?, INTERVAL 1 HOUR))",
            [$userId, hash('sha256', $token), $purpose === 'legacy_reset' ? 'legacy_reset' : 'forgot_password', \sql_now()]
        );

        return $token;
    }

    public static function passwordResetUser(string $token): ?array
    {
        self::ensureSchema();
        if ($token === '') {
            return null;
        }

        return \db()->fetch(
            "SELECT prt.id AS token_id, prt.user_id, u.email, u.username, u.display_name
             FROM password_reset_tokens prt
             INNER JOIN users u ON u.id = prt.user_id
             WHERE prt.token_hash = ? AND prt.used_at IS NULL AND prt.expires_at > ?
             LIMIT 1",
            [hash('sha256', $token), \sql_now()]
        );
    }

    public static function completePasswordReset(string $token, string $password): bool
    {
        self::ensureSchema();
        $row = self::passwordResetUser($token);
        if (!$row) {
            return false;
        }

        \db()->beginTransaction();
        try {
            \db()->query(
                'UPDATE users SET password_hash = ?, legacy_password_reset_at = COALESCE(legacy_password_reset_at, ?), email_verified_at = COALESCE(email_verified_at, ?), updated_at = ? WHERE id = ?',
                [
                    password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT),
                    \sql_now(),
                    \sql_now(),
                    \sql_now(),
                    (int)$row['user_id'],
                ]
            );
            \db()->query('UPDATE password_reset_tokens SET used_at = ? WHERE id = ?', [\sql_now(), (int)$row['token_id']]);
            \db()->query(
                "DELETE FROM security_attempts WHERE purpose IN ('login', 'forgot_password') AND identifier_hash = ?",
                [self::identifierHash((string)$row['email'])]
            );
            \db()->commit();
        } catch (\Throwable $e) {
            if (\db()->pdo()->inTransaction()) {
                \db()->rollBack();
            }
            throw $e;
        }

        return true;
    }

    public static function passwordHash(string $password): string
    {
        return password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
    }

    public static function rateLimitStatus(string $purpose, string $identifier): array
    {
        self::ensureSchema();
        $key = self::identifierHash($identifier);
        $row = \db()->fetch(
            'SELECT attempts, locked_until, last_attempt_at FROM security_attempts WHERE purpose = ? AND identifier_hash = ? AND ip_address = ? LIMIT 1',
            [$purpose, $key, self::clientIp()]
        );

        if (!$row) {
            return ['blocked' => false, 'captcha' => false, 'attempts' => 0, 'locked_until' => null];
        }

        $lockedUntil = (string)($row['locked_until'] ?? '');
        $blocked = $lockedUntil !== '' && strtotime($lockedUntil) !== false && strtotime($lockedUntil) > time();

        return [
            'blocked' => $blocked,
            'captcha' => (int)($row['attempts'] ?? 0) >= 3,
            'attempts' => (int)($row['attempts'] ?? 0),
            'locked_until' => $lockedUntil !== '' ? $lockedUntil : null,
        ];
    }

    public static function recordFailure(string $purpose, string $identifier): void
    {
        self::ensureSchema();
        $limit = $purpose === 'registration' ? self::REGISTER_LIMIT : self::LOGIN_LIMIT;
        $lockMinutes = $purpose === 'registration' ? self::REGISTER_WINDOW_MINUTES : self::LOGIN_LOCK_MINUTES;
        $windowMinutes = $purpose === 'registration' ? self::REGISTER_WINDOW_MINUTES : self::LOGIN_WINDOW_MINUTES;
        $key = self::identifierHash($identifier);
        $now = \sql_now();

        \db()->query(
            "INSERT INTO security_attempts (purpose, identifier_hash, ip_address, attempts, locked_until, last_attempt_at)
             VALUES (?, ?, ?, 1, NULL, ?)
             ON DUPLICATE KEY UPDATE
                attempts = IF(last_attempt_at < DATE_SUB(?, INTERVAL {$windowMinutes} MINUTE), 1, attempts + 1),
                locked_until = IF(IF(last_attempt_at < DATE_SUB(?, INTERVAL {$windowMinutes} MINUTE), 1, attempts + 1) >= ?, DATE_ADD(?, INTERVAL {$lockMinutes} MINUTE), locked_until),
                last_attempt_at = ?",
            [$purpose, $key, self::clientIp(), $now, $now, $now, $limit, $now, $now]
        );
    }

    public static function clearAttempts(string $purpose, string $identifier): void
    {
        self::ensureSchema();
        \db()->query(
            'DELETE FROM security_attempts WHERE purpose = ? AND identifier_hash = ? AND ip_address = ?',
            [$purpose, self::identifierHash($identifier), self::clientIp()]
        );
    }

    public static function captchaRequired(string $purpose, string $identifier): bool
    {
        return (bool)self::rateLimitStatus($purpose, $identifier)['captcha'];
    }

    public static function ensureCaptchaChallenge(string $purpose): array
    {
        $key = 'captcha_' . $purpose;
        if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
            $a = random_int(2, 9);
            $b = random_int(2, 9);
            $_SESSION[$key] = ['question' => "{$a} + {$b}", 'answer' => (string)($a + $b)];
        }

        return $_SESSION[$key];
    }

    public static function verifyCaptcha(string $purpose, string $answer): bool
    {
        $key = 'captcha_' . $purpose;
        $expected = (string)($_SESSION[$key]['answer'] ?? '');
        unset($_SESSION[$key]);

        return $expected !== '' && hash_equals($expected, trim($answer));
    }

    public static function passwordErrors(string $password, int $minimumLength = 5): array
    {
        $errors = [];
        $minimumLength = max(1, $minimumLength);
        if (strlen($password) < $minimumLength) {
            $errors[] = 'Password must be at least ' . $minimumLength . ' characters.';
        }

        return $errors;
    }

    public static function isDisposableEmail(string $email): bool
    {
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
        if ($domain === '') {
            return false;
        }

        $blocked = [
            '10minutemail.com', 'guerrillamail.com', 'mailinator.com', 'tempmail.com',
            'temp-mail.org', 'throwawaymail.com', 'yopmail.com', 'sharklasers.com',
            'trashmail.com', 'getnada.com', 'dispostable.com',
        ];

        return in_array($domain, $blocked, true);
    }

    public static function registrationLooksSpammy(array $fields): bool
    {
        $joined = strtolower(implode(' ', array_map('strval', $fields)));
        if (preg_match('/https?:\/\/|<[^>]+>|\\[url=|viagra|casino|crypto bonus|loan offer/i', $joined)) {
            return true;
        }

        $first = trim((string)($fields['first_name'] ?? ''));
        $last = trim((string)($fields['last_name'] ?? ''));
        return strlen($first . $last) > 80;
    }

    public static function rolesNeedMfa(array $roles): bool
    {
        return false;
    }

    public static function createMfaChallenge(int $userId, string $email, string $name = ''): void
    {
        self::ensureSchema();
        $code = (string)random_int(100000, 999999);
        \db()->query(
            "INSERT INTO mfa_challenges (user_id, code_hash, ip_address, user_agent, expires_at)
             VALUES (?, ?, ?, ?, DATE_ADD(?, INTERVAL 10 MINUTE))",
            [$userId, hash('sha256', $code), self::clientIp(), (string)($_SERVER['HTTP_USER_AGENT'] ?? ''), \sql_now()]
        );

        try {
            EmailService::send(
                [['email' => $email, 'name' => $name]],
                'Your Seller Africa security code',
                '<p>Your Seller Africa security code is <strong>' . \e($code) . '</strong>.</p><p>This code expires in 10 minutes.</p>'
            );
        } catch (\Throwable $e) {
            error_log('MFA email failed: ' . $e->getMessage());
        }
    }

    public static function verifyMfaCode(int $userId, string $code): bool
    {
        self::ensureSchema();
        $row = \db()->fetch(
            'SELECT id, code_hash FROM mfa_challenges WHERE user_id = ? AND used_at IS NULL AND expires_at > ? ORDER BY id DESC LIMIT 1',
            [$userId, \sql_now()]
        );

        if (!$row || !hash_equals((string)$row['code_hash'], hash('sha256', trim($code)))) {
            return false;
        }

        \db()->query('UPDATE mfa_challenges SET used_at = ? WHERE id = ?', [\sql_now(), (int)$row['id']]);
        return true;
    }

    public static function assertVendorApproved(int $userId): void
    {
        $vendor = \db()->fetch('SELECT status FROM vendors WHERE user_id = ? LIMIT 1', [$userId]);
        if (!$vendor || (string)$vendor['status'] !== 'active') {
            \audit('vendor_access_blocked', 'users', (string)$userId, [], ['status' => $vendor['status'] ?? 'missing'], $userId);
            \flash('warning', 'Your vendor account is pending admin approval.');
            \redirect('store');
        }
    }

    public static function assertAffiliateApproved(int $userId): void
    {
        $affiliate = \db()->fetch('SELECT status FROM affiliates WHERE user_id = ? LIMIT 1', [$userId]);
        $status = (string)($affiliate['status'] ?? 'missing');
        if (!$affiliate || in_array($status, ['rejected', 'inactive', 'blocked'], true)) {
            \audit('affiliate_access_blocked', 'users', (string)$userId, [], ['status' => $affiliate['status'] ?? 'missing'], $userId);
            \flash('warning', 'Your affiliate account cannot access the affiliate dashboard right now.');
            \redirect('store');
        }
    }

    private static function identifierHash(string $identifier): string
    {
        return hash('sha256', strtolower(trim($identifier)) . '|' . self::clientIp());
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
            return false;
        }
    }
}
