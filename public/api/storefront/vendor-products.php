<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/core/bootstrap.php';

use App\PublicVendorService;

header('Content-Type: application/json');

try {
    $vendorId = max(0, (int)($_GET['vendor_id'] ?? $_POST['vendor_id'] ?? 0));
    $limit = max(1, min(5, (int)($_GET['limit'] ?? $_POST['limit'] ?? 5)));

    if ($vendorId <= 0) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'message' => 'Invalid vendor.',
        ]);
        return;
    }

    $vendor = db()->fetch('SELECT id FROM vendors WHERE id = ? AND status = "active" LIMIT 1', [$vendorId]);
    if (!$vendor) {
        http_response_code(404);
        echo json_encode([
            'ok' => false,
            'message' => 'Vendor not found.',
        ]);
        return;
    }

    $products = (new PublicVendorService())->products($vendorId, $limit);

    echo json_encode([
        'ok' => true,
        'count' => count($products),
        'products' => $products,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Vendor products request failed.',
    ]);
}
