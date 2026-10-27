<?php
/**
 * YBT Management Software — Products & Services Catalog
 */

require_once __DIR__ . '/includes/bootstrap.php';

Auth::requireLogin();
Permissions::require('products.view');

$businessId = Auth::businessId();
$userId = Auth::id();

// Handle Create Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_product') {
    Permissions::require('products.create');
    if (!Csrf::validate($_POST['_csrf_token'] ?? '')) {
        Flash::error('Invalid security token.');
    } else {
        $name = trim($_POST['name'] ?? '');
        $price = max(0, (float)($_POST['selling_price'] ?? 0));
        $tax = max(0, (float)($_POST['tax_rate'] ?? 18));

        if (empty($name)) {
            Flash::error('Product / Service name is required.');
        } else {
            $newId = Database::insert('products', [
                'business_id' => $businessId,
                'name' => $name,
                'type' => $_POST['type'] ?? 'service',
                'sku' => trim($_POST['sku'] ?? ''),
                'hsn_sac' => trim($_POST['hsn_sac'] ?? ''),
                'unit' => trim($_POST['unit'] ?? 'Nos'),
                'selling_price' => $price,
                'cost_price' => max(0, (float)($_POST['cost_price'] ?? 0)),
                'tax_rate' => $tax,
                'description' => trim($_POST['description'] ?? ''),
                'status' => 'active',
                'created_by' => $userId
            ]);
            ActivityLog::log($businessId, $userId, 'product_created', 'product', $newId, "Added item '{$name}'");
            Flash::success("Item '{$name}' created successfully.");
            header('Location: ' . url('products.php'));
            exit;
        }
    }
}

// Fetch products
$products = Database::fetchAll("SELECT * FROM products WHERE business_id = ? ORDER BY name ASC", [$businessId]);

$pageTitle = 'Products & Services';
require YBT_ROOT . '/includes/header.php';
?>

<div class="page-header d-flex justify-between align-center mb-4">
    <div>
        <h1 class="page-title">Products & Services Catalog</h1>
        <p class="page-subtitle">Standardized inventory, service rates, HSN/SAC codes, and GST tax rules</p>
    </div>
    <div class="page-header-actions">
        <?php if (Permissions::has('products.create')): ?>
        <button type="button" class="btn btn-primary" data-modal-target="new-product-modal">
            <i class="ti ti-plus mr-1"></i> Add Item
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <h3 class="card-title">Catalog Items (<?= count($products) ?>)</h3>
        <div style="width: 250px;">
            <input type="text" class="form-input form-input-sm" placeholder="Filter items..." data-table-filter="#products-table">
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($products)): ?>
            <div class="empty-state p-5 text-center">
                <i class="ti ti-package text-4xl text-muted mb-2"></i>
                <h3>No Items In Catalog</h3>
                <p class="text-muted text-sm mb-3">Add standard products or services to speed up quotation and invoice generation.</p>
                <button type="button" class="btn btn-primary btn-sm" data-modal-target="new-product-modal">
                    <i class="ti ti-plus mr-1"></i> Add First Item
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table" id="products-table">
                    <thead>
                        <tr>
                            <th>Item Name / Description</th>
                            <th>Type</th>
                            <th>SKU</th>
                            <th>HSN/SAC</th>
                            <th class="text-right">Selling Price</th>
                            <th class="text-right">Tax Rate</th>
                            <th>Unit</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <strong><?= e($p['name']) ?></strong>
                                <?php if (!empty($p['description'])): ?>
                                    <div class="text-xs text-muted"><?= e(truncate($p['description'], 60)) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $p['type'] === 'service' ? 'primary' : 'secondary' ?>">
                                    <?= ucfirst($p['type']) ?>
                                </span>
                            </td>
                            <td><span class="badge badge-subtle"><?= e($p['sku'] ?: '—') ?></span></td>
                            <td><?= e($p['hsn_sac'] ?: '—') ?></td>
                            <td class="text-right font-bold text-primary"><?= formatCurrency($p['selling_price']) ?></td>
                            <td class="text-right font-medium"><?= $p['tax_rate'] ?>% GST</td>
                            <td><?= e($p['unit'] ?: 'Nos') ?></td>
                            <td>
                                <span class="badge badge-<?= $p['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($p['status']) ?>
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

<!-- Add Item Modal -->
<div class="modal-backdrop" id="new-product-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title"><i class="ti ti-package mr-1 text-primary"></i> Add Item to Catalog</h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="<?= url('products.php') ?>" method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="create_product">

            <div class="modal-body">
                <div class="form-group mb-3">
                    <label class="form-label required">Product or Service Name</label>
                    <input type="text" name="name" class="form-input" placeholder="e.g. Full-Stack Web Development" required>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="service">Service</option>
                            <option value="product">Physical Product</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unit of Measure</label>
                        <input type="text" name="unit" class="form-input" placeholder="e.g. Project, Month, Hours, Nos" value="Project">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label">SKU / Code</label>
                        <input type="text" name="sku" class="form-input" placeholder="SRV-WEB-01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">HSN / SAC Code</label>
                        <input type="text" name="hsn_sac" class="form-input" placeholder="998314">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label required">Selling Price (₹)</label>
                        <input type="number" name="selling_price" class="form-input" placeholder="25000" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cost Price (₹)</label>
                        <input type="number" name="cost_price" class="form-input" placeholder="10000" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">GST Tax Rate</label>
                        <select name="tax_rate" class="form-select">
                            <option value="18" selected>18% GST (Standard)</option>
                            <option value="12">12% GST</option>
                            <option value="5">5% GST</option>
                            <option value="28">28% GST</option>
                            <option value="0">0% (Exempt)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Default Description</label>
                    <textarea name="description" class="form-input" rows="2" placeholder="Default description auto-filled on invoices..."></textarea>
                </div>
            </div>

            <div class="modal-footer d-flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="ti ti-check mr-1"></i> Add to Catalog</button>
            </div>
        </form>
    </div>
</div>

<?php require YBT_ROOT . '/includes/footer.php'; ?>
