<?php
$taxonomyData = $taxonomyData ?? [];
$taxonomyLinks = [
    'categories' => 'Categories',
    'subcategories' => 'Subcategories',
    'brands' => 'Brands',
    'attributes' => 'Attributes',
    'variations' => 'Variations',
];
$rows = $taxonomyData['rows'] ?? [];
$stats = $taxonomyData['stats'] ?? [];
$pagination = $taxonomyData['pagination'] ?? [];
$edit = $taxonomyData['edit'] ?? null;
$showForm = (bool)($taxonomyData['showForm'] ?? false);
$editValues = $taxonomyData['editValues'] ?? [];
$value = static fn (string $key, mixed $default = ''): string => e($edit[$key] ?? $default);
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
$searchPlaceholder = match ($view) {
    'brands' => 'Search brands by name, slug or description',
    'attributes' => 'Search attributes by name, slug or type',
    'variations' => 'Search variations by product, SKU, parent or vendor',
    'subcategories' => 'Search subcategories by name, slug, parent or description',
    default => 'Search categories by name, slug or description',
};
$taxonomyType = match ($view) {
    'subcategories' => 'subcategory',
    'brands' => 'brand',
    'attributes' => 'attribute',
    default => 'category',
};
$addLabel = match ($view) {
    'subcategories' => 'Add Subcategory',
    'brands' => 'Add Brand',
    'attributes' => 'Add Attribute',
    'variations' => 'Add Variation',
    default => 'Add Category',
};
$singularLabel = match ($view) {
    'subcategories' => 'Subcategory',
    'brands' => 'Brand',
    'attributes' => 'Attribute',
    'variations' => 'Variation',
    default => 'Category',
};
?>

