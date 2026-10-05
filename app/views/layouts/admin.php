<?php
$title = $title ?? 'Dashboard';
$bodyClass = trim('admin-layout ' . (string)($bodyClass ?? ''));
$user = current_user();
$navigation = $navigation ?? require VIEW_PATH . '/partials/admin_navigation.php';
$active = $active ?? 'overview';
$pageTitle = $pageTitle ?? $title;
$breadcrumbs = $breadcrumbs ?? [
    ['label' => 'Dashboard', 'href' => 'dashboard.php'],
    ['label' => $pageTitle],
];
?>
<?php require VIEW_PATH . '/layouts/head.php'; ?>
<body class="<?= e($bodyClass) ?>" data-active-view="<?= e($active) ?>">
<?php require VIEW_PATH . '/layouts/loader.php'; ?>
<?php require VIEW_PATH . '/layouts/notifications.php'; ?>

<div class="admin-shell">
    <?php require VIEW_PATH . '/layouts/sidebar.php'; ?>

    <main class="admin-main">
        <?php require VIEW_PATH . '/layouts/navbar.php'; ?>
        <?php require VIEW_PATH . '/layouts/breadcrumb.php'; ?>
        <?php $maintenanceStatus = class_exists('\App\MaintenanceService') ? \App\MaintenanceService::status() : ['enabled' => false]; ?>
        <?php if (!empty($maintenanceStatus['enabled'])): ?>
            <section class="maintenance-admin-banner" role="status">
                <div>
                    <strong>System currently in Maintenance Mode</strong>
                    <span>Started <?= e((string)($maintenanceStatus['started_at'] ?? '')) ?><?= !empty($maintenanceStatus['ends_at']) ? ' | Ends ' . e((string)$maintenanceStatus['ends_at']) : '' ?></span>
                    <small><?= e((string)($maintenanceStatus['message'] ?? '')) ?></small>
                </div>
                <?php if (\App\AuthService::hasRole('super_admin')): ?>
                <form method="post" action="<?= e(app_url('dashboard/' . ($active ?? 'overview'))) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="disable_maintenance">
                    <button class="btn danger" type="submit">Disable Maintenance</button>
                </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <div class="content">
            <?= $content ?? '' ?>
        </div>

        <?php require VIEW_PATH . '/layouts/footer.php'; ?>
    </main>
</div>

<?php require VIEW_PATH . '/layouts/modals.php'; ?>
<?php require VIEW_PATH . '/layouts/theme-panel.php'; ?>
<?php require VIEW_PATH . '/layouts/scripts.php'; ?>
</body>
</html>
