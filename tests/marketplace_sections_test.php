<?php
declare(strict_types=1);

namespace App {
    final class Database
    {
        public string $sql = '';
        public array $params = [];
        public function fetchAll(string $sql, array $params = []): array
        {
            $this->sql = $sql;
            $this->params = $params;
            return [];
        }
        public function fetch(string $sql, array $params = []): array
        {
            $this->fetchAll($sql, $params);
            return ['total' => 0];
        }
    }
}
namespace {
    function table_exists(string $name): bool { return false; }
    require __DIR__ . '/../app/services/StorefrontTemplateService.php';
    $db = new \App\Database();
    $service = new \App\StorefrontTemplateService($db);
    $expect = static function (bool $condition, string $message): void {
        if (!$condition) throw new \RuntimeException($message);
    };
    foreach (['Hot Deals', 'On Sale', 'Deals and Savings'] as $section) {
        foreach (['products', 'productCount'] as $method) {
            $filters = ['section' => $section, 'sort' => 'price_asc'];
            if ($method === 'products') $service->products(8, '', 'fashion', $filters);
            else $service->productCount('', 'fashion', $filters);
            $expect(str_contains($db->sql, 'p.sale_price < p.regular_price'), "$method lost discount constraint");
            $expect(str_contains($db->sql, 'c_filter.is_active = 1'), 'Inactive category accepted');
            $expect(in_array('fashion', $db->params, true), 'Category filter lost');
        }
    }
    $service->products(8, '', '', ['sort' => 'newest']);
    $expect(str_contains($db->sql, 'ORDER BY p.published_at DESC, p.id DESC'), 'New arrivals order incorrect');
    $service->products(8, '', '', ['section' => 'Best Sellers', 'sort' => 'top_selling']);
    $expect(str_contains($db->sql, 'p.total_sales > 0'), 'Non-selling products in Best Sellers');
    $expect(str_contains($db->sql, 'ORDER BY p.total_sales DESC'), 'Best seller order incorrect');
    $service->products(8, '', '', ['sort' => 'price_asc']);
    $expect(str_contains($db->sql, 'ORDER BY (CASE'), 'Price sort overridden by categories');
    $service->categories(80);
    $expect(str_contains($db->sql, "'uncategorized', 'subscription'"), 'Internal categories visible');
    echo "Marketplace category, section, count and sorting tests passed.\n";
}
