<?php
if (PHP_SAPI !== 'cli') { exit(1); }
require __DIR__.'/../app/services/VendorOnboardingService.php';
require __DIR__.'/../app/services/ProductCategoryService.php';
function db() {
    return new class {
        function fetchAll($sql, $params=[]) { return [['id'=>4,'name'=>'Food &amp; Groceries'],['id'=>30,'name'=>'Fashion &amp; Clothing']]; }
        function fetch($sql, $params=[]) { return in_array((int)($params[0] ?? 0),[4,30],true) ? ['id'=>(int)$params[0],'slug'=>'food-groceries','is_active'=>1] : false; }
    };
}
function app_branding() { return ['logo'=>'']; }
function e($v) { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function app_url($v) { return 'https://sellerafrica.com/'.$v; }
function getCsrfToken() { return 'test'; }
$data=['verification'=>[]]; $vendor=['status'=>'pending','kyc_status'=>'not_started'];
$errors=[]; $pending=false; $paymentReady=true; $step=0;
echo '<html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>';
require __DIR__.'/../app/views/auth/vendor-verification.php';
echo '</body></html>';
