<?php

declare(strict_types=1);

namespace App;

use Stripe\Checkout\Session;
use Stripe\Stripe;
use Stripe\Webhook;

final class PaymentService
{
    public function gateways(): array
    {
        return ['stripe', 'paypal', 'klasha', 'paystack'];
    }

    public static function ensureKlashaMethod(): void
    {
        if (!\table_exists('payment_methods')) {
            return;
        }

        \db()->query(
            "INSERT INTO payment_methods (code, name, provider, is_active, settings)
             VALUES ('klasha', 'Klasha Payment', 'klasha', 0, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), provider = VALUES(provider)",
            [json_encode(self::defaultKlashaSettings(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
        );
    }

    public static function ensurePaystackMethod(): void
    {
        if (!\table_exists('payment_methods')) {
            return;
        }

        \db()->query(
            "INSERT INTO payment_methods (code, name, provider, is_active, settings)
             VALUES ('paystack', 'Paystack Payment', 'paystack', 0, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), provider = VALUES(provider)",
            [json_encode(self::defaultPaystackSettings(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultKlashaSettings(): array
    {
        return [
            'mode' => 'test',
            'public_key' => '',
            'secret_key' => '',
            'business_id' => '',
            'webhook_secret' => '',
            'destination_currency' => 'NGN',
            'status_endpoint' => 'https://gate.klasha.com/nucleus/tnx/merchant/status',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultStripeSettings(): array
    {
        return [
            'mode' => 'live',
            'public_key' => '',
            'secret_key' => '',
            'webhook_secret' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultPaystackSettings(): array
    {
        return [
            'mode' => 'test',
            'public_key' => '',
            'secret_key' => '',
            'webhook_secret' => '',
            'charge_currency' => 'NGN',
            'initialize_endpoint' => 'https://api.paystack.co/transaction/initialize',
            'verify_endpoint' => 'https://api.paystack.co/transaction/verify',
        ];
    }

    /**
     * @param array<string, mixed>|null $method
     * @return array<string, mixed>
     */
    public static function settings(?array $method): array
    {
        $settings = self::defaultKlashaSettings();
        if (!$method) {
            return $settings;
        }

        $decoded = json_decode((string)($method['settings'] ?? ''), true);
        if (is_array($decoded)) {
            $settings = array_merge($settings, $decoded);
        }

        return $settings;
    }

    public static function isKlashaConfigured(?array $method): bool
    {
        $settings = self::settings($method);

        return trim((string)$settings['public_key']) !== ''
            && trim((string)$settings['business_id']) !== '';
    }

    /**
     * @param array<string, mixed>|null $method
     * @return array<string, mixed>
     */
    public static function stripeSettings(?array $method = null): array
    {
        $settings = self::defaultStripeSettings();

        $configFile = \APP_ROOT . '/app/config/payment_gateways.php';
        $config = is_file($configFile) ? require $configFile : [];
        if (is_array($config) && is_array($config['stripe'] ?? null)) {
            $settings = array_merge($settings, $config['stripe']);
        }

        $decoded = json_decode((string)($method['settings'] ?? ''), true);
        if (is_array($decoded)) {
            $settings = array_merge($settings, array_filter($decoded, static fn ($value): bool => $value !== null && $value !== ''));
        }

        return $settings;
    }

    public static function isStripeConfigured(?array $method = null): bool
    {
        $settings = self::stripeSettings($method);

        return trim((string)($settings['secret_key'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed>|null $method
     * @return array<string, mixed>
     */
    public static function paystackSettings(?array $method = null): array
    {
        $settings = self::defaultPaystackSettings();

        $configFile = \APP_ROOT . '/app/config/payment_gateways.php';
        $config = is_file($configFile) ? require $configFile : [];
        if (is_array($config) && is_array($config['paystack'] ?? null)) {
            $settings = array_merge($settings, $config['paystack']);
        }

        $decoded = json_decode((string)($method['settings'] ?? ''), true);
        if (is_array($decoded)) {
            $settings = array_merge($settings, array_filter($decoded, static fn ($value): bool => $value !== null && $value !== ''));
        }

        return $settings;
    }

    public static function isPaystackConfigured(?array $method = null): bool
    {
        $settings = self::paystackSettings($method);

        return trim((string)($settings['secret_key'] ?? '')) !== '';
    }

    /**
     * @param array<string, string> $metadata
     * @return array<string, mixed>
     */
    public static function initializePaystackTransaction(float $amount, string $currency, string $email, string $reference, string $callbackUrl, array $metadata = [], ?array $method = null): array
    {
        $settings = self::paystackSettings($method);
        $secretKey = trim((string)($settings['secret_key'] ?? ''));
        if ($secretKey === '') {
            throw new \RuntimeException('Paystack is not configured yet. Please choose another payment method or contact support.');
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('cURL is not available for Paystack payments.');
        }

        $endpoint = trim((string)($settings['initialize_endpoint'] ?? '')) ?: 'https://api.paystack.co/transaction/initialize';
        $currency = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $currency) ?: 'NGN', 0, 3));
        $payload = json_encode([
            'email' => $email,
            'amount' => max(1, (int)round($amount * 100)),
            'currency' => $currency,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $curl = curl_init($endpoint);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $secretKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        $decoded = is_string($body) ? json_decode($body, true) : null;
        if ($body === false || $status >= 400 || !is_array($decoded) || empty($decoded['status'])) {
            throw new \RuntimeException($error ?: (string)($decoded['message'] ?? 'Paystack transaction could not be initialized.'));
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    public static function verifyPaystackTransaction(string $reference, ?array $method = null): array
    {
        $settings = self::paystackSettings($method);
        $secretKey = trim((string)($settings['secret_key'] ?? ''));
        if ($secretKey === '') {
            return ['ok' => false, 'message' => 'Paystack verification is not configured.'];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'cURL is not available for Paystack verification.'];
        }

        $endpoint = rtrim(trim((string)($settings['verify_endpoint'] ?? '')), '/') ?: 'https://api.paystack.co/transaction/verify';
        $curl = curl_init($endpoint . '/' . rawurlencode($reference));
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $secretKey,
                'Accept: application/json',
            ],
        ]);

        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        $decoded = is_string($body) ? json_decode($body, true) : null;
        if ($body === false || $status >= 400 || !is_array($decoded) || empty($decoded['status'])) {
            return [
                'ok' => false,
                'message' => $error ?: (string)($decoded['message'] ?? 'Paystack verification failed.'),
                'status' => $status,
                'raw' => is_string($body) ? $body : '',
            ];
        }

        return ['ok' => true, 'status' => $status, 'data' => $decoded];
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $payment
     * @param array<string, mixed> $customer
     * @param array<string, mixed>|null $method
     */
    public static function createStripeCheckoutSession(array $order, array $payment, array $customer, ?array $method = null): string
    {
        $settings = self::stripeSettings($method);
        $secretKey = trim((string)($settings['secret_key'] ?? ''));
        if ($secretKey === '') {
            throw new \RuntimeException('Stripe payment is not fully configured yet. Please select another payment method or contact support.');
        }

        $orderId = (int)($order['id'] ?? 0);
        $paymentId = (int)($payment['id'] ?? 0);
        $orderNumber = trim((string)($order['order_number'] ?? $order['number'] ?? ''));
        $currency = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($order['currency'] ?? $payment['currency'] ?? 'USD')) ?: 'USD', 0, 3));
        $amount = (float)($order['grand_total'] ?? $payment['amount'] ?? 0);
        if ($orderId <= 0 || $paymentId <= 0 || $orderNumber === '' || $amount <= 0) {
            throw new \RuntimeException('Stripe checkout could not be started for this order.');
        }

        Stripe::setApiKey($secretKey);

        $email = trim((string)($customer['email'] ?? ''));
        $metadata = [
            'context' => 'storefront_checkout',
            'order_id' => (string)$orderId,
            'order_number' => $orderNumber,
            'payment_id' => (string)$paymentId,
        ];

        $session = Session::create(array_filter([
            'mode' => 'payment',
            'client_reference_id' => $orderNumber,
            'customer_email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'success_url' => \app_url('storefront/order-success?order=' . rawurlencode($orderNumber) . '&session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => \app_url('storefront/checkout?stripe_cancelled=1&order=' . rawurlencode($orderNumber)),
            'metadata' => $metadata,
            'payment_intent_data' => [
                'metadata' => $metadata,
            ],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($currency),
                    'unit_amount' => self::stripeMinorAmount($amount, $currency),
                    'product_data' => [
                        'name' => 'Seller Africa order ' . $orderNumber,
                    ],
                ],
            ]],
        ], static fn ($value): bool => $value !== null));

        $url = trim((string)($session->url ?? ''));
        if ($url === '') {
            throw new \RuntimeException('Stripe did not return a checkout page. Please try again.');
        }

        $raw = json_decode((string)($payment['raw_response'] ?? ''), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        $raw['stripe_checkout_session'] = [
            'id' => (string)$session->id,
            'url' => $url,
            'payment_status' => (string)($session->payment_status ?? ''),
            'status' => (string)($session->status ?? ''),
            'created_at' => \sql_now(),
        ];

        \db()->query(
            "UPDATE payments
             SET provider_reference = ?, provider_status = 'checkout_created', raw_response = ?
             WHERE id = ?",
            [
                (string)$session->id,
                json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $paymentId,
            ]
        );

        return $url;
    }

    public static function paystackOrderReference(string $orderNumber): string
    {
        return 'SA-ORDER-PAYSTACK-' . preg_replace('/[^A-Za-z0-9-]/', '', $orderNumber) . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $payment
     * @param array<string, mixed> $customer
     * @param array<string, mixed>|null $method
     */
    public static function createPaystackCheckoutUrl(array $order, array $payment, array $customer, ?array $method = null): string
    {
        $orderId = (int)($order['id'] ?? 0);
        $paymentId = (int)($payment['id'] ?? 0);
        $orderNumber = trim((string)($order['order_number'] ?? $order['number'] ?? ''));
        $currency = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($order['currency'] ?? $payment['currency'] ?? 'USD')) ?: 'USD', 0, 3));
        $amount = (float)($order['grand_total'] ?? $payment['amount'] ?? 0);
        $email = trim((string)($customer['email'] ?? ''));
        $reference = trim((string)($payment['provider_reference'] ?? ''));

        if ($orderId <= 0 || $paymentId <= 0 || $orderNumber === '' || $amount <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Paystack checkout could not be started for this order.');
        }
        if ($reference === '') {
            $reference = self::paystackOrderReference($orderNumber);
        }

        $settings = self::paystackSettings($method);
        $chargeCurrency = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($settings['charge_currency'] ?? 'NGN')) ?: 'NGN', 0, 3));
        $chargeAmount = self::convertAmount($amount, $currency, $chargeCurrency);
        if ($chargeAmount <= 0) {
            throw new \RuntimeException('Paystack checkout amount could not be calculated. Please choose another payment method.');
        }

        $response = self::initializePaystackTransaction(
            $chargeAmount,
            $chargeCurrency,
            $email,
            $reference,
            \app_url('paystack_callback.php?context=storefront_checkout'),
            [
                'context' => 'storefront_checkout',
                'order_id' => (string)$orderId,
                'order_number' => $orderNumber,
                'payment_id' => (string)$paymentId,
                'order_amount' => number_format($amount, 2, '.', ''),
                'order_currency' => $currency,
                'charge_amount' => number_format($chargeAmount, 2, '.', ''),
                'charge_currency' => $chargeCurrency,
                'customer_name' => trim((string)($customer['first_name'] ?? '') . ' ' . (string)($customer['last_name'] ?? '')),
            ],
            $method
        );

        $url = trim((string)($response['data']['authorization_url'] ?? ''));
        if ($url === '') {
            throw new \RuntimeException('Paystack did not return a checkout page. Please try again.');
        }

        $existing = \db()->fetch('SELECT raw_response FROM payments WHERE id = ? LIMIT 1', [$paymentId]);
        $raw = json_decode((string)($existing['raw_response'] ?? ''), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        $raw['paystack_checkout'] = [
            'reference' => $reference,
            'access_code' => (string)($response['data']['access_code'] ?? ''),
            'authorization_url' => $url,
            'order_amount' => $amount,
            'order_currency' => $currency,
            'charge_amount' => $chargeAmount,
            'charge_currency' => $chargeCurrency,
            'created_at' => \sql_now(),
        ];

        \db()->query(
            "UPDATE payments
             SET provider_reference = ?, provider_status = 'checkout_created', raw_response = ?
             WHERE id = ?",
            [
                $reference,
                json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $paymentId,
            ]
        );

        return $url;
    }

    public static function transactionReference(string $orderNumber): string
    {
        return 'SA-KLASHA-' . preg_replace('/[^A-Za-z0-9-]/', '', $orderNumber);
    }

    /**
     * @return array<string, mixed>
     */
    public static function verifyKlashaTransaction(string $reference, ?array $method = null): array
    {
        $settings = self::settings($method);
        $endpoint = trim((string)$settings['status_endpoint']);
        $publicKey = trim((string)$settings['public_key']);

        if ($endpoint === '' || $publicKey === '') {
            return ['ok' => false, 'message' => 'Klasha verification is not configured.'];
        }

        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'cURL is not available for Klasha verification.'];
        }

        $payload = json_encode(['tnxRef' => $reference], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $curl = curl_init($endpoint);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'x-auth-token: ' . $publicKey,
            ],
        ]);

        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        $decoded = is_string($body) ? json_decode($body, true) : null;
        if ($body === false || $status >= 400 || !is_array($decoded)) {
            return [
                'ok' => false,
                'message' => $error ?: 'Klasha verification failed.',
                'status' => $status,
                'raw' => is_string($body) ? $body : '',
            ];
        }

        return ['ok' => true, 'status' => $status, 'data' => $decoded];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function applyKlashaStatus(string $reference, array $data, string $source = 'callback'): bool
    {
        CommerceSafetyService::ensureSchema();

        if (!\table_exists('payments')) {
            return false;
        }

        $status = strtolower((string)($data['status'] ?? $data['data']['status'] ?? ''));
        $providerSuccessful = in_array($status, ['successful', 'success', 'paid'], true);
        $providerFailed = in_array($status, ['failed', 'failure', 'declined', 'error'], true);
        $providerCancelled = in_array($status, ['cancelled', 'canceled', 'abandoned'], true);
        $payment = \db()->fetch(
            "SELECT p.*, o.id AS order_id, o.grand_total AS order_total, o.currency AS order_currency, o.shipping_total, o.shipping_rate_snapshot
             FROM payments p
             LEFT JOIN orders o ON o.id = p.order_id
             WHERE p.provider = 'klasha' AND p.provider_reference = ?
             LIMIT 1",
            [$reference]
        );

        if (!$payment) {
            return false;
        }

        $reportedCurrency = strtoupper((string)($data['sourceCurrency'] ?? $data['data']['sourceCurrency'] ?? $data['destinationCurrency'] ?? $data['data']['destinationCurrency'] ?? ''));
        $reportedAmount = (float)($data['sourceAmount'] ?? $data['data']['sourceAmount'] ?? $data['destinationAmount'] ?? $data['data']['destinationAmount'] ?? 0);
        $expectedCurrency = strtoupper((string)($payment['order_currency'] ?? $payment['currency'] ?? ''));
        $expectedAmount = (float)($payment['order_total'] ?? $payment['amount'] ?? 0);
        $amountMatches = $reportedAmount > 0 && abs($reportedAmount - $expectedAmount) < 0.01;
        $currencyMatches = $reportedCurrency === '' || $reportedCurrency === $expectedCurrency;
        $isSuccessful = $providerSuccessful && $amountMatches && $currencyMatches;
        $requiresReview = $providerSuccessful && (!$amountMatches || !$currencyMatches);

        $raw = json_decode((string)($payment['raw_response'] ?? ''), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        $raw['klasha_' . $source] = $data;
        $raw['klasha_' . $source . '_at'] = \sql_now();
        $raw['verification'] = [
            'provider_successful' => $providerSuccessful,
            'reported_amount' => $reportedAmount,
            'expected_amount' => $expectedAmount,
            'reported_currency' => $reportedCurrency,
            'expected_currency' => $expectedCurrency,
            'amount_matches' => $amountMatches,
            'currency_matches' => $currencyMatches,
            'source' => $source,
        ];

        $shouldNotifyPaidOrder = false;
        \db()->beginTransaction();
        try {
            $freshPayment = \db()->fetch('SELECT status FROM payments WHERE id = ? FOR UPDATE', [(int)$payment['id']]);
            if (!$freshPayment) {
                \db()->rollBack();
                return false;
            }

            $wasPaid = (string)($freshPayment['status'] ?? '') === 'paid';
            $paymentStatus = 'pending';
            $orderPaymentStatus = 'payment_pending';
            if ($isSuccessful) {
                $paymentStatus = 'paid';
                $orderPaymentStatus = 'paid';
            } elseif ($requiresReview) {
                $paymentStatus = 'under_review';
                $orderPaymentStatus = 'payment_under_review';
            } elseif ($providerCancelled) {
                $paymentStatus = 'cancelled';
                $orderPaymentStatus = 'payment_cancelled';
            } elseif ($providerFailed) {
                $paymentStatus = 'failed';
                $orderPaymentStatus = 'payment_failed';
            } elseif ($wasPaid) {
                $paymentStatus = 'paid';
                $orderPaymentStatus = 'paid';
            }

            \db()->query(
                "UPDATE payments
             SET provider_status = ?, status = ?, raw_response = ?, paid_at = CASE WHEN ? = 1 THEN COALESCE(paid_at, ?) ELSE paid_at END
             WHERE id = ?",
                [
                    $status ?: $source,
                    $paymentStatus,
                    json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $isSuccessful ? 1 : 0,
                    \sql_now(),
                    (int)$payment['id'],
                ]
            );

            if ($isSuccessful && !$wasPaid && (int)($payment['order_id'] ?? 0) > 0) {
                \db()->query(
                    "UPDATE orders
                     SET payment_status = 'paid',
                         status = CASE WHEN status IN ('pending', 'on_hold') THEN 'confirmed' ELSE status END,
                         paid_amount = ?,
                         payment_currency = ?,
                         payment_reference = ?,
                         payment_verified_at = COALESCE(payment_verified_at, ?),
                         paid_at = COALESCE(paid_at, ?)
                     WHERE id = ?",
                    [$reportedAmount, $expectedCurrency, $reference, \sql_now(), \sql_now(), (int)$payment['order_id']]
                );
                AffiliateService::markOrderPaid((int)$payment['order_id']);
                $shouldNotifyPaidOrder = true;
                CommerceSafetyService::log('Payment verified; shipment creation remains gated until paid order is processed.', [
                    'order_id' => (int)$payment['order_id'],
                    'payment_reference' => $reference,
                    'correlation' => $reference,
                ]);
            } elseif (!$isSuccessful && (int)($payment['order_id'] ?? 0) > 0) {
                \db()->query(
                    "UPDATE orders
                     SET payment_status = ?,
                         status = CASE WHEN ? IN ('payment_failed', 'payment_cancelled') AND status = 'pending' THEN 'failed' ELSE status END,
                         payment_reference = COALESCE(payment_reference, ?)
                     WHERE id = ? AND payment_status <> 'paid'",
                    [$orderPaymentStatus, $orderPaymentStatus, $reference, (int)$payment['order_id']]
                );
                if ($requiresReview) {
                    CommerceSafetyService::log('Payment moved to manual review.', [
                        'order_id' => (int)$payment['order_id'],
                        'payment_reference' => $reference,
                        'reported_amount' => $reportedAmount,
                        'expected_amount' => $expectedAmount,
                        'reported_currency' => $reportedCurrency,
                        'expected_currency' => $expectedCurrency,
                        'correlation' => $reference,
                    ]);
                }
            }

            \db()->commit();
        } catch (\Throwable $e) {
            if (\db()->pdo()->inTransaction()) {
                \db()->rollBack();
            }
            throw $e;
        }

        if ($shouldNotifyPaidOrder) {
            self::notifyPaidOrder((int)$payment['order_id']);
        }

        return $isSuccessful || $wasPaid;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function applyPaystackStatus(string $reference, array $data, string $source = 'callback'): bool
    {
        CommerceSafetyService::ensureSchema();

        if (!\table_exists('payments')) {
            return false;
        }

        $transaction = is_array($data['data'] ?? null) ? $data['data'] : $data;
        $status = strtolower((string)($transaction['status'] ?? $data['event'] ?? ''));
        $providerSuccessful = in_array($status, ['success', 'successful', 'paid', 'charge.success'], true);
        $providerFailed = in_array($status, ['failed', 'failure', 'declined', 'abandoned', 'reversed'], true);
        $reference = trim($reference) !== '' ? trim($reference) : trim((string)($transaction['reference'] ?? ''));
        if ($reference === '') {
            return false;
        }

        $payment = \db()->fetch(
            "SELECT p.*, o.id AS order_id, o.grand_total AS order_total, o.currency AS order_currency
             FROM payments p
             LEFT JOIN orders o ON o.id = p.order_id
             WHERE p.provider = 'paystack' AND p.provider_reference = ?
             LIMIT 1",
            [$reference]
        );

        if (!$payment) {
            return false;
        }

        $raw = json_decode((string)($payment['raw_response'] ?? ''), true);
        if (!is_array($raw)) {
            $raw = [];
        }

        $reportedCurrency = strtoupper((string)($transaction['currency'] ?? ''));
        $reportedAmountMinor = (int)($transaction['amount'] ?? 0);
        $orderCurrency = strtoupper((string)($payment['order_currency'] ?? $payment['currency'] ?? ''));
        $orderAmount = (float)($payment['order_total'] ?? $payment['amount'] ?? 0);
        $expectedCurrency = strtoupper((string)($raw['paystack_checkout']['charge_currency'] ?? $orderCurrency));
        $expectedAmount = (float)($raw['paystack_checkout']['charge_amount'] ?? self::convertAmount($orderAmount, $orderCurrency, $expectedCurrency));
        $expectedAmountMinor = self::paystackMinorAmount($expectedAmount, $expectedCurrency);
        $amountMatches = $reportedAmountMinor > 0 && $reportedAmountMinor === $expectedAmountMinor;
        $currencyMatches = $reportedCurrency === '' || $reportedCurrency === $expectedCurrency;
        $isSuccessful = $providerSuccessful && $amountMatches && $currencyMatches;
        $requiresReview = $providerSuccessful && (!$amountMatches || !$currencyMatches);

        $raw['paystack_' . $source] = $data;
        $raw['paystack_' . $source . '_at'] = \sql_now();
        $raw['paystack_verification'] = [
            'provider_successful' => $providerSuccessful,
            'reported_amount_minor' => $reportedAmountMinor,
            'expected_amount_minor' => $expectedAmountMinor,
            'reported_currency' => $reportedCurrency,
            'expected_currency' => $expectedCurrency,
            'order_amount' => $orderAmount,
            'order_currency' => $orderCurrency,
            'amount_matches' => $amountMatches,
            'currency_matches' => $currencyMatches,
            'source' => $source,
        ];

        $shouldNotifyPaidOrder = false;
        \db()->beginTransaction();
        try {
            $freshPayment = \db()->fetch('SELECT status FROM payments WHERE id = ? FOR UPDATE', [(int)$payment['id']]);
            if (!$freshPayment) {
                \db()->rollBack();
                return false;
            }

            $wasPaid = (string)($freshPayment['status'] ?? '') === 'paid';
            $paymentStatus = 'pending';
            $orderPaymentStatus = 'payment_pending';
            if ($isSuccessful || $wasPaid) {
                $paymentStatus = 'paid';
                $orderPaymentStatus = 'paid';
            } elseif ($requiresReview) {
                $paymentStatus = 'under_review';
                $orderPaymentStatus = 'payment_under_review';
            } elseif ($providerFailed) {
                $paymentStatus = 'failed';
                $orderPaymentStatus = 'payment_failed';
            }

            \db()->query(
                "UPDATE payments
                 SET provider_status = ?, status = ?, raw_response = ?, paid_at = CASE WHEN ? = 1 THEN COALESCE(paid_at, ?) ELSE paid_at END
                 WHERE id = ?",
                [
                    $status ?: $source,
                    $paymentStatus,
                    json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $isSuccessful ? 1 : 0,
                    \sql_now(),
                    (int)$payment['id'],
                ]
            );

            if ($isSuccessful && !$wasPaid && (int)($payment['order_id'] ?? 0) > 0) {
                \db()->query(
                    "UPDATE orders
                     SET payment_status = 'paid',
                         status = CASE WHEN status IN ('pending', 'on_hold') THEN 'confirmed' ELSE status END,
                         paid_amount = ?,
                         payment_currency = ?,
                         payment_reference = ?,
                         payment_verified_at = COALESCE(payment_verified_at, ?),
                         paid_at = COALESCE(paid_at, ?)
                     WHERE id = ?",
                    [$orderAmount, $orderCurrency, $reference, \sql_now(), \sql_now(), (int)$payment['order_id']]
                );
                AffiliateService::markOrderPaid((int)$payment['order_id']);
                $shouldNotifyPaidOrder = true;
                CommerceSafetyService::log('Paystack payment verified; shipment creation remains gated until paid order is processed.', [
                    'order_id' => (int)$payment['order_id'],
                    'payment_reference' => $reference,
                    'correlation' => $reference,
                ]);
            } elseif (!$isSuccessful && (int)($payment['order_id'] ?? 0) > 0) {
                \db()->query(
                    "UPDATE orders
                     SET payment_status = ?,
                         status = CASE WHEN ? = 'payment_failed' AND status = 'pending' THEN 'failed' ELSE status END,
                         payment_reference = COALESCE(payment_reference, ?)
                     WHERE id = ? AND payment_status <> 'paid'",
                    [$orderPaymentStatus, $orderPaymentStatus, $reference, (int)$payment['order_id']]
                );
                if ($requiresReview) {
                    CommerceSafetyService::log('Paystack payment moved to manual review.', [
                        'order_id' => (int)$payment['order_id'],
                        'payment_reference' => $reference,
                        'reported_amount_minor' => $reportedAmountMinor,
                        'expected_amount_minor' => $expectedAmountMinor,
                        'reported_currency' => $reportedCurrency,
                        'expected_currency' => $expectedCurrency,
                        'order_amount' => $orderAmount,
                        'order_currency' => $orderCurrency,
                        'correlation' => $reference,
                    ]);
                }
            }

            \db()->commit();
        } catch (\Throwable $e) {
            if (\db()->pdo()->inTransaction()) {
                \db()->rollBack();
            }
            throw $e;
        }

        if ($shouldNotifyPaidOrder) {
            self::notifyPaidOrder((int)$payment['order_id']);
        }

        return $isSuccessful || $wasPaid;
    }

    public static function handleStripeWebhook(string $payload, string $signature, ?object $verifiedEvent = null): bool
    {
        $settings = self::stripeSettings();
        $webhookSecret = trim((string)($settings['webhook_secret'] ?? ''));
        if ($verifiedEvent !== null) {
            $event = $verifiedEvent;
        } elseif ($webhookSecret !== '') {
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        } else {
            $decoded = json_decode($payload);
            if (!is_object($decoded)) {
                throw new \RuntimeException('Invalid Stripe webhook payload.');
            }
            $event = $decoded;
        }

        $type = (string)($event->type ?? '');
        $object = $event->data->object ?? null;
        if (!is_object($object) || !str_starts_with($type, 'checkout.session.')) {
            return false;
        }

        return self::applyStripeCheckoutSession($object, $type);
    }

    public static function verifyStripeCheckoutSession(string $sessionId): bool
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '' || !\table_exists('payments')) {
            return false;
        }

        $payment = \db()->fetch(
            "SELECT p.*, pm.settings AS method_settings
             FROM payments p
             LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
             WHERE p.provider = 'stripe' AND p.provider_reference = ?
             LIMIT 1",
            [$sessionId]
        );
        if (!$payment) {
            return false;
        }

        $settings = self::stripeSettings(['settings' => $payment['method_settings'] ?? '']);
        $secretKey = trim((string)($settings['secret_key'] ?? ''));
        if ($secretKey === '') {
            return false;
        }

        Stripe::setApiKey($secretKey);
        $session = Session::retrieve($sessionId);

        return self::applyStripeCheckoutSession($session, 'stripe.session.refresh');
    }

    private static function applyStripeCheckoutSession(object $session, string $eventType): bool
    {
        CommerceSafetyService::ensureSchema();

        if (!\table_exists('payments')) {
            return false;
        }

        $metadata = $session->metadata ?? (object)[];
        $context = (string)($metadata->context ?? '');
        $paymentId = (int)($metadata->payment_id ?? 0);
        $sessionId = (string)($session->id ?? '');

        if ($context !== 'storefront_checkout' && $paymentId <= 0) {
            return false;
        }

        $payment = $paymentId > 0
            ? \db()->fetch(
                "SELECT p.*, o.id AS order_id, o.grand_total AS order_total, o.currency AS order_currency
                 FROM payments p
                 LEFT JOIN orders o ON o.id = p.order_id
                 WHERE p.id = ? AND p.provider = 'stripe'
                 LIMIT 1",
                [$paymentId]
            )
            : \db()->fetch(
                "SELECT p.*, o.id AS order_id, o.grand_total AS order_total, o.currency AS order_currency
                 FROM payments p
                 LEFT JOIN orders o ON o.id = p.order_id
                 WHERE p.provider = 'stripe' AND p.provider_reference = ?
                 LIMIT 1",
                [$sessionId]
            );

        if (!$payment) {
            return false;
        }

        $paymentStatus = strtolower((string)($session->payment_status ?? ''));
        $checkoutStatus = strtolower((string)($session->status ?? ''));
        $providerSuccessful = in_array($paymentStatus, ['paid', 'no_payment_required'], true)
            || $eventType === 'checkout.session.async_payment_succeeded';
        $providerFailed = in_array($eventType, ['checkout.session.expired', 'checkout.session.async_payment_failed'], true)
            || in_array($checkoutStatus, ['expired'], true);

        $reportedCurrency = strtoupper((string)($session->currency ?? ''));
        $expectedCurrency = strtoupper((string)($payment['order_currency'] ?? $payment['currency'] ?? ''));
        $reportedAmountMinor = (int)($session->amount_total ?? 0);
        $expectedAmount = (float)($payment['order_total'] ?? $payment['amount'] ?? 0);
        $expectedAmountMinor = self::stripeMinorAmount($expectedAmount, $expectedCurrency);
        $amountMatches = $reportedAmountMinor > 0 && $reportedAmountMinor === $expectedAmountMinor;
        $currencyMatches = $reportedCurrency === '' || $reportedCurrency === $expectedCurrency;
        $isSuccessful = $providerSuccessful && $amountMatches && $currencyMatches;
        $requiresReview = $providerSuccessful && (!$amountMatches || !$currencyMatches);

        $raw = json_decode((string)($payment['raw_response'] ?? ''), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        $raw['stripe_webhook'] = [
            'event' => $eventType,
            'session_id' => $sessionId,
            'payment_status' => $paymentStatus,
            'status' => $checkoutStatus,
            'amount_total' => $reportedAmountMinor,
            'currency' => $reportedCurrency,
            'received_at' => \sql_now(),
        ];
        $raw['verification'] = [
            'provider_successful' => $providerSuccessful,
            'reported_amount_minor' => $reportedAmountMinor,
            'expected_amount_minor' => $expectedAmountMinor,
            'reported_currency' => $reportedCurrency,
            'expected_currency' => $expectedCurrency,
            'amount_matches' => $amountMatches,
            'currency_matches' => $currencyMatches,
            'source' => 'stripe_webhook',
        ];

        $shouldNotifyPaidOrder = false;
        \db()->beginTransaction();
        try {
            $freshPayment = \db()->fetch('SELECT status FROM payments WHERE id = ? FOR UPDATE', [(int)$payment['id']]);
            if (!$freshPayment) {
                \db()->rollBack();
                return false;
            }

            $wasPaid = (string)($freshPayment['status'] ?? '') === 'paid';
            $newPaymentStatus = 'pending';
            $newOrderPaymentStatus = 'payment_pending';
            if ($isSuccessful || $wasPaid) {
                $newPaymentStatus = 'paid';
                $newOrderPaymentStatus = 'paid';
            } elseif ($requiresReview) {
                $newPaymentStatus = 'under_review';
                $newOrderPaymentStatus = 'payment_under_review';
            } elseif ($providerFailed) {
                $newPaymentStatus = 'failed';
                $newOrderPaymentStatus = 'payment_failed';
            }

            \db()->query(
                "UPDATE payments
                 SET provider_reference = ?,
                     provider_status = ?,
                     status = ?,
                     raw_response = ?,
                     paid_at = CASE WHEN ? = 1 THEN COALESCE(paid_at, ?) ELSE paid_at END
                 WHERE id = ?",
                [
                    $sessionId !== '' ? $sessionId : (string)$payment['provider_reference'],
                    $paymentStatus ?: $checkoutStatus ?: $eventType,
                    $newPaymentStatus,
                    json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $isSuccessful ? 1 : 0,
                    \sql_now(),
                    (int)$payment['id'],
                ]
            );

            if ($isSuccessful && !$wasPaid && (int)($payment['order_id'] ?? 0) > 0) {
                \db()->query(
                    "UPDATE orders
                     SET payment_status = 'paid',
                         status = CASE WHEN status IN ('pending', 'on_hold') THEN 'confirmed' ELSE status END,
                         paid_amount = ?,
                         payment_currency = ?,
                         payment_reference = ?,
                         payment_verified_at = COALESCE(payment_verified_at, ?),
                         paid_at = COALESCE(paid_at, ?)
                     WHERE id = ?",
                    [$expectedAmount, $expectedCurrency, $sessionId, \sql_now(), \sql_now(), (int)$payment['order_id']]
                );
                AffiliateService::markOrderPaid((int)$payment['order_id']);
                $shouldNotifyPaidOrder = true;
                CommerceSafetyService::log('Stripe payment verified; shipment creation remains gated until paid order is processed.', [
                    'order_id' => (int)$payment['order_id'],
                    'payment_reference' => $sessionId,
                    'correlation' => $sessionId,
                ]);
            } elseif (!$isSuccessful && (int)($payment['order_id'] ?? 0) > 0) {
                \db()->query(
                    "UPDATE orders
                     SET payment_status = ?,
                         status = CASE WHEN ? = 'payment_failed' AND status = 'pending' THEN 'failed' ELSE status END,
                         payment_reference = COALESCE(payment_reference, ?)
                     WHERE id = ? AND payment_status <> 'paid'",
                    [$newOrderPaymentStatus, $newOrderPaymentStatus, $sessionId, (int)$payment['order_id']]
                );
                if ($requiresReview) {
                    CommerceSafetyService::log('Stripe payment moved to manual review.', [
                        'order_id' => (int)$payment['order_id'],
                        'payment_reference' => $sessionId,
                        'reported_amount_minor' => $reportedAmountMinor,
                        'expected_amount_minor' => $expectedAmountMinor,
                        'reported_currency' => $reportedCurrency,
                        'expected_currency' => $expectedCurrency,
                        'correlation' => $sessionId,
                    ]);
                }
            }

            \db()->commit();
        } catch (\Throwable $e) {
            if (\db()->pdo()->inTransaction()) {
                \db()->rollBack();
            }
            throw $e;
        }

        if ($shouldNotifyPaidOrder) {
            self::notifyPaidOrder((int)$payment['order_id']);
        }

        return $isSuccessful || $wasPaid;
    }

    private static function notifyPaidOrder(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        try {
            $order = \db()->fetch(
                "SELECT o.*, COALESCE(u.email, o.guest_email, a.email) AS email,
                        COALESCE(u.first_name, a.first_name, '') AS first_name,
                        COALESCE(u.last_name, a.last_name, '') AS last_name
                 FROM orders o
                 LEFT JOIN users u ON u.id = o.customer_id
                 LEFT JOIN addresses a ON a.id = o.shipping_address_id
                 WHERE o.id = ?
                 LIMIT 1",
                [$orderId]
            );
            if (!$order) {
                return;
            }

            $items = \table_exists('order_items')
                ? \db()->fetchAll(
                    "SELECT name, sku, quantity, unit_price, total
                     FROM order_items
                     WHERE order_id = ?
                     ORDER BY id ASC",
                    [$orderId]
                )
                : [];

            $payment = \table_exists('payments')
                ? \db()->fetch(
                    "SELECT p.*, pm.code AS method_code, pm.name AS method_name
                     FROM payments p
                     LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
                     WHERE p.order_id = ?
                     ORDER BY p.id DESC
                     LIMIT 1",
                    [$orderId]
                )
                : null;

            NotificationService::orderCreated(
                [
                    'id' => $orderId,
                    'number' => (string)$order['order_number'],
                    'order_number' => (string)$order['order_number'],
                    'total' => StorefrontTemplateService::money((float)$order['grand_total'], (string)$order['currency']),
                    'grand_total' => (float)$order['grand_total'],
                    'currency' => (string)$order['currency'],
                    'payment_status' => (string)($order['payment_status'] ?? 'paid'),
                    'tracking_url' => \app_url('track-order?order=' . rawurlencode((string)$order['order_number'])),
                ],
                [
                    'email' => (string)($order['email'] ?? ''),
                    'first_name' => (string)($order['first_name'] ?? ''),
                    'last_name' => (string)($order['last_name'] ?? ''),
                ],
                $items,
                [
                    'code' => (string)($payment['method_code'] ?? ''),
                    'name' => (string)($payment['method_name'] ?? $payment['provider'] ?? ''),
                    'provider' => (string)($payment['provider'] ?? ''),
                    'status' => (string)($payment['status'] ?? 'paid'),
                    'payment_status' => (string)($order['payment_status'] ?? 'paid'),
                    'reference' => (string)($payment['provider_reference'] ?? $order['payment_reference'] ?? ''),
                ]
            );
        } catch (\Throwable $e) {
            error_log('Paid order notification failed: ' . $e->getMessage());
        }
    }

    private static function stripeMinorAmount(float $amount, string $currency): int
    {
        $zeroDecimal = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG',
            'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];

        return in_array(strtoupper($currency), $zeroDecimal, true)
            ? (int)round($amount)
            : (int)round($amount * 100);
    }

    public static function paystackMinorAmount(float $amount, string $currency): int
    {
        return (int)round($amount * 100);
    }

    public static function convertAmount(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);
        if ($fromCurrency === $toCurrency) {
            return round($amount, 2);
        }
        if (!\table_exists('currencies')) {
            return round($amount, 2);
        }

        $rows = \db()->fetchAll(
            'SELECT code, exchange_rate FROM currencies WHERE code IN (?, ?) AND is_enabled = 1',
            [$fromCurrency, $toCurrency]
        );
        $rates = [];
        foreach ($rows as $row) {
            $rates[strtoupper((string)$row['code'])] = max(0.000001, (float)($row['exchange_rate'] ?? 1));
        }
        if (empty($rates[$fromCurrency]) || empty($rates[$toCurrency])) {
            return round($amount, 2);
        }

        return round(($amount / $rates[$fromCurrency]) * $rates[$toCurrency], 2);
    }
}
