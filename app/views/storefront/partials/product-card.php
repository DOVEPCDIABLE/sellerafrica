<?php
$product = $product ?? [];
$variant = $variant ?? 'feature';
$productUrl = (string)($product['url'] ?? '#');
$image = trim((string)($product['image'] ?? $product['fallbackImage'] ?? ''));
$name = (string)($product['name'] ?? 'Seller Africa product');
$vendor = (string)($product['vendor'] ?? 'Seller Africa');
$price = (string)($product['price'] ?? '$0.00');
$regularPrice = (string)($product['regularPrice'] ?? '');
$rawPrice = (float)($product['rawPrice'] ?? 0);
$regularRawPrice = isset($product['regularRawPrice']) ? (float)$product['regularRawPrice'] : null;
$currency = (string)($product['currency'] ?? 'USD');
$badge = (string)($product['badge'] ?? 'NEW');
$productId = (string)($product['id'] ?? '');
?>
<article class="mtfeature__product-item p-relative fix" data-sa-live="product" data-sa-server-card="1" data-product-variant="<?= e($variant) ?>">
    <div class="mtfeature__product-shape">
        <img loading="lazy" src="<?= e(app_url('storefront/assets/img/product/feature-shape-1.png')) ?>" alt="">
    </div>
    <div class="mtfeature__product-offer">
        <span><?= e($badge) ?><br></span>
    </div>
    <div class="mtfeature__product-img mb-15">
        <a href="<?= e($productUrl) ?>">
            <?php if ($image !== ''): ?>
                <img loading="lazy" src="<?= e($image) ?>" alt="<?= e($name) ?>">
            <?php else: ?>
                <span class="sa-product-image-placeholder"><?= e($name) ?></span>
            <?php endif; ?>
        </a>
    </div>
    <div class="mtfeature__product-content mb-15">
        <span><?= e($vendor) ?></span>
        <h5 class="mtfeature__product-title">
            <a href="<?= e($productUrl) ?>"><?= e($name) ?></a>
        </h5>
    </div>
    <div class="mtfeature__product-rating mb-15">
        <?php for ($i = 0; $i < 5; $i++): ?>
            <a href="#"><i class="fa-solid fa-star-sharp"></i></a>
        <?php endfor; ?>
    </div>
    <div class="mtfeature__product-price mb-15">
        <?php if ($regularPrice !== ''): ?>
            <del data-sa-price="<?= e((string)($regularRawPrice ?? 0)) ?>" data-sa-currency="<?= e($currency) ?>"><?= e($regularPrice) ?></del>
        <?php endif; ?>
        <span data-sa-price="<?= e((string)$rawPrice) ?>" data-sa-currency="<?= e($currency) ?>"><?= e($price) ?></span>
    </div>
    <div class="mtfeature__product-btn">
        <a href="#" class="mt-btn-3" data-add-cart="<?= e($productId) ?>">Add To Cart</a>
    </div>
</article>
