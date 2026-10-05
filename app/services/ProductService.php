<?php

namespace App;

final class ProductService
{
    public function featured(): array
    {
        return [];
    }

    public static function normalizeSku(string $sku): string
    {
        $sku = strtoupper(trim($sku));
        $sku = preg_replace('/[^A-Z0-9._-]+/', '-', $sku) ?? '';
        $sku = trim($sku, '.-_');

        return substr($sku, 0, 100);
    }

    public static function requireUniqueSku(string $sku, ?int $ignoreProductId = null): string
    {
        $sku = self::normalizeSku($sku);
        if ($sku === '') {
            throw new \RuntimeException('SKU is required.');
        }

        $params = [$sku];
        $sql = 'SELECT id FROM products WHERE sku = ?';
        if ($ignoreProductId !== null && $ignoreProductId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreProductId;
        }

        if (\db()->fetch($sql . ' LIMIT 1', $params)) {
            throw new \RuntimeException('Another product already uses that SKU.');
        }

        return $sku;
    }

    public static function generatedSku(int $productId, string $name = ''): string
    {
        $namePart = self::normalizeSku($name);
        $namePart = $namePart !== '' ? substr($namePart, 0, 24) . '-' : '';

        return substr('SA-AUTO-' . $namePart . str_pad((string)$productId, 8, '0', STR_PAD_LEFT), 0, 100);
    }

    public static function backfillMissingSkus(): int
    {
        if (!\function_exists('table_exists') || !\table_exists('products')) {
            return 0;
        }

        $rows = \db()->fetchAll(
            "SELECT id, name FROM products WHERE sku IS NULL OR TRIM(sku) = '' ORDER BY id"
        );
        $updated = 0;

        foreach ($rows as $row) {
            $productId = (int)($row['id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $base = self::generatedSku($productId, (string)($row['name'] ?? ''));
            $sku = $base;
            $counter = 2;
            while (\db()->fetch('SELECT id FROM products WHERE sku = ? AND id <> ? LIMIT 1', [$sku, $productId])) {
                $suffix = '-' . $counter++;
                $sku = substr($base, 0, 100 - strlen($suffix)) . $suffix;
            }

            \db()->query('UPDATE products SET sku = ?, updated_at = ? WHERE id = ?', [$sku, \sql_now(), $productId]);
            $updated++;
        }

        return $updated;
    }
}
