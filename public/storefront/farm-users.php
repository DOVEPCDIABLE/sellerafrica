<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/core/bootstrap.php';

use App\FarmFreshMembershipService;
use App\PaymentService;

FarmFreshMembershipService::ensureSchema();

function freshroots_join_request_country_code(): string {
    foreach ([
        'HTTP_CF_IPCOUNTRY',
        'HTTP_CLOUDFRONT_VIEWER_COUNTRY',
        'HTTP_X_APPENGINE_COUNTRY',
        'HTTP_X_COUNTRY_CODE',
        'HTTP_X_GEOIP_COUNTRY_CODE',
        'GEOIP_COUNTRY_CODE',
    ] as $key) {
        $country = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($_SERVER[$key] ?? '')) ?: '', 0, 2));
        if ($country !== '' && !in_array($country, ['XX', 'T1'], true)) {
            return $country;
        }
    }

    $ip = client_ip();
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return '';
    }

    $cache = $_SESSION['freshroots_join_country_lookup'] ?? [];
    if (
        is_array($cache)
        && (string)($cache['ip'] ?? '') === $ip
        && (int)($cache['expires_at'] ?? 0) > time()
    ) {
        return strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($cache['country_code'] ?? '')) ?: '', 0, 2));
    }

    $country = freshroots_join_lookup_country_by_ip($ip);
    $_SESSION['freshroots_join_country_lookup'] = [
        'ip' => $ip,
        'country_code' => $country,
        'expires_at' => time() + 21600,
    ];

    return $country;
}

function freshroots_join_lookup_country_by_ip(string $ip): string {
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: SellerAfrica-FreshRoots/1.0\r\n",
            ],
        ]);
        $json = @file_get_contents('https://ipwho.is/' . rawurlencode($ip) . '?fields=success,country_code', false, $context);
        if (!is_string($json) || $json === '') {
            return '';
        }
        $payload = json_decode($json, true);
        if (!is_array($payload) || empty($payload['success'])) {
            return '';
        }

        return strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($payload['country_code'] ?? '')) ?: '', 0, 2));
    } catch (\Throwable $e) {
        error_log('FreshRoots membership country lookup failed: ' . $e->getMessage());
    }

    return '';
}

function freshroots_join_render_country_block(array $brand, string $countryCode): void {
    $brandName = (string)($brand['name'] ?? 'Seller Africa');
    $logo = trim((string)($brand['logo'] ?? ''));
    http_response_code(403);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>FreshRoots unavailable | ' . e($brandName) . '</title>';
    echo '<style>body{margin:0;font-family:system-ui,-apple-system,Segoe UI,sans-serif;background:#f4faf5;color:#17211f}.wrap{min-height:100vh;display:grid;place-items:center;padding:28px}.card{width:min(620px,100%);background:#fff;border:1px solid #dfe8e5;border-radius:18px;padding:34px;box-shadow:0 20px 60px rgba(17,24,39,.10)}img{max-width:170px;max-height:64px;object-fit:contain;margin-bottom:24px}h1{margin:0 0 12px;font-size:clamp(30px,6vw,48px);line-height:1.02}p{color:#667276;font-size:17px;line-height:1.6;margin:0 0 18px}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:24px}.btn{min-height:46px;border-radius:10px;padding:0 18px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-weight:900}.primary{background:#00684f;color:#fff}.secondary{border:1px solid #dfe8e5;color:#00684f;background:#fff}.note{font-size:13px;color:#7a8588}</style></head><body>';
    echo '<main class="wrap"><section class="card">';
    if ($logo !== '') {
        echo '<img src="' . e($logo) . '" alt="' . e($brandName) . '">';
    }
    echo '<h1>FreshRoots membership is not available in your country.</h1>';
    echo '<p>FreshRoots access is currently available only for eligible U.S. customers. Based on your location, this membership cannot be started from your country.</p>';
    echo '<p class="note">Detected country: ' . e($countryCode !== '' ? $countryCode : 'Unavailable') . '</p>';
    echo '<div class="actions"><a class="btn primary" href="' . e(app_url('freshroots')) . '">Back to FreshRoots</a><a class="btn secondary" href="' . e(app_url('contact')) . '">Contact support</a></div>';
    echo '</section></main></body></html>';
    exit;
}

