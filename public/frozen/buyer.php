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
        $frozen->createBuyer($_POST);
        $frozenSubmitted = true;
    } catch (\Throwable $e) {
        $frozenErrors[] = $e->getMessage();
        audit('frozen_buyer_registration_failed', 'users', null, [], [
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
<title>Buyer Registration — Sac Frozen</title>
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
    width:100%;
  }
  .btn:active{transform:translateY(1px);}
  .btn-primary{background:var(--green-deep); color:var(--white);}
  .btn-primary:hover{background:var(--green);}
  .btn-gold{background:var(--gold-light); color:var(--white);}
  .btn-gold:hover{background:var(--gold);}

  /* ---------- Layout ---------- */
  main.wrap{
    display:grid; grid-template-columns:1fr 1.15fr; gap:64px; align-items:flex-start;
    padding:64px 32px 100px;
  }
  @media (max-width:860px){ main.wrap{grid-template-columns:1fr; padding:44px 20px 80px;} }

  /* ---------- Intro side ---------- */
  .intro{position:sticky; top:110px;}
  @media (max-width:860px){ .intro{position:static;} }
  .eyebrow{
    display:inline-flex; align-items:center; gap:8px;
    font-size:12px; font-weight:600; letter-spacing:.14em; text-transform:uppercase;
    color:var(--gold); margin-bottom:20px;
  }
  .eyebrow::before{content:''; width:24px; height:1px; background:var(--gold-light);}
  h1{
    font-family:'Cormorant Garamond', serif;
    font-weight:600;
    font-size:clamp(32px, 4.4vw, 46px);
    line-height:1.08;
    color:var(--green-deep);
    max-width:14ch;
    letter-spacing:-.01em;
  }
  h1 em{font-style:italic; color:var(--gold);}
  .intro-sub{
    margin-top:20px; max-width:38ch; font-size:16px; color:var(--ink-soft);
  }
  .intro-points{margin-top:36px; display:flex; flex-direction:column; gap:18px;}
  .intro-point{display:flex; gap:14px; align-items:flex-start;}
  .intro-point .dot{
    width:7px; height:7px; border-radius:50%; background:var(--gold-light); margin-top:7px; flex:none;
  }
  .intro-point p{font-size:14.5px; color:var(--ink-soft);}
  .intro-point strong{color:var(--ink); font-weight:600;}

  /* ---------- Form card ---------- */
  .step-card{
    background:var(--white); border:1px solid var(--line); padding:40px;
  }
  @media (max-width:640px){ .step-card{padding:26px 22px;} }
  .card-kicker{
    font-size:12px; font-weight:700; letter-spacing:.12em; text-transform:uppercase;
    color:var(--gold); margin-bottom:10px;
  }
  .step-card h2{
    font-family:'Cormorant Garamond', serif; font-weight:600; font-size:26px; color:var(--green-deep);
  }
  .section-label{
    font-size:12px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-soft);
    margin:34px 0 4px;
    padding-top:22px; border-top:1px solid var(--line);
  }
  .section-label:first-of-type{border-top:none; padding-top:0; margin-top:26px;}

  .grid2{display:grid; grid-template-columns:1fr 1fr; gap:18px;}
  @media (max-width:520px){ .grid2{grid-template-columns:1fr;} }
  .grid3{display:grid; grid-template-columns:1.4fr .8fr .8fr; gap:14px;}
  @media (max-width:520px){ .grid3{grid-template-columns:1fr;} }

  .field-group{margin-top:18px;}
  label{
    display:block; font-size:13px; font-weight:600; color:var(--ink); margin-bottom:7px;
  }
  label .req{color:var(--red);}
  label .hint{font-weight:400; color:var(--ink-soft); font-size:12.5px; display:block; margin-top:2px;}
  .field, select{
    width:100%; padding:13px 14px; border:1px solid var(--line); font-size:14.5px;
    font-family:inherit; background:var(--white); color:var(--ink); border-radius:2px;
  }
  select{appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235B6158' stroke-width='1.4' fill='none'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 14px center;}
  .field:focus-visible, select:focus-visible{outline:2px solid var(--gold); outline-offset:1px;}

  .pwd-wrap{position:relative;}
  .pwd-toggle{
    position:absolute; right:12px; top:50%; transform:translateY(-50%);
    font-size:12px; font-weight:700; color:var(--gold); background:none; border:none; cursor:pointer;
    letter-spacing:.03em; text-transform:uppercase; font-family:inherit;
  }
  .pwd-hint{font-size:12px; color:var(--ink-soft); margin-top:6px;}
  .pwd-hint.ok{color:var(--green);}

  .chipselect{display:flex; flex-wrap:wrap; gap:9px; margin-top:8px;}
  .chip-toggle{
    border:1px solid var(--line); padding:9px 16px; font-size:13.5px; border-radius:999px;
    cursor:pointer; color:var(--ink-soft); background:var(--white); transition:all .15s ease; user-select:none;
  }
  .chip-toggle input{display:none;}
  .chip-toggle.checked{background:var(--green-deep); color:var(--white); border-color:var(--green-deep);}

  .checkrow{
    display:flex; gap:12px; align-items:flex-start; margin-top:16px; padding:14px 16px;
    background:var(--ivory-dim); border:1px solid var(--line);
  }
  .checkrow input[type="checkbox"]{
    margin-top:3px; width:16px; height:16px; accent-color:var(--green-deep); flex:none;
  }
  .checkrow p{font-size:13.5px; color:var(--ink-soft);}
  .checkrow a{color:var(--green-deep); font-weight:600; text-decoration:underline;}

  .submit-row{margin-top:30px;}
  .form-note{font-size:12.5px; color:var(--ink-soft); margin-top:14px; text-align:center;}
  .form-note a{color:var(--green-deep); font-weight:600;}

  /* ---------- Success ---------- */
  .success-panel{
    display:none; background:var(--green-deep); color:var(--ivory); padding:60px 44px; text-align:center;
  }
  .success-panel.show{display:block;}
  .success-panel .mark-lg{width:44px; height:44px; margin:0 auto 22px;}
  .success-panel h2{color:var(--white); font-family:'Cormorant Garamond', serif; font-size:30px; font-weight:600;}
  .success-panel p{margin-top:14px; color:#C9D6CC; max-width:44ch; margin-left:auto; margin-right:auto; font-size:15px;}
  .success-panel .btn{margin-top:26px; width:auto; padding:14px 30px;}

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
    <div class="eyebrow">Buyer registration</div>
    <h1>Order from the kitchens<br>that taste like <em>home.</em></h1>
    <p class="intro-sub">Create your account to order frozen dishes shipped straight from the vendors who make them, with no warehouse in between.</p>

    <div class="intro-points">
      <div class="intro-point">
        <span class="dot"></span>
        <p><strong>Shipped direct.</strong> Every order comes straight from the vendor's kitchen, not a fulfillment center.</p>
      </div>
      <div class="intro-point">
        <span class="dot"></span>
        <p><strong>Verified vendors only.</strong> Every seller on Sac Frozen holds current food safety registration and insurance.</p>
      </div>
      <div class="intro-point">
        <span class="dot"></span>
        <p><strong>Free to join.</strong> Creating an account costs nothing. You only pay when you order.</p>
      </div>
    </div>
  </div>

  <div>
    <?php if ($frozenErrors !== []): ?>
      <div class="step-card" style="border-color:var(--red); margin-bottom:18px;">
        <?php foreach ($frozenErrors as $message): ?><p style="color:var(--red);"><?= e($message) ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="step-card" id="formCard"<?= $frozenSubmitted ? ' style="display:none;"' : '' ?>>
      <div class="card-kicker">Create your account</div>
      <h2>A few details to get started</h2>

      <form id="buyerForm" method="post" action="<?= e(app_url('sacfrozen/buyer')) ?>" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

        <div class="section-label">Your details</div>
        <div class="grid2">
          <div class="field-group">
            <label for="fname">Full name <span class="req">*</span></label>
            <input class="field" id="fname" name="fname" required>
          </div>
          <div class="field-group">
            <label for="phone">Phone number</label>
            <input class="field" type="tel" id="phone" name="phone">
          </div>
        </div>
        <div class="field-group">
          <label for="email">Email address <span class="req">*</span></label>
          <input class="field" type="email" id="email" name="email" required>
        </div>
        <div class="field-group">
          <label for="pwd">Password <span class="req">*</span> <span class="hint">At least 8 characters</span></label>
          <div class="pwd-wrap">
            <input class="field" type="password" id="pwd" name="pwd" minlength="8" required>
            <button type="button" class="pwd-toggle" id="pwdToggle">Show</button>
          </div>
          <div class="pwd-hint" id="pwdHint"></div>
        </div>

        <div class="section-label">Delivery location</div>
        <div class="field-group">
          <label for="address">Street address <span class="req">*</span></label>
          <input class="field" id="address" name="address" required>
        </div>
        <div class="grid3">
          <div class="field-group">
            <label for="city">City <span class="req">*</span></label>
            <input class="field" id="city" name="city" required>
          </div>
          <div class="field-group">
            <label for="state">State <span class="req">*</span></label>
            <select id="state" name="state" required>
              <option value="">—</option>
              <option>AL</option><option>AK</option><option>AZ</option><option>AR</option><option>CA</option>
              <option>CO</option><option>CT</option><option>DE</option><option>FL</option><option>GA</option>
              <option>IL</option><option>IN</option><option>MD</option><option>MA</option><option>MI</option>
              <option>MN</option><option>NJ</option><option>NY</option><option>NC</option><option>OH</option>
              <option>PA</option><option>TX</option><option>VA</option><option>WA</option><option>Other</option>
            </select>
          </div>
          <div class="field-group">
            <label for="zip">ZIP <span class="req">*</span></label>
            <input class="field" id="zip" name="zip" required>
          </div>
        </div>

        <div class="section-label">What are you craving?</div>
        <div class="field-group" style="margin-top:8px;">
          <label style="margin-bottom:0;">Select all that interest you <span class="hint">You can change this anytime</span></label>
          <div class="chipselect" id="cuisineChips">
            <label class="chip-toggle"><input type="checkbox" name="cuisine[]" value="Nigerian">Nigerian</label>
            <label class="chip-toggle"><input type="checkbox" name="cuisine[]" value="Ghanaian">Ghanaian</label>
            <label class="chip-toggle"><input type="checkbox" name="cuisine[]" value="Jamaican">Jamaican</label>
            <label class="chip-toggle"><input type="checkbox" name="cuisine[]" value="Trinidadian">Trinidadian</label>
            <label class="chip-toggle"><input type="checkbox" name="cuisine[]" value="Ethiopian">Ethiopian / Eritrean</label>
            <label class="chip-toggle"><input type="checkbox" name="cuisine[]" value="Senegalese">Senegalese</label>
            <label class="chip-toggle"><input type="checkbox" name="cuisine[]" value="Ivorian">Ivorian</label>
            <label class="chip-toggle"><input type="checkbox" name="cuisine[]" value="Other">Still exploring</label>
          </div>
        </div>

        <div class="section-label">Before you go</div>
        <div class="checkrow">
          <input type="checkbox" id="terms" name="terms" value="1" required>
          <p>I agree to Sac Frozen's <a href="<?= e(app_url('sacfrozen/user-terms')) ?>">Terms of Service</a> and <a href="<?= e(app_url('privacy-policy')) ?>">Privacy Policy</a>, and understand vendors ship directly to me. <span class="req">*</span></p>
        </div>
        <div class="checkrow">
          <input type="checkbox" id="marketing" name="marketing" value="1">
          <p>Email me about new vendors and dishes near me. I can unsubscribe anytime.</p>
        </div>

        <div class="submit-row">
          <button type="submit" class="btn btn-gold">Create my account</button>
          <p class="form-note">Already have an account? <a href="<?= e(app_url('login')) ?>">Sign in</a></p>
        </div>
      </form>
    </div>

    <div class="success-panel<?= $frozenSubmitted ? ' show' : '' ?>" id="successPanel">
      <svg class="mark-lg" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <path d="M16 2 L28 9 V23 L16 30 L4 23 V9 Z" stroke="#C9922A" stroke-width="1.4"/>
        <path d="M16 9 L16 23 M9.5 12.5 L22.5 19.5 M22.5 12.5 L9.5 19.5" stroke="#FBF8F1" stroke-width="1.4"/>
      </svg>
      <h2 id="successName">You're in.</h2>
      <p>Your account is ready. Start browsing vendors shipping to your area.</p>
      <a href="<?= e(app_url('sacfrozen')) ?>" class="btn btn-primary">Browse Sac Frozen</a>
    </div>
  </div>
</main>

<footer>
  <p>© 2026 Seller Africa Inc. · Sac Frozen — from their hands to your line.</p>
</footer>

<script>
  // cuisine chip toggles
  document.querySelectorAll('#cuisineChips .chip-toggle').forEach(chip => {
    chip.addEventListener('change', () => {
      const input = chip.querySelector('input');
      chip.classList.toggle('checked', input.checked);
    });
  });

  // password show/hide + strength hint
  const pwdInput = document.getElementById('pwd');
  const pwdToggle = document.getElementById('pwdToggle');
  const pwdHint = document.getElementById('pwdHint');
  pwdToggle.addEventListener('click', () => {
    const showing = pwdInput.type === 'text';
    pwdInput.type = showing ? 'password' : 'text';
    pwdToggle.textContent = showing ? 'Show' : 'Hide';
  });
  pwdInput.addEventListener('input', () => {
    const len = pwdInput.value.length;
    if (len === 0) { pwdHint.textContent = ''; pwdHint.classList.remove('ok'); return; }
    if (len < 8) { pwdHint.textContent = `${8 - len} more character${8 - len === 1 ? '' : 's'} needed`; pwdHint.classList.remove('ok'); }
    else { pwdHint.textContent = 'Looks good'; pwdHint.classList.add('ok'); }
  });

  // submit
  document.getElementById('buyerForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const required = this.querySelectorAll('[required]');
    let ok = true;
    required.forEach(f => {
      if (f.type === 'checkbox' && !f.checked) ok = false;
      else if (f.type !== 'checkbox' && !f.value) ok = false;
    });
    if (pwdInput.value.length < 8) ok = false;
    if (!ok) {
      alert('Please fill in all required fields and use a password of at least 8 characters.');
      return;
    }
    this.submit();
  });
</script>

</body>
</html>
