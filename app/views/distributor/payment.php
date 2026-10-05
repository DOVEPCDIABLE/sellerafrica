<?php $brand=app_branding(); ?>
<link rel="stylesheet" href="<?= e(asset('css/distributor.css?v=2')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/distributor-payment.css?v=1')) ?>">
<div class="dist-site retail-payment">
<?php require __DIR__.'/header.php'; ?>
<main class="retail-main">
<header class="retail-heading"><p class="dist-eyebrow">SELLER AFRICA RETAIL PLACEMENT</p><h1>Choose your package</h1><p>Your application is saved. Take the next step toward retail readiness and buyer access.</p></header>
<?php if ($error): ?><p class="retail-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if (!empty($_GET['cancelled'])): ?><p role="status">Payment was cancelled. Your application is saved and you can return to payment at any time.</p><?php endif; ?>
<?php if (!empty($payment['paid_at'])): ?>
<section class="retail-confirmation"><h2>Payment confirmed</h2><p><?= e($plans[$payment['plan_code']]['name']) ?> · <?= e($payment['currency']) ?> <?= number_format((int)$payment['amount_minor']/100,2) ?></p><p>Reference: <?= e($payment['reference']) ?></p><p>Our team will review your application and contact you. Payment does not guarantee retail acceptance or purchase orders.</p><a class="dist-button" href="<?= e(app_url('distributor')) ?>">View application</a></section>
<?php else: ?>
<form method="post" action="<?= e(app_url('distributor?payment=1')) ?>">
<input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
<fieldset class="retail-plans"><legend>Retail placement packages</legend>
<?php foreach ($plans as $key=>$plan): ?>
<label class="retail-plan"><span class="retail-plan-top"><strong><?= e($plan['name']) ?></strong><input type="radio" name="plan" value="<?= e($key) ?>" required <?= ($_POST['plan'] ?? '')===$key?'checked':'' ?>></span>
<span class="retail-price">$<?= number_format($plan['price']) ?><small>one-time</small></span>
<span class="retail-ngn"><?= $quotes[$key]!==null?'Paystack: NGN '.number_format($quotes[$key]/100,2):'Naira pricing temporarily unavailable' ?></span>
<span><?= e($plan['intro']) ?></span><ul><?php foreach ($plan['features'] as $feature): ?><li><?= e($feature) ?></li><?php endforeach; ?></ul>
<strong class="retail-plan-terms"><?= e($plan['terms']) ?></strong></label>
<?php endforeach; ?>
</fieldset>
<section class="retail-checkout"><h2>Payment</h2><label for="retail-provider">Payment method</label><select id="retail-provider" name="provider" required><option value="stripe" <?= ($_POST['provider'] ?? '')==='stripe'?'selected':'' ?>>Stripe — pay in USD</option><option value="paystack" <?= ($_POST['provider'] ?? '')==='paystack'?'selected':'' ?>>Paystack — pay in naira (NGN)</option></select>
<p>Paystack uses the naira amount displayed for your package. Success fees, where applicable, are separate from this payment.</p>
<label class="retail-consent"><input type="checkbox" name="accept_terms" value="1" required <?= !empty($_POST['accept_terms'])?'checked':'' ?>><span>I accept the selected package scope and success-fee terms. I understand that retailer acceptance and purchase orders are not guaranteed.</span></label>
<button class="dist-button" type="submit">Continue to secure payment</button></section>
</form>
<?php endif; ?>
<section class="retail-disclosure"><h2>Important information</h2><p>Payment covers Seller Africa's market-entry, preparation, outreach, buyer-engagement and placement services. Retail acceptance and purchase orders are determined solely by individual retailers and are not guaranteed. Products must meet applicable regulatory, labeling, packaging, insurance, compliance, pricing and retailer requirements before placement. Seller Africa reserves the right to decline products that are not suitable for the selected retail market.</p><h3>Distribution partnership</h3><p>Distribution pricing is customized based on product category, volume, retailer requirements and territory.</p><a href="<?= e(app_url('contact')) ?>">Speak with our team</a></section>
</main></div>
