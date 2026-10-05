<?php
/**
 * File Folder Path: /app/config
 * File Path: /app/config/database.php
 * Designed by Daniel Pybexai Framework
 * ==============================================================================
 * DATABASE CONFIGURATION
 * Summary: Returns the PDO connection parameters used by the \App\Database 
 * singleton in bootstrap.php. Environment variables are used for security.
 */

declare(strict_types=1);

return [
    // Database Driver (mysql, pgsql, sqlite, sqlsrv)
    'driver'    => $_ENV['DB_CONNECTION'] ?? 'mysql',
    
    // Server / Hostname
    'host'      => $_ENV['DB_HOST'] ?? '127.0.0.1',
    
    // Port (Default MySQL: 3306, PostgreSQL: 5432)
    'port'      => (int)($_ENV['DB_PORT'] ?? 3306),
    
    // Database Name
    'database'  => $_ENV['DB_DATABASE'] ?? 'seller-africa',
    
    // Credentials
    'username'  => $_ENV['DB_USERNAME'] ?? 'root',
    'password'  => $_ENV['DB_PASSWORD'] ?? '',
    
    // Character Set and Collation (utf8mb4 supports full unicode including emojis)
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    
    // PDO Connection Options
    'options'   => [
        // Throw exceptions on SQL errors (critical for debugging and transactions)
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        
        // Fetch rows as associative arrays by default to prevent duplicate data
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        
        // Disable emulated prepared statements for strict typing and better security
        PDO::ATTR_EMULATE_PREPARES   => false,
        
        // Connection persistence (keep false unless explicitly needed for high concurrency tuning)
        PDO::ATTR_PERSISTENT         => false,
    ],
];