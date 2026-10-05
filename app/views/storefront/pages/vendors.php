<?php
$brand = app_branding();
$brandName = (string)($brand['name'] ?? 'Seller Africa');
$cartCount = (int)($cartCount ?? 0);
$vendorDisplayTotal = (int)($allVendorTotal ?? 0);
if ($vendorDisplayTotal <= 0 && class_exists('\App\PublicVendorService')) {
    try {
        $vendorDisplayTotal = (new \App\PublicVendorService())->totalVendorCount();
    } catch (\Throwable) {
        $vendorDisplayTotal = (int)($vendorTotal ?? count($vendors ?? []));
    }
}
$renderLogo = static function () use ($brand, $brandName): void {
    if (!empty($brand['logo'])) {
        echo '<img src="' . e((string)$brand['logo']) . '" alt="' . e($brandName) . '">';
        return;
    }
    echo '<strong>' . e($brandName) . '</strong>';
};
$icon = static function (string $name): string {
    $paths = [
        'bag' => '<path d="M6 8h12l-1 11H7L6 8Z"/><path d="M9 8a3 3 0 0 1 6 0"/>',
        'arrow' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        'store' => '<path d="M4 10h16l-1-5H5l-1 5Z"/><path d="M6 10v9h12v-9"/><path d="M9 19v-5h6v5"/>',
        'box' => '<path d="m21 8-9-5-9 5 9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
        'star' => '<path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z"/>',
        'chat' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z"/>',
    ];
    return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ($paths[$name] ?? $paths['arrow']) . '</svg>';
};
$verifiedBadge = static function (array $item): string {
    if (($item['status'] ?? '') !== 'active' || ($item['kyc_status'] ?? '') !== 'approved') {
        return '';
    }
    return '<img class="sa-vendors-verified-badge" src="' . e(app_url('assets/images/iamavendor.jpeg')) . '" alt="Verified SAC vendor" title="Verified SAC vendor" width="56" height="56" loading="lazy">';
};
$vendorCard = static function (array $item) use ($icon, $verifiedBadge): void {
    $name = (string)($item['name'] ?? 'Seller Africa vendor');
    $chatUrl = app_url('chat?vendor=' . (int)($item['id'] ?? 0));
    $productCount = min(5, max(0, (int)($item['product_count'] ?? 0)));
    echo '<article class="sa-vendors-card">';
    echo '<a class="sa-vendors-card__banner' . (empty($item['has_banner']) ? ' is-placeholder' : '') . '" href="' . e((string)$item['url']) . '">';
    if (!empty($item['has_banner'])) {
        echo '<img src="' . e((string)$item['banner']) . '" alt="' . e($name) . '">';
    } else {
        echo '<span>' . $icon('store') . '</span>';
    }
    echo '</a>';
    echo '<div class="sa-vendors-card__body">';
    echo '<div class="sa-vendors-card__logo">';
    if (!empty($item['has_logo'])) {
        echo '<img src="' . e((string)$item['logo']) . '" alt="' . e($name) . ' logo">';
    } else {
        echo '<span>' . strtoupper(e(substr($name, 0, 1))) . '</span>';
    }
    echo '</div>';
    echo '<div><div class="sa-vendors-name-row"><h2><a href="' . e((string)$item['url']) . '">' . e($name) . '</a></h2>' . $verifiedBadge($item) . '</div>';
    echo '<p>' . e((string)(($item['description'] ?? '') ?: 'This vendor is preparing their public store story and product collection.')) . '</p>';
    echo '<div class="sa-vendors-meta"><span>' . $icon('box') . number_format($productCount) . ' products</span><span>' . $icon('star') . e((string)($item['average_rating'] ?? '0')) . ' rating</span></div>';
    echo '<div class="sa-vendors-card-actions"><a class="sa-vendors-link" href="' . e((string)$item['url']) . '">View vendor ' . $icon('arrow') . '</a><a class="sa-vendors-link" href="' . e($chatUrl) . '">' . $icon('chat') . ' Chat seller</a></div></div>';
    echo '</div></article>';
};
?>
<style>
  .sa-vendors {
    --font-unageo: "Unageo", "DM Sans", system-ui, -apple-system, sans-serif;
    --sa-sans: "DM Sans", system-ui, -apple-system, sans-serif;
    --green: #00684f;
    --mint: #e8fbf6;
    --ink: #2b3438;
    --muted: #667276;
    --line: #e6ecec;
    --cream: #fffaf2;
    --gutter: clamp(24px, 6vw, 110px);
    --max: 1536px;
    color: var(--ink);
    background: #fff;
    font-family: var(--sa-sans);
  }
  .sa-vendors * { box-sizing: border-box; }
  .sa-vendors a { color: inherit; text-decoration: none; }
  .sa-vendors svg { width: 20px; height: 20px; }
  .sa-vendors-announce { min-height: 36px; display: grid; place-items: center; background: var(--mint); color: #173d34; font-weight: 800; }
  .sa-vendors-header { background: #fff; border-bottom: 1px solid rgba(230,236,236,.62); }
  .sa-vendors-nav { width: min(var(--max), 100% - var(--gutter)); min-height: 92px; margin: 0 auto; display: grid; grid-template-columns: auto minmax(0,1fr) auto; align-items: center; gap: clamp(22px, 4vw, 58px); }
  .sa-vendors-logo img { width: auto; max-width: 174px; max-height: 62px; object-fit: contain; display: block; }
  .sa-vendors-links { display: flex; justify-content: center; align-items: center; gap: clamp(18px, 3vw, 46px); color: #5f6a6d; font: 800 16px/1 var(--font-unageo); }
  .sa-vendors-links a.is-active { color: var(--green); border-bottom: 2px solid var(--green); padding-bottom: 5px; }
  .sa-vendors-actions { display: flex; align-items: center; gap: 12px; }
  .sa-vendors-cart { width: 42px; height: 42px; display: grid; place-items: center; color: #596467; position: relative; }
  .sa-vendors-cart span { position: absolute; right: 0; top: 1px; min-width: 17px; height: 17px; border-radius: 999px; background: var(--green); color: #fff; display: grid; place-items: center; font-size: 10px; font-weight: 900; }
  .sa-vendors-btn { min-height: 46px; border: 1px solid var(--green); border-radius: 8px; padding: 0 18px; display: inline-flex; align-items: center; justify-content: center; background: var(--green); color: #fff !important; font: 850 15px/1 var(--font-unageo); }
  .sa-vendors-btn.is-ghost { background: #fff; color: var(--green) !important; }
  .sa-vendors-wrap { width: min(var(--max), 100% - var(--gutter)); margin: 0 auto; }
  .sa-vendors-hero { display: grid; grid-template-columns: minmax(0, .92fr) minmax(360px, 1fr); gap: clamp(32px, 6vw, 90px); align-items: center; padding: clamp(58px, 8vw, 118px) 0 clamp(44px, 6vw, 90px); }
  .sa-vendors-eyebrow { margin: 0 0 12px; color: var(--green); font: 900 13px/1 var(--font-unageo); text-transform: uppercase; letter-spacing: 0; }
  .sa-vendors h1, .sa-vendors h2, .sa-vendors h3 { font-family: var(--font-unageo); letter-spacing: 0; }
  .sa-vendors h1 { max-width: 800px; margin: 0; color: #111827; font-size: clamp(42px, 6vw, 76px); line-height: .98; font-weight: 900; }
  .sa-vendors-hero p { max-width: 690px; margin: 22px 0 0; color: var(--muted); font-size: 18px; line-height: 1.65; }
  .sa-vendors-hero__actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }
  .sa-vendors-hero__art { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 16px; }
  .sa-vendors-art-card { min-height: 220px; border-radius: 12px; overflow: hidden; background: var(--cream); box-shadow: 0 18px 48px rgba(17,24,39,.08); }
  .sa-vendors-art-card:first-child { grid-row: span 2; }
  .sa-vendors-art-card img { width: 100%; height: 100%; display: block; object-fit: cover; }
  .sa-vendors-art-card.is-stat { padding: 24px; display: flex; flex-direction: column; justify-content: flex-end; color: #fff; background: linear-gradient(135deg, #00684f, #0f8b6d); }
  .sa-vendors-art-card strong { display: block; font: 900 clamp(38px, 5vw, 62px)/1 var(--font-unageo); }
  .sa-vendors-section { padding: clamp(48px, 7vw, 86px) 0; }
  .sa-vendors-section.is-soft { background: #fffaf2; }
  .sa-vendors-section-head { display: flex; align-items: end; justify-content: space-between; gap: 24px; margin-bottom: 28px; }
  .sa-vendors-section-head h2 { margin: 0; color: #111827; font-size: clamp(30px, 4vw, 52px); line-height: 1.04; font-weight: 900; }
  .sa-vendors-section-head p { max-width: 610px; margin: 12px 0 0; color: var(--muted); line-height: 1.6; }
  .sa-vendors-grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 22px; }
  .sa-vendors-card { overflow: hidden; border: 1px solid var(--line); border-radius: 10px; background: #fff; box-shadow: 0 14px 38px rgba(17,24,39,.06); }
  .sa-vendors-card__banner { aspect-ratio: 16/7; display: grid; place-items: center; background: #eef6f2; color: var(--green); }
  .sa-vendors-card__banner img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .sa-vendors-card__banner.is-placeholder { background: radial-gradient(circle at 20% 18%, rgba(0,104,79,.14), transparent 28%), linear-gradient(135deg,#eef8f3,#fffaf2); }
  .sa-vendors-card__banner span { width: 58px; height: 58px; border: 1px solid rgba(0,104,79,.14); border-radius: 12px; background: #fff; display: grid; place-items: center; box-shadow: 0 14px 30px rgba(17,24,39,.08); }
  .sa-vendors-card__body { display: grid; grid-template-columns: 72px minmax(0,1fr); gap: 16px; padding: 18px; }
  .sa-vendors-card__logo img, .sa-vendors-card__logo span { width: 72px; height: 72px; border: 1px solid var(--line); border-radius: 10px; background: #fff; display: grid; place-items: center; object-fit: contain; padding: 8px; color: var(--green); font: 900 28px/1 var(--font-unageo); }
  .sa-vendors-card h2 { margin: 0; color: #111827; font-size: 21px; line-height: 1.2; font-weight: 900; }
  .sa-vendors-card p { min-height: 74px; margin: 9px 0 0; color: var(--muted); line-height: 1.55; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
  .sa-vendors-meta { display: flex; flex-wrap: wrap; gap: 8px; margin: 14px 0; }
  .sa-vendors-meta span,
  .sa-vendors-meta a { display: inline-flex; align-items: center; gap: 6px; min-height: 32px; border-radius: 999px; background: #f1f7f4; color: #29463e; padding: 0 10px; font-size: 13px; font-weight: 850; }
  .sa-vendors-meta a { text-decoration: none; cursor: pointer; }
  .sa-vendors-meta a:hover { background: #29463e; color: #fff; }
  .sa-vendors-meta svg { width: 15px; height: 15px; }
  .sa-vendors-link { display: inline-flex; align-items: center; gap: 8px; color: var(--green) !important; font: 900 14px/1 var(--font-unageo); }
  .sa-vendors-link svg { width: 16px; height: 16px; }
  .sa-vendors-card-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
  .sa-vendors-empty { padding: 34px; border: 1px dashed var(--line); border-radius: 10px; background: #fff; color: var(--muted); text-align: center; }
  .sa-vendors-pagination { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 10px; margin-top: 32px; }
  .sa-vendors-pagination a, .sa-vendors-pagination span { min-width: 42px; min-height: 42px; border: 1px solid var(--line); border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; padding: 0 13px; background: #fff; color: var(--green); font-weight: 900; }
  .sa-vendors-pagination .is-active { background: var(--green); border-color: var(--green); color: #fff; }
  .sa-vendors-pagination .is-disabled { color: var(--muted); background: #f6f7f7; }
  .sa-vendors-detail-hero { padding: clamp(58px, 8vw, 108px) 0; background-size: cover; background-position: center; }
  .sa-vendors-detail-hero.is-placeholder { background: linear-gradient(135deg,#eef8f3,#fffaf2); }
  .sa-vendors-detail { display: grid; grid-template-columns: 116px minmax(0,1fr); gap: 28px; align-items: center; }
  .sa-vendors-detail__logo img, .sa-vendors-detail__logo span { width: 116px; height: 116px; border-radius: 14px; border: 1px solid var(--line); background: #fff; display: grid; place-items: center; object-fit: contain; padding: 10px; color: var(--green); font: 900 42px/1 var(--font-unageo); }
  .sa-vendors-detail h1 { color: #fff; }
  .sa-vendors-detail-hero.is-placeholder h1 { color: #111827; }
  .sa-vendors-detail p { max-width: 780px; color: rgba(255,255,255,.86); font-size: 18px; line-height: 1.65; }
  .sa-vendors-detail-hero.is-placeholder p { color: var(--muted); }
  .sa-vendors-name-row { display: flex; align-items: center; gap: 12px; }
  .sa-vendors-name-row > h1, .sa-vendors-name-row > h2 { min-width: 0; overflow-wrap: anywhere; }
  .sa-vendors .sa-vendors-verified-badge { display: block; width: 56px; height: 56px; flex: 0 0 56px; border-radius: 50%; object-fit: cover; background: #fff; border: 1px solid var(--line); }
  .sa-vendors-profile { display: grid; grid-template-columns: minmax(0,.9fr) minmax(0,1.1fr); gap: 22px; }
  .sa-vendors-panel { border: 1px solid var(--line); border-radius: 10px; background: #fff; padding: 24px; box-shadow: 0 14px 38px rgba(17,24,39,.06); }
  .sa-vendors-panel h2 { margin: 0 0 14px; color: #111827; font-size: 26px; }
  .sa-vendors-products { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 18px; }
  @media (max-width: 1120px) { .sa-vendors-hero, .sa-vendors-profile { grid-template-columns: 1fr; } .sa-vendors-grid, .sa-vendors-products { grid-template-columns: repeat(2,minmax(0,1fr)); } .sa-vendors-links { justify-content: flex-start; overflow-x: auto; } .sa-vendors-nav { grid-template-columns: 1fr auto; } .sa-vendors-links { grid-column: 1/-1; } }
  @media (max-width: 680px) { .sa-vendors { --gutter: 24px; } .sa-vendors-announce { font-size: 12px; text-align: center; padding: 0 12px; } .sa-vendors-actions .sa-vendors-btn { display: none; } .sa-vendors-hero__art, .sa-vendors-grid, .sa-vendors-products { grid-template-columns: 1fr; } .sa-vendors-card__body, .sa-vendors-detail { grid-template-columns: 1fr; } .sa-vendors-section-head { flex-direction: column; align-items: flex-start; } }
</style>

<div class="sa-vendors">
  <div class="sa-vendors-announce"><strong>Free shipping on your first order from our U.S. warehouse</strong></div>
  <header class="sa-vendors-header">
    <nav class="sa-vendors-nav">
      <a class="sa-vendors-logo" href="<?= e(app_url('')) ?>"><?php $renderLogo(); ?></a>
      <div class="sa-vendors-links">
        <a href="<?= e(app_url('')) ?>">Home</a>
        <a href="<?= e(app_url('shop')) ?>">Marketplace</a>
        <a href="<?= e(app_url('shop?browse=1')) ?>">Shop</a>
        <a class="is-active" href="<?= e(app_url('vendors')) ?>">Our Vendors</a>
        <a href="<?= e(app_url('about')) ?>">About Us</a>
        <a href="<?= e(app_url('contact')) ?>">Contact Us</a>
      </div>
      <div class="sa-vendors-actions">
        <a class="sa-vendors-cart" href="<?= e(app_url('cart')) ?>" aria-label="Cart"><?= $icon('bag') ?><span><?= number_format($cartCount) ?></span></a>
        <?php if (!empty($_SESSION['user_id'])): ?>
        <a class="sa-vendors-btn is-ghost" href="<?= e(app_url('buyer')) ?>">My Account</a>
        <a class="sa-vendors-btn" href="<?= e(app_url('logout')) ?>">Sign Out</a>
        <?php else: ?>
        <a class="sa-vendors-btn is-ghost" href="<?= e(app_url('login')) ?>">Login</a>
        <a class="sa-vendors-btn" href="<?= e(app_url('buyer/register')) ?>">Register</a>
        <?php endif; ?>
      </div>
    </nav>
  </header>

  <?php if (!empty($vendor)): ?>
    <?php $heroStyle = !empty($vendor['has_banner']) ? "background-image: linear-gradient(90deg, rgba(7,45,35,.9), rgba(7,45,35,.62)), url('" . e((string)$vendor['banner']) . "')" : ''; ?>
    <?php $vendorPublicProductCount = min(5, max(0, (int)($vendor['product_count'] ?? 0))); ?>
    <section class="sa-vendors-detail-hero<?= empty($vendor['has_banner']) ? ' is-placeholder' : '' ?>"<?= $heroStyle !== '' ? ' style="' . $heroStyle . '"' : '' ?>>
      <div class="sa-vendors-wrap">
        <div class="sa-vendors-detail">
          <div class="sa-vendors-detail__logo">
            <?php if (!empty($vendor['has_logo'])): ?><img src="<?= e($vendor['logo']) ?>" alt="<?= e($vendor['name']) ?> logo"><?php else: ?><span><?= e(strtoupper(substr((string)$vendor['name'], 0, 1))) ?></span><?php endif; ?>
          </div>
          <div>
            <p class="sa-vendors-eyebrow"><?= $verifiedBadge($vendor) !== '' ? 'Verified Vendor' : 'Vendor' ?></p>
            <div class="sa-vendors-name-row"><h1><?= e($vendor['name']) ?></h1><?= $verifiedBadge($vendor) ?></div>
            <div class="sa-vendors-meta"><a href="<?= e(app_url('shop?q=' . rawurlencode($vendor['name']))) ?>"><?= $icon('box') ?> <?= number_format($vendorPublicProductCount) ?> products</a><span><?= $icon('star') ?> <?= e((string)$vendor['average_rating']) ?> rating</span><a href="<?= e(app_url('chat?vendor=' . (int)$vendor['id'])) ?>"><?= $icon('chat') ?> Chat with seller</a></div>
          </div>
        </div>
      </div>
    </section>
    <section class="sa-vendors-section">
      <div class="sa-vendors-wrap sa-vendors-profile">
        <article class="sa-vendors-panel"><h2>Vendor Details</h2><p><?= e($vendor['description'] ?: 'No vendor description has been added yet.') ?></p><div class="sa-vendors-meta"><span>Status: <?= e(ucwords(str_replace('_', ' ', $vendor['status']))) ?></span><span>Verification: <?= e(ucwords(str_replace('_', ' ', $vendor['kyc_status']))) ?></span></div></article>
        <article class="sa-vendors-panel"><h2>Store Performance</h2><div class="sa-vendors-meta"><a href="<?= e(app_url('shop?q=' . rawurlencode($vendor['name']))) ?>"><?= number_format($vendorPublicProductCount) ?> Products</a><span><?= e((string)$vendor['average_rating']) ?> Rating</span><span><?= number_format((int)$vendor['total_sales']) ?> Sales</span></div><div class="sa-vendors-hero__actions"><a class="sa-vendors-btn" href="<?= e(app_url('shop?q=' . rawurlencode($vendor['name']))) ?>">Shop This Vendor</a><a class="sa-vendors-btn is-ghost" href="<?= e(app_url('chat?vendor=' . (int)$vendor['id'])) ?>">Chat with Seller</a><a class="sa-vendors-btn is-ghost" href="<?= e(app_url('vendors/' . rawurlencode((string)$vendor['slug']) . '/review')) ?>">Review Vendor</a></div></article>
      </div>
    </section>
  <?php elseif (!empty($vendorNotFound)): ?>
    <section class="sa-vendors-wrap sa-vendors-hero"><div><p class="sa-vendors-eyebrow">Vendor Not Found</p><h1>This vendor profile is not available.</h1><p>The vendor may not be approved yet, or the public profile link may have changed.</p><div class="sa-vendors-hero__actions"><a class="sa-vendors-btn" href="<?= e(app_url('vendors')) ?>">View All Vendors</a><a class="sa-vendors-btn is-ghost" href="<?= e(app_url('shop')) ?>">Shop Products</a></div></div></section>
  <?php else: ?>
    <section class="sa-vendors-wrap sa-vendors-hero">
      <div>
        <p class="sa-vendors-eyebrow">Our Vendors</p>
        <h1>Built for the global African community.</h1>
        <p>Meet verified vendors bringing the African and Caribbean Marketplace to shoppers across the diaspora. Vendors with logos, banners, and complete store stories are featured first.</p>
        <div class="sa-vendors-hero__actions">
          <a class="sa-vendors-btn" href="<?= e(app_url('vendor/register')) ?>">Register as a Vendor <?= $icon('arrow') ?></a>
          <a class="sa-vendors-btn is-ghost" href="<?= e(app_url('shop')) ?>">Start Shopping</a>
        </div>
      </div>
      <div class="sa-vendors-hero__art" aria-hidden="true">
        <div class="sa-vendors-art-card"><img src="<?= e(app_url('assets/images/iamavendor.jpeg')) ?>" alt=""></div>
        <div class="sa-vendors-art-card is-stat"><strong><?= number_format($vendorDisplayTotal) ?></strong><span>total vendors</span></div>
      </div>
    </section>
    <section class="sa-vendors-section is-soft">
      <div class="sa-vendors-wrap">
        <div class="sa-vendors-section-head">
          <div><p class="sa-vendors-eyebrow">Vendor Directory</p><h2>Explore trusted stores.</h2><p>Shop from stores with real product catalogues, stronger visual profiles, and complete descriptions first.</p></div>
          <a class="sa-vendors-link" href="<?= e(app_url('vendor/register')) ?>">Open your store <?= $icon('arrow') ?></a>
        </div>
        <?php if (!empty($vendors)): ?>
          <div class="sa-vendors-grid">
            <?php foreach ($vendors as $item): ?><?php $vendorCard($item); ?><?php endforeach; ?>
          </div>
          <?php if ((int)($vendorTotalPages ?? 1) > 1): ?>
            <nav class="sa-vendors-pagination" aria-label="Vendor pages">
              <?php $currentPage = (int)($vendorPage ?? 1); $totalPages = (int)($vendorTotalPages ?? 1); ?>
              <?php if ($currentPage > 1): ?><a href="<?= e(app_url('vendors?page=' . ($currentPage - 1))) ?>">Previous</a><?php else: ?><span class="is-disabled">Previous</span><?php endif; ?>
              <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                <?php if ($pageNumber === 1 || $pageNumber === $totalPages || abs($pageNumber - $currentPage) <= 2): ?>
                  <a class="<?= $pageNumber === $currentPage ? 'is-active' : '' ?>" href="<?= e(app_url('vendors?page=' . $pageNumber)) ?>"><?= e((string)$pageNumber) ?></a>
                <?php elseif (abs($pageNumber - $currentPage) === 3): ?>
                  <span>...</span>
                <?php endif; ?>
              <?php endfor; ?>
              <?php if ($currentPage < $totalPages): ?><a href="<?= e(app_url('vendors?page=' . ($currentPage + 1))) ?>">Next</a><?php else: ?><span class="is-disabled">Next</span><?php endif; ?>
            </nav>
          <?php endif; ?>
        <?php else: ?>
          <div class="sa-vendors-empty"><h2>No active vendors yet</h2><p>Approved vendors will appear here as soon as their stores are ready.</p></div>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>
  <?php $footerClass = 'sa-vendors-footer'; $footerGridClass = 'sa-vendors-footer-grid'; include __DIR__ . '/../partials/standard-footer.php'; ?>
  <?= app_chat_widget_embed() ?>
</div>
