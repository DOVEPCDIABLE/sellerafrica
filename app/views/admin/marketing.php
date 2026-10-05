<?php
$marketingData = $marketingData ?? [];
$links = $marketingData['links'] ?? [];
$stats = $marketingData['stats'] ?? [];
$rows = $marketingData['rows'] ?? [];
$pagination = $marketingData['pagination'] ?? [];
$coupon = $marketingData['couponEdit'] ?? [];
$showCouponForm = (bool)($marketingData['showCouponForm'] ?? false);
$showCampaignForm = (bool)($marketingData['showCampaignForm'] ?? false);
$showMarketingForm = (bool)($marketingData['showMarketingForm'] ?? false);
$marketingEdit = is_array($marketingData['marketingEdit'] ?? null) ? $marketingData['marketingEdit'] : [];
$money = static fn (mixed $amount, string $currency = 'USD'): string => $currency . ' ' . number_format((float)$amount, 2);
$statusClass = static fn (string $status): string => 'status-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $status) ?? 'unknown');
$dateInput = static fn (mixed $value): string => $value ? date('Y-m-d\TH:i', strtotime((string)$value)) : '';
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
$moduleCopy = [
    'popups' => ['title' => 'Popups', 'summary' => 'Homepage and promotional modal campaigns with scheduling, impressions and clicks.'],
    'sliders' => ['title' => 'Sliders', 'summary' => 'Hero, storefront, and mobile app carousel slides with sort order and active windows.'],
    'ads-banners' => ['title' => 'Ads Banners', 'summary' => 'Storefront ad placements with campaign dates, target links and performance counters.'],
    'announcements' => ['title' => 'Announcements', 'summary' => 'Audience-targeted messages for buyers, vendors, affiliates and storefront visitors.'],
    'email-campaigns' => ['title' => 'Email Campaigns', 'summary' => 'Bulk email campaigns, recipient totals, opens, clicks and schedule state.'],
    'abandoned-cart-emails' => ['title' => 'Abandoned Cart Emails', 'summary' => 'Recovery email automations with delay timing and linked coupon offers.'],
    'marketing-pixels' => ['title' => 'Pixels', 'summary' => 'Google and Facebook tracking snippets for storefront analytics and ad conversion events.'],
];
?>

