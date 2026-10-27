<?php
/**
 * YBT Management Software — Leads & Deals Sales Pipeline
 */

require_once __DIR__ . '/includes/bootstrap.php';

Auth::requireLogin();
Permissions::require('leads.view');

$businessId = Auth::businessId();
$userId = Auth::id();

// Handle Stage Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_stage') {
    Permissions::require('leads.change_stage');
    if (Csrf::validate($_POST['_csrf_token'] ?? '')) {
        $leadId = (int)$_POST['lead_id'];
        $newStage = $_POST['stage'] ?? 'new';
        Database::update('leads', ['stage' => $newStage], 'id = ? AND business_id = ?', [$leadId, $businessId]);
        ActivityLog::log($businessId, $userId, 'lead_stage_updated', 'lead', $leadId, "Updated lead #{$leadId} stage to {$newStage}");
        Flash::success('Lead stage updated successfully.');
        header('Location: ' . url('leads.php'));
        exit;
    }
}

// Handle Add Lead
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_lead') {
    Permissions::require('leads.create');
    if (!Csrf::validate($_POST['_csrf_token'] ?? '')) {
        Flash::error('Invalid security token.');
    } else {
        $title = trim($_POST['title'] ?? '');
        $contact = trim($_POST['contact_name'] ?? '');
        $val = max(0, (float)($_POST['deal_value'] ?? 0));

        if (empty($title)) {
            Flash::error('Deal / Lead title is required.');
        } else {
            $newId = Database::insert('leads', [
                'business_id' => $businessId,
                'title' => $title,
                'contact_name' => $contact,
                'company_name' => trim($_POST['company_name'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'deal_value' => $val,
                'stage' => $_POST['stage'] ?? 'new',
                'source' => trim($_POST['source'] ?? 'Website'),
                'notes' => trim($_POST['notes'] ?? ''),
                'created_by' => $userId
            ]);
            ActivityLog::log($businessId, $userId, 'lead_created', 'lead', $newId, "Created deal '{$title}' valued at ₹" . number_format($val, 2));
            Flash::success("Lead '{$title}' created.");
            header('Location: ' . url('leads.php'));
            exit;
        }
    }
}

// Convert Lead to Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'convert_lead') {
    Permissions::require('customers.create');
    if (Csrf::validate($_POST['_csrf_token'] ?? '')) {
        $leadId = (int)$_POST['lead_id'];
        $lead = Database::fetch("SELECT * FROM leads WHERE id = ? AND business_id = ?", [$leadId, $businessId]);
        if ($lead) {
            $custId = Database::insert('customers', [
                'business_id' => $businessId,
                'name' => $lead['contact_name'] ?: $lead['title'],
                'company_name' => $lead['company_name'],
                'email' => $lead['email'],
                'phone' => $lead['phone'],
                'status' => 'active',
                'created_by' => $userId
            ]);
            Database::update('leads', [
                'stage' => 'won',
                'converted_customer_id' => $custId
            ], 'id = ?', [$leadId]);

            ActivityLog::log($businessId, $userId, 'lead_converted', 'lead', $leadId, "Converted lead #{$leadId} to customer #{$custId}");
            Flash::success("Lead converted to customer successfully!");
            header('Location: ' . url('customer-view.php?id=' . $custId));
            exit;
        }
    }
}

// Fetch Leads
$leads = Database::fetchAll("SELECT * FROM leads WHERE business_id = ? ORDER BY deal_value DESC", [$businessId]);

$stages = [
    'new' => ['name' => 'New Inquiries', 'color' => 'info'],
    'qualified' => ['name' => 'Discovery / Qualified', 'color' => 'secondary'],
    'proposal_sent' => ['name' => 'Proposal Sent', 'color' => 'primary'],
    'negotiation' => ['name' => 'Negotiation', 'color' => 'warning'],
    'won' => ['name' => 'Closed Won', 'color' => 'success'],
    'lost' => ['name' => 'Closed Lost', 'color' => 'danger']
];

$pipelineGroups = [];
foreach ($stages as $key => $meta) {
    $pipelineGroups[$key] = [
        'meta' => $meta,
        'leads' => [],
        'total_value' => 0.0
    ];
}

foreach ($leads as $lead) {
    $st = $lead['stage'] ?? 'new';
    if (!isset($pipelineGroups[$st])) $st = 'new';
    $pipelineGroups[$st]['leads'][] = $lead;
    $pipelineGroups[$st]['total_value'] += (float)$lead['deal_value'];
}

$pageTitle = 'Leads & Sales Pipeline';
require YBT_ROOT . '/includes/header.php';
?>

