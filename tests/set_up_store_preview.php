<?php
if (PHP_SAPI !== 'cli') exit(1);
function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function app_url($path) { return '/' . $path; }
function db() {
    return new class {
        public function fetch($sql, $params) { return ['total' => 26]; }
        public function fetchAll($sql, $params) {
            if (!str_contains($sql, 'JOIN users') || !str_contains($sql, 'LIMIT 25 OFFSET 0')) throw new RuntimeException('Unexpected query');
            if ($params !== array_fill(0, 3, '%Example%')) throw new RuntimeException('Search must be parameterized');
            return [['id' => 42, 'store_name' => 'Example <Store>', 'display_name' => 'Example Owner', 'email' => 'long.store.owner.email@example.test', 'status' => 'pending']];
        }
    };
}
$_GET = ['q' => 'Example'];
ob_start();
require __DIR__ . '/../app/views/admin/set-up-store.php';
$html = ob_get_clean();
foreach (['Example &lt;Store&gt;', 'vendor=42#store-products', 'Page 1 of 2', 'page=2', 'Set Up a Store'] as $expected) {
    if (!str_contains($html, $expected)) throw new RuntimeException('Missing ' . $expected);
}
echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="admin-layout"><main class="admin-main"><div class="content">' . $html . '</div></main></body></html>';
