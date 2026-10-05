<?php
$ordersData = $ordersData ?? [];
$orderLinks = [
    'orders' => 'All Orders',
    'pending-orders' => 'Pending Orders',
    'processing-orders' => 'Processing Orders',
    'shipped-orders' => 'Shipped Orders',
    'delivered-orders' => 'Delivered Orders',
    'cancelled-orders' => 'Cancelled Orders',
    'returned-orders' => 'Returned Orders',
];
$stats = $ordersData['stats'] ?? [];
$statusClass = static fn (string $status): string => 'status-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $status) ?? 'unknown');
$money = static fn (mixed $amount, ?string $currency = 'USD'): string => trim((string)($currency ?: 'USD')) . ' ' . number_format((float)$amount, 2);
$orders = match ($view) {
    'pending-orders' => $ordersData['pending'] ?? [],
    'processing-orders' => $ordersData['processing'] ?? [],
    'shipped-orders' => $ordersData['shipped'] ?? [],
    'delivered-orders' => $ordersData['delivered'] ?? [],
    'cancelled-orders' => $ordersData['cancelled'] ?? [],
    'returned-orders' => $ordersData['returned'] ?? [],
    default => $ordersData['orders'] ?? [],
};
$pagination = $ordersData['pagination'] ?? [];
$orderPage = max(1, (int)($pagination['page'] ?? 1));
$orderTotalPages = max(1, (int)($pagination['totalPages'] ?? 1));
$orderPerPage = (int)($pagination['perPage'] ?? 25);
$listUrl = static function (int $page, ?int $perPage = null) use ($view, $orderPerPage): string {
    $query = [
        'page' => max(1, $page),
        'per_page' => $perPage ?? $orderPerPage,
    ];
    if (trim((string)($_GET['q'] ?? '')) !== '') {
        $query['q'] = trim((string)$_GET['q']);
    }
    return app_url('dashboard/' . $view . '?' . http_build_query($query));
};
$addressText = static function (?array $address): string {
    if (!$address) {
        return 'No address saved.';
    }

    $name = trim((string)($address['first_name'] ?? '') . ' ' . (string)($address['last_name'] ?? ''));
    $parts = array_filter([
        $name,
        $address['company'] ?? null,
        $address['address_line1'] ?? null,
        $address['address_line2'] ?? null,
        trim((string)($address['city'] ?? '') . ', ' . (string)($address['state'] ?? '') . ' ' . (string)($address['postcode'] ?? '')),
        $address['country_code'] ?? null,
        $address['phone'] ?? null,
        $address['email'] ?? null,
    ], static fn ($part): bool => trim((string)$part) !== '');

    return implode("\n", $parts);
};
$detail = $ordersData['detail'] ?? null;
?>

