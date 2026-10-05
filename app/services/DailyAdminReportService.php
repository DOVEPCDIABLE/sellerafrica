<?php

declare(strict_types=1);

namespace App;

final class DailyAdminReportService
{
    public static function send(array $options = []): array
    {
        $now = \app_now();
        $date = trim((string)($options['date'] ?? ''));
        $reportDay = $date !== ''
            ? new \DateTimeImmutable($date, new \DateTimeZone(\app_timezone()))
            : $now;
        $start = $reportDay->setTime(0, 0, 0);
        $end = ($reportDay->format('Y-m-d') === $now->format('Y-m-d')) ? $now : $reportDay->setTime(23, 59, 59);
        $range = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'label' => $start->format('M j, Y') . ' 12:00 AM - ' . $end->format('g:i A'),
            'day' => $start->format('Y-m-d'),
        ];

        $data = self::build($range);
        $html = self::html($range, $data);

        NotificationService::notifyAdmins(
            'Seller Africa daily report - ' . $start->format('M j, Y'),
            'Daily marketplace report',
            $html,
            [
                'button_text' => 'Open Admin Dashboard',
                'button_url' => \app_url('dashboard'),
                'metadata' => [
                    'type' => 'daily_admin_report',
                    'report_date' => $range['day'],
                    'start' => $range['start'],
                    'end' => $range['end'],
                ],
            ]
        );

        if (function_exists('audit')) {
            \audit('daily_admin_report_queued', 'system', $range['day'], [], [
                'start' => $range['start'],
                'end' => $range['end'],
                'new_users' => (int)($data['registrations']['total'] ?? 0),
                'new_products' => (int)($data['products']['created'] ?? 0),
                'orders' => (int)($data['sales']['orders'] ?? 0),
            ]);
        }

