<?php
$withdrawalsData = $withdrawalsData ?? [];
$withdrawalLinks = [
    'vendor-withdrawals' => 'Vendor Withdrawals',
    'affiliate-withdrawals' => 'Affiliate Withdrawals',
    'pending-withdrawals' => 'Pending Withdrawals',
    'approved-withdrawals' => 'Approved Withdrawals',
    'rejected-withdrawals' => 'Rejected Withdrawals',
];
$rows = $withdrawalsData['rows'] ?? [];
$stats = $withdrawalsData['stats'] ?? [];
$pagination = $withdrawalsData['pagination'] ?? [];
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
$payoutSummary = static function (?string $details): string {
    $decoded = json_decode((string)$details, true);
    if (is_array($decoded) && $decoded !== []) {
        return implode(', ', array_slice(array_map(static fn ($key): string => ucwords(str_replace('_', ' ', (string)$key)), array_keys($decoded)), 0, 3));
    }

    return trim((string)$details) !== '' ? (string)$details : 'Not provided';
};
?>

<section class="settings-shell withdrawals-shell">
    <aside class="settings-index panel">
        <div class="panel-header">
            <div>
                <h2>Withdrawals</h2>
                <p>Vendor and affiliate payout requests, approval queue and settlement status</p>
            </div>
        </div>
        <nav class="settings-nav" aria-label="Withdrawal pages">
            <?php foreach ($withdrawalLinks as $key => $label): ?>
                <a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(app_url('dashboard/' . $key)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="settings-content">
        <section class="metric-grid settings-metrics" aria-label="Withdrawal metrics">
            <div class="metric"><span>Vendor Requests</span><strong><?= number_format((int)($stats['vendor_withdrawals'] ?? 0)) ?></strong><small>Vendor payouts</small></div>
            <div class="metric"><span>Affiliate Requests</span><strong><?= number_format((int)($stats['affiliate_withdrawals'] ?? 0)) ?></strong><small>Affiliate payouts</small></div>
            <div class="metric"><span>Pending</span><strong><?= number_format((int)($stats['pending_withdrawals'] ?? 0)) ?></strong><small><?= e($money($stats['pending_amount'] ?? 0, 'USD')) ?> queued</small></div>
            <div class="metric"><span>Approved/Paid</span><strong><?= number_format((int)($stats['approved_withdrawals'] ?? 0)) ?></strong><small>Ready or settled</small></div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2><?= e($withdrawalLinks[$view] ?? 'Withdrawals') ?></h2>
                    <p>
                        Review payout requests, payout methods and processing history.
                        Showing <?= number_format((int)($pagination['from'] ?? 0)) ?>-<?= number_format((int)($pagination['to'] ?? 0)) ?> of <?= number_format((int)($pagination['total'] ?? 0)) ?>.
                    </p>
                </div>
                <span class="settings-badge"><?= number_format(count($rows)) ?> loaded</span>
            </div>

            <form class="admin-searchbar" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                <input type="search" name="q" value="<?= e($pagination['search'] ?? '') ?>" placeholder="Search withdrawals by store, affiliate, email, method or status">
                <input type="hidden" name="per_page" value="<?= e($perPage) ?>">
                <button class="btn secondary" type="submit">Search</button>
                <?php if (($pagination['search'] ?? '') !== ''): ?><a class="btn secondary" href="<?= e(app_url('dashboard/' . $view)) ?>">Clear</a><?php endif; ?>
            </form>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Requester</th><th>Type</th><th>Amount</th><th>Method</th><th>Status</th><th>Requested</th><th>Processed</th><th>Note</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><strong><?= e($row['owner_name'] ?: ucfirst((string)$row['withdrawal_type']) . ' #' . $row['owner_id']) ?></strong><small><?= e($row['owner_email'] ?: '-') ?></small></td>
                            <td><span class="settings-badge"><?= e($row['withdrawal_type']) ?></span></td>
                            <td><strong><?= e($money($row['amount'], $row['currency'])) ?></strong></td>
                            <td><?= e($row['method'] ?: $row['payout_method'] ?: 'Not set') ?><small><?= e($payoutSummary($row['payout_details'] ?? null)) ?></small></td>
                            <td><span class="settings-badge <?= e($statusClass((string)$row['status'])) ?>"><?= e($row['status']) ?></span></td>
                            <td><?= e($row['requested_at'] ?: '-') ?></td>
                            <td><?= e($row['processed_at'] ?: 'Not processed') ?><small><?= e($row['processed_by_name'] ?: '') ?></small></td>
                            <td class="product-long-text"><?= e($row['note'] ?: '-') ?></td>
                            <td>
                                <?php if ($row['status'] === 'pending'): ?>
                                    <div class="withdrawal-actions">
                                        <?php foreach (['approved' => 'Approve', 'rejected' => 'Reject'] as $status => $label): ?>
                                            <form method="post" action="<?= e(app_url('dashboard/' . $view)) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                                <input type="hidden" name="action" value="update_withdrawal_status">
                                                <input type="hidden" name="return_view" value="<?= e($view) ?>">
                                                <input type="hidden" name="withdrawal_type" value="<?= e($row['withdrawal_type']) ?>">
                                                <input type="hidden" name="withdrawal_id" value="<?= e($row['id']) ?>">
                                                <input type="hidden" name="status" value="<?= e($status) ?>">
                                                <button class="btn secondary compact-btn" type="submit"><?= e($label) ?></button>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif ($row['status'] === 'approved'): ?>
                                    <form method="post" action="<?= e(app_url('dashboard/' . $view)) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="action" value="update_withdrawal_status">
                                        <input type="hidden" name="return_view" value="<?= e($view) ?>">
                                        <input type="hidden" name="withdrawal_type" value="<?= e($row['withdrawal_type']) ?>">
                                        <input type="hidden" name="withdrawal_id" value="<?= e($row['id']) ?>">
                                        <input type="hidden" name="status" value="paid">
                                        <button class="btn secondary compact-btn" type="submit">Mark Paid</button>
                                    </form>
                                <?php else: ?>
                                    <span class="settings-badge">No action</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="9">No withdrawal records found for this view.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ((int)($pagination['total'] ?? 0) > 0): ?>
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
                        <label>
                            <span>Rows</span>
                            <select name="per_page" onchange="this.form.submit()">
                                <?php foreach (($pagination['perPageOptions'] ?? [25, 50, 100]) as $option): ?>
                                    <option value="<?= e($option) ?>" <?= (int)$option === $perPage ? 'selected' : '' ?>><?= e($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </form>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>
