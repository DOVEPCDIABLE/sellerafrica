<?php
declare(strict_types=1);
namespace App {
    final class PaymentService {
        public static array $transaction = [];
        public static function verifyPaystackTransaction($reference, $method) { return ['ok'=>true,'data'=>['data'=>self::$transaction]]; }
    }
}
namespace {
    if (PHP_SAPI !== 'cli') exit(1);
    require __DIR__.'/../app/services/VendorRegistrationPaymentService.php';
    require __DIR__.'/../app/services/VendorOnboardingService.php';
    final class FakeFeeDb {
        public bool $paid=false;
        public bool $subscriptionPaid=false;
        public float $planPrice=0;
        public int $updates=0;
        public function fetch($sql,$params=[]) {
            if(str_contains($sql,'payment_methods')) return ['is_active'=>1];
            if(str_contains($sql,'WHERE reference=')) return ['id'=>1,'reference'=>'SA-REGISTRATION-test','amount_minor'=>150000,'currency'=>'NGN','email'=>'test@example.test','paid_at'=>$this->paid?'2026-09-30':null];
            if(str_contains($sql,'vendor_registration_payments')) return $this->paid?['id'=>1]:null;
            if(str_contains($sql,'vendor_packages')) return ['price'=>$this->planPrice];
            if(str_contains($sql,'vendor_subscriptions')) return $this->subscriptionPaid?['id'=>2]:null;
            throw new RuntimeException('Unexpected query: '.$sql);
        }
        public function query($sql,$params=[]) { if(!str_contains($sql,'COALESCE(paid_at,NOW())')) throw new RuntimeException('Unexpected write'); $this->paid=true; $this->updates++; }
    }
    $fake=new FakeFeeDb();
    function db(){return $GLOBALS['fake'];}
    function table_exists($table){return true;}
    function check($condition,$message){if(!$condition)throw new RuntimeException($message);}
    $data=['onboarding'=>['package_id'=>1,'registration_fee_required'=>true]];
    check(App\VendorRegistrationPaymentService::required(['onboarding'=>['registration_fee_policy'=>App\VendorRegistrationPaymentService::POLICY]]),'New registration policy must require payment without the flag');
    check(App\VendorRegistrationPaymentService::required(['onboarding'=>['registration_fee_policy'=>App\VendorRegistrationPaymentService::POLICY,'registration_fee_required'=>false]]),'New registration policy must not allow a false flag to waive payment');
    $policyData=['onboarding'=>['package_id'=>1,'registration_fee_policy'=>App\VendorRegistrationPaymentService::POLICY]];
    check(!App\VendorOnboardingService::paymentReady(1,$policyData),'New Free plan without a fee flag must remain locked');
    check(App\VendorOnboardingService::paymentReady(1,[])===true,'Legacy accounts must be unaffected');
    check(!App\VendorOnboardingService::paymentReady(1,$data),'Free plan must require registration fee');
    $fake->planPrice=10;$fake->subscriptionPaid=true;
    check(!App\VendorOnboardingService::paymentReady(1,$data),'Paid plan cannot bypass registration fee');
    $good=['reference'=>'SA-REGISTRATION-test','amount'=>150000,'currency'=>'NGN','status'=>'success','customer'=>['email'=>'test@example.test']];
    foreach(['amount'=>149999,'currency'=>'USD','reference'=>'wrong','status'=>'failed','customer'=>['email'=>'different@example.test']] as $key=>$value){
        App\PaymentService::$transaction=array_replace($good,[$key=>$value]);
        check(!App\VendorRegistrationPaymentService::verify('SA-REGISTRATION-test'),'Must reject invalid '.$key);
        check(!$fake->paid,'Invalid transaction unlocked payment');
    }
    App\PaymentService::$transaction=$good;
    check(App\VendorRegistrationPaymentService::verify('SA-REGISTRATION-test'),'Valid payment rejected');
    check(App\VendorRegistrationPaymentService::verify('SA-REGISTRATION-test'),'Duplicate payment callback failed');
    check($fake->updates===1,'Duplicate callback performed another update');
    $fake->subscriptionPaid=false;
    check(!App\VendorOnboardingService::paymentReady(1,$data),'Registration fee must not pay a paid subscription');
    $fake->planPrice=0;
    check(App\VendorOnboardingService::paymentReady(1,$data),'Free plan should unlock after fee');
    $fake->planPrice=10;$fake->subscriptionPaid=true;
    check(App\VendorOnboardingService::paymentReady(1,$data),'Paid plan and registration fee should unlock');
    echo "PASS: legacy, Free, paid plans, amount/currency/reference/email/status validation, replay safety.\n";
}
