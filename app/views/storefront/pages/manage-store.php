<?php

declare(strict_types=1);

$brand = app_branding();
$brandName = (string)($brand['name'] ?? 'Seller Africa');
$csrf = getCsrfToken();
$prefillName = trim((string)($_POST['name'] ?? ''));
$prefillEmail = strtolower(trim((string)($_POST['email'] ?? '')));
$setupSelected = ($selectedPlan ?? $_GET['plan'] ?? 'annual') === 'setup';
?>

<?php foreach (consume_toasts() as $notice): ?>
  <div class="manage-notice" role="status"><?= e($notice['message'] ?? '') ?></div>
<?php endforeach; ?>

<main class="sa-manage-page">
  <section class="sa-manage-hero">
    <div class="sa-manage-wrap sa-manage-grid">
      <div>
        <p class="sa-manage-kicker">Manage My Store</p>
        <h1><?= $setupSelected ? 'Set Up My Store' : 'Manage My Store' ?></h1>
        <p class="sa-manage-copy">Don’t have time to manage your store? For just $1 a month, billed annually at $12, our team will help keep your products updated, organized, and easier for buyers to understand.</p>
        <p class="sa-manage-copy">Just getting started? Choose Set Up My Store for a one-time $5 payment, with no recurring charge.</p>
        <div class="sa-manage-actions">
          <a class="sa-manage-btn is-primary" href="#manage-store-checkout"><?= $setupSelected ? 'Set up my store - $5' : 'Choose your service' ?></a>
          <?php if (!$setupSelected): ?><a class="sa-manage-btn is-ghost" href="<?= e(app_url('setup-store')) ?>">Set Up My Store - $5 once</a><?php endif; ?>
          <a class="sa-manage-btn is-ghost" href="<?= e(app_url('vendor/register')) ?>">Need a vendor account?</a>
        </div>
      </div>

    </div>
  </section>
  <section class="sa-manage-checkout sa-manage-wrap" aria-labelledby="manage-checkout-title">
    <div class="sa-manage-checkout-intro">
      <p class="sa-manage-kicker">Support for your next step</p>
      <h2 id="manage-checkout-title">Your products.<br><em>Our helping hands.</em></h2>
      <p>From your first listing to everyday updates, let our team help keep your storefront ready for buyers.</p>
      <p><strong>One-time setup:</strong> help preparing your storefront, organizing your store information, and uploading your first product. Ongoing updates are available separately with annual management.</p>
      <p>Choose one-time setup or ongoing management. No sign-in required. Use the email associated with your Seller Africa store.</p>
      <a class="dist-text-link" href="<?= e(app_url('contact')) ?>">Have a question? Talk to us &#8594;</a>
    </div>
      <aside class="sa-manage-panel" aria-label="Manage My Store payment options">
        <p class="sa-manage-kicker">Simple pricing</p>
        <div class="sa-manage-price"><strong id="servicePrice"><?= $setupSelected ? '$5' : '$1' ?></strong><span id="servicePeriod"><?= $setupSelected ? 'one-time' : '/ month' ?></span></div>
        <p id="serviceBilling"><?= $setupSelected ? 'One $5 payment for store setup. No recurring charge or annual management included.' : 'Billed annually at $12 for one full year. You focus on your products. We manage your store.' ?></p>

        <form id="manageStoreCheckout" method="post" action="<?= e(app_url('manage-store')) ?>">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <fieldset class="service-options"><legend>Choose your service</legend>
            <label><input type="radio" name="plan" value="annual" <?= !$setupSelected ? 'checked' : '' ?> required><span><strong>Manage My Store</strong><small>$12 / year · ongoing store management</small></span></label>
            <label><input type="radio" name="plan" value="setup" <?= $setupSelected ? 'checked' : '' ?> required><span><strong>Set Up My Store</strong><small>$5 once · help setting up your storefront and first listing</small></span></label>
          </fieldset>
          <div class="sa-manage-fields" id="manage-store-checkout">
            <label>Your name
              <input type="text" name="name" id="manageStoreName" value="<?= e($prefillName) ?>" autocomplete="name" required>
            </label>
            <label>Your email
              <input type="email" name="email" id="manageStoreEmail" value="<?= e($prefillEmail) ?>" autocomplete="email" required>
            </label>
          </div>
        </form>

        <div class="sa-manage-plan">
          <h3 id="serviceCheckoutTitle"><?= $setupSelected ? 'One-time setup' : 'Annual subscription' ?></h3>
          <p>Choose a secure payment method. Your service is confirmed after payment verification.</p>
          <button class="sa-manage-btn is-primary" id="stripeManageStoreBtn" type="submit" form="manageStoreCheckout"><?= $setupSelected ? 'Pay with Stripe - $5 once' : 'Subscribe with Stripe - $12/year' ?></button>
          <button class="sa-manage-btn is-ghost" id="paystackManageStoreBtn" type="button" style="width:100%; color:#0b3d2b; border-color:#dcd4c4; margin-top:10px;"><?= $setupSelected ? 'Pay with Paystack - $5 once' : 'Subscribe with Paystack - $12/year' ?></button>
          <button class="sa-manage-btn is-ghost" id="klashaManageStoreBtn" type="button" style="width:100%; color:#0b3d2b; border-color:#dcd4c4; margin-top:10px;"><?= $setupSelected ? 'Pay with Klasha - $5 once' : 'Subscribe with Klasha - $12/year' ?></button>
        </div>
        <div class="sa-manage-status" id="manageStoreStatus" role="status" aria-live="polite"></div>
      </aside>
  </section>

  <section class="sa-manage-benefits">
    <div class="sa-manage-wrap">
      <div class="sa-manage-section-head">
        <h2>Ongoing management includes</h2>
        <p>Keep your storefront clean, current, and useful so buyers have the right information to make a purchase.</p>
      </div>
      <div class="sa-manage-cards">
        <?php foreach ([
          ['Upload your products', 'We can help add products to your store when you do not have time.'],
          ['Improve descriptions', 'Product copy can be edited and organized so shoppers understand what they are buying.'],
          ['Update information', 'We help keep prices, product details, and store content current.'],
          ['Organize listings', 'Listings can be cleaned up so your storefront feels professional.'],
          ['Professional storefront', 'Your store stays easier to browse and better prepared for buyer visits.'],
          ['Order alerts', 'We help alert you when you receive an order so you can respond faster.'],
        ] as $item): ?>
          <article class="sa-manage-card">
            <span>✓</span>
            <h3><?= e($item[0]) ?></h3>
            <p><?= e($item[1]) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="sa-manage-strip">
    <div class="sa-manage-wrap">
      <div>
        <h2>A helping hand for your store.</h2>
        <p>One-time setup for $5, or ongoing management for $12 per year.</p>
      </div>
      <a class="sa-manage-btn is-primary" href="#manage-store-checkout">Choose your service</a>
    </div>
  </section>
