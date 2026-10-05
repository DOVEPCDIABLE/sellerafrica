<?php
$pageTitle = $pageTitle ?? $title ?? 'Dashboard';
$user = current_user();
$notificationCount = (int)($notificationCount ?? count($notifications ?? []));
$brand = app_branding();
?>
<header class="admin-topbar">
    <button class="mobile-sidebar-toggle" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
        <span></span>
    </button>
    <div class="topbar-title">
        <p><?= e($topbarEyebrow ?? (\App\AuthService::hasRole('super_admin') ? 'Super Admin Console' : 'Admin Console')) ?></p>
        <h1><?= e($pageTitle) ?></h1>
    </div>
    <form class="topbar-search" role="search" action="<?= e(app_url('dashboard.php')) ?>" method="get">
        <span></span>
        <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Search workspace...">
        <kbd>⌘ K</kbd>
    </form>
    <div class="topbar-actions">
        <button class="icon-pill" type="button" data-theme-toggle aria-label="Toggle dark mode">◐</button>
        <a class="icon-pill" href="<?= e(app_url(\App\AuthService::hasRole('super_admin') ? 'dashboard.php?view=audit-hub' : 'dashboard/in-app-notifications')) ?>" aria-label="Notification center">
            <?= $notificationCount > 0 ? e((string)min($notificationCount, 99)) : '○' ?>
        </a>
        <a class="workspace-pill" href="<?= e(app_url('dashboard.php')) ?>">
            <span><?= e($brand['name']) ?></span>
            <strong>ERP</strong>
        </a>
        <?php
        $profileInitial = strtoupper(substr((string)($user['display_name'] ?? 'A'), 0, 1));
        $profileAvatar = (string)($user['avatar_url'] ?? $user['avatar'] ?? '');
        ?>
        <a class="profile-pill" href="<?= e(app_url(\App\AuthService::hasRole('super_admin') ? 'dashboard.php?view=security-settings' : 'dashboard')) ?>">
            <span>
                <?php if ($profileAvatar !== '' && preg_match('/\\.(png|jpe?g|webp|gif|svg)(\\?.*)?$/i', $profileAvatar)): ?>
                    <img src="<?= e($profileAvatar) ?>" alt="<?= e($user['display_name'] ?? 'Admin') ?>">
                <?php else: ?>
                    <?= e($profileInitial) ?>
                <?php endif; ?>
            </span>
            <strong><?= e($user['display_name'] ?? 'Admin') ?></strong>
        </a>
    </div>
</header>
