<?php
$storefrontTemplate = $storefrontTemplate ?? new \App\StorefrontTemplateChunkService('index.html');
$brand = app_branding();
$title = $title ?? $brand['name'] . ' Storefront';
$metaDescription = $metaDescription ?? 'African and Caribbean Marketplace';
$meta = is_array($meta ?? null) ? $meta : [];
?>
<?= $storefrontTemplate->head((string)$title, (string)$metaDescription, $meta) ?>
<?php require VIEW_PATH . '/storefront/layouts/header.php'; ?>
<div id="toast-root" class="toast-root" data-toasts='<?= e(json_encode(consume_toasts(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>'></div>
<main>
    <?= $content ?? '' ?>
</main>
<?php require VIEW_PATH . '/storefront/layouts/footer.php'; ?>
<?php require VIEW_PATH . '/storefront/layouts/scripts.php'; ?>
</body>
</html>
