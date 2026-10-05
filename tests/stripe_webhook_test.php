<?php
namespace Stripe {
class Stripe {static function setApiKey($key){}}
class Event {static function retrieve($id){if($id!=='evt_trusted')throw new \RuntimeException('Unknown event');return (object)['id'=>'evt_trusted','type'=>'checkout.session.expired','data'=>(object)['object'=>(object)['metadata'=>(object)[]]]];}}
}
namespace App {
class PaymentService {static function stripeSettings(...$args){return ['secret_key'=>'test'];}static function handleStripeWebhook(...$args){$GLOBALS['calls']++;return false;}}
class VendorSubscriptionService {static function ensureSchema(){}static function handleStripeWebhook(...$args){}}
class PriorityMembershipService {static function webhook(...$args){return false;}}
class DistributorPaymentService {static function stripeWebhook(...$args){return false;}}
class FrozenMarketService {static function handleStripeWebhook(...$args){return false;}}
class VisibilityBoostService {static function handleStripeWebhook(...$args){return false;}}
class ManageStoreService {static function handleStripeWebhook(...$args){return false;}}
class FarmFreshMembershipService {static function handleStripeWebhook(...$args){return false;}}
}
namespace {
define('APP_ROOT',__DIR__.'/..');require __DIR__.'/../app/services/StripeWebhookService.php';
$calls=0;$receipt=false;
function db(){return new class {function fetch($sql,$args=[]){if(str_contains($sql,'GET_LOCK'))return ['acquired'=>1];if(str_contains($sql,'stripe_webhook_receipts'))return $GLOBALS['receipt']?['event_id'=>'evt_trusted']:null;return null;}function query($sql,$args=[]){if(str_starts_with($sql,'INSERT INTO stripe_webhook_receipts'))$GLOBALS['receipt']=true;}};}
function check($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function rejected($f,$label){try{$f();}catch(Throwable $e){echo "PASS $label\n";return;}throw new RuntimeException($label);}
use App\StripeWebhookService as S;
$event=S::authenticate('{"id":"evt_trusted","type":"checkout.session.completed","data":{"object":{"payment_status":"paid"}}}','',true);
check($event->type==='checkout.session.expired','Legacy payload replaced with Stripe canonical event');
rejected(fn()=>S::authenticate('{"id":"evt_forged"}','',true),'Unknown event rejected');
rejected(fn()=>S::authenticate('{}','',true),'Missing event ID rejected');
rejected(fn()=>S::authenticate('{}','',false),'Unsigned new endpoint rejected');
check(S::process($event),'First event dispatched');
check(!S::process($event) && $calls===1,'Duplicate event skipped');
}
