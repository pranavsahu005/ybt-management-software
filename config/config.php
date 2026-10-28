<?php
/**
 * YBT Management Software — Application Configuration
 * Environment-specific settings. Keep production secrets out of version control.
 */

// Prevent direct access
if (!defined('YBT_ROOT')) {
    define('YBT_ROOT', dirname(__DIR__));
}

$base = [
    // Application
    'app_name'    => 'YBT Management Software',
    'app_version' => '1.0.0',
    'app_url'     => 'http://localhost/ybt-management-software-main',
    'environment' => 'development', // development | production
    'debug'       => true,
    'timezone'    => 'Asia/Kolkata',

    // Database
    'db_host'     => '127.0.0.1',
    'db_port'     => 3307,
    'db_name'     => 'ybt_management',
    'db_user'     => 'root',
    'db_pass'     => '',
    'db_charset'  => 'utf8mb4',

    // Session
    'session_name'     => 'YBT_SESSION',
    'session_lifetime' => 7200, // 2 hours
    'session_secure'   => false, // set true under HTTPS
    'session_httponly'  => true,
    'session_samesite'  => 'Lax',

    // Storage
    'storage_path'  => YBT_ROOT . '/storage',
    'upload_max_mb' => 10,

    // Security
    'login_max_attempts'  => 5,
    'login_lockout_mins'  => 15,
    'password_reset_ttl'  => 3600, // 1 hour
    'csrf_token_lifetime' => 3600,

    // Encryption key for API keys at rest (change in production!)
    'encryption_key' => 'CHANGE_THIS_IN_PRODUCTION_32_CHAR_KEY!',
];

// Merge local environment config if present
$localConfigFile = __DIR__ . '/config.local.php';
if (file_exists($localConfigFile)) {
    $localConfig = require $localConfigFile;
    if (is_array($localConfig)) {
        $base = array_replace_recursive($base, $localConfig);
    }
}

return $base;
