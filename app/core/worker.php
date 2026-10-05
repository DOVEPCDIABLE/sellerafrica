<?php
/**
 * File Folder Path: /app/core
 * File Path: /app/core/worker.php
 * Designed by Daniel Pybexai Framework
 * ==============================================================================
 * MULTIVENDOR ERP - BACKGROUND JOB WORKER DAEMON
 * Summary: Processes queued jobs from jobs_queue.
 * Supports daemon mode and shared-host cron mode:
 *   php app/core/worker.php --once --max-jobs=10
 */

declare(strict_types=1);


// Security: Prevent execution from a web browser
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Forbidden: The worker daemon can only be executed via the CLI.");
}

// Allow a reasonable amount of memory for heavy tasks (e.g., generating PDF invoices)
ini_set('memory_limit', '256M');

// Bootstrap the core environment (loads DB, config, autoloader, services)
require_once __DIR__ . '/bootstrap.php';

function log_worker(string $message, string $level = 'INFO'): void {
    $time = sql_now();
    $formattedMessage = "[$time] [$level] WORKER: $message\n";
    echo $formattedMessage;
    error_log($formattedMessage, 3, LOG_PATH . '/worker.log');
}

$db = db();
$args = $argv ?? [];
$runOnce = in_array('--once', $args, true);
$maxJobs = $runOnce ? 1 : 0;
$maxRuntimeSeconds = $runOnce ? 55 : 3600;
$sleepSeconds = $runOnce ? 0 : 3;

foreach ($args as $arg) {
    if (str_starts_with((string)$arg, '--max-jobs=')) {
        $maxJobs = max(1, (int)substr((string)$arg, 11));
    }
    if (str_starts_with((string)$arg, '--max-runtime=')) {
        $maxRuntimeSeconds = max(10, (int)substr((string)$arg, 14));
    }
}

set_time_limit($maxRuntimeSeconds + 10);
log_worker($runOnce ? "Starting Job Queue Worker Cron Run..." : "Starting Job Queue Worker Daemon...");

// Configuration for the worker lifecycle
$startTime = time();
$processedJobs = 0;

while (true) {
    // Check if worker needs to restart (let Supervisor restart it automatically)
    if (time() - $startTime > $maxRuntimeSeconds) {
        log_worker("Max runtime reached. Exiting gracefully for Supervisor restart.");
        exit(0);
    }

    try {
        
        // 1. Fetch the oldest pending job that is ready to run
        // Assuming db()->query returns a PDOStatement or similar
        $jobId = null;
        $stmt = $db->query(
            "SELECT * FROM jobs_queue 
             WHERE status = 'pending' AND available_at <= ? 
             ORDER BY id ASC LIMIT 1",
            [sql_now()]
        );
        
        // Fetch associative array (adjust depending on your \App\Database implementation)
        $job = method_exists($stmt, 'fetch') ? $stmt->fetch(PDO::FETCH_ASSOC) : $stmt;

        if (!$job || empty($job['id'])) {
            if ($runOnce) {
                log_worker("No pending jobs found for this cron run.");
                exit(0);
            }

            sleep($sleepSeconds);
            continue;
        }

        $jobId = (int)$job['id'];
        $queueName = $job['queue_name'];
        
        
        // 2. ATOMIC LOCK: Try to claim the job. 
        // If another worker grabs it first, affected rows will be 0.
        $lockStmt = $db->query(
            "UPDATE jobs_queue SET status = 'processing' WHERE id = ? AND status = 'pending'",
            [$jobId]
        );
        
        $affectedRows = method_exists($lockStmt, 'rowCount') ? $lockStmt->rowCount() : 1; // Fallback if wrapper doesn't support rowCount
        
        if ($affectedRows === 0) {
            // Another worker took this job right before we did. Skip.
            continue;
        }

        log_worker("Processing Job #{$jobId} from queue: [{$queueName}]");

        
        // 3. Decode Payload
        $payload = json_decode($job['payload'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON payload in job #{$jobId}");
        }

        $action = $payload['action'] ?? 'unknown';
        $data = $payload['data'] ?? [];

        // 4. Job Router - Map queue actions to Application Services
        switch ($queueName) {
            case 'emails':
                $result = \App\EmailService::dispatch($action, $data);
                log_worker("Email dispatch completed via " . ($result['provider'] ?? 'configured provider'));
                break;
                
            case 'notifications':
                $result = \App\NotificationService::handle((string)$action, is_array($data) ? $data : []);
                log_worker("Notification dispatch completed: {$action} " . json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                break;

            case 'system':
                if ($action === 'calculate_daily_summaries') {
                    // Example: \App\CommissionService::calculateDaily();
                    log_worker("Calculating daily summaries...");
                }
                break;

            case 'billing':
                if ($action === 'generate_vendor_invoices') {
                    $result = \App\VendorSubscriptionService::processOutstandingPaymentReminders(is_array($data) ? $data : []);
                    log_worker("Vendor invoice reminders processed: " . json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    break;
                }
                if ($action === 'process_vendor_plan_payment_reminders') {
                    $result = \App\VendorSubscriptionService::processOutstandingPaymentReminders(is_array($data) ? $data : []);
                    log_worker("Vendor plan payment reminders processed: " . json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    break;
                }
                break;

            default:
                throw new \Exception("No handler defined for queue: {$queueName}");
        }

        
        // 5. Mark as Completed
        $db->query(
            "UPDATE jobs_queue SET status = 'completed' WHERE id = ?",
            [$jobId]
        );
        
        log_worker("Job #{$jobId} completed successfully.");
        $processedJobs++;

        if ($maxJobs > 0 && $processedJobs >= $maxJobs) {
            log_worker("Processed {$processedJobs} job(s). Exiting cron run.");
            exit(0);
        }

    } catch (\Throwable $e) {
        
        // 6. Handle Failures
        $errorMsg = $e->getMessage();
        log_worker("Job Failed: " . $errorMsg, 'ERROR');

        if (isset($jobId)) {
            // Increment attempts and mark as failed
            $db->query(
                "UPDATE jobs_queue SET status = 'failed', attempts = attempts + 1 WHERE id = ?",
                [$jobId]
            );
        }
        
        if ($runOnce) {
            exit(1);
        }

        sleep($sleepSeconds);
    }

    // Force PHP's garbage collector to run to keep memory usage flat
    gc_collect_cycles();
}
