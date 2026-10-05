<?php
$shippingData = $shippingData ?? [];
$shippingLinks = [
    'shipping-zones' => 'Shipping Zones',
    'shipping-rates' => 'Shipping Rates',
    'aramex-api-settings' => 'Aramex API Settings',
    'shipment-tracking' => 'Shipment Tracking',
    'delivery-partners' => 'Delivery Partners',
];
$rows = $shippingData['rows'] ?? [];
$stats = $shippingData['stats'] ?? [];
$pagination = $shippingData['pagination'] ?? [];
$edit = $shippingData['edit'] ?? null;
$showForm = (bool)($shippingData['showForm'] ?? false);
$statusClass = static fn (string $status): string => 'status-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $status) ?? 'unknown');
$money = static fn (mixed $amount, ?string $currency = 'USD'): string => trim((string)($currency ?: 'USD')) . ' ' . number_format((float)$amount, 2);
$value = static fn (string $key, mixed $default = ''): string => e($edit[$key] ?? $default);
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
$searchPlaceholder = match ($view) {
    'shipping-rates' => 'Search methods by name, zone, carrier or vendor',
    'shipment-tracking' => 'Search shipments by tracking, order, carrier or vendor',
    'delivery-partners' => 'Search partners by name, code, contact or carrier',
    default => 'Search shipping zones by name or vendor',
};
?>