</main>

<script src="https://js.klasha.com/pay.js"></script>
<script>
  const manageStoreIntentUrl = <?= json_encode(app_url('api/payments/manage-store'), JSON_UNESCAPED_SLASHES) ?>;
  const manageStoreCsrf = <?= json_encode($csrf) ?>;
  const manageStoreStatus = document.getElementById('manageStoreStatus');
  const klashaManageStoreBtn = document.getElementById('klashaManageStoreBtn');
  const paystackManageStoreBtn = document.getElementById('paystackManageStoreBtn');
  const manageStoreForm = document.getElementById('manageStoreCheckout');
  const manageStoreName = document.getElementById('manageStoreName');
  const manageStoreEmail = document.getElementById('manageStoreEmail');
  const selectedService = () => manageStoreForm.querySelector('[name="plan"]:checked').value;
  const serviceButtonLabel = provider => selectedService() === 'setup' ? `Pay with ${provider} - $5 once` : `Subscribe with ${provider} - $12/year`;
  manageStoreForm.querySelectorAll('[name="plan"]').forEach(input => input.addEventListener('change', () => {
    const setup = selectedService() === 'setup';
    document.getElementById('servicePrice').textContent = setup ? '$5' : '$1';
    document.getElementById('servicePeriod').textContent = setup ? 'one-time' : '/ month';
    document.getElementById('serviceBilling').textContent = setup ? 'One $5 payment for store setup. No recurring charge or annual management included.' : 'Billed annually at $12 for one full year.';
    document.getElementById('serviceCheckoutTitle').textContent = setup ? 'One-time setup' : 'Annual subscription';
    document.getElementById('stripeManageStoreBtn').textContent = serviceButtonLabel('Stripe');
    paystackManageStoreBtn.textContent = serviceButtonLabel('Paystack');
    klashaManageStoreBtn.textContent = serviceButtonLabel('Klasha');
    manageStoreStatus.textContent = '';
  }));

  function setManageStoreStatus(message, ok = false) {
    if (!manageStoreStatus) return;
    manageStoreStatus.textContent = message;
    manageStoreStatus.style.color = ok ? '#0b7f45' : '#9b2c2c';
  }

  async function reportManageStoreKlasha(config, data) {
    setManageStoreStatus('Confirming Klasha payment...', true);
    const payload = new FormData();
    payload.set('csrf_token', manageStoreCsrf);
    payload.set('action', 'klasha_callback');
    payload.set('reference', config.reference);
    payload.set('txRef', config.reference);
    payload.set('tnxRef', config.reference);
    if (data && typeof data === 'object') {
      Object.keys(data).forEach((key) => {
        const value = data[key];
        if (['string', 'number', 'boolean'].includes(typeof value)) {
          payload.set(key, String(value));
        }
      });
    }
    const response = await fetch(manageStoreIntentUrl, { method: 'POST', body: payload });
    const json = await response.json();
    if (json.ok && json.paid) {
      setManageStoreStatus('Your service payment is confirmed.', true);
      window.setTimeout(() => window.location.href = <?= json_encode(app_url('manage-store?subscribed=1'), JSON_UNESCAPED_SLASHES) ?>, 800);
      return;
    }
    setManageStoreStatus(json.message || 'Klasha payment response received. We will verify it shortly.', Boolean(json.ok));
  }

  async function payManageStoreWithKlasha() {
    if (!klashaManageStoreBtn) return;
    if (!manageStoreForm.reportValidity()) {
      setManageStoreStatus('Please enter your name and email first.');
      return;
    }
    klashaManageStoreBtn.disabled = true;
    klashaManageStoreBtn.textContent = 'Opening Klasha...';
    try {
      const payload = new FormData();
      payload.set('csrf_token', manageStoreCsrf);
      payload.set('action', 'create_klasha');
      payload.set('plan', selectedService());
      payload.set('name', manageStoreName.value);
      payload.set('email', manageStoreEmail.value);
      const response = await fetch(manageStoreIntentUrl, { method: 'POST', body: payload });
      const json = await response.json();
      if (!response.ok || !json.ok || !json.klasha) {
        throw new Error(json.message || 'Klasha payment could not be started.');
      }
      if (typeof KlashaClient === 'undefined') {
        throw new Error('Klasha checkout could not load. Please refresh and try again.');
      }

      const config = json.klasha;
      const paymentKit = {
        tx_ref: config.reference,
        fullname: config.fullname,
        firstName: config.firstName,
        lastName: config.lastName,
        email: config.email,
        phone_number: config.phone,
        businessId: config.businessId,
        merchantKey: config.merchantKey,
        amount: config.amount,
        sourceAmount: config.sourceAmount,
        rate: 1
      };
      const client = new KlashaClient(
        config.merchantKey,
        config.businessId,
        config.amount,
        config.description,
        (data) => reportManageStoreKlasha(config, data || {}),
        config.currency,
        config.destinationCurrency,
        paymentKit,
        config.environment
      );
      client.init();
      setManageStoreStatus('Klasha checkout opened. Complete payment in the popup.', true);
    } catch (error) {
      setManageStoreStatus(error.message || 'Klasha payment could not be started.');
    } finally {
      klashaManageStoreBtn.disabled = false;
      klashaManageStoreBtn.textContent = serviceButtonLabel('Klasha');
    }
  }

  async function payManageStoreWithPaystack() {
    if (!paystackManageStoreBtn) return;
    if (!manageStoreForm.reportValidity()) {
      setManageStoreStatus('Please enter your name and email first.');
      return;
    }
    paystackManageStoreBtn.disabled = true;
    paystackManageStoreBtn.textContent = 'Opening Paystack...';
    try {
      const payload = new FormData();
      payload.set('csrf_token', manageStoreCsrf);
      payload.set('action', 'create_paystack');
      payload.set('plan', selectedService());
      payload.set('name', manageStoreName.value);
      payload.set('email', manageStoreEmail.value);
      const response = await fetch(manageStoreIntentUrl, { method: 'POST', body: payload });
      const json = await response.json();
      if (!response.ok || !json.ok || !json.redirect_url) {
        throw new Error(json.message || 'Paystack payment could not be started.');
      }
      setManageStoreStatus('Opening Paystack checkout...', true);
      window.location.href = json.redirect_url;
    } catch (error) {
      setManageStoreStatus(error.message || 'Paystack payment could not be started.');
      paystackManageStoreBtn.disabled = false;
      paystackManageStoreBtn.textContent = serviceButtonLabel('Paystack');
    }
  }

  klashaManageStoreBtn?.addEventListener('click', payManageStoreWithKlasha);
  paystackManageStoreBtn?.addEventListener('click', payManageStoreWithPaystack);
</script>
