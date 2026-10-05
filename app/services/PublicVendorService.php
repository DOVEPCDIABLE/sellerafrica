<?php

declare(strict_types=1);

namespace App;

final class PublicVendorService
{
    public function activeVendorCount(): int
    {
        $row = \db()->fetch(
            "SELECT COUNT(*) AS total
             FROM vendors
             WHERE status = 'active'
               AND LOWER(COALESCE(store_email, '')) <> 'contact@sellerafrica.com'
               AND LOWER(REPLACE(TRIM(COALESCE(store_name, '')), ' ', '')) <> 'sellerafrica'
               AND LOWER(COALESCE(store_slug, '')) NOT IN ('seller-africa', 'seller_africa', 'sellerafrica', 'seller-mall', 'seller_mall', 'sellermall')"
        );

        return (int)($row['total'] ?? 0);
    }

    public function vendorCount(): int
    {
        $row = \db()->fetch(
            "SELECT COUNT(*) AS total
             FROM vendors
             WHERE status = 'active'
               AND LOWER(COALESCE(store_email, '')) <> 'contact@sellerafrica.com'
               AND LOWER(REPLACE(TRIM(COALESCE(store_name, '')), ' ', '')) <> 'sellerafrica'
               AND LOWER(COALESCE(store_slug, '')) NOT IN ('seller-africa', 'seller_africa', 'sellerafrica', 'seller-mall', 'seller_mall', 'sellermall')"
        );

        return (int)($row['total'] ?? 0);
    }

    public function totalVendorCount(): int
    {
        $row = \db()->fetch(
            "SELECT COUNT(*) AS total
             FROM vendors
             WHERE LOWER(COALESCE(store_email, '')) <> 'contact@sellerafrica.com'
               AND LOWER(REPLACE(TRIM(COALESCE(store_name, '')), ' ', '')) <> 'sellerafrica'
               AND LOWER(COALESCE(store_slug, '')) NOT IN ('seller-africa', 'seller_africa', 'sellerafrica', 'seller-mall', 'seller_mall', 'sellermall')"
        );

        return (int)($row['total'] ?? 0);
    }

    public function vendors(int $limit = 120, int $offset = 0): array
    {
        $limit = max(1, min(96, $limit));
        $offset = max(0, $offset);

        return array_map(
            fn (array $row): array => $this->shapeVendor($row),
            \db()->fetchAll(
                "SELECT v.*, lf.path AS logo_path, bf.path AS banner_path,
                        (
                            SELECT f.path
                            FROM files f
                            WHERE f.owner_user_id = v.user_id
                              AND f.mime_type LIKE 'image/%'
                            ORDER BY
                              CASE
                                WHEN LOWER(CONCAT(f.path, ' ', f.original_name)) REGEXP 'logo|avatar|profile|store|cropped' THEN 0
                                WHEN COALESCE(f.width, 0) > 0 AND COALESCE(f.height, 0) > 0 AND f.width <= 800 AND f.height <= 800 THEN 1
                                ELSE 2
                              END,
                              f.id DESC
                            LIMIT 1
                        ) AS fallback_logo_path,
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
                        a.city, a.state, a.country_code,
                        COALESCE(stats.product_count, 0) AS product_count,
                        COALESCE(stats.average_rating, 0) AS average_rating,
                        COALESCE(stats.total_sales, 0) AS total_sales,
                        CASE WHEN COALESCE(lf.path, '') <> '' OR COALESCE((
                            SELECT f.path
                            FROM files f
                            WHERE f.owner_user_id = v.user_id
                              AND f.mime_type LIKE 'image/%'
                            ORDER BY
                              CASE
                                WHEN LOWER(CONCAT(f.path, ' ', f.original_name)) REGEXP 'logo|avatar|profile|store|cropped' THEN 0
                                WHEN COALESCE(f.width, 0) > 0 AND COALESCE(f.height, 0) > 0 AND f.width <= 800 AND f.height <= 800 THEN 1
                                ELSE 2
                              END,
                              f.id DESC
                            LIMIT 1
                        ), '') <> '' THEN 1 ELSE 0 END AS has_logo_asset,
                        CASE WHEN COALESCE(bf.path, '') <> '' OR COALESCE((
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
                        ), '') <> '' THEN 1 ELSE 0 END AS has_banner_asset,
                        CASE WHEN TRIM(COALESCE(v.description, '')) <> '' THEN 1 ELSE 0 END AS has_description_asset
                 FROM vendors v
                 LEFT JOIN files lf ON lf.id = v.logo_file_id
                 LEFT JOIN files bf ON bf.id = v.banner_file_id
                 LEFT JOIN addresses a ON a.id = v.address_id
                 LEFT JOIN (
                    SELECT vendor_id,
                           COUNT(DISTINCT id) AS product_count,
                           AVG(NULLIF(average_rating, 0)) AS average_rating,
                           SUM(total_sales) AS total_sales
                    FROM products
                    WHERE status = 'active' AND NOT EXISTS (SELECT 1 FROM product_attribute_assignments va JOIN product_attributes vt ON vt.id=va.attribute_id WHERE va.product_id=products.id AND vt.slug='sac-variation-options')
                    GROUP BY vendor_id
                 ) stats ON stats.vendor_id = v.id
                 WHERE v.status = 'active'
                   AND LOWER(COALESCE(v.store_email, '')) <> 'contact@sellerafrica.com'
                   AND LOWER(REPLACE(TRIM(COALESCE(v.store_name, '')), ' ', '')) <> 'sellerafrica'
                   AND LOWER(COALESCE(v.store_slug, '')) NOT IN ('seller-africa', 'seller_africa', 'sellerafrica', 'seller-mall', 'seller_mall', 'sellermall')
                 ORDER BY
                    CASE WHEN COALESCE(bf.path, '') <> '' AND bf.height > 0 AND bf.width > bf.height THEN 1 ELSE 0 END DESC,
                    has_logo_asset DESC,
                    has_banner_asset DESC,
                    has_description_asset DESC,
                    product_count DESC,
                    v.updated_at DESC,
                    v.id DESC
                 LIMIT ? OFFSET ?",
                [$limit, $offset]
            )
        );
    }

