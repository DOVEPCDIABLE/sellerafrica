<?php
$affiliateData = $affiliateData ?? [];
$affiliateLinks = [
    'affiliates' => 'Affiliates',
    'referral-links' => 'Referral Links',
    'commission-rules' => 'Commission Rules',
    'pending-commissions' => 'Pending Commissions',
    'approved-commissions' => 'Approved Commissions',
    'affiliate-payouts' => 'Affiliate Payouts',
];
$rows = $affiliateData['rows'] ?? [];
$stats = $affiliateData['stats'] ?? [];
$pagination = $affiliateData['pagination'] ?? [];
$ruleEdit = $affiliateData['ruleEdit'] ?? null;
$showRuleForm = (bool)($affiliateData['showRuleForm'] ?? false);
$statusClass = static fn (string $status): string => 'status-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $status) ?? 'unknown');
$money = static fn (mixed $amount, ?string $currency = 'USD'): string => trim((string)($currency ?: 'USD')) . ' ' . number_format((float)$amount, 2);
$page = max(1, (int)($pagination['page'] ?? 1));
$totalPages = max(1, (int)($pagination['totalPages'] ?? 1));
$perPage = (int)($pagination['perPage'] ?? 25);
$listUrl = static function (int $targetPage, ?int $targetPerPage = null) use ($view, $perPage): string {
    $query = ['page' => max(1, $targetPage), 'per_page' => $targetPerPage ?? $perPage];
    if (trim((string)($_GET['q'] ?? '')) !== '') {
        $query['q'] = trim((string)$_GET['q']);
    }

    return app_url('dashboard/' . $view . '?' . http_build_query($query));
};
$statusForm = static function (string $action, array $hidden, array $statuses, string $currentStatus): void {
    ?>
    <form class="inline-action-form" method="post" action="<?= e(app_url('dashboard/' . ($hidden['return_view'] ?? 'affiliates'))) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
        <input type="hidden" name="action" value="<?= e($action) ?>">
        <?php foreach ($hidden as $name => $value): ?>
            <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
        <?php endforeach; ?>
        <select name="status" onchange="this.form.submit()">
            <?php foreach ($statuses as $status => $label): ?>
                <option value="<?= e($status) ?>" <?= $status === $currentStatus ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php
};
?>

