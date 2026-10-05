<?php

use App\Core\Env;

return [
    'stripe' => [
        'public_key' => Env::get('STRIPE_PUBLIC_KEY', ''),
        'secret_key' => Env::get('STRIPE_SECRET_KEY', ''),
        'webhook_secret' => Env::get('STRIPE_WEBHOOK_SECRET', ''),
    ],
    'paystack' => [
        'public_key' => Env::get('PAYSTACK_PUBLIC_KEY', ''),
        'secret_key' => Env::get('PAYSTACK_SECRET_KEY', ''),
        'webhook_secret' => Env::get('PAYSTACK_WEBHOOK_SECRET', ''),
        'mode' => Env::get('PAYSTACK_MODE', 'test'),
        'initialize_endpoint' => Env::get('PAYSTACK_INITIALIZE_ENDPOINT', 'https://api.paystack.co/transaction/initialize'),
        'verify_endpoint' => Env::get('PAYSTACK_VERIFY_ENDPOINT', 'https://api.paystack.co/transaction/verify'),
    ],
    'paypal' => [
        'client_id' => Env::get('PAYPAL_CLIENT_ID', ''),
        'client_secret' => Env::get('PAYPAL_CLIENT_SECRET', ''),
        'mode' => Env::get('PAYPAL_MODE', 'sandbox'),
    ],
];
