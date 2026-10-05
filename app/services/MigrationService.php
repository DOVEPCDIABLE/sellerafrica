<?php

declare(strict_types=1);

namespace App;

use PDO;
use Throwable;

final class MigrationService
{
    private const STEPS = [
        ['users', 'Users, roles and profile data', 'users'],
        ['files', 'Media files and image URL mapping', 'posts'],
        ['terms', 'Categories, brands and product tags', 'terms'],
        ['vendors', 'Vendor accounts and store profiles', 'users'],
        ['products', 'Products, variations, prices and stock', 'posts'],
        ['product_terms', 'Product category, brand and tag relationships', 'term_relationships'],
        ['shipping', 'Shipping zones, locations, methods and rates', 'woocommerce_shipping_zones'],
        ['content_posts', 'Blog and news posts', 'posts'],
        ['product_shipping_details', 'Product weights, dimensions and shipping classes', 'posts'],
        ['orders', 'Orders, sales totals and payment references', 'wc_orders'],
        ['order_items', 'Order line items, shipping lines, fees and taxes', 'woocommerce_order_items'],
        ['vendor_splits', 'Dokan vendor order splits and earnings', 'dokan_orders'],
        ['affiliates', 'Affiliate accounts', 'affiliate_wp_affiliates'],
        ['affiliate_activity', 'Affiliate links, visits, referrals and payouts', 'affiliate_wp_visits'],
    ];

    public function __construct(private Database $db)
    {
        $this->ensureTables();
        $this->ensureProductMeasurementCapacity();
    }

