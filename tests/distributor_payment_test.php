<?php
declare(strict_types=1);
namespace App {
    final class PaymentService {
        public static array $tx=[];
        public static function verifyPaystackTransaction($r){return ['ok'=>true,'data'=>['data'=>self::$tx]];}
        public static function stripeSettings(){return ['secret_key'=>'test'];}
        public static function convertAmount($amount,$from,$to){return $amount*1500;}
    }
    final class NotificationService {
        public static int $emails=0;
        public static function enqueueEmail(...$args){self::$emails++;}
        public static function notifySuperAdmins(...$args){}
    }
}
namespace Stripe {
    final class Stripe {public static function setApiKey($key){}}
    final class Event {public static function retrieve($id){throw new \RuntimeException('Event does not exist');}}
}
namespace Stripe\Checkout {
    final class Session {public static $session;public static function retrieve($id){return self::$session;}}
}
namespace {
    if(PHP_SAPI!=='cli')exit(1);
    require __DIR__.'/../app/services/DistributorPaymentService.php';
    function e($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
    function app_url($s){return '/'.$s;}
    function audit(...$args){}
    $db=new class {
        public array $row=['id'=>1,'reference'=>'SA-RETAIL-test','plan_code'=>'starter','provider'=>'paystack','amount_minor'=>75000000,'currency'=>'NGN','email'=>'applicant@example.test','paid_at'=>null];
        public bool $rates=true;
        public function fetch($sql,$params=[]){return $params[0]===$this->row['reference']?$this->row:null;}
        public function fetchAll($sql){return $this->rates?[['code'=>'NGN'],['code'=>'USD']]:[];}
        public function query($sql,$params=[]){$this->row['paid_at']='2026-09-30';return new class {public function rowCount(){return 1;}};}
    };
    function db(){return $GLOBALS['db'];}
    function check($condition,$label){if(!$condition)throw new RuntimeException($label);}
    $plans=App\DistributorPaymentService::plans();
    check(array_column($plans,'price')===[500,1500,5000],'PDF prices');
    check(count($plans['expansion']['features'])===10,'PDF benefits');
    check(App\DistributorPaymentService::naira(500)===750000.0,'Naira conversion');
    $db->rates=false;
    try{App\DistributorPaymentService::naira(500);throw new RuntimeException('Missing rates accepted');}catch(RuntimeException $e){check(str_contains($e->getMessage(),'temporarily unavailable'),'Fail closed without rates');}
    $good=['reference'=>'SA-RETAIL-test','status'=>'success','amount'=>75000000,'currency'=>'NGN','customer'=>['email'=>'applicant@example.test']];
    foreach(['reference'=>'SA-RETAIL-other','status'=>'failed','amount'=>50000,'currency'=>'USD','customer'=>['email'=>'other@example.test']] as $k=>$v){
        App\PaymentService::$tx=array_replace($good,[$k=>$v]);
        check(!App\DistributorPaymentService::verify('SA-RETAIL-test'),'Reject '.$k);
        check(!$db->row['paid_at'],'Must not mark invalid payment paid');
    }
    App\PaymentService::$tx=$good;
    check(App\DistributorPaymentService::verify('SA-RETAIL-test'),'Paystack success');
    check(App\DistributorPaymentService::verify('SA-RETAIL-test'),'Callback replay');
    check(App\NotificationService::$emails===1,'No duplicate receipts');
    $db->row=array_replace($db->row,['paid_at'=>null,'provider'=>'stripe','currency'=>'USD','amount_minor'=>50000,'stripe_session_id'=>'cs_test']);
    \Stripe\Checkout\Session::$session=(object)['id'=>'cs_test','metadata'=>(object)['context'=>'retail_placement'],'payment_status'=>'unpaid','client_reference_id'=>'SA-RETAIL-test','amount_total'=>50000,'currency'=>'usd','customer_details'=>(object)['email'=>'applicant@example.test']];
    check(!App\DistributorPaymentService::verify('SA-RETAIL-test'),'Stripe unpaid');
    \Stripe\Checkout\Session::$session->payment_status='paid';
    check(App\DistributorPaymentService::verify('SA-RETAIL-test'),'Stripe paid');
    try{App\DistributorPaymentService::stripeWebhook('{"data":{"object":{"metadata":{"context":"retail_placement"}}}}','');throw new RuntimeException('Unsigned accepted');}catch(RuntimeException $e){check($e->getMessage()==='Stripe event reference required.','Event guard');}
    try{App\DistributorPaymentService::stripeWebhook('{"id":"evt_fake","data":{"object":{"metadata":{"context":"retail_placement"}}}}','');throw new RuntimeException('Fake event accepted');}catch(RuntimeException $e){check($e->getMessage()==='Event does not exist','API verification guard');}
    echo "PASS: prices, benefits, FX fail-closed, Paystack validation, Stripe paid/unpaid, webhook signature, idempotency.\n";
}
