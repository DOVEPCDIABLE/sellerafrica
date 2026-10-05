<?php
$brand = app_branding();
$values = $data['verification'] ?? [];
$checks = \App\VendorOnboardingService::checks($data);
$percent = (int)round(count(array_filter($checks, static fn ($c) => $c['done'])) / count($checks) * 100);
$rejected = $vendor['status'] === 'rejected' || $vendor['kyc_status'] === 'rejected';
$field = static function (string $name, string $label, string $type = 'text', bool $required = true) use ($values): void {
    echo '<label>' . e($label) . ($required ? ' *' : ' (optional)');
    if ($type === 'textarea') echo '<textarea name="' . e($name) . '" ' . ($required ? 'required' : '') . '>' . e($values[$name] ?? '') . '</textarea>';
    else echo '<input name="' . e($name) . '" type="' . e($type) . '" value="' . e($values[$name] ?? '') . '" ' . ($required ? 'required' : '') . ($type === 'number' ? ' min="0.01" step="0.01" inputmode="decimal"' : '') . '>';
    echo '</label>';
};
$upload = static function (string $name, string $label, bool $required = true) use ($data): void {
    $saved = !empty($data['file_ids'][$name]);
    echo '<label>' . e($label) . ($required ? ' *' : ' (optional)') . '<input type="file" name="' . e($name) . '" accept="image/jpeg,image/png,image/webp' . (in_array($name, ['identity', 'business_registration'], true) ? ',application/pdf' : '') . '" ' . ($required && !$saved ? 'required' : '') . '><small>' . ($saved ? 'Upload saved. Choose a file only to replace it. ' : '') . 'Maximum 5 MB. JPG, PNG, WebP' . (in_array($name, ['identity', 'business_registration'], true) ? ' or PDF.' : '.') . '</small></label>';
};
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Urbanist:wght@400;500;600;700&display=swap');
.onboarding { --green:#177d51; --ink:#16372a; --line:#dceae2; font-family:Urbanist, sans-serif; color:var(--ink); background:#f2faf5; min-height:100vh; padding:24px; }
.onboarding * { box-sizing:border-box; letter-spacing:0; }
.onboarding header { max-width:1100px; margin:0 auto 24px; display:flex; justify-content:space-between; align-items:center; gap:16px; }
.onboarding header img { width:130px; height:64px; object-fit:contain; }
.onboarding main { max-width:1100px; margin:auto; display:grid; grid-template-columns:280px minmax(0,1fr); background:white; border:1px solid var(--line); border-radius:28px; overflow:hidden; }
.onboarding aside { background:#177d51; color:white; padding:32px; }
.onboarding article { min-width:0; padding:32px; }
.onboarding h1 { font-size:30px; color:var(--ink); margin:0 0 16px; }
.onboarding h2 { font-size:22px; margin:0 0 18px; }
.onboarding aside h2 { color:white; }
.onboarding p { line-height:1.6; }
.onboarding nav { display:flex; flex-wrap:wrap; gap:12px; margin:20px 0; }
.onboarding a { color:var(--green); text-decoration:underline; }
.onboarding aside li { margin:16px 0; }
.onboarding .fields { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:20px; }
.onboarding label { display:flex; flex-direction:column; gap:8px; font-weight:600; min-width:0; }
.onboarding input,.onboarding select,.onboarding textarea { width:100%; min-width:0; min-height:54px; padding:12px; border:1px solid var(--line); border-radius:14px; font:inherit; color:var(--ink); background:white; }
.onboarding textarea { min-height:120px; }
.onboarding input[type=checkbox] { width:22px; min-height:22px; }
.onboarding small { font-weight:400; line-height:1.5; }
.onboarding button,.onboarding .button { padding:14px 22px; min-height:48px; background:var(--green); color:white; border:0; border-radius:14px; font:600 16px Urbanist,sans-serif; cursor:pointer; text-decoration:none; }
.onboarding button:disabled { opacity:.5; cursor:wait; }
.onboarding :focus-visible { outline:3px solid #c29324; outline-offset:3px; }
.onboarding .error { background:#fff3f2; color:#a32d29; padding:16px; border:1px solid #c33b36; border-radius:8px; margin-bottom:20px; }
.onboarding progress { width:100%; height:14px; accent-color:var(--green); }
.onboarding .actions { display:flex; gap:12px; flex-wrap:wrap; margin-top:24px; }
.onboarding .wide { grid-column:1/-1; }
@media(max-width:760px) { .onboarding{padding:12px}.onboarding main{grid-template-columns:1fr}.onboarding aside{padding:20px}.onboarding aside ol{display:none}.onboarding article{padding:20px}.onboarding .fields{grid-template-columns:1fr} }
</style>
<div class="onboarding">
 <header><a href="<?= e(app_url('')) ?>"><?php if (!empty($brand['logo'])): ?><img src="<?= e($brand['logo']) ?>" alt="Seller Africa"><?php else: ?>Seller Africa<?php endif; ?></a><a href="<?= e(app_url('logout')) ?>">Sign out</a></header>
 <main><aside><h2>Complete your application</h2><p>Your store starts here.</p><ol><li>Create account</li><li>Choose plan and payment</li><li>Complete verification</li><li>Team review</li></ol><p>Your documents are reviewed by the Seller Africa team.</p></aside>
 <article><h1>Vendor verification</h1>
 <?php foreach ($errors as $error): ?><div class="error" role="alert"><?= e($error) ?></div><?php endforeach; ?>
 <?php if (!$paymentReady): ?>
 <?php if (!\App\VendorRegistrationPaymentService::paid((int)$vendor['id'], $data)): ?>
 <h2>Complete your registration payment</h2><p>Your account is saved. A one-time registration fee of <strong>&#8358;1,500 ($1 equivalent)</strong> is required, including for the Free plan. Paystack will charge NGN 1,500. Paid-plan subscriptions are charged separately.</p>
 <form method="post"><input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>"><input type="hidden" name="intent" value="pay"><div class="actions"><button type="submit">Pay NGN 1,500 with Paystack</button><a href="<?= e(app_url('vendor/verification')) ?>">Refresh payment status</a></div></form>
 <?php else: ?>
 <h2>Complete your plan payment</h2><p>Your account has been saved. Complete payment for your selected plan to continue verification. Payment confirmation may take a moment after you return.</p>
 <form method="post"><input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>"><input type="hidden" name="intent" value="pay"><label>Payment method<select name="provider"><option value="stripe">Stripe</option><option value="paystack">Paystack</option></select></label><div class="actions"><button>Continue to payment</button><a href="<?= e(app_url('vendor/verification')) ?>">Refresh payment status</a></div></form>
 <?php endif; ?>
 <?php elseif ($pending): ?>
 <h2>Pending review</h2><p>Your application has been submitted. Our team is reviewing your store and product details. You can access your dashboard once your application is approved.</p><a href="<?= e(app_url('vendor/verification')) ?>">Refresh status</a>
 <?php else: ?>
 <?php if ($rejected): ?><div class="error"><strong>Application rejected</strong><p>Please review your details and the feedback sent to your email, make corrections, and submit again.</p></div><?php endif; ?>
 <p><strong><?= $percent ?>% complete</strong></p><progress max="100" value="<?= $percent ?>" aria-label="Application completion"></progress>
 <p>Complete every required field marked *. Saved uploads do not need to be uploaded again. Optional fields do not affect completion.</p>
 <nav aria-label="Verification sections"><a href="#verification-store">1. Store</a><a href="#verification-product">2. Product</a><a href="#verification-fulfillment">3. Fulfillment &amp; submit</a></nav>
 <form method="post" enctype="multipart/form-data" id="verification-form"><input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>"><input type="hidden" name="step" value="<?= $step ?>"><input type="hidden" name="MAX_FILE_SIZE" value="5242880">
 <div class="fields">
 <h2 class="wide" id="verification-store">Store details</h2>
 <?php $field('store_name','Store name'); $upload('store_banner','Store banner'); $upload('store_profile_image','Store profile photo',false); ?>
 <p class="wide">Use a landscape banner, recommended 2,000 x 700 px. Profile photos should be square, recommended 1,000 x 1,000 px. No phone numbers, email addresses, website links or social media handles may appear in these images. Images with contact details will be rejected.</p>
 <h2 class="wide" id="verification-product">First product</h2>
 <?php \App\ProductCategoryService::field('product_category_id', $values['product_category_id'] ?? null); ?>
 <?php $field('product_name','Product name'); $upload('product_image','First product photo'); $field('product_regular_price','Product price (USD)','number'); $field('product_sale_price','Discount price (USD)','number',false); $field('product_weight','Packaged weight (kg)','number'); $field('product_length','Package length (cm)','number'); $field('product_width','Package width (cm)','number'); $field('product_height','Package height (cm)','number'); $field('product_description','Product description','textarea'); ?>
 <p class="wide">Upload a clear photo of your product with no visible contact details. Minimum recommended image size: 1,000 x 1,000 px.</p>
 <h2 class="wide" id="verification-fulfillment">Fulfillment and declaration</h2>
 <label>Fulfillment method *<select name="fulfillment_method" required><option value="">Select method</option><?php foreach (\App\VendorOnboardingService::FULFILLMENT as $option): ?><option <?= ($values['fulfillment_method'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
 <label>Join monthly shipment? *<select name="monthly_shipment" required><option value="">Select answer</option><?php foreach (['Yes','No'] as $option): ?><option <?= ($values['monthly_shipment'] ?? '') === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?></select></label>
 <?php $upload('business_registration','Business registration document',false); $upload('identity','Identity document',false); $field('social_page','Social media page','url',false); ?>
 <p class="wide"><a href="<?= e(app_url('manage-store')) ?>" target="_blank" rel="noopener">Manage My Store (optional): $12 per year</a><br><a href="<?= e(app_url('visibility-boost')) ?>" target="_blank" rel="noopener">Rank on homepage (optional): $5 per month</a></p>
 <label class="wide"><input type="checkbox" name="terms_consent" value="1" required <?= ($values['terms_consent'] ?? '') === '1' ? 'checked' : '' ?>>I agree to the <a href="<?= e(app_url('vendor-agreement')) ?>" target="_blank" rel="noopener">Vendor Terms and Conditions</a> and <a href="<?= e(app_url('privacy-policy')) ?>" target="_blank" rel="noopener">Privacy Policy</a>.</label>
 </div><div class="actions"><button type="submit" name="intent" value="draft" formnovalidate>Save draft</button><button type="submit" name="intent" value="submit">Submit for verification</button></div>
 </form>
 <?php if ($percent < 100): ?><h2 style="margin-top:24px">Still needed</h2><ul><?php foreach ($checks as $check): ?><?php if (!$check['done']): ?><li><?= e($check['label']) ?></li><?php endif; ?><?php endforeach; ?></ul><?php endif; ?>
 <?php endif; ?>
 </article></main>
</div>
<script>
const verificationForm = document.getElementById('verification-form');
verificationForm?.querySelectorAll('input,select,textarea').forEach((input, index) => {
 const error = document.createElement('small');
 error.id = `verification-error-${index}`;
 error.style.color = '#a32d29';
 error.setAttribute('aria-live', 'polite');
 if (input.type === 'hidden') return;
 input.setAttribute('aria-describedby', error.id);
 input.insertAdjacentElement('afterend', error);
 input.addEventListener('invalid', () => {
   input.setAttribute('aria-invalid', 'true');
   error.textContent = input.validationMessage;
 });
 input.addEventListener('input', () => {
   if (input.validity.valid) { input.removeAttribute('aria-invalid'); error.textContent = ''; }
 });
});
document.querySelectorAll('.onboarding input[type=file]').forEach(input => input.addEventListener('change', () => { input.setCustomValidity(input.files[0]?.size > 5242880 ? 'Choose a file of 5 MB or smaller.' : ''); input.reportValidity(); }));
document.querySelectorAll('.onboarding form').forEach(form => form.addEventListener('submit', event => {
 const invalidFile = [...form.querySelectorAll('input[type=file]')].find(input => !input.validity.valid && !input.validity.valueMissing);
 if (invalidFile) { event.preventDefault(); invalidFile.reportValidity(); return; }
 if (event.submitter) { const intent = document.createElement('input'); intent.type='hidden'; intent.name='intent'; intent.value=event.submitter.value || form.querySelector('[name=intent]')?.value || ''; form.append(intent); }
 form.querySelectorAll('button').forEach(button => button.disabled=true);
}));
</script>