$brand = app_branding();
$brandName = (string)($brand['name'] ?? 'Seller Africa');
$brandLogo = trim((string)($brand['logo'] ?? ''));
$detectedCountryCode = freshroots_join_request_country_code();
if ($detectedCountryCode === 'NG') {
    freshroots_join_render_country_block($brand, $detectedCountryCode);
}
$templatePath = dirname(__DIR__) . '/farmer/farm_users_template.html';
$templateHtml = is_file($templatePath) ? (string)file_get_contents($templatePath) : '';
$message = '';

if (trim((string)($_GET['session_id'] ?? '')) !== '') {
    $membership = FarmFreshMembershipService::syncStripeCheckout((string)$_GET['session_id']);
    if ($membership && in_array((string)($membership['status'] ?? ''), ['active', 'trialing', 'paid'], true)) {
        flash('success', 'Your FreshRoots membership is active. You can now browse farms and produce.');
        redirect('freshroots');
    }
    $message = 'We found your Stripe checkout. If payment is complete, access will activate shortly.';
}
if (isset($_GET['cancelled'])) {
    $message = 'Payment was cancelled. You can restart your membership below.';
}

if (trim($templateHtml) === '') {
    render_restricted_page(404, 'This link may be broken', 'The FreshRoots membership page template is missing. We have sent this to our technical team to review.', ['route' => 'farm-users', 'reason' => 'template_missing']);
    return;
}

$stripeConfigured = PaymentService::isStripeConfigured();
PaymentService::ensureKlashaMethod();
$klashaConfigured = PaymentService::isKlashaConfigured(db()->fetch("SELECT * FROM payment_methods WHERE code = 'klasha' LIMIT 1"));
$paymentUrl = e(app_url('api/farm-fresh/membership'));
$farmFreshUrl = e(app_url('freshroots'));
$csrf = e(getCsrfToken());
$messageHtml = $message !== '' ? '<p class="intro ff-member-message">' . e($message) . '</p>' : '';
$stripeDisabled = $stripeConfigured ? '' : ' disabled';
$klashaDisabled = $klashaConfigured ? '' : ' disabled';
$providerMessage = (!$stripeConfigured && !$klashaConfigured) ? 'Payments are not configured yet. Please contact support.' : '';
$brandLogoHtml = $brandLogo !== ''
    ? '<img class="ff-brand-logo" src="' . e($brandLogo) . '" alt="' . e($brandName) . '">'
    : '';
$footerLinksHtml = '<div class="footer-links">'
    . '<a href="' . e(app_url('freshroots')) . '">Home</a>'
    . '<a href="' . e(app_url('freshroots/terms')) . '">Terms</a>'
    . '<a href="' . e(app_url('freshroots/privacy-policy')) . '">Privacy</a>'
    . '<a href="' . e(app_url('freshroots/legal')) . '">Legal</a>'
    . '</div>';