<div class="page-header d-flex justify-between align-center mb-4">
    <div>
        <h1 class="page-title">Leads & Deals Pipeline</h1>
        <p class="page-subtitle">Track sales progress from initial discovery to closed business</p>
    </div>
    <div class="page-header-actions">
        <?php if (Permissions::has('leads.create')): ?>
        <button type="button" class="btn btn-primary" data-modal-target="new-lead-modal">
            <i class="ti ti-plus mr-1"></i> Add Deal
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Kanban Board Layout -->
<div class="pipeline-kanban-board">
    <?php foreach ($pipelineGroups as $stageKey => $group): ?>
    <div class="kanban-column">
        <div class="kanban-column-header">
            <div class="d-flex justify-between align-center">
                <span class="column-title font-bold text-sm"><?= e($group['meta']['name']) ?></span>
                <span class="badge badge-<?= $group['meta']['color'] ?>"><?= count($group['leads']) ?></span>
            </div>
            <div class="column-value text-xs text-muted mt-1">
                <?= formatCurrency($group['total_value']) ?>
            </div>
        </div>

        <div class="kanban-cards-list">
            <?php if (empty($group['leads'])): ?>
                <div class="kanban-empty p-3 text-center text-xs text-muted">No deals in this stage</div>
            <?php else: ?>
                <?php foreach ($group['leads'] as $l): ?>
                <div class="kanban-card card mb-2 p-3">
                    <div class="d-flex justify-between align-start mb-1">
                        <strong class="card-title text-sm"><?= e($l['title']) ?></strong>
                        <span class="badge badge-subtle text-xs"><?= e($l['source'] ?: 'Direct') ?></span>
                    </div>
                    <?php if (!empty($l['company_name'])): ?>
                        <div class="text-xs text-muted mb-1"><i class="ti ti-building mr-1"></i><?= e($l['company_name']) ?></div>
                    <?php endif; ?>
                    <div class="text-xs text-muted mb-2"><i class="ti ti-user mr-1"></i><?= e($l['contact_name']) ?></div>
                    <div class="d-flex justify-between align-center mt-2 pt-2 border-top">
                        <strong class="text-primary font-medium"><?= formatCurrency($l['deal_value']) ?></strong>
                        
                        <!-- Actions Dropdown -->
                        <div class="dropdown">
                            <button type="button" class="btn btn-icon btn-ghost btn-xs" data-toggle="dropdown">
                                <i class="ti ti-dots-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <div class="dropdown-header">Move Stage</div>
                                <?php foreach ($stages as $sK => $sM): ?>
                                    <?php if ($sK !== $stageKey): ?>
                                    <form method="POST" action="<?= url('leads.php') ?>">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="change_stage">
                                        <input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
                                        <input type="hidden" name="stage" value="<?= $sK ?>">
                                        <button type="submit" class="dropdown-item">Move to <?= e($sM['name']) ?></button>
                                    </form>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if ($stageKey !== 'won' && empty($l['converted_customer_id'])): ?>
                                    <div class="dropdown-divider"></div>
                                    <form method="POST" action="<?= url('leads.php') ?>">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="convert_lead">
                                        <input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
                                        <button type="submit" class="dropdown-item text-success font-medium">
                                            <i class="ti ti-user-check mr-1"></i> Convert to Customer
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Add Deal Modal -->
<div class="modal-backdrop" id="new-lead-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title"><i class="ti ti-star mr-1 text-primary"></i> Create Deal / Lead</h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="<?= url('leads.php') ?>" method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="create_lead">

            <div class="modal-body">
                <div class="form-group mb-3">
                    <label class="form-label required">Deal / Project Title</label>
                    <input type="text" name="title" class="form-input" placeholder="e.g. Website Redesign & SEO" required>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_name" class="form-input" placeholder="e.g. Sunita Sharma">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-input" placeholder="e.g. Blue Horizon Media">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label">Estimated Deal Value (₹)</label>
                        <input type="number" name="deal_value" class="form-input" placeholder="50000" min="0" step="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Initial Stage</label>
                        <select name="stage" class="form-select">
                            <?php foreach ($stages as $k => $m): ?>
                                <option value="<?= $k ?>"><?= e($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" placeholder="client@example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-input" placeholder="+91 98765 43210">
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Lead Source</label>
                    <input type="text" name="source" class="form-input" placeholder="Website, Referral, LinkedIn, Cold Outreach...">
                </div>
                <div class="form-group">
                    <label class="form-label">Notes & Requirements</label>
                    <textarea name="notes" class="form-input" rows="2" placeholder="Key scope, requirements, follow-up timeline..."></textarea>
                </div>
            </div>

            <div class="modal-footer d-flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="ti ti-check mr-1"></i> Save Deal</button>
            </div>
        </form>
    </div>
</div>

<?php require YBT_ROOT . '/includes/footer.php'; ?>
