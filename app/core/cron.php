<?php
/**
 * Shared-host cron entrypoint.
 *
 * Configure one cron job to run this file every minute:
 * php /path/to/seller_africa/app/core/cron.php
 *
 * It runs the scheduler once, then processes a small queue batch so shared
 * hosting cron can do the work without a persistent daemon.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Forbidden: Cron can only be executed via CLI.");
}

require_once __DIR__ . '/bootstrap.php';

function log_shared_cron(string $message): void {
    $line = '[' . sql_now() . '] CRON: ' . $message . "\n";
    echo $line;
    error_log($line, 3, LOG_PATH . '/cron.log');
}

$php = escapeshellarg(PHP_BINARY ?: 'php');
$scheduler = escapeshellarg(__DIR__ . '/scheduler.php');
$worker = escapeshellarg(__DIR__ . '/worker.php');

$commands = [
    $php . ' ' . $scheduler,
    $php . ' ' . $worker . ' --once --max-jobs=10 --max-runtime=50',
];

foreach ($commands as $command) {
    log_shared_cron('Running: ' . $command);
    passthru($command, $exitCode);
    if ((int)$exitCode !== 0) {
        log_shared_cron('Command failed with exit code ' . (int)$exitCode);
        exit((int)$exitCode);
    }
}

log_shared_cron('Cron cycle completed.');
