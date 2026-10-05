<?php

declare(strict_types=1);

namespace App;

final class AffiliateService
{
    private const ATTRIBUTION_DAYS = 30;

    public static function captureRequest(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return;
        }

        $raw = trim((string)($_GET['ref'] ?? $_GET['affiliate'] ?? $_GET['aff'] ?? ''));
        if ($raw === '') {
            return;
        }

        self::rememberReferral($raw, true);
    }

    public static function rememberReferral(string $raw, bool $countVisit = false): ?array
    {
        $record = self::resolveReferral($raw);
        if (!$record) {
            return null;
        }

        $visitId = null;
        if ($countVisit && \table_exists('affiliate_visits')) {
            \db()->query(
                "INSERT INTO affiliate_visits
                    (affiliate_id, campaign_id, link_id, user_id, ip_address, user_agent, landing_url, referrer_url, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    (int)$record['affiliate_id'],
                    self::nullableInt($record['campaign_id'] ?? null),
                    self::nullableInt($record['link_id'] ?? null),
                    isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                    \client_ip(),
                    substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
                    substr(self::currentUrl(), 0, 1000),
                    substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 1000) ?: null,
                    \sql_now(),
                ]
            );
            $visitId = (int)\db()->lastInsertId();

            if (!empty($record['link_id']) && \table_exists('affiliate_links')) {
                \db()->query('UPDATE affiliate_links SET clicks_count = clicks_count + 1 WHERE id = ?', [(int)$record['link_id']]);
            }
            \db()->query('UPDATE affiliates SET visits_count = visits_count + 1 WHERE id = ?', [(int)$record['affiliate_id']]);
        }

        $_SESSION['affiliate_referral'] = [
            'affiliate_id' => (int)$record['affiliate_id'],
            'link_id' => self::nullableInt($record['link_id'] ?? null),
            'campaign_id' => self::nullableInt($record['campaign_id'] ?? null),
            'visit_id' => $visitId,
            'token' => (string)($record['token'] ?? ''),
            'code' => (string)($record['referral_code'] ?? ''),
            'captured_at' => time(),
            'expires_at' => time() + (self::ATTRIBUTION_DAYS * 86400),
        ];

        return $_SESSION['affiliate_referral'];
    }

    public static function currentReferral(): ?array
    {
        $referral = $_SESSION['affiliate_referral'] ?? null;
        if (!is_array($referral) || (int)($referral['affiliate_id'] ?? 0) <= 0) {
            return null;
        }

        if ((int)($referral['expires_at'] ?? 0) < time()) {
            unset($_SESSION['affiliate_referral']);
            return null;
        }

        return $referral;
    }

    public static function resolveReferral(string $raw): ?array
    {
        if (!\table_exists('affiliates')) {
            return null;
        }

        $code = self::extractCode($raw);
        if ($code === '') {
            return null;
        }

        if (\table_exists('affiliate_links')) {
            $link = \db()->fetch(
                "SELECT l.id AS link_id, l.affiliate_id, l.campaign_id, l.token, l.target_url,
                        a.referral_code, a.status, a.user_id
                 FROM affiliate_links l
                 INNER JOIN affiliates a ON a.id = l.affiliate_id
                 WHERE l.token = ? AND a.status IN ('pending', 'active')
                 LIMIT 1",
                [$code]
            );
            if ($link) {
                return $link;
            }
        }

        $affiliate = \db()->fetch(
            "SELECT id AS affiliate_id, NULL AS link_id, NULL AS campaign_id, referral_code, status, user_id
             FROM affiliates
             WHERE referral_code = ? AND status IN ('pending', 'active')
             LIMIT 1",
            [$code]
        );

        return $affiliate ?: null;
    }

    public static function referralUrl(string $targetUrl, string $token): string
    {
        $targetUrl = trim($targetUrl);
        $token = trim($token);
        if ($targetUrl === '' || $token === '') {
            return $targetUrl;
        }

        $parts = parse_url($targetUrl);
        if (!is_array($parts)) {
            return $targetUrl . (str_contains($targetUrl, '?') ? '&' : '?') . 'ref=' . rawurlencode($token);
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string)$parts['query'], $query);
        }
        $query['ref'] = $token;

        $rebuilt = '';
        if (isset($parts['scheme'])) {
            $rebuilt .= $parts['scheme'] . '://';
        }
        if (isset($parts['user'])) {
            $rebuilt .= $parts['user'] . (isset($parts['pass']) ? ':' . $parts['pass'] : '') . '@';
        }
        if (isset($parts['host'])) {
            $rebuilt .= $parts['host'];
        }
        if (isset($parts['port'])) {
            $rebuilt .= ':' . $parts['port'];
        }
        $rebuilt .= $parts['path'] ?? '';
        $rebuilt .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        if (isset($parts['fragment'])) {
            $rebuilt .= '#' . $parts['fragment'];
        }

        return $rebuilt;
    }

    public static function createReferralForOrder(int $orderId, ?int $customerId, float $orderTotal, string $currency): void
    {
        if ($orderId <= 0 || $orderTotal <= 0 || !\table_exists('affiliate_referrals')) {
            return;
        }

        $referral = self::currentReferral();
        if (!$referral) {
            return;
        }

        $affiliateId = (int)$referral['affiliate_id'];
        $affiliate = \db()->fetch(
            "SELECT id, user_id, rate_type, commission_rate, status
             FROM affiliates
             WHERE id = ? AND status IN ('pending', 'active')
             LIMIT 1",
            [$affiliateId]
        );
        if (!$affiliate || ($customerId && (int)$affiliate['user_id'] === $customerId)) {
            return;
        }

        $existing = \db()->fetch('SELECT id FROM affiliate_referrals WHERE order_id = ? LIMIT 1', [$orderId]);
        if ($existing) {
            return;
        }

        $rule = self::commissionRule($affiliateId, self::nullableInt($referral['campaign_id'] ?? null), $orderId, $orderTotal);
        if (!$rule) {
            $rule = [
                'rate_type' => (string)($affiliate['rate_type'] ?? 'percentage'),
                'commission_rate' => (float)($affiliate['commission_rate'] ?? 0),
                'name' => 'Affiliate default rate',
            ];
        }

        $rate = max(0.0, (float)($rule['commission_rate'] ?? 0));
        $amount = (string)($rule['rate_type'] ?? 'percentage') === 'flat'
            ? $rate
            : ($orderTotal * $rate / 100);
        $amount = round(max(0.0, $amount), 4);
        if ($amount <= 0) {
            return;
        }

        \db()->query(
            "INSERT INTO affiliate_referrals
                (affiliate_id, visit_id, order_id, customer_id, status, amount, order_total, currency, description, reference, created_at)
             VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?)",
            [
                $affiliateId,
                self::nullableInt($referral['visit_id'] ?? null),
                $orderId,
                $customerId,
                $amount,
                $orderTotal,
                strtoupper(substr($currency, 0, 3)) ?: 'USD',
                'Order referral - ' . (string)($rule['name'] ?? 'Commission rule'),
                'order:' . $orderId,
                \sql_now(),
            ]
        );

        \db()->query('UPDATE affiliates SET referrals_count = referrals_count + 1 WHERE id = ?', [$affiliateId]);
    }

    public static function markOrderPaid(int $orderId): void
    {
        if ($orderId <= 0 || !\table_exists('affiliate_referrals')) {
            return;
        }

        $rows = \db()->fetchAll(
            "SELECT id, affiliate_id, visit_id, amount
             FROM affiliate_referrals
             WHERE order_id = ? AND status = 'pending'",
            [$orderId]
        );
        if ($rows === []) {
            return;
        }

        foreach ($rows as $row) {
            \db()->query(
                "UPDATE affiliate_referrals
                 SET status = 'unpaid'
                 WHERE id = ? AND status = 'pending'",
                [(int)$row['id']]
            );

            \db()->query(
                "UPDATE affiliates
                 SET total_earnings = total_earnings + ?, unpaid_earnings = unpaid_earnings + ?
                 WHERE id = ?",
                [(float)$row['amount'], (float)$row['amount'], (int)$row['affiliate_id']]
            );

            if (!empty($row['visit_id']) && \table_exists('affiliate_visits')) {
                \db()->query(
                    'UPDATE affiliate_visits SET converted_at = COALESCE(converted_at, ?) WHERE id = ?',
                    [\sql_now(), (int)$row['visit_id']]
                );
            }
        }
    }

    private static function commissionRule(int $affiliateId, ?int $campaignId, int $orderId, float $orderTotal): ?array
    {
        if (!\table_exists('affiliate_commission_rules')) {
            return null;
        }

        $vendorIds = self::orderVendorIds($orderId);
        $categoryIds = self::orderCategoryIds($orderId);
        $conditions = ["(rule_type = 'global')", "(rule_type = 'affiliate' AND target_id = ?)"];
        $params = [$affiliateId];

        if ($campaignId) {
            $conditions[] = "(rule_type = 'campaign' AND target_id = ?)";
            $params[] = $campaignId;
        }
        if ($vendorIds !== []) {
            $conditions[] = "(rule_type = 'vendor' AND target_id IN (" . implode(',', array_fill(0, count($vendorIds), '?')) . '))';
            array_push($params, ...$vendorIds);
        }
        if ($categoryIds !== []) {
            $conditions[] = "(rule_type = 'category' AND target_id IN (" . implode(',', array_fill(0, count($categoryIds), '?')) . '))';
            array_push($params, ...$categoryIds);
        }

        $params[] = $orderTotal;
        $now = \sql_now();
        $params[] = $now;
        $params[] = $now;

        $rules = \db()->fetchAll(
            "SELECT *
             FROM affiliate_commission_rules
             WHERE is_active = 1
               AND (" . implode(' OR ', $conditions) . ")
               AND (min_order_amount IS NULL OR min_order_amount <= ?)
               AND (starts_at IS NULL OR starts_at <= ?)
               AND (ends_at IS NULL OR ends_at >= ?)
             ORDER BY priority ASC, FIELD(rule_type, 'affiliate', 'campaign', 'vendor', 'category', 'global') ASC, id DESC
             LIMIT 1",
            $params
        );

        return $rules[0] ?? null;
    }

    private static function orderVendorIds(int $orderId): array
    {
        if (!\table_exists('order_items')) {
            return [];
        }

        return array_map('intval', array_column(\db()->fetchAll(
            'SELECT DISTINCT vendor_id FROM order_items WHERE order_id = ? AND vendor_id IS NOT NULL',
            [$orderId]
        ), 'vendor_id'));
    }

    private static function orderCategoryIds(int $orderId): array
    {
        if (!\table_exists('order_items') || !\table_exists('product_categories')) {
            return [];
        }

        return array_map('intval', array_column(\db()->fetchAll(
            "SELECT DISTINCT pc.category_id
             FROM order_items oi
             INNER JOIN product_categories pc ON pc.product_id = oi.product_id
             WHERE oi.order_id = ?",
            [$orderId]
        ), 'category_id'));
    }

    private static function extractCode(string $raw): string
    {
        $value = trim($raw);
        if (preg_match('/^https?:\/\//i', $value)) {
            $parts = parse_url($value);
            if (is_array($parts) && !empty($parts['query'])) {
                parse_str((string)$parts['query'], $query);
                $value = (string)($query['ref'] ?? $query['affiliate'] ?? $query['aff'] ?? $value);
            }
        }

        return substr(preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?? '', 0, 120);
    }

    private static function currentUrl(): string
    {
        $scheme = \app_is_https() ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? parse_url(BASE_URL, PHP_URL_HOST) ?? 'localhost');
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');

        return $scheme . '://' . $host . $uri;
    }

    private static function nullableInt(mixed $value): ?int
    {
        $int = (int)($value ?? 0);
        return $int > 0 ? $int : null;
    }
}
