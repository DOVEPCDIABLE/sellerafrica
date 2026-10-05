<?php

declare(strict_types=1);

namespace App;

final class StorefrontPerformanceService
{
    public static function optimizeHeadHtml(string $html): string
    {
        $html = self::addPreconnects($html);
        $html = self::asyncStylesheets($html);

        if (!str_contains($html, 'sa-critical-storefront-css')) {
            $html = str_ireplace('</head>', self::criticalCss() . "\n</head>", $html);
        }

        return $html;
    }

    public static function asyncStylesheetTag(string $href): string
    {
        $safeHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');

        if (self::isCriticalStylesheet($href)) {
            return '<link rel="stylesheet" href="' . $safeHref . '">';
        }

        return '<link rel="preload" href="' . $safeHref . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">'
            . '<noscript><link rel="stylesheet" href="' . $safeHref . '"></noscript>';
    }

    public static function deferScriptTags(string $html): string
    {
        return preg_replace_callback(
            '/<script\b(?![^>]*(?:\sdefer\b|\sasync\b))([^>]*)\bsrc=(["\'])(.*?)\2([^>]*)><\/script>/i',
            static function (array $matches): string {
                return '<script defer' . $matches[1] . 'src=' . $matches[2] . $matches[3] . $matches[2] . $matches[4] . '></script>';
            },
            $html
        ) ?? $html;
    }

    public static function prioritizeFirstImage(string $html): string
    {
        $updated = false;
        $html = preg_replace_callback(
            '/<section\b(?=[^>]*mthero__area).*?<\/section>/is',
            static function (array $matches) use (&$updated): string {
                if ($updated) {
                    return $matches[0];
                }

                $section = preg_replace_callback(
                    '/<img\b[^>]*>/i',
                    static function (array $imageMatches) use (&$updated): string {
                        if ($updated || !self::isPriorityImageCandidate($imageMatches[0])) {
                            return $imageMatches[0];
                        }

                        $updated = true;
                        return self::prioritizeImageTag($imageMatches[0]);
                    },
                    $matches[0],
                    1
                );

                return $section ?? $matches[0];
            },
            $html,
            1
        ) ?? $html;

        if ($updated) {
            return $html;
        }

        return preg_replace_callback(
            '/<img\b[^>]*>/i',
            static function (array $matches) use (&$updated): string {
                if ($updated || !self::isPriorityImageCandidate($matches[0])) {
                    return $matches[0];
                }

                $updated = true;
                return self::prioritizeImageTag($matches[0]);
            },
            $html,
            6
        ) ?? $html;
    }

    private static function addPreconnects(string $html): string
    {
        $preconnect = '';
        if (!str_contains($html, 'rel="preconnect" href="https://fonts.googleapis.com"')) {
            $preconnect .= '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        }
        if (!str_contains($html, 'rel="preconnect" href="https://fonts.gstatic.com"')) {
            $preconnect .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        }

        if ($preconnect === '') {
            return $html;
        }

        return preg_replace('/(<meta\b[^>]*charset[^>]*>\s*)/i', '$1' . $preconnect, $html, 1)
            ?? str_ireplace('<head>', '<head>' . "\n" . $preconnect, $html);
    }

    private static function asyncStylesheets(string $html): string
    {
        $noscriptBlocks = [];
        $html = preg_replace_callback(
            '/<noscript\b.*?<\/noscript>/is',
            static function (array $matches) use (&$noscriptBlocks): string {
                $key = '%%SA_NOSCRIPT_' . count($noscriptBlocks) . '%%';
                $noscriptBlocks[$key] = $matches[0];
                return $key;
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '/<link\b[^>]*>/i',
            static function (array $matches): string {
                $tag = $matches[0];
                if (!preg_match('/(?:\s|<)rel=(["\'])stylesheet\1/i', $tag)) {
                    return $tag;
                }
                if (!preg_match('/\shref=(["\'])(.*?)\1/i', $tag, $hrefMatch)) {
                    return $tag;
                }

                $href = html_entity_decode($hrefMatch[2], ENT_QUOTES, 'UTF-8');

                return self::asyncStylesheetTag($href);
            },
            $html
        ) ?? $html;

        return $noscriptBlocks === [] ? $html : strtr($html, $noscriptBlocks);
    }

    private static function prioritizeImageTag(string $tag): string
    {
        if (preg_match('/\sloading=(["\']).*?\1/i', $tag)) {
            $tag = preg_replace('/\sloading=(["\']).*?\1/i', ' loading="eager"', $tag, 1) ?? $tag;
        } else {
            $tag = preg_replace('/<img\b/i', '<img loading="eager"', $tag, 1) ?? $tag;
        }

        if (!preg_match('/\sfetchpriority=/i', $tag)) {
            $tag = preg_replace('/<img\b/i', '<img fetchpriority="high"', $tag, 1) ?? $tag;
        }

        if (!preg_match('/\sdecoding=/i', $tag)) {
            $tag = preg_replace('/<img\b/i', '<img decoding="async"', $tag, 1) ?? $tag;
        }

        return $tag;
    }

    private static function isPriorityImageCandidate(string $tag): bool
    {
        return !preg_match('/(?:\/icon\/|\.svg(?:["\']|\?))/i', $tag);
    }

    private static function isCriticalStylesheet(string $href): bool
    {
        $path = parse_url($href, PHP_URL_PATH);
        $filename = basename((string)$path);

        return in_array($filename, ['bootstrap.min.css', 'main.css', 'template-storefront-fixes.css'], true);
    }

    private static function criticalCss(): string
    {
        return '<style id="sa-critical-storefront-css">'
            . 'body{margin:0;background:#fff;color:#111;font-family:Arial,Helvetica,sans-serif}'
            . 'img{max-width:100%;height:auto}'
            . '#loading{position:fixed;inset:0;z-index:9999;background:#12a827;display:flex;align-items:center;justify-content:center}'
            . '#loading-center,#loading-center-absolute{position:relative;width:120px;height:120px}'
            . '.object{position:absolute;width:18px;height:18px;border-radius:999px;background:#fff}'
            . '.mtheader__top-area{min-height:42px}.mtheader__midel-area{min-height:88px}.mtheader__bottom-area{min-height:58px}'
            . '.mthero__area{min-height:clamp(520px,68vh,760px)}.mthero__banner{min-height:clamp(420px,58vh,680px);background-size:cover;background-position:center}'
            . '.mthero__subtitle img,.mtbanner__subtitle img{width:18px!important;height:18px!important;display:inline-block;vertical-align:middle}'
            . '.mthero__img img,.mthero__thumb img{width:100%;height:auto;display:block}'
            . '.mtfeature__product-img,.mthot__product-img,.mt-shop-grid-img,.mtflash__product-img{aspect-ratio:1/.88;background:#f8f8f9}'
            . '.mtrecent__product-img{width:148px;min-width:148px;height:148px;background:#f8f8f9}'
            . '</style>';
    }
}
