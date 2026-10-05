<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/app/core/bootstrap.php';

use App\MigrationService;

header('Content-Type: application/json');

require_super_admin();

function migration_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function migration_service(): MigrationService
{
    return new MigrationService(db());
}

function migration_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        migration_json(['ok' => false, 'message' => 'POST request required.'], 405);
    }

    validateCsrf();
}

function migration_handle(callable $callback): void
{
    try {
        $callback();
    } catch (Throwable $e) {
        error_log('Migration API error: ' . $e->getMessage());
        migration_json(['ok' => false, 'message' => $e->getMessage()], 500);
    }
}
