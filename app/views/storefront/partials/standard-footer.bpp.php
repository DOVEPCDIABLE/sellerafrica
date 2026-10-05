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
</style>
<footer class="<?= e($footerClass) ?>">
  <div class="<?= e($footerGridClass) ?>">
    <div class="<?= e($footerBrandClass) ?>">
      <strong><?= e($footerBrandName) ?></strong>
      <p>Seller Africa is building the bridge between African producers and global consumers. Through our marketplace, logistics network, and U.S. fulfillment services, we help businesses reach new markets while delivering authentic African and Caribbean products to customers worldwide.</p>
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
