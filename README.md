# sellerafrica
sellerafrica ecommerce

## Last 30 Days of Updates

**Reporting period:** September 6 through October 5, 2026 (Africa/Lagos).

This changelog covers ecommerce, the public website, distribution, FreshRoots,
vendor tools and administration. BTCPay pages and server operations are excluded.
It summarises recorded changes, including later corrections to earlier versions;
it is not a new production audit. Batch counts below are historical snapshots and
may overlap. No October 6 changes are claimed.

### Website, Branding and Navigation

- Replaced the old products tagline with **African and Caribbean Marketplace**
  across browser titles, descriptions, Open Graph metadata and active page copy.
- Refreshed Shop and Marketplace typography, colours, buttons and cards without
  replacing their existing layouts or shopping logic.
- Matched Our Vendors, About Us, Contact Us, login and Manage My Store to the
  updated public design; retained existing imagery where requested.
- Improved responsive login forms, password visibility, inline errors and
  mobile-menu modals across login and distributor pages.
- Updated header calls to action and navigation: **Our Marketplace** opens the
  complete marketplace; **Shop** opens `/shop?browse=1` on desktop and mobile.
- Removed Distributors from public menu links while retaining its application
  and landing-page routes.
- Made the distribution landing page the homepage, then replaced the redirect
  with direct rendering at the root domain.
- Uploaded anniversary advertising banners, then restored the original three
  marketplace banners on October 2.
- Added a responsive slider for 15 renamed partner logos, with swipe, arrows,
  automatic scrolling and pause/play; changed its heading to **Retailers we work with:**.
- Added the requested retail-opportunity copy and registration CTA after the
  brand-fit section on the distribution page.
- Removed the public metrics block and added an October 1-only, dismissible
  Nigeria 66th Independence Day popup.
- Expanded `robots.txt` restrictions for the requested competitive-intelligence
  crawlers. These are voluntary crawler directives, not server-level blocking.

### Marketplace and Products

- Introduced weekly discovery rotation, then daily rotation across the website
  and mobile catalog API, while preserving explicit sort options.
- Expanded selection pools, rotated the visible product slice and reduced
  repeated products between homepage sections.
- Corrected New Arrivals to prioritise newly approved products, Hot Deals to show
  genuine discounts, and Best Sellers to use actual sales.
- Restored all marketplace sections and banners; corrected category links and
  preserved section/category filters in View All.
- Required categories during vendor product uploads, edits and registration
  product submission; recategorised 794 products with parent-category inheritance
  for variations.
- Created a dedicated Handmade category, removed unrelated-food fallback and
  assigned six matching listings, four of which were approved at that time.
- Replaced the unrelated broccoli fallback with a neutral Image unavailable graphic.
- Added circular verified-vendor badges and prioritised landscape-banner stores
  in the public vendor directory.
- Added optional size, colour, length and custom variations with separate prices,
  stock, images and optional shipping measurements; selected variations carry
  into the basket and order.
- Audited 571 approved standalone products for repetition, excluding 30 variation
  rows; produced a report without deleting the identified candidates.

### Basket, Shipping and Checkout

- Fixed Add to Basket and Buy Now failures caused by cart API routing and the
  root front controller; cart failures now return structured JSON errors.
- Added database-backed cart persistence instead of session-only storage.
- Restricted vendor order views and update actions to paid orders, hiding unpaid
  checkout attempts from the vendor order workflow.
- Fixed country-name handling in Aramex shipping quotes and removed the silent
  free-shipping fallback when quotation fails.
- Required complete customer and shipping-address information before checkout,
  including country, state/region, city, postal code, street address and contact details.
- Added separate billing and shipping addresses with a Same as shipping address checkbox.
- Added an editable order-review screen showing products, addresses, shipping,
  tax and total before **Confirm and pay** starts payment.
- Rechecks shipping and totals at confirmation so changed charges require review.

### Vendor Registration, Verification and Plans

- Removed the direct buyer-to-vendor upgrade shortcut: existing users must
  complete a vendor application rather than bypassing required information.
- Added `/vendor/plans`, sidebar access, current-plan/slot summaries and upgrade actions.
- Changed product-slot accounting to published active/private products; pending
  application products no longer consume the five free catalog slots.
