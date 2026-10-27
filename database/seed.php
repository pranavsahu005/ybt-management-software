<?php
/**
 * YBT Management Software — Database Seeder Runner
 */

define('YBT_ROOT', dirname(__DIR__));
require_once YBT_ROOT . '/includes/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}
echo "=== YBT Management Software Seeder ===\n";

try {
    $db = Database::getInstance();

    // 1. Check if tables exist, if not import schema
    $tables = Database::fetchAll("SHOW TABLES LIKE 'businesses'");
    if (empty($tables)) {
        $schemaFile = YBT_ROOT . '/database/schema.sql';
        if (file_exists($schemaFile)) {
            echo "Importing schema.sql...\n";
            $sql = file_get_contents($schemaFile);
            $db->exec($sql);
            echo "✓ Schema imported.\n";
        }
    } else {
        echo "✓ Tables already exist.\n";
    }

    // 2. Run demo-data.sql
    $demoFile = YBT_ROOT . '/database/demo-data.sql';
    if (file_exists($demoFile)) {
        echo "Importing demo-data.sql...\n";
        $sql = file_get_contents($demoFile);
        $db->exec($sql);
        echo "✓ Demo data imported.\n";
    }

    // 3. Ensure known password hash for demo accounts
    $pwdHash = password_hash('password123', PASSWORD_DEFAULT);
    Database::query("UPDATE users SET password = ? WHERE email IN ('owner@ybt.com', 'manager@ybt.com')", [$pwdHash]);
    echo "✓ Demo passwords set to 'password123'.\n";

    echo "\n=== Seeding Finished Successfully! ===\n";
    echo "Demo Credentials:\n";
    echo "  Owner:   owner@ybt.com   / password123\n";
    echo "  Manager: manager@ybt.com / password123\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
