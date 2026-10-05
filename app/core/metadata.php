<?php
/**
 * File Folder Path: /app/core
 * File Path: /app/core/metadata.php
 * Designed by Daniel Pybexai Framework
 * ==============================================================================
 * METADATA & TELEMETRY INJECTION
 * Summary: Handles global SEO metadata, Open Graph configurations, 
 * and initializes request performance telemetry.
 */

declare(strict_types=1);

namespace App\Core;

class Metadata
{
    private static array $data = [
        'title'       => 'ERP Market',
        'description' => 'A premium multivendor e-commerce platform.',
        'keywords'    => 'ecommerce, multivendor, erp, shopping',
        'author'      => 'ERP Market',
        'theme_color' => '#16a34a', // Green-600
        'og_image'    => '', // Default fallback image URL can be set here
        'og_type'     => 'website',
    ];

    private static float $startTime;

    public static function init(): void
    {
        // Capture the exact microsecond the script started
        self::$startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        
        // Define default Open Graph Image based on BASE_URL
        self::$data['og_image'] = defined('BASE_URL') ? BASE_URL . 'assets/images/default-og.webp' : '';
    }

    public static function set(string $key, string $value): void
    {
        if (array_key_exists($key, self::$data)) {
            self::$data[$key] = htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
        }
    }

    public static function setTitle(string $title): void
    {
        self::set('title', $title . ' | ' . ($_ENV['APP_NAME'] ?? 'ERP Market'));
    }

    public static function setDescription(string $description): void
    {
        self::set('description', $description);
    }

    public static function setOgImage(string $imageUrl): void
    {
        self::set('og_image', $imageUrl);
    }

    public static function render(): string
    {
        $currentUrl = htmlspecialchars((defined('BASE_URL') ? BASE_URL : '/') . ltrim($_SERVER['REQUEST_URI'] ?? '', '/'), ENT_QUOTES, 'UTF-8');
        
        $html = "<!-- ERP Market Meta Tags -->\n";
        $html .= "<meta name=\"description\" content=\"" . self::$data['description'] . "\">\n";
        $html .= "<meta name=\"keywords\" content=\"" . self::$data['keywords'] . "\">\n";
        $html .= "<meta name=\"author\" content=\"" . self::$data['author'] . "\">\n";
        $html .= "<meta name=\"theme-color\" content=\"" . self::$data['theme_color'] . "\">\n";
        
        // Open Graph / Facebook
        $html .= "<meta property=\"og:type\" content=\"" . self::$data['og_type'] . "\">\n";
        $html .= "<meta property=\"og:url\" content=\"{$currentUrl}\">\n";
        $html .= "<meta property=\"og:title\" content=\"" . self::$data['title'] . "\">\n";
        $html .= "<meta property=\"og:description\" content=\"" . self::$data['description'] . "\">\n";
        if (!empty(self::$data['og_image'])) {
            $html .= "<meta property=\"og:image\" content=\"" . self::$data['og_image'] . "\">\n";
        }

        // Twitter
        $html .= "<meta name=\"twitter:card\" content=\"summary_large_image\">\n";
        $html .= "<meta name=\"twitter:url\" content=\"{$currentUrl}\">\n";
        $html .= "<meta name=\"twitter:title\" content=\"" . self::$data['title'] . "\">\n";
        $html .= "<meta name=\"twitter:description\" content=\"" . self::$data['description'] . "\">\n";
        if (!empty(self::$data['og_image'])) {
            $html .= "<meta name=\"twitter:image\" content=\"" . self::$data['og_image'] . "\">\n";
        }

        return $html;
    }

    public static function getExecutionTime(): float
    {
        $endTime = microtime(true);
        return round(($endTime - self::$startTime) * 1000, 2); // Returns execution time in milliseconds
    }
    
    public static function getMemoryUsage(): string
    {
        $size = memory_get_peak_usage(true);
        $unit = ['b','kb','mb','gb','tb','pb'];
        $i = (int) floor(log($size, 1024));
        return @round($size / pow(1024, $i), 2) . ' ' . $unit[$i];
    }
}

// Initialize telemetry as soon as the file is included by bootstrap.php
\App\Core\Metadata::init();
