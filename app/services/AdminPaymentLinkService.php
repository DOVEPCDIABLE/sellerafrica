<?php
declare(strict_types=1);
namespace App;

final class AdminPaymentLinkService
{
    public static function ensureSchema(): void
    {
        \db()->query('CREATE TABLE IF NOT EXISTS admin_payment_links (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_email VARCHAR(190) NOT NULL, customer_name VARCHAR(190) NOT NULL, service VARCHAR(80) NOT NULL, label VARCHAR(255) NOT NULL, provider VARCHAR(20) NOT NULL, checkout_url TEXT NOT NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(customer_email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    public static function catalog(): array
    {
        $both=['stripe','paystack'];
        $items=[
            'manage'=>['label'=>'Manage My Store - $12 for one year','providers'=>$both],
            'setup'=>['label'=>'Set Up My Store - $5 one-time','providers'=>$both],
            'rank'=>['label'=>'Rank Products - $5/month','providers'=>$both],
            'priority_monthly'=>['label'=>'Priority Membership - $5/month','providers'=>$both],
            'priority_annual'=>['label'=>'Priority Membership - $50/year','providers'=>$both],
            'registration'=>['label'=>'Vendor registration fee - NGN 1,500','providers'=>['paystack']],
        ];
        VendorSubscriptionService::ensureSchema();
        foreach(VendorSubscriptionService::packages() as $p){
            if((float)$p['price']>0)$items['vendor_'.(int)$p['id']]=['label'=>'Vendor plan: '.$p['name'].' - '.($p['currency'] ?? 'USD').' '.number_format((float)$p['price'],2),'providers'=>$both];
        }
        foreach(DistributorPaymentService::plans() as $code=>$p)$items['retail_'.$code]=['label'=>$p['name'].' - $'.number_format((float)$p['price'],2).' one-time','providers'=>$both];
        return $items;
    }

    public static function safeUrl(string $url): bool
    {
        return parse_url($url,PHP_URL_SCHEME)==='https' && in_array(parse_url($url,PHP_URL_HOST),['checkout.stripe.com','checkout.paystack.com'],true);
    }

    public static function generate(string $email,string $name,string $service,string $provider,int $actorId): int
    {
        $email=strtolower(trim($email));$name=trim($name);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190 || $name==='' || strlen($name)>190)throw new \RuntimeException('Enter the customer name and a valid email address.');
        $item=self::catalog()[$service] ?? null;
        if(!$item || !in_array($provider,$item['providers'],true))throw new \RuntimeException('Choose an available service and payment method.');
        $user=\db()->fetch('SELECT * FROM users WHERE email=? AND status<>"deleted" LIMIT 1',[$email]);
        $vendor=$user?\db()->fetch('SELECT * FROM vendors WHERE user_id=? LIMIT 1',[$user['id']]):null;
        if(($service==='rank' || $service==='registration' || str_starts_with($service,'vendor_')) && !$vendor)throw new \RuntimeException('This service requires an existing vendor account for the email provided.');
        if(str_starts_with($service,'retail_') && !$user)throw new \RuntimeException('A distributor account and submitted application are required.');
        $lock='payment_link_'.substr(hash('sha256',$email),0,40);
        if((int)(\db()->fetch('SELECT GET_LOCK(?,10) AS acquired',[$lock])['acquired'] ?? 0)!==1)throw new \RuntimeException('Another request is processing. Please try again shortly.');
        try {
            if(in_array($service,['manage','setup'],true)){
                $plan=$service==='setup'?'setup':'annual';
                $paid=ManageStoreService::activeServiceForEmail($email,$plan);
                if($paid && in_array($paid['status'],['active','paid','trialing'],true))throw new \RuntimeException('This store service has already been paid for.');
            } elseif($service==='rank'){
                VisibilityBoostService::ensureSchema();
                $boost=VisibilityBoostService::activeBoost((int)$vendor['id']);
                if($boost && in_array($boost['status'],['active','trialing'],true))throw new \RuntimeException('This vendor already has active product ranking.');
            }
            $recent=\db()->fetch('SELECT id FROM admin_payment_links WHERE customer_email=? AND service=? AND provider=? AND created_at>DATE_SUB(NOW(),INTERVAL 20 MINUTE) ORDER BY id DESC LIMIT 1',[$email,$service,$provider]);
            if($recent)return (int)$recent['id'];
            if(in_array($service,['manage','setup'],true)){
                $url=$provider==='paystack'?ManageStoreService::createPaystackAnnualCheckout($name,$email,(string)($user['phone'] ?? ''),$plan):ManageStoreService::createStripeCheckoutSession($name,$email,$plan,\app_url('manage-store?subscribed=1'),\app_url('manage-store?cancelled=1'));
            } elseif(str_starts_with($service,'priority_')){
                PriorityMembershipService::ensureSchema();
                $url=$provider==='paystack'?PriorityPaystackService::checkout($name,$email,substr($service,9)):PriorityMembershipService::checkout($name,$email,substr($service,9));
            } elseif($service==='rank'){
                $url=VisibilityBoostService::createCheckoutSession((int)$vendor['id'],\app_url('visibility-boost?subscribed=1'),\app_url('visibility-boost?cancelled=1'),$provider);
            } elseif($service==='registration'){
                $url=VendorRegistrationPaymentService::checkout((int)$vendor['id']);
            } elseif(str_starts_with($service,'vendor_')){
                $url=VendorSubscriptionService::createCheckoutSession((int)$vendor['id'],(int)substr($service,7),\app_url('vendor/plans?subscribed=1'),\app_url('vendor/plans?cancelled=1'),$provider);
            } else {
                DistributorPaymentService::ensureSchema();
                $code=substr($service,7);$retail=DistributorPaymentService::plans()[$code];
                $quote=$provider==='paystack'?(int)round(DistributorPaymentService::naira((float)$retail['price'])*100):null;
                $url=DistributorPaymentService::checkout((int)$user['id'],$code,$provider,$quote);
            }
            if(!self::safeUrl($url))throw new \RuntimeException('No payment is due, or the provider did not return a checkout link. Check the account before trying again.');
            \db()->query('INSERT INTO admin_payment_links (customer_email,customer_name,service,label,provider,checkout_url,created_by) VALUES (?,?,?,?,?,?,?)',[$email,$name,$service,$item['label'],$provider,$url,$actorId]);
            $id=(int)\db()->lastInsertId();
            \audit('payment_link.created','admin_payment_links',(string)$id,[],['service'=>$service,'provider'=>$provider]);
            return $id;
        } finally {\db()->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }

    public static function email(int $id): void
    {
        $row=\db()->fetch('SELECT * FROM admin_payment_links WHERE id=?',[$id]);
        if(!$row || !self::safeUrl($row['checkout_url']))throw new \RuntimeException('Payment link not found.');
        if(strtotime($row['created_at'])<time()-1200)throw new \RuntimeException('Generate a fresh checkout link before emailing this customer.');
        NotificationService::enqueueEmail($row['customer_email'],'Your Seller Africa payment link','Complete your payment','<p>Hi '.\e($row['customer_name']).',</p><p>'.\e($row['label']).'</p><p>Please review the amount, currency and any recurring billing terms on the secure checkout page before paying. Paystack displays the converted amount. Your service is activated only after payment verification. Contact our team if your link has expired.</p>',['button_text'=>'Review and pay','button_url'=>$row['checkout_url']]);
        \audit('payment_link.emailed','admin_payment_links',(string)$id);
    }
}
