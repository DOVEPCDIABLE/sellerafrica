<?php
declare(strict_types=1);
namespace App;

final class PriorityPaystackService
{
    public const PREFIX='SA-PRIORITY-NGN-';
    public static function ensureSchema(): void
    {
        \db()->query('CREATE TABLE IF NOT EXISTS priority_paystack_payments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,membership_id BIGINT UNSIGNED NOT NULL,reference VARCHAR(100) NOT NULL UNIQUE,email VARCHAR(190) NOT NULL,plan VARCHAR(16) NOT NULL,amount_minor BIGINT NOT NULL,checkout_url TEXT NULL,paid_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX(membership_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
    public static function quote(string $plan): int
    {
        $p=PriorityMembershipService::plans()[$plan] ?? null;
        if(!$p)throw new \RuntimeException('Choose monthly or annual membership.');
        return (int)round(DistributorPaymentService::naira($p['amount']/100)*100);
    }
    public static function checkout(string $name,string $email,string $plan,?int $quote=null): string
    {
        $name=trim($name);$email=strtolower(trim($email));
        if(!$name || strlen($name)>190 || strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL))throw new \RuntimeException('Enter your name and a valid email address.');
        $amount=self::quote($plan);
        if($quote!==null && $quote!==$amount)throw new \RuntimeException('The naira rate has changed. Review the updated price and try again.');
        self::ensureSchema();PriorityMembershipService::ensureSchema();
        $method=\db()->fetch('SELECT * FROM payment_methods WHERE code="paystack" LIMIT 1');
        if(!$method || empty($method['is_active']) || !PaymentService::isPaystackConfigured($method))throw new \RuntimeException('Paystack is temporarily unavailable. Please choose Stripe.');
        if((int)(\db()->fetch("SELECT GET_LOCK('seller_africa_priority_intake',10) AS acquired")['acquired'] ?? 0)!==1)throw new \RuntimeException('Checkout is busy. Please try again.');
        try{
            $m=\db()->fetch('SELECT * FROM priority_memberships WHERE email=?',[$email]);
            if($m && $m['stripe_session_id']){
                PriorityMembershipService::syncCheckout($m['stripe_session_id']);
                $m=\db()->fetch('SELECT * FROM priority_memberships WHERE id=?',[$m['id']]);
                if($m['stripe_subscription_id'] && !in_array($m['status'],['canceled','incomplete_expired'],true))throw new \RuntimeException('You already have a Stripe membership. Manage that membership before changing providers.');
                $s=\Stripe\Checkout\Session::retrieve($m['stripe_session_id']);
                if($s->status==='open')$s->expire();
            }
            if($m && PriorityMembershipService::active($m))throw new \RuntimeException('Your membership is already active. Renew after the current period ends.');
            if($m && str_starts_with($m['reference'],self::PREFIX)){
                $pending=\db()->fetch('SELECT * FROM priority_paystack_payments WHERE reference=? AND paid_at IS NULL',[$m['reference']]);
                if($pending){
                    if(self::verify($pending['reference']))throw new \RuntimeException('Your membership payment is already confirmed.');
                    if($pending['plan']!==$plan || (int)$pending['amount_minor']!==$amount)throw new \RuntimeException('A Paystack checkout already exists for this email. Select the original plan or contact support to change it.');
                    if($pending['checkout_url'])return $pending['checkout_url'];
                    return self::initialize($pending,$method);
                }
            }
            $count=(int)(\db()->fetch('SELECT COUNT(*) AS total FROM priority_memberships WHERE first_paid_at IS NOT NULL OR seat_released=0')['total'] ?? 0);
            if((!$m || (!$m['first_paid_at'] && $m['seat_released'])) && $count>=PriorityMembershipService::LIMIT)throw new \RuntimeException('The founding intake is currently full or reserved.');
            $reference=self::PREFIX.bin2hex(random_bytes(16));
            if($m){
                \db()->query('UPDATE priority_memberships SET name=?,plan=?,reference=?,status="incomplete",stripe_session_id=NULL,stripe_subscription_id=NULL,checkout_url=NULL,checkout_paid_at=NULL,checkout_expires=NULL,seat_released=0,cancel_at_period_end=1 WHERE id=?',[$name,$plan,$reference,$m['id']]);$id=$m['id'];
            }else{
                $user=\db()->fetch('SELECT id FROM users WHERE email=?',[$email]);
                \db()->query('INSERT INTO priority_memberships (name,email,user_id,plan,reference,cancel_at_period_end) VALUES (?,?,?,?,?,1)',[$name,$email,$user['id'] ?? null,$plan,$reference]);$id=(int)\db()->lastInsertId();
            }
            \db()->query('INSERT INTO priority_paystack_payments (membership_id,reference,email,plan,amount_minor) VALUES (?,?,?,?,?)',[$id,$reference,$email,$plan,$amount]);
            return self::initialize(['reference'=>$reference,'email'=>$email,'plan'=>$plan,'amount_minor'=>$amount],$method);
        }finally{\db()->query("SELECT RELEASE_LOCK('seller_africa_priority_intake')");}
    }
    private static function initialize(array $row,array $method): string
    {
        $result=PaymentService::initializePaystackTransaction($row['amount_minor']/100,'NGN',$row['email'],$row['reference'],\app_url('priority?paystack_reference='.rawurlencode($row['reference']).'#membership'),['context'=>'seller_africa_priority','plan'=>$row['plan'],'renewal'=>'manual'],$method);
        $url=(string)($result['data']['authorization_url'] ?? '');
        if(parse_url($url,PHP_URL_SCHEME)!=='https' || parse_url($url,PHP_URL_HOST)!=='checkout.paystack.com')throw new \RuntimeException('Paystack did not return a secure checkout link. Please try again.');
        \db()->query('UPDATE priority_paystack_payments SET checkout_url=? WHERE reference=?',[$url,$row['reference']]);
        \db()->query('UPDATE priority_memberships SET checkout_url=? WHERE reference=?',[$url,$row['reference']]);return $url;
    }
    public static function matches(array $row,array $data): bool
    {
        return ($data['status'] ?? '')==='success' && ($data['reference'] ?? '')===$row['reference'] && strtoupper((string)($data['currency'] ?? ''))==='NGN' && (int)($data['amount'] ?? 0)===(int)$row['amount_minor'] && strtolower((string)($data['customer']['email'] ?? ''))===strtolower($row['email']);
    }
    public static function verify(string $reference): bool
    {
        $row=\db()->fetch('SELECT * FROM priority_paystack_payments WHERE reference=?',[$reference]);
        if(!$row)return false;if($row['paid_at'])return true;
        $method=\db()->fetch('SELECT * FROM payment_methods WHERE code="paystack" LIMIT 1');
        $result=PaymentService::verifyPaystackTransaction($reference,$method ?: null);
        $data=$result['data']['data'] ?? $result['data'] ?? [];
        if(empty($result['ok']) || !self::matches($row,$data))return false;
        $paid=strtotime((string)($data['paid_at'] ?? ''));
        if(!$paid)throw new \RuntimeException('Payment date could not be verified.');
        $end=date('Y-m-d H:i:s',strtotime($row['plan']==='annual'?'+1 year':'+1 month',$paid));
        $pdo=\db()->pdo();$pdo->beginTransaction();
        try{
            $locked=\db()->fetch('SELECT * FROM priority_paystack_payments WHERE reference=? FOR UPDATE',[$reference]);
            if($locked['paid_at']){$pdo->commit();return true;}
            $m=\db()->fetch('SELECT * FROM priority_memberships WHERE id=? FOR UPDATE',[$row['membership_id']]);
            if($m['reference']!==$reference)throw new \RuntimeException('Payment requires support review.');
            \db()->query('UPDATE priority_paystack_payments SET paid_at=? WHERE reference=?',[date('Y-m-d H:i:s',$paid),$reference]);
            \db()->query('UPDATE priority_memberships SET first_paid_at=COALESCE(first_paid_at,?),checkout_paid_at=?,status="active",current_period_end=?,cancel_at_period_end=1 WHERE id=?',[date('Y-m-d H:i:s',$paid),date('Y-m-d H:i:s',$paid),$end,$m['id']]);$pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        try{
            NotificationService::enqueueEmail($row['email'],'Seller Africa Priority payment confirmed','Welcome to Priority','<p>Hi '.\e($m['name']).',</p><p>Your membership is paid through '.\e($end).'. This Paystack payment does not renew automatically. Our team will send your community access details.</p>',['button_text'=>'Membership','button_url'=>\app_url('priority#manage-membership')]);
            NotificationService::notifySuperAdmins('Priority membership paid via Paystack','Priority member confirmed','<p>'.\e($row['email']).' paid for '.\e($row['plan']).' membership. Please provide community access.</p>',['button_text'=>'View members','button_url'=>\app_url('dashboard/priority-members')]);
        }catch(\Throwable $e){\error_log('Priority confirmation email: '.$e->getMessage());}
        return true;
    }
}
