<?php
$settingsPages = $settingsPages ?? [];
$settingPage = $settingPage ?? null;
$settingValues = $settingValues ?? [];
$roleRows = $roleRows ?? [];
$auditRows = $auditRows ?? [];
$auditPagination = $auditPagination ?? [];
$jobRows = $jobRows ?? [];
$jobSummary = $jobSummary ?? [];
$workerRows = $workerRows ?? [];
$hasFileUpload = $settingPage && !empty(array_filter(
    $settingPage['fields'] ?? [],
    static fn (array $field): bool => ($field['type'] ?? '') === 'file'
));
$auditDetailText = static function (?string $json): string {
    return function_exists('audit_detail_text') ? audit_detail_text($json) : '-';
};
$auditPage = max(1, (int)($auditPagination['page'] ?? 1));
$auditTotalPages = max(1, (int)($auditPagination['totalPages'] ?? 1));
$auditPerPage = (int)($auditPagination['perPage'] ?? 50);
$auditListUrl = static function (int $targetPage, ?int $targetPerPage = null) use ($view, $auditPerPage): string {
    $query = ['page' => max(1, $targetPage), 'per_page' => $targetPerPage ?? $auditPerPage];
    if (trim((string)($_GET['q'] ?? '')) !== '') {
        $query['q'] = trim((string)$_GET['q']);
    }
    return app_url('dashboard/' . $view . '?' . http_build_query($query));
};

$settingsLinks = [
    'general-settings' => 'General Settings',
    'store-settings' => 'Store Settings',
    'currency-settings' => 'Currency Settings',
    'tax-settings' => 'Tax Settings',
    'seo-settings' => 'SEO Settings',
    'mail-settings' => 'Mail Settings',
    'security-settings' => 'Security Settings',
    'role-permissions' => 'Role & Permissions',
    'audit-logs' => 'Audit Logs',
    'queue-worker-monitor' => 'Queue & Worker Monitor',
    'audit-hub' => 'Audit Hub',
];

$statusTotals = [];
foreach ($jobSummary as $row) {
    $statusTotals[(string)$row['status']] = (int)$row['total'];
}
?>

