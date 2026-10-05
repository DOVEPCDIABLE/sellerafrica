<?php
declare(strict_types=1);
namespace App;

final class StripeWebhookService
{
    public static function ensureSchema(): void
    {
        \db()->query('CREATE TABLE IF NOT EXISTS stripe_webhook_receipts (event_id VARCHAR(190) PRIMARY KEY,event_type VARCHAR(100) NOT NULL,processed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    public static function authenticate(string $payload,string $signature,bool $legacy=false): object
    {
        if($legacy){
            // The old WooCommerce endpoint secret is unavailable. Fetch the canonical
            // event from this Stripe account instead of trusting any posted fields.
            $id=(string)(json_decode($payload,true)['id'] ?? '');
            if(!preg_match('/^evt_[A-Za-z0-9]+$/',$id))throw new \InvalidArgumentException('A valid Stripe event ID is required.');
            $method=\db()->fetch('SELECT * FROM payment_methods WHERE code=? LIMIT 1',['stripe']);
            $settings=PaymentService::stripeSettings($method ?: null);
            \Stripe\Stripe::setApiKey((string)($settings['secret_key'] ?? ''));
            return \Stripe\Event::retrieve($id);
        }
        $file=dirname(\APP_ROOT).'/.marketplace-stripe-webhook-secret';
        $secret=is_readable($file)?trim((string)file_get_contents($file)):'';
        if($secret==='')$secret=trim((string)(PaymentService::stripeSettings()['webhook_secret'] ?? ''));
        if($secret==='' || $signature==='')throw new \InvalidArgumentException('Stripe webhook signature is required.');
        return \Stripe\Webhook::constructEvent($payload,$signature,$secret);
    }

    public static function process(object $event): bool
    {
        $id=(string)($event->id ?? '');
        if(!preg_match('/^evt_[A-Za-z0-9]+$/',$id))throw new \InvalidArgumentException('Invalid event ID.');
        $lock='stripe_evt_'.substr(hash('sha256',$id),0,40);
        if((int)(\db()->fetch('SELECT GET_LOCK(?,10) AS acquired',[$lock])['acquired'] ?? 0)!==1)throw new \RuntimeException('Event is processing. Retry shortly.');
        try {
            if(\db()->fetch('SELECT event_id FROM stripe_webhook_receipts WHERE event_id=?',[$id]))return false;
            $payload=json_encode($event,JSON_THROW_ON_ERROR);
            VendorSubscriptionService::ensureSchema();
            $handled=PriorityMembershipService::webhook($payload,'',$event);
            if(!$handled)$handled=DistributorPaymentService::stripeWebhook($payload,'',$event);
            if(!$handled)$handled=FrozenMarketService::handleStripeWebhook($payload,'',$event);
            if(!$handled)$handled=VisibilityBoostService::handleStripeWebhook($payload,'',$event);
            if(!$handled)$handled=ManageStoreService::handleStripeWebhook($payload,'',$event);
            if(!$handled)$handled=FarmFreshMembershipService::handleStripeWebhook($payload,'',$event);
            if(!$handled)$handled=PaymentService::handleStripeWebhook($payload,'',$event);
            if(!$handled)VendorSubscriptionService::handleStripeWebhook($payload,'',$event);
            \db()->query('INSERT INTO stripe_webhook_receipts (event_id,event_type) VALUES (?,?)',[$id,(string)$event->type]);
            return true;
        }finally{\db()->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
}
