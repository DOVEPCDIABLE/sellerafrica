<?php
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/../app/services/DistributorPaymentService.php';
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function app_url($s){return '/'.$s;}
function asset($s){return '/assets/'.$s;}
function app_branding(){return ['logo'=>'https://sellerafrica.com/uploads/settings/logo_path-20260712173349-45c92b5e.png'];}
function getCsrfToken(){return 'preview-token';}
$user=['id'=>1];$application=['id'=>1];$plans=App\DistributorPaymentService::plans();$quotes=['starter'=>75000000,'access'=>225000000,'expansion'=>750000000];$payment=null;$error='';
if(($argv[1]??'')==='paid')$payment=['paid_at'=>'2026-09-30','plan_code'=>'starter','currency'=>'NGN','amount_minor'=>75000000,'reference'=>'SA-RETAIL-preview'];
if(($argv[1]??'')==='error'){$error='The exchange rate has changed. Review the updated naira amount and try again.';$_POST=['plan'=>'access','provider'=>'paystack','accept_terms'=>'1'];}
if(($argv[1]??'')==='cancelled')$_GET['cancelled']='1';
echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>';
require __DIR__.'/../app/views/distributor/payment.php';
echo '</body></html>';
