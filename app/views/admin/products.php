<?php
$productsData = $productsData ?? [];
$productLinks = [
    'products' => 'All Products',
    'pending-products' => 'Pending Approval',
    'approved-products' => 'Approved Products',
    'rejected-products' => 'Rejected Products',
    'featured-products' => 'Featured Products',
    'ranked-products' => 'Ranked Products',
    'low-stock-products' => 'Low Stock Products',
    'out-of-stock-products' => 'Out of Stock Products',
    'duplicate-products' => 'Duplicate Cleanup',
    'product-comments' => 'Product Comments',
    'product-reviews' => 'Product Reviews',
    'product-meta-tags' => 'Product Meta Tags',
];
$stats = $productsData['stats'] ?? [];
$statusClass = static fn (string $status): string => 'status-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $status) ?? 'unknown');
$productRows = match ($view) {
    'pending-products' => $productsData['pending'] ?? [],
    'approved-products' => $productsData['approved'] ?? [],
    'rejected-products' => $productsData['rejected'] ?? [],
    'featured-products' => $productsData['featured'] ?? [],
    'ranked-products' => $productsData['ranked'] ?? [],
    'low-stock-products' => $productsData['lowStock'] ?? [],
    'out-of-stock-products' => $productsData['outOfStock'] ?? [],
    default => $productsData['products'] ?? [],
};
$edit = $productsData['edit'] ?? null;
$showForm = (bool)($productsData['showForm'] ?? false);
$value = static fn (string $key, mixed $default = ''): string => e($edit[$key] ?? $default);
$pagination = $productsData['pagination'] ?? [];
$productPage = max(1, (int)($pagination['page'] ?? 1));
$productTotalPages = max(1, (int)($pagination['totalPages'] ?? 1));
$productPerPage = (int)($pagination['perPage'] ?? 25);
$productListUrl = static function (int $page, ?int $perPage = null) use ($view, $productPerPage): string {
    $query = ['page' => max(1, $page), 'per_page' => $perPage ?? $productPerPage];
    if (trim((string)($_GET['q'] ?? '')) !== '') {
        $query['q'] = trim((string)$_GET['q']);
    }
    return app_url('dashboard/' . $view . '?' . http_build_query($query));
};
?>

