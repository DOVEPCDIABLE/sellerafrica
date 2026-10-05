<?php
$pageTitle = $pageTitle ?? 'Dashboard';
$user = current_user();
?>
<header class="admin-topbar">
    <button class="mobile-sidebar-toggle" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
        <span></span>
    </button>
    <div class="topbar-title">
        <p>Super Admin Console</p>
        <h1><?= e($pageTitle) ?></h1>
    </div>
    <form class="topbar-search" role="search">
        <span></span>
        <input type="search" placeholder="Search workspace...">
        <kbd>⌘ K</kbd>
    </form>
    <div class="topbar-actions">
        <button class="icon-pill" type="button" aria-label="Toggle dark mode">◐</button>
        <a class="icon-pill" href="<?= e(app_url('dashboard.php?view=audit-hub')) ?>" aria-label="Notification center">○</a>
        <a class="workspace-pill" href="<?= e(app_url('dashboard.php')) ?>">
            <span>Seller Africa</span>
            <strong>ERP</strong>
        </a>
        <a class="profile-pill" href="<?= e(app_url('dashboard.php?view=security-settings')) ?>">
            <span><?= e(strtoupper(substr((string)($user['display_name'] ?? 'A'), 0, 1))) ?></span>
            <strong><?= e($user['display_name'] ?? 'Admin') ?></strong>
        </a>
    </div>
</header>
