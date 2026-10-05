<?php
$dashboardReportsData = $dashboardReportsData ?? [];
$summary = $dashboardReportsData['summary'] ?? [];
$range = $dashboardReportsData['range'] ?? [];
$trend = $dashboardReportsData['trend'] ?? [];
$money = static fn (mixed $amount, string $currency = 'USD'): string => $currency . ' ' . number_format((float)$amount, 2);
$percent = static fn (mixed $value): string => number_format((float)$value, 2) . '%';
$reportLinks = [
    'sales-analytics' => 'Sales Analytics',
    'revenue-report' => 'Revenue Report',
    'vendor-performance' => 'Vendor Performance',
    'affiliate-performance' => 'Affiliate Performance',
];
$period = (string)($range['period'] ?? 'month');
?>

<section class="settings-shell reports-shell">
    <aside class="settings-index panel">
        <div class="panel-header">
            <div>
                <h2>Reports</h2>
                <p>Live sales, revenue, vendor and affiliate performance</p>
            </div>
        </div>
        <nav class="settings-nav" aria-label="Dashboard reports">
            <?php foreach ($reportLinks as $key => $label): ?>
                <a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(app_url('dashboard/' . $key . '?period=' . $period)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="settings-content">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2><?= e($reportLinks[$view] ?? 'Dashboard Report') ?></h2>
                    <p>Reporting period: <?= e((string)($range['label'] ?? 'Current period')) ?></p>
                </div>
            </div>
            <form class="admin-searchbar report-filter" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                <select name="period" onchange="this.form.submit()">
                    <?php foreach (['today' => 'Today', 'week' => 'Last 7 Days', 'month' => 'This Month', 'year' => 'This Year', 'custom' => 'Custom Dates'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $period === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="start" value="<?= e(isset($range['start']) ? $range['start']->format('Y-m-d') : '') ?>">
                <input type="date" name="end" value="<?= e(isset($range['end']) ? $range['end']->format('Y-m-d') : '') ?>">
                <button class="btn secondary" type="submit">Apply</button>
            </form>
        </section>

        <section class="metric-grid settings-metrics" aria-label="Report metrics">
            <div class="metric metric-revenue"><span>Paid Revenue</span><strong><?= e($money($summary['paid_revenue'] ?? 0)) ?></strong><small>Paid orders + Manage My Store (<?= e($money($summary['manage_store_revenue'] ?? 0)) ?>)</small></div>
            <div class="metric"><span>Gross Sales</span><strong><?= e($money($summary['gross_sales'] ?? 0)) ?></strong><small>Non-cancelled order value</small></div>
            <div class="metric"><span>Orders</span><strong><?= number_format((int)($summary['total_orders'] ?? 0)) ?></strong><small><?= e($money($summary['average_order_value'] ?? 0)) ?> average order</small></div>
            <div class="metric"><span>Customers</span><strong><?= number_format((int)($summary['customers'] ?? 0)) ?></strong><small><?= number_format((int)($summary['failed_payments'] ?? 0)) ?> failed payments</small></div>
        </section>

        <?php if (in_array($view, ['sales-analytics', 'revenue-report'], true)): ?>
            <section class="dashboard-grid">
                <div class="panel analytics-panel">
                    <div class="panel-header"><div><h2>Sales Trend</h2><p>Daily orders and gross revenue for the selected period.</p></div></div>
                    <div class="chart-card report-chart">
                        <div class="bar-chart">
                            <?php foreach ($trend as $point): ?>
                                <i style="--h: <?= e((int)($point['height'] ?? 4)) ?>%" title="<?= e(($point['report_date'] ?? '') . ' | ' . $money($point['gross_sales'] ?? 0) . ' | ' . number_format((int)($point['orders'] ?? 0)) . ' orders') ?>"></i>
                            <?php endforeach; ?>
                            <?php if ($trend === []): ?><i style="--h:4%"></i><?php endif; ?>
                        </div>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Date</th><th>Orders</th><th>Gross Sales</th><th>Paid Revenue</th></tr></thead>
                            <tbody>
                            <?php foreach ($trend as $point): ?>
                                <tr><td><?= e($point['report_date']) ?></td><td><?= number_format((int)$point['orders']) ?></td><td><?= e($money($point['gross_sales'])) ?></td><td><?= e($money($point['paid_revenue'])) ?></td></tr>
                            <?php endforeach; ?>
                            <?php if ($trend === []): ?><tr><td colspan="4">No sales found for this period.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <aside class="panel">
                    <div class="panel-header"><div><h2>Revenue Breakdown</h2><p>Currency and order status totals.</p></div></div>
                    <div class="status-list">
                        <?php foreach (($dashboardReportsData['currencyBreakdown'] ?? []) as $currency): ?>
                            <div class="status-item"><strong><?= e($currency['currency']) ?> <?= e(number_format((float)$currency['gross_sales'], 2)) ?></strong><span><?= number_format((int)$currency['orders']) ?> orders | paid <?= e(number_format((float)$currency['paid_revenue'], 2)) ?></span></div>
                        <?php endforeach; ?>
                        <?php foreach (($dashboardReportsData['statusBreakdown'] ?? []) as $status): ?>
                            <div class="status-item"><strong><?= e(ucwords(str_replace('_', ' ', (string)$status['status']))) ?></strong><span><?= number_format((int)$status['total']) ?> orders | <?= e($money($status['gross_sales'])) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </aside>
            </section>

            <section class="panel">
                <div class="panel-header"><div><h2>Top Selling Products</h2><p>Product revenue and quantities from order line items.</p></div></div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Product</th><th>SKU</th><th>Orders</th><th>Quantity</th><th>Revenue</th></tr></thead>
                        <tbody>
                        <?php foreach (($dashboardReportsData['topProducts'] ?? []) as $product): ?>
                            <tr><td><strong><?= e($product['product_name']) ?></strong></td><td><?= e($product['sku'] ?: '-') ?></td><td><?= number_format((int)$product['orders']) ?></td><td><?= number_format((float)$product['quantity'], 2) ?></td><td><?= e($money($product['revenue'])) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (($dashboardReportsData['topProducts'] ?? []) === []): ?><tr><td colspan="5">No product sales found for this period.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php elseif ($view === 'vendor-performance'): ?>
            <section class="panel">
                <div class="panel-header"><div><h2>Vendor Performance</h2><p>Gross sales, vendor earnings, platform commission and fees.</p></div></div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Vendor</th><th>Status</th><th>Orders</th><th>Gross Sales</th><th>Vendor Earnings</th><th>Platform Commission</th><th>Gateway Fees</th></tr></thead>
                        <tbody>
                        <?php foreach (($dashboardReportsData['vendors'] ?? []) as $vendor): ?>
                            <tr>
                                <td><strong><?= e($vendor['store_name']) ?></strong></td>
                                <td><span class="settings-badge status-<?= e($vendor['status']) ?>"><?= e($vendor['status']) ?></span></td>
                                <td><?= number_format((int)$vendor['orders']) ?></td>
                                <td><?= e($money($vendor['gross_sales'])) ?></td>
                                <td><?= e($money($vendor['vendor_earning'])) ?></td>
                                <td><?= e($money($vendor['platform_commission'])) ?></td>
                                <td><?= e($money($vendor['gateway_fee'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($dashboardReportsData['vendors'] ?? []) === []): ?><tr><td colspan="7">No vendor split sales found for this period.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php elseif ($view === 'affiliate-performance'): ?>
            <section class="metric-grid settings-metrics" aria-label="Affiliate metrics">
                <div class="metric"><span>Affiliate Visits</span><strong><?= number_format((int)($summary['affiliate_visits'] ?? 0)) ?></strong><small>Tracked visits</small></div>
                <div class="metric"><span>Conversions</span><strong><?= number_format((int)($summary['affiliate_conversions'] ?? 0)) ?></strong><small><?= e($percent($summary['conversion_rate'] ?? 0)) ?> conversion rate</small></div>
                <div class="metric"><span>Discounts</span><strong><?= e($money($summary['discounts'] ?? 0)) ?></strong><small>Order discount total</small></div>
                <div class="metric"><span>Captured</span><strong><?= e($money($summary['captured_payments'] ?? 0)) ?></strong><small>Paid payment rows</small></div>
            </section>
            <section class="panel">
                <div class="panel-header"><div><h2>Affiliate Performance</h2><p>Referral count, commission, order total and approval state.</p></div></div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Affiliate</th><th>Status</th><th>Referrals</th><th>Pending</th><th>Approved</th><th>Order Total</th><th>Commission</th></tr></thead>
                        <tbody>
                        <?php foreach (($dashboardReportsData['affiliates'] ?? []) as $affiliate): ?>
                            <tr>
                                <td><strong><?= e($affiliate['display_name'] ?: $affiliate['email'] ?: $affiliate['referral_code']) ?></strong><small><?= e($affiliate['referral_code']) ?></small></td>
                                <td><span class="settings-badge status-<?= e($affiliate['status']) ?>"><?= e($affiliate['status']) ?></span></td>
                                <td><?= number_format((int)$affiliate['referrals']) ?></td>
                                <td><?= number_format((int)$affiliate['pending_referrals']) ?></td>
                                <td><?= number_format((int)$affiliate['approved_referrals']) ?></td>
                                <td><?= e($money($affiliate['order_total'])) ?></td>
                                <td><?= e($money($affiliate['commission'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($dashboardReportsData['affiliates'] ?? []) === []): ?><tr><td colspan="7">No affiliate records found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <section class="panel">
            <div class="panel-header"><div><h2>Recent Orders</h2><p>Latest orders inside this report period.</p></div><a class="btn secondary" href="<?= e(app_url('dashboard/orders')) ?>">All Orders</a></div>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Payment</th><th>Total</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach (($dashboardReportsData['recentOrders'] ?? []) as $order): ?>
                        <tr><td><a href="<?= e(app_url('dashboard/orders?order=' . (int)$order['id'])) ?>"><strong><?= e($order['order_number']) ?></strong></a></td><td><?= e($order['customer_name']) ?></td><td><?= e($order['status']) ?></td><td><?= e($order['payment_status']) ?></td><td><?= e($money($order['grand_total'], $order['currency'])) ?></td><td><?= e($order['created_at']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (($dashboardReportsData['recentOrders'] ?? []) === []): ?><tr><td colspan="6">No recent orders in this report period.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</section>
