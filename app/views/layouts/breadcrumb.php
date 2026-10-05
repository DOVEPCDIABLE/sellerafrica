<?php
$breadcrumbs = $breadcrumbs ?? [];
?>
<?php if ($breadcrumbs !== []): ?>
    <nav class="admin-breadcrumb" aria-label="Breadcrumb">
        <?php foreach ($breadcrumbs as $index => $crumb): ?>
            <?php $isLast = $index === array_key_last($breadcrumbs); ?>
            <?php if (!$isLast && !empty($crumb['href'])): ?>
                <a href="<?= e(app_url((string)$crumb['href'])) ?>"><?= e($crumb['label'] ?? '') ?></a>
            <?php else: ?>
                <span><?= e($crumb['label'] ?? '') ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
