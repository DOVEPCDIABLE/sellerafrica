<style>
@import url('https://fonts.googleapis.com/css2?family=Urbanist:wght@400;500;600;700&display=swap');
body { background:#f2faf5; }
.sa-auth { --green:#177d51; --fr-serif:Urbanist,sans-serif; --fr-sans:Urbanist,sans-serif; --fr-gold:#177d51; --fr-cream:#f2faf5; --fr-soil:#177d51; }
.sa-auth,.sa-auth input,.sa-auth select,.sa-auth button,.sa-auth h1,.sa-auth h2 { font-family:Urbanist,sans-serif; }
.sa-auth__card { border-radius:28px; }
.sa-auth input,.sa-auth select { border-radius:14px; min-height:54px; }
.sa-auth__submit { background:#177d51; color:white; border-radius:14px; }
.sa-auth__aside { background:#177d51; }
.sa-auth__aside h2,.sa-auth__aside p { color:white; }
.sa-auth__switch a.is-active { color:white; background:#177d51; }
.sa-auth .sa-plan-card input[type=radio] { position:static; opacity:1; pointer-events:auto; width:22px; height:22px; min-height:22px; justify-self:end; accent-color:#177d51; }
</style>
<p class="sa-vendor-step__note">Create your account and select a plan. You will upload your store and product details on the verification page.</p>
<p class="sa-vendor-step__note"><strong>Compulsory registration fee: $1 equivalent / &#8358;1,500.</strong> Every new vendor must pay &#8358;1,500 through Paystack, including vendors selecting the Free plan. There is no free vendor registration. Monthly plan charges are separate.</p>
<div class="sa-auth__grid">
<?php foreach (['first_name' => 'First name', 'last_name' => 'Surname', 'email' => 'Email', 'phone' => 'Phone'] as $key => $label): ?>
<label class="sa-auth__field"><?= e($label) ?> *<input name="<?= e($key) ?>" type="<?= $key === 'email' ? 'email' : ($key === 'phone' ? 'tel' : 'text') ?>" required autocomplete="<?= e(['first_name'=>'given-name','last_name'=>'family-name','email'=>'email','phone'=>'tel'][$key]) ?>" value="<?= e($_POST[$key] ?? ($loggedInUser[$key] ?? '')) ?>" <?= $key === 'email' && !empty($isLoggedIn) ? 'readonly' : '' ?>><?php if ($key === 'phone'): ?><small>Include your country code, for example +234 or +1.</small><?php endif; ?></label>
<?php endforeach; ?>
<label class="sa-auth__field">Country *<select name="country_of_operation" required><option value="">Select country</option><?php foreach ($countries as $country): ?><option <?= ($_POST['country_of_operation'] ?? '') === $country ? 'selected' : '' ?>><?= e($country) ?></option><?php endforeach; ?></select></label>
<label class="sa-auth__field">Category of products *<select name="product_category" required><option value="">Select category</option><?php foreach (['Food & Grocery','Fashion','Beauty & Wellness','Home & Lifestyle','Agriculture','Other'] as $category): ?><option <?= ($_POST['product_category'] ?? '') === $category ? 'selected' : '' ?>><?= e($category) ?></option><?php endforeach; ?></select></label>
<?php if (empty($isLoggedIn)): ?>
<label class="sa-auth__field">Password *<span class="sa-auth__password"><input id="password" name="password" type="password" required minlength="5" autocomplete="new-password"><button type="button" data-password-toggle="password">Show</button></span><small>Use at least 5 characters.</small></label>
<label class="sa-auth__field">Confirm password *<span class="sa-auth__password"><input id="password_confirmation" name="password_confirmation" type="password" required minlength="5" autocomplete="new-password"><button type="button" data-password-toggle="password_confirmation">Show</button></span></label>
<?php endif; ?>
<div class="sa-auth__field is-full"><h2>Select a plan</h2><div class="sa-plan-grid">
<?php foreach ($vendorPackages as $package): ?>
<label class="sa-plan-card"><input type="radio" name="package_id" value="<?= (int)$package['id'] ?>" required <?= (int)$selectedVendorPackageId === (int)$package['id'] ? 'checked' : '' ?>><span class="sa-plan-card__name"><?= e($package['name']) ?></span><span class="sa-plan-card__price"><?= (float)$package['price'] > 0 ? e($package['currency'] . ' ' . number_format((float)$package['price'],2) . ' / ' . $package['billing_interval']) : 'Free' ?></span><span>Product limit: <?= e($package['product_limit'] ?? 'Unlimited') ?></span><span><?= e($package['description'] ?? '') ?></span><span class="sa-plan-card__benefits"><?php foreach ((array)json_decode((string)($package['features'] ?? '[]'),true) as $benefit): ?><?php if (is_string($benefit)): ?><span class="sa-plan-card__benefit"><?= e($benefit) ?></span><?php endif; ?><?php endforeach; ?></span></label>
<?php endforeach; ?></div></div>
<label class="sa-auth__field is-full">Payment method for paid plans<select name="payment_provider"><option value="stripe" <?= $paymentProvider === 'stripe' ? 'selected' : '' ?>>Stripe</option><?php if ($paystackAvailable): ?><option value="paystack" <?= $paymentProvider === 'paystack' ? 'selected' : '' ?>>Paystack</option><?php endif; ?></select></label>
<div class="sa-auth__field is-full"><strong>One-time registration fee: &#8358;1,500 ($1 equivalent)</strong><p>Required for every plan, including Free. Pay securely with Paystack after creating your account. This is separate from any paid-plan subscription. Your account is saved if payment is interrupted.</p></div>
<label class="sa-auth__field is-full">Referral code (optional)<input name="referral_code" value="<?= e($referralInput ?? '') ?>"></label>
<label class="sa-auth__terms"><input type="checkbox" name="terms_consent" value="1" required <?= !empty($_POST['terms_consent']) ? 'checked' : '' ?>><span>I agree to the <a href="<?= e(app_url('vendor-agreement')) ?>">Vendor Terms</a> and <a href="<?= e(app_url('privacy-policy')) ?>">Privacy Policy</a>.</span></label>
<?php if (!empty($captchaRequired) && !empty($captchaChallenge)): ?><label class="sa-auth__field">Security check: <?= e($captchaChallenge['question']) ?><input name="captcha_answer" required inputmode="numeric"></label><?php endif; ?>
<button class="sa-auth__submit" type="submit">Continue to &#8358;1,500 payment</button>
</div>
