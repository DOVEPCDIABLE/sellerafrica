<?php

declare(strict_types=1);

namespace App;

final class InventoryService
{
    public static function restoreVendorSplit(int $splitId, ?int $actorId = null): void
    {
        if ($splitId <= 0 || !\table_exists('order_items') || !\table_exists('products')) {
            return;
        }

        $items = \db()->fetchAll(
            "SELECT oi.product_id, oi.quantity
             FROM order_items oi
             INNER JOIN order_vendor_splits ovs ON ovs.order_id = oi.order_id AND ovs.vendor_id = oi.vendor_id
             WHERE ovs.id = ? AND oi.item_type = 'product' AND oi.product_id IS NOT NULL",
            [$splitId]
        );

        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $quantity = max(1, (int)($item['quantity'] ?? 1));
            if ($productId <= 0) {
                continue;
            }

            if (\table_exists('product_inventory_movements')) {
                $existing = \db()->fetch(
                    "SELECT id FROM product_inventory_movements
                     WHERE product_id = ? AND reference_type = 'order_vendor_split_cancelled' AND reference_id = ?
                     LIMIT 1",
                    [$productId, $splitId]
                );
                if ($existing) {
                    continue;
                }
            }

            $product = \db()->fetch('SELECT id, manage_stock, stock_quantity FROM products WHERE id = ? FOR UPDATE', [$productId]);
            if (!$product || (int)($product['manage_stock'] ?? 0) !== 1) {
                continue;
            }

            $after = max(0, (int)($product['stock_quantity'] ?? 0)) + $quantity;
            \db()->query(
                "UPDATE products
                 SET stock_quantity = ?, stock_status = 'in_stock', updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?",
                [$after, $productId]
            );

            if (\table_exists('product_inventory_movements')) {
                \db()->query(
                    "INSERT INTO product_inventory_movements
                        (product_id, movement_type, quantity_change, quantity_after, reference_type, reference_id, note, created_by)
                     VALUES (?, 'reservation_release', ?, ?, 'order_vendor_split_cancelled', ?, ?, ?)",
                    [$productId, $quantity, $after, $splitId, 'Restored after vendor order cancellation', $actorId]
                );
            }
        }
    }
}
