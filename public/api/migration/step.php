<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

migration_require_post();

migration_handle(static function (): void {
    $runId = (int)($_POST['run_id'] ?? 0);
    if ($runId <= 0) {
        migration_json(['ok' => false, 'message' => 'Migration run is required.'], 422);
    }

    $service = migration_service();
    $summary = $service->processNext($runId);

    migration_json([
        'ok' => true,
        'message' => 'Batch processed.',
        'summary' => $summary,
    ]);
});
