<?php
declare(strict_types=1);
namespace App;

final class DistributorPaymentService
{
    public const CONTEXT = 'retail_placement';
    public const PREFIX = 'SA-RETAIL-';

    public static function plans(): array
    {
        return [
            'starter'=>['name'=>'Retail Starter','price'=>500,'intro'=>'For brands preparing to become retail-ready.',
                'features'=>['Retail Readiness Assessment','Packaging & Label Review','Pricing & Margin Review','UPC/Barcode Review','Product Listing Review','Sell-Sheet Guidance','Market Positioning Recommendations','Retail Readiness Report'],
                'terms'=>'Retail placement not included.'],
            'access'=>['name'=>'Retail Access','price'=>1500,'intro'=>'For retail-ready brands seeking buyer access and placement opportunities.',
                'features'=>['Everything in Retail Starter','Product Presentation to Relevant Buyers','Buyer Outreach','Up to 3 Target Retail Opportunities','Buyer Introduction & Follow-Up','Retail Onboarding Support','Pricing & Wholesale Strategy','30-Day Placement Campaign'],
                'terms'=>'Success fee: 10% of the first purchase order secured through Seller Africa.'],
            'expansion'=>['name'=>'Retail Expansion','price'=>5000,'intro'=>'For established brands ready for broader U.S. retail distribution.',
                'features'=>['Full Retail Readiness Review','Retail Buyer Presentation','Up to 10 Target Retail Opportunities','Multi-Retailer Outreach Campaign','Buyer Meetings & Introductions','Wholesale Pricing Strategy','Retail Onboarding Coordination','Distribution Strategy','90-Day Placement Campaign','Dedicated Account Support'],
                'terms'=>'Success fee: 10% of purchase orders secured during the campaign.'],
        ];
    }

