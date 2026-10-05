<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\FarmFreshDiscoveryService;
use App\FarmFreshMembershipService;

FarmFreshMembershipService::requireAccess();
$brand = app_branding();
$brandName = (string)($brand['name'] ?? 'Seller Africa');
$brandLogo = trim((string)($brand['logo'] ?? ''));
$categories = FarmFreshDiscoveryService::CATEGORIES;
$farmers = FarmFreshDiscoveryService::farmers([], 6);
$templatePath = dirname(__DIR__) . '/farmer/black_owned_farms_template.html';
$templateHtml = is_file($templatePath) ? (string)file_get_contents($templatePath) : '';

if (trim($templateHtml) === '') {
    render_layout('storefront-home', 'storefront/pages/farm-fresh.php', [
        'page' => 'farm-fresh',
        'title' => 'FreshRoots | ' . $brandName,
        'metaDescription' => 'Meet American farmers selling fresh produce through FreshRoots by Seller Africa.',
        'categories' => $categories,
        'farmers' => $farmers,
    ]);
    return;
}

$farmCards = '';
foreach ($farmers as $farmer) {
    $farmName = (string)($farmer['store_name'] ?? 'FreshRoots vendor');
    $slug = (string)($farmer['store_slug'] ?? '');
    $farmUrl = app_url($slug !== '' ? 'vendors/' . rawurlencode($slug) : 'farms');
    $image = FarmFreshDiscoveryService::assetUrl((string)($farmer['banner_path'] ?? $farmer['logo_path'] ?? ''));
    $imageHtml = $image !== ''
        ? '<img src="' . e($image) . '" alt="' . e($farmName) . '">'
        : '<span>' . e(strtoupper(substr($farmName, 0, 1))) . '</span>';
    $farmerCategories = array_slice((array)($farmer['categories'] ?? []), 0, 3);
    if ($farmerCategories === []) {
        $farmerCategories = ['FreshRoots'];
    }
    $categoryHtml = '';
    foreach ($farmerCategories as $category) {
        $categoryHtml .= '<span>' . e((string)$category) . '</span>';
    }

    $farmCards .= '
        <article class="ff-template-farm-card">
          <a class="ff-template-farm-image" href="' . e($farmUrl) . '">' . $imageHtml . '</a>
          <div class="ff-template-farm-body">
            <div class="ff-template-farm-meta">' . $categoryHtml . '</div>
            <h3><a href="' . e($farmUrl) . '">' . e($farmName) . '</a></h3>
            <p>' . e((string)($farmer['description'] ?: 'This farm is preparing its FreshRoots profile.')) . '</p>
            <div class="ff-template-farm-line">
              <span>' . e((string)($farmer['location_label'] ?? 'Farm location pending')) . '</span>
              <span>' . number_format((int)($farmer['product_count'] ?? 0)) . ' products</span>
            </div>
            <a class="confirm-btn" href="' . e($farmUrl) . '">View farm</a>
          </div>
        </article>';
}
if ($farmCards === '') {
    $farmCards = '
        <div class="ff-template-empty">
          <h3>FreshRoots vendors are being reviewed.</h3>
          <p>Approved farmer profiles will show here after verification.</p>
        </div>';
}

$categoryChips = '';
foreach (array_slice($categories, 0, 12) as $category) {
    $categoryChips .= '<a href="' . e(app_url('farms?category=' . rawurlencode((string)$category))) . '">' . e((string)$category) . '</a>';
}

$farmsUrl = e(app_url('farms'));
$mapUrl = e(app_url('farms-near-me'));
$farmCount = number_format(count($farmers));
$brandLogoHtml = $brandLogo !== ''
    ? '<img class="ff-brand-logo" src="' . e($brandLogo) . '" alt="' . e($brandName) . '">'
    : '';
