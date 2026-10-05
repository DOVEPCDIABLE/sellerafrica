<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/core/bootstrap.php';

use App\AuthService;
use App\ProductDuplicateCleanupService;

header('Content-Type: application/json');

$isAdmin = AuthService::check() && (AuthService::hasRole('super_admin') || AuthService::hasRole('admin'));
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Admin access is required.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST is required.']);
    exit;
}

validateCsrf();

$service = new ProductDuplicateCleanupService(db());
$action = (string)($_POST['action'] ?? 'scan');

try {
    if ($action === 'cleanup') {
        $hashes = $_POST['hashes'] ?? [];
        if (is_string($hashes)) {
            $decoded = json_decode($hashes, true);
            $hashes = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $hashes)));
        }

        echo json_encode([
            'ok' => true,
            'result' => $service->archive(
                is_array($hashes) ? $hashes : [],
                max(1, min(100, (int)($_POST['limit_groups'] ?? 20))),
                (int)($_SESSION['user_id'] ?? 0) ?: null
            ),
        ]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'result' => $service->scan(max(1, min(200, (int)($_POST['limit'] ?? 50)))),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
