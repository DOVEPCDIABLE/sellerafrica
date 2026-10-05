<?php
$orderNumber = $orderNumber ?? '';
$record = $record ?? null;
$order = $record['order'] ?? null;
$items = $record['items'] ?? [];
$timeline = $record['timeline'] ?? [];
$currency = (string)($order['currency'] ?? 'USD');
$money = static fn (mixed $amount): string => \App\StorefrontTemplateService::money((float)$amount, $currency);
$paymentStatus = strtolower((string)($order['payment_status'] ?? ''));
$paymentLabel = match ($paymentStatus) {
    'paid', 'authorized' => 'Paid',
    'failed', 'payment_failed' => 'Payment Failed',
    'cancelled', 'canceled', 'payment_cancelled' => 'Payment Cancelled',
    'under_review', 'payment_under_review' => 'Payment Under Review',
    'unpaid', 'pending', 'payment_pending', '' => 'Pending Payment',
    default => ucwords(str_replace('_', ' ', $paymentStatus)),
};
?>

<section class="sa-order-success sa-track-order">
    <div class="container">
        <div class="sa-order-success__hero">
            <span>Order tracking</span>
            <h1>Track your order.</h1>
            <p>Enter your order number to see the latest order, payment, and fulfillment status.</p>
        </div>

        <form class="sa-track-order__form" action="<?= e(app_url('track-order')) ?>" method="GET">
            <input type="text" name="order" value="<?= e((string)$orderNumber) ?>" placeholder="Example: SA-20260702220958-EE21A1" required>
            <button class="mt-btn" type="submit">Track Order</button>
        </form>

        <?php if ($order): ?>
            <?php if (in_array($paymentStatus, ['failed', 'payment_failed', 'cancelled', 'canceled', 'payment_cancelled'], true)): ?>
                <article class="sa-order-success__panel">
                    <h2><?= e($paymentLabel) ?></h2>
                    <p>Your order has not been confirmed because the payment did not go through. Please return to checkout and try payment again.</p>
                    <div class="sa-order-success__actions">
                        <a class="mt-btn" href="<?= e(app_url('cart')) ?>">Return To Cart</a>
                    </div>
                </article>
            <?php endif; ?>
            <div class="sa-order-success__grid">
                <article class="sa-order-success__panel">
                    <h2>Current Status</h2>
                    <dl class="sa-order-success__facts">
                        <div><dt>Order number</dt><dd><?= e((string)$order['order_number']) ?></dd></div>
                        <div><dt>Order status</dt><dd><?= e(ucwords(str_replace('_', ' ', (string)$order['status']))) ?></dd></div>
                        <div><dt>Payment</dt><dd><?= e($paymentLabel) ?></dd></div>
                        <div><dt>Fulfillment</dt><dd><?= e(ucwords(str_replace('_', ' ', (string)$order['fulfillment_status']))) ?></dd></div>
                        <div><dt>Total</dt><dd><?= e($money($order['grand_total'])) ?></dd></div>
                    </dl>
                    <div class="sa-order-success__actions">
                        <a class="mt-btn-3" href="<?= e(app_url('storefront/order-pdf?order=' . rawurlencode((string)$order['order_number']))) ?>">Download Order Details</a>
                    </div>
                </article>

                <article class="sa-order-success__panel">
                    <h2>Timeline</h2>
                    <div class="sa-track-order__timeline">
                        <?php foreach ($timeline as $step): ?>
                            <div class="sa-track-order__step is-<?= e((string)$step['status']) ?>">
                                <strong><?= e((string)$step['label']) ?></strong>
                                <span><?= e((string)$step['note']) ?></span>
                                <?php if (!empty($step['date'])): ?><small><?= e((string)$step['date']) ?></small><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>

            <article class="sa-order-success__panel sa-order-success__items">
                <h2>Items</h2>
                <div class="sa-order-success__table">
                    <?php foreach ($items as $item): ?>
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
            </article>
        <?php elseif ($orderNumber !== ''): ?>
            <article class="sa-order-success__panel">
                <h2>Order not found</h2>
                <p>No order matched <strong><?= e((string)$orderNumber) ?></strong>. Check the order number and try again.</p>
            </article>
        <?php endif; ?>
    </div>
</section>
