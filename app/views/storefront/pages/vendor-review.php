<?php
$brand = app_branding();
$brandName = (string)($brand['name'] ?? 'Seller Africa');
$cartCount = (int)($cartCount ?? 0);
$vendor = $vendor ?? null;
$products = $products ?? [];
$errors = $errors ?? [];
$form = $form ?? [];
$submitted = !empty($submitted);
$renderLogo = static function () use ($brand, $brandName): void {
    if (!empty($brand['logo'])) {
        echo '<img src="' . e((string)$brand['logo']) . '" alt="' . e($brandName) . '">';
        return;
    }
    echo '<strong>' . e($brandName) . '</strong>';
};
$selectedRating = max(1, min(5, (int)($form['rating'] ?? 5)));
?>
<style>
  .sa-review-page { --green:#00684f; --ink:#172126; --muted:#677377; --line:#e5ecea; --soft:#f3fbf7; --gold:#f5a400; --gutter:clamp(24px,6vw,96px); font-family:"DM Sans",system-ui,-apple-system,sans-serif; color:var(--ink); background:#fff; }
  .sa-review-page * { box-sizing:border-box; }
  .sa-review-page a { color:inherit; text-decoration:none; }
  .sa-review-wrap { width:min(1120px,100% - var(--gutter)); margin:0 auto; }
  .sa-review-header { border-bottom:1px solid rgba(229,236,234,.82); background:#fff; }
  .sa-review-nav { min-height:88px; display:flex; align-items:center; justify-content:space-between; gap:20px; }
  .sa-review-logo img { width:auto; max-width:174px; max-height:62px; object-fit:contain; display:block; }
  .sa-review-links { display:flex; flex-wrap:wrap; align-items:center; justify-content:flex-end; gap:12px; }
  .sa-review-link, .sa-review-btn { min-height:44px; border-radius:8px; padding:0 16px; display:inline-flex; align-items:center; justify-content:center; font-weight:850; }
  .sa-review-link { color:var(--green); }
  .sa-review-btn { border:1px solid var(--green); background:var(--green); color:#fff !important; }
  .sa-review-btn.is-ghost { background:#fff; color:var(--green) !important; }
  .sa-review-hero { padding:clamp(44px,7vw,86px) 0 28px; background:linear-gradient(180deg,var(--soft),#fff); }
  .sa-review-hero__grid { display:grid; grid-template-columns:minmax(0,.85fr) minmax(320px,1fr); gap:clamp(24px,5vw,64px); align-items:center; }
  .sa-review-vendor { display:grid; grid-template-columns:92px minmax(0,1fr); gap:18px; align-items:center; }
  .sa-review-logo-box img, .sa-review-logo-box span { width:92px; height:92px; border:1px solid var(--line); border-radius:10px; background:#fff; display:grid; place-items:center; object-fit:contain; padding:9px; color:var(--green); font-size:34px; font-weight:900; }
  .sa-review-eyebrow { margin:0 0 10px; color:var(--green); font-size:13px; font-weight:900; text-transform:uppercase; letter-spacing:0; }
  .sa-review-page h1 { margin:0; font-size:clamp(36px,6vw,68px); line-height:1; letter-spacing:0; font-weight:900; }
  .sa-review-page p { color:var(--muted); line-height:1.65; }
  .sa-review-card { border:1px solid var(--line); border-radius:10px; background:#fff; padding:24px; box-shadow:0 18px 50px rgba(17,24,39,.08); }
  .sa-review-card h2 { margin:0 0 18px; font-size:26px; letter-spacing:0; }
  .sa-review-form { display:grid; gap:16px; }
  .sa-review-form label { display:grid; gap:7px; color:#344146; font-weight:850; }
  .sa-review-form input, .sa-review-form select, .sa-review-form textarea { width:100%; border:1px solid var(--line); border-radius:8px; background:#fff; padding:13px 14px; color:var(--ink); font:inherit; }
  .sa-review-form textarea { min-height:138px; resize:vertical; }
  .sa-review-stars { display:flex; flex-direction:row-reverse; justify-content:flex-end; gap:8px; }
  .sa-review-stars input { position:absolute; opacity:0; pointer-events:none; }
  .sa-review-stars span { width:44px; height:44px; border:1px solid var(--line); border-radius:8px; display:grid; place-items:center; color:#a8b2b4; font-size:23px; cursor:pointer; }
  .sa-review-stars input:checked + span, .sa-review-stars label:has(input:checked) ~ label span { color:var(--gold); border-color:rgba(245,164,0,.45); background:#fff8e8; }
  .sa-review-alert { border:1px solid #b7ecd8; border-radius:8px; background:#ebfff7; color:#145f49; padding:14px 16px; font-weight:800; }
  .sa-review-alert.is-error { border-color:#fecaca; background:#fff1f1; color:#991b1b; }
  .sa-review-empty { padding:18px; border:1px dashed var(--line); border-radius:8px; color:var(--muted); background:#fff; }
  .sa-review-hidden { position:absolute; left:-10000px; width:1px; height:1px; overflow:hidden; }
  @media (max-width:760px) { .sa-review-nav { align-items:flex-start; flex-direction:column; padding:16px 0; } .sa-review-hero__grid, .sa-review-vendor { grid-template-columns:1fr; } .sa-review-links { justify-content:flex-start; } }
</style>

<div class="sa-review-page">
  <header class="sa-review-header">
    <nav class="sa-review-wrap sa-review-nav">
      <a class="sa-review-logo" href="<?= e(app_url('store')) ?>"><?php $renderLogo(); ?></a>
      <div class="sa-review-links">
        <a class="sa-review-link" href="<?= e(app_url('vendors')) ?>">Vendors</a>
        <a class="sa-review-link" href="<?= e(app_url('shop')) ?>">Marketplace</a>
        <a class="sa-review-btn is-ghost" href="<?= e(app_url('cart')) ?>">Cart <?= number_format($cartCount) ?></a>
      </div>
    </nav>
  </header>

  <main class="sa-review-hero">
    <div class="sa-review-wrap sa-review-hero__grid">
      <?php if (!$vendor): ?>
        <section>
          <p class="sa-review-eyebrow">Vendor Not Found</p>
          <h1>This review link is not available.</h1>
          <p>The store may not be active yet, or the vendor review link may have changed.</p>
          <a class="sa-review-btn" href="<?= e(app_url('vendors')) ?>">View Vendors</a>
        </section>
      <?php else: ?>
        <section>
          <div class="sa-review-vendor">
            <div class="sa-review-logo-box">
              <?php if (!empty($vendor['has_logo'])): ?><img src="<?= e($vendor['logo']) ?>" alt="<?= e($vendor['name']) ?> logo"><?php else: ?><span><?= e(strtoupper(substr((string)$vendor['name'], 0, 1))) ?></span><?php endif; ?>
            </div>
            <div>
              <p class="sa-review-eyebrow">Vendor Review</p>
              <h1><?= e($vendor['name']) ?></h1>
            </div>
          </div>
          <p>Your rating helps other customers choose trusted products from this store.</p>
          <a class="sa-review-link" href="<?= e((string)$vendor['url']) ?>">Back to vendor profile</a>
        </section>

        <section class="sa-review-card">
          <h2>Leave a review</h2>
          <?php if ($submitted): ?><div class="sa-review-alert">Thank you. Your review and star rating have been saved for this vendor.</div><?php endif; ?>
          <?php foreach ($errors as $error): ?><div class="sa-review-alert is-error"><?= e((string)$error) ?></div><?php endforeach; ?>
          <?php if ($products === []): ?>
            <div class="sa-review-empty">This vendor does not have active products available for reviews yet.</div>
          <?php else: ?>
            <form class="sa-review-form" method="post" action="<?= e(app_url('vendors/' . rawurlencode((string)$vendor['slug']) . '/review')) ?>">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
              <label class="sa-review-hidden">Website<input name="website" autocomplete="off"></label>
              <label><span>Product</span><select name="product_id" required>
                <?php foreach ($products as $product): ?><option value="<?= (int)$product['id'] ?>" <?= (int)($form['product_id'] ?? 0) === (int)$product['id'] ? 'selected' : '' ?>><?= e((string)$product['name']) ?></option><?php endforeach; ?>
              </select></label>
              <div>
                <span>Your rating</span>
                <div class="sa-review-stars" aria-label="Rating">
                  <?php for ($rating = 5; $rating >= 1; $rating--): ?><label><input type="radio" name="rating" value="<?= $rating ?>" <?= $selectedRating === $rating ? 'checked' : '' ?>><span>&#9733;</span></label><?php endfor; ?>
                </div>
              </div>
              <label><span>Review title</span><input name="title" maxlength="190" value="<?= e((string)($form['title'] ?? '')) ?>" placeholder="What stood out?"></label>
              <label><span>Your review</span><textarea name="body" required placeholder="Tell other customers about your experience."><?= e((string)($form['body'] ?? '')) ?></textarea></label>
              <button class="sa-review-btn" type="submit">Submit Review</button>
            </form>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </div>
  </main>
  <?php $footerClass = 'sa-review-footer'; $footerGridClass = 'sa-review-footer-grid'; include __DIR__ . '/../partials/standard-footer.php'; ?>
  <?= app_chat_widget_embed() ?>
</div>