<section class="settings-shell affiliate-shell">
    <aside class="settings-index panel">
        <div class="panel-header">
            <div>
                <h2>Affiliate System</h2>
                <p>Affiliates, links, commissions, rules and payout batches</p>
            </div>
        </div>
        <nav class="settings-nav" aria-label="Affiliate pages">
            <?php foreach ($affiliateLinks as $key => $label): ?>
                <a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(app_url('dashboard/' . $key)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="settings-content">
        <section class="metric-grid settings-metrics" aria-label="Affiliate metrics">
            <div class="metric"><span>Affiliates</span><strong><?= number_format((int)($stats['affiliates'] ?? 0)) ?></strong><small><?= number_format((int)($stats['active_affiliates'] ?? 0)) ?> active</small></div>
            <div class="metric"><span>Referral Links</span><strong><?= number_format((int)($stats['referral_links'] ?? 0)) ?></strong><small>Tracked links</small></div>
            <div class="metric"><span>Pending</span><strong><?= number_format((int)($stats['pending_commissions'] ?? 0)) ?></strong><small>Commission review queue</small></div>
            <div class="metric"><span>Unpaid</span><strong><?= e($money($stats['unpaid_commission_amount'] ?? 0, 'USD')) ?></strong><small>Approved commissions</small></div>
        </section>

        <?php if ($view === 'commission-rules'): ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2><?= $showRuleForm ? ($ruleEdit ? 'Edit Commission Rule' : 'Add Commission Rule') : 'Commission Rules' ?></h2>
                        <p>Control default, campaign, affiliate, vendor and category commission behavior.</p>
                    </div>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/commission-rules' . ($showRuleForm ? '' : '?new=1'))) ?>"><?= $showRuleForm ? 'View Rules' : 'Add Rule' ?></a>
                </div>
                <?php if ($showRuleForm): ?>
                    <form class="settings-form" method="post" action="<?= e(app_url('dashboard/commission-rules')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                        <input type="hidden" name="action" value="save_commission_rule">
                        <input type="hidden" name="rule_id" value="<?= e($ruleEdit['id'] ?? 0) ?>">
                        <div class="settings-grid">
                            <label class="settings-field"><span>Name</span><input name="name" value="<?= e($ruleEdit['name'] ?? '') ?>" required></label>
                            <label class="settings-field"><span>Rule Type</span><select name="rule_type"><?php foreach (['global' => 'Global', 'affiliate' => 'Affiliate', 'vendor' => 'Vendor', 'category' => 'Category', 'campaign' => 'Campaign'] as $key => $label): ?><option value="<?= e($key) ?>" <?= ($ruleEdit['rule_type'] ?? 'global') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                            <label class="settings-field"><span>Target ID</span><input type="number" name="target_id" min="0" value="<?= e($ruleEdit['target_id'] ?? '') ?>" placeholder="Optional"></label>
                            <label class="settings-field"><span>Rate Type</span><select name="rate_type"><option value="percentage" <?= ($ruleEdit['rate_type'] ?? 'percentage') === 'percentage' ? 'selected' : '' ?>>Percentage</option><option value="flat" <?= ($ruleEdit['rate_type'] ?? '') === 'flat' ? 'selected' : '' ?>>Flat</option></select></label>
                            <label class="settings-field"><span>Commission Rate</span><input type="number" step="0.0001" min="0" name="commission_rate" value="<?= e($ruleEdit['commission_rate'] ?? '0') ?>"></label>
                            <label class="settings-field"><span>Currency</span><input name="currency" maxlength="3" value="<?= e($ruleEdit['currency'] ?? 'USD') ?>"></label>
                            <label class="settings-field"><span>Minimum Order</span><input type="number" step="0.01" min="0" name="min_order_amount" value="<?= e($ruleEdit['min_order_amount'] ?? '') ?>"></label>
                            <label class="settings-field"><span>Priority</span><input type="number" name="priority" value="<?= e($ruleEdit['priority'] ?? 10) ?>"></label>
                            <label class="settings-field"><span>Starts At</span><input type="datetime-local" name="starts_at" value="<?= e($ruleEdit['starts_at'] ? str_replace(' ', 'T', substr((string)$ruleEdit['starts_at'], 0, 16)) : '') ?>"></label>
                            <label class="settings-field"><span>Ends At</span><input type="datetime-local" name="ends_at" value="<?= e($ruleEdit['ends_at'] ? str_replace(' ', 'T', substr((string)$ruleEdit['ends_at'], 0, 16)) : '') ?>"></label>
                            <label class="settings-field"><span>Status</span><select name="is_active"><option value="1" <?= (int)($ruleEdit['is_active'] ?? 1) === 1 ? 'selected' : '' ?>>Active</option><option value="0" <?= (int)($ruleEdit['is_active'] ?? 1) === 0 ? 'selected' : '' ?>>Inactive</option></select></label>
                            <label class="settings-field settings-field-wide"><span>Notes</span><textarea name="notes" rows="3"><?= e($ruleEdit['notes'] ?? '') ?></textarea></label>
                        </div>
                        <button class="btn primary" type="submit">Save Rule</button>
                    </form>
                <?php else: ?>
                    <form class="admin-searchbar" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                        <input type="search" name="q" value="<?= e($pagination['search'] ?? '') ?>" placeholder="Search rules by name, type or notes">
                        <input type="hidden" name="per_page" value="<?= e($perPage) ?>">
                        <button class="btn secondary" type="submit">Search</button>
                    </form>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Rule</th><th>Scope</th><th>Rate</th><th>Minimum</th><th>Priority</th><th>Status</th><th>Window</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><strong><?= e($row['name']) ?></strong><small><?= e($row['notes'] ?: '-') ?></small></td>
                                    <td><?= e($row['rule_type']) ?><small><?= $row['target_id'] ? 'Target #' . e($row['target_id']) : 'All eligible records' ?></small></td>
                                    <td><strong><?= e($row['rate_type'] === 'percentage' ? number_format((float)$row['commission_rate'], 2) . '%' : $money($row['commission_rate'], $row['currency'])) ?></strong></td>
                                    <td><?= e($row['min_order_amount'] !== null ? $money($row['min_order_amount'], $row['currency']) : '-') ?></td>
                                    <td><?= number_format((int)$row['priority']) ?></td>
                                    <td><span class="settings-badge <?= (int)$row['is_active'] === 1 ? 'status-active' : 'status-failed' ?>"><?= (int)$row['is_active'] === 1 ? 'active' : 'inactive' ?></span></td>
                                    <td><?= e($row['starts_at'] ?: 'Anytime') ?><small><?= e($row['ends_at'] ?: 'No end') ?></small></td>
                                    <td><a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/commission-rules?edit=' . (int)$row['id'])) ?>">Edit</a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($rows === []): ?><tr><td colspan="8">No commission rules found.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2><?= e($affiliateLinks[$view] ?? 'Affiliate System') ?></h2>
                        <p>Showing <?= number_format((int)($pagination['from'] ?? 0)) ?>-<?= number_format((int)($pagination['to'] ?? 0)) ?> of <?= number_format((int)($pagination['total'] ?? 0)) ?> records.</p>
                    </div>
                    <?php if ($view === 'affiliate-payouts'): ?><span class="settings-badge"><?= e($money($stats['paid_payout_amount'] ?? 0, 'USD')) ?> paid</span><?php endif; ?>
                </div>

                <form class="admin-searchbar" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                    <input type="search" name="q" value="<?= e($pagination['search'] ?? '') ?>" placeholder="Search <?= e(strtolower($affiliateLinks[$view] ?? 'affiliate records')) ?>">
                    <input type="hidden" name="per_page" value="<?= e($perPage) ?>">
                    <button class="btn secondary" type="submit">Search</button>
                    <?php if (($pagination['search'] ?? '') !== ''): ?><a class="btn secondary" href="<?= e(app_url('dashboard/' . $view)) ?>">Clear</a><?php endif; ?>
                </form>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <?php if ($view === 'affiliates'): ?>
                            <thead><tr><th>Affiliate</th><th>Code</th><th>Rate</th><th>Traffic</th><th>Earnings</th><th>Status</th><th>Registered</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><strong><?= e($row['display_name'] ?: $row['email']) ?></strong><small><?= e($row['payment_email'] ?: $row['email']) ?></small></td>
                                    <td><code><?= e($row['referral_code']) ?></code><small><?= $row['parent_referral_code'] ? 'Parent: ' . e($row['parent_referral_code']) : 'Direct' ?></small></td>
                                    <td><?= e($row['rate_type']) ?><small><?= e($row['rate_type'] === 'percentage' ? number_format((float)$row['commission_rate'], 2) . '%' : $money($row['commission_rate'])) ?></small></td>
                                    <td><?= number_format((int)$row['visits_count']) ?> visits<small><?= number_format((int)$row['referrals_count']) ?> referrals</small></td>
                                    <td><strong><?= e($money($row['total_earnings'])) ?></strong><small><?= e($money($row['unpaid_earnings'])) ?> unpaid</small></td>
                                    <td><span class="settings-badge <?= e($statusClass((string)$row['status'])) ?>"><?= e($row['status']) ?></span></td>
                                    <td><?= e($row['registered_at'] ?: $row['created_at']) ?></td>
                                    <td><?php $statusForm('update_affiliate_status', ['affiliate_id' => $row['id'], 'return_view' => 'affiliates'], ['pending' => 'Pending', 'active' => 'Active', 'inactive' => 'Inactive', 'rejected' => 'Rejected', 'blocked' => 'Blocked'], (string)$row['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($rows === []): ?><tr><td colspan="8">No affiliates found.</td></tr><?php endif; ?>
                            </tbody>
                        <?php elseif ($view === 'referral-links'): ?>
                            <thead><tr><th>Affiliate</th><th>Campaign</th><th>Token</th><th>Target URL</th><th>Clicks</th><th>Created</th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><strong><?= e($row['display_name'] ?: $row['email']) ?></strong><small><?= e($row['referral_code']) ?></small></td>
                                    <td><?= e($row['campaign_name'] ?: 'Default') ?><small><?= e($row['campaign_slug'] ?: '-') ?></small></td>
                                    <td><code><?= e($row['token'] ?: '-') ?></code></td>
                                    <td class="product-long-text"><a href="<?= e($row['target_url']) ?>" target="_blank" rel="noopener"><?= e($row['target_url']) ?></a></td>
                                    <td><?= number_format((int)$row['clicks_count']) ?></td>
                                    <td><?= e($row['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($rows === []): ?><tr><td colspan="6">No referral links found.</td></tr><?php endif; ?>
                            </tbody>
                        <?php elseif (in_array($view, ['pending-commissions', 'approved-commissions'], true)): ?>
                            <thead><tr><th>Affiliate</th><th>Order</th><th>Reference</th><th>Commission</th><th>Order Total</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><strong><?= e($row['display_name'] ?: $row['email']) ?></strong><small><?= e($row['referral_code']) ?></small></td>
                                    <td><?= $row['order_id'] ? '<a href="' . e(app_url('dashboard/orders?order=' . (int)$row['order_id'])) . '">#' . e($row['order_number'] ?: $row['order_id']) . '</a>' : '-' ?></td>
                                    <td><strong><?= e($row['reference'] ?: 'Commission #' . $row['id']) ?></strong><small><?= e($row['description'] ?: '-') ?></small></td>
                                    <td><strong><?= e($money($row['amount'], $row['currency'])) ?></strong></td>
                                    <td><?= e($row['order_total'] !== null ? $money($row['order_total'], $row['currency']) : '-') ?></td>
                                    <td><span class="settings-badge <?= e($statusClass((string)$row['status'])) ?>"><?= e($row['status']) ?></span></td>
                                    <td><?= e($row['created_at']) ?><small><?= $row['paid_at'] ? 'Paid: ' . e($row['paid_at']) : '' ?></small></td>
                                    <td><?php $statusForm('update_affiliate_referral_status', ['referral_id' => $row['id'], 'return_view' => $view], ['pending' => 'Pending', 'unpaid' => 'Approved', 'paid' => 'Paid', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'], (string)$row['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($rows === []): ?><tr><td colspan="8">No commission records found.</td></tr><?php endif; ?>
                            </tbody>
                        <?php elseif ($view === 'affiliate-payouts'): ?>
                            <thead><tr><th>Affiliate</th><th>Amount</th><th>Method</th><th>Reference</th><th>Commissions</th><th>Status</th><th>Dates</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><strong><?= e($row['display_name'] ?: $row['email']) ?></strong><small><?= e($row['payment_email'] ?: $row['referral_code']) ?></small></td>
                                    <td><strong><?= e($money($row['amount'], $row['currency'])) ?></strong></td>
                                    <td><?= e($row['payment_method'] ?: 'Not set') ?></td>
                                    <td><code><?= e($row['payment_reference'] ?: '-') ?></code></td>
                                    <td><?= number_format((int)$row['referral_count']) ?></td>
                                    <td><span class="settings-badge <?= e($statusClass((string)$row['status'])) ?>"><?= e($row['status']) ?></span></td>
                                    <td><?= e($row['requested_at'] ?: $row['created_at']) ?><small><?= $row['paid_at'] ? 'Paid: ' . e($row['paid_at']) : '' ?></small></td>
                                    <td><?php $statusForm('update_affiliate_payout_status', ['payout_id' => $row['id'], 'return_view' => 'affiliate-payouts'], ['pending' => 'Pending', 'processing' => 'Processing', 'paid' => 'Paid', 'failed' => 'Failed', 'cancelled' => 'Cancelled'], (string)$row['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($rows === []): ?><tr><td colspan="8">No affiliate payouts found.</td></tr><?php endif; ?>
                            </tbody>
                        <?php endif; ?>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!$showRuleForm && (int)($pagination['total'] ?? 0) > 0): ?>
            <div class="admin-pagination">
                <div class="pagination-summary">Page <?= number_format($page) ?> of <?= number_format($totalPages) ?></div>
                <div class="pagination-actions">
                    <a class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= e($listUrl(max(1, $page - 1))) ?>">Previous</a>
                    <?php for ($pageNumber = max(1, $page - 2); $pageNumber <= min($totalPages, $page + 2); $pageNumber++): ?>
                        <a class="pagination-btn <?= $pageNumber === $page ? 'active' : '' ?>" href="<?= e($listUrl($pageNumber)) ?>"><?= number_format($pageNumber) ?></a>
                    <?php endfor; ?>
                    <a class="pagination-btn <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= e($listUrl(min($totalPages, $page + 1))) ?>">Next</a>
                </div>
                <form class="pagination-size" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                    <?php if (($pagination['search'] ?? '') !== ''): ?><input type="hidden" name="q" value="<?= e($pagination['search']) ?>"><?php endif; ?>
                    <label><span>Rows</span><select name="per_page" onchange="this.form.submit()">
                        <?php foreach (($pagination['perPageOptions'] ?? [25, 50, 100]) as $option): ?>
                            <option value="<?= e($option) ?>" <?= (int)$option === $perPage ? 'selected' : '' ?>><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>
