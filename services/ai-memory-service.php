<?php
/**
 * YBT Management Software — AI Memory Service
 * Manages conversation context, business facts, and prompt configurations
 */

class AIMemoryService {
    /**
     * Get or create active conversation
     */
    public static function getOrCreateConversation(int $businessId, int $userId): int {
        $conv = Database::fetch(
            "SELECT id FROM ai_conversations WHERE business_id = ? AND user_id = ? ORDER BY updated_at DESC LIMIT 1",
            [$businessId, $userId]
        );

        if ($conv) return (int)$conv['id'];

        return Database::insert('ai_conversations', [
            'business_id' => $businessId,
            'user_id' => $userId,
            'title' => 'Business Operations Session'
        ]);
    }

    /**
     * Store message
     */
    public static function logMessage(int $conversationId, string $role, string $content, ?array $meta = null): int {
        return Database::insert('ai_messages', [
            'conversation_id' => $conversationId,
            'role' => $role,
            'content' => $content,
            'metadata' => $meta ? json_encode($meta) : null
        ]);
    }

    /**
     * Get conversation messages
     */
    public static function getMessages(int $conversationId, int $limit = 30): array {
        return Database::fetchAll(
            "SELECT * FROM ai_messages WHERE conversation_id = ? ORDER BY created_at ASC LIMIT {$limit}",
            [$conversationId]
        );
    }
}
