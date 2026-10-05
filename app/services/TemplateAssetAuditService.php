<?php

declare(strict_types=1);

namespace App;

final class TemplateAssetAuditService
{
    /**
     * @return array<int, string>
     */
    public function missingAssets(string $htmlPath, string $assetRoot): array
    {
        if (!is_file($htmlPath)) {
            return ['Template file missing: ' . $htmlPath];
        }

        $html = file_get_contents($htmlPath);
        if ($html === false) {
            return ['Template file unreadable: ' . $htmlPath];
        }

        $references = $this->localReferences($html);
        $missing = [];

        foreach ($references as $reference) {
            $path = $assetRoot . '/' . ltrim($reference, '/');
            if (!is_file($path)) {
                $missing[] = $reference;
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * @return array<int, string>
     */
    private function localReferences(string $html): array
    {
        $references = [];
        preg_match_all('/(?:src|href|data-background)=["\']([^"\']+)["\']/i', $html, $attributeMatches);
        preg_match_all('/url\(["\']?([^)"\']+)["\']?\)/i', $html, $urlMatches);

        foreach (array_merge($attributeMatches[1] ?? [], $urlMatches[1] ?? []) as $reference) {
            $reference = trim(html_entity_decode((string)$reference, ENT_QUOTES, 'UTF-8'));
            if ($reference === '' || str_starts_with($reference, '#')) {
                continue;
            }
            if (preg_match('/^(https?:|data:|mailto:|tel:|\/\/)/i', $reference)) {
                continue;
            }
            if (str_starts_with($reference, 'assets/')) {
                $references[] = substr($reference, 7);
            }
        }

        return $references;
    }
}
