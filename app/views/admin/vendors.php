<?php
$vendorsData = $vendorsData ?? [];
$vendorLinks = [
    'vendors' => 'All Vendors',
    'vendor-packages' => 'Vendor Packages',
    'vendor-subscriptions' => 'Vendor Subscriptions',
    'pending-vendors' => 'Pending Vendors',
    'verified-vendors' => 'Verified Vendors',
    'rejected-vendors' => 'Rejected Vendors',
    'vendor-kyc-documents' => 'Vendor KYC Documents',
    'vendor-stores' => 'Vendor Stores',
    'vendor-ratings' => 'Vendor Ratings',
    'vendor-payout-accounts' => 'Vendor Payout Accounts',
];
$stats = $vendorsData['stats'] ?? [];
$statusClass = static fn (string $status): string => 'status-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $status) ?? 'unknown');
$vendorReadiness = static function (array $vendor): array {
    $applicationData = json_decode((string)($vendor['latest_application_data'] ?? ''), true);
    $applicationData = is_array($applicationData) ? $applicationData : [];
    if (($applicationData['onboarding']['version'] ?? 0) === 2) {
        $checks = \App\VendorCompletionService::checks($vendor);
        foreach ($checks as &$check) {
            $fileId = (int)($applicationData['file_ids'][$check['key']] ?? 0);
            if ($fileId > 0 && $file = db()->fetch('SELECT path, mime_type FROM files WHERE id=?', [$fileId])) {
                $check['preview_url'] = app_brand_asset_url($file['path']);
                $check['preview_mime'] = $file['mime_type'];
            }
        }
        unset($check);
        $done = count(array_filter($checks, static fn ($check) => $check['done']));
        return ['checks' => $checks, 'complete' => $done === count($checks), 'percent' => (int)round($done / count($checks) * 100)];
    }
    $applicationFileIds = is_array($applicationData['file_ids'] ?? null) ? $applicationData['file_ids'] : [];
    $checks = \App\VendorCompletionService::checks($vendor);
    $done = count(array_filter($checks, static fn (array $check): bool => (bool)$check['done']));
    $productImageId = (int)($applicationFileIds['product_image'] ?? 0);
    if ($productImageId <= 0) {
        $productImage = db()->fetch("SELECT pm.file_id FROM product_media pm JOIN products p ON p.id = pm.product_id WHERE p.vendor_id = ? AND pm.role IN ('primary', 'gallery') ORDER BY pm.role = 'primary' DESC, pm.id DESC LIMIT 1", [(int)$vendor['id']]);
        $productImageId = (int)($productImage['file_id'] ?? 0);
    }
    $previewIds = [(int)($vendor['logo_file_id'] ?? 0), (int)($vendor['banner_file_id'] ?? 0), $productImageId];
    $previewFiles = db()->fetchAll('SELECT id, path, mime_type FROM files WHERE id IN (?, ?, ?)', $previewIds);
    foreach ($previewFiles as $file) {
        foreach ($previewIds as $index => $fileId) {
            if ($fileId === (int)$file['id']) {
                $checks[$index]['preview_url'] = app_brand_asset_url($file['path']);
                $checks[$index]['preview_mime'] = $file['mime_type'];
            }
        }
    }

    return [
        'checks' => $checks,
        'complete' => $done === count($checks),
        'percent' => (int)round(($done / max(1, count($checks))) * 100),
    ];
};
$vendorRows = match ($view) {
    'pending-vendors' => $vendorsData['pending'] ?? [],
    'verified-vendors' => $vendorsData['verified'] ?? [],
    'rejected-vendors' => $vendorsData['rejected'] ?? [],
    'vendor-stores' => $vendorsData['stores'] ?? [],
    default => $vendorsData['vendors'] ?? [],
};
$pagination = $vendorsData['pagination'] ?? [];
$page = max(1, (int)($pagination['page'] ?? 1));
$totalPages = max(1, (int)($pagination['totalPages'] ?? 1));
$perPage = (int)($pagination['perPage'] ?? 25);
$listUrl = static function (int $targetPage, ?int $targetPerPage = null) use ($view, $perPage): string {
    $query = ['page' => max(1, $targetPage), 'per_page' => $targetPerPage ?? $perPage];
    if (trim((string)($_GET['q'] ?? '')) !== '') {
        $query['q'] = trim((string)$_GET['q']);
    }
    if ($view === 'pending-vendors' && is_string($_GET['completion'] ?? null) && array_key_exists($_GET['completion'], \App\VendorCompletionService::RANGES)) {
        $query['completion'] = $_GET['completion'];
    }
    return app_url('dashboard/' . $view . '?' . http_build_query($query));
};
$payoutSummary = static function (?string $json): string {
    $decoded = json_decode((string)$json, true);
    if (!is_array($decoded) || $decoded === []) {
        return 'Not provided';
    }

    $keys = array_slice(array_keys($decoded), 0, 3);
    return implode(', ', array_map(static fn (string $key): string => ucwords(str_replace('_', ' ', $key)), $keys));
};
$kycDocumentsByVendor = [];
foreach (($vendorsData['kycDocuments'] ?? []) as $document) {
    $kycDocumentsByVendor[(int)($document['vendor_id'] ?? 0)][] = $document;
}
$editVendor = $vendorsData['editVendor'] ?? null;
$editProducts = $vendorsData['editProducts'] ?? [];
$editDocuments = $vendorsData['editDocuments'] ?? [];
$editSubscription = $vendorsData['editSubscription'] ?? null;
$editProduct = $vendorsData['editProduct'] ?? null;
$editApplication = $vendorsData['editApplication'] ?? null;
$editMedia = $vendorsData['editMedia'] ?? [];
$editProductMedia = $vendorsData['editProductMedia'] ?? [];
$editProductStatus = (string)($vendorsData['editProductStatus'] ?? 'all');
$vendorAsset = static function (?string $path): string {
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    return preg_match('/^https?:\/\//i', $path) ? $path : app_brand_asset_url($path);
};
$selectOptions = static function (array $options, string $current): string {
    $html = '';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . e((string)$value) . '"' . ((string)$value === $current ? ' selected' : '') . '>' . e((string)$label) . '</option>';
    }
    return $html;
};
$applicationData = [];
if (is_array($editApplication)) {
    $decodedApplication = json_decode((string)($editApplication['application_data'] ?? ''), true);
    $applicationData = is_array($decodedApplication) ? $decodedApplication : [];
}
$applicationLabel = static fn (string $key): string => ucwords(str_replace('_', ' ', $key));
$vendorApplicationData = static function (array $vendor): array {
    $decoded = json_decode((string)($vendor['latest_application_data'] ?? ''), true);
    return is_array($decoded) ? $decoded : [];
};
$applicationValue = static function (array $data, string $path) {
    $value = $data;
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};
$vendorMissingApplicationFields = static function (array $vendor) use ($vendorApplicationData, $applicationValue): array {
    $data = $vendorApplicationData($vendor);
    if (($data['onboarding']['version'] ?? 0) === 2) {
        return array_column(array_filter(\App\VendorOnboardingService::checks($data), static fn ($check) => !$check['done']), 'label');
    }
    if ($data === []) {
        return ['Application packet'];
    }

    $required = [
        'business.business_name' => 'Business name',
        'business.country_of_operation' => 'Country of operation',
        'business.business_category' => 'Business category',
        'business.what_do_you_sell' => 'What the vendor sells',
        'business.is_business_registered' => 'Business registration answer',
        'owner.full_name' => 'Owner full name',
        'owner.email' => 'Owner email',
        'owner.whatsapp_number' => 'WhatsApp number',
        'owner.country_of_residence' => 'Owner country',
        'readiness.products_currently_available' => 'Products currently available answer',
        'readiness.currently_sells_outside_country' => 'Export answer',
        'readiness.how_to_sell_through_sac' => 'Selling/fulfillment method',
        'readiness.product_description' => 'Product description',
        'readiness.product_price' => 'Product price',
        'readiness.product_weight' => 'Product weight',
        'readiness.package_length' => 'Package length',
        'readiness.package_width' => 'Package width',
        'readiness.package_height' => 'Package height',
        'declaration.authentic_legal_source' => 'Authentic/legal source confirmation',
        'declaration.vendor_terms_and_privacy' => 'Terms and privacy confirmation',
    ];

    $missing = [];
    foreach ($required as $path => $label) {
        $value = $applicationValue($data, $path);
        if (is_array($value)) {
            $value = implode('', $value);
        }
        if (trim((string)$value) === '') {
            $missing[] = $label;
        }
    }

    return $missing;
};
$summaryItems = static function (?string $summary, int $limit = 3): array {
    $items = array_values(array_filter(array_map('trim', explode('||', (string)$summary)), static fn (string $item): bool => $item !== ''));
    return array_slice($items, 0, $limit);
};
$vendorApplicationProductSummary = static function (array $vendor) use ($vendorApplicationData): array {
    $data = $vendorApplicationData($vendor);
    if ($data === []) {
        return [];
    }

    $business = is_array($data['business'] ?? null) ? $data['business'] : [];
    $readiness = is_array($data['readiness'] ?? null) ? $data['readiness'] : [];
    $fileIds = is_array($data['file_ids'] ?? null) ? $data['file_ids'] : [];
    $description = trim((string)($readiness['product_description'] ?? $business['what_do_you_sell'] ?? ''));
    if ($description === '' && (int)($fileIds['product_image'] ?? 0) <= 0) {
        return [];
    }

    $meta = [];
    if (trim((string)($business['business_category'] ?? '')) !== '') {
        $meta[] = (string)$business['business_category'];
    }
    if ((float)($readiness['product_price'] ?? 0) > 0) {
        $meta[] = 'Price ' . number_format((float)$readiness['product_price'], 2);
    }
    if ((float)($readiness['product_weight'] ?? 0) > 0) {
        $meta[] = 'Weight ' . (string)$readiness['product_weight'];
    }
    if ((float)($readiness['package_length'] ?? 0) > 0 && (float)($readiness['package_width'] ?? 0) > 0 && (float)($readiness['package_height'] ?? 0) > 0) {
        $meta[] = 'Package ' . (string)$readiness['package_length'] . ' x ' . (string)$readiness['package_width'] . ' x ' . (string)$readiness['package_height'];
    }
    if ((int)($fileIds['product_image'] ?? 0) > 0) {
        $meta[] = 'Image uploaded';
    }

    return [[
        'name' => $description !== '' ? mb_strimwidth($description, 0, 80, '...') : 'Registration product image',
        'meta' => implode(' · ', $meta),
    ]];
};
?>