    public function vendor(string $slug): ?array
    {
        $row = \db()->fetch(
            "SELECT v.*, lf.path AS logo_path, bf.path AS banner_path,
                    (
                        SELECT f.path
                        FROM files f
                        WHERE f.owner_user_id = v.user_id
                          AND f.mime_type LIKE 'image/%'
                        ORDER BY
                          CASE
                            WHEN LOWER(CONCAT(f.path, ' ', f.original_name)) REGEXP 'logo|avatar|profile|store|cropped' THEN 0
                            WHEN COALESCE(f.width, 0) > 0 AND COALESCE(f.height, 0) > 0 AND f.width <= 800 AND f.height <= 800 THEN 1
                            ELSE 2
                          END,
                          f.id DESC
                        LIMIT 1
                    ) AS fallback_logo_path,
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
                    a.address_line1, a.address_line2, a.city, a.state, a.postcode, a.country_code,
                    COALESCE(stats.product_count, 0) AS product_count,
                    COALESCE(stats.average_rating, 0) AS average_rating,
                    COALESCE(stats.total_sales, 0) AS total_sales
             FROM vendors v
             LEFT JOIN files lf ON lf.id = v.logo_file_id
             LEFT JOIN files bf ON bf.id = v.banner_file_id
             LEFT JOIN addresses a ON a.id = v.address_id
             LEFT JOIN (
                SELECT vendor_id,
                       COUNT(DISTINCT id) AS product_count,
                       AVG(NULLIF(average_rating, 0)) AS average_rating,
                       SUM(total_sales) AS total_sales
                FROM products
                WHERE status = 'active' AND NOT EXISTS (SELECT 1 FROM product_attribute_assignments va JOIN product_attributes vt ON vt.id=va.attribute_id WHERE va.product_id=products.id AND vt.slug='sac-variation-options')
                GROUP BY vendor_id
             ) stats ON stats.vendor_id = v.id
             WHERE v.status = 'active'
               AND LOWER(COALESCE(v.store_email, '')) <> 'contact@sellerafrica.com'
               AND LOWER(REPLACE(TRIM(COALESCE(v.store_name, '')), ' ', '')) <> 'sellerafrica'
               AND LOWER(COALESCE(v.store_slug, '')) NOT IN ('seller-africa', 'seller_africa', 'sellerafrica', 'seller-mall', 'seller_mall', 'sellermall')
               AND v.store_slug = ?
             LIMIT 1",
            [$slug]
        );

        return $row ? $this->shapeVendor($row, true) : null;
    }

    public function products(int $vendorId, int $limit = 48): array
    {
        $distribution = DistributorService::allowance($vendorId);
        $limit = max(1, min($distribution ? min(48, (int)$distribution['product_limit']) : 5, $limit));

        return array_map(
            fn (array $row): array => $this->shapeProduct($row),
            \db()->fetchAll(
                "SELECT p.id, p.name, p.slug, p.short_description, p.description, p.regular_price, p.sale_price,
                        p.currency, p.stock_status, p.average_rating, p.review_count, p.total_sales, f.path AS image_path
                 FROM products p
                 LEFT JOIN product_media pm ON pm.id = (
                    SELECT pm2.id FROM product_media pm2
                    WHERE pm2.product_id = p.id
                    ORDER BY FIELD(pm2.role, 'primary', 'gallery'), pm2.sort_order ASC, pm2.id ASC
                    LIMIT 1
                 )
                 LEFT JOIN files f ON f.id = pm.file_id
	                 WHERE p.vendor_id = ? AND p.status = 'active' AND NOT EXISTS (SELECT 1 FROM product_attribute_assignments va JOIN product_attributes vt ON vt.id=va.attribute_id WHERE va.product_id=p.id AND vt.slug='sac-variation-options')
                 ORDER BY p.featured DESC, p.total_sales DESC, p.updated_at DESC, p.id DESC
                 LIMIT ?",
                [$vendorId, $limit]
            )
        );
    }

