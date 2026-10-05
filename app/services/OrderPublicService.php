<?php

declare(strict_types=1);

namespace App;

final class OrderPublicService
{
    public function __construct(private Database $db)
    {
    }

    public function find(string $orderNumber): ?array
    {
        $orderNumber = strtoupper(trim($orderNumber));
        if ($orderNumber === '' || !\table_exists('orders')) {
            return null;
        }

        $order = $this->db->fetch(
            "SELECT o.*, a.first_name, a.last_name, a.phone, a.email, a.address_line1, a.address_line2,
                    a.city, a.state, a.postcode, a.country_code
             FROM orders o
             LEFT JOIN addresses a ON a.id = o.shipping_address_id
             WHERE o.order_number = ?
             LIMIT 1",
            [$orderNumber]
        );

        if (!$order) {
            return null;
        }

        $items = \table_exists('order_items')
            ? $this->db->fetchAll(
                "SELECT name, sku, quantity, unit_price, total
                 FROM order_items
                 WHERE order_id = ?
                 ORDER BY id ASC",
                [(int)$order['id']]
            )
            : [];

        $payment = null;
        if (\table_exists('payments')) {
            $payment = $this->db->fetch(
                "SELECT p.*, pm.name AS method_name, pm.code AS method_code
                 FROM payments p
                 LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
                 WHERE p.order_id = ?
                 ORDER BY p.id DESC
                 LIMIT 1",
                [(int)$order['id']]
            );
        }

        return [
            'order' => $order,
            'items' => $items,
            'payment' => $payment,
            'timeline' => $this->timeline($order, $payment),
        ];
    }

    private function timeline(array $order, ?array $payment): array
    {
        $placed = (string)($order['placed_at'] ?: $order['created_at'] ?: '');
        $status = (string)($order['status'] ?? 'pending');
        $paymentStatus = (string)($order['payment_status'] ?? 'unpaid');
        $fulfillment = (string)($order['fulfillment_status'] ?? 'unfulfilled');

        return [
            ['label' => 'Order received', 'status' => 'complete', 'date' => $placed, 'note' => 'Your order has been created.'],
            ['label' => 'Payment', 'status' => in_array($paymentStatus, ['paid', 'authorized'], true) ? 'complete' : 'current', 'date' => (string)($payment['paid_at'] ?? ''), 'note' => ucwords(str_replace('_', ' ', $paymentStatus))],
            ['label' => 'Processing', 'status' => in_array($status, ['confirmed', 'processing', 'paid', 'partially_shipped', 'shipped', 'completed'], true) ? 'complete' : 'pending', 'date' => '', 'note' => ucwords(str_replace('_', ' ', $status))],
            ['label' => 'Fulfillment', 'status' => in_array($fulfillment, ['partial', 'fulfilled'], true) ? 'complete' : 'pending', 'date' => '', 'note' => ucwords(str_replace('_', ' ', $fulfillment))],
        ];
    }
}
