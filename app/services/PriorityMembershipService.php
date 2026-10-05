<?php
declare(strict_types=1);
namespace App;

final class PriorityMembershipService
{
    public const CONTEXT='seller_africa_priority';
    public const LIMIT=500;
    public static function plans(): array
    {
        return ['monthly'=>['amount'=>500,'interval'=>'month','label'=>'Monthly'], 'annual'=>['amount'=>5000,'interval'=>'year','label'=>'Annual']];
    }

    public static function ensureSchema(): void
    {
        \db()->query("CREATE TABLE IF NOT EXISTS priority_memberships (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL UNIQUE, name VARCHAR(190) NOT NULL,
            user_id BIGINT UNSIGNED NULL, plan VARCHAR(16) NOT NULL,
            reference VARCHAR(100) NOT NULL UNIQUE,
            status VARCHAR(30) NOT NULL DEFAULT 'incomplete',
            stripe_session_id VARCHAR(190) NULL UNIQUE,
            stripe_subscription_id VARCHAR(190) NULL UNIQUE,
            stripe_customer_id VARCHAR(190) NULL,
            checkout_url TEXT NULL, checkout_expires BIGINT NULL,
            seat_released TINYINT(1) NOT NULL DEFAULT 0,
            first_paid_at DATETIME NULL, checkout_paid_at DATETIME NULL, current_period_end DATETIME NULL,
            cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
            management_hash CHAR(64) NULL, management_expires DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private static function settings(): array
    {
        return PaymentService::stripeSettings(\db()->fetch('SELECT * FROM payment_methods WHERE code=? LIMIT 1',['stripe']) ?: null);
    }
    private static function stripe(): void
    {
        $secret=(string)(self::settings()['secret_key'] ?? '');
        if($secret==='') throw new \RuntimeException('Membership checkout is temporarily unavailable. Please try again later.');
        \Stripe\Stripe::setApiKey($secret);
    }
    public static function active(array $member): bool
    {
        return !empty($member['checkout_paid_at']) && $member['status']==='active'
            && !empty($member['current_period_end']) && strtotime($member['current_period_end'])>time();
    }

    public static function checkout(string $name,string $email,string $plan): string
    {
        $name=trim($name);$email=strtolower(trim($email));
        if($name==='' || strlen($name)>190 || strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Enter your name and a valid email address.');
        if(!isset(self::plans()[$plan])) throw new \InvalidArgumentException('Choose monthly or annual membership.');
        self::stripe();
        $locked=\db()->fetch("SELECT GET_LOCK('seller_africa_priority_intake',10) AS acquired");
        if((int)($locked['acquired'] ?? 0)!==1) throw new \RuntimeException('Checkout is busy. Please try again in a moment.');
        try {
            // Reconcile expired reservations before releasing any founding-member places.
            $stale=\db()->fetchAll('SELECT * FROM priority_memberships WHERE first_paid_at IS NULL AND seat_released=0 AND checkout_expires < ?',[time()]);
            foreach($stale as $row) {
                if($row['stripe_session_id']) {
                    self::syncCheckout($row['stripe_session_id']);
                    $session=\Stripe\Checkout\Session::retrieve($row['stripe_session_id']);
                    if($session->status!=='expired') continue;
                }
                \db()->query('UPDATE priority_memberships SET seat_released=1 WHERE id=? AND first_paid_at IS NULL',[$row['id']]);
            }
            $member=\db()->fetch('SELECT * FROM priority_memberships WHERE email=?',[$email]);
            if($member && str_starts_with($member['reference'],PriorityPaystackService::PREFIX) && (self::active($member) || empty($member['checkout_paid_at'])))throw new \RuntimeException('You have an active membership or pending Paystack checkout. Continue with Paystack or contact support before changing providers.');
            if($member && $member['stripe_session_id']) {
                self::syncCheckout($member['stripe_session_id']);
                $member=\db()->fetch('SELECT * FROM priority_memberships WHERE id=?',[$member['id']]);
                if($member['stripe_subscription_id'] && !in_array($member['status'],['canceled','incomplete_expired'],true)) throw new \RuntimeException('A membership already exists for this email. Use Manage membership below to check or update it.');
                if($member['checkout_url'] && (int)$member['checkout_expires']>time() && !$member['seat_released']) {
                    if($member['plan']===$plan) return $member['checkout_url'];
                    \Stripe\Checkout\Session::retrieve($member['stripe_session_id'])->expire();
                }
            }
            $count=(int)(\db()->fetch('SELECT COUNT(*) AS total FROM priority_memberships WHERE first_paid_at IS NOT NULL OR seat_released=0')['total'] ?? 0);
            $holdsSeat=$member && ($member['first_paid_at'] || !$member['seat_released']);
            if(!$holdsSeat && $count>=self::LIMIT) throw new \RuntimeException('The founding intake is currently full or reserved. Please try again later or contact our team.');
            $reference='SA-PRIORITY-'.bin2hex(random_bytes(16));
            $expires=time()+1900;
            $matched=\db()->fetch('SELECT id FROM users WHERE email=?',[$email]);
            if($member) {
                \db()->query('UPDATE priority_memberships SET name=?,plan=?,reference=?,status="incomplete",stripe_session_id=NULL,stripe_subscription_id=NULL,checkout_url=NULL,checkout_paid_at=NULL,checkout_expires=?,seat_released=0 WHERE id=?',[$name,$plan,$reference,$expires,$member['id']]);
                $id=(int)$member['id'];
            } else {
                \db()->query('INSERT INTO priority_memberships (name,email,user_id,plan,reference,checkout_expires) VALUES (?,?,?,?,?,?)',[$name,$email,$matched['id'] ?? null,$plan,$reference,$expires]);
                $id=(int)\db()->lastInsertId();
            }
            $price=self::plans()[$plan];
            $metadata=['context'=>self::CONTEXT,'membership_id'=>(string)$id,'reference'=>$reference];
            $session=\Stripe\Checkout\Session::create([
                'mode'=>'subscription','customer_email'=>$email,'client_reference_id'=>$reference,
                'expires_at'=>$expires,'success_url'=>\app_url('priority?session_id={CHECKOUT_SESSION_ID}#membership'),
                'cancel_url'=>\app_url('priority?cancelled=1#membership'),
                'metadata'=>$metadata,'subscription_data'=>['metadata'=>$metadata],
                'line_items'=>[['quantity'=>1,'price_data'=>['currency'=>'usd','unit_amount'=>$price['amount'],'recurring'=>['interval'=>$price['interval']],'product_data'=>['name'=>'Seller Africa Priority - '.$price['label'],'description'=>'Private membership community. Opportunities are subject to eligibility; sales, contracts, funding and retail placement are not guaranteed.']]]],
            ],['idempotency_key'=>$reference]);
            \db()->query('UPDATE priority_memberships SET stripe_session_id=?,checkout_url=? WHERE id=?',[(string)$session->id,(string)$session->url,$id]);
            return (string)$session->url;
        } finally { \db()->query("SELECT RELEASE_LOCK('seller_africa_priority_intake')"); }
    }

    public static function syncCheckout(string $sessionId): ?array
    {
        $row=\db()->fetch('SELECT * FROM priority_memberships WHERE stripe_session_id=?',[$sessionId]);
        if(!$row) return null;
        self::stripe();
        $session=\Stripe\Checkout\Session::retrieve($sessionId);
        if(($session->metadata->context ?? '')!==self::CONTEXT || (string)$session->client_reference_id!==$row['reference']) return null;
        if(!$session->subscription) return $row;
        $subId=is_string($session->subscription)?$session->subscription:$session->subscription->id;
        $subscription=\Stripe\Subscription::retrieve($subId);
        if(!self::subscriptionMatches($row,$subscription)) throw new \RuntimeException('Membership payment details did not match. Please contact support.');
        $customer=is_string($session->customer)?$session->customer:$session->customer->id;
        \db()->query('UPDATE priority_memberships SET stripe_subscription_id=?,stripe_customer_id=? WHERE id=?',[$subId,$customer,$row['id']]);
        if($session->payment_status==='paid' && (int)$session->amount_total===self::plans()[$row['plan']]['amount'] && strtolower((string)$session->currency)==='usd') {
            $updated=\db()->query('UPDATE priority_memberships SET first_paid_at=COALESCE(first_paid_at,NOW()),checkout_paid_at=NOW() WHERE id=? AND checkout_paid_at IS NULL',[$row['id']]);
            if($updated->rowCount()>0) {
                try {
                    NotificationService::enqueueEmail($row['email'],'Welcome to Seller Africa Priority','You are a Priority Member','<p>Hi '.\e($row['name']).',</p><p>Your founding membership payment is confirmed. Our team will email your private community access details.</p><p>You can manage your membership or cancel renewal using the secure email link requested on our Priority page. Membership does not guarantee sales, contracts, funding or retail placement.</p>',['button_text'=>'Manage membership','button_url'=>\app_url('priority#manage-membership')]);
                    NotificationService::notifySuperAdmins('New Seller Africa Priority member','Priority membership confirmed','<p>'.\e($row['name']).' ('.\e($row['email']).') joined the '.\e($row['plan']).' plan. Please send their community invitation.</p>',['button_text'=>'View members','button_url'=>\app_url('dashboard/priority-members')]);
                } catch(\Throwable $e){\error_log('Priority welcome email: '.$e->getMessage());}
            }
        }
        self::applySubscription($row,$subscription);
        return \db()->fetch('SELECT * FROM priority_memberships WHERE id=?',[$row['id']]);
    }

    public static function subscriptionMatches(array $row,object $sub): bool
    {
        $items=$sub->items->data ?? [];
        $price=self::plans()[$row['plan']] ?? null;
        return $price && count($items)===1
            && ($sub->metadata->context ?? '')===self::CONTEXT
            && (string)($sub->metadata->reference ?? '')===$row['reference']
            && (int)($items[0]->quantity ?? 0)===1
            && (int)($items[0]->price->unit_amount ?? 0)===$price['amount']
            && strtolower((string)($items[0]->price->currency ?? ''))==='usd'
            && ($items[0]->price->recurring->interval ?? '')===$price['interval']
            && (int)($items[0]->price->recurring->interval_count ?? 1)===1;
    }
    private static function applySubscription(array $row,object $sub): void
    {
        if(!self::subscriptionMatches($row,$sub)) throw new \RuntimeException('Unrecognized Priority subscription.');
        $end=(int)($sub->current_period_end ?? $sub->items->data[0]->current_period_end ?? 0);
        \db()->query('UPDATE priority_memberships SET status=?,current_period_end=?,cancel_at_period_end=? WHERE id=?',[(string)$sub->status,$end>0?date('Y-m-d H:i:s',$end):null,!empty($sub->cancel_at_period_end)?1:0,$row['id']]);
    }

    public static function webhook(string $payload,string $signature,?object $verifiedEvent=null): bool
    {
        $preview=json_decode($payload,true);
        $object=$preview['data']['object'] ?? [];
        $subId=$object['subscription'] ?? $object['parent']['subscription_details']['subscription'] ?? '';
        if(!is_string($subId))$subId='';
        $row=$subId?\db()->fetch('SELECT * FROM priority_memberships WHERE stripe_subscription_id=?',[$subId]):null;
        if(($object['metadata']['context'] ?? '')!==self::CONTEXT && !$row) return false;
        self::stripe();
        $secret=(string)(self::settings()['webhook_secret'] ?? '');
        if($verifiedEvent!==null) $event=$verifiedEvent;
        elseif($secret!=='') $event=\Stripe\Webhook::constructEvent($payload,$signature,$secret);
        else {
            if(!preg_match('/^evt_[A-Za-z0-9]+$/',(string)($preview['id'] ?? ''))) throw new \RuntimeException('Stripe event ID required.');
            $event=\Stripe\Event::retrieve($preview['id']);
        }
        $obj=$event->data->object;
        if(str_starts_with((string)$event->type,'checkout.session.') && ($obj->metadata->context ?? '')===self::CONTEXT) {
            self::syncCheckout((string)$obj->id);
        } else {
            $id=str_starts_with((string)$event->type,'customer.subscription.')?(string)$obj->id:(string)($obj->subscription ?? $obj->parent->subscription_details->subscription ?? '');
            $member=$id?\db()->fetch('SELECT * FROM priority_memberships WHERE stripe_subscription_id=?',[$id]):null;
            if($member && $member['stripe_session_id']) self::syncCheckout($member['stripe_session_id']);
        }
        return true;
    }

    public static function requestManagement(string $email): void
    {
        $email=strtolower(trim($email));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Enter a valid email address.');
        $row=\db()->fetch('SELECT * FROM priority_memberships WHERE email=? AND (stripe_subscription_id IS NOT NULL OR checkout_paid_at IS NOT NULL)',[$email]);
        if(!$row) return;
        $token=bin2hex(random_bytes(32));
        \db()->query('UPDATE priority_memberships SET management_hash=?,management_expires=DATE_ADD(NOW(),INTERVAL 1 HOUR) WHERE id=?',[hash('sha256',$token),$row['id']]);
        NotificationService::enqueueEmail($email,'Manage your Seller Africa Priority membership','Your secure membership link','<p>Use this link to view your membership and cancel renewal. It expires in one hour. If you did not request it, you can ignore this email.</p>',['button_text'=>'Manage membership','button_url'=>\app_url('priority?manage='.$token.'#membership')]);
    }

    public static function managed(string $token): ?array
    {
        if(!preg_match('/^[a-f0-9]{64}$/',$token)) return null;
        return \db()->fetch('SELECT * FROM priority_memberships WHERE management_hash=? AND management_expires>NOW()',[hash('sha256',$token)]) ?: null;
    }

    public static function cancelRenewal(string $token): void
    {
        $member=self::managed($token);
        if(!$member || !$member['stripe_subscription_id']) throw new \RuntimeException('This link has expired. Request a new membership email below.');
        self::stripe();
        $sub=\Stripe\Subscription::retrieve($member['stripe_subscription_id']);
        if(!self::subscriptionMatches($member,$sub)) throw new \RuntimeException('Membership could not be verified.');
        $sub=\Stripe\Subscription::update($member['stripe_subscription_id'],['cancel_at_period_end'=>true]);
        self::applySubscription($member,$sub);
        \audit('priority.renewal_cancelled','priority_memberships',(string)$member['id']);
    }
}
