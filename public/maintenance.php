<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/core/bootstrap.php';

$brand = app_branding();
$status = class_exists('\App\MaintenanceService') ? \App\MaintenanceService::status() : [];
$message = (string)($status['message'] ?? 'We are making improvements and will be back shortly.');
$endsAt = (string)($status['ends_at'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($brand['name']) ?> Maintenance</title>
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f7f3e8;color:#17351f;font-family:Inter,Arial,sans-serif;padding:24px}
        main{width:min(680px,100%);background:#fff;border:1px solid #e4dcc8;border-radius:8px;padding:34px;box-shadow:0 18px 50px rgba(23,53,31,.12)}
        img{max-width:170px;height:auto;margin-bottom:22px}
        h1{font-size:clamp(30px,5vw,52px);line-height:1;margin:0 0 14px}
        p{font-size:18px;line-height:1.65;margin:0;color:#4b5c4d}
        .countdown{margin-top:22px;padding:14px 16px;background:#fff8df;border:1px solid #f1d17a;border-radius:8px;font-weight:800}
        a{display:inline-flex;margin-top:24px;color:#17351f;font-weight:800}
    </style>
</head>
<body>
<main>
    <img src="<?= e($brand['logo']) ?>" alt="<?= e($brand['name']) ?>">
    <h1>Maintenance in progress</h1>
    <p><?= e($message) ?></p>
    <?php if ($endsAt !== ''): ?>
        <div class="countdown" data-maintenance-ends="<?= e($endsAt) ?>">Scheduled return: <?= e($endsAt) ?></div>
    <?php endif; ?>
    <a href="<?= e(app_url('login')) ?>">Administrator login</a>
</main>
<script>
const box=document.querySelector('[data-maintenance-ends]');
if(box){const end=new Date(box.dataset.maintenanceEnds.replace(' ','T')).getTime();setInterval(()=>{const left=end-Date.now();if(left<=0){location.reload();return}const h=Math.floor(left/36e5),m=Math.floor(left%36e5/6e4),s=Math.floor(left%6e4/1e3);box.textContent=`Estimated return in ${h}h ${m}m ${s}s`;},1000)}
</script>
<?php app_render_chat_widget(); ?>
</body>
</html>