$footerLinksHtml = '<div class="footer-links">'
    . '<a href="' . e(app_url('freshroots')) . '">Home</a>'
    . '<a href="' . e(app_url('freshroots/terms')) . '">Terms</a>'
    . '<a href="' . e(app_url('freshroots/privacy-policy')) . '">Privacy</a>'
    . '<a href="' . e(app_url('freshroots/legal')) . '">Legal</a>'
    . '</div>';

$pageContent = <<<HTML
    <div class="form-card ff-template-live-card" id="farms">
      <h2>Explore FreshRoots</h2>
      <p class="intro">Search Black-owned farms, browse fresh produce categories, or open the map to find farmers near you.</p>

      <form class="ff-template-search" method="get" action="{$farmsUrl}">
        <input name="q" placeholder="Search by farm, city, state, produce, honey, eggs...">
        <button class="submit-btn" type="submit">Search farms</button>
      </form>

      <div class="ff-template-actions">
        <a class="confirm-btn" href="{$farmsUrl}">Browse all farms</a>
        <a class="confirm-btn is-outline" href="{$mapUrl}">Black owned farms near you</a>
      </div>

      <div class="form-section" id="why">
        <h3>FreshRoots categories</h3>
        <div class="ff-template-chips">{$categoryChips}</div>
      </div>

      <div class="form-section" id="how">
        <h3>Featured farms</h3>
        <div class="ff-template-farm-grid">{$farmCards}</div>
      </div>
    </div>
HTML;

$templateCss = <<<CSS
<style>
.brand .ff-brand-logo, .footer-brand .ff-brand-logo { width: auto; max-width: 150px; height: 42px; object-fit: contain; display: block; }
.footer-brand .ff-brand-logo { height: 34px; max-width: 130px; }
.ff-template-live-card { min-width: 0; }
.ff-template-search {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 12px;
  margin-bottom: 20px;
}
.ff-template-search input {
  width: 100%;
  border: 1px solid #d8d2c4;
  border-radius: 3px;
  padding: 12px 14px;
  font-family: 'Work Sans', sans-serif;
  font-size: 0.94rem;
  color: var(--ink);
  background: var(--cream);
}
.ff-template-actions,
.ff-template-chips,
.ff-template-farm-meta,
.ff-template-farm-line {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
.ff-template-actions { margin: 0 0 36px; }
.confirm-btn.is-outline {
  background: transparent;
  color: var(--deep-forest);
  border: 1px solid var(--line-dark);
}
.ff-template-chips a,
.ff-template-farm-meta span {
  border: 1px solid #d8d2c4;
  border-radius: 999px;
  padding: 9px 13px;
  background: var(--cream);
  color: var(--deep-forest);
  font-size: 0.86rem;
  font-weight: 600;
}
.ff-template-farm-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
}
.ff-template-farm-card,
.ff-template-empty {
  border: 1px solid var(--line-dark);
  border-radius: 6px;
  overflow: hidden;
  background: #fff;
}
.ff-template-farm-image {
  display: grid;
  place-items: center;
  aspect-ratio: 1.45;
  background: var(--cream);
  color: var(--gold);
  font-family: 'Fraunces', Georgia, serif;
  font-size: 2.2rem;
}
.ff-template-farm-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.ff-template-farm-body,
.ff-template-empty {
  padding: 18px;
}
.ff-template-farm-body h3 {
  margin: 12px 0 8px;
  color: var(--deep-forest);
  font-size: 1.15rem;
}
.ff-template-farm-body p,
.ff-template-empty p {
  color: #555;
  font-size: 0.92rem;
  margin-bottom: 14px;
}
.ff-template-farm-line {
  color: var(--soil);
  font-size: 0.84rem;
  margin: 0 0 16px;
}
.ff-template-empty h3 {
  color: var(--deep-forest);
  margin-bottom: 8px;
}
@media (max-width: 900px) {
  .sidebar { position: static; top: auto; }
}
@media (max-width: 640px) {
  .wrap { padding: 0 18px; }
  .content { padding: 44px 0 72px; }
  .content-grid {
    display: flex;
    flex-direction: column;
    gap: 32px;
  }
  .form-card { padding: 24px 18px; }
  .ff-template-search,
  .ff-template-farm-grid {
    grid-template-columns: 1fr;
  }
  .ff-template-search .submit-btn,
  .ff-template-actions a {
    width: 100%;
    text-align: center;
  }
}
</style>
CSS;

