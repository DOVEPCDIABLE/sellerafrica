<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

migration_require_post();

migration_handle(static function (): void {
    $service = migration_service();
    $runId = $service->createRun($_POST, (int)($_SESSION['user_id'] ?? 0) ?: null);

    audit('migration.run_created', 'migration_run', (string)$runId, [], [
        'source_type' => $_POST['source_type'] ?? 'source_database',
        'table_prefix' => $_POST['table_prefix'] ?? 'hqgs_',
    ]);

    migration_json([
        'ok' => true,
        'message' => 'Migration run created.',
        'summary' => $service->summary($runId),
    ]);
});
