<?php
$brand = app_branding();
$brandName = (string)($brand['name'] ?? 'Seller Africa');
$accountType = in_array((string)($accountType ?? 'customer'), ['customer', 'vendor', 'affiliate'], true) ? (string)$accountType : 'customer';
$toasts = function_exists('consume_toasts') ? consume_toasts() : [];
$copy = [
    'customer' => [
        'title' => 'Create your user account',
        'kicker' => 'Shop authentic products from trusted vendors.',
        'button' => 'Create user account',
        'asideTitle' => $brandName,
        'asideText' => 'Discover the African and Caribbean Marketplace, manage your orders, and keep the flavors of home close wherever you are.',
        'points' => [
            ['Free and quick sign up', 'Create your account with email verification and start shopping.'],
            ['Secure checkout', 'Pay safely, track your order, and review delivery details before purchase.'],
            ['African marketplace', 'Explore food, fashion, beauty, wellness, and everyday essentials.'],
            ['Community for the diaspora', 'Stay connected with vendors and products that feel like home.'],
        ],
    ],
    'vendor' => [
        'title' => 'Start selling on Seller Africa',
        'kicker' => 'Tell us about your business to begin your seller application.',
        'button' => 'Submit Application',
        'asideTitle' => 'Seller application',
        'asideText' => 'Application is subject to verification, KYC review, and product review before store activation.',
        'points' => [
            ['Complete application', 'Business, owner, product, fulfillment, and verification details.'],
            ['Upload documents', 'Store branding, product files, identity, and business verification.'],
            ['Team review', 'Approval is not automatic; our team reviews every seller.'],
            ['Store activation', 'Approved sellers can continue setup from the vendor dashboard.'],
        ],
    ],
    'affiliate' => [
        'title' => 'Join as an affiliate',
        'kicker' => 'Share Seller Africa and earn from referrals.',
        'button' => 'Create affiliate account',
        'asideTitle' => 'Earn with referrals',
        'asideText' => 'Join the Seller Africa affiliate program, receive a referral code, and grow with the marketplace as you introduce new shoppers and vendors.',
        'points' => [
            ['Referral tracking', 'Get a unique referral code after approval.'],
            ['Simple dashboard', 'Track referrals, commissions, creatives, and payout status.'],
            ['Community-led growth', 'Share authentic products with people who care about the culture.'],
            ['Protected registration', 'Every account starts with email verification and review.'],
        ],
    ],
];
$active = $copy[$accountType];
$dashboardUrl = (string)($dashboardUrl ?? app_url($accountType === 'affiliate' ? 'affiliate' : ($accountType === 'vendor' ? 'vendor' : 'buyer')));
$formAction = match ($accountType) {
    'vendor' => app_url('vendor/register'),
    'affiliate' => app_url('affiliate/join'),
    default => app_url('buyer/register'),
};
$termsLabel = match ($accountType) {
    'vendor' => 'I have read and accept the Seller Africa Vendor Terms & Conditions, including Third-Party Service Providers & Limitation of Liability, the Vendor Agreement, and Privacy Policy.',
    'affiliate' => 'I have read and accept the Seller Africa Affiliate Terms & Conditions and Privacy Policy.',
    default => 'I agree to the terms and privacy policy.',
};
$links = [
    'vendor' => ['label' => 'Apply as a vendor', 'url' => app_url('vendor/register')],
];
$vendorPackages = is_array($vendorPackages ?? null) ? $vendorPackages : [];
$selectedVendorPackageId = (int)($selectedVendorPackageId ?? 0);
$paymentProvider = in_array((string)($paymentProvider ?? 'stripe'), ['stripe', 'paystack'], true) ? (string)$paymentProvider : 'stripe';
$paystackAvailable = !empty($paystackAvailable);
if (!$paystackAvailable && $paymentProvider === 'paystack') {
    $paymentProvider = 'stripe';
}
$maxUploadBytes = max(1, (int)($maxUploadBytes ?? 5242880));
$maxUploadMb = (int)ceil($maxUploadBytes / 1048576);
$countries = [
    'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua and Barbuda', 'Argentina', 'Armenia', 'Australia', 'Austria',
    'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belgium', 'Belize', 'Benin', 'Bolivia', 'Botswana', 'Brazil', 'Bulgaria', 'Burkina Faso',
    'Cameroon', 'Canada', 'Cape Verde', 'Chile', 'China', 'Colombia', 'Costa Rica', 'Cote d’Ivoire', 'Croatia', 'Cuba',
    'Denmark', 'Dominica', 'Dominican Republic', 'Ecuador', 'Egypt', 'Ethiopia', 'Finland', 'France', 'Gambia', 'Germany',
    'Ghana', 'Greece', 'Grenada', 'Guatemala', 'Guinea', 'Guyana', 'Haiti', 'Honduras', 'India', 'Indonesia', 'Ireland', 'Italy',
    'Jamaica', 'Japan', 'Kenya', 'Liberia', 'Mexico', 'Morocco', 'Netherlands', 'New Zealand', 'Nigeria', 'Norway',
    'Pakistan', 'Panama', 'Peru', 'Philippines', 'Portugal', 'Rwanda', 'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Vincent and the Grenadines',
    'Senegal', 'Sierra Leone', 'South Africa', 'Spain', 'Suriname', 'Sweden', 'Switzerland', 'Tanzania', 'Togo', 'Trinidad and Tobago',
    'Uganda', 'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay', 'Venezuela', 'Zambia', 'Zimbabwe',
];
$authCartCount = 0;
try {
    if (class_exists('\App\CartService')) {
        $authCartCount = (new \App\CartService())->count();
    }
} catch (\Throwable) {
    $authCartCount = 0;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($active['title'] . ' | ' . $brandName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Urbanist:wght@400;500;600;700;800;900&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --font-unageo: "Unageo", "DM Sans", system-ui, -apple-system, sans-serif;
            --sa-sans: "DM Sans", system-ui, -apple-system, sans-serif;
            --green: #00684f;
            --ink: #293236;
            --muted: #657276;
            --line: #e5eaec;
            --soft: #f3f5f6;
            --gutter: clamp(24px, 5vw, 72px);
            --radius: 10px;
        }
        * { box-sizing: border-box; }
        body { margin: 0; color: var(--ink); background: linear-gradient(180deg, #f7f5ef 0%, #ffffff 38%, #f6f8f7 100%); font-family: var(--sa-sans); }
        a { color: var(--green); text-decoration: none; }
        .sa-register-header { --green:#00684f; --mint:#e7fbf5; --ink:#202326; --muted:#5f6b67; --radius:10px; font-family:var(--sa-sans); background:#fff; color:var(--muted); }
        .sa-register-header * { box-sizing:border-box; }
        .sa-register-header__announce { min-height:38px; display:grid; place-items:center; padding:6px 16px; background:var(--mint); color:#153d35; font-weight:800; text-align:center; }
        .sa-register-header__bar { position:sticky; top:0; z-index:60; background:rgba(255,255,255,.96); border-bottom:1px solid rgba(231,228,220,.7); backdrop-filter:blur(14px); }
        .sa-register-header__nav { width:min(1440px, calc(100% - 40px)); min-height:88px; margin:0 auto; display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:30px; }
        .sa-register-header__logo { display:inline-flex; align-items:center; color:var(--green); font-weight:900; font-size:20px; }
        .sa-register-header__logo img { display:block; width:auto; max-width:170px; max-height:56px; object-fit:contain; }
        .sa-register-header__links { display:flex; justify-content:center; align-items:center; gap:clamp(22px,4vw,60px); font-weight:800; }
        .sa-register-header__links a { color:var(--muted); }
        .sa-register-header__links a:hover { color:var(--green); }
        .sa-register-header__actions { display:flex; align-items:center; justify-content:flex-end; gap:12px; }
        .sa-register-header__cart, .sa-register-header__btn, .sa-register-header__menu { min-height:44px; border:1px solid var(--green); border-radius:var(--radius); background:#fff; color:var(--green); display:inline-flex; align-items:center; justify-content:center; }
        .sa-register-header__cart { width:46px; position:relative; }
        .sa-register-header__cart svg { width:22px; height:22px; }
        .sa-register-header__cart span { position:absolute; right:-1px; top:0; min-width:18px; height:18px; display:grid; place-items:center; border-radius:999px; background:var(--green); color:#fff; font-size:11px; font-weight:900; }
        .sa-register-header__btn { padding:0 20px; font-weight:900; }
        .sa-register-header__btn--solid { background:var(--green); color:#fff; }
        .sa-register-header__menu { display:none; width:46px; padding:0; cursor:pointer; }
        .sa-register-header__menu span, .sa-register-header__menu::before, .sa-register-header__menu::after { content:""; display:block; width:18px; height:2px; margin:4px auto; border-radius:999px; background:currentColor; }
        .sa-auth { width: min(1440px, calc(100% - 40px)); min-height: 100vh; margin: 0 auto; padding: clamp(28px, 4vw, 56px) 0; display: grid; grid-template-columns: minmax(0, 1fr) minmax(320px, 380px); gap: clamp(22px, 3vw, 38px); align-items: start; }
        .sa-auth__form { min-width: 0; padding: clamp(26px, 4vw, 44px); display: flex; flex-direction: column; justify-content: flex-start; background: rgba(255,255,255,.94); border: 1px solid rgba(0,104,79,.10); border-radius: 18px; box-shadow: 0 18px 44px rgba(28, 39, 35, .08); }
        .sa-auth__brand { display: inline-flex; align-items: center; width: fit-content; margin-bottom: 24px; }
        .sa-auth__brand img { width: auto; max-width: 150px; max-height: 54px; object-fit: contain; }
        .sa-auth h1, .sa-auth h2, .sa-auth h3 { font-family: var(--font-unageo); letter-spacing: 0; }
        .sa-auth h1 { margin: 0; color: #1d2927; font-size: clamp(30px, 3.5vw, 44px); line-height: 1.06; font-weight: 900; }
        .sa-auth__kicker { max-width: 680px; margin: 10px 0 0; color: var(--muted); font-size: 16px; line-height: 1.55; }
        .sa-auth__login { margin: 12px 0 22px; color: var(--muted); font-size: 15px; text-align: left; }
        .sa-auth__switch { display: grid; grid-template-columns: minmax(0, 1fr); gap: 10px; margin: 26px 0 26px; }
        .sa-auth__switch a { min-height: 46px; border: 1px solid var(--line); border-radius: var(--radius); display: inline-flex; align-items: center; justify-content: center; color: #4d595d; font-weight: 800; text-align: center; padding: 0 12px; }
        .sa-auth__switch a.is-active { border-color: var(--green); color: #fff; background: var(--green); }
        .sa-auth__alerts { display: grid; gap: 10px; margin-bottom: 18px; }
        .sa-auth__alert { border: 1px solid #fecaca; border-radius: var(--radius); background: #fff1f2; color: #9f1239; padding: 12px 14px; font-weight: 700; }
        .sa-auth__alert.is-success { border-color: #bbf7d0; background: #f0fdf4; color: #166534; }
        .sa-auth__inline-alert { grid-column: 1 / -1; display: none; border: 1px solid #f6b21a; border-radius: var(--radius); background: #fff8e6; color: #5b3b00; padding: 12px 14px; font-weight: 800; line-height: 1.45; }
        .sa-auth__inline-alert.is-visible { display: block; }
        .sa-auth__inline-alert a { color: var(--green); font-weight: 900; text-decoration: underline; text-underline-offset: 3px; }
        .sa-auth__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px 18px; margin-top: 18px; }
        .sa-auth__field { display: grid; gap: 8px; align-content: start; }
        .sa-auth__field.is-full { grid-column: 1 / -1; }
        .sa-auth__field small { color: var(--muted); font-size: 12px; font-weight: 700; line-height: 1.45; text-transform: none; }
        .sa-auth label { color: #33413e; font-size: 14px; font-weight: 800; }
        .sa-auth input, .sa-auth select, .sa-auth textarea { width: 100%; min-height: 54px; border: 1px solid #dfe6e2; border-radius: 9px; background: #fbfcfb; color: #20282c; padding: 0 15px; font: 600 15px/1.35 var(--sa-sans); outline: 0; transition: border-color .18s ease, box-shadow .18s ease, background .18s ease; }
        .sa-auth textarea { min-height: 128px; padding-top: 16px; resize: vertical; }
        .sa-auth input[type="file"] { padding: 17px 18px; line-height: 1.3; }
        .sa-auth input:focus, .sa-auth select:focus, .sa-auth textarea:focus { border-color: var(--green); background: #fff; box-shadow: 0 0 0 4px rgba(0, 104, 79, .1); }
        .sa-auth input::placeholder { color: #a5adb1; }
        .sa-auth__password { position: relative; }
        .sa-auth__password button { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); border: 0; background: transparent; color: #94a0a5; font-weight: 800; }
        .sa-auth__terms { grid-column: 1 / -1; display: flex; gap: 10px; align-items: flex-start; color: var(--muted); line-height: 1.5; }
        .sa-auth__terms input { width: 18px; min-height: 18px; margin-top: 2px; accent-color: var(--green); }
        .sa-auth__terms a { font-weight: 800; }
        .sa-auth__submit { grid-column: 1 / -1; min-height: 58px; border: 0; border-radius: 9px; background: var(--green); color: #fff; font: 850 16px/1 var(--font-unageo); box-shadow: 0 12px 24px rgba(0, 104, 79, .18); display: inline-flex; align-items: center; justify-content: center; gap: 10px; cursor: pointer; }
        .sa-auth__submit:disabled, .sa-vendor-action:disabled { cursor: not-allowed; opacity: .72; }
        .sa-auth__submit.is-loading::before { content: ""; width: 18px; height: 18px; border-radius: 999px; border: 2px solid rgba(255,255,255,.42); border-top-color: #fff; animation: sa-spin .72s linear infinite; }
        @keyframes sa-spin { to { transform: rotate(360deg); } }
        .sa-vendor-step-status { display: none; margin: 22px 0 10px; padding: 12px 14px; border: 1px solid rgba(0,104,79,.14); border-radius: 12px; background: #f3fbf7; color: #20423a; font-weight: 900; }
        .sa-vendor-step-status small { display: block; margin-top: 4px; color: var(--muted); font-weight: 800; }
        .sa-vendor-steps { --step-progress: 0%; position: relative; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin: 24px 0 20px; }
        .sa-vendor-steps::before, .sa-vendor-steps::after { content: ""; position: absolute; left: 7%; right: 7%; top: 19px; height: 3px; border-radius: 999px; pointer-events: none; }
        .sa-vendor-steps::before { background: #e5eaec; }
        .sa-vendor-steps::after { right: auto; width: var(--step-progress); max-width: 86%; background: linear-gradient(90deg, #00684f, #d99a00); transition: width .35s ease; }
        .sa-vendor-steps__item { position: relative; z-index: 1; min-height: 40px; border: 1px solid var(--line); border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 6px 8px; color: var(--muted); background: #fff; font-weight: 900; font-size: 12px; text-align: center; transition: background .25s ease, border-color .25s ease, color .25s ease, transform .25s ease, box-shadow .25s ease; }
        .sa-vendor-steps__num { width: 22px; height: 22px; border-radius: 999px; display: inline-grid; place-items: center; background: #edf2f3; color: #4d5b60; font-size: 12px; line-height: 1; flex: 0 0 auto; }
        .sa-vendor-steps__label { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sa-vendor-steps__item.is-complete { border-color: var(--green); background: #e7f5ef; color: var(--green); }
        .sa-vendor-steps__item.is-complete .sa-vendor-steps__num { background: var(--green); color: #fff; }
        .sa-vendor-steps__item.is-active { border-color: #d99a00; background: #f6b21a; color: #1f2933; transform: translateY(-1px); box-shadow: 0 10px 20px rgba(217, 154, 0, .2); }
        .sa-vendor-steps__item.is-active .sa-vendor-steps__num { background: #1f2933; color: #fff; }
        .sa-vendor-step { display: none; }
        .sa-vendor-step.is-active { display: block; }
        .sa-vendor-step h2 { margin: 0 0 8px; color: #1f2d29; font-size: clamp(22px, 2.5vw, 30px); line-height: 1.15; }
        .sa-vendor-step__note { margin: 0 0 18px; color: var(--muted); line-height: 1.5; }
        .sa-vendor-step__meta { margin: -4px 0 18px; color: #6c7773; font-size: 13px; line-height: 1.45; }
        .sa-vendor-error-summary { display: none; grid-column: 1 / -1; margin: 0 0 14px; padding: 12px 14px; border: 1px solid #fecaca; border-radius: 10px; background: #fff1f2; color: #9f1239; font-weight: 800; line-height: 1.45; }
        .sa-vendor-error-summary.is-visible { display: block; }
        .sa-upload-preview { display: none; gap: 10px; align-items: center; min-height: 58px; padding: 9px; border: 1px solid #dfe6e2; border-radius: 9px; background: #fff; color: #33413e; font-size: 13px; font-weight: 800; }
        .sa-upload-preview.is-visible { display: flex; }
        .sa-upload-preview img { width: 48px; height: 48px; border-radius: 7px; object-fit: cover; background: #eef2ef; border: 1px solid #edf2f3; }
        .sa-vendor-actions { grid-column: 1 / -1; display: flex; gap: 12px; justify-content: space-between; margin-top: 8px; }
        .sa-vendor-action { min-height: 54px; border-radius: 9px; padding: 0 22px; font: 850 15px/1 var(--font-unageo); cursor: pointer; }
        .sa-vendor-action--ghost { border: 1px solid var(--line); color: var(--green); background: #fff; }
        .sa-vendor-action--primary { border: 0; color: #fff; background: var(--green); box-shadow: 0 12px 28px rgba(0, 104, 79, .18); }
        .sa-plan-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; }
        .sa-plan-card { position: relative; display: grid; gap: 10px; min-height: 100%; padding: 18px; border: 1px solid var(--sa-border, #DCEAE2); border-radius: 18px; background: #fff; box-shadow: 0 14px 34px rgba(22,55,42,.06); cursor: pointer; }
        .sa-plan-card input { position: absolute; opacity: 0; pointer-events: none; }
        .sa-plan-card:has(input:checked) { border-color: var(--sa-primary, #177D51); background: linear-gradient(180deg, #fff, #f2faf5); box-shadow: 0 18px 38px rgba(23,125,81,.14); }
        .sa-plan-card__top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
        .sa-plan-card__name { color: var(--sa-ink, #16372A); font-size: 17px; font-weight: 900; line-height: 1.15; }
        .sa-plan-card__check { width: 24px; height: 24px; border-radius: 999px; border: 1px solid var(--sa-border, #DCEAE2); display: grid; place-items: center; color: transparent; flex: 0 0 auto; }
        .sa-plan-card:has(input:checked) .sa-plan-card__check { border-color: var(--sa-primary, #177D51); background: var(--sa-primary, #177D51); color: #fff; }
        .sa-plan-card__price { color: var(--sa-primary, #177D51); font-size: 24px; font-weight: 900; }
        .sa-plan-card__price small { color: var(--sa-muted, #61766C); font-size: 13px; }
        .sa-plan-card__meta { color: var(--sa-muted, #61766C); font-size: 13px; font-weight: 700; line-height: 1.45; }
        .sa-plan-card__benefits { display: grid; gap: 9px; border-top: 1px solid var(--sa-border, #DCEAE2); padding-top: 14px; margin-top: 4px; }
        .sa-plan-card__benefit { display: flex; align-items: flex-start; gap: 9px; color: var(--sa-ink, #16372A); font-size: 14px; font-weight: 500; line-height: 1.5; overflow-wrap: anywhere; }
        .sa-plan-card__benefit::before { content: '\2713'; display: grid; place-items: center; flex: 0 0 19px; height: 19px; margin-top: 1px; border-radius: 50%; background: var(--sa-primary, #177D51); color: #fff; font-size: 12px; }
        .sa-plan-card:has(input:focus-visible) { outline: 3px solid var(--sa-primary, #177D51); outline-offset: 3px; }
        .sa-payment-choice { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .sa-payment-choice label { display: flex; align-items: center; gap: 10px; min-height: 54px; padding: 0 15px; border: 1px solid var(--sa-border, #DCEAE2); border-radius: 16px; background: #fff; cursor: pointer; }
        .sa-payment-choice input { width: 18px; min-height: 18px; accent-color: var(--sa-primary, #177D51); }
        .sa-auth__aside { min-width: 0; position: sticky; top: 24px; display: flex; align-items: stretch; justify-content: center; padding: 0; background: transparent; }
        .sa-auth__aside-inner { width: 100%; text-align: left; padding: 24px; border: 1px solid rgba(0,104,79,.12); border-radius: 18px; background: #ffffff; box-shadow: 0 18px 44px rgba(28, 39, 35, .06); }
        .sa-auth__parcel { width: 100%; margin: 0 0 20px; aspect-ratio: 16 / 9; border-radius: 14px; background: #eef5f2; position: relative; box-shadow: none; overflow: hidden; }
        .sa-auth__parcel--image { aspect-ratio: 16 / 9; }
        .sa-auth__parcel--image img { width: 100%; height: 100%; display: block; object-fit: cover; object-position: center; }
        .sa-auth__parcel--image::before, .sa-auth__parcel--image::after { display: none; }
        .sa-auth__parcel img { width: 100%; height: 100%; display: block; object-fit: cover; object-position: center; }
        .sa-auth__aside h2 { margin: 0; color: #1f2d29; font-size: 24px; line-height: 1.15; font-weight: 900; }
        .sa-auth__aside p { margin: 10px 0 20px; max-width: 580px; color: var(--muted); font-size: 14px; line-height: 1.55; }
        .sa-auth__points { display: grid; gap: 12px; text-align: left; }
        .sa-auth__point { display: grid; grid-template-columns: 28px minmax(0, 1fr); gap: 10px; align-items: start; padding: 12px; border: 1px solid #eef2ef; border-radius: 12px; background: #fbfcfb; }
        .sa-auth__check { width: 28px; height: 28px; border-radius: 999px; display: grid; place-items: center; background: #e8f5ef; color: var(--green); font-weight: 900; box-shadow: none; }
        .sa-auth__point strong { display: block; color: #1f2d29; font: 850 14px/1.2 var(--font-unageo); }
        .sa-auth__point span { display: block; margin-top: 4px; color: var(--muted); font-size: 13px; line-height: 1.4; }
        .sa-auth__hidden { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
        @media (max-width: 1180px) {
            .sa-vendor-steps {
                display: flex;
                overflow-x: auto;
                overscroll-behavior-x: contain;
                scroll-snap-type: x proximity;
                padding: 0 2px 10px;
                scrollbar-width: thin;
            }
            .sa-vendor-steps::before,
            .sa-vendor-steps::after { display: none; }
            .sa-vendor-steps__item {
                min-width: 132px;
                justify-content: flex-start;
                scroll-snap-align: start;
            }
            .sa-vendor-steps__label {
                white-space: normal;
                line-height: 1.15;
            }
        }
        @media (max-width: 980px) {
            .sa-register-header__nav { grid-template-columns:auto auto; width:min(100% - 28px, 1180px); min-height:78px; }
            .sa-register-header__logo img { max-width:148px; }
            .sa-register-header__links { display:none; grid-column:1 / -1; width:100%; flex-direction:column; align-items:stretch; gap:0; padding:8px 0 18px; }
            .sa-register-header.is-menu-open .sa-register-header__links { display:flex; }
            .sa-register-header__links a { padding:14px 0; border-top:1px solid #eef0f2; }
            .sa-register-header__actions { justify-self:end; }
            .sa-register-header__actions .sa-register-header__btn { display:none; }
            .sa-register-header__menu { display:inline-block; }
            .sa-auth { grid-template-columns: 1fr; }
            .sa-auth__aside { order: -1; padding: 28px var(--gutter) 22px; }
            .sa-auth__aside-inner { display: grid; grid-template-columns: 112px minmax(0, 1fr); gap: 18px; align-items: center; text-align: left; }
            .sa-auth__parcel { width: 112px; margin: 0; border-radius: 14px; }
            .sa-auth__aside h2 { font-size: 26px; }
            .sa-auth__aside p { margin: 8px 0 0; font-size: 15px; }
            .sa-auth__points { grid-column: 1 / -1; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
            .sa-auth__point { grid-template-columns: 30px minmax(0, 1fr); gap: 10px; }
            .sa-auth__check { width: 30px; height: 30px; }
            .sa-auth__point strong { font-size: 15px; }
            .sa-auth__point span { font-size: 13px; }
            .sa-auth__form { padding-top: 34px; }
        }
        @media (max-width: 620px) {
            :root { --gutter: 18px; }
            body { overflow-x: hidden; }
            .sa-register-header__announce { font-size:12px; }
            .sa-auth__grid, .sa-auth__switch { grid-template-columns: 1fr; gap: 14px; }
            .sa-auth__form, .sa-auth__aside { padding-inline: 18px; }
            .sa-auth__form { padding-top: 28px; padding-bottom: 34px; }
            .sa-auth__brand { margin-bottom: 22px; }
            .sa-auth__brand img { max-width: 142px; max-height: 52px; }
            .sa-auth h1 { font-size: clamp(30px, 10vw, 38px); line-height: 1.05; overflow-wrap: anywhere; }
            .sa-auth__kicker { font-size: 15px; }
            .sa-auth__login { text-align: left; }
            .sa-auth input, .sa-auth select { min-height: 54px; font-size: 16px; padding-inline: 14px; }
            .sa-auth textarea { min-height: 112px; }
            .sa-auth input[type="file"] { min-height: 54px; padding: 14px; font-size: 14px; }
            .sa-auth__aside { padding-top: 20px; padding-bottom: 20px; }
            .sa-auth__aside-inner { grid-template-columns: 82px minmax(0, 1fr); gap: 14px; }
            .sa-auth__parcel { width: 82px; }
            .sa-auth__aside h2 { font-size: 22px; }
            .sa-auth__aside p { font-size: 14px; line-height: 1.4; }
            .sa-auth__points { display: none; }
            .sa-vendor-steps { margin: 20px -2px 16px; }
            .sa-vendor-step-status { display: block; }
            .sa-vendor-steps__item { min-width: 118px; min-height: 44px; padding: 7px 9px; }
            .sa-vendor-step h2 { font-size: 24px; }
            .sa-vendor-step__note { font-size: 14px; }
            .sa-auth__terms { font-size: 14px; }
            .sa-vendor-actions { flex-direction: column-reverse; gap: 10px; }
            .sa-vendor-action, .sa-auth__submit { width: 100%; min-height: 56px; }
            .sa-payment-choice { grid-template-columns: 1fr; }
        }
        @media (max-width: 390px) {
            .sa-auth__aside-inner { grid-template-columns: 1fr; }
            .sa-auth__parcel { display: none; }
            .sa-vendor-steps__item { min-width: 108px; }
        }

        /* Premium registration refresh */
        :root {
            --sa-font: "Urbanist", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --sa-primary: #177D51;
            --sa-primary-hover: #106B44;
            --sa-soft-green: #F2FAF5;
            --sa-ink: #16372A;
            --sa-muted: #61766C;
            --sa-border: #DCEAE2;
            --sa-error: #C33B36;
            --sa-surface: #FFFFFF;
            --sa-page: #F7FBF8;
            --sa-shadow: 0 28px 80px rgba(22, 55, 42, .12);
            --sa-shadow-soft: 0 14px 34px rgba(22, 55, 42, .08);
            --sa-card-radius: 30px;
            --sa-control-radius: 16px;
            --sa-control-height: 56px;
            --sa-focus: 0 0 0 4px rgba(23, 125, 81, .15);
        }
        body {
            color: var(--sa-ink);
            background:
                radial-gradient(circle at top left, rgba(23, 125, 81, .12), transparent 34%),
                linear-gradient(180deg, #ffffff 0%, var(--sa-page) 55%, #eef8f1 100%);
            font-family: var(--sa-font);
        }
        .sa-register-header,
        .sa-auth,
        .sa-auth input,
        .sa-auth select,
        .sa-auth textarea,
        .sa-auth button {
            font-family: var(--sa-font);
        }
        .sa-register-header__announce {
            background: var(--sa-soft-green);
            color: var(--sa-ink);
        }
        .sa-register-header__bar {
            border-bottom-color: rgba(220, 234, 226, .78);
        }
        .sa-register-header__links a,
        .sa-register-header__btn {
            color: var(--sa-primary);
        }
        .sa-register-header__cart,
        .sa-register-header__btn,
        .sa-register-header__menu {
            border-color: var(--sa-border);
            border-radius: 999px;
        }
        .sa-register-header__btn--solid {
            border-color: var(--sa-primary);
            background: var(--sa-primary);
            color: #fff;
        }
        .sa-auth {
            width: min(1220px, calc(100% - 48px));
            min-height: auto;
            margin: clamp(28px, 4vw, 58px) auto;
            padding: 0;
            grid-template-columns: minmax(320px, .82fr) minmax(0, 1.18fr);
            gap: 0;
            align-items: stretch;
            overflow: hidden;
            border: 1px solid rgba(220, 234, 226, .95);
            border-radius: var(--sa-card-radius);
            background: rgba(255, 255, 255, .92);
            box-shadow: var(--sa-shadow);
            backdrop-filter: blur(18px);
        }
        .sa-auth__aside {
            order: -1;
            position: relative;
            top: auto;
            padding: clamp(28px, 4vw, 48px);
            background:
                radial-gradient(circle at 15% 15%, rgba(255,255,255,.34), transparent 28%),
                linear-gradient(145deg, #177D51 0%, #0f6642 58%, #0b4d34 100%);
            color: #fff;
        }
        .sa-auth__aside::after {
            content: "";
            position: absolute;
            inset: auto -18% -22% 20%;
            height: 260px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .08);
            pointer-events: none;
        }
        .sa-auth__aside-inner {
            position: relative;
            z-index: 1;
            display: flex;
            min-height: 100%;
            flex-direction: column;
            justify-content: space-between;
            gap: 24px;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
        }
        .sa-auth__brand {
            display: none;
        }
        .sa-auth__aside-brand {
            display: inline-flex;
            width: fit-content;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            color: #fff;
            font-weight: 900;
        }
        .sa-auth__aside-brand img {
            width: auto;
            max-width: 174px;
            max-height: 60px;
            object-fit: contain;
            filter: drop-shadow(0 8px 20px rgba(0,0,0,.14));
        }
        .sa-auth__parcel {
            margin: 0;
            border-radius: 24px;
            background: rgba(255,255,255,.12);
            box-shadow: 0 18px 48px rgba(0, 0, 0, .18);
        }
        .sa-auth__aside h2 {
            color: #fff;
            font-size: clamp(32px, 4vw, 48px);
            line-height: .98;
            font-weight: 900;
        }
        .sa-auth__aside p {
            color: rgba(255,255,255,.82);
            font-size: 16px;
            line-height: 1.65;
        }
        .sa-auth__points {
            gap: 10px;
        }
        .sa-auth__point {
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 18px;
            background: rgba(255,255,255,.10);
        }
        .sa-auth__check {
            background: #fff;
            color: var(--sa-primary);
        }
        .sa-auth__point strong {
            color: #fff;
            font-family: var(--sa-font);
            font-weight: 800;
        }
        .sa-auth__point span {
            color: rgba(255,255,255,.76);
        }
        .sa-auth__trust {
            display: grid;
            gap: 8px;
            padding: 16px;
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 20px;
            background: rgba(255,255,255,.12);
            color: rgba(255,255,255,.86);
            font-size: 14px;
            line-height: 1.45;
        }
        .sa-auth__trust strong {
            color: #fff;
            font-size: 15px;
        }
        .sa-auth__form {
            padding: clamp(28px, 4vw, 54px);
            border: 0;
            border-radius: 0;
            background: var(--sa-surface);
            box-shadow: none;
        }
        .sa-auth h1,
        .sa-auth h2,
        .sa-auth h3 {
            color: var(--sa-ink);
            font-family: var(--sa-font);
            letter-spacing: 0;
        }
        .sa-auth .sa-auth__aside h2 {
            color: #fff;
        }
        .sa-auth h1 {
            max-width: 620px;
            font-size: clamp(34px, 4vw, 52px);
            line-height: .98;
            font-weight: 900;
        }
        .sa-auth__kicker,
        .sa-auth__login,
        .sa-vendor-step__note,
        .sa-auth__field small,
        .sa-auth__terms {
            color: var(--sa-muted);
        }
        .sa-auth__login a,
        .sa-auth__terms a {
            color: var(--sa-primary);
            font-weight: 800;
        }
        .sa-auth__switch {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            padding: 5px;
            border: 1px solid var(--sa-border);
            border-radius: 18px;
            background: var(--sa-soft-green);
        }
        .sa-auth__switch a {
            min-height: 46px;
            border: 0;
            border-radius: 14px;
            color: var(--sa-muted);
            background: transparent;
        }
        .sa-auth__switch a.is-active {
            color: #fff;
            background: var(--sa-primary);
            box-shadow: var(--sa-shadow-soft);
        }
        .sa-vendor-steps {
            gap: 12px;
            margin: 22px 0 24px;
        }
        .sa-vendor-steps::before,
        .sa-vendor-steps::after {
            display: none;
        }
        .sa-vendor-steps__item {
            min-height: 54px;
            justify-content: flex-start;
            gap: 10px;
            padding: 10px 12px;
            border-color: var(--sa-border);
            border-radius: 18px;
            color: var(--sa-muted);
            background: #fff;
            box-shadow: 0 8px 18px rgba(22, 55, 42, .04);
        }
        .sa-vendor-steps__num {
            width: 30px;
            height: 30px;
            background: var(--sa-soft-green);
            color: var(--sa-primary);
            font-weight: 900;
        }
        .sa-vendor-steps__item.is-active {
            border-color: rgba(23, 125, 81, .32);
            background: var(--sa-soft-green);
            color: var(--sa-ink);
            transform: none;
            box-shadow: 0 12px 26px rgba(23, 125, 81, .10);
        }
        .sa-vendor-steps__item.is-active .sa-vendor-steps__num,
        .sa-vendor-steps__item.is-complete .sa-vendor-steps__num {
            background: var(--sa-primary);
            color: #fff;
        }
        .sa-vendor-steps__item.is-complete {
            border-color: rgba(23, 125, 81, .24);
            background: #fff;
            color: var(--sa-primary);
        }
        .sa-vendor-step h2 {
            font-size: clamp(24px, 3vw, 34px);
            font-weight: 900;
        }
        .sa-auth__grid {
            gap: 18px;
        }
        .sa-auth label {
            color: var(--sa-ink);
            font-size: 14px;
            font-weight: 800;
        }
        .sa-auth input,
        .sa-auth select,
        .sa-auth textarea {
            min-height: var(--sa-control-height);
            border-color: var(--sa-border);
            border-radius: var(--sa-control-radius);
            background: #fff;
            color: var(--sa-ink);
            padding-inline: 16px;
            font: 650 16px/1.35 var(--sa-font);
        }
        .sa-auth textarea {
            min-height: 128px;
            padding-top: 15px;
        }
        .sa-auth input:hover,
        .sa-auth select:hover,
        .sa-auth textarea:hover {
            border-color: rgba(23, 125, 81, .45);
        }
        .sa-auth input:focus,
        .sa-auth select:focus,
        .sa-auth textarea:focus,
        .sa-auth button:focus-visible,
        .sa-auth a:focus-visible,
        .sa-register-header a:focus-visible,
        .sa-register-header button:focus-visible {
            outline: 0;
            border-color: var(--sa-primary);
            box-shadow: var(--sa-focus);
        }
        .sa-field-error {
            display: none;
            color: var(--sa-error);
            font-size: 13px;
            font-weight: 750;
            line-height: 1.35;
        }
        .sa-auth__field.has-error input,
        .sa-auth__field.has-error select,
        .sa-auth__field.has-error textarea {
            border-color: var(--sa-error);
            box-shadow: 0 0 0 4px rgba(195, 59, 54, .1);
        }
        .sa-auth__field.has-error .sa-field-error {
            display: block;
        }
        .sa-password-help {
            margin: 0;
            color: var(--sa-muted);
            font-size: 13px;
            line-height: 1.45;
        }
        .sa-phone-row {
            display: grid;
            grid-template-columns: minmax(116px, .32fr) minmax(0, 1fr);
            gap: 10px;
        }
        .sa-auth__password button {
            right: 8px;
            min-height: 40px;
            padding: 0 10px;
            border-radius: 999px;
            color: var(--sa-primary);
        }
        .sa-auth__submit,
        .sa-vendor-action {
            border-radius: 16px;
            font-family: var(--sa-font);
            font-weight: 900;
        }
        .sa-auth__submit,
        .sa-vendor-action--primary {
            background: var(--sa-primary);
            box-shadow: 0 16px 34px rgba(23, 125, 81, .22);
        }
        .sa-auth__submit:hover,
        .sa-vendor-action--primary:hover {
            background: var(--sa-primary-hover);
        }
        .sa-vendor-action--ghost {
            border-color: var(--sa-border);
            color: var(--sa-primary);
            background: #fff;
        }
        .sa-auth__submit:disabled,
        .sa-vendor-action:disabled {
            opacity: .65;
            box-shadow: none;
        }
        .sa-vendor-error-summary,
        .sa-auth__alert,
        .sa-auth__inline-alert {
            border-radius: 18px;
        }
        .sa-vendor-error-summary {
            border-color: rgba(195, 59, 54, .28);
            background: #fff6f5;
            color: var(--sa-error);
        }
        .sa-auth__alert.is-success,
        .sa-auth__success {
            border-color: rgba(23, 125, 81, .25);
            background: var(--sa-soft-green);
            color: var(--sa-primary-hover);
        }
        @media (max-width: 980px) {
            .sa-auth {
                width: min(100% - 28px, 720px);
                grid-template-columns: 1fr;
                overflow: visible;
            }
            .sa-auth__aside {
                padding: 28px;
                border-radius: var(--sa-card-radius) var(--sa-card-radius) 0 0;
            }
            .sa-auth__aside-inner {
                display: grid;
                grid-template-columns: 96px minmax(0, 1fr);
                align-items: center;
            }
            .sa-auth__aside-brand,
            .sa-auth__trust {
                grid-column: 1 / -1;
            }
            .sa-auth__aside h2 {
                font-size: 30px;
            }
            .sa-auth__parcel {
                width: 96px;
            }
            .sa-auth__points {
                display: none;
            }
            .sa-auth__form {
                border-radius: 0 0 var(--sa-card-radius) var(--sa-card-radius);
            }
        }
        @media (max-width: 620px) {
            .sa-register-header__nav {
                width: calc(100% - 24px);
            }
            .sa-auth {
                width: calc(100% - 22px);
                margin: 18px auto 32px;
                border-radius: 24px;
            }
            .sa-auth__aside {
                padding: 22px;
            }
            .sa-auth__aside-inner {
                grid-template-columns: 1fr;
            }
            .sa-auth__parcel {
                display: none;
            }
            .sa-auth__form {
                padding: 24px 18px 26px;
            }
            .sa-auth h1 {
                font-size: 31px;
            }
            .sa-auth__switch,
            .sa-auth__grid {
                grid-template-columns: 1fr;
            }
            .sa-vendor-steps {
                display: grid;
                grid-template-columns: 1fr;
                overflow: visible;
            }
            .sa-vendor-steps__item {
                min-width: 0;
            }
            .sa-phone-row {
                grid-template-columns: 1fr;
            }
            .sa-vendor-actions {
                flex-direction: column-reverse;
            }
        }

        /* FreshRoots-inspired registration template */
        :root {
            --fr-deep-forest: #0D3D25;
            --fr-forest: #1B4332;
            --fr-gold: #C9922A;
            --fr-gold-light: #E0B563;
            --fr-cream: #FAF6ED;
            --fr-soil: #6B4226;
            --fr-ink: #1A1A1A;
            --fr-muted: #5f5a50;
            --fr-line: rgba(13, 61, 37, .15);
            --fr-line-light: rgba(255, 255, 255, .16);
            --fr-serif: "Fraunces", Georgia, serif;
            --fr-sans: "Work Sans", "Urbanist", system-ui, sans-serif;
        }

        body {
            background: var(--fr-cream);
            color: var(--fr-ink);
            font-family: var(--fr-sans);
        }

        .sa-register-header,
        .sa-auth,
        .sa-auth input,
        .sa-auth select,
        .sa-auth textarea,
        .sa-auth button {
            font-family: var(--fr-sans);
        }

        .sa-register-header {
            background: var(--fr-deep-forest);
            color: var(--fr-cream);
        }

        .sa-register-header__announce {
            min-height: 40px;
            background: rgba(201, 146, 42, .12);
            color: var(--fr-gold-light);
            font-weight: 600;
        }

        .sa-register-header__bar {
            position: relative;
            background: var(--fr-deep-forest);
            border-bottom: 1px solid var(--fr-line-light);
            backdrop-filter: none;
        }

        .sa-register-header__nav {
            width: min(1180px, calc(100% - 64px));
            min-height: 76px;
        }

        .sa-register-header__links {
            gap: 32px;
            font-weight: 500;
        }

        .sa-register-header__links a {
            color: rgba(250, 246, 237, .86);
            border-bottom: 1px solid transparent;
            padding-bottom: 3px;
        }

        .sa-register-header__links a:hover {
            color: var(--fr-cream);
            border-color: var(--fr-gold);
        }

        .sa-register-header__cart,
        .sa-register-header__btn,
        .sa-register-header__menu {
            border-color: rgba(250, 246, 237, .22);
            background: transparent;
            color: var(--fr-cream);
            border-radius: 3px;
        }

        .sa-register-header__btn--solid,
        .sa-register-header__cart span {
            border-color: var(--fr-gold);
            background: var(--fr-gold);
            color: var(--fr-deep-forest);
        }

        .sa-auth {
            width: min(1180px, calc(100% - 64px));
            margin: 0 auto;
            padding: 72px 0 100px;
            display: grid;
            grid-template-columns: minmax(280px, .78fr) minmax(0, 1.35fr);
            gap: 64px;
            align-items: start;
            overflow: visible;
            border: 0;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
            backdrop-filter: none;
        }

        .sa-auth::before {
            content: "";
            grid-column: 1 / -1;
            display: block;
            min-height: 1px;
            margin-top: -1px;
        }

        .sa-auth__aside {
            position: sticky;
            top: 32px;
            order: -1;
            padding: 0;
            color: var(--fr-cream);
            background: transparent;
        }

        .sa-auth__aside::after {
            display: none;
        }

        .sa-auth__aside-inner {
            display: block;
            min-height: 0;
            padding: 32px;
            border: 0;
            border-radius: 4px;
            background: var(--fr-forest);
            box-shadow: none;
        }

        .sa-auth__aside-brand {
            margin-bottom: 24px;
            color: var(--fr-cream);
            font-family: var(--fr-serif);
            font-size: 1.18rem;
            font-weight: 600;
        }

        .sa-auth__aside-brand img {
            max-width: 156px;
            max-height: 58px;
            filter: drop-shadow(0 8px 18px rgba(0,0,0,.16));
        }

        .sa-auth__parcel {
            margin: 0 0 28px;
            border-radius: 4px;
            box-shadow: none;
            background: rgba(250, 246, 237, .08);
        }

        .sa-auth__aside h2 {
            color: var(--fr-cream);
            font-family: var(--fr-serif);
            font-size: clamp(2rem, 3vw, 2.7rem);
            font-weight: 600;
            line-height: 1.08;
        }

        .sa-auth__aside p {
            margin: 18px 0 24px;
            color: rgba(250, 246, 237, .84);
            font-size: .96rem;
            line-height: 1.65;
        }

        .sa-auth__points {
            gap: 0;
            border-top: 1px solid var(--fr-line-light);
        }

        .sa-auth__point {
            grid-template-columns: 24px minmax(0, 1fr);
            gap: 12px;
            padding: 14px 0;
            border: 0;
            border-bottom: 1px solid var(--fr-line-light);
            border-radius: 0;
            background: transparent;
        }

        .sa-auth__check {
            width: 24px;
            height: 24px;
            color: var(--fr-gold-light);
            background: transparent;
        }

        .sa-auth__point strong {
            color: var(--fr-cream);
            font-family: var(--fr-sans);
            font-size: .92rem;
            font-weight: 600;
        }

        .sa-auth__point span {
            color: rgba(250, 246, 237, .72);
            font-size: .86rem;
        }

        .sa-auth__trust {
            margin-top: 24px;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            color: rgba(250, 246, 237, .66);
            font-size: .84rem;
        }

        .sa-auth__trust strong {
            color: var(--fr-gold-light);
            font-size: .88rem;
        }

        .sa-auth__form {
            padding: 44px;
            border: 1px solid var(--fr-line);
            border-radius: 4px;
            background: #fff;
            box-shadow: none;
        }

        .sa-auth h1,
        .sa-auth h2,
        .sa-auth h3 {
            color: var(--fr-deep-forest);
            font-family: var(--fr-serif);
            font-weight: 600;
            letter-spacing: -.01em;
        }

        .sa-auth h1 {
            max-width: 680px;
            font-size: clamp(2rem, 4vw, 2.65rem);
            line-height: 1.08;
        }

        .sa-auth__kicker,
        .sa-auth__login,
        .sa-vendor-step__note,
        .sa-auth__field small,
        .sa-auth__terms,
        .sa-password-help {
            color: #555;
        }

        .sa-auth__login a,
        .sa-auth__terms a {
            color: var(--fr-soil);
            font-weight: 600;
        }

        .sa-auth__switch {
            margin: 34px 0;
            padding: 0;
            border: 1px solid var(--fr-line);
            border-radius: 4px;
            background: var(--fr-cream);
        }

        .sa-auth__switch a {
            min-height: 48px;
            border-radius: 3px;
            color: var(--fr-deep-forest);
            font-weight: 600;
        }

        .sa-auth__switch a.is-active {
            background: var(--fr-gold);
            color: var(--fr-deep-forest);
            box-shadow: none;
        }

        .sa-vendor-steps {
            gap: 10px;
            margin: 24px 0 30px;
        }

        .sa-vendor-steps__item {
            min-height: 46px;
            border-color: var(--fr-line);
            border-radius: 4px;
            color: var(--fr-muted);
            background: #fff;
            box-shadow: none;
        }

        .sa-vendor-steps__num {
            background: var(--fr-cream);
            color: var(--fr-soil);
        }

        .sa-vendor-steps__item.is-active,
        .sa-vendor-steps__item.is-complete {
            border-color: var(--fr-gold);
            background: var(--fr-cream);
            color: var(--fr-deep-forest);
            box-shadow: none;
        }

        .sa-vendor-steps__item.is-active .sa-vendor-steps__num,
        .sa-vendor-steps__item.is-complete .sa-vendor-steps__num {
            background: var(--fr-gold);
            color: var(--fr-deep-forest);
        }

        .sa-vendor-step h2 {
            color: var(--fr-deep-forest);
            font-family: var(--fr-serif);
            font-size: 1.55rem;
        }

        .sa-vendor-step__note {
            margin-bottom: 28px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--fr-line);
        }

        .sa-auth__grid {
            gap: 20px;
        }

        .sa-auth label {
            color: var(--fr-ink);
            font-size: .86rem;
            font-weight: 500;
        }

        .sa-auth input,
        .sa-auth select,
        .sa-auth textarea {
            min-height: 50px;
            border-color: #d8d2c4;
            border-radius: 3px;
            background: var(--fr-cream);
            color: var(--fr-ink);
            font: 500 .95rem/1.35 var(--fr-sans);
        }

        .sa-auth input:hover,
        .sa-auth select:hover,
        .sa-auth textarea:hover {
            border-color: rgba(201, 146, 42, .7);
        }

        .sa-auth input:focus,
        .sa-auth select:focus,
        .sa-auth textarea:focus,
        .sa-auth button:focus-visible,
        .sa-auth a:focus-visible,
        .sa-register-header a:focus-visible,
        .sa-register-header button:focus-visible {
            border-color: var(--fr-gold);
            box-shadow: 0 0 0 4px rgba(201, 146, 42, .14);
        }

        .sa-auth__password button {
            color: var(--fr-soil);
        }

        .sa-auth__submit,
        .sa-vendor-action {
            min-height: 52px;
            border-radius: 3px;
            font-family: var(--fr-sans);
            font-weight: 600;
        }

        .sa-auth__submit,
        .sa-vendor-action--primary {
            background: var(--fr-gold);
            color: var(--fr-deep-forest);
            box-shadow: none;
        }

        .sa-auth__submit:hover,
        .sa-vendor-action--primary:hover {
            background: var(--fr-gold-light);
        }

        .sa-vendor-action--ghost {
            border-color: var(--fr-line);
            color: var(--fr-deep-forest);
            background: #fff;
        }

        .sa-auth__alert,
        .sa-auth__inline-alert,
        .sa-vendor-error-summary {
            border-radius: 4px;
        }

        .sa-field-error {
            color: #A33A2E;
        }

        .sa-auth__field.has-error input,
        .sa-auth__field.has-error select,
        .sa-auth__field.has-error textarea {
            border-color: #A33A2E;
            box-shadow: 0 0 0 4px rgba(163, 58, 46, .10);
        }

        @media (max-width: 980px) {
            .sa-auth {
                width: min(100% - 48px, 760px);
                grid-template-columns: 1fr;
                gap: 32px;
                padding: 48px 0 72px;
            }

            .sa-auth__aside {
                position: static;
            }

            .sa-auth__aside-inner {
                display: block;
            }

            .sa-auth__parcel {
                display: none;
            }
        }

        @media (max-width: 620px) {
            .sa-register-header__nav {
                width: calc(100% - 28px);
            }

            .sa-auth {
                width: calc(100% - 28px);
                margin: 0 auto;
                padding: 32px 0 58px;
                border-radius: 0;
            }

            .sa-auth__aside-inner,
            .sa-auth__form {
                padding: 24px;
            }

            .sa-auth h1 {
                font-size: 2rem;
            }

            .sa-auth__switch,
            .sa-auth__grid,
            .sa-phone-row {
                grid-template-columns: 1fr;
            }

            .sa-vendor-steps {
                display: grid;
                grid-template-columns: 1fr;
                overflow: visible;
            }
        }

        /* Desktop visibility guard: keep the shared registration shell in a real two-column row. */
        @media (min-width: 981px) {
            .sa-auth::before {
                display: none;
                content: none;
            }

            .sa-auth {
                display: grid;
                grid-template-columns: minmax(300px, .78fr) minmax(0, 1.35fr);
                grid-auto-flow: row;
                align-items: start;
                min-height: auto;
                overflow: visible;
            }

            .sa-auth__aside {
                grid-column: 1;
                grid-row: 1;
                order: 0;
                align-self: start;
                visibility: visible;
                opacity: 1;
            }

            .sa-auth__form {
                grid-column: 2;
                grid-row: 1;
                order: 0;
                align-self: start;
                visibility: visible;
                opacity: 1;
            }
        }

        @media (max-width: 980px) {
            .sa-auth__aside,
            .sa-auth__form {
                grid-column: auto;
                grid-row: auto;
            }
        }
    </style>
</head>
<body>
<div class="sa-register-header" id="sa-register-header">
    <div class="sa-register-header__announce"><strong>Free shipping on your first order from our U.S. warehouse</strong></div>
    <header class="sa-register-header__bar">
        <nav class="sa-register-header__nav">
            <a class="sa-register-header__logo" href="<?= e(app_url('store')) ?>" aria-label="<?= e($brandName) ?> home">
                <?php if (!empty($brand['logo'])): ?><img src="<?= e((string)$brand['logo']) ?>" alt="<?= e($brandName) ?>"><?php else: ?><strong><?= e($brandName) ?></strong><?php endif; ?>
            </a>
            <div class="sa-register-header__links" id="sa-register-menu">
                <a href="<?= e(app_url('store')) ?>">Home</a>
                <a href="<?= e(app_url('shop')) ?>">Marketplace</a>
                <a href="<?= e(app_url('vendors')) ?>">Our Vendors</a>
                <a href="<?= e(app_url('about')) ?>">About Us</a>
                <a href="<?= e(app_url('contact')) ?>">Contact Us</a>
            </div>
            <div class="sa-register-header__actions">
                <a class="sa-register-header__cart" href="<?= e(app_url('cart')) ?>" aria-label="Cart">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 8V6a5 5 0 0 1 10 0v2"/><path d="M5 8h14l-1 13H6L5 8Z"/></svg>
                    <span><?= number_format($authCartCount) ?></span>
                </a>
                <a class="sa-register-header__btn" href="<?= e(app_url('login')) ?>">Login</a>
                <a class="sa-register-header__btn sa-register-header__btn--solid" href="<?= e(app_url('buyer/register')) ?>">Register</a>
                <button class="sa-register-header__menu" type="button" aria-controls="sa-register-menu" aria-expanded="false" aria-label="Toggle navigation menu"><span></span></button>
            </div>
        </nav>
    </header>
</div>
<main class="sa-auth">
    <section class="sa-auth__form" aria-labelledby="register-title">
        <a class="sa-auth__brand" href="<?= e(app_url('store')) ?>">
            <?php if (!empty($brand['logo'])): ?><img src="<?= e((string)$brand['logo']) ?>" alt="<?= e($brandName) ?>"><?php else: ?><strong><?= e($brandName) ?></strong><?php endif; ?>
        </a>
        <h1 id="register-title"><?= e($active['title']) ?></h1>
        <p class="sa-auth__kicker"><?= e($active['kicker']) ?></p>
        <?php if (empty($isLoggedIn)): ?>
            <p class="sa-auth__login">Already have an account? <a href="<?= e(app_url('login')) ?>">Log in here</a></p>
        <?php else: ?>
            <p class="sa-auth__login">You are already logged in. <a href="<?= e($dashboardUrl) ?>">Click here to go to your dashboard</a>.</p>
        <?php endif; ?>

        <?php if ($accountType !== 'customer'): ?>
            <div class="sa-auth__switch" aria-label="Registration options">
                <?php foreach ($links as $type => $link): ?>
                    <a class="<?= $accountType === $type ? 'is-active' : '' ?>" href="<?= e($link['url']) ?>"><?= e($link['label']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($toasts)): ?>
            <div class="sa-auth__alerts" aria-live="polite">
                <?php foreach ($toasts as $toast): ?>
                    <?php
                    $toastMessage = (string)($toast['message'] ?? '');
                    $signInCallout = ' Click here to sign in.';
                    $loginDashboardCallout = ' Click here to log in to your dashboard.';
                    $dashboardCallout = ' Click here to go to your dashboard.';
                    $hasSignInCallout = str_ends_with($toastMessage, $signInCallout);
                    $hasLoginDashboardCallout = str_ends_with($toastMessage, $loginDashboardCallout);
                    $hasDashboardCallout = str_ends_with($toastMessage, $dashboardCallout);
                    ?>
                    <div class="sa-auth__alert <?= ($toast['type'] ?? '') === 'success' ? 'is-success' : '' ?>">
                        <?php if ($hasSignInCallout): ?>
                            <?= e(substr($toastMessage, 0, -strlen($signInCallout))) ?>
                            <a href="<?= e(app_url('login')) ?>">Click here to sign in</a>.
                        <?php elseif ($hasLoginDashboardCallout): ?>
                            <?= e(substr($toastMessage, 0, -strlen($loginDashboardCallout))) ?>
                            <a href="<?= e(app_url('login')) ?>">Click here to log in to your dashboard</a>.
                        <?php elseif ($hasDashboardCallout): ?>
                            <?= e(substr($toastMessage, 0, -strlen($dashboardCallout))) ?>
                            <a href="<?= e($dashboardUrl) ?>">Click here to go to your dashboard</a>.
                        <?php else: ?>
                            <?= e($toastMessage) ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e($formAction) ?>" autocomplete="on" enctype="multipart/form-data" data-vendor-form="0">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <input type="hidden" name="started_at" value="<?= e((string)time()) ?>">
            <input type="hidden" name="account_type" value="<?= e($accountType) ?>">
            <?php if ($accountType === 'vendor'): ?>
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= e((string)$maxUploadBytes) ?>">
            <?php endif; ?>
            <div class="sa-auth__hidden" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>

            <?php if ($accountType === 'vendor'): ?>
                <?php require __DIR__ . '/vendor-signup-fields.php'; ?>
            <?php else: ?>
            <div class="sa-auth__grid">
                <?php if (empty($isLoggedIn)): ?>
                    <label class="sa-auth__field">First Name<input name="first_name" required placeholder="Enter your first name" value="<?= e($_POST['first_name'] ?? '') ?>"></label>
                    <label class="sa-auth__field">Last Name<input name="last_name" required placeholder="Enter your last name" value="<?= e($_POST['last_name'] ?? '') ?>"></label>
                    <label class="sa-auth__field">Email address<input type="email" name="email" required placeholder="your@example.com" value="<?= e($_POST['email'] ?? '') ?>"></label>
                    <div class="sa-auth__field">
                        <label for="account_phone">Phone Number</label>
                        <div class="sa-phone-row">
                            <select name="country_code" aria-label="Phone country code">
                                <?php foreach (['US' => '+1', 'NG' => '+234', 'GB' => '+44', 'CA' => '+1', 'GH' => '+233', 'KE' => '+254', 'ZA' => '+27'] as $code => $dial): ?>
                                    <option value="<?= e($code) ?>" <?= (string)($_POST['country_code'] ?? '') === $code ? 'selected' : '' ?>><?= e($dial . ' ' . $code) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input id="account_phone" type="tel" inputmode="tel" name="phone" placeholder="8012345678" value="<?= e($_POST['phone'] ?? '') ?>">
                        </div>
                    </div>
                <?php else: ?>
                    <div class="sa-auth__field is-full">
                        <div class="sa-auth__alert" style="background: #f0fdf4; border-color: #bbf7d0; color: #166534; font-weight: normal;">
                            <strong>Logged in as <?= e($loggedInUser['display_name'] ?? $loggedInUser['first_name'] ?? 'User') ?> (<?= e($loggedInUser['email'] ?? '') ?>)</strong><br>
                            Welcome back. We will use your existing account email, but you still need to complete and submit the full vendor application below.
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($accountType === 'vendor'): ?>
                    <label class="sa-auth__field is-full">Store name<input name="store_name" required placeholder="Enter your store name" value="<?= e($_POST['store_name'] ?? '') ?>"></label>
                <?php endif; ?>
                <?php if ($accountType === 'affiliate'): ?>
                    <label class="sa-auth__field is-full">Affiliate payment email<input type="email" name="payment_email" placeholder="Payment email address" value="<?= e($_POST['payment_email'] ?? '') ?>"></label>
                <?php endif; ?>
                <?php if (empty($isLoggedIn)): ?>
                    <label class="sa-auth__field">Password<span class="sa-auth__password"><input id="password" type="password" name="password" required minlength="5" autocomplete="new-password" placeholder="Enter your password" aria-describedby="account-password-help"><button type="button" data-password-toggle="password">Show</button></span><span id="account-password-help" class="sa-password-help">Use at least 5 characters. A mix of letters and numbers is recommended.</span></label>
                    <label class="sa-auth__field">Confirm new password<span class="sa-auth__password"><input id="password_confirmation" type="password" name="password_confirmation" required minlength="5" autocomplete="new-password" placeholder="Confirm your password"><button type="button" data-password-toggle="password_confirmation">Show</button></span></label>
                <?php endif; ?>
                <label class="sa-auth__field is-full">Post code<input name="postcode" placeholder="Enter your postcode" value="<?= e($_POST['postcode'] ?? '') ?>"></label>
                <label class="sa-auth__field">City<input name="city" placeholder="Enter your city" value="<?= e($_POST['city'] ?? '') ?>"></label>
                <label class="sa-auth__field">Address<input name="address_line1" placeholder="Enter your street address" value="<?= e($_POST['address_line1'] ?? '') ?>"></label>
                <label class="sa-auth__field is-full">Referral Code (Optional)<input name="referral_code" placeholder="Enter referral code if you have one" value="<?= e($_POST['referral_code'] ?? ($referralInput ?? $_GET['ref'] ?? '')) ?>"></label>
                <?php if (!empty($captchaRequired) && !empty($captchaChallenge)): ?>
                    <label class="sa-auth__field is-full">Security check: <?= e((string)$captchaChallenge['question']) ?><input name="captcha_answer" inputmode="numeric" required></label>
                <?php endif; ?>
                <label class="sa-auth__terms">
                    <input type="checkbox" name="terms_consent" value="1" required <?= !empty($_POST['terms_consent']) ? 'checked' : '' ?>>
                    <span>
                        <?= e($termsLabel) ?>
                        <?php if ($accountType === 'vendor'): ?>
                            <a href="<?= e(app_url('vendor-agreement')) ?>" target="_blank" rel="noopener noreferrer">Vendor Terms</a>,
                            <a href="<?= e(app_url('terms')) ?>" target="_blank" rel="noopener noreferrer">Marketplace Terms</a>, and
                            <a href="<?= e(app_url('privacy-policy')) ?>" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.
                        <?php elseif ($accountType === 'affiliate'): ?>
                            <a href="<?= e(app_url('partner-agreement')) ?>" target="_blank" rel="noopener noreferrer">Affiliate Terms</a>,
                            <a href="<?= e(app_url('terms')) ?>" target="_blank" rel="noopener noreferrer">Marketplace Terms</a>, and
                            <a href="<?= e(app_url('privacy-policy')) ?>" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.
                        <?php else: ?>
                            <a href="<?= e(app_url('terms')) ?>" target="_blank" rel="noopener noreferrer">Terms</a> and
                            <a href="<?= e(app_url('privacy-policy')) ?>" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.
                        <?php endif; ?>
                    </span>
                </label>
                <button class="sa-auth__submit" type="submit" data-loading-text="Creating account..."><?= e($active['button']) ?></button>
            </div>
            <?php endif; ?>
        </form>
    </section>

    <aside class="sa-auth__aside">
        <div class="sa-auth__aside-inner">
            <a class="sa-auth__aside-brand" href="<?= e(app_url('store')) ?>">
                <?php if (!empty($brand['logo'])): ?><img src="<?= e((string)$brand['logo']) ?>" alt="<?= e($brandName) ?>"><?php else: ?><strong><?= e($brandName) ?></strong><?php endif; ?>
            </a>
            <?php if ($accountType === 'vendor' || $accountType === 'affiliate'): ?>
                <div class="sa-auth__parcel sa-auth__parcel--image" aria-hidden="true">
                    <img src="<?= e(app_url($accountType === 'vendor' ? 'assets/images/iamavendor.jpeg' : 'assets/images/affiliate-reg.jpeg')) ?>" alt="">
                </div>
            <?php endif; ?>
            <h2><?= e($active['asideTitle']) ?></h2>
            <p><?= e($active['asideText']) ?></p>
            <div class="sa-auth__points">
                <?php foreach ($active['points'] as $point): ?>
                    <div class="sa-auth__point"><span class="sa-auth__check">✓</span><div><strong><?= e($point[0]) ?></strong><span><?= e($point[1]) ?></span></div></div>
                <?php endforeach; ?>
            </div>
            <div class="sa-auth__trust">
                <strong>Secure application</strong>
                <span>Your information is protected and reviewed only by the Seller Africa team for account setup and verification.</span>
            </div>
        </div>
    </aside>
</main>
<script>
(() => {
  const root = document.getElementById('sa-register-header');
  const menu = root?.querySelector('.sa-register-header__menu');
  if (!root || !menu) return;
  menu.addEventListener('click', () => {
    const open = root.classList.toggle('is-menu-open');
    menu.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
})();

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
  button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.passwordToggle || '');
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.textContent = show ? 'Hide' : 'Show';
  });
});

const describeField = (field) => {
  const container = field.closest('.sa-auth__field') || field.closest('label');
  const label = container?.querySelector('label') || field.closest('label');
  return (label?.childNodes[0]?.textContent || label?.textContent || field.name || 'This field').replace('*', '').trim();
};

const validationMessageFor = (field) => {
  const label = describeField(field);
  if (field.validity.valueMissing) return `${label} is required.`;
  if (field.validity.typeMismatch && field.type === 'email') return 'Enter a valid email address.';
  if (field.validity.typeMismatch && field.type === 'url') return 'Enter a valid URL.';
  if (field.validity.tooShort) return `${label} must be at least ${field.minLength} characters.`;
  if (field.validity.rangeUnderflow) return `${label} must be at least ${field.min}.`;
  if (field.validity.rangeOverflow) return `${label} must be no more than ${field.max}.`;
  if (field.validity.badInput) return `${label} must be a valid number.`;
  if (field.validity.stepMismatch) return `Enter a valid increment of ${field.step} for ${label}.`;
  if (field.validity.patternMismatch) return `${label} has an invalid format.`;
  return field.validationMessage || `${label} is invalid.`;
};

const fieldContainer = (field) => field.closest('.sa-auth__field') || field.closest('label');

const ensureFieldError = (field) => {
  const container = fieldContainer(field);
  if (!container) return null;
  let error = container.querySelector('.sa-field-error');
  if (!error) {
    error = document.createElement('span');
    error.className = 'sa-field-error';
    error.setAttribute('aria-live', 'polite');
    container.append(error);
  }
  if (!field.id) {
    field.id = `field-${Math.random().toString(36).slice(2)}`;
  }
  error.id = `${field.id}-error`;
  field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
  return error;
};

const setFieldError = (field, message = '') => {
  const container = fieldContainer(field);
  const error = ensureFieldError(field);
  if (!container || !error) return;
  const hasError = message !== '';
  container.classList.toggle('has-error', hasError);
  field.setAttribute('aria-invalid', hasError ? 'true' : 'false');
  error.textContent = message;
};

document.querySelectorAll('.sa-auth input, .sa-auth select, .sa-auth textarea').forEach((field) => {
  if (field.type === 'tel') {
    const validatePhone = () => {
      const value = field.value.trim();
      const digits = value.replace(/\D/g, '');
      field.setCustomValidity(value && (digits.length < 7 || digits.length > 15)
        ? 'Enter a phone number with 7 to 15 digits, for example +2348012345678.' : '');
    };
    field.addEventListener('input', validatePhone);
    field.addEventListener('change', validatePhone);
    validatePhone();
  }
  field.addEventListener('invalid', () => setFieldError(field, validationMessageFor(field)));
  field.addEventListener('input', () => {
    if (field.checkValidity()) setFieldError(field);
  });
  field.addEventListener('change', () => {
    if (field.checkValidity()) setFieldError(field);
  });
});

document.querySelectorAll('form').forEach((form) => {
  form.addEventListener('submit', (event) => {
    if (form.dataset.isSubmitting === '1') {
      event.preventDefault();
      return;
    }

    const invalidFields = Array.from(form.querySelectorAll('input, select, textarea')).filter((field) => !field.checkValidity());
    invalidFields.forEach((field) => setFieldError(field, validationMessageFor(field)));
    if (invalidFields.length > 0) {
      event.preventDefault();
      return;
    }

    form.dataset.isSubmitting = '1';
    form.querySelectorAll('button[type="submit"]').forEach((button) => {
      button.dataset.originalText = button.textContent || '';
      button.textContent = button.dataset.loadingText || 'Submitting...';
      button.classList.add('is-loading');
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
    });
    form.querySelectorAll('[data-step-next], [data-step-prev]').forEach((button) => {
      button.disabled = true;
    });
  });
});

const vendorForm = document.querySelector('[data-vendor-form="1"]');
if (vendorForm) {
  const steps = Array.from(vendorForm.querySelectorAll('[data-vendor-step]'));
  const dots = Array.from(vendorForm.querySelectorAll('[data-step-dot]'));
  const accountCheckAlert = vendorForm.querySelector('[data-account-check-alert]');
  const stepStatusCount = vendorForm.querySelector('[data-step-status-count]');
  const stepStatusName = vendorForm.querySelector('[data-step-status-name]');
  const errorSummary = vendorForm.querySelector('[data-vendor-error-summary]');
  const accountCheckUrl = '<?= e(app_url('api/register/vendor-account-check.php')) ?>';
  const currentUserLoggedIn = <?= !empty($isLoggedIn) ? 'true' : 'false' ?>;
  let activeStep = 0;
  const initialVendorStep = Math.max(0, Math.min(steps.length - 1, <?= (int)($initialVendorStep ?? 0) ?>));

  const fieldLabel = (field) => {
    return describeField(field) || 'the required field';
  };

  const showStepError = (message) => {
    if (!errorSummary) return;
    errorSummary.textContent = message;
    errorSummary.classList.add('is-visible');
    errorSummary.focus({ preventScroll: true });
  };

  const clearStepError = () => {
    if (!errorSummary) return;
    errorSummary.textContent = '';
    errorSummary.classList.remove('is-visible');
  };

  const showStep = (index) => {
    activeStep = Math.max(0, Math.min(index, steps.length - 1));
    steps.forEach((step, stepIndex) => {
      const isActive = stepIndex === activeStep;
      step.classList.toggle('is-active', isActive);
      step.hidden = !isActive;
      step.setAttribute('aria-hidden', isActive ? 'false' : 'true');
    });
    dots.forEach((dot, stepIndex) => {
      const isActive = stepIndex === activeStep;
      dot.classList.toggle('is-active', isActive);
      dot.classList.toggle('is-complete', stepIndex < activeStep);
      dot.setAttribute('aria-current', isActive ? 'step' : 'false');
    });
    const progress = steps.length > 1 ? (activeStep / (steps.length - 1)) * 86 : 0;
    vendorForm.querySelector('.sa-vendor-steps')?.style.setProperty('--step-progress', `${progress}%`);
    if (stepStatusCount) stepStatusCount.textContent = `Step ${activeStep + 1} of ${steps.length}`;
    if (stepStatusName) stepStatusName.textContent = dots[activeStep]?.dataset.stepLabel || '';
    clearStepError();
    const activePanel = steps[activeStep];
    const scrollTarget = activePanel || vendorForm;
    const offset = window.matchMedia('(max-width: 620px)').matches ? 96 : 32;
    window.scrollTo({ top: scrollTarget.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth' });
  };

  const currentStepIsValid = () => {
    const fields = Array.from(steps[activeStep].querySelectorAll('input, select, textarea'));
    const invalidFields = fields.filter((field) => !field.checkValidity());
    invalidFields.forEach((field) => setFieldError(field, validationMessageFor(field)));
    const invalid = invalidFields[0];
    if (invalid) {
      showStepError(validationMessageFor(invalid));
      invalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      invalid.focus({ preventScroll: true });
      return false;
    }
    clearStepError();
    return true;
  };

  const firstInvalidStep = () => {
    for (let index = 0; index < steps.length; index += 1) {
      const fields = Array.from(steps[index].querySelectorAll('input, select, textarea'));
      if (fields.some((field) => !field.checkValidity())) {
        return index;
      }
    }
    return -1;
  };

  const focusFirstInvalidInStep = (index) => {
    const step = steps[index];
    if (!step) return;
    const invalid = Array.from(step.querySelectorAll('input, select, textarea')).find((field) => !field.checkValidity());
    if (!invalid) return;
    setFieldError(invalid, validationMessageFor(invalid));
    showStepError(validationMessageFor(invalid));
    window.requestAnimationFrame(() => {
      invalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      invalid.focus({ preventScroll: true });
    });
  };

  vendorForm.addEventListener('submit', (event) => {
    const invalidStep = firstInvalidStep();
    if (invalidStep < 0) return;
    event.preventDefault();
    showStep(invalidStep);
    focusFirstInvalidInStep(invalidStep);
  });

  const showAccountCheckAlert = (message, loginUrl) => {
    if (!accountCheckAlert) return;
    const safeMessage = String(message || 'This email or phone number is already registered. Please sign in, then return to Apply as a Vendor to complete the full application.');
    accountCheckAlert.textContent = safeMessage;
    if (loginUrl) {
      accountCheckAlert.append(' ');
      const loginLink = document.createElement('a');
      loginLink.href = loginUrl;
      loginLink.textContent = 'Click here to login';
      accountCheckAlert.append(loginLink, '.');
    }
    accountCheckAlert.classList.add('is-visible');
    accountCheckAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    accountCheckAlert.focus({ preventScroll: true });
  };

  const clearAccountCheckAlert = () => {
    if (!accountCheckAlert) return;
    accountCheckAlert.textContent = '';
    accountCheckAlert.classList.remove('is-visible');
  };

  const vendorAccountCanContinue = async () => {
    if (currentUserLoggedIn) return true;
    if (activeStep !== 2) return true;
    clearAccountCheckAlert();

    const body = new URLSearchParams();
    body.set('csrf_token', vendorForm.querySelector('[name="csrf_token"]')?.value || '');
    body.set('email', vendorForm.querySelector('[name="email"]')?.value || '');
    body.set('phone', vendorForm.querySelector('[name="phone"]')?.value || '');

    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 15000);
    try {
      const response = await fetch(accountCheckUrl, {
        method: 'POST',
        signal: controller.signal,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body,
      });
      const data = await response.json();
      if (data?.csrf) {
        showAccountCheckAlert(data.message || 'Your registration session expired. Please refresh the page and try again.', data.login_url);
        return false;
      }
      if (data?.exists) {
        showAccountCheckAlert(data.message, data.login_url);
        return false;
      }
      if (!response.ok || data?.ok !== true) {
        showAccountCheckAlert(data.message || 'We could not check your account. Please try Next again.');
        return false;
      }
    } catch (error) {
      showAccountCheckAlert('We could not check your account. Check your connection and click Next again. Your details are still here.');
      return false;
    } finally {
      window.clearTimeout(timeout);
    }

    return true;
  };

  vendorForm.querySelectorAll('[data-step-next]').forEach((button) => {
    button.addEventListener('click', async () => {
      if (!currentStepIsValid()) return;
      const originalText = button.textContent;
      button.disabled = true;
      button.textContent = activeStep === 2 ? 'Checking...' : originalText;
      const canContinue = await vendorAccountCanContinue();
      button.disabled = false;
      button.textContent = originalText;
      if (canContinue) showStep(activeStep + 1);
    });
  });

  vendorForm.querySelectorAll('[data-step-prev]').forEach((button) => {
    button.addEventListener('click', () => showStep(activeStep - 1));
  });

  vendorForm.querySelectorAll('input[type="file"]').forEach((input) => {
    const preview = document.createElement('div');
    preview.className = 'sa-upload-preview';
    input.insertAdjacentElement('afterend', preview);

    input.addEventListener('change', async () => {
      const file = input.files && input.files[0] ? input.files[0] : null;
      preview.replaceChildren();
      input.setCustomValidity('');
      if (!file) {
        preview.classList.remove('is-visible');
        return;
      }

      const maxBytes = Number(input.dataset.maxBytes || 0);
      if (maxBytes > 0 && file.size > maxBytes) {
        const maxMb = Math.ceil(maxBytes / 1048576);
        input.setCustomValidity(`This file must be ${maxMb} MB or smaller.`);
        setFieldError(input, `This file must be ${maxMb} MB or smaller.`);
        input.reportValidity();
        preview.classList.remove('is-visible');
        return;
      }
      setFieldError(input);

      // Snapshot device uploads before temporary mobile file handles become unavailable.
      input.setCustomValidity('Please wait while your image is prepared.');
      preview.textContent = 'Preparing image...';
      preview.classList.add('is-visible');
      let preparedFile;
      try {
        const bytes = await file.arrayBuffer();
        if (input.files[0] !== file) return;
        preparedFile = new File([bytes], file.name, { type: file.type, lastModified: file.lastModified });
        const transfer = new DataTransfer();
        transfer.items.add(preparedFile);
        input.files = transfer.files;
        input.setCustomValidity('');
        setFieldError(input);
      } catch (error) {
        if (input.files[0] !== file) return;
        const message = 'This image could not be read. Save it to your device, then select it again.';
        input.setCustomValidity(message);
        setFieldError(input, message);
        preview.replaceChildren();
        preview.classList.remove('is-visible');
        return;
      }
      preview.replaceChildren();

      if (preparedFile.type.startsWith('image/')) {
        const img = document.createElement('img');
        img.alt = '';
        img.src = URL.createObjectURL(preparedFile);
        img.onload = () => URL.revokeObjectURL(img.src);
        preview.append(img);
      }

      const name = document.createElement('span');
      name.textContent = file.name;
      preview.append(name);
      preview.classList.add('is-visible');
    });
  });

  vendorForm.addEventListener('input', clearStepError);
  vendorForm.addEventListener('change', clearStepError);
  showStep(initialVendorStep);
}
</script>
</body>
</html>