$formHtml = <<<HTML
    <form class="form-card" id="signupForm" method="post">
      <input type="hidden" name="csrf_token" value="{$csrf}">
      <h2>Create your account</h2>
      <p class="intro">Start browsing and ordering as soon as your membership is active.</p>
      {$messageHtml}

      <div class="form-section">
        <h3>Your information</h3>
        <div class="field-row">
          <div class="field">
            <label>Full name *</label>
            <input class="field" name="name" type="text" required placeholder="Full name">
          </div>
          <div class="field">
            <label>Email *</label>
            <input class="field" name="email" type="email" required placeholder="you@example.com">
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label>Phone</label>
            <input class="field" name="phone" type="tel" placeholder="+1 555 555 5555">
          </div>
          <div class="field">
            <label>City *</label>
            <input class="field" name="city" type="text" required placeholder="City">
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label>State *</label>
            <input class="field" name="state" type="text" required placeholder="State">
          </div>
          <div class="field">
            <label>Password *</label>
            <input class="field" name="password" type="password" required minlength="5" autocomplete="new-password" placeholder="Create a password">
          </div>
        </div>
        <div class="field-row single">
          <div class="field">
            <label>Confirm password *</label>
            <input class="field" name="password_confirmation" type="password" required minlength="5" autocomplete="new-password" placeholder="Confirm password">
          </div>
        </div>
      </div>

      <div class="form-section">
        <h3>Choose access</h3>
        <div class="ff-member-plans" role="radiogroup" aria-label="FreshRoots membership plan">
          <label class="tiercard selected">
            <input type="radio" name="plan" value="monthly" checked>
            <span class="radio-dot"></span>
            <span class="t-name">Monthly</span>
            <span class="t-price"><strong>&#36;19.99</strong> / month</span>
            <span class="t-commission">Stripe subscription</span>
          </label>
          <label class="tiercard">
            <input type="radio" name="plan" value="annual">
            <span class="radio-dot"></span>
            <span class="t-name">Annual</span>
            <span class="t-price"><strong>&#36;199</strong> / year</span>
            <span class="t-commission">Stripe or Klasha</span>
          </label>
        </div>
      </div>

      <div class="form-section">
        <h3>Payment</h3>
        <p class="card-note">Choose Stripe for card, Apple Pay, Google Pay, Link, and other Stripe-enabled methods. Choose Klasha for supported local payment options.</p>
        <div class="ff-pay-actions">
          <button class="submit-btn" id="stripeBtn" type="submit" name="provider" value="stripe"{$stripeDisabled}>Subscribe with Stripe</button>
          <button class="submit-btn is-secondary" id="klashaBtn" type="button"{$klashaDisabled}>Pay with Klasha</button>
        </div>
        <p class="note" id="paymentMessage">{$providerMessage}</p>
      </div>
    </form>
HTML;

$script = <<<HTML
<script src="https://js.klasha.com/pay.js"></script>
<script>
(function () {
  const form = document.getElementById('signupForm');
  const message = document.getElementById('paymentMessage');
  const endpoint = '{$paymentUrl}';
  const plans = Array.from(document.querySelectorAll('.tiercard'));

  plans.forEach((card) => {
    card.addEventListener('click', () => {
      plans.forEach((item) => item.classList.remove('selected'));
      card.classList.add('selected');
      const input = card.querySelector('input[type="radio"]');
      if (input) input.checked = true;
    });
  });

  function setMessage(text, ok) {
    if (!message) return;
    message.textContent = text || '';
    message.style.color = ok ? '#0D3D25' : '#963B2E';
  }

  function payload(action) {
    const data = new FormData(form);
    data.set('action', action);
    return data;
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const button = document.getElementById('stripeBtn');
    button.disabled = true;
    setMessage('Starting Stripe checkout...', true);
    try {
      const response = await fetch(endpoint, { method: 'POST', body: payload('stripe') });
      const json = await response.json();
      if (!response.ok || !json.ok || !json.url) throw new Error(json.message || 'Stripe checkout could not be started.');
      window.location.href = json.url;
    } catch (error) {
      setMessage(error.message || 'Stripe checkout could not be started.');
      button.disabled = false;
    }
  });

  document.getElementById('klashaBtn').addEventListener('click', async function () {
    if (!form.reportValidity()) return;
    const button = this;
    button.disabled = true;
    setMessage('Opening Klasha checkout...', true);
    try {
      const response = await fetch(endpoint, { method: 'POST', body: payload('klasha') });
      const json = await response.json();
      if (!response.ok || !json.ok || !json.klasha) throw new Error(json.message || 'Klasha checkout could not be started.');
      if (typeof KlashaClient === 'undefined') throw new Error('Klasha checkout could not load. Please refresh and try again.');
      const config = json.klasha;
      const client = new KlashaClient(
        config.merchantKey,
        config.businessId,
        config.amount,
        config.fullname,
        config.email,
        config.phone || '',
        config.reference,
        config.destinationCurrency,
        config.sourceAmount,
        config.currency,
        config.environment,
        '',
        {},
        async function (data) {
          const result = payload('klasha_callback');
          result.set('reference', config.reference);
          Object.keys(data || {}).forEach((key) => result.set(key, data[key]));
          const verify = await fetch(endpoint, { method: 'POST', body: result });
          const verified = await verify.json();
          if (verified.paid) {
            setMessage('Payment verified. Opening FreshRoots...', true);
            window.location.href = '{$farmFreshUrl}';
          } else {
            setMessage(verified.message || 'Payment response received. Access will activate after verification.', Boolean(verified.ok));
          }
        }
      );
      client.init();
    } catch (error) {
      setMessage(error.message || 'Klasha checkout could not be started.');
      button.disabled = false;
    }
  });
})();
</script>
HTML;