<section class="settings-shell">
    <aside class="settings-index panel">
        <div class="panel-header">
            <div>
                <h2>Settings</h2>
                <p>Super-admin control center</p>
            </div>
        </div>
        <nav class="settings-nav" aria-label="Settings pages">
            <?php foreach ($settingsLinks as $key => $label): ?>
                <a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(app_url('dashboard/' . $key)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="settings-content">
        <?php if ($settingPage): ?>
            <form class="panel settings-form" method="post" action="<?= e(app_url('dashboard/' . $view)) ?>"<?= $hasFileUpload ? ' enctype="multipart/form-data"' : '' ?>>
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <div class="panel-header">
                    <div>
                        <h2><?= e($activeLabel) ?></h2>
                        <p><?= e($settingPage['summary']) ?></p>
                    </div>
                    <div class="settings-actions">
                        <?php if ($view === 'mail-settings'): ?>
                            <button class="btn secondary" type="submit" name="action" value="test_mail">Send Test Mail</button>
                        <?php endif; ?>
                        <button class="btn" type="submit">Save Changes</button>
                    </div>
                </div>

                <div class="settings-form-grid">
                    <?php foreach ($settingPage['fields'] as $field): ?>
                        <?php
                        $key = (string)$field['key'];
                        $value = (string)($settingValues[$key] ?? ($field['default'] ?? ''));
                        $type = (string)($field['type'] ?? 'text');
                        $isSecret = !empty($field['secret']);
                        ?>
                        <label class="settings-field <?= in_array($type, ['textarea', 'file'], true) ? 'span-2' : '' ?>">
                            <span><?= e($field['label']) ?></span>
                            <?php if ($type === 'select'): ?>
                                <select name="settings[<?= e($key) ?>]">
                                    <?php foreach (($field['options'] ?? []) as $optionValue => $optionLabel): ?>
                                        <option value="<?= e($optionValue) ?>" <?= (string)$optionValue === $value ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($type === 'textarea'): ?>
                                <textarea name="settings[<?= e($key) ?>]" rows="4"><?= e($value) ?></textarea>
                            <?php elseif ($type === 'file'): ?>
                                <?php if ($value !== ''): ?>
                                    <div class="settings-logo-preview">
                                        <img src="<?= e(app_brand_asset_url($value)) ?>" alt="<?= e($field['label']) ?>">
                                        <small><?= e($value) ?></small>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="settings_file[<?= e($key) ?>]" accept="<?= e($field['accept'] ?? 'image/png,image/jpeg,image/webp') ?>">
                                <input type="hidden" name="settings[<?= e($key) ?>]" value="<?= e($value) ?>">
                            <?php elseif ($isSecret): ?>
                                <input type="<?= e($type) ?>" name="settings[<?= e($key) ?>]" value="" placeholder="<?= $value !== '' ? 'Saved - leave blank to keep current value' : 'Enter value' ?>" autocomplete="new-password">
                            <?php else: ?>
                                <input type="<?= e($type) ?>" name="settings[<?= e($key) ?>]" value="<?= e($value) ?>">
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="settings-note">
                    <strong>Production note</strong>
                    <span>Changes are audit logged and stored in the central settings table under the <?= e($settingPage['group']) ?> scope.</span>
                </div>
            </form>
        <?php elseif ($view === 'role-permissions'): ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Role & Permissions</h2>
                        <p>Role inventory and assigned user counts. Permission matrices can be extended from this foundation.</p>
                    </div>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/audit-hub')) ?>">Audit Role Changes</a>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Role</th><th>Code</th><th>Assigned Users</th><th>Description</th></tr></thead>
                        <tbody>
                        <?php foreach ($roleRows as $role): ?>
                            <tr>
                                <td><strong><?= e($role['name']) ?></strong></td>
                                <td><code><?= e($role['code']) ?></code></td>
                                <td><?= number_format((int)$role['user_count']) ?></td>
                                <td><?= e($role['description'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($roleRows === []): ?>
                            <tr><td colspan="4">No roles found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php elseif (in_array($view, ['audit-logs', 'audit-hub'], true)): ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2><?= $view === 'audit-hub' ? 'Audit Hub' : 'Audit Logs' ?></h2>
                        <p>Trace administrative actions, affected entities, actors, IP addresses and timestamps. Showing <?= number_format((int)($auditPagination['from'] ?? 0)) ?>-<?= number_format((int)($auditPagination['to'] ?? 0)) ?> of <?= number_format((int)($auditPagination['total'] ?? 0)) ?>.</p>
                    </div>
                    <span class="settings-badge"><?= number_format(count($auditRows)) ?> loaded</span>
                </div>
                <form class="admin-searchbar" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                    <input type="search" name="q" value="<?= e($auditPagination['search'] ?? '') ?>" placeholder="Search audit logs by email, action, entity, IP, actor or details">
                    <input type="hidden" name="per_page" value="<?= e($auditPerPage) ?>">
                    <button class="btn secondary" type="submit">Search</button>
                    <?php if (($auditPagination['search'] ?? '') !== ''): ?><a class="btn secondary" href="<?= e(app_url('dashboard/' . $view)) ?>">Clear</a><?php endif; ?>
                </form>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Action</th><th>Actor</th><th>Entity</th><th>Details</th><th>IP</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach ($auditRows as $event): ?>
                            <tr>
                                <td><strong><?= e($event['action']) ?></strong></td>
                                <td><?= e($event['display_name'] ?: ($event['email'] ?: 'System')) ?></td>
                                <td><?= e($event['entity_type'] . ' #' . ($event['entity_id'] ?? '-')) ?></td>
                                <td><?= e($auditDetailText($event['new_values'] ?? null)) ?></td>
                                <td><?= e($event['ip_address'] ?? '-') ?></td>
                                <td><?= e($event['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($auditRows === []): ?>
                            <tr><td colspan="6">No audit events found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ((int)($auditPagination['total'] ?? 0) > 0): ?>
                    <div class="admin-pagination" aria-label="Audit log pagination">
                        <div class="pagination-summary">Page <?= number_format($auditPage) ?> of <?= number_format($auditTotalPages) ?></div>
                        <div class="pagination-actions">
                            <a class="pagination-btn <?= $auditPage <= 1 ? 'disabled' : '' ?>" href="<?= e($auditListUrl(max(1, $auditPage - 1))) ?>">Previous</a>
                            <?php
                            $auditStartPage = max(1, $auditPage - 2);
                            $auditEndPage = min($auditTotalPages, $auditPage + 2);
                            if ($auditEndPage - $auditStartPage < 4) {
                                $auditStartPage = max(1, min($auditStartPage, $auditEndPage - 4));
                                $auditEndPage = min($auditTotalPages, max($auditEndPage, $auditStartPage + 4));
                            }
                            ?>
                            <?php if ($auditStartPage > 1): ?>
                                <a class="pagination-btn" href="<?= e($auditListUrl(1)) ?>">1</a>
                                <?php if ($auditStartPage > 2): ?><span class="pagination-gap">...</span><?php endif; ?>
                            <?php endif; ?>
                            <?php for ($pageNumber = $auditStartPage; $pageNumber <= $auditEndPage; $pageNumber++): ?>
                                <a class="pagination-btn <?= $pageNumber === $auditPage ? 'active' : '' ?>" href="<?= e($auditListUrl($pageNumber)) ?>"><?= number_format($pageNumber) ?></a>
                            <?php endfor; ?>
                            <?php if ($auditEndPage < $auditTotalPages): ?>
                                <?php if ($auditEndPage < $auditTotalPages - 1): ?><span class="pagination-gap">...</span><?php endif; ?>
                                <a class="pagination-btn" href="<?= e($auditListUrl($auditTotalPages)) ?>"><?= number_format($auditTotalPages) ?></a>
                            <?php endif; ?>
                            <a class="pagination-btn <?= $auditPage >= $auditTotalPages ? 'disabled' : '' ?>" href="<?= e($auditListUrl(min($auditTotalPages, $auditPage + 1))) ?>">Next</a>
                        </div>
                        <form class="pagination-size" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                            <?php if (($auditPagination['search'] ?? '') !== ''): ?><input type="hidden" name="q" value="<?= e($auditPagination['search']) ?>"><?php endif; ?>
                            <label><span>Rows</span><select name="per_page" onchange="this.form.submit()">
                                <?php foreach (($auditPagination['perPageOptions'] ?? [25, 50, 100, 200]) as $option): ?>
                                    <option value="<?= e($option) ?>" <?= (int)$option === $auditPerPage ? 'selected' : '' ?>><?= e($option) ?></option>
                                <?php endforeach; ?>
                            </select></label>
                        </form>
                    </div>
                <?php endif; ?>
            </section>
        <?php elseif ($view === 'queue-worker-monitor'): ?>
            <section class="metric-grid settings-metrics" aria-label="Queue status totals">
                <?php foreach (['pending', 'processing', 'failed', 'completed'] as $status): ?>
                    <div class="metric">
                        <span><?= e(ucfirst($status)) ?> Jobs</span>
                        <strong><?= number_format((int)($statusTotals[$status] ?? 0)) ?></strong>
                        <small>Queue health</small>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="dashboard-grid">
                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Queue Jobs</h2>
                            <p>Latest jobs ordered by operational priority.</p>
                        </div>
                        <form method="post" action="<?= e(app_url('dashboard/queue-worker-monitor')) ?>" onsubmit="return confirm('Clear all queued jobs? This cannot be undone.');">
                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                            <input type="hidden" name="action" value="clear_queue">
                            <button class="btn secondary" type="submit">Clear Queue</button>
                        </form>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>ID</th><th>Queue</th><th>Type</th><th>Status</th><th>Attempts</th><th>Created</th></tr></thead>
                            <tbody>
                            <?php foreach ($jobRows as $job): ?>
                                <tr>
                                    <td>#<?= number_format((int)$job['id']) ?></td>
                                    <td><?= e($job['queue_name']) ?></td>
                                    <td><?= e($job['job_type']) ?></td>
                                    <td><span class="settings-badge status-<?= e($job['status']) ?>"><?= e($job['status']) ?></span></td>
                                    <td><?= number_format((int)$job['attempts']) ?>/<?= number_format((int)$job['max_attempts']) ?></td>
                                    <td><?= e($job['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($jobRows === []): ?>
                                <tr><td colspan="6">No jobs found.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <aside class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Worker Heartbeats</h2>
                            <p>Shared-host cron batches and optional daemon activity.</p>
                        </div>
                    </div>
                    <div class="status-list">
                        <?php foreach ($workerRows as $worker): ?>
                            <div class="status-item">
                                <strong><?= e($worker['worker_name']) ?></strong>
                                <span><?= e($worker['queue_name'] . ' | ' . $worker['status'] . ' | last seen ' . $worker['last_seen_at']) ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($workerRows === []): ?>
                            <div class="status-item"><strong>No worker heartbeat yet</strong><span>Shared-host cron can still process queue batches without a daemon.</span></div>
                        <?php endif; ?>
                    </div>
                </aside>
            </section>
        <?php endif; ?>
    </div>
</section>
