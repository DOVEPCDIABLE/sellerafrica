<?php

declare(strict_types=1);

namespace App;

final class CommerceSafetyService
{
    public static function ensureSchema(): void
    {
        self::extendEnum('orders', 'status', [
            'pending',
            'confirmed',
            'processing',
            'on_hold',
            'paid',
            'partially_shipped',
            'shipped',
            'completed',
            'cancelled',
            'failed',
            'refunded',
        ], 'pending');

        self::extendEnum('orders', 'payment_status', [
            'unpaid',
            'payment_pending',
            'paid',
            'payment_failed',
            'payment_cancelled',
            'payment_under_review',
            'authorized',
            'partially_refunded',
            'refunded',
            'failed',
        ], 'unpaid');

        self::extendEnum('payments', 'status', [
            'pending',
            'authorized',
            'paid',
            'failed',
            'cancelled',
            'under_review',
            'refunded',
        ], 'pending');

        self::extendEnum('shipments', 'status', [
            'not_created',
            'creation_pending',
            'shipment_created',
            'pending',
            'label_created',
            'picked_up',
            'in_transit',
            'out_for_delivery',
            'delivered',
            'delivery_failed',
            'failed',
            'return_in_progress',
            'returned',
            'cancelled',
        ], 'not_created');

        self::addColumn('orders', 'shipping_rate_snapshot', 'LONGTEXT NULL AFTER shipping_total');
        self::addColumn('orders', 'paid_amount', 'DECIMAL(19,4) NULL AFTER grand_total');
        self::addColumn('orders', 'payment_currency', 'CHAR(3) NULL AFTER paid_amount');
        self::addColumn('orders', 'payment_reference', 'VARCHAR(190) NULL AFTER payment_currency');
        self::addColumn('orders', 'payment_verified_at', 'DATETIME NULL AFTER payment_reference');
        self::addColumn('shipments', 'creation_key', 'VARCHAR(190) NULL AFTER metadata');
        self::addIndex('shipments', 'idx_shipments_creation_key', 'creation_key');
    }

    public static function log(string $message, array $context = []): void
    {
        $line = '[' . \sql_now() . '] ' . $message;
        if ($context !== []) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        error_log($line);
    }

    private static function addColumn(string $table, string $column, string $definition): void
    {
        if (!\table_exists($table) || self::columnExists($table, $column)) {
            return;
        }

        \db()->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }

    private static function addIndex(string $table, string $index, string $column): void
    {
        if (!\table_exists($table) || !self::columnExists($table, $column)) {
            return;
        }

        $row = \db()->fetch(
            "SELECT COUNT(*) AS total
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?",
            [(string)(\db_config()['database'] ?? ''), $table, $index]
        );
        if ((int)($row['total'] ?? 0) > 0) {
            return;
        }

        \db()->query("ALTER TABLE `{$table}` ADD INDEX `{$index}` (`{$column}`)");
    }

    private static function extendEnum(string $table, string $column, array $values, string $default): void
    {
        if (!\table_exists($table) || !self::columnExists($table, $column)) {
            return;
        }

        $escaped = array_map(static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'", $values);
        \db()->query("ALTER TABLE `{$table}` MODIFY `{$column}` ENUM(" . implode(',', $escaped) . ") NOT NULL DEFAULT ?", [$default]);
    }

    private static function columnExists(string $table, string $column): bool
    {
        $row = \db()->fetch(
            "SELECT COUNT(*) AS total
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [(string)(\db_config()['database'] ?? ''), $table, $column]
        );

        return (int)($row['total'] ?? 0) > 0;
    }
}
