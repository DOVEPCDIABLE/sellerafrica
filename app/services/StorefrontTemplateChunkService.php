<?php

declare(strict_types=1);

namespace App;

final class StorefrontTemplateChunkService
{
    private ?string $html = null;
    private int $controlCounter = 0;

    public function __construct(private string $template = 'index.html')
    {
    }

    public function head(string $title, string $description = '', array $meta = []): string
    {
        $html = $this->load();
        if (!preg_match('/\A.*?<\/head>/is', $html, $matches)) {
            return '<!DOCTYPE html><html lang="en"><head><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title></head>';
        }

        $head = $this->rewrite($matches[0]);
        $head = SeoService::applyToHead($head, $title, $description, $meta);

        $fixesPath = dirname(__DIR__, 2) . '/public/assets/css/template-storefront-fixes.css';
        $fixesVersion = is_file($fixesPath) ? (string)filemtime($fixesPath) : '1';

        $head = str_ireplace(
            '</head>',
            StorefrontPerformanceService::asyncStylesheetTag(app_url('assets/css/template-storefront-fixes.css?v=' . $fixesVersion)) . "\n"
                . (\function_exists('app_marketing_pixel_code') ? \app_marketing_pixel_code('head') : '')
                . '</head>',
            $head
        );

        return StorefrontPerformanceService::optimizeHeadHtml($head);
    }

    public function beforeMain(): string
    {
        $html = $this->load();
        $start = strpos($html, '<body');
        $main = strpos($html, '<main>');
        if ($start === false || $main === false) {
            return '';
        }

        return $this->rewrite(substr($html, $start, $main - $start));
    }

    public function section(string $key): string
    {
        if ($key === 'newsletter') {
            return '';
        }

        $sections = $this->sections();
        $html = $this->rewrite($sections[$key] ?? '');

        return $key === 'hero'
            ? StorefrontPerformanceService::prioritizeFirstImage($this->stripTemplateHeroFallbackMedia($html))
            : $html;
    }

