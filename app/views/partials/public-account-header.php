<?php
$brand = app_branding();
$accountNav = [''=>'Home','marketplace'=>'Marketplace','shop?browse=1'=>'Shop','vendors'=>'Our Vendors','about'=>'About Us','contact'=>'Contact Us'];
?>
<link rel="stylesheet" href="<?= e(asset('css/account-navigation.css?v=2')) ?>">
<header class="dist-header account-header"><div class="dist-container dist-header-inner">
  <a class="dist-logo" href="<?= e(app_url('')) ?>"><img src="<?= e($brand['logo']) ?>" alt="Seller Africa"></a>
  <nav class="account-desktop-nav" aria-label="Main navigation"><?php foreach ($accountNav as $path=>$label): ?><a href="<?= e(app_url($path)) ?>"><?= e($label) ?></a><?php endforeach; ?></nav>
  <?php if (!empty($distributionHeader)): ?><a class="dist-signin" href="<?= e(app_url($accountLoginPath ?? (!empty($user)?'distributor':'login?as=distributor'))) ?>"><?= !empty($user)?'My application':'Sign in' ?></a><?php endif; ?>
  <?php if (!empty($distributionHeader)): ?><a class="account-register" href="<?= e(app_url('marketplace')) ?>">Our Marketplace</a><?php endif; ?>
  <button type="button" class="account-menu-toggle" aria-label="Open menu" title="Open menu" aria-controls="account-mobile-menu" aria-expanded="false"><span aria-hidden="true">&#9776;</span></button>
</div></header>
<dialog id="account-mobile-menu" class="account-mobile-menu" aria-labelledby="account-menu-title">
  <div class="account-menu-heading"><h2 id="account-menu-title">Menu</h2><button type="button" class="account-menu-close" aria-label="Close menu" title="Close menu" autofocus><span aria-hidden="true">&#215;</span></button></div>
  <nav aria-label="Mobile navigation"><?php foreach ($accountNav as $path=>$label): ?><a href="<?= e(app_url($path)) ?>"><?= e($label) ?><span aria-hidden="true">&#8594;</span></a><?php endforeach; ?></nav>
  <?php if (!empty($distributionHeader)): ?><a class="account-menu-signin" href="<?= e(app_url($accountLoginPath ?? (!empty($user)?'distributor':'login?as=distributor'))) ?>"><?= !empty($user)?'My application':'Sign in' ?></a><?php endif; ?>
  <?php if (!empty($distributionHeader)): ?><a class="account-menu-signin" href="<?= e(app_url('marketplace')) ?>">Our Marketplace</a><?php endif; ?>
</dialog>
<script src="<?= e(asset('js/account-navigation.js?v=1')) ?>" defer></script>
