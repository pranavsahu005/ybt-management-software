<?php
/**
 * YBT Management Software — Restore & Safe Data Reset Service
 * Provides transactional backup restore and demo data reset
 */

class RestoreService {
    /**
     * Restore from backup file
     */
    public static function restore(string $backupFilename, int $businessId, int $userId): array {
        if (!Auth::isOwner()) {
            return ['success' => false, 'error' => 'Restore operations require Owner authorization.'];
        }

        $filepath = YBT_ROOT . '/storage/backups/' . basename($backupFilename);
        if (!file_exists($filepath)) {
            return ['success' => false, 'error' => 'Backup file not found.'];
        }

        $sql = file_get_contents($filepath);
        if (!$sql) {
            return ['success' => false, 'error' => 'Backup file is empty.'];
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $db->exec($sql);
            $db->commit();

            ActivityLog::log($businessId, $userId, 'backup_restored', 'backup', 0, "Restored system backup {$backupFilename}");
            return ['success' => true];
        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Reset Demo Data
     */
    public static function resetDemoData(int $businessId, int $userId): array {
        if (!Auth::isOwner()) {
            return ['success' => false, 'error' => 'Resetting demo data requires Owner authorization.'];
        }

        $demoFile = YBT_ROOT . '/database/demo-data.sql';
        if (!file_exists($demoFile)) {
            return ['success' => false, 'error' => 'Demo data seed file not found.'];
        }

        try {
            $db = Database::getInstance();
            $sql = file_get_contents($demoFile);
            $db->exec($sql);

            // Re-hash password
            $pwdHash = password_hash('password123', PASSWORD_DEFAULT);
            Database::query("UPDATE users SET password = ? WHERE email IN ('owner@ybt.com', 'manager@ybt.com')", [$pwdHash]);

            ActivityLog::log($businessId, $userId, 'demo_data_reset', 'system', 0, "Reset business demo data to initial factory state");

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
