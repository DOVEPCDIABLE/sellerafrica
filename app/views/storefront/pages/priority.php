<?php $benefits=['Priority vendor support','Buyer opportunity alerts','Retail & distribution opportunity alerts','Priority product consideration','Export opportunity alerts','Monthly market-access sessions','Selected business, grant & trade opportunity alerts','Priority Member recognition']; ?>
<main class="priority-page">
<section class="priority-hero"><img src="<?= e(asset('images/High_produce_quality.jpeg')) ?>" alt="Produce being prepared for market"><div class="priority-wrap priority-hero-copy"><p class="priority-eyebrow">FOUNDING MEMBERSHIP · FIRST 500 MEMBERS</p><h1>Seller Africa<br><em>Priority</em></h1><p>You asked for more access. We’re creating it.</p><p>A private membership community for vendors who want to get closer to buyer, retail, export and market-access opportunities.</p><a class="priority-button" href="#membership">Join Seller Africa Priority</a><span class="priority-hero-price">$5/month or $50/year</span></div></section>
<section class="priority-wrap priority-benefits"><div class="priority-section-heading"><p class="priority-eyebrow">YOUR MEMBERSHIP</p><h2>Get closer to the opportunities.</h2><p>More than another group. Your priority lane into the Seller Africa ecosystem.</p></div><ul><?php foreach($benefits as $benefit): ?><li><span aria-hidden="true">&#10003;</span><?= e($benefit) ?></li><?php endforeach; ?></ul></section>
<section class="priority-membership" id="membership"><div class="priority-wrap priority-join-layout"><div><p class="priority-eyebrow">FOUNDING MEMBER RATE</p><h2>Make your next<br>connection count.</h2><p>Choose monthly flexibility or a full year of Priority membership. The first intake is limited to 500 founding members.</p><p class="priority-disclaimer">Membership does not guarantee sales, contracts, funding or retail placement. Eligibility requirements apply to individual opportunities.</p></div><div>
<?php if($error): ?><p class="priority-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if($notice): ?><p class="priority-notice" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if(!empty($_GET['cancelled'])): ?><p class="priority-notice">Checkout was cancelled. You have not joined yet; you can subscribe below.</p><?php endif; ?>
<?php if($managed): ?>
<h3>Your membership</h3><p><?= e($managed['name']) ?> · <?= e(ucfirst($managed['plan'])) ?></p><p>Status: <?= e(ucwords(str_replace('_',' ',$managed['status']))) ?></p><p>Current period ends: <?= e($managed['current_period_end'] ?: 'Awaiting payment') ?></p>
<?php if(str_starts_with($managed['reference'] ?? '', 'SA-PRIORITY-NGN-')): ?><p>Paystack membership: manual renewal. You will not be charged automatically.</p><?php elseif($managed['cancel_at_period_end']): ?><p>Renewal is cancelled.</p><?php elseif(!in_array($managed['status'],['canceled','incomplete_expired'],true)): ?><form method="post" action="<?= e(app_url('priority#membership')) ?>"><input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>"><input type="hidden" name="intent" value="cancel"><input type="hidden" name="management_token" value="<?= e($token) ?>"><button class="priority-button" type="submit">Cancel automatic renewal</button></form><?php endif; ?>
<?php elseif($member && \App\PriorityMembershipService::active($member)): ?>
<h3>You’re a Priority Member</h3><p>Your membership is active. Check your inbox for confirmation and community access details from our team.</p><a href="#manage-membership">Manage your membership</a>
<?php else: ?>
<form method="post" action="<?= e(app_url('priority#membership')) ?>" class="priority-form">
<input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>"><input type="hidden" name="intent" value="subscribe">
<fieldset class="priority-plans"><legend>Choose your membership</legend><label><input type="radio" name="plan" value="monthly" <?= $plan==='monthly'?'checked':'' ?> required><span>Monthly<strong>$5 <small>/ month</small></strong></span></label><label><input type="radio" name="plan" value="annual" <?= $plan==='annual'?'checked':'' ?> required><span>Annual<strong>$50 <small>/ year</small></strong><small>Save $10 per year</small></span></label></fieldset>
<label>Full name<input name="name" autocomplete="name" maxlength="190" required value="<?= e($_POST['name'] ?? $user['display_name'] ?? '') ?>"></label>
<label>Email address<input name="email" type="email" autocomplete="email" maxlength="190" required value="<?= e($_POST['email'] ?? $user['email'] ?? '') ?>"></label>
<label>Payment method<select name="provider" id="priority-provider"><option value="stripe" <?= ($provider ?? '')==='stripe'?'selected':'' ?>>Stripe - USD</option><option value="paystack" <?= ($provider ?? '')==='paystack'?'selected':'' ?> <?= empty($naira)?'disabled':'' ?>>Paystack - Nigerian naira</option></select></label>
<?php foreach(($naira ?? []) as $key=>$amount): ?><input type="hidden" name="naira_quote[<?= e($key) ?>]" value="<?= (int)$amount ?>"><?php endforeach; ?>
<p class="priority-small" id="priority-payment-terms">Stripe: $5/month or $50/year, automatically renewed until cancelled. Paystack: <?= isset($naira['monthly'])?'NGN '.number_format($naira['monthly']/100,2).' for one month or NGN '.number_format($naira['annual']/100,2).' for one year':'naira pricing temporarily unavailable' ?>, paid once per period with manual renewal.</p>
<label class="priority-consent"><input type="checkbox" name="terms" value="1" required <?= !empty($_POST['terms'])?'checked':'' ?>><span>I agree to the <a href="<?= e(app_url('terms')) ?>">terms</a>, <a href="<?= e(app_url('privacy-policy')) ?>">privacy policy</a>, the billing terms above for my selected payment method, and the membership disclaimer.</span></label>
<button type="submit" class="priority-button">Subscribe to Priority</button><small>No account required. Membership is recorded against your email. Stripe renewal can be cancelled using a secure membership email link.</small></form>
<?php endif; ?>
</div></div></section>
<section class="priority-wrap priority-manage" id="manage-membership"><div><h2>Already a member?</h2><p>Request a secure link to check your membership or cancel renewal.</p></div><form method="post" action="<?= e(app_url('priority#membership')) ?>"><input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>"><input type="hidden" name="intent" value="manage"><label>Membership email<input type="email" name="email" autocomplete="email" required maxlength="190"></label><button class="priority-button priority-secondary" type="submit">Email my membership link</button></form></section>
</main>
<script>
document.querySelectorAll('.priority-page form').forEach(form => {
  form.addEventListener('submit', () => {
    const button = form.querySelector('button[type="submit"]');
    if (!button) return;
    button.dataset.label = button.textContent;
    button.disabled = true;
    button.textContent = form.querySelector('[name="intent"]').value === 'subscribe' ? 'Opening secure checkout...' : 'Please wait...';
  });
});
window.addEventListener('pageshow', () => document.querySelectorAll('.priority-page button[data-label]').forEach(button => {
  button.disabled = false;
  button.textContent = button.dataset.label;
}));
</script>