- Refined registration through the supplied application template/PDF, removing
  business email and moving extended setup into verification.
- Added consistent registration styling for vendors, buyers and affiliates,
  country dropdowns, phone country-code controls, password visibility, loading
  states, inline validation and responsive desktop/mobile layouts.
- Fixed desktop registration forms being hidden, mobile step progression, unclear
  stage labels and failures that did not reveal the invalid field.
- Preserved entered information and returned users to the incomplete step; added
  specific phone errors and retry feedback for slow existing-account checks.
- Removed vendor/farmer links and vendor imagery from buyer registration;
  temporarily removed Apply as a farmer from vendor registration.
- Added first-product upload guidance, explicit USD/kg labels, package-dimension
  fields, image-size guidance and warnings against visible contact details in images.
- Enforced a 5 MB profile/banner/product-image limit in browser and backend;
  added image readability checks and temporary copies for mobile submission.
- Displayed plan benefits and moved plan selection to the beginning of signup.
- Split onboarding into short signup, payment, separate verification, pending or
  rejected status, and dashboard access after approval.
- Added verification drafts, complete required-field checks, application thank-you
  emails, completion guidance and approval notifications.
- Kept supporting/optional information separate from required completion scoring.
- Added unpaid-plan invoice reminders, dashboard payment prompts and scheduled
  reminder processing; fixed Plans/Details routing and provider redirects.
- Introduced a compulsory **NGN 1,500 one-time Paystack registration fee** for all
  new vendors, including the Free plan. Verification stays locked until payment
  is confirmed; paid-plan subscriptions are separate and existing vendors are unaffected.

### Admin and Vendor Review

- Removed duplicate inner sidebars and Vendor Health panels, expanding content
  space across vendor and general admin pages.
- Improved responsive grids, tables, search, pagination, forms, buttons and
  off-canvas navigation; standardised input and focus styling.
- Aligned pending-vendor readiness with current registration requirements and
  correctly counted submitted product images, descriptions, prices and dimensions.
- Fixed Pending Review double counting by counting distinct stores.
- Added registration timestamps, completion summaries/range filters and
  highest-completion-first sorting.
- Separated Rejected Vendors from Pending Vendors and its totals.
- Added modal document/image previews, previous/next navigation, PDF support and
  open/download fallbacks; completion badges can open their corresponding images.
- Configured future rejection emails with general guidance and specific admin
  reasons, without resending when an already-rejected vendor is saved.
- Allowed regular admin dashboard access except Settings and Payments, including
  direct-route restrictions; sensitive role/account/import tools remain superadmin-only.
- Restricted new-user, vendor, distributor and product-submission notifications
  to superadmins, retaining applicant confirmations.
- Generated vendor contact/status exports and branded system/month-to-date/daily
  reports; recorded report emails were accepted or completed by the email service.
- Built an admin Set Up a Store tool for vendor branding and product management.
  Its September 30 record reports deployment blocked, so it is not listed as confirmed live.

### Payments and Store Services

- Added real Paystack initialization, settings, callbacks and webhooks for
  storefront orders, vendor plans and supported service purchases.
- Fixed unsupported-currency failures by charging Paystack in NGN at the
  configured exchange rate and verifying the converted amount/currency.
- Restored reserved stock when payment initialization fails.
- Fixed Stripe plan/package checkout rejecting an empty product description.
- Added Paystack to Manage My Store and product-ranking payments.
- Preserved **Manage My Store at USD 12/year**, marketed as USD 1/month, and its
  email-linked service activation after verified payment.
- Added **Set Up My Store at USD 5 one-time**, with Stripe, Paystack and Klasha support.
- Added superadmin customer-specific payment links for management, setup, ranking,
  Priority, vendor plans/registration and distributor packages, including copy/open/email actions.
- Fixed payment-link generation being intercepted by the general dashboard route.
- Added **Seller Africa Priority at USD 5/month or USD 50/year**, benefits,
  disclaimers, a 500-founding-member limit, confirmations, cancellation and admin listings.
- Added Priority Paystack pricing of NGN 7,500/month or NGN 75,000/year at the
  recorded conversion rate. Paystack renewals are manual; Stripe subscriptions recur.
