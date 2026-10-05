<?php

declare(strict_types=1);

namespace App;

final class SeoService
{
    public static function tags(string $title, string $description = '', array $meta = []): string
    {
        $brand = \app_branding();
        $siteName = (string)$brand['name'];
        $title = trim($title) !== '' ? trim($title) : (string)\app_setting('seo', 'meta_title', $siteName);
        $description = self::summary($description !== '' ? $description : (string)\app_setting('seo', 'meta_description', 'African and Caribbean Marketplace'));
        $keywords = trim((string)($meta['keywords'] ?? \app_setting('seo', 'meta_keywords', '')));
        $robots = (string)\app_setting('seo', 'robots_index', 'index') === 'noindex' ? 'noindex,nofollow' : 'index,follow';
        $canonical = self::canonical((string)($meta['canonical'] ?? self::currentUrl()));
        $image = self::absoluteUrl((string)($meta['image'] ?? $meta['og_image'] ?? self::defaultImage()));
        $type = trim((string)($meta['og_type'] ?? 'website')) ?: 'website';
        $twitterHandle = trim((string)\app_setting('seo', 'twitter_handle', ''));

        $tags = [
            '<title>' . self::escape($title) . '</title>',
            '<meta name="description" content="' . self::escape($description) . '">',
            '<meta name="robots" content="' . self::escape($robots) . '">',
            '<link rel="canonical" href="' . self::escape($canonical) . '">',
            '<link rel="icon" href="' . self::escape(self::favicon()) . '">',
            '<link rel="apple-touch-icon" href="' . self::escape(self::favicon()) . '">',
            '<meta property="og:site_name" content="' . self::escape($siteName) . '">',
            '<meta property="og:type" content="' . self::escape($type) . '">',
            '<meta property="og:title" content="' . self::escape($title) . '">',
            '<meta property="og:description" content="' . self::escape($description) . '">',
            '<meta property="og:url" content="' . self::escape($canonical) . '">',
            '<meta property="og:image" content="' . self::escape($image) . '">',
            '<meta name="twitter:card" content="summary_large_image">',
            '<meta name="twitter:title" content="' . self::escape($title) . '">',
            '<meta name="twitter:description" content="' . self::escape($description) . '">',
            '<meta name="twitter:image" content="' . self::escape($image) . '">',
        ];

        if ($keywords !== '') {
            $tags[] = '<meta name="keywords" content="' . self::escape($keywords) . '">';
        }

        if ($twitterHandle !== '') {
            $tags[] = '<meta name="twitter:site" content="' . self::escape(str_starts_with($twitterHandle, '@') ? $twitterHandle : '@' . $twitterHandle) . '">';
        }

        if (isset($meta['product_price'])) {
            $tags[] = '<meta property="product:price:amount" content="' . self::escape((string)$meta['product_price']) . '">';
            $tags[] = '<meta property="product:price:currency" content="' . self::escape((string)($meta['product_currency'] ?? 'USD')) . '">';
        }

        $analytics = self::analyticsScript();
        if ($analytics !== '') {
            $tags[] = $analytics;
        }

        return implode("\n    ", $tags);
    }

    public static function applyToHead(string $head, string $title, string $description = '', array $meta = []): string
    {
        $patterns = [
            '/<title\b[^>]*>.*?<\/title>\s*/is',
            '/<meta\s+name=(["\'])(description|keywords|robots|twitter:card|twitter:title|twitter:description|twitter:image|twitter:site)\1[^>]*>\s*/i',
            '/<meta\s+property=(["\'])(og:site_name|og:type|og:title|og:description|og:url|og:image|product:price:amount|product:price:currency)\1[^>]*>\s*/i',
            '/<link\s+rel=(["\'])(canonical|icon|apple-touch-icon)\1[^>]*>\s*/i',
            '/<script\s+async\s+src=(["\'])https:\/\/www\.googletagmanager\.com\/gtag\/js\?id=.*?<\/script>\s*/is',
            '/<script>\s*window\.dataLayer\s*=.*?gtag\((["\'])config\1,.*?<\/script>\s*/is',
        ];

        foreach ($patterns as $pattern) {
            $head = preg_replace($pattern, '', $head) ?? $head;
        }

        return str_ireplace('</head>', '    ' . self::tags($title, $description, $meta) . "\n</head>", $head);
    }

    public static function favicon(): string
    {
        $path = trim((string)\app_setting('seo', 'favicon_path', ''));
        if ($path === '') {
            $path = (string)(\app_branding()['logo_path'] ?? '');
        }

        return self::absoluteUrl($path !== '' ? $path : (string)\app_branding()['logo']);
    }

    private static function defaultImage(): string
    {
        $path = trim((string)\app_setting('seo', 'og_image_path', ''));
        if ($path !== '') {
            return $path;
        }

        return (string)(\app_branding()['logo_path'] ?: \app_branding()['logo']);
    }

    private static function analyticsScript(): string
    {
        $id = strtoupper(trim((string)\app_setting('seo', 'google_analytics_id', '')));
        if ($id === '' || !preg_match('/^[A-Z0-9_-]{4,40}$/', $id)) {
            return '';
        }

        $safeId = self::escape($id);

        return '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $safeId . '"></script>' . "\n"
            . "    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . $safeId . "');</script>";
    }

    private static function canonical(string $url): string
    {
        $domain = rtrim(trim((string)\app_setting('seo', 'canonical_domain', '')), '/');
        if ($domain === '') {
            return self::absoluteUrl($url);
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $domain . $path . ($query ? '?' . $query : '');
    }

    private static function currentUrl(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');

        return self::origin() . ($uri !== '' ? $uri : '/');
    }

    private static function absoluteUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return \app_url('');
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, '//')) {
            return (\app_is_https() ? 'https:' : 'http:') . $path;
        }

        if (str_starts_with($path, '/')) {
            return self::origin() . $path;
        }

        $url = \app_url(ltrim($path, '/'));

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : self::origin() . $url;
    }

    private static function origin(): string
    {
        $base = (string)(defined('BASE_URL') ? BASE_URL : '/');
        if (preg_match('/^https?:\/\/[^\/]+/i', $base, $matches)) {
            return rtrim($matches[0], '/');
        }

        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return rtrim($base, '/');
        }

        return (\app_is_https() ? 'https://' : 'http://') . $host;
    }

    private static function summary(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($value, ENT_QUOTES, 'UTF-8'))) ?? '');
        if ($value === '') {
            $value = (string)\app_setting('seo', 'meta_description', 'African and Caribbean Marketplace');
        }

        return function_exists('mb_substr') ? mb_substr($value, 0, 180) : substr($value, 0, 180);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
