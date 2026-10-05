<?php
declare(strict_types=1);
namespace Stripe { class Stripe { public static function setApiKey($key): void {} } }
namespace Stripe\Checkout {
    class Session {
        public static array $payloads = [];
        public static function create(array $payload): object {
            self::$payloads[] = $payload;
            return (object)['id'=>'cs_test_'.count(self::$payloads), 'url'=>'https://checkout.stripe.com/fixture'];
        }
    }
}
namespace App {
    class PaymentService {
        public static array $initialized = [];
        public static function stripeSettings(): array { return ['secret_key'=>'fixture','webhook_secret'=>'fixture']; }
        public static function ensurePaystackMethod(): void {}
        public static function ensureKlashaMethod(): void {}
        public static function isPaystackConfigured($m): bool { return true; }
        public static function isKlashaConfigured($m): bool { return true; }
        public static function paystackSettings($m): array { return ['charge_currency'=>'NGN']; }
        public static function settings($m): array { return ['public_key'=>'fixture','business_id'=>'fixture']; }
        public static function convertAmount($amount,$from,$to): float { return $to==='NGN' ? $amount*1500 : $amount; }
        public static function paystackMinorAmount($amount,$currency): int { return (int)round($amount*100); }
        public static function initializePaystackTransaction(...$args): array {
            self::$initialized[]=$args;
            return ['data'=>['authorization_url'=>'https://checkout.paystack.com/fixture','access_code'=>'fixture']];
        }
    }
    class NotificationService {
        public static array $messages=[];
        public static function notifyAdmins(...$args): void { self::$messages[]=$args; }
    }
}
namespace {
    require dirname(__DIR__).'/app/services/ManageStoreService.php';
    use App\ManageStoreService as Service;
    function sql_now(): string { return '2026-09-24 12:00:00'; }
    function app_url($p): string { return 'https://example.com/'.$p; }
    function e($s): string { return htmlspecialchars((string)$s); }
    function db_config(): array { return ['database'=>'fixture']; }
    class FixtureDb {
        public array $rows=[];
        public function pdo(): self { return $this; }
        public function exec($sql): int { return 0; }
        public function fetch($sql,$p=[]): ?array {
            if(str_contains($sql,'INFORMATION_SCHEMA'))return ['total'=>1];
            if(str_contains($sql,'SHOW COLUMNS'))return ['Type'=>"enum('monthly','annual','setup')"];
            if(str_contains($sql,'FROM users'))return ['id'=>7];
            if(str_contains($sql,'FROM vendors'))return ['id'=>8];
            if(str_contains($sql,'payment_methods'))return ['is_active'=>1];
            if(str_contains($sql,'customer_email = ?')){
                foreach(array_reverse($this->rows) as $r)if($r['customer_email']===$p[0] && (($p[1]==='setup')===($r['plan']==='setup')))return $r;
                return null;
            }
            foreach($this->rows as $r){
                foreach(['paystack_reference','klasha_reference','stripe_checkout_session_id','id'] as $key){
                    if(str_contains($sql,'WHERE '.$key.' = ?') && ($r[$key]??null)===$p[0])return $r;
                }
            }
            return null;
        }
        public function query($sql,$p=[]): object {
            if(str_contains($sql,'INSERT INTO vendor_store_management_services')){
                $provider=str_contains($sql,"'stripe'")?'stripe':(str_contains($sql,"'klasha'")?'klasha':'paystack');
                $ref=['stripe'=>'stripe_checkout_session_id','klasha'=>'klasha_reference','paystack'=>'paystack_reference'][$provider];
                $id=count($this->rows)+1;
                $this->rows[$id]=['id'=>$id,'vendor_id'=>$p[0],'user_id'=>$p[1],'customer_name'=>$p[2],'customer_email'=>$p[3],'plan'=>$p[4],'amount'=>$p[5],'currency'=>$p[6],$ref=>$p[7],'raw_response'=>$provider==='stripe'?'{}':$p[8],'status'=>'pending','paid_at'=>null,'provider'=>$provider];
            }elseif(str_contains($sql,'SET raw_response = ?')){
                foreach($this->rows as &$r)if(($r['paystack_reference']??null)===$p[2])$r['raw_response']=$p[0];
            }elseif(str_contains($sql,"SET status = 'active'")){
                foreach($this->rows as &$r)if(($r['stripe_checkout_session_id']??null)===end($p)){$r['status']='active';$r['paid_at']=sql_now();}
            }elseif(str_contains($sql,'SET status = ?')){
                $id=end($p);$this->rows[$id]['status']=$p[0];$this->rows[$id]['raw_response']=$p[1];
                if($p[0]==='active')$this->rows[$id]['paid_at']=sql_now();
            }
            return new class { public function rowCount(): int { return 1; } };
        }
    }
    $database=new FixtureDb();
    function db(): FixtureDb {global $database;return $database;}
    function check($v,$message): void {if(!$v)throw new \RuntimeException($message);}
    foreach(['annual'=>12,'setup'=>5] as $plan=>$amount){
        Service::createStripeCheckoutSession('Test User','TEST@example.com',$plan,'https://example.com/ok','https://example.com/cancel');
        $payload=end(\Stripe\Checkout\Session::$payloads);
        check($payload['mode']==='payment','Must not recur');
        check($payload['line_items'][0]['price_data']['unit_amount']===$amount*100,'Stripe price');
        check(!isset($payload['line_items'][0]['price_data']['recurring']),'Recurring price not permitted');
        $config=Service::createKlashaAnnualConfig('Test User','test@example.com','',$plan);
        check($config['amount']==$amount,'Klasha price');
        Service::createPaystackAnnualCheckout('Test User','test@example.com','',$plan);
        $init=end(\App\PaymentService::$initialized);
        check($init[0]===$amount*1500.0 && $init[1]==='NGN','Paystack conversion');
        check($init[5]['plan']===$plan,'Paystack plan metadata');
    }
    check(Service::activeServiceForEmail('test@example.com')['plan']==='annual','Management and setup must not overlap');
    check(Service::activeServiceForEmail('test@example.com','setup')['plan']==='setup','Setup lookup');
    foreach($database->rows as $r)check($r['user_id']===7 && $r['vendor_id']===8,'Account attribution');
    $r=$database->rows[6];$ref=$r['paystack_reference'];
    check(!Service::applyPaystackStatus($ref,['status'=>'success','amount'=>750000,'currency'=>'NGN'],'callback_unverified'),'Untrusted callback');
    check(!Service::applyPaystackStatus($ref,['status'=>'success','amount'=>1,'currency'=>'NGN'],'verification'),'Underpayment');
    check(!Service::applyPaystackStatus($ref,['status'=>'success','amount'=>750000,'currency'=>'USD'],'verification'),'Wrong currency');
    check(Service::applyPaystackStatus($ref,['status'=>'success','amount'=>750000,'currency'=>'NGN'],'verification'),'Verified payment');
    $count=count(\App\NotificationService::$messages);
    check(Service::applyPaystackStatus($ref,['status'=>'failed'],'verification'),'Must not downgrade paid order');
    check(count(\App\NotificationService::$messages)===$count,'Duplicate notification');
    check(str_contains(end(\App\NotificationService::$messages)[0],'Set Up My Store'),'Setup notification label');
    $ref=$database->rows[5]['klasha_reference'];
    check(!Service::applyKlashaStatus($ref,['status'=>'success','sourceAmount'=>5,'sourceCurrency'=>'USD'],'callback_unverified'),'Untrusted Klasha');
    check(!Service::applyKlashaStatus($ref,['status'=>'success'],'verification'),'Missing Klasha amount');
    check(Service::applyKlashaStatus($ref,['status'=>'success','sourceAmount'=>5,'sourceCurrency'=>'USD'],'verification'),'Verified Klasha');
    $apply=new \ReflectionMethod(Service::class,'applyCheckoutSession');
    $session=(object)['id'=>'cs_test_2','payment_status'=>'unpaid','amount_total'=>500,'currency'=>'usd'];
    $apply->invoke(null,$session);check(!$database->rows[4]['paid_at'],'Unpaid Stripe activated');
    $session->payment_status='paid';$apply->invoke(null,$session);check((bool)$database->rows[4]['paid_at'],'Paid Stripe not activated');
    try{Service::createPaystackAnnualCheckout('Test','test@example.com','','arbitrary');throw new \RuntimeException('Invalid plan accepted');}catch(\InvalidArgumentException $e){}
    echo "PASS service separation, fixed prices, one-time Stripe, Paystack conversion, account attribution, verified payments and replay protection\n";
}
