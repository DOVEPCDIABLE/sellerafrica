<?php
$brand = app_branding();
$brandName = trim((string)($brand['name'] ?? 'Seller Africa')) ?: 'Seller Africa';
$logo = trim((string)($brand['logo'] ?? ''));
$vendor = is_array($vendor ?? null) ? $vendor : null;
$activeBoost = is_array($activeBoost ?? null) ? $activeBoost : null;
$cartCount = (int)($cartCount ?? 0);
$isBoostActive = $activeBoost && in_array((string)($activeBoost['status'] ?? ''), ['active', 'trialing', 'past_due', 'unpaid', 'incomplete'], true);
?>
<style>
  .sa-boost{--green:#006b52;--lime:#b6f06a;--ink:#111815;--muted:#65736c;--line:#e2ebe6;--cream:#fff8e8;--soft:#f5fbf7;--orange:#ff7a1a;--shadow:0 30px 90px rgba(17,24,21,.16);--gutter:clamp(20px,6vw,96px);font-family:"DM Sans",system-ui,-apple-system,sans-serif;color:var(--ink);background:#fff;overflow:hidden}.sa-boost *{box-sizing:border-box}.sa-boost a{text-decoration:none;color:inherit}.sa-boost-wrap{width:min(1280px,100% - var(--gutter));margin:0 auto}.sa-boost-nav{min-height:86px;display:flex;align-items:center;justify-content:space-between;gap:18px}.sa-boost-logo{display:flex;align-items:center;gap:10px;font-weight:950}.sa-boost-logo img{max-width:174px;max-height:58px}.sa-boost-nav__links{display:flex;gap:24px;color:var(--muted);font-weight:850}.sa-boost-cart{min-width:44px;height:44px;border:1px solid var(--line);border-radius:999px;display:grid;place-items:center;background:#fff;font-weight:950;color:var(--green)}
  .sa-boost-hero{position:relative;padding:clamp(42px,7vw,96px) 0;background:radial-gradient(circle at 78% 20%,#dff6e6 0,#fff 34%,#fff8e8 100%)}.sa-boost-hero__grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(310px,430px);gap:clamp(28px,6vw,76px);align-items:center}.sa-boost-kicker{margin:0 0 12px;color:var(--orange);font-size:13px;font-weight:950;text-transform:uppercase;letter-spacing:.08em}.sa-boost h1,.sa-boost h2,.sa-boost h3{letter-spacing:0}.sa-boost h1{margin:0;font-size:clamp(42px,7vw,84px);line-height:.94;max-width:880px}.sa-boost-lede{margin:20px 0 0;max-width:730px;color:#42504a;font-size:20px;line-height:1.62}.sa-boost-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:30px}.sa-boost-btn{min-height:52px;border:0;border-radius:999px;background:var(--green);color:#fff!important;font-weight:950;padding:0 22px;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 16px 32px rgba(0,107,82,.24);cursor:pointer}.sa-boost-btn.is-light{background:#fff;color:var(--ink)!important;box-shadow:0 14px 28px rgba(17,24,21,.1)}.sa-boost-btn.is-hot{background:linear-gradient(135deg,#ff7a1a,#ff3d00);box-shadow:0 16px 32px rgba(255,90,0,.25)}.sa-boost-note{margin-top:14px;color:var(--muted);font-size:14px}
  .sa-boost-panel{border-radius:34px;background:#101815;color:#fff;padding:24px;box-shadow:var(--shadow);position:relative;overflow:hidden}.sa-boost-panel:before{content:"";position:absolute;inset:-80px -120px auto auto;width:260px;height:260px;border-radius:999px;border:1px solid rgba(182,240,106,.28)}.sa-boost-card{position:relative;border-radius:28px;background:linear-gradient(160deg,#184232,#072219);padding:24px;min-height:430px;display:flex;flex-direction:column;justify-content:space-between}.sa-boost-rank{display:grid;gap:10px}.sa-boost-rank span{width:70%;height:18px;border-radius:999px;background:rgba(255,255,255,.14)}.sa-boost-rank span:nth-child(1){width:92%;background:var(--lime)}.sa-boost-rank span:nth-child(2){width:78%}.sa-boost-rank span:nth-child(3){width:62%}.sa-boost-price strong{font-size:64px;line-height:1}.sa-boost-price small{display:block;color:rgba(255,255,255,.72);font-weight:800}.sa-boost-badge{align-self:flex-start;border-radius:999px;background:rgba(182,240,106,.18);color:var(--lime);border:1px solid rgba(182,240,106,.35);padding:9px 12px;font-weight:950}
  .sa-boost-strip{padding:26px 0;background:#fff;border-block:1px solid var(--line)}.sa-boost-benefits{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.sa-boost-benefit{border-radius:22px;background:var(--soft);border:1px solid var(--line);padding:18px;min-height:132px}.sa-boost-benefit b{display:grid;width:40px;height:40px;place-items:center;border-radius:999px;background:var(--green);color:#fff;margin-bottom:12px}.sa-boost-benefit strong{display:block;line-height:1.2}.sa-boost-section{padding:clamp(52px,7vw,96px) 0}.sa-boost-story{display:grid;grid-template-columns:1fr 1fr;gap:22px;align-items:stretch}.sa-boost-copy-card,.sa-boost-form-card{border:1px solid var(--line);border-radius:30px;background:#fff;padding:clamp(24px,4vw,40px);box-shadow:0 18px 52px rgba(17,24,21,.08)}.sa-boost-copy-card h2,.sa-boost-form-card h2{margin:0;font-size:clamp(30px,4vw,50px);line-height:1}.sa-boost-copy-card p,.sa-boost-form-card p{color:var(--muted);line-height:1.65;font-size:17px}.sa-boost-list{list-style:none;margin:24px 0 0;padding:0;display:grid;gap:14px}.sa-boost-list li{display:flex;gap:10px;align-items:flex-start;font-weight:850}.sa-boost-list li:before{content:"";width:22px;height:22px;flex:0 0 22px;border-radius:999px;background:var(--green);box-shadow:inset 0 0 0 6px var(--lime)}.sa-boost-form-card{background:linear-gradient(180deg,#fff,#f7fbf8)}.sa-boost-status{border-radius:20px;background:#ecfdf3;color:#0f5f36;padding:14px 16px;font-weight:950;margin-bottom:16px}.sa-boost-footer{background:#0d1914;color:#fff;padding:34px 0}.sa-boost-footer .sa-boost-wrap{display:flex;justify-content:space-between;gap:18px;align-items:center}.sa-boost-footer a{color:rgba(255,255,255,.78)}
  @media(max-width:1050px){.sa-boost-hero__grid,.sa-boost-story{grid-template-columns:1fr}.sa-boost-benefits{grid-template-columns:repeat(2,minmax(0,1fr))}.sa-boost-nav__links{display:none}}@media(max-width:640px){.sa-boost{--gutter:24px}.sa-boost-actions{display:grid}.sa-boost-btn{width:100%}.sa-boost-panel{border-radius:28px;padding:16px}.sa-boost-card{border-radius:24px;min-height:360px}.sa-boost-benefits{grid-template-columns:1fr}.sa-boost-footer .sa-boost-wrap{display:grid}.sa-boost h1{font-size:clamp(38px,13vw,56px)}}
</style>

<div class="sa-boost">
  <header class="sa-boost-wrap sa-boost-nav">
    <a class="sa-boost-logo" href="<?= e(app_url('store')) ?>"><?php if ($logo !== ''): ?><img src="<?= e($logo) ?>" alt="<?= e($brandName) ?>"><?php else: ?><strong><?= e($brandName) ?></strong><?php endif; ?></a>
    <nav class="sa-boost-nav__links" aria-label="Boost navigation">
      <a href="<?= e(app_url('shop')) ?>">Marketplace</a>
      <a href="<?= e(app_url('vendors')) ?>">Vendors</a>
      <a href="<?= e(app_url('packages')) ?>">Packages</a>
    </nav>
    <a class="sa-boost-cart" href="<?= e(app_url('cart')) ?>"><?= number_format($cartCount) ?></a>
  </header>

  <main>
    <section class="sa-boost-hero">
      <div class="sa-boost-wrap sa-boost-hero__grid">
        <div>
          <p class="sa-boost-kicker">Visibility Boost</p>
          <h1>Your products are listed. But are buyers actually seeing them?</h1>
          <p class="sa-boost-lede">Do not let your products get buried among thousands of listings. For just $5/month, boost your visibility and get your products ranked on the Seller Africa Marketplace homepage, where buyers are more likely to discover them first.</p>
          <div class="sa-boost-actions">
            <?php if ($isBoostActive): ?>
              <a class="sa-boost-btn" href="<?= e(app_url('vendor/products')) ?>">Boost Active</a>
            <?php elseif ($vendor): ?>
              <form method="post" action="<?= e(app_url('visibility-boost')) ?>"><input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>"><input type="hidden" name="payment_provider" value="stripe"><button class="sa-boost-btn is-hot" type="submit">Subscribe with Stripe</button></form>
              <form method="post" action="<?= e(app_url('visibility-boost')) ?>"><input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>"><input type="hidden" name="payment_provider" value="paystack"><button class="sa-boost-btn" type="submit">Subscribe with Paystack</button></form>
            <?php else: ?>
              <a class="sa-boost-btn is-hot" href="<?= e(app_url('login')) ?>">Sign in to Subscribe</a>
            <?php endif; ?>
            <a class="sa-boost-btn is-light" href="<?= e(app_url('shop')) ?>">View Marketplace</a>
          </div>
          <p class="sa-boost-note">Stripe manages recurring monthly billing. Paystack starts the boost after payment confirmation.</p>
        </div>
        <aside class="sa-boost-panel" aria-label="Homepage ranking preview">
          <div class="sa-boost-card">
            <span class="sa-boost-badge">Homepage ranked listing</span>
            <div class="sa-boost-rank"><span></span><span></span><span></span><span></span></div>
            <div class="sa-boost-price"><strong>$5</strong><small>per month. Get ranked. Get seen. Get discovered.</small></div>
          </div>
        </aside>
      </div>
    </section>

    <section class="sa-boost-strip">
      <div class="sa-boost-wrap sa-boost-benefits">
        <div class="sa-boost-benefit"><b>1</b><strong>Homepage ranking</strong></div>
        <div class="sa-boost-benefit"><b>2</b><strong>Increased product visibility</strong></div>
        <div class="sa-boost-benefit"><b>3</b><strong>More buyer exposure</strong></div>
        <div class="sa-boost-benefit"><b>4</b><strong>Stand out from competing listings</strong></div>
        <div class="sa-boost-benefit"><b>5</b><strong>More opportunities to generate sales</strong></div>
      </div>
    </section>

    <section class="sa-boost-wrap sa-boost-section">
      <div class="sa-boost-story">
        <article class="sa-boost-copy-card">
          <p class="sa-boost-kicker">Get discovered</p>
          <h2>You already did the work to list your products.</h2>
          <p>Now make sure they are seen. Visibility Boost gives active vendor products higher placement in Seller Africa product ranking so buyers can discover your store faster.</p>
          <ul class="sa-boost-list">
            <li>Products from boosted vendors are prioritized in marketplace homepage sections.</li>
            <li>Your subscription remains active while Stripe billing is current.</li>
            <li>You can keep improving your product images, titles, and descriptions while the boost runs.</li>
          </ul>
        </article>
        <article class="sa-boost-form-card">
          <?php if ($isBoostActive): ?><div class="sa-boost-status">Your visibility boost is currently <?= e((string)$activeBoost['status']) ?>.</div><?php endif; ?>
          <p class="sa-boost-kicker">Subscribe today</p>
          <h2>$5/month. Get ranked.</h2>
          <p>Start a monthly Stripe subscription and put your listed products in a better position to be discovered by buyers.</p>
          <?php if ($isBoostActive): ?>
            <a class="sa-boost-btn" href="<?= e(app_url('vendor/products')) ?>">Manage Products</a>
          <?php elseif ($vendor): ?>
            <form method="post" action="<?= e(app_url('visibility-boost')) ?>"><input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>"><input type="hidden" name="payment_provider" value="stripe"><button class="sa-boost-btn is-hot" type="submit">Subscribe With Stripe</button></form>
            <form method="post" action="<?= e(app_url('visibility-boost')) ?>" style="margin-top:10px"><input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>"><input type="hidden" name="payment_provider" value="paystack"><button class="sa-boost-btn" type="submit">Subscribe With Paystack</button></form>
          <?php else: ?>
            <a class="sa-boost-btn is-hot" href="<?= e(app_url('login')) ?>">Sign in With Vendor Account</a>
          <?php endif; ?>
        </article>
      </div>
    </section>
  </main>

  <footer class="sa-boost-footer">
    <div class="sa-boost-wrap"><strong><?= e($brandName) ?> Visibility Boost</strong><a href="<?= e(app_url('vendor/register')) ?>">Need a vendor account?</a></div>
  </footer>
</div>
