<?php
/**
 * YBT Management Software — Test AI Provider API Connection
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../services/ai-provider-service.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

if (!Permissions::has('settings.edit') && !Auth::isOwner()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Owner or Settings permission required.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input.']);
    exit;
}

$providerType = trim($input['provider_type'] ?? 'google_gemini');
$apiKey = trim($input['api_key'] ?? '');
$modelId = trim($input['model_id'] ?? '');
$baseUrl = trim($input['base_url'] ?? '');
$providerId = (int)($input['provider_id'] ?? 0);

// If API key was not entered in the form but an existing provider ID was passed, use stored key
if (empty($apiKey) && $providerId > 0) {
    $existing = Database::fetch(
        "SELECT api_key_encrypted FROM ai_provider_configs WHERE id = ? AND business_id = ?",
        [$providerId, Auth::businessId()]
    );
    if ($existing && !empty($existing['api_key_encrypted'])) {
        $apiKey = decryptString($existing['api_key_encrypted']);
    }
}

if (empty($apiKey) && $providerType !== 'custom') {
    echo json_encode(['success' => false, 'error' => 'Please enter an API Key to test the connection.']);
    exit;
}

$result = AIProviderService::testConnection($providerType, $apiKey, $modelId, $baseUrl);
echo json_encode($result);
