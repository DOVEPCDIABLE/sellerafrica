<?php

namespace App;

final class AramexService
{
    public function quote(array $shipment): array
    {
        $settings = $this->settings();
        if (!$this->enabled($settings)) {
            return ['available' => false, 'message' => 'Aramex is disabled.', 'shipment' => $shipment];
        }

        foreach (['username', 'password', 'account_number', 'account_pin', 'account_entity', 'account_country_code'] as $key) {
            if (trim((string)($settings[$key] ?? '')) === '') {
                return ['available' => false, 'message' => 'Aramex credentials are incomplete.', 'shipment' => $shipment];
            }
        }

        if (!function_exists('curl_init')) {
            return ['available' => false, 'message' => 'cURL is not available for Aramex rates.', 'shipment' => $shipment];
        }

        $origin = is_array($shipment['origin'] ?? null) ? $shipment['origin'] : [];
        $destination = is_array($shipment['destination'] ?? null) ? $shipment['destination'] : [];
        $weight = max(0.1, (float)($shipment['weight'] ?? 0.1));
        $pieces = max(1, (int)($shipment['pieces'] ?? 1));
        $currency = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($shipment['currency'] ?? 'USD')) ?: 'USD', 0, 3));

        $payload = [
            'ClientInfo' => [
                'UserName' => (string)$settings['username'],
                'Password' => (string)$settings['password'],
                'Version' => 'v1.0',
                'AccountNumber' => (string)$settings['account_number'],
                'AccountPin' => (string)$settings['account_pin'],
                'AccountEntity' => (string)$settings['account_entity'],
                'AccountCountryCode' => strtoupper((string)$settings['account_country_code']),
                'Source' => 24,
            ],
            'Transaction' => [
                'Reference1' => (string)($shipment['reference'] ?? 'SA-RATE-' . time()),
                'Reference2' => '',
                'Reference3' => '',
                'Reference4' => '',
                'Reference5' => '',
            ],
            'OriginAddress' => $this->addressPayload($origin),
            'DestinationAddress' => $this->addressPayload($destination),
            'ShipmentDetails' => [
                'PaymentType' => 'P',
                'PaymentOptions' => '',
                'ProductGroup' => $this->productGroup($origin, $destination),
                'ProductType' => (string)($shipment['product_type'] ?? 'PPX'),
                'ActualWeight' => ['Unit' => 'KG', 'Value' => $weight],
                'ChargeableWeight' => ['Unit' => 'KG', 'Value' => $weight],
                'Dimensions' => [
                    'Unit' => 'CM',
                    'Length' => max(1.0, (float)($shipment['dimensions']['length'] ?? 1)),
                    'Width' => max(1.0, (float)($shipment['dimensions']['width'] ?? 1)),
                    'Height' => max(1.0, (float)($shipment['dimensions']['height'] ?? 1)),
                ],
                'NumberOfPieces' => $pieces,
                'DescriptionOfGoods' => substr((string)($shipment['description'] ?? 'Seller Africa marketplace products'), 0, 100),
                'GoodsOriginCountry' => strtoupper((string)($origin['country_code'] ?? $settings['shipper_country_code'] ?? 'US')),
            ],
            'PreferredCurrencyCode' => $currency,
        ];

        $curl = curl_init($this->rateEndpoint((string)($settings['api_base_url'] ?? '')));
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        $decoded = is_string($body) ? json_decode($body, true) : null;
        if ($body === false || $status >= 400 || !is_array($decoded)) {
            return [
                'available' => false,
                'message' => $error ?: 'Aramex rate request failed.',
                'status' => $status,
                'raw' => is_array($decoded) ? $decoded : (is_string($body) ? $body : null),
                'shipment' => $shipment,
            ];
        }

        if (!empty($decoded['HasErrors'])) {
            return [
                'available' => false,
                'message' => $this->errorMessage($decoded),
                'status' => $status,
                'raw' => $decoded,
                'shipment' => $shipment,
            ];
        }

        $amount = $decoded['TotalAmount'] ?? $decoded['RateDetails']['TotalAmount'] ?? null;
        $value = is_array($amount) ? (float)($amount['Value'] ?? 0) : (float)$amount;
        $rateCurrency = is_array($amount) ? strtoupper((string)($amount['CurrencyCode'] ?? $currency)) : $currency;

        if ($value <= 0) {
            return ['available' => false, 'message' => 'Aramex returned an empty rate.', 'raw' => $decoded, 'shipment' => $shipment];
        }

        return [
            'available' => true,
            'amount' => round($value, 4),
            'currency' => $rateCurrency ?: $currency,
            'carrier' => 'aramex',
            'service' => (string)($payload['ShipmentDetails']['ProductType'] ?? 'PPX'),
            'raw' => $decoded,
            'request' => $this->safeRequestSnapshot($payload),
        ];
    }

    public function originFromSettings(): array
    {
        $settings = $this->settings();

        return [
            'name' => (string)($settings['shipper_name'] ?? 'Seller Africa'),
            'address_line1' => (string)($settings['shipper_address'] ?? ''),
            'city' => (string)($settings['shipper_city'] ?? ''),
            'state' => (string)($settings['shipper_state'] ?? ''),
            'postcode' => (string)($settings['shipper_postcode'] ?? ''),
            'country_code' => strtoupper((string)($settings['shipper_country_code'] ?? $settings['account_country_code'] ?? 'US')),
            'phone' => (string)($settings['shipper_phone'] ?? ''),
            'email' => (string)($settings['shipper_email'] ?? ''),
        ];
    }

    private function settings(): array
    {
        $keys = [
            'enabled', 'api_base_url', 'username', 'password', 'account_number', 'account_pin',
            'account_entity', 'account_country_code', 'shipper_name', 'shipper_address',
            'shipper_city', 'shipper_state', 'shipper_postcode', 'shipper_country_code',
            'shipper_phone', 'shipper_email',
        ];
        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = \app_setting('shipping_aramex', $key, '');
        }

        return $settings;
    }

    private function enabled(array $settings): bool
    {
        return in_array(strtolower((string)($settings['enabled'] ?? '')), ['1', 'true', 'yes', 'on', 'enabled'], true);
    }

    private function rateEndpoint(string $base): string
    {
        $base = trim($base) ?: 'https://ws.aramex.net/ShippingAPI.V2/Shipping/Service_1_0.svc';
        $base = preg_replace('#/Shipping/#i', '/RateCalculator/', $base) ?: $base;
        $base = preg_replace('#/Service_1_0\.svc.*$#i', '/Service_1_0.svc', $base) ?: $base;

        return rtrim($base, '/') . '/json/CalculateRate';
    }

    private function addressPayload(array $address): array
    {
        return [
            'Line1' => substr((string)($address['address_line1'] ?? $address['line1'] ?? 'Address'), 0, 50),
            'Line2' => substr((string)($address['address_line2'] ?? $address['line2'] ?? ''), 0, 50),
            'Line3' => substr((string)($address['address_line3'] ?? $address['line3'] ?? ''), 0, 50),
            'City' => substr((string)($address['city'] ?? ''), 0, 50),
            'StateOrProvinceCode' => substr((string)($address['state'] ?? ''), 0, 30),
            'PostCode' => substr((string)($address['postcode'] ?? ''), 0, 30),
            'CountryCode' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($address['country_code'] ?? 'US')) ?: 'US', 0, 2)),
        ];
    }

    private function productGroup(array $origin, array $destination): string
    {
        $originCountry = strtoupper((string)($origin['country_code'] ?? ''));
        $destinationCountry = strtoupper((string)($destination['country_code'] ?? ''));

        return $originCountry !== '' && $originCountry === $destinationCountry ? 'DOM' : 'EXP';
    }

    private function errorMessage(array $payload): string
    {
        $notifications = $payload['Notifications'] ?? $payload['RateDetails']['Notifications'] ?? [];
        if (isset($notifications['Notification'])) {
            $notifications = $notifications['Notification'];
        }
        if (isset($notifications['Message'])) {
            return (string)$notifications['Message'];
        }
        if (is_array($notifications)) {
            foreach ($notifications as $notification) {
                if (is_array($notification) && isset($notification['Message'])) {
                    return (string)$notification['Message'];
                }
            }
        }

        return 'Aramex could not calculate a rate for this address.';
    }

    private function safeRequestSnapshot(array $payload): array
    {
        unset($payload['ClientInfo']['Password'], $payload['ClientInfo']['AccountPin']);

        return $payload;
    }
}
