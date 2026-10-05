<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/core/bootstrap.php';

use App\StorefrontTemplateService;

header('Content-Type: application/json');

try {
    $category = trim((string)($_GET['category'] ?? $_POST['category'] ?? ''));
    $label = trim((string)($_GET['label'] ?? $_POST['label'] ?? ''));
    $query = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));
    $filters = [
        'brand' => trim((string)($_GET['brand'] ?? $_POST['brand'] ?? '')),
        'rating' => (int)($_GET['rating'] ?? $_POST['rating'] ?? 0),
        'min_price' => trim((string)($_GET['min_price'] ?? $_POST['min_price'] ?? '')),
        'max_price' => trim((string)($_GET['max_price'] ?? $_POST['max_price'] ?? '')),
        'sort' => trim((string)($_GET['sort'] ?? $_POST['sort'] ?? 'default')),
    ];
    $limit = max(4, min(24, (int)($_GET['limit'] ?? $_POST['limit'] ?? 12)));

    $renderer = new StorefrontTemplateService(db());
    $products = $renderer->products($limit, $query, $category, $filters);
    $total = $renderer->productCount($query, $category, $filters);
    $params = array_filter([
        'category' => $category,
        'q' => $query,
        'brand' => $filters['brand'],
        'rating' => $filters['rating'] > 0 ? (string)$filters['rating'] : '',
        'min_price' => $filters['min_price'],
        'max_price' => $filters['max_price'],
        'sort' => $filters['sort'] !== 'default' ? $filters['sort'] : '',
        'section' => $label !== '' ? $label : '',
    ], static fn (mixed $value): bool => trim((string)$value) !== '');

    echo json_encode([
        'ok' => true,
        'category' => $category,
        'label' => $label,
        'count' => count($products),
        'total' => $total,
        'products' => $products,
        'viewAllUrl' => app_url('shop' . ($params !== [] ? '?' . http_build_query($params) : '')),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Category products could not be loaded.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
