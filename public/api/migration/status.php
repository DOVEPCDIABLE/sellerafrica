<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

migration_handle(static function (): void {
    $service = migration_service();
    $runId = (int)($_GET['run_id'] ?? 0);
    $run = $runId > 0 ? $service->run($runId) : $service->latestRun();

    migration_json([
        'ok' => true,
        'summary' => $run ? $service->summary((int)$run['id']) : null,
    ]);
});
