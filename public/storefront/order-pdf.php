<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\OrderPublicService;
use App\SimplePdfService;
use App\StorefrontTemplateService;

$orderNumber = trim((string)($_GET['order'] ?? ''));
$record = (new OrderPublicService(db()))->find($orderNumber);

if (!$record) {
    http_response_code(404);
    echo 'Order not found.';
    exit;
}

$brand = app_branding();
$order = $record['order'];
$items = $record['items'];
$payment = $record['payment'];
$currency = (string)($order['currency'] ?? 'USD');
$money = static fn (mixed $amount): string => StorefrontTemplateService::money((float)$amount, $currency);
$customerName = trim((string)($order['first_name'] ?? '') . ' ' . (string)($order['last_name'] ?? ''));
$address = implode(', ', array_filter([
    $order['address_line1'] ?? null,
    $order['address_line2'] ?? null,
    $order['city'] ?? null,
    $order['state'] ?? null,
    $order['postcode'] ?? null,
    $order['country_code'] ?? null,
], static fn (mixed $value): bool => trim((string)$value) !== ''));

$lines = [
    (string)$brand['name'] . ' Order Details',
    '',
    'Order Number: ' . (string)$order['order_number'],
    'Date: ' . (string)($order['placed_at'] ?: $order['created_at']),
    'Order Status: ' . ucwords(str_replace('_', ' ', (string)$order['status'])),
    'Payment Status: ' . ucwords(str_replace('_', ' ', (string)$order['payment_status'])),
    'Fulfillment Status: ' . ucwords(str_replace('_', ' ', (string)$order['fulfillment_status'])),
    '',
    'Customer: ' . ($customerName !== '' ? $customerName : 'Customer'),
    'Email: ' . (string)($order['email'] ?? $order['guest_email'] ?? ''),
    'Phone: ' . (string)($order['phone'] ?? ''),
    'Delivery Address: ' . $address,
    '',
    'Payment Method: ' . (string)($payment['method_name'] ?? 'Selected payment method'),
    'Payment Provider: ' . (string)($payment['provider'] ?? 'Manual'),
    'Payment Reference: ' . (string)($payment['provider_reference'] ?? $order['order_number']),
    '',
    'Items',
];

foreach ($items as $item) {
    $quantity = (float)($item['quantity'] ?? 1);
    $sku = trim((string)($item['sku'] ?? ''));
    $lines[] = '- ' . (string)$item['name'] . ($sku !== '' ? " ({$sku})" : '') . ' | Qty ' . $quantity . ' | ' . $money($item['total'] ?? 0);
}

$lines = array_merge($lines, [
    '',
    'Subtotal: ' . $money($order['subtotal']),
    'Shipping: ' . $money($order['shipping_total']),
    'Tax: ' . $money($order['tax_total']),
    'Total: ' . $money($order['grand_total']),
    '',
    'Track this order: ' . app_url('track-order?order=' . rawurlencode((string)$order['order_number'])),
]);

$pdf = SimplePdfService::document($lines, (string)$brand['name'] . ' Order ' . (string)$order['order_number']);
$filename = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$order['order_number']) ?: 'order-details';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