<section class="settings-shell products-shell">
    <aside class="settings-index panel">
        <div class="panel-header">
            <div>
                <h2>Products</h2>
                <p>Catalog approval, inventory, uploads and product intelligence</p>
            </div>
        </div>
        <nav class="settings-nav" aria-label="Product pages">
            <?php foreach ($productLinks as $key => $label): ?>
                <a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(app_url('dashboard/' . $key)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="settings-content">
        <section class="metric-grid settings-metrics" aria-label="Product metrics">
            <div class="metric"><span>Total Products</span><strong><?= number_format((int)($stats['total_products'] ?? 0)) ?></strong><small>Catalog records</small></div>
            <div class="metric"><span>Pending</span><strong><?= number_format((int)($stats['pending_products'] ?? 0)) ?></strong><small>Awaiting approval</small></div>
            <div class="metric"><span>Approved</span><strong><?= number_format((int)($stats['approved_products'] ?? 0)) ?></strong><small>Active listings</small></div>
            <div class="metric"><span>Stock Alerts</span><strong><?= number_format((int)($stats['low_stock_products'] ?? 0) + (int)($stats['out_of_stock_products'] ?? 0)) ?></strong><small>Low or out of stock</small></div>
        </section>

        <?php if ($showForm): ?>
            <form class="panel settings-form product-form" method="post" action="<?= e(app_url('dashboard/products')) ?>" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="save_product">
                <input type="hidden" name="product_id" value="<?= e($edit['id'] ?? 0) ?>">
                <div class="panel-header">
                    <div>
                        <h2><?= $edit ? 'Edit Product' : 'Add Product' ?></h2>
                        <p>Admins can create, edit, approve and upload product images from here.</p>
                    </div>
                    <button class="btn" type="submit">Save Product</button>
                </div>
                <div class="settings-form-grid">
                    <label class="settings-field"><span>Name</span><input name="name" required value="<?= $value('name') ?>"></label>
                    <label class="settings-field"><span>Slug</span><input name="slug" value="<?= $value('slug') ?>" placeholder="Auto generated if blank"></label>
                    <label class="settings-field"><span>SKU</span><input name="sku" required value="<?= $value('sku') ?>"></label>
                    <label class="settings-field"><span>Type</span><select name="type">
                        <?php foreach (['simple', 'variable', 'variation', 'grouped', 'external', 'digital'] as $type): ?>
                            <option value="<?= e($type) ?>" <?= ($edit['type'] ?? 'simple') === $type ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label class="settings-field"><span>Vendor</span><select name="vendor_id"><option value="">No vendor</option>
                        <?php foreach (($productsData['vendors'] ?? []) as $vendor): ?>
                            <option value="<?= e($vendor['id']) ?>" <?= (int)($edit['vendor_id'] ?? 0) === (int)$vendor['id'] ? 'selected' : '' ?>><?= e($vendor['store_name']) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label class="settings-field"><span>Brand</span><select name="brand_id"><option value="">No brand</option>
                        <?php foreach (($productsData['brands'] ?? []) as $brand): ?>
                            <option value="<?= e($brand['id']) ?>" <?= (int)($edit['brand_id'] ?? 0) === (int)$brand['id'] ? 'selected' : '' ?>><?= e($brand['name']) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label class="settings-field"><span>Status</span><select name="status">
                        <?php foreach (['draft', 'pending', 'active', 'private', 'archived', 'rejected'] as $status): ?>
                            <option value="<?= e($status) ?>" <?= ($edit['status'] ?? 'draft') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label class="settings-field"><span>Stock Status</span><select name="stock_status">
                        <?php foreach (['in_stock', 'out_of_stock', 'on_backorder'] as $stock): ?>
                            <option value="<?= e($stock) ?>" <?= ($edit['stock_status'] ?? 'in_stock') === $stock ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $stock))) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label class="settings-field"><span>Regular Price</span><input type="number" step="0.01" name="regular_price" value="<?= $value('regular_price', '0') ?>"></label>
                    <label class="settings-field"><span>Sale Price</span><input type="number" step="0.01" name="sale_price" value="<?= $value('sale_price') ?>"></label>
                    <label class="settings-field"><span>Currency</span><input maxlength="3" name="currency" value="<?= $value('currency', 'USD') ?>"></label>
                    <label class="settings-field"><span>Tax Status</span><select name="tax_status">
                        <?php foreach (['taxable', 'shipping', 'none'] as $taxStatus): ?>
                            <option value="<?= e($taxStatus) ?>" <?= ($edit['tax_status'] ?? 'taxable') === $taxStatus ? 'selected' : '' ?>><?= e(ucfirst($taxStatus)) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label class="settings-field"><span>Stock Quantity</span><input type="number" name="stock_quantity" value="<?= $value('stock_quantity') ?>"></label>
                    <label class="settings-field"><span>Low Stock Threshold</span><input type="number" name="low_stock_threshold" value="<?= $value('low_stock_threshold') ?>"></label>
                    <div class="settings-field span-2 product-shipping-group">
                        <span>Shipping</span>
                        <div class="settings-form-grid">
                            <label class="settings-field"><span>Weight</span><input type="number" step="0.0001" min="0" name="weight" value="<?= $value('weight') ?>"></label>
                            <label class="settings-field"><span>Length</span><input type="number" step="0.0001" min="0" name="length" value="<?= $value('length') ?>"></label>
                            <label class="settings-field"><span>Width</span><input type="number" step="0.0001" min="0" name="width" value="<?= $value('width') ?>"></label>
                            <label class="settings-field"><span>Height</span><input type="number" step="0.0001" min="0" name="height" value="<?= $value('height') ?>"></label>
                            <label class="settings-field span-2"><span>Shipping Class</span><input name="shipping_class" value="<?= $value('shipping_class') ?>" placeholder="standard-shipping"></label>
                        </div>
                    </div>
                    <label class="settings-field"><span>Product Image</span><input type="file" name="product_image" accept="image/jpeg,image/png,image/webp"></label>
                    <label class="settings-field product-check"><span>Flags</span><span><input type="checkbox" name="manage_stock" <?= !empty($edit['manage_stock']) ? 'checked' : '' ?>> Manage stock</span><span><input type="checkbox" name="featured" <?= !empty($edit['featured']) ? 'checked' : '' ?>> Featured</span></label>
                    <label class="settings-field span-2"><span>Short Description</span><textarea name="short_description" rows="3"><?= $value('short_description') ?></textarea></label>
                    <label class="settings-field span-2"><span>Description</span><textarea name="description" rows="6"><?= $value('description') ?></textarea></label>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($view === 'duplicate-products'): ?>
            <section class="panel" data-duplicate-cleanup data-endpoint="<?= e(app_url('api/admin/product-duplicates')) ?>" data-csrf="<?= e(getCsrfToken()) ?>">
                <div class="panel-header">
                    <div>
                        <h2>Duplicate Product Cleanup</h2>
                        <p>Scan same-name product groups, keep the strongest listing, and archive the extras so they no longer appear in the storefront.</p>
                    </div>
                    <div class="approval-actions">
                        <button class="btn secondary" type="button" data-scan-duplicates>Scan Duplicates</button>
                        <button class="btn primary" type="button" data-clean-selected disabled>Archive Selected</button>
                        <button class="btn secondary danger" type="button" data-clean-batch disabled>Archive First Batch</button>
                    </div>
                </div>
                <div class="metric-grid settings-metrics" aria-label="Duplicate product metrics">
                    <div class="metric"><span>Duplicate Groups</span><strong data-duplicate-groups>0</strong><small>Same normalized name</small></div>
                    <div class="metric"><span>Products To Archive</span><strong data-duplicate-products>0</strong><small>All except chosen keeper</small></div>
                    <div class="metric"><span>Archived This Run</span><strong data-duplicate-archived>0</strong><small>Removed from storefront</small></div>
                    <div class="metric"><span>Status</span><strong data-duplicate-status>Ready</strong><small>Scan before cleanup</small></div>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Select</th><th>Duplicate Group</th><th>Products</th><th>Keep</th><th>Archive</th></tr></thead>
                        <tbody data-duplicate-results>
                            <tr><td colspan="5">Click Scan Duplicates to inspect product groups.</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="settings-help-text">Cleanup uses the existing archived status instead of hard-deleting rows. That protects previous order history while removing duplicate listings from customer-facing pages.</p>
            </section>
            <script>
            (() => {
                const shell = document.querySelector('[data-duplicate-cleanup]');
                if (!shell) return;
                const endpoint = shell.dataset.endpoint;
                const csrf = shell.dataset.csrf;
                const results = shell.querySelector('[data-duplicate-results]');
                const scanButton = shell.querySelector('[data-scan-duplicates]');
                const cleanSelectedButton = shell.querySelector('[data-clean-selected]');
                const cleanBatchButton = shell.querySelector('[data-clean-batch]');
                const groupMetric = shell.querySelector('[data-duplicate-groups]');
                const productMetric = shell.querySelector('[data-duplicate-products]');
                const archivedMetric = shell.querySelector('[data-duplicate-archived]');
                const statusMetric = shell.querySelector('[data-duplicate-status]');

                let lastGroups = [];

                const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                })[char]);

                const post = (data) => {
                    const body = new URLSearchParams();
                    body.set('csrf_token', csrf);
                    Object.entries(data).forEach(([key, value]) => {
                        body.set(key, Array.isArray(value) ? JSON.stringify(value) : String(value));
                    });

                    return fetch(endpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        credentials: 'same-origin',
                        body
                    }).then((response) => response.json().then((payload) => {
                        if (!response.ok || !payload.ok) {
                            throw new Error(payload.message || 'Duplicate cleanup request failed.');
                        }
                        return payload.result;
                    }));
                };

                const updateButtons = () => {
                    const hasGroups = lastGroups.length > 0;
                    const hasSelection = shell.querySelectorAll('[data-duplicate-check]:checked').length > 0;
                    cleanBatchButton.disabled = !hasGroups;
                    cleanSelectedButton.disabled = !hasSelection;
                };

                const render = (scan) => {
                    lastGroups = scan.groups || [];
                    groupMetric.textContent = Number(scan.group_count || 0).toLocaleString();
                    productMetric.textContent = Number(scan.duplicate_product_count || 0).toLocaleString();
                    statusMetric.textContent = lastGroups.length ? 'Review' : 'Clean';

                    if (!lastGroups.length) {
                        results.innerHTML = '<tr><td colspan="5">No duplicate product groups found.</td></tr>';
                        updateButtons();
                        return;
                    }

                    results.innerHTML = lastGroups.map((group) => {
                        const keeper = (group.products || []).find((product) => product.will_keep) || {};
                        const archive = (group.products || []).filter((product) => !product.will_keep);
                        const productList = (group.products || []).map((product) => {
                            const marker = product.will_keep ? '<strong>Keep</strong>' : '<span>Archive</span>';
                            return `<div><code>#${product.id}</code> ${escapeHtml(product.status)} ${marker}<br><small>${escapeHtml(product.vendor)} · ${escapeHtml(product.price)} · sales ${product.sales}</small></div>`;
                        }).join('');

                        return `<tr>
                            <td><input type="checkbox" data-duplicate-check value="${escapeHtml(group.hash)}"></td>
                            <td><strong>${escapeHtml(group.name)}</strong><small>${group.count} matching products</small></td>
                            <td>${productList}</td>
                            <td><code>#${keeper.id || ''}</code><small>${escapeHtml(keeper.updated_at || '')}</small></td>
                            <td>${archive.map((product) => `<code>#${product.id}</code>`).join(' ')}</td>
                        </tr>`;
                    }).join('');
                    shell.querySelectorAll('[data-duplicate-check]').forEach((box) => box.addEventListener('change', updateButtons));
                    updateButtons();
                };

                const scan = () => {
                    statusMetric.textContent = 'Scanning';
                    results.innerHTML = '<tr><td colspan="5">Scanning duplicate product names...</td></tr>';
                    scanButton.disabled = true;
                    post({ action: 'scan', limit: 80 })
                        .then(render)
                        .catch((error) => {
                            statusMetric.textContent = 'Error';
                            results.innerHTML = `<tr><td colspan="5">${escapeHtml(error.message)}</td></tr>`;
                        })
                        .finally(() => {
                            scanButton.disabled = false;
                        });
                };

                const cleanup = (hashes) => {
                    if (!window.confirm('Archive duplicate products in the selected group(s)? The kept product stays active.')) return;
                    statusMetric.textContent = 'Archiving';
                    cleanSelectedButton.disabled = true;
                    cleanBatchButton.disabled = true;
                    post({ action: 'cleanup', hashes, limit_groups: hashes.length || 20 })
                        .then((result) => {
                            archivedMetric.textContent = Number(result.archived_count || 0).toLocaleString();
                            render(result.scan || { groups: [] });
                            statusMetric.textContent = 'Done';
                        })
                        .catch((error) => {
                            statusMetric.textContent = 'Error';
                            alert(error.message);
                        })
                        .finally(updateButtons);
                };

                scanButton.addEventListener('click', scan);
                cleanSelectedButton.addEventListener('click', () => {
                    cleanup(Array.from(shell.querySelectorAll('[data-duplicate-check]:checked')).map((box) => box.value));
                });
                cleanBatchButton.addEventListener('click', () => cleanup([]));
                scan();
            })();
            </script>
        <?php elseif (in_array($view, ['product-comments', 'product-reviews', 'product-meta-tags'], true)): ?>
            <?php require VIEW_PATH . '/admin/products_secondary.php'; ?>
        <?php else: ?>
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2><?= e($productLinks[$view] ?? 'All Products') ?></h2>
                        <p>
                            Manage product approval, pricing, visibility, image uploads and inventory state.
                            <?php if (!empty($pagination['isProductList'])): ?>
                                Showing <?= number_format((int)($pagination['from'] ?? 0)) ?>-<?= number_format((int)($pagination['to'] ?? 0)) ?> of <?= number_format((int)($pagination['total'] ?? 0)) ?>.
                            <?php endif; ?>
                        </p>
                    </div>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/products?new=1')) ?>">Add Product</a>
                </div>
                <form class="admin-searchbar" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                    <input type="search" name="q" value="<?= e($pagination['search'] ?? '') ?>" placeholder="Search products by name, SKU, vendor or brand">
                    <input type="hidden" name="per_page" value="<?= e($productPerPage) ?>">
                    <button class="btn secondary" type="submit">Search</button>
                    <?php if (($pagination['search'] ?? '') !== ''): ?><a class="btn secondary" href="<?= e(app_url('dashboard/' . $view)) ?>">Clear</a><?php endif; ?>
                </form>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Product</th><th>Vendor</th><th>Status</th><th>Stock</th><th>Price</th><th>Sales</th><th>Rating</th><th>Updated</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($productRows as $product): ?>
                            <tr>
                                <td>
                                    <div class="product-identity">
                                        <span class="product-thumb">
                                            <?php if (($product['image_path'] ?? '') !== ''): ?><a href="<?= e(app_brand_asset_url($product['image_path'])) ?>" target="_blank" rel="noopener"><img src="<?= e(app_brand_asset_url($product['image_path'])) ?>" alt="<?= e($product['name']) ?>"></a><?php else: ?>P<?php endif; ?>
                                        </span>
                                        <span><strong><?= e($product['name']) ?></strong><small><?= e(($product['sku'] ?: 'No SKU') . ' | ' . ($product['category_names'] ?: 'Uncategorized')) ?></small></span>
                                    </div>
                                </td>
                                <td><?= e($product['store_name'] ?? 'No vendor') ?></td>
                                <td><span class="settings-badge <?= e($statusClass((string)$product['status'])) ?>"><?= e($product['status']) ?></span><?= !empty($product['featured']) ? '<small>Featured</small>' : '' ?></td>
                                <td><?= e(ucwords(str_replace('_', ' ', (string)$product['stock_status']))) ?><small><?= $product['manage_stock'] ? number_format((int)$product['stock_quantity']) . ' units' : 'Not managed' ?></small></td>
                                <td><?= e($product['currency']) ?> <?= number_format((float)($product['sale_price'] ?: $product['regular_price']), 2) ?></td>
                                <td><?= number_format((int)$product['total_sales']) ?></td>
                                <td><?= number_format((float)$product['average_rating'], 2) ?>/5</td>
                                <td><?= e($product['updated_at']) ?></td>
                                <td>
                                    <?php if ($view === 'pending-products'): ?>
                                        <div class="approval-actions product-approval-actions">
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                                <input type="hidden" name="action" value="update_product_status">
                                                <input type="hidden" name="return_view" value="<?= e($view) ?>">
                                                <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                                                <input type="hidden" name="status" value="active">
                                                <button class="btn primary compact-btn" type="submit">Approve</button>
                                            </form>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                                <input type="hidden" name="action" value="update_product_status">
                                                <input type="hidden" name="return_view" value="<?= e($view) ?>">
                                                <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                                                <input type="hidden" name="status" value="rejected">
                                                <button class="btn secondary compact-btn danger" type="submit">Reject</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                    <a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/products?edit=' . (int)$product['id'])) ?>">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($productRows === []): ?>
                            <tr><td colspan="9">No products found for this view.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($pagination['isProductList']) && (int)($pagination['total'] ?? 0) > 0): ?>
                    <div class="admin-pagination" aria-label="Product pagination">
                        <div class="pagination-summary">
                            Page <?= number_format($productPage) ?> of <?= number_format($productTotalPages) ?>
                        </div>
                        <div class="pagination-actions">
                            <a class="pagination-btn <?= $productPage <= 1 ? 'disabled' : '' ?>" href="<?= e($productListUrl(max(1, $productPage - 1))) ?>">Previous</a>
                            <?php
                            $startPage = max(1, $productPage - 2);
                            $endPage = min($productTotalPages, $productPage + 2);
                            if ($endPage - $startPage < 4) {
                                $startPage = max(1, min($startPage, $endPage - 4));
                                $endPage = min($productTotalPages, max($endPage, $startPage + 4));
                            }
                            ?>
                            <?php if ($startPage > 1): ?>
                                <a class="pagination-btn" href="<?= e($productListUrl(1)) ?>">1</a>
                                <?php if ($startPage > 2): ?><span class="pagination-gap">...</span><?php endif; ?>
                            <?php endif; ?>
                            <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
                                <a class="pagination-btn <?= $pageNumber === $productPage ? 'active' : '' ?>" href="<?= e($productListUrl($pageNumber)) ?>"><?= number_format($pageNumber) ?></a>
                            <?php endfor; ?>
                            <?php if ($endPage < $productTotalPages): ?>
                                <?php if ($endPage < $productTotalPages - 1): ?><span class="pagination-gap">...</span><?php endif; ?>
                                <a class="pagination-btn" href="<?= e($productListUrl($productTotalPages)) ?>"><?= number_format($productTotalPages) ?></a>
                            <?php endif; ?>
                            <a class="pagination-btn <?= $productPage >= $productTotalPages ? 'disabled' : '' ?>" href="<?= e($productListUrl(min($productTotalPages, $productPage + 1))) ?>">Next</a>
                        </div>
                        <form class="pagination-size" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                            <?php if (($pagination['search'] ?? '') !== ''): ?><input type="hidden" name="q" value="<?= e($pagination['search']) ?>"><?php endif; ?>
                            <label>
                                <span>Rows</span>
                                <select name="per_page" onchange="this.form.submit()">
                                    <?php foreach (($pagination['perPageOptions'] ?? [25, 50, 100]) as $option): ?>
                                        <option value="<?= e($option) ?>" <?= (int)$option === $productPerPage ? 'selected' : '' ?>><?= e($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </form>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</section>
