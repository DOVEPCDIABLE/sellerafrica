<?php
$usersData = $usersData ?? [];
$userLinks = [
    'users' => 'All Users',
    'buyers' => 'Buyers',
    'guest-buyers' => 'Guest Buyers',
    'blocked-users' => 'Blocked Users',
    'user-activity-logs' => 'User Activity Logs',
];
$stats = $usersData['stats'] ?? [];
$statusClass = static fn (string $status): string => 'status-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $status) ?? 'unknown');
$auditDetailText = static function (?string $json): string {
    return function_exists('audit_detail_text') ? audit_detail_text($json) : '-';
};
$displayName = static function (array $user): string {
    $name = trim((string)($user['display_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    $fullName = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
    return $fullName !== '' ? $fullName : (string)($user['username'] ?? 'User');
};
$userRows = match ($view) {
    'buyers' => $usersData['buyers'] ?? [],
    'blocked-users' => $usersData['blockedUsers'] ?? [],
    default => $usersData['users'] ?? [],
};
$pagination = $usersData['pagination'] ?? [];
$selectedUser = $usersData['selectedUser'] ?? null;
$selectedUserRoleIds = array_map('intval', $usersData['selectedUserRoleIds'] ?? []);
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
?>

<section class="settings-shell users-shell">
    <aside class="settings-index panel">
        <div class="panel-header">
            <div>
                <h2>Users</h2>
                <p>Accounts, buyers, guests and activity intelligence</p>
            </div>
        </div>
        <nav class="settings-nav" aria-label="User pages">
            <?php foreach ($userLinks as $key => $label): ?>
                <a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(app_url('dashboard/' . $key)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="settings-content">
        <section class="metric-grid settings-metrics" aria-label="User metrics">
            <div class="metric"><span>Total Users</span><strong><?= number_format((int)($stats['total_users'] ?? 0)) ?></strong><small>Registered accounts</small></div>
            <div class="metric"><span>Active Users</span><strong><?= number_format((int)($stats['active_users'] ?? 0)) ?></strong><small>Can access the system</small></div>
            <div class="metric"><span>Buyers</span><strong><?= number_format((int)($stats['registered_buyers'] ?? 0)) ?></strong><small>Customer role or order history</small></div>
            <div class="metric"><span>Guest Buyers</span><strong><?= number_format((int)($stats['guest_buyers'] ?? 0)) ?></strong><small>Checkout without account</small></div>
        </section>

        <?php if ($view === 'guest-buyers'): ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Guest Buyers</h2>
                        <p>Customers who purchased without a registered account.</p>
                    </div>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/users')) ?>">All Users</a>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Email</th><th>Orders</th><th>Lifetime Value</th><th>Latest Status</th><th>First Order</th><th>Last Order</th></tr></thead>
                        <tbody>
                        <?php foreach (($usersData['guestBuyers'] ?? []) as $guest): ?>
                            <tr>
                                <td><strong><?= e($guest['guest_email']) ?></strong></td>
                                <td><?= number_format((int)$guest['order_count']) ?></td>
                                <td>$<?= number_format((float)$guest['lifetime_value'], 2) ?></td>
                                <td><span class="settings-badge <?= e($statusClass((string)$guest['latest_status'])) ?>"><?= e($guest['latest_status']) ?></span></td>
                                <td><?= e($guest['first_order_at'] ?? '-') ?></td>
                                <td><?= e($guest['last_order_at'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($usersData['guestBuyers'] ?? []) === []): ?>
                            <tr><td colspan="6">No guest buyers found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($view === 'user-activity-logs'): ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>User Activity Logs</h2>
                        <p>Audit-backed user activity, account changes and login events.</p>
                    </div>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/audit-hub')) ?>">Audit Hub</a>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Event</th><th>User</th><th>Entity</th><th>Details</th><th>IP</th><th>User Agent</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach (($usersData['activity'] ?? []) as $event): ?>
                            <tr>
                                <td><strong><?= e($event['action']) ?></strong></td>
                                <td><?= e(($event['display_name'] ?: $event['email']) ?: 'System') ?></td>
                                <td><?= e($event['entity_type'] . ' #' . ($event['entity_id'] ?? '-')) ?></td>
                                <td><?= e($auditDetailText($event['new_values'] ?? null)) ?></td>
                                <td><?= e($event['ip_address'] ?? '-') ?></td>
                                <td class="user-agent-cell"><?= e($event['user_agent'] ?? '-') ?></td>
                                <td><?= e($event['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($usersData['activity'] ?? []) === []): ?>
                            <tr><td colspan="7">No user activity events found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php else: ?>
            <?php if ($selectedUser): ?>
                <section class="panel detail-panel user-detail-panel">
                    <div class="panel-header">
                        <div>
                            <h2><?= e($displayName($selectedUser)) ?></h2>
                            <p><?= e(($selectedUser['email'] ?? '') . ' | User #' . (int)$selectedUser['id']) ?></p>
                        </div>
                        <a class="btn secondary" href="<?= e(app_url('dashboard/' . $view)) ?>">Close Details</a>
                    </div>
                    <form class="settings-form admin-detail-form" method="post">
                        <?php
                        $targetRoleCodes = array_column(array_filter($usersData['allRoles'] ?? [], static fn(array $role): bool => in_array((int)$role['id'], $selectedUserRoleIds, true)), 'code');
                        $canEditUser = \App\AdminAccessService::canEditUser($_SESSION['roles'] ?? [], $targetRoleCodes);
                        ?>
                        <?php if (!$canEditUser): ?><p>Only a super administrator can change this account.</p><?php endif; ?>
                        <fieldset <?= $canEditUser ? '' : 'disabled' ?> style="border:0;padding:0;margin:0;min-width:0">
                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                        <input type="hidden" name="action" value="save_user_admin">
                        <input type="hidden" name="user_id" value="<?= e($selectedUser['id']) ?>">
                        <div class="settings-grid">
                            <label class="settings-field"><span>Email</span><input type="email" name="email" value="<?= e($selectedUser['email']) ?>" required></label>
                            <label class="settings-field"><span>First Name</span><input type="text" name="first_name" value="<?= e($selectedUser['first_name']) ?>"></label>
                            <label class="settings-field"><span>Last Name</span><input type="text" name="last_name" value="<?= e($selectedUser['last_name']) ?>"></label>
                            <label class="settings-field"><span>Display Name</span><input type="text" name="display_name" value="<?= e($selectedUser['display_name']) ?>"></label>
                            <label class="settings-field"><span>Phone</span><input type="text" name="phone" value="<?= e($selectedUser['phone']) ?>"></label>
                            <label class="settings-field"><span>Status</span><select name="status">
                                <?php foreach (['active', 'pending', 'suspended', 'deleted'] as $status): ?>
                                    <option value="<?= e($status) ?>" <?= $status === (string)$selectedUser['status'] ? 'selected' : '' ?>><?= e(ucwords($status)) ?></option>
                                <?php endforeach; ?>
                            </select></label>
                        </div>
                        <label class="settings-toggle"><input type="checkbox" name="email_verified" value="1" <?= ($selectedUser['email_verified_at'] ?? '') !== '' ? 'checked' : '' ?>><span>Email verified</span></label>
                        <div class="settings-grid">
                            <label class="settings-field"><span>New Password</span><input type="password" name="new_password" autocomplete="new-password" minlength="5"></label>
                            <label class="settings-field"><span>Confirm Password</span><input type="password" name="new_password_confirmation" autocomplete="new-password" minlength="5"></label>
                        </div>
                        <div class="role-checkbox-grid">
                            <?php foreach (($usersData['allRoles'] ?? []) as $role): ?>
                                <label><input type="checkbox" name="roles[]" value="<?= e($role['id']) ?>" <?= in_array((int)$role['id'], $selectedUserRoleIds, true) ? 'checked' : '' ?> <?= \App\AuthService::hasRole('super_admin') ? '' : 'disabled' ?>> <span><?= e($role['name']) ?></span><small><?= e($role['code']) ?></small></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-actions"><button class="btn primary" type="submit">Save User Details</button></div>
                        </fieldset>
                    </form>

                    <?php
                    $management = $usersData['selectedUserManagement'] ?? null;
                    $managementRaw = json_decode((string)($management['raw_response'] ?? '{}'), true);
                    $managementCheckout = $managementRaw['paystack_checkout'] ?? [];
                    $managementActive = in_array((string)($management['status'] ?? ''), ['active', 'paid', 'trialing'], true);
                    $managementUrl = (string)($managementCheckout['authorization_url'] ?? '');
                    $managementLinkReady = !$managementActive && in_array((string)($management['status'] ?? ''), ['pending', 'incomplete'], true)
                        && parse_url($managementUrl, PHP_URL_SCHEME) === 'https'
                        && parse_url($managementUrl, PHP_URL_HOST) === 'checkout.paystack.com';
                    ?>
                    <?php if (\App\AuthService::hasRole('super_admin')): ?>
                    <p><a class="btn primary" href="<?= e(app_url('dashboard/payment-links?user='.(int)$selectedUser['id'])) ?>">Generate a service payment link</a></p>
                    <div class="settings-form admin-detail-form">
                        <h3>Manage My Store</h3>
                        <p>$1/month, billed as a single $12 annual payment. Activates after payment is verified.</p>
                        <p><strong>Status:</strong> <?= e($management ? ucwords(str_replace('_', ' ', (string)$management['status'])) : 'Not subscribed') ?></p>
                        <p><strong>Customer:</strong> <?= e($selectedUser['email']) ?></p>
                        <?php if ($managementLinkReady): ?>
                            <p><strong>Payment amount:</strong> <?= e((string)($managementCheckout['charge_currency'] ?? '') . ' ' . number_format((float)($managementCheckout['charge_amount'] ?? 0), 2)) ?></p>
                            <label class="settings-field"><span>Paystack payment link</span><input id="manage-store-payment-link" type="url" readonly value="<?= e($managementUrl) ?>" style="width:100%;min-width:0;box-sizing:border-box" onclick="this.select()"></label>
                            <div class="form-actions" style="display:flex;flex-wrap:wrap;gap:10px">
                                <button class="btn secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('manage-store-payment-link').value).then(() => { document.getElementById('manage-store-copy-status').textContent = 'Payment link copied.'; }).catch(() => { document.getElementById('manage-store-payment-link').select(); document.getElementById('manage-store-copy-status').textContent = 'Select and copy the payment link above.'; })">Copy payment link</button>
                                <a class="btn secondary" href="<?= e($managementUrl) ?>" target="_blank" rel="noopener noreferrer">Open payment page</a>
                            </div>
                            <p id="manage-store-copy-status" role="status"></p>
                        <?php elseif (!$managementActive): ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                <input type="hidden" name="action" value="create_manage_store_payment">
                                <input type="hidden" name="user_id" value="<?= e($selectedUser['id']) ?>">
                                <button class="btn primary" type="submit">Generate Paystack payment link</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php
                    $setupService = $usersData['selectedUserSetup'] ?? null;
                    $setupRaw = json_decode((string)($setupService['raw_response'] ?? '{}'), true);
                    $setupUrl = (string)($setupRaw['paystack_checkout']['authorization_url'] ?? '');
                    $setupPaid = !empty($setupService['paid_at']);
                    $setupLinkReady = !$setupPaid && in_array($setupService['status'] ?? '', ['pending','incomplete'], true)
                        && parse_url($setupUrl, PHP_URL_SCHEME) === 'https' && parse_url($setupUrl, PHP_URL_HOST) === 'checkout.paystack.com';
                    ?>
                    <div class="settings-form admin-detail-form">
                        <h3>Set Up My Store</h3>
                        <p>$5 one-time payment. No recurring charge. Separate from annual store management.</p>
                        <p><strong>Customer:</strong> <?= e($selectedUser['email']) ?></p>
                        <p><strong>Status:</strong> <?= e($setupPaid ? 'Payment received - arrange store setup' : ($setupService['status'] ?? 'Not purchased')) ?></p>
                        <?php if ($setupLinkReady): ?>
                            <label class="settings-field"><span>Paystack setup payment link</span><input type="url" readonly value="<?= e($setupUrl) ?>" onclick="this.select()"></label>
                            <a class="btn secondary" href="<?= e($setupUrl) ?>" target="_blank" rel="noopener noreferrer">Open payment link</a>
                        <?php elseif (!$setupPaid): ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                <input type="hidden" name="action" value="create_manage_store_payment">
                                <input type="hidden" name="service_plan" value="setup">
                                <input type="hidden" name="user_id" value="<?= e($selectedUser['id']) ?>">
                                <button class="btn primary" type="submit">Generate $5 setup payment link</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="detail-card-grid">
                        <div class="detail-card">
                            <h3>Recent Orders</h3>
                            <?php foreach (($usersData['selectedUserOrders'] ?? []) as $order): ?>
                                <a class="status-item" href="<?= e(app_url('dashboard/orders?order=' . (int)$order['id'])) ?>"><strong><?= e($order['order_number'] ?: ('Order #' . $order['id'])) ?></strong><span><?= e($order['status'] . ' | $' . number_format((float)$order['grand_total'], 2)) ?></span></a>
                            <?php endforeach; ?>
                            <?php if (($usersData['selectedUserOrders'] ?? []) === []): ?><div class="status-item"><strong>No orders</strong><span>This user has not placed an order yet.</span></div><?php endif; ?>
                        </div>
                        <div class="detail-card">
                            <h3>Vendor Stores</h3>
                            <?php foreach (($usersData['selectedUserVendors'] ?? []) as $vendor): ?>
                                <a class="status-item" href="<?= e(app_url('dashboard/vendors?q=' . urlencode((string)$vendor['store_name']))) ?>"><strong><?= e($vendor['store_name']) ?></strong><span><?= e($vendor['status'] . ' | KYC ' . $vendor['kyc_status']) ?></span></a>
                            <?php endforeach; ?>
                            <?php if (($usersData['selectedUserVendors'] ?? []) === []): ?><div class="status-item"><strong>No vendor store</strong><span>This user is not attached to a vendor store.</span></div><?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="dashboard-grid">
                <div class="panel">
	                    <div class="panel-header">
	                        <div>
	                            <h2><?= e($userLinks[$view] ?? 'All Users') ?></h2>
	                            <p><?= $view === 'blocked-users' ? 'Suspended and deleted users that require administrative review.' : 'Registered user accounts with roles, order activity and account health.' ?> Showing <?= number_format((int)($pagination['from'] ?? 0)) ?>-<?= number_format((int)($pagination['to'] ?? 0)) ?> of <?= number_format((int)($pagination['total'] ?? 0)) ?>.</p>
	                        </div>
	                        <span class="settings-badge"><?= number_format(count($userRows)) ?> loaded</span>
	                    </div>
	                    <form class="admin-searchbar" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
	                        <input type="search" name="q" value="<?= e($pagination['search'] ?? '') ?>" placeholder="Search users by name, email, username or phone">
	                        <input type="hidden" name="per_page" value="<?= e($perPage) ?>">
	                        <button class="btn secondary" type="submit">Search</button>
	                        <?php if (($pagination['search'] ?? '') !== ''): ?><a class="btn secondary" href="<?= e(app_url('dashboard/' . $view)) ?>">Clear</a><?php endif; ?>
	                    </form>
	                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>User</th><th>Roles</th><th>Status</th><th>Orders</th><th>Lifetime Value</th><th>Last Login</th><th>Joined</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($userRows as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-identity">
                                            <span class="user-avatar">
                                                <?php if (($user['avatar_url'] ?? '') !== ''): ?>
                                                    <img src="<?= e($user['avatar_url']) ?>" alt="<?= e($displayName($user)) ?>">
                                                <?php else: ?>
                                                    <?= e(strtoupper(substr($displayName($user), 0, 1))) ?>
                                                <?php endif; ?>
                                            </span>
                                            <span><strong><?= e($displayName($user)) ?></strong><small><?= e($user['email']) ?></small></span>
                                        </div>
                                    </td>
                                    <td><?= e($user['role_names'] ?: 'No role') ?></td>
                                    <td><span class="settings-badge <?= e($statusClass((string)$user['status'])) ?>"><?= e($user['status']) ?></span></td>
                                    <td><?= number_format((int)$user['order_count']) ?></td>
                                    <td>$<?= number_format((float)$user['lifetime_value'], 2) ?></td>
                                    <td><?= e($user['last_login_at'] ?? '-') ?></td>
                                    <td><?= e($user['created_at']) ?></td>
                                    <td><a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/' . $view . '?user=' . (int)$user['id'] . '&page=' . $page . '&per_page=' . $perPage . (($pagination['search'] ?? '') !== '' ? '&q=' . urlencode((string)$pagination['search']) : ''))) ?>">View/Edit</a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($userRows === []): ?>
                                <tr><td colspan="8">No users found for this view.</td></tr>
                            <?php endif; ?>
                            </tbody>
	                        </table>
	                    </div>
	                    <?php if ((int)($pagination['total'] ?? 0) > 0): ?>
	                        <div class="admin-pagination">
	                            <div class="pagination-summary">Page <?= number_format($page) ?> of <?= number_format($totalPages) ?></div>
	                            <div class="pagination-actions">
	                                <a class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= e($listUrl(max(1, $page - 1))) ?>">Previous</a>
	                                <?php for ($n = max(1, $page - 2); $n <= min($totalPages, $page + 2); $n++): ?>
	                                    <a class="pagination-btn <?= $n === $page ? 'active' : '' ?>" href="<?= e($listUrl($n)) ?>"><?= number_format($n) ?></a>
	                                <?php endfor; ?>
	                                <a class="pagination-btn <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= e($listUrl(min($totalPages, $page + 1))) ?>">Next</a>
	                            </div>
	                            <form class="pagination-size" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
	                                <?php if (($pagination['search'] ?? '') !== ''): ?><input type="hidden" name="q" value="<?= e($pagination['search']) ?>"><?php endif; ?>
	                                <label><span>Rows</span><select name="per_page" onchange="this.form.submit()">
	                                    <?php foreach (($pagination['perPageOptions'] ?? [25, 50, 100]) as $option): ?><option value="<?= e($option) ?>" <?= (int)$option === $perPage ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
	                                </select></label>
	                            </form>
	                        </div>
	                    <?php endif; ?>
	                </div>
                <aside class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Role Distribution</h2>
                            <p>Current role membership counts.</p>
                        </div>
                    </div>
                    <div class="status-list">
                        <?php foreach (($usersData['roles'] ?? []) as $role): ?>
                            <div class="status-item"><strong><?= e($role['name']) ?></strong><span><?= number_format((int)$role['total']) ?> users | <?= e($role['code']) ?></span></div>
                        <?php endforeach; ?>
                        <?php if (($usersData['roles'] ?? []) === []): ?>
                            <div class="status-item"><strong>No roles found</strong><span>Roles table is empty.</span></div>
                        <?php endif; ?>
                    </div>
                </aside>
            </section>
        <?php endif; ?>
    </div>
</section>