    public static function ensureSchema(): void
    {
        \db()->query("CREATE TABLE IF NOT EXISTS distributor_payments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            application_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            plan_code VARCHAR(24) NOT NULL,
            provider VARCHAR(20) NOT NULL,
            reference VARCHAR(100) NOT NULL UNIQUE,
            stripe_session_id VARCHAR(255) NULL UNIQUE,
            email VARCHAR(255) NOT NULL,
            amount_minor BIGINT UNSIGNED NOT NULL,
            currency CHAR(3) NOT NULL,
            terms_snapshot TEXT NOT NULL,
            checkout_url TEXT NULL,
            paid_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX application_paid (application_id,paid_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public static function latest(int $applicationId): ?array
    {
        if (!\table_exists('distributor_payments')) return null;
        return \db()->fetch('SELECT * FROM distributor_payments WHERE application_id=? ORDER BY (paid_at IS NOT NULL) DESC,id DESC LIMIT 1',[$applicationId]) ?: null;
    }

    public static function naira(float $usd): float
    {
        $rates=\db()->fetchAll("SELECT code FROM currencies WHERE code IN ('USD','NGN') AND is_enabled=1 AND exchange_rate>0");
        if(count($rates)!==2) throw new \RuntimeException('Naira pricing is temporarily unavailable. Please use Stripe or try again later.');
        $amount=PaymentService::convertAmount($usd,'USD','NGN');
        if(!is_finite($amount) || $amount<=0) throw new \RuntimeException('Naira pricing is unavailable.');
        return $amount;
    }

    private static function stripeSettings(): array
    {
        $method=\db()->fetch('SELECT * FROM payment_methods WHERE code=? LIMIT 1',['stripe']);
        return PaymentService::stripeSettings($method ?: null);
    }

    public static function checkout(int $userId, string $planCode, string $provider, ?int $quotedMinor): string
    {
        $plan=self::plans()[$planCode] ?? null;
        if(!$plan || !in_array($provider,['stripe','paystack'],true)) throw new \RuntimeException('Select a package and payment method.');
        $application=DistributorService::forUser($userId);
        if(!$application || $application['status']==='draft') throw new \RuntimeException('Submit your distributor application first.');
        $existing=self::latest((int)$application['id']);
        if(!empty($existing['paid_at'])) return \app_url('distributor?payment=1');
        if($existing && self::verify($existing['reference'])) return \app_url('distributor?payment=1');
        // Reuse a recent checkout for the same package to avoid duplicate submissions.
        if($existing && $existing['plan_code']===$planCode && $existing['provider']===$provider && $existing['checkout_url'] && ($provider==='stripe' || (int)$existing['amount_minor']===$quotedMinor) && strtotime($existing['created_at'])>time()-1800) return $existing['checkout_url'];
        $currency=$provider==='paystack'?'NGN':'USD';
        $amount=$provider==='paystack'?self::naira((float)$plan['price']):(float)$plan['price'];
        $minor=(int)round($amount*100);
        if($provider==='paystack' && $quotedMinor!==$minor) throw new \RuntimeException('The exchange rate has changed. Review the updated naira amount and try again.');
        $user=\db()->fetch('SELECT email FROM users WHERE id=?',[$userId]);
        $method=\db()->fetch('SELECT * FROM payment_methods WHERE code=? LIMIT 1',[$provider]);
        if(!$user || !$method || empty($method['is_active'])) throw new \RuntimeException('This payment method is temporarily unavailable.');
        $reference=self::PREFIX.strtoupper(bin2hex(random_bytes(12)));
        $terms=$plan['name'].'; USD '.$plan['price'].' one-time. '.$plan['terms'].' Retail acceptance and purchase orders are not guaranteed.';
        \db()->query('INSERT INTO distributor_payments (application_id,user_id,plan_code,provider,reference,email,amount_minor,currency,terms_snapshot) VALUES (?,?,?,?,?,?,?,?,?)',[$application['id'],$userId,$planCode,$provider,$reference,$user['email'],$minor,$currency,$terms]);
        if($provider==='paystack') {
            $response=PaymentService::initializePaystackTransaction($amount,'NGN',$user['email'],$reference,\app_url('paystack_callback.php?context=retail_placement'),['context'=>self::CONTEXT,'application_id'=>(string)$application['id'],'plan'=>$planCode],$method);
            $url=(string)($response['data']['authorization_url'] ?? '');
            if(parse_url($url,PHP_URL_SCHEME)!=='https' || parse_url($url,PHP_URL_HOST)!=='checkout.paystack.com') throw new \RuntimeException('Paystack could not start checkout. Please retry.');
            \db()->query('UPDATE distributor_payments SET checkout_url=? WHERE reference=?',[$url,$reference]);
        } else {
            $secret=trim((string)(self::stripeSettings()['secret_key'] ?? ''));
            if($secret==='') throw new \RuntimeException('Stripe is temporarily unavailable.');
            \Stripe\Stripe::setApiKey($secret);
            $session=\Stripe\Checkout\Session::create([
                'mode'=>'payment','customer_email'=>$user['email'],'client_reference_id'=>$reference,
                'success_url'=>\app_url('distributor?payment=1&session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url'=>\app_url('distributor?payment=1&cancelled=1'),
                'metadata'=>['context'=>self::CONTEXT,'reference'=>$reference],
                'line_items'=>[['quantity'=>1,'price_data'=>['currency'=>'usd','unit_amount'=>$minor,'product_data'=>['name'=>'Seller Africa '.$plan['name'],'description'=>$terms]]]],
            ],['idempotency_key'=>$reference]);
            $url=(string)$session->url;
            if($url==='') throw new \RuntimeException('Stripe could not start checkout. Please retry.');
            \db()->query('UPDATE distributor_payments SET stripe_session_id=?,checkout_url=? WHERE reference=?',[(string)$session->id,$url,$reference]);
        }
        return $url;
    }

    public static function matches(array $row,array $transaction): bool
    {
        return ($transaction['status'] ?? '')==='success'
            && ($transaction['reference'] ?? '')===$row['reference']
            && (int)($transaction['amount'] ?? 0)===(int)$row['amount_minor']
            && strtoupper((string)($transaction['currency'] ?? ''))===$row['currency']
            && strcasecmp((string)($transaction['email'] ?? ''),$row['email'])===0;
    }

    public static function verify(string $reference): bool
    {
        if(!str_starts_with($reference,self::PREFIX)) return false;
        $row=\db()->fetch('SELECT * FROM distributor_payments WHERE reference=?',[$reference]);
        if(!$row) return false;
        if($row['paid_at']) return true;
        if($row['provider']==='paystack') {
            $method=\db()->fetch('SELECT * FROM payment_methods WHERE code=? LIMIT 1',['paystack']);
            $result=PaymentService::verifyPaystackTransaction($reference,$method ?: null);
            if(empty($result['ok'])) return false;
            $tx=(array)($result['data']['data'] ?? []);
            $tx['email']=$tx['customer']['email'] ?? '';
        } else {
            if(empty($row['stripe_session_id'])) return false;
            \Stripe\Stripe::setApiKey((string)(self::stripeSettings()['secret_key'] ?? ''));
            $session=\Stripe\Checkout\Session::retrieve($row['stripe_session_id']);
            if(($session->metadata->context ?? '')!==self::CONTEXT || (string)$session->id!==$row['stripe_session_id']) return false;
            $tx=['status'=>$session->payment_status==='paid'?'success':'pending','reference'=>(string)$session->client_reference_id,'amount'=>$session->amount_total,'currency'=>$session->currency,'email'=>$session->customer_details->email ?? $session->customer_email ?? ''];
        }
        if(!self::matches($row,$tx)) return false;
        $changed=\db()->query('UPDATE distributor_payments SET paid_at=NOW() WHERE id=? AND paid_at IS NULL',[$row['id']]);
        if($changed->rowCount()>0) {
            \audit('distributor.payment_confirmed','distributor_payments',(string)$row['id'],[],['reference'=>$reference,'plan'=>$row['plan_code'],'currency'=>$row['currency'],'amount_minor'=>$row['amount_minor']]);
            try {
                NotificationService::enqueueEmail($row['email'],'Retail placement payment received','Payment confirmed','<p>Thank you. Your payment for '.\e(self::plans()[$row['plan_code']]['name']).' has been received. Your application remains subject to review. Retail acceptance and purchase orders are not guaranteed.</p>',['button_text'=>'View application','button_url'=>\app_url('distributor?payment=1')]);
                NotificationService::notifySuperAdmins('Retail placement payment received','Distributor payment','<p>'.\e($row['email']).' paid for '.\e(self::plans()[$row['plan_code']]['name']).'.</p>',['button_url'=>\app_url('dashboard/distributors')]);
            } catch(\Throwable $e) { \error_log('Retail payment notification: '.$e->getMessage()); }
        }
        return true;
    }

    public static function stripeWebhook(string $payload,string $signature,?object $verifiedEvent=null): bool
    {
        $preview=json_decode($payload,true);
        if(($preview['data']['object']['metadata']['context'] ?? '')!==self::CONTEXT) return false;
        $settings=self::stripeSettings();
        $secret=(string)($settings['webhook_secret'] ?? '');
        if($verifiedEvent!==null) $event=$verifiedEvent;
        elseif($secret!=='') {
            if($signature==='') throw new \RuntimeException('Stripe signature required.');
            $event=\Stripe\Webhook::constructEvent($payload,$signature,$secret);
        } else {
            // Authenticate legacy gateway events through Stripe's API, never the posted body.
            $eventId=(string)($preview['id'] ?? '');
            if(!preg_match('/^evt_[A-Za-z0-9]+$/',$eventId)) throw new \RuntimeException('Stripe event reference required.');
            \Stripe\Stripe::setApiKey((string)($settings['secret_key'] ?? ''));
            $event=\Stripe\Event::retrieve($eventId);
            if(($event->data->object->metadata->context ?? '')!==self::CONTEXT) throw new \RuntimeException('Stripe event context mismatch.');
        }
        if(in_array($event->type,['checkout.session.completed','checkout.session.async_payment_succeeded'],true)) self::verify((string)($event->data->object->metadata->reference ?? ''));
        return true;
    }

    public static function page(array $user,array $application): void
    {
        header('Cache-Control: private, no-store');
        self::ensureSchema();
        $error='';
        try {
            $latest=self::latest((int)$application['id']);
            if($latest && !$latest['paid_at']) self::verify($latest['reference']);
            if(!empty($_GET['session_id'])) {
                $row=\db()->fetch('SELECT reference FROM distributor_payments WHERE stripe_session_id=? AND user_id=?',[(string)$_GET['session_id'],$user['id']]);
                if($row) self::verify($row['reference']);
            }
            if(($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
                \validateCsrf();
                if(($_POST['accept_terms'] ?? '')!=='1') throw new \RuntimeException('Please accept the package terms before continuing.');
                $plan=(string)($_POST['plan'] ?? '');
                $quote=$_SESSION['retail_quotes'][$plan] ?? null;
                \redirect(self::checkout((int)$user['id'],$plan,(string)($_POST['provider'] ?? ''),is_int($quote)?$quote:null));
            }
        } catch(\Throwable $e) { \error_log('Retail checkout: '.$e->getMessage()); $error=$e instanceof \Stripe\Exception\ApiErrorException?'Stripe could not start payment. Please retry or choose Paystack.':$e->getMessage(); }
        $plans=self::plans();$quotes=[];
        foreach($plans as $key=>$plan) { try {$quotes[$key]=(int)round(self::naira((float)$plan['price'])*100);}catch(\Throwable $e){$quotes[$key]=null;} }
        $_SESSION['retail_quotes']=$quotes;
        $payment=self::latest((int)$application['id']);
        \render_layout('vendor-onboarding','distributor/payment.php',compact('user','application','plans','quotes','payment','error')+['title'=>'Retail Placement Packages']);
    }
}
