<?php
// Local-only fixture: no database, sessions, uploads or mail.
if (PHP_SAPI !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) { http_response_code(403); exit; }
require dirname(__DIR__) . '/app/services/VendorOnboardingService.php';
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function app_url(string $path): string { return 'https://sellerafrica.com/' . $path; }
function app_branding(): array { return ['name'=>'Seller Africa','logo'=>'https://sellerafrica.com/assets/images/iamavendor.jpeg']; }
function getCsrfToken(): string { return 'fixture-only'; }
function asset(string $path): string { return app_url('assets/' . $path); }
$vendor = ['id'=>1,'status'=>'pending','kyc_status'=>'not_started'];
$user = ['display_name'=>'Preview vendor'];
$application = []; $data=[]; $errors=[];
$step = max(0,min(2,(int)($_GET['step'] ?? 0)));
$pending = ($_GET['state'] ?? '') === 'pending';
$paymentReady = ($_GET['state'] ?? '') !== 'payment';
if (($_GET['state'] ?? '') === 'rejected') $vendor['status']='rejected';
if (isset($_GET['signup'])) {
    $accountType='vendor'; $vendorPackages=[['id'=>1,'name'=>'Free','price'=>0,'currency'=>'USD','billing_interval'=>'month','product_limit'=>5,'features'=>'["List up to 5 products"]'],['id'=>2,'name'=>'Basic','price'=>10,'currency'=>'USD','billing_interval'=>'month','product_limit'=>10,'features'=>'["List up to 10 products","Priority support"]']];
    $isLoggedIn=false; $paystackAvailable=true; $paymentProvider='stripe';
    require dirname(__DIR__) . '/app/views/auth/register.php';
} else {
    ob_start(); require dirname(__DIR__) . '/app/views/auth/vendor-verification.php'; $content=ob_get_clean();
    require dirname(__DIR__) . '/app/views/layouts/vendor-onboarding.php';
}
