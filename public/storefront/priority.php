<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/core/bootstrap.php';
use App\PriorityMembershipService as Priority;
Priority::ensureSchema();
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
$error='';$notice='';$member=null;$managed=null;
$plan=(string)($_POST['plan'] ?? $_GET['plan'] ?? 'monthly');
$provider=(string)($_POST['provider'] ?? $_GET['provider'] ?? 'stripe');
$naira=[];
try { foreach(Priority::plans() as $key=>$p)$naira[$key]=\App\PriorityPaystackService::quote($key); } catch(Throwable $e){error_log('Priority NGN quote: '.$e->getMessage());}
\App\PriorityPaystackService::ensureSchema();
if(!isset(Priority::plans()[$plan]))$plan='monthly';
$token=(string)($_POST['management_token'] ?? $_GET['manage'] ?? '');
try {
    if(($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
        validateCsrf();
        $identifier=\App\SecurityService::clientIp();
        if(\App\SecurityService::rateLimitStatus('priority_checkout',$identifier)['blocked']) throw new RuntimeException('Too many requests. Please try again later.');
        \App\SecurityService::recordFailure('priority_checkout',$identifier);
        $intent=(string)($_POST['intent'] ?? 'subscribe');
        if($intent==='manage') {
            Priority::requestManagement((string)($_POST['email'] ?? ''));
            $notice='If this email has a membership, a secure management link has been sent. Please check your inbox.';
        } elseif($intent==='cancel') {
            Priority::cancelRenewal($token);
            $notice='Automatic renewal is cancelled. Any remaining paid membership time stays available until the period ends.';
        } else {
            if(($_POST['terms'] ?? '')!=='1') throw new InvalidArgumentException('Please agree to the recurring payment terms and membership disclaimer.');
            if(!in_array($provider,['stripe','paystack'],true))throw new InvalidArgumentException('Select Stripe or Paystack.');
            if($provider==='paystack'){
                redirect(\App\PriorityPaystackService::checkout((string)($_POST['name'] ?? ''),(string)($_POST['email'] ?? ''),$plan,(int)($_POST['naira_quote'][$plan] ?? 0)));
            }
            redirect(Priority::checkout((string)($_POST['name'] ?? ''),(string)($_POST['email'] ?? ''),$plan));
        }
    }
    if(!empty($_GET['session_id'])) {
        $member=Priority::syncCheckout((string)$_GET['session_id']);
        $notice=$member && Priority::active($member)?'Payment confirmed. Welcome to Seller Africa Priority. Our team will email your community access details.':'Your payment is not confirmed yet. Refresh this page shortly or contact support if you were charged.';
    }
    if(!empty($_GET['paystack_reference'])){
        $paid=\App\PriorityPaystackService::verify((string)$_GET['paystack_reference']);
        $member=$paid?db()->fetch('SELECT * FROM priority_memberships WHERE reference=?',[(string)$_GET['paystack_reference']]):null;
        $notice=$paid?'Payment confirmed. Welcome to Priority. Your Paystack membership does not renew automatically.':'Payment is not confirmed yet. Refresh shortly or contact support if you were charged.';
    }
    if($token!=='') {
        $managed=Priority::managed($token);
        if(!$managed) throw new RuntimeException('Your secure link has expired. Request a new membership link below.');
        if($managed['stripe_session_id']) Priority::syncCheckout($managed['stripe_session_id']);
        $managed=Priority::managed($token);
    }
} catch(Throwable $e) {
    error_log('Priority membership: '.$e->getMessage());
    $error=$e instanceof \Stripe\Exception\ApiErrorException?'Stripe could not complete this request. Please try again or contact support.':($e instanceof \PDOException?'Membership is temporarily unavailable. Please try again shortly.':$e->getMessage());
}
$user=\App\AuthService::user();
render_layout('priority','storefront/pages/priority.php',compact('plan','provider','naira','member','managed','token','error','notice','user')+[
    'title'=>'Seller Africa Priority | Founding Membership',
    'meta_description'=>'Join Seller Africa Priority for priority vendor support, buyer and export opportunity alerts, and monthly market-access sessions. $5/month or $50/year.',
]);
