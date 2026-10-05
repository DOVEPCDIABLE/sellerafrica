<?php
$automation = $automation ?? [];
$automationStats = $automation['stats'] ?? [];
$queueRows = $automation['queues'] ?? [];
$failedJobRows = $automation['failedJobs'] ?? [];
$emailJobRows = $automation['emailJobs'] ?? [];
$cartRows = $automation['carts'] ?? [];
$cartSummary = $automation['cartSummary'] ?? [];
$cronDefinitions = $automation['cronDefinitions'] ?? [];
$cleanupTargets = $automation['cleanupTargets'] ?? [];
$migrationRuns = $automation['migrationRuns'] ?? [];

$automationLinks = [
    'data-migration' => 'Data Migration',
    'cron-jobs' => 'Cron Jobs',
    'queues' => 'Queues',
    'worker-monitor' => 'Worker Monitor',
    'failed-jobs' => 'Failed Jobs',
    'email-scheduler' => 'Email Scheduler',
    'cart-checker' => 'Cart Checker',
    'cleanup-tasks' => 'Cleanup Tasks',
];

$statusClass = static fn (string $status): string => 'status-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $status) ?? 'unknown');
?>

<section class="settings-shell automation-shell">
    <aside class="settings-index panel">
        <div class="panel-header">
            <div>
                <h2>System Automation</h2>
                <p>Queues, workers, schedules and cleanup health</p>
            </div>
        </div>
        <nav class="settings-nav" aria-label="System automation pages">
            <?php foreach ($automationLinks as $key => $label): ?>
                <a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(app_url('dashboard/' . $key)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="settings-content">
        <section class="metric-grid settings-metrics automation-metrics" aria-label="Automation status totals">
            <div class="metric">
                <span>Pending Jobs</span>
                <strong><?= number_format((int)($automationStats['pending_jobs'] ?? 0)) ?></strong>
                <small>Ready for workers</small>
            </div>
            <div class="metric">
                <span>Failed Jobs</span>
                <strong><?= number_format((int)($automationStats['failed_jobs'] ?? 0)) ?></strong>
                <small>Needs review</small>
            </div>
            <div class="metric">
                <span>Workers</span>
                <strong><?= number_format((int)($automationStats['worker_count'] ?? 0)) ?></strong>
                <small>Heartbeat records</small>
            </div>
            <div class="metric">
                <span>Mail Provider</span>
                <strong><?= e(strtoupper((string)($automationStats['mail_provider'] ?? 'SMTP'))) ?></strong>
                <small>Current transport</small>
            </div>
        </section>

        <?php if ($view === 'cron-jobs'): ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Cron Jobs</h2>
                            <p>Server-level schedules required to keep automation running on shared hosting.</p>
                    </div>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/queues')) ?>">View Queues</a>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Name</th><th>Frequency</th><th>Command / Route</th><th>Purpose</th></tr></thead>
                        <tbody>
                        <?php foreach ($cronDefinitions as $cron): ?>
                            <tr>
                                <td><strong><?= e($cron['name']) ?></strong></td>
                                <td><?= e($cron['frequency']) ?></td>
                                <td><code><?= e($cron['command']) ?></code></td>
                                <td><?= e($cron['purpose']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($view === 'queues'): ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Queues</h2>
                        <p>Queue distribution, backlog and latest activity from system jobs.</p>
                    </div>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/failed-jobs')) ?>">Failed Jobs</a>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Queue</th><th>Total</th><th>Pending</th><th>Processing</th><th>Failed</th><th>Completed</th><th>Next Available</th><th>Last Activity</th></tr></thead>
                        <tbody>
                        <?php foreach ($queueRows as $queue): ?>
                            <tr>
                                <td><strong><?= e($queue['queue_name']) ?></strong></td>
                                <td><?= number_format((int)$queue['total_jobs']) ?></td>
                                <td><?= number_format((int)$queue['pending_jobs']) ?></td>
                                <td><?= number_format((int)$queue['processing_jobs']) ?></td>
                                <td><?= number_format((int)$queue['failed_jobs']) ?></td>
                                <td><?= number_format((int)$queue['completed_jobs']) ?></td>
                                <td><?= e($queue['next_available_at'] ?? '-') ?></td>
                                <td><?= e($queue['last_activity_at'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($queueRows === []): ?>
                            <tr><td colspan="8">No queue jobs found yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($view === 'worker-monitor'): ?>
            <section class="dashboard-grid">
                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Worker Monitor</h2>
                            <p>Shared-host cron batches and optional daemon heartbeat.</p>
                        </div>
                        <a class="btn secondary" href="<?= e(app_url('dashboard/queues')) ?>">Queue Backlog</a>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Worker</th><th>Queue</th><th>Status</th><th>Current Job</th><th>Last Seen</th></tr></thead>
                            <tbody>
                            <?php foreach ($workerRows as $worker): ?>
                                <tr>
                                    <td><strong><?= e($worker['worker_name']) ?></strong></td>
                                    <td><?= e($worker['queue_name']) ?></td>
                                    <td><span class="settings-badge <?= e($statusClass((string)$worker['status'])) ?>"><?= e($worker['status']) ?></span></td>
                                    <td><?= e($worker['current_job_id'] ?? '-') ?></td>
                                    <td><?= e($worker['last_seen_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($workerRows === []): ?>
                                <tr><td colspan="5">No worker heartbeat yet. Shared-host cron can still process queue batches without a daemon.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <aside class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Shared Host Command</h2>
                            <p>Add this as a one-minute cron job in cPanel or your hosting control panel.</p>
                        </div>
                    </div>
                    <div class="automation-command"><code>php <?= e(APP_ROOT) ?>/app/core/cron.php</code></div>
                    <div class="status-list">
                        <div class="status-item"><strong>Processing Jobs</strong><span><?= number_format((int)($automationStats['processing_jobs'] ?? 0)) ?> currently marked processing</span></div>
                        <div class="status-item"><strong>Cron Log</strong><span><?= e((string)($automationStats['cron_log_size'] ?? '0 B')) ?></span></div>
                        <div class="status-item"><strong>Worker Log</strong><span><?= e((string)($automationStats['worker_log_size'] ?? '0 B')) ?></span></div>
                    </div>
                </aside>
            </section>

        <?php elseif ($view === 'failed-jobs'): ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Failed Jobs</h2>
                        <p>Latest failed jobs with attempts and error messages.</p>
                    </div>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/audit-hub')) ?>">Audit Hub</a>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>ID</th><th>Queue</th><th>Type</th><th>Attempts</th><th>Error</th><th>Updated</th></tr></thead>
                        <tbody>
                        <?php foreach ($failedJobRows as $job): ?>
                            <tr>
                                <td>#<?= number_format((int)$job['id']) ?></td>
                                <td><?= e($job['queue_name']) ?></td>
                                <td><strong><?= e($job['job_type']) ?></strong></td>
                                <td><?= number_format((int)$job['attempts']) ?>/<?= number_format((int)$job['max_attempts']) ?></td>
                                <td class="automation-error"><?= e($job['last_error'] ?: '-') ?></td>
                                <td><?= e($job['updated_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($failedJobRows === []): ?>
                            <tr><td colspan="6">No failed jobs found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($view === 'email-scheduler'): ?>
            <section class="dashboard-grid">
                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Email Scheduler</h2>
                            <p>Email queue jobs and configured provider state.</p>
                        </div>
                        <a class="btn secondary" href="<?= e(app_url('dashboard/mail-settings')) ?>">Mail Settings</a>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>ID</th><th>Type</th><th>Status</th><th>Attempts</th><th>Available</th><th>Updated</th></tr></thead>
                            <tbody>
                            <?php foreach ($emailJobRows as $job): ?>
                                <tr>
                                    <td>#<?= number_format((int)$job['id']) ?></td>
                                    <td><strong><?= e($job['job_type']) ?></strong></td>
                                    <td><span class="settings-badge <?= e($statusClass((string)$job['status'])) ?>"><?= e($job['status']) ?></span></td>
                                    <td><?= number_format((int)$job['attempts']) ?>/<?= number_format((int)$job['max_attempts']) ?></td>
                                    <td><?= e($job['available_at']) ?></td>
                                    <td><?= e($job['updated_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($emailJobRows === []): ?>
                                <tr><td colspan="6">No email jobs found yet.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <aside class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Delivery State</h2>
                            <p>Provider and log visibility.</p>
                        </div>
                    </div>
                    <div class="status-list">
                        <div class="status-item"><strong>Provider</strong><span><?= e(strtoupper((string)($automationStats['mail_provider'] ?? 'smtp'))) ?></span></div>
                        <div class="status-item"><strong>Mail Log</strong><span><?= e((string)($automationStats['mail_log_size'] ?? '0 B')) ?></span></div>
                        <div class="status-item"><strong>Email Queue</strong><span><?= number_format(count($emailJobRows)) ?> latest jobs loaded</span></div>
                    </div>
                </aside>
            </section>

        <?php elseif ($view === 'cart-checker'): ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Cart Checker</h2>
                        <p>Active, abandoned and expired cart visibility for recovery automation.</p>
                    </div>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/email-scheduler')) ?>">Email Scheduler</a>
                </div>
                <div class="module-grid automation-summary">
                    <?php foreach (['active', 'abandoned', 'expired', 'converted'] as $status): ?>
                        <?php $summary = $cartSummary[$status] ?? ['total' => 0, 'value_total' => 0, 'last_activity_at' => '-']; ?>
                        <div class="module-link">
                            <strong><?= number_format((int)$summary['total']) ?> <?= e(ucfirst($status)) ?></strong>
                            <span>$<?= number_format((float)$summary['value_total'], 2) ?> value | last <?= e($summary['last_activity_at'] ?? '-') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Cart</th><th>Customer</th><th>Status</th><th>Items</th><th>Total</th><th>Expires</th><th>Updated</th></tr></thead>
                        <tbody>
                        <?php foreach ($cartRows as $cart): ?>
                            <tr>
                                <td>#<?= number_format((int)$cart['id']) ?></td>
                                <td><?= e($cart['email'] ?: ($cart['session_key'] ? 'Guest session' : 'Guest')) ?></td>
                                <td><span class="settings-badge <?= e($statusClass((string)$cart['status'])) ?>"><?= e($cart['status']) ?></span></td>
                                <td><?= number_format((int)$cart['item_count']) ?></td>
                                <td><?= e($cart['currency']) ?> <?= number_format((float)$cart['grand_total'], 2) ?></td>
                                <td><?= e($cart['expires_at'] ?? '-') ?></td>
                                <td><?= e($cart['updated_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($cartRows === []): ?>
                            <tr><td colspan="7">No persisted carts found yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($view === 'cleanup-tasks'): ?>
            <section class="dashboard-grid">
                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Cleanup Tasks</h2>
                            <p>Safe cleanup candidates for jobs, carts, logs and migration records.</p>
                        </div>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Target</th><th>Records</th><th>Risk</th><th>Recommended Handling</th></tr></thead>
                            <tbody>
                            <?php foreach ($cleanupTargets as $target): ?>
                                <tr>
                                    <td><strong><?= e($target['label']) ?></strong></td>
                                    <td><?= number_format((int)$target['count']) ?></td>
                                    <td><span class="settings-badge"><?= e($target['risk']) ?></span></td>
                                    <td><?= e($target['action']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <aside class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Log Sizes</h2>
                            <p>Filesystem logs watched by cleanup policy.</p>
                        </div>
                    </div>
                    <div class="status-list">
                        <div class="status-item"><strong>PHP Errors</strong><span><?= e((string)($automationStats['php_error_log_size'] ?? '0 B')) ?></span></div>
                        <div class="status-item"><strong>Cron Log</strong><span><?= e((string)($automationStats['cron_log_size'] ?? '0 B')) ?></span></div>
                        <div class="status-item"><strong>Worker Log</strong><span><?= e((string)($automationStats['worker_log_size'] ?? '0 B')) ?></span></div>
                        <div class="status-item"><strong>Mail Log</strong><span><?= e((string)($automationStats['mail_log_size'] ?? '0 B')) ?></span></div>
                    </div>
                </aside>
            </section>
        <?php endif; ?>
    </div>
</section>
