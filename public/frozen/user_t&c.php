<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/core/bootstrap.php';

$brand = app_branding();
$brandName = (string)($brand['name'] ?? 'Seller Africa');
$brandLogo = (string)($brand['logo'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Terms &amp; Conditions — Sac Frozen</title>
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
    line-height:1.6;
    -webkit-font-smoothing:antialiased;
  }
  a{color:inherit;}
  .display{font-family:'Cormorant Garamond', 'Georgia', serif;}
  :focus-visible{outline:2px solid var(--gold); outline-offset:3px;}

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
    color:var(--green-deep); text-decoration:none;
  }
  .logo .mark{width:30px; height:30px; flex:none;}
  .logo img{width:auto; max-width:172px; max-height:54px; object-fit:contain; display:block;}
  .backlink{
    font-size:13px; font-weight:600; color:var(--ink-soft); text-decoration:none;
  }
  .backlink:hover{color:var(--green-deep);}

  /* ---------- Disclaimer banner ---------- */
  .legal-banner{
    background:#3A2A18; color:#F0E3C9; padding:14px 0; font-size:13px;
  }
  .legal-banner .wrap{
    max-width:1180px; margin:0 auto; padding:0 32px; display:flex; gap:10px; align-items:flex-start;
  }
  @media (max-width:640px){ .legal-banner .wrap{padding:0 20px;} }
  .legal-banner svg{flex:none; margin-top:2px;}

  /* ---------- Intro ---------- */
  .intro{padding:56px 0 8px;}
  .intro .wrap{max-width:1180px; margin:0 auto; padding:0 32px;}
  @media (max-width:640px){ .intro .wrap{padding:0 20px;} }
  .eyebrow{
    display:inline-flex; align-items:center; gap:8px;
    font-size:12px; font-weight:600; letter-spacing:.14em; text-transform:uppercase;
    color:var(--gold); margin-bottom:18px;
  }
  .eyebrow::before{content:''; width:24px; height:1px; background:var(--gold-light);}
  h1{
    font-family:'Cormorant Garamond', serif; font-weight:600;
    font-size:clamp(32px, 4.6vw, 48px); line-height:1.08; color:var(--green-deep); max-width:22ch;
  }
  .meta-row{
    display:flex; gap:22px; flex-wrap:wrap; margin-top:20px; font-size:13px; color:var(--ink-soft);
  }
  .meta-row strong{color:var(--ink); font-weight:600;}
  .intro-sub{margin-top:18px; max-width:62ch; font-size:15.5px; color:var(--ink-soft);}

  /* ---------- Layout ---------- */
  .layout{
    max-width:1180px; margin:0 auto; padding:56px 32px 100px;
    display:grid; grid-template-columns:260px 1fr; gap:60px; align-items:flex-start;
  }
  @media (max-width:640px){ .layout{padding:40px 20px 80px;} }
  @media (max-width:900px){ .layout{grid-template-columns:1fr; gap:36px;} }

  /* ---------- TOC ---------- */
  .toc{position:sticky; top:110px; border-left:1px solid var(--line); padding-left:22px;}
  @media (max-width:900px){ .toc{position:static; border-left:none; padding-left:0; border:1px solid var(--line); padding:22px; background:var(--white);} }
  .toc-title{
    font-size:11px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--gold);
    margin-bottom:14px;
  }
  .toc a{
    display:block; font-size:13.5px; color:var(--ink-soft); text-decoration:none; padding:6px 0;
    border-left:2px solid transparent; padding-left:12px; margin-left:-14px; transition:all .15s ease;
  }
  .toc a:hover, .toc a.active{color:var(--green-deep); border-left-color:var(--gold-light); font-weight:600;}

  /* ---------- Document body ---------- */
  .doc section{
    padding-top:8px; margin-bottom:44px; scroll-margin-top:110px;
  }
  .doc h2{
    font-family:'Cormorant Garamond', serif; font-weight:600; font-size:26px; color:var(--green-deep);
    display:flex; align-items:baseline; gap:12px; padding-bottom:14px; margin-bottom:16px;
    border-bottom:1px solid var(--line);
  }
  .doc h2 .num{color:var(--gold-light); font-size:20px;}
  .doc h3{
    font-size:15px; font-weight:700; color:var(--ink); margin:22px 0 8px;
  }
  .doc p{font-size:15px; color:var(--ink); margin-top:12px; max-width:70ch;}
  .doc p.soft{color:var(--ink-soft);}
  .doc ul, .doc ol{margin-top:12px; padding-left:22px; max-width:66ch;}
  .doc li{font-size:15px; color:var(--ink); margin-top:8px;}
  .doc table{width:100%; border-collapse:collapse; margin-top:16px; font-size:14px;}
  .doc th, .doc td{text-align:left; padding:10px 14px; border:1px solid var(--line);}
  .doc th{background:var(--ivory-dim); font-weight:700; color:var(--green-deep);}

  .clausebox{
    background:var(--white); border:1px solid var(--line); border-left:3px solid var(--gold-light);
    padding:18px 22px; margin-top:16px;
  }
  .clausebox p{margin-top:0;}
  .clausebox p + p{margin-top:10px;}

  .contact-card{
    background:var(--green-deep); color:var(--ivory); padding:36px; margin-top:8px;
  }
  .contact-card h2{color:var(--white); border-color:rgba(255,255,255,.18);}
  .contact-card p{color:#C9D6CC;}
  .contact-card a{color:var(--gold-light); font-weight:600; text-decoration:underline;}

  footer{padding:36px 0 50px; border-top:1px solid var(--line); text-align:center;}
  footer p{font-size:12.5px; color:var(--ink-soft);}
</style>
</head>
<body>

<header>
  <div class="nav">
    <a href="<?= e(app_url('sacfrozen')) ?>" class="logo">
      <?php if ($brandLogo !== ''): ?>
        <img src="<?= e($brandLogo) ?>" alt="<?= e($brandName) ?>">
      <?php else: ?>
        <?= e($brandName) ?>
      <?php endif; ?>
    </a>
    <a href="<?= e(app_url('sacfrozen/buyer')) ?>" class="backlink">Back to registration</a>
  </div>
</header>

<div class="legal-banner">
  <div class="wrap">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" stroke="#F0E3C9" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>This is a working draft prepared for internal review. It has not been finalized by counsel and should not be published or relied upon as a binding agreement until reviewed by a licensed attorney familiar with e-commerce and consumer protection law.</span>
  </div>
</div>

<div class="intro">
  <div class="wrap">
    <div class="eyebrow">Legal</div>
    <h1>User Terms &amp; Conditions</h1>
    <div class="meta-row">
      <span><strong>Effective date:</strong> Upon publication</span>
      <span><strong>Last updated:</strong> August 18, 2026</span>
      <span><strong>Applies to:</strong> All buyers using Sac Frozen</span>
    </div>
    <p class="intro-sub">These Terms and Conditions govern your use of Sac Frozen, a platform operated by Seller Africa Inc. that connects you directly with independent African and Caribbean food vendors. By creating an account, browsing listings, or placing an order, you agree to the terms below.</p>
  </div>
</div>

<div class="layout">
  <nav class="toc" id="toc">
    <div class="toc-title">On this page</div>
    <a href="#acceptance">1. Acceptance of terms</a>
    <a href="#role">2. Nature of the platform</a>
    <a href="#eligibility">3. Eligibility &amp; your account</a>
    <a href="#orders">4. Orders &amp; payment</a>
    <a href="#shipping">5. Shipping &amp; delivery</a>
    <a href="#risk">6. Assumption of risk</a>
    <a href="#cancellations">7. Cancellations &amp; refunds</a>
    <a href="#conduct">8. Prohibited conduct</a>
    <a href="#reviews">9. Reviews &amp; content</a>
    <a href="#privacy">10. Privacy</a>
    <a href="#ip">11. Intellectual property</a>
    <a href="#warranties">12. Disclaimer of warranties</a>
    <a href="#liability">13. Limitation of liability</a>
    <a href="#indemnification">14. Indemnification</a>
    <a href="#termination">15. Suspending your account</a>
    <a href="#disputes">16. Governing law &amp; disputes</a>
    <a href="#changes">17. Changes to these terms</a>
    <a href="#contact">18. Contact</a>
  </nav>

  <div class="doc">

    <section id="acceptance">
      <h2><span class="num">01</span>Acceptance of Terms</h2>
      <p>By creating an account, browsing vendor listings, or placing an order on Sac Frozen, you agree to be bound by these Terms and Conditions, along with any policies referenced within them. If you do not agree, do not create an account or use the platform.</p>
      <p>These terms apply in addition to the Sac Frozen Privacy Policy. Vendors on the platform are separately bound by the Vendor Terms and Conditions, which govern their obligations to you.</p>
    </section>

    <section id="role">
      <h2><span class="num">02</span>Nature of the Platform</h2>
      <div class="clausebox">
        <p>Sac Frozen is a discovery and connection platform that enables independent, third-party food vendors to list products for sale directly to buyers. Sac Frozen is not a manufacturer, processor, packer, distributor, carrier, or seller of any product listed on the platform.</p>
        <p>Vendors are solely responsible for the preparation, packaging, labeling, shipment, and delivery of every product you order. Sac Frozen's role is limited to facilitating discovery, processing payment where applicable, and providing account and support tools.</p>
      </div>
      <p class="soft">This distinction matters. When you place an order, you are entering into a transaction with the vendor, not with Sac Frozen. Sac Frozen does not prepare, store, or ship any product you receive.</p>
    </section>

    <section id="eligibility">
      <h2><span class="num">03</span>Eligibility &amp; Your Account</h2>
      <p>You must be at least 18 years old and able to receive deliveries at a valid U.S. shipping address to create an account on Sac Frozen.</p>
      <p>You are responsible for maintaining the confidentiality of your account credentials and for all activity that occurs under your account. Notify us immediately if you believe your account has been accessed without your permission.</p>
      <p>You agree to provide accurate, current information when registering, including your name, delivery address, and contact details, and to keep that information up to date.</p>
    </section>

    <section id="orders">
      <h2><span class="num">04</span>Orders &amp; Payment</h2>
      <p>When you place an order, you are purchasing directly from the vendor whose listing you selected. Prices, availability, and ship times are set by each vendor and displayed on their listing.</p>
      <p>Payment is collected through Sac Frozen at checkout and, where applicable, routed to the vendor. You authorize Sac Frozen to charge your selected payment method for the full order total, including any applicable shipping charges shown at checkout.</p>
      <p class="soft">You are responsible for any sales tax applied to your order, calculated and collected at checkout in accordance with applicable law.</p>
    </section>

    <section id="shipping">
      <h2><span class="num">05</span>Shipping &amp; Delivery</h2>
      <div class="clausebox">
        <p>Every order ships directly from the vendor who prepared it. Sac Frozen does not store, repack, or handle any product between the vendor's kitchen and your address. Ship times, packaging method, and carrier are set and controlled by the vendor.</p>
      </div>
      <p>Estimated delivery windows shown at checkout are provided by the vendor and are estimates, not guarantees. Delays caused by carriers, weather, or other circumstances outside a vendor's control are not the responsibility of Sac Frozen.</p>
    </section>

    <section id="risk">
      <h2><span class="num">06</span>Assumption of Risk</h2>
      <div class="clausebox">
        <p>You acknowledge that all products available on Sac Frozen are prepared, packaged, and shipped by independent third-party vendors, and that Sac Frozen makes no representation or warranty regarding the safety, quality, temperature integrity, or condition of any product upon delivery.</p>
        <p>If you experience an issue with spoilage, temperature failure, or product quality, you should contact the vendor directly and, where applicable, report the issue to Sac Frozen so it can be reviewed against the vendor's standing on the platform.</p>
      </div>
      <p class="soft">If you have a food allergy or dietary restriction, review each listing's ingredient and allergen disclosure carefully before ordering. Sac Frozen does not independently verify vendor-submitted ingredient information.</p>
    </section>

    <section id="cancellations">
      <h2><span class="num">07</span>Cancellations &amp; Refunds</h2>
      <p>Because each vendor ships independently and often prepares food to order, cancellation windows and refund eligibility are set by the individual vendor and displayed on their listing or storefront policy at the time of purchase.</p>
      <p>If a product arrives damaged, spoiled, or significantly different from its listing, contact the vendor within 48 hours of delivery to request a resolution. If the vendor does not respond within a reasonable time, you may escalate the issue to Sac Frozen for review.</p>
      <p class="soft">Sac Frozen may, at its discretion, facilitate a refund or credit in cases of vendor non-response, but is not obligated to do so and is not a guarantor of any vendor's products.</p>
    </section>

    <section id="conduct">
      <h2><span class="num">08</span>Prohibited Conduct</h2>
      <p>When using Sac Frozen, you may not:</p>
      <ul>
        <li>Provide false or misleading account, payment, or delivery information.</li>
        <li>Use another person's account or payment method without authorization.</li>
        <li>Harass, threaten, or discriminate against a vendor or another user.</li>
        <li>Attempt to circumvent the platform to transact with a vendor off-platform in a way that violates a vendor's listing terms.</li>
        <li>Submit a false or bad-faith complaint against a vendor.</li>
      </ul>
      <p>Violation of this section may result in suspension or termination of your account under Section 15.</p>
    </section>

    <section id="reviews">
      <h2><span class="num">09</span>Reviews &amp; Content</h2>
      <p>If Sac Frozen enables reviews or ratings, you agree that any review you submit will be honest, based on an actual order, and free of harassment, hate speech, or content unrelated to the product or vendor experience. Sac Frozen may remove reviews that violate this standard.</p>
      <p>By submitting a review or other content, you grant Sac Frozen a non-exclusive, royalty-free license to display and use that content on the platform.</p>
    </section>

    <section id="privacy">
      <h2><span class="num">10</span>Privacy</h2>
      <p>Your use of Sac Frozen is also governed by the Sac Frozen Privacy Policy, which describes how your personal information, including your delivery address and order history, is collected, used, and shared with vendors to fulfill your orders.</p>
    </section>

    <section id="ip">
      <h2><span class="num">11</span>Intellectual Property</h2>
      <p>The Sac Frozen name, logo, and platform design are the property of Seller Africa Inc. You may not copy, reproduce, or use these assets outside the platform without written permission.</p>
    </section>

    <section id="warranties">
      <h2><span class="num">12</span>Disclaimer of Warranties</h2>
      <p>The platform is provided on an as-is and as-available basis. Sac Frozen makes no warranty, express or implied, regarding the platform's uninterrupted availability, the accuracy of vendor listings, or the quality, safety, or timeliness of any product you order.</p>
    </section>

    <section id="liability">
      <h2><span class="num">13</span>Limitation of Liability</h2>
      <p>To the fullest extent permitted by law, Sac Frozen and Seller Africa Inc. will not be liable for any indirect, incidental, special, or consequential damages arising from your use of the platform or from any product ordered through it. Sac Frozen's total liability to you for any claim arising under these terms will not exceed the amount you paid for the order giving rise to the claim.</p>
    </section>

    <section id="indemnification">
      <h2><span class="num">14</span>Indemnification</h2>
      <p>You agree to indemnify and hold harmless Sac Frozen and Seller Africa Inc., along with their officers, employees, and affiliates, from and against any claim, loss, liability, or expense, including reasonable attorney's fees, arising from your violation of these terms or misuse of the platform.</p>
    </section>

    <section id="termination">
      <h2><span class="num">15</span>Suspending Your Account</h2>
      <p>Sac Frozen may suspend or terminate your account at any time, with or without notice, for violation of these terms or conduct that Sac Frozen determines in good faith poses a risk to vendors, other users, or the platform. You may close your account at any time by contacting Sac Frozen support.</p>
      <p>Sections 6, 12, 13, and 14 survive termination of your account.</p>
    </section>

    <section id="disputes">
      <h2><span class="num">16</span>Governing Law &amp; Disputes</h2>
      <p>These terms are governed by the laws of the State of Florida, without regard to conflict of law principles. Any dispute arising from these terms will be resolved in the state or federal courts located in Miami-Dade County, Florida, and you consent to that jurisdiction.</p>
    </section>

    <section id="changes">
      <h2><span class="num">17</span>Changes to These Terms</h2>
      <p>Sac Frozen may update these terms from time to time. Material changes will be communicated to you by email or in-app notice at least 30 days before taking effect. Continued use of the platform after changes take effect constitutes acceptance of the updated terms.</p>
    </section>

    <section id="contact" class="contact-card">
      <h2 style="border-bottom:1px solid rgba(255,255,255,.18);"><span class="num" style="color:var(--gold-light);">18</span>Contact</h2>
      <p>Questions about these terms or an order can be directed to Sac Frozen support.</p>
      <p style="margin-top:16px;"><strong style="color:var(--white);">Email:</strong> <a href="mailto:hello@sacfrozen.com">hello@sacfrozen.com</a></p>
      <p><strong style="color:var(--white);">Mail:</strong> Seller Africa Inc., Miami, Florida</p>
    </section>

  </div>
</div>

<footer>
  <p>© 2026 Seller Africa Inc. · Sac Frozen — from their hands to your line.</p>
</footer>

<script>
  const links = Array.from(document.querySelectorAll('.toc a'));
  const sections = links.map(l => document.querySelector(l.getAttribute('href')));

  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      const idx = sections.indexOf(entry.target);
      if (idx === -1) return;
      if (entry.isIntersecting) {
        links.forEach(l => l.classList.remove('active'));
        links[idx].classList.add('active');
      }
    });
  }, { rootMargin: '-20% 0px -70% 0px' });

  sections.forEach(s => { if (s) io.observe(s); });
</script>

</body>
</html>
