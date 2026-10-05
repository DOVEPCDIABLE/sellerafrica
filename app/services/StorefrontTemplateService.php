<?php

declare(strict_types=1);

namespace App;

final class StorefrontTemplateService
{
    public function __construct(private Database $db)
    {
    }

    public function products(int $limit = 80, string $query = '', string $category = '', array $filters = [], int $offset = 0): array
    {
        $conditions = $this->publicProductConditions('p');
        $params = [];
        $this->applySectionConditions($conditions, $filters);
        $brand = trim((string)($filters['brand'] ?? ''));
        $rating = max(0, min(5, (int)($filters['rating'] ?? 0)));
        $minPrice = isset($filters['min_price']) && $filters['min_price'] !== '' ? max(0, (float)$filters['min_price']) : null;
        $maxPrice = isset($filters['max_price']) && $filters['max_price'] !== '' ? max(0, (float)$filters['max_price']) : null;
        $sort = (string)($filters['sort'] ?? 'default');
        $excludeIds = array_values(array_unique(array_filter(array_map('intval', (array)($filters['exclude_ids'] ?? [])), static fn (int $id): bool => $id > 0)));
        $priceExpression = "(CASE WHEN p.sale_price IS NOT NULL AND p.sale_price > 0 THEN p.sale_price ELSE p.regular_price END)";
        $subscriberSelect = '0 AS subscriber_rank';
        $subscriberJoin = '';
        if (\table_exists('vendor_subscriptions') && \table_exists('vendor_packages')) {
            $graceDays = max(0, min(90, (int)\app_setting('subscriptions', 'grace_days', 7)));
            $subscriberSelect = 'COALESCE(vendor_subscription_priority.subscriber_rank, 0) AS subscriber_rank';
            $subscriberJoin = "
             LEFT JOIN (
                SELECT
                    ranked.vendor_id,
                    MAX(ranked.subscriber_rank) AS subscriber_rank
                FROM (
                    SELECT
                        vs.vendor_id,
                        CASE
                            WHEN vp.price > 0 AND vs.status IN ('active', 'trialing') THEN 3
                            WHEN vp.price > 0 AND vs.status IN ('past_due', 'unpaid') AND TIMESTAMPDIFF(DAY, COALESCE(vs.current_period_end, vs.updated_at, vs.started_at, vs.created_at), NOW()) <= {$graceDays} THEN 2
                            WHEN vs.status IN ('active', 'trialing') THEN 1
                            ELSE 0
                        END AS subscriber_rank
                    FROM vendor_subscriptions vs
                    INNER JOIN vendor_packages vp ON vp.id = vs.package_id
                    WHERE vp.is_active = 1
                    " . (\table_exists('vendor_visibility_boosts') ? "
                    UNION ALL
                    SELECT
                        vb.vendor_id,
                        CASE
                            WHEN vb.status IN ('active', 'trialing') THEN 4
                            WHEN vb.status IN ('past_due', 'unpaid') THEN 2
                            ELSE 0
                        END AS subscriber_rank
                    FROM vendor_visibility_boosts vb
                    " : '') . "
                ) ranked
                GROUP BY ranked.vendor_id
             ) vendor_subscription_priority ON vendor_subscription_priority.vendor_id = p.vendor_id";
        }

        if ($query !== '') {
            $conditions[] = "(p.name LIKE ? OR p.short_description LIKE ? OR p.description LIKE ? OR p.sku LIKE ? OR v.store_name LIKE ? OR b.name LIKE ?)";
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        if ($category !== '') {
            $conditions[] = "EXISTS (
                SELECT 1 FROM product_categories pc_filter
                INNER JOIN categories c_filter ON c_filter.id = pc_filter.category_id
                WHERE pc_filter.product_id = p.id AND c_filter.is_active = 1 AND c_filter.slug = ?
            )";
            $params[] = $category;
        }

        if ($brand !== '') {
            $conditions[] = "COALESCE(NULLIF(b.slug, ''), v.store_slug) = ?";
            $params[] = $brand;
        }

        if ($rating > 0) {
            $conditions[] = "p.average_rating >= ?";
            $params[] = $rating;
        }

        if ($minPrice !== null) {
            $conditions[] = "{$priceExpression} >= ?";
            $params[] = $minPrice;
        }

        if ($maxPrice !== null && $maxPrice > 0) {
            $conditions[] = "{$priceExpression} <= ?";
            $params[] = $maxPrice;
        }

        if ($excludeIds !== []) {
            $conditions[] = 'p.id NOT IN (' . implode(',', array_fill(0, count($excludeIds), '?')) . ')';
            array_push($params, ...$excludeIds);
        }

        $categorySelect = "
                (
                    SELECT c_pick.name
                    FROM product_categories pc_pick
                    INNER JOIN categories c_pick ON c_pick.id = pc_pick.category_id
                    WHERE pc_pick.product_id = p.id AND c_pick.is_active = 1
                    ORDER BY c_pick.name ASC
                    LIMIT 1
                ) AS primary_category_name,
                (
                    SELECT c_pick.slug
                    FROM product_categories pc_pick
                    INNER JOIN categories c_pick ON c_pick.id = pc_pick.category_id
                    WHERE pc_pick.product_id = p.id AND c_pick.is_active = 1
                    ORDER BY c_pick.name ASC
                    LIMIT 1
                ) AS primary_category_slug,";
        $categoryOrder = $category === '' ? 'COALESCE(primary_category_name, "Uncategorized") ASC, ' : '';
        $weeklyDiscoveryOrder = 'subscriber_rank DESC, '
            . self::weeklyProductRotationSql('p.id')
            . " ASC, p.featured DESC, {$categoryOrder}p.total_sales DESC, p.published_at DESC, p.id DESC";

        $orderBy = match ($sort) {
            'price_asc' => "{$priceExpression} ASC, p.id DESC",
            'price_desc' => "{$priceExpression} DESC, p.id DESC",
            'newest' => 'p.published_at DESC, p.id DESC',
            'on_sale' => 'subscriber_rank DESC, p.total_sales DESC, p.id DESC',
            'top_selling' => 'p.total_sales DESC, subscriber_rank DESC, p.id DESC',
            'weekly' => $weeklyDiscoveryOrder,
            'random' => 'RAND()',
            default => $weeklyDiscoveryOrder,
        };

        $rows = $this->db->fetchAll(
            "SELECT
                p.id, p.name, p.slug, p.regular_price, p.sale_price, p.currency, p.stock_status,
                p.average_rating, p.review_count, p.total_sales, p.short_description, p.description,
                p.sku, p.weight, p.length, p.width, p.height, p.shipping_class, p.brand_id, p.vendor_id, p.ships_from_us_warehouse, v.store_name, v.store_slug, b.name AS brand_name, f.path AS image_path,
                {$categorySelect}
                {$subscriberSelect}
             FROM products p
             LEFT JOIN vendors v ON v.id = p.vendor_id
             LEFT JOIN brands b ON b.id = p.brand_id
             LEFT JOIN product_media pm ON pm.id = (
                SELECT pm2.id FROM product_media pm2
                WHERE pm2.product_id = p.id
                ORDER BY FIELD(pm2.role, 'primary', 'gallery'), pm2.sort_order ASC, pm2.id ASC
                LIMIT 1
             )
             LEFT JOIN files f ON f.id = pm.file_id
             {$subscriberJoin}
             WHERE " . implode(' AND ', $conditions) . "
             ORDER BY {$orderBy}
             LIMIT " . max(1, min(200, $limit)) . "
             OFFSET " . max(0, $offset),
            $params
        );

        return array_map(fn (array $row): array => $this->shapeProduct($row), $rows);
    }

    public function productCount(string $query = '', string $category = '', array $filters = []): int
    {
        $conditions = $this->publicProductConditions('p');
        $params = [];
        $this->applySectionConditions($conditions, $filters);
        $brand = trim((string)($filters['brand'] ?? ''));
        $rating = max(0, min(5, (int)($filters['rating'] ?? 0)));
        $minPrice = isset($filters['min_price']) && $filters['min_price'] !== '' ? max(0, (float)$filters['min_price']) : null;
        $maxPrice = isset($filters['max_price']) && $filters['max_price'] !== '' ? max(0, (float)$filters['max_price']) : null;
        $priceExpression = "(CASE WHEN p.sale_price IS NOT NULL AND p.sale_price > 0 THEN p.sale_price ELSE p.regular_price END)";

        if ($query !== '') {
            $conditions[] = "(p.name LIKE ? OR p.short_description LIKE ? OR p.description LIKE ? OR p.sku LIKE ? OR v.store_name LIKE ? OR b.name LIKE ?)";
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        if ($category !== '') {
            $conditions[] = "EXISTS (
                SELECT 1 FROM product_categories pc_filter
                INNER JOIN categories c_filter ON c_filter.id = pc_filter.category_id
                WHERE pc_filter.product_id = p.id AND c_filter.is_active = 1 AND c_filter.slug = ?
            )";
            $params[] = $category;
        }

        if ($brand !== '') {
            $conditions[] = "COALESCE(NULLIF(b.slug, ''), v.store_slug) = ?";
            $params[] = $brand;
        }

        if ($rating > 0) {
            $conditions[] = "p.average_rating >= ?";
            $params[] = $rating;
        }

        if ($minPrice !== null) {
            $conditions[] = "{$priceExpression} >= ?";
            $params[] = $minPrice;
        }

        if ($maxPrice !== null && $maxPrice > 0) {
            $conditions[] = "{$priceExpression} <= ?";
            $params[] = $maxPrice;
        }

        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total
             FROM products p
             LEFT JOIN vendors v ON v.id = p.vendor_id
             LEFT JOIN brands b ON b.id = p.brand_id
             WHERE " . implode(' AND ', $conditions),
            $params
        );

