<?php
$statusClass = $statusClass ?? static fn (string $status): string => 'status-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $status) ?? 'unknown');
?>

<?php if ($view === 'product-comments'): ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Product Comments</h2>
                <p>Buyer questions and product discussion threads.</p>
            </div>
            <a class="btn secondary" href="<?= e(app_url('dashboard/products')) ?>">Products</a>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Product</th><th>Question</th><th>User</th><th>Status</th><th>Answers</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach (($productsData['comments'] ?? []) as $comment): ?>
                    <tr>
                        <td><strong><?= e($comment['product_name']) ?></strong></td>
                        <td class="product-long-text"><?= e($comment['question']) ?></td>
                        <td><?= e(($comment['display_name'] ?: $comment['email']) ?: 'Guest') ?></td>
                        <td><span class="settings-badge <?= e($statusClass((string)$comment['status'])) ?>"><?= e($comment['status']) ?></span></td>
                        <td><?= number_format((int)$comment['answer_count']) ?></td>
                        <td><?= e($comment['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (($productsData['comments'] ?? []) === []): ?>
                    <tr><td colspan="6">No product comments found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php elseif ($view === 'product-reviews'): ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Product Reviews</h2>
                <p>Review moderation, status and product quality signals.</p>
            </div>
            <a class="btn secondary" href="<?= e(app_url('dashboard/ranked-products')) ?>">Ranked Products</a>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Product</th><th>Review</th><th>User</th><th>Rating</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach (($productsData['reviews'] ?? []) as $review): ?>
                    <tr>
                        <td><strong><?= e($review['product_name']) ?></strong></td>
                        <td><strong><?= e($review['title'] ?: 'Review') ?></strong><small class="product-long-text"><?= e($review['body'] ?? '') ?></small></td>
                        <td><?= e(($review['display_name'] ?: $review['email']) ?: 'Guest') ?></td>
                        <td><?= number_format((int)$review['rating']) ?>/5</td>
                        <td><span class="settings-badge <?= e($statusClass((string)$review['status'])) ?>"><?= e($review['status']) ?></span></td>
                        <td><?= e($review['created_at']) ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Delete this review? This will update the product rating.');">
                                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                <input type="hidden" name="action" value="delete_product_review">
                                <input type="hidden" name="review_id" value="<?= e($review['id']) ?>">
                                <button class="btn danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (($productsData['reviews'] ?? []) === []): ?>
                    <tr><td colspan="7">No product reviews found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php elseif ($view === 'product-meta-tags'): ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Product Meta Tags</h2>
                <p>SEO-ready product identifiers, summaries, categories and visibility state.</p>
            </div>
            <a class="btn secondary" href="<?= e(app_url('dashboard/seo-settings')) ?>">SEO Settings</a>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Product</th><th>Slug</th><th>Meta Title</th><th>Meta Description</th><th>Categories</th><th>Visibility</th><th>Updated</th></tr></thead>
                <tbody>
                <?php foreach (($productsData['meta'] ?? []) as $meta): ?>
                    <?php
                    $description = trim((string)($meta['short_description'] ?: strip_tags((string)$meta['description'])));
                    $description = function_exists('mb_substr') ? mb_substr($description, 0, 156) : substr($description, 0, 156);
                    ?>
                    <tr>
                        <td><strong><?= e($meta['name']) ?></strong><small><?= e(($meta['sku'] ?: 'No SKU') . ' | ' . ($meta['brand_name'] ?: 'No brand')) ?></small></td>
                        <td><code><?= e($meta['slug']) ?></code></td>
                        <td><?= e($meta['name']) ?></td>
                        <td class="product-long-text"><?= e($description) ?></td>
                        <td><?= e($meta['category_names'] ?: '-') ?></td>
                        <td><?= e($meta['visibility'] . ' | ' . $meta['status']) ?></td>
                        <td><?= e($meta['updated_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (($productsData['meta'] ?? []) === []): ?>
                    <tr><td colspan="7">No product meta records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
