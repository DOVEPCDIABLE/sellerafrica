<?php
declare(strict_types=1);

namespace App;

final class ProductCategoryService
{
    private const EXCLUDED = ['uncategorized', 'subscription', 'sac-marketplace-subscription-plans'];
    private static array $validity = [];

    public static function options(): array
    {
        return \db()->fetchAll(
            "SELECT id, name FROM categories WHERE is_active=1 AND slug NOT IN ('uncategorized','subscription','sac-marketplace-subscription-plans') ORDER BY name, id"
        );
    }

    public static function valid(mixed $value): bool
    {
        if (!is_scalar($value) || !preg_match('/^[1-9][0-9]*$/', (string)$value)) return false;
        if (array_key_exists((int)$value, self::$validity)) return self::$validity[(int)$value];
        $category = \db()->fetch('SELECT id, slug, is_active FROM categories WHERE id=?', [(int)$value]);
        return self::$validity[(int)$value] = $category && (int)$category['is_active'] === 1 && !in_array($category['slug'], self::EXCLUDED, true);
    }

    public static function requireCategory(mixed $value): int
    {
        if (!self::valid($value)) {
            throw new \RuntimeException('Please select a product category from the list.');
        }
        return (int)$value;
    }

    public static function selected(int $productId): ?int
    {
        $row = \db()->fetch(
            "SELECT c.id FROM product_categories pc JOIN categories c ON c.id=pc.category_id WHERE pc.product_id=? AND c.is_active=1 AND c.slug NOT IN ('uncategorized','subscription','sac-marketplace-subscription-plans') ORDER BY c.id LIMIT 1",
            [$productId]
        );
        return $row ? (int)$row['id'] : null;
    }

    // A changed category replaces the previous grouping; unchanged choices retain secondary labels.
    public static function assign(int $productId, int $categoryId, ?int $previousCategoryId = null): void
    {
        self::requireCategory($categoryId);
        if ($previousCategoryId && $previousCategoryId !== $categoryId) {
            \db()->query('DELETE FROM product_categories WHERE product_id=?', [$productId]);
        }
        \db()->query("DELETE pc FROM product_categories pc JOIN categories c ON c.id=pc.category_id WHERE pc.product_id=? AND c.slug='uncategorized'", [$productId]);
        \db()->query('INSERT IGNORE INTO product_categories (product_id,category_id) VALUES (?,?)', [$productId, $categoryId]);
    }

    public static function inherit(int $parentId): void
    {
        $children = \db()->fetchAll("SELECT id FROM products WHERE parent_product_id=? AND type='variation'", [$parentId]);
        foreach ($children as $child) {
            \db()->query('DELETE FROM product_categories WHERE product_id=?', [$child['id']]);
            \db()->query('INSERT INTO product_categories (product_id,category_id) SELECT ?,category_id FROM product_categories WHERE product_id=?', [$child['id'], $parentId]);
        }
    }

    public static function field(string $name, mixed $selected = null, string $class = ''): void
    {
        echo '<label' . ($class !== '' ? ' class="' . \e($class) . '"' : '') . '><span>Product category *</span><select name="' . \e($name) . '" required><option value="">Select product category</option>';
        foreach (self::options() as $category) {
            echo '<option value="' . (int)$category['id'] . '"' . ((string)$selected === (string)$category['id'] ? ' selected' : '') . '>' . \e(html_entity_decode($category['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '</option>';
        }
        echo '</select></label>';
    }
}
