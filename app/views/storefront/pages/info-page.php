<?php
$brand = app_branding();
$brandName = (string)($brand['name'] ?? 'Seller Africa');
$cartCount = (int)($cartCount ?? 0);
$logo = trim((string)($brand['logo'] ?? ''));
$pageData = is_array($pageData ?? null) ? $pageData : [];
$titleText = (string)($pageData['title'] ?? 'Seller Africa');
$eyebrow = (string)($pageData['eyebrow'] ?? 'Seller Africa');
$subtitle = (string)($pageData['subtitle'] ?? '');
$intro = array_values(array_filter((array)($pageData['intro'] ?? []), 'is_string'));
$heroImage = trim((string)($pageData['image'] ?? ''));
$primaryButton = is_array($pageData['primaryButton'] ?? null) ? $pageData['primaryButton'] : null;
$secondaryButton = is_array($pageData['secondaryButton'] ?? null) ? $pageData['secondaryButton'] : null;
$sections = is_array($pageData['sections'] ?? null) ? $pageData['sections'] : [];
$icon = static fn (string $d): string => '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $d . '</svg>';
$bag = $icon('<path d="M7 8V6a5 5 0 0 1 10 0v2"/><path d="M5 8h14l-1 13H6L5 8Z"/>');
$renderButton = static function (?array $button, string $class): string {
    if (!$button) {
        return '';
    }
    $label = (string)($button['label'] ?? '');
    $url = (string)($button['url'] ?? '#');
    if ($label === '') {
        return '';
    }
    return '<a class="' . e($class) . '" href="' . e($url) . '">' . e($label) . '</a>';
};
?>
<style>
  .sa-info{--green:#00684f;--mint:#e8fbf6;--ink:#111827;--muted:#667276;--line:#e2e8e6;--cream:#fffaf2;--max:1536px;--gutter:clamp(24px,6vw,110px);font-family:"DM Sans",system-ui,sans-serif;color:var(--ink);background:#fff}.sa-info *{box-sizing:border-box}.sa-info a{text-decoration:none;color:inherit}.sa-info svg{width:22px;height:22px}.sa-info-ann{min-height:36px;display:grid;place-items:center;background:var(--mint);color:#153d35;font-weight:850;text-align:center;padding:6px 14px}.sa-info-nav{width:min(var(--max),100% - var(--gutter));min-height:92px;margin:auto;display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:30px}.sa-info-logo img{max-width:174px;max-height:60px;display:block}.sa-info-links{display:flex;justify-content:center;gap:clamp(18px,3vw,46px);color:#5f6a6d;font-weight:800}.sa-info-links .is-active{color:var(--green);border-bottom:2px solid var(--green);padding-bottom:5px}.sa-info-actions{display:flex;align-items:center;gap:12px}.sa-info-cart,.sa-info-btn{height:46px;border:1px solid var(--green);border-radius:8px;background:#fff;color:var(--green);display:inline-flex;align-items:center;justify-content:center}.sa-info-cart{width:48px;position:relative}.sa-info-cart span{position:absolute;right:-6px;top:-6px;min-width:20px;height:20px;border-radius:99px;background:var(--green);color:#fff;display:grid;place-items:center;font-size:11px;font-weight:900}.sa-info-btn{padding:0 18px;font-weight:850}.sa-info-btn.is-solid{background:var(--green);color:#fff}.sa-info-wrap{width:min(var(--max),100% - var(--gutter));margin:auto}.sa-info-hero{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,.72fr);gap:clamp(34px,7vw,90px);align-items:center;padding:clamp(58px,8vw,112px) 0}.sa-info-hero.no-image{grid-template-columns:1fr}.sa-info-eyebrow{margin:0 0 12px;color:var(--green);font-size:13px;font-weight:900;text-transform:uppercase}.sa-info h1,.sa-info h2,.sa-info h3{letter-spacing:0}.sa-info h1{max-width:900px;margin:0;font-size:clamp(42px,6vw,76px);line-height:1;font-weight:900}.sa-info h2{margin:0;font-size:clamp(30px,4vw,48px);line-height:1.08;font-weight:900}.sa-info h3{margin:0 0 10px;font-size:23px}.sa-info p{color:var(--muted);line-height:1.68;font-size:17px}.sa-info-hero p{font-size:19px}.sa-info-media img{width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:18px;box-shadow:0 24px 70px rgba(17,24,39,.12);background:#f8faf9}.sa-info-actions-row{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}.sa-info-section{padding:clamp(48px,7vw,86px) 0}.sa-info-section.is-soft{background:var(--cream)}.sa-info-section-head{max-width:780px;margin-bottom:28px}.sa-info-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.sa-info-grid.is-two{grid-template-columns:repeat(2,minmax(0,1fr))}.sa-info-card{border:1px solid var(--line);border-radius:12px;background:#fff;padding:25px;box-shadow:0 14px 38px rgba(17,24,39,.06)}.sa-info-card ul,.sa-info-list{margin:16px 0 0;padding:0;list-style:none;display:grid;gap:12px}.sa-info-card li,.sa-info-list li{position:relative;padding-left:26px;color:var(--muted);line-height:1.55}.sa-info-card li:before,.sa-info-list li:before{content:"";position:absolute;left:0;top:.68em;width:9px;height:9px;border-radius:999px;background:var(--green);box-shadow:0 0 0 5px rgba(0,104,79,.11)}.sa-info-steps{counter-reset:step;display:grid;gap:18px}.sa-info-step{counter-increment:step;border:1px solid var(--line);border-radius:12px;background:#fff;padding:25px;display:grid;grid-template-columns:auto 1fr;gap:18px;align-items:start}.sa-info-step:before{content:counter(step);width:42px;height:42px;border-radius:999px;background:var(--green);color:#fff;display:grid;place-items:center;font-weight:900}.sa-info-legal{display:grid;gap:18px;counter-reset:legal}.sa-info-legal article{counter-increment:legal;border:1px solid var(--line);border-radius:12px;background:#fff;padding:25px;box-shadow:0 14px 38px rgba(17,24,39,.05)}.sa-info-legal h3{display:flex;gap:12px;align-items:flex-start}.sa-info-legal h3:before{content:counter(legal) ".";color:var(--green);font-weight:900}.sa-info-legal p{margin:0 0 14px}.sa-info-legal p:last-child{margin-bottom:0}.sa-info-faq{display:grid;gap:12px}.sa-info-faq details{border:1px solid var(--line);border-radius:10px;background:#fff;padding:0 20px}.sa-info-faq summary{min-height:62px;display:flex;align-items:center;justify-content:space-between;gap:18px;cursor:pointer;font-weight:900;list-style:none}.sa-info-faq summary::-webkit-details-marker{display:none}.sa-info-faq p{margin:0 0 20px}.sa-info-contact{border:1px solid var(--line);border-radius:12px;background:#f8fffb;padding:25px}.sa-info-contact a{color:var(--green);font-weight:900}.sa-info-cta{background:var(--green);color:#fff;padding:clamp(58px,7vw,90px) 0}.sa-info-cta h2,.sa-info-cta p{color:#fff}.sa-info-footer{background:#004f3d;color:#fff;padding:52px 0}.sa-info-footer-grid{width:min(var(--max),100% - var(--gutter));margin:auto;display:grid;grid-template-columns:minmax(220px,1.1fr) repeat(4,minmax(150px,1fr));gap:34px}@media(max-width:1100px){.sa-info-nav{grid-template-columns:1fr auto}.sa-info-links{grid-column:1/-1;justify-content:flex-start;overflow-x:auto}.sa-info-actions .sa-info-btn{display:none}.sa-info-hero,.sa-info-footer-grid{grid-template-columns:1fr}.sa-info-grid,.sa-info-grid.is-two{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:680px){.sa-info{--gutter:28px}.sa-info-logo img{max-width:148px}.sa-info-links{display:none}.sa-info-hero{padding-top:48px}.sa-info-grid,.sa-info-grid.is-two{grid-template-columns:1fr}.sa-info-actions-row{display:grid}.sa-info-btn{width:100%}.sa-info-step{grid-template-columns:1fr}}
</style>
<div class="sa-info">
  <div class="sa-info-ann"><strong>Connecting Africa to global markets</strong></div>
  <header>
    <nav class="sa-info-nav">
      <a class="sa-info-logo" href="<?= e(app_url('store')) ?>"><?php if ($logo !== ''): ?><img src="<?= e($logo) ?>" alt="<?= e($brandName) ?>"><?php else: ?><strong><?= e($brandName) ?></strong><?php endif; ?></a>
      <div class="sa-info-links">
        <a href="<?= e(app_url('store')) ?>">Home</a>
        <a href="<?= e(app_url('shop')) ?>">Marketplace</a>
        <a href="<?= e(app_url('vendors')) ?>">Our Vendors</a>
        <a href="<?= e(app_url('about')) ?>">About Us</a>
        <a href="<?= e(app_url('contact')) ?>">Contact Us</a>
      </div>
      <div class="sa-info-actions">
        <a class="sa-info-cart" href="<?= e(app_url('cart')) ?>"><?= $bag ?><span><?= number_format($cartCount) ?></span></a>
        <a class="sa-info-btn" href="<?= e(app_url('login')) ?>">Login</a>
        <a class="sa-info-btn is-solid" href="<?= e(app_url('buyer/register')) ?>">Register</a>
      </div>
    </nav>
  </header>
  <main>
    <section class="sa-info-wrap sa-info-hero<?= $heroImage === '' ? ' no-image' : '' ?>">
      <div>
        <div class="sa-info-eyebrow"><?= e($eyebrow) ?></div>
        <h1><?= e($titleText) ?></h1>
        <?php if ($subtitle !== ''): ?><p><strong><?= e($subtitle) ?></strong></p><?php endif; ?>
        <?php foreach ($intro as $paragraph): ?><p><?= e($paragraph) ?></p><?php endforeach; ?>
        <?php if ($primaryButton || $secondaryButton): ?>
          <div class="sa-info-actions-row">
            <?= $renderButton($primaryButton, 'sa-info-btn is-solid') ?>
            <?= $renderButton($secondaryButton, 'sa-info-btn') ?>
          </div>
        <?php endif; ?>
      </div>
      <?php if ($heroImage !== ''): ?><div class="sa-info-media"><img src="<?= e($heroImage) ?>" alt="<?= e($titleText) ?>" loading="lazy"></div><?php endif; ?>
    </section>
    <?php $sectionIndex = 0; foreach ($sections as $section): $sectionIndex++; $type = (string)($section['type'] ?? 'text'); ?>
      <section class="sa-info-section<?= $sectionIndex % 2 === 0 ? ' is-soft' : '' ?>">
        <div class="sa-info-wrap">
          <div class="sa-info-section-head">
            <?php if (!empty($section['eyebrow'])): ?><div class="sa-info-eyebrow"><?= e((string)$section['eyebrow']) ?></div><?php endif; ?>
            <h2><?= e((string)($section['title'] ?? '')) ?></h2>
            <?php foreach ((array)($section['body'] ?? []) as $paragraph): ?><p><?= e((string)$paragraph) ?></p><?php endforeach; ?>
          </div>
          <?php if ($type === 'list'): ?>
            <ul class="sa-info-list">
              <?php foreach ((array)($section['items'] ?? []) as $item): ?><li><?= e((string)$item) ?></li><?php endforeach; ?>
            </ul>
          <?php elseif ($type === 'cards'): ?>
            <div class="sa-info-grid<?= count((array)($section['items'] ?? [])) <= 2 ? ' is-two' : '' ?>">
              <?php foreach ((array)($section['items'] ?? []) as $item): ?>
                <article class="sa-info-card"><h3><?= e((string)($item['title'] ?? '')) ?></h3><?php foreach ((array)($item['body'] ?? []) as $paragraph): ?><p><?= e((string)$paragraph) ?></p><?php endforeach; ?><?php if (!empty($item['items'])): ?><ul><?php foreach ((array)$item['items'] as $li): ?><li><?= e((string)$li) ?></li><?php endforeach; ?></ul><?php endif; ?><?php if (is_array($item['button'] ?? null)): ?><div class="sa-info-actions-row"><?= $renderButton($item['button'], 'sa-info-btn is-solid') ?></div><?php endif; ?></article>
              <?php endforeach; ?>
            </div>
          <?php elseif ($type === 'steps'): ?>
            <div class="sa-info-steps">
              <?php foreach ((array)($section['items'] ?? []) as $item): ?>
                <article class="sa-info-step"><div><h3><?= e((string)($item['title'] ?? '')) ?></h3><?php foreach ((array)($item['body'] ?? []) as $paragraph): ?><p><?= e((string)$paragraph) ?></p><?php endforeach; ?><?php if (is_array($item['button'] ?? null)): ?><div class="sa-info-actions-row"><?= $renderButton($item['button'], 'sa-info-btn is-solid') ?></div><?php endif; ?></div></article>
              <?php endforeach; ?>
            </div>
          <?php elseif ($type === 'legal'): ?>
            <div class="sa-info-legal">
              <?php foreach ((array)($section['items'] ?? []) as $item): ?>
                <article><h3><?= e((string)($item['title'] ?? '')) ?></h3><?php foreach ((array)($item['body'] ?? []) as $paragraph): ?><p><?= e((string)$paragraph) ?></p><?php endforeach; ?></article>
              <?php endforeach; ?>
            </div>
          <?php elseif ($type === 'faq'): ?>
            <div class="sa-info-faq">
              <?php foreach ((array)($section['items'] ?? []) as $item): ?><details><summary><?= e((string)($item['q'] ?? '')) ?><span>+</span></summary><p><?= e((string)($item['a'] ?? '')) ?></p></details><?php endforeach; ?>
            </div>
          <?php elseif ($type === 'contact'): ?>
            <div class="sa-info-contact">
              <?php foreach ((array)($section['items'] ?? []) as $item): ?><p><strong><?= e((string)($item['label'] ?? '')) ?></strong><br><a href="<?= e((string)($item['url'] ?? '#')) ?>"><?= e((string)($item['value'] ?? '')) ?></a></p><?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>
    <?php endforeach; ?>
    <?php if (!empty($pageData['cta'])): $cta = (array)$pageData['cta']; ?>
      <section class="sa-info-cta">
        <div class="sa-info-wrap">
          <h2><?= e((string)($cta['title'] ?? '')) ?></h2>
          <?php foreach ((array)($cta['body'] ?? []) as $paragraph): ?><p><?= e((string)$paragraph) ?></p><?php endforeach; ?>
          <div class="sa-info-actions-row"><?= $renderButton(is_array($cta['button'] ?? null) ? $cta['button'] : null, 'sa-info-btn') ?></div>
        </div>
      </section>
    <?php endif; ?>
  </main>
  <?php $footerClass = 'sa-info-footer'; $footerGridClass = 'sa-info-footer-grid'; include __DIR__ . '/../partials/standard-footer.php'; ?>
  <?= app_chat_widget_embed() ?>
</div>
