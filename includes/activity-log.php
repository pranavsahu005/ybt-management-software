<?php
/**
 * YBT Management Software — Activity Log
 */

class ActivityLog {
    /**
     * Log an activity
     */
    public static function log(
        int $businessId,
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            Database::insert(
                "INSERT INTO activity_logs (business_id, user_id, action, entity_type, entity_id, description, old_values, new_values, ip_address, user_agent) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $businessId,
                    $userId,
                    $action,
                    $entityType,
                    $entityId,
                    $description,
                    $oldValues ? json_encode($oldValues) : null,
                    $newValues ? json_encode($newValues) : null,
                    clientIp(),
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                ]
            );
        } catch (Exception $e) {
            error_log('ActivityLog error: ' . $e->getMessage());
        }
    }

    /**
     * Get recent activity for a business
     */
    public static function recent(int $businessId, int $limit = 20, int $offset = 0): array {
        return Database::fetchAll(
            "SELECT al.*, u.name as user_name 
             FROM activity_logs al 
             LEFT JOIN users u ON al.user_id = u.id 
             WHERE al.business_id = ? 
             ORDER BY al.created_at DESC 
             LIMIT ? OFFSET ?",
            [$businessId, $limit, $offset]
        );
    }

    /**
     * Get activity for a specific entity
     */
    public static function forEntity(string $entityType, int $entityId, int $limit = 50): array {
        return Database::fetchAll(
            "SELECT al.*, u.name as user_name 
             FROM activity_logs al 
             LEFT JOIN users u ON al.user_id = u.id 
             WHERE al.entity_type = ? AND al.entity_id = ? 
             ORDER BY al.created_at DESC 
             LIMIT ?",
            [$entityType, $entityId, $limit]
        );
    }

    /**
     * Count activity logs
     */
    public static function count(int $businessId): int {
        return (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM activity_logs WHERE business_id = ?",
            [$businessId]
        );
    }
}