        return [
            'queued' => true,
            'type' => 'daily',
            'date' => $range['day'],
            'new_users' => (int)($data['registrations']['total'] ?? 0),
            'new_vendors' => (int)($data['vendors']['new'] ?? 0),
            'vendors_approved' => (int)($data['vendors']['approved_period'] ?? 0),
            'new_products' => (int)($data['products']['created'] ?? 0),
            'orders' => (int)($data['sales']['orders'] ?? 0),
        ];
    }

    public static function sendWeekly(array $options = []): array
    {
        $now = \app_now();
        $date = trim((string)($options['date'] ?? $options['end_date'] ?? ''));
        $endDay = $date !== ''
            ? new \DateTimeImmutable($date, new \DateTimeZone(\app_timezone()))
            : $now;
        $end = ($endDay->format('Y-m-d') === $now->format('Y-m-d')) ? $now : $endDay->setTime(23, 59, 59);
        $start = $end->sub(new \DateInterval('P6D'))->setTime(0, 0, 0);
        $range = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'label' => $start->format('M j, Y') . ' 12:00 AM - ' . $end->format('M j, Y g:i A'),
            'day' => $end->format('Y-m-d'),
        ];

        $data = self::build($range);
        $html = self::html($range, $data, 'weekly');

        NotificationService::notifyAdmins(
            'Seller Africa weekly report - ' . $start->format('M j') . ' to ' . $end->format('M j, Y'),
            'Weekly marketplace report',
            $html,
            [
                'button_text' => 'Open Admin Dashboard',
                'button_url' => \app_url('dashboard'),
                'metadata' => [
                    'type' => 'weekly_admin_report',
                    'report_date' => $range['day'],
                    'start' => $range['start'],
                    'end' => $range['end'],
                ],
            ]
        );

        if (function_exists('audit')) {
            \audit('weekly_admin_report_queued', 'system', $range['day'], [], [
                'start' => $range['start'],
                'end' => $range['end'],
                'new_users' => (int)($data['registrations']['total'] ?? 0),
                'new_vendors' => (int)($data['vendors']['new'] ?? 0),
                'vendors_approved' => (int)($data['vendors']['approved_period'] ?? 0),
                'new_products' => (int)($data['products']['created'] ?? 0),
                'orders' => (int)($data['sales']['orders'] ?? 0),
            ]);
        }

        return [
            'queued' => true,
            'type' => 'weekly',
            'start' => $range['start'],
            'end' => $range['end'],
            'new_users' => (int)($data['registrations']['total'] ?? 0),
            'new_vendors' => (int)($data['vendors']['new'] ?? 0),
            'vendors_approved' => (int)($data['vendors']['approved_period'] ?? 0),
            'new_products' => (int)($data['products']['created'] ?? 0),
            'orders' => (int)($data['sales']['orders'] ?? 0),
        ];
    }

    /**
     * @param array{start:string,end:string,label:string,day:string} $range
     * @return array<string, mixed>
     */
    private static function build(array $range): array
    {
        $params = [$range['start'], $range['end']];

        $registrations = [
            'total' => self::scalar("SELECT COUNT(*) AS value FROM users WHERE created_at BETWEEN ? AND ?", $params, 'users'),
            'customers' => self::scalar(self::roleCountSql('customer'), $params, 'users'),
            'vendors' => self::scalar(self::roleCountSql('vendor'), $params, 'users'),
            'affiliates' => self::scalar(self::roleCountSql('affiliate'), $params, 'users'),
            'failed' => self::scalar("SELECT COUNT(*) AS value FROM audit_logs WHERE action IN ('registration_failed', 'registration_exception', 'registration_csrf_failed') AND created_at BETWEEN ? AND ?", $params, 'audit_logs'),
            'total_users' => self::scalar("SELECT COUNT(*) AS value FROM users", [], 'users'),
            'total_customers' => self::scalar(self::totalRoleCountSql('customer'), [], 'users'),
            'total_vendor_users' => self::scalar(self::totalRoleCountSql('vendor'), [], 'users'),
            'total_affiliates' => self::scalar(self::totalRoleCountSql('affiliate'), [], 'users'),
        ];

        $products = [
            'created' => self::scalar("SELECT COUNT(*) AS value FROM products WHERE type <> 'variation' AND created_at BETWEEN ? AND ?", $params, 'products'),
            'active' => self::scalar("SELECT COUNT(*) AS value FROM products WHERE type <> 'variation' AND status = 'active'", [], 'products'),
            'pending' => self::scalar("SELECT COUNT(*) AS value FROM products WHERE type <> 'variation' AND status = 'pending'", [], 'products'),
            'newRows' => self::rows(
                "SELECT p.name, p.status, p.regular_price, p.currency, COALESCE(v.store_name, 'No vendor') AS vendor_name
                 FROM products p
                 LEFT JOIN vendors v ON v.id = p.vendor_id
                 WHERE p.type <> 'variation' AND p.created_at BETWEEN ? AND ?
                 ORDER BY p.created_at DESC
                 LIMIT 8",
                $params,
                'products'
            ),
        ];

        $vendors = [
            'total' => self::scalar("SELECT COUNT(*) AS value FROM vendors", [], 'vendors'),
            'active' => self::scalar("SELECT COUNT(*) AS value FROM vendors WHERE status = 'active'", [], 'vendors'),
            'pending' => self::scalar("SELECT COUNT(*) AS value FROM vendors WHERE status = 'pending'", [], 'vendors'),
            'pending_kyc' => self::scalar("SELECT COUNT(*) AS value FROM vendors WHERE COALESCE(kyc_status, 'pending') <> 'approved'", [], 'vendors'),
            'new' => self::scalar("SELECT COUNT(*) AS value FROM vendors WHERE created_at BETWEEN ? AND ?", $params, 'vendors'),
            'approved_period' => self::approvedVendorCount($params),
        ];

        $sales = [
            'orders' => 0,
            'gross' => 0.0,
            'paid' => 0.0,
            'average_order' => 0.0,
            'failed_payments' => 0,
            'recentOrders' => [],
        ];
        if (\table_exists('orders')) {
            $summary = \db()->fetch(
                "SELECT
                    COUNT(*) AS orders,
                    COALESCE(SUM(grand_total), 0) AS gross,
                    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN grand_total ELSE 0 END), 0) AS paid,
                    COALESCE(AVG(NULLIF(grand_total, 0)), 0) AS average_order
                 FROM orders
                 WHERE created_at BETWEEN ? AND ?
                   AND status NOT IN ('cancelled', 'failed', 'refunded')",
                $params
            ) ?: [];
            $sales['orders'] = (int)($summary['orders'] ?? 0);
            $sales['gross'] = (float)($summary['gross'] ?? 0);
            $sales['paid'] = (float)($summary['paid'] ?? 0);
            $sales['average_order'] = (float)($summary['average_order'] ?? 0);
            $sales['recentOrders'] = self::rows(
                "SELECT order_number, status, payment_status, currency, grand_total, created_at
                 FROM orders
                 WHERE created_at BETWEEN ? AND ?
                 ORDER BY created_at DESC
                 LIMIT 8",
                $params,
                'orders'
            );
        }
        if (\table_exists('payments')) {
            $sales['failed_payments'] = self::scalar("SELECT COUNT(*) AS value FROM payments WHERE status = 'failed' AND created_at BETWEEN ? AND ?", $params, 'payments');
        }

        $vendorSales = self::rows(
            "SELECT v.store_name, COUNT(DISTINCT ovs.order_id) AS orders,
                    COALESCE(SUM(ovs.gross_total), 0) AS gross_sales,
                    COALESCE(SUM(ovs.vendor_earning), 0) AS vendor_earning,
                    COALESCE(SUM(ovs.platform_commission), 0) AS platform_commission
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             INNER JOIN vendors v ON v.id = ovs.vendor_id
             WHERE o.created_at BETWEEN ? AND ?
               AND o.status NOT IN ('cancelled', 'failed', 'refunded')
             GROUP BY v.id, v.store_name
             ORDER BY gross_sales DESC
             LIMIT 8",
            $params,
            'order_vendor_splits'
        );

        $affiliates = [
            'new' => self::scalar("SELECT COUNT(*) AS value FROM affiliates WHERE COALESCE(registered_at, created_at) BETWEEN ? AND ?", $params, 'affiliates'),
            'visits' => self::scalar("SELECT COUNT(*) AS value FROM affiliate_visits WHERE created_at BETWEEN ? AND ?", $params, 'affiliate_visits'),
            'conversions' => self::scalar("SELECT COUNT(*) AS value FROM affiliate_visits WHERE converted_at BETWEEN ? AND ?", $params, 'affiliate_visits'),
            'referrals' => self::scalar("SELECT COUNT(*) AS value FROM affiliate_referrals WHERE created_at BETWEEN ? AND ?", $params, 'affiliate_referrals'),
            'commission' => self::scalar("SELECT COALESCE(SUM(amount), 0) AS value FROM affiliate_referrals WHERE created_at BETWEEN ? AND ?", $params, 'affiliate_referrals'),
            'topRows' => self::rows(
                "SELECT a.referral_code, COALESCE(u.display_name, u.email, a.payment_email) AS affiliate_name,
                        COUNT(r.id) AS referrals,
                        COALESCE(SUM(r.amount), 0) AS commission
                 FROM affiliates a
                 LEFT JOIN users u ON u.id = a.user_id
                 LEFT JOIN affiliate_referrals r ON r.affiliate_id = a.id AND r.created_at BETWEEN ? AND ?
                 GROUP BY a.id, a.referral_code, u.display_name, u.email, a.payment_email
                 HAVING referrals > 0 OR commission > 0
                 ORDER BY commission DESC, referrals DESC
                 LIMIT 8",
                $params,
                'affiliates'
            ),
        ];

        return compact('registrations', 'products', 'vendors', 'sales', 'vendorSales', 'affiliates');
    }

    private static function roleCountSql(string $role): string
    {
        return "SELECT COUNT(DISTINCT u.id) AS value
                FROM users u
                INNER JOIN user_roles ur ON ur.user_id = u.id
                INNER JOIN roles r ON r.id = ur.role_id
                WHERE r.code = '" . str_replace("'", "''", $role) . "'
                  AND u.created_at BETWEEN ? AND ?";
    }

    private static function totalRoleCountSql(string $role): string
    {
        return "SELECT COUNT(DISTINCT u.id) AS value
                FROM users u
                INNER JOIN user_roles ur ON ur.user_id = u.id
                INNER JOIN roles r ON r.id = ur.role_id
                WHERE r.code = '" . str_replace("'", "''", $role) . "'";
    }

    /**
     * @param array<int, mixed> $params
     */
    private static function approvedVendorCount(array $params): float
    {
        $count = self::scalar("SELECT COUNT(*) AS value FROM audit_logs WHERE action = 'vendor.approved' AND created_at BETWEEN ? AND ?", $params, 'audit_logs');
        if ($count > 0) {
            return $count;
        }

        return self::scalar(
            "SELECT COUNT(*) AS value
             FROM vendors
             WHERE status = 'active'
               AND kyc_status = 'approved'
               AND updated_at BETWEEN ? AND ?",
            $params,
            'vendors'
        );
    }

    /**
     * @param array<int, mixed> $params
     */
    private static function scalar(string $sql, array $params = [], string $requiredTable = ''): float
    {
        if ($requiredTable !== '' && !\table_exists($requiredTable)) {
            return 0.0;
        }

        try {
            $row = \db()->fetch($sql, $params);
            return (float)($row['value'] ?? 0);
        } catch (\Throwable $e) {
            error_log('Daily admin report scalar failed: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private static function rows(string $sql, array $params = [], string $requiredTable = ''): array
    {
        if ($requiredTable !== '' && !\table_exists($requiredTable)) {
            return [];
        }

        try {
            return \db()->fetchAll($sql, $params);
        } catch (\Throwable $e) {
            error_log('Daily admin report rows failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @param array{start:string,end:string,label:string,day:string} $range
     * @param array<string, mixed> $data
     */
    private static function html(array $range, array $data, string $reportType = 'daily'): string
    {
        $registrations = (array)$data['registrations'];
        $products = (array)$data['products'];
        $vendors = (array)$data['vendors'];
        $sales = (array)$data['sales'];
        $affiliates = (array)$data['affiliates'];

        $body = '<p>' . ($reportType === 'weekly' ? 'Weekly' : 'Daily') . ' operating summary for <strong>' . \e($range['label']) . '</strong>.</p>';
        $body .= '<h3>Snapshot</h3>';
        $body .= self::metricGrid([
            'New users' => number_format((int)$registrations['total']),
            'New vendors' => number_format((int)$vendors['new']),
            'Vendors approved' => number_format((int)$vendors['approved_period']),
            'New products' => number_format((int)$products['created']),
            'Orders' => number_format((int)$sales['orders']),
            'Gross sales' => self::money($sales['gross'] ?? 0),
            'Paid revenue' => self::money($sales['paid'] ?? 0),
            'Affiliate commission' => self::money($affiliates['commission'] ?? 0),
        ]);

        $body .= '<h3>Registrations</h3>';
        $body .= self::keyTable([
            'Customers' => number_format((int)$registrations['customers']),
            'Vendors' => number_format((int)$registrations['vendors']),
            'Affiliates' => number_format((int)$registrations['affiliates']),
            'Failed registration attempts' => number_format((int)$registrations['failed']),
            'Total users' => number_format((int)$registrations['total_users']),
            'Total customers' => number_format((int)$registrations['total_customers']),
            'Total vendor users' => number_format((int)$registrations['total_vendor_users']),
            'Total affiliates' => number_format((int)$registrations['total_affiliates']),
        ]);

        $body .= '<h3>Products</h3>';
        $body .= self::keyTable([
            'New products added today' => number_format((int)$products['created']),
            'Active products total' => number_format((int)$products['active']),
            'Pending products total' => number_format((int)$products['pending']),
        ]);
        $body .= self::productRows((array)($products['newRows'] ?? []));

        $body .= '<h3>Vendor Summary</h3>';
        $body .= self::keyTable([
            'Total vendors' => number_format((int)$vendors['total']),
            'Active vendors' => number_format((int)$vendors['active']),
            'Pending vendors' => number_format((int)$vendors['pending']),
            'Pending KYC' => number_format((int)$vendors['pending_kyc']),
            ($reportType === 'weekly' ? 'New vendor records this week' : 'New vendor records today') => number_format((int)$vendors['new']),
            ($reportType === 'weekly' ? 'Vendors approved this week' : 'Vendors approved today') => number_format((int)$vendors['approved_period']),
        ]);
        $body .= self::vendorSalesRows((array)($data['vendorSales'] ?? []));

        $body .= '<h3>Sales</h3>';
        $body .= self::keyTable([
            'Orders' => number_format((int)$sales['orders']),
            'Gross sales' => self::money($sales['gross'] ?? 0),
            'Paid revenue' => self::money($sales['paid'] ?? 0),
            'Average order value' => self::money($sales['average_order'] ?? 0),
            'Failed payments' => number_format((int)$sales['failed_payments']),
        ]);
        $body .= self::orderRows((array)($sales['recentOrders'] ?? []));

        $body .= '<h3>Affiliate Summary</h3>';
        $body .= self::keyTable([
            'New affiliates' => number_format((int)$affiliates['new']),
            'Affiliate visits' => number_format((int)$affiliates['visits']),
            'Affiliate conversions' => number_format((int)$affiliates['conversions']),
            'Affiliate referrals' => number_format((int)$affiliates['referrals']),
            'Commission generated' => self::money($affiliates['commission'] ?? 0),
        ]);
        $body .= self::affiliateRows((array)($affiliates['topRows'] ?? []));

        return $body;
    }

    /**
     * @param array<string, string> $items
     */
    private static function metricGrid(array $items): string
    {
        $html = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:12px 0 18px"><tr>';
        $count = 0;
        foreach ($items as $label => $value) {
            if ($count > 0 && $count % 2 === 0) {
                $html .= '</tr><tr>';
            }
            $html .= '<td style="width:50%;padding:10px;border:1px solid #e5e7eb;background:#f8fafc"><div style="font-size:12px;color:#64748b">' . \e($label) . '</div><strong style="font-size:20px;color:#0f172a">' . \e($value) . '</strong></td>';
            $count++;
        }
        return $html . '</tr></table>';
    }

    /**
     * @param array<string, string> $items
     */
    private static function keyTable(array $items): string
    {
        $html = '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:8px 0 18px">';
        foreach ($items as $label => $value) {
            $html .= '<tr><td style="padding:8px;border-bottom:1px solid #edf2f7;color:#475569">' . \e($label) . '</td><td style="padding:8px;border-bottom:1px solid #edf2f7;text-align:right;font-weight:700;color:#0f172a">' . \e($value) . '</td></tr>';
        }
        return $html . '</table>';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private static function productRows(array $rows): string
    {
        if ($rows === []) {
            return '<p style="color:#64748b">No new products were added in this report period.</p>';
        }

        $html = '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:8px 0 18px"><tr><th align="left">Product</th><th align="left">Vendor</th><th align="right">Price</th></tr>';
        foreach ($rows as $row) {
            $html .= '<tr><td style="padding:7px;border-top:1px solid #edf2f7">' . \e((string)$row['name']) . '<br><small>' . \e((string)$row['status']) . '</small></td><td style="padding:7px;border-top:1px solid #edf2f7">' . \e((string)$row['vendor_name']) . '</td><td style="padding:7px;border-top:1px solid #edf2f7;text-align:right">' . self::money($row['regular_price'] ?? 0, (string)($row['currency'] ?? 'USD')) . '</td></tr>';
        }
        return $html . '</table>';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private static function vendorSalesRows(array $rows): string
    {
        if ($rows === []) {
            return '<p style="color:#64748b">No vendor sales were recorded in this report period.</p>';
        }

        $html = '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:8px 0 18px"><tr><th align="left">Vendor</th><th align="right">Orders</th><th align="right">Gross</th><th align="right">Vendor earning</th></tr>';
        foreach ($rows as $row) {
            $html .= '<tr><td style="padding:7px;border-top:1px solid #edf2f7">' . \e((string)$row['store_name']) . '</td><td style="padding:7px;border-top:1px solid #edf2f7;text-align:right">' . number_format((int)$row['orders']) . '</td><td style="padding:7px;border-top:1px solid #edf2f7;text-align:right">' . self::money($row['gross_sales'] ?? 0) . '</td><td style="padding:7px;border-top:1px solid #edf2f7;text-align:right">' . self::money($row['vendor_earning'] ?? 0) . '</td></tr>';
        }
        return $html . '</table>';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private static function orderRows(array $rows): string
    {
        if ($rows === []) {
            return '<p style="color:#64748b">No orders were recorded in this report period.</p>';
        }

        $html = '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:8px 0 18px"><tr><th align="left">Order</th><th align="left">Status</th><th align="right">Total</th></tr>';
        foreach ($rows as $row) {
            $html .= '<tr><td style="padding:7px;border-top:1px solid #edf2f7">' . \e((string)$row['order_number']) . '<br><small>' . \e((string)$row['created_at']) . '</small></td><td style="padding:7px;border-top:1px solid #edf2f7">' . \e((string)$row['status']) . ' / ' . \e((string)$row['payment_status']) . '</td><td style="padding:7px;border-top:1px solid #edf2f7;text-align:right">' . self::money($row['grand_total'] ?? 0, (string)($row['currency'] ?? 'USD')) . '</td></tr>';
        }
        return $html . '</table>';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private static function affiliateRows(array $rows): string
    {
        if ($rows === []) {
            return '<p style="color:#64748b">No affiliate referrals were recorded in this report period.</p>';
        }

        $html = '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:8px 0 18px"><tr><th align="left">Affiliate</th><th align="right">Referrals</th><th align="right">Commission</th></tr>';
        foreach ($rows as $row) {
            $html .= '<tr><td style="padding:7px;border-top:1px solid #edf2f7">' . \e((string)$row['affiliate_name']) . '<br><small>' . \e((string)$row['referral_code']) . '</small></td><td style="padding:7px;border-top:1px solid #edf2f7;text-align:right">' . number_format((int)$row['referrals']) . '</td><td style="padding:7px;border-top:1px solid #edf2f7;text-align:right">' . self::money($row['commission'] ?? 0) . '</td></tr>';
        }
        return $html . '</table>';
    }

    private static function money(mixed $amount, string $currency = 'USD'): string
    {
        return strtoupper($currency !== '' ? $currency : 'USD') . ' ' . number_format((float)$amount, 2);
    }
}
