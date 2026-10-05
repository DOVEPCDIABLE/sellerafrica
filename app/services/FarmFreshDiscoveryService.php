<?php

declare(strict_types=1);

namespace App;

final class FarmFreshDiscoveryService
{
    public const CATEGORIES = [
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

    public static function farmers(array $filters = [], int $limit = 120): array
    {
        if (!\table_exists('vendor_applications')) {
            return [];
        }

        $params = [];
        $where = [
            "a.application_type = 'farmer'",
            "v.status = 'active'",
            "v.kyc_status = 'approved'",
        ];
        $settingsJoin = \table_exists('farmer_store_settings') ? 'LEFT JOIN farmer_store_settings fss ON fss.vendor_id = v.id' : '';
        $hasSettingsTable = \table_exists('settings');
        $categoryJoin = $hasSettingsTable ? "LEFT JOIN settings fs ON fs.scope = 'farmer' AND fs.scope_id = v.id AND fs.setting_key = 'farm_categories'" : '';
        $categorySelect = $hasSettingsTable ? 'fs.setting_value AS farm_categories' : 'NULL AS farm_categories';
        $categoryGroup = $hasSettingsTable ? 'fs.setting_value' : 'NULL';
        if (\table_exists('farmer_store_settings')) {
            $where[] = 'COALESCE(fss.harvest_available, 1) = 1';
            $where[] = 'COALESCE(fss.vacation_mode, 0) = 0';
        }

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $categorySearch = $hasSettingsTable ? ' OR fs.setting_value LIKE ?' : '';
            $where[] = '(v.store_name LIKE ? OR v.description LIKE ? OR ad.city LIKE ? OR ad.state LIKE ? OR ad.postcode LIKE ?' . $categorySearch . ')';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like);
            if ($hasSettingsTable) {
                $params[] = $like;
            }
        }

        $category = trim((string)($filters['category'] ?? ''));
        if ($category !== '' && in_array($category, self::CATEGORIES, true)) {
            $categorySearch = $hasSettingsTable ? 'fs.setting_value LIKE ? OR ' : '';
            $where[] = '(' . $categorySearch . 'p.name LIKE ? OR p.description LIKE ?)';
            $like = '%' . $category . '%';
            if ($hasSettingsTable) {
                $params[] = $like;
            }
            array_push($params, $like, $like);
        }

        $sql = "SELECT v.id, v.store_name, v.store_slug, v.description, v.store_email, v.store_phone,
                    ad.city, ad.state, ad.postcode, ad.latitude, ad.longitude,
                    lf.path AS logo_path, bf.path AS banner_path,
                    {$categorySelect},
                    COUNT(DISTINCT p.id) AS product_count,
                    GROUP_CONCAT(DISTINCT p.name ORDER BY p.updated_at DESC SEPARATOR ', ') AS produce_names
                FROM vendor_applications a
                INNER JOIN vendors v ON v.id = a.vendor_id
                LEFT JOIN addresses ad ON ad.id = v.address_id
                LEFT JOIN files lf ON lf.id = v.logo_file_id
                LEFT JOIN files bf ON bf.id = v.banner_file_id
                {$categoryJoin}
                LEFT JOIN products p ON p.vendor_id = v.id AND p.status = 'active' AND p.type <> 'variation'
                {$settingsJoin}
                WHERE " . implode(' AND ', $where) . "
                GROUP BY v.id, v.store_name, v.store_slug, v.description, v.store_email, v.store_phone, ad.city, ad.state, ad.postcode, ad.latitude, ad.longitude, lf.path, bf.path, {$categoryGroup}
                ORDER BY v.updated_at DESC, v.id DESC
                LIMIT " . max(1, min(300, $limit));

