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
<title>Vendor Terms &amp; Conditions — Sac Frozen</title>
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
    <a href="<?= e(app_url('sacfrozen/vendor')) ?>" class="backlink">Back to vendor application</a>
  </div>
</header>

<div class="legal-banner">
  <div class="wrap">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" stroke="#F0E3C9" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>This is a working draft prepared for internal review. It has not been finalized by counsel and should not be published or relied upon as a binding agreement until reviewed by a licensed attorney familiar with e-commerce, food safety, and marketplace law.</span>
  </div>
</div>

<div class="intro">
  <div class="wrap">
    <div class="eyebrow">Legal</div>
    <h1>Vendor Terms &amp; Conditions</h1>
    <div class="meta-row">
      <span><strong>Effective date:</strong> Upon publication</span>
      <span><strong>Last updated:</strong> August 17, 2026</span>
      <span><strong>Applies to:</strong> All vendors listing on Sac Frozen</span>
    </div>
    <p class="intro-sub">These Terms and Conditions govern your participation as a vendor on Sac Frozen, a platform operated by Seller Africa Inc. that connects independent food vendors with diaspora buyers. By applying to list on Sac Frozen, you agree to the terms below.</p>
  </div>
</div>

<div class="layout">
  <nav class="toc" id="toc">
    <div class="toc-title">On this page</div>
    <a href="#acceptance">1. Acceptance of terms</a>
    <a href="#role">2. Nature of the platform</a>
    <a href="#eligibility">3. Vendor eligibility</a>
    <a href="#responsibilities">4. Vendor responsibilities</a>
    <a href="#custody">5. No fulfillment or custody</a>
    <a href="#listings">6. Listings &amp; content</a>
    <a href="#fees">7. Fees, plans &amp; payment</a>
    <a href="#cancellation">8. Cancellation &amp; plan changes</a>
    <a href="#conduct">9. Prohibited conduct</a>
    <a href="#risk">10. Assumption of risk &amp; indemnification</a>
    <a href="#insurance">11. Insurance requirements</a>
    <a href="#ip">12. Intellectual property</a>
    <a href="#termination">13. Suspension &amp; termination</a>
    <a href="#warranties">14. Disclaimer of warranties</a>
    <a href="#liability">15. Limitation of liability</a>
    <a href="#disputes">16. Governing law &amp; disputes</a>
    <a href="#changes">17. Changes to these terms</a>
    <a href="#contact">18. Contact</a>
  </nav>

  <div class="doc">

    <section id="acceptance">
      <h2><span class="num">01</span>Acceptance of Terms</h2>
      <p>By submitting a vendor application, listing a product, or accepting payment through Sac Frozen, you agree to be bound by these Terms and Conditions, along with any policies referenced within them. If you do not agree, do not apply to list or continue using the platform.</p>
      <p>These terms apply in addition to the general Sac Frozen Terms of Service and Privacy Policy. Where a conflict exists between this document and the general Terms of Service, this Vendor Terms and Conditions document governs for matters specific to vendors.</p>
    </section>

    <section id="role">
      <h2><span class="num">02</span>Nature of the Platform</h2>
      <div class="clausebox">
        <p>Sac Frozen is a discovery and connection platform that enables independent, third-party food vendors to list products for sale directly to buyers. Sac Frozen is not a manufacturer, processor, packer, distributor, carrier, or seller of any product listed on the platform.</p>
        <p>Vendors are solely responsible for the preparation, packaging, labeling, shipment, and delivery of all products sold through the platform. Sac Frozen's role is limited to facilitating discovery, processing or routing payment where applicable, and providing tools for vendors to manage their storefront.</p>
      </div>
      <p class="soft">This distinction matters. Vendors operate as independent businesses. Nothing in these terms creates a partnership, joint venture, employment relationship, or agency relationship between a vendor and Sac Frozen or Seller Africa Inc.</p>
    </section>

    <section id="eligibility">
      <h2><span class="num">03</span>Vendor Eligibility &amp; Onboarding</h2>
      <p>To be approved and remain listed on Sac Frozen, a vendor must maintain current documentation on file at all times, including:</p>
      <ul>
        <li>Current FDA Food Facility Registration, for any vendor preparing, packing, or holding food for consumption in the United States.</li>
        <li>USDA Food Safety and Inspection Service compliance, for any product containing meat, poultry, or egg.</li>
        <li>A valid state or local food handler permit, cottage food license, or commercial kitchen certification appropriate to the vendor's jurisdiction.</li>
        <li>Proof of product liability insurance meeting the minimum coverage described in Section 11.</li>
        <li>A documented cold chain shipping method and declared carrier for every listed product.</li>
        <li>Accurate ingredient and allergen disclosure for every product listed, consistent with FDA labeling requirements.</li>
      </ul>
      <p>Sac Frozen verifies that submitted documentation exists and is current. This verification is administrative in nature. It does not constitute an inspection, endorsement, or certification of a vendor's kitchen, process, or product safety, and no listing badge or platform communication should be read to suggest otherwise.</p>
      <p>Sac Frozen reserves the right to decline any application, request additional documentation, or suspend a listing at any time if eligibility requirements are not met or maintained.</p>
    </section>

    <section id="responsibilities">
      <h2><span class="num">04</span>Vendor Responsibilities</h2>
      <div class="clausebox">
        <p>Each vendor represents and warrants that it maintains all applicable federal, state, and local food safety registrations, licenses, and permits required to lawfully manufacture, package, and ship its products, and that it will maintain product liability insurance in the minimum amount specified in these terms for as long as it remains listed.</p>
      </div>
      <p>In addition to the compliance warranty above, vendors are responsible for:</p>
      <ul>
        <li>Packing and shipping every order directly to the buyer using their own materials, carrier, and cold chain method.</li>
        <li>Honoring the ship time and delivery window posted on their listing.</li>
        <li>Maintaining accurate, current pricing, descriptions, and availability for every listed product.</li>
        <li>Responding to buyer inquiries and order issues in a timely and professional manner.</li>
        <li>Carrying insurance and licensing documentation that remains valid and renewed without lapse.</li>
      </ul>
    </section>

    <section id="custody">
      <h2><span class="num">05</span>No Fulfillment or Custody</h2>
      <div class="clausebox">
        <p>Sac Frozen does not take title to, possession of, or custody over any product at any point. All shipping, cold chain packaging, and delivery arrangements are made directly between the vendor and the buyer, using a carrier of the vendor's choosing.</p>
        <p>Sac Frozen has no role in, and assumes no responsibility for, the physical transportation, temperature integrity, or condition of any shipment upon arrival.</p>
      </div>
    </section>

    <section id="listings">
      <h2><span class="num">06</span>Listings &amp; Content</h2>
      <p>Vendors retain ownership of the photos, descriptions, and other content they submit for their listings. By submitting content, a vendor grants Sac Frozen a non-exclusive, royalty-free license to display, reproduce, and promote that content on the platform and in related marketing, for as long as the vendor remains listed.</p>
      <p>Vendors are solely responsible for the accuracy of their listings, including ingredients, allergens, pricing, and ship times. Misleading or inaccurate listings are grounds for suspension under Section 13.</p>
    </section>

    <section id="fees">
      <h2><span class="num">07</span>Fees, Plans &amp; Payment</h2>
      <p>Vendors select one of the following subscription plans at signup. All plans are billed annually at the total shown, regardless of the monthly rate displayed for reference.</p>
      <table>
        <tr><th>Plan</th><th>Monthly rate</th><th>Billed annually</th><th>Commission per order</th></tr>
        <tr><td>Basic</td><td>$10.00</td><td>$120.00/year</td><td>8%</td></tr>
        <tr><td>Standard</td><td>$29.99</td><td>$359.88/year</td><td>6%</td></tr>
        <tr><td>Premium</td><td>$49.99</td><td>$599.88/year</td><td>5%</td></tr>
      </table>
      <p>The commission listed above is deducted from each completed order in addition to the annual subscription fee. Sac Frozen may update plan pricing or commission rates with at least 30 days' notice before a vendor's next renewal date.</p>
      <div class="clausebox">
        <p>Where feasible, payment is routed directly from buyer to vendor through a pass-through payment processor rather than held by Sac Frozen. Vendors are responsible for ensuring their payout account information is accurate and current.</p>
      </div>
      <p class="soft">Vendors are independently responsible for determining and remitting any sales tax, income tax, or other tax obligations arising from their sales on the platform.</p>
    </section>

    <section id="cancellation">
      <h2><span class="num">08</span>Cancellation &amp; Plan Changes</h2>
      <p>Vendors may upgrade, downgrade, or cancel their plan at any time from their vendor dashboard. Downgrades and cancellations take effect at the end of the current annual billing period. Annual subscription fees are non-refundable except where required by law.</p>
      <p>If a vendor cancels or is removed from the platform, any orders already placed must still be fulfilled according to these terms.</p>
    </section>

    <section id="conduct">
      <h2><span class="num">09</span>Prohibited Conduct</h2>
      <p>Vendors may not:</p>
      <ul>
        <li>List a product without the food safety documentation required in Section 3.</li>
        <li>Misrepresent ingredients, allergens, sourcing, or the condition of a product.</li>
        <li>Circumvent the platform to solicit buyers for off-platform transactions of listed products.</li>
        <li>Use Sac Frozen's name, marks, or badges to imply a safety certification the platform does not provide.</li>
        <li>Engage in harassing, discriminatory, or fraudulent conduct toward buyers or other vendors.</li>
      </ul>
      <p>Violation of this section may result in suspension or termination under Section 13, in addition to any other remedy available to Sac Frozen.</p>
    </section>

    <section id="risk">
      <h2><span class="num">10</span>Assumption of Risk &amp; Indemnification</h2>
      <div class="clausebox">
        <p>Buyers acknowledge that all products are prepared, packaged, and shipped by independent third-party vendors, and that Sac Frozen makes no representation or warranty regarding the safety, quality, or condition of any product upon delivery.</p>
        <p>Vendors agree to indemnify and hold harmless Sac Frozen and Seller Africa Inc., along with their officers, employees, and affiliates, from and against any claim, loss, liability, damage, or expense, including reasonable attorney's fees, arising from or related to the preparation, packaging, labeling, shipment, or condition of the vendor's products.</p>
      </div>
    </section>

    <section id="insurance">
      <h2><span class="num">11</span>Insurance Requirements</h2>
      <p>Vendors must maintain general liability insurance with product liability coverage of at least one million dollars, and product recall coverage where applicable to the vendor's product category. A current certificate of insurance must be submitted at onboarding and renewed annually. Lapsed insurance results in automatic suspension of the vendor's listing until a valid certificate is provided.</p>
    </section>

    <section id="ip">
      <h2><span class="num">12</span>Intellectual Property</h2>
      <p>The Sac Frozen name, logo, and platform design are the property of Seller Africa Inc. Vendors may not use these assets outside the platform without written permission, except as reasonably necessary to reference their participation on Sac Frozen in their own marketing.</p>
    </section>

    <section id="termination">
      <h2><span class="num">13</span>Suspension &amp; Termination</h2>
      <p>Sac Frozen may suspend or terminate a vendor's listing at any time, with or without notice, for violation of these terms, lapsed compliance documentation, a pattern of verified buyer complaints, or conduct that Sac Frozen determines in good faith poses a risk to buyers or to the platform.</p>
      <p>A vendor may terminate their participation at any time by canceling their plan as described in Section 8. Sections 10, 11, 14, and 15 survive termination.</p>
    </section>

    <section id="warranties">
      <h2><span class="num">14</span>Disclaimer of Warranties</h2>
      <p>The platform is provided on an as-is and as-available basis. Sac Frozen makes no warranty, express or implied, regarding the platform's uninterrupted availability, the accuracy of vendor listings, or the outcome of any transaction between a vendor and a buyer.</p>
    </section>

    <section id="liability">
      <h2><span class="num">15</span>Limitation of Liability</h2>
      <p>To the fullest extent permitted by law, Sac Frozen and Seller Africa Inc. will not be liable for any indirect, incidental, special, or consequential damages arising from a vendor's use of the platform. Sac Frozen's total liability to a vendor for any claim arising under these terms will not exceed the subscription fees paid by that vendor in the twelve months preceding the claim.</p>
    </section>

    <section id="disputes">
      <h2><span class="num">16</span>Governing Law &amp; Disputes</h2>
      <p>These terms are governed by the laws of the State of Florida, without regard to conflict of law principles. Any dispute arising from these terms will be resolved in the state or federal courts located in Miami-Dade County, Florida, and each party consents to that jurisdiction.</p>
    </section>

    <section id="changes">
      <h2><span class="num">17</span>Changes to These Terms</h2>
      <p>Sac Frozen may update these terms from time to time. Material changes will be communicated to vendors at least 30 days before taking effect. Continued use of the platform after changes take effect constitutes acceptance of the updated terms.</p>
    </section>

    <section id="contact" class="contact-card">
      <h2 style="border-bottom:1px solid rgba(255,255,255,.18);"><span class="num" style="color:var(--gold-light);">18</span>Contact</h2>
      <p>Questions about these terms can be directed to the Sac Frozen vendor support team.</p>
      <p style="margin-top:16px;"><strong style="color:var(--white);">Email:</strong> <a href="mailto:vendors@sacfrozen.com">vendors@sacfrozen.com</a></p>
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
