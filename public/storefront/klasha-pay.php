<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\PaymentService;
use App\StorefrontTemplateService;

$orderNumber = trim((string)($_GET['order'] ?? ''));
$order = null;
$payment = null;

if ($orderNumber !== '') {
    $order = db()->fetch(
        "SELECT id, order_number, guest_email, currency, grand_total, payment_status
         FROM orders
         WHERE order_number = ?
         LIMIT 1",
        [$orderNumber]
    );
}

if ($order) {
    $payment = db()->fetch(
        "SELECT p.*, pm.name AS method_name, pm.settings AS method_settings
         FROM payments p
         LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
         WHERE p.order_id = ? AND p.provider = 'klasha'
         ORDER BY p.id DESC
         LIMIT 1",
        [(int)$order['id']]
    );
}

if (!$order || !$payment) {
    http_response_code(404);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Klasha payment unavailable</title></head><body><h1>Payment not found</h1><p>This Klasha payment link is not available.</p>' . app_chat_widget_embed() . '</body></html>';
    exit;
}

$settings = PaymentService::settings(['settings' => $payment['method_settings'] ?? '']);
if (!PaymentService::isKlashaConfigured(['settings' => $payment['method_settings'] ?? ''])) {
    http_response_code(503);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Klasha not configured</title></head><body><h1>Klasha is not configured</h1><p>Please contact support or choose another payment method.</p>' . app_chat_widget_embed() . '</body></html>';
    exit;
}

$customer = db()->fetch(
    "SELECT COALESCE(u.email, o.guest_email) AS email,
            COALESCE(u.display_name, CONCAT(a.first_name, ' ', a.last_name), o.guest_email) AS name,
            COALESCE(u.first_name, a.first_name, '') AS first_name,
            COALESCE(u.last_name, a.last_name, '') AS last_name,
            COALESCE(u.phone, a.phone, '') AS phone
     FROM orders o
     LEFT JOIN users u ON u.id = o.customer_id
     LEFT JOIN addresses a ON a.id = o.billing_address_id
     WHERE o.id = ?
     LIMIT 1",
    [(int)$order['id']]
) ?: [];

$fullName = trim((string)($customer['name'] ?? 'Seller Africa Customer')) ?: 'Seller Africa Customer';
$firstName = trim((string)($customer['first_name'] ?? '')) ?: strtok($fullName, ' ') ?: 'Customer';
$lastName = trim((string)($customer['last_name'] ?? '')) ?: trim(substr($fullName, strlen($firstName))) ?: 'Customer';
$email = trim((string)($customer['email'] ?? $order['guest_email'] ?? ''));
$phone = trim((string)($customer['phone'] ?? ''));
$currency = strtoupper((string)$order['currency']);
$destinationCurrency = strtoupper((string)($settings['destination_currency'] ?: $currency));
$amount = round((float)$order['grand_total'], 2);
$reference = (string)$payment['provider_reference'];
$successUrl = app_url('storefront/order-success?order=' . rawurlencode((string)$order['order_number']));
$statusUrl = app_url('track-order?order=' . rawurlencode((string)$order['order_number']));
$callbackUrl = app_url('api/payments/klasha/callback');

