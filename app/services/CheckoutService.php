<?php

declare(strict_types=1);

namespace App;

final class CheckoutService
{
    public function __construct(private Database $db)
    {
    }

    public function quote(array $cartItems, string $currency, array $address): array
    {
        CommerceSafetyService::ensureSchema();

        $renderer = new StorefrontTemplateService($this->db);
        $items = $renderer->cartProducts($cartItems);
        $currencies = $renderer->currencies();
        $currency = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $currency) ?: 'USD', 0, 3));

        $rateFor = static function (string $code) use ($currencies): float {
            foreach ($currencies as $currencyRow) {
                if (strtoupper((string)$currencyRow['code']) === strtoupper($code)) {
                    return max(0.000001, (float)($currencyRow['exchangeRate'] ?? 1));
                }
            }

            return 1.0;
        };

        $convert = static fn (float $amount, string $from, string $to): float => ($amount / $rateFor($from)) * $rateFor($to);
        $subtotal = 0.0;
        $quantity = 0;
        foreach ($items as $item) {
            $itemQuantity = max(1, (int)($item['quantity'] ?? 1));
            $quantity += $itemQuantity;
            $subtotal += $convert((float)($item['rawPrice'] ?? 0), (string)($item['currency'] ?? 'USD'), $currency) * $itemQuantity;
        }

        $productIds = array_values(array_unique(array_filter(array_map(static fn (array $item): int => (int)($item['id'] ?? 0), $items))));
        $productRows = $productIds === [] ? [] : $this->db->fetchAll(
            'SELECT id, vendor_id, weight, length, width, height, shipping_class FROM products WHERE id IN (' . implode(',', array_fill(0, count($productIds), '?')) . ')',
            $productIds
        );
        $productMeta = [];
        foreach ($productRows as $row) {
            $productMeta[(int)$row['id']] = $row;
        }

        $totalWeight = 0.0;
        $maxLength = 0.0;
        $maxWidth = 0.0;
        $maxHeight = 0.0;
        $shippingClasses = [];
        $vendorIds = [];
        foreach ($items as $item) {
            $productId = (int)($item['id'] ?? 0);
            $itemQuantity = max(1, (int)($item['quantity'] ?? 1));
            $meta = $productMeta[$productId] ?? [];
            $totalWeight += (float)($meta['weight'] ?? 0) * $itemQuantity;
            $maxLength = max($maxLength, (float)($meta['length'] ?? 0));
            $maxWidth = max($maxWidth, (float)($meta['width'] ?? 0));
            $maxHeight = max($maxHeight, (float)($meta['height'] ?? 0) * $itemQuantity);
            $shippingClass = trim((string)($meta['shipping_class'] ?? ''));
            if ($shippingClass !== '') {
                $shippingClasses[] = $shippingClass;
            }
            if (!empty($meta['vendor_id'])) {
                $vendorIds[] = (int)$meta['vendor_id'];
            }
        }

        $destination = $this->normalizeShippingAddress($address);
        $shippingQuote = $this->shippingQuote(
            $subtotal,
            $quantity,
            $totalWeight,
            $vendorIds,
            $destination,
            $currency,
            [
                'length' => $maxLength,
                'width' => $maxWidth,
                'height' => $maxHeight,
                'shipping_classes' => array_values(array_unique($shippingClasses)),
                'item_count' => count($items),
            ]
        );
        $shipping = (float)$shippingQuote['amount'];
        $shippingCurrency = strtoupper((string)($shippingQuote['currency'] ?? $currency));
        if ($shippingCurrency !== $currency) {
            $shipping = $convert($shipping, $shippingCurrency, $currency);
        }

        return [
            'items' => $items,
            'subtotal' => round($subtotal, 4),
            'shipping' => round($shipping, 4),
            'tax' => 0.0,
            'total' => round($subtotal + $shipping, 4),
            'currency' => $currency,
            'shipping_rate_snapshot' => array_merge($shippingQuote, [
                'amount' => round($shipping, 4),
                'currency' => $currency,
                'destination' => $destination,
            ]),
        ];
    }

    public function shippingTotal(float $subtotal, int $quantity, float $weight, array $vendorIds, string $country, string $state, string $postcode, string $city): float
    {
        $quote = $this->shippingQuote($subtotal, $quantity, $weight, $vendorIds, [
            'country_code' => $country,
            'state' => $state,
            'postcode' => $postcode,
            'city' => $city,
            'address_line1' => 'Address',
        ], 'USD');

        return (float)$quote['amount'];
    }

    public function shippingQuote(float $subtotal, int $quantity, float $weight, array $vendorIds, array $address, string $currency = 'USD', array $package = []): array
    {
        $destination = $this->normalizeShippingAddress($address);
        $currency = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $currency) ?: 'USD', 0, 3));
        $weight = $weight > 0 ? $weight : max(0.1, $quantity * (float)(\app_setting('shipping', 'default_item_weight_kg', '0.5') ?: 0.5));

        $aramex = new AramexService();
        $aramexQuote = $aramex->quote([
            'reference' => 'SA-RATE-' . bin2hex(random_bytes(4)),
            'origin' => $aramex->originFromSettings(),
            'destination' => $destination,
            'weight' => $weight,
            'pieces' => max(1, $quantity),
            'currency' => $currency,
            'description' => 'Seller Africa marketplace order',
            'dimensions' => [
                'length' => (float)($package['length'] ?? 0),
                'width' => (float)($package['width'] ?? 0),
                'height' => (float)($package['height'] ?? 0),
            ],
            'shipping_classes' => (array)($package['shipping_classes'] ?? []),
        ]);

        if (!empty($aramexQuote['available'])) {
            return [
                'source' => 'aramex',
                'carrier' => 'aramex',
                'service' => (string)($aramexQuote['service'] ?? 'PPX'),
                'amount' => (float)$aramexQuote['amount'],
                'currency' => (string)($aramexQuote['currency'] ?? $currency),
                'weight_kg' => $weight,
                'package' => $package,
                'request' => $aramexQuote['request'] ?? null,
                'raw' => $aramexQuote['raw'] ?? null,
            ];
        }

        if (in_array(strtolower((string)\app_setting('shipping_aramex', 'enabled', '')), ['1', 'true', 'yes', 'on', 'enabled'], true)) {
            throw new \RuntimeException('Shipping could not be calculated for this address. Check your country, city and postal code, then try again. If the address is correct, please contact support before paying.');
        }

        $local = $this->localShippingTotal(
            $subtotal,
            $quantity,
            $weight,
            $vendorIds,
            (string)$destination['country_code'],
            (string)$destination['state'],
            (string)$destination['postcode'],
            (string)$destination['city']
        );

        return [
            'source' => 'local_rate',
            'carrier' => 'seller_africa',
            'service' => 'standard',
            'amount' => $local,
            'currency' => $currency,
            'weight_kg' => $weight,
            'package' => $package,
            'aramex_unavailable_reason' => (string)($aramexQuote['message'] ?? 'Aramex rate was not available for this address.'),
        ];
    }

    private function localShippingTotal(float $subtotal, int $quantity, float $weight, array $vendorIds, string $country, string $state, string $postcode, string $city): float
    {
        if (!\table_exists('shipping_methods') || !\table_exists('shipping_rates')) {
            return $this->fallbackShippingTotal($subtotal, $quantity);
        }

        $zoneIds = $this->matchingShippingZoneIds($country, $state, $postcode, $city);
        $zonePlaceholders = $zoneIds !== [] ? implode(',', array_fill(0, count($zoneIds), '?')) : '';
        $vendorIds = array_values(array_unique(array_filter($vendorIds)));
        $vendorPlaceholders = $vendorIds !== [] ? implode(',', array_fill(0, count($vendorIds), '?')) : '';

        $conditions = ['m.is_active = 1', '(m.min_order_amount IS NULL OR m.min_order_amount <= ?)'];
        $params = [$subtotal];

        if ($zoneIds !== []) {
            $conditions[] = "(m.zone_id IS NULL OR m.zone_id IN ({$zonePlaceholders}))";
            array_push($params, ...$zoneIds);
        } else {
            $conditions[] = 'm.zone_id IS NULL';
        }

        $marketplaceRatesOnly = (string)(\app_setting('shipping', 'use_marketplace_rates_only', 'yes') ?? 'yes') !== 'no';
        if ($marketplaceRatesOnly) {
            $conditions[] = 'm.vendor_id IS NULL';
        } elseif ($vendorIds !== []) {
            $conditions[] = "(m.vendor_id IS NULL OR m.vendor_id IN ({$vendorPlaceholders}))";
            array_push($params, ...$vendorIds);
        } else {
            $conditions[] = 'm.vendor_id IS NULL';
        }

        $methods = $this->db->fetchAll(
            "SELECT m.id, m.calculation_type, m.base_cost, m.zone_id, m.vendor_id,
                    r.condition_type, r.min_value, r.max_value, r.cost, r.per_item_cost
             FROM shipping_methods m
             LEFT JOIN shipping_rates r ON r.method_id = m.id
             WHERE " . implode(' AND ', $conditions) . "
             ORDER BY m.vendor_id IS NULL DESC, m.zone_id IS NULL ASC, m.base_cost ASC, r.sort_order ASC, m.id ASC",
            $params
        );

        $best = null;
        foreach ($methods as $method) {
            if ((string)$method['calculation_type'] === 'free_shipping') {
                return 0.0;
            }

            $conditionType = (string)($method['condition_type'] ?? 'none');
            $basis = match ($conditionType) {
                'weight' => $weight,
                'subtotal' => $subtotal,
                'quantity' => (float)$quantity,
                default => 0.0,
            };

            if ($conditionType !== 'none') {
                $min = $method['min_value'] !== null ? (float)$method['min_value'] : null;
                $max = $method['max_value'] !== null ? (float)$method['max_value'] : null;
                if (($min !== null && $basis < $min) || ($max !== null && $basis > $max)) {
                    continue;
                }
            }

            $cost = (float)($method['base_cost'] ?? 0) + (float)($method['cost'] ?? 0) + ((float)($method['per_item_cost'] ?? 0) * $quantity);
            $best = $best === null ? $cost : min($best, $cost);
        }

        return round(max(0.0, (float)($best ?? $this->fallbackShippingTotal($subtotal, $quantity))), 4);
    }

    private function normalizeShippingAddress(array $address): array
    {
        $country = self::normalizeCountryCode((string)($address['country_code'] ?? ''));
        $city = trim((string)($address['city'] ?? ''));
        $postcode = trim((string)($address['postcode'] ?? ''));
        $line1 = trim((string)($address['address_line1'] ?? $address['line1'] ?? ''));
        if ($country === '' || $city === '' || $line1 === '') {
            throw new \RuntimeException('Enter your delivery address, city and country before shipping can be calculated.');
        }
        if (trim((string)($address['state'] ?? '')) === '') {
            throw new \RuntimeException('Enter your delivery state, province or region before shipping can be calculated.');
        }
        if ($postcode === '') {
            throw new \RuntimeException('Enter your delivery postal / ZIP code before shipping can be calculated.');
        }

        return [
            'address_line1' => $line1,
            'address_line2' => trim((string)($address['address_line2'] ?? $address['line2'] ?? '')),
            'city' => $city,
            'state' => trim((string)($address['state'] ?? '')),
            'postcode' => $postcode,
            'country_code' => $country,
            'name' => trim((string)($address['name'] ?? 'Seller Africa Customer')),
            'phone' => trim((string)($address['phone'] ?? '')),
            'email' => trim((string)($address['email'] ?? '')),
        ];
    }

    public static function normalizeCountryCode(string $country): string
    {
        $country = trim($country);
        $aliases = ['united states' => 'US', 'united states of america' => 'US', 'usa' => 'US', 'united kingdom' => 'GB', 'uk' => 'GB', 'nigeria' => 'NG', 'canada' => 'CA', 'ghana' => 'GH', 'kenya' => 'KE', 'south africa' => 'ZA', 'jamaica' => 'JM'];
        $code = $aliases[strtolower($country)] ?? strtoupper($country);
        if (!preg_match('/^[A-Z]{2}$/', $code)) {
            throw new \RuntimeException('Please select your delivery country from the country list.');
        }
        return $code;
    }

    private function fallbackShippingTotal(float $subtotal, int $quantity): float
    {
        if ($quantity <= 0 || $subtotal <= 0) {
            return 0.0;
        }

        $configured = trim((string)(\app_setting('shipping', 'fallback_shipping_amount', '9.99') ?? '9.99'));
        $amount = is_numeric($configured) ? (float)$configured : 9.99;

        return round(max(0.0, $amount), 4);
    }

    private function matchingShippingZoneIds(string $country, string $state, string $postcode, string $city): array
    {
        if (!\table_exists('shipping_zones')) {
            return [];
        }

        if (!\table_exists('shipping_zone_locations')) {
            $rows = $this->db->fetchAll('SELECT id FROM shipping_zones WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
            return array_map(static fn (array $row): int => (int)$row['id'], $rows);
        }

        $rows = $this->db->fetchAll(
            "SELECT DISTINCT z.id
             FROM shipping_zones z
             LEFT JOIN shipping_zone_locations l ON l.zone_id = z.id
             WHERE z.is_active = 1
               AND (
                   l.id IS NULL
                   OR (l.location_type = 'country' AND UPPER(l.location_code) = ?)
                   OR (l.location_type = 'state' AND UPPER(l.location_code) = ?)
                   OR (l.location_type = 'postcode' AND UPPER(l.location_code) = ?)
                   OR (l.location_type = 'city' AND UPPER(l.location_code) = ?)
               )
             ORDER BY z.sort_order ASC, z.id ASC",
            [strtoupper($country), strtoupper($state), strtoupper($postcode), strtoupper($city)]
        );

        return array_map(static fn (array $row): int => (int)$row['id'], $rows);
    }
}
