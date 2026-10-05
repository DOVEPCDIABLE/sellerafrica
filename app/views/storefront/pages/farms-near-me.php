<?php
use App\FarmFreshDiscoveryService;

$categories = is_array($categories ?? null) ? $categories : FarmFreshDiscoveryService::CATEGORIES;
$farmers = is_array($farmers ?? null) ? $farmers : [];
$filters = is_array($filters ?? null) ? $filters : [];
$q = (string)($filters['q'] ?? '');
$selectedCategory = (string)($filters['category'] ?? '');
$mapFarmers = array_map(static function (array $farmer): array {
    return [
        'id' => (int)$farmer['id'],
        'name' => (string)$farmer['store_name'],
        'slug' => (string)$farmer['store_slug'],
        'description' => (string)($farmer['description'] ?: 'This farm is preparing its FreshRoots profile.'),
        'location' => (string)$farmer['location_label'],
        'lat' => (float)$farmer['map_lat'],
        'lng' => (float)$farmer['map_lng'],
        'products' => (int)$farmer['product_count'],
        'categories' => array_values((array)$farmer['categories']),
        'image' => FarmFreshDiscoveryService::assetUrl((string)($farmer['banner_path'] ?: $farmer['logo_path'] ?? '')),
        'url' => app_url('vendors/' . rawurlencode((string)$farmer['store_slug'])),
    ];
}, $farmers);
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
  .ff-map-page{--green:#07843f;--deep:#101d17;--leaf:#dff3e7;--mint:#f4faf6;--line:#e2ebe6;--muted:#6a7770;--shadow:0 26px 80px rgba(16,29,23,.14);--gutter:clamp(16px,4vw,56px);font-family:"DM Sans",system-ui,-apple-system,sans-serif;color:var(--deep);background:radial-gradient(circle at top left,#e0f5e8 0,#f8fbf8 32%,#fff 68%);min-height:100vh}.ff-map-page *{box-sizing:border-box}.ff-map-page a{text-decoration:none;color:inherit}.ff-map-wrap{width:min(1440px,100% - var(--gutter));margin:0 auto;padding:22px 0 34px}.ff-map-hero{display:grid;grid-template-columns:1fr auto;gap:16px;align-items:end;margin-bottom:18px}.ff-kicker{margin:0 0 8px;color:var(--green);font-weight:950;text-transform:uppercase;font-size:12px;letter-spacing:.12em}.ff-map-hero h1{margin:0;font-size:clamp(38px,6vw,76px);line-height:.94;letter-spacing:0}.ff-map-hero p{margin:12px 0 0;color:var(--muted);font-size:17px;line-height:1.55;max-width:760px}.ff-map-actions{display:flex;gap:10px;flex-wrap:wrap}.ff-btn{min-height:46px;border-radius:999px;background:var(--green);border:0;color:#fff!important;font-weight:950;display:inline-flex;align-items:center;justify-content:center;padding:0 18px;box-shadow:0 12px 24px rgba(7,132,63,.22);cursor:pointer}.ff-btn.is-light{background:#fff;color:var(--deep)!important;box-shadow:0 12px 24px rgba(16,29,23,.1)}.ff-btn.is-soft{background:var(--leaf);color:#075b31!important;box-shadow:none}
  .ff-map-shell{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:18px;align-items:stretch}.ff-map-panel{position:relative;background:#fff;border:1px solid var(--line);border-radius:30px;overflow:hidden;box-shadow:var(--shadow)}.ff-map-search{position:absolute;z-index:500;top:16px;left:16px;right:16px;padding:10px;background:rgba(255,255,255,.92);backdrop-filter:blur(12px);border:1px solid rgba(226,235,230,.9);border-radius:999px;display:grid;grid-template-columns:1fr minmax(170px,230px) auto;gap:8px;box-shadow:0 16px 40px rgba(16,29,23,.12)}.ff-map-search input,.ff-map-search select{height:46px;border:0;background:#f4f7f5;border-radius:999px;padding:0 14px;font:inherit;min-width:0}#ffFarmMap{height:760px;min-height:62vh;background:#eef7f1}.ff-side{display:grid;grid-template-rows:auto 1fr;background:#fff;border:1px solid var(--line);border-radius:30px;overflow:hidden;box-shadow:0 22px 62px rgba(16,29,23,.1)}.ff-side-head{padding:20px 20px 14px;border-bottom:1px solid var(--line);background:linear-gradient(135deg,#fff,#f4faf6)}.ff-side-head strong{display:block;font-size:24px;letter-spacing:0}.ff-side-head span{color:var(--muted);line-height:1.45}.ff-farm-list{overflow:auto;padding:14px;display:grid;gap:14px;scroll-snap-type:y proximity}.ff-farm-card{scroll-snap-align:start;border:1px solid var(--line);border-radius:24px;background:#fff;padding:12px;display:grid;grid-template-columns:104px 1fr;gap:13px;cursor:pointer;transition:.2s;box-shadow:0 12px 30px rgba(16,29,23,.06)}.ff-farm-card.is-active{border-color:var(--green);box-shadow:0 0 0 4px rgba(7,132,63,.14),0 18px 42px rgba(16,29,23,.1)}.ff-farm-card img,.ff-farm-card b{width:104px;height:104px;border-radius:20px;object-fit:cover;background:linear-gradient(135deg,#dff3e7,#fff7db);display:grid;place-items:center;color:var(--green);font-size:30px}.ff-farm-card h3{margin:0;font-size:18px;line-height:1.1;letter-spacing:0}.ff-farm-card p{margin:7px 0;color:var(--muted);font-size:13px;line-height:1.42;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.ff-tags{display:flex;gap:6px;flex-wrap:wrap}.ff-tags span{background:var(--mint);color:#12633c;border-radius:999px;padding:6px 9px;font-size:11px;font-weight:950}.ff-empty-card{border:1px dashed var(--line);background:#fff;border-radius:24px;padding:22px;color:var(--muted)}
  .ff-modal{position:fixed;inset:0;z-index:999;display:none;place-items:center;background:rgba(4,18,12,.58);padding:20px}.ff-modal.is-open{display:grid}.ff-modal-card{position:relative;width:min(520px,100%);background:#fff;border-radius:30px;overflow:hidden;box-shadow:0 34px 90px rgba(0,0,0,.28)}.ff-modal-card img{width:100%;height:250px;object-fit:cover;background:var(--leaf)}.ff-modal-body{padding:20px;display:grid;gap:12px}.ff-modal-body h2{margin:0;font-size:30px;line-height:1.05;letter-spacing:0}.ff-modal-body p{margin:0;color:var(--muted);line-height:1.55}.ff-close{position:absolute;top:18px;right:18px;border:0;border-radius:999px;background:#fff;color:var(--deep);width:42px;height:42px;font-size:24px;cursor:pointer;box-shadow:0 10px 26px rgba(0,0,0,.12)}.leaflet-popup-content-wrapper{border-radius:16px}.leaflet-popup-content{font-family:"DM Sans",system-ui,sans-serif}.leaflet-popup-content button{margin-top:8px;border:0;border-radius:999px;background:var(--green);color:#fff;padding:8px 12px;font-weight:900}.ff-leaf-pin{background:#07843f;color:#fff;border:4px solid #fff;border-radius:999px;width:38px!important;height:38px!important;display:grid!important;place-items:center;box-shadow:0 10px 24px rgba(7,132,63,.32);font-weight:950}
  @media(max-width:1040px){.ff-map-hero{grid-template-columns:1fr}.ff-map-shell{grid-template-columns:1fr}.ff-side{height:auto}.ff-farm-list{display:flex;overflow-x:auto;scroll-snap-type:x mandatory}.ff-farm-card{min-width:min(86vw,390px)}#ffFarmMap{height:62vh}.ff-map-search{grid-template-columns:1fr 1fr}.ff-map-search .ff-btn{grid-column:1/-1}}@media(max-width:680px){.ff-map-wrap{width:min(100% - 24px,1440px);padding-top:14px}.ff-map-actions{display:grid}.ff-btn{width:100%}.ff-map-panel,.ff-side{border-radius:28px}.ff-map-search{position:relative;top:auto;left:auto;right:auto;border-radius:24px;margin:12px;grid-template-columns:1fr;background:#fff}.ff-map-search select{width:100%;min-width:0}#ffFarmMap{height:56vh}.ff-farm-card{grid-template-columns:82px 1fr}.ff-farm-card img,.ff-farm-card b{width:82px;height:82px;border-radius:18px}.ff-modal-card img{height:190px}}
</style>

<main class="ff-map-page">
  <section class="ff-map-wrap">
    <div class="ff-map-hero">
      <div>
        <p class="ff-kicker">FreshRoots Map</p>
        <h1>Black owned farms near you</h1>
        <p>Search by location or category, tap a point on the map, then swipe through Black owned farms and their produce.</p>
      </div>
      <div class="ff-map-actions">
        <a class="ff-btn is-light" href="<?= e(app_url('farms')) ?>">Browse All Farms</a>
      </div>
    </div>

    <div class="ff-map-shell">
      <section class="ff-map-panel">
        <form class="ff-map-search" method="get" action="<?= e(app_url('farms-near-me')) ?>">
          <input name="q" value="<?= e($q) ?>" placeholder="Search city, state, ZIP, farm, or produce">
          <select name="category">
            <option value="">All categories</option>
            <?php foreach ($categories as $category): ?><option value="<?= e((string)$category) ?>"<?= $selectedCategory === $category ? ' selected' : '' ?>><?= e((string)$category) ?></option><?php endforeach; ?>
          </select>
          <button class="ff-btn" type="submit">Search Map</button>
        </form>
        <div id="ffFarmMap"></div>
      </section>

      <aside class="ff-side">
        <div class="ff-side-head"><strong><?= number_format(count($farmers)) ?> farms listed</strong><span>Click a farm or swipe the cards to move the map.</span></div>
        <div class="ff-farm-list" id="ffFarmList">
          <?php foreach ($mapFarmers as $index => $farmer): ?>
            <article class="ff-farm-card" data-farm-index="<?= e((string)$index) ?>">
              <?php if ($farmer['image'] !== ''): ?><img src="<?= e($farmer['image']) ?>" alt="<?= e($farmer['name']) ?>"><?php else: ?><b><?= e(strtoupper(substr($farmer['name'], 0, 1))) ?></b><?php endif; ?>
              <div>
                <h3><?= e($farmer['name']) ?></h3>
                <p><?= e($farmer['description']) ?></p>
                <div class="ff-tags"><span><?= e($farmer['location']) ?></span><span><?= number_format($farmer['products']) ?> products</span></div>
              </div>
            </article>
          <?php endforeach; ?>
          <?php if ($mapFarmers === []): ?><div class="ff-empty-card"><strong>No farms found</strong><br>Try another location or category.</div><?php endif; ?>
        </div>
      </aside>
    </div>
  </section>
</main>

<div class="ff-modal" id="ffFarmModal" aria-hidden="true">
  <article class="ff-modal-card">
    <button class="ff-close" type="button" data-close-farm-modal>&times;</button>
    <img src="" alt="" data-modal-image>
    <div class="ff-modal-body">
      <h2 data-modal-name></h2>
      <div class="ff-tags" data-modal-tags></div>
      <p data-modal-description></p>
      <a class="ff-btn" href="#" data-modal-link>View Farm</a>
    </div>
  </article>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(() => {
  const farms = <?= json_encode($mapFarmers, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const map = L.map('ffFarmMap', { scrollWheelZoom: false }).setView([39.8283, -98.5795], farms.length ? 4 : 3);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap' }).addTo(map);
  const icon = L.divIcon({ className: 'ff-leaf-pin', html: '<span>F</span>', iconSize: [38, 38], iconAnchor: [19, 19] });
  const cards = [...document.querySelectorAll('[data-farm-index]')];
  const modal = document.getElementById('ffFarmModal');
  const markers = [];

  function openModal(farm) {
    modal.querySelector('[data-modal-name]').textContent = farm.name;
    modal.querySelector('[data-modal-description]').textContent = farm.description;
    modal.querySelector('[data-modal-link]').href = farm.url;
    const image = modal.querySelector('[data-modal-image]');
    image.src = farm.image || '<?= e(app_url('assets/images/farmers.jpeg')) ?>';
    image.alt = farm.name;
    const tags = modal.querySelector('[data-modal-tags]');
    tags.innerHTML = '';
    [farm.location, `${farm.products} products`, ...(farm.categories || [])].slice(0, 5).forEach((tag) => {
      const span = document.createElement('span');
      span.textContent = tag;
      tags.appendChild(span);
    });
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function focusFarm(index, open = true) {
    const farm = farms[index];
    if (!farm) return;
    cards.forEach((card) => card.classList.toggle('is-active', Number(card.dataset.farmIndex) === index));
    map.flyTo([farm.lat, farm.lng], 10, { duration: 0.9 });
    markers[index]?.openPopup();
    cards[index]?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    if (open) openModal(farm);
  }

  farms.forEach((farm, index) => {
    const marker = L.marker([farm.lat, farm.lng], { icon }).addTo(map);
    marker.bindPopup(`<strong>${farm.name}</strong><br>${farm.location}<br><button type="button" data-map-farm="${index}">View farm</button>`);
    marker.on('click', () => focusFarm(index, true));
    markers.push(marker);
  });
  if (farms.length > 1) {
    const group = L.featureGroup(markers);
    map.fitBounds(group.getBounds().pad(0.2));
  } else if (farms.length === 1) {
    map.setView([farms[0].lat, farms[0].lng], 10);
  }

  cards.forEach((card) => card.addEventListener('click', () => focusFarm(Number(card.dataset.farmIndex), true)));
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-map-farm]');
    if (button) focusFarm(Number(button.dataset.mapFarm), true);
    if (event.target.matches('[data-close-farm-modal]') || event.target === modal) {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
    }
  });
})();
</script>