$payload = [
    'merchantKey' => (string)$settings['public_key'],
    'businessId' => (string)$settings['business_id'],
    'environment' => (string)($settings['mode'] ?? 'test') !== 'live',
    'amount' => $amount,
    'sourceAmount' => (string)$amount,
    'currency' => $currency,
    'destinationCurrency' => $destinationCurrency,
    'description' => 'Seller Africa order ' . (string)$order['order_number'],
    'txRef' => $reference,
    'fullname' => $fullName,
    'firstName' => $firstName,
    'lastName' => $lastName,
    'email' => $email,
    'phone' => $phone,
    'callbackUrl' => $callbackUrl,
    'successUrl' => $successUrl,
    'statusUrl' => $statusUrl,
    'orderNumber' => (string)$order['order_number'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pay with Klasha | Seller Africa</title>
    <script type="text/javascript" src="https://js.klasha.com/pay.js"></script>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f7faf8; color: #111; }
        .klasha-shell { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        .klasha-card { width: min(520px, 100%); background: #fff; border: 1px solid #e7ece7; border-radius: 18px; padding: 28px; box-shadow: 0 24px 70px rgba(17,17,17,.08); }
        .klasha-card h1 { margin: 0 0 10px; font-size: 28px; }
        .klasha-card p { color: #5d665f; line-height: 1.55; }
        .klasha-total { display: flex; justify-content: space-between; gap: 20px; padding: 16px 0; border-top: 1px solid #edf1ed; border-bottom: 1px solid #edf1ed; margin: 20px 0; }
        .klasha-total strong { font-size: 22px; color: #0b7a3d; }
        .klasha-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .klasha-btn { border: 0; border-radius: 999px; background: #16a34a; color: #fff; padding: 13px 22px; font-weight: 800; cursor: pointer; }
        .klasha-link { color: #0b7a3d; font-weight: 800; text-decoration: none; align-self: center; }
        .klasha-status { margin-top: 16px; min-height: 22px; font-weight: 700; color: #6d6d76; }
    </style>
</head>
<body>
    <main class="klasha-shell">
        <section class="klasha-card">
            <h1>Pay with Klasha</h1>
            <p>Order <?= e((string)$order['order_number']) ?> is ready for secure Klasha payment.</p>
            <div class="klasha-total">
                <span>Total</span>
                <strong><?= e(StorefrontTemplateService::money($amount, $currency)) ?></strong>
            </div>
            <div class="klasha-actions">
                <button class="klasha-btn" type="button" onclick="payWithKlasha()">Open Klasha Payment</button>
                <a class="klasha-link" href="<?= e($statusUrl) ?>">Check payment status</a>
            </div>
            <div class="klasha-status" id="klasha-status">The Klasha payment window should open automatically.</div>
        </section>
    </main>
    <script>
        const klashaConfig = <?= json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        const statusNode = document.getElementById('klasha-status');

        async function reportKlashaResult(data) {
            statusNode.textContent = 'Confirming Klasha payment...';
            try {
                const response = await fetch(klashaConfig.callbackUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        reference: klashaConfig.txRef,
                        order: klashaConfig.orderNumber,
                        klasha: data || {}
                    })
                });
                const json = await response.json();
                statusNode.textContent = json.message || 'Payment response received.';
                if (json.ok && json.paid) {
                    window.location.href = klashaConfig.successUrl;
                }
            } catch (error) {
                statusNode.textContent = 'Payment response received. We could not verify it automatically yet.';
            }
        }

        function payWithKlasha() {
            if (typeof KlashaClient === 'undefined') {
                statusNode.textContent = 'Klasha checkout could not load. Please refresh and try again.';
                return;
            }

            const paymentKit = {
                tx_ref: klashaConfig.txRef,
                fullname: klashaConfig.fullname,
                firstName: klashaConfig.firstName,
                lastName: klashaConfig.lastName,
                email: klashaConfig.email,
                phone_number: klashaConfig.phone,
                businessId: klashaConfig.businessId,
                merchantKey: klashaConfig.merchantKey,
                amount: klashaConfig.amount,
                sourceAmount: klashaConfig.sourceAmount,
                rate: 1
            };

            const client = new KlashaClient(
                klashaConfig.merchantKey,
                klashaConfig.businessId,
                klashaConfig.amount,
                klashaConfig.description,
                reportKlashaResult,
                klashaConfig.currency,
                klashaConfig.destinationCurrency,
                paymentKit,
                klashaConfig.environment
            );

            client.init();
        }

        document.addEventListener('DOMContentLoaded', payWithKlasha);
    </script>
    <?php app_render_chat_widget(); ?>
</body>
</html>
