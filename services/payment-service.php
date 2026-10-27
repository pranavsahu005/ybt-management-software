<?php
/**
 * YBT Management Software — Payment Service
 * Handles recording payments, allocating to invoices, and balance adjustments
 */

class PaymentService {
    /**
     * Record a payment and optionally allocate to one or more invoices
     */
    public static function record(int $businessId, array $data, int $userId): int {
        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $customerId = (int)$data['customer_id'];
            $amount = max(0.01, (float)$data['amount']);
            $method = $data['method'] ?? 'bank_transfer';
            $paymentDate = $data['payment_date'] ?? date('Y-m-d');
            $ref = trim($data['reference'] ?? '');
            $notes = trim($data['notes'] ?? '');

            // Sequence payment number
            $payCount = Database::fetch("SELECT COUNT(*) as cnt FROM payments WHERE business_id = ?", [$businessId]);
            $num = ($payCount['cnt'] ?? 0) + 1;
            $paymentNumber = 'PAY-' . date('Y') . '-' . str_pad((string)$num, 4, '0', STR_PAD_LEFT);

            // Insert payment record
            $paymentId = Database::insert('payments', [
                'business_id' => $businessId,
                'customer_id' => $customerId,
                'payment_number' => $paymentNumber,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'method' => $method,
                'reference' => $ref,
                'notes' => $notes,
                'status' => 'completed',
                'created_by' => $userId
            ]);

            // Allocate to specific invoice if passed
            $invoiceId = !empty($data['invoice_id']) ? (int)$data['invoice_id'] : null;
            if ($invoiceId) {
                self::allocateToInvoice($paymentId, $invoiceId, $amount, $businessId);
            }

            ActivityLog::log($businessId, $userId, 'payment_recorded', 'payment', $paymentId, "Recorded payment {$paymentNumber} of ₹" . number_format($amount, 2));

            $db->commit();
            return $paymentId;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Allocate payment amount to an invoice and recalculate invoice status
     */
    public static function allocateToInvoice(int $paymentId, int $invoiceId, float $amount, int $businessId): void {
        $inv = Database::fetch("SELECT * FROM invoices WHERE id = ? AND business_id = ?", [$invoiceId, $businessId]);
        if (!$inv) return;

        // Insert allocation
        Database::insert('payment_allocations', [
            'payment_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'amount' => $amount
        ]);

        // Recompute invoice paid & outstanding
        $allocRow = Database::fetch("SELECT COALESCE(SUM(amount), 0) as total FROM payment_allocations WHERE invoice_id = ?", [$invoiceId]);
        $totalPaid = (float)($allocRow['total'] ?? 0);
        $outstanding = max(0, (float)$inv['grand_total'] - $totalPaid);

        $newStatus = 'sent';
        if ($outstanding <= 0.01) {
            $newStatus = 'paid';
        } elseif ($totalPaid > 0) {
            $newStatus = 'partial';
        }

        Database::update('invoices', [
            'amount_paid' => $totalPaid,
            'outstanding' => $outstanding,
            'status' => $newStatus
        ], 'id = ?', [$invoiceId]);
    }
}
