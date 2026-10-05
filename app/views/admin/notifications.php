<?php
$notificationsData = $notificationsData ?? [];
$notificationLinks = [
    'in-app-notifications' => 'In-App Notifications',
    'email-notifications' => 'Email Notifications',
    'sms-notifications' => 'SMS Notifications',
    'push-notifications' => 'Push Notifications',
];
$activeChannel = $notificationsData['active'] ?? [];
$channel = (string)($activeChannel['channel'] ?? 'in_app');
$summary = $notificationsData['summary'][$channel] ?? [];
$rows = $notificationsData['rows'] ?? [];
$templates = $notificationsData['templates'] ?? [];
$jobs = $notificationsData['jobs'] ?? [];
$preferences = $notificationsData['preferences'][$channel] ?? ['total' => 0, 'enabled_total' => 0, 'disabled_total' => 0];
$pushSubscriptionStats = $notificationsData['pushSubscriptionStats'] ?? [];
$statusClass = static fn (string $status): string => 'status-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $status) ?? 'unknown');
$recipientLabel = static function (array $row): string {
    if (($row['display_name'] ?? '') !== '') {
        return (string)$row['display_name'];
    }
    if (($row['user_email'] ?? '') !== '') {
        return (string)$row['user_email'];
    }
    if (($row['recipient_email'] ?? '') !== '') {
        return (string)$row['recipient_email'];
    }
    if (($row['recipient_phone'] ?? '') !== '') {
        return (string)$row['recipient_phone'];
    }

    return 'Broadcast / System';
};
?>

<section class="settings-shell notification-shell">
    <aside class="settings-index panel">
        <div class="panel-header">
            <div>
                <h2>Notifications</h2>
                <p>Delivery channels, templates, jobs and recipient state</p>
            </div>
        </div>
        <nav class="settings-nav" aria-label="Notification pages">
            <?php foreach ($notificationLinks as $key => $label): ?>
                <a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(app_url('dashboard/' . $key)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="settings-content">
        <section class="panel notification-hero">
            <div class="panel-header">
                <div>
                    <h2><?= e($activeChannel['label'] ?? 'Notifications') ?></h2>
                    <p><?= e($activeChannel['description'] ?? 'Manage notification delivery and templates.') ?></p>
                </div>
                <a class="btn secondary" href="<?= e(app_url($channel === 'email' ? 'dashboard/mail-settings' : 'dashboard/queues')) ?>">
                    <?= $channel === 'email' ? 'Mail Settings' : 'Queue Health' ?>
                </a>
            </div>
            <div class="module-grid automation-summary">
                <div class="module-link">
                    <strong><?= e((string)($activeChannel['provider'] ?? 'Provider')) ?></strong>
                    <span>Configured provider</span>
                </div>
                <div class="module-link">
                    <strong><?= number_format((int)($summary['pending'] ?? 0)) ?> Pending</strong>
                    <span>Waiting to be processed</span>
                </div>
                <div class="module-link">
                    <strong><?= number_format((int)(($summary['sent'] ?? 0) + ($summary['delivered'] ?? 0) + ($summary['read'] ?? 0))) ?> Delivered</strong>
                    <span>Sent, delivered or read</span>
                </div>
                <div class="module-link">
                    <strong><?= number_format((int)($summary['failed'] ?? 0)) ?> Failed</strong>
                    <span>Needs review or retry</span>
                </div>
            </div>
        </section>

        <section class="dashboard-grid">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Latest <?= e($activeChannel['label'] ?? 'Notifications') ?></h2>
                        <p>Recent delivery records for this channel.</p>
                    </div>
                    <span class="settings-badge"><?= number_format(count($rows)) ?> loaded</span>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>ID</th><th>Title</th><th>Recipient</th><th>Status</th><th>Priority</th><th>Scheduled</th><th>Updated</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>#<?= number_format((int)$row['id']) ?></td>
                                <td>
                                    <strong><?= e($row['title']) ?></strong>
                                    <?php if (($row['last_error'] ?? '') !== ''): ?>
                                        <span class="notification-error"><?= e($row['last_error']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($recipientLabel($row)) ?></td>
                                <td><span class="settings-badge <?= e($statusClass((string)$row['status'])) ?>"><?= e($row['status']) ?></span></td>
                                <td><?= e($row['priority']) ?></td>
                                <td><?= e($row['scheduled_at'] ?? '-') ?></td>
                                <td><?= e($row['updated_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows === []): ?>
                            <tr><td colspan="7">No notification records found for this channel yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <aside class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Channel Readiness</h2>
                        <p>Operational checks for this channel.</p>
                    </div>
                </div>
                <div class="status-list">
                    <div class="status-item">
                        <strong>Provider</strong>
                        <span><?= e((string)($activeChannel['provider'] ?? 'Not configured')) ?></span>
                    </div>
                    <div class="status-item">
                        <strong>Queue</strong>
                        <span><?= e((string)($activeChannel['queue'] ?? 'notifications')) ?></span>
                    </div>
                    <div class="status-item">
                        <strong>User Preferences</strong>
                        <span><?= number_format((int)($preferences['enabled_total'] ?? 0)) ?> enabled / <?= number_format((int)($preferences['disabled_total'] ?? 0)) ?> disabled</span>
                    </div>
                    <?php if ($channel === 'push'): ?>
                        <?php foreach ($pushSubscriptionStats as $stat): ?>
                            <div class="status-item">
                                <strong><?= e(ucfirst((string)$stat['status'])) ?> subscriptions</strong>
                                <span><?= number_format((int)$stat['total']) ?> total | last seen <?= e($stat['last_seen_at'] ?? '-') ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($pushSubscriptionStats === []): ?>
                            <div class="status-item"><strong>No push subscriptions</strong><span>Users must opt in before push delivery can start.</span></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </aside>
        </section>

        <section class="dashboard-grid lower-dashboard-grid">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Templates</h2>
                        <p>Reusable copy blocks for this notification channel.</p>
                    </div>
                    <span class="settings-badge"><?= number_format(count($templates)) ?> templates</span>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Template</th><th>Key</th><th>Subject</th><th>Status</th><th>Updated</th></tr></thead>
                        <tbody>
                        <?php foreach ($templates as $template): ?>
                            <tr>
                                <td><strong><?= e($template['name']) ?></strong></td>
                                <td><code><?= e($template['template_key']) ?></code></td>
                                <td><?= e($template['subject'] ?? '-') ?></td>
                                <td><span class="settings-badge"><?= ((int)$template['is_active'] === 1) ? 'Active' : 'Disabled' ?></span></td>
                                <td><?= e($template['updated_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($templates === []): ?>
                            <tr><td colspan="5">No templates created for this channel yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <aside class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Queue Jobs</h2>
                        <p>Latest jobs related to this notification channel.</p>
                    </div>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/queues')) ?>">Queues</a>
                </div>
                <div class="status-list">
                    <?php foreach (array_slice($jobs, 0, 8) as $job): ?>
                        <div class="status-item">
                            <strong>#<?= number_format((int)$job['id']) ?> <?= e($job['job_type']) ?></strong>
                            <span><?= e($job['status']) ?> | <?= number_format((int)$job['attempts']) ?>/<?= number_format((int)$job['max_attempts']) ?> attempts | <?= e($job['updated_at']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($jobs === []): ?>
                        <div class="status-item"><strong>No queue jobs</strong><span>This channel has not queued delivery work yet.</span></div>
                    <?php endif; ?>
                </div>
            </aside>
        </section>
    </div>
</section>
