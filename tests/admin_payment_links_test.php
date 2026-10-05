<?php
namespace App {
class VendorSubscriptionService {static function ensureSchema(){} static function packages(){return [['id'=>2,'name'=>'Basic','price'=>10,'currency'=>'USD']];}static function createCheckoutSession(...$args){return \recordLink('vendor',$args);}}
class DistributorPaymentService {static function ensureSchema(){}static function plans(){return ['starter'=>['name'=>'Retail Starter','price'=>500]];}static function naira($v){return $v*1500;}static function checkout(...$args){return \recordLink('retail',$args);}}
class ManageStoreService {static function activeServiceForEmail(...$args){return $GLOBALS['paid']?['status'=>'paid']:null;}static function createPaystackAnnualCheckout(...$args){return \recordLink('manage_paystack',$args);}static function createStripeCheckoutSession(...$args){return \recordLink('manage_stripe',$args);}}
class VisibilityBoostService {static function ensureSchema(){}static function activeBoost($id){return null;}static function createCheckoutSession(...$args){return \recordLink('rank',$args);}}
class PriorityMembershipService {static function ensureSchema(){}static function checkout(...$args){return \recordLink('priority',$args);}}
class PriorityPaystackService {static function checkout(...$args){return \recordLink('priority_paystack',$args);}}
class VendorRegistrationPaymentService {static function checkout(...$args){return \recordLink('registration',$args);}}
class NotificationService {static function enqueueEmail(...$args){$GLOBALS['mail']=$args;}}
}
namespace {
require __DIR__.'/../app/services/AdminPaymentLinkService.php';
require __DIR__.'/../app/services/AdminAccessService.php';
function app_url($v){return 'https://sellerafrica.com/'.$v;}function audit(...$args){}function e($v){return htmlspecialchars($v,ENT_QUOTES);}
function recordLink($kind,$args){$GLOBALS['called']=[$kind,$args];return 'https://checkout.stripe.com/c/pay/test';}
class FakeLinkDb{
 function query($sql,$args=[]){$GLOBALS['queries'][]=[$sql,$args];return true;}
 function lastInsertId(){return 42;}
 function fetch($sql,$args=[]){
  if(str_contains($sql,'GET_LOCK'))return ['acquired'=>1];
  if(str_contains($sql,'FROM users'))return ['id'=>5,'phone'=>''];
  if(str_contains($sql,'FROM vendors'))return $GLOBALS['hasVendor']?['id'=>9]:null;
  return null;
 }
}
function db(){static $db;return $db??=new FakeLinkDb();}
function check($value,$label){if(!$value)throw new \RuntimeException($label);echo "PASS $label\n";}
function fails($call,$label){try{$call();}catch(\RuntimeException $e){echo "PASS $label\n";return;}throw new \RuntimeException($label);}
$paid=false;$hasVendor=true;
use App\AdminPaymentLinkService as L;
foreach([['manage','paystack','manage_paystack'],['setup','stripe','manage_stripe'],['rank','paystack','rank'],['priority_monthly','stripe','priority'],['priority_annual','stripe','priority'],['vendor_2','stripe','vendor'],['retail_starter','paystack','retail'],['registration','paystack','registration']] as [$service,$provider,$kind]){
 check(L::generate('customer@example.com','Customer',$service,$provider,1)===42 && $called[0]===$kind,"Dispatch $service");
 if($service==='retail_starter')check($called[1][3]===75000000,'Retail NGN quote');
 if($service==='setup')check($called[1][2]==='setup','Setup is one-time service');
}
check(L::generate('customer@example.com','Customer','priority_monthly','paystack',1)===42 && $called[0]==='priority_paystack','Priority Paystack dispatch');
fails(fn()=>L::generate('customer@example.com','Customer','priority_monthly','klasha',1),'Unsupported provider blocked');
fails(fn()=>L::generate('bad','Customer','manage','stripe',1),'Invalid email blocked');
$hasVendor=false;fails(fn()=>L::generate('customer@example.com','Customer','rank','stripe',1),'Non-vendor ranking blocked');
$paid=true;fails(fn()=>L::generate('customer@example.com','Customer','manage','stripe',1),'Paid store service blocked');
check(!L::safeUrl('https://checkout.stripe.com.evil.example/pay'),'Spoofed payment host blocked');
check(!L::safeUrl('javascript:alert(1)'),'Unsafe payment URL blocked');
check(!App\AdminAccessService::allows(['admin'],'payment-links'),'Payment permission preserved');
check(App\AdminAccessService::allows(['super_admin'],'payment-links'),'Super admin access');
}
