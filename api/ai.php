<?php
/**
 * YBT Management Software — AI Assistant Chat API
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../services/ai-service.php';
require_once __DIR__ . '/../services/ai-memory-service.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if ($message === '') {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
    exit;
}

$businessId = Auth::businessId();
$userId = Auth::id();

$convId = AIMemoryService::getOrCreateConversation($businessId, $userId);
AIMemoryService::logMessage($convId, 'user', $message);

$result = AIService::process($businessId, $userId, $message, $convId);

if ($result['success']) {
    AIMemoryService::logMessage($convId, 'assistant', $result['response'], [
        'has_proposal' => !empty($result['proposal'])
    ]);
}

echo json_encode($result);
