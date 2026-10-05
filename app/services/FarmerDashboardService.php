<?php

declare(strict_types=1);

namespace App;

final class FarmerDashboardService
{
    private const SECTIONS = [
        'home' => ['label' => 'Home', 'items' => ['Overview', 'New orders', 'Fulfillment alerts', 'Inventory alerts', 'Weekly sales', 'Payouts', 'Quick actions']],
        'produce' => ['label' => 'Produce', 'items' => ['My Produce', 'Add Produce', 'Available This Week', 'Seasonal Produce', 'Sold Out / Paused']],
        'orders' => ['label' => 'Orders', 'items' => ['New', 'Accepted', 'Preparing', 'Ready', 'Shipped', 'Delivered', 'Cancelled', 'Refunded']],
        'inventory' => ['label' => 'Inventory', 'items' => ['Current Stock', 'Low Stock', 'Sold Out', 'Bulk Update', 'Seasonal Availability']],
        'fulfillment' => ['label' => 'Fulfillment', 'items' => ['Shipping', 'Local Delivery', 'Farm Pickup', 'Preparation Time', 'Delivery Areas']],
        'earnings' => ['label' => 'Earnings', 'items' => ['Overview', 'Pending Payouts', 'Payout History', 'Transactions', 'Fees & Refunds']],
        'customers' => ['label' => 'Customers', 'items' => ['Customers', 'Repeat Buyers', 'Customer Order History']],
        'reviews' => ['label' => 'Reviews', 'items' => ['Product Reviews', 'Farm Reviews', 'Responses']],
        'profile' => ['label' => 'Farm Profile', 'items' => ['Public Profile', 'Farm Story', 'Photos', 'Crops', 'Practices', 'Certifications', 'Delivery Areas']],
        'verification' => ['label' => 'Verification', 'items' => ['Verification Status', 'Business/Farm Details', 'Documents', 'Certifications']],
        'settings' => ['label' => 'Settings', 'items' => ['Store Settings', 'Notifications', 'Policies', 'Vacation Mode', 'Pickup/Shipping Defaults']],
        'support' => ['label' => 'Support', 'items' => ['Help Centre', 'Order Issues', 'Selling Guide', 'Marketplace Policies', 'Contact Support']],
    ];
    private const FARM_CATEGORIES = [
        'Grass-Fed Beef',
        'Chicken',
        'Pasture Eggs',
        'Raw Dairy',
        'Pasture Pork',
        'Lamb',
        'Vegetables',
        'Fruit',
        'Raw Honey',
        'Baked Goods',
        'Bison',
        'Duck',
        'Turkey',
        'Elk',
        'Seafood',
        'Herbs & Spices',
        'Jams & Preserves',
        'Skincare',
        'Jerky',
    ];

    public static function page(string $active = 'home'): void
    {
        $active = array_key_exists($active, self::SECTIONS) ? $active : 'home';
        self::requireAccess();

        $user = AuthService::user();
        $vendor = self::farmerVendor((int)($user['id'] ?? 0));
        if (!$vendor) {
            \render_restricted_page(403, 'FreshRoots access needed', 'This dashboard is for approved FreshRoots farmer accounts. Please go home or open your regular dashboard.', ['route' => 'farmer/' . $active, 'reason' => 'not_farmer_account']);
            return;
        }
        self::ensureOperationalSchema();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            self::handlePost($vendor);
            \redirect($active === 'home' ? 'farmer' : 'farmer/' . $active);
        }

        try {
            \audit('farmer_dashboard_viewed', 'vendors', (string)$vendor['id'], [], ['section' => $active], (int)($user['id'] ?? 0));
        } catch (\Throwable) {
        }