    public function footer(): string
    {
        $brand = app_branding();
        $brandName = htmlspecialchars((string)$brand['name'], ENT_QUOTES, 'UTF-8');
        $description = 'Seller Africa is building the bridge between African producers and global consumers. Through our African and Caribbean Marketplace, logistics network, and U.S. fulfillment services, we help businesses reach new markets while delivering trusted marketplace access to customers worldwide.';
        $columns = [
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
        $columnsHtml = '';
        foreach ($columns as $heading => $links) {
            $linksHtml = '';
            foreach ($links as [$label, $url]) {
                $linksHtml .= '<li class="mb-1"><a class="text-white text-decoration-none" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></li>';
            }
            $columnsHtml .= '<div class="col-sm-6 col-lg"><strong>' . htmlspecialchars((string)$heading, ENT_QUOTES, 'UTF-8') . '</strong><ul class="list-unstyled mb-0 mt-2">' . $linksHtml . '</ul></div>';
        }

        return '<footer class="mtfooter__area bg-dark text-white py-4">'
            . '<div class="container">'
            . '<div class="row gy-3 align-items-start">'
            . '<div class="col-lg-3"><strong>' . $brandName . '</strong><p class="mb-0 mt-2">' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p></div>'
            . $columnsHtml
            . '</div>'
            . '<div class="row mt-3 pt-3 border-top border-secondary"><div class="col-12"><small>&copy; ' . date('Y') . ' ' . $brandName . '. All rights reserved.</small></div></div>'
            . '</div>'
            . '</footer>';
    }

    public function scripts(array $payload): string
    {
        $html = $this->load();
        $footerEnd = strpos($html, '</footer>');
        $bodyEnd = strpos($html, '</body>');
        $scripts = ($footerEnd !== false && $bodyEnd !== false)
            ? substr($html, $footerEnd + 9, $bodyEnd - ($footerEnd + 9))
            : '';

        $scripts = preg_replace('/<script[^>]+email-decode\.min\.js[^>]*><\/script>/i', '', $scripts) ?? $scripts;
        $scripts = $this->rewrite($scripts);
        $scripts = StorefrontPerformanceService::deferScriptTags($scripts);
        $storefrontScriptPath = dirname(__DIR__, 2) . '/public/assets/js/template-storefront.js';
        $storefrontScriptVersion = is_file($storefrontScriptPath) ? (string)filemtime($storefrontScriptPath) : '1';

        return $scripts
            . "\n<script>window.SellerAfricaTemplateStorefront = " . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";</script>\n"
            . '<script defer src="' . htmlspecialchars(app_url('assets/js/toasts.js'), ENT_QUOTES, 'UTF-8') . "\"></script>\n"
            . '<script defer src="' . htmlspecialchars(app_url('assets/js/template-storefront.js?v=' . $storefrontScriptVersion), ENT_QUOTES, 'UTF-8') . "\"></script>\n";
    }

    /**
     * @return array<string, string>
     */
    public function sections(): array
    {
        $main = $this->between('<main>', '</main>');
        preg_match_all('/<section\b.*?<\/section>/is', $main, $matches);
        $sections = [];

        foreach ($matches[0] ?? [] as $section) {
            $key = $this->sectionKey($section);
            $sections[$key] = $section;
        }

        return $sections;
    }

    private function sectionKey(string $section): string
    {
        return match (true) {
            str_contains($section, 'mthero__area') => 'hero',
            str_contains($section, 'mtfeature__area') => 'feature-strip',
            str_contains($section, 'mtshop__category-area') => 'category-section',
            str_contains($section, 'mtfeature__product-area') => 'featured-products',
            str_contains($section, 'mtbanner__area pb-40') => 'promo-banners',
            str_contains($section, 'mthot__product-area') => 'hot-deals',
            str_contains($section, 'mtbanner__2-bg') => 'wide-promo-banner',
            str_contains($section, 'mtpopular__product-area') => 'popular-products',
            str_contains($section, 'mtflash__product-area') => 'flash-sale',
            str_contains($section, 'mtbanner__3') => 'secondary-banners',
            str_contains($section, 'mtrecent__product-area') => 'new-arrivals',
            str_contains($section, 'mttestimonial__area') => 'testimonials',
            str_contains($section, 'mtblog__area') => 'articles',
            str_contains($section, 'mtnewslatter__area') => 'newsletter',
            default => 'section-' . substr(sha1($section), 0, 10),
        };
    }

    private function between(string $startNeedle, string $endNeedle): string
    {
        $html = $this->load();
        $start = strpos($html, $startNeedle);
        $end = strpos($html, $endNeedle);
        if ($start === false || $end === false || $end <= $start) {
            return '';
        }

        return substr($html, $start, $end - $start);
    }

    private function load(): string
    {
        if ($this->html !== null) {
            return $this->html;
        }

        $path = APP_ROOT . '/public/storefront/' . $this->template;
        $html = is_file($path) ? file_get_contents($path) : false;
        if ($html === false) {
            throw new \RuntimeException('Storefront template missing: ' . $path);
        }

        return $this->html = $html;
    }

    private function rewrite(string $html): string
    {
        $assetBase = app_url('storefront/');
        $brand = app_branding();
        $brandLogo = htmlspecialchars((string)$brand['logo'], ENT_QUOTES, 'UTF-8');
        $brandName = htmlspecialchars((string)$brand['name'], ENT_QUOTES, 'UTF-8');
        $html = strtr($html, [
            'href="assets/' => 'href="' . $assetBase . 'assets/',
            "href='assets/" => "href='" . $assetBase . "assets/",
            'src="assets/' => 'src="' . $assetBase . 'assets/',
            "src='assets/" => "src='" . $assetBase . "assets/",
            'data-background="assets/' => 'data-background="' . $assetBase . 'assets/',
            "data-background='assets/" => "data-background='" . $assetBase . "assets/",
            'href="index.html"' => 'href="' . app_url('store') . '"',
            'href="shop.html"' => 'href="' . app_url('shop') . '"',
            'href="about.html"' => 'href="' . app_url('about') . '"',
            'href="contact.html"' => 'href="' . app_url('contact') . '"',
            'href="cart.html"' => 'href="' . app_url('cart') . '"',
            'href="checkout.html"' => 'href="' . app_url('storefront/checkout') . '"',
            'href="product-details.html"' => 'href="' . app_url('store') . '"',
            'href="index.html#"' => 'href="#"',
            'href="shop.html#"' => 'href="#"',
            'href="about.html#"' => 'href="#"',
            'href="contact.html#"' => 'href="#"',
            'href="cart.html#"' => 'href="#"',
            'href="checkout.html#"' => 'href="#"',
            'href="product-details.html#"' => 'href="#"',
            'https://template.moontelict.com/rosun/shop.html' => app_url('shop'),
            'https://template.moontelict.com/rosun/cart.html' => app_url('cart'),
            'https://template.moontelict.com/rosun/checkout.html' => app_url('storefront/checkout'),
            'https://template.moontelict.com/rosun/login.html' => app_url('login'),
            'https://template.moontelict.com/rosun/register.html' => app_url('login'),
            'https://template.moontelict.com/rosun/about.html' => app_url('about'),
            'https://template.moontelict.com/rosun/contact.html' => app_url('contact'),
            'https://template.moontelict.com/rosun/product-details.html' => app_url('store'),
            'https://template.moontelict.com/rosun/product.html' => app_url('store'),
            'https://template.moontelict.com/rosun/blog.html' => '#',
            'https://template.moontelict.com/rosun/blog-grid.html' => '#',
            'https://template.moontelict.com/rosun/blog-details.html' => '#',
            'https://template.moontelict.com/rosun/faq.html' => '#',
            'https://template.moontelict.com/rosun/wishlist.html' => '#',
            'href="index.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="index.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
            'href="shop.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="shop.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
            'href="about.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="about.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
            'href="contact.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="contact.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
            'href="cart.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="cart.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
            'href="checkout.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="checkout.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
            'href="product.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="product.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
            'href="product-details.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="product-details.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
            'href="faq.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="faq.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
            'href="wishlist.html#">Privacy Policy' => 'href="' . app_url('privacy-policy') . '">Privacy Policy',
            'href="wishlist.html#">Terms & Condition' => 'href="' . app_url('terms') . '">Terms & Condition',
        ]);

        $html = preg_replace(
            '/src=([("\"])' . preg_quote($assetBase, '/') . 'assets\/img\/logo\/(?:black|white)-logo\.png\1/i',
            'src="' . $brandLogo . '"',
            $html
        ) ?? $html;

        $html = preg_replace('/\bRoshun\b/i', $brandName, $html) ?? $html;
        $html = preg_replace('/\bRosun\b/i', $brandName, $html) ?? $html;

        $html = preg_replace_callback(
            '/<img\b(?=[^>]*src="' . preg_quote($brandLogo, '/') . '")[^>]*>/i',
            static function (array $matches) use ($brandName): string {
                $tag = $matches[0];
                if (preg_match('/\salt=(["\']).*?\1/i', $tag)) {
                    return preg_replace('/\salt=(["\']).*?\1/i', ' alt="' . $brandName . '"', $tag, 1) ?? $tag;
                }

                return preg_replace('/>$/', ' alt="' . $brandName . '">', $tag, 1) ?? $tag;
            },
            $html
        ) ?? $html;
        $html = preg_replace('/\bRoshun\b/i', $brandName, $html) ?? $html;
        $html = preg_replace('/\bRosun\b/i', $brandName, $html) ?? $html;
        $html = preg_replace(
            '/<div class=(["\'])mtheader__top-left\1>\s*<a\b[^>]*>\s*<i\b[^>]*fa-envelope[^>]*><\/i>.*?<\/a>\s*<span>\s*<i\b[^>]*fa-star-sharp[^>]*><\/i>\s*<\/span>\s*<a\b[^>]*>\s*<i\b[^>]*fa-location-dot[^>]*><\/i>.*?<\/a>\s*<\/div>/is',
            '<div class="mtheader__top-left"></div>',
            $html
        ) ?? $html;
        $html = preg_replace(
            '/<div class=(["\'])mt-offcanvas-info\b[^"\']*\1>.*?<\/div>/is',
            '<div class="mt-offcanvas-info mb-50"></div>',
            $html
        ) ?? $html;
        $html = $this->addFormControlIdentifiers($html);
        $html = preg_replace('/\s*<!--\s*preloader\s*-->\s*<div\s+id=(["\'])loading\1\b.*?<\/div>\s*<\/div>\s*<\/div>/is', "\n", $html) ?? $html;

        return preg_replace('/<img(?![^>]*\sloading=)/i', '<img loading="lazy"', $html) ?? $html;
    }

    private function stripTemplateHeroFallbackMedia(string $html): string
    {
        $html = preg_replace(
            '/\sdata-background=(["\'])[^"\']*\/assets\/img\/hero\/[^"\']+\1/i',
            '',
            $html
        ) ?? $html;

        return preg_replace(
            '/<div class=(["\'])mthero__img\1>\s*<img\b[^>]*\bsrc=(["\'])[^"\']*\/assets\/img\/hero\/[^"\']+\2[^>]*>\s*<\/div>/i',
            '<div class="mthero__img mthero__img--empty" aria-hidden="true"></div>',
            $html
        ) ?? $html;
    }

    private function addFormControlIdentifiers(string $html): string
    {
        return preg_replace_callback(
            '/<(input|select|textarea)\b(?![^>]*(?:\sid=|\sname=))([^>]*)>/i',
            function (array $matches): string {
                $tag = strtolower($matches[1]);
                $attrs = $matches[2];
                $this->controlCounter++;

                $name = $this->controlName($tag, $attrs, $this->controlCounter);
                $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

                return '<' . $tag . ' id="' . $safeName . '" name="' . $safeName . '"' . $attrs . '>';
            },
            $html
        ) ?? $html;
    }

    private function controlName(string $tag, string $attrs, int $index): string
    {
        if (preg_match('/placeholder=(["\'])(.*?)\1/i', $attrs, $matches)) {
            $placeholder = strtolower(html_entity_decode($matches[2], ENT_QUOTES, 'UTF-8'));
            if (str_contains($placeholder, 'search')) {
                return 'q';
            }
            if (str_contains($placeholder, 'email')) {
                return 'newsletter_email_' . $index;
            }
            if (str_contains($placeholder, 'name')) {
                return 'customer_name_' . $index;
            }
            if (str_contains($placeholder, 'review')) {
                return 'review_' . $index;
            }
        }

        if (preg_match('/value=(["\'])0?1\1/i', $attrs)) {
            return 'quantity_' . $index;
        }

        return $tag . '_field_' . $index;
    }
}