<section class="settings-shell taxonomy-shell">
    <aside class="settings-index panel">
        <div class="panel-header">
            <div>
                <h2>Categories & Brands</h2>
                <p>Catalog taxonomy, brands, attributes and variation intelligence</p>
            </div>
        </div>
        <nav class="settings-nav" aria-label="Categories and brands pages">
            <?php foreach ($taxonomyLinks as $key => $label): ?>
                <a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(app_url('dashboard/' . $key)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="settings-content">
        <section class="metric-grid settings-metrics" aria-label="Taxonomy metrics">
            <div class="metric"><span>Categories</span><strong><?= number_format((int)($stats['total_categories'] ?? 0)) ?></strong><small><?= number_format((int)($stats['active_categories'] ?? 0)) ?> active</small></div>
            <div class="metric"><span>Subcategories</span><strong><?= number_format((int)($stats['subcategories'] ?? 0)) ?></strong><small>Nested taxonomy</small></div>
            <div class="metric"><span>Brands</span><strong><?= number_format((int)($stats['brands'] ?? 0)) ?></strong><small>Brand records</small></div>
            <div class="metric"><span>Attributes</span><strong><?= number_format((int)($stats['attributes'] ?? 0)) ?></strong><small><?= number_format((int)($stats['attribute_values'] ?? 0)) ?> values</small></div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2><?= e($taxonomyLinks[$view] ?? 'Categories') ?></h2>
                    <p>
                        Manage catalog structure and merchandising data.
                        Showing <?= number_format((int)($pagination['from'] ?? 0)) ?>-<?= number_format((int)($pagination['to'] ?? 0)) ?> of <?= number_format((int)($pagination['total'] ?? 0)) ?>.
                    </p>
                </div>
                <?php if ($view === 'variations'): ?>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/products?new=1')) ?>">Add Variation</a>
                <?php elseif ($view === 'attributes'): ?>
                    <span class="settings-badge"><?= number_format((int)($stats['attribute_values'] ?? 0)) ?> values</span>
                <?php else: ?>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/' . $view . '?new=1')) ?>"><?= e($addLabel) ?></a>
                <?php endif; ?>
            </div>

            <?php if ($view === 'attributes' && !$showForm): ?>
                <div class="taxonomy-action-row">
                    <a class="btn secondary" href="<?= e(app_url('dashboard/attributes?new=1')) ?>">Add Attribute</a>
                    <span class="settings-badge"><?= number_format((int)($pagination['total'] ?? 0)) ?> records</span>
                </div>
            <?php endif; ?>

            <?php if ($showForm && $view !== 'variations'): ?>
                <form class="settings-form taxonomy-form" method="post" action="<?= e(app_url('dashboard/' . $view)) ?>" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="save_taxonomy">
                    <input type="hidden" name="taxonomy_type" value="<?= e($taxonomyType) ?>">
                    <input type="hidden" name="record_id" value="<?= e($edit['id'] ?? 0) ?>">
                    <div class="panel-header">
                        <div>
                            <h2><?= $edit ? 'Edit ' . e($singularLabel) : e($addLabel) ?></h2>
                            <p>Changes are saved directly into the marketplace catalog taxonomy tables and audit logged.</p>
                        </div>
                        <button class="btn" type="submit">Save</button>
                    </div>
                    <div class="settings-form-grid">
                        <label class="settings-field"><span>Name</span><input name="name" required value="<?= $value('name') ?>"></label>
                        <label class="settings-field"><span>Slug</span><input name="slug" value="<?= $value('slug') ?>" placeholder="Auto generated if blank"></label>
                        <?php if ($view === 'subcategories'): ?>
                            <label class="settings-field"><span>Parent Category</span><select name="parent_id" required>
                                <option value="">Select parent</option>
                                <?php foreach (($taxonomyData['parentCategories'] ?? []) as $parent): ?>
                                    <option value="<?= e($parent['id']) ?>" <?= (int)($edit['parent_id'] ?? 0) === (int)$parent['id'] ? 'selected' : '' ?>><?= e($parent['name']) ?></option>
                                <?php endforeach; ?>
                            </select></label>
                        <?php elseif ($view === 'categories'): ?>
                            <label class="settings-field"><span>Status</span><select name="is_active">
                                <option value="1" <?= (int)($edit['is_active'] ?? 1) === 1 ? 'selected' : '' ?>>Active</option>
                                <option value="0" <?= (int)($edit['is_active'] ?? 1) === 0 ? 'selected' : '' ?>>Inactive</option>
                            </select></label>
                        <?php elseif ($view === 'attributes'): ?>
                            <label class="settings-field"><span>Type</span><select name="type">
                                <?php foreach (['select', 'text', 'color', 'image', 'button'] as $type): ?>
                                    <option value="<?= e($type) ?>" <?= ($edit['type'] ?? 'select') === $type ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option>
                                <?php endforeach; ?>
                            </select></label>
                        <?php endif; ?>

                        <?php if (in_array($view, ['categories', 'subcategories'], true)): ?>
                            <?php if ($view === 'subcategories'): ?>
                                <label class="settings-field"><span>Status</span><select name="is_active">
                                    <option value="1" <?= (int)($edit['is_active'] ?? 1) === 1 ? 'selected' : '' ?>>Active</option>
                                    <option value="0" <?= (int)($edit['is_active'] ?? 1) === 0 ? 'selected' : '' ?>>Inactive</option>
                                </select></label>
                            <?php endif; ?>
                            <label class="settings-field"><span>Sort Order</span><input type="number" name="sort_order" value="<?= $value('sort_order', '0') ?>"></label>
                            <label class="settings-field"><span>Image</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
                            <label class="settings-field span-2"><span>Description</span><textarea name="description" rows="4"><?= $value('description') ?></textarea></label>
                        <?php elseif ($view === 'brands'): ?>
                            <label class="settings-field"><span>Logo</span><input type="file" name="logo" accept="image/jpeg,image/png,image/webp"></label>
                            <label class="settings-field span-2"><span>Description</span><textarea name="description" rows="4"><?= $value('description') ?></textarea></label>
                        <?php elseif ($view === 'attributes'): ?>
                            <label class="settings-field product-check"><span>Scope</span><span><input type="checkbox" name="is_global" <?= (int)($edit['is_global'] ?? 1) === 1 ? 'checked' : '' ?>> Global attribute</span></label>
                            <label class="settings-field span-2"><span>Values</span><textarea name="values" rows="4" placeholder="One value per line or comma separated"><?= e(implode("\n", array_map(static fn (array $row): string => (string)$row['value'], $editValues))) ?></textarea></label>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>

            <form class="admin-searchbar" method="get" action="<?= e(app_url('dashboard/' . $view)) ?>">
                <input type="search" name="q" value="<?= e($pagination['search'] ?? '') ?>" placeholder="<?= e($searchPlaceholder) ?>">
                <input type="hidden" name="per_page" value="<?= e($perPage) ?>">
                <button class="btn secondary" type="submit">Search</button>
                <?php if (($pagination['search'] ?? '') !== ''): ?><a class="btn secondary" href="<?= e(app_url('dashboard/' . $view)) ?>">Clear</a><?php endif; ?>
            </form>

            <div class="admin-table-wrap">
                <?php if (in_array($view, ['categories', 'subcategories'], true)): ?>
                    <table class="admin-table">
                        <thead><tr><th>Category</th><th>Parent</th><th>Status</th><th>Products</th><th>Children</th><th>Sort</th><th>Updated</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $category): ?>
                            <tr>
                                <td>
                                    <div class="product-identity">
                                        <span class="product-thumb">
                                            <?php if (($category['image_path'] ?? '') !== ''): ?><img src="<?= e(app_brand_asset_url($category['image_path'])) ?>" alt="<?= e($category['name']) ?>"><?php else: ?>C<?php endif; ?>
                                        </span>
                                        <span><strong><?= e($category['name']) ?></strong><small><?= e($category['slug']) ?></small></span>
                                    </div>
                                </td>
                                <td><?= e($category['parent_name'] ?: 'Top level') ?></td>
                                <td><span class="settings-badge <?= (int)$category['is_active'] === 1 ? 'status-active' : 'status-failed' ?>"><?= (int)$category['is_active'] === 1 ? 'active' : 'inactive' ?></span></td>
                                <td><?= number_format((int)$category['product_count']) ?></td>
                                <td><?= number_format((int)$category['child_count']) ?></td>
                                <td><?= number_format((int)$category['sort_order']) ?></td>
                                <td><?= e($category['updated_at']) ?></td>
                                <td><a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/' . $view . '?edit=' . (int)$category['id'])) ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows === []): ?><tr><td colspan="8">No categories found for this view.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                <?php elseif ($view === 'brands'): ?>
                    <table class="admin-table">
                        <thead><tr><th>Brand</th><th>Slug</th><th>Products</th><th>Active Products</th><th>Description</th><th>Updated</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $brand): ?>
                            <tr>
                                <td>
                                    <div class="product-identity">
                                        <span class="product-thumb">
                                            <?php if (($brand['logo_path'] ?? '') !== ''): ?><img src="<?= e(app_brand_asset_url($brand['logo_path'])) ?>" alt="<?= e($brand['name']) ?>"><?php else: ?>B<?php endif; ?>
                                        </span>
                                        <span><strong><?= e($brand['name']) ?></strong><small>WP #<?= e($brand['wp_term_id'] ?: '-') ?></small></span>
                                    </div>
                                </td>
                                <td><code><?= e($brand['slug']) ?></code></td>
                                <td><?= number_format((int)$brand['product_count']) ?></td>
                                <td><?= number_format((int)$brand['active_product_count']) ?></td>
                                <td class="product-long-text"><?= e($brand['description'] ?: '-') ?></td>
                                <td><?= e($brand['updated_at'] ?? $brand['created_at']) ?></td>
                                <td><a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/brands?edit=' . (int)$brand['id'])) ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows === []): ?><tr><td colspan="7">No brands found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                <?php elseif ($view === 'attributes'): ?>
                    <table class="admin-table">
                        <thead><tr><th>Attribute</th><th>Type</th><th>Scope</th><th>Values</th><th>Sample Values</th><th>Created</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $attribute): ?>
                            <tr>
                                <td><strong><?= e($attribute['name']) ?></strong><small><?= e($attribute['slug']) ?></small></td>
                                <td><span class="settings-badge"><?= e($attribute['type']) ?></span></td>
                                <td><?= (int)$attribute['is_global'] === 1 ? 'Global' : 'Local' ?></td>
                                <td><?= number_format((int)$attribute['value_count']) ?></td>
                                <td class="product-long-text"><?= e($attribute['sample_values'] ?: '-') ?></td>
                                <td><?= e($attribute['created_at']) ?></td>
                                <td><a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/attributes?edit=' . (int)$attribute['id'])) ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows === []): ?><tr><td colspan="7">No attributes found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                <?php elseif ($view === 'variations'): ?>
                    <table class="admin-table">
                        <thead><tr><th>Variation</th><th>Parent Product</th><th>Vendor</th><th>Status</th><th>Stock</th><th>Price</th><th>Updated</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $variation): ?>
                            <tr>
                                <td>
                                    <div class="product-identity">
                                        <span class="product-thumb">
                                            <?php if (($variation['image_path'] ?? '') !== ''): ?><img src="<?= e(app_brand_asset_url($variation['image_path'])) ?>" alt="<?= e($variation['name']) ?>"><?php else: ?>V<?php endif; ?>
                                        </span>
                                        <span><strong><?= e($variation['name']) ?></strong><small><?= e($variation['sku'] ?: 'No SKU') ?></small></span>
                                    </div>
                                </td>
                                <td><?= e($variation['parent_name'] ?: 'No parent') ?></td>
                                <td><?= e($variation['store_name'] ?: 'No vendor') ?></td>
                                <td><span class="settings-badge <?= e($statusClass((string)$variation['status'])) ?>"><?= e($variation['status']) ?></span></td>
                                <td><?= e(ucwords(str_replace('_', ' ', (string)$variation['stock_status']))) ?><small><?= $variation['stock_quantity'] === null ? 'Not managed' : number_format((int)$variation['stock_quantity']) . ' units' ?></small></td>
                                <td><?= e($money($variation['sale_price'] ?: $variation['regular_price'], $variation['currency'])) ?></td>
                                <td><?= e($variation['updated_at']) ?></td>
                                <td><a class="btn secondary compact-btn" href="<?= e(app_url('dashboard/products?edit=' . (int)$variation['id'])) ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows === []): ?><tr><td colspan="8">No product variations found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php if ((int)($pagination['total'] ?? 0) > 0): ?>
                <div class="admin-pagination">
                    <div class="pagination-summary">Page <?= number_format($page) ?> of <?= number_format($totalPages) ?></div>
                    <div class="pagination-actions">
                        <a class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= e($listUrl(max(1, $page - 1))) ?>">Previous</a>
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        ?>
                        <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
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
