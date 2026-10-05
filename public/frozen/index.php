<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/core/bootstrap.php';

$brand = app_branding();
$brandName = (string)($brand['name'] ?? 'Seller Africa');
$frozen = new \App\FrozenMarketService(db());
$frozen->ensureSchema();
$frozenErrors = [];
$frozenSubmitted = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $submittedToken = (string)($_POST['csrf_token'] ?? '');
        $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
        if ($submittedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
            throw new \RuntimeException('Your form session expired. Please refresh the page and submit again.');
        }
        $frozen->joinWaitlist($_POST);
        $frozenSubmitted = true;
    } catch (\Throwable $e) {
        $frozenErrors[] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sac Frozen — From Their Kitchen. To Your Freezer.</title>
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

  /* ---------- Adinkra-inspired hairline motif ---------- */
  .motif-row{
    display:flex; align-items:center; gap:14px; opacity:.55;
  }
  .motif-row svg{width:16px; height:16px; flex:none;}

  /* ---------- Header ---------- */
  header{
    position:sticky; top:0; z-index:50;
    background:rgba(251,248,241,.92);
    backdrop-filter:blur(8px);
    border-bottom:1px solid var(--line);
  }
  .nav{
    display:flex; align-items:center; justify-content:space-between;
    padding:18px 0;
  }
  .logo{
    display:flex; align-items:center; gap:10px;
    font-family:'Cormorant Garamond', serif;
    font-weight:600; font-size:22px; letter-spacing:.02em;
    color:var(--green-deep);
  }
  .logo .mark{
    width:30px; height:30px;
  }
  .logo img{width:auto; max-width:172px; max-height:54px; object-fit:contain;}
  .navlinks{display:flex; align-items:center; gap:34px;}
  .navlinks a{
    font-size:14px; font-weight:500; color:var(--ink-soft);
    transition:color .18s ease;
  }
  .navlinks a:hover{color:var(--green-deep);}
  .navlinks{}
  @media (max-width:800px){ .navlinks .navlink{display:none;} }
  .btn{
    display:inline-flex; align-items:center; justify-content:center;
    padding:12px 22px; border-radius:2px;
    font-size:14px; font-weight:600; letter-spacing:.01em;
    cursor:pointer; border:1px solid transparent;
    transition:transform .16s ease, background .16s ease, border-color .16s ease, color .16s ease;
  }
  .btn:active{transform:translateY(1px);}
  .btn-primary{background:var(--green-deep); color:var(--white);}
  .btn-primary:hover{background:var(--green);}
  .btn-outline{border-color:var(--green-deep); color:var(--green-deep); background:transparent;}
  .btn-outline:hover{background:var(--green-deep); color:var(--white);}
  .btn-gold{background:var(--gold-light); color:var(--white);}
  .btn-gold:hover{background:var(--gold);}

  /* ---------- Hero ---------- */
  .hero{
    padding:88px 0 40px;
    position:relative;
    overflow:hidden;
  }
  .eyebrow{
    display:inline-flex; align-items:center; gap:8px;
    font-size:12px; font-weight:600; letter-spacing:.14em; text-transform:uppercase;
    color:var(--gold); margin-bottom:22px;
  }
  .eyebrow::before{content:''; width:24px; height:1px; background:var(--gold-light);}
  h1{
    font-family:'Cormorant Garamond', serif;
    font-weight:600;
    font-size:clamp(42px, 6vw, 76px);
    line-height:1.03;
    color:var(--green-deep);
    max-width:15ch;
    letter-spacing:-.01em;
  }
  h1 em{font-style:italic; color:var(--gold);}
  .hero-sub{
    margin-top:26px;
    max-width:52ch;
    font-size:18px;
    color:var(--ink-soft);
  }
  .hero-ctas{
    margin-top:38px;
    display:flex; gap:14px; flex-wrap:wrap;
  }
  .hero-note{
    margin-top:20px; font-size:13px; color:var(--ink-soft);
  }

  /* ---------- Route signature ---------- */
  .route-wrap{
    margin-top:76px;
    border:1px solid var(--line);
    background:var(--white);
    padding:36px 32px 28px;
    position:relative;
  }
  .route-wrap::before{
    content:'THE ROUTE'; position:absolute; top:-11px; left:32px;
    background:var(--ivory); padding:0 10px;
    font-size:11px; letter-spacing:.16em; font-weight:700; color:var(--gold);
  }
  .route-svg{width:100%; height:auto;}
  .route-line{
    stroke-dasharray:6 7;
    stroke-dashoffset:0;
    animation:march 1.6s linear infinite;
  }
  @keyframes march{ to{ stroke-dashoffset:-26; } }
  .route-caption{
    display:flex; justify-content:space-between; margin-top:14px;
    font-size:13px; color:var(--ink-soft);
  }
  .route-caption strong{color:var(--ink); font-weight:600;}
  .route-strike{
    position:relative; color:#A79E8B;
  }
  .route-strike::after{
    content:''; position:absolute; left:-4px; right:-4px; top:50%;
    height:1px; background:#A79E8B; transform:rotate(-3deg);
  }

  @media (prefers-reduced-motion: reduce){
    .route-line{ animation:none; }
    *{ scroll-behavior:auto !important; }
  }

  /* ---------- Section shell ---------- */
  section{padding:96px 0;}
  .section-head{max-width:60ch; margin-bottom:52px;}
  .kicker{
    font-size:12px; font-weight:700; letter-spacing:.14em; text-transform:uppercase;
    color:var(--gold); margin-bottom:14px;
  }
  h2{
    font-family:'Cormorant Garamond', serif;
    font-weight:600; font-size:clamp(30px,4vw,44px);
    color:var(--green-deep); line-height:1.08;
  }
  .section-body{margin-top:16px; color:var(--ink-soft); font-size:16.5px; max-width:56ch;}

  /* ---------- reveal ---------- */
  .reveal{opacity:0; transform:translateY(18px); transition:opacity .6s ease, transform .6s ease;}
  .reveal.in{opacity:1; transform:none;}

  /* ---------- Why / compare ---------- */
  .compare{background:var(--green-deep); color:var(--ivory);}
  .compare h2{color:var(--white);}
  .compare .section-body{color:#C9D6CC;}
  .compare-grid{
    margin-top:44px;
    display:grid; grid-template-columns:1fr 1fr; gap:1px;
    background:rgba(255,255,255,.14);
    border:1px solid rgba(255,255,255,.14);
  }
  @media (max-width:760px){ .compare-grid{grid-template-columns:1fr;} }
  .compare-col{background:var(--green-deep); padding:34px 30px;}
  .compare-col.gold{background:#1E4A38;}
  .compare-label{
    font-size:12px; letter-spacing:.12em; text-transform:uppercase; font-weight:700;
    color:var(--gold-light); margin-bottom:18px;
  }
  .compare-list li{
    list-style:none; padding:11px 0; border-top:1px solid rgba(255,255,255,.1);
    font-size:15px; color:#D8E0D3; display:flex; gap:10px;
  }
  .compare-list li:first-child{border-top:none;}
  .compare-list .x{color:#8A9A8E;}
  .compare-list .check{color:var(--gold-light);}

  /* ---------- How it works — tabs ---------- */
  .tabs{display:flex; gap:8px; margin-bottom:44px;}
  .tab{
    padding:10px 20px; border:1px solid var(--line); background:transparent;
    font-size:14px; font-weight:600; color:var(--ink-soft); cursor:pointer;
    border-radius:2px; transition:all .16s ease; font-family:inherit;
  }
  .tab[aria-selected="true"]{background:var(--green-deep); color:var(--white); border-color:var(--green-deep);}
  .steps{display:grid; grid-template-columns:repeat(3,1fr); gap:28px;}
  @media (max-width:800px){ .steps{grid-template-columns:1fr;} }
  .step{border-top:2px solid var(--green-deep); padding-top:20px;}
  .step .num{
    font-family:'Cormorant Garamond', serif; font-size:38px; color:var(--gold-light); font-weight:600;
  }
  .step h3{
    font-family:'Cormorant Garamond', serif; font-size:22px; font-weight:600; margin-top:6px; color:var(--green-deep);
  }
  .step p{margin-top:8px; color:var(--ink-soft); font-size:15px;}
  .panel{display:none;}
  .panel.active{display:block;}

  /* ---------- Trust strip ---------- */
  .trust{background:var(--ivory-dim); border-top:1px solid var(--line); border-bottom:1px solid var(--line);}
  .trust-grid{
    display:grid; grid-template-columns:repeat(4,1fr); gap:0;
  }
  @media (max-width:760px){ .trust-grid{grid-template-columns:1fr 1fr;} }
  .trust-item{
    padding:34px 26px; border-left:1px solid var(--line);
  }
  .trust-item:first-child{border-left:none;}
  @media (max-width:760px){
    .trust-item:nth-child(odd){border-left:none;}
    .trust-item{border-top:1px solid var(--line);}
    .trust-item:nth-child(-n+2){border-top:none;}
  }
  .trust-item .t-num{font-family:'Cormorant Garamond', serif; font-size:15px; color:var(--gold); font-weight:600; margin-bottom:8px;}
  .trust-item p{font-size:14.5px; color:var(--ink-soft);}

  /* ---------- Cuisine chip strip ---------- */
  .chips{
    display:flex; flex-wrap:wrap; gap:10px; margin-top:36px;
  }
  .chip{
    padding:9px 18px; border:1px solid var(--line); border-radius:999px;
    font-size:14px; color:var(--ink); background:var(--white);
  }

  /* ---------- Waitlist ---------- */
  .join{
    background:var(--green-deep);
    position:relative;
    overflow:hidden;
  }
  .join-inner{
    display:grid; grid-template-columns:1.1fr .9fr; gap:60px; align-items:center;
  }
  @media (max-width:860px){ .join-inner{grid-template-columns:1fr;} }
  .join h2{color:var(--white); max-width:14ch;}
  .join .section-body{color:#C9D6CC; max-width:44ch;}
  .join-card{
    background:var(--ivory); padding:34px; border:1px solid rgba(255,255,255,.1);
  }
  .join-card .kicker{color:var(--gold);}
  .seg{
    display:flex; border:1px solid var(--line); margin-bottom:20px; border-radius:2px; overflow:hidden;
  }
  .seg button{
    flex:1; padding:10px; font-size:13px; font-weight:600; border:none; background:var(--white);
    color:var(--ink-soft); cursor:pointer; font-family:inherit;
  }
  .seg button.active{background:var(--green-deep); color:var(--white);}
  .field{
    width:100%; padding:13px 14px; border:1px solid var(--line); font-size:14px;
    font-family:inherit; margin-bottom:12px; background:var(--white); color:var(--ink);
  }
  .field:focus-visible{outline:2px solid var(--gold); outline-offset:1px;}
  .join-card .btn{width:100%;}
  .form-note{font-size:12.5px; color:var(--ink-soft); margin-top:12px;}
  .success{
    display:none; text-align:center; padding:30px 6px;
  }
  .success.show{display:block;}
  .success .display{font-size:24px; color:var(--green-deep); margin-bottom:6px;}

  /* ---------- Footer ---------- */
  footer{padding:64px 0 40px; border-top:1px solid var(--line);}
  .foot-top{
    display:flex; justify-content:space-between; gap:40px; flex-wrap:wrap;
    padding-bottom:44px;
  }
  .foot-brand .logo{margin-bottom:14px;}
  .foot-brand p{max-width:34ch; font-size:14px; color:var(--ink-soft);}
  .foot-cols{display:flex; gap:64px; flex-wrap:wrap;}
  .foot-col h4{
    font-size:12px; letter-spacing:.1em; text-transform:uppercase; color:var(--gold);
    margin-bottom:14px; font-weight:700;
  }
  .foot-col a, .foot-col span{
    display:block; font-size:14px; color:var(--ink-soft); margin-bottom:10px;
  }
  .foot-col a:hover{color:var(--green-deep);}
  .foot-bottom{
    display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;
    padding-top:26px; border-top:1px solid var(--line);
    font-size:12.5px; color:var(--ink-soft);
  }
</style>
</head>
<body>

<header>
  <div class="wrap nav">
    <a class="logo" href="<?= e(app_url('sacfrozen')) ?>"><?= \App\FrozenMarketService::logoHtml('', $brandName) ?></a>
    <nav class="navlinks">
      <a class="navlink" href="#how">How it works</a>
      <a class="navlink" href="#vendors">For vendors</a>
      <a class="navlink" href="#buyers">For buyers</a>
      <a href="<?= e(app_url('sacfrozen/waitlist')) ?>" class="btn btn-primary">Early bird</a>
    </nav>
  </div>
</header>

<main>
  <!-- HERO -->
  <section class="hero">
    <div class="wrap">
      <div class="eyebrow">A Seller Africa company</div>
      <h1>From their kitchen.<br>To your <em>freezer.</em></h1>
      <p class="hero-sub">Sac Frozen connects independent African and Caribbean food vendors directly with diaspora buyers across the U.S. Every vendor ships to you themselves, so what arrives is exactly what left their kitchen.</p>
      <div class="hero-ctas">
        <a href="<?= e(app_url('sacfrozen/waitlist')) ?>" class="btn btn-gold">Join early bird</a>
        <a href="<?= e(app_url('sacfrozen/buyer')) ?>" class="btn btn-outline">I'm a buyer</a>
      </div>
      <p class="hero-note">No warehouse. No repack. No platform ever touches your food.</p>

      <div class="route-wrap reveal">
        <svg class="route-svg" viewBox="0 0 1000 140" role="img" aria-label="Diagram showing a vendor's kitchen shipping directly to a buyer's home, with a crossed-out warehouse showing the platform never touches the food">
          <line x1="90" y1="70" x2="910" y2="70" stroke="#DCD3BC" stroke-width="1"/>
          <line class="route-line" x1="90" y1="70" x2="910" y2="70" stroke="#C9922A" stroke-width="2"/>
          <!-- kitchen -->
          <circle cx="90" cy="70" r="7" fill="#1B4332"/>
          <!-- warehouse (struck through) -->
          <g transform="translate(500,70)">
            <rect x="-26" y="-16" width="52" height="32" fill="none" stroke="#A79E8B" stroke-width="1.3"/>
            <path d="M-26 -16 L0 -30 L26 -16" fill="none" stroke="#A79E8B" stroke-width="1.3"/>
            <line x1="-30" y1="18" x2="30" y2="-34" stroke="#A79E8B" stroke-width="1.6"/>
          </g>
          <!-- home -->
          <circle cx="910" cy="70" r="7" fill="#946F1D"/>
        </svg>
        <div class="route-caption">
          <span><strong>Vendor's kitchen</strong><br>packs &amp; ships</span>
          <span class="route-strike">Warehouse<br>skipped</span>
          <span style="text-align:right"><strong>Your freezer</strong><br>arrives direct</span>
        </div>
      </div>
    </div>
  </section>

  <!-- COMPARE -->
  <section class="compare">
    <div class="wrap">
      <div class="section-head reveal">
        <div class="kicker">Why direct-ship</div>
        <h2>A platform that connects.<br>Not one that handles.</h2>
        <p class="section-body">Consolidators add a warehouse stop between vendor and buyer. Sac Frozen doesn't. Vendors pack and ship themselves, on their own timeline, in their own packaging, at their own trusted rate.</p>
      </div>
      <div class="compare-grid reveal">
        <div class="compare-col">
          <div class="compare-label">The old way</div>
          <ul class="compare-list">
            <li><span class="x">＋</span> Product sits in a third-party warehouse</li>
            <li><span class="x">＋</span> Repacked by someone who didn't make it</li>
            <li><span class="x">＋</span> Slower, with an added markup</li>
            <li><span class="x">＋</span> One missed pickup window delays everyone</li>
          </ul>
        </div>
        <div class="compare-col gold">
          <div class="compare-label">Sac Frozen</div>
          <ul class="compare-list">
            <li><span class="check">＋</span> Vendor ships from their own kitchen</li>
            <li><span class="check">＋</span> Sealed once, by the person who made it</li>
            <li><span class="check">＋</span> One flat connection fee, no freight markup</li>
            <li><span class="check">＋</span> Every vendor sets and owns their ship time</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- HOW IT WORKS -->
  <section id="how">
    <div class="wrap">
      <div class="section-head reveal">
        <div class="kicker">How it works</div>
        <h2>Three steps, either side of the screen.</h2>
      </div>

      <div class="tabs reveal" role="tablist" aria-label="View by role">
        <button class="tab" role="tab" aria-selected="true" data-panel="panel-buyer">For buyers</button>
        <button class="tab" role="tab" aria-selected="false" data-panel="panel-vendor">For vendors</button>
      </div>

      <div id="panel-buyer" class="panel active reveal">
        <div class="steps">
          <div class="step">
            <div class="num">01</div>
            <h3>Find your kitchen</h3>
            <p>Browse vendors by cuisine, country of origin, or dish. Every listing shows real ship times, set by the vendor.</p>
          </div>
          <div class="step">
            <div class="num">02</div>
            <h3>Order direct</h3>
            <p>Pay through Sac Frozen. Your order goes straight to the vendor who's making and packing it, not a fulfillment center.</p>
          </div>
          <div class="step">
            <div class="num">03</div>
            <h3>It ships from their kitchen</h3>
            <p>The vendor packs and ships to you themselves, using their own cold-chain method, so it arrives the way they intended.</p>
          </div>
        </div>
      </div>

      <div id="panel-vendor" class="panel reveal">
        <div class="steps">
          <div class="step">
            <div class="num">01</div>
            <h3>Apply &amp; verify</h3>
            <p>Submit your food safety registration and insurance once. Verified vendors go live within days, not months.</p>
          </div>
          <div class="step">
            <div class="num">02</div>
            <h3>List on your terms</h3>
            <p>Set your prices, your ship windows, your carrier. Sac Frozen never touches your product or your process.</p>
          </div>
          <div class="step">
            <div class="num">03</div>
            <h3>Ship &amp; get paid</h3>
            <p>Pack and ship orders yourself. Payment settles directly to your account, order by order.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- TRUST -->
  <section class="trust" style="padding:0;">
    <div class="wrap trust-grid">
      <div class="trust-item reveal">
        <div class="t-num">FDA</div>
        <p>Every vendor holds current food facility registration before their first listing goes live.</p>
      </div>
      <div class="trust-item reveal">
        <div class="t-num">Insured</div>
        <p>Vendors carry their own product liability coverage, verified annually.</p>
      </div>
      <div class="trust-item reveal">
        <div class="t-num">Direct</div>
        <p>Sac Frozen never stores, repacks, or ships your order. Only the vendor does.</p>
      </div>
      <div class="trust-item reveal">
        <div class="t-num">Transparent</div>
        <p>Ship times and cold-chain method are posted on every listing, set by the vendor.</p>
      </div>
    </div>
  </section>

  <!-- CUISINES -->
  <section id="vendors">
    <div class="wrap">
      <div class="section-head reveal">
        <div class="kicker">What's on the platform</div>
        <h2>Every corner of home,<br>frozen at its best.</h2>
        <p class="section-body">From a Lagos kitchen to a Kingston one, vendors list the dishes they already make well. If it ships frozen, it belongs here.</p>
      </div>
      <div class="chips reveal">
        <span class="chip">Jollof rice</span>
        <span class="chip">Egusi soup</span>
        <span class="chip">Suya spice packs</span>
        <span class="chip">Oxtail stew</span>
        <span class="chip">Jamaican patties</span>
        <span class="chip">Doubles</span>
        <span class="chip">Efo riro</span>
        <span class="chip">Fufu &amp; swallow</span>
        <span class="chip">Callaloo</span>
        <span class="chip">Moin moin</span>
        <span class="chip">Curry goat</span>
        <span class="chip">Attiéké</span>
      </div>
      <div class="hero-ctas" style="margin-top:40px;">
        <a href="<?= e(app_url('sacfrozen/waitlist')) ?>" class="btn btn-gold">Join early bird waitlist</a>
      </div>
    </div>
  </section>

  <!-- JOIN -->
  <section class="join" id="join" style="padding-bottom:96px;">
    <div class="wrap">
      <div class="join-inner">
        <div class="reveal">
          <div class="kicker" style="color:#E8C97C;">Get early access</div>
          <h2>Be first through the door.</h2>
          <p class="section-body">Sac Frozen is opening to vendors and buyers in waves, city by city. Join the list and we'll reach out when it's your turn.</p>
        </div>
        <div class="join-card reveal" id="buyers">
          <div class="seg" role="tablist" aria-label="I am a">
            <button class="active" id="seg-buyer" aria-selected="true" role="tab">I'm a buyer</button>
            <button id="seg-vendor" aria-selected="false" role="tab">I'm a vendor</button>
          </div>
          <?php if ($frozenErrors !== []): ?>
            <?php foreach ($frozenErrors as $message): ?><p style="color:var(--red, #963B2E); margin-bottom:12px;"><?= e($message) ?></p><?php endforeach; ?>
          <?php endif; ?>
          <form id="joinForm" method="post" action="<?= e(app_url('sacfrozen')) ?>" novalidate<?= $frozenSubmitted ? ' style="display:none;"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <input type="hidden" name="audience" id="audienceInput" value="buyer">
            <input class="field" type="text" id="fname" name="name" placeholder="Full name" required autocomplete="name">
            <input class="field" type="email" id="femail" name="email" placeholder="Email address" required autocomplete="email">
            <input class="field" type="text" id="fcity" name="city" placeholder="City, state" required>
            <button type="submit" class="btn btn-primary">Join the list</button>
            <p class="form-note">We'll only email you about Sac Frozen. No spam, ever.</p>
          </form>
          <div class="success<?= $frozenSubmitted ? ' show' : '' ?>" id="successMsg">
            <p class="display">You're on the list.</p>
            <p style="color:var(--ink-soft); font-size:14px;">We'll be in touch as Sac Frozen opens near you.</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<footer>
  <div class="wrap">
    <div class="foot-top">
      <div class="foot-brand">
        <a class="logo" href="<?= e(app_url('sacfrozen')) ?>"><?= \App\FrozenMarketService::logoHtml('', $brandName) ?></a>
        <p>A connection platform for African and Caribbean food vendors and the diaspora buyers who miss home. Part of the Seller Africa family of companies.</p>
      </div>
      <div class="foot-cols">
        <div class="foot-col">
          <h4>Platform</h4>
          <a href="#how">How it works</a>
          <a href="#vendors">For vendors</a>
          <a href="#buyers">For buyers</a>
          <a href="#join">Join the list</a>
        </div>
        <div class="foot-col">
          <h4>Seller Africa</h4>
          <span>FreshRoots</span>
        </div>
        <div class="foot-col">
          <h4>Contact</h4>
          <a href="mailto:hello@sacfrozen.com">hello@sacfrozen.com</a>
          <span>Miami, Florida</span>
        </div>
      </div>
    </div>
    <div class="motif-row" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none"><path d="M12 2 L22 12 L12 22 L2 12 Z" stroke="#C9922A"/></svg>
      <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#1B4332"/></svg>
      <svg viewBox="0 0 24 24" fill="none"><path d="M12 2 L22 12 L12 22 L2 12 Z" stroke="#C9922A"/></svg>
    </div>
    <div class="foot-bottom">
      <span>© 2026 Seller Africa Inc. All rights reserved.</span>
      <span>Sac Frozen · From their hands to your line.</span>
    </div>
  </div>
</footer>

<script>
  // reveal on scroll
  const io = new IntersectionObserver((entries)=>{
    entries.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target); } });
  }, {threshold:.12});
  document.querySelectorAll('.reveal').forEach(el=>io.observe(el));

  // how-it-works tabs
  document.querySelectorAll('.tabs .tab').forEach(tab=>{
    tab.addEventListener('click', ()=>{
      document.querySelectorAll('.tabs .tab').forEach(t=>t.setAttribute('aria-selected','false'));
      tab.setAttribute('aria-selected','true');
      document.querySelectorAll('.panel').forEach(p=>p.classList.remove('active'));
      document.getElementById(tab.dataset.panel).classList.add('active');
    });
  });

  // join segment toggle (cosmetic — informs the note text)
  const segBuyer = document.getElementById('seg-buyer');
  const segVendor = document.getElementById('seg-vendor');
  const formNote = document.querySelector('.form-note');
  const audienceInput = document.getElementById('audienceInput');
  segBuyer.addEventListener('click', ()=>{
    audienceInput.value = 'buyer';
    segBuyer.classList.add('active'); segBuyer.setAttribute('aria-selected','true');
    segVendor.classList.remove('active'); segVendor.setAttribute('aria-selected','false');
    formNote.textContent = "We'll only email you about Sac Frozen. No spam, ever.";
  });
  segVendor.addEventListener('click', ()=>{
    audienceInput.value = 'vendor';
    segVendor.classList.add('active'); segVendor.setAttribute('aria-selected','true');
    segBuyer.classList.remove('active'); segBuyer.setAttribute('aria-selected','false');
    formNote.textContent = "We'll follow up with vendor onboarding and document requirements.";
  });

  document.getElementById('joinForm').addEventListener('submit', function(e){
    e.preventDefault();
    const required = this.querySelectorAll('[required]');
    let ok = true;
    required.forEach(f => { if (!f.value.trim()) ok = false; });
    if (!ok) { alert('Please fill in all required fields.'); return; }
    this.submit();
  });
</script>

</body>
</html>
