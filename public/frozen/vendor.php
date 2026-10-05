<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/core/bootstrap.php';

$frozen = new \App\FrozenMarketService(db());
$frozen->ensureSchema();
$brand = app_branding();
$brandName = (string)($brand['name'] ?? 'Seller Africa');
$frozenErrors = [];
$frozenSubmitted = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $submittedToken = (string)($_POST['csrf_token'] ?? '');
        $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
        if ($submittedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
            throw new \RuntimeException('Your form session expired. Please refresh the page and submit again.');
        }
        $frozen->createVendorApplication($_POST, $_FILES);
        $frozenSubmitted = true;
    } catch (\Throwable $e) {
        $frozenErrors[] = $e->getMessage();
        audit('frozen_vendor_application_failed', 'frozen_vendor_applications', null, [], [
            'email' => strtolower(trim((string)($_POST['email'] ?? ''))),
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
<title>Vendor Application — Sac Frozen</title>
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
  .wrap{max-width:900px; margin:0 auto; padding:0 32px;}
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
  .btn:disabled{opacity:.45; cursor:not-allowed;}

  /* ---------- Intro ---------- */
  .intro{padding:64px 0 20px;}
  .eyebrow{
    display:inline-flex; align-items:center; gap:8px;
    font-size:12px; font-weight:600; letter-spacing:.14em; text-transform:uppercase;
    color:var(--gold); margin-bottom:20px;
  }
  .eyebrow::before{content:''; width:24px; height:1px; background:var(--gold-light);}
  h1{
    font-family:'Cormorant Garamond', serif;
    font-weight:600;
    font-size:clamp(34px, 5vw, 50px);
    line-height:1.06;
    color:var(--green-deep);
    max-width:16ch;
    letter-spacing:-.01em;
  }
  .intro-sub{
    margin-top:20px; max-width:58ch; font-size:16.5px; color:var(--ink-soft);
  }

  /* ---------- Progress ---------- */
  .progress-wrap{margin:36px 0 8px;}
  .progress-track{
    height:2px; background:var(--line); position:relative; margin-bottom:14px;
  }
  .progress-fill{
    position:absolute; left:0; top:0; height:2px; background:var(--gold-light);
    width:25%; transition:width .3s ease;
  }
  .progress-labels{display:flex; justify-content:space-between; font-size:12px; color:var(--ink-soft);}
  .progress-labels span{flex:1; text-align:center;}
  .progress-labels span:first-child{text-align:left;}
  .progress-labels span:last-child{text-align:right;}
  .progress-labels span.on{color:var(--green-deep); font-weight:700;}

  /* ---------- Form shell ---------- */
  form{padding-bottom:100px;}
  fieldset{
    border:none; padding:0; margin:0;
    display:none;
  }
  fieldset.active{display:block; animation:fadein .35s ease;}
  @keyframes fadein{ from{opacity:0; transform:translateY(8px);} to{opacity:1; transform:none;} }

  .step-card{
    background:var(--white); border:1px solid var(--line); padding:40px;
    margin-top:8px;
  }
  @media (max-width:640px){ .step-card{padding:26px 22px;} }
  .step-kicker{
    font-size:12px; font-weight:700; letter-spacing:.12em; text-transform:uppercase;
    color:var(--gold); margin-bottom:10px;
  }
  .step-card h2{
    font-family:'Cormorant Garamond', serif; font-weight:600; font-size:28px; color:var(--green-deep);
  }
  .step-card > p.step-note{margin-top:10px; color:var(--ink-soft); font-size:14.5px; max-width:56ch;}

  .grid2{display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-top:30px;}
  @media (max-width:640px){ .grid2{grid-template-columns:1fr;} }
  .field-group{margin-top:22px;}
  .field-group.tight{margin-top:0;}
  label{
    display:block; font-size:13px; font-weight:600; color:var(--ink); margin-bottom:7px;
  }
  label .req{color:var(--red);}
  label .hint{font-weight:400; color:var(--ink-soft); font-size:12.5px; display:block; margin-top:2px;}
  .field, select, textarea{
    width:100%; padding:13px 14px; border:1px solid var(--line); font-size:14.5px;
    font-family:inherit; background:var(--white); color:var(--ink); border-radius:2px;
  }
  select{appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235B6158' stroke-width='1.4' fill='none'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 14px center;}
  textarea{resize:vertical; min-height:96px;}
  .field:focus-visible, select:focus-visible, textarea:focus-visible{outline:2px solid var(--gold); outline-offset:1px;}

  .filefield{
    border:1px dashed var(--line); padding:18px; border-radius:2px; background:var(--ivory-dim);
    display:flex; align-items:center; justify-content:space-between; gap:14px; cursor:pointer;
  }
  .filefield input{display:none;}
  .filefield .flabel{font-size:13.5px; color:var(--ink-soft);}
  .filefield .fname{font-size:13px; font-weight:600; color:var(--green-deep);}
  .filefield .fbtn{
    font-size:12.5px; font-weight:700; color:var(--gold); letter-spacing:.03em; text-transform:uppercase; flex:none;
  }

  .checkrow{
    display:flex; gap:12px; align-items:flex-start; margin-top:16px; padding:14px 16px;
    background:var(--ivory-dim); border:1px solid var(--line);
  }
  .checkrow input[type="checkbox"]{
    margin-top:3px; width:16px; height:16px; accent-color:var(--green-deep); flex:none;
  }
  .checkrow p{font-size:13.5px; color:var(--ink-soft);}
  .checkrow strong{color:var(--ink); font-weight:600;}

  .radiogroup{display:flex; gap:10px; flex-wrap:wrap; margin-top:6px;}
  .radiopill{
    border:1px solid var(--line); padding:9px 16px; font-size:13.5px; border-radius:999px;
    cursor:pointer; color:var(--ink-soft); background:var(--white); transition:all .15s ease;
  }
  .radiopill input{display:none;}
  .radiopill.checked{background:var(--green-deep); color:var(--white); border-color:var(--green-deep);}

  .conditional{
    margin-top:18px; padding-left:16px; border-left:2px solid var(--gold-light); display:none;
  }
  .conditional.show{display:block;}

  .step-actions{
    display:flex; justify-content:space-between; align-items:center; margin-top:32px;
  }
  .btn-ghost{
    background:transparent; border:1px solid var(--line); color:var(--ink-soft);
  }
  .btn-ghost:hover{border-color:var(--green-deep); color:var(--green-deep);}

  /* ---------- Success ---------- */
  .success-panel{
    display:none; background:var(--green-deep); color:var(--ivory); padding:60px 44px; text-align:center; margin-top:8px;
  }
  .success-panel.show{display:block;}
  .success-panel .mark-lg{width:44px; height:44px; margin:0 auto 22px;}
  .success-panel h2{color:var(--white); font-family:'Cormorant Garamond', serif; font-size:32px; font-weight:600;}
  .success-panel p{margin-top:14px; color:#C9D6CC; max-width:48ch; margin-left:auto; margin-right:auto; font-size:15px;}
  .success-panel .btn{margin-top:26px;}

  /* ---------- Sidebar note ---------- */
  .sidenote{
    margin-top:36px; padding:22px 24px; border-left:2px solid var(--gold-light);
    background:var(--ivory-dim); font-size:13.5px; color:var(--ink-soft);
  }
  .sidenote strong{color:var(--ink); display:block; margin-bottom:6px; font-size:13px; letter-spacing:.04em; text-transform:uppercase;}

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

<main class="wrap">
  <div class="intro">
    <div class="eyebrow">Vendor application</div>
    <h1>List your kitchen on Sac Frozen.</h1>
    <p class="intro-sub">Four short steps. We verify your food safety documentation once, then you set your own prices, ship windows, and carrier. Sac Frozen never touches your product.</p>
  </div>

  <div class="progress-wrap"<?= $frozenSubmitted ? ' style="display:none;"' : '' ?>>
    <div class="progress-track"><div class="progress-fill" id="progressFill"></div></div>
    <div class="progress-labels">
      <span class="on" id="lbl-1">Business</span>
      <span id="lbl-2">Products</span>
      <span id="lbl-3">Compliance</span>
      <span id="lbl-4">Review</span>
    </div>
  </div>

  <?php if ($frozenErrors !== []): ?>
    <div class="step-card" style="border-color:var(--red); margin-top:18px;">
      <?php foreach ($frozenErrors as $message): ?><p style="color:var(--red);"><?= e($message) ?></p><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form id="vendorForm" method="post" enctype="multipart/form-data" action="<?= e(app_url('sacfrozen/vendor')) ?>" novalidate<?= $frozenSubmitted ? ' style="display:none;"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

    <!-- STEP 1 — Business & contact -->
    <fieldset class="active" data-step="1">
      <div class="step-card">
        <div class="step-kicker">Step 1 of 4</div>
        <h2>Business &amp; contact</h2>
        <p class="step-note">Who we're verifying and who buyers will be ordering from.</p>

        <div class="grid2">
          <div class="field-group tight">
            <label for="bizname">Business / kitchen name <span class="req">*</span></label>
            <input class="field" id="bizname" name="bizname" required>
          </div>
          <div class="field-group tight">
            <label for="contactname">Your full name <span class="req">*</span></label>
            <input class="field" id="contactname" name="contactname" required>
          </div>
        </div>

        <div class="grid2">
          <div class="field-group">
            <label for="email">Email address <span class="req">*</span></label>
            <input class="field" type="email" id="email" name="email" required>
          </div>
          <div class="field-group">
            <label for="phone">Phone number <span class="req">*</span></label>
            <input class="field" type="tel" id="phone" name="phone" required>
          </div>
        </div>

        <div class="grid2">
          <div class="field-group">
            <label for="city">City, state <span class="req">*</span></label>
            <input class="field" id="city" name="city" placeholder="e.g. Houston, TX" required>
          </div>
          <div class="field-group">
            <label for="entity">Business structure</label>
            <select id="entity" name="entity">
              <option value="">Select one</option>
              <option>Sole proprietorship</option>
              <option>LLC</option>
              <option>Corporation</option>
              <option>Not yet formalized</option>
            </select>
          </div>
        </div>

        <div class="field-group">
          <label for="years">Years operating <span class="hint">Home kitchen, ghost kitchen, or storefront — all welcome</span></label>
          <select id="years" name="years">
            <option value="">Select one</option>
            <option>Just starting</option>
            <option>Less than 1 year</option>
            <option>1–3 years</option>
            <option>3+ years</option>
          </select>
        </div>

        <div class="step-actions">
          <span></span>
          <button type="button" class="btn btn-primary next-btn">Continue →</button>
        </div>
      </div>
    </fieldset>

    <!-- STEP 2 — Products -->
    <fieldset data-step="2">
      <div class="step-card">
        <div class="step-kicker">Step 2 of 4</div>
        <h2>What you make and how it ships</h2>
        <p class="step-note">Buyers browse by cuisine and dish, so be specific.</p>

        <div class="field-group tight">
          <label for="origin">Cuisine / country of origin <span class="req">*</span></label>
          <select id="origin" name="origin" required>
            <option value="">Select one</option>
            <option>Nigerian</option>
            <option>Ghanaian</option>
            <option>Jamaican</option>
            <option>Trinidadian</option>
            <option>Ethiopian / Eritrean</option>
            <option>Senegalese</option>
            <option>Ivorian</option>
            <option>Multiple / Pan-African</option>
            <option>Multiple / Pan-Caribbean</option>
            <option>Other</option>
          </select>
        </div>

        <div class="field-group">
          <label for="dishes">Dishes you plan to list <span class="req">*</span> <span class="hint">One per line — e.g. Jollof rice (family size), Egusi soup, Suya spice pack</span></label>
          <textarea id="dishes" name="dishes" required></textarea>
        </div>

        <div class="field-group">
          <label>Does any listed product contain meat, poultry, or egg? <span class="req">*</span></label>
          <div class="radiogroup" id="meatGroup">
            <label class="radiopill"><input type="radio" name="meat" value="yes">Yes</label>
            <label class="radiopill"><input type="radio" name="meat" value="no">No</label>
          </div>
        </div>

        <div class="field-group">
          <label for="coldchain">How will you ship frozen? <span class="req">*</span></label>
          <select id="coldchain" name="coldchain" required>
            <option value="">Select one</option>
            <option>Dry ice, insulated box</option>
            <option>Gel packs, insulated box</option>
            <option>Third-party cold-chain fulfillment service</option>
            <option>Not yet decided</option>
          </select>
        </div>

        <div class="field-group">
          <label for="carrier">Carrier you plan to use</label>
          <select id="carrier" name="carrier">
            <option value="">Select one</option>
            <option>UPS</option>
            <option>FedEx</option>
            <option>USPS (ground only, no dry ice by air)</option>
            <option>Other / not yet decided</option>
          </select>
        </div>

        <div class="step-actions">
          <button type="button" class="btn btn-ghost prev-btn">← Back</button>
          <button type="button" class="btn btn-primary next-btn">Continue →</button>
        </div>
      </div>
    </fieldset>

    <!-- STEP 3 — Compliance -->
    <fieldset data-step="3">
      <div class="step-card">
        <div class="step-kicker">Step 3 of 4</div>
        <h2>Food safety documentation</h2>
        <p class="step-note">This is what keeps every buyer safe and every listing verified, not certified. We check that documents exist and are current, we don't inspect your kitchen.</p>

        <div class="field-group tight">
          <label for="fda">FDA Food Facility Registration number <span class="req">*</span></label>
          <input class="field" id="fda" name="fda" placeholder="e.g. 12345678901" required>
        </div>

        <div class="field-group">
          <div class="filefield" id="fdaFileWrap">
            <div>
              <div class="flabel">Upload FDA registration confirmation</div>
              <div class="fname" id="fdaFileName">No file selected</div>
            </div>
            <span class="fbtn">Choose file</span>
            <input type="file" id="fdaFile" name="fdaFile">
          </div>
        </div>

        <div class="conditional" id="usdaBlock">
          <div class="field-group tight">
            <label for="usda">USDA / FSIS establishment number <span class="hint">Required because you indicated a meat, poultry, or egg product</span></label>
            <input class="field" id="usda" name="usda">
          </div>
        </div>

        <div class="field-group">
          <label for="statelicense">State or local food handler license number <span class="req">*</span></label>
          <input class="field" id="statelicense" name="statelicense" required>
        </div>

        <div class="grid2">
          <div class="field-group tight">
            <label for="insurer">Product liability insurer</label>
            <input class="field" id="insurer" name="insurer">
          </div>
          <div class="field-group tight">
            <label for="policy">Policy number</label>
            <input class="field" id="policy" name="policy">
          </div>
        </div>

        <div class="field-group">
          <div class="filefield" id="coiFileWrap">
            <div>
              <div class="flabel">Upload certificate of insurance <span class="hint" style="display:inline; margin:0;">Minimum $1M coverage</span></div>
              <div class="fname" id="coiFileName">No file selected</div>
            </div>
            <span class="fbtn">Choose file</span>
            <input type="file" id="coiFile" name="coiFile">
          </div>
        </div>

        <div class="checkrow">
          <input type="checkbox" id="labelcheck" name="labelcheck" value="1" required>
          <p><strong>Labeling &amp; allergens.</strong> I confirm every product I list will carry an ingredient list and allergen disclosure that meets FDA labeling requirements.</p>
        </div>

        <div class="step-actions">
          <button type="button" class="btn btn-ghost prev-btn">← Back</button>
          <button type="button" class="btn btn-primary next-btn">Continue →</button>
        </div>
      </div>
    </fieldset>

    <!-- STEP 4 — Review & submit -->
    <fieldset data-step="4">
      <div class="step-card">
        <div class="step-kicker">Step 4 of 4</div>
        <h2>Review &amp; submit</h2>
        <p class="step-note">Double-check the details below. You can go back and edit any step before submitting.</p>

        <div id="reviewBlock" style="margin-top:26px; font-size:14.5px; color:var(--ink);"></div>

        <div class="checkrow">
          <input type="checkbox" id="agree1" name="agree1" value="1" required>
          <p><strong>Direct-ship agreement.</strong> I understand I ship and deliver to buyers myself. Sac Frozen does not store, repack, or ship my products.</p>
        </div>
        <div class="checkrow">
          <input type="checkbox" id="agree2" name="agree2" value="1" required>
          <p><strong>Accuracy.</strong> Everything I've submitted is accurate, and I'll keep my registration, license, and insurance documents current while listed.</p>
        </div>
        <div class="checkrow">
          <input type="checkbox" id="agree3" name="agree3" value="1" required>
          <p><strong>Indemnification.</strong> I agree to the Vendor Agreement, including indemnifying Sac Frozen and Seller Africa Inc. against claims arising from my products.</p>
        </div>

        <div class="sidenote">
          <strong>What happens next</strong>
          We verify your registration and license within 3–5 business days. Once approved, you'll set your own prices, ship windows, and go live.
        </div>

        <div class="step-actions">
          <button type="button" class="btn btn-ghost prev-btn">← Back</button>
          <button type="submit" class="btn btn-gold" id="submitBtn">Submit application</button>
        </div>
      </div>
    </fieldset>
  </form>

  <div class="success-panel<?= $frozenSubmitted ? ' show' : '' ?>" id="successPanel">
    <svg class="mark-lg" viewBox="0 0 32 32" fill="none" aria-hidden="true">
      <path d="M16 2 L28 9 V23 L16 30 L4 23 V9 Z" stroke="#C9922A" stroke-width="1.4"/>
      <path d="M16 9 L16 23 M9.5 12.5 L22.5 19.5 M22.5 12.5 L9.5 19.5" stroke="#FBF8F1" stroke-width="1.4"/>
    </svg>
    <h2>Application received.</h2>
    <p>We're reviewing your documentation now. Expect an email within 3–5 business days with next steps, or a request for anything missing.</p>
    <a href="<?= e(app_url('sacfrozen')) ?>" class="btn btn-primary">Back to Sac Frozen</a>
  </div>
</main>

<footer>
  <p>© 2026 Seller Africa Inc. · Sac Frozen — from their hands to your line.</p>
</footer>

<script>
  const steps = Array.from(document.querySelectorAll('fieldset[data-step]'));
  let current = 1;
  const total = steps.length;
  const fill = document.getElementById('progressFill');
  const labels = { 1:'lbl-1', 2:'lbl-2', 3:'lbl-3', 4:'lbl-4' };

  function goTo(n){
    steps.forEach(s => s.classList.toggle('active', Number(s.dataset.step) === n));
    fill.style.width = (n/total*100) + '%';
    Object.entries(labels).forEach(([k, id])=>{
      document.getElementById(id).classList.toggle('on', Number(k) <= n);
    });
    current = n;
    window.scrollTo({top: document.querySelector('.progress-wrap').offsetTop - 90, behavior:'smooth'});
    if(n === 4){ buildReview(); }
  }

  document.querySelectorAll('.next-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const activeStep = document.querySelector('fieldset.active');
      const requiredFields = activeStep.querySelectorAll('[required]');
      let ok = true;
      requiredFields.forEach(f=>{
        if(f.type === 'radio'){
          const group = activeStep.querySelectorAll(`[name="${f.name}"]`);
          if(![...group].some(r=>r.checked)) ok = false;
        } else if(!f.value || (f.type === 'checkbox' && !f.checked)){
          ok = false;
        }
      });
      if(!ok){
        activeStep.style.animation = 'none';
        activeStep.offsetHeight;
        activeStep.style.animation = null;
        alert('Please fill in the required fields marked with * before continuing.');
        return;
      }
      if(current < total) goTo(current + 1);
    });
  });
  document.querySelectorAll('.prev-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{ if(current > 1) goTo(current - 1); });
  });

  // meat -> USDA conditional
  document.getElementById('meatGroup').addEventListener('click', (e)=>{
    const pill = e.target.closest('.radiopill');
    if(!pill) return;
    document.querySelectorAll('#meatGroup .radiopill').forEach(p=>p.classList.remove('checked'));
    pill.classList.add('checked');
    const input = pill.querySelector('input');
    input.checked = true;
    document.getElementById('usdaBlock').classList.toggle('show', input.value === 'yes');
  });

  // file field cosmetic feedback
  function wireFile(wrapId, inputId, nameId){
    const wrap = document.getElementById(wrapId);
    const input = document.getElementById(inputId);
    const nameEl = document.getElementById(nameId);
    wrap.addEventListener('click', ()=> input.click());
    input.addEventListener('change', ()=>{
      nameEl.textContent = input.files.length ? input.files[0].name : 'No file selected';
    });
  }
  wireFile('fdaFileWrap','fdaFile','fdaFileName');
  wireFile('coiFileWrap','coiFile','coiFileName');

  function val(id){ const el = document.getElementById(id); return el ? el.value : ''; }

  function buildReview(){
    const meat = document.querySelector('input[name="meat"]:checked');
    const rows = [
      ['Business', val('bizname')],
      ['Contact', val('contactname') + ' · ' + val('email') + ' · ' + val('phone')],
      ['Location', val('city')],
      ['Cuisine', val('origin')],
      ['Contains meat/poultry/egg', meat ? (meat.value === 'yes' ? 'Yes' : 'No') : '—'],
      ['Cold-chain method', val('coldchain')],
      ['FDA registration #', val('fda') || '—'],
      ['State/local license #', val('statelicense') || '—'],
    ];
    const html = rows.map(([k,v]) => `
      <div style="display:flex; justify-content:space-between; gap:20px; padding:12px 0; border-top:1px solid var(--line);">
        <span style="color:var(--ink-soft);">${k}</span>
        <span style="font-weight:600; text-align:right;">${v || '—'}</span>
      </div>`).join('');
    document.getElementById('reviewBlock').innerHTML = html;
  }

  document.getElementById('vendorForm').addEventListener('submit', function(e){
    e.preventDefault();
    const activeStep = document.querySelector('fieldset.active');
    const requiredFields = activeStep.querySelectorAll('[required]');
    let ok = true;
    requiredFields.forEach(f=>{ if(f.type === 'checkbox' && !f.checked) ok = false; });
    if(!ok){ alert('Please confirm all three agreements before submitting.'); return; }
    this.submit();
  });
</script>

</body>
</html>
