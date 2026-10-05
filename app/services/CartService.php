<?php

namespace App;

final class CartService
{
    private const COOKIE_NAME = 'SA_STOREFRONT_CART';
    private const COOKIE_DAYS = 400;

    public function add(int $productId, int $quantity = 1): array
    {
        $cartKey = $this->cartKey();
        $quantity = max(1, $quantity);
        $this->ensureSchema();
        $this->attachUser($cartKey);
        ProductVariationService::assertPurchasable($productId, $quantity + $this->quantity($productId));
        \db()->query(
            "INSERT INTO storefront_cart_items (cart_key, user_id, product_id, quantity, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                quantity = quantity + VALUES(quantity),
                user_id = COALESCE(VALUES(user_id), user_id),
                updated_at = VALUES(updated_at)",
            [$cartKey, $this->userId(), $productId, $quantity, \sql_now(), \sql_now()]
        );

        return [
            'product_id' => $productId,
            'quantity' => $this->quantity($productId),
            'cart_count' => $this->count(),
        ];
    }

    public function set(int $productId, int $quantity): array
    {
        $cartKey = $this->cartKey();
        $this->ensureSchema();
        $this->attachUser($cartKey);
        if ($quantity <= 0) {
            \db()->query('DELETE FROM storefront_cart_items WHERE cart_key = ? AND product_id = ?', [$cartKey, $productId]);
        } else {
            ProductVariationService::assertPurchasable($productId, $quantity);
            \db()->query(
                "INSERT INTO storefront_cart_items (cart_key, user_id, product_id, quantity, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    quantity = VALUES(quantity),
                    user_id = COALESCE(VALUES(user_id), user_id),
                    updated_at = VALUES(updated_at)",
                [$cartKey, $this->userId(), $productId, $quantity, \sql_now(), \sql_now()]
            );
        }

        return [
            'product_id' => $productId,
            'quantity' => $quantity > 0 ? $quantity : 0,
            'cart_count' => $this->count(),
        ];
    }

    public function remove(int $productId): array
    {
        return $this->set($productId, 0);
    }

    public function items(): array
    {
        $cartKey = $this->cartKey();
        $this->ensureSchema();
        $this->attachUser($cartKey);
        $this->migrateSessionCart($cartKey);

        $rows = \db()->fetchAll(
            'SELECT product_id, quantity FROM storefront_cart_items WHERE cart_key = ? AND quantity > 0 ORDER BY updated_at DESC, id DESC',
            [$cartKey]
        );
        $items = [];
        foreach ($rows as $row) {
            $items[(int)$row['product_id']] = (int)$row['quantity'];
        }

        return $items;
    }

    public function count(): int
    {
        return array_sum($this->items());
    }

    public function clear(): void
    {
        $cartKey = $this->cartKey();
        $this->ensureSchema();
        \db()->query('DELETE FROM storefront_cart_items WHERE cart_key = ?', [$cartKey]);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        unset($_SESSION['storefront_cart']);
    }

    private function quantity(int $productId): int
    {
        $row = \db()->fetch(
            'SELECT quantity FROM storefront_cart_items WHERE cart_key = ? AND product_id = ? LIMIT 1',
            [$this->cartKey(), $productId]
        );

        return (int)($row['quantity'] ?? 0);
    }

    private function cartKey(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $this->ensureSchema();

        $cookieKey = preg_replace('/[^a-f0-9]/', '', strtolower((string)($_COOKIE[self::COOKIE_NAME] ?? '')));
        if (strlen($cookieKey) === 64) {
            $_SESSION['storefront_cart_key'] = $cookieKey;
            $this->refreshCookie($cookieKey);
            return $cookieKey;
        }

        $sessionKey = preg_replace('/[^a-f0-9]/', '', strtolower((string)($_SESSION['storefront_cart_key'] ?? '')));
        if (strlen($sessionKey) === 64) {
            $this->refreshCookie($sessionKey);
            return $sessionKey;
        }

        $userId = $this->userId();
        if ($userId !== null) {
            $existing = \db()->fetch(
                'SELECT cart_key FROM storefront_cart_items WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1',
                [$userId]
            );
            $existingKey = preg_replace('/[^a-f0-9]/', '', strtolower((string)($existing['cart_key'] ?? '')));
            if (strlen($existingKey) === 64) {
                $_SESSION['storefront_cart_key'] = $existingKey;
                $this->refreshCookie($existingKey);
                return $existingKey;
            }
        }

        $cartKey = bin2hex(random_bytes(32));
        $_SESSION['storefront_cart_key'] = $cartKey;
        $this->refreshCookie($cartKey);
        return $cartKey;
    }

    private function ensureSchema(): void
    {
        \db()->pdo()->exec("
            CREATE TABLE IF NOT EXISTS storefront_cart_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cart_key CHAR(64) NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                quantity INT UNSIGNED NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_storefront_cart_key_product (cart_key, product_id),
                INDEX idx_storefront_cart_user (user_id, updated_at),
                INDEX idx_storefront_cart_updated (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function migrateSessionCart(string $cartKey): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $sessionCart = $_SESSION['storefront_cart'] ?? [];
        if (!is_array($sessionCart) || $sessionCart === []) {
            return;
        }

        foreach ($sessionCart as $productId => $quantity) {
            $productId = (int)$productId;
            $quantity = (int)$quantity;
            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }
            \db()->query(
                "INSERT INTO storefront_cart_items (cart_key, user_id, product_id, quantity, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    quantity = GREATEST(quantity, VALUES(quantity)),
                    user_id = COALESCE(VALUES(user_id), user_id),
                    updated_at = VALUES(updated_at)",
                [$cartKey, $this->userId(), $productId, $quantity, \sql_now(), \sql_now()]
            );
        }
        unset($_SESSION['storefront_cart']);
    }

    private function attachUser(string $cartKey): void
    {
        $userId = $this->userId();
        if ($userId === null) {
            return;
        }
        \db()->query(
            'UPDATE storefront_cart_items SET user_id = ?, updated_at = ? WHERE cart_key = ? AND user_id IS NULL',
            [$userId, \sql_now(), $cartKey]
        );
    }

    private function userId(): ?int
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        return $userId > 0 ? $userId : null;
    }

    private function refreshCookie(string $cartKey): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie(self::COOKIE_NAME, $cartKey, [
            'expires' => time() + (self::COOKIE_DAYS * 86400),
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE_NAME] = $cartKey;
    }
}
