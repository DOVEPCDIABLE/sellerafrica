<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?></title><meta name="description" content="<?= e($meta_description) ?>"><link rel="canonical" href="<?= e(app_url('priority')) ?>"><link rel="stylesheet" href="<?= e(asset('css/distributor.css?v=2')) ?>"><link rel="stylesheet" href="<?= e(asset('css/priority.css?v=1')) ?>"></head>
<body class="priority-body">
<?php $distributionHeader=true;$accountLoginPath='login';require __DIR__.'/../partials/public-account-header.php'; ?>
<?= $content ?>
<footer class="priority-footer"><strong>Seller Africa</strong><span>Discover. Distribute. Deliver.</span><nav><a href="<?= e(app_url('privacy-policy')) ?>">Privacy</a><a href="<?= e(app_url('terms')) ?>">Terms</a><a href="<?= e(app_url('contact')) ?>">Contact</a></nav></footer>
</body></html>
