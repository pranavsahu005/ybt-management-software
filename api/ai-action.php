<?php
/**
 * YBT Management Software — AI Action Proposal API
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../services/approval-service.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$proposalId = (int)($input['proposal_id'] ?? 0);
$action = $input['action'] ?? '';

if ($proposalId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid proposal ID.']);
    exit;
}

$businessId = Auth::businessId();
$userId = Auth::id();

if ($action === 'approve') {
    $res = ApprovalService::approve($proposalId, $businessId, $userId);
} elseif ($action === 'reject') {
    $res = ApprovalService::reject($proposalId, $businessId, $userId);
} else {
    $res = ['success' => false, 'error' => 'Invalid action.'];
}

echo json_encode($res);
