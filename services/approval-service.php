<?php
/**
 * YBT Management Software — AI Action Proposal & Approval Service
 * Enforces mandatory Owner sign-off before mutating persistent business records
 */

require_once __DIR__ . '/invoice-service.php';

class ApprovalService {
    /**
     * Approve and execute a pending proposal
     */
    public static function approve(int $proposalId, int $businessId, int $ownerUserId): array {
        // Enforce Owner verification
        if (!Auth::isOwner()) {
            return ['success' => false, 'error' => 'Only the Business Owner can approve AI action proposals.'];
        }

        $proposal = Database::fetch(
            "SELECT * FROM ai_action_proposals WHERE id = ? AND business_id = ? AND status = 'pending'",
            [$proposalId, $businessId]
        );

        if (!$proposal) {
            return ['success' => false, 'error' => 'Proposal not found or already processed.'];
        }

        $data = json_decode($proposal['proposal_data'], true) ?: [];
        $result = [];

        try {
            switch ($proposal['action_type']) {
                case 'create_invoice':
                    // Execute invoice creation
                    $cust = Database::fetch("SELECT id FROM customers WHERE business_id = ? LIMIT 1", [$businessId]);
                    $custId = $cust['id'] ?? 1;
                    $invId = InvoiceService::create($businessId, [
                        'customer_id' => $custId,
                        'invoice_date' => date('Y-m-d'),
                        'due_days' => 15,
                        'notes' => 'Generated via approved AI proposal.'
                    ], [
                        [
                            'description' => $data['description'] ?? 'Consulting Service',
                            'quantity' => 1,
                            'unit_price' => $data['amount'] ?? 10000.00,
                            'tax_rate' => 18
                        ]
                    ], $ownerUserId);
                    $result = ['invoice_id' => $invId, 'status' => 'created'];
                    break;

                case 'create_expense':
                    // Execute expense creation
                    $cat = Database::fetch("SELECT id FROM expense_categories WHERE business_id = ? LIMIT 1", [$businessId]);
                    $catId = $cat['id'] ?? 1;
                    $expId = Database::insert('expenses', [
                        'business_id' => $businessId,
                        'category_id' => $catId,
                        'description' => $data['description'] ?? 'Software Subscription',
                        'amount' => $data['amount'] ?? 2500.00,
                        'expense_date' => date('Y-m-d'),
                        'method' => 'credit_card',
                        'status' => 'active',
                        'created_by' => $ownerUserId
                    ]);
                    $result = ['expense_id' => $expId, 'status' => 'recorded'];
                    break;

                default:
                    $result = ['executed' => true];
                    break;
            }

            // Update proposal state
            Database::update('ai_action_proposals', [
                'status' => 'approved',
                'approved_by' => $ownerUserId,
                'approved_at' => date('Y-m-d H:i:s'),
                'executed_at' => date('Y-m-d H:i:s'),
                'result' => json_encode($result)
            ], 'id = ?', [$proposalId]);

            // Log event
            Database::insert('ai_approval_events', [
                'proposal_id' => $proposalId,
                'event_type' => 'approved',
                'user_id' => $ownerUserId,
                'details' => json_encode($result)
            ]);

            ActivityLog::log($businessId, $ownerUserId, 'ai_proposal_approved', 'ai_proposal', $proposalId, "Owner approved and executed AI proposal #{$proposalId}");

            return ['success' => true, 'result' => $result];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Reject a proposal
     */
    public static function reject(int $proposalId, int $businessId, int $ownerUserId): array {
        if (!Auth::isOwner()) {
            return ['success' => false, 'error' => 'Only the Business Owner can reject AI proposals.'];
        }

        Database::update('ai_action_proposals', [
            'status' => 'rejected',
            'approved_by' => $ownerUserId,
            'approved_at' => date('Y-m-d H:i:s')
        ], 'id = ? AND business_id = ?', [$proposalId, $businessId]);

        Database::insert('ai_approval_events', [
            'proposal_id' => $proposalId,
            'event_type' => 'rejected',
            'user_id' => $ownerUserId
        ]);

        ActivityLog::log($businessId, $ownerUserId, 'ai_proposal_rejected', 'ai_proposal', $proposalId, "Owner rejected AI proposal #{$proposalId}");

        return ['success' => true];
    }
}
