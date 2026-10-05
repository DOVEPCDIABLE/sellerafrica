<?php
$brand = app_branding();
$title = $title ?? $brand['name'];
$meta = $meta ?? [];
$styles = $styles ?? [];
$adminCssPath = APP_ROOT . '/public/assets/css/admin.css';
$adminCssVersion = is_file($adminCssPath) ? (string)filemtime($adminCssPath) : '1';
$seoTitle = trim((string)$title . ' | ' . (string)$brand['name']);
$seoDescription = (string)($meta['description'] ?? $brand['name'] . ' marketplace ERP');
?>
<!doctype html>
<html lang="<?= e($meta['lang'] ?? 'en') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= \App\SeoService::tags($seoTitle, $seoDescription, is_array($meta) ? $meta : []) ?>
    <link rel="stylesheet" href="<?= e(asset('css/admin.css') . '?v=' . $adminCssVersion) ?>">
    <?php foreach ($styles as $style): ?>
        <link rel="stylesheet" href="<?= e((string)$style) ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?= e(asset('css/admin-controls.css') . '?v=' . (is_file(APP_ROOT.'/public/assets/css/admin-controls.css') ? filemtime(APP_ROOT.'/public/assets/css/admin-controls.css') : '1')) ?>">
</head>
