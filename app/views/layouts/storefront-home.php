<?php
$brand = app_branding();
$brandName = (string)($brand['name'] ?? 'Seller Africa');
$favicon = \App\SeoService::favicon();
$title = (string)($title ?? $brandName . ' Storefront');
$metaDescription = (string)($metaDescription ?? 'African and Caribbean Marketplace');
$canonical = (string)($canonical ?? app_url(($page ?? 'home') === 'shop' ? 'shop' : 'store'));
$pageClass = 'sa-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string)($page ?? 'home')) . '-page';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <link rel="icon" href="<?= e($favicon) ?>">
    <link rel="apple-touch-icon" href="<?= e($favicon) ?>">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <?php if (!empty($brand['logo'])): ?>
        <meta property="og:image" content="<?= e((string)$brand['logo']) ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php if (in_array($page ?? 'home', ['home', 'shop', 'vendors', 'about', 'contact'], true)): ?>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?= function_exists('app_marketing_pixel_code') ? app_marketing_pixel_code('head') : '' ?>
</head>
<body class="<?= e($pageClass) ?>">
    <?= function_exists('app_marketing_pixel_code') ? app_marketing_pixel_code('body') : '' ?>
    <?= $content ?? '' ?>
    <?php if (in_array($page ?? 'home', ['home', 'shop'], true)): ?>
    <link rel="stylesheet" href="<?= e(asset('css/storefront-refresh.css?v=20260928')) ?>">
    <?php endif; ?>
    <?php if (in_array($page ?? '', ['vendors', 'about', 'contact'], true)): ?>
    <link rel="stylesheet" href="<?= e(asset('css/public-pages-refresh.css?v=20260929')) ?>">
    <?php endif; ?>
    <style>
      .sa-public-menu-toggle {
        display: none;
        width: 46px;
        height: 44px;
        border: 1px solid #006b52;
        border-radius: .625rem;
        background: #fff;
        color: #006b52;
        place-items: center;
        padding: 0;
      }

      .sa-public-menu-toggle span,
      .sa-public-menu-toggle::before,
      .sa-public-menu-toggle::after {
        content: "";
        display: block;
        width: 18px;
        height: 2px;
        margin: 4px auto;
        border-radius: 999px;
        background: currentColor;
      }

      @media (max-width: 1100px) {
        .sa-about-nav,
        .sa-contact-nav,
        .sa-pricing-nav,
        .sa-help-nav,
        .sa-vendors-nav,
        .sa-info-nav {
          grid-template-columns: auto auto !important;
        }

        .sa-about-links,
        .sa-contact-links,
        .sa-pricing-links,
        .sa-help-links,
        .sa-vendors-links,
        .sa-info-links {
          display: none !important;
          grid-column: 1 / -1 !important;
          width: 100% !important;
          flex-direction: column !important;
          align-items: stretch !important;
          gap: 0 !important;
          padding: 10px 0 18px !important;
          overflow: visible !important;
        }

        .sa-public-nav-open .sa-about-links,
        .sa-public-nav-open .sa-contact-links,
        .sa-public-nav-open .sa-pricing-links,
        .sa-public-nav-open .sa-help-links,
        .sa-public-nav-open .sa-vendors-links,
        .sa-public-nav-open .sa-info-links {
          display: flex !important;
        }

        .sa-about-links a,
        .sa-contact-links a,
        .sa-pricing-links a,
        .sa-help-links a,
        .sa-vendors-links a,
        .sa-info-links a {
          padding: 14px 0 !important;
          border-top: 1px solid #eef0f2 !important;
          border-bottom: 0 !important;
        }

        .sa-about-actions .sa-about-btn,
        .sa-contact-actions .sa-contact-btn,
        .sa-pricing-actions .sa-pricing-btn,
        .sa-help-actions .sa-help-btn,
        .sa-vendors-actions .sa-vendors-btn,
        .sa-info-actions .sa-info-btn {
          display: none !important;
        }

        .sa-public-menu-toggle {
          display: inline-grid;
        }
      }
    </style>
    <script>
      (() => {
        const headers = [
          ['.sa-about-nav', '.sa-about-links', '.sa-about-actions'],
          ['.sa-contact-nav', '.sa-contact-links', '.sa-contact-actions'],
          ['.sa-pricing-nav', '.sa-pricing-links', '.sa-pricing-actions'],
          ['.sa-help-nav', '.sa-help-links', '.sa-help-actions'],
          ['.sa-vendors-nav', '.sa-vendors-links', '.sa-vendors-actions'],
          ['.sa-info-nav', '.sa-info-links', '.sa-info-actions']
        ];

        headers.forEach(([navSelector, linksSelector, actionsSelector]) => {
          document.querySelectorAll(navSelector).forEach((nav) => {
            const links = nav.querySelector(linksSelector);
            const actions = nav.querySelector(actionsSelector);
            if (!links || !actions || actions.querySelector('.sa-public-menu-toggle')) return;

            const button = document.createElement('button');
            button.className = 'sa-public-menu-toggle';
            button.type = 'button';
            button.setAttribute('aria-controls', links.id || `${linksSelector.slice(1)}-menu`);
            button.setAttribute('aria-expanded', 'false');
            button.setAttribute('aria-label', 'Toggle navigation menu');
            button.innerHTML = '<span></span>';
            if (!links.id) links.id = button.getAttribute('aria-controls');
            actions.appendChild(button);

            button.addEventListener('click', () => {
              const open = nav.classList.toggle('sa-public-nav-open');
              button.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
          });
        });
      })();
    </script>
<?php if (($page ?? '') === 'shop') require __DIR__.'/../partials/nigeria-independence.php'; ?>
</body>
</html>
