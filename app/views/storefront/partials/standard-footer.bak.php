<?php
$footerClass = trim((string)($footerClass ?? 'mtfooter__area bg-dark text-white py-4') . ' sa-standard-footer');
$footerGridClass = trim((string)($footerGridClass ?? 'row gy-3 align-items-start') . ' sa-standard-footer-grid');
$footerBrandClass = (string)($footerBrandClass ?? '');
$footerLinksClass = (string)($footerLinksClass ?? '');
$footerHeadingTag = in_array(($footerHeadingTag ?? 'strong'), ['h3', 'strong'], true) ? $footerHeadingTag : 'strong';
$footerBrandName = trim((string)($brandName ?? 'Seller Africa')) ?: 'Seller Africa';
$footerColumns = [
    'Community' => [
        ['Join Our Community', app_url('join-community')],
        ['Rewards Program', app_url('rewards-program')],
        ['Become a Vendor', app_url('become-a-vendor')],
        ['Become a Distribution Partner', app_url('distribution-partner')],
        ['Vendor Resources', app_url('vendor-resources')],
        ['Help Center', app_url('knowledge-base')],
    ],
    'Company' => [
        ['About Seller Africa', app_url('about')],
        ['Our Vendors', app_url('vendors')],
        ['Careers', app_url('careers')],
        ['Blog & News', app_url('blog-news')],
        ['Gallery', app_url('gallery')],
        ['Contact Us', app_url('contact')],
    ],
    'Support' => [
        ['FAQs', app_url('faqs')],
        ['Shipping & Delivery', app_url('shipping-delivery')],
        ['Returns & Refunds', app_url('returns-refunds')],
        ['Order Tracking', app_url('track-order')],
        ['Customer Support', app_url('customer-support')],
    ],
    'Legal' => [
        ['Terms & Conditions', app_url('terms')],
        ['Privacy Policy', app_url('privacy-policy')],
        ['Vendor Agreement', app_url('vendor-agreement')],
        ['Partner Agreement', app_url('partner-agreement')],
        ['Cookie Policy', app_url('cookie-policy')],
    ],
];
?>
<style>
  .sa-standard-footer { background:#004f3d; color:#fff; padding:52px 0; border-top:3px solid #d8951a; }
  .sa-standard-footer-grid { width:min(var(--max,1536px),100% - var(--gutter,clamp(24px,6vw,110px))); margin:auto; display:grid; grid-template-columns:minmax(220px,1.1fr) repeat(4,minmax(150px,1fr)) !important; gap:34px; }
  .sa-standard-footer a { color:rgba(255,255,255,.86); transition:color .15s ease; }
  .sa-standard-footer a:hover { color:#d8951a; }
  .sa-standard-footer ul { list-style:none; margin:14px 0 0; padding:0; display:grid; gap:12px; }
  .sa-standard-footer strong,.sa-standard-footer h3 { color:#fff; letter-spacing:0; font-size:15px; text-transform:uppercase; letter-spacing:.04em; opacity:.85; }
  .sa-standard-footer > .sa-standard-footer-grid > div:first-child strong { display:block; font-size:22px; text-transform:none; letter-spacing:-0.01em; opacity:1; margin-bottom:4px; }
  .sa-standard-footer p { color:rgba(255,255,255,.78); line-height:1.65; }
  @media(max-width:1100px) { .sa-standard-footer-grid { grid-template-columns:repeat(2,minmax(0,1fr)) !important; } }
  @media(max-width:640px) { .sa-standard-footer-grid { grid-template-columns:1fr !important; } }
  .sa-footer-social { margin-top:22px; }
  .sa-footer-social__label { display:block; margin-bottom:12px; color:rgba(255,255,255,.7); font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
  .sa-footer-social__icons { display:flex; flex-direction:row; align-items:center; gap:12px; }
  .sa-footer-social__icons a { display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border-radius:999px; background:rgba(255,255,255,.1); color:#fff; flex-shrink:0; }
  .sa-footer-social__icons a:hover { background:#d8951a; color:#fff; }
  .sa-footer-social__icons svg { width:18px; height:18px; }
  .sa-footer-stores { margin-top:24px; }
  .sa-footer-stores__label { display:block; margin-bottom:12px; color:rgba(255,255,255,.7); font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
  .sa-footer-stores__badges { display:flex; flex-wrap:wrap; gap:10px; }
  .sa-footer-store-badge { display:inline-flex; align-items:center; gap:8px; padding:9px 16px; border-radius:10px; background:#000; color:#fff; }
  .sa-footer-store-badge svg { width:20px; height:20px; flex-shrink:0; }
  .sa-footer-store-badge span { font-size:13px; font-weight:600; white-space:nowrap; }
</style>
<footer class="<?= e($footerClass) ?>">
  <div class="<?= e($footerGridClass) ?>">
    <div class="<?= e($footerBrandClass) ?>">
      <strong><?= e($footerBrandName) ?></strong>
      <p>Seller Africa is building the bridge between African producers and global consumers. Through our marketplace, logistics network, and U.S. fulfillment services, we help businesses reach new markets while delivering authentic African and Caribbean products to customers worldwide.</p>
      <div class="sa-footer-social" aria-label="Follow us on social media">
        <span class="sa-footer-social__label">Follow us on social media</span>
        <div class="sa-footer-social__icons">
          <a href="https://www.instagram.com/sellerafrica_?igsh=NW80bjd2d3owdmN5" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.24 2.23.41.56.21.96.47 1.38.89.42.42.68.82.89 1.38.17.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.24 1.8-.41 2.23-.21.56-.47.96-.89 1.38-.42.42-.82.68-1.38.89-.42.17-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.24-2.23-.41-.56-.21-.96-.47-1.38-.89-.42-.42-.68-.82-.89-1.38-.17-.42-.36-1.06-.41-2.23-.06-1.27-.07-1.65-.07-4.85s.01-3.58.07-4.85c.05-1.17.24-1.8.41-2.23.21-.56.47-.96.89-1.38.42-.42.82-.68 1.38-.89.42-.17 1.06-.36 2.23-.41 1.27-.06 1.65-.07 4.85-.07M12 0C8.74 0 8.33.01 7.05.07c-1.28.06-2.15.26-2.91.56-.79.31-1.46.72-2.13 1.39-.67.67-1.08 1.34-1.39 2.13-.3.76-.5 1.63-.56 2.91C.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.06 1.28.26 2.15.56 2.91.31.79.72 1.46 1.39 2.13.67.67 1.34 1.08 2.13 1.39.76.3 1.63.5 2.91.56C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c1.28-.06 2.15-.26 2.91-.56.79-.31 1.46-.72 2.13-1.39.67-.67 1.08-1.34 1.39-2.13.3-.76.5-1.63.56-2.91.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.06-1.28-.26-2.15-.56-2.91-.31-.79-.72-1.46-1.39-2.13-.67-.67-1.34-1.08-2.13-1.39-.76-.3-1.63-.5-2.91-.56C15.67.01 15.26 0 12 0zm0 5.84A6.16 6.16 0 1 0 12 18.16 6.16 6.16 0 0 0 12 5.84zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.41-10.85a1.44 1.44 0 1 1-2.88 0 1.44 1.44 0 0 1 2.88 0z"/></svg></a>
          <a href="https://www.facebook.com/share/14jbmg2i7co/" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.99 3.66 9.13 8.44 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.77l-.44 2.89h-2.33v6.99C18.34 21.13 22 16.99 22 12z"/></svg></a>
          <a href="https://x.com/sellerafrica" target="_blank" rel="noopener noreferrer" aria-label="X (Twitter)"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.9 1.5h3.68l-8.04 9.19L24 22.5h-7.4l-5.8-7.58-6.64 7.58H.47l8.6-9.83L0 1.5h7.6l5.24 6.93zm-1.3 18.9h2.04L6.5 3.48H4.3z"/></svg></a>
          <a href="https://www.tiktok.com/@seller_africa?_r=1&_t=ZS-98ZoOiQNGlB" target="_blank" rel="noopener noreferrer" aria-label="TikTok"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.6 5.82c-.9-.98-1.4-2.26-1.4-3.58h-3.1v14.3c0 1.55-1.26 2.8-2.81 2.8a2.81 2.81 0 0 1 0-5.62c.31 0 .61.05.89.13V10.7a6 6 0 0 0-.89-.07 5.98 5.98 0 0 0 0 11.96 5.98 5.98 0 0 0 5.98-5.98V8.4a9.3 9.3 0 0 0 5.43 1.74V6.98a5.6 5.6 0 0 1-4.1-1.16z"/></svg></a>
          <a href="https://youtube.com/@sellerafrica?si=Uxzx1dh-kLULr9YA" target="_blank" rel="noopener noreferrer" aria-label="YouTube"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M23.5 6.2a3.02 3.02 0 0 0-2.12-2.14C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.38.56A3.02 3.02 0 0 0 .5 6.2 31.6 31.6 0 0 0 0 12a31.6 31.6 0 0 0 .5 5.8 3.02 3.02 0 0 0 2.12 2.14C4.5 20.5 12 20.5 12 20.5s7.5 0 9.38-.56a3.02 3.02 0 0 0 2.12-2.14A31.6 31.6 0 0 0 24 12a31.6 31.6 0 0 0-.5-5.8zM9.6 15.6V8.4l6.3 3.6z"/></svg></a>
          <a href="https://www.linkedin.com/company/seller-africa/" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.45 20.45h-3.55v-5.57c0-1.33-.02-3.03-1.85-3.03-1.85 0-2.14 1.45-2.14 2.94v5.66H9.36V9h3.41v1.56h.05c.47-.9 1.63-1.85 3.36-1.85 3.59 0 4.25 2.36 4.25 5.44zM5.34 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12zM7.12 20.45H3.56V9h3.56z"/></svg></a>
        </div>
      </div>
      <div class="sa-footer-stores">
        <span class="sa-footer-stores__label">Coming soon</span>
        <div class="sa-footer-stores__badges">
          <span class="sa-footer-store-badge">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.05 12.5c-.03-2.4 1.96-3.55 2.05-3.6-1.12-1.63-2.86-1.86-3.48-1.89-1.48-.15-2.9.87-3.65.87-.76 0-1.92-.85-3.16-.83-1.62.02-3.13.95-3.97 2.4-1.7 2.94-.43 7.3 1.22 9.68.8 1.16 1.76 2.47 3.02 2.42 1.22-.05 1.68-.79 3.15-.79 1.47 0 1.88.79 3.16.76 1.31-.02 2.14-1.18 2.94-2.34.93-1.34 1.31-2.64 1.33-2.71-.03-.01-2.55-.98-2.58-3.88zM14.6 5.5c.66-.8 1.11-1.92 .99-3.03-.95.04-2.13.65-2.82 1.44-.62.7-1.16 1.83-1.02 2.91 1.05.08 2.13-.53 2.85-1.32z"/></svg>
            <span>App Store</span>
          </span>
          <span class="sa-footer-store-badge">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.6 2.3c-.34.35-.53.9-.53 1.6v16.2c0 .7.19 1.25.53 1.6l.09.08 9.08-9.08v-.22L3.69 2.22z"/><path d="M15.8 13.7l-3.03-3.02V10.5l3.03-3.02.07.04 3.6 2.04c1.03.58 1.03 1.54 0 2.12l-3.6 2.04z" opacity=".85"/><path d="M15.8 13.7l-3.1-3.1-9.11 9.11c.34.36.9.4 1.53.05z" opacity=".7"/><path d="M15.8 7.28l-9.11-5.16c-.63-.36-1.19-.31-1.53.05l9.11 9.11z" opacity=".9"/></svg>
            <span>Google Play</span>
          </span>
        </div>
      </div>
    </div>
    <?php foreach ($footerColumns as $heading => $links): ?>
      <div class="<?= e($footerLinksClass) ?>">
        <<?= $footerHeadingTag ?>><?= e($heading) ?></<?= $footerHeadingTag ?>>
        <ul>
          <?php foreach ($links as [$label, $url]): ?>
            <li><a href="<?= e($url) ?>"><?= e($label) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
</footer>