<section class="settings-shell marketing-shell">
    <aside class="settings-index panel">
        <div class="panel-header">
            <div>
                <h2>Marketing</h2>
                <p>Coupons, promotional placements, announcements and email growth tools</p>
            </div>
        </div>
        <nav class="settings-nav" aria-label="Marketing pages">
            <?php foreach ($links as $key => $label): ?>
                <a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(app_url('dashboard/' . $key)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="settings-content">
        <section class="metric-grid settings-metrics" aria-label="Marketing metrics">
            <div class="metric"><span>Coupons</span><strong><?= number_format((int)($stats['coupons'] ?? 0)) ?></strong><small><?= number_format((int)($stats['active_coupons'] ?? 0)) ?> active</small></div>
            <div class="metric"><span>Redemptions</span><strong><?= number_format((int)($stats['redemptions'] ?? 0)) ?></strong><small><?= e($money($stats['discount_total'] ?? 0)) ?> discounts</small></div>
            <div class="metric"><span>Promotions</span><strong><?= number_format((int)($stats['popups'] ?? 0) + (int)($stats['sliders'] ?? 0) + (int)($stats['banners'] ?? 0)) ?></strong><small>Popups, sliders and ads</small></div>
            <div class="metric"><span>Campaigns</span><strong><?= number_format((int)($stats['campaigns'] ?? 0)) ?></strong><small><?= number_format((int)($marketingData['pendingEmailJobs'] ?? 0)) ?> queued emails</small></div>
        </section>

        <?php if ($view === 'marketing-pixels'): ?>
            <?php
            $pixelFields = $marketingData['pixelFields'] ?? [];
            $pixelValues = $marketingData['pixelValues'] ?? [];
            ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Marketing Pixels</h2>
                        <p>Add Google and Facebook tracking snippets for storefront pages. Place head scripts in head fields and noscript/body tags in body fields.</p>
                    </div>
                </div>
                <form class="settings-form" method="post" action="<?= e(app_url('dashboard/marketing-pixels')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="save_marketing_pixels">
                    <?php foreach ($pixelFields as $field): ?>
                        <?php
                        $key = (string)($field['key'] ?? '');
                        $type = (string)($field['type'] ?? 'text');
                        $value = (string)($pixelValues[$key] ?? ($field['default'] ?? ''));
                        ?>
                        <label class="<?= $type === 'textarea' ? 'full' : '' ?>">
                            <span><?= e((string)($field['label'] ?? $key)) ?></span>
                            <?php if ($type === 'select'): ?>
                                <select name="settings[<?= e($key) ?>]">
                                    <?php foreach (($field['options'] ?? []) as $optionValue => $optionLabel): ?>
                                        <option value="<?= e((string)$optionValue) ?>" <?= $value === (string)$optionValue ? 'selected' : '' ?>><?= e((string)$optionLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <textarea name="settings[<?= e($key) ?>]" rows="8" spellcheck="false" placeholder="<?= str_contains($key, 'body') ? 'Optional noscript/body code' : 'Paste provider script code here' ?>"><?= e($value) ?></textarea>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                    <div class="settings-note full">
                        <strong>Placement</strong>
                        <span>Saved snippets are injected on public storefront pages only. Turn Enable Pixels off to keep the codes saved but stop rendering them.</span>
                    </div>
                    <div class="form-actions full"><button class="btn primary" type="submit">Save Pixels</button></div>
                </form>
            </section>
        <?php elseif ($view === 'coupons'): ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2><?= $showCouponForm ? ((int)($coupon['id'] ?? 0) > 0 ? 'Edit Coupon' : 'Add Coupon') : 'Coupons' ?></h2>
                        <p>Create marketplace or vendor-specific coupons with limits, validity windows and audit tracking.</p>
                    </div>
                    <a class="btn secondary" href="<?= e(app_url($showCouponForm ? 'dashboard/coupons' : 'dashboard/coupons?new=1')) ?>"><?= $showCouponForm ? 'Back To Coupons' : 'Add Coupon' ?></a>
                </div>

                <?php if ($showCouponForm): ?>
                    <form class="settings-form" method="post" action="<?= e(app_url('dashboard/coupons')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                        <input type="hidden" name="action" value="save_coupon">
                        <input type="hidden" name="coupon_id" value="<?= e((int)($coupon['id'] ?? 0)) ?>">
                        <label><span>Coupon Code</span><input type="text" name="code" value="<?= e($coupon['code'] ?? '') ?>" placeholder="WELCOME10" required></label>
                        <label><span>Status</span><select name="status">
                            <?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'expired' => 'Expired'] as $key => $label): ?><option value="<?= e($key) ?>" <?= ($coupon['status'] ?? 'active') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                        </select></label>
                        <label><span>Discount Type</span><select name="discount_type">
                            <?php foreach (['percentage' => 'Percentage', 'fixed_cart' => 'Fixed Cart', 'fixed_product' => 'Fixed Product', 'free_shipping' => 'Free Shipping'] as $key => $label): ?><option value="<?= e($key) ?>" <?= ($coupon['discount_type'] ?? 'percentage') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                        </select></label>
                        <label><span>Amount</span><input type="number" name="amount" value="<?= e($coupon['amount'] ?? '10') ?>" min="0" step="0.01"></label>
                        <label><span>Vendor Scope</span><select name="vendor_id">
                            <option value="">Marketplace-wide</option>
                            <?php foreach (($marketingData['vendors'] ?? []) as $vendor): ?><option value="<?= e((int)$vendor['id']) ?>" <?= (int)($coupon['vendor_id'] ?? 0) === (int)$vendor['id'] ? 'selected' : '' ?>><?= e($vendor['store_name']) ?></option><?php endforeach; ?>
                        </select></label>
                        <label><span>Minimum Cart Amount</span><input type="number" name="minimum_amount" value="<?= e($coupon['minimum_amount'] ?? '') ?>" min="0" step="0.01"></label>
                        <label><span>Maximum Discount</span><input type="number" name="maximum_discount" value="<?= e($coupon['maximum_discount'] ?? '') ?>" min="0" step="0.01"></label>
                        <label><span>Total Usage Limit</span><input type="number" name="usage_limit" value="<?= e($coupon['usage_limit'] ?? '') ?>" min="1" step="1"></label>
                        <label><span>Usage Limit Per User</span><input type="number" name="usage_limit_per_user" value="<?= e($coupon['usage_limit_per_user'] ?? '') ?>" min="1" step="1"></label>
                        <label><span>Starts At</span><input type="datetime-local" name="starts_at" value="<?= e($dateInput($coupon['starts_at'] ?? null)) ?>"></label>
                        <label><span>Expires At</span><input type="datetime-local" name="expires_at" value="<?= e($dateInput($coupon['expires_at'] ?? null)) ?>"></label>
                        <label class="full"><span>Description</span><textarea name="description" rows="4" placeholder="Internal or storefront coupon description"><?= e($coupon['description'] ?? '') ?></textarea></label>
                        <div class="form-actions full"><button class="btn primary" type="submit">Save Coupon</button></div>
                    </form>
                <?php else: ?>
                    <form class="admin-searchbar" method="get" action="<?= e(app_url('dashboard/coupons')) ?>">
                        <input type="search" name="q" value="<?= e($pagination['search'] ?? '') ?>" placeholder="Search coupons by code, status, vendor or type">
                        <input type="hidden" name="per_page" value="<?= e($perPage) ?>">
                        <button class="btn secondary" type="submit">Search</button>
                        <?php if (($pagination['search'] ?? '') !== ''): ?><a class="btn secondary" href="<?= e(app_url('dashboard/coupons')) ?>">Clear</a><?php endif; ?>
                    </form>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Code</th><th>Discount</th><th>Scope</th><th>Limits</th><th>Used</th><th>Dates</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><strong><?= e($row['code']) ?></strong><small><?= e($row['description'] ?: 'No description') ?></small></td>
                                    <td><strong><?= e($row['discount_type'] === 'percentage' ? number_format((float)$row['amount'], 2) . '%' : ($row['discount_type'] === 'free_shipping' ? 'Free shipping' : $money($row['amount']))) ?></strong><small>Min <?= e($row['minimum_amount'] !== null ? $money($row['minimum_amount']) : '-') ?> | Max <?= e($row['maximum_discount'] !== null ? $money($row['maximum_discount']) : '-') ?></small></td>
                                    <td><?= e($row['store_name'] ?: 'Marketplace-wide') ?></td>
                                    <td><?= e($row['usage_limit'] ?? 'Unlimited') ?><small>Per user: <?= e($row['usage_limit_per_user'] ?? 'Unlimited') ?></small></td>
                                    <td><?= number_format((int)$row['used_count']) ?><small><?= e($money($row['discount_total'] ?? 0)) ?> total</small></td>
                                    <td><span><?= e($row['starts_at'] ?: 'Now') ?></span><small>Expires: <?= e($row['expires_at'] ?: 'Never') ?></small></td>
                                    <td><span class="settings-badge <?= e($statusClass((string)$row['status'])) ?>"><?= e($row['status']) ?></span></td>
                                    <td class="action-cell">
                                        <a class="btn secondary" href="<?= e(app_url('dashboard/coupons?edit=' . (int)$row['id'])) ?>">Edit</a>
                                        <form method="post" action="<?= e(app_url('dashboard/coupons')) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                            <input type="hidden" name="action" value="update_coupon_status">
                                            <input type="hidden" name="coupon_id" value="<?= e((int)$row['id']) ?>">
                                            <input type="hidden" name="status" value="<?= e($row['status'] === 'active' ? 'inactive' : 'active') ?>">
                                            <button class="btn secondary" type="submit"><?= $row['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
                                        </form>
                                        <form method="post" action="<?= e(app_url('dashboard/coupons')) ?>" onsubmit="return confirm('Delete this coupon? Coupons with redemptions will be deactivated instead.');">
                                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_coupon">
                                            <input type="hidden" name="coupon_id" value="<?= e((int)$row['id']) ?>">
                                            <button class="btn secondary danger" type="submit">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($rows === []): ?><tr><td colspan="8">No coupons found.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <?php if (!$showCouponForm): ?>
                <section class="dashboard-grid lower-dashboard-grid">
                    <div class="panel">
                        <div class="panel-header"><div><h2>Recent Redemptions</h2><p>Latest coupon usage across orders and customers.</p></div></div>
                        <div class="status-list">
                            <?php foreach (($marketingData['recentRedemptions'] ?? []) as $redemption): ?>
                                <div class="status-item"><strong><?= e($redemption['code']) ?> | <?= e($money($redemption['discount_amount'])) ?></strong><span><?= e(($redemption['display_name'] ?: $redemption['email'] ?: 'Guest') . ' | Order ' . ($redemption['order_number'] ?: '-') . ' | ' . $redemption['created_at']) ?></span></div>
                            <?php endforeach; ?>
                            <?php if (($marketingData['recentRedemptions'] ?? []) === []): ?><div class="status-item"><strong>No redemptions yet</strong><span>Coupon usage will appear here after checkout applies discounts.</span></div><?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        <?php elseif ($view === 'email-campaigns'): ?>
            <section class="panel">
                <div class="panel-header">
                    <div><h2><?= $showCampaignForm ? 'Create Email Campaign' : 'Email Campaigns' ?></h2><p>Send queued marketing emails to buyers, vendors, affiliates or every active user at a controlled pace.</p></div>
                    <a class="btn secondary" href="<?= e(app_url($showCampaignForm ? 'dashboard/email-campaigns' : 'dashboard/email-campaigns?new=1')) ?>"><?= $showCampaignForm ? 'Back To Campaigns' : 'New Campaign' ?></a>
                </div>

                <?php if ($showCampaignForm): ?>
                    <form class="settings-form" method="post" action="<?= e(app_url('dashboard/email-campaigns')) ?>" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                        <input type="hidden" name="action" value="queue_marketing_campaign">
                        <label><span>Campaign Name</span><input type="text" name="name" placeholder="July marketplace update" required></label>
                        <label><span>Email Subject</span><input type="text" name="subject" placeholder="Fresh offers from Seller Africa" required></label>
                        <label><span>Audience</span><select name="audience">
                            <option value="buyers">Customers / Buyers</option>
                            <option value="vendors">Vendors</option>
                            <option value="affiliates">Affiliates</option>
                            <option value="all">All active users</option>
                        </select></label>
                        <label><span>Start Sending</span><input type="datetime-local" name="scheduled_at"></label>
                        <label><span>Seconds Between Emails</span><input type="number" name="pace_seconds" value="60" min="10" max="3600" step="5"></label>
                        <label><span>Campaign Image</span><input type="file" name="campaign_image" accept="image/png,image/jpeg,image/webp"></label>
                        <label class="full"><span>Message</span><textarea name="body_html" rows="9" placeholder="<p>Hello, here is what is new...</p>" required></textarea></label>
                        <div class="settings-note full"><strong>Queue behavior</strong><span>Each recipient is queued as a separate email job, spaced by the seconds above, so the worker sends steadily instead of blasting everyone at once.</span></div>
                        <div class="form-actions full"><button class="btn primary" type="submit">Queue Campaign</button></div>
                    </form>
                <?php else: ?>
                    <form class="admin-searchbar" method="get" action="<?= e(app_url('dashboard/email-campaigns')) ?>">
                        <input type="search" name="q" value="<?= e($pagination['search'] ?? '') ?>" placeholder="Search campaigns by name, subject, audience or status">
                        <input type="hidden" name="per_page" value="<?= e($perPage) ?>">
                        <button class="btn secondary" type="submit">Search</button>
                    </form>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Campaign</th><th>Audience</th><th>Status</th><th>Recipients</th><th>Schedule</th><th>Updated</th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><strong><?= e($row['name'] ?? 'Campaign') ?></strong><small><?= e($row['subject'] ?? '') ?></small></td>
                                    <td><?= e(ucwords(str_replace('_', ' ', (string)($row['audience'] ?? 'buyers')))) ?></td>
                                    <td><span class="settings-badge <?= e($statusClass((string)($row['status'] ?? 'draft'))) ?>"><?= e($row['status'] ?? 'draft') ?></span></td>
                                    <td><?= number_format((int)($row['total_recipients'] ?? 0)) ?><small>Opens <?= number_format((int)($row['opened_count'] ?? 0)) ?> | Clicks <?= number_format((int)($row['clicked_count'] ?? 0)) ?></small></td>
                                    <td><?= e($row['scheduled_at'] ?? 'Immediate') ?><small>Sent: <?= e($row['sent_at'] ?? '-') ?></small></td>
                                    <td><?= e($row['updated_at'] ?? $row['created_at'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($rows === []): ?><tr><td colspan="6">No email campaigns yet.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php elseif (in_array($view, ['sliders', 'ads-banners'], true)): ?>
            <?php $copy = $moduleCopy[$view] ?? ['title' => $links[$view] ?? 'Marketing', 'summary' => 'Marketing module records.']; ?>
            <section class="panel">
                <div class="panel-header">
                    <div><h2><?= $showMarketingForm ? ((int)($marketingEdit['id'] ?? 0) > 0 ? 'Edit ' : 'New ') . e(rtrim($copy['title'], 's')) : e($copy['title']) ?></h2><p><?= e($copy['summary']) ?></p></div>
                    <a class="btn secondary" href="<?= e(app_url($showMarketingForm ? 'dashboard/' . $view : 'dashboard/' . $view . '?new=1')) ?>"><?= $showMarketingForm ? 'Back To List' : 'New ' . e(rtrim($copy['title'], 's')) ?></a>
                </div>
                <?php if ($showMarketingForm && $view === 'sliders'): ?>
                    <form class="settings-form" method="post" action="<?= e(app_url('dashboard/sliders')) ?>" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                        <input type="hidden" name="action" value="save_marketing_slider">
                        <input type="hidden" name="slider_id" value="<?= e((int)($marketingEdit['id'] ?? 0)) ?>">
                        <label><span>Title</span><input type="text" name="title" value="<?= e($marketingEdit['title'] ?? '') ?>" required></label>
                        <label><span>Status</span><select name="status"><?php foreach (['active' => 'Active', 'draft' => 'Draft', 'paused' => 'Paused', 'expired' => 'Expired'] as $key => $label): ?><option value="<?= e($key) ?>" <?= ($marketingEdit['status'] ?? 'active') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                        <label><span>Headline</span><input type="text" name="headline" value="<?= e($marketingEdit['headline'] ?? '') ?>"></label>
                        <label><span>Subheadline</span><input type="text" name="subheadline" value="<?= e($marketingEdit['subheadline'] ?? '') ?>"></label>
                        <label><span>Web Slider Image</span><input type="file" name="slider_image" accept="image/png,image/jpeg,image/webp"></label>
                        <label><span>Mobile App Banner Image</span><input type="file" name="slider_mobile_image" accept="image/png,image/jpeg,image/webp"></label>
                        <label><span>CTA Label</span><input type="text" name="cta_label" value="<?= e($marketingEdit['cta_label'] ?? 'Shop Now') ?>"></label>
                        <label><span>CTA URL</span><input type="url" name="cta_url" value="<?= e($marketingEdit['cta_url'] ?? '') ?>"></label>
                        <label><span>Sort Order</span><input type="number" name="sort_order" value="<?= e($marketingEdit['sort_order'] ?? '0') ?>"></label>
                        <label><span>Starts At</span><input type="datetime-local" name="starts_at" value="<?= e($dateInput($marketingEdit['starts_at'] ?? null)) ?>"></label>
                        <label><span>Ends At</span><input type="datetime-local" name="ends_at" value="<?= e($dateInput($marketingEdit['ends_at'] ?? null)) ?>"></label>
                        <div class="settings-note full"><strong>App banners</strong><span>The mobile app uses the Mobile App Banner Image first, then the web slider image. If no active sliders exist, the app uses Seller Africa fallback banners.</span></div>
                        <div class="form-actions full"><button class="btn primary" type="submit">Save Slider</button></div>
                    </form>
                <?php elseif ($showMarketingForm && $view === 'ads-banners'): ?>
                    <form class="settings-form" method="post" action="<?= e(app_url('dashboard/ads-banners')) ?>" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                        <input type="hidden" name="action" value="save_marketing_banner">
                        <input type="hidden" name="banner_id" value="<?= e((int)($marketingEdit['id'] ?? 0)) ?>">
                        <label><span>Title</span><input type="text" name="title" value="<?= e($marketingEdit['title'] ?? '') ?>" required></label>
                        <label><span>Status</span><select name="status"><?php foreach (['active' => 'Active', 'draft' => 'Draft', 'paused' => 'Paused', 'expired' => 'Expired'] as $key => $label): ?><option value="<?= e($key) ?>" <?= ($marketingEdit['status'] ?? 'active') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                        <label><span>Placement</span><select name="placement"><?php foreach (['homepage_section_1' => 'Homepage Banner 1', 'homepage_section_2' => 'Homepage Banner 2', 'homepage' => 'Homepage General'] as $key => $label): ?><option value="<?= e($key) ?>" <?= ($marketingEdit['placement'] ?? 'homepage_section_1') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                        <label><span>Banner Image</span><input type="file" name="banner_image" accept="image/png,image/jpeg,image/webp"></label>
                        <label><span>Target URL</span><input type="url" name="target_url" value="<?= e($marketingEdit['target_url'] ?? '') ?>"></label>
                        <label><span>Starts At</span><input type="datetime-local" name="starts_at" value="<?= e($dateInput($marketingEdit['starts_at'] ?? null)) ?>"></label>
                        <label><span>Ends At</span><input type="datetime-local" name="ends_at" value="<?= e($dateInput($marketingEdit['ends_at'] ?? null)) ?>"></label>
                        <div class="settings-note full"><strong>Fallback</strong><span>If no active banners are uploaded, Home uses public/assets/images/bs1 and bs2.</span></div>
                        <div class="form-actions full"><button class="btn primary" type="submit">Save Banner</button></div>
                    </form>
                <?php else: ?>
                    <form class="admin-searchbar" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                        <input type="search" name="q" value="<?= e($pagination['search'] ?? '') ?>" placeholder="Search <?= e(strtolower($copy['title'])) ?>">
                        <input type="hidden" name="per_page" value="<?= e($perPage) ?>">
                        <button class="btn secondary" type="submit">Search</button>
                    </form>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Name</th><th>Status</th><th>Schedule</th><th>Image</th><th>Updated</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><strong><?= e($row['title'] ?? 'Marketing record') ?></strong><small><?= e($row['headline'] ?? $row['placement'] ?? '') ?></small></td>
                                    <td><span class="settings-badge <?= e($statusClass((string)($row['status'] ?? 'draft'))) ?>"><?= e($row['status'] ?? 'draft') ?></span></td>
                                    <td><?= e($row['starts_at'] ?? '-') ?><small>Ends: <?= e($row['ends_at'] ?? '-') ?></small></td>
                                    <td><?= !empty($row['image_url']) ? 'Uploaded' : 'Fallback' ?></td>
                                    <td><?= e($row['updated_at'] ?? $row['created_at'] ?? '-') ?></td>
                                    <td><a class="btn secondary" href="<?= e(app_url('dashboard/' . $view . '?edit=' . (int)$row['id'])) ?>">Edit</a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($rows === []): ?><tr><td colspan="6">No <?= e(strtolower($copy['title'])) ?> records yet.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <?php $copy = $moduleCopy[$view] ?? ['title' => $links[$view] ?? 'Marketing', 'summary' => 'Marketing module records.']; ?>
            <section class="panel">
                <div class="panel-header">
                    <div><h2><?= e($copy['title']) ?></h2><p><?= e($copy['summary']) ?></p></div>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/coupons?new=1')) ?>">Create Coupon</a>
                </div>
                <form class="admin-searchbar" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                    <input type="search" name="q" value="<?= e($pagination['search'] ?? '') ?>" placeholder="Search <?= e(strtolower($copy['title'])) ?>">
                    <input type="hidden" name="per_page" value="<?= e($perPage) ?>">
                    <button class="btn secondary" type="submit">Search</button>
                </form>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Name</th><th>Status</th><th>Schedule</th><th>Performance</th><th>Updated</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><strong><?= e($row['title'] ?? $row['name'] ?? 'Marketing record') ?></strong><small><?= e($row['subject'] ?? $row['headline'] ?? $row['placement'] ?? $row['audience'] ?? '') ?></small></td>
                                <td><span class="settings-badge <?= e($statusClass((string)($row['status'] ?? 'draft'))) ?>"><?= e($row['status'] ?? 'draft') ?></span></td>
                                <td><?= e($row['starts_at'] ?? $row['scheduled_at'] ?? '-') ?><small>Ends: <?= e($row['ends_at'] ?? $row['sent_at'] ?? '-') ?></small></td>
                                <td><?= number_format((int)($row['impressions'] ?? $row['total_recipients'] ?? $row['sent_count'] ?? 0)) ?><small>Clicks/recovered: <?= number_format((int)($row['clicks'] ?? $row['clicked_count'] ?? $row['recovered_count'] ?? 0)) ?></small></td>
                                <td><?= e($row['updated_at'] ?? $row['created_at'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows === []): ?><tr><td colspan="5">No <?= e(strtolower($copy['title'])) ?> records yet. The database table is ready for this module.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <?php if ((int)($pagination['total'] ?? 0) > 0 && !$showCouponForm && !$showCampaignForm && !$showMarketingForm): ?>
            <div class="admin-pagination">
                <div class="pagination-summary">Page <?= number_format($page) ?> of <?= number_format($totalPages) ?> | Showing <?= number_format((int)($pagination['from'] ?? 0)) ?>-<?= number_format((int)($pagination['to'] ?? 0)) ?> of <?= number_format((int)($pagination['total'] ?? 0)) ?></div>
                <div class="pagination-actions">
                    <a class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= e($listUrl(max(1, $page - 1))) ?>">Previous</a>
                    <?php for ($n = max(1, $page - 2); $n <= min($totalPages, $page + 2); $n++): ?><a class="pagination-btn <?= $n === $page ? 'active' : '' ?>" href="<?= e($listUrl($n)) ?>"><?= number_format($n) ?></a><?php endfor; ?>
                    <a class="pagination-btn <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= e($listUrl(min($totalPages, $page + 1))) ?>">Next</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