        $metrics = self::metrics((int)$vendor['id']);
        $application = self::application((int)$vendor['id']);
        $applicationData = self::decodeApplication($application['application_data'] ?? null);

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>FreshRoots Dashboard - ' . \e((string)($vendor['store_name'] ?? 'Farmer')) . '</title>';
        echo '<style>' . self::css() . '</style></head><body>';
        echo '<div class="ff-shell">';
        self::sidebar($active);
        echo '<main class="ff-main">';
        self::topbar($vendor);
        if ($active === 'home') {
            self::home($vendor, $metrics, $applicationData);
        } else {
            self::sectionPage($active, $vendor, $metrics, $applicationData);
        }
        echo '</main></div>';
        echo '<script>' . self::js() . '</script>';
        echo '</body></html>';
    }

    private static function requireAccess(): void
    {
        if (!AuthService::check()) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? \app_url('farmer');
            \redirect('login');
        }

        $roles = array_map('strval', (array)($_SESSION['roles'] ?? []));
        $allowed = in_array('vendor', $roles, true) || in_array('admin', $roles, true) || in_array('super_admin', $roles, true);
        if (!$allowed) {
            \render_restricted_page(403, 'FreshRoots access needed', 'This dashboard is for farmer sellers and the Seller Africa admin team.', ['route' => 'farmer', 'reason' => 'role_not_allowed']);
            exit;
        }
    }

    private static function farmerVendor(int $userId): ?array
    {
        $roles = array_map('strval', (array)($_SESSION['roles'] ?? []));
        $isAdmin = in_array('admin', $roles, true) || in_array('super_admin', $roles, true);
        $params = [];
        $where = 'va.application_type = "farmer"';

        if ($isAdmin && (int)($_GET['vendor_id'] ?? 0) > 0) {
            $where .= ' AND v.id = ?';
            $params[] = (int)$_GET['vendor_id'];
        } elseif (!$isAdmin) {
            $where .= ' AND v.user_id = ?';
            $params[] = $userId;
        }

        try {
            $sql = "SELECT v.*, va.submitted_at farmer_submitted_at
                    FROM vendors v
                    INNER JOIN vendor_applications va ON va.vendor_id = v.id
                    WHERE {$where}
                    ORDER BY va.submitted_at DESC, v.id DESC
                    LIMIT 1";
            $vendor = \db()->fetch($sql, $params);
        } catch (\Throwable) {
            $vendor = null;
        }

        return is_array($vendor) ? $vendor : null;
    }

    private static function ensureOperationalSchema(): void
    {
        \db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS farmer_store_settings (
                vendor_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                harvest_available TINYINT(1) NOT NULL DEFAULT 1,
                harvest_note VARCHAR(255) NULL,
                vacation_mode TINYINT(1) NOT NULL DEFAULT 0,
                vacation_message VARCHAR(255) NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_farmer_store_settings_available (harvest_available)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        \db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS farmer_fulfillment_settings (
                vendor_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                shipping_enabled TINYINT(1) NOT NULL DEFAULT 0,
                seller_africa_delivery_enabled TINYINT(1) NOT NULL DEFAULT 0,
                farmer_local_delivery_enabled TINYINT(1) NOT NULL DEFAULT 0,
                farm_pickup_enabled TINYINT(1) NOT NULL DEFAULT 0,
                default_carrier VARCHAR(120) NULL,
                shipping_fee DECIMAL(19,4) NULL,
                free_shipping_threshold DECIMAL(19,4) NULL,
                shipping_preparation_time VARCHAR(120) NULL,
                states_regions_served TEXT NULL,
                tracking_required TINYINT(1) NOT NULL DEFAULT 1,
                delivery_radius VARCHAR(80) NULL,
                delivery_areas TEXT NULL,
                delivery_fee DECIMAL(19,4) NULL,
                free_delivery_threshold DECIMAL(19,4) NULL,
                minimum_delivery_order DECIMAL(19,4) NULL,
                delivery_days VARCHAR(255) NULL,
                delivery_times VARCHAR(255) NULL,
                pickup_location VARCHAR(255) NULL,
                pickup_instructions TEXT NULL,
                pickup_days VARCHAR(255) NULL,
                pickup_hours VARCHAR(255) NULL,
                pickup_preparation_time VARCHAR(120) NULL,
                customer_instructions TEXT NULL,
                expose_private_address TINYINT(1) NOT NULL DEFAULT 0,
                default_preparation_time ENUM('same_day','1_day','2_days','custom') NOT NULL DEFAULT '1_day',
                custom_preparation_time VARCHAR(120) NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_farmer_fulfillment_methods (shipping_enabled, farmer_local_delivery_enabled, farm_pickup_enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        foreach ([
            'vacation_mode TINYINT(1) NOT NULL DEFAULT 0 AFTER harvest_note',
            'vacation_message VARCHAR(255) NULL AFTER vacation_mode',
        ] as $definition) {
            [$column] = explode(' ', $definition, 2);
            if (!self::columnExists('farmer_store_settings', $column)) {
                \db()->pdo()->exec('ALTER TABLE farmer_store_settings ADD ' . $definition);
            }
        }
        \db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS farmer_weekly_availability (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                vendor_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                available_this_week TINYINT(1) NOT NULL DEFAULT 0,
                available_quantity DECIMAL(12,2) NULL,
                selling_unit VARCHAR(40) NULL,
                freshness_ends_at DATETIME NULL,
                note VARCHAR(255) NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_farmer_week_product (vendor_id, product_id),
                INDEX idx_farmer_weekly_vendor_available (vendor_id, available_this_week),
                INDEX idx_farmer_weekly_freshness (freshness_ends_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        \db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS farmer_produce_details (
                product_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                vendor_id BIGINT UNSIGNED NOT NULL,
                farm_location VARCHAR(190) NULL,
                selling_unit VARCHAR(40) NOT NULL DEFAULT 'lb',
                minimum_order_quantity DECIMAL(12,2) NULL,
                bulk_pricing TEXT NULL,
                low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 5,
                maximum_quantity_per_order INT UNSIGNED NULL,
                harvest_date DATE NULL,
                freshness_window_days INT UNSIGNED NULL,
                best_before_date DATE NULL,
                organic_claim TINYINT(1) NOT NULL DEFAULT 0,
                farming_method VARCHAR(120) NULL,
                variety VARCHAR(120) NULL,
                quality_grade VARCHAR(80) NULL,
                certifications TEXT NULL,
                shipping_available TINYINT(1) NOT NULL DEFAULT 0,
                local_delivery_available TINYINT(1) NOT NULL DEFAULT 0,
                farm_pickup_available TINYINT(1) NOT NULL DEFAULT 0,
                preparation_time VARCHAR(120) NULL,
                delivery_areas TEXT NULL,
                seasonal_mode ENUM('year_round','seasonal') NOT NULL DEFAULT 'year_round',
                seasonal_start_month TINYINT UNSIGNED NULL,
                seasonal_end_month TINYINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_farmer_produce_vendor (vendor_id),
                INDEX idx_farmer_produce_harvest (harvest_date),
                INDEX idx_farmer_produce_season (seasonal_mode, seasonal_start_month, seasonal_end_month)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        \db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS farmer_order_lifecycle (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                vendor_split_id BIGINT UNSIGNED NOT NULL,
                order_id BIGINT UNSIGNED NOT NULL,
                vendor_id BIGINT UNSIGNED NOT NULL,
                stage ENUM('new','accepted','preparing','ready_pickup','ready_delivery','shipped','delivered','cancelled','refunded','problem_reported','refund_requested') NOT NULL DEFAULT 'new',
                note TEXT NULL,
                tracking_number VARCHAR(190) NULL,
                tracking_url VARCHAR(500) NULL,
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_farmer_order_lifecycle_split (vendor_split_id, created_at),
                INDEX idx_farmer_order_lifecycle_vendor_stage (vendor_id, stage, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        \db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS farmer_order_notes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                vendor_split_id BIGINT UNSIGNED NOT NULL,
                order_id BIGINT UNSIGNED NOT NULL,
                vendor_id BIGINT UNSIGNED NOT NULL,
                note TEXT NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_farmer_order_notes_split (vendor_split_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        \db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS farmer_profile_details (
                vendor_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                farm_story TEXT NULL,
                farming_practices TEXT NULL,
                crops_grown TEXT NULL,
                certifications TEXT NULL,
                delivery_areas TEXT NULL,
                public_contact_email VARCHAR(190) NULL,
                public_contact_phone VARCHAR(80) NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        \db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS farmer_review_responses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                review_id BIGINT UNSIGNED NOT NULL,
                vendor_id BIGINT UNSIGNED NOT NULL,
                response TEXT NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_farmer_review_response (review_id, vendor_id),
                INDEX idx_farmer_review_responses_vendor (vendor_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        \db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS farmer_support_requests (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                vendor_id BIGINT UNSIGNED NOT NULL,
                subject VARCHAR(190) NOT NULL,
                category VARCHAR(80) NOT NULL DEFAULT 'general',
                priority ENUM('normal','urgent') NOT NULL DEFAULT 'normal',
                message TEXT NOT NULL,
                status ENUM('open','in_review','resolved') NOT NULL DEFAULT 'open',
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                INDEX idx_farmer_support_vendor_status (vendor_id, status, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        foreach ([
            'available_quantity DECIMAL(12,2) NULL AFTER available_this_week',
            'selling_unit VARCHAR(40) NULL AFTER available_quantity',
        ] as $definition) {
            [$column] = explode(' ', $definition, 2);
            if (!self::columnExists('farmer_weekly_availability', $column)) {
                \db()->pdo()->exec('ALTER TABLE farmer_weekly_availability ADD ' . $definition);
            }
        }
        if (\table_exists('products') && !self::columnExists('products', 'low_stock_threshold')) {
            \db()->pdo()->exec('ALTER TABLE products ADD low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 5 AFTER manage_stock');
        }
    }

    private static function handlePost(array $vendor): void
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
        if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            \flash('error', 'Your dashboard session expired. Please refresh and try again.');
            return;
        }

        $intent = (string)($_POST['intent'] ?? '');
        try {
            if ($intent === 'order_action') {
                self::orderAction((int)$vendor['id']);
            } elseif ($intent === 'harvest_status') {
                self::saveHarvestStatus((int)$vendor['id']);
            } elseif ($intent === 'available_this_week') {
                self::saveAvailableThisWeek((int)$vendor['id']);
            } elseif ($intent === 'add_produce') {
                self::saveProduce($vendor, AuthService::user(), false);
            } elseif ($intent === 'save_produce_draft') {
                self::saveProduce($vendor, AuthService::user(), true);
            } elseif ($intent === 'produce_action') {
                self::produceAction((int)$vendor['id']);
            } elseif ($intent === 'farmer_order_action') {
                self::farmerOrderAction((int)$vendor['id']);
            } elseif ($intent === 'farmer_order_note') {
                self::saveOrderNote((int)$vendor['id']);
            } elseif ($intent === 'fulfillment_settings') {
                self::saveFulfillmentSettings((int)$vendor['id']);
            } elseif ($intent === 'payout_request') {
                self::savePayoutRequest((int)$vendor['id']);
            } elseif ($intent === 'farmer_profile') {
                self::saveFarmerProfile((int)$vendor['id']);
            } elseif ($intent === 'farmer_settings') {
                self::saveFarmerSettings((int)$vendor['id']);
            } elseif ($intent === 'review_response') {
                self::saveReviewResponse((int)$vendor['id']);
            } elseif ($intent === 'support_request') {
                self::saveSupportRequest((int)$vendor['id']);
            }
        } catch (\Throwable $e) {
            \error_log('Farmer dashboard action failed: ' . $e->getMessage());
            \flash('error', 'That farmer dashboard action could not be saved. Please try again.');
        }
    }

    private static function orderAction(int $vendorId): void
    {
        $splitId = (int)($_POST['split_id'] ?? 0);
        $action = (string)($_POST['order_action'] ?? '');
        $status = match ($action) {
            'accept', 'start_preparing', 'mark_ready' => 'processing',
            'mark_shipped' => 'shipped',
            default => '',
        };
        if ($splitId <= 0 || $status === '') {
            throw new \RuntimeException('Invalid order action.');
        }

        $split = \db()->fetch('SELECT id, status FROM order_vendor_splits WHERE id = ? AND vendor_id = ? LIMIT 1', [$splitId, $vendorId]);
        if (!$split) {
            throw new \RuntimeException('Order not found for farmer.');
        }

        \db()->query('UPDATE order_vendor_splits SET status = ?, updated_at = ? WHERE id = ?', [$status, \sql_now(), $splitId]);
        \audit('farmer_order_action', 'order_vendor_splits', (string)$splitId, $split, ['vendor_id' => $vendorId, 'action' => $action, 'status' => $status]);
        \flash('success', 'Order action saved.');
    }

    private static function farmerOrderAction(int $vendorId): void
    {
        $splitId = max(0, (int)($_POST['split_id'] ?? 0));
        $action = (string)($_POST['order_action'] ?? '');
        $note = trim((string)($_POST['note'] ?? ''));
        $trackingNumber = trim((string)($_POST['tracking_number'] ?? ''));
        $trackingUrl = trim((string)($_POST['tracking_url'] ?? ''));

        $split = \db()->fetch('SELECT * FROM order_vendor_splits WHERE id = ? AND vendor_id = ? LIMIT 1', [$splitId, $vendorId]);
        if (!$split) {
            throw new \RuntimeException('Order not found for this farmer.');
        }

        $stage = match ($action) {
            'accept' => 'accepted',
            'start_preparing' => 'preparing',
            'mark_ready_pickup' => 'ready_pickup',
            'mark_ready_delivery' => 'ready_delivery',
            'mark_shipped', 'add_tracking' => 'shipped',
            'mark_delivered' => 'delivered',
            'cancel', 'reject' => 'cancelled',
            'report_problem' => 'problem_reported',
            'request_refund' => 'refund_requested',
            default => '',
        };
        $splitStatus = match ($stage) {
            'accepted', 'preparing', 'ready_pickup', 'ready_delivery', 'problem_reported', 'refund_requested' => 'processing',
            'shipped' => 'shipped',
            'delivered' => 'completed',
            'cancelled' => 'cancelled',
            default => '',
        };
        if ($stage === '' || $splitStatus === '') {
            throw new \RuntimeException('Invalid farmer order action.');
        }

        \db()->beginTransaction();
        try {
            \db()->query('UPDATE order_vendor_splits SET status = ?, updated_at = ? WHERE id = ?', [$splitStatus, \sql_now(), $splitId]);
            if ($splitStatus === 'cancelled' && (string)$split['status'] !== 'cancelled' && class_exists(InventoryService::class)) {
                InventoryService::restoreVendorSplit($splitId, (int)($_SESSION['user_id'] ?? 0) ?: null);
            }
            self::recordOrderLifecycle($split, $vendorId, $stage, $note, $trackingNumber, $trackingUrl);
            if ($trackingNumber !== '' || $trackingUrl !== '') {
                self::saveShipmentTracking((int)$split['order_id'], $vendorId, $trackingNumber, $trackingUrl, $splitStatus);
            }
            \db()->commit();
        } catch (\Throwable $e) {
            if (\db()->pdo()->inTransaction()) {
                \db()->rollBack();
            }
            throw $e;
        }

        \audit('farmer_order_lifecycle_updated', 'order_vendor_splits', (string)$splitId, ['status' => $split['status']], ['vendor_id' => $vendorId, 'action' => $action, 'stage' => $stage, 'status' => $splitStatus]);
        \flash('success', 'Order lifecycle updated.');
    }

    private static function saveOrderNote(int $vendorId): void
    {
        $splitId = max(0, (int)($_POST['split_id'] ?? 0));
        $note = trim((string)($_POST['note'] ?? ''));
        if ($note === '') {
            throw new \RuntimeException('Order note is required.');
        }
        $split = \db()->fetch('SELECT * FROM order_vendor_splits WHERE id = ? AND vendor_id = ? LIMIT 1', [$splitId, $vendorId]);
        if (!$split) {
            throw new \RuntimeException('Order not found for this farmer.');
        }
        \db()->query(
            'INSERT INTO farmer_order_notes (vendor_split_id, order_id, vendor_id, note, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$splitId, (int)$split['order_id'], $vendorId, $note, (int)($_SESSION['user_id'] ?? 0) ?: null, \sql_now()]
        );
        self::recordOrderLifecycle($split, $vendorId, 'problem_reported', 'Internal note added: ' . $note);
        \flash('success', 'Internal order note saved.');
    }

    private static function recordOrderLifecycle(array $split, int $vendorId, string $stage, string $note = '', string $trackingNumber = '', string $trackingUrl = ''): void
    {
        \db()->query(
            'INSERT INTO farmer_order_lifecycle (vendor_split_id, order_id, vendor_id, stage, note, tracking_number, tracking_url, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int)$split['id'],
                (int)$split['order_id'],
                $vendorId,
                $stage,
                $note !== '' ? $note : null,
                $trackingNumber !== '' ? $trackingNumber : null,
                $trackingUrl !== '' ? $trackingUrl : null,
                (int)($_SESSION['user_id'] ?? 0) ?: null,
                \sql_now(),
            ]
        );
    }

    private static function saveShipmentTracking(int $orderId, int $vendorId, string $trackingNumber, string $trackingUrl, string $splitStatus): void
    {
        if (!\table_exists('shipments')) {
            return;
        }
        $shipmentStatus = $splitStatus === 'completed' ? 'delivered' : 'in_transit';
        $shipment = \db()->fetch('SELECT id FROM shipments WHERE order_id = ? AND vendor_id = ? LIMIT 1', [$orderId, $vendorId]);
        if ($shipment) {
            \db()->query('UPDATE shipments SET tracking_number = ?, tracking_url = ?, status = ?, updated_at = ? WHERE id = ?', [$trackingNumber, $trackingUrl, $shipmentStatus, \sql_now(), (int)$shipment['id']]);
            return;
        }
        \db()->query(
            'INSERT INTO shipments (order_id, vendor_id, tracking_number, tracking_url, status, shipped_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$orderId, $vendorId, $trackingNumber, $trackingUrl, $shipmentStatus, \sql_now(), \sql_now(), \sql_now()]
        );
    }

    private static function saveHarvestStatus(int $vendorId): void
    {
        $available = (string)($_POST['harvest_available'] ?? '0') === '1' ? 1 : 0;
        $note = trim(substr((string)($_POST['harvest_note'] ?? ''), 0, 255));
        \db()->query(
            'INSERT INTO farmer_store_settings (vendor_id, harvest_available, harvest_note, updated_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE harvest_available = VALUES(harvest_available), harvest_note = VALUES(harvest_note), updated_at = VALUES(updated_at)',
            [$vendorId, $available, $note !== '' ? $note : null, \sql_now()]
        );
        \audit('farmer_harvest_status_updated', 'vendors', (string)$vendorId, [], ['harvest_available' => $available, 'note' => $note]);
        \flash('success', $available ? 'Harvest is marked available on FreshRoots.' : 'Harvest is marked not currently available on FreshRoots.');
    }

    private static function saveFulfillmentSettings(int $vendorId): void
    {
        $prep = self::cleanOption((string)($_POST['default_preparation_time'] ?? '1_day'), ['same_day', '1_day', '2_days', 'custom'], '1_day');
        \db()->query(
            'INSERT INTO farmer_fulfillment_settings
             (vendor_id, shipping_enabled, seller_africa_delivery_enabled, farmer_local_delivery_enabled, farm_pickup_enabled, default_carrier, shipping_fee, free_shipping_threshold, shipping_preparation_time, states_regions_served, tracking_required, delivery_radius, delivery_areas, delivery_fee, free_delivery_threshold, minimum_delivery_order, delivery_days, delivery_times, pickup_location, pickup_instructions, pickup_days, pickup_hours, pickup_preparation_time, customer_instructions, expose_private_address, default_preparation_time, custom_preparation_time, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE shipping_enabled = VALUES(shipping_enabled), seller_africa_delivery_enabled = VALUES(seller_africa_delivery_enabled), farmer_local_delivery_enabled = VALUES(farmer_local_delivery_enabled), farm_pickup_enabled = VALUES(farm_pickup_enabled), default_carrier = VALUES(default_carrier), shipping_fee = VALUES(shipping_fee), free_shipping_threshold = VALUES(free_shipping_threshold), shipping_preparation_time = VALUES(shipping_preparation_time), states_regions_served = VALUES(states_regions_served), tracking_required = VALUES(tracking_required), delivery_radius = VALUES(delivery_radius), delivery_areas = VALUES(delivery_areas), delivery_fee = VALUES(delivery_fee), free_delivery_threshold = VALUES(free_delivery_threshold), minimum_delivery_order = VALUES(minimum_delivery_order), delivery_days = VALUES(delivery_days), delivery_times = VALUES(delivery_times), pickup_location = VALUES(pickup_location), pickup_instructions = VALUES(pickup_instructions), pickup_days = VALUES(pickup_days), pickup_hours = VALUES(pickup_hours), pickup_preparation_time = VALUES(pickup_preparation_time), customer_instructions = VALUES(customer_instructions), expose_private_address = VALUES(expose_private_address), default_preparation_time = VALUES(default_preparation_time), custom_preparation_time = VALUES(custom_preparation_time), updated_at = VALUES(updated_at)',
            [
                $vendorId,
                self::checkbox('shipping_enabled'),
                self::checkbox('seller_africa_delivery_enabled'),
                self::checkbox('farmer_local_delivery_enabled'),
                self::checkbox('farm_pickup_enabled'),
                self::nullableText($_POST['default_carrier'] ?? null, 120),
                self::nullableFloat($_POST['shipping_fee'] ?? null),
                self::nullableFloat($_POST['free_shipping_threshold'] ?? null),
                self::nullableText($_POST['shipping_preparation_time'] ?? null, 120),
                self::nullableText($_POST['states_regions_served'] ?? null, 2000),
                self::checkbox('tracking_required'),
                self::nullableText($_POST['delivery_radius'] ?? null, 80),
                self::nullableText($_POST['delivery_areas'] ?? null, 2000),
                self::nullableFloat($_POST['delivery_fee'] ?? null),
                self::nullableFloat($_POST['free_delivery_threshold'] ?? null),
                self::nullableFloat($_POST['minimum_delivery_order'] ?? null),
                self::nullableText($_POST['delivery_days'] ?? null, 255),
                self::nullableText($_POST['delivery_times'] ?? null, 255),
                self::nullableText($_POST['pickup_location'] ?? null, 255),
                self::nullableText($_POST['pickup_instructions'] ?? null, 2000),
                self::nullableText($_POST['pickup_days'] ?? null, 255),
                self::nullableText($_POST['pickup_hours'] ?? null, 255),
                self::nullableText($_POST['pickup_preparation_time'] ?? null, 120),
                self::nullableText($_POST['customer_instructions'] ?? null, 2000),
                self::checkbox('expose_private_address'),
                $prep,
                self::nullableText($_POST['custom_preparation_time'] ?? null, 120),
                \sql_now(),
            ]
        );

        $vacation = self::checkbox('vacation_mode');
        $message = self::nullableText($_POST['vacation_message'] ?? null, 255);
        \db()->query(
            'INSERT INTO farmer_store_settings (vendor_id, harvest_available, harvest_note, vacation_mode, vacation_message, updated_at)
             VALUES (?, 1, NULL, ?, ?, ?)
             ON DUPLICATE KEY UPDATE vacation_mode = VALUES(vacation_mode), vacation_message = VALUES(vacation_message), updated_at = VALUES(updated_at)',
            [$vendorId, $vacation, $message, \sql_now()]
        );

        \audit('farmer_fulfillment_settings_updated', 'vendors', (string)$vendorId, [], ['vacation_mode' => $vacation]);
        \flash('success', 'Fulfillment settings saved.');
    }

    private static function savePayoutRequest(int $vendorId): void
    {
        if (!\table_exists('vendor_withdrawals')) {
            throw new \RuntimeException('Payout requests are not available yet.');
        }

        $amount = (float)($_POST['amount'] ?? 0);
        $method = trim(substr((string)($_POST['method'] ?? 'bank_transfer'), 0, 80));
        $note = self::nullableText($_POST['note'] ?? null, 2000);
        $balances = self::balances($vendorId);

        if ($amount <= 0 || $amount > (float)$balances['available_balance']) {
            throw new \RuntimeException('Enter a payout amount within your available balance.');
        }

        \db()->query(
            'INSERT INTO vendor_withdrawals (vendor_id, wallet_id, amount, currency, method, status, note, requested_at) VALUES (?, NULL, ?, "USD", ?, "pending", ?, ?)',
            [$vendorId, $amount, $method !== '' ? $method : 'bank_transfer', $note, \sql_now()]
        );
        $withdrawalId = (int)\db()->lastInsertId();
        \audit('farmer_payout_requested', 'vendor_withdrawals', (string)$withdrawalId, [], ['vendor_id' => $vendorId, 'amount' => $amount, 'method' => $method]);
        \flash('success', 'Payout request submitted for review.');
    }

    private static function saveFarmerProfile(int $vendorId): void
    {
        $storeName = self::nullableText($_POST['store_name'] ?? null, 190);
        $storeEmail = self::nullableText($_POST['store_email'] ?? null, 190);
        $storePhone = self::nullableText($_POST['store_phone'] ?? null, 80);
        $description = self::nullableText($_POST['farm_story'] ?? null, 5000);
        if ($storeName === null) {
            throw new \RuntimeException('Farm name is required.');
        }
        if ($storeEmail !== null && !filter_var($storeEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Public contact email is invalid.');
        }

        \db()->query(
            'UPDATE vendors SET store_name = ?, store_email = ?, store_phone = ?, description = ?, updated_at = ? WHERE id = ?',
            [$storeName, $storeEmail, $storePhone, $description, \sql_now(), $vendorId]
        );
        \db()->query(
            'INSERT INTO farmer_profile_details (vendor_id, farm_story, farming_practices, crops_grown, certifications, delivery_areas, public_contact_email, public_contact_phone, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE farm_story = VALUES(farm_story), farming_practices = VALUES(farming_practices), crops_grown = VALUES(crops_grown), certifications = VALUES(certifications), delivery_areas = VALUES(delivery_areas), public_contact_email = VALUES(public_contact_email), public_contact_phone = VALUES(public_contact_phone), updated_at = VALUES(updated_at)',
            [
                $vendorId,
                $description,
                self::nullableText($_POST['farming_practices'] ?? null, 5000),
                self::nullableText($_POST['crops_grown'] ?? null, 2000),
                self::nullableText($_POST['certifications'] ?? null, 2000),
                self::nullableText($_POST['delivery_areas'] ?? null, 2000),
                $storeEmail,
                $storePhone,
                \sql_now(),
            ]
        );
        \audit('farmer_profile_updated', 'vendors', (string)$vendorId, [], ['store_name' => $storeName]);
        \flash('success', 'Farm profile saved.');
    }

    private static function saveFarmerSettings(int $vendorId): void
    {
        $notificationEmail = self::nullableText($_POST['notification_email'] ?? null, 190);
        if ($notificationEmail !== null && !filter_var($notificationEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Notification email is invalid.');
        }
        if (\table_exists('settings')) {
            $selectedCategories = array_values(array_intersect(self::FARM_CATEGORIES, array_map('strval', (array)($_POST['farm_categories'] ?? []))));
            if (count($selectedCategories) > 3) {
                throw new \RuntimeException('Please choose up to 3 farm categories.');
            }
            foreach ([
                'notification_email' => $notificationEmail,
                'order_notifications' => self::checkbox('order_notifications'),
                'review_notifications' => self::checkbox('review_notifications'),
                'low_stock_notifications' => self::checkbox('low_stock_notifications'),
                'store_policy' => self::nullableText($_POST['store_policy'] ?? null, 3000),
                'return_policy' => self::nullableText($_POST['return_policy'] ?? null, 3000),
                'farm_categories' => json_encode($selectedCategories, JSON_UNESCAPED_SLASHES),
            ] as $key => $value) {
                \db()->query(
                    'INSERT INTO settings (scope, scope_id, setting_key, setting_value) VALUES ("farmer", ?, ?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                    [$vendorId, $key, (string)($value ?? '')]
                );
            }
        }
        \db()->query(
            'INSERT INTO farmer_fulfillment_settings (vendor_id, default_carrier, shipping_fee, pickup_location, pickup_hours, default_preparation_time, custom_preparation_time, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE default_carrier = VALUES(default_carrier), shipping_fee = VALUES(shipping_fee), pickup_location = VALUES(pickup_location), pickup_hours = VALUES(pickup_hours), default_preparation_time = VALUES(default_preparation_time), custom_preparation_time = VALUES(custom_preparation_time), updated_at = VALUES(updated_at)',
            [
                $vendorId,
                self::nullableText($_POST['default_carrier'] ?? null, 120),
                self::nullableFloat($_POST['shipping_fee'] ?? null),
                self::nullableText($_POST['pickup_location'] ?? null, 255),
                self::nullableText($_POST['pickup_hours'] ?? null, 255),
                self::cleanOption((string)($_POST['default_preparation_time'] ?? '1_day'), ['same_day', '1_day', '2_days', 'custom'], '1_day'),
                self::nullableText($_POST['custom_preparation_time'] ?? null, 120),
                \sql_now(),
            ]
        );
        \db()->query(
            'INSERT INTO farmer_store_settings (vendor_id, harvest_available, harvest_note, vacation_mode, vacation_message, updated_at)
             VALUES (?, 1, NULL, ?, ?, ?)
             ON DUPLICATE KEY UPDATE vacation_mode = VALUES(vacation_mode), vacation_message = VALUES(vacation_message), updated_at = VALUES(updated_at)',
            [$vendorId, self::checkbox('vacation_mode'), self::nullableText($_POST['vacation_message'] ?? null, 255), \sql_now()]
        );
        \audit('farmer_settings_updated', 'vendors', (string)$vendorId, [], ['notification_email' => $notificationEmail]);
        \flash('success', 'Farmer settings saved.');
    }

    private static function saveReviewResponse(int $vendorId): void
    {
        $reviewId = max(0, (int)($_POST['review_id'] ?? 0));
        $response = self::nullableText($_POST['response'] ?? null, 2000);
        if ($reviewId <= 0 || $response === null) {
            throw new \RuntimeException('Review response is required.');
        }
        $review = \db()->fetch('SELECT r.id FROM reviews r INNER JOIN products p ON p.id = r.product_id WHERE r.id = ? AND p.vendor_id = ? LIMIT 1', [$reviewId, $vendorId]);
        if (!$review) {
            throw new \RuntimeException('Review not found for this farm.');
        }
        \db()->query(
            'INSERT INTO farmer_review_responses (review_id, vendor_id, response, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NULL)
             ON DUPLICATE KEY UPDATE response = VALUES(response), updated_at = VALUES(created_at)',
            [$reviewId, $vendorId, $response, (int)($_SESSION['user_id'] ?? 0) ?: null, \sql_now()]
        );
        \audit('farmer_review_response_saved', 'reviews', (string)$reviewId, [], ['vendor_id' => $vendorId]);
        \flash('success', 'Review response saved.');
    }

    private static function saveSupportRequest(int $vendorId): void
    {
        $subject = self::nullableText($_POST['subject'] ?? null, 190);
        $message = self::nullableText($_POST['message'] ?? null, 5000);
        if ($subject === null || $message === null) {
            throw new \RuntimeException('Support subject and message are required.');
        }
        $category = self::cleanOption((string)($_POST['category'] ?? 'general'), ['general', 'order_issue', 'produce_listing', 'payout', 'verification', 'technical'], 'general');
        $priority = self::cleanOption((string)($_POST['priority'] ?? 'normal'), ['normal', 'urgent'], 'normal');
        \db()->query(
            'INSERT INTO farmer_support_requests (vendor_id, subject, category, priority, message, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, "open", ?, ?)',
            [$vendorId, $subject, $category, $priority, $message, (int)($_SESSION['user_id'] ?? 0) ?: null, \sql_now()]
        );
        \audit('farmer_support_request_created', 'farmer_support_requests', (string)\db()->lastInsertId(), [], ['vendor_id' => $vendorId, 'category' => $category, 'priority' => $priority]);
        \flash('success', 'Support request sent to the Seller Africa team.');
    }

    private static function saveAvailableThisWeek(int $vendorId): void
    {
        $availableIds = array_map('intval', (array)($_POST['available_product_ids'] ?? []));
        $quantities = is_array($_POST['available_quantity'] ?? null) ? $_POST['available_quantity'] : [];
        $units = is_array($_POST['selling_unit'] ?? null) ? $_POST['selling_unit'] : [];
        $freshness = is_array($_POST['freshness_ends_at'] ?? null) ? $_POST['freshness_ends_at'] : [];
        $notes = is_array($_POST['availability_note'] ?? null) ? $_POST['availability_note'] : [];
        $products = self::rows('SELECT id FROM products WHERE vendor_id = ? AND status <> "archived" LIMIT 500', [$vendorId]);
        foreach ($products as $product) {
            $productId = (int)$product['id'];
            $quantity = trim((string)($quantities[$productId] ?? ''));
            $unit = self::cleanOption((string)($units[$productId] ?? ''), self::sellingUnits(), 'lb');
            $freshnessValue = trim((string)($freshness[$productId] ?? ''));
            $note = trim(substr((string)($notes[$productId] ?? ''), 0, 255));
            \db()->query(
                'INSERT INTO farmer_weekly_availability (vendor_id, product_id, available_this_week, available_quantity, selling_unit, freshness_ends_at, note, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE available_this_week = VALUES(available_this_week), available_quantity = VALUES(available_quantity), selling_unit = VALUES(selling_unit), freshness_ends_at = VALUES(freshness_ends_at), note = VALUES(note), updated_at = VALUES(updated_at)',
                [
                    $vendorId,
                    $productId,
                    in_array($productId, $availableIds, true) ? 1 : 0,
                    $quantity !== '' ? (float)$quantity : null,
                    $unit,
                    $freshnessValue !== '' ? $freshnessValue . ' 23:59:59' : null,
                    $note !== '' ? $note : null,
                    \sql_now(),
                ]
            );
        }
        \audit('farmer_weekly_availability_updated', 'vendors', (string)$vendorId, [], ['available_count' => count($availableIds)]);
        \flash('success', 'Available This Week has been updated.');
    }

    private static function saveProduce(array $vendor, array $user, bool $draft): void
    {
        $vendorId = (int)$vendor['id'];
        $name = trim((string)($_POST['name'] ?? ''));
        $categoryName = trim((string)($_POST['category'] ?? ''));
        $subcategory = trim((string)($_POST['subcategory'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $price = (float)($_POST['regular_price'] ?? 0);
        $unit = self::cleanOption((string)($_POST['selling_unit'] ?? ''), self::sellingUnits(), 'lb');
        $stock = max(0, (int)($_POST['stock_quantity'] ?? 0));
        $threshold = max(0, (int)($_POST['low_stock_threshold'] ?? 5));

        if ($name === '' || $categoryName === '' || $description === '' || $price <= 0) {
            throw new \RuntimeException('Product name, category, description, and price are required.');
        }

        $status = $draft ? 'draft' : 'pending';
        $slug = self::uniqueSlug('products', 'slug', $name);
        $sku = self::productSku((string)($_POST['sku'] ?? ''), $name);
        $shortDescription = trim((string)($_POST['short_description'] ?? ''));
        if ($shortDescription === '') {
            $shortDescription = substr($description, 0, 240);
        }

        \db()->query(
            "INSERT INTO products
             (vendor_id, type, sku, name, slug, short_description, description, status, visibility, regular_price, sale_price, currency, tax_status, stock_status, stock_quantity, manage_stock, low_stock_threshold, weight, length, width, height, created_at, updated_at)
             VALUES (?, 'simple', ?, ?, ?, ?, ?, ?, 'visible', ?, NULL, 'USD', 'taxable', ?, ?, 1, ?, 1, 1, 1, 1, ?, ?)",
            [
                $vendorId,
                $sku,
                $name,
                $slug,
                $shortDescription,
                $description,
                $status,
                $price,
                $stock > 0 ? 'in_stock' : 'out_of_stock',
                $stock,
                $threshold,
                \sql_now(),
                \sql_now(),
            ]
        );
        $productId = (int)\db()->lastInsertId();

        self::assignCategory($productId, $categoryName);
        if ($subcategory !== '') {
            self::assignCategory($productId, $subcategory);
        }
        self::saveProduceDetails($productId, $vendorId, $unit);
        self::storeProducePhotos($productId, (int)($user['id'] ?? 0), $vendorId);

        \audit('farmer_produce_created', 'products', (string)$productId, [], ['vendor_id' => $vendorId, 'status' => $status]);
        \flash('success', $draft ? 'Produce draft saved.' : 'Produce submitted for review and marketplace publishing.');
    }

    private static function saveProduceDetails(int $productId, int $vendorId, string $unit): void
    {
        $harvestDate = trim((string)($_POST['harvest_date'] ?? ''));
        $bestBefore = trim((string)($_POST['best_before_date'] ?? ''));
        $seasonalMode = (string)($_POST['seasonal_mode'] ?? 'year_round') === 'seasonal' ? 'seasonal' : 'year_round';
        $startMonth = max(0, min(12, (int)($_POST['seasonal_start_month'] ?? 0)));
        $endMonth = max(0, min(12, (int)($_POST['seasonal_end_month'] ?? 0)));

        \db()->query(
            'INSERT INTO farmer_produce_details
             (product_id, vendor_id, farm_location, selling_unit, minimum_order_quantity, bulk_pricing, low_stock_threshold, maximum_quantity_per_order, harvest_date, freshness_window_days, best_before_date, organic_claim, farming_method, variety, quality_grade, certifications, shipping_available, local_delivery_available, farm_pickup_available, preparation_time, delivery_areas, seasonal_mode, seasonal_start_month, seasonal_end_month, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $productId,
                $vendorId,
                self::nullableText($_POST['farm_location'] ?? null, 190),
                $unit,
                self::nullableFloat($_POST['minimum_order_quantity'] ?? null),
                self::nullableText($_POST['bulk_pricing'] ?? null, 2000),
                max(0, (int)($_POST['low_stock_threshold'] ?? 5)),
                self::nullableInt($_POST['maximum_quantity_per_order'] ?? null),
                $harvestDate !== '' ? $harvestDate : null,
                self::nullableInt($_POST['freshness_window_days'] ?? null),
                $bestBefore !== '' ? $bestBefore : null,
                (string)($_POST['organic_claim'] ?? '') === '1' ? 1 : 0,
                self::nullableText($_POST['farming_method'] ?? null, 120),
                self::nullableText($_POST['variety'] ?? null, 120),
                self::nullableText($_POST['quality_grade'] ?? null, 80),
                self::nullableText($_POST['certifications'] ?? null, 2000),
                (string)($_POST['shipping_available'] ?? '') === '1' ? 1 : 0,
                (string)($_POST['local_delivery_available'] ?? '') === '1' ? 1 : 0,
                (string)($_POST['farm_pickup_available'] ?? '') === '1' ? 1 : 0,
                self::nullableText($_POST['preparation_time'] ?? null, 120),
                self::nullableText($_POST['delivery_areas'] ?? null, 2000),
                $seasonalMode,
                $seasonalMode === 'seasonal' && $startMonth > 0 ? $startMonth : null,
                $seasonalMode === 'seasonal' && $endMonth > 0 ? $endMonth : null,
                \sql_now(),
                \sql_now(),
            ]
        );
    }

    private static function produceAction(int $vendorId): void
    {
        $productId = max(0, (int)($_POST['product_id'] ?? 0));
        $action = (string)($_POST['produce_action'] ?? '');
        $product = \db()->fetch('SELECT * FROM products WHERE id = ? AND vendor_id = ? LIMIT 1', [$productId, $vendorId]);
        if (!$product) {
            throw new \RuntimeException('Produce listing not found.');
        }

        if ($action === 'update_stock') {
            $stock = max(0, (int)($_POST['stock_quantity'] ?? 0));
            \db()->query('UPDATE products SET stock_quantity = ?, stock_status = ?, updated_at = ? WHERE id = ? AND vendor_id = ?', [$stock, $stock > 0 ? 'in_stock' : 'out_of_stock', \sql_now(), $productId, $vendorId]);
        } elseif ($action === 'pause') {
            \db()->query('UPDATE products SET status = "draft", visibility = "hidden", updated_at = ? WHERE id = ? AND vendor_id = ?', [\sql_now(), $productId, $vendorId]);
        } elseif ($action === 'mark_sold_out') {
            \db()->query('UPDATE products SET stock_quantity = 0, stock_status = "out_of_stock", updated_at = ? WHERE id = ? AND vendor_id = ?', [\sql_now(), $productId, $vendorId]);
        } elseif ($action === 'duplicate') {
            self::duplicateProduce($product, $vendorId);
        } elseif ($action === 'delete') {
            self::deleteProduce($productId, $vendorId, $product);
        } elseif ($action === 'publish') {
            \db()->query('UPDATE products SET status = "pending", visibility = "visible", updated_at = ? WHERE id = ? AND vendor_id = ?', [\sql_now(), $productId, $vendorId]);
        } else {
            throw new \RuntimeException('Unsupported produce action.');
        }

        \audit('farmer_produce_action', 'products', (string)$productId, $product, ['vendor_id' => $vendorId, 'action' => $action]);
        \flash('success', 'Produce listing updated.');
    }

    private static function duplicateProduce(array $product, int $vendorId): void
    {
        $name = trim((string)$product['name']) . ' Copy';
        \db()->query(
            "INSERT INTO products
             (vendor_id, type, sku, name, slug, short_description, description, status, visibility, regular_price, sale_price, currency, tax_status, stock_status, stock_quantity, manage_stock, low_stock_threshold, weight, length, width, height, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', 'hidden', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $vendorId,
                (string)($product['type'] ?? 'simple'),
                self::productSku('', $name),
                $name,
                self::uniqueSlug('products', 'slug', $name),
                (string)($product['short_description'] ?? ''),
                (string)($product['description'] ?? ''),
                (float)($product['regular_price'] ?? 0),
                $product['sale_price'] ?? null,
                (string)($product['currency'] ?? 'USD'),
                (string)($product['tax_status'] ?? 'taxable'),
                (string)($product['stock_status'] ?? 'in_stock'),
                (int)($product['stock_quantity'] ?? 0),
                (int)($product['manage_stock'] ?? 1),
                (int)($product['low_stock_threshold'] ?? 5),
                (float)($product['weight'] ?? 1),
                (float)($product['length'] ?? 1),
                (float)($product['width'] ?? 1),
                (float)($product['height'] ?? 1),
                \sql_now(),
                \sql_now(),
            ]
        );
        $copyId = (int)\db()->lastInsertId();
        if (\table_exists('product_categories')) {
            \db()->query('INSERT IGNORE INTO product_categories (product_id, category_id) SELECT ?, category_id FROM product_categories WHERE product_id = ?', [$copyId, (int)$product['id']]);
        }
        \db()->query('INSERT INTO farmer_produce_details (product_id, vendor_id, farm_location, selling_unit, minimum_order_quantity, bulk_pricing, low_stock_threshold, maximum_quantity_per_order, harvest_date, freshness_window_days, best_before_date, organic_claim, farming_method, variety, quality_grade, certifications, shipping_available, local_delivery_available, farm_pickup_available, preparation_time, delivery_areas, seasonal_mode, seasonal_start_month, seasonal_end_month, created_at, updated_at) SELECT ?, vendor_id, farm_location, selling_unit, minimum_order_quantity, bulk_pricing, low_stock_threshold, maximum_quantity_per_order, harvest_date, freshness_window_days, best_before_date, organic_claim, farming_method, variety, quality_grade, certifications, shipping_available, local_delivery_available, farm_pickup_available, preparation_time, delivery_areas, seasonal_mode, seasonal_start_month, seasonal_end_month, ?, ? FROM farmer_produce_details WHERE product_id = ? LIMIT 1', [$copyId, \sql_now(), \sql_now(), (int)$product['id']]);
    }

    private static function deleteProduce(int $productId, int $vendorId, array $product): void
    {
        $orders = \table_exists('order_items') ? (int)(\db()->fetch('SELECT COUNT(*) total FROM order_items WHERE product_id = ? LIMIT 1', [$productId])['total'] ?? 0) : 0;
        if ($orders > 0) {
            \db()->query('UPDATE products SET status = "archived", visibility = "hidden", updated_at = ? WHERE id = ? AND vendor_id = ?', [\sql_now(), $productId, $vendorId]);
            return;
        }
        \db()->query('DELETE FROM farmer_produce_details WHERE product_id = ? AND vendor_id = ?', [$productId, $vendorId]);
        \db()->query('DELETE FROM farmer_weekly_availability WHERE product_id = ? AND vendor_id = ?', [$productId, $vendorId]);
        \db()->query('DELETE FROM product_categories WHERE product_id = ?', [$productId]);
        \db()->query('DELETE FROM products WHERE id = ? AND vendor_id = ?', [$productId, $vendorId]);
    }

    private static function metrics(int $vendorId): array
    {
        $sales = self::salesSnapshot($vendorId);
        $balances = self::balances($vendorId);
        return [
            'produce' => self::count('products', 'vendor_id = ? AND status <> "archived"', [$vendorId]),
            'available' => self::count('products', 'vendor_id = ? AND status IN ("active","published","approved") AND stock_status <> "out_of_stock"', [$vendorId]),
            'low_stock' => self::count('products', 'vendor_id = ? AND manage_stock = 1 AND stock_quantity <= 5 AND status <> "archived"', [$vendorId]),
            'sold_out' => self::count('products', 'vendor_id = ? AND stock_status = "out_of_stock" AND status <> "archived"', [$vendorId]),
            'freshness_alerts' => self::count('farmer_weekly_availability', 'vendor_id = ? AND available_this_week = 1 AND freshness_ends_at IS NOT NULL AND freshness_ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 DAY)', [$vendorId]),
            'restock_alerts' => self::count('products', 'vendor_id = ? AND manage_stock = 1 AND stock_quantity <= 10 AND status <> "archived"', [$vendorId]),
            'new_orders' => self::count('order_vendor_splits', 'vendor_id = ? AND status = "pending"', [$vendorId]),
            'need_fulfillment' => self::count('order_vendor_splits', 'vendor_id = ? AND status IN ("pending","processing")', [$vendorId]),
            'weekly_sales' => self::money((float)$sales['week_total']),
            'pending_payouts' => self::money((float)$balances['pending_payout']),
            'available_balance' => self::money((float)$balances['available_balance']),
            'customers' => self::countDistinctCustomers($vendorId),
            'sales' => $sales,
        ];
    }

    private static function home(array $vendor, array $metrics, array $applicationData): void
    {
        echo '<section class="ff-hero">';
        echo '<div class="ff-hero-copy"><p>Welcome back, ' . \e((string)$vendor['store_name']) . '</p><h1>Your farm. Your produce. Your store.</h1><span>Manage your listings, orders, and customers all in one place.</span></div>';
        echo '<aside class="ff-verified"><b>' . self::icon('leaf') . '</b><strong>Verified FreshRoots Vendor</strong><span>Your business and farm information have been verified.</span><a href="' . \e(\app_url('farmer/verification')) . '">View Details</a></aside>';
        echo '</section>';

        echo '<section class="ff-metrics">';
        foreach ([
            ['bag', 'New Orders', $metrics['new_orders'], 'View Orders', 'farmer/orders'],
            ['truck', 'Need Fulfillment', $metrics['need_fulfillment'], 'View Fulfillment', 'farmer/fulfillment'],
            ['alert', 'Low Stock Items', $metrics['low_stock'], 'View Inventory', 'farmer/inventory'],
            ['money', 'Pending Payout', $metrics['pending_payouts'], 'View Earnings', 'farmer/earnings'],
            ['leaf', 'Products Live', $metrics['available'], 'View Produce', 'farmer/produce'],
            ['money', 'Sales This Week', $metrics['weekly_sales'], 'View Snapshot', 'farmer/earnings'],
        ] as $card) {
            echo '<article class="ff-metric"><b>' . self::icon($card[0]) . '</b><div><strong>' . \e((string)$card[2]) . '</strong><span>' . \e($card[1]) . '</span><a href="' . \e(\app_url($card[4])) . '">' . \e($card[3]) . '</a></div></article>';
        }
        echo '</section>';

        echo '<section class="ff-dashboard-grid"><div class="ff-main-col">';
        self::recentOrdersPanel((int)$vendor['id']);
        self::availableThisWeekPanel((int)$vendor['id']);
        self::salesSnapshotPanel($metrics['sales']);
        self::producePanel((int)$vendor['id']);
        echo '<div class="ff-farm-note"><b>' . self::icon('leaf') . '</b><span><strong>Fresh. Local. Direct from the Farm.</strong><small>You are not just selling produce - you are feeding communities.</small></span><em>Thank you for being a part of what we are building.</em></div>';
        echo '</div><div class="ff-side-col">';
        self::quickActionsPanel();
        self::payoutPanel($metrics);
        self::harvestStatusPanel((int)$vendor['id']);
        echo '<article class="ff-panel ff-help"><img src="' . \e(\app_url('assets/images/farmers.jpeg')) . '" alt=""><div><h2>Need Help?</h2><p>Visit our support center for FAQs, guides and marketplace policies.</p><a href="' . \e(\app_url('farmer/support')) . '">Go to Support</a></div></article>';
        echo '</div></section>';
    }

    private static function recentOrdersPanel(int $vendorId): void
    {
        $rows = self::rows(
            "SELECT ovs.id, ovs.status, ovs.gross_total, ovs.shipping_total, o.created_at, o.order_number, o.payment_status,
                    COALESCE(NULLIF(u.display_name, ''), o.guest_email, 'Customer') customer_name,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id AND oi.vendor_id = ovs.vendor_id AND oi.item_type = 'product') item_count
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             LEFT JOIN users u ON u.id = o.customer_id
             WHERE ovs.vendor_id = ?
             ORDER BY ovs.created_at DESC, ovs.id DESC
             LIMIT 5",
            [$vendorId]
        );

        echo '<article class="ff-panel"><div class="ff-panel-head"><h2>Recent Orders</h2><a href="' . \e(\app_url('farmer/orders')) . '">View All Orders</a></div>';
        echo '<div class="ff-tabs"><span class="is-active">New (' . \e((string)count($rows)) . ')</span><span>Accepted</span><span>Preparing</span><span>Shipped</span><span>Delivered</span></div>';
        echo '<div class="ff-order-list">';
        foreach ($rows as $row) {
            $customer = trim((string)($row['customer_name'] ?? 'Customer'));
            $status = (string)($row['status'] ?? 'pending');
            echo '<div class="ff-order-row"><span class="ff-thumb">' . self::icon('leaf') . '</span>';
            echo '<div><strong>Order #' . \e((string)($row['order_number'] ?: 'SA-' . $row['id'])) . '</strong><small>Placed ' . \e(substr((string)$row['created_at'], 0, 10)) . ' - ' . \e((string)($row['item_count'] ?? 0)) . ' items</small></div>';
            echo '<div><strong>' . \e($customer !== '' ? $customer : 'Customer') . '</strong><small>Customer</small></div>';
            echo '<div><strong>' . \e(self::money((float)($row['gross_total'] ?? 0))) . '</strong><small>' . \e(ucwords(str_replace('_', ' ', (string)($row['payment_status'] ?? 'pending')))) . '</small></div>';
            echo '<div><strong>' . \e(((float)($row['shipping_total'] ?? 0) > 0) ? 'Shipping' : 'Farm fulfillment') . '</strong><small>' . \e(ucwords(str_replace('_', ' ', $status))) . '</small></div>';
            echo '<div class="ff-order-actions">' . self::orderActionButton((int)$row['id'], $status) . '</div><button type="button">...</button></div>';
        }
        if ($rows === []) {
            foreach (['Fresh greens bundle', 'Sweet berries box', 'Harvest produce order'] as $index => $label) {
                echo '<div class="ff-order-row"><span class="ff-thumb">' . self::icon('leaf') . '</span><div><strong>Order #SA-1043' . \e((string)$index) . '</strong><small>' . \e($label) . ' - ' . (1 + $index) . ' items</small></div><div><strong>Customer</strong><small>Customer</small></div><div><strong>$' . \e((string)(48 + ($index * 12))) . '.00</strong><small>Paid</small></div><div><strong>' . ($index === 0 ? 'Shipping' : 'Local Delivery') . '</strong><small>Pending</small></div><a href="' . \e(\app_url('farmer/orders')) . '">View</a><button type="button">...</button></div>';
            }
        }
        echo '</div></article>';
    }

    private static function orderActionButton(int $splitId, string $status): string
    {
        $action = match ($status) {
            'pending' => ['accept', 'Accept'],
            'processing' => ['mark_ready', 'Mark Ready'],
            'shipped', 'completed' => ['', 'View'],
            default => ['start_preparing', 'Start Preparing'],
        };
        if ($action[0] === '') {
            return '<a href="' . \e(\app_url('farmer/orders')) . '">View</a>';
        }

        return '<form method="post"><input type="hidden" name="csrf_token" value="' . \e(\getCsrfToken()) . '"><input type="hidden" name="intent" value="order_action"><input type="hidden" name="split_id" value="' . \e((string)$splitId) . '"><input type="hidden" name="order_action" value="' . \e($action[0]) . '"><button type="submit">' . \e($action[1]) . '</button></form>';
    }

    private static function producePanel(int $vendorId): void
    {
        $rows = self::rows(
            'SELECT p.id, p.name, p.regular_price, p.stock_quantity, p.stock_status, f.path image_path
             FROM products p
             LEFT JOIN product_media pm ON pm.product_id = p.id AND pm.role = "primary"
             LEFT JOIN files f ON f.id = pm.file_id
             WHERE p.vendor_id = ? AND p.status <> "archived"
             ORDER BY p.updated_at DESC, p.id DESC
             LIMIT 5',
            [$vendorId]
        );

        echo '<section class="ff-lower-grid"><article class="ff-panel"><div class="ff-panel-head"><h2>My Produce</h2><a href="' . \e(\app_url('farmer/produce')) . '">View All Produce</a></div><div class="ff-produce-table"><div class="ff-produce-head"><span>Product</span><span>Price</span><span>Unit</span><span>Available</span><span>Status</span><span></span></div>';
        foreach ($rows as $row) {
            self::produceRow($row);
        }
        if ($rows === []) {
            $sample = [
                ['name' => 'Fresh Collard Greens', 'regular_price' => 3.50, 'stock_quantity' => 24, 'stock_status' => 'in_stock'],
                ['name' => 'Sweet Strawberries', 'regular_price' => 5.00, 'stock_quantity' => 10, 'stock_status' => 'low_stock'],
                ['name' => 'Okra', 'regular_price' => 4.00, 'stock_quantity' => 0, 'stock_status' => 'out_of_stock'],
                ['name' => 'Basil Fresh Herbs', 'regular_price' => 3.00, 'stock_quantity' => 18, 'stock_status' => 'in_stock'],
                ['name' => 'Heirloom Tomatoes', 'regular_price' => 4.50, 'stock_quantity' => 15, 'stock_status' => 'in_stock'],
            ];
            foreach ($sample as $row) {
                self::produceRow($row);
            }
        }
        echo '</div></article><article class="ff-panel"><div class="ff-panel-head"><h2>Inventory Alerts</h2><a href="' . \e(\app_url('farmer/inventory')) . '">View All</a></div>' . self::alertCards($rows) . '</article></section>';
    }

    private static function produceRow(array $row): void
    {
        $status = (string)($row['stock_status'] ?? 'in_stock');
        $qty = (int)($row['stock_quantity'] ?? 0);
        $label = $status === 'out_of_stock' || $qty <= 0 ? 'Sold Out' : ($qty <= 10 ? 'Low Stock' : 'Available');
        $image = self::fileUrl($row['image_path'] ?? null);
        echo '<div class="ff-produce-row"><span class="ff-product-cell">' . ($image !== '' ? '<img src="' . \e($image) . '" alt="">' : '<b>' . self::icon('leaf') . '</b>') . '<strong>' . \e((string)$row['name']) . '</strong></span><span>' . \e(self::money((float)($row['regular_price'] ?? 0))) . '</span><span>lb</span><span class="' . ($qty <= 0 ? 'is-zero' : '') . '">' . \e((string)$qty) . '</span><span><em class="ff-status ' . \e(strtolower(str_replace(' ', '-', $label))) . '">' . \e($label) . '</em></span><span>...</span></div>';
    }

    private static function availableThisWeekPanel(int $vendorId): void
    {
        $rows = self::rows(
            'SELECT p.id, p.name, p.stock_quantity, p.stock_status, COALESCE(fpd.selling_unit, fwa.selling_unit, "lb") selling_unit,
                    fwa.available_this_week, fwa.available_quantity, fwa.freshness_ends_at, fwa.note
             FROM products p
             LEFT JOIN farmer_weekly_availability fwa ON fwa.product_id = p.id AND fwa.vendor_id = p.vendor_id
             LEFT JOIN farmer_produce_details fpd ON fpd.product_id = p.id
             WHERE p.vendor_id = ? AND p.status <> "archived"
             ORDER BY COALESCE(fwa.available_this_week, 0) DESC, p.updated_at DESC, p.id DESC
             LIMIT 8',
            [$vendorId]
        );

        echo '<article class="ff-panel ff-weekly" id="available-this-week"><div class="ff-panel-head"><h2>Available This Week</h2><a href="' . \e(\app_url('farmer/produce')) . '">Manage Listings</a></div>';
        if ($rows === []) {
            echo '<p>Add produce first, then you can mark what has been harvested and available this week.</p></article>';
            return;
        }

        echo '<form method="post"><input type="hidden" name="csrf_token" value="' . \e(\getCsrfToken()) . '"><input type="hidden" name="intent" value="available_this_week"><div class="ff-weekly-grid">';
        foreach ($rows as $row) {
            $productId = (int)$row['id'];
            $checked = (int)($row['available_this_week'] ?? 0) === 1 ? ' checked' : '';
            $freshDate = substr((string)($row['freshness_ends_at'] ?? ''), 0, 10);
            echo '<label><span><input type="checkbox" name="available_product_ids[]" value="' . \e((string)$productId) . '"' . $checked . '> ' . \e((string)$row['name']) . '</span><small>' . \e((string)($row['stock_quantity'] ?? 0)) . ' in stock</small><div class="ff-weekly-inline"><input type="number" min="0" step="0.01" name="available_quantity[' . \e((string)$productId) . ']" value="' . \e((string)($row['available_quantity'] ?? $row['stock_quantity'] ?? '')) . '"><select name="selling_unit[' . \e((string)$productId) . ']">' . self::options(self::sellingUnits(), (string)($row['selling_unit'] ?? 'lb')) . '</select></div><input type="date" name="freshness_ends_at[' . \e((string)$productId) . ']" value="' . \e($freshDate) . '"><input name="availability_note[' . \e((string)$productId) . ']" placeholder="Short note" value="' . \e((string)($row['note'] ?? '')) . '"></label>';
        }
        echo '</div><button class="ff-save-btn" type="submit">Update Available This Week</button></form></article>';
    }

    private static function salesSnapshotPanel(array $sales): void
    {
        echo '<article class="ff-panel ff-sales"><div class="ff-panel-head"><h2>Sales Snapshot</h2><a href="' . \e(\app_url('farmer/earnings')) . '">View Earnings</a></div><div class="ff-sales-grid">';
        foreach ([
            'Today' => self::money((float)$sales['today_total']),
            'This Week' => self::money((float)$sales['week_total']),
            'This Month' => self::money((float)$sales['month_total']),
            'Orders' => (string)(int)$sales['week_orders'],
            'Average Order' => self::money((float)$sales['average_order']),
            'Top Produce' => (string)($sales['top_product'] ?: 'No sales yet'),
        ] as $label => $value) {
            echo '<div><span>' . \e($label) . '</span><strong>' . \e($value) . '</strong></div>';
        }
        echo '</div></article>';
    }

    private static function quickActionsPanel(): void
    {
        echo '<article class="ff-panel ff-quick"><h2>Quick Actions</h2><a class="ff-primary-action" href="' . \e(\app_url('farmer/produce#add-produce')) . '">+ Add Produce</a><div><a href="' . \e(\app_url('farmer/orders')) . '">' . self::icon('bag') . ' View New Orders</a><a href="' . \e(\app_url('farmer/inventory')) . '">' . self::icon('box') . ' Update Inventory</a><a href="#available-this-week">' . self::icon('leaf') . ' Update Available This Week</a><a href="' . \e(\app_url('farmer/fulfillment')) . '">' . self::icon('truck') . ' Manage Fulfillment</a><a href="' . \e(\app_url('farmer/earnings')) . '">' . self::icon('money') . ' View Earnings</a></div></article>';
    }

    private static function payoutPanel(array $metrics): void
    {
        echo '<article class="ff-panel ff-payout"><div class="ff-panel-head"><h2>Upcoming Payout</h2><a href="' . \e(\app_url('farmer/earnings')) . '">View Earnings</a></div><div class="ff-payout-row"><div><span>Next Payout</span><strong>' . \e(app_date('F j, Y', 'next wednesday')) . '</strong><a href="' . \e(\app_url('farmer/earnings')) . '">Open Earnings</a></div><div><b>' . \e((string)$metrics['pending_payouts']) . '</b><small>Pending amount</small><b>' . \e((string)$metrics['available_balance']) . '</b><small>Available balance</small></div></div><p>Payouts are processed weekly, every Wednesday.</p></article>';
    }

    private static function harvestStatusPanel(int $vendorId): void
    {
        $settings = self::storeSettings($vendorId);
        $available = (int)($settings['harvest_available'] ?? 1) === 1;
        echo '<article class="ff-panel ff-season"><div><h2>Harvest Status</h2><p>This controls whether your farm appears as currently available on the FreshRoots marketplace.</p><form method="post"><input type="hidden" name="csrf_token" value="' . \e(\getCsrfToken()) . '"><input type="hidden" name="intent" value="harvest_status"><label class="ff-toggle"><input type="checkbox" name="harvest_available" value="1"' . ($available ? ' checked' : '') . '><span></span><b>' . ($available ? 'Harvest Available' : 'Not Currently Available') . '</b></label><input name="harvest_note" placeholder="Optional public note" value="' . \e((string)($settings['harvest_note'] ?? '')) . '"><button class="ff-save-btn" type="submit">Save Harvest Status</button></form></div></article>';
    }

    private static function alertCards(array $rows): string
    {
        $alerts = [];
        foreach ($rows as $row) {
            $qty = (int)($row['stock_quantity'] ?? 0);
            if ($qty <= 15) {
                $alerts[] = [(string)$row['name'], $qty <= 0 ? 'is out of stock' : 'is low in stock', $qty <= 0 ? 'Restock soon to avoid lost sales.' : 'Only ' . $qty . ' units remaining.'];
            }
        }
        if ($alerts === []) {
            $alerts = [
                ['Okra', 'is out of stock', 'Restock soon to avoid lost sales.'],
                ['Strawberries', 'is low in stock', 'Only 10 units remaining.'],
                ['Tomatoes', 'is low in stock', 'Only 15 units remaining.'],
                ['Collard Greens', 'is available', 'Great! 24 units in stock.'],
            ];
        }

        $html = '<div class="ff-alert-cards">';
        foreach (array_slice($alerts, 0, 4) as $index => $alert) {
            $html .= '<div><span class="ff-dot ff-dot-' . ($index + 1) . '">' . self::icon($index === 3 ? 'check' : 'alert') . '</span><p><strong>' . \e($alert[0]) . ' ' . \e($alert[1]) . '</strong><small>' . \e($alert[2]) . '</small></p></div>';
        }
        return $html . '</div>';
    }

    private static function sectionPage(string $active, array $vendor, array $metrics, array $applicationData): void
    {
        $section = self::SECTIONS[$active];
        echo '<section class="ff-page-head"><p>FreshRoots Dashboard</p><h1>' . \e($section['label']) . '</h1></section>';
        echo '<section class="ff-panel"><div class="ff-section-links">';
        foreach ($section['items'] as $item) {
            echo '<a href="#' . \e(strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $item))) . '">' . \e($item) . '</a>';
        }
        echo '</div></section>';

        if ($active === 'produce' || $active === 'inventory') {
            self::produceTable((int)$vendor['id']);
            return;
        }
        if ($active === 'orders') {
            self::ordersTable((int)$vendor['id']);
            return;
        }
        if ($active === 'fulfillment') {
            self::fulfillmentPage((int)$vendor['id']);
            return;
        }
        if ($active === 'earnings') {
            self::earningsPage((int)$vendor['id']);
            return;
        }
        if ($active === 'customers') {
            self::customersPage((int)$vendor['id']);
            return;
        }
        if ($active === 'reviews') {
            self::reviewsPage((int)$vendor['id']);
            return;
        }
        if ($active === 'verification') {
            self::verificationPage($vendor, $applicationData);
            return;
        }
        if ($active === 'profile') {
            self::profilePanel($vendor, $applicationData);
            return;
        }
        if ($active === 'settings') {
            self::settingsPage((int)$vendor['id'], $vendor);
            return;
        }
        if ($active === 'support') {
            self::supportPage((int)$vendor['id']);
            return;
        }

        echo '<section class="ff-grid">';
        foreach ($section['items'] as $item) {
            echo '<article class="ff-card"><span>' . \e($section['label']) . '</span><h2>' . \e($item) . '</h2><p>' . \e(self::sectionHint($active, $item, $metrics, $applicationData)) . '</p></article>';
        }
        echo '</section>';
    }

    private static function produceTable(int $vendorId): void
    {
        $rows = self::produceRows($vendorId, 120);
        echo '<section class="ff-produce-tools">';
        echo '<article class="ff-panel ff-management-head"><div><p>Product / Listing Management Centre</p><h2>All Produce</h2><span>Manage pricing, stock, visibility, weekly availability, seasonal windows, and fulfillment from one place.</span></div><a class="ff-primary-action" href="#add-produce" data-ff-modal-open>+ Add New Produce</a></article>';
        echo '<article class="ff-panel ff-produce-list"><div class="ff-produce-management-table">';
        echo '<div class="ff-produce-management-head"><span>Image</span><span>Product</span><span>Category</span><span>Price</span><span>Unit</span><span>Qty</span><span>Harvest</span><span>Status</span><span>Visibility</span><span>Updated</span><span>Actions</span></div>';
        foreach ($rows as $row) {
            self::produceManagementRow($row);
        }
        if ($rows === []) {
            echo '<p>No produce has been added yet. Use Add New Produce to create your first FreshRoots listing.</p>';
        }
        echo '</div></article>';
        self::availableThisWeekBulkPanel($vendorId);
        self::seasonalProducePanel($rows);
        self::addProduceModal($vendorId);
        echo '</section>';
    }

    private static function fulfillmentPage(int $vendorId): void
    {
        $settings = self::fulfillmentSettings($vendorId);
        $store = self::storeSettings($vendorId);
        echo '<section class="ff-fulfillment-wrap">';
        echo '<article class="ff-panel ff-management-head"><div><p>Fulfillment Control Centre</p><h2>How Customers Receive Produce</h2><span>Enable shipping, Seller Africa delivery, local delivery, pickup, preparation timing, and temporary closure from one page.</span></div></article>';
        echo '<form method="post" class="ff-fulfillment-form"><input type="hidden" name="csrf_token" value="' . \e(\getCsrfToken()) . '"><input type="hidden" name="intent" value="fulfillment_settings">';

        echo '<article class="ff-panel" id="fulfillment-methods"><div class="ff-panel-head"><h2>Fulfillment Methods</h2><span>Turn methods on or off.</span></div><div class="ff-method-grid">';
        foreach ([
            'shipping_enabled' => ['Shipping', 'Ship produce to customers with carrier tracking.'],
            'seller_africa_delivery_enabled' => ['Seller Africa Delivery', 'Use Seller Africa delivery where offered.'],
            'farmer_local_delivery_enabled' => ['Farmer Local Delivery', 'Deliver directly inside your service area.'],
            'farm_pickup_enabled' => ['Farm Pickup', 'Let customers collect orders from an approved pickup location.'],
        ] as $field => $copy) {
            echo '<label class="ff-method-card"><input type="checkbox" name="' . \e($field) . '" value="1"' . ((int)($settings[$field] ?? 0) === 1 ? ' checked' : '') . '><span></span><strong>' . \e($copy[0]) . '</strong><small>' . \e($copy[1]) . '</small></label>';
        }
        echo '</div></article>';

        echo '<article class="ff-panel" id="shipping"><div class="ff-panel-head"><h2>Shipping</h2><span>Carrier defaults, rates, coverage, and tracking.</span></div><div class="ff-form-grid">';
        self::textInput('Default carrier', 'default_carrier', $settings);
        self::moneyInput('Shipping fee / rate', 'shipping_fee', $settings);
        self::moneyInput('Free-shipping threshold', 'free_shipping_threshold', $settings);
        self::textInput('Preparation time', 'shipping_preparation_time', $settings, '24 hours');
        self::textareaInput('States / regions served', 'states_regions_served', $settings);
        echo '<label class="ff-check-field"><input type="checkbox" name="tracking_required" value="1"' . ((int)($settings['tracking_required'] ?? 1) === 1 ? ' checked' : '') . '> Tracking is required for shipped orders</label>';
        echo '</div></article>';

        echo '<article class="ff-panel" id="local-delivery"><div class="ff-panel-head"><h2>Local Delivery</h2><span>Service area, fees, minimums, days, and delivery windows.</span></div><div class="ff-form-grid">';
        self::textInput('Delivery radius', 'delivery_radius', $settings, '25 miles');
        self::textareaInput('ZIP/postal codes or areas served', 'delivery_areas', $settings);
        self::moneyInput('Delivery fee', 'delivery_fee', $settings);
        self::moneyInput('Free-delivery threshold', 'free_delivery_threshold', $settings);
        self::moneyInput('Minimum order', 'minimum_delivery_order', $settings);
        self::textInput('Delivery days', 'delivery_days', $settings, 'Monday, Wednesday, Saturday');
        self::textInput('Delivery times', 'delivery_times', $settings, '9 AM - 5 PM');
        echo '</div></article>';

        echo '<article class="ff-panel" id="farm-pickup"><div class="ff-panel-head"><h2>Farm Pickup</h2><span>Pickup location and instructions. Keep exact private address hidden unless it is appropriate.</span></div><div class="ff-form-grid">';
        self::textInput('Farm/store pickup location', 'pickup_location', $settings);
        self::textareaInput('Pickup instructions', 'pickup_instructions', $settings);
        self::textInput('Available pickup days', 'pickup_days', $settings, 'Tuesday - Saturday');
        self::textInput('Pickup hours', 'pickup_hours', $settings, '10 AM - 4 PM');
        self::textInput('Pickup preparation time', 'pickup_preparation_time', $settings, '1 day');
        self::textareaInput('Customer instructions', 'customer_instructions', $settings);
        echo '<label class="ff-check-field"><input type="checkbox" name="expose_private_address" value="1"' . ((int)($settings['expose_private_address'] ?? 0) === 1 ? ' checked' : '') . '> Show exact private farm address only when appropriate</label>';
        echo '</div></article>';

        echo '<article class="ff-panel" id="order-preparation"><div class="ff-panel-head"><h2>Order Preparation</h2><span>Default time needed before produce is ready.</span></div><div class="ff-form-grid">';
        echo '<label>Default preparation time<select name="default_preparation_time">' . self::keyedOptions(['same_day' => 'Same Day', '1_day' => '1 Day', '2_days' => '2 Days', 'custom' => 'Custom'], (string)($settings['default_preparation_time'] ?? '1_day')) . '</select></label>';
        self::textInput('Custom preparation time', 'custom_preparation_time', $settings, 'Example: 36 hours');
        echo '</div></article>';

        echo '<article class="ff-panel" id="vacation-temporary-closure"><div class="ff-panel-head"><h2>Vacation / Temporary Closure</h2><span>Stop receiving new FreshRoots orders while preserving listings and inventory.</span></div><div class="ff-form-grid">';
        echo '<label class="ff-method-card ff-closure-card"><input type="checkbox" name="vacation_mode" value="1"' . ((int)($store['vacation_mode'] ?? 0) === 1 ? ' checked' : '') . '><span></span><strong>Temporary Closure</strong><small>Listings stay saved, but customers can see that the farm is not accepting orders.</small></label>';
        echo '<label>Closure message<input name="vacation_message" value="' . \e((string)($store['vacation_message'] ?? '')) . '" placeholder="We are harvesting again next week."></label>';
        echo '</div></article>';

        echo '<div class="ff-sticky-save"><button type="submit">Save Fulfillment Settings</button></div></form></section>';
    }

    private static function earningsPage(int $vendorId): void
    {
        $summary = self::earningsSummary($vendorId);
        $balances = self::balances($vendorId);
        $transactions = self::earningTransactions($vendorId);
        $payouts = self::payoutHistory($vendorId);

        echo '<section class="ff-earnings-wrap">';
        echo '<article class="ff-panel ff-management-head"><div><p>FreshRoots Earnings</p><h2>Revenue, Payouts, Fees & Refunds</h2><span>Track what customers paid, Seller Africa fees, farmer earnings, payout history, and available balance.</span></div></article>';

        echo '<section class="ff-earnings-metrics">';
        foreach ([
            ['Total Sales', self::money((float)$summary['total_sales']), 'Gross customer payments across farmer orders.'],
            ['Farmer Earnings', self::money((float)$summary['total_earnings']), 'Net amount credited to the farm before withdrawals.'],
            ['Available Balance', self::money((float)$balances['available_balance']), 'Eligible balance not yet requested.'],
            ['Pending Payout', self::money((float)$balances['pending_payout']), 'Payout requests waiting for approval or payment.'],
            ['Seller Africa Fees', self::money((float)$summary['platform_fees']), 'Marketplace commission on farmer orders.'],
            ['Refunded Orders', self::money((float)$summary['refunded_total']), 'Refunded or returned farmer order value.'],
        ] as $card) {
            echo '<article class="ff-metric"><b>' . self::icon('money') . '</b><div><strong>' . \e($card[1]) . '</strong><span>' . \e($card[0]) . '</span><small>' . \e($card[2]) . '</small></div></article>';
        }
        echo '</section>';

        echo '<section class="ff-earnings-grid">';
        echo '<article class="ff-panel"><div class="ff-panel-head"><h2>Sales Snapshot</h2><span>Today, 7 days, and current month.</span></div><div class="ff-sales-grid">';
        foreach ([
            'Today' => self::money((float)$summary['today_sales']),
            'This Week' => self::money((float)$summary['week_sales']),
            'This Month' => self::money((float)$summary['month_sales']),
            'Orders' => number_format((int)$summary['order_count']),
            'Average Order' => self::money((float)$summary['average_order']),
            'Top Produce' => (string)($summary['top_product'] ?: 'No sales yet'),
        ] as $label => $value) {
            echo '<div><span>' . \e($label) . '</span><strong>' . \e($value) . '</strong></div>';
        }
        echo '</div></article>';

        echo '<article class="ff-panel ff-payout-request"><div class="ff-panel-head"><h2>Request Payout</h2><span>Available balance: ' . \e(self::money((float)$balances['available_balance'])) . '</span></div>';
        echo '<form method="post"><input type="hidden" name="csrf_token" value="' . \e(\getCsrfToken()) . '"><input type="hidden" name="intent" value="payout_request">';
        echo '<label>Amount<input type="number" step="0.01" min="0" max="' . \e((string)$balances['available_balance']) . '" name="amount" placeholder="0.00"></label>';
        echo '<label>Method<select name="method">' . self::keyedOptions(['bank_transfer' => 'Bank Transfer', 'paypal' => 'PayPal', 'manual' => 'Manual Review'], 'bank_transfer') . '</select></label>';
        echo '<label class="ff-wide">Note<textarea name="note" placeholder="Optional payout note for the finance team"></textarea></label>';
        echo '<button type="submit">Submit Payout Request</button></form></article>';
        echo '</section>';

        echo '<section class="ff-earnings-grid">';
        self::payoutHistoryPanel($payouts);
        self::feeRefundPanel($summary);
        echo '</section>';

        echo '<article class="ff-panel ff-transactions"><div class="ff-panel-head"><h2>Transactions</h2><span>Latest farmer order earnings.</span></div><div class="ff-transaction-table">';
        echo '<div class="ff-transaction-head"><span>Date</span><span>Order</span><span>Customer Paid</span><span>Seller Africa Fee</span><span>Gateway Fee</span><span>Farmer Earnings</span><span>Status</span></div>';
        foreach ($transactions as $row) {
            echo '<div class="ff-transaction-row"><span data-label="Date">' . \e(self::dateTimeShort((string)($row['placed_at'] ?: $row['created_at']))) . '</span><strong data-label="Order">#' . \e((string)($row['order_number'] ?: 'SA-' . $row['id'])) . '</strong><span data-label="Customer Paid">' . \e(self::money((float)$row['gross_total'])) . '</span><span data-label="Seller Africa Fee">' . \e(self::money((float)$row['platform_commission'])) . '</span><span data-label="Gateway Fee">' . \e(self::money((float)$row['gateway_fee'])) . '</span><span data-label="Farmer Earnings">' . \e(self::money((float)$row['vendor_earning'])) . '</span><span data-label="Status"><em class="ff-status ' . \e(strtolower((string)$row['status'])) . '">' . \e(self::label((string)$row['status'])) . '</em></span></div>';
        }
        if ($transactions === []) {
            echo '<p>No earnings transactions yet.</p>';
        }
        echo '</div></article></section>';
    }

    private static function payoutHistoryPanel(array $payouts): void
    {
        echo '<article class="ff-panel"><div class="ff-panel-head"><h2>Payout History</h2><span>Requests and settlements.</span></div><div class="ff-payout-history">';
        foreach ($payouts as $row) {
            echo '<div><strong>' . \e(self::money((float)$row['amount'])) . '</strong><span>' . \e(self::label((string)$row['status'])) . ' - ' . \e((string)($row['method'] ?: 'Manual')) . '</span><em>' . \e(self::dateTimeShort((string)$row['requested_at'])) . '</em></div>';
        }
        if ($payouts === []) {
            echo '<p>No payout requests yet.</p>';
        }
        echo '</div></article>';
    }

    private static function feeRefundPanel(array $summary): void
    {
        echo '<article class="ff-panel"><div class="ff-panel-head"><h2>Fees & Refunds</h2><span>Clear deduction summary.</span></div><div class="ff-money-breakdown">';
        foreach ([
            'Product / Order Sales' => self::money((float)$summary['total_sales']),
            'Seller Africa Fee' => self::money((float)$summary['platform_fees']),
            'Gateway Fee' => self::money((float)$summary['gateway_fees']),
            'Refunded / Returned' => self::money((float)$summary['refunded_total']),
            'Your Earnings' => self::money((float)$summary['total_earnings']),
        ] as $label => $value) {
            echo '<p class="' . ($label === 'Your Earnings' ? 'is-total' : '') . '"><span>' . \e($label) . '</span><strong>' . \e($value) . '</strong></p>';
        }
        echo '</div></article>';
    }

    private static function customersPage(int $vendorId): void
    {
        $query = trim((string)($_GET['q'] ?? ''));
        $selectedKey = trim((string)($_GET['customer'] ?? ''));
        $summary = self::customerSummary($vendorId);
        $customers = self::customerRows($vendorId, $query);
        $repeatCustomers = array_values(array_filter($customers, static fn (array $row): bool => (int)($row['order_count'] ?? 0) > 1));
        $orders = $selectedKey !== '' ? self::customerOrders($vendorId, $selectedKey) : self::recentCustomerOrders($vendorId);
        $selected = $selectedKey !== '' ? self::selectedCustomer($customers, $selectedKey) : null;

        echo '<section class="ff-customers-wrap">';
        echo '<article class="ff-panel ff-management-head"><div><p>Customer Centre</p><h2>Customers, Repeat Buyers & Order History</h2><span>See who is buying from your farm, which customers are returning, and the latest FreshRoots order activity.</span></div></article>';

        echo '<section class="ff-customer-metrics">';
        foreach ([
            ['Total Customers', number_format((int)$summary['total_customers']), 'Unique customers who ordered from this farm.'],
            ['Repeat Buyers', number_format((int)$summary['repeat_buyers']), 'Customers with more than one order.'],
            ['New This Month', number_format((int)$summary['new_30_days']), 'First farm order in the last 30 days.'],
            ['Average Customer Value', self::money((float)$summary['average_customer_value']), 'Average lifetime spend per customer.'],
        ] as $card) {
            echo '<article class="ff-metric"><b>' . self::icon('users') . '</b><div><strong>' . \e($card[1]) . '</strong><span>' . \e($card[0]) . '</span><small>' . \e($card[2]) . '</small></div></article>';
        }
        echo '</section>';

        echo '<section class="ff-customers-grid">';
        echo '<article class="ff-panel ff-customer-list" id="customers"><div class="ff-panel-head"><h2>Customers</h2><form method="get" class="ff-customer-search"><input type="search" name="q" value="' . \e($query) . '" placeholder="Search name or email"><button type="submit">Search</button></form></div>';
        echo '<div class="ff-customer-table"><div class="ff-customer-head"><span>Customer</span><span>Orders</span><span>Total Spent</span><span>Last Order</span><span>Status</span><span>Action</span></div>';
        foreach ($customers as $row) {
            $key = (string)$row['customer_key'];
            echo '<div class="ff-customer-row">';
            echo '<strong data-label="Customer"><span>' . \e((string)$row['customer_name']) . '</span><small>' . \e((string)($row['customer_email'] ?: 'No email captured')) . '</small></strong>';
            echo '<span data-label="Orders">' . \e(number_format((int)$row['order_count'])) . '</span>';
            echo '<span data-label="Total Spent">' . \e(self::money((float)$row['total_spent'])) . '</span>';
            echo '<span data-label="Last Order">' . \e(self::dateShort((string)$row['last_order_at'])) . '</span>';
            echo '<span data-label="Status"><em class="ff-status ' . ((int)$row['order_count'] > 1 ? 'available' : 'draft') . '">' . ((int)$row['order_count'] > 1 ? 'Repeat Buyer' : 'New Buyer') . '</em></span>';
            echo '<span data-label="Action"><a href="' . \e(\app_url('farmer/customers?customer=' . rawurlencode($key))) . '">View History</a></span>';
            echo '</div>';
        }
        if ($customers === []) {
            echo '<p>No customers found yet. Customer records will appear here after FreshRoots orders come in.</p>';
        }
        echo '</div></article>';

        echo '<article class="ff-panel" id="repeat-buyers"><div class="ff-panel-head"><h2>Repeat Buyers</h2><span>Customers with multiple orders.</span></div><div class="ff-repeat-list">';
        foreach (array_slice($repeatCustomers, 0, 8) as $row) {
            echo '<a href="' . \e(\app_url('farmer/customers?customer=' . rawurlencode((string)$row['customer_key']))) . '"><strong>' . \e((string)$row['customer_name']) . '</strong><span>' . \e(number_format((int)$row['order_count'])) . ' orders</span><em>' . \e(self::money((float)$row['total_spent'])) . '</em></a>';
        }
        if ($repeatCustomers === []) {
            echo '<p>No repeat buyers yet.</p>';
        }
        echo '</div></article>';
        echo '</section>';

        echo '<article class="ff-panel ff-customer-history" id="customer-order-history"><div class="ff-panel-head"><h2>' . \e($selected ? 'Order History: ' . (string)$selected['customer_name'] : 'Customer Order History') . '</h2><span>' . \e($selected ? (string)($selected['customer_email'] ?: 'Customer order activity') : 'Latest customer orders for this farm.') . '</span></div>';
        echo '<div class="ff-history-table"><div class="ff-history-head"><span>Order</span><span>Date</span><span>Items</span><span>Total</span><span>Payment</span><span>Fulfillment</span><span>Status</span></div>';
        foreach ($orders as $row) {
            echo '<div class="ff-history-row">';
            echo '<strong data-label="Order">#' . \e((string)($row['order_number'] ?: 'SA-' . $row['id'])) . '</strong>';
            echo '<span data-label="Date">' . \e(self::dateTimeShort((string)($row['placed_at'] ?: $row['created_at']))) . '</span>';
            echo '<span data-label="Items">' . \e(number_format((int)$row['item_count'])) . ' items / ' . \e(number_format((float)$row['item_quantity'], 0)) . ' qty</span>';
            echo '<span data-label="Total">' . \e(self::money((float)$row['gross_total'])) . '</span>';
            echo '<span data-label="Payment">' . \e(self::label((string)($row['payment_status'] ?: 'pending'))) . '</span>';
            echo '<span data-label="Fulfillment">' . \e(self::fulfillmentMethod($row)) . '</span>';
            echo '<span data-label="Status"><em class="ff-status ' . \e(strtolower((string)$row['status'])) . '">' . \e(self::label((string)$row['status'])) . '</em></span>';
            echo '</div>';
        }
        if ($orders === []) {
            echo '<p>No order history found for this customer.</p>';
        }
        echo '</div></article></section>';
    }

    private static function reviewsPage(int $vendorId): void
    {
        $summary = self::reviewSummary($vendorId);
        $reviews = self::reviewRows($vendorId);

        echo '<section class="ff-reviews-wrap">';
        echo '<article class="ff-panel ff-management-head"><div><p>Review Centre</p><h2>Product Reviews, Farm Reviews & Responses</h2><span>Track customer feedback across FreshRoots produce and respond from one place.</span></div></article>';
        echo '<section class="ff-review-metrics">';
        foreach ([
            ['Average Rating', number_format((float)$summary['average_rating'], 1), 'Average approved and pending product rating.'],
            ['Total Reviews', number_format((int)$summary['total_reviews']), 'All reviews on this farm produce.'],
            ['Pending Reviews', number_format((int)$summary['pending_reviews']), 'Reviews still waiting for moderation.'],
            ['Responses Sent', number_format((int)$summary['responses_sent']), 'Replies saved by the farm team.'],
        ] as $card) {
            echo '<article class="ff-metric"><b>' . self::icon('star') . '</b><div><strong>' . \e($card[1]) . '</strong><span>' . \e($card[0]) . '</span><small>' . \e($card[2]) . '</small></div></article>';
        }
        echo '</section>';

        echo '<article class="ff-panel ff-review-list" id="product-reviews"><div class="ff-panel-head"><h2>Product Reviews</h2><span>Latest customer feedback.</span></div>';
        foreach ($reviews as $row) {
            echo '<div class="ff-review-card">';
            echo '<div><strong>' . \e((string)($row['title'] ?: 'Customer review')) . '</strong><span>' . str_repeat('★', max(1, min(5, (int)$row['rating']))) . str_repeat('☆', max(0, 5 - (int)$row['rating'])) . '</span><small>' . \e((string)$row['product_name']) . ' - ' . \e((string)($row['customer_name'] ?: 'Customer')) . ' - ' . \e(self::dateShort((string)$row['created_at'])) . '</small></div>';
            echo '<p>' . \e((string)($row['body'] ?: 'No written comment.')) . '</p>';
            if ((string)($row['response'] ?? '') !== '') {
                echo '<blockquote><b>Your response</b><span>' . \e((string)$row['response']) . '</span></blockquote>';
            }
            echo '<form method="post"><input type="hidden" name="csrf_token" value="' . \e(\getCsrfToken()) . '"><input type="hidden" name="intent" value="review_response"><input type="hidden" name="review_id" value="' . \e((string)$row['id']) . '"><textarea name="response" placeholder="Write a professional response to this review">' . \e((string)($row['response'] ?? '')) . '</textarea><button type="submit">Save Response</button></form>';
            echo '</div>';
        }
        if ($reviews === []) {
            echo '<p>No reviews yet. Product and farm reviews will appear here after customers submit them.</p>';
        }
        echo '</article></section>';
    }

    private static function verificationPage(array $vendor, array $applicationData): void
    {
        $vendorId = (int)$vendor['id'];
        $documents = self::verificationDocuments($vendorId);
        $status = (string)($vendor['kyc_status'] ?? $vendor['status'] ?? 'pending');

        echo '<section class="ff-verification-wrap">';
        echo '<article class="ff-panel ff-management-head"><div><p>Verification Centre</p><h2>Verification Status, Farm Details & Documents</h2><span>Seller Africa reviews every FreshRoots application before store activation.</span></div><a class="ff-primary-action" href="' . \e(\app_url('vendor/kyc')) . '">Upload KYC</a></article>';
        echo '<section class="ff-verification-grid">';
        echo '<article class="ff-panel"><div class="ff-panel-head"><h2>Verification Status</h2><span>Current review state.</span></div><div class="ff-verification-status"><b>' . self::icon($status === 'approved' ? 'check' : 'alert') . '</b><strong>' . \e(self::label($status)) . '</strong><p>' . \e($status === 'approved' ? 'Your farm has passed KYC review.' : 'Your FreshRoots application or documents are still being reviewed.') . '</p></div></article>';
        echo '<article class="ff-panel"><div class="ff-panel-head"><h2>Business / Farm Details</h2><span>Application snapshot.</span></div>' . self::applicationHighlights($applicationData) . '</article>';
        echo '</section>';
        echo '<article class="ff-panel"><div class="ff-panel-head"><h2>Documents & Certifications</h2><span>Uploaded verification files.</span></div><div class="ff-doc-table"><div class="ff-doc-head"><span>Document</span><span>Status</span><span>Submitted</span></div>';
        foreach ($documents as $row) {
            echo '<div class="ff-doc-row"><strong data-label="Document">' . \e(self::label((string)$row['document_type'])) . '</strong><span data-label="Status"><em class="ff-status ' . \e(strtolower((string)$row['status'])) . '">' . \e(self::label((string)$row['status'])) . '</em></span><span data-label="Submitted">' . \e(self::dateTimeShort((string)$row['submitted_at'])) . '</span></div>';
        }
        if ($documents === []) {
            echo '<p>No documents have been uploaded yet.</p>';
        }
        echo '</div></article></section>';
    }

    private static function settingsPage(int $vendorId, array $vendor): void
    {
        $settings = self::farmerSettings($vendorId);
        $fulfillment = self::fulfillmentSettings($vendorId);
        $store = self::storeSettings($vendorId);
        $selectedCategories = json_decode((string)($settings['farm_categories'] ?? '[]'), true);
        $selectedCategories = is_array($selectedCategories) ? array_map('strval', $selectedCategories) : [];

        echo '<section class="ff-settings-wrap">';
        echo '<article class="ff-panel ff-management-head"><div><p>Farm Settings</p><h2>Store Settings, Notifications, Policies & Defaults</h2><span>Control operational defaults without changing your public FreshRoots design.</span></div></article>';
        echo '<form method="post" class="ff-settings-form"><input type="hidden" name="csrf_token" value="' . \e(\getCsrfToken()) . '"><input type="hidden" name="intent" value="farmer_settings">';
        echo '<article class="ff-panel" id="store-settings"><div class="ff-panel-head"><h2>Notifications</h2><span>Choose where important alerts go.</span></div><div class="ff-form-grid">';
        echo '<label>Notification email<input name="notification_email" value="' . \e((string)($settings['notification_email'] ?: $vendor['store_email'] ?? '')) . '"></label>';
        foreach (['order_notifications' => 'Order notifications', 'review_notifications' => 'Review notifications', 'low_stock_notifications' => 'Low-stock notifications'] as $field => $label) {
            $checked = (string)($settings[$field] ?? '1') !== '0';
            echo '<label class="ff-check-field"><input type="checkbox" name="' . \e($field) . '" value="1"' . ($checked ? ' checked' : '') . '> ' . \e($label) . '</label>';
        }
        echo '</div></article>';
        echo '<article class="ff-panel" id="farm-categories"><div class="ff-panel-head"><h2>Farm Categories</h2><span>Choose 1 to 3 categories shown on the public FreshRoots pages.</span></div><div class="ff-category-picks">';
        foreach (self::FARM_CATEGORIES as $category) {
            echo '<label><input data-farm-category type="checkbox" name="farm_categories[]" value="' . \e($category) . '"' . (in_array($category, $selectedCategories, true) ? ' checked' : '') . '><span>' . \e($category) . '</span></label>';
        }
        echo '</div></article>';
        echo '<article class="ff-panel" id="policies"><div class="ff-panel-head"><h2>Policies</h2><span>Show clear expectations to buyers.</span></div><div class="ff-form-grid">';
        echo '<label class="ff-wide">Store policy<textarea name="store_policy">' . \e((string)($settings['store_policy'] ?? '')) . '</textarea></label>';
        echo '<label class="ff-wide">Returns / refunds policy<textarea name="return_policy">' . \e((string)($settings['return_policy'] ?? '')) . '</textarea></label>';
        echo '</div></article>';
        echo '<article class="ff-panel" id="vacation-mode"><div class="ff-panel-head"><h2>Vacation Mode</h2><span>Pause new FreshRoots orders while keeping listings saved.</span></div><div class="ff-form-grid">';
        echo '<label class="ff-method-card ff-closure-card"><input type="checkbox" name="vacation_mode" value="1"' . ((int)($store['vacation_mode'] ?? 0) === 1 ? ' checked' : '') . '><span></span><strong>Temporary Closure</strong><small>Customers can see that your farm is temporarily unavailable.</small></label>';
        echo '<label>Closure message<input name="vacation_message" value="' . \e((string)($store['vacation_message'] ?? '')) . '"></label>';
        echo '</div></article>';
        echo '<article class="ff-panel" id="pickup-shipping-defaults"><div class="ff-panel-head"><h2>Pickup / Shipping Defaults</h2><span>Default fulfillment values used across the farm.</span></div><div class="ff-form-grid">';
        self::textInput('Default carrier', 'default_carrier', $fulfillment);
        self::moneyInput('Shipping fee / rate', 'shipping_fee', $fulfillment);
        self::textInput('Pickup location', 'pickup_location', $fulfillment);
        self::textInput('Pickup hours', 'pickup_hours', $fulfillment);
        echo '<label>Default preparation time<select name="default_preparation_time">' . self::keyedOptions(['same_day' => 'Same Day', '1_day' => '1 Day', '2_days' => '2 Days', 'custom' => 'Custom'], (string)($fulfillment['default_preparation_time'] ?? '1_day')) . '</select></label>';
        self::textInput('Custom preparation time', 'custom_preparation_time', $fulfillment);
        echo '<input type="hidden" name="shipping_enabled" value="' . \e((string)(int)($fulfillment['shipping_enabled'] ?? 0)) . '"><input type="hidden" name="seller_africa_delivery_enabled" value="' . \e((string)(int)($fulfillment['seller_africa_delivery_enabled'] ?? 0)) . '"><input type="hidden" name="farmer_local_delivery_enabled" value="' . \e((string)(int)($fulfillment['farmer_local_delivery_enabled'] ?? 0)) . '"><input type="hidden" name="farm_pickup_enabled" value="' . \e((string)(int)($fulfillment['farm_pickup_enabled'] ?? 0)) . '">';
        echo '</div></article><div class="ff-sticky-save"><button type="submit">Save Settings</button></div></form></section>';
    }

    private static function supportPage(int $vendorId): void
    {
        $requests = self::supportRequests($vendorId);
        echo '<section class="ff-support-wrap">';
        echo '<article class="ff-panel ff-management-head"><div><p>Support Centre</p><h2>Help Centre, Order Issues, Selling Guide & Policies</h2><span>Get help from Seller Africa FreshRoots support and keep a record of your requests.</span></div></article>';
        echo '<section class="ff-support-grid">';
        echo '<article class="ff-panel"><div class="ff-panel-head"><h2>Contact Support</h2><span>Send an issue to the team.</span></div><form method="post" class="ff-support-form"><input type="hidden" name="csrf_token" value="' . \e(\getCsrfToken()) . '"><input type="hidden" name="intent" value="support_request"><label>Subject<input name="subject" required></label><label>Category<select name="category">' . self::keyedOptions(['general' => 'General', 'order_issue' => 'Order Issue', 'produce_listing' => 'Produce Listing', 'payout' => 'Payout', 'verification' => 'Verification', 'technical' => 'Technical'], 'general') . '</select></label><label>Priority<select name="priority">' . self::keyedOptions(['normal' => 'Normal', 'urgent' => 'Urgent'], 'normal') . '</select></label><label class="ff-wide">Message<textarea name="message" required></textarea></label><button type="submit">Send Request</button></form></article>';
        echo '<article class="ff-panel"><div class="ff-panel-head"><h2>Selling Guide</h2><span>Quick links.</span></div><div class="ff-support-links"><a href="' . \e(\app_url('farmer/produce')) . '">Add or update produce</a><a href="' . \e(\app_url('farmer/orders')) . '">Manage order issues</a><a href="' . \e(\app_url('farmer/fulfillment')) . '">Set delivery and pickup rules</a><a href="' . \e(\app_url('farmer/verification')) . '">Review verification status</a></div></article>';
        echo '</section>';
        echo '<article class="ff-panel"><div class="ff-panel-head"><h2>Recent Support Requests</h2><span>Open, in-review, and resolved requests.</span></div><div class="ff-support-list">';
        foreach ($requests as $row) {
            echo '<div><strong>' . \e((string)$row['subject']) . '</strong><span>' . \e(self::label((string)$row['category'])) . ' - ' . \e(self::label((string)$row['status'])) . '</span><em>' . \e(self::dateTimeShort((string)$row['created_at'])) . '</em></div>';
        }
        if ($requests === []) {
            echo '<p>No support requests yet.</p>';
        }
        echo '</div></article></section>';
    }

    private static function produceRows(int $vendorId, int $limit): array
    {
        $categorySelect = \table_exists('product_categories') && \table_exists('categories')
            ? "(SELECT c.name FROM product_categories pc INNER JOIN categories c ON c.id = pc.category_id WHERE pc.product_id = p.id ORDER BY c.name LIMIT 1) category_name"
            : "NULL category_name";

        return self::rows(
            "SELECT p.id, p.name, p.slug, p.status, p.visibility, p.stock_status, p.stock_quantity, p.regular_price, p.currency, p.updated_at,
                    f.path image_path, {$categorySelect},
                    fpd.selling_unit, fpd.low_stock_threshold, fpd.harvest_date, fpd.seasonal_mode, fpd.seasonal_start_month, fpd.seasonal_end_month
             FROM products p
             LEFT JOIN product_media pm ON pm.product_id = p.id AND pm.role = 'primary'
             LEFT JOIN files f ON f.id = pm.file_id
             LEFT JOIN farmer_produce_details fpd ON fpd.product_id = p.id
             WHERE p.vendor_id = ? AND p.status <> 'archived'
             ORDER BY p.updated_at DESC, p.id DESC
             LIMIT {$limit}",
            [$vendorId]
        );
    }

    private static function produceManagementRow(array $row): void
    {
        $productId = (int)$row['id'];
        $qty = (int)($row['stock_quantity'] ?? 0);
        $threshold = (int)($row['low_stock_threshold'] ?? 5);
        $status = self::produceStatus($row);
        $image = self::fileUrl($row['image_path'] ?? null);
        $visibility = (string)($row['visibility'] ?? 'visible') === 'hidden' ? 'Hidden' : 'Visible';
        $url = (string)($row['slug'] ?? '') !== '' ? \app_url('product/' . rawurlencode((string)$row['slug'])) : \app_url('farmer/produce');

        echo '<div class="ff-produce-management-row">';
        echo '<span data-label="Product image">' . ($image !== '' ? '<img src="' . \e($image) . '" alt="">' : '<b>' . self::icon('leaf') . '</b>') . '</span>';
        echo '<strong data-label="Product name">' . \e((string)$row['name']) . '</strong>';
        echo '<span data-label="Category">' . \e((string)($row['category_name'] ?: 'Uncategorized')) . '</span>';
        echo '<span data-label="Price">' . \e(self::money((float)($row['regular_price'] ?? 0))) . '</span>';
        echo '<span data-label="Selling unit">' . \e((string)($row['selling_unit'] ?: 'lb')) . '</span>';
        echo '<form data-label="Available quantity" method="post" class="ff-stock-mini"><input type="hidden" name="csrf_token" value="' . \e(\getCsrfToken()) . '"><input type="hidden" name="intent" value="produce_action"><input type="hidden" name="produce_action" value="update_stock"><input type="hidden" name="product_id" value="' . \e((string)$productId) . '"><input type="number" min="0" name="stock_quantity" value="' . \e((string)$qty) . '"><button type="submit">Save</button></form>';
        echo '<span data-label="Harvest date">' . \e(self::dateShort((string)($row['harvest_date'] ?? ''))) . '</span>';
        echo '<span data-label="Status"><em class="ff-status ' . \e(strtolower(str_replace(' ', '-', $status))) . '">' . \e($status) . '</em></span>';
        echo '<span data-label="Visibility">' . \e($visibility) . '</span>';
        echo '<span data-label="Last updated">' . \e(self::dateShort((string)($row['updated_at'] ?? ''))) . '</span>';
        echo '<span data-label="Actions" class="ff-actions-menu">';
        echo '<a href="' . \e($url) . '" target="_blank" rel="noopener">View</a>';
        echo '<a href="' . \e(\app_url('vendor/product-upload?edit=' . $productId)) . '">Edit</a>';
        foreach ([
            'pause' => 'Pause',
            'duplicate' => 'Duplicate',
            'mark_sold_out' => 'Mark Sold Out',
            'delete' => 'Delete',
        ] as $action => $label) {
            echo '<form method="post"><input type="hidden" name="csrf_token" value="' . \e(\getCsrfToken()) . '"><input type="hidden" name="intent" value="produce_action"><input type="hidden" name="produce_action" value="' . \e($action) . '"><input type="hidden" name="product_id" value="' . \e((string)$productId) . '"><button type="submit">' . \e($label) . '</button></form>';
        }
        echo '</span></div>';
    }

    private static function availableThisWeekBulkPanel(int $vendorId): void
    {
        $rows = self::rows(
            'SELECT p.id, p.name, p.stock_quantity, COALESCE(fpd.selling_unit, fwa.selling_unit, "lb") selling_unit, fwa.available_this_week, fwa.available_quantity
             FROM products p
             LEFT JOIN farmer_produce_details fpd ON fpd.product_id = p.id
             LEFT JOIN farmer_weekly_availability fwa ON fwa.product_id = p.id AND fwa.vendor_id = p.vendor_id
             WHERE p.vendor_id = ? AND p.status <> "archived"
             ORDER BY p.name ASC
             LIMIT 100',
            [$vendorId]
        );

        echo '<article class="ff-panel ff-available-bulk" id="available-this-week"><div class="ff-panel-head"><h2>Available This Week</h2><span>Update harvested quantities quickly.</span></div>';
        if ($rows === []) {
            echo '<p>Add produce first, then weekly availability will appear here.</p></article>';
            return;
        }

        echo '<form method="post"><input type="hidden" name="csrf_token" value="' . \e(\getCsrfToken()) . '"><input type="hidden" name="intent" value="available_this_week"><div>';
        foreach ($rows as $row) {
            $productId = (int)$row['id'];
            $checked = (int)($row['available_this_week'] ?? 0) === 1 ? ' checked' : '';
            echo '<label><input type="checkbox" name="available_product_ids[]" value="' . \e((string)$productId) . '"' . $checked . '><strong>' . \e((string)$row['name']) . '</strong><input type="number" min="0" step="0.01" name="available_quantity[' . \e((string)$productId) . ']" value="' . \e((string)($row['available_quantity'] ?? $row['stock_quantity'] ?? '')) . '"><select name="selling_unit[' . \e((string)$productId) . ']">' . self::options(self::sellingUnits(), (string)($row['selling_unit'] ?? 'lb')) . '</select></label>';
        }
        echo '</div><button class="ff-save-btn" type="submit">Update Availability</button></form></article>';
    }

    private static function seasonalProducePanel(array $rows): void
    {
        echo '<article class="ff-panel ff-seasonal-list" id="seasonal-produce"><div class="ff-panel-head"><h2>Seasonal Produce</h2><span>Marketplace messaging can use these windows.</span></div><div>';
        $printed = 0;
        foreach ($rows as $row) {
            $mode = (string)($row['seasonal_mode'] ?? 'year_round');
            if ($mode === 'seasonal') {
                $window = self::monthName((int)($row['seasonal_start_month'] ?? 0)) . ' -> ' . self::monthName((int)($row['seasonal_end_month'] ?? 0));
            } else {
                $window = 'Available year-round';
            }
            echo '<p><strong>' . \e((string)$row['name']) . '</strong><span>' . \e($window) . '</span></p>';
            $printed++;
        }
        if ($printed === 0) {
            echo '<p><strong>No seasonal windows yet</strong><span>Add produce and choose year-round or seasonal availability.</span></p>';
        }
        echo '</div></article>';
    }

    private static function addProduceModal(int $vendorId): void
    {
        $categories = ['Vegetables', 'Fruits', 'Leafy Greens', 'Herbs', 'Roots & Tubers', 'Grains', 'Honey & Farm Goods', 'Other'];
        echo '<div class="ff-modal" id="add-produce" aria-hidden="true"><div class="ff-modal-backdrop" data-ff-modal-close></div><section class="ff-modal-card" role="dialog" aria-modal="true" aria-labelledby="ff-add-produce-title">';
        echo '<div class="ff-modal-head"><div><p>Add New Produce</p><h2 id="ff-add-produce-title">Create FreshRoots Listing</h2></div><a href="' . \e(\app_url('farmer/produce')) . '" data-ff-modal-close>Close</a></div>';
        echo '<form method="post" enctype="multipart/form-data" class="ff-produce-form"><input type="hidden" name="csrf_token" value="' . \e(\getCsrfToken()) . '"><input type="hidden" name="intent" value="add_produce">';
        echo '<fieldset><legend>Basic Information</legend><label>Product name *<input name="name" required></label><label>Category *<select name="category" required>' . self::options($categories, '') . '</select></label><label>Subcategory<input name="subcategory"></label><label>Farm / farm location<input name="farm_location"></label><label class="ff-wide">Description *<textarea name="description" required></textarea></label><label class="ff-wide">Photos<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label></fieldset>';
        echo '<fieldset><legend>Pricing</legend><label>Price *<input type="number" step="0.01" min="0" name="regular_price" required></label><label>Selling unit<select name="selling_unit">' . self::options(self::sellingUnits(), 'lb') . '</select></label><label>Minimum order quantity<input type="number" step="0.01" min="0" name="minimum_order_quantity"></label><label>Bulk pricing<textarea name="bulk_pricing"></textarea></label></fieldset>';
        echo '<fieldset><legend>Inventory</legend><label>Quantity available<input type="number" min="0" name="stock_quantity" value="0"></label><label>Low-stock threshold<input type="number" min="0" name="low_stock_threshold" value="5"></label><label>Maximum quantity per order<input type="number" min="0" name="maximum_quantity_per_order"></label></fieldset>';
        echo '<fieldset><legend>Freshness</legend><label>Harvest date<input type="date" name="harvest_date" value="' . \e(date('Y-m-d')) . '"></label><label>Estimated freshness window<input type="number" min="0" name="freshness_window_days" placeholder="Days"></label><label>Best-before / use-by<input type="date" name="best_before_date"></label></fieldset>';
        echo '<fieldset><legend>Product Attributes</legend><label><span class="ff-check"><input type="checkbox" name="organic_claim" value="1"> Organic claim</span></label><label>Farming method<input name="farming_method" placeholder="Organic, hydroponic, regenerative..."></label><label>Variety<input name="variety"></label><label>Grade / quality<input name="quality_grade"></label><label class="ff-wide">Certifications<textarea name="certifications"></textarea></label></fieldset>';
        echo '<fieldset><legend>Fulfillment</legend><label><span class="ff-check"><input type="checkbox" name="shipping_available" value="1"> Shipping available</span></label><label><span class="ff-check"><input type="checkbox" name="local_delivery_available" value="1"> Local delivery available</span></label><label><span class="ff-check"><input type="checkbox" name="farm_pickup_available" value="1"> Farm pickup available</span></label><label>Preparation time<input name="preparation_time" placeholder="24 hours"></label><label class="ff-wide">Applicable delivery areas<textarea name="delivery_areas"></textarea></label></fieldset>';
        echo '<fieldset><legend>Seasonal Produce</legend><label>Availability<select name="seasonal_mode"><option value="year_round">Available year-round</option><option value="seasonal">Seasonal window</option></select></label><label>Start month<select name="seasonal_start_month">' . self::monthOptions(0) . '</select></label><label>End month<select name="seasonal_end_month">' . self::monthOptions(0) . '</select></label></fieldset>';
        echo '<div class="ff-form-actions"><button type="submit" name="intent" value="save_produce_draft">Save Draft</button><button type="button" data-ff-preview>Preview</button><button type="submit" name="intent" value="add_produce">Publish</button></div></form></section></div>';
    }

    private static function ordersTable(int $vendorId): void
    {
        $tab = self::orderTab();
        $selectedSplitId = max(0, (int)($_GET['order'] ?? 0));
        $rows = self::farmerOrders($vendorId, $tab);
        $counts = self::orderTabCounts($vendorId);

        echo '<section class="ff-orders-wrap">';
        echo '<article class="ff-panel ff-management-head"><div><p>Complete Order Lifecycle</p><h2>Orders</h2><span>Accept orders, prepare produce, manage delivery, add tracking, handle issues, and keep a full status timeline.</span></div></article>';
        echo '<article class="ff-panel"><div class="ff-order-tabs">';
        foreach (self::orderTabs() as $key => $label) {
            $url = \app_url('farmer/orders' . ($key === 'all' ? '' : '?status=' . rawurlencode($key)));
            echo '<a class="' . ($tab === $key ? 'is-active' : '') . '" href="' . \e($url) . '">' . \e($label) . '<b>' . \e((string)($counts[$key] ?? 0)) . '</b></a>';
        }
        echo '</div></article>';

        if ($selectedSplitId > 0) {
            self::orderDetailsPanel($vendorId, $selectedSplitId);
        }

        echo '<article class="ff-panel ff-order-centre"><div class="ff-order-management-table">';
        echo '<div class="ff-order-management-head"><span>Order ID</span><span>Date / Time</span><span>Customer</span><span>Items</span><span>Qty</span><span>Total</span><span>SA Fee</span><span>Earnings</span><span>Payment</span><span>Fulfillment</span><span>Delivery / Pickup</span><span>Status</span><span>Actions</span></div>';
        foreach ($rows as $row) {
            self::orderManagementRow($row, $vendorId);
        }
        if ($rows === []) {
            echo '<p>No FreshRoots orders yet.</p>';
        }
        echo '</div></article></section>';
    }

    private static function farmerOrders(int $vendorId, string $tab): array
    {
        $params = [$vendorId];
        $where = 'ovs.vendor_id = ?';
        if ($tab !== 'all') {
            if (in_array($tab, ['accepted', 'preparing', 'ready'], true)) {
                $stageMap = [
                    'accepted' => ['accepted'],
                    'preparing' => ['preparing'],
                    'ready' => ['ready_pickup', 'ready_delivery'],
                ];
                $where .= ' AND COALESCE(last_stage.stage, "new") IN (' . implode(',', array_fill(0, count($stageMap[$tab]), '?')) . ')';
                array_push($params, ...$stageMap[$tab]);
            } elseif ($tab === 'new') {
                $where .= ' AND ovs.status = "pending"';
            } elseif ($tab === 'delivered') {
                $where .= ' AND ovs.status = "completed"';
            } else {
                $where .= ' AND ovs.status = ?';
                $params[] = $tab;
            }
        }

        return self::rows(
            "SELECT ovs.*, o.order_number, o.created_at order_created_at, o.placed_at, o.payment_status, o.customer_note,
                    o.shipping_total order_shipping_total, o.grand_total, COALESCE(NULLIF(u.display_name, ''), o.guest_email, 'Customer') customer_name,
                    COALESCE(u.email, o.guest_email) customer_email,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id AND oi.vendor_id = ovs.vendor_id AND oi.item_type = 'product') item_count,
                    (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id AND oi.vendor_id = ovs.vendor_id AND oi.item_type = 'product') item_quantity,
                    s.tracking_number, s.tracking_url, s.status shipment_status,
                    last_stage.stage farmer_stage
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             LEFT JOIN users u ON u.id = o.customer_id
             LEFT JOIN shipments s ON s.id = (SELECT s2.id FROM shipments s2 WHERE s2.order_id = o.id AND s2.vendor_id = ovs.vendor_id ORDER BY s2.id DESC LIMIT 1)
             LEFT JOIN farmer_order_lifecycle last_stage ON last_stage.id = (SELECT fol.id FROM farmer_order_lifecycle fol WHERE fol.vendor_split_id = ovs.id ORDER BY fol.id DESC LIMIT 1)
             WHERE {$where}
             ORDER BY ovs.created_at DESC, ovs.id DESC
             LIMIT 100",
            $params
        );
    }

    private static function orderManagementRow(array $row, int $vendorId): void
    {
        $splitId = (int)$row['id'];
        $stage = self::displayOrderStage($row);
        $fulfillment = self::fulfillmentMethod($row);
        $details = self::deliveryDetails($row);
        echo '<div class="ff-order-management-row">';
        echo '<strong data-label="Order ID"><a href="' . \e(\app_url('farmer/orders?order=' . $splitId)) . '">#' . \e((string)($row['order_number'] ?: 'SA-' . $splitId)) . '</a></strong>';
        echo '<span data-label="Order date/time">' . \e(self::dateTimeShort((string)($row['placed_at'] ?: $row['order_created_at'] ?? $row['created_at'] ?? ''))) . '</span>';
        echo '<span data-label="Customer"><b>' . \e((string)$row['customer_name']) . '</b><small>' . \e((string)($row['customer_email'] ?? '')) . '</small></span>';
        echo '<span data-label="Items">' . \e((string)(int)($row['item_count'] ?? 0)) . '</span>';
        echo '<span data-label="Quantity">' . \e(number_format((float)($row['item_quantity'] ?? 0), 0)) . '</span>';
        echo '<span data-label="Order total">' . \e(self::money((float)($row['gross_total'] ?? 0))) . '</span>';
        echo '<span data-label="Seller Africa fee">' . \e(self::money((float)($row['platform_commission'] ?? 0))) . '</span>';
        echo '<span data-label="Farmer earnings">' . \e(self::money((float)($row['vendor_earning'] ?? 0))) . '</span>';
        echo '<span data-label="Payment status">' . \e(self::label((string)($row['payment_status'] ?? 'pending'))) . '</span>';
        echo '<span data-label="Fulfillment method">' . \e($fulfillment) . '</span>';
        echo '<span data-label="Delivery/pickup details">' . \e($details) . '</span>';
        echo '<span data-label="Current status"><em class="ff-status ' . \e(strtolower(str_replace(' ', '-', $stage))) . '">' . \e($stage) . '</em></span>';
        echo '<span data-label="Actions" class="ff-order-action-stack"><a href="' . \e(\app_url('farmer/orders?order=' . $splitId)) . '">Open</a>' . self::orderActionForms($splitId, $stage, $fulfillment, false) . '</span>';
        echo '</div>';
    }

    private static function orderDetailsPanel(int $vendorId, int $splitId): void
    {
        $order = self::rows("SELECT * FROM (" . self::orderDetailSql() . ") detail WHERE detail.id = ? LIMIT 1", [$vendorId, $splitId]);
        $order = $order[0] ?? null;
        if (!$order) {
            echo '<article class="ff-panel"><p>Order was not found for this farmer.</p></article>';
            return;
        }
        $items = self::orderItems((int)$order['order_id'], $vendorId);
        $timeline = self::orderTimeline($splitId);
        $notes = self::orderNotes($splitId);
        $stage = self::displayOrderStage($order);
        $fulfillment = self::fulfillmentMethod($order);

        echo '<article class="ff-panel ff-order-detail"><div class="ff-panel-head"><div><h2>Order #' . \e((string)($order['order_number'] ?: 'SA-' . $splitId)) . '</h2><p>' . \e(self::label($stage)) . ' - ' . \e($fulfillment) . '</p></div><a href="' . \e(\app_url('farmer/orders')) . '">Close Details</a></div>';
        echo '<div class="ff-order-detail-grid">';
        echo '<section><h3>Product Breakdown</h3><div class="ff-detail-list">';
        foreach ($items as $item) {
            echo '<div><strong>' . \e((string)$item['name']) . '</strong><span>' . \e(number_format((float)$item['quantity'], 0)) . ' x ' . \e(self::money((float)$item['unit_price'])) . '</span><em>' . \e(self::money((float)$item['total'])) . '</em></div>';
        }
        if ($items === []) {
            echo '<p>No product line items found for this farmer split.</p>';
        }
        echo '</div></section>';
        echo '<section><h3>Delivery / Pickup</h3><p>' . \e(self::deliveryDetails($order)) . '</p><p><strong>Customer instructions:</strong> ' . \e((string)($order['customer_note'] ?: 'No special instructions.')) . '</p><p><strong>Tracking:</strong> ' . \e((string)($order['tracking_number'] ?: 'Not added')) . '</p></section>';
        echo '<section><h3>Payment Breakdown</h3>' . self::financialBreakdown($order) . '</section>';
        echo '<section><h3>Actions</h3>' . self::orderActionForms($splitId, $stage, $fulfillment, true) . '</section>';
        echo '<section><h3>Timeline / History</h3><div class="ff-timeline">';
        foreach ($timeline as $entry) {
            echo '<div><b></b><strong>' . \e(self::label((string)$entry['stage'])) . '</strong><span>' . \e(self::dateTimeShort((string)$entry['created_at'])) . '</span><p>' . \e((string)($entry['note'] ?: 'Status updated.')) . '</p></div>';
        }
        if ($timeline === []) {
            echo '<div><b></b><strong>New</strong><span>' . \e(self::dateTimeShort((string)($order['created_at'] ?? ''))) . '</span><p>Order received.</p></div>';
        }
        echo '</div></section>';
        echo '<section><h3>Internal Notes</h3><form method="post" class="ff-note-form"><input type="hidden" name="csrf_token" value="' . \e(\getCsrfToken()) . '"><input type="hidden" name="intent" value="farmer_order_note"><input type="hidden" name="split_id" value="' . \e((string)$splitId) . '"><textarea name="note" placeholder="Add an internal note for this order"></textarea><button type="submit">Save Note</button></form><div class="ff-notes">';
        foreach ($notes as $note) {
            echo '<p><strong>' . \e(self::dateTimeShort((string)$note['created_at'])) . '</strong><span>' . \e((string)$note['note']) . '</span></p>';
        }
        echo '</div></section></div></article>';
    }

    private static function orderTabs(): array
    {
        return [
            'all' => 'All',
            'new' => 'New',
            'accepted' => 'Accepted',
            'preparing' => 'Preparing',
            'ready' => 'Ready',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
        ];
    }

    private static function orderTab(): string
    {
        $tab = strtolower((string)($_GET['status'] ?? 'all'));
        return array_key_exists($tab, self::orderTabs()) ? $tab : 'all';
    }

    private static function orderTabCounts(int $vendorId): array
    {
        $counts = array_fill_keys(array_keys(self::orderTabs()), 0);
        $rows = self::farmerOrders($vendorId, 'all');
        $counts['all'] = count($rows);
        foreach ($rows as $row) {
            $stage = strtolower(str_replace(' ', '_', self::displayOrderStage($row)));
            $key = match ($stage) {
                'new' => 'new',
                'accepted' => 'accepted',
                'preparing' => 'preparing',
                'ready_for_pickup', 'ready_for_delivery' => 'ready',
                'shipped' => 'shipped',
                'delivered' => 'delivered',
                'cancelled' => 'cancelled',
                'refunded' => 'refunded',
                default => 'all',
            };
            if (isset($counts[$key])) {
                $counts[$key]++;
            }
        }
        return $counts;
    }

    private static function orderDetailSql(): string
    {
        return "SELECT ovs.*, o.order_number, o.created_at order_created_at, o.placed_at, o.payment_status, o.customer_note,
                    o.shipping_total order_shipping_total, o.grand_total, COALESCE(NULLIF(u.display_name, ''), o.guest_email, 'Customer') customer_name,
                    COALESCE(u.email, o.guest_email) customer_email,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id AND oi.vendor_id = ovs.vendor_id AND oi.item_type = 'product') item_count,
                    (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id AND oi.vendor_id = ovs.vendor_id AND oi.item_type = 'product') item_quantity,
                    s.tracking_number, s.tracking_url, s.status shipment_status,
                    last_stage.stage farmer_stage
                FROM order_vendor_splits ovs
                INNER JOIN orders o ON o.id = ovs.order_id
                LEFT JOIN users u ON u.id = o.customer_id
                LEFT JOIN shipments s ON s.id = (SELECT s2.id FROM shipments s2 WHERE s2.order_id = o.id AND s2.vendor_id = ovs.vendor_id ORDER BY s2.id DESC LIMIT 1)
                LEFT JOIN farmer_order_lifecycle last_stage ON last_stage.id = (SELECT fol.id FROM farmer_order_lifecycle fol WHERE fol.vendor_split_id = ovs.id ORDER BY fol.id DESC LIMIT 1)
                WHERE ovs.vendor_id = ?";
    }

    private static function orderItems(int $orderId, int $vendorId): array
    {
        if (!\table_exists('order_items')) {
            return [];
        }
        return self::rows(
            'SELECT name, sku, quantity, unit_price, subtotal, discount_total, tax_total, total FROM order_items WHERE order_id = ? AND vendor_id = ? AND item_type = "product" ORDER BY id ASC',
            [$orderId, $vendorId]
        );
    }

    private static function orderTimeline(int $splitId): array
    {
        return self::rows('SELECT stage, note, tracking_number, tracking_url, created_at FROM farmer_order_lifecycle WHERE vendor_split_id = ? ORDER BY created_at ASC, id ASC', [$splitId]);
    }

    private static function orderNotes(int $splitId): array
    {
        return self::rows('SELECT note, created_at FROM farmer_order_notes WHERE vendor_split_id = ? ORDER BY created_at DESC, id DESC LIMIT 20', [$splitId]);
    }

    private static function financialBreakdown(array $order): string
    {
        $products = max(0, (float)($order['item_subtotal'] ?? 0));
        if ($products <= 0) {
            $products = max(0, (float)($order['gross_total'] ?? 0) - (float)($order['shipping_total'] ?? 0));
        }
        $delivery = (float)($order['shipping_total'] ?? $order['order_shipping_total'] ?? 0);
        $customerPaid = (float)($order['gross_total'] ?? 0);
        $fee = (float)($order['platform_commission'] ?? 0);
        $deliveryAdjustment = 0.0;
        $earnings = (float)($order['vendor_earning'] ?? 0);
        if ($earnings <= 0) {
            $earnings = max(0, $customerPaid - $fee - (float)($order['gateway_fee'] ?? 0));
        }

        $lines = [
            ['Products', self::money($products)],
            ['Delivery', self::money($delivery)],
            ['Customer Paid', self::money($customerPaid), true],
            ['Seller Africa Fee', self::money($fee)],
            ['Delivery Adjustment', self::money($deliveryAdjustment)],
            ['Your Earnings', self::money($earnings), true],
        ];
        $html = '<div class="ff-money-breakdown">';
        foreach ($lines as $line) {
            $html .= '<p class="' . (!empty($line[2]) ? 'is-total' : '') . '"><span>' . \e($line[0]) . '</span><strong>' . \e($line[1]) . '</strong></p>';
        }
        return $html . '</div>';
    }

    private static function orderActionForms(int $splitId, string $stage, string $fulfillment, bool $detailed): string
    {
        $stageKey = strtolower(str_replace(' ', '_', $stage));
        $actions = match ($stageKey) {
            'new' => ['accept' => 'Accept Order', 'reject' => 'Reject/Cancel Order'],
            'accepted' => ['start_preparing' => 'Start Preparing', 'cancel' => 'Reject/Cancel Order'],
            'preparing' => str_contains(strtolower($fulfillment), 'pickup')
                ? ['mark_ready_pickup' => 'Mark Ready for Pickup', 'report_problem' => 'Report Problem']
                : ['mark_ready_delivery' => 'Mark Ready for Delivery', 'mark_shipped' => 'Mark Shipped', 'report_problem' => 'Report Problem'],
            'ready_for_pickup', 'ready_for_delivery' => ['mark_shipped' => 'Mark Shipped', 'mark_delivered' => 'Mark Delivered', 'report_problem' => 'Report Problem'],
            'shipped' => ['add_tracking' => 'Add Tracking', 'mark_delivered' => 'Mark Delivered', 'request_refund' => 'Issue/Request Refund'],
            default => ['report_problem' => 'Report Problem', 'request_refund' => 'Issue/Request Refund'],
        };

        $html = '<div class="' . ($detailed ? 'ff-detail-actions' : 'ff-mini-actions') . '">';
        foreach ($actions as $action => $label) {
            $needsExtra = in_array($action, ['add_tracking', 'report_problem', 'request_refund', 'reject', 'cancel'], true);
            $html .= '<form method="post"><input type="hidden" name="csrf_token" value="' . \e(\getCsrfToken()) . '"><input type="hidden" name="intent" value="farmer_order_action"><input type="hidden" name="split_id" value="' . \e((string)$splitId) . '"><input type="hidden" name="order_action" value="' . \e($action) . '">';
            if ($detailed && $needsExtra) {
                $html .= '<input name="note" placeholder="Optional note">';
            }
            if ($detailed && in_array($action, ['add_tracking', 'mark_shipped'], true)) {
                $html .= '<input name="tracking_number" placeholder="Tracking number"><input name="tracking_url" placeholder="Tracking URL">';
            }
            $html .= '<button type="submit">' . \e($label) . '</button></form>';
        }
        return $html . '</div>';
    }

    private static function displayOrderStage(array $row): string
    {
        $stage = (string)($row['farmer_stage'] ?? '');
        if ($stage !== '') {
            return self::label($stage);
        }
        return match ((string)($row['status'] ?? 'pending')) {
            'pending' => 'New',
            'processing' => 'Accepted',
            'shipped' => 'Shipped',
            'completed' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            default => self::label((string)($row['status'] ?? 'pending')),
        };
    }

    private static function fulfillmentMethod(array $row): string
    {
        $shipping = (float)($row['shipping_total'] ?? $row['order_shipping_total'] ?? 0);
        $shipmentStatus = strtolower((string)($row['shipment_status'] ?? ''));
        if ($shipping > 0 || $shipmentStatus !== '') {
            return 'Shipping';
        }
        $note = strtolower((string)($row['customer_note'] ?? ''));
        if (str_contains($note, 'pickup')) {
            return 'Farm Pickup';
        }
        if (str_contains($note, 'delivery')) {
            return 'Local Delivery';
        }
        return 'Farm fulfillment';
    }

    private static function deliveryDetails(array $row): string
    {
        $bits = [];
        $note = trim((string)($row['customer_note'] ?? ''));
        if ($note !== '') {
            $bits[] = $note;
        }
        if (trim((string)($row['tracking_number'] ?? '')) !== '') {
            $bits[] = 'Tracking: ' . trim((string)$row['tracking_number']);
        }
        return $bits ? implode(' | ', $bits) : 'Details will appear when the customer or shipping record provides them.';
    }

    private static function label(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }

    private static function dateTimeShort(string $date): string
    {
        $date = trim($date);
        return $date !== '' ? date('M j, Y g:i A', strtotime($date)) : 'Not dated';
    }

    private static function documentsTable(int $vendorId): void
    {
        $rows = self::rows('SELECT document_type, status, submitted_at FROM vendor_kyc_documents WHERE vendor_id = ? ORDER BY submitted_at DESC, id DESC LIMIT 30', [$vendorId]);
        echo '<section class="ff-panel"><div class="ff-panel-head"><h2>Verification Documents</h2><a href="' . \e(\app_url('vendor/kyc')) . '">Manage KYC</a></div><div class="ff-table">';
        foreach ($rows as $row) {
            echo '<div><strong>' . \e(ucwords(str_replace('_', ' ', (string)$row['document_type']))) . '</strong><span>' . \e(ucwords((string)$row['status'])) . '</span><em>' . \e((string)$row['submitted_at']) . '</em></div>';
        }
        if ($rows === []) {
            echo '<p>No documents have been uploaded yet.</p>';
        }
        echo '</div></section>';
    }

    private static function profilePanel(array $vendor, array $applicationData): void
    {
        $details = self::farmerProfileDetails((int)$vendor['id']);
        echo '<section class="ff-profile-wrap">';
        echo '<article class="ff-panel ff-management-head"><div><p>Public Farm Profile</p><h2>Farm Story, Photos, Crops, Practices & Delivery Areas</h2><span>Keep the public FreshRoots profile accurate so customers know what you grow and how you fulfill orders.</span></div><a class="ff-primary-action" href="' . \e(\app_url('vendors/' . rawurlencode((string)($vendor['store_slug'] ?? '')))) . '">View Public Profile</a></article>';
        echo '<section class="ff-profile-grid"><article class="ff-panel"><div class="ff-panel-head"><h2>Edit Farm Profile</h2><span>Public information.</span></div><form method="post" class="ff-profile-form"><input type="hidden" name="csrf_token" value="' . \e(\getCsrfToken()) . '"><input type="hidden" name="intent" value="farmer_profile">';
        echo '<label>Farm name<input name="store_name" value="' . \e((string)$vendor['store_name']) . '" required></label>';
        echo '<label>Public email<input name="store_email" value="' . \e((string)($details['public_contact_email'] ?? $vendor['store_email'] ?? '')) . '"></label>';
        echo '<label>Public phone<input name="store_phone" value="' . \e((string)($details['public_contact_phone'] ?? $vendor['store_phone'] ?? '')) . '"></label>';
        echo '<label class="ff-wide">Farm story<textarea name="farm_story">' . \e((string)($details['farm_story'] ?? $vendor['description'] ?? '')) . '</textarea></label>';
        echo '<label class="ff-wide">Crops grown<textarea name="crops_grown">' . \e((string)($details['crops_grown'] ?? self::fromPayload($applicationData, ['products', 'produce'], ''))) . '</textarea></label>';
        echo '<label class="ff-wide">Farming practices<textarea name="farming_practices">' . \e((string)($details['farming_practices'] ?? self::fromPayload($applicationData, ['farming_practices', 'practices'], ''))) . '</textarea></label>';
        echo '<label class="ff-wide">Certifications<textarea name="certifications">' . \e((string)($details['certifications'] ?? self::fromPayload($applicationData, ['certifications', 'licenses'], ''))) . '</textarea></label>';
        echo '<label class="ff-wide">Delivery areas<textarea name="delivery_areas">' . \e((string)($details['delivery_areas'] ?? self::fromPayload($applicationData, ['fulfillment', 'service_areas'], ''))) . '</textarea></label>';
        echo '<button type="submit">Save Farm Profile</button></form></article>';
        echo '<article class="ff-panel"><div class="ff-panel-head"><h2>Application Snapshot</h2><span>Details submitted during registration.</span></div>' . self::applicationHighlights($applicationData) . '</article></section></section>';
    }

    private static function sidebar(string $active): void
    {
        $brand = \app_branding();
        echo '<aside class="ff-sidebar"><a class="ff-logo" href="' . \e(\app_url('farmer')) . '"><span class="ff-africa">' . self::icon('leaf') . '</span><strong>' . \e((string)($brand['name'] ?? 'Seller Africa')) . '<em>FreshRoots</em></strong></a><nav>';
        foreach (self::SECTIONS as $key => $section) {
            echo '<a class="' . ($key === $active ? 'is-active' : '') . '" href="' . \e(\app_url($key === 'home' ? 'farmer' : 'farmer/' . $key)) . '"><b>' . self::icon(self::sectionIcon($key)) . '</b><span>' . \e($section['label']) . '</span>' . ($key === 'orders' ? '<i>3</i>' : '') . '</a>';
        }
        echo '</nav><div class="ff-grow-card"><img src="' . \e(\app_url('assets/images/farmers.jpeg')) . '" alt=""><strong>Grow Your Reach</strong><p>List more fresh produce and reach new customers across the country.</p><a href="' . \e(\app_url('farmer/produce#add-produce')) . '">Add New Produce</a></div></aside>';
    }

    private static function topbar(array $vendor): void
    {
        echo '<header class="ff-topbar"><button type="button" data-ff-menu>Menu</button><form role="search"><span>' . self::icon('search') . '</span><input type="search" placeholder="Search orders, products, or customers..."></form><div class="ff-top-actions"><a class="ff-bell" href="' . \e(\app_url('farmer/orders')) . '">' . self::icon('bell') . '<i>3</i></a><div class="ff-avatar">' . \e(strtoupper(substr((string)$vendor['store_name'], 0, 1))) . '</div><div class="ff-user"><strong>' . \e((string)$vendor['store_name']) . '</strong><span>Vendor</span></div><a class="ff-signout" href="' . \e(\app_url('logout')) . '">Sign out</a></div></header>';
    }

    private static function sectionIcon(string $key): string
    {
        return [
            'home' => 'home',
            'produce' => 'produce',
            'orders' => 'bag',
            'inventory' => 'box',
            'fulfillment' => 'truck',
            'earnings' => 'money',
            'customers' => 'users',
            'reviews' => 'star',
            'profile' => 'farm',
            'verification' => 'check',
            'settings' => 'settings',
            'support' => 'help',
        ][$key] ?? 'leaf';
    }

    private static function icon(string $name): string
    {
        $paths = [
            'home' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10.5V20h14v-9.5"/><path d="M9 20v-6h6v6"/>',
            'produce' => '<path d="M12 20c5-3 8-7 8-13-5 0-9 2-12 7"/><path d="M4 20c0-6 4-10 11-11"/>',
            'bag' => '<path d="M6 8h12l-1 12H7L6 8Z"/><path d="M9 8a3 3 0 0 1 6 0"/>',
            'box' => '<path d="m3 7 9-4 9 4-9 4-9-4Z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/>',
            'truck' => '<path d="M3 6h11v10H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
            'money' => '<path d="M12 2v20"/><path d="M17 6.5c-.9-.9-2.3-1.5-4-1.5-2.5 0-4 1.2-4 3s1.5 2.5 4 3 4 1.2 4 3-1.5 3-4 3c-1.8 0-3.4-.6-4.5-1.7"/>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.8"/><path d="M16 3.2a4 4 0 0 1 0 7.6"/>',
            'star' => '<path d="m12 3 2.7 5.5 6.1.9-4.4 4.2 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.2 6.1-.9L12 3Z"/>',
            'farm' => '<path d="M4 20V8l8-5 8 5v12"/><path d="M8 20v-7h8v7"/><path d="M4 12h16"/>',
            'check' => '<path d="M20 6 9 17l-5-5"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1-2 3.4-.2-.1a1.7 1.7 0 0 0-1.8-.1 1.7 1.7 0 0 0-1 1.5V22H9.2v-.4a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.1l-.2.1-2-3.4.1-.1A1.7 1.7 0 0 0 4.6 15 1.7 1.7 0 0 0 3 14H2v-4h1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1 2-3.4.2.1a1.7 1.7 0 0 0 1.8.1 1.7 1.7 0 0 0 1-1.5V2h5.6v.4a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.1l.2-.1 2 3.4-.1.1a1.7 1.7 0 0 0-.3 1.8 1.7 1.7 0 0 0 1.6 1h1v4h-1a1.7 1.7 0 0 0-1.6 1Z"/>',
            'help' => '<circle cx="12" cy="12" r="10"/><path d="M9.5 9a2.7 2.7 0 1 1 4.4 2.1c-.9.6-1.4 1.1-1.4 2.4"/><path d="M12 17h.01"/>',
            'leaf' => '<path d="M20 4c-8 0-13 4-13 10 0 3 2 5 5 5 6 0 8-7 8-15Z"/><path d="M4 20c3-6 8-9 16-16"/>',
            'alert' => '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 9v5"/><path d="M12 17h.01"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        ];
        return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ($paths[$name] ?? $paths['leaf']) . '</svg>';
    }

    private static function sectionHint(string $active, string $item, array $metrics, array $applicationData): string
    {
        if ($active === 'earnings' && str_contains(strtolower($item), 'pending')) {
            return 'Pending payout total: ' . $metrics['pending_payouts'];
        }
        if ($active === 'customers') {
            return 'Customer count: ' . $metrics['customers'];
        }
        if ($active === 'fulfillment') {
            return self::fromPayload($applicationData, ['fulfillment', 'service_areas'], 'Manage how customers receive your FreshRoots orders.');
        }
        return 'Manage ' . strtolower($item) . ' for your FreshRoots store.';
    }

    private static function applicationHighlights(array $data): string
    {
        $items = [
            'Main crops' => self::fromPayload($data, ['what_do_you_grow', 'main_crops_products'], 'Not provided yet'),
            'Availability' => self::fromPayload($data, ['what_do_you_grow', 'availability'], 'Not provided yet'),
            'Service areas' => self::fromPayload($data, ['fulfillment', 'service_areas'], 'Not provided yet'),
            'Fulfillment speed' => self::fromPayload($data, ['fulfillment', 'fulfillment_speed'], 'Not provided yet'),
        ];
        $html = '<div class="ff-list">';
        foreach ($items as $label => $value) {
            $html .= '<div><strong>' . \e($label) . '</strong><span>' . \e($value) . '</span></div>';
        }
        return $html . '</div>';
    }

    private static function alertRows(array $metrics): string
    {
        return '<div class="ff-list"><div><strong>New orders</strong><span>' . \e((string)$metrics['new_orders']) . ' need attention</span></div><div><strong>Low stock</strong><span>' . \e((string)$metrics['low_stock']) . ' listings need inventory updates</span></div><div><strong>Sold out</strong><span>' . \e((string)$metrics['sold_out']) . ' listings are paused or unavailable</span></div></div>';
    }

    private static function application(int $vendorId): array
    {
        $row = \db()->fetch('SELECT * FROM vendor_applications WHERE vendor_id = ? AND application_type = "farmer" ORDER BY id DESC LIMIT 1', [$vendorId]);
        return is_array($row) ? $row : [];
    }

    private static function decodeApplication(mixed $json): array
    {
        $data = json_decode((string)$json, true);
        return is_array($data) ? $data : [];
    }

    private static function fromPayload(array $data, array $path, string $fallback): string
    {
        $value = $data;
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $fallback;
            }
            $value = $value[$key];
        }
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map('strval', $value)));
        }
        $value = trim((string)$value);
        return $value !== '' ? $value : $fallback;
    }

    private static function count(string $table, string $where, array $params): int
    {
        if (!\table_exists($table)) {
            return 0;
        }
        try {
            $row = \db()->fetch("SELECT COUNT(*) total FROM {$table} WHERE {$where}", $params);
            return (int)($row['total'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function salesSnapshot(int $vendorId): array
    {
        $empty = [
            'today_total' => 0.0,
            'week_total' => 0.0,
            'month_total' => 0.0,
            'week_orders' => 0,
            'average_order' => 0.0,
            'top_product' => '',
        ];
        if (!\table_exists('order_vendor_splits')) {
            return $empty;
        }

        try {
            $row = \db()->fetch(
                "SELECT
                    COALESCE(SUM(CASE WHEN created_at >= CURDATE() THEN gross_total ELSE 0 END), 0) today_total,
                    COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN gross_total ELSE 0 END), 0) week_total,
                    COALESCE(SUM(CASE WHEN created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN gross_total ELSE 0 END), 0) month_total,
                    COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) week_orders,
                    COALESCE(AVG(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN gross_total ELSE NULL END), 0) average_order
                 FROM order_vendor_splits
                 WHERE vendor_id = ? AND status NOT IN ('cancelled','failed','refunded')",
                [$vendorId]
            ) ?: [];
            $top = '';
            if (\table_exists('order_items') && \table_exists('products')) {
                $topRow = \db()->fetch(
                    "SELECT p.name, COALESCE(SUM(oi.quantity), 0) sold
                     FROM order_items oi
                     INNER JOIN orders o ON o.id = oi.order_id
                     INNER JOIN products p ON p.id = oi.product_id
                     WHERE oi.vendor_id = ? AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     GROUP BY p.id, p.name
                     ORDER BY sold DESC
                     LIMIT 1",
                    [$vendorId]
                );
                $top = (string)($topRow['name'] ?? '');
            }

            return array_merge($empty, [
                'today_total' => (float)($row['today_total'] ?? 0),
                'week_total' => (float)($row['week_total'] ?? 0),
                'month_total' => (float)($row['month_total'] ?? 0),
                'week_orders' => (int)($row['week_orders'] ?? 0),
                'average_order' => (float)($row['average_order'] ?? 0),
                'top_product' => $top,
            ]);
        } catch (\Throwable) {
            return $empty;
        }
    }

    private static function balances(int $vendorId): array
    {
        $earned = self::sum('order_vendor_splits', 'vendor_earning', 'vendor_id = ? AND status IN ("processing","shipped","completed")', [$vendorId]);
        if ($earned <= 0) {
            $earned = self::sum('order_vendor_splits', 'gross_total', 'vendor_id = ? AND status IN ("processing","shipped","completed")', [$vendorId]);
        }
        $requested = self::sum('vendor_withdrawals', 'amount', 'vendor_id = ? AND status IN ("pending","approved","paid")', [$vendorId]);
        $pending = self::sum('vendor_withdrawals', 'amount', 'vendor_id = ? AND status IN ("pending","approved")', [$vendorId]);

        return [
            'pending_payout' => $pending,
            'available_balance' => max(0, $earned - $requested),
        ];
    }

    private static function storeSettings(int $vendorId): array
    {
        try {
            $row = \db()->fetch('SELECT * FROM farmer_store_settings WHERE vendor_id = ? LIMIT 1', [$vendorId]);
            return is_array($row) ? $row : ['harvest_available' => 1, 'harvest_note' => ''];
        } catch (\Throwable) {
            return ['harvest_available' => 1, 'harvest_note' => ''];
        }
    }

    private static function fulfillmentSettings(int $vendorId): array
    {
        $defaults = [
            'shipping_enabled' => 0,
            'seller_africa_delivery_enabled' => 0,
            'farmer_local_delivery_enabled' => 0,
            'farm_pickup_enabled' => 0,
            'tracking_required' => 1,
            'expose_private_address' => 0,
            'default_preparation_time' => '1_day',
        ];
        try {
            $row = \db()->fetch('SELECT * FROM farmer_fulfillment_settings WHERE vendor_id = ? LIMIT 1', [$vendorId]);
            return is_array($row) ? array_merge($defaults, $row) : $defaults;
        } catch (\Throwable) {
            return $defaults;
        }
    }

    private static function earningsSummary(int $vendorId): array
    {
        $empty = [
            'total_sales' => 0.0,
            'total_earnings' => 0.0,
            'platform_fees' => 0.0,
            'gateway_fees' => 0.0,
            'refunded_total' => 0.0,
            'today_sales' => 0.0,
            'week_sales' => 0.0,
            'month_sales' => 0.0,
            'order_count' => 0,
            'average_order' => 0.0,
            'top_product' => '',
        ];
        if (!\table_exists('order_vendor_splits')) {
            return $empty;
        }

        try {
            $row = \db()->fetch(
                "SELECT
                    COALESCE(SUM(CASE WHEN ovs.status NOT IN ('cancelled','failed','refunded') THEN ovs.gross_total ELSE 0 END), 0) total_sales,
                    COALESCE(SUM(CASE WHEN ovs.status NOT IN ('cancelled','failed','refunded') THEN ovs.vendor_earning ELSE 0 END), 0) total_earnings,
                    COALESCE(SUM(CASE WHEN ovs.status NOT IN ('cancelled','failed','refunded') THEN ovs.platform_commission ELSE 0 END), 0) platform_fees,
                    COALESCE(SUM(CASE WHEN ovs.status NOT IN ('cancelled','failed','refunded') THEN ovs.gateway_fee ELSE 0 END), 0) gateway_fees,
                    COALESCE(SUM(CASE WHEN ovs.status = 'refunded' THEN ovs.gross_total ELSE 0 END), 0) refunded_total,
                    COALESCE(SUM(CASE WHEN o.created_at >= CURDATE() AND ovs.status NOT IN ('cancelled','failed','refunded') THEN ovs.gross_total ELSE 0 END), 0) today_sales,
                    COALESCE(SUM(CASE WHEN o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND ovs.status NOT IN ('cancelled','failed','refunded') THEN ovs.gross_total ELSE 0 END), 0) week_sales,
                    COALESCE(SUM(CASE WHEN o.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') AND ovs.status NOT IN ('cancelled','failed','refunded') THEN ovs.gross_total ELSE 0 END), 0) month_sales,
                    COUNT(CASE WHEN ovs.status NOT IN ('cancelled','failed','refunded') THEN 1 ELSE NULL END) order_count,
                    COALESCE(AVG(CASE WHEN ovs.status NOT IN ('cancelled','failed','refunded') THEN ovs.gross_total ELSE NULL END), 0) average_order
                 FROM order_vendor_splits ovs
                 INNER JOIN orders o ON o.id = ovs.order_id
                 WHERE ovs.vendor_id = ?",
                [$vendorId]
            ) ?: [];
            $top = '';
            if (\table_exists('order_items') && \table_exists('products')) {
                $topRow = \db()->fetch(
                    "SELECT p.name, COALESCE(SUM(oi.quantity), 0) sold
                     FROM order_items oi
                     INNER JOIN orders o ON o.id = oi.order_id
                     INNER JOIN products p ON p.id = oi.product_id
                     WHERE oi.vendor_id = ? AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     GROUP BY p.id, p.name
                     ORDER BY sold DESC
                     LIMIT 1",
                    [$vendorId]
                );
                $top = (string)($topRow['name'] ?? '');
            }

            return array_merge($empty, [
                'total_sales' => (float)($row['total_sales'] ?? 0),
                'total_earnings' => (float)($row['total_earnings'] ?? 0),
                'platform_fees' => (float)($row['platform_fees'] ?? 0),
                'gateway_fees' => (float)($row['gateway_fees'] ?? 0),
                'refunded_total' => (float)($row['refunded_total'] ?? 0),
                'today_sales' => (float)($row['today_sales'] ?? 0),
                'week_sales' => (float)($row['week_sales'] ?? 0),
                'month_sales' => (float)($row['month_sales'] ?? 0),
                'order_count' => (int)($row['order_count'] ?? 0),
                'average_order' => (float)($row['average_order'] ?? 0),
                'top_product' => $top,
            ]);
        } catch (\Throwable) {
            return $empty;
        }
    }

    private static function earningTransactions(int $vendorId): array
    {
        if (!\table_exists('order_vendor_splits')) {
            return [];
        }
        return self::rows(
            "SELECT ovs.id, ovs.status, ovs.gross_total, ovs.vendor_earning, ovs.platform_commission, ovs.gateway_fee, ovs.created_at,
                    o.order_number, o.placed_at
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             WHERE ovs.vendor_id = ?
             ORDER BY ovs.created_at DESC, ovs.id DESC
             LIMIT 50",
            [$vendorId]
        );
    }

    private static function payoutHistory(int $vendorId): array
    {
        if (!\table_exists('vendor_withdrawals')) {
            return [];
        }
        return self::rows(
            'SELECT id, amount, currency, method, status, note, requested_at, processed_at FROM vendor_withdrawals WHERE vendor_id = ? ORDER BY requested_at DESC, id DESC LIMIT 30',
            [$vendorId]
        );
    }

    private static function customerSummary(int $vendorId): array
    {
        $empty = [
            'total_customers' => 0,
            'repeat_buyers' => 0,
            'new_30_days' => 0,
            'average_customer_value' => 0.0,
        ];
        if (!\table_exists('order_vendor_splits') || !\table_exists('orders')) {
            return $empty;
        }

        try {
            $row = \db()->fetch(
                "SELECT COUNT(*) total_customers,
                        COALESCE(SUM(CASE WHEN order_count > 1 THEN 1 ELSE 0 END), 0) repeat_buyers,
                        COALESCE(SUM(CASE WHEN first_order_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) new_30_days,
                        COALESCE(AVG(total_spent), 0) average_customer_value
                 FROM (
                    SELECT COALESCE(CAST(o.customer_id AS CHAR), CONCAT('guest:', COALESCE(o.guest_email, ''))) customer_key,
                           COUNT(DISTINCT o.id) order_count,
                           COALESCE(SUM(CASE WHEN ovs.status NOT IN ('cancelled','failed','refunded') THEN ovs.gross_total ELSE 0 END), 0) total_spent,
                           MIN(o.created_at) first_order_at
                    FROM order_vendor_splits ovs
                    INNER JOIN orders o ON o.id = ovs.order_id
                    WHERE ovs.vendor_id = ?
                    GROUP BY customer_key
                 ) customer_rollup
                 WHERE customer_key <> 'guest:'",
                [$vendorId]
            ) ?: [];

            return array_merge($empty, [
                'total_customers' => (int)($row['total_customers'] ?? 0),
                'repeat_buyers' => (int)($row['repeat_buyers'] ?? 0),
                'new_30_days' => (int)($row['new_30_days'] ?? 0),
                'average_customer_value' => (float)($row['average_customer_value'] ?? 0),
            ]);
        } catch (\Throwable) {
            return $empty;
        }
    }

    private static function customerRows(int $vendorId, string $query = ''): array
    {
        if (!\table_exists('order_vendor_splits') || !\table_exists('orders')) {
            return [];
        }

        $params = [$vendorId];
        $searchSql = '';
        if ($query !== '') {
            $searchSql = " AND (u.display_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR o.guest_email LIKE ?)";
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return self::rows(
            "SELECT COALESCE(CAST(o.customer_id AS CHAR), CONCAT('guest:', COALESCE(o.guest_email, ''))) customer_key,
                    COALESCE(NULLIF(u.display_name, ''), NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ''), o.guest_email, 'Guest Customer') customer_name,
                    COALESCE(u.email, o.guest_email, '') customer_email,
                    COALESCE(u.phone, '') customer_phone,
                    COUNT(DISTINCT o.id) order_count,
                    COALESCE(SUM(CASE WHEN ovs.status NOT IN ('cancelled','failed','refunded') THEN ovs.gross_total ELSE 0 END), 0) total_spent,
                    MIN(o.created_at) first_order_at,
                    MAX(o.created_at) last_order_at
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             LEFT JOIN users u ON u.id = o.customer_id
             WHERE ovs.vendor_id = ? {$searchSql}
             GROUP BY customer_key, customer_name, customer_email, customer_phone
             HAVING customer_key <> 'guest:'
             ORDER BY last_order_at DESC
             LIMIT 200",
            $params
        );
    }

    private static function selectedCustomer(array $customers, string $key): ?array
    {
        foreach ($customers as $customer) {
            if ((string)($customer['customer_key'] ?? '') === $key) {
                return $customer;
            }
        }
        return null;
    }

    private static function recentCustomerOrders(int $vendorId): array
    {
        return self::customerOrderRows($vendorId, '', 40);
    }

    private static function customerOrders(int $vendorId, string $customerKey): array
    {
        return self::customerOrderRows($vendorId, $customerKey, 60);
    }

    private static function customerOrderRows(int $vendorId, string $customerKey, int $limit): array
    {
        if (!\table_exists('order_vendor_splits') || !\table_exists('orders')) {
            return [];
        }

        $params = [$vendorId];
        $filter = '';
        if ($customerKey !== '') {
            if (str_starts_with($customerKey, 'guest:')) {
                $filter = ' AND o.customer_id IS NULL AND o.guest_email = ?';
                $params[] = substr($customerKey, 6);
            } else {
                $filter = ' AND o.customer_id = ?';
                $params[] = (int)$customerKey;
            }
        }

        return self::rows(
            "SELECT ovs.id, ovs.status, ovs.gross_total, ovs.shipping_total, ovs.created_at,
                    o.order_number, o.created_at order_created_at, o.placed_at, o.payment_status,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id AND oi.vendor_id = ovs.vendor_id AND oi.item_type = 'product') item_count,
                    (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id AND oi.vendor_id = ovs.vendor_id AND oi.item_type = 'product') item_quantity
             FROM order_vendor_splits ovs
             INNER JOIN orders o ON o.id = ovs.order_id
             WHERE ovs.vendor_id = ? {$filter}
             ORDER BY COALESCE(o.placed_at, o.created_at) DESC, ovs.id DESC
             LIMIT {$limit}",
            $params
        );
    }

    private static function reviewSummary(int $vendorId): array
    {
        $empty = ['average_rating' => 0.0, 'total_reviews' => 0, 'pending_reviews' => 0, 'responses_sent' => 0];
        if (!\table_exists('reviews') || !\table_exists('products')) {
            return $empty;
        }
        try {
            $row = \db()->fetch(
                "SELECT COALESCE(AVG(r.rating), 0) average_rating,
                        COUNT(*) total_reviews,
                        COALESCE(SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END), 0) pending_reviews
                 FROM reviews r
                 INNER JOIN products p ON p.id = r.product_id
                 WHERE p.vendor_id = ?",
                [$vendorId]
            ) ?: [];
            $responses = \table_exists('farmer_review_responses')
                ? (int)(\db()->fetch('SELECT COUNT(*) total FROM farmer_review_responses WHERE vendor_id = ?', [$vendorId])['total'] ?? 0)
                : 0;
            return [
                'average_rating' => (float)($row['average_rating'] ?? 0),
                'total_reviews' => (int)($row['total_reviews'] ?? 0),
                'pending_reviews' => (int)($row['pending_reviews'] ?? 0),
                'responses_sent' => $responses,
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    private static function reviewRows(int $vendorId): array
    {
        if (!\table_exists('reviews') || !\table_exists('products')) {
            return [];
        }
        $responseJoin = \table_exists('farmer_review_responses') ? 'LEFT JOIN farmer_review_responses frr ON frr.review_id = r.id AND frr.vendor_id = p.vendor_id' : '';
        $responseSelect = \table_exists('farmer_review_responses') ? 'frr.response' : 'NULL response';
        return self::rows(
            "SELECT r.id, r.rating, r.title, r.body, r.status, r.created_at, p.name product_name,
                    COALESCE(NULLIF(u.display_name, ''), u.email, 'Customer') customer_name,
                    {$responseSelect}
             FROM reviews r
             INNER JOIN products p ON p.id = r.product_id
             LEFT JOIN users u ON u.id = r.user_id
             {$responseJoin}
             WHERE p.vendor_id = ?
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT 80",
            [$vendorId]
        );
    }

    private static function farmerProfileDetails(int $vendorId): array
    {
        if (!\table_exists('farmer_profile_details')) {
            return [];
        }
        try {
            $row = \db()->fetch('SELECT * FROM farmer_profile_details WHERE vendor_id = ? LIMIT 1', [$vendorId]);
            return is_array($row) ? $row : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private static function verificationDocuments(int $vendorId): array
    {
        if (!\table_exists('vendor_kyc_documents')) {
            return [];
        }
        return self::rows(
            'SELECT document_type, status, submitted_at FROM vendor_kyc_documents WHERE vendor_id = ? ORDER BY submitted_at DESC, id DESC LIMIT 50',
            [$vendorId]
        );
    }

    private static function farmerSettings(int $vendorId): array
    {
        $defaults = [
            'notification_email' => '',
            'order_notifications' => '1',
            'review_notifications' => '1',
            'low_stock_notifications' => '1',
            'store_policy' => '',
            'return_policy' => '',
        ];
        if (!\table_exists('settings')) {
            return $defaults;
        }
        try {
            $rows = \db()->fetchAll('SELECT setting_key, setting_value FROM settings WHERE scope = "farmer" AND scope_id = ?', [$vendorId]);
            foreach ($rows as $row) {
                $defaults[(string)$row['setting_key']] = (string)$row['setting_value'];
            }
            return $defaults;
        } catch (\Throwable) {
            return $defaults;
        }
    }

    private static function supportRequests(int $vendorId): array
    {
        if (!\table_exists('farmer_support_requests')) {
            return [];
        }
        return self::rows(
            'SELECT subject, category, priority, message, status, created_at FROM farmer_support_requests WHERE vendor_id = ? ORDER BY created_at DESC, id DESC LIMIT 30',
            [$vendorId]
        );
    }

    private static function textInput(string $label, string $name, array $values, string $placeholder = ''): void
    {
        echo '<label>' . \e($label) . '<input name="' . \e($name) . '" value="' . \e((string)($values[$name] ?? '')) . '"' . ($placeholder !== '' ? ' placeholder="' . \e($placeholder) . '"' : '') . '></label>';
    }

    private static function moneyInput(string $label, string $name, array $values): void
    {
        echo '<label>' . \e($label) . '<input type="number" step="0.01" min="0" name="' . \e($name) . '" value="' . \e((string)($values[$name] ?? '')) . '"></label>';
    }

    private static function textareaInput(string $label, string $name, array $values): void
    {
        echo '<label class="ff-wide">' . \e($label) . '<textarea name="' . \e($name) . '">' . \e((string)($values[$name] ?? '')) . '</textarea></label>';
    }

    private static function sum(string $table, string $column, string $where, array $params): float
    {
        if (!\table_exists($table)) {
            return 0.0;
        }
        try {
            $row = \db()->fetch("SELECT COALESCE(SUM({$column}), 0) total FROM {$table} WHERE {$where}", $params);
            return (float)($row['total'] ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private static function rows(string $sql, array $params): array
    {
        try {
            return \db()->fetchAll($sql, $params);
        } catch (\Throwable) {
            return [];
        }
    }

    private static function countDistinctCustomers(int $vendorId): int
    {
        if (!\table_exists('order_vendor_splits') || !\table_exists('orders')) {
            return 0;
        }
        try {
            $row = \db()->fetch('SELECT COUNT(DISTINCT o.customer_id) total FROM order_vendor_splits ovs INNER JOIN orders o ON o.id = ovs.order_id WHERE ovs.vendor_id = ? AND o.customer_id IS NOT NULL', [$vendorId]);
            return (int)($row['total'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
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

    private static function assignCategory(int $productId, string $categoryName): void
    {
        if (!\table_exists('categories') || !\table_exists('product_categories')) {
            return;
        }
        $categoryName = trim($categoryName);
        if ($categoryName === '') {
            return;
        }
        $slug = self::slug($categoryName);
        $category = \db()->fetch('SELECT id FROM categories WHERE slug = ? OR name = ? LIMIT 1', [$slug, $categoryName]);
        if (!$category) {
            \db()->query('INSERT INTO categories (parent_id, name, slug, description, sort_order, is_active, image_file_id) VALUES (NULL, ?, ?, NULL, 0, 1, NULL)', [$categoryName, self::uniqueSlug('categories', 'slug', $categoryName)]);
            $categoryId = (int)\db()->lastInsertId();
        } else {
            $categoryId = (int)$category['id'];
        }
        \db()->query('INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)', [$productId, $categoryId]);
    }

    private static function storeProducePhotos(int $productId, int $ownerUserId, int $vendorId): void
    {
        $files = $_FILES['photos'] ?? null;
        if (!is_array($files) || !is_array($files['name'] ?? null)) {
            return;
        }
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if ((int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i] ?? 0,
            ];
            $fileId = self::storeUploadedFile($file, $ownerUserId, $vendorId, 'product');
            \db()->query('INSERT INTO product_media (product_id, file_id, role, sort_order, created_at) VALUES (?, ?, ?, ?, ?)', [$productId, $fileId, $i === 0 ? 'primary' : 'gallery', $i, \sql_now()]);
        }
    }

    private static function storeUploadedFile(array $file, int $ownerUserId, int $vendorId, string $bucket): int
    {
        $tmp = (string)($file['tmp_name'] ?? '');
        $mime = $tmp !== '' ? (string)(mime_content_type($tmp) ?: '') : '';
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if ($tmp === '' || !in_array($mime, $allowed, true)) {
            throw new \RuntimeException('Unsupported produce image type.');
        }

        $dir = APP_ROOT . '/public/uploads/vendor/' . max(1, $vendorId) . '/' . $bucket;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Upload directory could not be created.');
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        $target = $dir . '/' . $name;
        if (!move_uploaded_file($tmp, $target)) {
            throw new \RuntimeException('Produce image could not be saved.');
        }

        $dimensions = @getimagesize($target);
        $relative = 'uploads/vendor/' . max(1, $vendorId) . '/' . $bucket . '/' . $name;
        \db()->query(
            'INSERT INTO files (owner_user_id, disk, path, original_name, mime_type, size_bytes, width, height, alt_text, created_at) VALUES (?, "public", ?, ?, ?, ?, ?, ?, ?, ?)',
            [$ownerUserId, $relative, (string)($file['name'] ?? $name), $mime, filesize($target) ?: 0, $dimensions[0] ?? null, $dimensions[1] ?? null, pathinfo((string)($file['name'] ?? $name), PATHINFO_FILENAME), \sql_now()]
        );
        return (int)\db()->lastInsertId();
    }

    private static function uniqueSlug(string $table, string $column, string $value): string
    {
        $base = self::slug($value) ?: 'farm-produce';
        $slug = $base;
        $counter = 2;
        while (\db()->fetch("SELECT id FROM {$table} WHERE {$column} = ? LIMIT 1", [$slug])) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }

    private static function slug(string $value): string
    {
        return trim(strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $value)), '-');
    }

    private static function productSku(string $input, string $name): string
    {
        $base = ProductService::normalizeSku($input);
        if ($base === '') {
            $base = ProductService::normalizeSku($name);
        }
        $base = substr($base !== '' ? $base : 'FARM-PRODUCE', 0, 86);
        $sku = $base;
        $counter = 2;
        while (\db()->fetch('SELECT id FROM products WHERE sku = ? LIMIT 1', [$sku])) {
            $suffix = '-' . $counter++;
            $sku = substr($base, 0, 100 - strlen($suffix)) . $suffix;
        }
        return $sku;
    }

    private static function produceStatus(array $row): string
    {
        $status = (string)($row['status'] ?? '');
        if ($status === 'draft') {
            return 'Draft';
        }
        if ((string)($row['visibility'] ?? '') === 'hidden') {
            return 'Paused';
        }
        if ((string)($row['seasonal_mode'] ?? '') === 'seasonal') {
            return 'Seasonal';
        }
        $qty = (int)($row['stock_quantity'] ?? 0);
        if ($qty <= 0 || (string)($row['stock_status'] ?? '') === 'out_of_stock') {
            return 'Sold Out';
        }
        if ($qty <= (int)($row['low_stock_threshold'] ?? 5)) {
            return 'Low Stock';
        }
        return 'Available';
    }

    private static function cleanOption(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private static function checkbox(string $name): int
    {
        return (string)($_POST[$name] ?? '') === '1' ? 1 : 0;
    }

    private static function sellingUnits(): array
    {
        return ['kg', 'lb', 'bunch', 'basket', 'box', 'crate', 'case', 'piece', 'bag', 'dozen', 'bundle', 'tray'];
    }

    private static function options(array $options, string $selected): string
    {
        $html = '';
        foreach ($options as $option) {
            $html .= '<option value="' . \e((string)$option) . '"' . ((string)$option === $selected ? ' selected' : '') . '>' . \e(ucwords(str_replace('_', ' ', (string)$option))) . '</option>';
        }
        return $html;
    }

    private static function keyedOptions(array $options, string $selected): string
    {
        $html = '';
        foreach ($options as $value => $label) {
            $html .= '<option value="' . \e((string)$value) . '"' . ((string)$value === $selected ? ' selected' : '') . '>' . \e((string)$label) . '</option>';
        }
        return $html;
    }

    private static function monthOptions(int $selected): string
    {
        $html = '<option value="">Choose month</option>';
        for ($i = 1; $i <= 12; $i++) {
            $html .= '<option value="' . $i . '"' . ($selected === $i ? ' selected' : '') . '>' . \e(self::monthName($i)) . '</option>';
        }
        return $html;
    }

    private static function monthName(int $month): string
    {
        return $month >= 1 && $month <= 12 ? date('F', mktime(0, 0, 0, $month, 1)) : 'Not set';
    }

    private static function nullableText(mixed $value, int $max): ?string
    {
        $text = trim((string)$value);
        return $text === '' ? null : substr($text, 0, $max);
    }

    private static function nullableFloat(mixed $value): ?float
    {
        $text = trim((string)$value);
        return $text === '' ? null : (float)$text;
    }

    private static function nullableInt(mixed $value): ?int
    {
        $text = trim((string)$value);
        return $text === '' ? null : max(0, (int)$text);
    }

    private static function dateShort(string $date): string
    {
        $date = trim($date);
        return $date !== '' ? date('M j, Y', strtotime($date)) : 'Not set';
    }

    private static function money(float $amount): string
    {
        return '$' . number_format($amount, 2);
    }

    private static function fileUrl(mixed $path): string
    {
        $path = trim((string)$path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        return \app_url(ltrim($path, '/'));
    }

    private static function js(): string
    {
        return <<<'JS'
document.querySelector("[data-ff-menu]")?.addEventListener("click",function(){document.body.classList.toggle("ff-sidebar-open")});
const modal=document.getElementById("add-produce");
function openProduceModal(){if(!modal)return;modal.classList.add("is-open");modal.setAttribute("aria-hidden","false");document.body.classList.add("ff-modal-open")}
function closeProduceModal(event){if(event)event.preventDefault();if(!modal)return;modal.classList.remove("is-open");modal.setAttribute("aria-hidden","true");document.body.classList.remove("ff-modal-open");if(location.hash==="#add-produce")history.replaceState(null,"",location.pathname+location.search)}
document.querySelectorAll("[data-ff-modal-open]").forEach(function(link){link.addEventListener("click",function(event){event.preventDefault();openProduceModal()})});
document.querySelectorAll("[data-ff-modal-close]").forEach(function(link){link.addEventListener("click",closeProduceModal)});
if(location.hash==="#add-produce")openProduceModal();
document.addEventListener("keydown",function(event){if(event.key==="Escape")closeProduceModal(event)});
document.querySelector("[data-ff-preview]")?.addEventListener("click",function(){alert("Preview will use the listing details entered in this form once saved.")});
document.querySelectorAll("[data-farm-category]").forEach(function(box){box.addEventListener("change",function(){const selected=document.querySelectorAll("[data-farm-category]:checked");if(selected.length>3){box.checked=false;alert("Please choose up to 3 farm categories.")}})});
JS;
    }

    private static function css(): string
    {
        return <<<'CSS'
:root{color-scheme:light;--side:#003f31;--side2:#022b24;--green:#087347;--green2:#14925d;--pale:#e9f6ef;--lime:#83d64f;--ink:#102022;--muted:#65727a;--line:#dfe7e8;--bg:#f7f9fa;--card:#fff}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,"DM Sans",Arial,sans-serif;font-size:14px}a{text-decoration:none;color:inherit}svg{width:20px;height:20px}.ff-shell{display:grid;grid-template-columns:300px minmax(0,1fr);min-height:100vh}.ff-sidebar{background:linear-gradient(180deg,var(--side),var(--side2));color:#fff;padding:18px 14px;position:sticky;top:0;height:100vh;overflow:auto}.ff-logo{display:flex;align-items:center;gap:10px;color:#fff;margin:0 0 24px 6px}.ff-logo strong{font-size:24px;line-height:.92;font-weight:900}.ff-logo em{display:block;color:#8de15f;font:500 30px/1 "Brush Script MT","Segoe Script",cursive}.ff-africa{width:38px;height:38px;border-radius:14px;background:#e0af30;color:#173d22;display:grid;place-items:center}.ff-sidebar nav{display:grid;gap:8px}.ff-sidebar nav a{min-height:49px;display:flex;align-items:center;gap:14px;color:#f6fff8;border-radius:8px;padding:0 14px;font-weight:800;position:relative}.ff-sidebar nav a b{opacity:.92}.ff-sidebar nav a.is-active{background:linear-gradient(90deg,#34833f,#2a703b)}.ff-sidebar nav a i{margin-left:auto;background:#ef233c;color:#fff;border-radius:999px;min-width:22px;height:22px;display:grid;place-items:center;font-style:normal;font-size:12px}.ff-grow-card{margin:34px 6px 0;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:8px;overflow:hidden;padding-bottom:16px}.ff-grow-card img{width:100%;height:116px;object-fit:cover;display:block}.ff-grow-card strong,.ff-grow-card p{display:block;margin:14px 18px 0}.ff-grow-card p{color:#e7f2ea;line-height:1.45}.ff-grow-card a{display:block;margin:16px 18px 0;text-align:center;background:#86d857;color:#10321e;border-radius:8px;padding:12px;font-weight:900}.ff-main{min-width:0}.ff-topbar{height:84px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:22px;padding:0 28px}.ff-topbar button{display:none}.ff-topbar form{width:min(610px,52vw);height:44px;border:1px solid #d8dee2;border-radius:8px;background:#f8fafb;display:flex;align-items:center;gap:12px;padding:0 14px;color:#7b858b}.ff-topbar input{border:0;background:transparent;outline:0;width:100%;font:inherit}.ff-top-actions{display:flex;align-items:center;gap:18px}.ff-bell{position:relative;color:#063f31}.ff-bell i{position:absolute;right:-7px;top:-9px;background:#e8293d;color:#fff;border-radius:99px;min-width:18px;height:18px;display:grid;place-items:center;font-size:10px;font-style:normal}.ff-avatar{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,#183b2b,#8bd15d);color:#fff;display:grid;place-items:center;font-weight:900}.ff-user strong,.ff-user span{display:block}.ff-user span{color:var(--muted);font-size:12px}.ff-signout{font-size:12px;color:var(--green);font-weight:900}.ff-hero,.ff-metrics,.ff-dashboard-grid,.ff-page-head,.ff-panel.ff-section-wrap,.ff-grid{margin-left:24px;margin-right:24px}.ff-hero{margin-top:22px;min-height:176px;border-radius:8px;overflow:hidden;background:linear-gradient(90deg,rgba(0,57,39,.92),rgba(0,80,50,.72),rgba(0,0,0,.1)),url("/assets/images/farmers.jpeg") center/cover;color:#fff;padding:36px;display:flex;align-items:center;justify-content:space-between;gap:24px}.ff-hero-copy p{margin:0 0 14px;text-transform:uppercase;letter-spacing:.09em;font-weight:900;font-size:13px}.ff-hero h1{margin:0;font-size:36px;line-height:1.05;letter-spacing:0}.ff-hero span{display:block;margin-top:14px;font-size:17px}.ff-verified{width:300px;background:rgba(255,255,255,.94);color:var(--ink);border-radius:8px;padding:18px 18px 18px 58px;position:relative}.ff-verified b{position:absolute;left:18px;top:20px;color:var(--green);background:#dff5e8;width:30px;height:30px;border-radius:50%;display:grid;place-items:center}.ff-verified strong,.ff-verified span,.ff-verified a{display:block}.ff-verified span{margin:8px 0;color:#243237;line-height:1.4}.ff-verified a{font-weight:900;color:#1a5b3e}.ff-metrics{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px;margin-top:16px}.ff-metric,.ff-panel,.ff-card{background:var(--card);border:1px solid var(--line);border-radius:8px;box-shadow:0 8px 22px rgba(16,32,34,.04)}.ff-metric{min-height:128px;padding:20px;display:flex;gap:16px;align-items:flex-start}.ff-metric b{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;background:#dff4e8;color:var(--green);flex:0 0 auto}.ff-metric:nth-child(3) b{background:#fff4d7;color:#d89000}.ff-metric strong{display:block;font-size:25px;line-height:1;font-weight:950}.ff-metric span{display:block;margin:7px 0 14px}.ff-metric a,.ff-panel-head a{color:#006b43;font-weight:900}.ff-dashboard-grid{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:20px;margin-top:20px}.ff-main-col,.ff-side-col{display:grid;gap:20px}.ff-lower-grid{display:grid;grid-template-columns:1.18fr .82fr;gap:20px}.ff-panel{padding:20px}.ff-panel h2{margin:0;font-size:20px;letter-spacing:0}.ff-panel p{color:var(--muted);line-height:1.5}.ff-panel-head{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:18px}.ff-tabs{display:flex;gap:4px;background:#f3f5f6;border-radius:999px;padding:4px;width:max-content;max-width:100%;overflow:auto;margin-bottom:14px}.ff-tabs span{white-space:nowrap;padding:10px 24px;border-radius:999px;color:#344348;font-size:13px}.ff-tabs .is-active{background:#fff;color:#0d6a46;box-shadow:0 2px 8px rgba(0,0,0,.08);font-weight:900}.ff-order-list{display:grid}.ff-order-row{display:grid;grid-template-columns:58px 1.2fr 1fr .78fr .95fr 150px 28px;align-items:center;gap:12px;border-top:1px solid #e8eeee;padding:12px 0}.ff-order-row:first-child{border-top:0}.ff-order-row strong,.ff-order-row small{display:block}.ff-order-row small{color:var(--muted);margin-top:5px}.ff-order-row>a,.ff-order-actions button,.ff-order-actions a{background:var(--green);color:#fff;border:0;border-radius:7px;text-align:center;padding:11px 13px;font-size:12px;font-weight:900;display:inline-block;width:100%;cursor:pointer}.ff-order-row>button{border:0;background:transparent;font-weight:900;font-size:18px}.ff-thumb{width:50px;height:50px;border-radius:7px;background:#e7f4e9;color:#0b7048;display:grid;place-items:center}.ff-produce-table{display:grid}.ff-produce-head,.ff-produce-row{display:grid;grid-template-columns:2fr .8fr .65fr .8fr 1fr 28px;align-items:center;gap:10px}.ff-produce-head{background:#f5f7f8;border-radius:7px;padding:12px;color:#56636a;font-weight:900;font-size:12px}.ff-produce-row{padding:11px 12px;border-bottom:1px solid #eef2f2}.ff-product-cell{display:flex;align-items:center;gap:12px;font-weight:900}.ff-product-cell img,.ff-product-cell b{width:42px;height:42px;border-radius:7px;object-fit:cover;background:#e7f4e9;color:#0b7048;display:grid;place-items:center;flex:0 0 auto}.ff-status{font-style:normal;border-radius:999px;padding:7px 12px;font-size:12px;font-weight:900;background:#def6e9;color:#168051}.ff-status.low-stock{background:#fff2ce;color:#a86c00}.ff-status.sold-out{background:#ffdce1;color:#d21f35}.is-zero{color:#e21f37;font-weight:950}.ff-alert-cards{display:grid;gap:10px}.ff-alert-cards div{display:grid;grid-template-columns:48px minmax(0,1fr);align-items:center;border:1px solid #edf1f1;border-radius:8px;padding:10px}.ff-alert-cards p{margin:0}.ff-alert-cards strong,.ff-alert-cards small{display:block}.ff-alert-cards small{color:var(--muted);margin-top:4px}.ff-dot{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;color:#fff}.ff-dot-1{background:#e52b3f}.ff-dot-2,.ff-dot-3{background:#ffb020}.ff-dot-4{background:#1b9b62}.ff-weekly-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.ff-weekly label{border:1px solid #e6eeee;border-radius:8px;background:#fbfdfd;padding:12px;display:grid;gap:8px}.ff-weekly label span{font-weight:900}.ff-weekly input[type=checkbox]{accent-color:var(--green)}.ff-weekly input[type=date],.ff-weekly input[name^=availability_note],.ff-season input[name=harvest_note]{height:40px;border:1px solid #dfe7e8;border-radius:7px;padding:0 10px;font:inherit;background:#fff}.ff-save-btn{margin-top:12px;border:0;background:var(--green);color:#fff;border-radius:7px;padding:12px 16px;font-weight:950;cursor:pointer}.ff-sales-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}.ff-sales-grid div{background:#f8faf9;border:1px solid #e7eeee;border-radius:8px;padding:14px}.ff-sales-grid span{display:block;color:var(--muted);font-size:12px;font-weight:900}.ff-sales-grid strong{display:block;margin-top:8px;font-size:18px}.ff-quick h2{margin-bottom:16px}.ff-primary-action{display:block;background:linear-gradient(90deg,#16875b,#0b7048);color:#fff;text-align:center;border-radius:7px;padding:14px;font-weight:950;margin-bottom:16px}.ff-quick>div{display:grid;grid-template-columns:1fr 1fr;gap:12px}.ff-quick>div a{background:#f8fafb;border:1px solid #e4e9ea;border-radius:7px;min-height:62px;display:flex;align-items:center;gap:10px;justify-content:center;font-weight:900;color:#223136}.ff-payout-row{display:flex;justify-content:space-between;gap:16px;border:1px solid #edf1f1;border-radius:8px;padding:16px}.ff-payout-row span,.ff-payout-row small{display:block;color:var(--muted)}.ff-payout-row strong{display:block;font-size:20px;margin:6px 0}.ff-payout-row a,.ff-season a,.ff-help a{display:inline-block;background:var(--green);color:#fff;border-radius:7px;padding:10px 18px;font-weight:900;margin-top:8px}.ff-payout-row b{font-size:28px;display:block}.ff-payout p{font-size:12px;margin:12px 0 0}.ff-season form{display:grid;gap:10px;margin-top:12px}.ff-toggle{display:flex;align-items:center;gap:10px;font-weight:950}.ff-toggle input{position:absolute;opacity:0}.ff-toggle span{width:52px;height:30px;background:#cbd5d1;border-radius:999px;position:relative}.ff-toggle span:before{content:"";position:absolute;width:24px;height:24px;border-radius:50%;left:3px;top:3px;background:#fff;transition:.2s}.ff-toggle input:checked+span{background:var(--green)}.ff-toggle input:checked+span:before{transform:translateX(22px)}.ff-help{display:grid;grid-template-columns:132px minmax(0,1fr);gap:16px;align-items:center;padding:0;overflow:hidden}.ff-help img{width:100%;height:100%;min-height:128px;object-fit:cover}.ff-help div{padding:18px 18px 18px 0}.ff-farm-note{background:#edf7e9;border-radius:8px;color:#24492d;display:flex;align-items:center;gap:12px;padding:13px 22px}.ff-farm-note b{color:#188352}.ff-farm-note span{display:grid}.ff-farm-note small{color:#557160}.ff-farm-note em{margin-left:auto;font-family:"Segoe Script","Brush Script MT",cursive;font-style:normal;color:#225b34}.ff-page-head{margin-top:24px}.ff-page-head p{margin:0 0 8px;color:#0d7048;text-transform:uppercase;font-weight:950;letter-spacing:.08em}.ff-page-head h1{font-size:36px;margin:0}.ff-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.ff-card{padding:22px}.ff-card span{color:#0d7048;text-transform:uppercase;font-weight:950;font-size:12px}.ff-card h2{margin:10px 0}.ff-list{display:grid;gap:8px}.ff-list div,.ff-table div{display:flex;justify-content:space-between;gap:12px;border-top:1px solid var(--line);padding:12px 0}.ff-list div:first-child,.ff-table div:first-child{border-top:0}.ff-list span,.ff-table span{color:var(--muted)}.ff-table em{font-style:normal;font-weight:900;color:var(--green)}.ff-steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.ff-steps span,.ff-section-links a{border:1px solid var(--line);border-radius:8px;padding:12px;background:var(--pale);color:var(--green);font-weight:900}.ff-section-links{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px}
.ff-produce-tools{display:grid;gap:18px;margin:18px 24px 40px}.ff-management-head{display:flex;justify-content:space-between;align-items:center;gap:20px}.ff-management-head p,.ff-modal-head p{margin:0 0 6px;color:#0d7048;text-transform:uppercase;font-weight:950;letter-spacing:.08em;font-size:12px}.ff-management-head span{display:block;color:var(--muted);margin-top:8px}.ff-management-head .ff-primary-action{min-width:190px;margin:0}.ff-produce-list{overflow:auto}.ff-produce-management-table{min-width:1180px}.ff-produce-management-head,.ff-produce-management-row{display:grid;grid-template-columns:74px 1.3fr .95fr .7fr .62fr .95fr .85fr .9fr .8fr .9fr 1.42fr;align-items:center;gap:10px}.ff-produce-management-head{background:#f5f8f8;border-radius:8px;padding:12px;color:#506067;font-size:12px;font-weight:950}.ff-produce-management-row{border-top:1px solid #edf1f1;padding:12px}.ff-produce-management-row:first-of-type{border-top:0}.ff-produce-management-row img,.ff-produce-management-row b{width:50px;height:50px;border-radius:8px;object-fit:cover;background:#e7f4e9;color:#0b7048;display:grid;place-items:center}.ff-stock-mini{display:flex;gap:6px;align-items:center}.ff-stock-mini input{width:68px;height:34px;border:1px solid var(--line);border-radius:7px;padding:0 8px}.ff-stock-mini button,.ff-actions-menu button,.ff-actions-menu a{border:1px solid #dfe7e8;background:#fff;border-radius:7px;padding:8px 10px;font:inherit;font-size:12px;font-weight:900;color:#1a4f3a;cursor:pointer;white-space:nowrap}.ff-actions-menu{display:flex;gap:6px;flex-wrap:wrap}.ff-actions-menu form{display:inline}.ff-actions-menu form:last-child button{color:#bf1730}.ff-available-bulk>form>div{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.ff-available-bulk label{display:grid;grid-template-columns:26px minmax(0,1fr) 120px 110px;align-items:center;gap:10px;border:1px solid #e5eeee;border-radius:8px;padding:12px;background:#fbfdfd}.ff-available-bulk input[type=checkbox]{accent-color:var(--green)}.ff-available-bulk input[type=number],.ff-available-bulk select,.ff-weekly-inline input,.ff-weekly-inline select,.ff-produce-form input,.ff-produce-form select,.ff-produce-form textarea{width:100%;border:1px solid #dfe7e8;border-radius:7px;background:#fff;min-height:42px;padding:9px 10px;font:inherit;color:var(--ink)}.ff-weekly-inline{display:grid;grid-template-columns:1fr 110px;gap:8px}.ff-seasonal-list>div:last-child{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.ff-seasonal-list p{margin:0;border:1px solid #e8eeee;border-radius:8px;padding:12px;background:#fbfdfd}.ff-seasonal-list strong,.ff-seasonal-list span{display:block}.ff-seasonal-list span{color:var(--muted);margin-top:4px}.ff-modal{display:none;position:fixed;inset:0;z-index:80}.ff-modal.is-open{display:block}.ff-modal-open{overflow:hidden}.ff-modal-backdrop{position:absolute;inset:0;background:rgba(4,22,18,.62)}.ff-modal-card{position:absolute;inset:4vh max(16px,6vw);background:#fff;border-radius:8px;box-shadow:0 30px 80px rgba(0,0,0,.28);overflow:auto}.ff-modal-head{position:sticky;top:0;z-index:2;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:20px;padding:18px 22px}.ff-modal-head h2{margin:0;font-size:26px}.ff-modal-head a{font-weight:950;color:var(--green)}.ff-produce-form{display:grid;gap:16px;padding:22px}.ff-produce-form fieldset{border:1px solid #e5eeee;border-radius:8px;padding:18px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:0}.ff-produce-form legend{font-weight:950;color:#0e503a;padding:0 8px}.ff-produce-form label{display:grid;gap:7px;font-weight:900;color:#27383d}.ff-produce-form textarea{min-height:92px;resize:vertical}.ff-produce-form .ff-wide{grid-column:1/-1}.ff-check{min-height:42px;display:flex;align-items:center;gap:8px}.ff-check input{width:auto;min-height:0;accent-color:var(--green)}.ff-form-actions{position:sticky;bottom:0;background:#fff;border-top:1px solid var(--line);padding:16px 22px;display:flex;justify-content:flex-end;gap:10px}.ff-form-actions button{border:0;border-radius:7px;padding:13px 18px;font-weight:950;cursor:pointer}.ff-form-actions button:first-child{background:#edf5ef;color:#13543c}.ff-form-actions button:nth-child(2){background:#f6f8f8;color:#243438}.ff-form-actions button:last-child{background:var(--green);color:#fff}
.ff-orders-wrap{display:grid;gap:18px;margin:18px 24px 40px}.ff-order-tabs{display:flex;gap:8px;overflow:auto}.ff-order-tabs a{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;background:#f4f7f7;border:1px solid #e4eaea;color:#304247;font-weight:950;white-space:nowrap}.ff-order-tabs a.is-active{background:#0b7048;color:#fff;border-color:#0b7048}.ff-order-tabs b{min-width:22px;height:22px;border-radius:999px;background:#fff;color:#0b7048;display:grid;place-items:center;font-size:12px}.ff-order-tabs a.is-active b{background:#e6f5ee}.ff-order-centre{overflow:auto}.ff-order-management-table{min-width:1480px}.ff-order-management-head,.ff-order-management-row{display:grid;grid-template-columns:.9fr 1.05fr 1.25fr .45fr .45fr .7fr .7fr .75fr .8fr .9fr 1.25fr .85fr 1.35fr;align-items:center;gap:10px}.ff-order-management-head{background:#f5f8f8;border-radius:8px;padding:12px;color:#506067;font-size:12px;font-weight:950}.ff-order-management-row{border-top:1px solid #edf1f1;padding:12px}.ff-order-management-row:first-of-type{border-top:0}.ff-order-management-row small{display:block;color:var(--muted);margin-top:4px}.ff-order-management-row a{color:#0b7048;font-weight:950}.ff-order-action-stack,.ff-mini-actions,.ff-detail-actions{display:flex;gap:7px;flex-wrap:wrap}.ff-order-action-stack form,.ff-mini-actions form,.ff-detail-actions form{display:inline-grid;gap:7px}.ff-order-action-stack button,.ff-order-action-stack>a,.ff-mini-actions button,.ff-detail-actions button,.ff-note-form button{border:1px solid #dfe7e8;background:#fff;border-radius:7px;padding:8px 10px;font:inherit;font-size:12px;font-weight:950;color:#174f3a;cursor:pointer}.ff-detail-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.ff-detail-actions input,.ff-note-form textarea{width:100%;border:1px solid #dfe7e8;border-radius:7px;padding:10px;font:inherit}.ff-order-detail-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:18px}.ff-order-detail-grid section{border:1px solid #e6eeee;border-radius:8px;padding:16px;background:#fbfdfd}.ff-order-detail-grid h3{margin:0 0 12px;font-size:16px}.ff-detail-list{display:grid;gap:8px}.ff-detail-list div{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:10px;border-bottom:1px solid #e8eeee;padding-bottom:8px}.ff-money-breakdown{display:grid;gap:7px}.ff-money-breakdown p{display:flex;justify-content:space-between;gap:12px;margin:0;padding:8px 0;border-bottom:1px dashed #cfdada;color:var(--ink)}.ff-money-breakdown p.is-total{border-top:1px solid #9fb5ad;border-bottom:0;font-size:16px;font-weight:950}.ff-timeline{display:grid;gap:12px}.ff-timeline div{position:relative;padding-left:28px}.ff-timeline b{position:absolute;left:0;top:3px;width:14px;height:14px;border-radius:50%;background:#0b7048;box-shadow:0 0 0 4px #dff4e8}.ff-timeline strong,.ff-timeline span{display:block}.ff-timeline span{color:var(--muted);font-size:12px;margin-top:3px}.ff-timeline p{margin:5px 0 0}.ff-note-form{display:grid;gap:10px}.ff-note-form textarea{min-height:86px;resize:vertical}.ff-notes{display:grid;gap:8px;margin-top:12px}.ff-notes p{margin:0;border-top:1px solid #e8eeee;padding-top:8px}.ff-notes strong,.ff-notes span{display:block}.ff-notes strong{font-size:12px;color:var(--muted)}
.ff-fulfillment-wrap{display:grid;gap:18px;margin:18px 24px 40px}.ff-fulfillment-form{display:grid;gap:18px}.ff-method-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.ff-method-card{border:1px solid #dfe8e6;border-radius:8px;background:#fbfdfd;padding:16px;display:grid;gap:8px;position:relative}.ff-method-card input{position:absolute;opacity:0}.ff-method-card span{width:52px;height:30px;border-radius:999px;background:#cad8d3;position:relative}.ff-method-card span:before{content:"";position:absolute;left:3px;top:3px;width:24px;height:24px;border-radius:50%;background:#fff;transition:.2s}.ff-method-card input:checked+span{background:#0b7048}.ff-method-card input:checked+span:before{transform:translateX(22px)}.ff-method-card strong{font-size:17px}.ff-method-card small{color:var(--muted);line-height:1.45}.ff-form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.ff-form-grid label{display:grid;gap:7px;font-weight:900;color:#27383d}.ff-form-grid input,.ff-form-grid select,.ff-form-grid textarea{width:100%;border:1px solid #dfe7e8;border-radius:7px;background:#fff;min-height:42px;padding:9px 10px;font:inherit;color:var(--ink)}.ff-form-grid textarea{min-height:94px;resize:vertical}.ff-form-grid .ff-wide{grid-column:1/-1}.ff-check-field{align-content:center;border:1px solid #e5eeee;border-radius:8px;padding:12px;background:#fbfdfd}.ff-check-field input{width:auto;min-height:0;margin-right:8px;accent-color:#0b7048}.ff-closure-card{grid-column:span 2;background:#fff7ed;border-color:#fed7aa}.ff-sticky-save{position:sticky;bottom:0;background:rgba(247,249,250,.94);border:1px solid #dfe7e8;border-radius:8px;padding:12px;display:flex;justify-content:flex-end;backdrop-filter:blur(10px)}.ff-sticky-save button{border:0;background:#0b7048;color:#fff;border-radius:7px;padding:13px 20px;font-weight:950;cursor:pointer}
.ff-earnings-wrap{display:grid;gap:18px;margin:18px 24px 40px}.ff-earnings-metrics{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}.ff-earnings-metrics .ff-metric{margin:0}.ff-earnings-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.ff-snapshot-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.ff-snapshot-grid div,.ff-money-breakdown div{border:1px solid #e5eeee;border-radius:8px;background:#fbfdfd;padding:12px}.ff-snapshot-grid span,.ff-money-breakdown span{display:block;color:var(--muted);font-size:12px;font-weight:950;text-transform:uppercase}.ff-snapshot-grid strong,.ff-money-breakdown strong{display:block;margin-top:5px;font-size:18px}.ff-payout-request{display:grid;gap:12px}.ff-payout-request form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.ff-payout-request label{display:grid;gap:7px;font-weight:900;color:#27383d}.ff-payout-request input,.ff-payout-request select,.ff-payout-request textarea{width:100%;border:1px solid #dfe7e8;border-radius:7px;background:#fff;min-height:42px;padding:9px 10px;font:inherit;color:var(--ink)}.ff-payout-request textarea{min-height:94px;resize:vertical}.ff-payout-request .ff-wide{grid-column:1/-1}.ff-payout-request button{border:0;background:#0b7048;color:#fff;border-radius:7px;padding:13px 18px;font-weight:950;cursor:pointer}.ff-payout-history{display:grid;gap:10px}.ff-payout-row{display:grid;grid-template-columns:1fr auto auto;gap:12px;align-items:center;border:1px solid #e5eeee;border-radius:8px;padding:12px;background:#fbfdfd}.ff-payout-row strong{font-size:18px}.ff-money-breakdown{display:grid;gap:10px}.ff-transactions{overflow:auto}.ff-transaction-table{min-width:760px}.ff-transaction-head,.ff-transaction-row{display:grid;grid-template-columns:1.1fr 1fr repeat(5,.9fr);gap:10px;align-items:center;padding:12px;border-bottom:1px solid #eef3f3}.ff-transaction-head{font-size:12px;text-transform:uppercase;color:var(--muted);font-weight:950;background:#fbfdfd}.ff-transaction-row strong{font-size:14px}.ff-transaction-row span{color:#314348}
.ff-customers-wrap{display:grid;gap:18px;margin:18px 24px 40px}.ff-customer-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.ff-customer-metrics .ff-metric{margin:0}.ff-customers-grid{display:grid;grid-template-columns:2fr 1fr;gap:18px}.ff-customer-search{display:flex;gap:8px;align-items:center}.ff-customer-search input{border:1px solid #dfe7e8;border-radius:7px;min-height:40px;padding:8px 10px;font:inherit}.ff-customer-search button{border:0;background:#0b7048;color:#fff;border-radius:7px;padding:10px 14px;font-weight:950;cursor:pointer}.ff-customer-list,.ff-customer-history{overflow:auto}.ff-customer-table{min-width:760px}.ff-customer-head,.ff-customer-row{display:grid;grid-template-columns:1.6fr .6fr .8fr .8fr .8fr .7fr;gap:10px;align-items:center;padding:12px;border-bottom:1px solid #eef3f3}.ff-customer-head,.ff-history-head{font-size:12px;text-transform:uppercase;color:var(--muted);font-weight:950;background:#fbfdfd}.ff-customer-row strong{display:grid;gap:2px}.ff-customer-row small{color:var(--muted)}.ff-customer-row a,.ff-repeat-list a{color:#0b7048;font-weight:950;text-decoration:none}.ff-repeat-list{display:grid;gap:10px}.ff-repeat-list a{display:grid;grid-template-columns:1fr auto;gap:4px 10px;border:1px solid #e5eeee;border-radius:8px;background:#fbfdfd;padding:12px}.ff-repeat-list a strong{color:var(--ink)}.ff-repeat-list a em{grid-column:1/-1;color:var(--muted);font-style:normal}.ff-history-table{min-width:820px}.ff-history-head,.ff-history-row{display:grid;grid-template-columns:1fr 1fr 1fr .8fr .8fr 1fr .9fr;gap:10px;align-items:center;padding:12px;border-bottom:1px solid #eef3f3}.ff-history-row strong{font-size:14px}.ff-history-row span{color:#314348}
.ff-reviews-wrap,.ff-profile-wrap,.ff-verification-wrap,.ff-settings-wrap,.ff-support-wrap{display:grid;gap:18px;margin:18px 24px 40px}.ff-review-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.ff-review-metrics .ff-metric{margin:0}.ff-review-list{display:grid;gap:12px}.ff-review-card{display:grid;gap:12px;border:1px solid #e5eeee;border-radius:8px;background:#fbfdfd;padding:14px}.ff-review-card>div{display:grid;gap:4px}.ff-review-card>div span{color:#f59e0b;letter-spacing:1px}.ff-review-card small{color:var(--muted)}.ff-review-card p{margin:0;color:#314348;line-height:1.55}.ff-review-card blockquote{margin:0;border-left:4px solid #0b7048;background:#eef8f2;border-radius:0 8px 8px 0;padding:10px 12px;display:grid;gap:4px}.ff-review-card form,.ff-profile-form,.ff-support-form,.ff-settings-form{display:grid;gap:12px}.ff-review-card textarea,.ff-profile-form input,.ff-profile-form textarea,.ff-support-form input,.ff-support-form select,.ff-support-form textarea{width:100%;border:1px solid #dfe7e8;border-radius:7px;background:#fff;min-height:42px;padding:9px 10px;font:inherit;color:var(--ink)}.ff-review-card textarea,.ff-profile-form textarea,.ff-support-form textarea{min-height:96px;resize:vertical}.ff-review-card button,.ff-profile-form button,.ff-support-form button{border:0;background:#0b7048;color:#fff;border-radius:7px;padding:12px 16px;font-weight:950;cursor:pointer;width:max-content}.ff-profile-grid,.ff-verification-grid,.ff-support-grid{display:grid;grid-template-columns:1.25fr .75fr;gap:18px}.ff-profile-form{grid-template-columns:repeat(2,minmax(0,1fr))}.ff-profile-form label,.ff-support-form label{display:grid;gap:7px;font-weight:900;color:#27383d}.ff-profile-form .ff-wide,.ff-support-form .ff-wide{grid-column:1/-1}.ff-verification-status{display:grid;gap:8px;place-items:start}.ff-verification-status b{width:52px;height:52px;border-radius:50%;display:grid;place-items:center;background:#e8f7ef;color:#0b7048}.ff-verification-status strong{font-size:28px}.ff-doc-table{display:grid}.ff-doc-head,.ff-doc-row{display:grid;grid-template-columns:1.4fr .8fr 1fr;gap:10px;align-items:center;padding:12px;border-bottom:1px solid #eef3f3}.ff-doc-head{font-size:12px;text-transform:uppercase;color:var(--muted);font-weight:950;background:#fbfdfd}.ff-settings-form .ff-panel{display:grid;gap:12px}.ff-category-picks{display:flex;flex-wrap:wrap;gap:10px}.ff-category-picks label{cursor:pointer}.ff-category-picks input{position:absolute;opacity:0;pointer-events:none}.ff-category-picks span{display:inline-flex;align-items:center;min-height:38px;border:1px solid #dfe7e8;border-radius:999px;background:#fff;padding:0 13px;font-weight:900;color:#27383d}.ff-category-picks input:checked+span{background:#0b7048;border-color:#0b7048;color:#fff}.ff-support-links{display:grid;gap:10px}.ff-support-links a{border:1px solid #e5eeee;background:#fbfdfd;border-radius:8px;padding:12px;color:#0b7048;text-decoration:none;font-weight:950}.ff-support-list{display:grid;gap:10px}.ff-support-list div{display:grid;grid-template-columns:1fr auto auto;gap:10px;align-items:center;border:1px solid #e5eeee;background:#fbfdfd;border-radius:8px;padding:12px}.ff-support-list span,.ff-support-list em{color:var(--muted);font-style:normal}
@media (max-width:1300px){.ff-shell{grid-template-columns:270px 1fr}.ff-dashboard-grid{grid-template-columns:1fr}.ff-metrics{grid-template-columns:repeat(3,minmax(0,1fr))}.ff-lower-grid{grid-template-columns:1fr}.ff-sales-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.ff-method-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.ff-earnings-metrics{grid-template-columns:repeat(3,minmax(0,1fr))}.ff-earnings-grid,.ff-customers-grid,.ff-profile-grid,.ff-verification-grid,.ff-support-grid{grid-template-columns:1fr}.ff-customer-metrics,.ff-review-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:980px){.ff-shell{grid-template-columns:1fr}.ff-sidebar{position:fixed;z-index:40;left:0;top:0;width:min(86vw,300px);transform:translateX(-105%);transition:.22s ease}.ff-sidebar-open .ff-sidebar{transform:translateX(0)}.ff-topbar{padding:14px;height:auto}.ff-topbar button{display:inline-flex;border:1px solid var(--line);background:#fff;border-radius:8px;padding:11px 14px;font-weight:900}.ff-topbar form{width:100%;order:3;flex-basis:100%}.ff-topbar{flex-wrap:wrap}.ff-hero,.ff-metrics,.ff-dashboard-grid,.ff-page-head,.ff-panel.ff-section-wrap,.ff-grid{margin-left:14px;margin-right:14px}.ff-produce-tools,.ff-orders-wrap,.ff-fulfillment-wrap,.ff-earnings-wrap,.ff-customers-wrap,.ff-reviews-wrap,.ff-profile-wrap,.ff-verification-wrap,.ff-settings-wrap,.ff-support-wrap{margin-left:14px;margin-right:14px}.ff-hero{display:grid}.ff-verified{width:100%}.ff-user,.ff-signout{display:none}.ff-order-row{grid-template-columns:52px 1fr;align-items:start}.ff-order-row>div:nth-of-type(n+2),.ff-order-actions,.ff-order-row>button{margin-left:64px}.ff-order-actions{width:max-content;min-width:150px}.ff-produce-head{display:none}.ff-produce-row{grid-template-columns:1fr 1fr;gap:8px}.ff-product-cell{grid-column:1/-1}.ff-weekly-grid{grid-template-columns:1fr}.ff-management-head{display:grid}.ff-management-head .ff-primary-action{width:100%}.ff-available-bulk>form>div,.ff-seasonal-list>div:last-child{grid-template-columns:1fr}.ff-produce-form fieldset,.ff-form-grid{grid-template-columns:1fr 1fr}.ff-modal-card{inset:2vh 12px}.ff-produce-management-table,.ff-order-management-table,.ff-transaction-table,.ff-customer-table,.ff-history-table{min-width:0}.ff-produce-management-head,.ff-order-management-head,.ff-transaction-head,.ff-customer-head,.ff-history-head,.ff-doc-head{display:none}.ff-produce-management-row,.ff-order-management-row{grid-template-columns:64px 1fr;border:1px solid #e8eeee;border-radius:8px;margin-bottom:10px}.ff-transaction-row,.ff-customer-row,.ff-history-row,.ff-doc-row{grid-template-columns:1fr;border:1px solid #e8eeee;border-radius:8px;margin-bottom:10px}.ff-produce-management-row>span,.ff-produce-management-row>strong,.ff-produce-management-row>form,.ff-order-management-row>span,.ff-order-management-row>strong{grid-column:1/-1;display:flex;justify-content:space-between;gap:12px}.ff-transaction-row span,.ff-transaction-row strong,.ff-customer-row span,.ff-customer-row strong,.ff-history-row span,.ff-history-row strong,.ff-doc-row span,.ff-doc-row strong{display:flex;justify-content:space-between;gap:12px}.ff-customer-row strong{display:grid}.ff-produce-management-row>span:first-child{grid-column:1;grid-row:1 / span 2}.ff-produce-management-row>strong{grid-column:2;justify-content:flex-start}.ff-produce-management-row [data-label]:before,.ff-order-management-row [data-label]:before,.ff-transaction-row [data-label]:before,.ff-customer-row [data-label]:before,.ff-history-row [data-label]:before,.ff-doc-row [data-label]:before{content:attr(data-label);font-size:11px;text-transform:uppercase;color:var(--muted);font-weight:950}.ff-produce-management-row>span:first-child:before,.ff-produce-management-row>strong:before{display:none}.ff-actions-menu,.ff-order-action-stack{justify-content:flex-start}.ff-stock-mini{justify-content:space-between}.ff-order-detail-grid,.ff-detail-actions{grid-template-columns:1fr}.ff-detail-list div{grid-template-columns:1fr}.ff-order-tabs{padding-bottom:3px}.ff-support-list div{grid-template-columns:1fr}}
@media (max-width:640px){body{font-size:13px}.ff-hero{padding:24px;min-height:0}.ff-hero h1{font-size:30px}.ff-metrics,.ff-grid,.ff-quick>div,.ff-sales-grid,.ff-method-grid,.ff-form-grid,.ff-earnings-metrics,.ff-earnings-grid,.ff-snapshot-grid,.ff-payout-request form,.ff-customer-metrics,.ff-review-metrics,.ff-profile-form{grid-template-columns:1fr}.ff-metric{min-height:0}.ff-tabs{width:100%}.ff-tabs span{padding:9px 14px}.ff-payout-row,.ff-season,.ff-farm-note{display:grid;grid-template-columns:1fr}.ff-farm-note em{margin-left:0}.ff-help{grid-template-columns:1fr}.ff-help img{height:150px}.ff-help div{padding:0 18px 18px}.ff-available-bulk label{grid-template-columns:24px 1fr}.ff-available-bulk label input[type=number],.ff-available-bulk label select{grid-column:2}.ff-produce-form fieldset{grid-template-columns:1fr}.ff-form-actions,.ff-customer-search{display:grid}.ff-modal-head{align-items:flex-start}.ff-modal-head h2{font-size:22px}.ff-closure-card{grid-column:auto}.ff-sticky-save{display:grid}.ff-sticky-save button{width:100%}.ff-review-card button,.ff-profile-form button,.ff-support-form button{width:100%}}
CSS;
    }
}
