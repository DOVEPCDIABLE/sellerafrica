<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vendor Early Access — Sac Frozen</title>
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
  .logo .mark{width:30px; height:30px; flex:none;}
  .backlink{
    font-size:13px; font-weight:600; color:var(--ink-soft);
  }
  .backlink:hover{color:var(--green-deep);}

  .btn{
    display:inline-flex; align-items:center; justify-content:center;
    padding:14px 26px; border-radius:2px;
    font-size:14.5px; font-weight:600; letter-spacing:.01em;
    cursor:pointer; border:1px solid transparent;
    transition:transform .16s ease, background .16s ease, border-color .16s ease, color .16s ease;
  }
  .btn:active{transform:translateY(1px);}
  .btn-primary{background:var(--green-deep); color:var(--white);}
  .btn-primary:hover{background:var(--green);}
  .btn-gold{background:var(--gold-light); color:var(--white);}
  .btn-gold:hover{background:var(--gold);}
  .btn-block{width:100%;}

  /* ---------- Intro ---------- */
  .intro{padding:72px 0 8px; text-align:center;}
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
    max-width:22ch;
    margin:0 auto;
    letter-spacing:-.01em;
  }
  h1 em{font-style:italic; color:var(--gold);}
  .intro-sub{
    margin:20px auto 0; max-width:56ch; font-size:16.5px; color:var(--ink-soft);
  }
  .paid-note{
    display:inline-flex; align-items:center; gap:8px; margin-top:26px;
    font-size:13px; font-weight:600; color:var(--green-deep);
    background:var(--ivory-dim); border:1px solid var(--line); padding:9px 18px; border-radius:999px;
  }
  .paid-note .dot{width:6px; height:6px; border-radius:50%; background:var(--gold-light);}

  /* ---------- Benefits strip ---------- */
  .benefits{padding:48px 0 8px;}
  .benefits-grid{
    display:grid; grid-template-columns:repeat(4,1fr); gap:0;
    border:1px solid var(--line); background:var(--white);
  }
  @media (max-width:760px){ .benefits-grid{grid-template-columns:1fr 1fr;} }
  .benefit{
    padding:28px 24px; border-left:1px solid var(--line);
  }
  .benefit:first-child{border-left:none;}
  @media (max-width:760px){
    .benefit:nth-child(odd){border-left:none;}
    .benefit{border-top:1px solid var(--line);}
    .benefit:nth-child(-n+2){border-top:none;}
  }
  .benefit .b-num{font-family:'Cormorant Garamond', serif; font-size:15px; color:var(--gold); font-weight:600; margin-bottom:8px;}
  .benefit p{font-size:14px; color:var(--ink-soft);}

  /* ---------- Form section ---------- */
  .formsection{padding:72px 0 100px;}
  .form-grid{
    display:grid; grid-template-columns:1.15fr .85fr; gap:40px; align-items:flex-start;
  }
  @media (max-width:900px){ .form-grid{grid-template-columns:1fr;} }

  .card-shell{
    background:var(--white); border:1px solid var(--line); padding:40px;
  }
  @media (max-width:640px){ .card-shell{padding:24px 20px;} }
  .card-kicker{
    font-size:12px; font-weight:700; letter-spacing:.12em; text-transform:uppercase;
    color:var(--gold); margin-bottom:10px;
  }
  .card-shell h2{
    font-family:'Cormorant Garamond', serif; font-weight:600; font-size:26px; color:var(--green-deep);
  }
  .card-note{font-size:14px; color:var(--ink-soft); margin-top:10px; max-width:52ch;}

  .section-label{
    font-size:12px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-soft);
    margin:32px 0 4px;
    padding-top:24px; border-top:1px solid var(--line);
  }
  .section-label:first-of-type{border-top:none; padding-top:0; margin-top:30px;}

  .grid2{display:grid; grid-template-columns:1fr 1fr; gap:18px;}
  @media (max-width:520px){ .grid2{grid-template-columns:1fr;} }

  .field-group{margin-top:18px;}
  label{
    display:block; font-size:13px; font-weight:600; color:var(--ink); margin-bottom:7px;
  }
  label .req{color:var(--red);}
  label .hint{font-weight:400; color:var(--ink-soft); font-size:12.5px; display:block; margin-top:2px;}
  .field, select, textarea{
    width:100%; padding:13px 14px; border:1px solid var(--line); font-size:14.5px;
    font-family:inherit; background:var(--white); color:var(--ink); border-radius:2px;
  }
  textarea{resize:vertical; min-height:76px;}
  .field:focus-visible, textarea:focus-visible{outline:2px solid var(--gold); outline-offset:1px;}

  /* ---------- Tier picker ---------- */
  .tierpicker{display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-top:10px;}
  @media (max-width:560px){ .tierpicker{grid-template-columns:1fr;} }
  .tiercard{
    border:1px solid var(--line); padding:18px 16px; cursor:pointer; position:relative;
    transition:all .16s ease; background:var(--white);
  }
  .tiercard:hover{transform:translateY(-2px);}
  .tiercard.selected{border-color:var(--green-deep); box-shadow:0 0 0 1px var(--green-deep);}
  .tiercard .badge{
    position:absolute; top:-10px; left:14px; background:var(--gold-light); color:var(--white);
    font-size:10px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; padding:4px 10px; border-radius:999px;
  }
  .tiercard .t-name{font-family:'Cormorant Garamond', serif; font-weight:600; font-size:18px; color:var(--green-deep);}
  .tiercard .t-price{font-size:13px; color:var(--ink-soft); margin-top:4px;}
  .tiercard .t-price strong{color:var(--ink); font-weight:700;}
  .tiercard .t-commission{font-size:12px; color:var(--gold); font-weight:600; margin-top:8px;}
  .tiercard input{display:none;}
  .tiercard .radio-dot{
    position:absolute; top:16px; right:16px; width:16px; height:16px; border-radius:50%;
    border:1.5px solid var(--line); transition:all .15s ease;
  }
  .tiercard.selected .radio-dot{border-color:var(--green-deep); background:var(--green-deep); box-shadow:inset 0 0 0 3px var(--white);}

  /* ---------- Documentation checklist ---------- */
  .checklist{margin-top:12px; border:1px solid var(--line);}
  .check-item{
    display:flex; gap:12px; align-items:flex-start; padding:15px 18px;
    border-top:1px solid var(--line);
  }
  .check-item:first-child{border-top:none;}
  .check-item input[type="checkbox"]{
    margin-top:3px; width:16px; height:16px; accent-color:var(--green-deep); flex:none;
  }
  .check-item .ci-title{font-size:14px; font-weight:600; color:var(--ink);}
  .check-item .ci-desc{font-size:13px; color:var(--ink-soft); margin-top:3px;}
  .checklist-note{
    display:flex; gap:10px; margin-top:14px; padding:14px 16px; background:var(--ivory-dim);
    border:1px solid var(--line); font-size:13px; color:var(--ink-soft);
  }
  .checklist-note svg{flex:none; margin-top:2px;}

  .checkrow{
    display:flex; gap:12px; align-items:flex-start; margin-top:16px; padding:14px 16px;
    background:var(--ivory-dim); border:1px solid var(--line);
  }
  .checkrow input[type="checkbox"]{
    margin-top:3px; width:16px; height:16px; accent-color:var(--green-deep); flex:none;
  }
  .checkrow p{font-size:13.5px; color:var(--ink-soft);}
  .checkrow a{color:var(--green-deep); font-weight:600; text-decoration:underline;}

  /* ---------- Card visual ---------- */
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

  .submit-row{margin-top:30px;}
  .form-note{font-size:12.5px; color:var(--ink-soft); margin-top:14px; text-align:center;}

  /* ---------- Order summary ---------- */
  .summary-panel{
    background:var(--ivory-dim); border:1px solid var(--line); padding:32px;
    position:sticky; top:110px;
  }
  @media (max-width:900px){ .summary-panel{position:static;} }
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
  .summary-checklist{margin-top:22px; padding-top:20px; border-top:1px solid var(--line);}
  .summary-checklist .sc-title{font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--ink-soft); margin-bottom:10px;}
  .summary-checklist .sc-row{display:flex; justify-content:space-between; font-size:13px; margin-top:6px;}

  /* ---------- Success ---------- */
  .success-panel{
    display:none; background:var(--green-deep); color:var(--ivory); padding:60px 44px; text-align:center;
  }
  .success-panel.show{display:block;}
  .success-panel .mark-lg{width:44px; height:44px; margin:0 auto 22px;}
  .success-panel h2{color:var(--white); font-family:'Cormorant Garamond', serif; font-size:30px; font-weight:600;}
  .success-panel p{margin-top:14px; color:#C9D6CC; max-width:48ch; margin-left:auto; margin-right:auto; font-size:15px;}
  .success-panel .plan-pill{
    display:inline-block; margin-top:18px; padding:8px 18px; border:1px solid rgba(255,255,255,.25);
    border-radius:999px; font-size:13px; font-weight:600; color:var(--gold-light);
  }
  .success-panel .btn{margin-top:12px; padding:14px 30px; margin-left:6px; margin-right:6px;}

  footer{padding:36px 0 50px; border-top:1px solid var(--line); text-align:center;}
  footer p{font-size:12.5px; color:var(--ink-soft);}
  footer .footlinks{margin-top:10px; display:flex; gap:16px; justify-content:center; font-size:12.5px;}
  footer .footlinks a{color:var(--ink-soft);}
  footer .footlinks a:hover{color:var(--green-deep);}
</style>
</head>
<body>

<header>
  <div class="nav">
    <div class="logo">
      <svg class="mark" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <path d="M16 2 L28 9 V23 L16 30 L4 23 V9 Z" stroke="#946F1D" stroke-width="1.4"/>
        <path d="M16 9 L16 23 M9.5 12.5 L22.5 19.5 M22.5 12.5 L9.5 19.5" stroke="#1B4332" stroke-width="1.4"/>
      </svg>
      Sac Frozen
    </div>
    <a href="index.html" class="backlink">← Back to home</a>
  </div>
</header>

<main>
  <div class="intro">
    <div class="wrap">
      <div class="eyebrow">Vendor early access</div>
      <h1>Start selling now,<br>on the plan you <em>choose.</em></h1>
      <p class="intro-sub">Early access vendors subscribe today and begin listing as soon as their documentation is confirmed. There's no free tier, you start in the same paid environment every vendor operates in, at the plan that fits your kitchen.</p>
      <div class="paid-note"><span class="dot"></span> Paid subscription, billed annually, activates immediately</div>
    </div>
  </div>

  <div class="benefits">
    <div class="wrap benefits-grid">
      <div class="benefit">
        <div class="b-num">Live from day one</div>
        <p>You're in the real paid environment immediately, not a limited free trial.</p>
      </div>
      <div class="benefit">
        <div class="b-num">Locked-in rate</div>
        <p>Your plan's price is held for as long as your subscription stays active.</p>
      </div>
      <div class="benefit">
        <div class="b-num">Direct input</div>
        <p>Early vendors shape what gets built next. Feedback goes straight to the team.</p>
      </div>
      <div class="benefit">
        <div class="b-num">Fast review</div>
        <p>Submit your documentation checklist now, full verification follows within days.</p>
      </div>
    </div>
  </div>

  <div class="formsection">
    <div class="wrap">
      <div class="form-grid" id="formGrid">
        <div class="card-shell">
          <div class="card-kicker">Early access signup</div>
          <h2>Set up your subscription</h2>
          <p class="card-note">Choose your plan, confirm you have the required documentation, and complete payment. Your subscription activates immediately.</p>

          <form id="waitlistForm" novalidate>

            <div class="section-label" style="margin-top:26px;">Your details</div>
            <div class="grid2">
              <div class="field-group">
                <label for="bizname">Business / kitchen name <span class="req">*</span></label>
                <input class="field" id="bizname" required>
              </div>
              <div class="field-group">
                <label for="contactname">Your full name <span class="req">*</span></label>
                <input class="field" id="contactname" required>
              </div>
            </div>
            <div class="grid2">
              <div class="field-group">
                <label for="email">Email address <span class="req">*</span></label>
                <input class="field" type="email" id="email" required>
              </div>
              <div class="field-group">
                <label for="phone">Phone number</label>
                <input class="field" type="tel" id="phone">
              </div>
            </div>
            <div class="field-group">
              <label for="city">City, state <span class="req">*</span></label>
              <input class="field" id="city" placeholder="e.g. Atlanta, GA" required>
            </div>
            <div class="field-group">
              <label for="cuisine">Cuisine / dishes you'd list <span class="hint">A short list is fine</span></label>
              <input class="field" id="cuisine" placeholder="e.g. Jollof rice, suya, egusi soup">
            </div>

            <div class="section-label">Choose your plan</div>
            <div class="tierpicker" id="tierPicker">
              <label class="tiercard" data-plan="basic" data-monthly="10.00" data-annual="120.00" data-commission="8% per order">
                <input type="radio" name="tier">
                <span class="radio-dot"></span>
                <div class="t-name">Basic</div>
                <div class="t-price">$10/mo, billed annually at <strong>$120</strong></div>
                <div class="t-commission">8% commission</div>
              </label>
              <label class="tiercard selected" data-plan="standard" data-monthly="29.99" data-annual="359.88" data-commission="6% per order">
                <span class="badge">Most popular</span>
                <input type="radio" name="tier" checked>
                <span class="radio-dot"></span>
                <div class="t-name">Standard</div>
                <div class="t-price">$29.99/mo, billed annually at <strong>$359.88</strong></div>
                <div class="t-commission">6% commission</div>
              </label>
              <label class="tiercard" data-plan="premium" data-monthly="49.99" data-annual="599.88" data-commission="5% per order">
                <input type="radio" name="tier">
                <span class="radio-dot"></span>
                <div class="t-name">Premium</div>
                <div class="t-price">$49.99/mo, billed annually at <strong>$599.88</strong></div>
                <div class="t-commission">5% commission</div>
              </label>
            </div>

            <div class="section-label">Required documentation checklist</div>
            <p class="card-note" style="margin-top:0;">Confirm you can provide each item below. You'll upload the actual documents for verification in the next step, this checklist just tells us you're ready before you pay.</p>
            <div class="checklist" id="docChecklist">
              <label class="check-item">
                <input type="checkbox" data-doc="fda" required>
                <div>
                  <div class="ci-title">FDA Food Facility Registration <span class="req">*</span></div>
                  <div class="ci-desc">Required for any vendor preparing, packing, or holding food for U.S. consumption.</div>
                </div>
              </label>
              <label class="check-item">
                <input type="checkbox" data-doc="usda">
                <div>
                  <div class="ci-title">USDA / FSIS compliance <span class="hint" style="display:inline; margin:0;">Only if your products contain meat, poultry, or egg</span></div>
                  <div class="ci-desc">Establishment number for any product regulated by USDA Food Safety and Inspection Service.</div>
                </div>
              </label>
              <label class="check-item">
                <input type="checkbox" data-doc="license" required>
                <div>
                  <div class="ci-title">State or local food handler license <span class="req">*</span></div>
                  <div class="ci-desc">A valid permit, cottage food license, or commercial kitchen certification for your jurisdiction.</div>
                </div>
              </label>
              <label class="check-item">
                <input type="checkbox" data-doc="insurance" required>
                <div>
                  <div class="ci-title">Product liability insurance <span class="req">*</span></div>
                  <div class="ci-desc">Minimum $1M coverage, with a current certificate of insurance.</div>
                </div>
              </label>
              <label class="check-item">
                <input type="checkbox" data-doc="coldchain" required>
                <div>
                  <div class="ci-title">Cold chain shipping method <span class="req">*</span></div>
                  <div class="ci-desc">A declared packaging method (dry ice or gel pack) and carrier for frozen shipment.</div>
                </div>
              </label>
              <label class="check-item">
                <input type="checkbox" data-doc="labeling" required>
                <div>
                  <div class="ci-title">Labeling &amp; allergen disclosure <span class="req">*</span></div>
                  <div class="ci-desc">Ingredient list and allergen disclosure ready for every product you plan to list.</div>
                </div>
              </label>
            </div>
            <div class="checklist-note">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" stroke="#5B6158" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <span>Your subscription activates today regardless. Listings stay unpublished until Sac Frozen verifies your submitted documentation, usually within 3 to 5 business days.</span>
            </div>

            <div class="section-label">Payment details</div>
            <div class="field-group" style="margin-top:0;">
              <label for="cname">Name on card <span class="req">*</span></label>
              <input class="field" id="cname" required>
            </div>
            <div class="field-group">
              <label for="cnumber">Card number <span class="req">*</span></label>
              <input class="field" id="cnumber" inputmode="numeric" placeholder="0000 0000 0000 0000" maxlength="19" required>
            </div>
            <div class="grid2 field-group">
              <div>
                <label for="cexp">Expiry <span class="req">*</span></label>
                <input class="field" id="cexp" placeholder="MM / YY" maxlength="7" required>
              </div>
              <div>
                <label for="ccvc">CVC <span class="req">*</span></label>
                <input class="field" id="ccvc" inputmode="numeric" placeholder="•••" maxlength="4" required>
              </div>
            </div>
            <div class="field-group">
              <label for="czip">Billing ZIP <span class="req">*</span></label>
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

            <div class="section-label">Before you go</div>
            <div class="checkrow">
              <input type="checkbox" id="terms" required>
              <p>I confirm the documentation checklist above is accurate, understand my listing stays unpublished until verified, and agree to ship and deliver directly to buyers per the <a href="vendor-terms.html">Vendor Terms &amp; Conditions</a>. <span class="req">*</span></p>
            </div>

            <div class="submit-row">
              <button type="submit" class="btn btn-gold btn-block" id="submitBtn">Subscribe to Standard — $359.88/year</button>
              <p class="form-note">Already completed this before? <a href="vendor-application.html" style="color:var(--green-deep); font-weight:600;">Go to the full application</a></p>
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

          <div class="summary-checklist">
            <div class="sc-title">Documentation confirmed</div>
            <div class="sc-row"><span>Items checked</span><span id="sumChecked">0 of 6</span></div>
          </div>
        </div>
      </div>

      <div class="success-panel" id="successPanel">
        <svg class="mark-lg" viewBox="0 0 32 32" fill="none" aria-hidden="true">
          <path d="M16 2 L28 9 V23 L16 30 L4 23 V9 Z" stroke="#C9922A" stroke-width="1.4"/>
          <path d="M16 9 L16 23 M9.5 12.5 L22.5 19.5 M22.5 12.5 L9.5 19.5" stroke="#FBF8F1" stroke-width="1.4"/>
        </svg>
        <h2 id="successHeadline">You're subscribed.</h2>
        <p>Your subscription is active. Your listing stays unpublished until we verify the documentation you confirmed. Upload the actual files on the full application page to complete review.</p>
        <div class="plan-pill" id="successPlanPill">Standard — $359.88/year</div>
        <div>
          <a href="vendor-application.html" class="btn btn-gold">Upload documentation</a>
          <a href="index.html" class="btn btn-primary">Back to Sac Frozen</a>
        </div>
      </div>
    </div>
  </div>
</main>

<footer>
  <p>© 2026 Seller Africa Inc. · Sac Frozen — from their hands to your line.</p>
  <div class="footlinks">
    <a href="user-terms.html">User Terms</a>
    <a href="vendor-terms.html">Vendor Terms</a>
    <a href="privacy-policy.html">Privacy Policy</a>
  </div>
</footer>

<script>
  const plans = {
    basic:    { name: 'Basic',    monthly: 10.00, annual: 120.00,  commission: '8% per order' },
    standard: { name: 'Standard', monthly: 29.99, annual: 359.88,  commission: '6% per order' },
    premium:  { name: 'Premium',  monthly: 49.99, annual: 599.88,  commission: '5% per order' },
  };
  let selected = 'standard';

  function fmt(n){ return '$' + n.toFixed(2); }

  function renderSelection(){
    document.querySelectorAll('.tiercard').forEach(el => {
      const isSel = el.dataset.plan === selected;
      el.classList.toggle('selected', isSel);
      el.querySelector('input').checked = isSel;
    });
    const p = plans[selected];
    document.getElementById('sumPlan').textContent = p.name;
    document.getElementById('sumMonthly').textContent = fmt(p.monthly) + '/mo';
    document.getElementById('sumCommission').textContent = p.commission;
    document.getElementById('sumTotal').textContent = fmt(p.annual);
    document.getElementById('submitBtn').textContent = 'Subscribe to ' + p.name + ' — ' + fmt(p.annual) + '/year';
  }

  document.querySelectorAll('.tiercard').forEach(card => {
    card.addEventListener('click', () => {
      selected = card.dataset.plan;
      renderSelection();
    });
  });
  renderSelection();

  // checklist progress
  const checklistInputs = Array.from(document.querySelectorAll('#docChecklist input[type="checkbox"]'));
  function updateChecklistCount(){
    const checked = checklistInputs.filter(i => i.checked).length;
    document.getElementById('sumChecked').textContent = checked + ' of ' + checklistInputs.length;
  }
  checklistInputs.forEach(i => i.addEventListener('change', updateChecklistCount));
  updateChecklistCount();

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

  document.getElementById('waitlistForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const required = this.querySelectorAll('[required]');
    let ok = true;
    required.forEach(f => {
      if (f.type === 'checkbox' && !f.checked) ok = false;
      else if (f.type !== 'checkbox' && !f.value.trim()) ok = false;
    });
    if (!ok) {
      alert('Please complete all required fields, the required documentation items, and payment details before continuing.');
      return;
    }
    const plan = plans[selected];
    const first = document.getElementById('contactname').value.trim().split(' ')[0];

    document.getElementById('successHeadline').textContent = first ? `You're subscribed, ${first}.` : "You're subscribed.";
    document.getElementById('successPlanPill').textContent = `${plan.name} — ${fmt(plan.annual)}/year`;

    document.getElementById('formGrid').style.display = 'none';
    document.getElementById('successPanel').classList.add('show');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
</script>

</body>
</html>