<section class="settings-shell vendors-shell vendors-shell--wide">
    <?php if (false): ?>
        <aside class="settings-index panel">
            <div class="panel-header">
                <div>
                    <h2>Vendors</h2>
                    <p>Stores, KYC, ratings and payout readiness</p>
                </div>
            </div>
            <nav class="settings-nav" aria-label="Vendor pages">
                <?php foreach ($vendorLinks as $key => $label): ?>
                    <a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(app_url('dashboard/' . $key)) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
        </aside>
    <?php endif; ?>

    <div class="settings-content">
        <section class="metric-grid settings-metrics" aria-label="Vendor metrics">
            <div class="metric"><span>Total Vendors</span><strong><?= number_format((int)($stats['total_vendors'] ?? 0)) ?></strong><small>Store accounts</small></div>
            <div class="metric"><span>Pending Review</span><strong><?= number_format((int)($stats['pending_vendors'] ?? 0)) ?></strong><small>Status or KYC pending</small></div>
            <div class="metric"><span>Verified</span><strong><?= number_format((int)($stats['verified_vendors'] ?? 0)) ?></strong><small>Active stores</small></div>
            <div class="metric"><span>Payout Accounts</span><strong><?= number_format((int)($stats['payout_accounts'] ?? 0)) ?></strong><small>Configured methods</small></div>
        </section>

        <?php if ($view === 'vendor-edit'): ?>
            <?php if (!$editVendor): ?>
                <section class="panel">
                    <div class="panel-header"><div><h2>Edit Vendor</h2><p>Select a vendor from All Vendors to edit their full store profile.</p></div></div>
                    <a class="btn primary" href="<?= e(app_url('dashboard/vendors')) ?>">Back to Vendors</a>
                </section>
            <?php else: ?>
                <?php
                $vendorId = (int)$editVendor['id'];
                $logoUrl = $vendorAsset($editVendor['logo_path'] ?? '');
                $bannerUrl = $vendorAsset($editVendor['banner_path'] ?? '');
                $payoutDetails = json_decode((string)($editVendor['payout_details'] ?? ''), true);
                $payoutText = is_array($payoutDetails) ? implode("\n", array_map(static fn ($key, $value): string => ucwords(str_replace('_', ' ', (string)$key)) . ': ' . (is_scalar($value) ? (string)$value : json_encode($value)), array_keys($payoutDetails), $payoutDetails)) : (string)($editVendor['payout_details'] ?? '');
                $productForForm = $editProduct ?? [];
                ?>
                <section class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Set Up a Store: <?= e($editVendor['store_name']) ?></h2>
                            <p>Admin control for store profile, documents, plan, products, payout and approval state.</p>
                        </div>
                        <div class="vendor-review-actions">
                            <a class="btn secondary" href="<?= e(app_url('dashboard/set-up-store')) ?>">Choose Vendor</a>
                            <a class="btn primary" href="#store-products">Add Product</a>
                            <a class="btn secondary" href="<?= e(app_url('dashboard/vendors')) ?>">All Vendors</a>
                            <a class="btn secondary" href="<?= e(app_url('vendors/' . rawurlencode((string)$editVendor['store_slug']))) ?>" target="_blank" rel="noopener">Public Store</a>
                        </div>
                    </div>
                    <?php require __DIR__.'/vendor-payment-links.php'; ?>
                    <form class="settings-form" method="post" enctype="multipart/form-data" action="<?= e(app_url('dashboard/vendor-edit?vendor=' . $vendorId)) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                        <input type="hidden" name="action" value="save_vendor_admin">
                        <input type="hidden" name="vendor_id" value="<?= e($vendorId) ?>">
                        <div class="settings-form-grid">
                            <label class="settings-field"><span>Store Name</span><input name="store_name" required value="<?= e($editVendor['store_name']) ?>"></label>
                            <label class="settings-field"><span>Store Slug</span><input name="store_slug" value="<?= e($editVendor['store_slug']) ?>"></label>
                            <label class="settings-field"><span>Store Email</span><input type="email" name="store_email" value="<?= e($editVendor['store_email'] ?? '') ?>"></label>
                            <label class="settings-field"><span>Store Phone</span><input name="store_phone" value="<?= e($editVendor['store_phone'] ?? '') ?>"></label>
                            <label class="settings-field"><span>Status</span><select name="status"><?= $selectOptions(['pending' => 'Pending', 'active' => 'Active', 'suspended' => 'Suspended', 'rejected' => 'Rejected', 'closed' => 'Closed'], (string)$editVendor['status']) ?></select></label>
                            <label class="settings-field"><span>KYC Status</span><select name="kyc_status"><?= $selectOptions(['not_started' => 'Not Started', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'expired' => 'Expired'], (string)$editVendor['kyc_status']) ?></select></label>
                            <label class="settings-field"><span>Commission Type</span><select name="commission_type"><?= $selectOptions(['percentage' => 'Percentage', 'flat' => 'Flat', 'hybrid' => 'Hybrid'], (string)$editVendor['commission_type']) ?></select></label>
                            <label class="settings-field"><span>Commission Rate</span><input type="number" min="0" step="0.0001" name="commission_rate" value="<?= e($editVendor['commission_rate']) ?>"></label>
                            <label class="settings-field"><span>Origin Region</span><select name="origin_region"><?= $selectOptions(['' => 'Choose region', 'West Africa' => 'West Africa', 'East Africa' => 'East Africa', 'Caribbean' => 'Caribbean', 'Other' => 'Other'], (string)($editVendor['origin_region'] ?? '')) ?></select></label>
                            <label class="settings-field"><span>Country of Origin</span><input name="country_of_origin" value="<?= e($editVendor['country_of_origin'] ?? '') ?>"></label>
                            <label class="settings-field span-2"><span>Description</span><textarea name="description" rows="5"><?= e($editVendor['description'] ?? '') ?></textarea></label>
                            <label class="settings-field"><span>Logo / Profile Photo</span><input type="file" name="logo" accept="image/jpeg,image/png,image/webp"><?php if ($logoUrl !== ''): ?><small><a href="<?= e($logoUrl) ?>" target="_blank" rel="noopener">Current logo</a></small><?php endif; ?></label>
                            <label class="settings-field"><span>Store Banner</span><input type="file" name="banner" accept="image/jpeg,image/png,image/webp"><?php if ($bannerUrl !== ''): ?><small><a href="<?= e($bannerUrl) ?>" target="_blank" rel="noopener">Current banner</a></small><?php endif; ?></label>
                            <label class="settings-field"><span>Address</span><input name="address_line1" value="<?= e($editVendor['address_line1'] ?? '') ?>"></label>
                            <label class="settings-field"><span>City</span><input name="city" value="<?= e($editVendor['city'] ?? '') ?>"></label>
                            <label class="settings-field"><span>State</span><input name="state" value="<?= e($editVendor['state'] ?? '') ?>"></label>
                            <label class="settings-field"><span>Postcode</span><input name="postcode" value="<?= e($editVendor['postcode'] ?? '') ?>"></label>
                            <label class="settings-field"><span>Country Code</span><input maxlength="2" name="country_code" value="<?= e($editVendor['country_code'] ?? '') ?>"></label>
                            <label class="settings-field"><span>Payout Method</span><input name="payout_method" value="<?= e($editVendor['payout_method'] ?? '') ?>"></label>
                            <label class="settings-field span-2"><span>Payout Details</span><textarea name="payout_details" rows="4"><?= e($payoutText) ?></textarea></label>
                        </div>
                        <button class="btn primary" type="submit">Save Vendor Details</button>
                    </form>
                </section>

                <section class="dashboard-grid">
                    <div class="panel">
                        <div class="panel-header"><div><h2>Plan</h2><p>Assign or change the vendor package used for product limits.</p></div></div>
                        <form class="settings-form" method="post" action="<?= e(app_url('dashboard/vendor-edit?vendor=' . $vendorId)) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                            <input type="hidden" name="action" value="save_vendor_subscription_admin">
                            <input type="hidden" name="vendor_id" value="<?= e($vendorId) ?>">
                            <div class="settings-form-grid">
                                <label class="settings-field"><span>Package</span><select name="package_id">
                                    <?php foreach (($vendorsData['packages'] ?? []) as $package): ?><option value="<?= e($package['id']) ?>" <?= (int)($editSubscription['package_id'] ?? 0) === (int)$package['id'] ? 'selected' : '' ?>><?= e($package['name']) ?><?= $package['product_limit'] === null ? ' - Unlimited' : ' - ' . number_format((int)$package['product_limit']) . ' products' ?></option><?php endforeach; ?>
                                </select></label>
                                <label class="settings-field"><span>Status</span><select name="subscription_status"><?= $selectOptions(['active' => 'Active', 'trialing' => 'Trialing', 'past_due' => 'Past Due', 'unpaid' => 'Unpaid', 'cancelled' => 'Cancelled', 'incomplete' => 'Incomplete', 'expired' => 'Expired'], (string)($editSubscription['status'] ?? 'active')) ?></select></label>
                                <label class="settings-field"><span>Period Start</span><input type="datetime-local" name="current_period_start" value="<?= e(str_replace(' ', 'T', substr((string)($editSubscription['current_period_start'] ?? ''), 0, 16))) ?>"></label>
                                <label class="settings-field"><span>Period End</span><input type="datetime-local" name="current_period_end" value="<?= e(str_replace(' ', 'T', substr((string)($editSubscription['current_period_end'] ?? ''), 0, 16))) ?>"></label>
                                <label class="settings-field"><span>Grace Ends</span><input type="datetime-local" name="grace_ends_at" value="<?= e(str_replace(' ', 'T', substr((string)($editSubscription['grace_ends_at'] ?? ''), 0, 16))) ?>"></label>
                            </div>
                            <button class="btn primary" type="submit">Update Plan</button>
                        </form>
                    </div>
                    <div class="panel">
                        <div class="panel-header"><div><h2>Owner</h2><p>Linked vendor account details.</p></div></div>
                        <div class="status-list">
                            <div class="status-item"><strong><?= e($editVendor['display_name'] ?: $editVendor['username'] ?: $editVendor['owner_email']) ?></strong><span><?= e($editVendor['owner_email']) ?></span></div>
                            <div class="status-item"><strong><?= e($editVendor['owner_status']) ?></strong><span><?= e($editVendor['owner_phone'] ?: 'No owner phone') ?></span></div>
                            <div class="status-item"><strong><?= number_format(count($editProducts)) ?> products</strong><span><?= e($editSubscription['package_name'] ?? 'No plan') ?></span></div>
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <div><h2><?= e((string)($editApplication['application_type'] ?? 'vendor') === 'farmer' ? 'FreshRoots Farmer Application' : 'SAC Vendor Application') ?></h2><p>Original application answers submitted by this vendor.</p></div>
                        <?php if (is_array($editApplication)): ?>
                            <div class="approval-actions">
                                <?php if ((string)($editApplication['application_type'] ?? '') === 'farmer'): ?><a class="btn secondary compact-btn" href="<?= e(app_url('farmer?vendor_id=' . (int)$editVendor['id'])) ?>" target="_blank" rel="noopener">Open Farmer Dashboard</a><?php endif; ?>
                                <span class="settings-badge">Submitted <?= e((string)($editApplication['submitted_at'] ?? '')) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($applicationData !== []): ?>
                        <div class="dashboard-grid">
                            <?php foreach ($applicationData as $sectionKey => $section): ?>
                                <?php $section = $applicationData[$sectionKey] ?? []; ?>
                                <?php if (!is_array($section) || $section === []): ?><?php continue; ?><?php endif; ?>
                                <div class="status-list">
                                    <div class="status-item"><strong><?= e($applicationLabel($sectionKey)) ?></strong><span>Application section</span></div>
                                    <?php foreach ($section as $fieldKey => $fieldValue): ?>
                                        <?php
                                        if (is_bool($fieldValue)) {
                                            $fieldValue = $fieldValue ? 'Yes' : 'No';
                                        } elseif (is_array($fieldValue)) {
                                            $fieldValue = json_encode($fieldValue, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                                        }
                                        $fieldValue = trim((string)$fieldValue);
                                        ?>
                                        <?php if ($fieldValue === ''): ?><?php continue; ?><?php endif; ?>
                                        <div class="status-item"><strong><?= e($applicationLabel((string)$fieldKey)) ?></strong><span><?= e($fieldValue) ?></span></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="vendor-review-empty">No SAC application packet has been saved for this vendor yet.</div>
                    <?php endif; ?>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <div><h2>Uploaded Pictures</h2><p>All images connected to this vendor: store branding, product media, KYC images and vendor uploads.</p></div>
                        <span class="settings-badge"><?= number_format(count($editMedia)) ?> images</span>
                    </div>
                    <div class="vendor-media-admin-grid">
                        <?php foreach ($editMedia as $media): ?>
                            <?php $mediaUrl = $vendorAsset($media['path'] ?? ''); ?>
                            <article class="vendor-media-admin-card" data-vendor-delete-item data-file-id="<?= e($media['id']) ?>">
                                <a href="<?= e($mediaUrl) ?>" target="_blank" rel="noopener">
                                    <img src="<?= e($mediaUrl) ?>" alt="<?= e($media['original_name'] ?: 'Vendor image') ?>">
                                </a>
                                <div>
                                    <strong><?= e($media['original_name'] ?: basename((string)$media['path'])) ?></strong>
                                    <span><?= e(ucwords(str_replace('_', ' ', (string)$media['usage_type']))) ?><?= !empty($media['product_name']) ? ' | ' . e($media['product_name']) : '' ?></span>
                                    <small><?= e(trim((string)($media['width'] ?? '') . 'x' . (string)($media['height'] ?? ''), 'x')) ?> <?= e($media['mime_type'] ?? '') ?></small>
                                </div>
                                <div class="approval-actions">
                                    <?php if (!empty($media['product_id'])): ?><a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/vendor-edit?vendor=' . $vendorId . '&edit_product=' . (int)$media['product_id'])) ?>">Edit Product</a><?php endif; ?>
                                    <form method="post" data-ajax-delete data-confirm="Delete this image? This removes it from the vendor files and any product/gallery references.">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="action" value="delete_vendor_file_admin">
                                        <input type="hidden" name="vendor_id" value="<?= e($vendorId) ?>">
                                        <input type="hidden" name="file_id" value="<?= e($media['id']) ?>">
                                        <button class="btn secondary compact-btn danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if ($editMedia === []): ?><div class="vendor-review-empty">No uploaded pictures found for this vendor.</div><?php endif; ?>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header"><div><h2>Documents</h2><p>Edit document names, numbers, status and uploaded files.</p></div></div>
                    <?php foreach ($editDocuments as $doc): ?>
                        <form id="vendor-doc-form-<?= e($doc['id']) ?>" method="post" enctype="multipart/form-data" action="<?= e(app_url('dashboard/vendor-edit?vendor=' . $vendorId)) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                            <input type="hidden" name="action" value="save_vendor_kyc_document_admin">
                            <input type="hidden" name="vendor_id" value="<?= e($vendorId) ?>">
                            <input type="hidden" name="document_id" value="<?= e($doc['id']) ?>">
                        </form>
                    <?php endforeach; ?>
                    <form id="vendor-doc-form-new" method="post" enctype="multipart/form-data" action="<?= e(app_url('dashboard/vendor-edit?vendor=' . $vendorId)) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                        <input type="hidden" name="action" value="save_vendor_kyc_document_admin">
                        <input type="hidden" name="vendor_id" value="<?= e($vendorId) ?>">
                    </form>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Document</th><th>Number</th><th>File</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($editDocuments as $doc): ?>
                                <?php $docFormId = 'vendor-doc-form-' . (int)$doc['id']; ?>
                                <tr>
                                    <td><input form="<?= e($docFormId) ?>" type="text" name="document_type" value="<?= e($doc['document_type']) ?>"></td>
                                    <td><input form="<?= e($docFormId) ?>" type="text" name="document_number" value="<?= e($doc['document_number'] ?? '') ?>"></td>
                                    <td><?php if (($doc['file_path'] ?? '') !== ''): ?><a href="<?= e($vendorAsset($doc['file_path'])) ?>" target="_blank" rel="noopener"><?= e($doc['original_name'] ?: 'View file') ?></a><?php endif; ?><input form="<?= e($docFormId) ?>" type="file" name="document_file" accept="image/jpeg,image/png,image/webp,application/pdf"></td>
                                    <td><select form="<?= e($docFormId) ?>" name="status"><?= $selectOptions(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'], (string)$doc['status']) ?></select><input form="<?= e($docFormId) ?>" type="text" name="rejection_reason" value="<?= e($doc['rejection_reason'] ?? '') ?>" placeholder="Rejection reason"></td>
                                    <td><button form="<?= e($docFormId) ?>" class="btn secondary" type="submit">Save</button></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td><input form="vendor-doc-form-new" type="text" name="document_type" placeholder="business_registration"></td>
                                <td><input form="vendor-doc-form-new" type="text" name="document_number" placeholder="Document number"></td>
                                <td><input form="vendor-doc-form-new" type="file" name="document_file" accept="image/jpeg,image/png,image/webp,application/pdf"></td>
                                <td><select form="vendor-doc-form-new" name="status"><?= $selectOptions(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'], 'pending') ?></select><input form="vendor-doc-form-new" type="text" name="rejection_reason" placeholder="Rejection reason"></td>
                                <td><button form="vendor-doc-form-new" class="btn primary" type="submit">Add Document</button></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <div><h2 id="store-products"><?= $editProduct ? 'Edit Product' : 'Add Product' ?></h2><p>Admin can update this vendor catalogue without leaving the vendor profile.</p></div>
                        <?php if ($editProduct): ?><a class="btn secondary" href="<?= e(app_url('dashboard/vendor-edit?vendor=' . $vendorId)) ?>">New Product</a><?php endif; ?>
                    </div>
                    <form class="settings-form" method="post" enctype="multipart/form-data" action="<?= e(app_url('dashboard/vendor-edit?vendor=' . $vendorId)) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                        <input type="hidden" name="action" value="save_product">
                        <input type="hidden" name="vendor_id" value="<?= e($vendorId) ?>">
                        <input type="hidden" name="return_vendor_id" value="<?= e($vendorId) ?>">
                        <input type="hidden" name="product_id" value="<?= e($productForForm['id'] ?? 0) ?>">
                        <div class="settings-form-grid">
                            <label class="settings-field"><span>Name</span><input name="name" required value="<?= e($productForForm['name'] ?? '') ?>"></label>
                            <label class="settings-field"><span>Slug</span><input name="slug" value="<?= e($productForForm['slug'] ?? '') ?>"></label>
                            <label class="settings-field"><span>SKU</span><input name="sku" required value="<?= e($productForForm['sku'] ?? '') ?>"></label>
                            <label class="settings-field"><span>Status</span><select name="status"><?= $selectOptions(['draft' => 'Draft', 'pending' => 'Pending', 'active' => 'Active', 'private' => 'Private', 'archived' => 'Archived', 'rejected' => 'Rejected'], (string)($productForForm['status'] ?? 'draft')) ?></select></label>
                            <label class="settings-field"><span>Type</span><select name="type"><?= $selectOptions(['simple' => 'Simple', 'variable' => 'Variable', 'variation' => 'Variation', 'grouped' => 'Grouped', 'external' => 'External', 'digital' => 'Digital'], (string)($productForForm['type'] ?? 'simple')) ?></select></label>
                            <label class="settings-field"><span>Regular Price</span><input type="number" min="0" step="0.01" name="regular_price" value="<?= e($productForForm['regular_price'] ?? '0.00') ?>"></label>
                            <label class="settings-field"><span>Sale Price</span><input type="number" min="0" step="0.01" name="sale_price" value="<?= e($productForForm['sale_price'] ?? '') ?>"></label>
                            <label class="settings-field"><span>Currency</span><input maxlength="3" name="currency" value="<?= e($productForForm['currency'] ?? 'USD') ?>"></label>
                            <label class="settings-field"><span>Stock Status</span><select name="stock_status"><?= $selectOptions(['in_stock' => 'In Stock', 'out_of_stock' => 'Out of Stock', 'on_backorder' => 'On Backorder'], (string)($productForForm['stock_status'] ?? 'in_stock')) ?></select></label>
                            <label class="settings-field"><span>Stock Quantity</span><input type="number" name="stock_quantity" value="<?= e($productForForm['stock_quantity'] ?? '0') ?>"></label>
                            <label class="settings-field"><span>Low Stock Threshold</span><input type="number" name="low_stock_threshold" value="<?= e($productForForm['low_stock_threshold'] ?? '') ?>"></label>
                            <label class="settings-field"><span>Tax Status</span><select name="tax_status"><?= $selectOptions(['taxable' => 'Taxable', 'shipping' => 'Shipping', 'none' => 'None'], (string)($productForForm['tax_status'] ?? 'taxable')) ?></select></label>
                            <label class="settings-field"><span>Weight</span><input type="number" min="0" step="0.01" name="weight" value="<?= e($productForForm['weight'] ?? '') ?>"></label>
                            <label class="settings-field"><span>Length</span><input type="number" min="0" step="0.01" name="length" value="<?= e($productForForm['length'] ?? '') ?>"></label>
                            <label class="settings-field"><span>Width</span><input type="number" min="0" step="0.01" name="width" value="<?= e($productForForm['width'] ?? '') ?>"></label>
                            <label class="settings-field"><span>Height</span><input type="number" min="0" step="0.01" name="height" value="<?= e($productForForm['height'] ?? '') ?>"></label>
                            <label class="settings-field"><span>Shipping Class</span><input name="shipping_class" value="<?= e($productForForm['shipping_class'] ?? '') ?>"></label>
                            <label class="settings-field"><span>Primary Product Image</span><input type="file" name="product_image" accept="image/jpeg,image/png,image/webp"></label>
                            <label class="settings-field"><span>Gallery Images</span><input type="file" name="product_gallery[]" accept="image/jpeg,image/png,image/webp" multiple></label>
                            <label class="settings-field span-2"><span>Short Description</span><textarea name="short_description" rows="3"><?= e($productForForm['short_description'] ?? '') ?></textarea></label>
                            <label class="settings-field span-2"><span>Description</span><textarea name="description" rows="6"><?= e($productForForm['description'] ?? '') ?></textarea></label>
                            <label class="settings-toggle"><input type="checkbox" name="manage_stock" value="1" <?= !empty($productForForm['manage_stock']) ? 'checked' : '' ?>><span>Manage stock</span></label>
                            <label class="settings-toggle"><input type="checkbox" name="featured" value="1" <?= !empty($productForForm['featured']) ? 'checked' : '' ?>><span>Featured product</span></label>
                        </div>
                        <button class="btn primary" type="submit"><?= $editProduct ? 'Update Product' : 'Create Product' ?></button>
                    </form>
                    <?php if ($editProduct): ?>
                        <div class="vendor-product-media-manager">
                            <div class="panel-header"><div><h2>Product Pictures</h2><p>Set primary image, reorder gallery images, or remove product media.</p></div></div>
                            <div class="vendor-media-admin-grid">
                                <?php foreach ($editProductMedia as $media): ?>
                                    <?php $mediaUrl = $vendorAsset($media['path'] ?? ''); ?>
                                    <article class="vendor-media-admin-card" data-vendor-delete-item data-file-id="<?= e($media['file_id']) ?>" data-media-id="<?= e($media['id']) ?>">
                                        <a href="<?= e($mediaUrl) ?>" target="_blank" rel="noopener"><img src="<?= e($mediaUrl) ?>" alt="<?= e($media['original_name'] ?: 'Product image') ?>"></a>
                                        <form method="post" class="settings-form" data-ajax-media-form data-confirm="Delete this product image?">
                                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                            <input type="hidden" name="action" value="update_product_media_admin">
                                            <input type="hidden" name="vendor_id" value="<?= e($vendorId) ?>">
                                            <input type="hidden" name="product_id" value="<?= e($editProduct['id']) ?>">
                                            <input type="hidden" name="media_id" value="<?= e($media['id']) ?>">
                                            <label class="settings-field"><span>Role</span><select name="role"><?= $selectOptions(['primary' => 'Primary', 'gallery' => 'Gallery', 'download' => 'Download', 'document' => 'Document'], (string)$media['role']) ?></select></label>
                                            <label class="settings-field"><span>Sort</span><input type="number" name="sort_order" value="<?= e($media['sort_order']) ?>"></label>
                                            <div class="approval-actions">
                                                <button class="btn secondary compact-btn" name="media_action" value="save" type="submit">Save</button>
                                                <button class="btn primary compact-btn" name="media_action" value="primary" type="submit">Set Primary</button>
                                                <button class="btn secondary compact-btn danger" name="media_action" value="delete" type="submit">Delete</button>
                                            </div>
                                        </form>
                                    </article>
                                <?php endforeach; ?>
                                <?php if ($editProductMedia === []): ?><div class="vendor-review-empty">No pictures attached to this product yet.</div><?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="vendor-product-filter">
                        <?php foreach (['all' => 'All', 'pending' => 'Pending', 'active' => 'Approved', 'rejected' => 'Rejected', 'draft' => 'Draft', 'archived' => 'Archived'] as $statusKey => $statusLabel): ?>
                            <a class="btn <?= $editProductStatus === $statusKey ? 'primary' : 'secondary' ?> compact-btn" href="<?= e(app_url('dashboard/vendor-edit?vendor=' . $vendorId . ($statusKey !== 'all' ? '&product_status=' . $statusKey : ''))) ?>"><?= e($statusLabel) ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Product</th><th>Price</th><th>Stock</th><th>Status</th><th>Pictures</th><th>Sales</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($editProducts as $product): ?>
                                <tr data-vendor-delete-item>
                                    <td><strong><?= e($product['name']) ?></strong><small><?= e($product['sku'] ?: $product['slug']) ?></small></td>
                                    <td><?= e($product['currency']) ?> <?= number_format((float)$product['regular_price'], 2) ?></td>
                                    <td><?= e($product['stock_quantity'] ?? '0') ?><small><?= e(str_replace('_', ' ', (string)$product['stock_status'])) ?></small></td>
                                    <td><span class="settings-badge <?= e($statusClass((string)$product['status'])) ?>"><?= e($product['status']) ?></span></td>
                                    <td><?= number_format((int)($product['media_count'] ?? 0)) ?> image(s)</td>
                                    <td><?= number_format((int)$product['total_sales']) ?></td>
                                    <td>
                                        <div class="approval-actions product-approval-actions">
                                            <a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/vendor-edit?vendor=' . $vendorId . '&edit_product=' . (int)$product['id'])) ?>">Edit</a>
                                            <?php foreach (['active' => 'Approve', 'pending' => 'Pending', 'rejected' => 'Reject', 'archived' => 'Archive'] as $nextStatus => $label): ?>
                                                <?php if ((string)$product['status'] !== $nextStatus): ?>
                                                    <form method="post">
                                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                                        <input type="hidden" name="action" value="update_vendor_product_status_admin">
                                                        <input type="hidden" name="vendor_id" value="<?= e($vendorId) ?>">
                                                        <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                                                        <input type="hidden" name="status" value="<?= e($nextStatus) ?>">
                                                        <button class="btn <?= $nextStatus === 'active' ? 'primary' : 'secondary' ?> compact-btn<?= $nextStatus === 'rejected' || $nextStatus === 'archived' ? ' danger' : '' ?>" type="submit"><?= e($label) ?></button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <form method="post" data-ajax-delete data-confirm="Delete this product? Products with order history will be archived instead.">
                                                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                                <input type="hidden" name="action" value="delete_vendor_product_admin">
                                                <input type="hidden" name="vendor_id" value="<?= e($vendorId) ?>">
                                                <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                                                <button class="btn secondary compact-btn danger" type="submit">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($editProducts === []): ?><tr><td colspan="7">No products for this vendor/status yet.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <script>
                (() => {
                    const showToast = (type, message) => {
                        if (window.SellerAfricaToast) {
                            window.SellerAfricaToast({ type, message });
                            return;
                        }
                        window.alert(message);
                    };

                    const setBusy = (form, busy) => {
                        form.querySelectorAll('button, input, select, textarea').forEach((field) => {
                            if (field.type !== 'hidden') field.disabled = busy;
                        });
                    };

                    const postDelete = async (form, submitter = null) => {
                        const question = form.dataset.confirm || 'Delete this item?';
                        if (!window.confirm(question)) return;

                        const body = new FormData(form);
                        if (submitter?.name) {
                            body.set(submitter.name, submitter.value || '');
                        }

                        setBusy(form, true);
                        try {
                            const response = await fetch(form.action || window.location.href, {
                                method: 'POST',
                                body,
                                credentials: 'same-origin',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });
                            const payload = await response.json().catch(() => ({}));
                            if (!response.ok || payload.ok === false) {
                                throw new Error(payload.message || 'Delete failed.');
                            }

                            const item = form.closest('[data-vendor-delete-item]');
                            if (payload.mode === 'archived' && item) {
                                const badge = item.querySelector('.settings-badge');
                                if (badge) {
                                    badge.textContent = payload.status || 'archived';
                                    badge.className = 'settings-badge status-archived';
                                }
                                setBusy(form, false);
                            } else if (item) {
                                if (payload.file_id) {
                                    document.querySelectorAll(`[data-file-id="${String(payload.file_id)}"]`).forEach((node) => node.remove());
                                }
                                if (payload.media_id) {
                                    document.querySelectorAll(`[data-media-id="${String(payload.media_id)}"]`).forEach((node) => node.remove());
                                }
                                if (item.isConnected) item.remove();
                            }

                            showToast(payload.mode === 'archived' ? 'warning' : 'success', payload.message || 'Deleted.');
                        } catch (error) {
                            setBusy(form, false);
                            showToast('error', error.message || 'Delete failed.');
                        }
                    };

                    document.querySelectorAll('form[data-ajax-delete]').forEach((form) => {
                        form.addEventListener('submit', (event) => {
                            event.preventDefault();
                            postDelete(form, event.submitter || null);
                        });
                    });

                    document.querySelectorAll('form[data-ajax-media-form]').forEach((form) => {
                        form.addEventListener('submit', (event) => {
                            if ((event.submitter?.value || '') !== 'delete') return;
                            event.preventDefault();
                            postDelete(form, event.submitter || null);
                        });
                    });
                })();
                </script>
            <?php endif; ?>

        <?php elseif ($view === 'vendor-packages'): ?>
            <?php
            $editPackageId = max(0, (int)($_GET['edit'] ?? 0));
            $editPackage = null;
            foreach (($vendorsData['packages'] ?? []) as $packageRow) {
                if ((int)$packageRow['id'] === $editPackageId) {
                    $editPackage = $packageRow;
                    break;
                }
            }
            $features = $editPackage ? json_decode((string)($editPackage['features'] ?? '[]'), true) : [];
            if (!is_array($features)) {
                $features = [];
            }
            ?>
            <section class="panel">
                <div class="panel-header">
                <div>
                    <h2>Vendor Packages</h2>
                    <p>Create the plans vendors can manage from their vendor dashboard.</p>
                </div>
            </div>
                <form class="settings-form" method="post" action="<?= e(app_url('dashboard/vendor-packages')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="save_vendor_package">
                    <input type="hidden" name="id" value="<?= e($editPackage['id'] ?? 0) ?>">
                    <div class="settings-form-grid">
                        <label class="settings-field"><span>Name</span><input name="name" required value="<?= e($editPackage['name'] ?? '') ?>" placeholder="Basic"></label>
                        <label class="settings-field"><span>Price</span><input name="price" type="number" min="0" step="0.01" value="<?= e($editPackage['price'] ?? '0.00') ?>"></label>
                        <label class="settings-field"><span>Currency</span><input name="currency" maxlength="3" value="<?= e($editPackage['currency'] ?? 'USD') ?>"></label>
                        <label class="settings-field"><span>Billing</span><select name="billing_interval"><option value="month" <?= ($editPackage['billing_interval'] ?? 'month') === 'month' ? 'selected' : '' ?>>Monthly</option><option value="year" <?= ($editPackage['billing_interval'] ?? '') === 'year' ? 'selected' : '' ?>>Yearly</option></select></label>
                        <label class="settings-field"><span>Product Limit</span><input name="product_limit" type="number" min="0" value="<?= e($editPackage['product_limit'] ?? '') ?>" placeholder="Blank for unlimited"></label>
                        <label class="settings-field"><span>Transaction Fee %</span><input name="transaction_fee_percent" type="number" min="0" max="100" step="0.01" value="<?= e($editPackage['transaction_fee_percent'] ?? '0') ?>"></label>
                        <label class="settings-field span-2"><span>Stripe Price ID</span><input name="stripe_price_id" value="<?= e($editPackage['stripe_price_id'] ?? '') ?>" placeholder="Optional existing Stripe price_..."></label>
                        <label class="settings-field span-2"><span>Description</span><textarea name="description" rows="3"><?= e($editPackage['description'] ?? '') ?></textarea></label>
                        <label class="settings-field span-2"><span>Features, one per line</span><textarea name="features" rows="7"><?= e(implode("\n", $features)) ?></textarea></label>
                        <label class="settings-field"><span>Sort Order</span><input name="sort_order" type="number" value="<?= e($editPackage['sort_order'] ?? 0) ?>"></label>
                        <label class="settings-field"><span>Status</span><select name="is_active"><option value="1" <?= (int)($editPackage['is_active'] ?? 1) === 1 ? 'selected' : '' ?>>Active</option><option value="0" <?= (int)($editPackage['is_active'] ?? 1) !== 1 ? 'selected' : '' ?>>Inactive</option></select></label>
                        <label class="settings-toggle"><input type="checkbox" name="is_featured" value="1" <?= !empty($editPackage['is_featured']) ? 'checked' : '' ?>><span>Feature this package</span></label>
                    </div>
                    <button class="btn primary" type="submit"><?= $editPackage ? 'Update Package' : 'Create Package' ?></button>
                    <?php if ($editPackage): ?><a class="btn secondary" href="<?= e(app_url('dashboard/vendor-packages')) ?>">New Package</a><?php endif; ?>
                </form>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Package</th><th>Price</th><th>Products</th><th>Fee</th><th>Stripe</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach (($vendorsData['packages'] ?? []) as $package): ?>
                            <tr>
                                <td><strong><?= e($package['name']) ?></strong><small><?= e($package['description'] ?? '') ?></small></td>
                                <td><?= e($package['currency']) ?> <?= number_format((float)$package['price'], 2) ?><small>/<?= e($package['billing_interval']) ?></small></td>
                                <td><?= $package['product_limit'] === null ? 'Unlimited' : number_format((int)$package['product_limit']) ?></td>
                                <td><?= number_format((float)$package['transaction_fee_percent'], 2) ?>%</td>
                                <td><?= e($package['stripe_price_id'] ?: 'Dynamic Checkout price') ?></td>
                                <td><span class="settings-badge <?= (int)$package['is_active'] === 1 ? 'status-active' : 'status-failed' ?>"><?= (int)$package['is_active'] === 1 ? 'active' : 'inactive' ?></span></td>
                                <td><a class="btn secondary" href="<?= e(app_url('dashboard/vendor-packages?edit=' . (int)$package['id'])) ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($vendorsData['packages'] ?? []) === []): ?><tr><td colspan="7">No vendor packages found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($view === 'vendor-subscriptions'): ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Vendor Subscriptions</h2>
                        <p>Stripe subscription state and product upload grace period.</p>
                    </div>
                    <a class="btn secondary" href="<?= e(app_url('api/webhooks/stripe')) ?>" target="_blank" rel="noopener">Webhook URL</a>
                </div>
                <?php if (\App\AuthService::hasRole('super_admin')): ?>
                <form class="settings-form" method="post" action="<?= e(app_url('dashboard/vendor-subscriptions')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="save_subscription_settings">
                    <div class="settings-form-grid">
                        <label class="settings-field"><span>Unpaid Grace Days</span><input name="grace_days" type="number" min="0" max="90" value="<?= e($vendorsData['graceDays'] ?? 7) ?>"></label>
                    </div>
                    <button class="btn primary" type="submit">Save Grace Period</button>
                </form>
                <?php endif; ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Vendor</th><th>Package</th><th>Status</th><th>Provider</th><th>Stripe Subscription</th><th>Period End</th><th>Grace Ends</th><th>Updated</th></tr></thead>
                        <tbody>
                        <?php foreach (($vendorsData['subscriptions'] ?? []) as $subscription): ?>
                            <tr>
                                <td><strong><?= e($subscription['store_name']) ?></strong><small><?= e($subscription['owner_email']) ?></small></td>
                                <td><?= e($subscription['package_name']) ?><small><?= $subscription['product_limit'] === null ? 'Unlimited products' : number_format((int)$subscription['product_limit']) . ' products' ?></small></td>
                                <td><span class="settings-badge <?= e($statusClass((string)$subscription['status'])) ?>"><?= e($subscription['status']) ?></span></td>
                                <td><?= e($subscription['provider']) ?></td>
                                <td><code><?= e($subscription['stripe_subscription_id'] ?: '-') ?></code></td>
                                <td><?= e($subscription['current_period_end'] ?: '-') ?></td>
                                <td><?= e($subscription['grace_ends_at'] ?: '-') ?></td>
                                <td><?= e($subscription['updated_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($vendorsData['subscriptions'] ?? []) === []): ?><tr><td colspan="8">No vendor subscriptions found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($view === 'vendor-kyc-documents'): ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Vendor KYC Documents</h2>
                        <p>Submitted identity, business and verification documents.</p>
                    </div>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/pending-vendors')) ?>">Pending Vendors</a>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Vendor</th><th>Document</th><th>Number</th><th>File</th><th>Status</th><th>Reviewer</th><th>Submitted</th><th>Reviewed</th></tr></thead>
                        <tbody>
                        <?php foreach (($vendorsData['kycDocuments'] ?? []) as $doc): ?>
                            <tr>
                                <td><strong><?= e($doc['store_name']) ?></strong><small><?= e($doc['store_slug']) ?></small></td>
                                <td><?= e($doc['document_type']) ?></td>
                                <td><?= e($doc['document_number'] ?? '-') ?></td>
                                <td>
                                    <?php if (($doc['file_path'] ?? '') !== ''): ?>
                                        <a href="<?= e(app_brand_asset_url($doc['file_path'])) ?>" target="_blank" rel="noopener"><?= e($doc['original_name'] ?: 'View file') ?></a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><span class="settings-badge <?= e($statusClass((string)$doc['status'])) ?>"><?= e($doc['status']) ?></span></td>
                                <td><?= e($doc['reviewer_name'] ?? '-') ?></td>
                                <td><?= e($doc['submitted_at']) ?></td>
                                <td><?= e($doc['reviewed_at'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($vendorsData['kycDocuments'] ?? []) === []): ?>
                            <tr><td colspan="8">No KYC documents found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($view === 'vendor-ratings'): ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Vendor Ratings</h2>
                        <p>Review quality signals aggregated from approved product reviews.</p>
                    </div>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/product-reviews')) ?>">Product Reviews</a>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Vendor</th><th>Status</th><th>KYC</th><th>Average Rating</th><th>Reviews</th><th>Pending Reviews</th><th>Latest Review</th></tr></thead>
                        <tbody>
                        <?php foreach (($vendorsData['ratings'] ?? []) as $rating): ?>
                            <tr>
                                <td><strong><?= e($rating['store_name']) ?></strong><small><?= e($rating['store_slug']) ?></small></td>
                                <td><span class="settings-badge <?= e($statusClass((string)$rating['status'])) ?>"><?= e($rating['status']) ?></span></td>
                                <td><?= e($rating['kyc_status']) ?></td>
                                <td><strong><?= number_format((float)$rating['avg_rating'], 2) ?>/5</strong></td>
                                <td><?= number_format((int)$rating['review_count']) ?></td>
                                <td><?= number_format((int)$rating['pending_reviews']) ?></td>
                                <td><?= e($rating['latest_review_at'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($vendorsData['ratings'] ?? []) === []): ?>
                            <tr><td colspan="7">No vendor ratings found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($view === 'vendor-payout-accounts'): ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Vendor Payout Accounts</h2>
                        <p>Payout method coverage, pending withdrawals and paid totals.</p>
                    </div>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/vendor-withdrawals')) ?>">Withdrawals</a>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Vendor</th><th>Status</th><th>Payout Method</th><th>Account Details</th><th>Withdrawals</th><th>Pending</th><th>Paid</th><th>Latest Request</th></tr></thead>
                        <tbody>
                        <?php foreach (($vendorsData['payouts'] ?? []) as $payout): ?>
                            <tr>
                                <td><strong><?= e($payout['store_name']) ?></strong><small><?= e($payout['store_slug']) ?></small></td>
                                <td><span class="settings-badge <?= e($statusClass((string)$payout['status'])) ?>"><?= e($payout['status']) ?></span></td>
                                <td><?= e($payout['payout_method'] ?: 'Not set') ?></td>
                                <td><?= e($payoutSummary($payout['payout_details'] ?? null)) ?></td>
                                <td><?= number_format((int)$payout['withdrawal_count']) ?></td>
                                <td>$<?= number_format((float)$payout['pending_amount'], 2) ?></td>
                                <td>$<?= number_format((float)$payout['paid_amount'], 2) ?></td>
                                <td><?= e($payout['latest_request_at'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (($vendorsData['payouts'] ?? []) === []): ?>
                            <tr><td colspan="8">No payout account records found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php else: ?>
            <section class="dashboard-grid-single">
                <div class="panel">
	                    <div class="panel-header">
	                        <div>
	                            <h2><?= e($vendorLinks[$view] ?? 'All Vendors') ?></h2>
	                            <p><?= $view === 'vendor-stores' ? 'Store performance, product coverage and marketplace sales.' : 'Vendor account, KYC, products and payout readiness.' ?> Showing <?= number_format((int)($pagination['from'] ?? 0)) ?>-<?= number_format((int)($pagination['to'] ?? 0)) ?> of <?= number_format((int)($pagination['total'] ?? 0)) ?>.</p>
	                        </div>
	                        <span class="settings-badge"><?= number_format(count($vendorRows)) ?> loaded</span>
	                    </div>
                        <?php if ($view === 'pending-vendors'): ?>
                            <div class="vendor-completion-summary" aria-label="Pending vendor completion summary">
                                <?php foreach (\App\VendorCompletionService::RANGES as $range => $label): ?>
                                    <div><strong><?= number_format((int)($vendorsData['completion']['summary'][$range] ?? 0)) ?></strong><span><?= e($label) ?> complete</span></div>
                                <?php endforeach; ?>
                            </div>
                            <p><?= ($pagination['search'] ?? '') !== '' ? 'Completion totals for pending vendors matching your search.' : 'Completion totals for all pending vendors.' ?> Highest completion first.</p>
                        <?php endif; ?>
	                    <form class="admin-searchbar vendor-completion-search" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
	                        <input type="search" name="q" value="<?= e($pagination['search'] ?? '') ?>" placeholder="Search vendors by store, owner, email or phone">
	                        <input type="hidden" name="per_page" value="<?= e($perPage) ?>">
                            <?php if ($view === 'pending-vendors'): ?>
                                <label for="vendor-completion">Completion
                                    <select id="vendor-completion" name="completion">
                                        <option value="">All percentages</option>
                                        <?php foreach (\App\VendorCompletionService::RANGES as $range => $label): ?>
                                            <option value="<?= e($range) ?>" <?= (string)($vendorsData['completion']['filter'] ?? '') === (string)$range ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endif; ?>
	                        <button class="btn secondary" type="submit">Search</button>
	                        <?php if (($pagination['search'] ?? '') !== '' || ($vendorsData['completion']['filter'] ?? '') !== ''): ?><a class="btn secondary" href="<?= e(app_url('dashboard/' . $view)) ?>">Clear</a><?php endif; ?>
	                    </form>
	                    <div class="vendor-review-list">
                            <?php foreach ($vendorRows as $vendor): ?>
                                <?php
                                $ready = $vendorReadiness($vendor);
                                $missingApplicationFields = $vendorMissingApplicationFields($vendor);
                                $productSummary = $summaryItems($vendor['product_summary'] ?? '', 4);
                                $applicationProducts = $vendorApplicationProductSummary($vendor);
                                $documentSummary = $summaryItems($vendor['document_summary'] ?? '', 4);
                                $registrationDate = 'Not recorded';
                                if (!empty($vendor['created_at']) && !str_starts_with((string)$vendor['created_at'], '0000-')) {
                                    try {
                                        $registrationDate = app_date('d M Y, g:i A', (string)$vendor['created_at']) . ' (' . app_timezone() . ')';
                                    } catch (\Throwable) {
                                        // Older imported records may not contain a valid timestamp.
                                    }
                                }
                                ?>
                                <article class="vendor-review-card">
                                    <div class="vendor-review-main">
                                        <div class="vendor-review-title">
                                            <div>
                                                <h3><?= e($vendor['store_name']) ?></h3>
                                                <p><?= e($vendor['store_slug']) ?></p>
                                            </div>
                                            <div class="vendor-review-badges">
                                                <span class="settings-badge <?= e($statusClass((string)$vendor['status'])) ?>"><?= e($vendor['status']) ?></span>
                                                <span class="settings-badge <?= e($statusClass((string)$vendor['kyc_status'])) ?>">KYC <?= e($vendor['kyc_status']) ?></span>
                                            </div>
                                        </div>
                                        <div class="vendor-review-owner">
                                            <span><?= e($vendor['display_name'] ?: $vendor['owner_email']) ?></span>
                                            <small><?= e($vendor['store_email'] ?: $vendor['owner_email']) ?></small>
                                            <small>Registered: <?= e($registrationDate) ?></small>
                                            <?php if (!empty($vendor['origin_region']) || !empty($vendor['country_of_origin'])): ?>
                                                <small><?= e(trim((string)($vendor['origin_region'] ?? '') . ' | ' . (string)($vendor['country_of_origin'] ?? ''), ' |')) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="vendor-review-stats" aria-label="Vendor activity">
                                            <span><strong><?= number_format((int)$vendor['active_product_count']) ?>/<?= number_format((int)$vendor['product_count']) ?></strong> Products</span>
                                            <span><strong><?= number_format((int)$vendor['pending_product_count']) ?></strong> Pending products</span>
                                            <span><strong><?= number_format((int)$vendor['uploaded_document_count']) ?>/<?= number_format((int)$vendor['kyc_document_count']) ?></strong> Docs uploaded</span>
                                            <span><strong><?= number_format((int)$vendor['order_count']) ?></strong> Orders</span>
                                        </div>
                                    </div>
                                    <div class="vendor-review-status">
                                        <div class="approval-checks">
                                            <strong><?= number_format($ready['percent']) ?>% ready</strong>
                                            <?php foreach ($ready['checks'] as $check): ?>
                                                <?php if ($check['done'] && !empty($check['preview_url'])): ?>
                                                <a class="is-done" href="<?= e($check['preview_url']) ?>" data-document-preview data-vendor="<?= e($vendor['id']) ?>" data-title="<?= e($check['label']) ?>" data-mime="<?= e($check['preview_mime'] ?? '') ?>">Done &middot; <?= e($check['label']) ?></a>
                                                <?php else: ?>
                                                <span class="<?= $check['done'] ? 'is-done' : 'is-missing' ?>"><?= $check['done'] ? 'Done' : 'Missing' ?> &middot; <?= e($check['label']) ?></span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="vendor-uploaded-files vendor-review-data">
                                            <strong>Missing application data</strong>
                                            <?php if ($missingApplicationFields === []): ?>
                                                <span class="vendor-uploaded-files__empty is-complete">No missing application answers found.</span>
                                            <?php else: ?>
                                                <?php foreach (array_slice($missingApplicationFields, 0, 8) as $missingField): ?>
                                                    <span class="vendor-uploaded-files__empty is-warning"><?= e($missingField) ?></span>
                                                <?php endforeach; ?>
                                                <?php if (count($missingApplicationFields) > 8): ?>
                                                    <span class="vendor-uploaded-files__empty is-warning">+<?= number_format(count($missingApplicationFields) - 8) ?> more missing fields</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="vendor-uploaded-files vendor-review-products">
                                            <strong>Product uploaded</strong>
                                            <?php if ($productSummary !== []): ?>
                                                <?php foreach ($productSummary as $productItem): ?>
                                                    <?php [$productName, $productSku, $productStatus] = array_pad(array_map('trim', explode(' :: ', $productItem)), 3, ''); ?>
                                                    <span class="vendor-uploaded-files__empty">
                                                        <?= e($productName !== '' ? $productName : 'Unnamed product') ?>
                                                        <small><?= e(trim(($productSku !== '' ? 'SKU ' . $productSku . ' · ' : '') . ($productStatus !== '' ? ucfirst($productStatus) : ''), ' ·')) ?></small>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php elseif ($applicationProducts !== []): ?>
                                                <?php foreach ($applicationProducts as $applicationProduct): ?>
                                                    <span class="vendor-uploaded-files__empty is-complete">
                                                        <?= e((string)$applicationProduct['name']) ?>
                                                        <?php if (trim((string)($applicationProduct['meta'] ?? '')) !== ''): ?>
                                                            <small><?= e((string)$applicationProduct['meta']) ?></small>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="vendor-uploaded-files__empty is-warning">No product has been uploaded yet.</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="vendor-uploaded-files">
                                            <strong>Uploaded KYC files</strong>
                                            <?php foreach ($documentSummary as $documentItem): ?>
                                                <?php [$documentType, $documentStatus, $documentName] = array_pad(array_map('trim', explode(' :: ', $documentItem)), 3, ''); ?>
                                                <span class="vendor-uploaded-files__empty">
                                                    <?= e(ucwords(str_replace('_', ' ', $documentType))) ?>
                                                    <small><?= e(($documentName !== '' ? $documentName : 'No file') . ($documentStatus !== '' ? ' · ' . $documentStatus : '')) ?></small>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php foreach ($kycDocumentsByVendor[(int)$vendor['id']] ?? [] as $document): ?>
                                                <?php if (($document['file_path'] ?? '') !== ''): ?>
                                                    <a href="<?= e(app_brand_asset_url($document['file_path'])) ?>" data-document-preview data-vendor="<?= e($vendor['id']) ?>" data-title="<?= e($document['original_name'] ?: $document['document_type']) ?>" data-mime="<?= e($document['mime_type'] ?? '') ?>" target="_blank" rel="noopener">
                                                        <span><?= e(ucwords(str_replace('_', ' ', (string)$document['document_type']))) ?></span>
                                                        <small><?= e($document['original_name'] ?: 'View file') ?> &middot; <?= e($document['status']) ?></small>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="vendor-uploaded-files__empty"><?= e(ucwords(str_replace('_', ' ', (string)$document['document_type']))) ?> has no file</span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <?php if (($kycDocumentsByVendor[(int)$vendor['id']] ?? []) === []): ?>
                                                <span class="vendor-uploaded-files__empty">No KYC files uploaded yet.</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="vendor-review-actions">
                                        <a class="btn secondary" href="<?= e(app_url('dashboard/vendor-edit?vendor=' . (int)$vendor['id'])) ?>">Edit Everything</a>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                            <input type="hidden" name="action" value="update_vendor_approval">
                                            <input type="hidden" name="return_view" value="<?= e($view) ?>">
                                            <input type="hidden" name="vendor_id" value="<?= e($vendor['id']) ?>">
                                            <input type="hidden" name="decision" value="approve">
                                            <button class="btn primary" type="submit">Approve</button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                            <input type="hidden" name="action" value="update_vendor_approval">
                                            <input type="hidden" name="return_view" value="<?= e($view) ?>">
                                            <input type="hidden" name="vendor_id" value="<?= e($vendor['id']) ?>">
                                            <input type="hidden" name="decision" value="reject">
                                            <input type="hidden" name="reason" value="Rejected during admin review">
                                            <button class="btn secondary danger" type="submit">Reject</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                            <?php if ($vendorRows === []): ?>
                                <div class="vendor-review-empty">No vendors found for this view.</div>
                            <?php endif; ?>
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
	                                <?php if ($view === 'pending-vendors' && ($vendorsData['completion']['filter'] ?? '') !== ''): ?><input type="hidden" name="completion" value="<?= e($vendorsData['completion']['filter']) ?>"><?php endif; ?>
	                                <?php if (($pagination['search'] ?? '') !== ''): ?><input type="hidden" name="q" value="<?= e($pagination['search']) ?>"><?php endif; ?>
	                                <label><span>Rows</span><select name="per_page" onchange="this.form.submit()">
	                                    <?php foreach (($pagination['perPageOptions'] ?? [25, 50, 100]) as $option): ?><option value="<?= e($option) ?>" <?= (int)$option === $perPage ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
	                                </select></label>
	                            </form>
	                        </div>
	                    <?php endif; ?>
	                </div>
                <?php if (false): ?>
                    <aside class="panel">
                        <div class="panel-header">
                            <div>
                                <h2>Vendor Health</h2>
                                <p>Status and KYC distribution.</p>
                            </div>
                        </div>
                        <div class="status-list">
                            <?php foreach (($vendorsData['statusTotals'] ?? []) as $status => $total): ?>
                                <div class="status-item"><strong><?= e(ucfirst((string)$status)) ?></strong><span><?= number_format((int)$total) ?> vendors</span></div>
                            <?php endforeach; ?>
                            <?php foreach (($vendorsData['kycTotals'] ?? []) as $status => $total): ?>
                                <div class="status-item"><strong>KYC <?= e(ucfirst(str_replace('_', ' ', (string)$status))) ?></strong><span><?= number_format((int)$total) ?> vendors</span></div>
                            <?php endforeach; ?>
                        </div>
                    </aside>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</section>
<dialog id="vendor-document-preview" aria-labelledby="vendor-preview-title">
    <header><h2 id="vendor-preview-title">Document preview</h2><button type="button" data-preview-close aria-label="Close preview">&times;</button></header>
    <div id="vendor-preview-content"></div>
    <footer><button type="button" data-preview-prev aria-label="Previous document">&larr;</button><span id="vendor-preview-count" aria-live="polite"></span><button type="button" data-preview-next aria-label="Next document">&rarr;</button><a id="vendor-preview-download" target="_blank" rel="noopener">Open / Download</a></footer>
</dialog>
<style>
.vendor-completion-summary { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; padding: 16px 0; border-block: 1px solid #dceae2; }
.vendor-completion-summary > div { min-width: 0; display: grid; gap: 4px; }
.vendor-completion-summary strong { font-size: 24px; color: #177d51; }
.vendor-completion-summary span { font-size: 14px; overflow-wrap: anywhere; }
.vendor-completion-search { flex-wrap: wrap; }
.vendor-completion-search label { display: grid; gap: 4px; min-width: 0; font-size: 14px; }
.vendor-completion-search select { width: 100%; min-height: 44px; padding: 8px 12px; border: 1px solid #dceae2; border-radius: 8px; background: white; color: #16372a; font: inherit; }
.vendor-completion-search select:focus-visible { outline: 2px solid #177d51; outline-offset: 2px; }
@media (max-width: 640px) {
    .vendor-completion-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .vendor-completion-search label { width: 100%; }
}
#vendor-document-preview { width: min(960px, calc(100vw - 24px)); max-width: 100%; max-height: 92dvh; padding: 16px; border: 1px solid #dceae2; border-radius: 8px; }
#vendor-document-preview::backdrop { background: rgb(0 0 0 / 60%); }
#vendor-document-preview header, #vendor-document-preview footer { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
#vendor-preview-title { flex: 1; min-width: 0; overflow-wrap: anywhere; font-size: 18px; }
#vendor-document-preview button { min-width: 44px; min-height: 44px; cursor: pointer; }
#vendor-preview-content { height: min(65dvh, 680px); margin: 12px 0; overflow: auto; }
#vendor-preview-content img { width: 100%; height: 100%; object-fit: contain; }
#vendor-preview-content iframe { width: 100%; height: 100%; border: 0; }
#vendor-preview-download { margin-left: auto; }
</style>
<script>
(() => {
    const dialog = document.getElementById('vendor-document-preview');
    const content = document.getElementById('vendor-preview-content');
    const previous = dialog.querySelector('[data-preview-prev]');
    const next = dialog.querySelector('[data-preview-next]');
    let files = [], index = 0, trigger;
    function show() {
        const file = files[index];
        const url = new URL(file.href, location.href);
        if (!['https:', 'http:'].includes(url.protocol)) return;
        content.replaceChildren();
        document.getElementById('vendor-preview-title').textContent = file.dataset.title;
        document.getElementById('vendor-preview-count').textContent = `${index + 1} of ${files.length}`;
        document.getElementById('vendor-preview-download').href = url.href;
        previous.disabled = index === 0;
        next.disabled = index === files.length - 1;
        const mime = file.dataset.mime || '';
        if (['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(mime) || /\.(jpe?g|png|webp|gif)$/i.test(url.pathname)) {
            const img = document.createElement('img');
            img.alt = file.dataset.title;
            img.src = url.href;
            img.onerror = () => { content.textContent = 'Image unavailable. Use Open / Download to view the file.'; };
            content.append(img);
        } else if (mime === 'application/pdf' || /\.pdf$/i.test(url.pathname)) {
            const frame = document.createElement('iframe');
            frame.title = file.dataset.title;
            frame.setAttribute('sandbox', '');
            frame.src = url.href;
            content.append(frame);
        } else {
            content.textContent = 'Preview is not available for this format. Use Open / Download to view the file.';
        }
    }
    document.querySelectorAll('[data-document-preview]').forEach(link => link.addEventListener('click', event => {
        event.preventDefault();
        trigger = link;
        files = [...document.querySelectorAll('[data-document-preview]')].filter(item => item.dataset.vendor === link.dataset.vendor);
        index = files.indexOf(link);
        show();
        dialog.showModal();
    }));
    previous.addEventListener('click', () => { if (index > 0) { index--; show(); } });
    next.addEventListener('click', () => { if (index < files.length - 1) { index++; show(); } });
    dialog.querySelector('[data-preview-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { content.replaceChildren(); trigger?.focus(); });
})();
</script>
