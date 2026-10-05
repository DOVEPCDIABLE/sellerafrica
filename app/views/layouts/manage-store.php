<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Manage My Store | Seller Africa') ?></title>
<meta name="description" content="<?= e($meta_description ?? '') ?>">
<link rel="canonical" href="<?= e(app_url(($selectedPlan ?? '') === 'setup' ? 'setup-store' : 'manage-store')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/distributor.css?v=2')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/manage-store.css?v=2')) ?>">
</head><body class="manage-body">
<?php $distributionHeader = true; $accountLoginPath = 'login'; require __DIR__.'/../partials/public-account-header.php'; ?>
<?= $content ?? '' ?>
<footer class="dist-footer dist-container"><span>Seller Africa · African and Caribbean Marketplace</span><nav><a href="<?= e(app_url('privacy-policy')) ?>">Privacy</a><a href="<?= e(app_url('terms')) ?>">Terms</a><a href="<?= e(app_url('contact')) ?>">Contact</a></nav></footer>
<?php if (function_exists('app_render_chat_widget')) app_render_chat_widget(); ?>
</body></html>