$extraCss = <<<CSS
<style>
.brand .ff-brand-logo, .footer-brand .ff-brand-logo { width: auto; max-width: 150px; height: 42px; object-fit: contain; display: block; }
.footer-brand .ff-brand-logo { height: 34px; max-width: 130px; }
.ff-member-message { padding: 12px 14px; background: #FAF6ED; border: 1px solid var(--line-dark); color: var(--soil) !important; }
.ff-member-plans { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.ff-pay-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 18px; }
.submit-btn.is-secondary { background: var(--deep-forest); color: #fff; }
.submit-btn:disabled { opacity: .55; cursor: not-allowed; }
@media (max-width: 900px) {
  .sidebar { position: static; top: auto; }
}
@media (max-width: 640px) {
  .wrap { padding: 0 18px; }
  .content { padding: 44px 0 72px; }
  .content-grid { display: flex; flex-direction: column; gap: 32px; }
  .form-card { padding: 24px 18px; }
  .ff-member-plans { grid-template-columns: 1fr; }
  .ff-pay-actions, .ff-pay-actions button { width: 100%; }
}
</style>
CSS;

$templateHtml = str_replace(
    [
        '<title>Get Access — FreshRoots by Seller Africa</title>',
        'href="black-farms-home.html#why"',
        'href="black-farms-home.html#how"',
        'href="apply-as-farmer.html"',
        'href="black-farms-home.html"',
        'FreshRoots by Seller Africa',
    ],
    [
        '<title>FreshRoots Membership | ' . e($brandName) . '</title><meta name="description" content="Subscribe to browse Seller Africa FreshRoots farms and produce.">',
        'href="' . e(app_url('freshroots/join#why')) . '"',
        'href="' . e(app_url('freshroots/join#how')) . '"',
        'href="' . e(app_url('freshroots')) . '"',
        'href="' . e(app_url('freshroots')) . '"',
        'FreshRoots by ' . e($brandName),
    ],
    $templateHtml
);
$templateHtml = preg_replace(
    '/<div class="brand">\s*<svg\b.*?<\/svg>\s*FreshRoots\s*<\/div>/s',
    '<div class="brand">' . $brandLogoHtml . '<span>FreshRoots</span></div>',
    $templateHtml,
    1
) ?? $templateHtml;
$templateHtml = preg_replace(
    '/<div class="footer-brand">\s*<svg\b.*?<\/svg>\s*FreshRoots by ' . preg_quote(e($brandName), '/') . '\s*<\/div>/s',
    '<div class="footer-brand">' . $brandLogoHtml . '<span>FreshRoots by ' . e($brandName) . '</span></div>',
    $templateHtml,
    1
) ?? $templateHtml;
$templateHtml = preg_replace('/<div class="footer-links">.*?<\/div>/s', $footerLinksHtml, $templateHtml, 1) ?? $templateHtml;
$templateHtml = str_replace('</style>', '</style>' . $extraCss, $templateHtml);
$templateHtml = preg_replace('/<div class="form-card" id="signupForm">.*?<script>.*?<\/script>/s', $formHtml . "\n\n" . $script, $templateHtml, 1) ?? $templateHtml;

echo $templateHtml;
