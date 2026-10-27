<?php
/**
 * YBT Management Software — Customer Profile & Financial Ledger
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/services/customer-service.php';

Auth::requireLogin();
Permissions::require('customers.view');

$businessId = Auth::businessId();
$userId = Auth::id();
$customerId = (int)($_GET['id'] ?? 0);

if ($customerId <= 0) {
    Flash::error('Customer not found.');
    header('Location: ' . url('customers.php'));
    exit;
}

// Handle Customer Edit POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_customer') {
    Permissions::require('customers.edit');
    if (!Csrf::validate($_POST['_csrf_token'] ?? '')) {
        Flash::error('Invalid security token.');
    } else {
        CustomerService::update($customerId, $businessId, $_POST);
        ActivityLog::log($businessId, $userId, 'customer_updated', 'customer', $customerId, "Updated profile for customer #{$customerId}");
        Flash::success('Customer profile updated successfully.');
        header('Location: ' . url('customer-view.php?id=' . $customerId));
        exit;
    }
}

$customer = CustomerService::getById($customerId, $businessId);
if (!$customer) {
    Flash::error('Customer not found.');
    header('Location: ' . url('customers.php'));
    exit;
}

$pageTitle = $customer['name'] . ' — Customer Profile';
require YBT_ROOT . '/includes/header.php';
?>

<div class="page-header d-flex justify-between align-center mb-4">
    <div>
        <div class="d-flex align-center gap-2 mb-1">
            <a href="<?= url('customers.php') ?>" class="text-muted"><i class="ti ti-arrow-left"></i> Customers</a>
            <span class="text-muted">/</span>
            <span class="badge badge-<?= $customer['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($customer['status']) ?></span>
        </div>
        <h1 class="page-title"><?= e($customer['name']) ?></h1>
        <?php if (!empty($customer['company_name'])): ?>
            <p class="page-subtitle"><?= e($customer['company_name']) ?></p>
        <?php endif; ?>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <?php if (Permissions::has('invoices.create')): ?>
        <a href="<?= url('invoice-create.php?customer_id=' . $customer['id']) ?>" class="btn btn-primary">
            <i class="ti ti-receipt mr-1"></i> Create Invoice
        </a>
        <?php endif; ?>
        <?php if (Permissions::has('quotations.create')): ?>
        <a href="<?= url('quotation-create.php?customer_id=' . $customer['id']) ?>" class="btn btn-secondary">
            <i class="ti ti-file-text mr-1"></i> Create Quotation
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Ledger Financial Summary Cards -->
<div class="grid grid-cols-3 gap-4 mb-4">
    <div class="kpi-card card-glow-primary">
        <div class="kpi-card-header">
            <span class="kpi-title">Total Invoiced</span>
            <i class="ti ti-receipt text-primary"></i>
        </div>
        <div class="kpi-value"><?= formatCurrency($customer['total_billed']) ?></div>
        <div class="kpi-footer text-xs text-muted"><?= $customer['invoice_count'] ?> total invoice(s)</div>
    </div>

    <div class="kpi-card card-glow-success">
        <div class="kpi-card-header">
            <span class="kpi-title">Total Collected</span>
            <i class="ti ti-circle-check text-success"></i>
        </div>
        <div class="kpi-value text-success"><?= formatCurrency($customer['total_paid']) ?></div>
        <div class="kpi-footer text-xs text-muted">Cleared payments</div>
    </div>

    <div class="kpi-card card-glow-warning">
        <div class="kpi-card-header">
            <span class="kpi-title">Outstanding Ledger Balance</span>
            <i class="ti ti-alert-triangle text-warning"></i>
        </div>
        <div class="kpi-value <?= $customer['total_outstanding'] > 0 ? 'text-danger' : 'text-success' ?>">
            <?= formatCurrency($customer['total_outstanding']) ?>
        </div>
        <div class="kpi-footer text-xs">
            <?php if ($customer['total_outstanding'] > 0): ?>
                <span class="text-danger font-medium">Payment due</span>
            <?php else: ?>
                <span class="text-success font-medium">Account fully cleared</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-3 gap-4">
    <!-- Left Column: Details & Edit Form (col-span-1) -->
    <div class="col-span-1">
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-address-book mr-1 text-primary"></i> Customer Details</h3>
            </div>
            <div class="card-body">
                <div class="info-list">
                    <div class="info-item mb-2">
                        <span class="info-label text-muted text-xs">Email</span>
                        <div class="info-val font-medium"><?= e($customer['email'] ?: '—') ?></div>
                    </div>
                    <div class="info-item mb-2">
                        <span class="info-label text-muted text-xs">Phone</span>
                        <div class="info-val font-medium"><?= e($customer['phone'] ?: '—') ?></div>
                    </div>
                    <div class="info-item mb-2">
                        <span class="info-label text-muted text-xs">GSTIN</span>
                        <div class="info-val"><span class="badge badge-subtle"><?= e($customer['gstin'] ?: 'Not registered') ?></span></div>
                    </div>
                    <div class="info-item mb-2">
                        <span class="info-label text-muted text-xs">PAN</span>
                        <div class="info-val"><span class="badge badge-subtle"><?= e($customer['pan'] ?: '—') ?></span></div>
                    </div>
                    <div class="info-item mb-2">
                        <span class="info-label text-muted text-xs">Billing Address</span>
                        <div class="info-val text-sm">
                            <?= e($customer['billing_address_line1']) ?><br>
                            <?= e($customer['billing_city']) ?>, <?= e($customer['billing_state']) ?> <?= e($customer['billing_postal_code']) ?>
                        </div>
                    </div>
                    <?php if (!empty($customer['notes'])): ?>
                    <div class="info-item mt-3 p-2 bg-subtle rounded text-xs text-muted">
                        <strong>Notes:</strong> <?= nl2br(e($customer['notes'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Tabs for Invoices, Quotations & Payments (col-span-2) -->
    <div class="col-span-2">
        <div class="card">
            <div class="card-header d-flex justify-between align-center">
                <h3 class="card-title"><i class="ti ti-receipt mr-1 text-primary"></i> Invoices & Billing History</h3>
            </div>
            <div class="card-body p-0">
                <?php if (empty($customer['invoices'])): ?>
                    <div class="empty-state p-4 text-center">
                        <p class="text-muted text-sm">No invoices recorded for this customer.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Date</th>
                                    <th>Due Date</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-right">Paid</th>
                                    <th class="text-right">Outstanding</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customer['invoices'] as $inv): ?>
                                <tr>
                                    <td>
                                        <a href="<?= url('invoice-view.php?id=' . $inv['id']) ?>" class="font-bold text-primary">
                                            <?= e($inv['invoice_number']) ?>
                                        </a>
                                    </td>
                                    <td><?= formatDate($inv['invoice_date']) ?></td>
                                    <td><?= formatDate($inv['due_date']) ?></td>
                                    <td class="text-right font-medium"><?= formatCurrency($inv['grand_total']) ?></td>
                                    <td class="text-right text-success"><?= formatCurrency($inv['amount_paid']) ?></td>
                                    <td class="text-right font-bold <?= $inv['outstanding'] > 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= formatCurrency($inv['outstanding']) ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'overdue' ? 'danger' : 'warning') ?>">
                                            <?= ucfirst($inv['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require YBT_ROOT . '/includes/footer.php'; ?>
