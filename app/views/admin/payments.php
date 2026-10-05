<?php
$paymentsData = $paymentsData ?? [];
$paymentLinks = [
    'transactions' => 'Transactions',
    'payment-gateways' => 'Payment Gateways',
    'failed-payments' => 'Failed Payments',
    'idempotency-logs' => 'Idempotency Logs',
    'multi-currency-settings' => 'Multi-Currency Settings',
];
$stats = $paymentsData['stats'] ?? [];
$pagination = $paymentsData['pagination'] ?? [];
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
?>

<section class="settings-shell payments-shell">
    <aside class="settings-index panel">
        <div class="panel-header">
            <div>
                <h2>Payments</h2>
                <p>Transactions, gateways, idempotency and currency operations</p>
            </div>
        </div>
        <nav class="settings-nav" aria-label="Payment pages">
            <?php foreach ($paymentLinks as $key => $label): ?>
                <a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(app_url('dashboard/' . $key)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="settings-content">
        <section class="metric-grid settings-metrics" aria-label="Payment metrics">
            <div class="metric"><span>Transactions</span><strong><?= number_format((int)($stats['total_transactions'] ?? 0)) ?></strong><small>Payment records</small></div>
            <div class="metric"><span>Paid</span><strong><?= number_format((int)($stats['paid_transactions'] ?? 0)) ?></strong><small>Successful payments</small></div>
            <div class="metric"><span>Failed</span><strong><?= number_format((int)($stats['failed_transactions'] ?? 0)) ?></strong><small>Needs review</small></div>
            <div class="metric"><span>Gateways</span><strong><?= number_format((int)($stats['gateway_count'] ?? 0)) ?></strong><small>Configured methods</small></div>
        </section>

        <?php if (in_array($view, ['transactions', 'failed-payments'], true)): ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2><?= e($paymentLinks[$view]) ?></h2>
                        <p>Searchable payment records with order references and gateway status. Showing <?= number_format((int)($pagination['from'] ?? 0)) ?>-<?= number_format((int)($pagination['to'] ?? 0)) ?> of <?= number_format((int)($pagination['total'] ?? 0)) ?>.</p>
                    </div>
                </div>
                <form class="admin-searchbar" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                    <input type="search" name="q" value="<?= e($pagination['search'] ?? '') ?>" placeholder="Search payments by provider, reference, order or customer">
                    <input type="hidden" name="per_page" value="<?= e($perPage) ?>">
                    <button class="btn secondary" type="submit">Search</button>
                    <?php if (($pagination['search'] ?? '') !== ''): ?><a class="btn secondary" href="<?= e(app_url('dashboard/' . $view)) ?>">Clear</a><?php endif; ?>
                </form>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Reference</th><th>Order</th><th>Customer</th><th>Provider</th><th>Status</th><th>Amount</th><th>Paid At</th><th>Created</th></tr></thead>
                        <tbody>
                        <?php foreach (($paymentsData['transactions'] ?? []) as $payment): ?>
                            <tr>
                                <td><strong><?= e($payment['provider_reference'] ?: 'No reference') ?></strong><small><?= e($payment['provider_status'] ?: '-') ?></small></td>
                                <td><a href="<?= e(app_url('dashboard/orders?order=' . (int)$payment['order_id'])) ?>">#<?= e($payment['order_number'] ?: $payment['order_id']) ?></a><small><?= e($payment['order_status'] ?: '-') ?></small></td>
                                <td><?= e(($payment['display_name'] ?: $payment['email']) ?: 'Guest') ?></td>
                                <td><?= e($payment['provider'] ?: 'manual') ?></td>
                                <td><span class="settings-badge <?= e($statusClass((string)$payment['status'])) ?>"><?= e($payment['status']) ?></span></td>
                                <td><strong><?= e($money($payment['amount'], $payment['currency'])) ?></strong></td>
                                <td><?= e($payment['paid_at'] ?: '-') ?></td>
                                <td><?= e($payment['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($paymentsData['transactions'] ?? []) === []): ?>
                            <tr><td colspan="8">No payment transactions found.</td></tr>
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
            </section>
        <?php elseif ($view === 'payment-gateways'): ?>
            <section class="panel">
                <div class="panel-header"><div><h2>Payment Gateways</h2><p>Gateway status, transaction volume and latest activity.</p></div></div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Gateway</th><th>Code</th><th>Provider</th><th>Status</th><th>Transactions</th><th>Paid Volume</th><th>Latest Transaction</th></tr></thead>
                        <tbody>
                        <?php foreach (($paymentsData['gateways'] ?? []) as $gateway): ?>
                            <tr>
                                <td><strong><?= e($gateway['name']) ?></strong></td>
                                <td><code><?= e($gateway['code']) ?></code></td>
                                <td><?= e($gateway['provider'] ?: '-') ?></td>
                                <td><span class="settings-badge <?= (int)$gateway['is_active'] === 1 ? 'status-active' : 'status-failed' ?>"><?= (int)$gateway['is_active'] === 1 ? 'active' : 'inactive' ?></span></td>
                                <td><?= number_format((int)$gateway['transaction_count']) ?></td>
                                <td>$<?= number_format((float)$gateway['paid_amount'], 2) ?></td>
                                <td><?= e($gateway['latest_transaction_at'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($paymentsData['gateways'] ?? []) === []): ?><tr><td colspan="7">No payment gateways found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php foreach (($paymentsData['gateways'] ?? []) as $gateway): ?>
                    <?php if (($gateway['code'] ?? '') === 'klasha'): ?>
                        <?php $klashaSettings = \App\PaymentService::settings($gateway); ?>
                        <form class="settings-form" method="post" action="<?= e(app_url('dashboard/payment-gateways')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                            <input type="hidden" name="action" value="save_payment_gateway">
                            <input type="hidden" name="gateway_id" value="<?= e($gateway['id']) ?>">
                            <div class="panel-header">
                                <div>
                                    <h2>Klasha Payment Integration</h2>
                                    <p>Add the Klasha API details from your Klasha dashboard. Webhook URL: <code><?= e(app_url('api/webhooks/klasha')) ?></code></p>
                                </div>
                            </div>
                            <div class="settings-form-grid">
                                <label class="settings-field"><span>Display Name</span><input name="name" value="<?= e($gateway['name']) ?>" required></label>
                                <label class="settings-field"><span>Status</span><select name="is_active"><option value="1" <?= (int)$gateway['is_active'] === 1 ? 'selected' : '' ?>>Active</option><option value="0" <?= (int)$gateway['is_active'] !== 1 ? 'selected' : '' ?>>Inactive</option></select></label>
                                <label class="settings-field"><span>Mode</span><select name="mode"><option value="test" <?= ($klashaSettings['mode'] ?? 'test') === 'test' ? 'selected' : '' ?>>Test / Sandbox</option><option value="live" <?= ($klashaSettings['mode'] ?? '') === 'live' ? 'selected' : '' ?>>Live / Production</option></select></label>
                                <label class="settings-field"><span>Destination Currency</span><input name="destination_currency" maxlength="3" value="<?= e($klashaSettings['destination_currency'] ?? 'NGN') ?>"></label>
                                <label class="settings-field span-2"><span>Merchant Public Key</span><input name="public_key" value="<?= e($klashaSettings['public_key'] ?? '') ?>" placeholder="Merchant public key"></label>
                                <label class="settings-field span-2"><span>Business ID</span><input name="business_id" value="<?= e($klashaSettings['business_id'] ?? '') ?>" placeholder="Klasha business ID"></label>
                                <label class="settings-field"><span>Secret Key</span><input type="password" name="secret_key" value="" placeholder="<?= ($klashaSettings['secret_key'] ?? '') !== '' ? 'Saved - leave blank to keep current value' : 'Enter secret key' ?>" autocomplete="new-password"></label>
                                <label class="settings-field"><span>Webhook Secret</span><input type="password" name="webhook_secret" value="" placeholder="<?= ($klashaSettings['webhook_secret'] ?? '') !== '' ? 'Saved - leave blank to keep current value' : 'Optional webhook secret' ?>" autocomplete="new-password"></label>
                                <label class="settings-field span-2"><span>Status Verification Endpoint</span><input type="url" name="status_endpoint" value="<?= e($klashaSettings['status_endpoint'] ?? 'https://gate.klasha.com/nucleus/tnx/merchant/status') ?>"></label>
                            </div>
                            <div class="settings-note">
                                <span>Klasha checkout uses the public key and business ID in the browser, then verifies transaction status server-side before marking an order paid.</span>
                            </div>
                            <button class="btn primary" type="submit">Save Klasha Integration</button>
                        </form>
                    <?php elseif (($gateway['code'] ?? '') === 'paystack'): ?>
                        <?php $paystackSettings = \App\PaymentService::paystackSettings($gateway); ?>
                        <form class="settings-form" method="post" action="<?= e(app_url('dashboard/payment-gateways')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                            <input type="hidden" name="action" value="save_payment_gateway">
                            <input type="hidden" name="gateway_id" value="<?= e($gateway['id']) ?>">
                            <div class="panel-header">
                                <div>
                                    <h2>Paystack Payment Integration</h2>
                                    <p>Add Paystack API details from your dashboard. Callback URL: <code><?= e(app_url('paystack_callback.php')) ?></code><br>Webhook URL: <code><?= e(app_url('paystack_webhook.php')) ?></code></p>
                                </div>
                            </div>
                            <div class="settings-form-grid">
                                <label class="settings-field"><span>Display Name</span><input name="name" value="<?= e($gateway['name']) ?>" required></label>
                                <label class="settings-field"><span>Status</span><select name="is_active"><option value="1" <?= (int)$gateway['is_active'] === 1 ? 'selected' : '' ?>>Active</option><option value="0" <?= (int)$gateway['is_active'] !== 1 ? 'selected' : '' ?>>Inactive</option></select></label>
                                <label class="settings-field"><span>Mode</span><select name="mode"><option value="test" <?= ($paystackSettings['mode'] ?? 'test') === 'test' ? 'selected' : '' ?>>Test / Sandbox</option><option value="live" <?= ($paystackSettings['mode'] ?? '') === 'live' ? 'selected' : '' ?>>Live / Production</option></select></label>
                                <label class="settings-field span-2"><span>Public Key</span><input name="public_key" value="<?= e($paystackSettings['public_key'] ?? '') ?>" placeholder="pk_live_..."></label>
                                <label class="settings-field"><span>Charge Currency</span><input name="charge_currency" maxlength="3" value="<?= e($paystackSettings['charge_currency'] ?? 'NGN') ?>" placeholder="NGN"></label>
                                <label class="settings-field"><span>Secret Key</span><input type="password" name="secret_key" value="" placeholder="<?= ($paystackSettings['secret_key'] ?? '') !== '' ? 'Saved - leave blank to keep current value' : 'sk_live_...' ?>" autocomplete="new-password"></label>
                                <label class="settings-field"><span>Webhook Signature Secret</span><input type="password" name="webhook_secret" value="" placeholder="<?= ($paystackSettings['webhook_secret'] ?? '') !== '' ? 'Saved - leave blank to keep current value' : 'Leave blank to use secret key' ?>" autocomplete="new-password"></label>
                                <label class="settings-field span-2"><span>Initialize Endpoint</span><input type="url" name="initialize_endpoint" value="<?= e($paystackSettings['initialize_endpoint'] ?? 'https://api.paystack.co/transaction/initialize') ?>"></label>
                                <label class="settings-field span-2"><span>Verify Endpoint</span><input type="url" name="verify_endpoint" value="<?= e($paystackSettings['verify_endpoint'] ?? 'https://api.paystack.co/transaction/verify') ?>"></label>
                            </div>
                            <div class="settings-note">
                                <span>Paystack redirects customers to Paystack Checkout and verifies the transaction server-side. Storefront orders are converted to the Paystack charge currency before checkout.</span>
                            </div>
                            <button class="btn primary" type="submit">Save Paystack Integration</button>
                        </form>
                    <?php elseif (($gateway['code'] ?? '') === 'stripe'): ?>
                        <?php $stripeSettings = \App\PaymentService::stripeSettings($gateway); ?>
                        <form class="settings-form" method="post" action="<?= e(app_url('dashboard/payment-gateways')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                            <input type="hidden" name="action" value="save_payment_gateway">
                            <input type="hidden" name="gateway_id" value="<?= e($gateway['id']) ?>">
                            <div class="panel-header">
                                <div>
                                    <h2>Stripe Payment Integration</h2>
                                    <p>Add your Stripe API details. Webhook URL: <code><?= e(app_url('api/webhooks/stripe')) ?></code></p>
                                </div>
                            </div>
                            <div class="settings-form-grid">
                                <label class="settings-field"><span>Display Name</span><input name="name" value="<?= e($gateway['name']) ?>" required></label>
                                <label class="settings-field"><span>Status</span><select name="is_active"><option value="1" <?= (int)$gateway['is_active'] === 1 ? 'selected' : '' ?>>Active</option><option value="0" <?= (int)$gateway['is_active'] !== 1 ? 'selected' : '' ?>>Inactive</option></select></label>
                                <label class="settings-field"><span>Mode</span><select name="mode"><option value="test" <?= ($stripeSettings['mode'] ?? '') === 'test' ? 'selected' : '' ?>>Test / Sandbox</option><option value="live" <?= ($stripeSettings['mode'] ?? 'live') === 'live' ? 'selected' : '' ?>>Live / Production</option></select></label>
                                <label class="settings-field span-2"><span>Publishable Key</span><input name="public_key" value="<?= e($stripeSettings['public_key'] ?? '') ?>" placeholder="pk_live_..."></label>
                                <label class="settings-field"><span>Secret Key</span><input type="password" name="secret_key" value="" placeholder="<?= ($stripeSettings['secret_key'] ?? '') !== '' ? 'Saved - leave blank to keep current value' : 'sk_live_...' ?>" autocomplete="new-password"></label>
                                <label class="settings-field"><span>Webhook Secret</span><input type="password" name="webhook_secret" value="" placeholder="<?= ($stripeSettings['webhook_secret'] ?? '') !== '' ? 'Saved - leave blank to keep current value' : 'whsec_...' ?>" autocomplete="new-password"></label>
                            </div>
                            <div class="settings-note">
                                <span>Stripe checkout redirects shoppers to Stripe Hosted Checkout, then the webhook marks the local order paid after Stripe confirms payment.</span>
                            </div>
                            <button class="btn primary" type="submit">Save Stripe Integration</button>
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            </section>
        <?php elseif ($view === 'idempotency-logs'): ?>
            <section class="panel">
                <div class="panel-header"><div><h2>Idempotency Logs</h2><p>Duplicate payment protection and request replay audit trail.</p></div></div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Key</th><th>Provider</th><th>Order</th><th>Payment</th><th>Response</th><th>Status</th><th>Created</th><th>Updated</th></tr></thead>
                        <tbody>
                        <?php foreach (($paymentsData['idempotency'] ?? []) as $row): ?>
                            <tr>
                                <td><strong><?= e($row['idem_key']) ?></strong><small><?= e($row['request_hash'] ?: 'No hash') ?></small></td>
                                <td><?= e($row['provider'] ?: '-') ?></td>
                                <td><?= $row['order_id'] ? '<a href="' . e(app_url('dashboard/orders?order=' . (int)$row['order_id'])) . '">#' . e($row['order_number'] ?: $row['order_id']) . '</a>' : '-' ?></td>
                                <td><?= e($row['payment_id'] ?: '-') ?></td>
                                <td><?= e($row['response_code'] ?: '-') ?></td>
                                <td><span class="settings-badge <?= e($statusClass((string)$row['status'])) ?>"><?= e($row['status']) ?></span></td>
                                <td><?= e($row['created_at']) ?></td>
                                <td><?= e($row['updated_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($paymentsData['idempotency'] ?? []) === []): ?><tr><td colspan="8">No idempotency logs found yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php elseif ($view === 'multi-currency-settings'): ?>
            <section class="panel">
                <div class="panel-header"><div><h2>Multi-Currency Settings</h2><p>Enabled currencies, exchange rates and display precision.</p></div><a class="btn secondary" href="<?= e(app_url('dashboard/currency-settings')) ?>">Currency Settings</a></div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Currency</th><th>Symbol</th><th>Exchange Rate</th><th>Default</th><th>Enabled</th><th>Decimals</th><th>Updated</th></tr></thead>
                        <tbody>
                        <?php foreach (($paymentsData['currencies'] ?? []) as $currency): ?>
                            <tr>
                                <td><strong><?= e($currency['code']) ?></strong><small><?= e($currency['name']) ?></small></td>
                                <td><?= e($currency['symbol']) ?></td>
                                <td><?= number_format((float)$currency['exchange_rate'], 8) ?></td>
                                <td><?= (int)$currency['is_default'] === 1 ? 'Yes' : 'No' ?></td>
                                <td><?= (int)$currency['is_enabled'] === 1 ? 'Yes' : 'No' ?></td>
                                <td><?= number_format((int)$currency['decimal_places']) ?></td>
                                <td><?= e($currency['updated_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($paymentsData['currencies'] ?? []) === []): ?><tr><td colspan="7">No currencies configured.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </div>
</section>