<section class="settings-shell shipping-shell">
    <aside class="settings-index panel">
        <div class="panel-header">
            <div>
                <h2>Shipping</h2>
                <p>Zones, rates, Aramex, tracking and delivery partner operations</p>
            </div>
        </div>
        <nav class="settings-nav" aria-label="Shipping pages">
            <?php foreach ($shippingLinks as $key => $label): ?>
                <a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(app_url('dashboard/' . $key)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="settings-content">
        <section class="metric-grid settings-metrics" aria-label="Shipping metrics">
            <div class="metric"><span>Zones</span><strong><?= number_format((int)($stats['zones'] ?? 0)) ?></strong><small>Service regions</small></div>
            <div class="metric"><span>Rates</span><strong><?= number_format((int)($stats['rates'] ?? 0)) ?></strong><small><?= number_format((int)($stats['methods'] ?? 0)) ?> methods</small></div>
            <div class="metric"><span>Shipments</span><strong><?= number_format((int)($stats['shipments'] ?? 0)) ?></strong><small><?= number_format((int)($stats['in_transit'] ?? 0)) ?> in transit</small></div>
            <div class="metric"><span>Partners</span><strong><?= number_format((int)($stats['partners'] ?? 0)) ?></strong><small><?= number_format((int)($stats['carriers'] ?? 0)) ?> carriers</small></div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2><?= e($shippingLinks[$view] ?? 'Shipping') ?></h2>
                    <p>
                        Configure shipping operations and monitor shipment status.
                        <?php if ($view !== 'aramex-api-settings'): ?>
                            Showing <?= number_format((int)($pagination['from'] ?? 0)) ?>-<?= number_format((int)($pagination['to'] ?? 0)) ?> of <?= number_format((int)($pagination['total'] ?? 0)) ?>.
                        <?php endif; ?>
                    </p>
                </div>
                <?php if ($view === 'shipping-zones'): ?>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/shipping-zones?new=1')) ?>">Add Zone</a>
                <?php elseif ($view === 'shipping-rates'): ?>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/shipping-rates?new=1')) ?>">Add Rate</a>
                <?php elseif ($view === 'delivery-partners'): ?>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/delivery-partners?new=1')) ?>">Add Partner</a>
                <?php endif; ?>
            </div>

            <?php if ($view === 'aramex-api-settings'): ?>
                <?php $aramex = $shippingData['aramex'] ?? []; ?>
                <form class="settings-form taxonomy-form" method="post" action="<?= e(app_url('dashboard/aramex-api-settings')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="save_aramex_settings">
                    <div class="panel-header">
                        <div><h2>Aramex API Settings</h2><p>Credentials, account identity and default shipper profile used for Aramex shipment creation.</p></div>
                        <button class="btn" type="submit">Save Settings</button>
                    </div>
                    <div class="settings-form-grid">
                        <label class="settings-field"><span>Enabled</span><select name="enabled"><option value="off" <?= ($aramex['enabled'] ?? 'off') === 'off' ? 'selected' : '' ?>>Off</option><option value="on" <?= ($aramex['enabled'] ?? '') === 'on' ? 'selected' : '' ?>>On</option></select></label>
                        <label class="settings-field"><span>Environment</span><select name="environment"><option value="test" <?= ($aramex['environment'] ?? 'test') === 'test' ? 'selected' : '' ?>>Test</option><option value="production" <?= ($aramex['environment'] ?? '') === 'production' ? 'selected' : '' ?>>Production</option></select></label>
                        <?php foreach ([
                            'account_number' => 'Account Number', 'account_pin' => 'Account PIN', 'account_entity' => 'Account Entity',
                            'account_country_code' => 'Account Country Code', 'username' => 'Username', 'password' => 'Password',
                            'api_base_url' => 'API Base URL', 'shipper_name' => 'Shipper Name', 'shipper_phone' => 'Shipper Phone',
                            'shipper_email' => 'Shipper Email', 'shipper_address' => 'Shipper Address', 'shipper_city' => 'Shipper City',
                            'shipper_state' => 'Shipper State', 'shipper_postcode' => 'Shipper Postcode', 'shipper_country_code' => 'Shipper Country Code',
                        ] as $key => $label): ?>
                            <label class="settings-field <?= $key === 'shipper_address' ? 'span-2' : '' ?>"><span><?= e($label) ?></span><input name="<?= e($key) ?>" value="<?= e($aramex[$key] ?? '') ?>"<?= $key === 'password' || $key === 'account_pin' ? ' type="password"' : '' ?>></label>
                        <?php endforeach; ?>
                    </div>
                </form>
            <?php elseif ($showForm && $view === 'shipping-zones'): ?>
                <form class="settings-form taxonomy-form" method="post" action="<?= e(app_url('dashboard/shipping-zones')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="save_shipping_zone">
                    <input type="hidden" name="zone_id" value="<?= e($edit['id'] ?? 0) ?>">
                    <div class="panel-header"><div><h2><?= $edit ? 'Edit Zone' : 'Add Zone' ?></h2><p>Define regional shipping areas and optional vendor-specific zones.</p></div><button class="btn" type="submit">Save Zone</button></div>
                    <div class="settings-form-grid">
                        <label class="settings-field"><span>Name</span><input name="name" required value="<?= $value('name') ?>"></label>
                        <label class="settings-field"><span>Vendor</span><select name="vendor_id"><option value="">Marketplace-wide</option><?php foreach (($shippingData['vendors'] ?? []) as $vendor): ?><option value="<?= e($vendor['id']) ?>" <?= (int)($edit['vendor_id'] ?? 0) === (int)$vendor['id'] ? 'selected' : '' ?>><?= e($vendor['store_name']) ?></option><?php endforeach; ?></select></label>
                        <label class="settings-field"><span>Sort Order</span><input type="number" name="sort_order" value="<?= $value('sort_order', '0') ?>"></label>
                        <label class="settings-field"><span>Status</span><select name="is_active"><option value="1" <?= (int)($edit['is_active'] ?? 1) === 1 ? 'selected' : '' ?>>Active</option><option value="0" <?= (int)($edit['is_active'] ?? 1) === 0 ? 'selected' : '' ?>>Inactive</option></select></label>
                    </div>
                </form>
            <?php elseif ($showForm && $view === 'shipping-rates'): ?>
                <form class="settings-form taxonomy-form" method="post" action="<?= e(app_url('dashboard/shipping-rates')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="save_shipping_method">
                    <input type="hidden" name="method_id" value="<?= e($edit['id'] ?? 0) ?>">
                    <div class="panel-header"><div><h2><?= $edit ? 'Edit Shipping Rate' : 'Add Shipping Rate' ?></h2><p>Configure a shipping method and its primary rate rule.</p></div><button class="btn" type="submit">Save Rate</button></div>
                    <div class="settings-form-grid">
                        <label class="settings-field"><span>Name</span><input name="name" required value="<?= $value('name') ?>"></label>
                        <label class="settings-field"><span>Code</span><input name="code" value="<?= $value('code') ?>"></label>
                        <label class="settings-field"><span>Zone</span><select name="zone_id"><option value="">No zone</option><?php foreach (($shippingData['zones'] ?? []) as $zone): ?><option value="<?= e($zone['id']) ?>" <?= (int)($edit['zone_id'] ?? 0) === (int)$zone['id'] ? 'selected' : '' ?>><?= e($zone['name']) ?></option><?php endforeach; ?></select></label>
                        <label class="settings-field"><span>Carrier</span><select name="carrier_id"><option value="">No carrier</option><?php foreach (($shippingData['carriers'] ?? []) as $carrier): ?><option value="<?= e($carrier['id']) ?>" <?= (int)($edit['carrier_id'] ?? 0) === (int)$carrier['id'] ? 'selected' : '' ?>><?= e($carrier['name']) ?></option><?php endforeach; ?></select></label>
                        <label class="settings-field"><span>Vendor</span><select name="vendor_id"><option value="">Marketplace-wide</option><?php foreach (($shippingData['vendors'] ?? []) as $vendor): ?><option value="<?= e($vendor['id']) ?>" <?= (int)($edit['vendor_id'] ?? 0) === (int)$vendor['id'] ? 'selected' : '' ?>><?= e($vendor['store_name']) ?></option><?php endforeach; ?></select></label>
                        <label class="settings-field"><span>Calculation</span><select name="calculation_type"><?php foreach (['flat_rate','free_shipping','table_rate','distance_rate','carrier_api','local_pickup'] as $type): ?><option value="<?= e($type) ?>" <?= ($edit['calculation_type'] ?? 'flat_rate') === $type ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $type))) ?></option><?php endforeach; ?></select></label>
                        <label class="settings-field"><span>Base Cost</span><input type="number" step="0.01" name="base_cost" value="<?= $value('base_cost', '0') ?>"></label>
                        <label class="settings-field"><span>Minimum Order</span><input type="number" step="0.01" name="min_order_amount" value="<?= $value('min_order_amount') ?>"></label>
                        <label class="settings-field"><span>Condition</span><select name="condition_type"><?php foreach (['none','weight','subtotal','quantity','distance'] as $type): ?><option value="<?= e($type) ?>" <?= ($edit['condition_type'] ?? 'none') === $type ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option><?php endforeach; ?></select></label>
                        <label class="settings-field"><span>Min Value</span><input type="number" step="0.01" name="min_value" value="<?= $value('min_value') ?>"></label>
                        <label class="settings-field"><span>Max Value</span><input type="number" step="0.01" name="max_value" value="<?= $value('max_value') ?>"></label>
                        <label class="settings-field"><span>Rate Cost</span><input type="number" step="0.01" name="cost" value="<?= $value('cost', '0') ?>"></label>
                        <label class="settings-field"><span>Per Item Cost</span><input type="number" step="0.01" name="per_item_cost" value="<?= $value('per_item_cost', '0') ?>"></label>
                        <label class="settings-field"><span>Status</span><select name="is_active"><option value="1" <?= (int)($edit['is_active'] ?? 1) === 1 ? 'selected' : '' ?>>Active</option><option value="0" <?= (int)($edit['is_active'] ?? 1) === 0 ? 'selected' : '' ?>>Inactive</option></select></label>
                        <label class="settings-field span-2"><span>Notes</span><textarea name="notes" rows="3"></textarea></label>
                    </div>
                </form>
            <?php elseif ($showForm && $view === 'delivery-partners'): ?>
                <form class="settings-form taxonomy-form" method="post" action="<?= e(app_url('dashboard/delivery-partners')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="save_delivery_partner">
                    <input type="hidden" name="partner_id" value="<?= e($edit['id'] ?? 0) ?>">
                    <div class="panel-header"><div><h2><?= $edit ? 'Edit Partner' : 'Add Partner' ?></h2><p>Manage third-party delivery partners and tracking templates.</p></div><button class="btn" type="submit">Save Partner</button></div>
                    <div class="settings-form-grid">
                        <label class="settings-field"><span>Name</span><input name="name" required value="<?= $value('name') ?>"></label>
                        <label class="settings-field"><span>Code</span><input name="code" value="<?= $value('code') ?>"></label>
                        <label class="settings-field"><span>Carrier</span><select name="carrier_id"><option value="">No carrier</option><?php foreach (($shippingData['carriers'] ?? []) as $carrier): ?><option value="<?= e($carrier['id']) ?>" <?= (int)($edit['carrier_id'] ?? 0) === (int)$carrier['id'] ? 'selected' : '' ?>><?= e($carrier['name']) ?></option><?php endforeach; ?></select></label>
                        <label class="settings-field"><span>Contact Email</span><input type="email" name="contact_email" value="<?= $value('contact_email') ?>"></label>
                        <label class="settings-field"><span>Contact Phone</span><input name="contact_phone" value="<?= $value('contact_phone') ?>"></label>
                        <label class="settings-field"><span>Status</span><select name="is_active"><option value="1" <?= (int)($edit['is_active'] ?? 1) === 1 ? 'selected' : '' ?>>Active</option><option value="0" <?= (int)($edit['is_active'] ?? 1) === 0 ? 'selected' : '' ?>>Inactive</option></select></label>
                        <label class="settings-field span-2"><span>Service Regions</span><textarea name="service_regions" rows="3"><?= $value('service_regions') ?></textarea></label>
                        <label class="settings-field span-2"><span>Tracking URL Template</span><input name="tracking_url_template" value="<?= $value('tracking_url_template') ?>" placeholder="https://example.com/track/{tracking_number}"></label>
                    </div>
                </form>
            <?php elseif ($showForm && $view === 'shipment-tracking'): ?>
                <form class="settings-form taxonomy-form" method="post" action="<?= e(app_url('dashboard/shipment-tracking')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="update_shipment_tracking">
                    <input type="hidden" name="shipment_id" value="<?= e($edit['id'] ?? 0) ?>">
                    <div class="panel-header"><div><h2>Edit Shipment Tracking</h2><p>Update carrier assignment, tracking number, tracking URL and delivery status.</p></div><button class="btn" type="submit">Save Tracking</button></div>
                    <div class="settings-form-grid">
                        <label class="settings-field"><span>Carrier</span><select name="carrier_id"><option value="">No carrier</option><?php foreach (($shippingData['carriers'] ?? []) as $carrier): ?><option value="<?= e($carrier['id']) ?>" <?= (int)($edit['carrier_id'] ?? 0) === (int)$carrier['id'] ? 'selected' : '' ?>><?= e($carrier['name']) ?></option><?php endforeach; ?></select></label>
                        <label class="settings-field"><span>Status</span><select name="status"><?php foreach (['pending','label_created','picked_up','in_transit','out_for_delivery','delivered','failed','returned','cancelled'] as $status): ?><option value="<?= e($status) ?>" <?= ($edit['status'] ?? 'pending') === $status ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></label>
                        <label class="settings-field"><span>Tracking Number</span><input name="tracking_number" value="<?= $value('tracking_number') ?>"></label>
                        <label class="settings-field"><span>Tracking URL</span><input name="tracking_url" value="<?= $value('tracking_url') ?>"></label>
                        <label class="settings-field"><span>Shipped At</span><input name="shipped_at" placeholder="YYYY-MM-DD HH:MM:SS" value="<?= $value('shipped_at') ?>"></label>
                        <label class="settings-field"><span>Delivered At</span><input name="delivered_at" placeholder="YYYY-MM-DD HH:MM:SS" value="<?= $value('delivered_at') ?>"></label>
                        <label class="settings-field"><span>Cost</span><input type="number" step="0.01" name="cost" value="<?= $value('cost') ?>"></label>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($view !== 'aramex-api-settings'): ?>
                <form class="admin-searchbar" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                    <input type="search" name="q" value="<?= e($pagination['search'] ?? '') ?>" placeholder="<?= e($searchPlaceholder) ?>">
                    <input type="hidden" name="per_page" value="<?= e($perPage) ?>">
                    <button class="btn secondary" type="submit">Search</button>
                    <?php if (($pagination['search'] ?? '') !== ''): ?><a class="btn secondary" href="<?= e(app_url('dashboard/' . $view)) ?>">Clear</a><?php endif; ?>
                </form>
            <?php endif; ?>

            <?php if ($view === 'shipping-zones'): ?>
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Zone</th><th>Vendor</th><th>Methods</th><th>Status</th><th>Sort</th><th>Updated</th><th></th></tr></thead><tbody>
                <?php foreach ($rows as $row): ?><tr><td><strong><?= e($row['name']) ?></strong><small>WP #<?= e($row['wp_zone_id'] ?: '-') ?></small></td><td><?= e($row['store_name'] ?: 'Marketplace-wide') ?></td><td><?= number_format((int)$row['method_count']) ?></td><td><span class="settings-badge <?= (int)$row['is_active'] === 1 ? 'status-active' : 'status-failed' ?>"><?= (int)$row['is_active'] === 1 ? 'active' : 'inactive' ?></span></td><td><?= number_format((int)$row['sort_order']) ?></td><td><?= e($row['updated_at']) ?></td><td><a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/shipping-zones?edit=' . (int)$row['id'])) ?>">Edit</a></td></tr><?php endforeach; ?>
                <?php if ($rows === []): ?><tr><td colspan="7">No shipping zones found.</td></tr><?php endif; ?></tbody></table></div>
            <?php elseif ($view === 'shipping-rates'): ?>
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Method</th><th>Zone</th><th>Carrier</th><th>Calculation</th><th>Rate Rule</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody>
                <?php foreach ($rows as $row): ?><tr><td><strong><?= e($row['name']) ?></strong><small><?= e($row['code']) ?></small></td><td><?= e($row['zone_name'] ?: 'No zone') ?></td><td><?= e($row['carrier_name'] ?: 'No carrier') ?><small><?= e($row['store_name'] ?: 'Marketplace-wide') ?></small></td><td><?= e(ucwords(str_replace('_', ' ', $row['calculation_type']))) ?><small>Base <?= e($money($row['base_cost'])) ?></small></td><td><?= e($row['condition_type'] ?: 'none') ?><small><?= e($money($row['cost'] ?? 0)) ?> + <?= e($money($row['per_item_cost'] ?? 0)) ?>/item</small></td><td><span class="settings-badge <?= (int)$row['is_active'] === 1 ? 'status-active' : 'status-failed' ?>"><?= (int)$row['is_active'] === 1 ? 'active' : 'inactive' ?></span></td><td><?= e($row['updated_at']) ?></td><td><a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/shipping-rates?edit=' . (int)$row['id'])) ?>">Edit</a></td></tr><?php endforeach; ?>
                <?php if ($rows === []): ?><tr><td colspan="8">No shipping rates found.</td></tr><?php endif; ?></tbody></table></div>
            <?php elseif ($view === 'shipment-tracking'): ?>
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Shipment</th><th>Order</th><th>Carrier</th><th>Tracking</th><th>Status</th><th>Dates</th><th>Cost</th><th></th></tr></thead><tbody>
                <?php foreach ($rows as $row): ?><tr><td><strong>Shipment #<?= e($row['id']) ?></strong><small><?= e($row['store_name'] ?: 'No vendor') ?></small></td><td><a href="<?= e(app_url('dashboard/orders?order=' . (int)$row['order_id'])) ?>">#<?= e($row['order_number'] ?: $row['order_id']) ?></a><small><?= e($row['method_name'] ?: '-') ?></small></td><td><?= e($row['carrier_name'] ?: 'No carrier') ?></td><td><?= e($row['tracking_number'] ?: 'Not assigned') ?><?php if (($row['tracking_url'] ?? '') !== ''): ?><small><a href="<?= e($row['tracking_url']) ?>" target="_blank" rel="noopener">Open tracking</a></small><?php endif; ?></td><td><span class="settings-badge <?= e($statusClass((string)$row['status'])) ?>"><?= e($row['status']) ?></span></td><td>Shipped: <?= e($row['shipped_at'] ?: '-') ?><small>Delivered: <?= e($row['delivered_at'] ?: '-') ?></small></td><td><?= e($money($row['cost'] ?? 0, $row['currency'] ?? 'USD')) ?></td><td><a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/shipment-tracking?edit=' . (int)$row['id'])) ?>">Edit</a></td></tr><?php endforeach; ?>
                <?php if ($rows === []): ?><tr><td colspan="8">No shipments found.</td></tr><?php endif; ?></tbody></table></div>
            <?php elseif ($view === 'delivery-partners'): ?>
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Partner</th><th>Carrier</th><th>Contact</th><th>Regions</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody>
                <?php foreach ($rows as $row): ?><tr><td><strong><?= e($row['name']) ?></strong><small><?= e($row['code']) ?></small></td><td><?= e($row['carrier_name'] ?: 'No carrier') ?></td><td><?= e($row['contact_email'] ?: '-') ?><small><?= e($row['contact_phone'] ?: '-') ?></small></td><td class="product-long-text"><?= e($row['service_regions'] ?: '-') ?></td><td><span class="settings-badge <?= (int)$row['is_active'] === 1 ? 'status-active' : 'status-failed' ?>"><?= (int)$row['is_active'] === 1 ? 'active' : 'inactive' ?></span></td><td><?= e($row['updated_at']) ?></td><td><a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/delivery-partners?edit=' . (int)$row['id'])) ?>">Edit</a></td></tr><?php endforeach; ?>
                <?php if ($rows === []): ?><tr><td colspan="7">No delivery partners found.</td></tr><?php endif; ?></tbody></table></div>
            <?php endif; ?>

            <?php if ($view !== 'aramex-api-settings' && (int)($pagination['total'] ?? 0) > 0): ?>
                <div class="admin-pagination">
                    <div class="pagination-summary">Page <?= number_format($page) ?> of <?= number_format((int)($pagination['totalPages'] ?? 1)) ?></div>
                    <div class="pagination-actions">
                        <a class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= e($listUrl(max(1, $page - 1))) ?>">Previous</a>
                        <?php for ($pageNumber = max(1, $page - 2); $pageNumber <= min((int)($pagination['totalPages'] ?? 1), $page + 2); $pageNumber++): ?>
                            <a class="pagination-btn <?= $pageNumber === $page ? 'active' : '' ?>" href="<?= e($listUrl($pageNumber)) ?>"><?= number_format($pageNumber) ?></a>
                        <?php endfor; ?>
                        <a class="pagination-btn <?= $page >= (int)($pagination['totalPages'] ?? 1) ? 'disabled' : '' ?>" href="<?= e($listUrl(min((int)($pagination['totalPages'] ?? 1), $page + 1))) ?>">Next</a>
                    </div>
                    <form class="pagination-size" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                        <?php if (($pagination['search'] ?? '') !== ''): ?><input type="hidden" name="q" value="<?= e($pagination['search']) ?>"><?php endif; ?>
                        <label><span>Rows</span><select name="per_page" onchange="this.form.submit()"><?php foreach (($pagination['perPageOptions'] ?? [25, 50, 100]) as $option): ?><option value="<?= e($option) ?>" <?= (int)$option === $perPage ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
                    </form>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>
