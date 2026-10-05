<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

$route = trim((string)($_GET['route'] ?? 'freshroots/legal'), '/');
$templateFile = match ($route) {
    'freshroots/privacy-policy',
    'farm-fresh/privacy-policy', 'farm-privacy-policy' => 'privacy_policy.html',
    'freshroots/terms',
    'farm-fresh/terms', 'farm-terms' => 't&c_template.html',
    default => 'legal_template.html',
};

$templatePath = dirname(__DIR__) . '/farmer/' . $templateFile;
if (!is_file($templatePath)) {
    render_restricted_page(404, 'This link may be broken', 'The FreshRoots legal page template is missing. We have sent this to our technical team to review.', ['route' => $route, 'reason' => 'template_missing']);
    return;
}

$brand = app_branding();
$brandName = (string)($brand['name'] ?? 'Seller Africa');
$brandLogo = trim((string)($brand['logo'] ?? ''));
$brandLogoHtml = $brandLogo !== ''
    ? '<img class="ff-brand-logo" src="' . e($brandLogo) . '" alt="' . e($brandName) . '">'
    : '';

$html = (string)file_get_contents($templatePath);
$legalCss = <<<CSS
<style>
.brand .ff-brand-logo, .footer-brand .ff-brand-logo { width: auto; max-width: 150px; height: 42px; object-fit: contain; display: block; }
.footer-brand .ff-brand-logo { height: 34px; max-width: 130px; }
@media (max-width: 640px) {
  .wrap { padding: 0 18px; }
  .nav-inner { padding: 18px; }
  .content { padding: 44px 0 72px; }
  .toc { position: static; }
  .footer-links { width: 100%; display: grid; gap: 12px; }
}
</style>
CSS;

$html = str_replace(
    [
        'href="black-farms-home.html"',
        'href="black-farms-home.html#why"',
        'href="black-farms-home.html#how"',
        'href="terms-and-conditions.html"',
        'href="privacy-policy.html"',
        'href="legal.html"',
        'href="apply-as-farmer.html"',
        'FreshRoots by Seller Africa',
    ],
    [
        'href="' . e(app_url('freshroots')) . '"',
        'href="' . e(app_url('freshroots#why')) . '"',
        'href="' . e(app_url('freshroots#how')) . '"',
        'href="' . e(app_url('freshroots/terms')) . '"',
        'href="' . e(app_url('freshroots/privacy-policy')) . '"',
        'href="' . e(app_url('freshroots/legal')) . '"',
        'href="' . e(app_url('freshroots')) . '"',
        'FreshRoots by ' . e($brandName),
    ],
    $html
);

$html = preg_replace(
    '/<div class="brand">\s*<svg\b.*?<\/svg>\s*FreshRoots\s*<\/div>/s',
    '<div class="brand">' . $brandLogoHtml . '<span>FreshRoots</span></div>',
    $html,
    1
) ?? $html;
$html = preg_replace(
    '/<div class="footer-brand">\s*<svg\b.*?<\/svg>\s*FreshRoots by ' . preg_quote(e($brandName), '/') . '\s*<\/div>/s',
    '<div class="footer-brand">' . $brandLogoHtml . '<span>FreshRoots by ' . e($brandName) . '</span></div>',
    $html,
    1
) ?? $html;
$html = str_replace('</style>', '</style>' . $legalCss, $html);

echo $html;
