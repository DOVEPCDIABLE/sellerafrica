<?php
$navigation = $navigation ?? require VIEW_PATH . '/partials/admin_navigation.php';
$active = $active ?? 'overview';
$user = current_user();
$brand = app_branding();
?>
<aside class="admin-sidebar" id="admin-sidebar">
    <div class="brand">
        <div class="brand-mark">
            <img src="<?= e($brand['logo']) ?>" alt="<?= e($brand['name']) ?>">
        </div>
        <div class="brand-copy">
            <strong><?= e($brand['name']) ?></strong>
            <span>Marketplace ERP</span>
        </div>
        <button class="sidebar-collapse" type="button" data-sidebar-toggle aria-label="Collapse sidebar">
            <span></span>
        </button>
    </div>

    <a class="workspace-selector" href="<?= e(app_url('dashboard.php')) ?>">
        <span>Workspace</span>
        <strong><?= e($brand['name']) ?> Store</strong>
    </a>

    <div class="operator">
        <?php
        $avatar = strtoupper(substr((string)($user['display_name'] ?? 'A'), 0, 1));
        $avatarUrl = (string)($user['avatar_url'] ?? $user['avatar'] ?? '');
        ?>
        <div class="operator-avatar">
            <?php if ($avatarUrl !== '' && preg_match('/\\.(png|jpe?g|webp|gif|svg)(\\?.*)?$/i', $avatarUrl)): ?>
                <img src="<?= e($avatarUrl) ?>" alt="<?= e($user['display_name'] ?? 'Admin') ?>">
            <?php else: ?>
                <?= e($avatar) ?>
            <?php endif; ?>
        </div>
        <div class="operator-copy">
            <strong><?= e($user['display_name'] ?? 'Admin') ?></strong>
            <span><?= e($user['email'] ?? 'super admin') ?></span>
        </div>
    </div>

    <nav class="admin-nav" aria-label="Super admin navigation">
        <?php foreach ($navigation as $section => $items): ?>
            <?php
            $sectionKey = strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string)$section));
            $isOpen = in_array($active, array_column($items, 'key'), true) || $section === 'Dashboard';
            ?>
            <section class="nav-section <?= $isOpen ? 'open' : '' ?>" data-nav-section>
                <button class="nav-section-toggle" type="button" data-section-toggle aria-expanded="<?= $isOpen ? 'true' : 'false' ?>">
                    <span class="nav-section-initial"><?= e(substr((string)$section, 0, 1)) ?></span>
                    <span class="nav-section-title"><?= e($section) ?></span>
                    <span class="nav-section-chevron"></span>
                </button>
                <div class="nav-section-items" id="nav-section-<?= e($sectionKey) ?>">
                    <?php foreach ($items as $item): ?>
                        <a class="<?= $active === $item['key'] ? 'active' : '' ?>" href="<?= e(app_url($item['href'])) ?>">
                            <span><?= e($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </nav>
</aside>