    public function createRun(array $input, ?int $userId = null): int
    {
        $sourceType = (string)($input['source_type'] ?? 'source_database');
        $prefix = trim((string)($input['table_prefix'] ?? 'hqgs_')) ?: 'hqgs_';
        $batchSize = max(10, min(500, (int)($input['batch_size'] ?? 100)));
        $sourceConfig = null;
        $sourcePath = null;

        if ($sourceType === 'source_database') {
            $sourceConfig = [
                'host' => (string)($input['source_host'] ?? '127.0.0.1'),
                'port' => (int)($input['source_port'] ?? 3306),
                'database' => (string)($input['source_database'] ?? ''),
                'username' => (string)($input['source_username'] ?? 'root'),
                'password' => (string)($input['source_password'] ?? ''),
                'charset' => 'utf8mb4',
                'options' => [
                    'skip_existing_users' => true,
                    'import_user_passwords' => false,
                    'fill_missing_vendor_descriptions' => !empty($input['fill_missing_vendor_descriptions']),
                    'updated_since' => $this->normalizeUpdatedSince($input),
                ],
            ];

            if ($sourceConfig['database'] === '') {
                throw new \InvalidArgumentException('Source database name is required.');
            }
        } else {
            $sourcePath = (string)($input['source_path'] ?? '');
            if ($sourcePath === '' || !is_file($sourcePath)) {
                throw new \InvalidArgumentException('A valid uploaded SQL dump path is required.');
            }
        }

        $this->db->beginTransaction();
        try {
            $this->db->query(
                "INSERT INTO migration_runs
                (source_type, source_label, source_path, source_config, table_prefix, status, batch_size, created_by)
                VALUES (?, ?, ?, ?, ?, 'draft', ?, ?)",
                [
                    $sourceType,
                    (string)($input['source_label'] ?? $sourceConfig['database'] ?? basename($sourcePath ?? '')),
                    $sourcePath,
                    $sourceConfig ? json_encode($sourceConfig, JSON_UNESCAPED_SLASHES) : null,
                    $prefix,
                    $batchSize,
                    $userId,
                ]
            );
            $runId = (int)$this->db->lastInsertId();

            $selectedSteps = $this->selectedStepKeys($input);
            foreach (self::STEPS as [$key, $label, $sourceTable]) {
                if (!in_array($key, $selectedSteps, true)) {
                    continue;
                }
                $this->db->query(
                    "INSERT INTO migration_steps (run_id, step_key, label, source_table) VALUES (?, ?, ?, ?)",
                    [$runId, $key, $label, $prefix . $sourceTable]
                );
            }

            $this->log($runId, null, 'info', 'Migration run created.');
            $this->db->commit();

            return $runId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function latestRun(): ?array
    {
        return $this->db->fetch("SELECT * FROM migration_runs ORDER BY id DESC LIMIT 1");
    }

    public function run(int $runId): ?array
    {
        return $this->db->fetch("SELECT * FROM migration_runs WHERE id = ?", [$runId]);
    }

    public function steps(int $runId): array
    {
        return $this->db->fetchAll("SELECT * FROM migration_steps WHERE run_id = ? ORDER BY id", [$runId]);
    }

    public function logs(int $runId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM migration_logs WHERE run_id = ? ORDER BY id DESC LIMIT 80",
            [$runId]
        );
    }

    public function start(int $runId): void
    {
        $this->db->query(
            "UPDATE migration_runs SET status = 'running', started_at = COALESCE(started_at, ?), updated_at = ? WHERE id = ?",
            [sql_now(), sql_now(), $runId]
        );
    }

    public function pause(int $runId): void
    {
        $this->db->query("UPDATE migration_runs SET status = 'paused', updated_at = ? WHERE id = ?", [sql_now(), $runId]);
    }

    public function processNext(int $runId): array
    {
        $run = $this->run($runId);
        if (!$run) {
            throw new \RuntimeException('Migration run not found.');
        }

        if ($run['status'] === 'paused') {
            return $this->summary($runId);
        }

        if ($run['source_type'] !== 'source_database') {
            $this->log($runId, null, 'warning', 'SQL dump is registered. Import it into a staging database, then create a source database run for throttled migration.');
            return $this->summary($runId);
        }

        if ($run['status'] === 'failed') {
            $this->db->query(
                "UPDATE migration_steps SET status = 'pending', message = CONCAT('Retrying after fix. Previous error: ', COALESCE(message, '')) WHERE run_id = ? AND status = 'failed'",
                [$runId]
            );
            $this->log($runId, null, 'info', 'Retrying failed migration steps.');
        }

        if ($run['status'] !== 'running') {
            $this->start($runId);
        }

        $step = $this->db->fetch(
            "SELECT * FROM migration_steps
             WHERE run_id = ? AND status IN ('pending', 'running')
             ORDER BY id LIMIT 1",
            [$runId]
        );

        if (!$step) {
            $this->db->query("UPDATE migration_runs SET status = 'completed', completed_at = ?, updated_at = ? WHERE id = ?", [sql_now(), sql_now(), $runId]);
            $this->log($runId, null, 'info', 'Migration completed.');
            return $this->summary($runId);
        }

        $this->db->query(
            "UPDATE migration_steps SET status = 'running', started_at = COALESCE(started_at, ?), updated_at = ? WHERE id = ?",
            [sql_now(), sql_now(), (int)$step['id']]
        );

        try {
            $source = $this->sourcePdo($run);
            $processed = $this->processStep($run, $step, $source);

            if ($processed['done']) {
                $this->db->query(
                    "UPDATE migration_steps SET status = ?, last_source_id = ?, processed_count = processed_count + ?, message = ?, completed_at = ?, updated_at = ? WHERE id = ?",
                    [$processed['skipped'] ? 'skipped' : 'completed', $processed['last_id'], $processed['count'], $processed['message'], sql_now(), sql_now(), (int)$step['id']]
                );
            } else {
                $this->db->query(
                    "UPDATE migration_steps SET last_source_id = ?, processed_count = processed_count + ?, message = ?, updated_at = ? WHERE id = ?",
                    [$processed['last_id'], $processed['count'], $processed['message'], sql_now(), (int)$step['id']]
                );
            }

            $this->db->query("UPDATE migration_runs SET current_step = ?, updated_at = ? WHERE id = ?", [$step['step_key'], sql_now(), $runId]);
            $this->log($runId, $step['step_key'], 'info', $processed['message'], ['count' => $processed['count'], 'last_id' => $processed['last_id']]);
        } catch (Throwable $e) {
            $this->db->query("UPDATE migration_steps SET status = 'failed', message = ?, updated_at = ? WHERE id = ?", [$e->getMessage(), sql_now(), (int)$step['id']]);
            $this->db->query("UPDATE migration_runs SET status = 'failed', updated_at = ? WHERE id = ?", [sql_now(), $runId]);
            $this->log($runId, $step['step_key'], 'error', $e->getMessage());
            throw $e;
        }

        return $this->summary($runId);
    }

    public function summary(int $runId): array
    {
        return [
            'run' => $this->run($runId),
            'steps' => $this->steps($runId),
            'logs' => $this->logs($runId),
        ];
    }

    private function processStep(array $run, array $step, PDO $source): array
    {
        $method = 'migrate' . str_replace(' ', '', ucwords(str_replace('_', ' ', (string)$step['step_key'])));
        if (!method_exists($this, $method)) {
            return $this->result((int)$step['last_source_id'], 0, true, 'Step is not implemented yet.', true);
        }

        return $this->{$method}($run, $step, $source);
    }

    private function migrateUsers(array $run, array $step, PDO $source): array
    {
        $prefix = $run['table_prefix'];
        $skipExisting = $this->runOption($run, 'skip_existing_users');
        if (!$this->sourceTableExists($source, $prefix . 'users')) {
            return $this->result((int)$step['last_source_id'], 0, true, 'Source users table not found.', true);
        }

        $rows = $this->sourceRows($source, "SELECT * FROM `{$prefix}users` WHERE ID > ? ORDER BY ID LIMIT ?", [(int)$step['last_source_id'], (int)$run['batch_size']]);
        if ($rows === []) {
            return $this->result((int)$step['last_source_id'], 0, true, 'Users completed.');
        }

        $meta = $this->userMeta($source, $prefix, array_column($rows, 'ID'));
        $last = (int)$step['last_source_id'];
        foreach ($rows as $row) {
            $wpId = (int)$row['ID'];
            $last = max($last, $wpId);
            $m = $meta[$wpId] ?? [];
            $first = $this->limitText($m['first_name'] ?? $m['billing_first_name'] ?? null, 120);
            $lastName = $this->limitText($m['last_name'] ?? $m['billing_last_name'] ?? null, 120);
            $display = $this->limitText($row['display_name'] ?: trim(($first ?? '') . ' ' . ($lastName ?? '')) ?: $row['user_login'], 190);
            $phone = $this->limitText($m['billing_phone'] ?? $m['phone'] ?? null, 40);
            $username = $this->limitText((string)$row['user_login'], 100) ?? 'wp-user-' . $wpId;
            $email = $this->limitText((string)$row['user_email'], 190) ?? 'wp-user-' . $wpId . '@legacy.local';
            $existingUserId = $this->targetIdOrNull('users', 'wp_user_id', $wpId)
                ?? $this->userIdByLegacyIdentity($email, $username);

            if (!$skipExisting || !$existingUserId) {
                $this->db->query(
                    "INSERT INTO users (wp_user_id, email, username, password_hash, first_name, last_name, display_name, phone, status, email_verified_at, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE email = VALUES(email), username = VALUES(username), first_name = VALUES(first_name), last_name = VALUES(last_name), display_name = VALUES(display_name), phone = VALUES(phone), email_verified_at = COALESCE(email_verified_at, VALUES(email_verified_at)), updated_at = CURRENT_TIMESTAMP",
                    [$wpId, $email, $username, $this->migrationPlaceholderPasswordHash(), $first, $lastName, $display, $phone, ((int)$row['user_status'] === 0 ? 'active' : 'pending'), $this->dateOrNow($row['user_registered']), $this->dateOrNow($row['user_registered'])]
                );
            }

            $userId = $existingUserId
                ?? $this->targetIdOrNull('users', 'wp_user_id', $wpId)
                ?? $this->userIdByLegacyIdentity($email, $username);
            if (!$userId) {
                throw new \RuntimeException("Missing mapped users.wp_user_id: {$wpId}");
            }

            $this->claimWpUserIdIfAvailable($userId, $wpId);
            foreach ($this->rolesFromMeta($m) as $role) {
                $roleId = $this->roleId($role);
                $this->db->query("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $roleId]);
            }
            $this->map($prefix . 'users', (string)$wpId, 'users', $userId);
        }

        return $this->result($last, count($rows), false, 'Users batch migrated.');
    }

    private function migrateFiles(array $run, array $step, PDO $source): array
    {
        $prefix = $run['table_prefix'];
        if (!$this->sourceTableExists($source, $prefix . 'posts')) {
            return $this->result((int)$step['last_source_id'], 0, true, 'Source posts table not found for media files.', true);
        }

        $recent = $this->recentPostClause($run);
        $rows = $this->sourceRows(
            $source,
            "SELECT * FROM `{$prefix}posts` WHERE ID > ? AND post_type = 'attachment'{$recent['sql']} ORDER BY ID LIMIT ?",
            array_merge([(int)$step['last_source_id']], $recent['params'], [(int)$run['batch_size']])
        );
        if ($rows === []) return $this->result((int)$step['last_source_id'], 0, true, 'Files completed.');
        $last = (int)$step['last_source_id'];
        foreach ($rows as $row) {
            $last = max($last, (int)$row['ID']);
            $this->db->query(
                "INSERT INTO files (wp_attachment_id, owner_user_id, disk, path, original_name, mime_type, created_at)
                 VALUES (?, ?, 'remote', ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE path = VALUES(path), original_name = VALUES(original_name), mime_type = VALUES(mime_type)",
                [(int)$row['ID'], $this->userIdByWp((int)$row['post_author']), $row['guid'], $row['post_title'], $row['post_mime_type'], $this->dateOrNow($row['post_date'])]
            );
            $this->map($prefix . 'posts', (string)$row['ID'], 'files', $this->targetId('files', 'wp_attachment_id', (int)$row['ID']));
        }
        return $this->result($last, count($rows), false, 'Media files batch migrated.');
    }

    private function migrateTerms(array $run, array $step, PDO $source): array
    {
        $prefix = $run['table_prefix'];
        if (!$this->sourceTableExists($source, $prefix . 'terms') || !$this->sourceTableExists($source, $prefix . 'term_taxonomy')) {
            return $this->result((int)$step['last_source_id'], 0, true, 'Source terms tables not found.', true);
        }

        $sql = "SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.parent, tt.description
                FROM `{$prefix}terms` t
                JOIN `{$prefix}term_taxonomy` tt ON tt.term_id = t.term_id
                WHERE t.term_id > ? AND tt.taxonomy IN ('product_cat','product_tag','product_brand','pa_brand')
                ORDER BY t.term_id LIMIT ?";
        $rows = $this->sourceRows($source, $sql, [(int)$step['last_source_id'], (int)$run['batch_size']]);
        if ($rows === []) return $this->result((int)$step['last_source_id'], 0, true, 'Terms completed.');
        $last = (int)$step['last_source_id'];
        foreach ($rows as $row) {
            $last = max($last, (int)$row['term_id']);
            if ($row['taxonomy'] === 'product_cat') {
                $this->db->query(
                    "INSERT INTO categories (wp_term_id, name, slug, description, is_active) VALUES (?, ?, ?, ?, 1)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug), description = VALUES(description)",
                    [(int)$row['term_id'], $row['name'], $row['slug'], $row['description']]
                );
                $this->map($prefix . 'terms', (string)$row['term_id'], 'categories', $this->targetId('categories', 'wp_term_id', (int)$row['term_id']));
            } elseif (in_array($row['taxonomy'], ['product_brand', 'pa_brand'], true)) {
                $this->db->query("INSERT INTO brands (wp_term_id, name, slug, description) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug)", [(int)$row['term_id'], $row['name'], $row['slug'], $row['description']]);
                $this->map($prefix . 'terms', (string)$row['term_id'], 'brands', $this->targetId('brands', 'wp_term_id', (int)$row['term_id']));
            } else {
                $this->db->query("INSERT INTO tags (wp_term_id, name, slug) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug)", [(int)$row['term_id'], $row['name'], $row['slug']]);
                $this->map($prefix . 'terms', (string)$row['term_id'], 'tags', $this->targetId('tags', 'wp_term_id', (int)$row['term_id']));
            }
        }
        return $this->result($last, count($rows), false, 'Terms batch migrated.');
    }

    private function migrateVendors(array $run, array $step, PDO $source): array
    {
        $prefix = $run['table_prefix'];
        if (!$this->sourceTableExists($source, $prefix . 'users') || !$this->sourceTableExists($source, $prefix . 'usermeta')) {
            return $this->result((int)$step['last_source_id'], 0, true, 'Source user tables not found for vendors.', true);
        }

        $sql = "SELECT u.* FROM `{$prefix}users` u
                WHERE u.ID > ? AND EXISTS (
                    SELECT 1 FROM `{$prefix}usermeta` um
                    WHERE um.user_id = u.ID
                    AND (um.meta_key LIKE '%capabilities' AND (um.meta_value LIKE '%seller%' OR um.meta_value LIKE '%vendor%' OR um.meta_value LIKE '%dokan%')
                         OR um.meta_key IN ('dokan_store_name', 'store_name'))
                )
                ORDER BY u.ID LIMIT ?";
        $rows = $this->sourceRows($source, $sql, [(int)$step['last_source_id'], (int)$run['batch_size']]);
        if ($rows === []) return $this->result((int)$step['last_source_id'], 0, true, 'Vendors completed.');
        $meta = $this->userMeta($source, $prefix, array_column($rows, 'ID'));
        $last = (int)$step['last_source_id'];
        foreach ($rows as $row) {
            $wpId = (int)$row['ID'];
            $last = max($last, $wpId);
            $userId = $this->userIdByWp($wpId);
            if (!$userId) continue;
            $m = $meta[$wpId] ?? [];
            $profile = $this->dokanProfile($m['dokan_profile_settings'] ?? null);
            $store = $this->limitText($m['dokan_store_name'] ?? $m['store_name'] ?? $row['display_name'] ?: $row['user_login'], 190) ?? 'Imported Store ' . $wpId;
            $slug = $this->limitText($this->slug($m['dokan_store_slug'] ?? $row['user_nicename'] ?? $store), 190) ?? 'imported-store-' . $wpId;
            $storeEmail = $this->limitText((string)$row['user_email'], 190);
            $storePhone = $this->limitText($profile['phone'] ?? $m['billing_phone'] ?? null, 40);
            $description = $this->runOption($run, 'fill_missing_vendor_descriptions') ? $this->vendorDescription($m) : null;
            $this->db->query(
                "INSERT INTO vendors (wp_user_id, user_id, store_name, store_slug, store_email, store_phone, description, status, kyc_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 'approved')
                 ON DUPLICATE KEY UPDATE store_name = VALUES(store_name), store_slug = VALUES(store_slug), store_email = VALUES(store_email), store_phone = VALUES(store_phone), description = COALESCE(NULLIF(description, ''), VALUES(description))",
                [$wpId, $userId, $store, $slug, $storeEmail, $storePhone, $description]
            );
            $this->db->query("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $this->roleId('vendor')]);
            $vendorId = $this->targetIdOrNull('vendors', 'wp_user_id', $wpId)
                ?? $this->vendorIdByLegacyIdentity($userId, $slug, $storeEmail);
            if (!$vendorId) {
                throw new \RuntimeException("Missing mapped vendors.wp_user_id: {$wpId}");
            }

            $this->claimWpVendorIdIfAvailable($vendorId, $wpId);
            $this->map($prefix . 'users', (string)$wpId, 'vendors', $vendorId);
        }
        return $this->result($last, count($rows), false, 'Vendors batch migrated.');
    }

    private function migrateProducts(array $run, array $step, PDO $source): array
    {
        $prefix = $run['table_prefix'];
        if (!$this->sourceTableExists($source, $prefix . 'posts')) {
            return $this->result((int)$step['last_source_id'], 0, true, 'Source posts table not found for products.', true);
        }

        $recent = $this->recentPostClause($run);
        $rows = $this->sourceRows(
            $source,
            "SELECT * FROM `{$prefix}posts` WHERE ID > ? AND post_type IN ('product','product_variation'){$recent['sql']} ORDER BY ID LIMIT ?",
            array_merge([(int)$step['last_source_id']], $recent['params'], [(int)$run['batch_size']])
        );
        if ($rows === []) return $this->result((int)$step['last_source_id'], 0, true, 'Products completed.');
        $meta = $this->postMeta($source, $prefix, array_column($rows, 'ID'));
        $categories = $this->postPrimaryCategories($source, $prefix, array_column($rows, 'ID'));
        $last = (int)$step['last_source_id'];
        foreach ($rows as $row) {
            $wpId = (int)$row['ID'];
            $last = max($last, $wpId);
            $m = $meta[$wpId] ?? [];
            $vendorId = $this->vendorIdByWp((int)$row['post_author']);
            $parent = (int)$row['post_parent'] > 0 ? $this->productIdByWp((int)$row['post_parent']) : null;
            $sku = $this->uniqueProductImportValue('sku', $this->limitText($m['_sku'] ?? null, 100), $wpId, 100);
            $slug = $this->uniqueProductImportValue('slug', $this->limitText($row['post_name'] ?: 'product-' . $wpId, 255), $wpId, 255) ?? 'product-' . $wpId;
            $name = $this->limitText($row['post_title'] ?: 'Imported Product #' . $wpId, 255) ?? 'Imported Product #' . $wpId;
            $this->db->query(
                "INSERT INTO products
                (wp_post_id, vendor_id, type, parent_product_id, sku, name, slug, short_description, description, status, regular_price, sale_price, currency, tax_status, stock_status, stock_quantity, manage_stock, weight, length, width, height, virtual, downloadable, average_rating, review_count, total_sales, featured, published_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'USD', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE vendor_id = VALUES(vendor_id), sku = VALUES(sku), name = VALUES(name), slug = VALUES(slug), description = VALUES(description), status = VALUES(status), regular_price = VALUES(regular_price), sale_price = VALUES(sale_price), tax_status = VALUES(tax_status), stock_status = VALUES(stock_status), stock_quantity = VALUES(stock_quantity), weight = VALUES(weight), length = VALUES(length), width = VALUES(width), height = VALUES(height), updated_at = CURRENT_TIMESTAMP",
                [
                    $wpId, $vendorId, $row['post_type'] === 'product_variation' ? 'variation' : (($m['_product_type'] ?? '') === 'variable' ? 'variable' : 'simple'), $parent,
                    $sku, $name, $slug,
                    $row['post_excerpt'], $row['post_content'], $this->productStatus($row['post_status']),
                    $this->money($m['_regular_price'] ?? $m['_price'] ?? 0), isset($m['_sale_price']) && $m['_sale_price'] !== '' ? $this->money($m['_sale_price']) : null,
                    $this->productTaxStatus($m['_tax_status'] ?? null),
                    ($m['_stock_status'] ?? 'instock') === 'outofstock' ? 'out_of_stock' : 'in_stock',
                    isset($m['_stock']) && $m['_stock'] !== '' ? (int)$m['_stock'] : null,
                    ($m['_manage_stock'] ?? 'no') === 'yes' ? 1 : 0,
                    $this->nullableMoney($m['_weight'] ?? null),
                    $this->nullableMoney($m['_length'] ?? null),
                    $this->nullableMoney($m['_width'] ?? null),
                    $this->nullableMoney($m['_height'] ?? null),
                    ($m['_virtual'] ?? 'no') === 'yes' ? 1 : 0,
                    ($m['_downloadable'] ?? 'no') === 'yes' ? 1 : 0,
                    $this->money($m['_wc_average_rating'] ?? 0),
                    (int)($m['_wc_review_count'] ?? 0),
                    (int)($m['total_sales'] ?? 0),
                    ($m['_featured'] ?? 'no') === 'yes' ? 1 : 0,
                    $row['post_date'] !== '0000-00-00 00:00:00' ? $row['post_date'] : null,
                    $this->dateOrNow($row['post_date']),
                ]
            );
            $productId = $this->targetIdOrNull('products', 'wp_post_id', $wpId)
                ?? $this->productIdByLegacyIdentity($sku, $slug);
            if (!$productId) {
                throw new \RuntimeException("Missing mapped products.wp_post_id: {$wpId}");
            }

            $this->claimWpProductIdIfAvailable($productId, $wpId);
            if (!empty($m['_thumbnail_id'])) {
                $fileId = $this->fileIdByWp((int)$m['_thumbnail_id']);
                if ($fileId) {
                    $this->db->query("INSERT IGNORE INTO product_media (product_id, file_id, role, sort_order) VALUES (?, ?, 'primary', 0)", [$productId, $fileId]);
                }
            }
            $this->map($prefix . 'posts', (string)$wpId, 'products', $productId);
        }
        return $this->result($last, count($rows), false, 'Products batch migrated.');
    }

    private function migrateProductTerms(array $run, array $step, PDO $source): array
    {
        $prefix = $run['table_prefix'];
        if (
            !$this->sourceTableExists($source, $prefix . 'term_relationships')
            || !$this->sourceTableExists($source, $prefix . 'term_taxonomy')
            || !$this->sourceTableExists($source, $prefix . 'terms')
        ) {
            return $this->result((int)$step['last_source_id'], 0, true, 'Source product relationship tables not found.', true);
        }

        $sql = "SELECT tr.object_id, t.term_id, t.name, t.slug, tt.taxonomy
                FROM `{$prefix}term_relationships` tr
                JOIN `{$prefix}term_taxonomy` tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                JOIN `{$prefix}terms` t ON t.term_id = tt.term_id
                WHERE tr.object_id > ? AND tt.taxonomy IN ('product_cat','product_tag','product_shipping_class')
                ORDER BY tr.object_id LIMIT ?";
        $rows = $this->sourceRows($source, $sql, [(int)$step['last_source_id'], (int)$run['batch_size']]);
        if ($rows === []) return $this->result((int)$step['last_source_id'], 0, true, 'Product relationships completed.');
        $last = (int)$step['last_source_id'];
        foreach ($rows as $row) {
            $last = max($last, (int)$row['object_id']);
            $productId = $this->productIdByWp((int)$row['object_id']);
            if (!$productId) continue;
            if ($row['taxonomy'] === 'product_cat') {
                $categoryId = $this->targetIdOrNull('categories', 'wp_term_id', (int)$row['term_id']);
                if ($categoryId) $this->db->query("INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)", [$productId, $categoryId]);
            } elseif ($row['taxonomy'] === 'product_tag') {
                $tagId = $this->targetIdOrNull('tags', 'wp_term_id', (int)$row['term_id']);
                if ($tagId) $this->db->query("INSERT IGNORE INTO product_tags (product_id, tag_id) VALUES (?, ?)", [$productId, $tagId]);
            } else {
                $shippingClass = $this->limitText($row['slug'] ?: $row['name'] ?: null, 120);
                if ($shippingClass !== null) {
                    $this->db->query(
                        "UPDATE products SET shipping_class = COALESCE(NULLIF(shipping_class, ''), ?) WHERE id = ?",
                        [$shippingClass, $productId]
                    );
                }
            }
        }
        return $this->result($last, count($rows), false, 'Product relationships batch migrated.');
    }

    private function migrateShipping(array $run, array $step, PDO $source): array
    {
        $prefix = $run['table_prefix'];
        if (!$this->sourceTableExists($source, $prefix . 'woocommerce_shipping_zones')) {
            return $this->result((int)$step['last_source_id'], 0, true, 'Shipping tables not found.', true);
        }
        $rows = $this->sourceRows($source, "SELECT * FROM `{$prefix}woocommerce_shipping_zones` WHERE zone_id > ? ORDER BY zone_id LIMIT ?", [(int)$step['last_source_id'], (int)$run['batch_size']]);
        if ($rows === []) {
            $locationCount = $this->migrateShippingZoneLocations($source, $prefix);
            $methodCount = $this->migrateShippingZoneMethods($source, $prefix);
            return $this->result((int)$step['last_source_id'], $methodCount + $locationCount, true, 'Shipping completed.');
        }
        $last = (int)$step['last_source_id'];
        foreach ($rows as $row) {
            $last = max($last, (int)$row['zone_id']);
            $this->db->query("INSERT INTO shipping_zones (wp_zone_id, name, sort_order, is_active) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order)", [(int)$row['zone_id'], $row['zone_name'], (int)$row['zone_order']]);
        }
        $this->migrateShippingZoneLocations($source, $prefix);
        $this->migrateShippingZoneMethods($source, $prefix);
        return $this->result($last, count($rows), false, 'Shipping zones batch migrated.');
    }

    private function migrateContentPosts(array $run, array $step, PDO $source): array
    {
        $prefix = $run['table_prefix'];
        if (!$this->sourceTableExists($source, $prefix . 'posts')) {
            return $this->result((int)$step['last_source_id'], 0, true, 'Source posts table not found for blog/news.', true);
        }
        $rows = $this->sourceRows(
            $source,
            "SELECT * FROM `{$prefix}posts` WHERE ID > ? AND post_type = 'post' AND post_status NOT IN ('auto-draft','inherit') ORDER BY ID LIMIT ?",
            [(int)$step['last_source_id'], (int)$run['batch_size']]
        );
        if ($rows === []) return $this->result((int)$step['last_source_id'], 0, true, 'Blog and news posts completed.');
        $meta = $this->postMeta($source, $prefix, array_column($rows, 'ID'));
        $last = (int)$step['last_source_id'];
        foreach ($rows as $row) {
            $wpId = (int)$row['ID'];
            $last = max($last, $wpId);
            $m = $meta[$wpId] ?? [];
            $image = null;
            if (!empty($m['_thumbnail_id'])) {
                $file = $this->db->fetch('SELECT url, storage_path FROM files WHERE wp_attachment_id = ? LIMIT 1', [(int)$m['_thumbnail_id']]);
                $image = $file['url'] ?? $file['storage_path'] ?? null;
            }
            $title = $this->limitText($row['post_title'] ?: 'Imported Post #' . $wpId, 255) ?? 'Imported Post #' . $wpId;
            $slug = $this->uniqueContentPostSlug($this->limitText($row['post_name'] ?: $title, 255) ?? ('post-' . $wpId), $wpId);
            $this->db->query(
                "INSERT INTO content_posts (wp_post_id, title, slug, excerpt, body, featured_image_url, category, author_name, status, published_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE title = VALUES(title), slug = VALUES(slug), excerpt = VALUES(excerpt), body = VALUES(body), featured_image_url = VALUES(featured_image_url), category = VALUES(category), status = VALUES(status), published_at = VALUES(published_at), updated_at = CURRENT_TIMESTAMP",
                [
                    $wpId,
                    $title,
                    $slug,
                    $this->cleanLongText($row['post_excerpt'] ?? null),
                    $this->cleanLongText($row['post_content'] ?? null),
                    $this->limitText($image, 500),
                    $this->limitText($categories[$wpId] ?? null, 120),
                    'Seller Africa',
                    $row['post_status'] === 'publish' ? 'published' : 'draft',
                    $row['post_date'] !== '0000-00-00 00:00:00' ? $row['post_date'] : null,
                    $this->dateOrNow($row['post_date']),
                ]
            );
            $postId = $this->targetId('content_posts', 'wp_post_id', $wpId);
            $this->map($prefix . 'posts', (string)$wpId, 'content_posts', $postId);
        }
        return $this->result($last, count($rows), false, 'Blog and news posts batch migrated.');
    }

    private function migrateProductShippingDetails(array $run, array $step, PDO $source): array
    {
        $prefix = $run['table_prefix'];
        if (!$this->sourceTableExists($source, $prefix . 'posts') || !$this->sourceTableExists($source, $prefix . 'postmeta')) {
            return $this->result((int)$step['last_source_id'], 0, true, 'Source product shipping tables not found.', true);
        }

        $rows = $this->sourceRows(
            $source,
            "SELECT ID FROM `{$prefix}posts` WHERE ID > ? AND post_type IN ('product','product_variation') ORDER BY ID LIMIT ?",
            [(int)$step['last_source_id'], (int)$run['batch_size']]
        );
        if ($rows === []) return $this->result((int)$step['last_source_id'], 0, true, 'Product shipping details completed.');

        $wpIds = array_map(static fn (array $row): int => (int)$row['ID'], $rows);
        $meta = $this->postMeta($source, $prefix, $wpIds);
        $classes = $this->productShippingClasses($source, $prefix, $wpIds);
        $last = (int)$step['last_source_id'];
        $updated = 0;
        foreach ($rows as $row) {
            $wpId = (int)$row['ID'];
            $last = max($last, $wpId);
            $productId = $this->productIdByWp($wpId);
            if (!$productId) {
                continue;
            }
            $m = $meta[$wpId] ?? [];
            $this->db->query(
                "UPDATE products
                 SET weight = ?, length = ?, width = ?, height = ?, shipping_class = COALESCE(?, shipping_class), updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?",
                [
                    $this->nullableMoney($m['_weight'] ?? null),
                    $this->nullableMoney($m['_length'] ?? null),
                    $this->nullableMoney($m['_width'] ?? null),
                    $this->nullableMoney($m['_height'] ?? null),
                    $this->limitText($classes[$wpId] ?? null, 120),
                    $productId,
                ]
            );
            $updated++;
        }

        return $this->result($last, count($rows), false, 'Product shipping details batch migrated. Updated ' . $updated . ' existing products.');
    }

    private function migrateOrders(array $run, array $step, PDO $source): array
    {
        $prefix = $run['table_prefix'];
        if (!$this->sourceTableExists($source, $prefix . 'wc_orders')) {
            return $this->result((int)$step['last_source_id'], 0, true, 'WooCommerce HPOS orders table not found.', true);
        }
        $recent = $this->recentOrderClause($run);
        $rows = $this->sourceRows(
            $source,
            "SELECT * FROM `{$prefix}wc_orders` WHERE id > ? AND type = 'shop_order'{$recent['sql']} ORDER BY id LIMIT ?",
            array_merge([(int)$step['last_source_id']], $recent['params'], [(int)$run['batch_size']])
        );
        if ($rows === []) return $this->result((int)$step['last_source_id'], 0, true, 'Orders completed.');
        $last = (int)$step['last_source_id'];
        foreach ($rows as $row) {
            $last = max($last, (int)$row['id']);
            $customerId = $this->userIdByWp((int)$row['customer_id']);
            $this->db->query(
                "INSERT INTO orders (wp_order_id, order_number, customer_id, guest_email, status, payment_status, currency, tax_total, grand_total, customer_note, ip_address, user_agent, placed_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), payment_status = VALUES(payment_status), grand_total = VALUES(grand_total), updated_at = CURRENT_TIMESTAMP",
                [(int)$row['id'], (string)$row['id'], $customerId, $row['billing_email'], $this->orderStatus($row['status']), $this->paymentStatus($row['status']), $row['currency'] ?: 'USD', $this->money($row['tax_amount']), $this->money($row['total_amount']), $row['customer_note'], $row['ip_address'], $row['user_agent'], $row['date_created_gmt'], $this->dateOrNow($row['date_created_gmt'])]
            );
            $orderId = $this->targetId('orders', 'wp_order_id', (int)$row['id']);
            $this->migrateOrderAddresses($source, $prefix, (int)$row['id'], $orderId, $customerId);
            if (!empty($row['payment_method']) || !empty($row['transaction_id'])) {
                $this->db->query(
                    "INSERT INTO payments (order_id, user_id, provider, provider_reference, provider_status, amount, currency, status, paid_at, raw_response)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE provider_status = VALUES(provider_status), amount = VALUES(amount), status = VALUES(status)",
                    [$orderId, $customerId, $row['payment_method'] ?: 'unknown', $row['transaction_id'] ?: 'wp-order-' . $row['id'], $row['payment_method_title'], $this->money($row['total_amount']), $row['currency'] ?: 'USD', $this->paymentStatus($row['status']) === 'paid' ? 'paid' : 'pending', $this->paymentStatus($row['status']) === 'paid' ? $row['date_updated_gmt'] : null, null]
                );
            }
            $this->map($prefix . 'wc_orders', (string)$row['id'], 'orders', $orderId);
        }
        return $this->result($last, count($rows), false, 'Orders batch migrated.');
    }

    private function migrateOrderItems(array $run, array $step, PDO $source): array
    {
        $prefix = $run['table_prefix'];
        if (!$this->sourceTableExists($source, $prefix . 'woocommerce_order_items')) {
            return $this->result((int)$step['last_source_id'], 0, true, 'WooCommerce order items table not found.', true);
        }

        $rows = $this->sourceRows($source, "SELECT * FROM `{$prefix}woocommerce_order_items` WHERE order_item_id > ? ORDER BY order_item_id LIMIT ?", [(int)$step['last_source_id'], (int)$run['batch_size']]);
        if ($rows === []) return $this->result((int)$step['last_source_id'], 0, true, 'Order items completed.');
        $last = (int)$step['last_source_id'];
        foreach ($rows as $row) {
            $last = max($last, (int)$row['order_item_id']);
            $orderId = $this->targetIdOrNull('orders', 'wp_order_id', (int)$row['order_id']);
            if (!$orderId) continue;
            $meta = $this->orderItemMeta($source, $prefix, (int)$row['order_item_id']);
            $type = match ($row['order_item_type']) {
                'shipping' => 'shipping',
                'fee' => 'fee',
                'tax' => 'tax',
                'coupon' => 'coupon',
                default => 'product',
            };
            $quantity = max(1.0, $this->money($meta['_qty'] ?? 1));
            $productId = null;
            if ($type === 'product') {
                $wpProductId = (int)($meta['_variation_id'] ?? 0) ?: (int)($meta['_product_id'] ?? 0);
                $productId = $wpProductId > 0 ? $this->targetIdOrNull('products', 'wp_post_id', $wpProductId) : null;
            }
            $subtotal = match ($type) {
                'product' => $this->money($meta['_line_subtotal'] ?? 0),
                'shipping' => $this->money($meta['cost'] ?? $meta['_line_total'] ?? 0),
                'fee' => $this->money($meta['_line_total'] ?? 0),
                default => 0.0,
            };
            $total = match ($type) {
                'product' => $this->money($meta['_line_total'] ?? $subtotal),
                'shipping' => $this->money($meta['cost'] ?? $meta['_line_total'] ?? $subtotal),
                'fee' => $this->money($meta['_line_total'] ?? $subtotal),
                'tax' => $this->money($meta['tax_amount'] ?? $meta['shipping_tax_amount'] ?? 0),
                default => 0.0,
            };
            $taxTotal = match ($type) {
                'product' => $this->money($meta['_line_tax'] ?? $meta['_line_subtotal_tax'] ?? 0),
                'shipping' => $this->money($meta['total_tax'] ?? 0),
                default => 0.0,
            };
            $discount = $type === 'product' ? max(0, $subtotal - $total) : 0.0;
            $unitPrice = $type === 'product' && $quantity > 0 ? round($total / $quantity, 4) : $total;
            $sku = null;
            if ($productId) {
                $skuRow = $this->db->fetch('SELECT sku FROM products WHERE id = ? LIMIT 1', [$productId]);
                $sku = $this->limitText($skuRow['sku'] ?? null, 100);
            }
            $this->db->query(
                "INSERT INTO order_items
                    (wp_order_item_id, order_id, product_id, item_type, name, sku, quantity, unit_price, subtotal, discount_total, tax_total, total, metadata)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE product_id = VALUES(product_id), name = VALUES(name), sku = VALUES(sku),
                    item_type = VALUES(item_type), quantity = VALUES(quantity), unit_price = VALUES(unit_price),
                    subtotal = VALUES(subtotal), discount_total = VALUES(discount_total), tax_total = VALUES(tax_total),
                    total = VALUES(total), metadata = VALUES(metadata)",
                [
                    (int)$row['order_item_id'],
                    $orderId,
                    $productId,
                    $type,
                    html_entity_decode($row['order_item_name']),
                    $sku,
                    $quantity,
                    $unitPrice,
                    $subtotal,
                    $discount,
                    $taxTotal,
                    $total,
                    json_encode($meta, JSON_UNESCAPED_SLASHES),
                ]
            );
            $this->refreshOrderFinancials($orderId);
        }
        return $this->result($last, count($rows), false, 'Order items batch migrated.');
    }

    private function migrateOrderAddresses(PDO $source, string $prefix, int $wpOrderId, int $orderId, ?int $customerId): void
    {
        foreach (['billing', 'shipping'] as $type) {
            $address = $this->sourceOrderAddress($source, $prefix, $wpOrderId, $type);
            if ($address === []) {
                continue;
            }

            $line1 = $this->limitText($address['address_line1'] ?? null, 255);
            if ($line1 === null) {
                continue;
            }

            $this->db->query(
                "INSERT INTO addresses
                    (user_id, type, first_name, last_name, company, phone, email, address_line1, address_line2, city, state, postcode, country_code, is_default, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)",
                [
                    $customerId,
                    $type,
                    $this->limitText($address['first_name'] ?? null, 120),
                    $this->limitText($address['last_name'] ?? null, 120),
                    $this->limitText($address['company'] ?? null, 190),
                    $this->limitText($address['phone'] ?? null, 40),
                    $this->limitText($address['email'] ?? null, 190),
                    $line1,
                    $this->limitText($address['address_line2'] ?? null, 255),
                    $this->limitText($address['city'] ?? null, 120),
                    $this->limitText($address['state'] ?? null, 120),
                    $this->limitText($address['postcode'] ?? null, 40),
                    strtoupper(substr((string)($address['country_code'] ?? ''), 0, 2)) ?: null,
                    sql_now(),
                    sql_now(),
                ]
            );
            $addressId = (int)$this->db->lastInsertId();
            $column = $type === 'billing' ? 'billing_address_id' : 'shipping_address_id';
            $this->db->query("UPDATE orders SET {$column} = COALESCE({$column}, ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$addressId, $orderId]);
        }
    }

    private function sourceOrderAddress(PDO $source, string $prefix, int $wpOrderId, string $type): array
    {
        if ($this->sourceTableExists($source, $prefix . 'wc_order_addresses')) {
            $row = $this->sourceRows(
                $source,
                "SELECT first_name, last_name, company, address_1 AS address_line1, address_2 AS address_line2,
                        city, state, postcode, country AS country_code, email, phone
                 FROM `{$prefix}wc_order_addresses`
                 WHERE order_id = ? AND address_type = ?
                 LIMIT 1",
                [$wpOrderId, $type]
            )[0] ?? null;
            if ($row) {
                return $row;
            }
        }

        if (!$this->sourceTableExists($source, $prefix . 'postmeta')) {
            return [];
        }

        $metaRows = $this->sourceRows(
            $source,
            "SELECT meta_key, meta_value FROM `{$prefix}postmeta` WHERE post_id = ? AND meta_key LIKE ?",
            [$wpOrderId, '_' . $type . '\\_%']
        );
        $meta = [];
        foreach ($metaRows as $row) {
            $meta[(string)$row['meta_key']] = $row['meta_value'];
        }

        return [
            'first_name' => $meta['_' . $type . '_first_name'] ?? null,
            'last_name' => $meta['_' . $type . '_last_name'] ?? null,
            'company' => $meta['_' . $type . '_company'] ?? null,
            'address_line1' => $meta['_' . $type . '_address_1'] ?? null,
            'address_line2' => $meta['_' . $type . '_address_2'] ?? null,
            'city' => $meta['_' . $type . '_city'] ?? null,
            'state' => $meta['_' . $type . '_state'] ?? null,
            'postcode' => $meta['_' . $type . '_postcode'] ?? null,
            'country_code' => $meta['_' . $type . '_country'] ?? null,
            'email' => $meta['_' . $type . '_email'] ?? null,
            'phone' => $meta['_' . $type . '_phone'] ?? null,
        ];
    }

    private function orderItemMeta(PDO $source, string $prefix, int $wpOrderItemId): array
    {
        if (!$this->sourceTableExists($source, $prefix . 'woocommerce_order_itemmeta')) {
            return [];
        }

        $rows = $this->sourceRows(
            $source,
            "SELECT meta_key, meta_value FROM `{$prefix}woocommerce_order_itemmeta` WHERE order_item_id = ?",
            [$wpOrderItemId]
        );
        $meta = [];
        foreach ($rows as $row) {
            $meta[(string)$row['meta_key']] = $row['meta_value'];
        }

        return $meta;
    }

    private function refreshOrderFinancials(int $orderId): void
    {
        $this->db->query(
            "UPDATE orders o
             LEFT JOIN (
                SELECT order_id,
                    COALESCE(SUM(CASE WHEN item_type = 'product' THEN subtotal ELSE 0 END), 0) AS subtotal,
                    COALESCE(SUM(discount_total), 0) AS discount_total,
                    COALESCE(SUM(CASE WHEN item_type = 'shipping' THEN total ELSE 0 END), 0) AS shipping_total,
                    COALESCE(SUM(tax_total), 0) AS tax_total,
                    COALESCE(SUM(CASE WHEN item_type = 'fee' THEN total ELSE 0 END), 0) AS fee_total
                 FROM order_items
                 WHERE order_id = ?
                 GROUP BY order_id
             ) totals ON totals.order_id = o.id
             SET o.subtotal = COALESCE(totals.subtotal, o.subtotal),
                 o.discount_total = COALESCE(totals.discount_total, o.discount_total),
                 o.shipping_total = COALESCE(totals.shipping_total, o.shipping_total),
                 o.tax_total = COALESCE(NULLIF(totals.tax_total, 0), o.tax_total),
                 o.fee_total = COALESCE(totals.fee_total, o.fee_total),
                 o.updated_at = CURRENT_TIMESTAMP
             WHERE o.id = ?",
            [$orderId, $orderId]
        );
    }

    private function migrateVendorSplits(array $run, array $step, PDO $source): array
    {
        $prefix = $run['table_prefix'];
        if (!$this->sourceTableExists($source, $prefix . 'dokan_orders')) {
            return $this->result((int)$step['last_source_id'], 0, true, 'Dokan orders table not found.', true);
        }
        $rows = $this->sourceRows($source, "SELECT * FROM `{$prefix}dokan_orders` WHERE id > ? ORDER BY id LIMIT ?", [(int)$step['last_source_id'], (int)$run['batch_size']]);
        if ($rows === []) return $this->result((int)$step['last_source_id'], 0, true, 'Vendor splits completed.');
        $last = (int)$step['last_source_id'];
        foreach ($rows as $row) {
            $last = max($last, (int)$row['id']);
            $orderId = $this->targetIdOrNull('orders', 'wp_order_id', (int)$row['order_id']);
            $vendorId = $this->vendorIdByWp((int)$row['seller_id']);
            if (!$orderId || !$vendorId) continue;
            $gross = $this->money($row['order_total']);
            $net = $this->money($row['net_amount']);
            $this->db->query(
                "INSERT INTO order_vendor_splits (wp_dokan_order_id, order_id, vendor_id, status, gross_total, vendor_earning, platform_commission)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), gross_total = VALUES(gross_total), vendor_earning = VALUES(vendor_earning), platform_commission = VALUES(platform_commission)",
                [(int)$row['id'], $orderId, $vendorId, $this->orderStatus($row['order_status']), $gross, $net, max(0, $gross - $net)]
            );
        }
        return $this->result($last, count($rows), false, 'Vendor splits batch migrated.');
    }

    private function migrateAffiliates(array $run, array $step, PDO $source): array
    {
        $prefix = $run['table_prefix'];
        if (!$this->sourceTableExists($source, $prefix . 'affiliate_wp_affiliates')) {
            return $this->result((int)$step['last_source_id'], 0, true, 'AffiliateWP table not found.', true);
        }
        $rows = $this->sourceRows($source, "SELECT * FROM `{$prefix}affiliate_wp_affiliates` WHERE affiliate_id > ? ORDER BY affiliate_id LIMIT ?", [(int)$step['last_source_id'], (int)$run['batch_size']]);
        if ($rows === []) return $this->result((int)$step['last_source_id'], 0, true, 'Affiliates completed.');
        $last = (int)$step['last_source_id'];
        foreach ($rows as $row) {
            $last = max($last, (int)$row['affiliate_id']);
            $userId = $this->userIdByWp((int)$row['user_id']);
            if (!$userId) continue;
            $this->db->query(
                "INSERT INTO affiliates (wp_affiliate_id, user_id, referral_code, payment_email, rate_type, commission_rate, status, visits_count, referrals_count, total_earnings, unpaid_earnings, registered_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE payment_email = VALUES(payment_email), status = VALUES(status), visits_count = VALUES(visits_count), referrals_count = VALUES(referrals_count), total_earnings = VALUES(total_earnings), unpaid_earnings = VALUES(unpaid_earnings)",
                [(int)$row['affiliate_id'], $userId, 'aff' . $row['affiliate_id'], $row['payment_email'], $row['rate_type'] ?: 'percentage', $this->money($row['rate'] ?: 0), $row['status'] ?: 'pending', (int)$row['visits'], (int)$row['referrals'], $this->money($row['earnings']), $this->money($row['unpaid_earnings']), $row['date_registered']]
            );
            $this->db->query("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $this->roleId('affiliate')]);
        }
        return $this->result($last, count($rows), false, 'Affiliates batch migrated.');
    }

    private function migrateAffiliateActivity(array $run, array $step, PDO $source): array
    {
        return $this->result((int)$step['last_source_id'], 0, true, 'Affiliate activity tables are registered for phase two after core users/orders are verified.', true);
    }

    private function sourcePdo(array $run): PDO
    {
        $config = json_decode((string)$run['source_config'], true, flags: JSON_THROW_ON_ERROR);
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database'], $config['charset'] ?? 'utf8mb4');
        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private function runOption(array $run, string $key): bool
    {
        try {
            $config = json_decode((string)($run['source_config'] ?? ''), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }

        return !empty($config['options'][$key]);
    }

    private function normalizeUpdatedSince(array $input): ?string
    {
        $date = trim((string)($input['updated_since'] ?? ''));
        if ($date !== '') {
            $timestamp = strtotime($date);
            if ($timestamp === false) {
                throw new \InvalidArgumentException('Updated since must be a valid date.');
            }

            return date('Y-m-d 00:00:00', $timestamp);
        }

        $days = (int)($input['updated_since_days'] ?? 0);
        if ($days <= 0) {
            return null;
        }

        $days = min($days, 3650);

        return date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
    }

    private function updatedSince(array $run): ?string
    {
        try {
            $config = json_decode((string)($run['source_config'] ?? ''), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $value = $config['options']['updated_since'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function recentPostClause(array $run): array
    {
        $updatedSince = $this->updatedSince($run);
        if ($updatedSince === null) {
            return ['sql' => '', 'params' => []];
        }

        return [
            'sql' => " AND (post_modified >= ? OR post_modified_gmt >= ? OR post_date >= ?)",
            'params' => [$updatedSince, $updatedSince, $updatedSince],
        ];
    }

    private function recentOrderClause(array $run): array
    {
        $updatedSince = $this->updatedSince($run);
        if ($updatedSince === null) {
            return ['sql' => '', 'params' => []];
        }

        return [
            'sql' => " AND (date_updated_gmt >= ? OR date_created_gmt >= ?)",
            'params' => [$updatedSince, $updatedSince],
        ];
    }

    private function sourceRows(PDO $source, string $sql, array $params): array
    {
        $stmt = $source->prepare($sql);
        foreach ($params as $i => $value) {
            $stmt->bindValue($i + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function selectedStepKeys(array $input): array
    {
        $available = array_map(static fn (array $step): string => (string)$step[0], self::STEPS);
        $selected = (array)($input['selected_steps'] ?? []);
        $selected = array_values(array_unique(array_filter(array_map(static function (mixed $key): string {
            return preg_replace('/[^a-z0-9_]+/', '', (string)$key) ?? '';
        }, $selected))));
        $selected = array_values(array_intersect($available, $selected));
        if ($selected === []) {
            throw new \InvalidArgumentException('Select at least one migration item to run.');
        }

        return $selected;
    }

    private function sourceTableExists(PDO $source, string $table): bool
    {
        $stmt = $source->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->execute([$table]);

        return (int)$stmt->fetchColumn() > 0;
    }

    private function migrateShippingZoneMethods(PDO $source, string $prefix): int
    {
        if (!$this->sourceTableExists($source, $prefix . 'woocommerce_shipping_zone_methods')) {
            return 0;
        }
        $rows = $this->sourceRows(
            $source,
            "SELECT * FROM `{$prefix}woocommerce_shipping_zone_methods` ORDER BY zone_id, instance_id",
            []
        );
        $options = [];
        if ($this->sourceTableExists($source, $prefix . 'options')) {
            $settingsRows = $this->sourceRows(
                $source,
                "SELECT option_name, option_value FROM `{$prefix}options` WHERE option_name LIKE 'woocommerce\\_%\\_%\\_settings'",
                []
            );
            foreach ($settingsRows as $row) {
                $options[(string)$row['option_name']] = $this->decodeWpSettings($row['option_value'] ?? null);
            }
        }

        $count = 0;
        foreach ($rows as $row) {
            $instanceId = (int)($row['instance_id'] ?? 0);
            $methodId = (string)($row['method_id'] ?? 'flat_rate');
            $zoneId = $this->targetIdOrNull('shipping_zones', 'wp_zone_id', (int)($row['zone_id'] ?? 0));
            $settings = $options["woocommerce_{$methodId}_{$instanceId}_settings"] ?? [];
            $name = $this->limitText($settings['title'] ?? ucwords(str_replace('_', ' ', $methodId)), 190) ?? 'Shipping';
            $calculationType = match ($methodId) {
                'free_shipping' => 'free_shipping',
                'local_pickup' => 'local_pickup',
                default => 'flat_rate',
            };
            $cost = $this->money($settings['cost'] ?? ($calculationType === 'free_shipping' ? 0 : 9.99));
            $this->db->query(
                "INSERT INTO shipping_methods (wp_instance_id, zone_id, vendor_id, code, name, calculation_type, base_cost, is_active, settings)
                 VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE zone_id = VALUES(zone_id), vendor_id = NULL, name = VALUES(name), calculation_type = VALUES(calculation_type), base_cost = VALUES(base_cost), is_active = VALUES(is_active), settings = VALUES(settings)",
                [
                    $instanceId,
                    $zoneId,
                    $methodId . '-' . $instanceId,
                    $name,
                    $calculationType,
                    $cost,
                    (int)($row['is_enabled'] ?? 1) === 1 ? 1 : 0,
                    json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]
            );
            $target = $this->targetId('shipping_methods', 'wp_instance_id', $instanceId);
            $this->db->query('DELETE FROM shipping_rates WHERE method_id = ?', [$target]);
            $this->db->query(
                'INSERT INTO shipping_rates (method_id, condition_type, min_value, max_value, cost, per_item_cost, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$target, 'none', null, null, $cost, 0, (int)($row['method_order'] ?? 0)]
            );
            $this->map($prefix . 'woocommerce_shipping_zone_methods', (string)$instanceId, 'shipping_methods', $target);
            $count++;
        }
        return $count;
    }

    private function migrateShippingZoneLocations(PDO $source, string $prefix): int
    {
        if (!$this->sourceTableExists($source, $prefix . 'woocommerce_shipping_zone_locations')) {
            return 0;
        }

        $rows = $this->sourceRows(
            $source,
            "SELECT * FROM `{$prefix}woocommerce_shipping_zone_locations` ORDER BY zone_id, location_id",
            []
        );
        $byZone = [];
        foreach ($rows as $row) {
            $wpZoneId = (int)($row['zone_id'] ?? 0);
            $type = strtolower((string)($row['location_type'] ?? ''));
            if (!in_array($type, ['country', 'state', 'postcode', 'city'], true)) {
                continue;
            }
            $code = $this->limitText($row['location_code'] ?? null, 190);
            if ($wpZoneId <= 0 || $code === null) {
                continue;
            }
            $byZone[$wpZoneId][] = [$type, strtoupper($code)];
        }

        $count = 0;
        foreach ($byZone as $wpZoneId => $locations) {
            $zoneId = $this->targetIdOrNull('shipping_zones', 'wp_zone_id', (int)$wpZoneId);
            if (!$zoneId) {
                continue;
            }
            $this->db->query('DELETE FROM shipping_zone_locations WHERE zone_id = ?', [$zoneId]);
            foreach ($locations as [$type, $code]) {
                $this->db->query(
                    'INSERT INTO shipping_zone_locations (zone_id, location_type, location_code) VALUES (?, ?, ?)',
                    [$zoneId, $type, $code]
                );
                $count++;
            }
        }

        return $count;
    }

    private function decodeWpSettings(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = @unserialize($value, ['allowed_classes' => false]);
        return is_array($decoded) ? $decoded : [];
    }

    private function userMeta(PDO $source, string $prefix, array $ids): array
    {
        return $this->meta($source, $prefix . 'usermeta', 'user_id', $ids);
    }

    private function postMeta(PDO $source, string $prefix, array $ids): array
    {
        return $this->meta($source, $prefix . 'postmeta', 'post_id', $ids);
    }

    private function productShippingClasses(PDO $source, string $prefix, array $productIds): array
    {
        $productIds = array_values(array_filter(array_map('intval', $productIds)));
        if ($productIds === []
            || !$this->sourceTableExists($source, $prefix . 'term_relationships')
            || !$this->sourceTableExists($source, $prefix . 'term_taxonomy')
            || !$this->sourceTableExists($source, $prefix . 'terms')
        ) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $source->prepare(
            "SELECT tr.object_id, COALESCE(NULLIF(t.slug, ''), t.name) AS shipping_class
             FROM `{$prefix}term_relationships` tr
             JOIN `{$prefix}term_taxonomy` tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             JOIN `{$prefix}terms` t ON t.term_id = tt.term_id
             WHERE tr.object_id IN ({$placeholders})
               AND tt.taxonomy = 'product_shipping_class'"
        );
        $stmt->execute($productIds);

        $classes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $classes[(int)$row['object_id']] = (string)$row['shipping_class'];
        }

        return $classes;
    }

    private function postPrimaryCategories(PDO $source, string $prefix, array $postIds): array
    {
        $postIds = array_values(array_filter(array_map('intval', $postIds)));
        if ($postIds === []
            || !$this->sourceTableExists($source, $prefix . 'term_relationships')
            || !$this->sourceTableExists($source, $prefix . 'term_taxonomy')
            || !$this->sourceTableExists($source, $prefix . 'terms')
        ) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $source->prepare(
            "SELECT tr.object_id, t.name
             FROM `{$prefix}term_relationships` tr
             JOIN `{$prefix}term_taxonomy` tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             JOIN `{$prefix}terms` t ON t.term_id = tt.term_id
             WHERE tr.object_id IN ({$placeholders})
               AND tt.taxonomy = 'category'
             ORDER BY tr.object_id ASC, t.name ASC"
        );
        $stmt->execute($postIds);

        $categories = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $postId = (int)$row['object_id'];
            if (!isset($categories[$postId])) {
                $categories[$postId] = (string)$row['name'];
            }
        }

        return $categories;
    }

    private function meta(PDO $source, string $table, string $idColumn, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $source->prepare("SELECT {$idColumn}, meta_key, meta_value FROM `{$table}` WHERE {$idColumn} IN ({$placeholders})");
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int)$row[$idColumn]][$row['meta_key']] = $row['meta_value'];
        }
        return $out;
    }

    private function rolesFromMeta(array $meta): array
    {
        $blob = implode(' ', $meta);
        $roles = ['customer'];
        if (str_contains($blob, 'administrator')) $roles[] = 'admin';
        if (str_contains($blob, 'seller') || str_contains($blob, 'vendor') || str_contains($blob, 'dokan')) $roles[] = 'vendor';
        if (str_contains($blob, 'affiliate')) $roles[] = 'affiliate';
        return array_unique($roles);
    }

    private function dokanProfile(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $profile = @unserialize($value, ['allowed_classes' => false]);

        return is_array($profile) ? $profile : [];
    }

    private function vendorDescription(array $meta): ?string
    {
        $profile = $this->dokanProfile($meta['dokan_profile_settings'] ?? null);
        $candidates = [
            $profile['vendor_biography'] ?? null,
            $profile['store_tnc'] ?? null,
            $meta['description'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $text = $this->cleanLongText($candidate);
            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }

    private function roleId(string $code): int
    {
        $row = $this->db->fetch("SELECT id FROM roles WHERE code = ? LIMIT 1", [$code]);
        if (!$row) throw new \RuntimeException("Missing role: {$code}");
        return (int)$row['id'];
    }

    private function targetId(string $table, string $column, int $value): int
    {
        $row = $this->db->fetch("SELECT id FROM {$table} WHERE {$column} = ? LIMIT 1", [$value]);
        if (!$row) throw new \RuntimeException("Missing mapped {$table}.{$column}: {$value}");
        return (int)$row['id'];
    }

    private function targetIdOrNull(string $table, string $column, int $value): ?int
    {
        $row = $this->db->fetch("SELECT id FROM {$table} WHERE {$column} = ? LIMIT 1", [$value]);
        return $row ? (int)$row['id'] : null;
    }

    private function userIdByWp(int $wpId): ?int
    {
        return $this->targetIdOrNull('users', 'wp_user_id', $wpId)
            ?? $this->mappedTargetId((string)$wpId, 'users');
    }
    private function vendorIdByWp(int $wpId): ?int
    {
        return $this->targetIdOrNull('vendors', 'wp_user_id', $wpId)
            ?? $this->mappedTargetId((string)$wpId, 'vendors');
    }
    private function productIdByWp(int $wpId): ?int
    {
        return $this->targetIdOrNull('products', 'wp_post_id', $wpId)
            ?? $this->mappedTargetId((string)$wpId, 'products');
    }
    private function fileIdByWp(int $wpId): ?int { return $this->targetIdOrNull('files', 'wp_attachment_id', $wpId); }

    private function map(string $sourceTable, string $sourceId, string $targetTable, int $targetId): void
    {
        $this->db->query(
            "INSERT INTO wordpress_import_map (source_table, source_id, target_table, target_id)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE target_id = VALUES(target_id), migrated_at = CURRENT_TIMESTAMP",
            [$sourceTable, $sourceId, $targetTable, $targetId]
        );
    }

    private function mappedTargetId(string $sourceId, string $targetTable): ?int
    {
        $row = $this->db->fetch(
            "SELECT target_id FROM wordpress_import_map WHERE source_id = ? AND target_table = ? ORDER BY migrated_at DESC LIMIT 1",
            [$sourceId, $targetTable]
        );

        return $row ? (int)$row['target_id'] : null;
    }

    private function userIdByLegacyIdentity(string $email, string $username): ?int
    {
        $row = null;
        if ($email !== '') {
            $row = $this->db->fetch("SELECT id FROM users WHERE email = ? LIMIT 1", [$email]);
        }

        if (!$row && $username !== '') {
            $row = $this->db->fetch("SELECT id FROM users WHERE username = ? LIMIT 1", [$username]);
        }

        return $row ? (int)$row['id'] : null;
    }

    private function vendorIdByLegacyIdentity(int $userId, string $slug, ?string $email): ?int
    {
        $row = $this->db->fetch("SELECT id FROM vendors WHERE user_id = ? LIMIT 1", [$userId]);
        if (!$row && $slug !== '') {
            $row = $this->db->fetch("SELECT id FROM vendors WHERE store_slug = ? LIMIT 1", [$slug]);
        }
        if (!$row && $email) {
            $row = $this->db->fetch("SELECT id FROM vendors WHERE store_email = ? LIMIT 1", [$email]);
        }

        return $row ? (int)$row['id'] : null;
    }

    private function productIdByLegacyIdentity(?string $sku, string $slug): ?int
    {
        $row = null;
        if ($sku) {
            $row = $this->db->fetch("SELECT id FROM products WHERE sku = ? LIMIT 1", [$sku]);
        }
        if (!$row) {
            $row = $this->db->fetch("SELECT id FROM products WHERE slug = ? LIMIT 1", [$slug]);
        }

        return $row ? (int)$row['id'] : null;
    }

    private function uniqueProductImportValue(string $column, ?string $value, int $wpId, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $row = $this->db->fetch("SELECT wp_post_id FROM products WHERE {$column} = ? LIMIT 1", [$value]);
        if (!$row || (int)($row['wp_post_id'] ?? 0) === $wpId) {
            return $value;
        }

        $suffix = '-' . $wpId;
        $base = $this->limitText($value, max(1, $maxLength - strlen($suffix))) ?? 'imported';

        return $base . $suffix;
    }

    private function uniqueContentPostSlug(string $value, int $wpId): string
    {
        $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $value), '-')) ?: 'post-' . $wpId;
        $row = $this->db->fetch('SELECT wp_post_id FROM content_posts WHERE slug = ? LIMIT 1', [$slug]);
        if (!$row || (int)($row['wp_post_id'] ?? 0) === $wpId) {
            return $this->limitText($slug, 255) ?? ('post-' . $wpId);
        }

        $suffix = '-' . $wpId;
        $base = $this->limitText($slug, 255 - strlen($suffix)) ?? 'post';
        return $base . $suffix;
    }

    private function migrationPlaceholderPasswordHash(): string
    {
        return password_hash('migration-disabled-' . bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
    }

    private function claimWpUserIdIfAvailable(int $userId, int $wpId): void
    {
        $row = $this->db->fetch("SELECT wp_user_id FROM users WHERE id = ? LIMIT 1", [$userId]);
        if (!$row || $row['wp_user_id'] !== null) {
            return;
        }

        try {
            $this->db->query("UPDATE users SET wp_user_id = ? WHERE id = ? AND wp_user_id IS NULL", [$wpId, $userId]);
        } catch (Throwable) {
            // Duplicate legacy identities are still linked through wordpress_import_map.
        }
    }

    private function claimWpVendorIdIfAvailable(int $vendorId, int $wpId): void
    {
        $row = $this->db->fetch("SELECT wp_user_id FROM vendors WHERE id = ? LIMIT 1", [$vendorId]);
        if (!$row || $row['wp_user_id'] !== null) {
            return;
        }

        try {
            $this->db->query("UPDATE vendors SET wp_user_id = ? WHERE id = ? AND wp_user_id IS NULL", [$wpId, $vendorId]);
        } catch (Throwable) {
            // Duplicate legacy stores are still linked through wordpress_import_map.
        }
    }

    private function claimWpProductIdIfAvailable(int $productId, int $wpId): void
    {
        $row = $this->db->fetch("SELECT wp_post_id FROM products WHERE id = ? LIMIT 1", [$productId]);
        if (!$row || $row['wp_post_id'] !== null) {
            return;
        }

        try {
            $this->db->query("UPDATE products SET wp_post_id = ? WHERE id = ? AND wp_post_id IS NULL", [$wpId, $productId]);
        } catch (Throwable) {
            // Duplicate legacy products are still linked through wordpress_import_map.
        }
    }

    private function productStatus(?string $status): string
    {
        return match ($status) {
            'publish' => 'active',
            'pending' => 'pending',
            'private' => 'private',
            'trash' => 'archived',
            default => 'draft',
        };
    }

    private function orderStatus(?string $status): string
    {
        return match (str_replace('wc-', '', (string)$status)) {
            'processing' => 'processing',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'failed' => 'failed',
            'refunded' => 'refunded',
            'on-hold' => 'on_hold',
            default => 'pending',
        };
    }

    private function paymentStatus(?string $status): string
    {
        return in_array(str_replace('wc-', '', (string)$status), ['completed', 'processing'], true) ? 'paid' : 'unpaid';
    }

    private function money(mixed $value): float { return round((float)($value ?: 0), 4); }
    private function nullableMoney(mixed $value): ?float
    {
        return $value === null || trim((string)$value) === '' ? null : $this->money($value);
    }

    private function productTaxStatus(mixed $value): string
    {
        return in_array((string)$value, ['taxable', 'shipping', 'none'], true) ? (string)$value : 'taxable';
    }

    private function dateOrNow(?string $date): string { return $date && $date !== '0000-00-00 00:00:00' ? $date : sql_now(); }
    private function slug(string $value): string { return trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $value)), '-') ?: 'imported-' . uniqid(); }

    private function limitText(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim(html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '') {
            return null;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') : $text;
        }

        return strlen($text) > $max ? substr($text, 0, $max) : $text;
    }

    private function cleanLongText(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $text = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(strip_tags($text));
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/', "\n\n", $text) ?? $text;
        $text = trim($text);

        return $text === '' ? null : $text;
    }

    private function result(int $lastId, int $count, bool $done, string $message, bool $skipped = false): array
    {
        return ['last_id' => $lastId, 'count' => $count, 'done' => $done, 'message' => $message, 'skipped' => $skipped];
    }

    private function log(int $runId, ?string $stepKey, string $level, string $message, array $context = []): void
    {
        $this->db->query(
            "INSERT INTO migration_logs (run_id, step_key, level, message, context) VALUES (?, ?, ?, ?, ?)",
            [$runId, $stepKey, $level, $message, $context ? json_encode($context, JSON_UNESCAPED_SLASHES) : null]
        );
    }

    private function ensureTables(): void
    {
        $this->db->pdo()->exec(<<<SQL
CREATE TABLE IF NOT EXISTS migration_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_type ENUM('source_database', 'sql_dump') NOT NULL DEFAULT 'source_database',
    source_label VARCHAR(190) NULL,
    source_path VARCHAR(500) NULL,
    source_config JSON NULL,
    table_prefix VARCHAR(40) NOT NULL DEFAULT 'hqgs_',
    status ENUM('draft', 'running', 'paused', 'completed', 'failed') NOT NULL DEFAULT 'draft',
    current_step VARCHAR(80) NULL,
    batch_size INT UNSIGNED NOT NULL DEFAULT 100,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_migration_runs_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migration_steps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id BIGINT UNSIGNED NOT NULL,
    step_key VARCHAR(80) NOT NULL,
    label VARCHAR(190) NOT NULL,
    source_table VARCHAR(190) NULL,
    status ENUM('pending', 'running', 'completed', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
    last_source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    processed_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    failed_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    message TEXT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_migration_steps_run_step (run_id, step_key),
    INDEX idx_migration_steps_run_status (run_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migration_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id BIGINT UNSIGNED NOT NULL,
    step_key VARCHAR(80) NULL,
    level ENUM('info', 'warning', 'error') NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    context JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_migration_logs_run_date (run_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    private function ensureProductMeasurementCapacity(): void
    {
        try {
            $column = $this->db->fetch(
                "SELECT NUMERIC_PRECISION AS numeric_precision
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'products'
                   AND COLUMN_NAME = 'weight'"
            );
            if (!$column || (int)($column['numeric_precision'] ?? 0) >= 19) {
                return;
            }

            $this->db->pdo()->exec(
                "ALTER TABLE products
                 MODIFY weight DECIMAL(19, 4) NULL,
                 MODIFY length DECIMAL(19, 4) NULL,
                 MODIFY width DECIMAL(19, 4) NULL,
                 MODIFY height DECIMAL(19, 4) NULL"
            );
        } catch (Throwable $e) {
            error_log('Product measurement schema could not be widened: ' . $e->getMessage());
        }
    }
}