<section class="settings-shell orders-shell">
    <aside class="settings-index panel">
        <div class="panel-header">
            <div>
                <h2>Orders</h2>
                <p>Order lifecycle, payments, vendor splits and shipment tracking</p>
            </div>
        </div>
        <nav class="settings-nav" aria-label="Order pages">
            <?php foreach ($orderLinks as $key => $label): ?>
                <a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(app_url('dashboard/' . $key)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="settings-content">
        <section class="metric-grid settings-metrics" aria-label="Order metrics">
            <div class="metric"><span>Total Orders</span><strong><?= number_format((int)($stats['total_orders'] ?? 0)) ?></strong><small>Marketplace orders</small></div>
            <div class="metric"><span>Pending</span><strong><?= number_format((int)($stats['pending_orders'] ?? 0)) ?></strong><small>Awaiting action</small></div>
            <div class="metric"><span>Delivered</span><strong><?= number_format((int)($stats['delivered_orders'] ?? 0)) ?></strong><small>Completed orders</small></div>
            <div class="metric"><span>Cancelled</span><strong><?= number_format((int)($stats['cancelled_orders'] ?? 0)) ?></strong><small>Cancelled or failed</small></div>
        </section>

        <?php if ($detail): ?>
            <section class="panel order-detail-panel">
                <div class="panel-header">
                    <div>
                        <h2>Order #<?= e($detail['order_number'] ?: $detail['id']) ?></h2>
                        <p><?= e(($detail['customer_name'] ?: $detail['customer_email'] ?: $detail['guest_email'] ?: 'Guest customer') . ' | placed ' . ($detail['placed_at'] ?: $detail['created_at'])) ?></p>
                    </div>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/' . $view)) ?>">Back to Orders</a>
                </div>

                <div class="order-status-grid">
                    <div class="status-card">
                        <span>Order Status</span>
                        <strong class="settings-badge <?= e($statusClass((string)$detail['status'])) ?>"><?= e($detail['status']) ?></strong>
                    </div>
                    <div class="status-card">
                        <span>Payment</span>
                        <strong class="settings-badge <?= e($statusClass((string)$detail['payment_status'])) ?>"><?= e($detail['payment_status']) ?></strong>
                    </div>
                    <div class="status-card">
                        <span>Fulfillment</span>
                        <strong class="settings-badge <?= e($statusClass((string)$detail['fulfillment_status'])) ?>"><?= e($detail['fulfillment_status']) ?></strong>
                    </div>
                    <div class="status-card">
                        <span>Total</span>
                        <strong><?= e($money($detail['grand_total'], $detail['currency'])) ?></strong>
                    </div>
                </div>

                <div class="order-detail-grid">
                    <div class="detail-card">
                        <h3>Timeline</h3>
                        <ol class="order-timeline">
                            <li><strong>Created</strong><span><?= e($detail['created_at'] ?: '-') ?></span></li>
                            <li><strong>Placed</strong><span><?= e($detail['placed_at'] ?: '-') ?></span></li>
                            <li><strong>Paid</strong><span><?= e($detail['paid_at'] ?: 'Not paid') ?></span></li>
                            <li><strong>Completed</strong><span><?= e($detail['completed_at'] ?: 'Not completed') ?></span></li>
                            <li><strong>Last Updated</strong><span><?= e($detail['updated_at'] ?: '-') ?></span></li>
                        </ol>
                    </div>
                    <div class="detail-card">
                        <h3>Customer</h3>
                        <p><strong><?= e($detail['customer_name'] ?: 'Guest customer') ?></strong></p>
                        <p><?= e($detail['customer_email'] ?: $detail['guest_email'] ?: '-') ?></p>
                        <p><?= e($detail['customer_phone'] ?: '-') ?></p>
                        <?php if (($detail['customer_note'] ?? '') !== ''): ?><p class="muted-text"><?= e($detail['customer_note']) ?></p><?php endif; ?>
                    </div>
                    <div class="detail-card">
                        <h3>Billing Address</h3>
                        <p class="address-text"><?= nl2br(e($addressText($ordersData['billingAddress'] ?? null))) ?></p>
                    </div>
                    <div class="detail-card">
                        <h3>Shipping Address</h3>
                        <p class="address-text"><?= nl2br(e($addressText($ordersData['shippingAddress'] ?? null))) ?></p>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Items</h2>
                        <p>Products, shipping, fees, taxes and coupon rows attached to this order.</p>
                    </div>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Product</th><th>Item</th><th>Vendor</th><th>Type</th><th>Qty</th><th>Unit</th><th>Discount</th><th>Tax</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php foreach (($ordersData['items'] ?? []) as $item): ?>
                            <tr>
                                <td>
                                    <span class="product-thumb">
                                        <?php if (($item['image_path'] ?? '') !== ''): ?>
                                            <a href="<?= e(app_brand_asset_url($item['image_path'])) ?>" target="_blank" rel="noopener"><img src="<?= e(app_brand_asset_url($item['image_path'])) ?>" alt="<?= e($item['name']) ?>"></a>
                                        <?php else: ?>
                                            P
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td><strong><?= e($item['name']) ?></strong><small><?= e($item['sku'] ?: 'No SKU') ?></small></td>
                                <td><?= e($item['store_name'] ?: 'No vendor') ?></td>
                                <td><span class="settings-badge"><?= e($item['item_type']) ?></span></td>
                                <td><?= number_format((float)$item['quantity'], 2) ?></td>
                                <td><?= e($money($item['unit_price'], $detail['currency'])) ?></td>
                                <td><?= e($money($item['discount_total'], $detail['currency'])) ?></td>
                                <td><?= e($money($item['tax_total'], $detail['currency'])) ?></td>
                                <td><strong><?= e($money($item['total'], $detail['currency'])) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($ordersData['items'] ?? []) === []): ?>
                            <tr><td colspan="9">No order items found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="order-columns">
                <div class="panel">
                    <div class="panel-header"><div><h2>Payments</h2><p>Gateway status and references.</p></div></div>
                    <div class="activity-list">
                        <?php foreach (($ordersData['payments'] ?? []) as $payment): ?>
                            <div class="activity-item">
                                <strong><?= e(($payment['provider'] ?: 'Payment') . ' | ' . $money($payment['amount'], $payment['currency'])) ?></strong>
                                <span><?= e(($payment['status'] ?: '-') . ' | provider: ' . ($payment['provider_status'] ?: '-')) ?></span>
                                <span><?= e(($payment['provider_reference'] ?: 'No reference') . ' | paid: ' . ($payment['paid_at'] ?: 'not paid')) ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (($ordersData['payments'] ?? []) === []): ?>
                            <div class="activity-item"><strong>No payments found</strong><span>This migrated order has no payment rows.</span></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-header"><div><h2>Shipment Tracking</h2><p>Shipment status, tracking links and delivery timestamps.</p></div></div>
                    <div class="activity-list">
                        <?php foreach (($ordersData['shipments'] ?? []) as $shipment): ?>
                            <div class="activity-item">
                                <strong><?= e(($shipment['store_name'] ?: 'Shipment') . ' | ' . $shipment['status']) ?></strong>
                                <span><?= e('Tracking: ' . ($shipment['tracking_number'] ?: 'Not assigned')) ?></span>
                                <span><?= e('Shipped: ' . ($shipment['shipped_at'] ?: '-') . ' | Delivered: ' . ($shipment['delivered_at'] ?: '-')) ?></span>
                                <?php if (($shipment['tracking_url'] ?? '') !== ''): ?><a class="module-link compact-order-link" href="<?= e($shipment['tracking_url']) ?>" target="_blank" rel="noopener">Open Tracking</a><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (($ordersData['shipments'] ?? []) === []): ?>
                            <div class="activity-item"><strong>No shipment created</strong><span>Tracking will appear here once a shipment row exists.</span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><div><h2>Vendor Splits</h2><p>Vendor earnings, commission and marketplace fees for this order.</p></div></div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Vendor</th><th>Status</th><th>Gross</th><th>Vendor Earning</th><th>Commission</th><th>Gateway Fee</th><th>Updated</th></tr></thead>
                        <tbody>
                        <?php foreach (($ordersData['splits'] ?? []) as $split): ?>
                            <tr>
                                <td><strong><?= e($split['store_name'] ?: 'No vendor') ?></strong></td>
                                <td><span class="settings-badge <?= e($statusClass((string)$split['status'])) ?>"><?= e($split['status']) ?></span></td>
                                <td><?= e($money($split['gross_total'], $detail['currency'])) ?></td>
                                <td><?= e($money($split['vendor_earning'], $detail['currency'])) ?></td>
                                <td><?= e($money($split['platform_commission'], $detail['currency'])) ?></td>
                                <td><?= e($money($split['gateway_fee'], $detail['currency'])) ?></td>
                                <td><?= e($split['updated_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($ordersData['splits'] ?? []) === []): ?>
                            <tr><td colspan="7">No vendor split rows found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2><?= e($orderLinks[$view] ?? 'All Orders') ?></h2>
                    <p>
                        Review order lifecycle, payment state and fulfillment progress.
                        <?php if (!empty($pagination['isOrderList'])): ?>
                            Showing <?= number_format((int)($pagination['from'] ?? 0)) ?>-<?= number_format((int)($pagination['to'] ?? 0)) ?> of <?= number_format((int)($pagination['total'] ?? 0)) ?>.
                        <?php endif; ?>
                    </p>
                </div>
                <form class="admin-searchbar" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                    <input type="search" name="q" value="<?= e($pagination['search'] ?? '') ?>" placeholder="Search orders by number, customer, email or status">
                    <input type="hidden" name="per_page" value="<?= e($orderPerPage) ?>">
                    <button class="btn secondary" type="submit">Search</button>
                    <?php if (($pagination['search'] ?? '') !== ''): ?><a class="btn secondary" href="<?= e(app_url('dashboard/' . $view)) ?>">Clear</a><?php endif; ?>
                </form>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Payment</th><th>Fulfillment</th><th>Items</th><th>Total</th><th>Tracking</th><th>Placed</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong>#<?= e($order['order_number'] ?: $order['id']) ?></strong><small>WP #<?= e($order['wp_order_id'] ?: '-') ?></small></td>
                            <td><strong><?= e($order['customer_name'] ?: 'Guest') ?></strong><small><?= e($order['customer_email'] ?: $order['guest_email'] ?: '-') ?></small></td>
                            <td><span class="settings-badge <?= e($statusClass((string)$order['status'])) ?>"><?= e($order['status']) ?></span></td>
                            <td><span class="settings-badge <?= e($statusClass((string)$order['payment_status'])) ?>"><?= e($order['payment_status']) ?></span><small><?= e($money($order['paid_amount'], $order['currency'])) ?> paid</small></td>
                            <td><span class="settings-badge <?= e($statusClass((string)$order['fulfillment_status'])) ?>"><?= e($order['fulfillment_status']) ?></span></td>
                            <td><?= number_format((int)$order['item_count']) ?><small><?= number_format((float)$order['total_quantity'], 2) ?> qty | <?= number_format((int)$order['vendor_count']) ?> vendors</small></td>
                            <td><strong><?= e($money($order['grand_total'], $order['currency'])) ?></strong></td>
                            <td>
                                <?= e($order['latest_tracking_number'] ?: 'Not assigned') ?>
                                <small><?= e($order['latest_shipment_status'] ?: ((int)$order['shipment_count'] > 0 ? 'Shipment pending' : 'No shipment')) ?></small>
                            </td>
                            <td><?= e($order['placed_at'] ?: $order['created_at']) ?></td>
                            <td><a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/' . $view . '?order=' . (int)$order['id'])) ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($orders === []): ?>
                        <tr><td colspan="10">No orders found for this view.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($pagination['isOrderList']) && (int)($pagination['total'] ?? 0) > 0): ?>
                <div class="admin-pagination" aria-label="Order pagination">
                    <div class="pagination-summary">Page <?= number_format($orderPage) ?> of <?= number_format($orderTotalPages) ?></div>
                    <div class="pagination-actions">
                        <a class="pagination-btn <?= $orderPage <= 1 ? 'disabled' : '' ?>" href="<?= e($listUrl(max(1, $orderPage - 1))) ?>">Previous</a>
                        <?php
                        $startPage = max(1, $orderPage - 2);
                        $endPage = min($orderTotalPages, $orderPage + 2);
                        if ($endPage - $startPage < 4) {
                            $startPage = max(1, min($startPage, $endPage - 4));
                            $endPage = min($orderTotalPages, max($endPage, $startPage + 4));
                        }
                        ?>
                        <?php if ($startPage > 1): ?>
                            <a class="pagination-btn" href="<?= e($listUrl(1)) ?>">1</a>
                            <?php if ($startPage > 2): ?><span class="pagination-gap">...</span><?php endif; ?>
                        <?php endif; ?>
                        <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
                            <a class="pagination-btn <?= $pageNumber === $orderPage ? 'active' : '' ?>" href="<?= e($listUrl($pageNumber)) ?>"><?= number_format($pageNumber) ?></a>
                        <?php endfor; ?>
                        <?php if ($endPage < $orderTotalPages): ?>
                            <?php if ($endPage < $orderTotalPages - 1): ?><span class="pagination-gap">...</span><?php endif; ?>
                            <a class="pagination-btn" href="<?= e($listUrl($orderTotalPages)) ?>"><?= number_format($orderTotalPages) ?></a>
                        <?php endif; ?>
                        <a class="pagination-btn <?= $orderPage >= $orderTotalPages ? 'disabled' : '' ?>" href="<?= e($listUrl(min($orderTotalPages, $orderPage + 1))) ?>">Next</a>
                    </div>
                    <form class="pagination-size" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                        <?php if (($pagination['search'] ?? '') !== ''): ?><input type="hidden" name="q" value="<?= e($pagination['search']) ?>"><?php endif; ?>
                        <label>
                            <span>Rows</span>
                            <select name="per_page" onchange="this.form.submit()">
                                <?php foreach (($pagination['perPageOptions'] ?? [25, 50, 100]) as $option): ?>
                                    <option value="<?= e($option) ?>" <?= (int)$option === $orderPerPage ? 'selected' : '' ?>><?= e($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </form>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>
