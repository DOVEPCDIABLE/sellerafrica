<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/core/bootstrap.php';

use App\StorefrontContentService;

header('Content-Type: application/json');

try {
    $service = new StorefrontContentService(db());

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        validateCsrf();

        if (!class_exists('\App\AuthService') || !\App\AuthService::hasRole('super_admin')) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Super admin access required.']);
            exit;
        }

        $key = (string)($_POST['key'] ?? '');
        $type = (string)($_POST['type'] ?? '');
        $value = (string)($_POST['value'] ?? '');

        $service->save($key, $type, $value, isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
        audit('storefront.content_updated', 'storefront_content', null, [], [
            'key' => $key,
            'type' => $type,
        ]);

        echo json_encode(['ok' => true, 'blocks' => $service->all()]);
        exit;
    }

    echo json_encode(['ok' => true, 'blocks' => $service->all()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
