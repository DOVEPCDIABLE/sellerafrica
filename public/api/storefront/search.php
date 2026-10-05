<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/core/bootstrap.php';

use App\StorefrontTemplateService;

header('Content-Type: application/json');

try {
    $query = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));
    $category = trim((string)($_GET['category'] ?? $_POST['category'] ?? ''));
    $filters = [
        'brand' => trim((string)($_GET['brand'] ?? $_POST['brand'] ?? '')),
        'rating' => (int)($_GET['rating'] ?? $_POST['rating'] ?? 0),
        'min_price' => trim((string)($_GET['min_price'] ?? $_POST['min_price'] ?? '')),
        'max_price' => trim((string)($_GET['max_price'] ?? $_POST['max_price'] ?? '')),
        'sort' => trim((string)($_GET['sort'] ?? $_POST['sort'] ?? 'default')),
    ];
    $perPage = max(8, min(48, (int)($_GET['per_page'] ?? $_POST['per_page'] ?? $_GET['limit'] ?? $_POST['limit'] ?? 16)));
    $page = max(1, (int)($_GET['page'] ?? $_POST['page'] ?? 1));

    $renderer = new StorefrontTemplateService(db());
    $total = $renderer->productCount($query, $category, $filters);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $products = $renderer->products($perPage, $query, $category, $filters, ($page - 1) * $perPage);

    echo json_encode([
        'ok' => true,
        'query' => $query,
        'category' => $category,
        'filters' => $filters,
        'count' => count($products),
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => $totalPages,
        'products' => $products,
        'brands' => $renderer->brands(80, $query, $category),
        'ratingFacets' => $renderer->ratingFacets($query, $category, $filters['brand']),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Search request failed.',
    ]);
}
