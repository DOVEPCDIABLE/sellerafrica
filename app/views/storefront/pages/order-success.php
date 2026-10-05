<?php
$brand = app_branding();
$order = $order ?? null;
$orderItems = $orderItems ?? [];
$payment = $payment ?? null;
$currency = (string)($order['currency'] ?? 'USD');
$money = static fn (mixed $amount): string => \App\StorefrontTemplateService::money((float)$amount, $currency);
$customerName = trim((string)($order['first_name'] ?? '') . ' ' . (string)($order['last_name'] ?? ''));
$addressParts = array_filter([
    $order['address_line1'] ?? null,
    $order['address_line2'] ?? null,
    $order['city'] ?? null,
    $order['state'] ?? null,
    $order['postcode'] ?? null,
    $order['country_code'] ?? null,
], static fn (mixed $value): bool => trim((string)$value) !== '');
?>

<section class="sa-order-success">
    <div class="container">
        <?php if ($order): ?>
            <div class="sa-order-success__hero">
                <span>Order confirmed</span>
                <h1>Thank you for your order.</h1>
                <p>Your payment was confirmed and your order has been received.</p>
            </div>

            <div class="sa-order-success__grid">
                <article class="sa-order-success__panel">
                    <h2>Order Summary</h2>
                    <dl class="sa-order-success__facts">
                        <div><dt>Order number</dt><dd><?= e((string)$order['order_number']) ?></dd></div>
                        <div><dt>Date</dt><dd><?= e((string)($order['placed_at'] ?: $order['created_at'])) ?></dd></div>
                        <div><dt>Status</dt><dd><?= e(ucwords(str_replace('_', ' ', (string)$order['status']))) ?></dd></div>
                        <div><dt>Payment</dt><dd><?= e(ucwords(str_replace('_', ' ', (string)$order['payment_status']))) ?></dd></div>
                        <div><dt>Total</dt><dd><?= e($money($order['grand_total'])) ?></dd></div>
                    </dl>
                </article>

                <article class="sa-order-success__panel">
                    <h2>Payment Details</h2>
                    <dl class="sa-order-success__facts">
                        <div><dt>Method</dt><dd><?= e((string)($payment['method_name'] ?? 'Selected payment method')) ?></dd></div>
                        <div><dt>Provider</dt><dd><?= e((string)($payment['provider'] ?? 'Manual')) ?></dd></div>
                        <div><dt>Reference</dt><dd><?= e((string)($payment['provider_reference'] ?? $order['order_number'])) ?></dd></div>
                        <div><dt>Amount</dt><dd><?= e($money($payment['amount'] ?? $order['grand_total'])) ?></dd></div>
                    </dl>
                    <p class="sa-order-success__note">Payment is confirmed. We will keep you updated as the order moves through processing and delivery.</p>
                </article>
            </div>

            <article class="sa-order-success__panel sa-order-success__items">
                <h2>Items Ordered</h2>
                <div class="sa-order-success__table">
                    <?php foreach ($orderItems as $item): ?>
                        <div class="sa-order-success__row">
                            <div>
                                <strong><?= e((string)$item['name']) ?></strong>
                                <?php if (!empty($item['sku'])): ?><span>SKU: <?= e((string)$item['sku']) ?></span><?php endif; ?>
                            </div>
                            <span>Qty <?= e((string)(float)$item['quantity']) ?></span>
                            <span><?= e($money($item['total'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="sa-order-success__totals">
                    <span>Subtotal</span><strong><?= e($money($order['subtotal'])) ?></strong>
                    <span>Shipping</span><strong><?= e($money($order['shipping_total'])) ?></strong>
                    <span>Tax</span><strong><?= e($money($order['tax_total'])) ?></strong>
                    <span>Total</span><strong><?= e($money($order['grand_total'])) ?></strong>
                </div>
            </article>

            <div class="sa-order-success__grid">
                <article class="sa-order-success__panel">
                    <h2>Delivery Details</h2>
                    <p><strong><?= e($customerName !== '' ? $customerName : 'Customer') ?></strong></p>
                    <p><?= e(implode(', ', $addressParts)) ?></p>
                    <p><?= e((string)($order['phone'] ?? '')) ?></p>
                    <p><?= e((string)($order['email'] ?? $order['guest_email'] ?? '')) ?></p>
                </article>

                <article class="sa-order-success__panel">
                    <h2>What Happens Next</h2>
                    <p><?= e($brand['name']) ?> will keep this order in your marketplace records. You can continue shopping or sign in to view user account orders when available.</p>
                    <div class="sa-order-success__actions">
                        <a class="mt-btn" href="<?= e(app_url('storefront/order-pdf?order=' . rawurlencode((string)$order['order_number']))) ?>">Download Order Details</a>
                        <a class="mt-btn-3" href="<?= e(app_url('track-order?order=' . rawurlencode((string)$order['order_number']))) ?>">Track Order</a>
                        <a class="mt-btn" href="<?= e(app_url('shop')) ?>">Continue Shopping</a>
                        <a class="mt-btn-3" href="<?= e(app_url('store')) ?>">Back To Store</a>
                    </div>
                </article>
            </div>
        <?php else: ?>
            <div class="sa-order-success__hero">
                <span>Order not found</span>
                <h1>We could not find that order.</h1>
                <p>Please check the confirmation link or return to the shop.</p>
                <div class="sa-order-success__actions">
                    <a class="mt-btn" href="<?= e(app_url('shop')) ?>">Shop Products</a>
                    <a class="mt-btn-3" href="<?= e(app_url('cart')) ?>">View Cart</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
