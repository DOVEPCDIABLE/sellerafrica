<?php
use App\FarmFreshDiscoveryService;

$brand = app_branding();
$brandName = (string)($brand['name'] ?? 'Seller Africa');
$farmers = is_array($farmers ?? null) ? $farmers : [];
$categories = is_array($categories ?? null) ? $categories : FarmFreshDiscoveryService::CATEGORIES;
?>
<style>
  .sa-farmfresh{--green:#07843f;--deep:#101d17;--leaf:#dff3e7;--cream:#fffaf0;--soft:#f5fbf7;--muted:#66736d;--line:#e3ece7;--shadow:0 26px 80px rgba(16,29,23,.14);--gutter:clamp(18px,6vw,92px);font-family:"DM Sans",system-ui,-apple-system,sans-serif;color:var(--deep);background:#fff;overflow:hidden}.sa-farmfresh *{box-sizing:border-box}.sa-farmfresh a{text-decoration:none;color:inherit}.sa-farmfresh-wrap{width:min(1280px,100% - var(--gutter));margin:0 auto}.sa-farmfresh-kicker{margin:0 0 10px;color:var(--green);font-size:12px;font-weight:950;text-transform:uppercase;letter-spacing:.12em}.sa-farmfresh h1,.sa-farmfresh h2,.sa-farmfresh h3{letter-spacing:0}.sa-farmfresh-hero{position:relative;padding:28px 0 42px;background:linear-gradient(180deg,#f4fbf6 0,#fff 100%)}.sa-farmfresh-hero__grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,500px);gap:clamp(28px,6vw,70px);align-items:center}.sa-farmfresh-copy{padding:clamp(28px,5vw,66px) 0}.sa-farmfresh h1{margin:0;font-size:clamp(44px,7.2vw,88px);line-height:.94;max-width:840px}.sa-farmfresh-copy p:not(.sa-farmfresh-kicker){max-width:720px;margin:18px 0 0;color:var(--muted);font-size:19px;line-height:1.62}.sa-farmfresh-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}.sa-farmfresh-btn{min-height:50px;border-radius:999px;padding:0 20px;display:inline-flex;align-items:center;justify-content:center;font-weight:950;background:var(--green);color:#fff!important;border:0;box-shadow:0 14px 28px rgba(7,132,63,.24)}.sa-farmfresh-btn.is-ghost{background:#fff;color:var(--deep)!important;box-shadow:0 12px 26px rgba(16,29,23,.1)}.sa-farmfresh-visual{position:relative;border-radius:34px;min-height:560px;overflow:hidden;box-shadow:var(--shadow);background:var(--leaf)}.sa-farmfresh-visual img{width:100%;height:100%;position:absolute;inset:0;object-fit:cover}.sa-farmfresh-cardlet{position:absolute;left:22px;right:22px;bottom:22px;background:rgba(255,255,255,.92);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.8);border-radius:24px;padding:16px;box-shadow:0 18px 46px rgba(16,29,23,.15);display:grid;gap:10px}.sa-farmfresh-cardlet strong{font-size:20px}.sa-farmfresh-cardlet span{color:var(--muted);line-height:1.45}.sa-farmfresh-search{margin-top:26px;padding:10px;background:#fff;border:1px solid var(--line);border-radius:999px;display:grid;grid-template-columns:1fr auto;gap:8px;max-width:760px;box-shadow:0 16px 38px rgba(16,29,23,.08)}.sa-farmfresh-search input{min-height:48px;border:0;background:#f5f8f6;border-radius:999px;padding:0 16px;font:inherit}.sa-farmfresh-chips{display:flex;gap:10px;overflow:auto;padding:18px 0 0}.sa-farmfresh-chip{white-space:nowrap;border:1px solid var(--line);background:#fff;border-radius:999px;padding:11px 15px;font-weight:950;color:#133327!important;box-shadow:0 10px 22px rgba(16,29,23,.05)}.sa-farmfresh-strip{padding:24px 0;background:#fff;border-block:1px solid var(--line)}.sa-farmfresh-strip__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.sa-farmfresh-stat{border-radius:24px;background:linear-gradient(135deg,#fff,#f5fbf7);border:1px solid var(--line);padding:20px}.sa-farmfresh-stat strong{display:block;font-size:28px;margin-bottom:5px}.sa-farmfresh-stat span{color:var(--muted);line-height:1.45}.sa-farmfresh-section{padding:clamp(48px,7vw,86px) 0;background:linear-gradient(180deg,#fff,#f7fbf8)}.sa-farmfresh-head{display:flex;align-items:end;justify-content:space-between;gap:18px;margin-bottom:28px}.sa-farmfresh-head h2{margin:0;font-size:clamp(32px,4.6vw,58px);line-height:1}.sa-farmfresh-head p{max-width:640px;margin:10px 0 0;color:var(--muted);line-height:1.6}.sa-farmfresh-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px}.sa-farmfresh-card{border:1px solid var(--line);border-radius:28px;overflow:hidden;background:#fff;box-shadow:0 18px 46px rgba(16,29,23,.08)}.sa-farmfresh-card__image{aspect-ratio:1.35;display:grid;place-items:center;background:linear-gradient(135deg,#e8f6e9,#fff8df);color:var(--green);font-weight:950;font-size:42px}.sa-farmfresh-card__image img{width:100%;height:100%;object-fit:cover;display:block}.sa-farmfresh-card__body{padding:18px;display:grid;gap:12px}.sa-farmfresh-card h3{margin:0;font-size:23px;line-height:1.12}.sa-farmfresh-card p{margin:0;color:var(--muted);line-height:1.55;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}.sa-farmfresh-meta{display:flex;gap:8px;flex-wrap:wrap}.sa-farmfresh-meta span{background:var(--soft);border-radius:999px;color:#12633c;padding:7px 10px;font-size:12px;font-weight:950}.sa-farmfresh-empty{border:1px dashed var(--line);border-radius:28px;padding:34px;background:#fff;color:var(--muted);text-align:center}.sa-farmfresh-empty h3{margin:0 0 8px;color:var(--deep);font-size:24px}
  @media(max-width:980px){.sa-farmfresh-hero__grid{grid-template-columns:1fr}.sa-farmfresh-visual{min-height:420px}.sa-farmfresh-grid,.sa-farmfresh-strip__grid{grid-template-columns:1fr 1fr}}@media(max-width:640px){.sa-farmfresh{--gutter:24px}.sa-farmfresh-copy{padding-top:24px}.sa-farmfresh-actions,.sa-farmfresh-search{display:grid;grid-template-columns:1fr}.sa-farmfresh-btn{width:100%}.sa-farmfresh-visual{border-radius:30px;min-height:390px}.sa-farmfresh-head{display:block}.sa-farmfresh-grid,.sa-farmfresh-strip__grid{grid-template-columns:1fr}.sa-farmfresh-card{border-radius:24px}.sa-farmfresh-search{border-radius:24px}.sa-farmfresh-search input{width:100%}}
</style>

<main class="sa-farmfresh">
  <section class="sa-farmfresh-hero">
    <div class="sa-farmfresh-wrap sa-farmfresh-hero__grid">
      <div class="sa-farmfresh-copy">
        <p class="sa-farmfresh-kicker">FreshRoots by <?= e($brandName) ?></p>
        <h1>Fresh food, closer to the farm.</h1>
        <p>Discover American farmers, pasture-raised goods, fresh produce, raw honey, herbs, fruits, vegetables, and specialty farm products in one dedicated FreshRoots marketplace.</p>
        <div class="sa-farmfresh-actions">
          <a class="sa-farmfresh-btn" href="<?= e(app_url('farms')) ?>">Browse Farms</a>
          <a class="sa-farmfresh-btn is-ghost" href="<?= e(app_url('farms-near-me')) ?>">Black Owned Farms Near You</a>
        </div>
        <form class="sa-farmfresh-search" method="get" action="<?= e(app_url('farms-near-me')) ?>">
          <input name="q" placeholder="Search by city, state, produce, eggs, honey...">
          <button class="sa-farmfresh-btn" type="submit">Search Map</button>
        </form>
        <nav class="sa-farmfresh-chips" aria-label="FreshRoots categories">
          <?php foreach (array_slice($categories, 0, 10) as $category): ?>
            <a class="sa-farmfresh-chip" href="<?= e(app_url('farms?category=' . rawurlencode((string)$category))) ?>"><?= e((string)$category) ?></a>
          <?php endforeach; ?>
        </nav>
      </div>
      <div class="sa-farmfresh-visual" aria-hidden="true">
        <img src="<?= e(app_url('assets/images/farmers.jpeg')) ?>" alt="">
        <div class="sa-farmfresh-cardlet">
          <strong>Direct from verified farms</strong>
          <span>Shop by category, location, or farm profile. Fresh listings appear as farmers are approved.</span>
        </div>
      </div>
    </div>
  </section>

  <section class="sa-farmfresh-strip">
    <div class="sa-farmfresh-wrap sa-farmfresh-strip__grid">
      <div class="sa-farmfresh-stat"><strong><?= number_format(count($farmers)) ?></strong><span>approved FreshRoots vendors currently discoverable</span></div>
      <div class="sa-farmfresh-stat"><strong>19</strong><span>farm categories from pasture meats to vegetables and raw honey</span></div>
      <div class="sa-farmfresh-stat"><strong>Map</strong><span>browse farms by location and open profiles from the Black owned farms near you page</span></div>
    </div>
  </section>

  <section class="sa-farmfresh-section">
    <div class="sa-farmfresh-wrap">
      <div class="sa-farmfresh-head">
        <div>
          <p class="sa-farmfresh-kicker">Farmer Market</p>
          <h2>Explore approved farms.</h2>
          <p>Approved American farmer profiles will appear here as their stores are activated.</p>
        </div>
        <a class="sa-farmfresh-btn is-ghost" href="<?= e(app_url('farms')) ?>">See All Farms</a>
      </div>

      <?php if ($farmers !== []): ?>
        <div class="sa-farmfresh-grid">
          <?php foreach ($farmers as $farmer): ?>
            <?php $image = FarmFreshDiscoveryService::assetUrl((string)($farmer['banner_path'] ?? $farmer['logo_path'] ?? '')); ?>
            <article class="sa-farmfresh-card">
              <a class="sa-farmfresh-card__image" href="<?= e(app_url('vendors/' . rawurlencode((string)$farmer['store_slug']))) ?>">
                <?php if ($image !== ''): ?><img src="<?= e($image) ?>" alt="<?= e((string)$farmer['store_name']) ?>"><?php else: ?><span><?= e(strtoupper(substr((string)$farmer['store_name'], 0, 1))) ?></span><?php endif; ?>
              </a>
              <div class="sa-farmfresh-card__body">
                <h3><a href="<?= e(app_url('vendors/' . rawurlencode((string)$farmer['store_slug']))) ?>"><?= e((string)$farmer['store_name']) ?></a></h3>
                <div class="sa-farmfresh-meta"><span><?= e((string)$farmer['location_label']) ?></span><span><?= number_format((int)($farmer['product_count'] ?? 0)) ?> products</span></div>
                <p><?= e((string)($farmer['description'] ?: 'This farmer is preparing their FreshRoots profile.')) ?></p>
                <a class="sa-farmfresh-btn is-ghost" href="<?= e(app_url('vendors/' . rawurlencode((string)$farmer['store_slug']))) ?>">View Farm</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="sa-farmfresh-empty">
          <h3>FreshRoots vendors are being reviewed.</h3>
          <p>Approved farmer profiles will show here after verification. Farmers can apply now to join the launch group.</p>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>
