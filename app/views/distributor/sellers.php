<?php
$sellerLogos = [
    'costco'=>'Costco Wholesale', 'target'=>'Target', 'publix'=>'Publix',
    'asda'=>'Asda', 'sainsburys'=>"Sainsbury's", 'waitrose'=>'Waitrose',
    'kroger'=>'Kroger', 'aldi'=>'Aldi', 'tesco'=>'Tesco',
    'whole-foods-market'=>'Whole Foods Market', 'bjs-wholesale-club'=>"BJ's Wholesale Club",
    'morrisons'=>'Morrisons', 'walmart'=>'Walmart', 'sams-club'=>"Sam's Club", 'lidl'=>'Lidl',
];
?>
<link rel="stylesheet" href="<?= e(asset('css/distribution-sellers.css?v=1')) ?>">
<section class="dist-section dist-sellers" data-seller-slider aria-labelledby="dist-sellers-title">
  <div class="dist-container">
    <div class="dist-sellers-heading">
      <h2 id="dist-sellers-title">Retailers we <em>work with:</em></h2>
      <div class="dist-sellers-controls">
        <button type="button" data-sellers-prev aria-label="Previous sellers" title="Previous sellers" aria-controls="dist-sellers-track">&#8592;</button>
        <button type="button" data-sellers-pause aria-label="Pause slider" title="Pause slider" aria-controls="dist-sellers-track">&#10074;&#10074;</button>
        <button type="button" data-sellers-next aria-label="Next sellers" title="Next sellers" aria-controls="dist-sellers-track">&#8594;</button>
      </div>
    </div>
    <ul class="dist-sellers-track" id="dist-sellers-track" tabindex="0" aria-label="Seller logos">
      <?php foreach ($sellerLogos as $file=>$name): ?>
        <li><img src="<?= e(app_url('partners-logo/'.$file.'.jpeg')) ?>" alt="<?= e($name) ?>" width="180" height="120" loading="lazy" decoding="async"></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<script src="<?= e(asset('js/distribution-sellers.js?v=1')) ?>" defer></script>