    private function shapeVendor(array $row, bool $includePrivateContact = false): array
    {
        $location = trim(implode(', ', array_filter([
            (string)($row['city'] ?? ''),
            (string)($row['state'] ?? ''),
            (string)($row['country_code'] ?? ''),
        ])));

        $logo = self::fileUrl((string)(($row['logo_path'] ?? '') ?: ($row['fallback_logo_path'] ?? '')));
        $banner = self::fileUrl((string)(($row['banner_path'] ?? '') ?: ($row['fallback_banner_path'] ?? '')));

        return [
            'id' => (int)$row['id'],
            'name' => html_entity_decode((string)$row['store_name'], ENT_QUOTES, 'UTF-8'),
            'slug' => (string)$row['store_slug'],
            'url' => \app_url('vendors/' . rawurlencode((string)$row['store_slug'])),
            'email' => $includePrivateContact ? (string)($row['store_email'] ?? '') : '',
            'phone' => $includePrivateContact ? (string)($row['store_phone'] ?? '') : '',
            'description' => trim(strip_tags(html_entity_decode((string)($row['description'] ?? ''), ENT_QUOTES, 'UTF-8'))),
            'logo' => $logo,
            'banner' => $banner,
            'has_logo' => $logo !== '',
            'has_banner' => $banner !== '',
            'location' => $location,
            'address' => trim(implode(', ', array_filter([
                (string)($row['address_line1'] ?? ''),
                (string)($row['address_line2'] ?? ''),
                (string)($row['city'] ?? ''),
                (string)($row['state'] ?? ''),
                (string)($row['postcode'] ?? ''),
                (string)($row['country_code'] ?? ''),
            ]))),
            'status' => (string)($row['status'] ?? ''),
            'kyc_status' => (string)($row['kyc_status'] ?? ''),
            'product_count' => (int)($row['product_count'] ?? 0),
            'average_rating' => round((float)($row['average_rating'] ?? 0), 1),
            'total_sales' => (int)($row['total_sales'] ?? 0),
        ];
    }

    private function shapeProduct(array $row): array
    {
        $sale = $row['sale_price'] !== null && (float)$row['sale_price'] > 0 ? (float)$row['sale_price'] : null;
        $regular = (float)($row['regular_price'] ?? 0);
        $image = self::fileUrl((string)($row['image_path'] ?? ''));

        return [
            'id' => (int)$row['id'],
            'name' => html_entity_decode((string)$row['name'], ENT_QUOTES, 'UTF-8'),
            'url' => \app_url('product/' . rawurlencode((string)$row['slug'])),
            'image' => $image,
            'has_image' => $image !== '',
            'description' => trim(strip_tags(html_entity_decode((string)($row['short_description'] ?: $row['description'] ?: ''), ENT_QUOTES, 'UTF-8'))),
            'price' => StorefrontTemplateService::money($sale ?: $regular, (string)($row['currency'] ?? 'USD')),
            'regular_price' => $sale && $regular > $sale ? StorefrontTemplateService::money($regular, (string)($row['currency'] ?? 'USD')) : '',
            'rating' => max(0, min(5, (int)round((float)($row['average_rating'] ?? 0)))),
            'review_count' => (int)($row['review_count'] ?? 0),
            'stock_status' => (string)($row['stock_status'] ?? ''),
        ];
    }

    private static function fileUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $path)) {
            $urlPath = (string)(parse_url($path, PHP_URL_PATH) ?? '');
            $basePath = trim((string)(parse_url(BASE_URL, PHP_URL_PATH) ?? ''), '/');
            $localPathPattern = $basePath !== ''
                ? '#^/' . preg_quote($basePath, '#') . '/((?:public/)?uploads/.+)$#'
                : '#^/((?:public/)?uploads/.+)$#';
            if (preg_match($localPathPattern, $urlPath, $matches) === 1) {
                return \app_url(preg_replace('#^public/#', '', $matches[1]) ?? $matches[1]);
            }

            $localAssetPattern = $basePath !== ''
                ? '#^/' . preg_quote($basePath, '#') . '/public/(assets/.+)$#'
                : '#^/public/(assets/.+)$#';
            if (preg_match($localAssetPattern, $urlPath, $matches) === 1) {
                return \app_url($matches[1]);
            }

            return $path;
        }

        $path = preg_replace('#^/?public/#', '', $path) ?? $path;
        $path = preg_replace('#^(?:assets/)?uploads/#', 'uploads/', ltrim($path, '/')) ?? $path;
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));

        return \app_url($encodedPath);
    }
}
