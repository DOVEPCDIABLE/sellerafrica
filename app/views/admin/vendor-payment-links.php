<?php if (\App\AuthService::hasRole('super_admin')): ?>
<div class="vendor-payment-tools">
    <h3>Service payment links</h3>
    <p>Paystack checkout for <?= e($editVendor['owner_email']) ?>. The service activates after payment is verified.</p>
    <form method="post" action="<?= e(app_url('dashboard/payment-links')) ?>" class="vendor-payment-form">
        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
        <input type="hidden" name="action" value="generate">
        <input type="hidden" name="name" value="<?= e(trim((string)($editVendor['display_name'] ?? '')) ?: $editVendor['store_name']) ?>">
        <input type="hidden" name="email" value="<?= e($editVendor['owner_email']) ?>">
        <input type="hidden" name="provider" value="paystack">
        <label class="settings-field"><span>Service</span><select name="service" required>
            <option value="manage">Manage My Store - $12 for one year</option>
            <option value="setup">Set Up My Store - $5 one-time</option>
            <option value="rank">Rank Products - $5/month</option>
            <option value="priority_monthly">Priority - $5 for one month</option>
            <option value="priority_annual">Priority - $50 for one year</option>
        </select></label>
        <button class="btn primary" type="submit">Generate Paystack link</button>
        <a class="btn secondary" href="<?= e(app_url('dashboard/payment-links?user='.(int)$editVendor['user_id'])) ?>">All payment options</a>
    </form>
</div>
<?php endif; ?>
