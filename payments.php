<?php
/**
 * YBT Management Software — Payments Directory & Recording
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/services/payment-service.php';

Auth::requireLogin();
Permissions::require('payments.view');

$businessId = Auth::businessId();
$userId = Auth::id();

// Handle Record Payment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    Permissions::require('payments.record');
    if (!Csrf::validate($_POST['_csrf_token'] ?? '')) {
        Flash::error('Invalid security token.');
    } else {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);

        if ($customerId <= 0) {
            Flash::error('Please select a customer.');
        } elseif ($amount <= 0) {
            Flash::error('Please enter a valid payment amount.');
        } else {
            try {
                $payId = PaymentService::record($businessId, $_POST, $userId);
                Flash::success('Payment recorded successfully.');
                header('Location: ' . url('payments.php'));
                exit;
            } catch (Exception $e) {
                Flash::error('Failed to record payment: ' . $e->getMessage());
            }
        }
    }
}

// Fetch active customers and unpaid invoices for recording
$customers = Database::fetchAll("SELECT id, name, company_name FROM customers WHERE business_id = ? ORDER BY name ASC", [$businessId]);
$unpaidInvoices = Database::fetchAll(
    "SELECT id, customer_id, invoice_number, outstanding FROM invoices 
     WHERE business_id = ? AND status IN ('sent', 'partial', 'overdue') AND outstanding > 0 
     ORDER BY invoice_date DESC",
    [$businessId]
);

// Fetch Payments List
$payments = Database::fetchAll(
    "SELECT p.*, c.name as customer_name, c.company_name, i.invoice_number, i.id as inv_id
     FROM payments p
     JOIN customers c ON p.customer_id = c.id
     LEFT JOIN payment_allocations pa ON p.id = pa.payment_id
     LEFT JOIN invoices i ON pa.invoice_id = i.id
     WHERE p.business_id = ?
     ORDER BY p.payment_date DESC",
    [$businessId]
);

$pageTitle = 'Payments & Receipts';
require YBT_ROOT . '/includes/header.php';
?>

<div class="page-header d-flex justify-between align-center mb-4">
    <div>
        <h1 class="page-title">Payments & Receipts</h1>
        <p class="page-subtitle">Track collections, bank transactions, UPI remittances, and cash inflows</p>
    </div>
    <div class="page-header-actions">
        <?php if (Permissions::has('payments.record')): ?>
        <button type="button" class="btn btn-primary" data-modal-target="record-payment-modal">
            <i class="ti ti-cash mr-1"></i> Record Payment
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <h3 class="card-title">Payment Transactions (<?= count($payments) ?>)</h3>
        <div style="width: 250px;">
            <input type="text" class="form-input form-input-sm" placeholder="Filter payments..." data-table-filter="#payments-table">
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($payments)): ?>
            <div class="empty-state p-5 text-center">
                <i class="ti ti-cash text-4xl text-muted mb-2"></i>
                <h3>No Payments Recorded</h3>
                <p class="text-muted text-sm mb-3">Record incoming payments against client invoices.</p>
                <?php if (Permissions::has('payments.record')): ?>
                <button type="button" class="btn btn-primary btn-sm" data-modal-target="record-payment-modal">
                    <i class="ti ti-plus mr-1"></i> Record First Payment
                </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table" id="payments-table">
                    <thead>
                        <tr>
                            <th>Payment #</th>
                            <th>Customer</th>
                            <th>Allocated Invoice</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Reference / UTR</th>
                            <th class="text-right">Amount Received</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><strong><?= e($p['payment_number']) ?></strong></td>
                            <td>
                                <strong><?= e($p['customer_name']) ?></strong>
                                <?php if (!empty($p['company_name'])): ?>
                                    <div class="text-xs text-muted"><?= e($p['company_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($p['invoice_number'])): ?>
                                    <a href="<?= url('invoice-view.php?id=' . $p['inv_id']) ?>" class="badge badge-primary">
                                        <?= e($p['invoice_number']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted text-xs">Unallocated</span>
                                <?php endif; ?>
                            </td>
                            <td><?= formatDate($p['payment_date']) ?></td>
                            <td><span class="badge badge-subtle"><?= ucfirst(str_replace('_', ' ', $p['method'])) ?></span></td>
                            <td><code><?= e($p['reference'] ?: '—') ?></code></td>
                            <td class="text-right font-bold text-success">
                                <?= formatCurrency($p['amount']) ?>
                            </td>
                            <td>
                                <span class="badge badge-success">Completed</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal-backdrop <?= (isset($_GET['action']) && $_GET['action'] === 'record') ? 'active' : '' ?>" id="record-payment-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title"><i class="ti ti-cash mr-1 text-success"></i> Record Incoming Payment</h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="<?= url('payments.php') ?>" method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="record_payment">

            <div class="modal-body">
                <div class="form-group mb-3">
                    <label class="form-label required">Customer</label>
                    <select name="customer_id" class="form-select" id="pay-customer-select" required>
                        <option value="">— Select Customer —</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= e($c['name']) ?> <?= !empty($c['company_name']) ? '— ' . e($c['company_name']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Apply to Invoice (Optional)</label>
                    <select name="invoice_id" class="form-select">
                        <option value="">— Unallocated / Account Credit —</option>
                        <?php foreach ($unpaidInvoices as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ((int)($_GET['invoice_id'] ?? 0) === $u['id']) ? 'selected' : '' ?>>
                                <?= e($u['invoice_number']) ?> (Due: <?= formatCurrency($u['outstanding']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label required">Amount Received (₹)</label>
                        <input type="number" name="amount" class="form-input" placeholder="10000" min="0.01" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Payment Date</label>
                        <input type="date" name="payment_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label">Payment Method</label>
                        <select name="method" class="form-select">
                            <option value="bank_transfer" selected>Bank Transfer (NEFT/RTGS/IMPS)</option>
                            <option value="upi">UPI (GPay / PhonePe / Paytm)</option>
                            <option value="cheque">Cheque</option>
                            <option value="cash">Cash</option>
                            <option value="credit_card">Credit / Debit Card</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reference # / UTR / Cheque No.</label>
                        <input type="text" name="reference" class="form-input" placeholder="e.g. UTR-99201923">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Notes / Remarks</label>
                    <textarea name="notes" class="form-input" rows="2" placeholder="e.g. Received via HDFC current account"></textarea>
                </div>
            </div>

            <div class="modal-footer d-flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-success"><i class="ti ti-check mr-1"></i> Save Payment</button>
            </div>
        </form>
    </div>
</div>

<?php require YBT_ROOT . '/includes/footer.php'; ?>
