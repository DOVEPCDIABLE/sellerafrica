<?php

declare(strict_types=1);

namespace App;

final class StorefrontContentService
{
    public function __construct(private Database $db)
    {
        $this->ensureTable();
    }

    public function all(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT block_key, block_type, value
             FROM storefront_content_blocks
             ORDER BY id"
        );

        $blocks = [];
        foreach ($rows as $row) {
            $blocks[(string)$row['block_key']] = [
                'type' => (string)$row['block_type'],
                'value' => (string)($row['value'] ?? ''),
            ];
        }

        return $blocks;
    }

    public function save(string $key, string $type, string $value, ?int $userId = null): void
    {
        $key = trim($key);
        $type = trim($type);

        if ($key === '' || !in_array($type, ['text', 'image', 'background'], true)) {
            throw new \InvalidArgumentException('Invalid storefront content block.');
        }

        $this->db->query(
            "INSERT INTO storefront_content_blocks (block_key, block_type, value, updated_by, updated_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE block_type = VALUES(block_type), value = VALUES(value), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)",
            [$key, $type, $value, $userId, sql_now()]
        );
    }

    private function ensureTable(): void
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS storefront_content_blocks (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                block_key VARCHAR(190) NOT NULL UNIQUE,
                block_type ENUM('text', 'image', 'background') NOT NULL,
                value TEXT NULL,
                updated_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_storefront_content_type (block_type),
                INDEX idx_storefront_content_updated_by (updated_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
