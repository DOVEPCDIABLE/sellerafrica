<?php
declare(strict_types=1);

namespace App;

final class VendorCompletionService
{
    public const RANGES = ['100' => '100%', '75-99' => '75-99%', '50-74' => '50-74%', '25-49' => '25-49%', '0-24' => '0-24%'];

    public static function checks(array $vendor): array
    {
        $data = json_decode((string)($vendor['latest_application_data'] ?? ''), true);
        $data = is_array($data) ? $data : [];
        if (($data['onboarding']['version'] ?? 0) === 2) {
            return VendorOnboardingService::checks($data);
        }
        $r = is_array($data['readiness'] ?? null) ? $data['readiness'] : [];
        $details = trim((string)($r['product_description'] ?? '')) !== '';
        foreach (['product_price', 'product_weight', 'package_length', 'package_width', 'package_height'] as $key) {
            $details = $details && (float)($r[$key] ?? 0) > 0;
        }
        return [
            ['label' => 'Profile photo/logo', 'done' => !empty($vendor['logo_file_id'])],
            ['label' => 'Store banner', 'done' => !empty($vendor['banner_file_id'])],
            ['label' => 'Product image', 'done' => (int)($vendor['imaged_product_count'] ?? 0) > 0 || (int)($data['file_ids']['product_image'] ?? 0) > 0],
            ['label' => 'Product description, price, weight and package dimensions', 'done' => (int)($vendor['ready_product_count'] ?? 0) > 0 || $details],
        ];
    }

    public static function percent(array $vendor): int
    {
        $checks = self::checks($vendor);
        return (int)round(count(array_filter($checks, static fn (array $check): bool => (bool)$check['done'])) / max(1, count($checks)) * 100);
    }

    public static function range(int $percent): string
    {
        return $percent === 100 ? '100' : ($percent >= 75 ? '75-99' : ($percent >= 50 ? '50-74' : ($percent >= 25 ? '25-49' : '0-24')));
    }

    public static function listing(array $rows, string $filter): array
    {
        $filter = array_key_exists($filter, self::RANGES) ? $filter : '';
        $summary = array_fill_keys(array_keys(self::RANGES), 0);
        $selected = [];
        foreach ($rows as $row) {
            $row['completion_percent'] = self::percent($row);
            $range = self::range($row['completion_percent']);
            $summary[$range]++;
            if ($filter === '' || $range === $filter) {
                $selected[] = $row;
            }
        }
        usort($selected, static function (array $a, array $b): int {
            return ($b['completion_percent'] <=> $a['completion_percent'])
                ?: strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''))
                ?: ((int)$b['id'] <=> (int)$a['id']);
        });
        return ['rows' => $selected, 'summary' => $summary, 'filter' => $filter];
    }
}
