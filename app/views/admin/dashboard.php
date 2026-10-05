<?php
$navigation = $navigation ?? require VIEW_PATH . '/partials/admin_navigation.php';
$activeLabel = $activeLabel ?? 'Overview';
$dashboardReportsData = $dashboardReportsData ?? [];
$reportSummary = $dashboardReportsData['summary'] ?? [];
$reportTrend = $dashboardReportsData['trend'] ?? [];
$reportMoney = static fn (mixed $amount, string $currency = 'USD'): string => $currency . ' ' . number_format((float)$amount, 2);
foreach ($navigation as $sectionItems) {
    foreach ($sectionItems as $item) {
        if ($item['key'] === $active) {
            $activeLabel = $item['label'];
            break 2;
        }
    }
}
?>
            <?php if ($view === 'overview'): ?>
                <section class="metric-grid" aria-label="Dashboard metrics">
                    <div class="metric metric-revenue">
                        <span>Monthly Revenue</span>
                        <strong><?= e($reportMoney($stats['paid_revenue'] ?? 0)) ?></strong>
                        <small><?= e($dashboardReportsData['range']['label'] ?? 'Current period') ?></small>
                        <small>Includes <?= e($reportMoney($reportSummary['manage_store_revenue'] ?? 0)) ?> in Manage My Store sales</small>
                    </div>
                    <div class="metric">
                        <span>Products</span>
                        <strong><?= number_format($stats['products']) ?></strong>
                        <small><?= number_format($stats['pending_products']) ?> waiting approval</small>
                    </div>
                    <div class="metric">
                        <span>Total Vendors</span>
                        <strong><?= number_format($stats['vendors']) ?></strong>
                        <small><?= number_format($stats['active_vendors'] ?? 0) ?> active / <?= number_format($stats['pending_vendors']) ?> pending approval</small>
                    </div>
                    <div class="metric">
                        <span>Customers</span>
                        <strong><?= number_format($stats['users']) ?></strong>
                        <small>Buyers, guests and affiliates</small>
                    </div>
                </section>

                <section class="system-strip" aria-label="System status">
                    <a href="<?= e(app_url('dashboard.php?view=audit-hub')) ?>">
                        <strong>Audit System</strong>
                        <span><?= number_format(count($recentAudit)) ?> recent events loaded</span>
                    </a>
                    <a href="<?= e(app_url('dashboard.php?view=queue-worker-monitor')) ?>">
                        <strong>Queue Monitor</strong>
                        <span><?= number_format($stats['failed_jobs']) ?> failed jobs</span>
                    </a>
                    <a href="<?= e(app_url('dashboard.php?view=security-settings')) ?>">
                        <strong>Security</strong>
                        <span>Role guarded dashboard</span>
                    </a>
                </section>

                <section class="dashboard-grid">
                    <div class="panel analytics-panel">
                        <div class="panel-header">
                            <div>
                                <h2>Revenue Overview</h2>
                                <p>Real sales analytics across marketplace, vendor and affiliate channels.</p>
                            </div>
                            <a class="btn secondary" href="<?= e(app_url('dashboard/sales-analytics')) ?>">Open Reports</a>
                        </div>
                        <div class="chart-card" aria-label="Revenue chart">
                            <div class="chart-summary">
                                <strong><?= e($reportMoney($stats['gross_sales'] ?? 0)) ?></strong>
                                <span>Gross order value in selected period</span>
                            </div>
                            <div class="bar-chart">
                                <?php foreach (array_slice($reportTrend, -14) as $point): ?>
                                    <i style="--h: <?= e((int)($point['height'] ?? 4)) ?>%" title="<?= e(($point['report_date'] ?? '') . ' | ' . $reportMoney($point['gross_sales'] ?? 0)) ?>"></i>
                                <?php endforeach; ?>
                                <?php if ($reportTrend === []): ?><i style="--h: 4%"></i><?php endif; ?>
                            </div>
                        </div>
                        <div class="module-grid">
                            <a class="module-link" href="<?= e(app_url('dashboard.php?view=pending-vendors')) ?>"><strong><?= number_format($stats['pending_vendors']) ?> Pending Vendors</strong><span>KYC and store approvals</span></a>
                            <a class="module-link" href="<?= e(app_url('dashboard.php?view=pending-products')) ?>"><strong><?= number_format($stats['pending_products']) ?> Pending Products</strong><span>Catalog approval queue</span></a>
                            <?php if (\App\AuthService::hasRole('super_admin')): ?><a class="module-link" href="<?= e(app_url('dashboard.php?view=failed-payments')) ?>"><strong><?= number_format($stats['failed_payments']) ?> Failed Payments</strong><span>Gateway and retry review</span></a><?php endif; ?>
                        </div>
                    </div>

                    <aside class="panel performance-panel">
                        <div class="panel-header">
                            <div>
                                <h2>Sales Performance</h2>
                                <p>Live conversion and marketplace health.</p>
                            </div>
                        </div>
                        <div class="radial-card">
                            <div class="radial-meter"><strong>80%</strong><span>Sales Goal</span></div>
                            <div class="mini-stats">
                                <span><strong><?= number_format($stats['orders']) ?></strong> Orders</span>
                                <span><strong><?= e($reportMoney($stats['average_order_value'] ?? 0)) ?></strong> AOV</span>
                            </div>
                        </div>
                        <div class="status-list">
                            <div class="status-item"><strong>Inventory Status</strong><span><?= number_format($stats['pending_products']) ?> products need approval</span></div>
                            <div class="status-item"><strong>Pending Withdrawals</strong><span>Vendor and affiliate payout queue</span></div>
                        </div>
                    </aside>
                </section>

                <section class="dashboard-grid lower-dashboard-grid">
                    <div class="panel">
                        <div class="panel-header">
                            <div>
                                <h2>Recent Transactions</h2>
                                <p>Latest operational activity and audit movement.</p>
                            </div>
                            <a class="btn secondary" href="<?= e(app_url('dashboard.php?view=transactions')) ?>">Export</a>
                        </div>
                        <div class="activity-list">
                            <?php if ($recentAudit === []): ?>
                                <div class="activity-item">
                                    <strong>No audit events yet</strong>
                                    <span>Create or update records to populate this feed.</span>
                                </div>
                            <?php endif; ?>
                            <?php foreach ($recentAudit as $event): ?>
                                <div class="activity-item">
                                    <strong><?= e($event['action']) ?></strong>
                                    <span><?= e(($event['display_name'] ?? 'System') . ' on ' . $event['entity_type'] . ' #' . ($event['entity_id'] ?? '-')) ?></span>
                                    <span><?= e($event['created_at']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <aside class="panel">
                        <div class="panel-header">
                            <div>
                                <h2>Quick Actions</h2>
                                <p>Common super-admin workflows.</p>
                            </div>
                        </div>
                        <div class="module-grid compact-actions">
                            <a class="module-link" href="<?= e(app_url('dashboard.php?view=data-migration')) ?>"><strong>Data Migration</strong><span>WordPress import console</span></a>
                            <a class="module-link" href="<?= e(app_url('dashboard.php?view=queue-worker-monitor')) ?>"><strong>Queue Monitor</strong><span><?= number_format($stats['failed_jobs']) ?> failed jobs</span></a>
                            <a class="module-link" href="<?= e(app_url('dashboard.php?view=audit-hub')) ?>"><strong>Audit Hub</strong><span>Trace admin actions</span></a>
                        </div>
                    </aside>
                </section>
            <?php elseif ($view === 'data-migration'): ?>
                <?php require VIEW_PATH . '/admin/migration.php'; ?>
            <?php elseif (in_array($view, $dashboardReportsData['views'] ?? [], true)): ?>
                <?php require VIEW_PATH . '/admin/dashboard_reports.php'; ?>
            <?php elseif (in_array($view, $automation['views'] ?? [], true)): ?>
                <?php require VIEW_PATH . '/admin/automation.php'; ?>
            <?php elseif (in_array($view, $notificationsData['views'] ?? [], true)): ?>
                <?php require VIEW_PATH . '/admin/notifications.php'; ?>
            <?php elseif (in_array($view, $paymentsData['views'] ?? [], true)): ?>
                <?php require VIEW_PATH . '/admin/payments.php'; ?>
            <?php elseif (in_array($view, $withdrawalsData['views'] ?? [], true)): ?>
                <?php require VIEW_PATH . '/admin/withdrawals.php'; ?>
            <?php elseif (in_array($view, $shippingData['views'] ?? [], true)): ?>
                <?php require VIEW_PATH . '/admin/shipping.php'; ?>
            <?php elseif (in_array($view, ['albums', 'gallery', 'blog-posts'], true)): ?>
                <?php require VIEW_PATH . '/admin/content.php'; ?>
            <?php elseif (in_array($view, $affiliateData['views'] ?? [], true)): ?>
                <?php require VIEW_PATH . '/admin/affiliates.php'; ?>
            <?php elseif (in_array($view, $marketingData['views'] ?? [], true)): ?>
                <?php require VIEW_PATH . '/admin/marketing.php'; ?>
            <?php elseif (in_array($view, $refundDisputesData['views'] ?? [], true)): ?>
                <?php require VIEW_PATH . '/admin/refunds_disputes.php'; ?>
            <?php elseif (in_array($view, $usersData['views'] ?? [], true)): ?>
                <?php require VIEW_PATH . '/admin/users.php'; ?>
            <?php elseif ($view === 'set-up-store'): ?>
                <?php require VIEW_PATH . '/admin/set-up-store.php'; ?>
            <?php elseif (in_array($view, $vendorsData['views'] ?? [], true)): ?>
                <?php require VIEW_PATH . '/admin/vendors.php'; ?>
            <?php elseif (in_array($view, $ordersData['views'] ?? [], true)): ?>
                <?php require VIEW_PATH . '/admin/orders.php'; ?>
            <?php elseif (in_array($view, $productsData['views'] ?? [], true)): ?>
                <?php require VIEW_PATH . '/admin/products.php'; ?>
            <?php elseif (in_array($view, $taxonomyData['views'] ?? [], true)): ?>
                <?php require VIEW_PATH . '/admin/taxonomy.php'; ?>
            <?php elseif ($view === 'admin-profile'): ?>
                <?php require VIEW_PATH . '/admin/profile.php'; ?>
            <?php elseif ($settingPage || in_array($view, ['role-permissions', 'audit-logs', 'audit-hub', 'queue-worker-monitor'], true)): ?>
                <?php require VIEW_PATH . '/admin/settings.php'; ?>
            <?php else: ?>
                <section class="placeholder-page">
                    <div class="panel">
                        <div class="panel-header">
                            <div>
                                <h2><?= e($activeLabel) ?></h2>
                                <p>This module is registered in the super-admin navigation and ready for its table/actions view.</p>
                            </div>
                            <a class="btn secondary" href="<?= e(app_url('dashboard.php')) ?>">Overview</a>
                        </div>

                        <?php if (in_array($view, ['audit-logs', 'audit-hub'], true)): ?>
                            <div class="activity-list">
                                <?php foreach ($recentAudit as $event): ?>
                                    <div class="activity-item">
                                        <strong><?= e($event['action']) ?></strong>
                                        <span><?= e(($event['display_name'] ?? 'System') . ' | ' . $event['entity_type'] . ' #' . ($event['entity_id'] ?? '-')) ?></span>
                                        <span><?= e($event['created_at']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($recentAudit === []): ?>
                                    <div class="activity-item"><strong>No audit events found</strong><span>The audit system is installed and waiting for events.</span></div>
                                <?php endif; ?>
                            </div>
                        <?php elseif (in_array($view, ['queues', 'worker-monitor', 'queue-worker-monitor', 'failed-jobs'], true)): ?>
                            <div class="status-list">
                                <div class="status-item">
                                    <strong>Pending Jobs</strong>
                                    <span><?= number_format(dashboard_count('jobs_queue', "status = 'pending'")) ?> jobs waiting</span>
                                </div>
                                <div class="status-item">
                                    <strong>Failed Jobs</strong>
                                    <span><?= number_format($stats['failed_jobs']) ?> jobs need review</span>
                                </div>
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
                        <?php else: ?>
                            <div class="module-grid">
                                <a class="module-link" href="<?= e(app_url('dashboard.php?view=audit-hub')) ?>"><strong>Audit Hub</strong><span>Trace admin actions</span></a>
                                <a class="module-link" href="<?= e(app_url('dashboard.php?view=queue-worker-monitor')) ?>"><strong>Queue Monitor</strong><span>Worker status and failed jobs</span></a>
                                <a class="module-link" href="<?= e(app_url('dashboard.php?view=role-permissions')) ?>"><strong>Role & Permissions</strong><span>Access control foundation</span></a>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
