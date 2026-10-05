<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/core/bootstrap.php';

$frozen = new \App\FrozenMarketService(db());
$frozen->ensureSchema();
$brand = app_branding();
$brandName = (string)($brand['name'] ?? 'Seller Africa');
$frozenErrors = [];
$frozenSubmitted = false;
$selectedPlan = 'standard';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $submittedToken = (string)($_POST['csrf_token'] ?? '');
        $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
        if ($submittedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
            throw new \RuntimeException('Your form session expired. Please refresh the page and submit again.');
        }
        $selectedPlan = strtolower(trim((string)($_POST['plan'] ?? 'standard')));
        $frozen->recordPlanSelection($selectedPlan);
        $frozenSubmitted = true;
    } catch (\Throwable $e) {
        $frozenErrors[] = $e->getMessage();
        audit('frozen_vendor_plan_selection_failed', 'frozen_vendor_plan_selections', null, [], [
            'plan' => strtolower(trim((string)($_POST['plan'] ?? ''))),
            'error' => $e->getMessage(),
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vendor Plans & Payment — Sac Frozen</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --green:#1B4332;
    --green-deep:#102A20;
    --gold:#946F1D;
    --gold-light:#C9922A;
    --ivory:#FBF8F1;
    --ivory-dim:#F2EDE1;
    --ink:#20241F;
    --ink-soft:#5B6158;
    --line:#DCD3BC;
    --white:#FFFFFF;
    --red:#963B2E;
  }
  *{box-sizing:border-box; margin:0; padding:0;}
  html{scroll-behavior:smooth;}
  body{
    font-family:'Inter', 'Calibri', 'Carlito', sans-serif;
    color:var(--ink);
    background:var(--ivory);
    line-height:1.5;
    -webkit-font-smoothing:antialiased;
  }
  img,svg{display:block; max-width:100%;}
  a{color:inherit; text-decoration:none;}
  .display{font-family:'Cormorant Garamond', 'Georgia', serif;}
  :focus-visible{outline:2px solid var(--gold); outline-offset:3px;}
  .wrap{max-width:1180px; margin:0 auto; padding:0 32px;}
  @media (max-width:640px){ .wrap{padding:0 20px;} }

  /* ---------- Header ---------- */
  header{
    position:sticky; top:0; z-index:50;
    background:rgba(251,248,241,.94);
    backdrop-filter:blur(8px);
    border-bottom:1px solid var(--line);
  }
  .nav{
    max-width:1180px; margin:0 auto; padding:18px 32px;
    display:flex; align-items:center; justify-content:space-between;
  }
  @media (max-width:640px){ .nav{padding:18px 20px;} }
  .logo{
    display:flex; align-items:center; gap:10px;
    font-family:'Cormorant Garamond', serif;
    font-weight:600; font-size:22px; letter-spacing:.02em;
    color:var(--green-deep);
  }
  .logo .mark{width:30px; height:30px;}
  .logo img{width:auto; max-width:172px; max-height:54px; object-fit:contain;}
  .backlink{
    font-size:13px; font-weight:600; color:var(--ink-soft);
    display:flex; align-items:center; gap:6px;
  }
  .backlink:hover{color:var(--green-deep);}

  .btn{
    display:inline-flex; align-items:center; justify-content:center;
    padding:13px 24px; border-radius:2px;
    font-size:14.5px; font-weight:600; letter-spacing:.01em;
    cursor:pointer; border:1px solid transparent;
    transition:transform .16s ease, background .16s ease, border-color .16s ease, color .16s ease;
  }
  .btn:active{transform:translateY(1px);}
  .btn-primary{background:var(--green-deep); color:var(--white);}
  .btn-primary:hover{background:var(--green);}
  .btn-gold{background:var(--gold-light); color:var(--white);}
  .btn-gold:hover{background:var(--gold);}
  .btn-outline{border-color:var(--green-deep); color:var(--green-deep); background:transparent;}
  .btn-outline:hover{background:var(--green-deep); color:var(--white);}
  .btn-block{width:100%;}

  /* ---------- Intro ---------- */
  .intro{padding:64px 0 8px; text-align:center;}
  .eyebrow{
    display:inline-flex; align-items:center; justify-content:center; gap:8px;
    font-size:12px; font-weight:600; letter-spacing:.14em; text-transform:uppercase;
    color:var(--gold); margin-bottom:20px;
  }
  .eyebrow::before, .eyebrow::after{content:''; width:24px; height:1px; background:var(--gold-light);}
  h1{
    font-family:'Cormorant Garamond', serif;
    font-weight:600;
    font-size:clamp(32px, 5vw, 50px);
    line-height:1.06;
    color:var(--green-deep);
    max-width:20ch;
    margin:0 auto;
    letter-spacing:-.01em;
  }
  .intro-sub{
    margin:20px auto 0; max-width:52ch; font-size:16.5px; color:var(--ink-soft);
  }
  .billing-note{
    display:inline-flex; align-items:center; gap:8px; margin-top:26px;
    font-size:13px; font-weight:600; color:var(--green-deep);
    background:var(--ivory-dim); border:1px solid var(--line); padding:9px 18px; border-radius:999px;
  }
  .billing-note .dot{width:6px; height:6px; border-radius:50%; background:var(--gold-light);}

  /* ---------- Pricing grid ---------- */
  .pricing{padding:56px 0 80px;}
  .plans{
    display:grid; grid-template-columns:repeat(3,1fr); gap:24px;
  }
  @media (max-width:900px){ .plans{grid-template-columns:1fr; max-width:440px; margin:0 auto;} }

  .plan{
    background:var(--white); border:1px solid var(--line); padding:34px 30px;
    cursor:pointer; position:relative; transition:border-color .18s ease, transform .18s ease, box-shadow .18s ease;
  }
  .plan:hover{transform:translateY(-3px);}
  .plan.selected{
    border-color:var(--green-deep); box-shadow:0 0 0 1px var(--green-deep);
  }
  .plan.featured{border-color:var(--gold-light);}
  .plan.featured.selected{box-shadow:0 0 0 1px var(--gold-light); border-color:var(--gold-light);}

  .badge{
    position:absolute; top:-13px; left:30px;
    background:var(--gold-light); color:var(--white); font-size:11px; font-weight:700;
    letter-spacing:.08em; text-transform:uppercase; padding:6px 14px; border-radius:999px;
  }
  .plan-name{
    font-family:'Cormorant Garamond', serif; font-size:24px; font-weight:600; color:var(--green-deep);
  }
  .plan-tagline{font-size:13.5px; color:var(--ink-soft); margin-top:6px; min-height:38px;}
  .plan-price{
    display:flex; align-items:baseline; gap:6px; margin-top:22px;
  }
  .plan-price .amount{
    font-family:'Cormorant Garamond', serif; font-size:46px; font-weight:600; color:var(--ink);
  }
  .plan-price .period{font-size:14px; color:var(--ink-soft);}
  .plan-annual{font-size:12.5px; color:var(--ink-soft); margin-top:6px;}

  .plan-select{
    margin-top:24px; width:100%; padding:12px; text-align:center;
    border:1px solid var(--green-deep); color:var(--green-deep); font-size:13.5px; font-weight:700;
    border-radius:2px; background:transparent; transition:all .16s ease;
  }
  .plan.selected .plan-select{background:var(--green-deep); color:var(--white);}
  .plan.featured .plan-select{border-color:var(--gold-light); color:var(--gold);}
  .plan.featured.selected .plan-select{background:var(--gold-light); color:var(--white); border-color:var(--gold-light);}

  .plan-features{list-style:none; margin-top:26px; padding-top:22px; border-top:1px solid var(--line);}
  .plan-features li{
    display:flex; gap:10px; align-items:flex-start; font-size:13.5px; color:var(--ink); margin-top:13px;
  }
  .plan-features li:first-child{margin-top:0;}
  .plan-features svg{flex:none; margin-top:2px;}

  /* ---------- Checkout ---------- */
  .checkout-section{padding-bottom:100px;}
  .checkout-grid{
    display:grid; grid-template-columns:1fr 1fr; gap:40px; align-items:flex-start;
  }
  @media (max-width:860px){ .checkout-grid{grid-template-columns:1fr;} }

  .card-panel{
    background:var(--white); border:1px solid var(--line); padding:36px;
  }
  @media (max-width:640px){ .card-panel{padding:24px 20px;} }
  .card-kicker{
    font-size:12px; font-weight:700; letter-spacing:.12em; text-transform:uppercase;
    color:var(--gold); margin-bottom:10px;
  }
  .card-panel h2{
    font-family:'Cormorant Garamond', serif; font-weight:600; font-size:24px; color:var(--green-deep);
  }

  .field-group{margin-top:18px;}
  label{display:block; font-size:13px; font-weight:600; color:var(--ink); margin-bottom:7px;}
  .field{
    width:100%; padding:13px 14px; border:1px solid var(--line); font-size:14.5px;
    font-family:inherit; background:var(--white); color:var(--ink); border-radius:2px;
  }
  .field:focus-visible{outline:2px solid var(--gold); outline-offset:1px;}
  .grid2{display:grid; grid-template-columns:1fr 1fr; gap:14px;}
  .grid3{display:grid; grid-template-columns:1.3fr .8fr .8fr; gap:14px;}
  @media (max-width:480px){ .grid3{grid-template-columns:1fr;} }

  .card-visual{
    position:relative;
    background:linear-gradient(135deg, var(--green-deep), #1E4A38);
    border-radius:10px; padding:24px; color:var(--ivory); margin-top:22px;
    overflow:hidden; min-height:150px;
  }
  .card-visual::after{
    content:''; position:absolute; right:-40px; top:-40px; width:150px; height:150px;
    border:1px solid rgba(255,255,255,.12); border-radius:50%;
  }
  .card-visual .cv-number{
    font-family:'Cormorant Garamond', serif; font-size:22px; letter-spacing:.08em; margin-top:30px;
  }
  .card-visual .cv-row{display:flex; justify-content:space-between; margin-top:20px; font-size:11px; letter-spacing:.06em; text-transform:uppercase; color:#B9C6BD;}
  .card-visual .cv-name{font-size:13px; color:var(--white); margin-top:4px; text-transform:none; letter-spacing:.02em;}

  .paylock{
    display:flex; align-items:center; gap:8px; margin-top:20px; font-size:12.5px; color:var(--ink-soft);
  }

  /* ---------- Order summary ---------- */
  .summary-panel{
    background:var(--ivory-dim); border:1px solid var(--line); padding:34px;
    position:sticky; top:110px;
  }
  @media (max-width:860px){ .summary-panel{position:static;} }
  .summary-panel h3{
    font-family:'Cormorant Garamond', serif; font-weight:600; font-size:20px; color:var(--green-deep);
  }
  .summary-row{
    display:flex; justify-content:space-between; margin-top:18px; padding-top:16px; border-top:1px solid var(--line);
    font-size:14px;
  }
  .summary-row:first-of-type{border-top:none; margin-top:22px; padding-top:0;}
  .summary-row .label{color:var(--ink-soft);}
  .summary-row .value{font-weight:600;}
  .summary-total{
    display:flex; justify-content:space-between; margin-top:22px; padding-top:20px; border-top:1px solid var(--line);
    font-size:16px; font-weight:700; color:var(--green-deep);
  }
  .summary-note{font-size:12px; color:var(--ink-soft); margin-top:14px;}

  /* ---------- Success ---------- */
  .success-panel{
    display:none; background:var(--green-deep); color:var(--ivory); padding:60px 44px; text-align:center;
  }
  .success-panel.show{display:block;}
  .success-panel .mark-lg{width:44px; height:44px; margin:0 auto 22px;}
  .success-panel h2{color:var(--white); font-family:'Cormorant Garamond', serif; font-size:30px; font-weight:600;}
  .success-panel p{margin-top:14px; color:#C9D6CC; max-width:46ch; margin-left:auto; margin-right:auto; font-size:15px;}
  .success-panel .btn{margin-top:26px; padding:14px 30px;}

  footer{padding:36px 0 50px; border-top:1px solid var(--line); text-align:center;}
  footer p{font-size:12.5px; color:var(--ink-soft);}
</style>
</head>
<body>

<header>
  <div class="nav">
    <a class="logo" href="<?= e(app_url('sacfrozen')) ?>"><?= \App\FrozenMarketService::logoHtml('', $brandName) ?></a>
    <a href="<?= e(app_url('sacfrozen')) ?>" class="backlink">Back to home</a>
  </div>
</header>

<main>
  <div class="wrap">
    <div class="intro">
      <div class="eyebrow">Vendor plans</div>
      <h1>Choose the plan that fits your kitchen.</h1>
      <p class="intro-sub">Every plan includes direct-ship listing, buyer discovery, and secure payouts. Sac Frozen never touches your product, only the connection.</p>
      <div class="billing-note"><span class="dot"></span> All plans billed annually — shown as a monthly rate</div>
    </div>
  </div>

  <div class="wrap pricing">
    <div class="plans" id="plans">

      <div class="plan" data-plan="basic" data-monthly="10" data-annual="120">
        <div class="plan-name">Basic</div>
        <div class="plan-tagline">For vendors just getting started with a small menu.</div>
        <div class="plan-price"><span class="amount">$10</span><span class="period">/mo</span></div>
        <div class="plan-annual">Billed annually at $120/year</div>
        <div class="plan-select">Select Basic</div>
        <ul class="plan-features">
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> List up to 10 products</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Standard search placement</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> 8% commission per order</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Monthly payouts</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Email support</li>
        </ul>
      </div>

      <div class="plan featured" data-plan="standard" data-monthly="29.99" data-annual="359.88">
        <div class="badge">Most popular</div>
        <div class="plan-name">Standard</div>
        <div class="plan-tagline">For vendors ready to grow their catalog and visibility.</div>
        <div class="plan-price"><span class="amount">$29.99</span><span class="period">/mo</span></div>
        <div class="plan-annual">Billed annually at $359.88/year</div>
        <div class="plan-select">Select Standard</div>
        <ul class="plan-features">
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> List up to 50 products</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Rotating featured placement</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> 6% commission per order</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Weekly payouts</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Priority email support</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Basic buyer insights</li>
        </ul>
      </div>

      <div class="plan" data-plan="premium" data-monthly="49.99" data-annual="599.88">
        <div class="plan-name">Premium</div>
        <div class="plan-tagline">For established kitchens ready to scale across markets.</div>
        <div class="plan-price"><span class="amount">$49.99</span><span class="period">/mo</span></div>
        <div class="plan-annual">Billed annually at $599.88/year</div>
        <div class="plan-select">Select Premium</div>
        <ul class="plan-features">
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Unlimited product listings</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Guaranteed homepage placement</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> 5% commission per order — our lowest rate</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Daily payouts</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Dedicated account support</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Full buyer insights dashboard</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Early access to new city launches</li>
        </ul>
      </div>

    </div>
  </div>

  <div class="wrap checkout-section">
    <?php if ($frozenErrors !== []): ?>
      <div class="card-panel" style="border-color:var(--red); margin-bottom:18px;">
        <?php foreach ($frozenErrors as $message): ?><p style="color:var(--red);"><?= e($message) ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="checkout-grid" id="checkoutCard"<?= $frozenSubmitted ? ' style="display:none;"' : '' ?>>
      <div class="card-panel">
        <div class="card-kicker">Payment details</div>
        <h2>Complete your subscription</h2>

        <form id="paymentForm" method="post" action="<?= e(app_url('sacfrozen/pricing')) ?>" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
          <input type="hidden" name="plan" id="selectedPlan" value="<?= e($selectedPlan) ?>">
          <div class="field-group">
            <label for="cname">Name on card <span style="color:var(--red);">*</span></label>
            <input class="field" id="cname" required>
          </div>
          <div class="field-group">
            <label for="cnumber">Card number <span style="color:var(--red);">*</span></label>
            <input class="field" id="cnumber" inputmode="numeric" placeholder="0000 0000 0000 0000" maxlength="19" required>
          </div>
          <div class="grid2 field-group">
            <div>
              <label for="cexp">Expiry <span style="color:var(--red);">*</span></label>
              <input class="field" id="cexp" placeholder="MM / YY" maxlength="7" required>
            </div>
            <div>
              <label for="ccvc">CVC <span style="color:var(--red);">*</span></label>
              <input class="field" id="ccvc" inputmode="numeric" placeholder="•••" maxlength="4" required>
            </div>
          </div>
          <div class="field-group">
            <label for="czip">Billing ZIP <span style="color:var(--red);">*</span></label>
            <input class="field" id="czip" maxlength="10" required>
          </div>

          <div class="card-visual" aria-hidden="true">
            <svg width="34" height="24" viewBox="0 0 34 24" fill="none"><rect width="34" height="24" rx="3" fill="#C9922A" opacity=".85"/></svg>
            <div class="cv-number" id="cvNumber">•••• •••• •••• ••••</div>
            <div class="cv-row">
              <div><div>Card holder</div><div class="cv-name" id="cvName">Your name</div></div>
              <div><div>Expires</div><div class="cv-name" id="cvExp">MM/YY</div></div>
            </div>
          </div>

          <div class="paylock">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><rect x="5" y="11" width="14" height="9" rx="1.5" stroke="#5B6158" stroke-width="1.5"/><path d="M8 11V7a4 4 0 018 0v4" stroke="#5B6158" stroke-width="1.5"/></svg>
            This is a front-end preview only. No real payment is processed and no card data is transmitted or stored.
          </div>

          <div class="field-group" style="margin-top:26px;">
            <button type="submit" class="btn btn-gold btn-block" id="submitBtn">Subscribe to Standard — $359.88/year</button>
          </div>
        </form>
      </div>

      <div class="summary-panel">
        <h3>Order summary</h3>
        <div class="summary-row" style="border-top:none; margin-top:22px; padding-top:0;">
          <span class="label">Plan</span>
          <span class="value" id="sumPlan">Standard</span>
        </div>
        <div class="summary-row">
          <span class="label">Monthly rate</span>
          <span class="value" id="sumMonthly">$29.99/mo</span>
        </div>
        <div class="summary-row">
          <span class="label">Billing cycle</span>
          <span class="value">Annual</span>
        </div>
        <div class="summary-row">
          <span class="label">Commission rate</span>
          <span class="value" id="sumCommission">6% per order</span>
        </div>
        <div class="summary-total">
          <span>Due today</span>
          <span id="sumTotal">$359.88</span>
        </div>
        <p class="summary-note">Renews annually. Cancel or change your plan anytime from your vendor dashboard.</p>
      </div>
    </div>

    <div class="success-panel<?= $frozenSubmitted ? ' show' : '' ?>" id="successPanel">
      <svg class="mark-lg" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <path d="M16 2 L28 9 V23 L16 30 L4 23 V9 Z" stroke="#C9922A" stroke-width="1.4"/>
        <path d="M16 9 L16 23 M9.5 12.5 L22.5 19.5 M22.5 12.5 L9.5 19.5" stroke="#FBF8F1" stroke-width="1.4"/>
      </svg>
      <h2 id="successPlanName">You're subscribed.</h2>
      <p>Your plan is active. Head to your vendor dashboard to finish setting up your storefront and start listing.</p>
      <a href="<?= e(app_url('sacfrozen/vendor')) ?>" class="btn btn-primary">Go to vendor setup</a>
    </div>
  </div>
</main>

<footer>
  <p>© 2026 Seller Africa Inc. · Sac Frozen — from their hands to your line.</p>
</footer>

<script>
  const plans = {
    basic:    { name: 'Basic',    monthly: 10.00,  annual: 120.00,  commission: '8% per order' },
    standard: { name: 'Standard', monthly: 29.99,  annual: 359.88,  commission: '6% per order' },
    premium:  { name: 'Premium',  monthly: 49.99,  annual: 599.88,  commission: '5% per order' },
  };
  let selected = 'standard';

  function fmt(n){ return '$' + n.toFixed(2); }

  function renderSelection(){
    document.querySelectorAll('.plan').forEach(el => {
      el.classList.toggle('selected', el.dataset.plan === selected);
      el.querySelector('.plan-select').textContent =
        el.dataset.plan === selected ? 'Selected' : 'Select ' + plans[el.dataset.plan].name;
    });
    const p = plans[selected];
    document.getElementById('selectedPlan').value = selected;
    document.getElementById('sumPlan').textContent = p.name;
    document.getElementById('sumMonthly').textContent = fmt(p.monthly) + '/mo';
    document.getElementById('sumCommission').textContent = p.commission;
    document.getElementById('sumTotal').textContent = fmt(p.annual);
    document.getElementById('submitBtn').textContent =
      'Subscribe to ' + p.name + ' — ' + fmt(p.annual) + '/year';
  }

  document.querySelectorAll('.plan').forEach(el => {
    el.addEventListener('click', () => {
      selected = el.dataset.plan;
      renderSelection();
      document.getElementById('checkoutCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  renderSelection();

  // card number formatting
  const cnumber = document.getElementById('cnumber');
  cnumber.addEventListener('input', () => {
    let digits = cnumber.value.replace(/\D/g, '').slice(0, 16);
    cnumber.value = digits.replace(/(.{4})/g, '$1 ').trim();
    document.getElementById('cvNumber').textContent = digits.length
      ? digits.replace(/(.{4})/g, '$1 ').trim().padEnd(19, '•')
      : '•••• •••• •••• ••••';
  });

  // expiry formatting
  const cexp = document.getElementById('cexp');
  cexp.addEventListener('input', () => {
    let digits = cexp.value.replace(/\D/g, '').slice(0, 4);
    if (digits.length >= 3) digits = digits.slice(0,2) + ' / ' + digits.slice(2);
    cexp.value = digits;
    document.getElementById('cvExp').textContent = digits || 'MM/YY';
  });

  // cardholder name live preview
  const cname = document.getElementById('cname');
  cname.addEventListener('input', () => {
    document.getElementById('cvName').textContent = cname.value || 'Your name';
  });

  // cvc numeric only
  document.getElementById('ccvc').addEventListener('input', function(){
    this.value = this.value.replace(/\D/g, '').slice(0,4);
  });

  function check(){
    return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#946F1D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  }

  document.getElementById('paymentForm').addEventListener('submit', function(e){
    e.preventDefault();
    const required = this.querySelectorAll('[required]');
    let ok = true;
    required.forEach(f => { if (!f.value.trim()) ok = false; });
    if (!ok) { alert('Please fill in all payment fields to continue.'); return; }
    this.submit();
  });
</script>

</body>
</html>
