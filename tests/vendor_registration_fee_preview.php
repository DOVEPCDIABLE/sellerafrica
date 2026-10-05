<?php
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__.'/../app/services/VendorOnboardingService.php';
require __DIR__.'/../app/services/VendorRegistrationPaymentService.php';
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function app_url($path){return '/'.$path;}
function app_branding(){return [];}
function getCsrfToken(){return 'preview-only';}
function table_exists($table){return true;}
function db(){return new class { public function fetch($sql,$args=[]){return null;} };}
$data=['onboarding'=>['registration_fee_required'=>true]];
$vendor=['id'=>1,'status'=>'pending','kyc_status'=>'not_started'];
$errors=[];$paymentReady=false;$pending=false;$step=0;
echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>';
require __DIR__.'/../app/views/auth/vendor-verification.php';
echo '</body></html>';
