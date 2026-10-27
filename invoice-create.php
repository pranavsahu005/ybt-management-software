<?php
/**
 * YBT Management Software — Generate Tax Invoice
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/services/invoice-service.php';

Auth::requireLogin();
Permissions::require('invoices.create');

$businessId = Auth::businessId();
$userId = Auth::id();

$customers = Database::fetchAll("SELECT id, name, company_name, billing_city, billing_state FROM customers WHERE business_id = ? AND status = 'active' ORDER BY name ASC", [$businessId]);
$products = Database::fetchAll("SELECT * FROM products WHERE business_id = ? AND status = 'active' ORDER BY name ASC", [$businessId]);
$business = Database::fetch("SELECT * FROM businesses WHERE id = ?", [$businessId]);

// Generate invoice number
$nextInvoiceNumber = InvoiceService::generateInvoiceNumber($businessId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf_token'] ?? '')) {
        Flash::error('Invalid security token.');
    } else {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $itemsData = $_POST['items'] ?? [];

        if ($customerId <= 0) {
            Flash::error('Please select a customer for this invoice.');
        } elseif (empty($itemsData)) {
            Flash::error('Invoice must contain at least one line item.');
        } else {
            try {
                $invId = InvoiceService::create($businessId, [
                    'customer_id' => $customerId,
                    'invoice_number' => trim($_POST['invoice_number'] ?? $nextInvoiceNumber),
                    'invoice_date' => $_POST['invoice_date'] ?? date('Y-m-d'),
                    'due_date' => $_POST['due_date'] ?? date('Y-m-d', strtotime('+15 days')),
                    'discount_total' => (float)($_POST['discount_total'] ?? 0),
                    'notes' => trim($_POST['notes'] ?? ''),
                    'terms' => trim($_POST['terms'] ?? $business['default_terms'] ?? ''),
                    'status' => 'sent'
                ], $itemsData, $userId);

                Flash::success("Invoice created successfully.");
                header('Location: ' . url('invoice-view.php?id=' . $invId));
                exit;
            } catch (Exception $e) {
                Flash::error('Error creating invoice: ' . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Create Invoice';
$pageScripts = ['forms.js'];
require YBT_ROOT . '/includes/header.php';
?>

<div class="page-header d-flex justify-between align-center mb-4">
    <div>
        <div class="d-flex align-center gap-2 mb-1">
            <a href="<?= url('invoices.php') ?>" class="text-muted"><i class="ti ti-arrow-left"></i> Invoices</a>
            <span class="text-muted">/</span>
            <span>New Bill</span>
        </div>
        <h1 class="page-title">Generate Tax Invoice</h1>
    </div>
</div>

<form method="POST" action="<?= url('invoice-create.php') ?>" id="invoice-builder-form">
    <?= Csrf::field() ?>

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title"><i class="ti ti-receipt mr-1 text-primary"></i> Invoice Particulars</h3>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-4 gap-3 mb-3">
                <div class="form-group col-span-2">
                    <label class="form-label required">Customer / Client</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">— Select Customer —</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ((int)($_GET['customer_id'] ?? 0) === $c['id']) ? 'selected' : '' ?>>
                                <?= e($c['name']) ?> <?= !empty($c['company_name']) ? '— ' . e($c['company_name']) : '' ?> (<?= e($c['billing_city'] ?: 'India') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label required">Invoice Number</label>
                    <input type="text" name="invoice_number" class="form-input" value="<?= e($nextInvoiceNumber) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label required">Invoice Date</label>
                    <input type="date" name="invoice_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="grid grid-cols-4 gap-3">
                <div class="form-group">
                    <label class="form-label required">Due Date</label>
                    <input type="date" name="due_date" class="form-input" value="<?= date('Y-m-d', strtotime('+15 days')) ?>" required>
                </div>

                <div class="form-group col-span-3">
                    <label class="form-label">Payment Instructions / Reference</label>
                    <input type="text" name="notes" class="form-input" placeholder="e.g. Please transfer to HDFC A/C or scan UPI QR code" value="<?= e($business['default_notes'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Line Items Table -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-between align-center">
            <h3 class="card-title"><i class="ti ti-list mr-1 text-primary"></i> Line Items</h3>
            <div class="d-flex gap-2">
                <select class="form-select form-select-sm item-select-product" style="width: 250px;">
                    <option value="">+ Insert from Catalog...</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" data-name="<?= e($p['name']) ?>" data-price="<?= $p['selling_price'] ?>" data-tax="<?= $p['tax_rate'] ?>" data-description="<?= e($p['description']) ?>">
                            <?= e($p['name']) ?> (₹<?= number_format($p['selling_price'], 2) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-secondary btn-sm" id="add-item-btn">
                    <i class="ti ti-plus mr-1"></i> Add Row
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="data-table" id="line-items-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">Item / Service Description</th>
                        <th style="width: 12%;">HSN/SAC</th>
                        <th style="width: 10%;">Qty</th>
                        <th style="width: 14%;">Rate (₹)</th>
                        <th style="width: 10%;">GST (%)</th>
                        <th style="width: 14%;" class="text-right">Line Total (₹)</th>
                        <th style="width: 50px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="line-item-row">
                        <td>
                            <input type="text" name="items[0][name]" class="form-input item-name" placeholder="Item / service title" required>
                            <textarea name="items[0][description]" class="form-input form-textarea-sm item-description mt-1" rows="1" placeholder="Optional details..."></textarea>
                        </td>
                        <td><input type="text" name="items[0][hsn_sac]" class="form-input form-input-sm" placeholder="998314"></td>
                        <td><input type="number" name="items[0][quantity]" class="form-input item-qty" value="1" min="0.01" step="any" required></td>
                        <td><input type="number" name="items[0][unit_price]" class="form-input item-price" value="0.00" min="0" step="0.01" required></td>
                        <td><input type="number" name="items[0][tax_rate]" class="form-input item-tax-rate" value="18" min="0" step="0.5"></td>
                        <td class="text-right"><strong class="item-total-display">0.00</strong></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-icon btn-ghost text-danger remove-row-btn" title="Remove row">
                                <i class="ti ti-trash"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-between p-4">
            <div class="terms-area" style="width: 45%;">
                <label class="form-label text-xs">Terms & Conditions</label>
                <textarea name="terms" class="form-input text-xs" rows="3"><?= e($business['default_terms'] ?? '') ?></textarea>
            </div>

            <div style="width: 320px;">
                <div class="d-flex justify-between mb-2">
                    <span class="text-muted">Subtotal:</span>
                    <strong id="display-subtotal">₹0.00</strong>
                </div>
                <div class="d-flex justify-between mb-2 align-center">
                    <span class="text-muted">Special Discount:</span>
                    <div style="width: 120px;">
                        <input type="number" name="discount_total" id="invoice-overall-discount" class="form-input form-input-sm text-right" value="0.00" min="0" step="0.01">
                    </div>
                </div>
                <div class="d-flex justify-between mb-2">
                    <span class="text-muted">Tax (GST):</span>
                    <strong id="display-tax">₹0.00</strong>
                </div>
                <div class="d-flex justify-between pt-2 border-top">
                    <span class="text-lg font-bold">Grand Total:</span>
                    <span class="text-lg font-bold text-primary" id="display-grand-total">₹0.00</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit bar -->
    <div class="d-flex justify-end gap-2 mb-5">
        <a href="<?= url('invoices.php') ?>" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="ti ti-check mr-1"></i> Issue Invoice
        </button>
    </div>
</form>

<?php require YBT_ROOT . '/includes/footer.php'; ?>