        return array_map([self::class, 'normalizeFarmer'], self::rows($sql, $params));
    }

    public static function produce(array $filters = [], int $limit = 48): array
    {
        if (!\table_exists('vendor_applications') || !\table_exists('products')) {
            return [];
        }

        $params = [];
        $where = [
            "a.application_type = 'farmer'",
            "v.status = 'active'",
            "v.kyc_status = 'approved'",
            "p.status = 'active'",
            "p.type <> 'variation'",
        ];
        $settingsJoin = \table_exists('farmer_store_settings') ? 'LEFT JOIN farmer_store_settings fss ON fss.vendor_id = v.id' : '';
        $categoryJoin = \table_exists('settings') ? "LEFT JOIN settings fs ON fs.scope = 'farmer' AND fs.scope_id = v.id AND fs.setting_key = 'farm_categories'" : '';
        if (\table_exists('farmer_store_settings')) {
            $where[] = 'COALESCE(fss.harvest_available, 1) = 1';
            $where[] = 'COALESCE(fss.vacation_mode, 0) = 0';
        }

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.name LIKE ? OR p.description LIKE ? OR v.store_name LIKE ? OR ad.city LIKE ? OR ad.state LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $category = trim((string)($filters['category'] ?? ''));
        if ($category !== '' && in_array($category, self::CATEGORIES, true)) {
            $categorySearch = \table_exists('settings') ? 'fs.setting_value LIKE ? OR ' : '';
            $where[] = '(' . $categorySearch . 'p.name LIKE ? OR p.description LIKE ?)';
            $like = '%' . $category . '%';
            if (\table_exists('settings')) {
                $params[] = $like;
            }
            array_push($params, $like, $like);
        }

        $detailsJoin = \table_exists('farmer_produce_details') ? 'LEFT JOIN farmer_produce_details fpd ON fpd.product_id = p.id' : '';
        $unitSelect = \table_exists('farmer_produce_details') ? 'fpd.selling_unit, fpd.harvest_date' : "NULL selling_unit, NULL harvest_date";

        return self::rows(
            "SELECT p.id, p.name, p.slug, p.short_description, p.description, p.regular_price, p.currency, p.stock_quantity,
                    v.store_name, v.store_slug, ad.city, ad.state, pmf.path image_path, {$unitSelect}
             FROM products p
             INNER JOIN vendors v ON v.id = p.vendor_id
             INNER JOIN vendor_applications a ON a.vendor_id = v.id
             LEFT JOIN addresses ad ON ad.id = v.address_id
             {$categoryJoin}
             LEFT JOIN product_media pm ON pm.product_id = p.id AND pm.role = 'primary'
             LEFT JOIN files pmf ON pmf.id = pm.file_id
             {$detailsJoin}
             {$settingsJoin}
             WHERE " . implode(' AND ', $where) . "
             ORDER BY p.updated_at DESC, p.id DESC
             LIMIT " . max(1, min(120, $limit)),
            $params
        );
    }

    public static function assetUrl(?string $path): string
    {
        $path = trim((string)$path);
        if ($path === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }
        return \app_url(ltrim($path, '/'));
    }

    private static function normalizeFarmer(array $row): array
    {
        $categories = json_decode((string)($row['farm_categories'] ?? ''), true);
        if (!is_array($categories)) {
            $categories = [];
        }
        $row['categories'] = array_values(array_intersect(self::CATEGORIES, array_map('strval', $categories)));
        if ($row['categories'] === []) {
            $row['categories'] = self::inferCategories((string)($row['produce_names'] ?? ''));
        }

        $lat = (float)($row['latitude'] ?? 0);
        $lng = (float)($row['longitude'] ?? 0);
        if ($lat === 0.0 || $lng === 0.0) {
            [$lat, $lng] = self::stateCoordinates((string)($row['state'] ?? ''));
        }
        $row['map_lat'] = $lat;
        $row['map_lng'] = $lng;
        $row['location_label'] = trim(implode(', ', array_filter([(string)($row['city'] ?? ''), (string)($row['state'] ?? '')]))) ?: 'Farm location pending';
        return $row;
    }

    private static function inferCategories(string $text): array
    {
        $haystack = strtolower($text);
        $matched = [];
        foreach (self::CATEGORIES as $category) {
            $words = array_filter(preg_split('/[^a-z0-9]+/i', strtolower($category)) ?: []);
            foreach ($words as $word) {
                if (strlen($word) > 3 && str_contains($haystack, $word)) {
                    $matched[] = $category;
                    break;
                }
            }
        }
        return array_slice(array_values(array_unique($matched)), 0, 3);
    }

    private static function stateCoordinates(string $state): array
    {
        $key = strtoupper(trim($state));
        $map = [
            'AL' => [32.8067, -86.7911], 'AK' => [61.3707, -152.4044], 'AZ' => [33.7298, -111.4312],
            'AR' => [34.9697, -92.3731], 'CA' => [36.1162, -119.6816], 'CO' => [39.0598, -105.3111],
            'CT' => [41.5978, -72.7554], 'DE' => [39.3185, -75.5071], 'FL' => [27.7663, -81.6868],
            'GA' => [33.0406, -83.6431], 'IL' => [40.3495, -88.9861], 'MD' => [39.0639, -76.8021],
            'MA' => [42.2302, -71.5301], 'MI' => [43.3266, -84.5361], 'NJ' => [40.2989, -74.5210],
            'NY' => [42.1657, -74.9481], 'NC' => [35.6301, -79.8064], 'OH' => [40.3888, -82.7649],
            'PA' => [40.5908, -77.2098], 'SC' => [33.8569, -80.9450], 'TX' => [31.0545, -97.5635],
            'VA' => [37.7693, -78.1700], 'WA' => [47.4009, -121.4905],
        ];
        $names = ['GEORGIA' => 'GA', 'TEXAS' => 'TX', 'NEW YORK' => 'NY', 'CALIFORNIA' => 'CA', 'FLORIDA' => 'FL'];
        if (isset($names[$key])) {
            $key = $names[$key];
        }
        return $map[$key] ?? [39.8283, -98.5795];
    }

    private static function rows(string $sql, array $params): array
    {
        try {
            return \db()->fetchAll($sql, $params);
        } catch (\Throwable $e) {
            \error_log('FreshRoots discovery query failed: ' . $e->getMessage());
            return [];
        }
    }
}
