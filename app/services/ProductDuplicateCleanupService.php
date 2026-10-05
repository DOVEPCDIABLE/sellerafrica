<?php

declare(strict_types=1);

namespace App;

final class ProductDuplicateCleanupService
{
    public function __construct(private Database $db)
    {
    }

    public function scan(int $limit = 50): array
    {
        $groups = $this->duplicateGroups();
        $limited = array_slice($groups, 0, max(1, $limit));

        return [
            'group_count' => count($groups),
            'duplicate_product_count' => array_sum(array_map(
                static fn (array $group): int => max(0, count($group['products']) - 1),
                $groups
            )),
            'groups' => array_map(fn (array $group): array => $this->presentGroup($group), $limited),
        ];
    }

    public function archive(array $hashes = [], int $limitGroups = 20, ?int $actorUserId = null): array
    {
        $groups = $this->duplicateGroups();
        $hashSet = array_fill_keys(array_filter(array_map('strval', $hashes)), true);
        $selected = [];

        foreach ($groups as $group) {
            if ($hashSet !== [] && !isset($hashSet[$group['hash']])) {
                continue;
            }

            $selected[] = $group;
            if (count($selected) >= max(1, $limitGroups)) {
                break;
            }
        }

        $archivedIds = [];
        $keptIds = [];
        $now = function_exists('sql_now') ? \sql_now() : date('Y-m-d H:i:s');

        $this->db->beginTransaction();
        try {
            foreach ($selected as $group) {
                $keeper = $this->chooseKeeper($group['products']);
                $keptIds[] = (int)$keeper['id'];

                foreach ($group['products'] as $product) {
                    $productId = (int)$product['id'];
                    if ($productId === (int)$keeper['id'] || (string)($product['status'] ?? '') === 'archived') {
                        continue;
                    }

                    $this->db->query(
                        "UPDATE products
                         SET status = 'archived', updated_at = ?
                         WHERE id = ? AND status <> 'archived'",
                        [$now, $productId]
                    );
                    $archivedIds[] = $productId;
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        if (function_exists('audit') && $archivedIds !== []) {
            try {
                \audit(
                    'product_duplicate_cleanup',
                    'products',
                    implode(',', $archivedIds),
                    [],
                    [
                        'archived_ids' => $archivedIds,
                        'kept_ids' => array_values(array_unique($keptIds)),
                    ],
                    $actorUserId
                );
            } catch (\Throwable) {
                // Cleanup should not fail because audit logging is unavailable.
            }
        }

        return [
            'groups_processed' => count($selected),
            'archived_count' => count(array_unique($archivedIds)),
            'archived_ids' => array_values(array_unique($archivedIds)),
            'kept_ids' => array_values(array_unique($keptIds)),
            'scan' => $this->scan(50),
        ];
    }

    private function duplicateGroups(): array
    {
        if (!function_exists('table_exists') || !\table_exists('products')) {
            return [];
        }

        $rows = $this->db->fetchAll(
            "SELECT p.id, p.name, p.slug, p.sku, p.status, p.featured, p.regular_price, p.sale_price,
                    p.currency, p.total_sales, p.average_rating, p.review_count, p.stock_status,
                    p.created_at, p.updated_at, p.published_at, v.store_name,
                    f.path AS image_path
             FROM products p
             LEFT JOIN vendors v ON v.id = p.vendor_id
             LEFT JOIN (
                SELECT product_id, MIN(file_id) AS file_id
                FROM product_media
                WHERE role = 'primary'
                GROUP BY product_id
             ) pm ON pm.product_id = p.id
             LEFT JOIN files f ON f.id = pm.file_id
             WHERE COALESCE(p.status, '') <> 'archived'
             ORDER BY p.name ASC, p.id ASC"
        );

        $groups = [];
        foreach ($rows as $row) {
            $key = self::normalizeName((string)($row['name'] ?? ''));
            if ($key === '') {
                continue;
            }

            $hash = hash('sha256', $key);
            $groups[$hash] ??= [
                'hash' => $hash,
                'key' => $key,
                'display_name' => (string)($row['name'] ?? ''),
                'products' => [],
            ];
            $groups[$hash]['products'][] = $row;
        }

        $groups = array_values(array_filter($groups, static fn (array $group): bool => count($group['products']) > 1));
        usort($groups, static fn (array $a, array $b): int => count($b['products']) <=> count($a['products']));

        return $groups;
    }

    private function presentGroup(array $group): array
    {
        $keeper = $this->chooseKeeper($group['products']);

        return [
            'hash' => $group['hash'],
            'name' => $group['display_name'],
            'count' => count($group['products']),
            'keeper_id' => (int)$keeper['id'],
            'products' => array_map(static function (array $product) use ($keeper): array {
                $imagePath = trim((string)($product['image_path'] ?? ''));

                return [
                    'id' => (int)$product['id'],
                    'name' => (string)($product['name'] ?? ''),
                    'sku' => (string)($product['sku'] ?? ''),
                    'status' => (string)($product['status'] ?? ''),
                    'vendor' => (string)($product['store_name'] ?? 'No vendor'),
                    'price' => strtoupper((string)($product['currency'] ?? 'USD')) . ' ' . number_format((float)($product['sale_price'] ?: $product['regular_price'] ?: 0), 2),
                    'sales' => (int)($product['total_sales'] ?? 0),
                    'rating' => number_format((float)($product['average_rating'] ?? 0), 2),
                    'updated_at' => (string)($product['updated_at'] ?? ''),
                    'image' => $imagePath !== '' && function_exists('app_brand_asset_url') ? \app_brand_asset_url($imagePath) : '',
                    'will_keep' => (int)$product['id'] === (int)$keeper['id'],
                ];
            }, $group['products']),
        ];
    }

    private function chooseKeeper(array $products): array
    {
        usort($products, function (array $a, array $b): int {
            $scoreA = $this->score($a);
            $scoreB = $this->score($b);

            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }

            return (int)$a['id'] <=> (int)$b['id'];
        });

        return $products[0] ?? [];
    }

    private function score(array $product): int
    {
        $score = 0;
        $status = (string)($product['status'] ?? '');
        if ($status === 'active') {
            $score += 100000;
        } elseif ($status === 'pending') {
            $score += 20000;
        } elseif ($status === 'draft') {
            $score += 10000;
        }

        if (trim((string)($product['image_path'] ?? '')) !== '') {
            $score += 5000;
        }

        $score += !empty($product['featured']) ? 1000 : 0;
        $score += min(50000, (int)($product['total_sales'] ?? 0) * 100);
        $score += min(1000, (int)round((float)($product['average_rating'] ?? 0) * 100));
        $score += min(1000, (int)($product['review_count'] ?? 0) * 10);

        return $score;
    }

    private static function normalizeName(string $name): string
    {
        $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim($name);
    }
}
