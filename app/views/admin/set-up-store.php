<?php
$search = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$where = $search === '' ? '' : ' WHERE (v.store_name LIKE ? OR u.email LIKE ? OR u.display_name LIKE ?)';
$params = $search === '' ? [] : array_fill(0, 3, '%' . $search . '%');
$total = (int)(db()->fetch('SELECT COUNT(*) AS total FROM vendors v JOIN users u ON u.id=v.user_id' . $where, $params)['total'] ?? 0);
$pages = max(1, (int)ceil($total / 25));
$page = min($page, $pages);
$stores = db()->fetchAll('SELECT v.id, v.store_name, v.status, u.display_name, u.email FROM vendors v JOIN users u ON u.id=v.user_id' . $where . ' ORDER BY v.store_name, v.id LIMIT 25 OFFSET ' . (($page - 1) * 25), $params);
?>
<section class="panel">
    <div class="panel-header"><h2>Set Up a Store</h2></div>
    <form method="get" action="<?= e(app_url('dashboard/set-up-store')) ?>" class="settings-form">
        <label class="settings-field"><span>Find a vendor</span><input type="search" name="q" value="<?= e($search) ?>" placeholder="Store name, owner or email"></label>
        <button class="btn primary" type="submit">Search</button>
    </form>
    <p><?= number_format($total) ?> vendors</p>
    <div class="settings-form">
        <?php foreach ($stores as $store): ?>
            <article style="min-width:0;overflow-wrap:anywhere;border-bottom:1px solid #e5e7eb;padding:20px 0">
                <div class="panel-header"><div><h3><?= e($store['store_name']) ?></h3><p><?= e($store['display_name']) ?> · <?= e($store['email']) ?></p><span><?= e(ucfirst($store['status'])) ?></span></div></div>
                <div class="vendor-review-actions">
                    <a class="btn primary" href="<?= e(app_url('dashboard/vendor-edit?vendor=' . (int)$store['id'])) ?>">Set Up Store</a>
                    <a class="btn secondary" href="<?= e(app_url('dashboard/vendor-edit?vendor=' . (int)$store['id'] . '#store-products')) ?>">Add Product</a>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$stores): ?><p>No vendors found.</p><?php endif; ?>
    </div>
    <nav class="vendor-review-actions" aria-label="Store results pages">
        <?php if ($page > 1): ?><a class="btn secondary" href="<?= e(app_url('dashboard/set-up-store?' . http_build_query(['q' => $search, 'page' => $page - 1]))) ?>">Previous</a><?php endif; ?>
        <span>Page <?= $page ?> of <?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="btn secondary" href="<?= e(app_url('dashboard/set-up-store?' . http_build_query(['q' => $search, 'page' => $page + 1]))) ?>">Next</a><?php endif; ?>
    </nav>
</section>
