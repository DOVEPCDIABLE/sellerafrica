<?php
$refundDisputesData = $refundDisputesData ?? [];
$disputeLinks = [
    'refund-requests' => 'Refund Requests',
    'vendor-disputes' => 'Vendor Disputes',
    'buyer-complaints' => 'Buyer Complaints',
    'return-requests' => 'Return Requests',
];
$rows = $refundDisputesData['rows'] ?? [];
$stats = $refundDisputesData['stats'] ?? [];
$pagination = $refundDisputesData['pagination'] ?? [];
$edit = $refundDisputesData['edit'] ?? null;
$showForm = (bool)($refundDisputesData['showForm'] ?? false);
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
$statusForm = static function (string $action, string $idName, mixed $idValue, array $statuses, string $currentStatus, string $returnView): void {
    ?>
    <form class="inline-action-form" method="post" action="<?= e(app_url('dashboard/' . $returnView)) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
        <input type="hidden" name="action" value="<?= e($action) ?>">
        <input type="hidden" name="<?= e($idName) ?>" value="<?= e($idValue) ?>">
        <select name="status" onchange="this.form.submit()">
            <?php foreach ($statuses as $status => $label): ?>
                <option value="<?= e($status) ?>" <?= $status === $currentStatus ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php
};
?>

<section class="settings-shell refunds-disputes-shell">
    <aside class="settings-index panel">
        <div class="panel-header">
            <div>
                <h2>Refunds & Disputes</h2>
                <p>Refund approvals, vendor disputes, buyer complaints and return/RMA requests</p>
            </div>
        </div>
        <nav class="settings-nav" aria-label="Refunds and disputes pages">
            <?php foreach ($disputeLinks as $key => $label): ?>
                <a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(app_url('dashboard/' . $key)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="settings-content">
        <section class="metric-grid settings-metrics" aria-label="Refund and dispute metrics">
            <div class="metric"><span>Refunds</span><strong><?= number_format((int)($stats['refunds'] ?? 0)) ?></strong><small><?= e($money($stats['refund_amount_requested'] ?? 0, 'USD')) ?> requested</small></div>
            <div class="metric"><span>Returns</span><strong><?= number_format((int)($stats['returns'] ?? 0)) ?></strong><small><?= number_format((int)($stats['return_requested'] ?? 0)) ?> requested</small></div>
            <div class="metric"><span>Vendor Cases</span><strong><?= number_format((int)($stats['vendor_disputes'] ?? 0)) ?></strong><small><?= number_format((int)($stats['open_vendor_disputes'] ?? 0)) ?> open/review</small></div>
            <div class="metric"><span>Buyer Cases</span><strong><?= number_format((int)($stats['buyer_complaints'] ?? 0)) ?></strong><small><?= number_format((int)($stats['open_buyer_complaints'] ?? 0)) ?> open/review</small></div>
        </section>

        <?php if (in_array($view, ['vendor-disputes', 'buyer-complaints'], true) && $showForm): ?>
            <?php $isVendorCase = $view === 'vendor-disputes'; ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2><?= e($edit ? 'Edit ' . ($isVendorCase ? 'Vendor Dispute' : 'Buyer Complaint') : 'Add ' . ($isVendorCase ? 'Vendor Dispute' : 'Buyer Complaint')) ?></h2>
                        <p>Create or update operational cases with order/vendor/user context and an internal admin note.</p>
                    </div>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/' . $view)) ?>">View List</a>
                </div>
                <form class="settings-form" method="post" action="<?= e(app_url('dashboard/' . $view)) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="<?= $isVendorCase ? 'save_vendor_dispute' : 'save_buyer_complaint' ?>">
                    <input type="hidden" name="<?= $isVendorCase ? 'dispute_id' : 'complaint_id' ?>" value="<?= e($edit['id'] ?? 0) ?>">
                    <div class="settings-grid">
                        <label class="settings-field settings-field-wide"><span><?= $isVendorCase ? 'Title' : 'Subject' ?></span><input name="<?= $isVendorCase ? 'title' : 'subject' ?>" value="<?= e($edit[$isVendorCase ? 'title' : 'subject'] ?? '') ?>" required></label>
                        <?php if (!$isVendorCase): ?>
                            <label class="settings-field"><span>Buyer</span><select name="user_id"><option value="">No buyer selected</option><?php foreach (($refundDisputesData['users'] ?? []) as $user): ?><option value="<?= e($user['id']) ?>" <?= (int)($edit['user_id'] ?? 0) === (int)$user['id'] ? 'selected' : '' ?>><?= e(($user['display_name'] ?: $user['email']) . ' #' . $user['id']) ?></option><?php endforeach; ?></select></label>
                        <?php endif; ?>
                        <label class="settings-field"><span>Vendor</span><select name="vendor_id"><option value="">No vendor selected</option><?php foreach (($refundDisputesData['vendors'] ?? []) as $vendor): ?><option value="<?= e($vendor['id']) ?>" <?= (int)($edit['vendor_id'] ?? 0) === (int)$vendor['id'] ? 'selected' : '' ?>><?= e($vendor['store_name']) ?></option><?php endforeach; ?></select></label>
                        <label class="settings-field"><span>Order</span><select name="order_id"><option value="">No order selected</option><?php foreach (($refundDisputesData['orders'] ?? []) as $order): ?><option value="<?= e($order['id']) ?>" <?= (int)($edit['order_id'] ?? 0) === (int)$order['id'] ? 'selected' : '' ?>>#<?= e($order['order_number']) ?> - <?= e($money($order['grand_total'], $order['currency'])) ?></option><?php endforeach; ?></select></label>
                        <?php if ($isVendorCase): ?>
                            <label class="settings-field"><span>Opened By</span><select name="opened_by"><option value="">Current admin</option><?php foreach (($refundDisputesData['users'] ?? []) as $user): ?><option value="<?= e($user['id']) ?>" <?= (int)($edit['opened_by'] ?? 0) === (int)$user['id'] ? 'selected' : '' ?>><?= e(($user['display_name'] ?: $user['email']) . ' #' . $user['id']) ?></option><?php endforeach; ?></select></label>
                        <?php endif; ?>
                        <label class="settings-field"><span>Status</span><select name="status"><?php foreach (['open' => 'Open', 'under_review' => 'Under Review', 'awaiting_vendor' => 'Awaiting Vendor', 'awaiting_buyer' => 'Awaiting Buyer', 'resolved' => 'Resolved', 'rejected' => 'Rejected', 'closed' => 'Closed'] as $status => $label): ?><option value="<?= e($status) ?>" <?= ($edit['status'] ?? 'open') === $status ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                        <label class="settings-field"><span>Priority</span><select name="priority"><?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $priority => $label): ?><option value="<?= e($priority) ?>" <?= ($edit['priority'] ?? 'normal') === $priority ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                        <label class="settings-field settings-field-wide"><span><?= $isVendorCase ? 'Description' : 'Message' ?></span><textarea name="<?= $isVendorCase ? 'description' : 'message' ?>" rows="4"><?= e($edit[$isVendorCase ? 'description' : 'message'] ?? '') ?></textarea></label>
                        <label class="settings-field settings-field-wide"><span>Admin Note</span><textarea name="admin_note" rows="3"><?= e($edit['admin_note'] ?? '') ?></textarea></label>
                    </div>
                    <button class="btn primary" type="submit">Save Case</button>
                </form>
            </section>
        <?php else: ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2><?= e($disputeLinks[$view] ?? 'Refunds & Disputes') ?></h2>
                        <p>Showing <?= number_format((int)($pagination['from'] ?? 0)) ?>-<?= number_format((int)($pagination['to'] ?? 0)) ?> of <?= number_format((int)($pagination['total'] ?? 0)) ?> records.</p>
                    </div>
                    <?php if (in_array($view, ['vendor-disputes', 'buyer-complaints'], true)): ?>
                        <a class="btn secondary" href="<?= e(app_url('dashboard/' . $view . '?new=1')) ?>">Add Case</a>
                    <?php endif; ?>
                </div>
                <form class="admin-searchbar" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                    <input type="search" name="q" value="<?= e($pagination['search'] ?? '') ?>" placeholder="Search <?= e(strtolower($disputeLinks[$view] ?? 'records')) ?>">
                    <input type="hidden" name="per_page" value="<?= e($perPage) ?>">
                    <button class="btn secondary" type="submit">Search</button>
                    <?php if (($pagination['search'] ?? '') !== ''): ?><a class="btn secondary" href="<?= e(app_url('dashboard/' . $view)) ?>">Clear</a><?php endif; ?>
                </form>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <?php if ($view === 'refund-requests'): ?>
                            <thead><tr><th>Refund</th><th>Order</th><th>Payment</th><th>Requester</th><th>Status</th><th>Processed</th><th>Created</th><th>Action</th></tr></thead>
                            <tbody><?php foreach ($rows as $row): ?><tr>
                                <td><strong><?= e($money($row['amount'], $row['currency'])) ?></strong><small><?= e($row['reason'] ?: 'No reason provided') ?></small></td>
                                <td><a href="<?= e(app_url('dashboard/orders?order=' . (int)$row['order_id'])) ?>">#<?= e($row['order_number'] ?: $row['order_id']) ?></a><small><?= e($row['order_status'] ?: '-') ?></small></td>
                                <td><?= e($row['provider'] ?: '-') ?><small><?= e($row['provider_reference'] ?: '-') ?></small></td>
                                <td><?= e(($row['requester_name'] ?: $row['requester_email']) ?: '-') ?></td>
                                <td><span class="settings-badge <?= e($statusClass((string)$row['status'])) ?>"><?= e($row['status']) ?></span></td>
                                <td><?= e($row['processed_at'] ?: 'Not processed') ?><small><?= e($row['processor_name'] ?: '') ?></small></td>
                                <td><?= e($row['created_at']) ?></td>
                                <td><?php $statusForm('update_refund_status', 'refund_id', $row['id'], ['requested' => 'Requested', 'approved' => 'Approved', 'rejected' => 'Rejected', 'processed' => 'Processed', 'failed' => 'Failed'], (string)$row['status'], 'refund-requests'); ?></td>
                            </tr><?php endforeach; ?><?php if ($rows === []): ?><tr><td colspan="8">No refund requests found.</td></tr><?php endif; ?></tbody>
                        <?php elseif ($view === 'return-requests'): ?>
                            <thead><tr><th>Return</th><th>Order</th><th>Item</th><th>Buyer</th><th>Vendor</th><th>Status</th><th>Updated</th><th>Action</th></tr></thead>
                            <tbody><?php foreach ($rows as $row): ?><tr>
                                <td><strong><?= e($row['type']) ?></strong><small><?= e($row['reason'] ?: 'No reason') ?></small></td>
                                <td><a href="<?= e(app_url('dashboard/orders?order=' . (int)$row['order_id'])) ?>">#<?= e($row['order_number'] ?: $row['order_id']) ?></a></td>
                                <td><?= e($row['item_name'] ?: '-') ?></td>
                                <td><?= e(($row['display_name'] ?: $row['email']) ?: '-') ?></td>
                                <td><?= e($row['store_name'] ?: '-') ?></td>
                                <td><span class="settings-badge <?= e($statusClass((string)$row['status'])) ?>"><?= e($row['status']) ?></span></td>
                                <td><?= e($row['updated_at']) ?><small><?= e($row['created_at']) ?></small></td>
                                <td><?php $statusForm('update_return_status', 'return_id', $row['id'], ['requested' => 'Requested', 'approved' => 'Approved', 'rejected' => 'Rejected', 'received' => 'Received', 'resolved' => 'Resolved', 'cancelled' => 'Cancelled'], (string)$row['status'], 'return-requests'); ?></td>
                            </tr><?php endforeach; ?><?php if ($rows === []): ?><tr><td colspan="8">No return requests found.</td></tr><?php endif; ?></tbody>
                        <?php elseif ($view === 'vendor-disputes'): ?>
                            <thead><tr><th>Case</th><th>Vendor</th><th>Order</th><th>Opened By</th><th>Priority</th><th>Status</th><th>Updated</th><th>Action</th></tr></thead>
                            <tbody><?php foreach ($rows as $row): ?><tr>
                                <td><strong><?= e($row['title']) ?></strong><small><?= e($row['description'] ?: $row['admin_note'] ?: '-') ?></small></td>
                                <td><?= e($row['store_name'] ?: '-') ?></td>
                                <td><?= $row['order_id'] ? '<a href="' . e(app_url('dashboard/orders?order=' . (int)$row['order_id'])) . '">#' . e($row['order_number'] ?: $row['order_id']) . '</a>' : '-' ?></td>
                                <td><?= e(($row['opener_name'] ?: $row['opener_email']) ?: '-') ?></td>
                                <td><span class="settings-badge <?= e($statusClass((string)$row['priority'])) ?>"><?= e($row['priority']) ?></span></td>
                                <td><span class="settings-badge <?= e($statusClass((string)$row['status'])) ?>"><?= e($row['status']) ?></span><small><?= e($row['assignee_name'] ?: '') ?></small></td>
                                <td><?= e($row['updated_at']) ?><small><?= $row['resolved_at'] ? 'Resolved: ' . e($row['resolved_at']) : '' ?></small></td>
                                <td><a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/vendor-disputes?edit=' . (int)$row['id'])) ?>">Edit</a><?php $statusForm('update_vendor_dispute_status', 'dispute_id', $row['id'], ['open' => 'Open', 'under_review' => 'Review', 'awaiting_vendor' => 'Need Vendor', 'awaiting_buyer' => 'Need Buyer', 'resolved' => 'Resolved', 'rejected' => 'Rejected', 'closed' => 'Closed'], (string)$row['status'], 'vendor-disputes'); ?></td>
                            </tr><?php endforeach; ?><?php if ($rows === []): ?><tr><td colspan="8">No vendor disputes found.</td></tr><?php endif; ?></tbody>
                        <?php elseif ($view === 'buyer-complaints'): ?>
                            <thead><tr><th>Complaint</th><th>Buyer</th><th>Vendor</th><th>Order</th><th>Priority</th><th>Status</th><th>Updated</th><th>Action</th></tr></thead>
                            <tbody><?php foreach ($rows as $row): ?><tr>
                                <td><strong><?= e($row['subject']) ?></strong><small><?= e($row['message'] ?: $row['admin_note'] ?: '-') ?></small></td>
                                <td><?= e(($row['display_name'] ?: $row['email']) ?: '-') ?></td>
                                <td><?= e($row['store_name'] ?: '-') ?></td>
                                <td><?= $row['order_id'] ? '<a href="' . e(app_url('dashboard/orders?order=' . (int)$row['order_id'])) . '">#' . e($row['order_number'] ?: $row['order_id']) . '</a>' : '-' ?></td>
                                <td><span class="settings-badge <?= e($statusClass((string)$row['priority'])) ?>"><?= e($row['priority']) ?></span></td>
                                <td><span class="settings-badge <?= e($statusClass((string)$row['status'])) ?>"><?= e($row['status']) ?></span><small><?= e($row['assignee_name'] ?: '') ?></small></td>
                                <td><?= e($row['updated_at']) ?><small><?= $row['resolved_at'] ? 'Resolved: ' . e($row['resolved_at']) : '' ?></small></td>
                                <td><a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/buyer-complaints?edit=' . (int)$row['id'])) ?>">Edit</a><?php $statusForm('update_buyer_complaint_status', 'complaint_id', $row['id'], ['open' => 'Open', 'under_review' => 'Review', 'awaiting_buyer' => 'Need Buyer', 'awaiting_vendor' => 'Need Vendor', 'resolved' => 'Resolved', 'rejected' => 'Rejected', 'closed' => 'Closed'], (string)$row['status'], 'buyer-complaints'); ?></td>
                            </tr><?php endforeach; ?><?php if ($rows === []): ?><tr><td colspan="8">No buyer complaints found.</td></tr><?php endif; ?></tbody>
                        <?php endif; ?>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!$showForm && (int)($pagination['total'] ?? 0) > 0): ?>
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