$planCard = <<<HTML
      <div class="plan-card">
        <div class="plan-label">FreshRoots marketplace</div>
        <div class="plan-price">
          <div class="amount">{$farmCount}</div>
          <div class="period">farms live</div>
        </div>
        <div class="plan-note">More verified Black-owned farms are added as applications are approved.</div>
        <div class="plan-includes">
          <div class="plan-item"><div class="mark">+</div>Browse verified FreshRoots sellers</div>
          <div class="plan-item"><div class="mark">+</div>Search by state, product, or farm name</div>
          <div class="plan-item"><div class="mark">+</div>Use the map to find farms near you</div>
          <div class="plan-item"><div class="mark">+</div>Farmers can apply and list after review</div>
        </div>
      </div>
HTML;

$templateHtml = str_replace(
    [
        '<title>Get Access — FreshRoots by Seller Africa</title>',
        'href="black-farms-home.html#why"',
        'href="black-farms-home.html#how"',
        'href="apply-as-farmer.html"',
        'href="black-farms-home.html"',
        'FreshRoots by Seller Africa',
        'Get direct access to Black-owned farms across the country.',
        'One membership unlocks the full farmer directory, search, and direct ordering. Farms list for free. Your subscription is what keeps the directory verified and growing.',
    ],
    [
        '<title>FreshRoots | ' . e($brandName) . '</title><meta name="description" content="Meet American farmers selling fresh produce through FreshRoots by Seller Africa.">',
        'href="' . e(app_url('freshroots#why')) . '"',
        'href="' . e(app_url('freshroots#how')) . '"',
        'href="' . e(app_url('freshroots')) . '"',
        'href="' . e(app_url('farms')) . '"',
        'FreshRoots by ' . e($brandName),
        'Fresh food, closer to the farm.',
        'Discover American farmers, pasture-raised goods, fresh produce, raw honey, herbs, fruits, vegetables, and specialty farm products in one dedicated FreshRoots marketplace.',
    ],
    $templateHtml
);

$templateHtml = preg_replace(
    '/<div class="brand">\s*<svg\b.*?<\/svg>\s*FreshRoots\s*<\/div>/s',
    '<div class="brand">' . $brandLogoHtml . '<span>FreshRoots</span></div>',
    $templateHtml,
    1
) ?? $templateHtml;
$templateHtml = preg_replace(
    '/<div class="footer-brand">\s*<svg\b.*?<\/svg>\s*FreshRoots by ' . preg_quote(e($brandName), '/') . '\s*<\/div>/s',
    '<div class="footer-brand">' . $brandLogoHtml . '<span>FreshRoots by ' . e($brandName) . '</span></div>',
    $templateHtml,
    1
) ?? $templateHtml;
$templateHtml = preg_replace('/<div class="footer-links">.*?<\/div>/s', $footerLinksHtml, $templateHtml, 1) ?? $templateHtml;
$templateHtml = str_replace('</style>', '</style>' . $templateCss, $templateHtml);
$templateHtml = preg_replace('/<div class="plan-card">.*?<\/div>\s*<div class="sidebar-note">/s', $planCard . "\n\n      <div class=\"sidebar-note\">", $templateHtml, 1) ?? $templateHtml;
$templateHtml = preg_replace('/<div class="form-card" id="signupForm">.*?<script>.*?<\/script>/s', $pageContent, $templateHtml, 1) ?? $templateHtml;

echo $templateHtml;
