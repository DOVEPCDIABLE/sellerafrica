<?php
/**
 * File Folder Path: /app/config
 * File Path: /app/config/config.php
 * Designed by Daniel Pybexai Framework
 * ==============================================================================
 * MULTIVENDOR E-COMMERCE & ERP CORE CONFIGURATION
 * Summary: Central configuration file for the application. Returns an array 
 * of settings dynamically populated from environment variables where available.
 */

declare(strict_types=1);

return [
    
    'app' => [
        'name'            => $_ENV['APP_NAME'] ?? 'ERP Market',
        'env'             => $_ENV['APP_ENV'] ?? 'production', // 'development', 'staging', 'production'
        'debug'           => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'url'             => $_ENV['APP_URL'] ?? 'https://localhost/seller_africa',
        'timezone'        => $_ENV['APP_TIMEZONE'] ?? 'Africa/Lagos',
        'locale'          => 'en',
        'maintenance_mode'=> false,
    ],

    'security' => [
        // Argon2id is recommended for PHP 8.2+ password hashing
        'hash_algo'       => PASSWORD_ARGON2ID,
        'hash_options'    => [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 3,
        ],
        // JWT Secret for API/Mobile app tokens if needed in the future
        'jwt_secret'      => $_ENV['JWT_SECRET'] ?? 'change_this_to_a_secure_random_string',
        'session_name'    => 'ERP_MARKET_NODE',
        'session_lifetime'=> 2592000, // 30 days in seconds
    ],

    /* 
     * ==============================================================================
     * E-COMMERCE SETTINGS MOVED TO DATABASE
     * ==============================================================================
     * Settings like default_currency, affiliate_commission_rate, minimum_withdrawal, 
     * and vendor_commission are now managed via the Super Admin Panel.
     * Access them dynamically using \App\SettingsService::get('key')
     */

    'uploads' => [
        'max_file_size'   => 5 * 1024 * 1024, // 5 MB
        'allowed_images'  => ['image/jpeg', 'image/png', 'image/webp'],
        'allowed_docs'    => ['application/pdf'], // For KYC documents
        'storage_path'    => dirname(__DIR__, 2) . '/public/assets/uploads/',
    ],

    'mail' => [
        'mailer'       => 'smtp',
        'host'         => $_ENV['MAIL_HOST'] ?? 'smtp.mailtrap.io',
        'port'         => (int)($_ENV['MAIL_PORT'] ?? 2525),
        'username'     => $_ENV['MAIL_USERNAME'] ?? '',
        'password'     => $_ENV['MAIL_PASSWORD'] ?? '',
        'encryption'   => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@erpmarket.com',
        'from_name'    => $_ENV['MAIL_FROM_NAME'] ?? 'ERP Market',
    ],

    /* 
     * ==============================================================================
     * INTEGRATIONS SETTINGS MOVED TO DATABASE
     * ==============================================================================
     * API Keys (Aramex, Stripe, PayPal) are now stored securely in the database 
     * and managed via the Super Admin Panel for easy updates without touching code.
     */
];