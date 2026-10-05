<?php
use App\FarmFreshDiscoveryService;

$categories = is_array($categories ?? null) ? $categories : FarmFreshDiscoveryService::CATEGORIES;
$farmers = is_array($farmers ?? null) ? $farmers : [];
$produce = is_array($produce ?? null) ? $produce : [];
$filters = is_array($filters ?? null) ? $filters : [];
$q = (string)($filters['q'] ?? '');
$selectedCategory = (string)($filters['category'] ?? '');
$featuredProduce = $produce[0] ?? null;
$featuredImage = $featuredProduce ? FarmFreshDiscoveryService::assetUrl((string)($featuredProduce['image_path'] ?? '')) : '';
?>
<style>
  .ff-market{--green:#07843f;--deep:#101d17;--leaf:#dff3e7;--mint:#f3fbf6;--gold:#ffc857;--line:#e4ece7;--muted:#68776f;--shadow:0 24px 70px rgba(16,29,23,.12);--gutter:clamp(16px,5vw,80px);font-family:"DM Sans",system-ui,-apple-system,sans-serif;background:linear-gradient(180deg,#f6fbf7 0,#fff 42%,#f8fbf8 100%);color:var(--deep);min-height:100vh}
  .ff-market *{box-sizing:border-box}.ff-market a{text-decoration:none;color:inherit}.ff-wrap{width:min(1240px,100% - var(--gutter));margin:0 auto}.ff-pill{border-radius:999px;background:#fff;border:1px solid var(--line);box-shadow:0 10px 24px rgba(16,29,23,.06)}
  .ff-hero{position:relative;overflow:hidden;padding:34px 0 26px}.ff-hero-shell{display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,380px);gap:26px;align-items:stretch}.ff-hero-copy{border-radius:30px;padding:clamp(28px,5vw,58px);min-height:520px;background:linear-gradient(120deg,rgba(8,64,39,.92),rgba(8,132,63,.74)),url("<?= e(app_url('assets/images/farmers.jpeg')) ?>") center/cover;color:#fff;box-shadow:var(--shadow);display:flex;flex-direction:column;justify-content:flex-end}.ff-kicker{margin:0 0 12px;font-size:12px;text-transform:uppercase;font-weight:950;letter-spacing:.12em}.ff-hero h1{margin:0;font-size:clamp(40px,7vw,82px);line-height:.96;letter-spacing:0;max-width:760px}.ff-hero p{max-width:640px;margin:16px 0 0;color:rgba(255,255,255,.86);font-size:18px;line-height:1.55}.ff-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}.ff-btn{min-height:48px;border:0;border-radius:999px;background:var(--green);color:#fff!important;font-weight:950;display:inline-flex;align-items:center;justify-content:center;padding:0 20px;box-shadow:0 12px 24px rgba(7,132,63,.22);cursor:pointer}.ff-btn.is-light{background:#fff;color:var(--deep)!important;box-shadow:0 12px 24px rgba(16,29,23,.12)}.ff-btn.is-soft{background:var(--leaf);color:#075b31!important;box-shadow:none}
  .ff-phone{border-radius:34px;background:#fff;padding:18px;box-shadow:var(--shadow);display:grid;gap:16px}.ff-phone-top{display:flex;align-items:center;justify-content:space-between;gap:10px}.ff-avatar{width:42px;height:42px;border-radius:999px;overflow:hidden;background:var(--leaf);display:grid;place-items:center;font-weight:950;color:var(--green)}.ff-phone-search{display:grid;grid-template-columns:1fr 46px;gap:10px}.ff-phone-search input{height:48px;border:0;background:#f4f6f5;border-radius:999px;padding:0 16px;font:inherit}.ff-icon-btn{width:46px;height:46px;border:0;border-radius:999px;background:var(--leaf);font-size:20px;color:var(--green);display:grid;place-items:center}.ff-phone-product{border-radius:26px;background:linear-gradient(180deg,#f8fbf8,#eef8f1);overflow:hidden;box-shadow:inset 0 0 0 1px var(--line)}.ff-phone-product__image{height:190px;display:grid;place-items:center;background:#fff}.ff-phone-product__image img{width:100%;height:100%;object-fit:cover}.ff-phone-product__image span{font-size:56px}.ff-phone-product__body{padding:16px}.ff-phone-product h3{margin:0;font-size:21px;line-height:1.1;letter-spacing:0}.ff-phone-product p{margin:8px 0 0;color:var(--muted);font-size:13px}.ff-bottom-nav{height:58px;border-radius:999px;background:#fff;box-shadow:0 14px 34px rgba(16,29,23,.12);display:grid;grid-template-columns:repeat(4,1fr);align-items:center;text-align:center;font-size:11px;font-weight:900;color:var(--muted)}.ff-bottom-nav span:first-child{color:var(--green)}
  .ff-search-band{margin-top:-6px;position:relative;z-index:2}.ff-search-card{background:#fff;border:1px solid var(--line);border-radius:24px;padding:14px;display:grid;grid-template-columns:1fr minmax(190px,240px) auto;gap:10px;box-shadow:0 18px 44px rgba(16,29,23,.08)}.ff-search-card input,.ff-search-card select{height:52px;border:0;background:#f5f8f6;border-radius:999px;padding:0 16px;font:inherit}.ff-section{padding:34px 0}.ff-head{display:flex;justify-content:space-between;align-items:end;gap:16px;margin-bottom:18px}.ff-head h2{margin:0;font-size:clamp(28px,4vw,48px);letter-spacing:0;line-height:1.02}.ff-head p{margin:8px 0 0;color:var(--muted);line-height:1.5}.ff-chips{display:flex;gap:12px;overflow:auto;padding:2px 2px 12px;scroll-snap-type:x proximity}.ff-chip{white-space:nowrap;scroll-snap-align:start;padding:12px 16px;font-weight:950;color:#1b3026}.ff-chip.is-active{background:var(--green);color:#fff;border-color:var(--green)}
  .ff-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}.ff-card{position:relative;background:#fff;border:1px solid var(--line);border-radius:26px;overflow:hidden;box-shadow:0 18px 46px rgba(16,29,23,.08);transition:transform .2s ease,box-shadow .2s ease}.ff-card:hover{transform:translateY(-4px);box-shadow:0 26px 70px rgba(16,29,23,.13)}.ff-image{aspect-ratio:1.25;background:linear-gradient(135deg,#daf2e2,#fff7df);display:grid;place-items:center;color:var(--green);font-size:42px;font-weight:950}.ff-image img{width:100%;height:100%;object-fit:cover;display:block}.ff-body{padding:18px;display:grid;gap:12px}.ff-body h3{margin:0;font-size:22px;line-height:1.12;letter-spacing:0}.ff-body p{margin:0;color:var(--muted);line-height:1.5;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}.ff-meta{display:flex;gap:8px;flex-wrap:wrap}.ff-meta span{background:var(--mint);color:#12633c;border-radius:999px;padding:7px 10px;font-size:12px;font-weight:950}.ff-price{display:flex;align-items:center;justify-content:space-between;gap:12px;font-weight:950}.ff-price small{color:var(--muted)}.ff-empty{border:1px dashed var(--line);background:#fff;border-radius:26px;padding:34px;text-align:center;color:var(--muted)}
  @media(max-width:980px){.ff-hero-shell{grid-template-columns:1fr}.ff-hero-copy{min-height:430px}.ff-phone{max-width:420px;margin:0 auto}.ff-grid{grid-template-columns:1fr 1fr}.ff-search-card{grid-template-columns:1fr 1fr}.ff-search-card .ff-btn{grid-column:1/-1}}@media(max-width:640px){.ff-market{--gutter:24px}.ff-hero{padding-top:18px}.ff-hero-copy{border-radius:0 0 34px 34px;margin-inline:calc(var(--gutter) / -1);padding-inline:var(--gutter);min-height:500px}.ff-grid{grid-template-columns:1fr}.ff-head{display:block}.ff-actions,.ff-search-card{display:grid;grid-template-columns:1fr}.ff-btn{width:100%}.ff-phone{border-radius:30px}.ff-section{padding:26px 0}.ff-card{border-radius:24px}.ff-image{aspect-ratio:1.15}}
</style>

<main class="ff-market">
  <section class="ff-hero">
    <div class="ff-wrap ff-hero-shell">
      <div class="ff-hero-copy">
        <p class="ff-kicker">Seller Africa FreshRoots</p>
        <h1>Find fresh food from real farmers.</h1>
        <p>Search farms, local produce, pasture-raised goods, raw honey, fresh vegetables, and specialty farm products from approved FreshRoots sellers.</p>
        <div class="ff-actions">
          <a class="ff-btn" href="<?= e(app_url('farms-near-me')) ?>">Black Owned Farms Near You</a>
        </div>
      </div>

      <aside class="ff-phone" aria-label="FreshRoots preview">
        <div class="ff-phone-top">
          <div style="display:flex;align-items:center;gap:10px"><span class="ff-avatar">FF</span><div><strong>FreshRoots</strong><br><small><?= e($selectedCategory !== '' ? $selectedCategory : 'Local market') ?></small></div></div>
          <a class="ff-icon-btn" href="<?= e(app_url('farms-near-me')) ?>" aria-label="Open farms map">+</a>
        </div>
        <form class="ff-phone-search" method="get" action="<?= e(app_url('farms')) ?>">
          <input name="q" value="<?= e($q) ?>" placeholder="Search food...">
          <button class="ff-icon-btn" type="submit" aria-label="Search">Go</button>
        </form>
        <article class="ff-phone-product">
          <a class="ff-phone-product__image" href="<?= e($featuredProduce ? app_url('product/' . rawurlencode((string)$featuredProduce['slug'])) : app_url('farms')) ?>">
            <?php if ($featuredImage !== ''): ?><img src="<?= e($featuredImage) ?>" alt="<?= e((string)$featuredProduce['name']) ?>"><?php else: ?><span>Fresh</span><?php endif; ?>
          </a>
          <div class="ff-phone-product__body">
            <h3><?= e($featuredProduce ? (string)$featuredProduce['name'] : 'Freshly harvested local produce') ?></h3>
            <p><?= e($featuredProduce ? (string)$featuredProduce['store_name'] : 'Approved farms will appear here as they go live.') ?></p>
          </div>
        </article>
        <div class="ff-bottom-nav"><span>Home</span><span>Explore</span><span>Farms</span><span>Cart</span></div>
      </aside>
    </div>
  </section>

  <section class="ff-wrap ff-search-band">
    <form class="ff-search-card" method="get" action="<?= e(app_url('farms')) ?>">
      <input name="q" value="<?= e($q) ?>" placeholder="What are you looking for? Try eggs, honey, Dallas...">
      <select name="category">
        <option value="">All categories</option>
        <?php foreach ($categories as $category): ?><option value="<?= e((string)$category) ?>"<?= $selectedCategory === $category ? ' selected' : '' ?>><?= e((string)$category) ?></option><?php endforeach; ?>
      </select>
      <button class="ff-btn" type="submit">Search</button>
    </form>
  </section>

  <section class="ff-wrap ff-section">
    <div class="ff-head"><div><h2>Shop by categories</h2><p>Choose what you need, then explore farms and produce that match.</p></div></div>
    <nav class="ff-chips" aria-label="Farm categories">
      <a class="ff-chip ff-pill<?= $selectedCategory === '' ? ' is-active' : '' ?>" href="<?= e(app_url('farms' . ($q !== '' ? '?q=' . rawurlencode($q) : ''))) ?>">All</a>
      <?php foreach ($categories as $category): ?>
        <?php $url = app_url('farms?category=' . rawurlencode((string)$category) . ($q !== '' ? '&q=' . rawurlencode($q) : '')); ?>
        <a class="ff-chip ff-pill<?= $selectedCategory === $category ? ' is-active' : '' ?>" href="<?= e($url) ?>"><?= e((string)$category) ?></a>
      <?php endforeach; ?>
    </nav>
  </section>

  <section class="ff-wrap ff-section">
    <div class="ff-head"><div><h2>Farmers and farms</h2><p>Meet approved FreshRoots growers and sellers.</p></div><a class="ff-btn is-soft" href="<?= e(app_url('farms-near-me')) ?>">Open Map</a></div>
    <?php if ($farmers): ?>
      <div class="ff-grid">
        <?php foreach ($farmers as $farmer): ?>
          <?php $image = FarmFreshDiscoveryService::assetUrl((string)($farmer['banner_path'] ?: $farmer['logo_path'] ?? '')); ?>
          <article class="ff-card">
            <a class="ff-image" href="<?= e(app_url('vendors/' . rawurlencode((string)$farmer['store_slug']))) ?>"><?php if ($image): ?><img src="<?= e($image) ?>" alt="<?= e((string)$farmer['store_name']) ?>"><?php else: ?><span><?= e(strtoupper(substr((string)$farmer['store_name'], 0, 1))) ?></span><?php endif; ?></a>
            <div class="ff-body">
              <h3><?= e((string)$farmer['store_name']) ?></h3>
              <div class="ff-meta"><span><?= e((string)$farmer['location_label']) ?></span><span><?= number_format((int)$farmer['product_count']) ?> products</span></div>
              <p><?= e((string)($farmer['description'] ?: 'This farm is preparing its FreshRoots profile.')) ?></p>
              <div class="ff-meta"><?php foreach (array_slice((array)$farmer['categories'], 0, 3) as $cat): ?><span><?= e((string)$cat) ?></span><?php endforeach; ?></div>
              <a class="ff-btn is-soft" href="<?= e(app_url('vendors/' . rawurlencode((string)$farmer['store_slug']))) ?>">View Farm</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="ff-empty">No approved farms match this search yet.</div>
    <?php endif; ?>
  </section>

  <section class="ff-wrap ff-section">
    <div class="ff-head"><div><h2>Recently listed produce</h2><p>Fresh listings from FreshRoots farmers.</p></div></div>
    <?php if ($produce): ?>
      <div class="ff-grid">
        <?php foreach ($produce as $product): ?>
          <?php $image = FarmFreshDiscoveryService::assetUrl((string)($product['image_path'] ?? '')); ?>
          <article class="ff-card">
            <a class="ff-image" href="<?= e(app_url('product/' . rawurlencode((string)$product['slug']))) ?>"><?php if ($image): ?><img src="<?= e($image) ?>" alt="<?= e((string)$product['name']) ?>"><?php else: ?><span>Fresh</span><?php endif; ?></a>
            <div class="ff-body">
              <h3><?= e((string)$product['name']) ?></h3>
              <div class="ff-price"><span>$<?= e(number_format((float)$product['regular_price'], 2)) ?></span><small><?= e((string)($product['selling_unit'] ?: 'unit')) ?></small></div>
              <p><?= e((string)($product['short_description'] ?: $product['description'] ?: 'Fresh produce listing from this farm.')) ?></p>
              <div class="ff-meta"><span><?= e((string)$product['store_name']) ?></span><span><?= e(trim((string)$product['city'] . ', ' . (string)$product['state'], ', ')) ?></span></div>
              <a class="ff-btn" href="<?= e(app_url('product/' . rawurlencode((string)$product['slug']))) ?>">View Produce</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="ff-empty">No produce listings match this search yet.</div>
    <?php endif; ?>
  </section>
</main>