        return (int)($row['total'] ?? 0);
    }

    private function applySectionConditions(array &$conditions, array $filters): void
    {
        $section = (string)($filters['section'] ?? '');
        if (($filters['sort'] ?? '') === 'on_sale' || in_array($section, ['On Sale', 'Sales & Deals', 'Deals and Savings', 'Hot Deals', 'Special Offers'], true)) {
            $conditions[] = 'p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.regular_price';
        }
        if ($section === 'Best Sellers' || ($filters['best_sellers_only'] ?? false)) {
            $conditions[] = 'p.total_sales > 0';
        }
    }

    public function brands(int $limit = 80, string $query = '', string $category = ''): array
    {
        $conditions = array_merge($this->publicProductConditions('p'), ["COALESCE(NULLIF(b.slug, ''), v.store_slug) IS NOT NULL", "COALESCE(NULLIF(b.slug, ''), v.store_slug) <> ''"]);
        $params = [];

        if ($query !== '') {
            $conditions[] = "(p.name LIKE ? OR p.short_description LIKE ? OR p.description LIKE ? OR p.sku LIKE ? OR v.store_name LIKE ? OR b.name LIKE ?)";
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        if ($category !== '') {
            $conditions[] = "EXISTS (
                SELECT 1 FROM product_categories pc_filter
                INNER JOIN categories c_filter ON c_filter.id = pc_filter.category_id
                WHERE pc_filter.product_id = p.id AND c_filter.slug = ?
            )";
            $params[] = $category;
        }

        $rows = $this->db->fetchAll(
            "SELECT
                COALESCE(NULLIF(b.name, ''), v.store_name) AS name,
                COALESCE(NULLIF(b.slug, ''), v.store_slug) AS slug,
                COUNT(p.id) AS product_count
             FROM products p
             LEFT JOIN brands b ON b.id = p.brand_id
             LEFT JOIN vendors v ON v.id = p.vendor_id
             WHERE NOT EXISTS (SELECT 1 FROM product_attribute_assignments va JOIN product_attributes vt ON vt.id=va.attribute_id WHERE va.product_id=p.id AND vt.slug='sac-variation-options') AND " . implode(' AND ', $conditions) . "
             GROUP BY COALESCE(NULLIF(b.slug, ''), v.store_slug), COALESCE(NULLIF(b.name, ''), v.store_name)
             HAVING product_count > 0
             ORDER BY product_count DESC, name ASC
             LIMIT " . max(1, min(120, $limit)),
            $params
        );

        return array_map(static fn (array $row): array => [
            'name' => html_entity_decode((string)$row['name'], ENT_QUOTES, 'UTF-8'),
            'slug' => (string)$row['slug'],
            'product_count' => (int)$row['product_count'],
            'url' => app_url('shop?brand=' . urlencode((string)$row['slug'])),
        ], $rows);
    }

    public function ratingFacets(string $query = '', string $category = '', string $brand = ''): array
    {
        $conditions = $this->publicProductConditions('p');
        $params = [];

        if ($query !== '') {
            $conditions[] = "(p.name LIKE ? OR p.short_description LIKE ? OR p.description LIKE ? OR p.sku LIKE ? OR v.store_name LIKE ? OR b.name LIKE ?)";
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        if ($category !== '') {
            $conditions[] = "EXISTS (
                SELECT 1 FROM product_categories pc_filter
                INNER JOIN categories c_filter ON c_filter.id = pc_filter.category_id
                WHERE pc_filter.product_id = p.id AND c_filter.slug = ?
            )";
            $params[] = $category;
        }

        if ($brand !== '') {
            $conditions[] = "COALESCE(NULLIF(b.slug, ''), v.store_slug) = ?";
            $params[] = $brand;
        }

        $row = $this->db->fetch(
            "SELECT
                SUM(CASE WHEN p.average_rating >= 5 THEN 1 ELSE 0 END) AS rating_5,
                SUM(CASE WHEN p.average_rating >= 4 THEN 1 ELSE 0 END) AS rating_4,
                SUM(CASE WHEN p.average_rating >= 3 THEN 1 ELSE 0 END) AS rating_3,
                SUM(CASE WHEN p.average_rating >= 2 THEN 1 ELSE 0 END) AS rating_2,
                SUM(CASE WHEN p.average_rating >= 1 THEN 1 ELSE 0 END) AS rating_1
             FROM products p
             LEFT JOIN vendors v ON v.id = p.vendor_id
             LEFT JOIN brands b ON b.id = p.brand_id
             WHERE " . implode(' AND ', $conditions),
            $params
        ) ?: [];

        $facets = [];
        for ($rating = 5; $rating >= 1; $rating--) {
            $facets[] = [
                'rating' => $rating,
                'label' => $rating . '+ stars',
                'product_count' => (int)($row['rating_' . $rating] ?? 0),
            ];
        }

        return $facets;
    }

    public function productBySlug(string $slug): ?array
    {
        $row = $this->db->fetch(
            "SELECT
                p.*, v.store_name, v.store_slug, b.name AS brand_name, f.path AS image_path,
                (
                    SELECT GROUP_CONCAT(c_all.name ORDER BY c_all.name ASC SEPARATOR ', ')
                    FROM product_categories pc_all
                    INNER JOIN categories c_all ON c_all.id = pc_all.category_id
                    WHERE pc_all.product_id = p.id AND c_all.is_active = 1
                ) AS category_names,
                (
                    SELECT c_pick.name
                    FROM product_categories pc_pick
                    INNER JOIN categories c_pick ON c_pick.id = pc_pick.category_id
                    WHERE pc_pick.product_id = p.id AND c_pick.is_active = 1
                    ORDER BY c_pick.name ASC
                    LIMIT 1
                ) AS primary_category_name,
                (
                    SELECT c_pick.slug
                    FROM product_categories pc_pick
                    INNER JOIN categories c_pick ON c_pick.id = pc_pick.category_id
                    WHERE pc_pick.product_id = p.id AND c_pick.is_active = 1
                    ORDER BY c_pick.name ASC
                    LIMIT 1
                ) AS primary_category_slug
             FROM products p
             LEFT JOIN vendors v ON v.id = p.vendor_id
             LEFT JOIN brands b ON b.id = p.brand_id
             LEFT JOIN product_media pm ON pm.id = (
                SELECT pm2.id FROM product_media pm2
                WHERE pm2.product_id = p.id
                ORDER BY FIELD(pm2.role, 'primary', 'gallery'), pm2.sort_order ASC, pm2.id ASC
                LIMIT 1
             )
             LEFT JOIN files f ON f.id = pm.file_id
             WHERE p.slug = ? AND p.status = 'active' AND NOT EXISTS (SELECT 1 FROM product_attribute_assignments va JOIN product_attributes vt ON vt.id=va.attribute_id WHERE va.product_id=p.id AND vt.slug='sac-variation-options')
             LIMIT 1",
            [$slug]
        );

        if (!$row) {
            return null;
        }

        $product = $this->shapeProduct($row);
        $product['description'] = trim(strip_tags(html_entity_decode((string)($row['description'] ?: $row['short_description'] ?: ''), ENT_QUOTES, 'UTF-8')));
        $product['sku'] = (string)($row['sku'] ?? '');
        $product['brand'] = html_entity_decode((string)($row['brand_name'] ?: $row['store_name'] ?: 'Seller Africa'), ENT_QUOTES, 'UTF-8');
        $product['weight'] = $row['weight'] !== null ? (string)$row['weight'] : '';
        $product['length'] = $row['length'] !== null ? (string)$row['length'] : '';
        $product['width'] = $row['width'] !== null ? (string)$row['width'] : '';
        $product['height'] = $row['height'] !== null ? (string)$row['height'] : '';
        $product['shippingClass'] = (string)($row['shipping_class'] ?? '');
        $product['reviewCount'] = (int)($row['review_count'] ?? 0);
        $product['media'] = $this->mediaForProduct((int)$row['id']);
        $product['categories'] = array_values(array_filter(array_map('trim', explode(',', (string)($row['category_names'] ?? '')))));

        return $product;
    }

    public function productsByVendor(int $vendorId, int $limit = 5, int $excludeProductId = 0): array
    {
        $limit = max(1, min(5, $limit));
        $conditions = $this->publicProductConditions('p');
        $conditions[] = 'p.vendor_id = ?';
        $params = [$vendorId];
        if ($excludeProductId > 0) {
            $conditions[] = 'p.id <> ?';
            $params[] = $excludeProductId;
        }

        $rows = $this->db->fetchAll(
            "SELECT p.id, p.name, p.slug, p.regular_price, p.sale_price, p.currency, p.stock_status,
                    p.average_rating, p.review_count, p.total_sales, p.short_description, p.description,
                    p.sku, p.weight, p.length, p.width, p.height, p.shipping_class, p.brand_id, p.vendor_id,
                    p.ships_from_us_warehouse, v.store_name, v.store_slug, b.name AS brand_name, f.path AS image_path,
                    (
                        SELECT c_pick.name
                        FROM product_categories pc_pick
                        INNER JOIN categories c_pick ON c_pick.id = pc_pick.category_id
                        WHERE pc_pick.product_id = p.id AND c_pick.is_active = 1
                        ORDER BY c_pick.name ASC
                        LIMIT 1
                    ) AS primary_category_name,
                    (
                        SELECT c_pick.slug
                        FROM product_categories pc_pick
                        INNER JOIN categories c_pick ON c_pick.id = pc_pick.category_id
                        WHERE pc_pick.product_id = p.id AND c_pick.is_active = 1
                        ORDER BY c_pick.name ASC
                        LIMIT 1
                    ) AS primary_category_slug,
                    0 AS subscriber_rank
             FROM products p
             LEFT JOIN vendors v ON v.id = p.vendor_id
             LEFT JOIN brands b ON b.id = p.brand_id
             LEFT JOIN product_media pm ON pm.id = (
                SELECT pm2.id FROM product_media pm2
                WHERE pm2.product_id = p.id
                ORDER BY FIELD(pm2.role, 'primary', 'gallery'), pm2.sort_order ASC, pm2.id ASC
                LIMIT 1
             )
             LEFT JOIN files f ON f.id = pm.file_id
             WHERE " . implode(' AND ', $conditions) . "
             ORDER BY p.featured DESC, p.total_sales DESC, p.updated_at DESC, p.id DESC
             LIMIT {$limit}",
            $params
        );

        return array_map(fn (array $row): array => $this->shapeProduct($row), $rows);
    }

    public function spotlightVendors(int $limit = 4): array
    {
        $limit = max(1, min(6, $limit));
        $rows = $this->db->fetchAll(
            "SELECT v.id, v.store_name, v.store_slug, v.description,
                    lf.path AS logo_path, bf.path AS banner_path,
                    (
                        SELECT f.path
                        FROM files f
                        WHERE f.owner_user_id = v.user_id
                          AND f.mime_type LIKE 'image/%'
                        ORDER BY
                          CASE
                            WHEN LOWER(CONCAT(f.path, ' ', f.original_name)) REGEXP 'banner|cover|header|store' THEN 0
                            WHEN COALESCE(f.width, 0) > COALESCE(f.height, 0) * 1.6 THEN 1
                            ELSE 2
                          END,
                          f.id DESC
                        LIMIT 1
                    ) AS fallback_banner_path,
                    COUNT(DISTINCT p.id) AS product_count,
                    AVG(NULLIF(p.average_rating, 0)) AS average_rating
             FROM vendors v
             LEFT JOIN files lf ON lf.id = v.logo_file_id
             LEFT JOIN files bf ON bf.id = v.banner_file_id
             INNER JOIN products p ON p.vendor_id = v.id AND p.status = 'active' AND NOT EXISTS (SELECT 1 FROM product_attribute_assignments va JOIN product_attributes vt ON vt.id=va.attribute_id WHERE va.product_id=p.id AND vt.slug='sac-variation-options')
             WHERE v.status = 'active'
               AND LOWER(COALESCE(v.store_email, '')) <> 'contact@sellerafrica.com'
               AND LOWER(REPLACE(TRIM(COALESCE(v.store_name, '')), ' ', '')) <> 'sellerafrica'
               AND LOWER(COALESCE(v.store_slug, '')) NOT IN ('seller-africa', 'seller_africa', 'sellerafrica', 'seller-mall', 'seller_mall', 'sellermall')
             GROUP BY v.id, v.user_id, v.store_name, v.store_slug, v.description, lf.path, bf.path
             ORDER BY product_count DESC, average_rating DESC, v.updated_at DESC
             LIMIT {$limit}"
        );

        return array_map(static function (array $row): array {
            return [
                'id' => (int)$row['id'],
                'name' => html_entity_decode((string)$row['store_name'], ENT_QUOTES, 'UTF-8'),
                'url' => app_url('vendors/' . rawurlencode((string)$row['store_slug'])),
                'logo' => self::asset($row['logo_path'] ?? null),
                'banner' => self::asset(($row['banner_path'] ?? null) ?: ($row['fallback_banner_path'] ?? null)),
                'description' => trim(strip_tags(html_entity_decode((string)($row['description'] ?? ''), ENT_QUOTES, 'UTF-8'))),
                'productCount' => (int)$row['product_count'],
                'rating' => round((float)($row['average_rating'] ?? 0), 1),
            ];
        }, $rows);
    }

    public function blogPosts(int $limit = 3): array
    {
        if (!\table_exists('content_posts')) {
            return [];
        }

        $rows = $this->db->fetchAll(
            "SELECT title, slug, excerpt, featured_image_url, category, published_at
             FROM content_posts
             WHERE status = 'published'
             ORDER BY COALESCE(published_at, created_at) DESC, id DESC
             LIMIT " . max(1, min(6, $limit))
        );

        return array_map(static function (array $row): array {
            return [
                'title' => html_entity_decode((string)$row['title'], ENT_QUOTES, 'UTF-8'),
                'url' => app_url('blog-news#' . rawurlencode((string)$row['slug'])),
                'excerpt' => trim(strip_tags(html_entity_decode((string)($row['excerpt'] ?? ''), ENT_QUOTES, 'UTF-8'))),
                'image' => self::asset($row['featured_image_url'] ?? null),
                'category' => (string)($row['category'] ?? 'News'),
                'date' => (string)($row['published_at'] ?? ''),
            ];
        }, $rows);
    }

    public function ensureReviewMediaSchema(): void
    {
        if (!\table_exists('reviews')) {
            $this->db->pdo()->exec("
                CREATE TABLE IF NOT EXISTS reviews (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    product_id BIGINT UNSIGNED NOT NULL,
                    user_id BIGINT UNSIGNED NULL,
                    order_id BIGINT UNSIGNED NULL,
                    rating TINYINT UNSIGNED NOT NULL,
                    title VARCHAR(190) NULL,
                    body TEXT NULL,
                    guest_name VARCHAR(190) NULL,
                    guest_email VARCHAR(190) NULL,
                    ip_hash CHAR(64) NULL,
                    user_agent_hash CHAR(64) NULL,
                    status ENUM('pending', 'approved', 'rejected', 'spam') NOT NULL DEFAULT 'pending',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                    CONSTRAINT fk_reviews_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
                    CHECK (rating BETWEEN 1 AND 5),
                    INDEX idx_reviews_product_status (product_id, status),
                    INDEX idx_reviews_guest_email (product_id, guest_email),
                    INDEX idx_reviews_ip_hash (product_id, ip_hash)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->ensureReviewsUtf8mb4();
        }

        foreach ([
            'guest_name' => "ALTER TABLE reviews ADD COLUMN guest_name VARCHAR(190) NULL AFTER body",
            'guest_email' => "ALTER TABLE reviews ADD COLUMN guest_email VARCHAR(190) NULL AFTER guest_name",
            'ip_hash' => "ALTER TABLE reviews ADD COLUMN ip_hash CHAR(64) NULL AFTER guest_email",
            'user_agent_hash' => "ALTER TABLE reviews ADD COLUMN user_agent_hash CHAR(64) NULL AFTER ip_hash",
        ] as $column => $sql) {
            if (!$this->columnExists('reviews', $column)) {
                $this->db->pdo()->exec($sql);
            }
        }

        $this->db->pdo()->exec("
            CREATE TABLE IF NOT EXISTS review_media (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                review_id BIGINT UNSIGNED NOT NULL,
                file_id BIGINT UNSIGNED NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_review_media_review FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
                CONSTRAINT fk_review_media_file FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
                INDEX idx_review_media_review (review_id, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->pdo()->exec("
            CREATE TABLE IF NOT EXISTS review_rate_limits (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id BIGINT UNSIGNED NOT NULL,
                identifier_hash CHAR(64) NOT NULL,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                window_start DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_review_rate_product_identifier (product_id, identifier_hash),
                INDEX idx_review_rate_window (window_start)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function ensureReviewsUtf8mb4(): void
    {
        try {
            $this->db->pdo()->exec('ALTER TABLE reviews CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (\Throwable $e) {
            error_log('Reviews table charset conversion skipped: ' . $e->getMessage());
        }

        foreach ([
            'title' => 'ALTER TABLE reviews MODIFY title VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
            'body' => 'ALTER TABLE reviews MODIFY body TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
            'guest_name' => 'ALTER TABLE reviews MODIFY guest_name VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
            'guest_email' => 'ALTER TABLE reviews MODIFY guest_email VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
        ] as $column => $sql) {
            if ($this->columnExists('reviews', $column)) {
                try {
                    $this->db->pdo()->exec($sql);
                } catch (\Throwable $e) {
                    error_log('Reviews column charset conversion skipped for ' . $column . ': ' . $e->getMessage());
                }
            }
        }
    }

    public function productReviews(int $productId, int $limit = 20): array
    {
        $this->ensureReviewMediaSchema();
        $rows = $this->db->fetchAll(
            "SELECT r.id, r.rating, r.title, r.body, r.created_at,
                    COALESCE(NULLIF(u.display_name, ''), NULLIF(r.guest_name, ''), 'Customer') AS author_name,
                    GROUP_CONCAT(f.path ORDER BY rm.sort_order ASC, rm.id ASC SEPARATOR '||') AS media_paths
             FROM reviews r
             LEFT JOIN users u ON u.id = r.user_id
             LEFT JOIN review_media rm ON rm.review_id = r.id
             LEFT JOIN files f ON f.id = rm.file_id
             WHERE r.product_id = ? AND r.status = 'approved'
             GROUP BY r.id, r.rating, r.title, r.body, r.created_at, author_name
             ORDER BY r.created_at DESC
             LIMIT " . max(1, min(100, $limit)),
            [$productId]
        );

        return array_map(static function (array $row): array {
            $paths = array_filter(explode('||', (string)($row['media_paths'] ?? '')));
            return [
                'id' => (int)$row['id'],
                'rating' => (int)$row['rating'],
                'title' => html_entity_decode((string)($row['title'] ?: 'Customer review'), ENT_QUOTES, 'UTF-8'),
                'body' => html_entity_decode((string)($row['body'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'author' => html_entity_decode((string)($row['author_name'] ?: 'Customer'), ENT_QUOTES, 'UTF-8'),
                'created_at' => (string)$row['created_at'],
                'images' => array_map(static fn (string $path): string => self::asset($path), $paths),
            ];
        }, $rows);
    }

    public function reviewEligibility(int $productId, ?int $userId): array
    {
        $this->ensureReviewMediaSchema();

        if (!$userId) {
            return ['canReview' => true, 'message' => 'Share your experience with this product.'];
        }

        $order = $this->db->fetch(
            "SELECT o.id
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.customer_id = ? AND oi.product_id = ? AND o.payment_status = 'paid'
             ORDER BY o.created_at DESC
             LIMIT 1",
            [$userId, $productId]
        );

        $existing = $this->db->fetch(
            'SELECT id, status FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1',
            [$productId, $userId]
        );
        if ($existing) {
            return ['canReview' => false, 'message' => 'Your review for this product has already been submitted.'];
        }

        return [
            'canReview' => true,
            'message' => $order ? 'Verified purchase' : 'Share your experience with this product.',
            'orderId' => $order ? (int)$order['id'] : null,
        ];
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $config = \db_config();
            $row = $this->db->fetch(
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

    public function marketplaceStats(): array
    {
        $vendorCount = (int)($this->db->fetch("SELECT COUNT(*) AS c FROM vendors WHERE status = 'active'")['c'] ?? 0);
        $productCount = (int)($this->db->fetch("SELECT COUNT(*) AS c FROM products WHERE status = 'active'")['c'] ?? 0);
        $categoryCount = (int)($this->db->fetch("SELECT COUNT(*) AS c FROM categories WHERE is_active = 1")['c'] ?? 0);
        $orderCount = (int)($this->db->fetch("SELECT COUNT(*) AS c FROM orders WHERE payment_status = 'paid'")['c'] ?? 0);

        return [
            'vendors' => $vendorCount,
            'products' => $productCount,
            'categories' => $categoryCount,
            'orders' => $orderCount,
        ];
    }

    public function categories(int $limit = 30): array
    {
        $conditions = $this->publicProductConditions('p');
        $conditions[] = "c.is_active = 1 AND c.slug NOT IN ('uncategorized', 'subscription', 'sac-marketplace-subscription-plans')";
        $rows = $this->db->fetchAll(
            "SELECT c.name, c.slug, COUNT(pc.product_id) AS product_count
             FROM categories c
             INNER JOIN product_categories pc ON pc.category_id = c.id
             INNER JOIN products p ON p.id = pc.product_id AND p.status = 'active'
             LEFT JOIN vendors v ON v.id = p.vendor_id
             WHERE " . implode(' AND ', $conditions) . "
             GROUP BY c.id, c.name, c.slug
             ORDER BY product_count DESC, c.name ASC
             LIMIT " . max(1, min(80, $limit))
        );

        return array_map(static function (array $row, int $index): array {
            return [
                'name' => html_entity_decode((string)$row['name'], ENT_QUOTES, 'UTF-8'),
                'slug' => (string)$row['slug'],
                'product_count' => (int)$row['product_count'],
                'url' => app_url('shop?category=' . urlencode((string)$row['slug'])),
                'image' => app_url('template/assets/img/category/cat-1-' . (($index % 10) + 1) . '.jpg'),
            ];
        }, $rows, array_keys($rows));
    }

    public function cartProducts(array $cartItems): array
    {
        if ($cartItems === []) {
            return [];
        }

        $ids = array_keys($cartItems);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->fetchAll(
            "SELECT p.*, v.store_name, f.path AS image_path,
                    (SELECT parent.slug FROM products parent WHERE parent.id=p.parent_product_id) AS parent_slug
             FROM products p
             LEFT JOIN vendors v ON v.id = p.vendor_id
             LEFT JOIN product_media pm ON pm.id = (
                SELECT pm2.id FROM product_media pm2
                WHERE pm2.product_id = p.id
                ORDER BY FIELD(pm2.role, 'primary', 'gallery'), pm2.sort_order ASC, pm2.id ASC
                LIMIT 1
             )
             LEFT JOIN files f ON f.id = pm.file_id
             WHERE p.id IN ({$placeholders})
             ORDER BY p.name ASC",
            $ids
        );

        $products = [];
        foreach ($rows as $row) {
            $product = $this->shapeProduct($row);
            $product['quantity'] = (int)($cartItems[(int)$row['id']] ?? 1);
            $product['lineTotal'] = self::money((float)($row['sale_price'] ?: $row['regular_price']) * $product['quantity'], (string)$row['currency']);
            $products[] = $product;
        }

        return $products;
    }

    public function currencies(): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT code, name, symbol, exchange_rate, is_default, decimal_places
                 FROM currencies
                 WHERE is_enabled = 1
                 ORDER BY is_default DESC, code ASC"
            );
        } catch (\Throwable) {
            $rows = [];
        }

        if ($rows === []) {
            $rows = [
                ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'decimal_places' => 2],
            ];
        }

        return array_map(static fn (array $row): array => [
            'code' => strtoupper((string)$row['code']),
            'name' => html_entity_decode((string)$row['name'], ENT_QUOTES, 'UTF-8'),
            'symbol' => (string)$row['symbol'],
            'exchangeRate' => (float)($row['exchange_rate'] ?? 1),
            'isDefault' => (int)($row['is_default'] ?? 0) === 1,
            'decimalPlaces' => (int)($row['decimal_places'] ?? 2),
        ], $rows);
    }

    public function paymentMethods(): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT id, code, name, provider, settings
                 FROM payment_methods
                 WHERE is_active = 1
                   AND provider <> 'manual'
                   AND LOWER(code) NOT IN ('cod', 'cash_on_delivery')
                   AND LOWER(name) NOT LIKE '%cash on delivery%'
                 ORDER BY FIELD(code, 'paystack', 'klasha', 'stripe', 'paypal', 'wallet'), name ASC"
            );
        } catch (\Throwable) {
            $rows = [];
        }

        return array_map(static fn (array $row): array => [
            'id' => (int)$row['id'],
            'code' => (string)$row['code'],
            'name' => html_entity_decode((string)$row['name'], ENT_QUOTES, 'UTF-8'),
            'provider' => (string)$row['provider'],
            'description' => match ((string)$row['provider']) {
                'klasha' => 'Pay securely with Klasha using card, bank transfer, mobile money or local payment options.',
                'paystack' => 'Pay securely with Paystack using card, bank transfer, USSD, mobile money or supported local options.',
                'stripe' => 'Pay securely by card through Stripe.',
                'paypal' => 'Continue to PayPal to complete your payment.',
                'internal' => 'Pay from your Seller Africa wallet balance.',
                default => 'Complete payment securely with this provider.',
            },
        ], $rows);
    }

    public function render(string $template, array $config = []): string
    {
        $brand = app_branding();
        $path = APP_ROOT . '/public/template/' . $template;
        $html = is_file($path) ? file_get_contents($path) : false;
        if ($html === false) {
            throw new \RuntimeException("Storefront template missing: {$template}");
        }

        $assetBase = app_url('template/');
        $brandLogo = htmlspecialchars((string)$brand['logo'], ENT_QUOTES, 'UTF-8');
        $brandName = htmlspecialchars((string)$brand['name'], ENT_QUOTES, 'UTF-8');
        $html = strtr($html, [
            'href="assets/' => 'href="' . $assetBase . 'assets/',
            "href='assets/" => "href='" . $assetBase . "assets/",
            'src="assets/' => 'src="' . $assetBase . 'assets/',
            "src='assets/" => "src='" . $assetBase . "assets/",
            'data-background="assets/' => 'data-background="' . $assetBase . 'assets/',
            "data-background='assets/" => "data-background='" . $assetBase . "assets/",
            'href="index.html"' => 'href="' . app_url('store') . '"',
            "href='index.html'" => "href='" . app_url('store') . "'",
            'href="shop.html"' => 'href="' . app_url('shop') . '"',
            'href="about.html"' => 'href="' . app_url('about') . '"',
            'href="contact.html"' => 'href="' . app_url('contact') . '"',
            'href="cart.html"' => 'href="' . app_url('cart') . '"',
            'href="checkout.html"' => 'href="' . app_url('storefront/checkout') . '"',
            'href="product-details.html"' => 'href="' . app_url('store') . '"',
            'href="index.html#"' => 'href="#"',
            'href="shop.html#"' => 'href="#"',
            'href="about.html#"' => 'href="#"',
            'href="contact.html#"' => 'href="#"',
            'href="cart.html#"' => 'href="#"',
            'href="checkout.html#"' => 'href="#"',
            'href="product-details.html#"' => 'href="#"',
            'https://template.moontelict.com/rosun/shop.html' => app_url('shop'),
            'https://template.moontelict.com/rosun/cart.html' => app_url('cart'),
            'https://template.moontelict.com/rosun/checkout.html' => app_url('storefront/checkout'),
            'https://template.moontelict.com/rosun/login.html' => app_url('login'),
            'https://template.moontelict.com/rosun/register.html' => app_url('login'),
            'https://template.moontelict.com/rosun/about.html' => app_url('about'),
            'https://template.moontelict.com/rosun/contact.html' => app_url('contact'),
            'https://template.moontelict.com/rosun/product-details.html' => app_url('store'),
            'https://template.moontelict.com/rosun/product.html' => app_url('store'),
            'https://template.moontelict.com/rosun/blog.html' => '#',
            'https://template.moontelict.com/rosun/blog-grid.html' => '#',
            'https://template.moontelict.com/rosun/blog-details.html' => '#',
            'href="index.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="index.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
            'href="shop.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="shop.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
            'href="about.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="about.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
            'href="contact.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="contact.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
            'href="cart.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="cart.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
            'href="checkout.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="checkout.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
            'href="product.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="product.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
            'href="product-details.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="product-details.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
            'href="faq.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="faq.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
            'href="wishlist.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="wishlist.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
        ]);

        $html = preg_replace(
            '/src=([("\"])' . preg_quote($assetBase, '/') . 'assets\/img\/logo\/(?:black|white)-logo\.png\1/i',
            'src="' . $brandLogo . '"',
            $html
        ) ?? $html;

        $html = preg_replace('/\bRoshun\b/i', $brandName, $html) ?? $html;
        $html = preg_replace('/\bRosun\b/i', $brandName, $html) ?? $html;

        $html = preg_replace(
            '/<footer\b.*?<\/footer>/is',
            $this->simpleFooterHtml(),
            $html
        ) ?? $html;

        $html = $this->removeNewsletterSections($html);

        $html = preg_replace_callback(
            '/<img\b(?=[^>]*src="' . preg_quote($brandLogo, '/') . '")[^>]*>/i',
            static function (array $matches) use ($brandName): string {
                $tag = $matches[0];
                if (preg_match('/\salt=(["\']).*?\1/i', $tag)) {
                    return preg_replace('/\salt=(["\']).*?\1/i', ' alt="' . $brandName . '"', $tag, 1) ?? $tag;
                }

                return preg_replace('/>$/', ' alt="' . $brandName . '">', $tag, 1) ?? $tag;
            },
            $html
        ) ?? $html;

        $html = preg_replace('/\s*<!--\s*preloader\s*-->\s*<div\s+id=(["\'])loading\1\b.*?<\/div>\s*<\/div>\s*<\/div>/is', "\n", $html) ?? $html;
        $html = preg_replace('/<script[^>]+email-decode\.min\.js[^>]*><\/script>/i', '', $html) ?? $html;
        $html = preg_replace('/<img(?![^>]*\sloading=)/i', '<img loading="lazy"', $html) ?? $html;
        $html = StorefrontPerformanceService::prioritizeFirstImage($html);
        $html = SeoService::applyToHead(
            $html,
            (string)($config['title'] ?? $brand['name'] . ' Storefront'),
            (string)($config['metaDescription'] ?? ''),
            is_array($config['meta'] ?? null) ? $config['meta'] : []
        );
        $pageClass = match ((string)($config['page'] ?? '')) {
            'shop' => 'sa-shop-page',
            'cart' => 'sa-cart-page',
            'checkout' => 'sa-checkout-page',
            'product' => 'sa-product-page',
            default => '',
        };
        if ($pageClass !== '') {
            $html = preg_replace_callback('/<body([^>]*)>/i', static function (array $matches) use ($pageClass): string {
                $attributes = $matches[1] ?? '';
                if (preg_match('/\sclass=(["\'])(.*?)\1/i', $attributes)) {
                    $attributes = preg_replace('/\sclass=(["\'])(.*?)\1/i', ' class=$1$2 ' . $pageClass . '$1', $attributes, 1) ?? $attributes;
                } else {
                    $attributes .= ' class="' . $pageClass . '"';
                }

                return '<body' . $attributes . '>';
            }, $html, 1) ?? $html;
        }

        if (in_array((string)($config['page'] ?? ''), ['cart', 'checkout', 'product'], true)) {
            $html = $this->replaceTemplatePublicHeader($html, $config);
        }

        if ((string)($config['page'] ?? '') === 'product' && $template === 'product-details.html') {
            $html = $this->cleanProductDetailsTemplate($html, $config);
        }

        $fixesPath = dirname(__DIR__, 2) . '/public/assets/css/template-storefront-fixes.css';
        $fixesVersion = is_file($fixesPath) ? (string)filemtime($fixesPath) : '1';

        $html = str_ireplace(
            '</head>',
            StorefrontPerformanceService::asyncStylesheetTag(app_url('assets/css/template-storefront-fixes.css?v=' . $fixesVersion)) . "\n</head>",
            $html
        );

        $html = StorefrontPerformanceService::optimizeHeadHtml($html);
        $html = StorefrontPerformanceService::deferScriptTags($html);

        $cartItems = $config['cartItems'] ?? null;
        $cartCount = $config['cartCount'] ?? null;
        if ($cartItems === null || $cartCount === null) {
            $cart = new CartService();
            $cartItems ??= $this->cartProducts($cart->items());
            $cartCount ??= $cart->count();
        }

        $payload = array_merge([
            'products' => [],
            'categories' => [],
            'cartItems' => $cartItems,
            'contentBlocks' => [],
            'isSuperAdmin' => class_exists('\App\AuthService') && \App\AuthService::hasRole('super_admin'),
            'csrf' => getCsrfToken(),
            'endpoints' => [
                'cart' => app_url('api/cart/add.php'),
                'checkout' => app_url('api/checkout/create'),
                'checkoutQuote' => app_url('api/checkout/quote'),
                'content' => app_url('api/storefront/content.php'),
                'search' => app_url('api/storefront/search'),
                'reviews' => app_url('api/storefront/reviews'),
            ],
            'urls' => [
                'store' => app_url('store'),
                'shop' => app_url('shop'),
                'cart' => app_url('cart'),
                'checkout' => app_url('storefront/checkout'),
                'trackOrder' => app_url('track-order'),
                'about' => app_url('about'),
                'contact' => app_url('contact'),
                'affiliateRegistration' => app_url('affiliate-registration'),
                'becomeVendor' => app_url('become-a-vendor'),
                'knowledgeBase' => app_url('knowledge-base'),
                'vendors' => app_url('vendors'),
                'login' => app_url('login'),
                'register' => app_url('buyer/register'),
            ],
            'cartCount' => $cartCount,
            'currencies' => $this->currencies(),
            'paymentMethods' => $this->paymentMethods(),
            'brand' => $brand,
            'title' => $brand['name'] . ' Storefront',
        ], $config);

        $storefrontScriptPath = dirname(__DIR__, 2) . '/public/assets/js/template-storefront.js';
        $storefrontScriptVersion = is_file($storefrontScriptPath) ? (string)filemtime($storefrontScriptPath) : '1';

        $injection = "\n<script>window.SellerAfricaTemplateStorefront = " . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";</script>\n"
            . '<script defer src="' . htmlspecialchars(app_url('assets/js/toasts.js'), ENT_QUOTES, 'UTF-8') . "\"></script>\n"
            . '<script defer src="' . htmlspecialchars(app_url('assets/js/template-storefront.js?v=' . $storefrontScriptVersion), ENT_QUOTES, 'UTF-8') . "\"></script>\n"
            . app_chat_widget_embed();

        return str_ireplace('</body>', $injection . '</body>', $html);
    }

    private function cleanProductDetailsTemplate(string $html, array $config): string
    {
        $brand = app_branding();
        $product = is_array($config['product'] ?? null) ? $config['product'] : null;
        $replacement = $product
            ? $this->liveProductDetailsHtml($product)
            : $this->missingProductDetailsHtml();

        $html = preg_replace(
            '/<!--product-details-area-start\s*-->.*?<!--\s*product-details-area-end\s*-->/is',
            $replacement,
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/<!--\s*Breadcrumb Area Start\s*-->.*?<!--\s*Breadcrumb Area End\s*-->/is',
            '',
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/<!--\s*Start Brand Title\s*-->.*?<!--\s*End Brand Title\s*-->/is',
            '',
            $html,
            1
        ) ?? $html;

        $html = preg_replace(
            '/<div class="mt-offcanvas-content mb-50 d-none d-xl-block">.*?<\/div>\s*<div class="mt-offcanvas-info mb-50">.*?<\/div>/is',
            '<div class="mt-offcanvas-content mb-50 d-none d-xl-block"><h2 class="mt-offcanvas-title">' . self::escape((string)$brand['name']) . '</h2><p>Shop verified marketplace products from Seller Africa vendors.</p></div><div class="mt-offcanvas-info mb-50"><h3 class="mt-offcanvas-sm-title">Information</h3><span><a href="' . app_url('contact') . '">Contact Support</a></span><span><a href="' . app_url('vendors') . '">Our Vendors</a></span><span><a href="' . app_url('track-order') . '">Track Order</a></span></div>',
            $html,
            1
        ) ?? $html;

        return preg_replace(
            '/<!--\s*Start Popular Product Area\s*-->.*?<!--\s*End Popular Product Area\s*-->/is',
            $this->relatedProductsHtml(
                is_array($config['products'] ?? null) ? $config['products'] : [],
                is_array($config['product'] ?? null) ? $config['product'] : null
            ),
            $html,
            1
        ) ?? $html;
    }

    private function liveProductDetailsHtml(array $product): string
    {
        $name = self::escape((string)($product['name'] ?? 'Seller Africa product'));
        $description = trim((string)($product['description'] ?? ''));
        $descriptionHtml = $description !== '' ? nl2br(self::escape($description)) : '';
        $summaryDescriptionHtml = $descriptionHtml !== '' ? '<div class="mt-shop-details__text mb-17"><p>' . $descriptionHtml . '</p></div>' : '';
        $tabDescriptionHtml = $descriptionHtml !== '' ? '<p class="mb-30">' . $descriptionHtml . '</p>' : '';
        $vendor = self::escape((string)($product['vendor'] ?? 'Seller Africa vendor'));
        $brand = self::escape((string)($product['brand'] ?? $product['vendor'] ?? 'Seller Africa'));
        $categories = array_values(array_filter(array_map('strval', (array)($product['categories'] ?? []))));
        $categoryText = self::escape($categories !== [] ? implode(', ', $categories) : (string)($product['primaryCategory'] ?? 'Uncategorized'));
        $sku = self::escape((string)(($product['sku'] ?? '') ?: 'N/A'));
        $stock = (string)($product['stock'] ?? 'in_stock');
        $stockLabel = self::escape(str_replace('_', ' ', ucfirst($stock)));
        $badge = self::escape((string)($product['badge'] ?? 'LIVE'));
        $reviewCount = (int)($product['reviewCount'] ?? 0);
        $averageRating = max(0, min(5, (float)($product['averageRating'] ?? $product['rating'] ?? 0)));
        $ratingStars = '';
        for ($star = 1; $star <= 5; $star++) {
            $ratingStars .= '<span><i class="' . ($star <= (int)round($averageRating) ? 'fa-solid' : 'fa-light') . ' fa-star"></i></span>';
        }
        $rawPrice = (float)($product['rawPrice'] ?? 0);

        $regularRawPrice = isset($product['regularRawPrice']) ? (float)$product['regularRawPrice'] : null;
        $currency = self::escape((string)($product['currency'] ?? 'USD'));
        $price = self::escape((string)($product['price'] ?? self::money($rawPrice, (string)($product['currency'] ?? 'USD'))));
        $regularPrice = self::escape((string)($product['regularPrice'] ?? ''));
        $productId = (int)($product['id'] ?? 0);
        $variationPicker = ProductVariationService::picker($productId);
        $chatVendorId = (int)($product['vendorId'] ?? 0);
        $chatUrl = self::escape(app_url('chat?vendor=' . $chatVendorId . '&product=' . $productId));
        $vendorUrl = self::escape((string)($product['vendorUrl'] ?? app_url('vendors')));
        $shareUrl = self::escape((string)($product['url'] ?? app_url('product/' . rawurlencode((string)($product['slug'] ?? '')))));
        $shareTitle = self::escape('Share ' . (string)($product['name'] ?? 'this product'));
        $rateSellerEndpoint = self::escape(app_url('rate-seller'));
        $csrfToken = self::escape(getCsrfToken());
        $images = array_values(array_filter(array_merge(
            is_array($product['media'] ?? null) ? $product['media'] : [],
            [(string)($product['image'] ?? '')]
        )));
        $transparent = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
        $images = $images !== [] ? array_slice(array_values(array_unique($images)), 0, 4) : [$transparent];
        $hasThumbs = count($images) > 1;

        $thumbs = '';
        $panes = '';
        foreach ($images as $index => $image) {
            $tab = ['one', 'two', 'three', 'four'][$index] ?? ('img-' . $index);
            $active = $index === 0 ? ' active' : '';
            $show = $index === 0 ? ' show active' : '';
            $selected = $index === 0 ? 'true' : 'false';
            $src = self::escape((string)$image);
            $thumbs .= '<button class="nav-links' . $active . '" id="nav-' . $tab . '-tab" data-bs-toggle="tab" data-bs-target="#nav-' . $tab . '" type="button" role="tab" aria-controls="nav-' . $tab . '" aria-selected="' . $selected . '"><img src="' . $src . '" alt="' . $name . '"></button>';
            $panes .= '<div class="tab-pane fade' . $show . '" id="nav-' . $tab . '" role="tabpanel" aria-labelledby="nav-' . $tab . '-tab"><div class="mt-shop-details__tab-big-img"><img src="' . $src . '" alt="' . $name . '"></div></div>';
        }
        $galleryThumbColumn = $hasThumbs
            ? '<div class="col-lg-3 col-md-3 col-sm-3"><div class="mt-shop-details__tab-btn-box"><nav><div class="nav nav-tab" id="nav-tab" role="tablist">' . $thumbs . '</div></nav></div></div>'
            : '';
        $galleryImageColumn = $hasThumbs ? 'col-lg-9 col-md-9 col-sm-9' : 'col-12';

        $oldPrice = $regularRawPrice && $regularRawPrice > $rawPrice
            ? '<del data-sa-price="' . self::escape((string)$regularRawPrice) . '" data-sa-currency="' . $currency . '">' . $regularPrice . '</del>'
            : '<del style="display:none"></del>';

        $weight = trim((string)($product['weight'] ?? ''));
        $length = trim((string)($product['length'] ?? ''));
        $width = trim((string)($product['width'] ?? ''));
        $height = trim((string)($product['height'] ?? ''));
        $shippingClass = trim((string)($product['shippingClass'] ?? ''));
        $saleUnit = self::escape((string)($product['saleUnit'] ?? 'Piece'));
        $hasWeight = is_numeric($weight) ? (float)$weight > 0 : $weight !== '';
        $dimensionValues = [$length, $width, $height];
        $hasDimensions = count(array_filter($dimensionValues, static fn (string $value): bool => is_numeric($value) ? (float)$value > 0 : $value !== '')) === 3;
        $dimensionsText = $hasDimensions ? implode(' x ', array_map(static fn (string $value): string => self::escape($value), $dimensionValues)) : '';
        $shippingClassText = $shippingClass !== '' ? self::escape($shippingClass) : 'Standard';
        $weightText = $hasWeight ? self::escape($weight) . ' kg' : '';
        $weightHtml = $hasWeight
            ? '<div class="mt-shop-details__product-info-2 mb-25"><ul><li>Weight:<span>' . self::escape($weight) . '</span></li></ul></div>'
            : '';
        $shippingMetaRows = '<div><dt>Sold as</dt><dd>' . $saleUnit . '</dd></div>';
        if ($hasWeight) {
            $shippingMetaRows .= '<div><dt>Weight</dt><dd>' . $weightText . '</dd></div>';
        }
        if ($hasDimensions) {
            $shippingMetaRows .= '<div><dt>Package</dt><dd>' . $dimensionsText . '</dd></div>';
        }
        if ($shippingClass !== '') {
            $shippingMetaRows .= '<div><dt>Class</dt><dd>' . $shippingClassText . '</dd></div>';
        }
        $shippingMetaHtml = '<div class="sa-product-shipping-meta">
            <span>Shipping details</span>
            <dl>
                ' . $shippingMetaRows . '
            </dl>
        </div>';
        $additionalInfoRows = '
                                    <tr><td class="add-info">Vendor</td><td class="add-info-list">' . $vendor . '</td></tr>
                                    <tr><td class="add-info">Category</td><td class="add-info-list">' . $categoryText . '</td></tr>
                                    <tr><td class="add-info">Brand</td><td class="add-info-list">' . $brand . '</td></tr>
                                    <tr><td class="add-info">Sold as</td><td class="add-info-list">' . $saleUnit . '</td></tr>
                                    <tr><td class="add-info">SKU</td><td class="add-info-list">' . $sku . '</td></tr>';
        if ($hasWeight) {
            $additionalInfoRows .= '
                                    <tr><td class="add-info">Weight</td><td class="add-info-list">' . $weightText . '</td></tr>';
        }
        if ($hasDimensions) {
            $additionalInfoRows .= '
                                    <tr><td class="add-info">Dimensions</td><td class="add-info-list">' . $dimensionsText . '</td></tr>';
        }
        if ($shippingClass !== '') {
            $additionalInfoRows .= '
                                    <tr><td class="add-info">Shipping Class</td><td class="add-info-list">' . $shippingClassText . '</td></tr>';
        }
        $additionalInfoRows .= '
                                    <tr><td class="add-info">Stock</td><td class="add-info-list">' . $stockLabel . '</td></tr>';
        $shippingEstimateEndpoint = self::escape(app_url('api/storefront/product-shipping-quote'));

        return <<<HTML
      <!--product-details-area-start -->
      <div class="mt-product-details-area pt-130 fix">
         <div class="container">
            <div class="row">
               <div class="col-xl-6 col-lg-6">
                  <div class="mt-shop-details__wrapper mb-30">
                     <div class="row">
                        {$galleryThumbColumn}
                        <div class="{$galleryImageColumn}">
                           <div class="mt-shop-details__tab-content-box mb-20"><div class="tab-content" id="nav-tabContent">{$panes}</div></div>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="col-xl-6 col-lg-6">
                  <div class="mt-shop-details__right-warp mb-30 p-relative">
                     <div class="mt-shop-details__offer mb-15"><span class="red-color">{$badge}</span></div>
                     <h3 class="mt-shop-details__title-sm mb-15">{$name}</h3>
                     {$summaryDescriptionHtml}
                     <div class="mt-shop-details__ratting mb-10">{$ratingStars}<span class="review-text">({$reviewCount} Review)</span></div>
                     <div class="mt-shop-details__price mb-17">{$oldPrice}<span data-sa-price="{$rawPrice}" data-sa-currency="{$currency}">{$price}</span></div>
                     {$weightHtml}
                     {$shippingMetaHtml}
                     {$variationPicker}
                     <form class="sa-product-shipping-estimate" data-shipping-estimate data-endpoint="{$shippingEstimateEndpoint}" data-product-id="{$productId}" data-currency="{$currency}">
                        <span>Estimated shipping</span>
                        <div>
                           <input name="address_line1" placeholder="Street address" autocomplete="shipping address-line1" required>
                           <input name="city" placeholder="City" autocomplete="shipping address-level2" required>
                           <input name="state" placeholder="State" autocomplete="shipping address-level1">
                           <input name="postcode" placeholder="Postcode" autocomplete="shipping postal-code">
                           <input name="country_code" placeholder="Country code" maxlength="2" value="US" autocomplete="shipping country" required>
                           <button type="submit">Estimate</button>
                        </div>
                        <p data-shipping-estimate-result>Enter your delivery address for an Aramex-based shipping estimate before checkout.</p>
                     </form>
                     <div class="mt-shop-details__quantity-wrap sa-product-action-row mb-40 d-flex align-items-center">
                        <div class="mt-shop-details__quantity-box"><div class="mt-shop-details__quantity"><div class="mt-cart-minus mt-cart-min-plus"><i class="fal fa-minus"></i></div><input type="text" value="01"><div class="mt-cart-plus mt-cart-min-plus"><i class="fal fa-plus"></i></div></div></div>
                        <div class="mt-shop-details__btn mr-12"><a class="mt-btn" href="#" data-add-cart="{$productId}"><i class="fa-solid fa-basket-shopping"></i><span class="ml-6">Add To Cart</span></a></div>
                        <div class="mt-shop-details__btn"><a class="mt-btn" style="background:#fff;color:#006b52;border:1px solid #006b52;" href="{$chatUrl}"><i class="fa-solid fa-comment-dots"></i><span class="ml-6">Chat with this Seller</span></a></div>
                     </div>
                     <div class="sa-product-secondary-actions">
                        <a href="{$vendorUrl}"><i class="fa-solid fa-store"></i><span>View seller page</span></a>
                        <button type="button" data-sa-share-product data-share-url="{$shareUrl}" data-share-title="{$shareTitle}"><i class="fa-solid fa-share-nodes"></i><span>Share this product</span></button>
                     </div>
                  </div>
               </div>
               <div class="sa-sticky-cart-bar" data-sa-sticky-cart>
                  <div class="sa-sticky-cart-bar__inner">
                     <img src="{$images[0]}" alt="{$name}">
                     <div class="sa-sticky-cart-bar__info">
                        <span class="sa-sticky-cart-bar__name">{$name}</span>
                        <span class="sa-sticky-cart-bar__price">{$oldPrice}<span data-sa-price="{$rawPrice}" data-sa-currency="{$currency}">{$price}</span></span>
                     </div>
                     <a class="mt-btn" href="#" data-add-cart="{$productId}"><i class="fa-solid fa-basket-shopping"></i><span class="ml-6">Add To Cart</span></a>
                  </div>
               </div>
            </div>
            <div class="row">
               <div class="productdetails-tabs mt-100">
                  <div class="col-xl-12 col-lg-12 col-12">
                     <div class="product-additional-tab">
                        <div class="pro-details-nav mb-40">
                           <ul class="nav nav-tabs pro-details-nav-btn" id="myTabs" role="tablist">
                              <li class="nav-item" role="presentation"><button class="nav-links active" id="home-tab-1" data-bs-toggle="tab" data-bs-target="#home-1" type="button" role="tab" aria-controls="home-1" aria-selected="true"><span>Description</span></button></li>
                              <li class="nav-item" role="presentation"><button class="nav-links" id="information-tab" data-bs-toggle="tab" data-bs-target="#additional-information" type="button" role="tab" aria-controls="additional-information" aria-selected="false"><span>Product Info</span></button></li>
                              <li class="nav-item" role="presentation"><button class="nav-links" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab" aria-controls="reviews" aria-selected="false"><span>Rate this Seller</span></button></li>
                           </ul>
                        </div>
                        <div class="tab-content mt-content-tab" id="myTabContent-2">
                           <div class="tab-para tab-pane fade show active" id="home-1" role="tabpanel" aria-labelledby="home-tab-1"><div class="mt-content-tab-wrap mb-15"><div class="mt-content-tab-content">{$tabDescriptionHtml}</div></div></div>
                           <div class="tab-pane fade" id="additional-information" role="tabpanel" aria-labelledby="information-tab">
                              <div class="product__details-info table-responsive mb-15">
                                 <table class="table table-striped"><tbody>
                                    {$additionalInfoRows}
                                 </tbody></table>
                              </div>
                           </div>
                           <div class="tab-pane fade" id="reviews" role="tabpanel" aria-labelledby="reviews-tab">
                              <div class="sa-rate-seller" data-sa-rate-seller data-product-id="{$productId}">
                                 <p class="sa-rate-seller__prompt">How would you rate this seller?</p>
                                 <div class="sa-rate-seller__stars" role="radiogroup" aria-label="Rate this seller">
                                    <button type="button" data-star="1" aria-label="1 star">&#9733;</button>
                                    <button type="button" data-star="2" aria-label="2 stars">&#9733;</button>
                                    <button type="button" data-star="3" aria-label="3 stars">&#9733;</button>
                                    <button type="button" data-star="4" aria-label="4 stars">&#9733;</button>
                                    <button type="button" data-star="5" aria-label="5 stars">&#9733;</button>
                                 </div>
                                 <button type="button" class="sa-rate-seller__submit" data-sa-rate-submit disabled>Submit Rating</button>
                                 <p class="sa-rate-seller__status" data-sa-rate-status></p>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
      <script>
      (() => {
        document.querySelectorAll('[data-shipping-estimate]').forEach((form) => {
          form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const result = form.querySelector('[data-shipping-estimate-result]');
            const button = form.querySelector('button');
            const data = new FormData(form);
            data.set('product_id', form.dataset.productId || '');
            data.set('currency', form.dataset.currency || 'USD');
            if (button) button.disabled = true;
            if (result) result.textContent = 'Calculating shipping...';
            try {
              const response = await fetch(form.dataset.endpoint || '', { method: 'POST', body: data, credentials: 'same-origin' });
              const json = await response.json();
              if (!json.ok) throw new Error(json.message || 'Shipping estimate unavailable.');
              if (result) result.textContent = json.message || ('Estimated shipping: ' + json.shipping);
            } catch (error) {
              if (result) result.textContent = error.message || 'Shipping estimate unavailable.';
            } finally {
              if (button) button.disabled = false;
            }
          });
        });
      })();
      </script>
      <style>
      <style>
        .sa-rate-seller { padding: 30px 0; text-align: center; }
        .sa-rate-seller__prompt { margin: 0 0 16px; font-weight: 700; color: #202326; font-size: 16px; }
        .sa-rate-seller__stars { display: inline-flex; gap: 8px; margin-bottom: 20px; }
        .sa-rate-seller__stars button { background: none; border: 0; font-size: 34px; line-height: 1; color: #d8d8dc; cursor: pointer; padding: 0; transition: color .12s ease, transform .12s ease; }
        .sa-rate-seller__stars button:hover,
        .sa-rate-seller__stars button.is-hover { color: #d8951a; transform: scale(1.08); }
        .sa-rate-seller__stars button.is-selected { color: #d8951a; }
        .sa-rate-seller__submit { display: block; margin: 0 auto; min-width: 180px; min-height: 46px; border: 0; border-radius: 8px; background: #006b52; color: #fff; font-weight: 800; font-size: 14px; cursor: pointer; }
        .sa-rate-seller__submit:disabled { opacity: .45; cursor: not-allowed; }
        .sa-rate-seller__status { margin: 14px 0 0; font-size: 13px; color: #6f7280; min-height: 18px; }
        .sa-rate-seller__status.is-success { color: #006b52; font-weight: 700; }
        .sa-rate-seller__status.is-error { color: #b91c1c; font-weight: 700; }
        .sa-product-secondary-actions { display: flex; flex-wrap: wrap; gap: 10px; margin: -18px 0 30px; }
        .sa-product-secondary-actions a,
        .sa-product-secondary-actions button { min-height: 42px; border: 1px solid #dfe8e5; border-radius: 999px; background: #fff; color: #164338; display: inline-flex; align-items: center; gap: 8px; padding: 0 15px; font-weight: 800; font-size: 13px; cursor: pointer; }
        .sa-product-secondary-actions a:hover,
        .sa-product-secondary-actions button:hover { border-color: #006b52; color: #006b52; }
      </style>
      <style>
        .sa-sticky-cart-bar { position: fixed; left: 0; right: 0; bottom: -100px; z-index: 90; background: #fff; box-shadow: 0 -8px 24px rgba(0,0,0,.12); transition: bottom .25s ease; }
        .sa-sticky-cart-bar.is-visible { bottom: 0; }
        .sa-sticky-cart-bar__inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; gap: 16px; padding: 12px 20px; }
        .sa-sticky-cart-bar__inner img { width: 52px; height: 52px; object-fit: contain; border-radius: 8px; background: #f8f8f9; flex-shrink: 0; }
        .sa-sticky-cart-bar__info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
        .sa-sticky-cart-bar__name { font-weight: 700; font-size: 14px; color: #111; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sa-sticky-cart-bar__price del { color: #999; margin-right: 6px; }
        .sa-sticky-cart-bar__price { font-size: 14px; }
        @media (max-width: 620px) { .sa-sticky-cart-bar__name { display: none; } }
      </style>
      <script>
      (() => {
        document.querySelectorAll('[data-sa-share-product]').forEach((button) => {
          button.addEventListener('click', async () => {
            const url = button.dataset.shareUrl || window.location.href;
            const title = button.dataset.shareTitle || document.title;
            try {
              if (navigator.share) {
                await navigator.share({ title, url });
              } else if (navigator.clipboard) {
                await navigator.clipboard.writeText(url);
                const label = button.querySelector('span');
                if (label) {
                  label.textContent = 'Link copied';
                  setTimeout(() => { label.textContent = 'Share this product'; }, 1800);
                }
              }
            } catch (error) {}
          });
        });
        const bar = document.querySelector('[data-sa-sticky-cart]');
        const trigger = document.querySelector('.sa-product-action-row');
        if (!bar || !trigger) return;
        const observer = new IntersectionObserver((entries) => {
          entries.forEach((entry) => {
            bar.classList.toggle('is-visible', !entry.isIntersecting);
          });
        }, { rootMargin: '-80px 0px 0px 0px' });
        observer.observe(trigger);
      })();
      </script>
      <script>
      (() => {
        const widget = document.querySelector('[data-sa-rate-seller]');
        if (!widget) return;
        const stars = Array.from(widget.querySelectorAll('[data-star]'));
        const submitBtn = widget.querySelector('[data-sa-rate-submit]');
        const status = widget.querySelector('[data-sa-rate-status]');
        const productId = widget.getAttribute('data-product-id');
        let selected = 0;

        const paint = (value) => {
          stars.forEach((star) => {
            star.classList.toggle('is-selected', parseInt(star.dataset.star, 10) <= value);
          });
        };

        stars.forEach((star) => {
          star.addEventListener('mouseenter', () => paint(parseInt(star.dataset.star, 10)));
          star.addEventListener('mouseleave', () => paint(selected));
          star.addEventListener('click', () => {
            selected = parseInt(star.dataset.star, 10);
            paint(selected);
            submitBtn.disabled = false;
          });
        });

        submitBtn.addEventListener('click', async () => {
          if (selected < 1) return;
          submitBtn.disabled = true;
          status.textContent = 'Submitting...';
          status.className = 'sa-rate-seller__status';
          try {
            const response = await fetch('{$rateSellerEndpoint}', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: new URLSearchParams({ product_id: productId, rating: String(selected), csrf_token: '{$csrfToken}' }),
            });
            const json = await response.json();
            if (json.ok) {
              status.textContent = 'Thank you for rating this seller!';
              status.classList.add('is-success');
            } else {
              status.textContent = json.message || 'Something went wrong, please try again.';
              status.classList.add('is-error');
              submitBtn.disabled = false;
            }
          } catch (error) {
            status.textContent = 'Something went wrong, please try again.';
            status.classList.add('is-error');
            submitBtn.disabled = false;
          }
        });
      })();
      </script>
      <!-- product-details-area-end -->
HTML;
    }

    private function relatedProductsHtml(array $products, ?array $product = null): string
    {
        $products = array_slice(array_values(array_filter($products, 'is_array')), 0, 8);
        if ($products === []) {
            return '';
        }

        $categoryName = trim((string)($product['primaryCategory'] ?? ''));
        if ($categoryName === '') {
            $categories = array_values(array_filter(array_map('strval', (array)($product['categories'] ?? []))));
            $categoryName = $categories[0] ?? 'this category';
        }
        $categorySlug = trim((string)($product['primaryCategorySlug'] ?? ''));
        $categoryLabel = self::escape($categoryName !== '' ? $categoryName : 'this category');
        $viewAllUrl = $categorySlug !== ''
            ? app_url('shop?category=' . rawurlencode($categorySlug) . '&section=' . rawurlencode($categoryName !== '' ? $categoryName : 'Similar Products'))
            : app_url('shop');
        $viewAllUrl = self::escape($viewAllUrl);

        $cards = '';
        foreach ($products as $relatedProduct) {
            $name = self::escape((string)($relatedProduct['name'] ?? 'Seller Africa product'));
            $url = self::escape((string)($relatedProduct['url'] ?? app_url('shop')));
            $image = self::escape((string)($relatedProduct['image'] ?? ''));
            $vendor = self::escape((string)($relatedProduct['vendor'] ?? $relatedProduct['brand'] ?? 'Seller Africa vendor'));
            $stock = self::escape((string)((($relatedProduct['stock'] ?? '') === 'out_of_stock') ? 'Out' : 'Stock'));
            $badge = self::escape((string)($relatedProduct['badge'] ?? 'LIVE'));
            $rawPrice = (float)($relatedProduct['rawPrice'] ?? 0);
            $regularRawPrice = isset($relatedProduct['regularRawPrice']) ? (float)$relatedProduct['regularRawPrice'] : null;
            $currency = self::escape((string)($relatedProduct['currency'] ?? 'USD'));
            $price = self::escape((string)($relatedProduct['price'] ?? self::money($rawPrice, (string)($relatedProduct['currency'] ?? 'USD'))));
            $regularPrice = self::escape((string)($relatedProduct['regularPrice'] ?? ''));
            $productId = (int)($relatedProduct['id'] ?? 0);
            $imageHtml = $image !== ''
                ? '<img src="' . $image . '" alt="' . $name . '">'
                : '<span class="sa-product-image-placeholder">' . $name . '</span>';
            $oldPrice = $regularRawPrice && $regularRawPrice > $rawPrice
                ? '<del data-sa-price="' . self::escape((string)$regularRawPrice) . '" data-sa-currency="' . $currency . '">' . $regularPrice . '</del>'
                : '<del style="display:none"></del>';

            $cards .= <<<HTML
                     <div class="swiper-slide">
                        <div class="mthot__product-item-wrap">
                           <div class="mthot__product-item p-relative fix">
                              <div class="mthot__product-img mb-10">
                                 <div class="mtfeature__product-offer"><span>{$badge}</span></div>
                                 <a href="{$url}">{$imageHtml}</a>
                              </div>
                              <div class="mthot__product-content">
                                 <div class="mthot__product-ratcat mb-10 d-flex align-items-center justify-content-between">
                                    <div class="mthot__product-cate"><span>{$vendor}</span><span>{$stock}</span></div>
                                 </div>
                                 <h6 class="mthot__product-title mb-30"><a href="{$url}">{$name}</a></h6>
                                 <div class="mthot__product-price-wrap d-flex align-items-center justify-content-between">
                                    <div class="mthot__product-price"><span data-sa-price="{$rawPrice}" data-sa-currency="{$currency}">{$price}</span>{$oldPrice}</div>
                                    <div class="mthot__product-cart"><a href="#" data-add-cart="{$productId}"><i class="fa-solid fa-basket-shopping"></i></a></div>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
HTML;
        }

        $fbtItems = $this->frequentlyBoughtWith((int)($product['id'] ?? 0), $categorySlug, 3);
        $fbtCards = '';
        $fbtTotal = (float)($product['rawPrice'] ?? 0);
        $fbtCurrency = self::escape((string)($product['currency'] ?? 'USD'));
        $fbtCheckboxes = '';
        if ($product) {
            $fbtCards .= '<div class="sa-fbt-item"><img src="' . self::escape((string)($product['image'] ?? '')) . '" alt="' . self::escape((string)($product['name'] ?? '')) . '"><span class="sa-fbt-item__label">This item</span></div>';
        }
        foreach ($fbtItems as $fbtIndex => $fbtProduct) {
            $fbtName = self::escape((string)($fbtProduct['name'] ?? 'Seller Africa product'));
            $fbtImage = self::escape((string)($fbtProduct['image'] ?? ''));
            $fbtId = (int)($fbtProduct['id'] ?? 0);
            $fbtRawPrice = (float)($fbtProduct['rawPrice'] ?? 0);
            $fbtTotal += $fbtRawPrice;
            $fbtCards .= '<span class="sa-fbt-plus">+</span><div class="sa-fbt-item"><input type="checkbox" data-sa-fbt-check data-sa-fbt-id="' . $fbtId . '" data-sa-fbt-price="' . $fbtRawPrice . '" checked><img src="' . $fbtImage . '" alt="' . $fbtName . '"><span class="sa-fbt-item__label">' . $fbtName . '</span></div>';
        }
        $fbtTotalFormatted = self::money($fbtTotal, (string)($product['currency'] ?? 'USD'));
        $fbtSection = $fbtItems !== []
            ? '<div class="sa-fbt-section">
                <h3 class="mt-section-title mb-20">Frequently bought together</h3>
                <div class="sa-fbt-row">' . $fbtCards . '</div>
                <div class="sa-fbt-summary">
                    <span>Total for these items: <strong data-sa-fbt-total data-sa-fbt-base="' . (float)($product['rawPrice'] ?? 0) . '">' . self::escape($fbtTotalFormatted) . '</strong></span>
                    <a class="mt-btn" href="#" data-sa-fbt-add-all data-sa-fbt-main-id="' . (int)($product['id'] ?? 0) . '"><span>Add all to cart</span></a>
                </div>
            </div>'
            : '';

        return <<<HTML
      <!-- Start Popular Product Area -->
      <section class="mtpopular__product-area mtpopular__product-2 pt-80 pb-50 p-relative fix">
         <div class="container">
            {$fbtSection}
            <div class="row align-items-center">
               <div class="col-lg-8">
                  <div class="mt-section-content mb-30">
                     <h3 class="mt-section-title">Buy similar products from <span>this category</span></h3>
                     <p>Similar products in {$categoryLabel}.</p>
                  </div>
               </div>
               <div class="col-lg-4">
                  <div class="mt-section-btn text-lg-end mb-30">
                     <a class="mt-btn" href="{$viewAllUrl}"><span>View all {$categoryLabel}</span></a>
                  </div>
               </div>
            </div>
            <div class="mtpopular__product-wrap">
               <div class="swiper mtpopular_product_2_active">
                  <div class="swiper-wrapper">
{$cards}
                  </div>
               </div>
            </div>
         </div>
      </section>
      <!-- End Popular Product Area -->
      <style>
        .sa-fbt-section { margin-bottom: 40px; padding: 24px; border: 1px solid #ececec; border-radius: 12px; background: #fbfbfb; }
        .sa-fbt-row { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin: 16px 0; }
        .sa-fbt-item { display: flex; flex-direction: column; align-items: center; gap: 6px; text-align: center; width: 96px; }
        .sa-fbt-item img { width: 76px; height: 76px; object-fit: contain; background: #fff; border: 1px solid #ececec; border-radius: 8px; padding: 6px; }
        .sa-fbt-item input[type="checkbox"] { margin-bottom: 4px; }
        .sa-fbt-item__label { font-size: 11px; color: #555; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; width: 100%; }
        .sa-fbt-plus { font-size: 20px; color: #999; flex-shrink: 0; }
        .sa-fbt-summary { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; padding-top: 16px; border-top: 1px solid #ececec; }
        .sa-fbt-summary strong { color: #22c55e; font-size: 18px; }
      </style>
      <script>
      (() => {
        const section = document.querySelector('.sa-fbt-section');
        if (!section) return;
        const totalEl = section.querySelector('[data-sa-fbt-total]');
        const currency = '{$fbtCurrency}';
        const recalc = () => {
          let total = parseFloat(totalEl.getAttribute('data-sa-fbt-base') || '0');
          section.querySelectorAll('[data-sa-fbt-check]:checked').forEach((box) => {
            total += parseFloat(box.getAttribute('data-sa-fbt-price') || '0');
          });
          totalEl.textContent = new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(total);
        };
        section.querySelectorAll('[data-sa-fbt-check]').forEach((box) => box.addEventListener('change', recalc));
        recalc();
        const addAllBtn = section.querySelector('[data-sa-fbt-add-all]');
        addAllBtn?.addEventListener('click', (event) => {
          event.preventDefault();
          const mainId = addAllBtn.getAttribute('data-sa-fbt-main-id');
          const ids = [mainId, ...Array.from(section.querySelectorAll('[data-sa-fbt-check]:checked')).map((b) => b.getAttribute('data-sa-fbt-id'))];
          ids.forEach((id) => {
            if (!id) return;
            const btn = document.querySelector('[data-add-cart="' + id + '"]');
            if (btn) btn.click();
          });
        });
      })();
      </script>
HTML;
    }

    private function productExtrasHtml(array $config): string
    {
        $sellerProducts = array_slice(array_values(array_filter((array)($config['sellerProducts'] ?? []), 'is_array')), 0, 5);
        $spotlightVendors = array_slice(array_values(array_filter((array)($config['spotlightVendors'] ?? []), 'is_array')), 0, 4);
        $blogPosts = array_slice(array_values(array_filter((array)($config['blogPosts'] ?? []), 'is_array')), 0, 3);
        if ($sellerProducts === [] && $spotlightVendors === [] && $blogPosts === []) {
            return '';
        }

        $sellerCards = '';
        foreach ($sellerProducts as $item) {
            $sellerCards .= $this->compactProductCardHtml($item);
        }

        $vendorCards = '';
        foreach ($spotlightVendors as $vendor) {
            $name = self::escape((string)($vendor['name'] ?? 'Seller Africa vendor'));
            $url = self::escape((string)($vendor['url'] ?? app_url('vendors')));
            $image = self::escape((string)(($vendor['banner'] ?? '') ?: ($vendor['logo'] ?? '')));
            $description = self::escape(mb_strimwidth((string)($vendor['description'] ?? 'Verified Seller Africa vendor.'), 0, 110, '...'));
            $productCount = number_format((int)($vendor['productCount'] ?? 0));
            $rating = self::escape((string)($vendor['rating'] ?? '0'));
            $imageHtml = $image !== '' ? '<img src="' . $image . '" alt="' . $name . '">' : '<span>' . $name . '</span>';
            $vendorCards .= '<article class="sa-product-extra-card is-vendor"><a class="sa-product-extra-card__image" href="' . $url . '">' . $imageHtml . '</a><div><h4><a href="' . $url . '">' . $name . '</a></h4><p>' . $description . '</p><small>' . $productCount . ' products · ' . $rating . ' rating</small></div></article>';
        }

        $blogCards = '';
        foreach ($blogPosts as $post) {
            $title = self::escape((string)($post['title'] ?? 'Seller Africa update'));
            $url = self::escape((string)($post['url'] ?? app_url('blog-news')));
            $image = self::escape((string)($post['image'] ?? ''));
            $excerpt = self::escape(mb_strimwidth((string)($post['excerpt'] ?? 'Marketplace news and seller resources.'), 0, 110, '...'));
            $category = self::escape((string)($post['category'] ?? 'News'));
            $imageHtml = $image !== '' ? '<img src="' . $image . '" alt="' . $title . '">' : '<span>' . $category . '</span>';
            $blogCards .= '<article class="sa-product-extra-card"><a class="sa-product-extra-card__image" href="' . $url . '">' . $imageHtml . '</a><div><small>' . $category . '</small><h4><a href="' . $url . '">' . $title . '</a></h4><p>' . $excerpt . '</p></div></article>';
        }

        $sellerSection = $sellerCards !== ''
            ? '<section class="sa-product-extra-section"><div class="container"><div class="sa-product-extra-head"><div><span>Same Seller</span><h3>Other products from this seller</h3></div></div><div class="sa-product-extra-grid is-products">' . $sellerCards . '</div></div></section>'
            : '';
        $vendorSection = $vendorCards !== ''
            ? '<section class="sa-product-extra-section is-soft"><div class="container"><div class="sa-product-extra-head"><div><span>Vendor Spotlight</span><h3>Trusted stores to explore</h3></div><a href="' . app_url('vendors') . '">View all vendors</a></div><div class="sa-product-extra-grid">' . $vendorCards . '</div></div></section>'
            : '';
        $blogSection = $blogCards !== ''
            ? '<section class="sa-product-extra-section"><div class="container"><div class="sa-product-extra-head"><div><span>Blog</span><h3>Marketplace news and guides</h3></div><a href="' . app_url('blog-news') . '">Read more</a></div><div class="sa-product-extra-grid">' . $blogCards . '</div></div></section>'
            : '';

        return $sellerSection . $vendorSection . $blogSection . <<<HTML
      <style>
        .sa-product-extra-section { padding: 56px 0; background: #fff; }
        .sa-product-extra-section.is-soft { background: #f5fbf8; }
        .sa-product-extra-head { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin-bottom: 24px; }
        .sa-product-extra-head span { color: #006b52; font-weight: 900; text-transform: uppercase; font-size: 12px; letter-spacing: .04em; }
        .sa-product-extra-head h3 { margin: 6px 0 0; color: #161a1d; font-size: clamp(25px, 3vw, 38px); }
        .sa-product-extra-head a { color: #006b52; font-weight: 900; }
        .sa-product-extra-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
        .sa-product-extra-grid.is-products { grid-template-columns: repeat(5, minmax(0, 1fr)); }
        .sa-product-extra-card { border: 1px solid #e5eeeb; border-radius: 12px; background: #fff; overflow: hidden; box-shadow: 0 12px 32px rgba(17,24,39,.06); }
        .sa-product-extra-card__image { aspect-ratio: 4 / 3; display: grid; place-items: center; background: #f7faf9; color: #006b52; font-weight: 900; text-align: center; padding: 12px; }
        .sa-product-extra-card__image img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .sa-product-extra-card.is-vendor .sa-product-extra-card__image { padding: 0; background: #e8f3ef; }
        .sa-product-extra-card.is-vendor .sa-product-extra-card__image img { object-fit: cover; }
        .sa-product-extra-card div { padding: 14px; }
        .sa-product-extra-card h4 { margin: 0 0 8px; font-size: 16px; line-height: 1.25; color: #15191b; }
        .sa-product-extra-card p { margin: 0 0 10px; color: #687477; font-size: 13px; line-height: 1.5; }
        .sa-product-extra-card small { color: #006b52; font-weight: 850; }
        @media (max-width: 1100px) { .sa-product-extra-grid, .sa-product-extra-grid.is-products { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 620px) { .sa-product-extra-head { align-items: flex-start; flex-direction: column; } .sa-product-extra-grid, .sa-product-extra-grid.is-products { grid-template-columns: 1fr; } }
      </style>
HTML;
    }

    private function compactProductCardHtml(array $product): string
    {
        $name = self::escape((string)($product['name'] ?? 'Seller Africa product'));
        $url = self::escape((string)($product['url'] ?? app_url('shop')));
        $image = self::escape((string)($product['image'] ?? ''));
        $price = self::escape((string)($product['price'] ?? ''));
        $vendor = self::escape((string)($product['vendor'] ?? 'Seller Africa vendor'));
        $imageHtml = $image !== '' ? '<img src="' . $image . '" alt="' . $name . '">' : '<span>' . $name . '</span>';

        return '<article class="sa-product-extra-card"><a class="sa-product-extra-card__image" href="' . $url . '">' . $imageHtml . '</a><div><small>' . $vendor . '</small><h4><a href="' . $url . '">' . $name . '</a></h4><p>' . $price . '</p></div></article>';
    }

    private function missingProductDetailsHtml(): string
    {
        return '<!--product-details-area-start --><div class="mt-product-details-area pt-130 pb-100 fix"><div class="container"><div class="sa-product-empty text-center"><h1>Product not found</h1><p>This product is unavailable or has been removed.</p><a class="mt-btn" href="' . app_url('shop') . '"><span>Browse Products</span></a></div></div></div><!-- product-details-area-end -->';
    }

    private function mediaForProduct(int $productId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT f.path FROM product_media pm
             INNER JOIN files f ON f.id = pm.file_id
             WHERE pm.product_id = ?
             ORDER BY FIELD(pm.role, 'primary', 'gallery'), pm.sort_order ASC, pm.id ASC
             LIMIT 8",
            [$productId]
        );

        return array_values(array_filter(array_map(fn (array $row): ?string => self::asset($row['path'] ?? null), $rows)));
    }

    private function shapeProduct(array $row): array
    {
        $salePrice = $row['sale_price'] !== null && (float)$row['sale_price'] > 0 ? (float)$row['sale_price'] : null;
        $regularPrice = (float)($row['regular_price'] ?? 0);
        $saleUnit = self::saleUnitFromProductText($row);
        $vendorName = html_entity_decode((string)($row['store_name'] ?? 'Seller Africa vendor'), ENT_QUOTES, 'UTF-8');
        $vendorSlug = trim((string)($row['store_slug'] ?? ''));
        $internalVendor = self::isInternalSellerAfricaVendor((string)($row['store_name'] ?? ''), $vendorSlug, (string)($row['store_email'] ?? ''));
        $brandOrVendor = $internalVendor ? ($row['brand_name'] ?? '') : (($row['brand_name'] ?? $row['store_name']) ?: 'Seller Africa');

        return [
            'id' => (int)$row['id'],
            'name' => html_entity_decode((string)$row['name'], ENT_QUOTES, 'UTF-8'),
            'slug' => (string)$row['slug'],
            'url' => app_url('product/' . urlencode((string)($row['parent_slug'] ?? $row['slug']))),
            'image' => self::asset($row['image_path'] ?? null),
            'fallbackImage' => app_url('assets/images/product-placeholder.svg'),
            'vendor' => $internalVendor ? '' : $vendorName,
            'vendorSlug' => $internalVendor ? '' : $vendorSlug,
            'vendorUrl' => (!$internalVendor && $vendorSlug !== '') ? app_url('vendors/' . rawurlencode($vendorSlug)) : app_url('vendors'),
            'description' => trim(strip_tags(html_entity_decode((string)($row['description'] ?: $row['short_description'] ?: ''), ENT_QUOTES, 'UTF-8'))),
            'price' => self::money($salePrice ?: $regularPrice, (string)($row['currency'] ?? 'USD')),
            'regularPrice' => $salePrice && $regularPrice > $salePrice ? self::money($regularPrice, (string)($row['currency'] ?? 'USD')) : '',
            'rawPrice' => round($salePrice ?: $regularPrice, 2),
            'regularRawPrice' => $salePrice && $regularPrice > $salePrice ? round($regularPrice, 2) : null,
            'currency' => (string)($row['currency'] ?? 'USD'),
            'badge' => $salePrice && $regularPrice > $salePrice ? 'SALE' : (($row['stock_status'] ?? '') === 'in_stock' ? 'HOT' : 'NEW'),
            'unit' => html_entity_decode((string)($brandOrVendor ?: 'Seller Africa'), ENT_QUOTES, 'UTF-8'),
            'saleUnit' => $saleUnit,
            'weight' => $row['weight'] !== null ? (string)$row['weight'] : '',
            'length' => $row['length'] !== null ? (string)$row['length'] : '',
            'width' => $row['width'] !== null ? (string)$row['width'] : '',
            'height' => $row['height'] !== null ? (string)$row['height'] : '',
            'shippingClass' => (string)($row['shipping_class'] ?? ''),
            'rating' => max(0, min(5, (int)round((float)($row['average_rating'] ?? 0)))),
            'averageRating' => round(max(0, min(5, (float)($row['average_rating'] ?? 0))), 1),
            'stock' => (string)($row['stock_status'] ?? 'in_stock'),
            'sku' => (string)($row['sku'] ?? ''),
            'brand' => html_entity_decode((string)($brandOrVendor ?: 'Seller Africa'), ENT_QUOTES, 'UTF-8'),
            'primaryCategory' => html_entity_decode((string)($row['primary_category_name'] ?? 'Uncategorized'), ENT_QUOTES, 'UTF-8'),
            'primaryCategorySlug' => (string)($row['primary_category_slug'] ?? ''),
            'subscriberRank' => (int)($row['subscriber_rank'] ?? 0),
            'shipsFromUsWarehouse' => (int)($row['ships_from_us_warehouse'] ?? 0) === 1,
            'vendorId' => (int)($row['vendor_id'] ?? 0),
        ];
    }

    private static function saleUnitFromProductText(array $row): string
    {
        $text = strtolower((string)($row['name'] ?? '') . ' ' . (string)($row['short_description'] ?? '') . ' ' . (string)($row['description'] ?? '') . ' ' . (string)($row['shipping_class'] ?? ''));
        $units = [
            'carton' => 'Carton',
            'case' => 'Case',
            'dozen' => 'Dozen',
            'bundle' => 'Bundle',
            'pack' => 'Pack',
            'box' => 'Box',
            'bag' => 'Bag',
            'bottle' => 'Bottle',
            'jar' => 'Jar',
            'tin' => 'Tin',
            'sachet' => 'Sachet',
            'set' => 'Set',
        ];

        foreach ($units as $needle => $label) {
            if (preg_match('/\b' . preg_quote($needle, '/') . 's?\b/', $text)) {
                return $label;
            }
        }

        return 'Piece';
    }

    private function simpleFooterHtml(): string
    {
        $brand = app_branding();
        $brandName = self::escape((string)$brand['name']);
        $description = 'Seller Africa is building the bridge between African producers and global consumers. Through our African and Caribbean Marketplace, logistics network, and U.S. fulfillment services, we help businesses reach new markets while delivering trusted marketplace access to customers worldwide.';
        $columns = [
            'Community' => [
                ['Join Our Community', app_url('join-community')],
                ['Rewards Program', app_url('rewards-program')],
                ['Become a Vendor', app_url('become-a-vendor')],
                ['Become a Distribution Partner', app_url('distribution-partner')],
                ['Vendor Resources', app_url('vendor-resources')],
                ['Help Center', app_url('knowledge-base')],
            ],
            'Company' => [
                ['About Seller Africa', app_url('about')],
                ['Our Vendors', app_url('vendors')],
                ['Careers', app_url('careers')],
                ['Blog & News', app_url('blog-news')],
                ['Gallery', app_url('gallery')],
                ['Contact Us', app_url('contact')],
            ],
            'Support' => [
                ['FAQs', app_url('faqs')],
                ['Shipping & Delivery', app_url('shipping-delivery')],
                ['Returns & Refunds', app_url('returns-refunds')],
                ['Order Tracking', app_url('track-order')],
                ['Customer Support', app_url('customer-support')],
            ],
            'Legal' => [
                ['Terms & Conditions', app_url('terms')],
                ['Privacy Policy', app_url('privacy-policy')],
                ['Vendor Agreement', app_url('vendor-agreement')],
                ['Partner Agreement', app_url('partner-agreement')],
                ['Cookie Policy', app_url('cookie-policy')],
            ],
        ];
        $columnsHtml = '';
        foreach ($columns as $heading => $links) {
            $linksHtml = '';
            foreach ($links as [$label, $url]) {
                $linksHtml .= '<li class="mb-1"><a class="text-white text-decoration-none" href="' . self::escape($url) . '">' . self::escape($label) . '</a></li>';
            }
            $columnsHtml .= '<div class="col-sm-6 col-lg"><strong>' . self::escape((string)$heading) . '</strong><ul class="list-unstyled mb-0 mt-2">' . $linksHtml . '</ul></div>';
        }

        return '<footer class="mtfooter__area bg-dark text-white py-4"><div class="container"><div class="row gy-3 align-items-start">'
            . '<div class="col-lg-3"><strong>' . $brandName . '</strong><p class="mb-0 mt-2">' . self::escape($description) . '</p></div>'
            . $columnsHtml
            . '</div><div class="row mt-3 pt-3 border-top border-secondary"><div class="col-12"><small>&copy; ' . date('Y') . ' ' . $brandName . '. All rights reserved.</small></div></div></div></footer>';
    }

    private function replaceTemplatePublicHeader(string $html, array $config): string
    {
        $replacement = '$1' . $this->templatePublicHeaderHtml((int)($config['cartCount'] ?? 0)) . '<main';

        return preg_replace('/(<body\b[^>]*>).*?<main\b/is', $replacement, $html, 1) ?? $html;
    }

    private function templatePublicHeaderHtml(int $cartCount): string
    {
        $brand = app_branding();
        $brandName = trim((string)($brand['name'] ?? 'Seller Africa')) ?: 'Seller Africa';
        $brandLogo = trim((string)($brand['logo'] ?? ''));
        $logoHtml = $brandLogo !== ''
            ? '<img src="' . self::escape($brandLogo) . '" alt="' . self::escape($brandName) . '">'
            : '<span class="sa-template-public-header__mark" aria-hidden="true"></span><strong>' . self::escape($brandName) . '</strong>';

        $headerHtml = <<<'HTML'
<style>
  .sa-template-public-header{--green:#006b52;--mint:#e7fbf5;--muted:#5f6b67;--radius:.625rem;--page-max:1536px;--page-gutter:clamp(32px,6.25vw,128px);font-family:"DM Sans",system-ui,-apple-system,sans-serif;color:var(--muted);background:#fff}
  .sa-template-public-header *,.sa-template-public-header *::before,.sa-template-public-header *::after{box-sizing:border-box}
  .sa-template-public-header a{color:inherit;text-decoration:none}
  .sa-template-public-header__announce{min-height:38px;display:grid;place-items:center;padding:6px 16px;background:var(--mint);color:#153d35;font-weight:700;text-align:center}
  .sa-template-public-header__bar{position:sticky;top:0;z-index:60;background:rgba(255,255,255,.96);border-bottom:1px solid rgba(231,228,220,.55);backdrop-filter:blur(14px)}
  .sa-template-public-header__nav{width:min(var(--page-max),calc(100% - var(--page-gutter)));min-height:92px;margin:0 auto;display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:32px}
  .sa-template-public-header__logo{display:inline-flex;align-items:center;gap:10px;color:var(--green);font-size:20px;font-weight:900;line-height:1}
  .sa-template-public-header__logo img{display:block;width:auto;max-width:178px;max-height:58px;object-fit:contain}
  .sa-template-public-header__mark{width:38px;height:38px;display:inline-block;border:5px solid #ffb13d;border-radius:calc(var(--radius) * 1.2);box-shadow:-8px 8px 0 -5px #ffb13d}
  .sa-template-public-header__links{display:flex;justify-content:center;align-items:center;gap:clamp(24px,4vw,72px);font-weight:700}
  .sa-template-public-header__links a:hover{color:var(--green)}
  .sa-template-public-header__actions{display:flex;align-items:center;justify-content:flex-end;gap:14px}
  .sa-template-public-header__cart,.sa-template-public-header__btn,.sa-template-public-header__menu{min-height:44px;border:1px solid var(--green);border-radius:var(--radius);background:#fff;color:var(--green);display:inline-flex;align-items:center;justify-content:center}
  .sa-template-public-header__cart{width:46px;position:relative}
  .sa-template-public-header__cart svg{width:22px;height:22px}
  .sa-template-public-header__cart span{position:absolute;right:-1px;top:0;min-width:18px;height:18px;display:grid;place-items:center;border-radius:999px;background:var(--green);color:#fff;font-size:11px;font-weight:900}
  .sa-template-public-header__btn{padding:0 22px;font-weight:800}
  .sa-template-public-header__btn--solid,.sa-template-public-header__btn--solid:visited,.sa-template-public-header__btn--solid:hover,.sa-template-public-header__btn--solid:focus{background:var(--green)!important;border-color:var(--green)!important;color:#fff!important}
  .sa-template-public-header__menu{display:none;width:46px;padding:0}
  .sa-template-public-header__menu span,.sa-template-public-header__menu::before,.sa-template-public-header__menu::after{content:"";display:block;width:18px;height:2px;margin:4px auto;border-radius:999px;background:currentColor}
  @media(max-width:1100px){.sa-template-public-header__announce{font-size:12px}.sa-template-public-header__nav{width:min(100% - 28px,1180px);min-height:78px;grid-template-columns:auto auto}.sa-template-public-header__logo img{max-width:148px}.sa-template-public-header__links{display:none;grid-column:1/-1;width:100%;flex-direction:column;align-items:stretch;gap:0;padding:10px 0 18px}.sa-template-public-header.is-menu-open .sa-template-public-header__links{display:flex}.sa-template-public-header__links a{padding:14px 0;border-top:1px solid #eef0f2}.sa-template-public-header__actions{justify-self:end}.sa-template-public-header__actions .sa-template-public-header__btn{display:none}.sa-template-public-header__menu{display:inline-block}}
</style>
<div class="sa-template-public-header" id="sa-template-public-header">
  <div class="sa-template-public-header__announce"><strong>Free shipping on your first order from our U.S. warehouse</strong></div>
  <header class="sa-template-public-header__bar">
    <nav class="sa-template-public-header__nav">
      <a class="sa-template-public-header__logo" href="__STORE_URL__" aria-label="__BRAND_NAME__ home">__LOGO_HTML__</a>
      <div class="sa-template-public-header__links" id="sa-template-public-menu">
        <a href="__STORE_URL__">Home</a>
        <a href="__SHOP_URL__">Marketplace</a>
        <a href="__VENDORS_URL__">Our Vendors</a>
        <a href="__ABOUT_URL__">About Us</a>
        <a href="__CONTACT_URL__">Contact Us</a>
      </div>
      <div class="sa-template-public-header__actions">
        <a class="sa-template-public-header__cart" href="__CART_URL__" aria-label="Cart">
          <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 8V6a5 5 0 0 1 10 0v2"/><path d="M5 8h14l-1 13H6L5 8Z"/></svg>
          <span data-cart-count>__CART_COUNT__</span>
        </a>
        <a class="sa-template-public-header__btn" href="__LOGIN_URL__">Login</a>
        <a class="sa-template-public-header__btn sa-template-public-header__btn--solid" href="__REGISTER_URL__">Register</a>
        <button class="sa-template-public-header__menu" type="button" aria-controls="sa-template-public-menu" aria-expanded="false" aria-label="Toggle navigation menu"><span></span></button>
      </div>
    </nav>
  </header>
</div>
<script>
  (() => {
    const root = document.getElementById('sa-template-public-header');
    const menu = root?.querySelector('.sa-template-public-header__menu');
    if (!root || !menu) return;
    menu.addEventListener('click', () => {
      const open = root.classList.toggle('is-menu-open');
      menu.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  })();
</script>
HTML;

        return strtr($headerHtml, [
            '__STORE_URL__' => self::escape(app_url('store')),
            '__BRAND_NAME__' => self::escape($brandName),
            '__LOGO_HTML__' => $logoHtml,
            '__SHOP_URL__' => self::escape(app_url('shop')),
            '__VENDORS_URL__' => self::escape(app_url('vendors')),
            '__ABOUT_URL__' => self::escape(app_url('about')),
            '__CONTACT_URL__' => self::escape(app_url('contact')),
            '__CART_URL__' => self::escape(app_url('cart')),
            '__CART_COUNT__' => number_format(max(0, $cartCount)),
            '__LOGIN_URL__' => self::escape(app_url('login')),
            '__REGISTER_URL__' => self::escape(app_url('buyer/register')),
        ]);
    }

    private function removeNewsletterSections(string $html): string
    {
        return preg_replace('/\s*<section\b(?=[^>]*\bmtnewslatter__area\b).*?<\/section>\s*/is', "\n", $html) ?? $html;
    }

    private function publicProductConditions(string $alias = 'p'): array
    {
        $alias = preg_replace('/[^a-z0-9_]+/i', '', $alias) ?: 'p';
        $vendorAlias = 'v';

        return [
            "{$alias}.status = 'active'",
            "NOT EXISTS (SELECT 1 FROM product_attribute_assignments va JOIN product_attributes vt ON vt.id=va.attribute_id WHERE va.product_id={$alias}.id AND vt.slug='sac-variation-options')",
            "({$vendorAlias}.id IS NULL OR (
                LOWER(COALESCE({$vendorAlias}.store_email, '')) <> 'contact@sellerafrica.com'
                AND LOWER(REPLACE(TRIM(COALESCE({$vendorAlias}.store_name, '')), ' ', '')) <> 'sellerafrica'
                AND LOWER(COALESCE({$vendorAlias}.store_slug, '')) NOT IN ('seller-africa', 'seller_africa', 'sellerafrica', 'seller-mall', 'seller_mall', 'sellermall')
            ))",
        ];
    }

    private static function isInternalSellerAfricaVendor(string $storeName = '', string $storeSlug = '', string $storeEmail = ''): bool
    {
        $nameKey = strtolower((string)preg_replace('/[^a-z0-9]+/i', '', $storeName));
        $slugKey = strtolower(trim($storeSlug));
        $emailKey = strtolower(trim($storeEmail));

        return $emailKey === 'contact@sellerafrica.com'
            || $nameKey === 'sellerafrica'
            || in_array($slugKey, ['seller-africa', 'seller_africa', 'sellerafrica', 'seller-mall', 'seller_mall', 'sellermall'], true);
    }

    public function frequentlyBoughtWith(int $productId, string $categorySlug = '', int $limit = 3): array
    {
        if ($productId <= 0) {
            return [];
        }

        $coPurchaseRows = $this->db->fetchAll(
            "SELECT oi2.product_id AS id, COUNT(*) AS times_together
             FROM order_items oi1
             INNER JOIN order_items oi2 ON oi2.order_id = oi1.order_id AND oi2.product_id != oi1.product_id
             INNER JOIN orders o ON o.id = oi1.order_id
             WHERE oi1.product_id = ? AND o.payment_status = 'paid'
             GROUP BY oi2.product_id
             ORDER BY times_together DESC, oi2.product_id DESC
             LIMIT ?",
            [$productId, max(1, min(10, $limit))]
        );

        $ids = array_map(static fn (array $row): int => (int)$row['id'], $coPurchaseRows);

        if ($ids === []) {
            // No real order history yet for this product. Don't fake it with
            // category filler under a "frequently bought together" label -
            // just show nothing until real co-purchase data exists.
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $conditions = $this->publicProductConditions('p');
        $conditions[] = "p.id IN ({$placeholders})";

        $rows = $this->db->fetchAll(
            "SELECT
                p.id, p.name, p.slug, p.regular_price, p.sale_price, p.currency, p.stock_status,
                p.average_rating, p.review_count, p.total_sales, p.short_description, p.description,
                p.sku, p.weight, p.length, p.width, p.height, p.shipping_class, p.brand_id, v.store_name, b.name AS brand_name, f.path AS image_path
             FROM products p
             LEFT JOIN vendors v ON v.id = p.vendor_id
             LEFT JOIN brands b ON b.id = p.brand_id
             LEFT JOIN product_media pm ON pm.id = (
                SELECT pm2.id FROM product_media pm2
                WHERE pm2.product_id = p.id
                ORDER BY FIELD(pm2.role, 'primary', 'gallery'), pm2.sort_order ASC, pm2.id ASC
                LIMIT 1
             )
             LEFT JOIN files f ON f.id = pm.file_id
             WHERE " . implode(' AND ', $conditions),
            $ids
        );

        // Preserve the co-purchase frequency order, not the DB's default row order.
        $rowsById = [];
        foreach ($rows as $row) {
            $rowsById[(int)$row['id']] = $row;
        }
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($rowsById[$id])) {
                $ordered[] = $this->shapeProduct($rowsById[$id]);
            }
        }

        return $ordered;
    }

    public static function money(float $amount, string $currency = 'USD'): string
    {
        $symbol = match (strtoupper($currency)) {
            'NGN' => '₦',
            'GBP' => '£',
            'EUR' => '€',
            'CAD' => 'C$',
            default => '$',
        };

        return $symbol . number_format($amount, $amount > 999 ? 0 : 2);
    }

    public static function asset(mixed $path): ?string
    {
        $path = trim((string)$path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
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
        $path = preg_replace('#^(?:assets/)?uploads/#', 'uploads/', ltrim($path, '/')) ?? $path;

        return str_starts_with($path, '/') ? rtrim(BASE_URL, '/') . $path : app_url($path);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private static function weeklyProductRotationSql(string $idExpression): string
    {
        $daySeed = date('Ymd') . '-marketplace-refresh-2';

        return "CRC32(CONCAT({$idExpression}, '-{$daySeed}'))";
    }
}