- Replaced the failing legacy WooCommerce Stripe destination with signed
  `/stripe-webhook` handling and duplicate-event protection; disabled the old destination.
- Included Manage My Store payments in monthly revenue reporting.

### Distribution and Retail Placement

- Added separate `/distributors` and `/distributor/register` pages and a five-step
  application with saved drafts, validation, status, feedback and emails.
- Added distributor dashboards using existing product/order tools, admin-set
  catalog allowances, search and pagination.
- Added `/dashboard/distributors` with status counts, expandable application
  details and responsive review forms.
- Fixed distributor signup redirecting to buyer registration.
- Refreshed retail-placement pages with Manrope/Cormorant Garamond typography,
  retail imagery, minimalist layouts and mobile navigation.
- Added Become a vendor as a separate CTA and changed distribution application
  wording to **Apply for shelf placement**.
- Added post-registration packages: **Retail Starter USD 500**, **Retail Access
  USD 1,500** and **Retail Expansion USD 5,000**.
- Added Stripe USD and Paystack NGN package payments, converted totals, package
  benefits, success-fee terms, confirmations and admin payment status.
- Kept payment confirmation separate from application approval.

### FreshRoots

- Applied the supplied farmer-registration template, restored original fields,
  required genuine answers/photos and fixed mobile form overlap.
- Applied the supplied black-owned-farms landing template with real approved
  farm/produce discovery content.
- Added farm-user signup, membership persistence, Stripe/Klasha checkout/webhooks
  and subscription gates for farms, farms-near-me and the farm shop.
- Set membership to USD 19.99/month, corrected broken rendered prices and
  retained the recorded USD 199/year option.
- Added SAC branding and adapted privacy, terms and legal templates/routes.
- Renamed Farm Fresh to FreshRoots, added `/freshroots` routes and preserved old aliases.
- Added Nigeria availability guards to farmer registration and FreshRoots join,
  with IP lookup when country headers are absent.

### Recorded Production Data Actions

| Date | Action |
| --- | --- |
| September 8 | Moved 118 unique contact-list vendors to pending; preserved user accounts. |
| September 11 | Moved 424 matched stores to pending and exported their contacts; unapproved the test farmer without deleting the user. |
| September 15 | Approved 24 vendors at 75%+ and 210 additional vendors at 50%+; retained explicitly rejected test stores. |
| September 16 | Removed two requested test accounts and their identified related records; the third requested email was not found. |
| September 17 | Rejected 3,987 pending vendors at 50% or below; approved 87 verified-vendor products, retaining two pending because of plan limits. |
| September 17 | Added three units each to 48 low-stock products: 144 units, as a one-time adjustment. |
| September 18 | Archived three test products; moved twelve zero-price products to pending; rejected identified test/unwanted vendor stores. |
| September 25 | Rejected 94 products lacking images/descriptions and 95 vendors in the requested incomplete ranges. |
| September 28 | Approved one pending vendor at 90-100% and rejected 51 below 90%. |
| September 30 | Configured Kestrel Topaz store/product details and imagery, approved both, sent the storefront approval email and fixed the missing store description. |
| October 2 | Rejected nine active zero-price listings, 41 products at USD 1,000+, eight spam/test products and identified machinery/electrical listings. |

These were recorded one-time batches, not a permanent automatic moderation
policy. Rejection/archive generally preserved accounts and order history;
explicit test-account deletions are identified separately. Backups were recorded
before production data changes.

### Verification and Remaining Limitations

- Recorded checks included PHP/JavaScript validation, automated payment/permission
  checks and responsive browser tests at phone, tablet and desktop widths.
- Many payment tests were mocked and did not create real charges; initial
  distributor authenticated end-to-end/email testing and some document-preview
  and rejection-email delivery checks were explicitly outstanding.
- Priority community invitations still require manual delivery by the team.
- Flutter app construction, saved-login/image-cache work and most app UI changes
  were recorded on September 5, outside this exact 30-day window. This period
  includes catalog API rotation, not a claim that those earlier app features were
  newly implemented after September 6.
- Local change reports, production/customer data, credentials, uploads, database
  dumps and build outputs are deliberately excluded from this repository.
