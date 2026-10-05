<?php

declare(strict_types=1);

namespace App;

final class NotificationService
{
    public static function ensureSchema(): void
    {
        try {
            \db()->pdo()->exec("
                CREATE TABLE IF NOT EXISTS jobs_queue (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    queue_name VARCHAR(100) NOT NULL DEFAULT 'default',
                    payload JSON NULL,
                    status ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
                    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_jobs_queue_status (queue_name, status, available_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            foreach ([
                'attempts' => 'ALTER TABLE jobs_queue ADD attempts TINYINT UNSIGNED NOT NULL DEFAULT 0',
                'available_at' => 'ALTER TABLE jobs_queue ADD available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'ALTER TABLE jobs_queue ADD updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            ] as $column => $sql) {
                if (!self::columnExists('jobs_queue', $column)) {
                    \db()->pdo()->exec($sql);
                }
            }

            \db()->pdo()->exec("
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
                    INDEX idx_notifications_recipient (recipient_user_id, channel, read_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Throwable $e) {
            error_log('Notification schema could not be prepared: ' . $e->getMessage());
        }
    }

    public static function enqueueEmail(string|array $to, string $subject, string $heading, string $body, array $options = []): void
    {
        self::ensureSchema();
        $html = self::brandedHtml($heading, $body, $options);
        $recipients = self::recipients($to);

        foreach ($recipients as $recipient) {
            self::record('email', $recipient['email'], $subject, strip_tags($body), $options);
        }

        $payload = json_encode([
            'action' => 'send',
            'data' => [
                'to' => $recipients,
                'subject' => $subject,
                'html' => $html,
                'text' => trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body))),
                'options' => array_diff_key($options, ['available_at' => true]),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $availableAt = trim((string)($options['available_at'] ?? '')) ?: \sql_now();

        try {
            \db()->query(
                "INSERT INTO jobs_queue (queue_name, payload, status, available_at, created_at)
                 VALUES ('emails', ?, 'pending', ?, ?)",
                [$payload, $availableAt, \sql_now()]
            );
        } catch (\Throwable $e) {
            error_log('Email queue failed; sending directly: ' . $e->getMessage());
            try {
                EmailService::send($recipients, $subject, $html);
            } catch (\Throwable $sendError) {
                error_log('Direct email fallback failed: ' . $sendError->getMessage());
            }
        }
    }

    public static function handle(string $action, array $data = []): array
    {
        return match ($action) {
            'process_pending_kyc_reminders' => self::processPendingKycReminders($data),
            'send_daily_admin_report' => DailyAdminReportService::send($data),
            'send_weekly_admin_report' => DailyAdminReportService::sendWeekly($data),
            default => throw new \InvalidArgumentException('No notification handler defined for action: ' . $action),
        };
    }

    public static function processPendingKycReminders(array $options = []): array
    {
        self::ensureSchema();

        $limit = max(1, min(1000, (int)($options['limit'] ?? 500)));
        $subject = 'Complete your Seller Africa vendor verification';
        $rows = [];

        try {
            $rows = \db()->fetchAll(
                "SELECT
                    v.id,
                    v.status,
                    v.kyc_status,
                    COALESCE(NULLIF(v.store_email, ''), u.email) AS email,
                    COALESCE(NULLIF(v.store_name, ''), NULLIF(u.display_name, ''), u.username, u.email) AS name
                 FROM vendors v
                 INNER JOIN users u ON u.id = v.user_id
                 WHERE LOWER(COALESCE(v.kyc_status, 'not_started')) <> 'approved'
                   AND LOWER(COALESCE(v.status, 'pending')) NOT IN ('rejected', 'suspended', 'closed')
                   AND LOWER(COALESCE(u.status, 'active')) IN ('active', 'pending')
                   AND COALESCE(NULLIF(v.store_email, ''), u.email) IS NOT NULL
                   AND COALESCE(NULLIF(v.store_email, ''), u.email) <> ''
                   AND NOT EXISTS (
                       SELECT 1
                       FROM notifications n
                       WHERE n.channel = 'email'
                         AND n.recipient_email = COALESCE(NULLIF(v.store_email, ''), u.email)
                         AND n.title = ?
                         AND n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                   )
                 ORDER BY FIELD(LOWER(COALESCE(v.kyc_status, 'not_started')), 'pending', 'not_started', 'expired', 'rejected'),
                          v.updated_at ASC,
                          v.id ASC
                 LIMIT {$limit}",
                [$subject]
            );
        } catch (\Throwable $e) {
            error_log('Pending KYC reminder lookup failed: ' . $e->getMessage());
            return ['queued' => 0, 'skipped' => 0, 'error' => $e->getMessage()];
        }

        $queued = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $email = trim((string)($row['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            $vendorId = (int)($row['id'] ?? 0);
            $name = trim((string)($row['name'] ?? 'there')) ?: 'there';
            $body = '<p>Hello ' . \e($name) . ',</p>'
                . '<p>We are still waiting for you to complete your Seller Africa vendor verification.</p>'
                . '<p>The world is waiting for your products. Finish your verification so we can review your store and help you get ready to sell authentic African and Caribbean goods to customers across the diaspora.</p>';

            self::enqueueEmail(
                [['email' => $email, 'name' => $name]],
                $subject,
                'The world is waiting for your products',
                $body,
                [
                    'button_text' => 'Complete Verification',
                    'button_url' => \app_url('vendor/kyc'),
                    'metadata' => [
                        'type' => 'vendor_verification_reminder',
                        'vendor_id' => $vendorId,
                        'status' => (string)($row['status'] ?? ''),
                        'kyc_status' => (string)($row['kyc_status'] ?? ''),
                    ],
                ]
            );

            if (function_exists('audit')) {
                \audit(
                    'vendor.verification_reminder_queued',
                    'vendors',
                    (string)$vendorId,
                    [],
                    ['email' => $email, 'kyc_status' => (string)($row['kyc_status'] ?? '')]
                );
            }

            $queued++;
        }

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    public static function notifyAdmins(string $subject, string $heading, string $body, array $options = []): void
    {
        self::notifyStaff($subject, $heading, $body, $options, false);
    }

    public static function notifySuperAdmins(string $subject, string $heading, string $body, array $options = []): void
    {
        self::notifyStaff($subject, $heading, $body, $options, true);
    }

    private static function notifyStaff(string $subject, string $heading, string $body, array $options, bool $superAdminsOnly): void
    {
        $roleCondition = $superAdminsOnly ? "r.code = 'super_admin'" : "r.code IN ('super_admin', 'admin')";
        $rows = [];
        try {
            $rows = \db()->fetchAll(
                "SELECT DISTINCT u.email, COALESCE(NULLIF(u.display_name, ''), u.username, u.email) AS name
                 FROM users u
                 INNER JOIN user_roles ur ON ur.user_id = u.id
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE {$roleCondition} AND u.status = 'active' AND u.email IS NOT NULL"
            );
        } catch (\Throwable $e) {
            error_log('Admin notification lookup failed: ' . $e->getMessage());
        }

        if ($rows !== []) {
            self::enqueueEmail($rows, $subject, $heading, $body, $options + ['tone' => 'admin']);
        }
    }

    public static function passwordReset(array $user, string $url, bool $legacy = false): void
    {
        $name = (string)($user['display_name'] ?: $user['username'] ?: 'there');
        $subject = $legacy ? 'Reset your Seller Africa password' : 'Your Seller Africa password reset link';
        $body = '<p>Hello ' . \e($name) . ',</p><p>' . ($legacy
            ? 'We have upgraded account security for migrated accounts. Please reset your password before signing in.'
            : 'Use the secure link below to reset your password. The link expires in 1 hour.') . '</p>';
        $options = ['button_text' => 'Reset Password', 'button_url' => $url];

        self::record('email', (string)$user['email'], $subject, strip_tags($body), $options);
        EmailService::send(
            [['email' => (string)$user['email'], 'name' => $name]],
            $subject,
            self::brandedHtml($legacy ? 'Password reset required' : 'Reset your password', $body, $options)
        );
    }

    public static function registrationCreated(array $user, string $accountType): void
    {
        if ($accountType !== 'vendor') {
            self::notifySuperAdmins(
                'New user sign up',
                'New user sign up',
                '<p>New user sign up.</p><p><strong>Email:</strong> ' . \e((string)$user['email']) . '</p>',
                ['button_text' => 'View Users', 'button_url' => \app_url('dashboard/users')]
            );
            return;
        }
        $buttonUrl = \app_url('dashboard/pending-vendors');
        $buttonText = 'Review Vendor';
        self::notifySuperAdmins(
            'New Seller Africa registration',
            'New account awaiting attention',
            '<p>A new ' . \e($accountType) . ' account registered.</p><p><strong>Email:</strong> ' . \e((string)$user['email']) . '</p>',
            ['button_text' => $buttonText, 'button_url' => $buttonUrl]
        );
    }

    public static function vendorApproved(int $vendorId): void
    {
        $user = \db()->fetch('SELECT u.email, u.display_name FROM vendors v JOIN users u ON u.id=v.user_id WHERE v.id=?', [$vendorId]);
        if (!$user || !filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL)) return;
        self::enqueueEmail($user['email'], 'Your Seller Africa store is approved', 'Welcome to your vendor dashboard', '<p>Hi ' . \e($user['display_name'] ?: 'there') . ', your vendor application has been approved. You can now access your vendor dashboard.</p>', ['button_text' => 'Open dashboard', 'button_url' => \app_url('vendor')]);
    }

    public static function vendorRejected(int $vendorId, string $reason = ''): void
    {
        $vendor = \db()->fetch(
            'SELECT v.store_name, u.email, u.display_name FROM vendors v JOIN users u ON u.id = v.user_id WHERE v.id = ?',
            [$vendorId]
        );
        if (!$vendor || !filter_var($vendor['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $body = '<p>Hi ' . \e(trim((string)$vendor['display_name']) ?: 'there') . ',</p>'
            . '<p>Thank you for applying to sell with Seller Africa. Your store, <strong>' . \e((string)$vendor['store_name']) . '</strong>, has not been approved and is not currently listed on our marketplace.</p>';
        if (trim($reason) !== '') {
            $body .= '<p><strong>Review feedback:</strong> ' . nl2br(\e(trim($reason))) . '</p>';
        }
        $body .= '<p>Please review the following common issues. These are general checks, not a finding that every item applies to your application:</p>'
            . '<ul><li>Check your store banner: it should be a clear landscape image that represents your business and meets the upload requirements.</li>'
            . '<li>Use a clear, appropriate store profile image and genuine product photos.</li>'
            . '<li>Do not display phone numbers, email addresses, website links or social media handles in store images or product descriptions. Use the designated contact fields.</li>'
            . '<li>Provide complete, accurate product descriptions, prices in USD, weights in kg and package dimensions.</li>'
            . '<li>Make sure your business and contact information is accurate, and avoid test or placeholder information.</li></ul>'
            . '<p>Please contact our support team for clarification and guidance on requesting another review. Updating your details does not automatically approve your store.</p>';
        self::enqueueEmail(
            [['email' => $vendor['email'], 'name' => $vendor['display_name']]],
            'Update on your Seller Africa vendor application',
            'Your store application was not approved',
            $body,
            ['button_text' => 'Contact Support', 'button_url' => \app_url('contact'), 'metadata' => ['type' => 'vendor_rejected', 'vendor_id' => $vendorId]]
        );
    }

    public static function cartUpdated(?array $user, array $item): void
    {
        if (!$user || empty($user['email'])) {
            return;
        }

        self::enqueueEmail(
            [['email' => (string)$user['email'], 'name' => (string)($user['display_name'] ?? '')]],
            'Your Seller Africa cart was updated',
            'Your cart is waiting',
            '<p>We saved your latest cart update. Your selected items will be ready when you return.</p>',
            ['button_text' => 'View Cart', 'button_url' => \app_url('cart'), 'metadata' => $item]
        );
    }

    public static function orderCreated(array $order, array $customer, array $items, array $payment = []): void
    {
        $itemList = self::itemList($items);
        $orderNumber = self::orderNumber($order);
        $total = (string)($order['total'] ?? '');
        $trackUrl = self::trackingUrl($order, (string)($order['tracking_url'] ?? ''));
        $customerName = trim((string)($customer['first_name'] ?? '') . ' ' . (string)($customer['last_name'] ?? ''));
        $customerEmail = (string)($customer['email'] ?? '');
        $paymentStatus = self::paymentStatusLabel((string)($payment['payment_status'] ?? $payment['status'] ?? $order['payment_status'] ?? 'payment_pending'));
        $summary = self::changeList(array_filter([
            'Order total' => $total,
            'Customer' => trim($customerName . ($customerEmail !== '' ? ' <' . $customerEmail . '>' : '')),
            'Payment method' => (string)($payment['name'] ?? $payment['code'] ?? ''),
            'Payment provider' => (string)($payment['provider'] ?? ''),
            'Payment status' => $paymentStatus,
        ], static fn ($value): bool => trim((string)$value) !== ''));

        self::notifyAdmins(
            'New Seller Africa sale: ' . $orderNumber,
            'New sale received',
            '<p>Order <strong>' . \e($orderNumber) . '</strong> was created.</p>' . $summary . $itemList,
            ['button_text' => 'Review Orders', 'button_url' => \app_url('dashboard/orders'), 'metadata' => ['order_number' => $orderNumber, 'type' => 'admin_new_purchase']]
        );

        self::notifyVendorsOfOrder($order, $customer, $payment);

        if (!empty($customer['email'])) {
            self::enqueueEmail(
                [['email' => (string)$customer['email'], 'name' => $customerName]],
                'Thank you for your Seller Africa purchase',
                'Thank you for your purchase',
                '<p>Thank you for shopping with us. Your order <strong>' . \e($orderNumber) . '</strong> has been received with payment status: <strong>' . \e($paymentStatus) . '</strong>.</p>'
                    . ($total !== '' ? '<p><strong>Order total:</strong> ' . \e($total) . '</p>' : '')
                    . $itemList
                    . '<p>You can use your order number to check the latest status at any time.</p>',
                ['button_text' => 'Track Order', 'button_url' => $trackUrl, 'metadata' => ['order_number' => $orderNumber, 'type' => 'purchase_thank_you']]
            );

            if (self::isPaymentOnDelivery($payment)) {
                self::enqueueEmail(
                    [['email' => (string)$customer['email'], 'name' => $customerName]],
                    'Payment on delivery for order ' . $orderNumber,
                    'Payment on delivery selected',
                    '<p>Your Seller Africa order <strong>' . \e($orderNumber) . '</strong> was placed with payment on delivery.</p>'
                        . ($total !== '' ? '<p>Please have <strong>' . \e($total) . '</strong> ready when your order arrives.</p>' : '<p>Please have your payment ready when your order arrives.</p>')
                        . '<p>We will keep you updated as the order moves through processing and delivery.</p>',
                    ['button_text' => 'Track Order', 'button_url' => $trackUrl, 'metadata' => ['order_number' => $orderNumber, 'type' => 'payment_on_delivery']]
                );
            }
        }
    }

    private static function notifyVendorsOfOrder(array $order, array $customer, array $payment = []): void
    {
        $orderId = (int)($order['id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        $rows = [];
        try {
            $rows = \db()->fetchAll(
                "SELECT
                    oi.vendor_id,
                    COALESCE(NULLIF(v.store_email, ''), u.email) AS vendor_email,
                    COALESCE(NULLIF(v.store_name, ''), NULLIF(u.display_name, ''), u.username, u.email) AS vendor_name,
                    oi.name,
                    oi.sku,
                    oi.quantity,
                    oi.unit_price,
                    oi.total,
                    o.currency
                 FROM order_items oi
                 INNER JOIN orders o ON o.id = oi.order_id
                 INNER JOIN vendors v ON v.id = oi.vendor_id
                 LEFT JOIN users u ON u.id = v.user_id
                 WHERE oi.order_id = ? AND oi.item_type = 'product' AND oi.vendor_id IS NOT NULL
                 ORDER BY v.store_name ASC, oi.id ASC",
                [$orderId]
            );
        } catch (\Throwable $e) {
            error_log('Vendor order notification lookup failed: ' . $e->getMessage());
            return;
        }

        $vendors = [];
        foreach ($rows as $row) {
            $email = trim((string)($row['vendor_email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $vendorId = (int)($row['vendor_id'] ?? 0);
            if (!isset($vendors[$vendorId])) {
                $vendors[$vendorId] = [
                    'email' => $email,
                    'name' => (string)($row['vendor_name'] ?? ''),
                    'currency' => (string)($row['currency'] ?? $order['currency'] ?? 'USD'),
                    'total' => 0.0,
                    'items' => [],
                ];
            }

            $lineTotal = (float)($row['total'] ?? 0);
            $vendors[$vendorId]['total'] += $lineTotal;
            $vendors[$vendorId]['items'][] = [
                'name' => (string)($row['name'] ?? 'Product'),
                'sku' => (string)($row['sku'] ?? ''),
                'quantity' => (float)($row['quantity'] ?? 1),
                'unit_price' => (float)($row['unit_price'] ?? 0),
                'total' => $lineTotal,
            ];
        }

        $orderNumber = self::orderNumber($order);
        $customerName = trim((string)($customer['first_name'] ?? '') . ' ' . (string)($customer['last_name'] ?? ''));
        $customerEmail = trim((string)($customer['email'] ?? ''));
        $paymentName = trim((string)($payment['name'] ?? $payment['code'] ?? ''));
        $paymentStatus = self::paymentStatusLabel((string)($payment['payment_status'] ?? $payment['status'] ?? $order['payment_status'] ?? 'payment_pending'));

        foreach ($vendors as $vendorId => $vendor) {
            $currency = (string)($vendor['currency'] ?: $order['currency'] ?? 'USD');
            $vendorTotal = self::money((float)$vendor['total'], $currency);
            $summary = self::changeList(array_filter([
                'Order number' => $orderNumber,
                'Your item total' => $vendorTotal,
                'Customer' => trim($customerName . ($customerEmail !== '' ? ' <' . $customerEmail . '>' : '')),
                'Payment method' => $paymentName,
                'Payment status' => $paymentStatus,
            ], static fn ($value): bool => trim((string)$value) !== ''));

            self::enqueueEmail(
                [['email' => (string)$vendor['email'], 'name' => (string)$vendor['name']]],
                'New purchase for your products: ' . $orderNumber,
                'Your product was purchased',
                '<p>A customer purchased product(s) from your Seller Africa store.</p>' . $summary . self::vendorItemList((array)$vendor['items'], $currency),
                [
                    'button_text' => 'Open Vendor Orders',
                    'button_url' => \app_url('vendor/orders'),
                    'metadata' => [
                        'order_id' => $orderId,
                        'order_number' => $orderNumber,
                        'vendor_id' => $vendorId,
                        'type' => 'vendor_new_purchase',
                    ],
                ]
            );
        }
    }

    public static function orderStatus(array $order, string $status, string $trackingNumber = '', string $trackingUrl = ''): void
    {
        $changes = ['Order status' => ucwords(str_replace('_', ' ', $status))];
        if ($trackingNumber !== '') {
            $changes['Tracking number'] = $trackingNumber;
        }

        self::orderUpdated($order, 'Your order is now ' . ucwords(str_replace('_', ' ', $status)), $changes, $trackingNumber, $trackingUrl);
    }

    public static function orderUpdated(array $order, string $summary = '', array $changes = [], string $trackingNumber = '', string $trackingUrl = ''): void
    {
        $email = (string)($order['email'] ?? '');
        if ($email === '') {
            return;
        }

        $orderNumber = self::orderNumber($order);
        $trackUrl = self::trackingUrl($order, $trackingUrl);
        $tracking = $trackingNumber !== ''
            ? '<p><strong>Tracking:</strong> ' . \e($trackingNumber) . '</p>'
            : '';
        $changeList = self::changeList($changes);
        $heading = $summary !== '' ? $summary : 'Your order was updated';

        self::enqueueEmail(
            [['email' => $email, 'name' => (string)($order['name'] ?? '')]],
            'Order update: ' . $orderNumber,
            $heading,
            '<p>Your Seller Africa order <strong>' . \e($orderNumber) . '</strong> has been updated.</p>' . $changeList . $tracking,
            ['button_text' => 'Track Order', 'button_url' => $trackUrl, 'metadata' => ['order_number' => $orderNumber, 'type' => 'order_update', 'changes' => $changes]]
        );
    }

    public static function productStatus(array $product, string $status): void
    {
        $email = (string)($product['vendor_email'] ?? '');
        if ($email === '') {
            return;
        }
        $displayStatus = $status === 'active' ? 'approved' : $status;

        self::enqueueEmail(
            [['email' => $email, 'name' => (string)($product['vendor_name'] ?? '')]],
            'Product ' . $displayStatus,
            'Product ' . ucwords(str_replace('_', ' ', $displayStatus)),
            '<p>Your product <strong>' . \e((string)$product['name']) . '</strong> has been <strong>' . \e($displayStatus) . '</strong>.</p>',
            ['button_text' => 'Open Vendor Dashboard', 'button_url' => \app_url('vendor/products'), 'metadata' => ['product_id' => (int)($product['id'] ?? 0), 'status' => $status]]
        );
    }

    public static function productPublished(array $product): void
    {
        $rows = [];
        try {
            $rows = \db()->fetchAll(
                "SELECT DISTINCT u.email, COALESCE(NULLIF(u.display_name, ''), u.username, u.email) AS name
                 FROM users u
                 INNER JOIN user_roles ur ON ur.user_id = u.id
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE r.code = 'customer' AND u.status = 'active' AND u.email_verified_at IS NOT NULL
                 ORDER BY u.last_login_at DESC, u.id DESC
                 LIMIT 200"
            );
        } catch (\Throwable $e) {
            error_log('Product broadcast lookup failed: ' . $e->getMessage());
        }

        if ($rows === []) {
            return;
        }

        $saleLine = ((float)($product['sale_price'] ?? 0) > 0)
            ? '<p>This product is currently on sale.</p>'
            : '<p>A new product is now available on the marketplace.</p>';

        self::enqueueEmail(
            $rows,
            'New on Seller Africa: ' . (string)$product['name'],
            'Fresh product just landed',
            '<p><strong>' . \e((string)$product['name']) . '</strong> is now live.</p>' . $saleLine,
            ['button_text' => 'Shop Now', 'button_url' => \app_url('product/' . urlencode((string)($product['slug'] ?? '')))]
        );
    }

    private static function brandedHtml(string $heading, string $body, array $options = []): string
    {
        $brand = \app_branding();
        $logo = \e((string)$brand['logo']);
        $name = \e((string)$brand['name']);
        $buttonText = trim((string)($options['button_text'] ?? 'Open Seller Africa'));
        $buttonUrl = trim((string)($options['button_url'] ?? \app_url('store')));

        return '<div style="margin:0;padding:28px;background:#f6f7f4;font-family:Inter,Arial,sans-serif;color:#172014">'
            . '<div style="max-width:680px;margin:0 auto;background:#fff;border:1px solid #e7eadf;border-radius:22px;overflow:hidden">'
            . '<div style="padding:24px 28px;background:#0b5d36;color:#fff"><img src="' . $logo . '" alt="' . $name . '" style="max-height:48px;display:block;margin-bottom:16px"><h1 style="margin:0;font-size:26px;line-height:1.2">' . \e($heading) . '</h1></div>'
            . '<div style="padding:28px;font-size:15px;line-height:1.7">' . $body
            . '<p style="margin:28px 0"><a href="' . \e($buttonUrl) . '" style="background:#f4c430;color:#17351f;text-decoration:none;border-radius:999px;padding:13px 22px;font-weight:800;display:inline-block">' . \e($buttonText) . '</a></p>'
            . '<p style="color:#6b7280;font-size:13px">With care,<br>' . $name . '</p></div></div></div>';
    }

    private static function record(string $channel, string $email, string $title, string $body, array $options = []): void
    {
        try {
            \db()->query(
                "INSERT INTO notifications (channel, recipient_email, title, body, status, priority, metadata, created_at)
                 VALUES (?, ?, ?, ?, 'pending', ?, ?, ?)",
                [
                    $channel,
                    $email,
                    $title,
                    $body,
                    (string)($options['priority'] ?? 'normal'),
                    json_encode($options['metadata'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    \sql_now(),
                ]
            );
        } catch (\Throwable $e) {
            error_log('Notification record failed: ' . $e->getMessage());
        }
    }

    private static function recipients(string|array $to): array
    {
        $items = is_array($to) ? $to : [['email' => $to, 'name' => '']];
        $recipients = [];
        foreach ($items as $item) {
            $email = is_array($item) ? trim((string)($item['email'] ?? '')) : trim((string)$item);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = ['email' => $email, 'name' => is_array($item) ? trim((string)($item['name'] ?? '')) : ''];
            }
        }

        return $recipients;
    }

    private static function itemList(array $items): string
    {
        if ($items === []) {
            return '';
        }

        $html = '<ul style="padding-left:18px">';
        foreach (array_slice($items, 0, 8) as $item) {
            $html .= '<li>' . \e((string)($item['name'] ?? 'Product')) . ' x ' . \e((string)($item['quantity'] ?? 1)) . '</li>';
        }
        return $html . '</ul>';
    }

    private static function vendorItemList(array $items, string $currency): string
    {
        if ($items === []) {
            return '';
        }

        $html = '<table style="width:100%;border-collapse:collapse;margin:18px 0;font-size:14px">'
            . '<thead><tr>'
            . '<th align="left" style="border-bottom:1px solid #e5e7eb;padding:8px">Product</th>'
            . '<th align="right" style="border-bottom:1px solid #e5e7eb;padding:8px">Qty</th>'
            . '<th align="right" style="border-bottom:1px solid #e5e7eb;padding:8px">Total</th>'
            . '</tr></thead><tbody>';

        foreach ($items as $item) {
            $sku = trim((string)($item['sku'] ?? ''));
            $name = (string)($item['name'] ?? 'Product');
            $quantity = (float)($item['quantity'] ?? 1);
            $html .= '<tr>'
                . '<td style="border-bottom:1px solid #f1f5f9;padding:8px"><strong>' . \e($name) . '</strong>' . ($sku !== '' ? '<br><span style="color:#6b7280">SKU: ' . \e($sku) . '</span>' : '') . '</td>'
                . '<td align="right" style="border-bottom:1px solid #f1f5f9;padding:8px">' . \e(rtrim(rtrim(number_format($quantity, 4), '0'), '.')) . '</td>'
                . '<td align="right" style="border-bottom:1px solid #f1f5f9;padding:8px">' . \e(self::money((float)($item['total'] ?? 0), $currency)) . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table>';
    }

    private static function changeList(array $changes): string
    {
        if ($changes === []) {
            return '';
        }

        $html = '<ul style="padding-left:18px">';
        foreach ($changes as $label => $value) {
            $html .= '<li><strong>' . \e((string)$label) . ':</strong> ' . \e((string)$value) . '</li>';
        }

        return $html . '</ul>';
    }

    private static function orderNumber(array $order): string
    {
        return (string)($order['order_number'] ?? $order['number'] ?? $order['id'] ?? '');
    }

    private static function money(float $amount, string $currency): string
    {
        if (class_exists(StorefrontTemplateService::class)) {
            return StorefrontTemplateService::money($amount, $currency);
        }

        return strtoupper($currency) . ' ' . number_format($amount, 2);
    }

    private static function trackingUrl(array $order, string $fallback = ''): string
    {
        if ($fallback !== '') {
            return $fallback;
        }

        $orderNumber = self::orderNumber($order);
        return \app_url('track-order' . ($orderNumber !== '' ? '?order=' . rawurlencode($orderNumber) : ''));
    }

    private static function isPaymentOnDelivery(array $payment): bool
    {
        $haystack = strtolower(trim(implode(' ', [
            (string)($payment['code'] ?? ''),
            (string)($payment['name'] ?? ''),
            (string)($payment['method'] ?? ''),
            (string)($payment['provider'] ?? ''),
        ])));

        return str_contains($haystack, 'cash_on_delivery')
            || str_contains($haystack, 'payment_on_delivery')
            || str_contains($haystack, 'pay_on_delivery')
            || str_contains($haystack, 'cash on delivery')
            || str_contains($haystack, 'payment on delivery')
            || str_contains($haystack, 'pay on delivery');
    }

    private static function paymentStatusLabel(string $status): string
    {
        $status = strtolower(trim($status));
        return match ($status) {
            'paid', 'authorized' => 'Paid',
            'failed', 'payment_failed' => 'Payment Failed',
            'cancelled', 'canceled', 'payment_cancelled' => 'Payment Cancelled',
            'under_review', 'payment_under_review' => 'Payment Under Review',
            'unpaid', 'pending', 'payment_pending', '' => 'Pending Payment',
            default => ucwords(str_replace('_', ' ', $status)),
        };
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
