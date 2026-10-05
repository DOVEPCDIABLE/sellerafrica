<?php
/**
 * File Folder Path: /app/core
 * File Path: /app/core/scheduler.php
 * Designed by Daniel Pybexai Framework
 * ==============================================================================
 * MULTIVENDOR ERP - TASK SCHEDULER (CRON)
 * Summary: This script should be run every minute via server cron.
 * It checks the current time and dispatches recurring background jobs 
 * (like abandoned carts, daily syncs, emails) to the jobs_queue.
 */

declare(strict_types=1);


// Security: Prevent execution from a web browser
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Forbidden: The scheduler can only be executed via the command line interface (CLI).");
}

// Bootstrap the core environment (loads DB, config, autoloader, services)
require_once __DIR__ . '/bootstrap.php';

function log_scheduler(string $message): void {
    $time = sql_now();
    echo "[$time] SCHEDULER: $message\n";
    // Also log to file for persistence
    error_log("[$time] SCHEDULER: $message", 3, LOG_PATH . '/scheduler.log');
}

log_scheduler("Initializing routine checks...");


try {
    $db = db();
    \App\NotificationService::ensureSchema();
    
    // Get current time parameters based on App Timezone
    $now = app_now();
    $currentMinute = (int)$now->format('i');
    $currentHour = (int)$now->format('H');
    $currentDay = (int)$now->format('d');
    $currentDayOfWeek = (int)$now->format('N');

    // Helper to enqueue jobs safely
    $enqueueJob = function(string $queue, string $action, array $data = []) use ($db): void {
        $payload = json_encode(['action' => $action, 'data' => $data], JSON_UNESCAPED_UNICODE);
        $db->query(
            "INSERT INTO jobs_queue (queue_name, payload, status, created_at) VALUES (?, ?, 'pending', ?)",
            [$queue, $payload, sql_now()]
        );
        log_scheduler("Enqueued job: [$queue] => $action");
    };

    $hasRecentJob = function(string $queue, string $action, int $minutes) use ($db): bool {
        $minutes = max(1, $minutes);
        $row = $db->fetch(
            "SELECT COUNT(*) AS total
             FROM jobs_queue
             WHERE queue_name = ?
               AND payload LIKE ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)",
            [$queue, '%"action":"' . $action . '"%']
        );

        return (int)($row['total'] ?? 0) > 0;
    };


    // ==============================================================================
    // 1. EVERY MINUTE TASKS
    // ==============================================================================
    
    // Example: Clean up expired idempotency keys older than 24 hours to keep DB lean
    if ($currentMinute % 15 === 0) { // Run every 15 minutes
        $db->query("DELETE FROM idempotency_keys WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        log_scheduler("Cleaned up expired idempotency keys.");
    }


    // ==============================================================================
    // 2. HOURLY TASKS (Runs strictly at minute 00 of every hour)
    // ==============================================================================
    if ($currentMinute === 0) {
        log_scheduler("Executing Hourly Tasks...");
        
        // Dispatch job to check for abandoned carts (users who added items but didn't checkout)
        $enqueueJob('emails', 'process_abandoned_carts');
    }


    // ==============================================================================
    // 3. DAILY TASKS (Runs strictly at midnight 00:00)
    // ==============================================================================
    if ($currentHour === 0 && $currentMinute === 0) {
        log_scheduler("Executing Daily Midnight Tasks...");

        // Dispatch job to email users who haven't bought anything in 30 days
        $enqueueJob('emails', 'send_inactivity_promos', ['days_inactive' => 30]);

        // Dispatch job to calculate daily affiliate commissions and vendor sales summaries
        $enqueueJob('system', 'calculate_daily_summaries');
        
        // Dispatch job to clean up failed jobs older than 7 days
        $db->query("DELETE FROM jobs_queue WHERE status = 'failed' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    }

    // ==============================================================================
    // 3B. DAILY ADMIN REPORT (Runs at 21:00 app time)
    // ==============================================================================
    if ($currentHour === 21 && $currentMinute === 0) {
        log_scheduler("Executing Daily 9PM Admin Report Task...");

        if (!$hasRecentJob('notifications', 'send_daily_admin_report', 1430)) {
            $enqueueJob('notifications', 'send_daily_admin_report', ['date' => $now->format('Y-m-d')]);
        } else {
            log_scheduler("Skipped duplicate daily admin report job.");
        }

        if (!$hasRecentJob('billing', 'process_vendor_plan_payment_reminders', 1430)) {
            $enqueueJob('billing', 'process_vendor_plan_payment_reminders', ['limit' => 500]);
        } else {
            log_scheduler("Skipped duplicate vendor plan payment reminder job.");
        }
    }


    // ==============================================================================
    // 4. WEEKLY TASKS (Runs Mondays at 09:00)
    // ==============================================================================
    if ($currentDayOfWeek === 1 && $currentHour === 9 && $currentMinute === 0) {
        log_scheduler("Executing Weekly Tasks...");

        // Remind vendors who still need to finish verification. The notification
        // handler also checks a 7-day throttle per vendor email.
        if (!$hasRecentJob('notifications', 'process_pending_kyc_reminders', 360)) {
            $enqueueJob('notifications', 'process_pending_kyc_reminders', ['limit' => 500]);
        } else {
            log_scheduler("Skipped duplicate weekly pending KYC reminder job.");
        }
    }

    // ==============================================================================
    // 4B. WEEKLY ADMIN REPORT (Runs Saturdays at 21:00 app time)
    // ==============================================================================
    if ($currentDayOfWeek === 6 && $currentHour === 21 && $currentMinute === 0) {
        log_scheduler("Executing Saturday 9PM Weekly Admin Report Task...");

        if (!$hasRecentJob('notifications', 'send_weekly_admin_report', 10070)) {
            $enqueueJob('notifications', 'send_weekly_admin_report', ['date' => $now->format('Y-m-d')]);
        } else {
            log_scheduler("Skipped duplicate weekly admin report job.");
        }
    }


    // ==============================================================================
    // 5. MONTHLY TASKS (Runs 1st of the month at midnight)
    // ==============================================================================
    if ($currentDay === 1 && $currentHour === 0 && $currentMinute === 0) {
        log_scheduler("Executing Monthly Tasks...");
        
        // Dispatch job to generate monthly vendor invoices
        $enqueueJob('billing', 'generate_vendor_invoices');
    }

    log_scheduler("Cycle completed successfully.");

} catch (\Throwable $e) {
    $errorMsg = "CRITICAL ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
    log_scheduler($errorMsg);
    // Optionally trigger an alert (e.g., Slack webhook or direct email to admin) here
    exit(1);
}
