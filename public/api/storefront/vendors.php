<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/core/bootstrap.php';

use App\PublicVendorService;

header('Content-Type: application/json');

try {
    $perPage = max(6, min(25, (int)($_GET['per_page'] ?? $_POST['per_page'] ?? $_GET['limit'] ?? $_POST['limit'] ?? 25)));
    $page = max(1, (int)($_GET['page'] ?? $_POST['page'] ?? 1));

    $service = new PublicVendorService();
    $total = $service->vendorCount();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $vendors = $service->vendors($perPage, ($page - 1) * $perPage);

    echo json_encode([
        'ok' => true,
        'count' => count($vendors),
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => $totalPages,
        'vendors' => $vendors,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Vendors request failed.',
    ]);
}
