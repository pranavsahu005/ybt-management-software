<?php
/**
 * YBT Management Software — AI Assistant Digital Manager
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/services/ai-memory-service.php';
require_once __DIR__ . '/services/ai-provider-service.php';

Auth::requireLogin();
Permissions::require('ai.access');

$businessId = Auth::businessId();
$userId = Auth::id();

$activeProvider = AIProviderService::getActive($businessId);
$convId = AIMemoryService::getOrCreateConversation($businessId, $userId);
$pastMessages = AIMemoryService::getMessages($convId, 25);

// Fetch pending proposals for Owner review
$pendingProposals = [];
if (Auth::isOwner()) {
    $pendingProposals = Database::fetchAll(
        "SELECT * FROM ai_action_proposals WHERE business_id = ? AND status = 'pending' ORDER BY created_at DESC",
        [$businessId]
    );
}

$pageTitle = 'AI Digital Manager';
$pageScripts = ['ai-chat.js'];
require YBT_ROOT . '/includes/header.php';
?>

<div class="page-header d-flex justify-between align-center mb-4">
    <div>
        <h1 class="page-title"><i class="ti ti-sparkles text-primary mr-1"></i> AI Digital Manager</h1>
        <p class="page-subtitle">Permission-controlled business intelligence, record search, and supervised action proposals</p>
    </div>
    <div class="page-header-actions d-flex gap-2 align-center">
        <?php if ($activeProvider && (!empty($activeProvider['api_key']) || $activeProvider['provider_type'] === 'custom')): ?>
            <a href="<?= url('settings.php#section-ai-providers') ?>" class="badge badge-primary text-decoration-none d-inline-flex align-center gap-1" title="Provider configured via Settings. Click to manage.">
                <i class="ti ti-cpu"></i>
                <span><?= e($activeProvider['display_name']) ?> (<?= e($activeProvider['model_id'] ?: 'Default') ?>)</span>
            </a>
        <?php else: ?>
            <a href="<?= url('settings.php#section-ai-providers') ?>" class="badge badge-secondary text-decoration-none d-inline-flex align-center gap-1" title="Running local logic engine. Click to configure Google AI Studio or OpenRouter.">
                <i class="ti ti-robot"></i>
                <span>Built-in Logic Engine</span>
            </a>
        <?php endif; ?>
        <span class="badge badge-success"><i class="ti ti-shield-check mr-1"></i> Zero Direct Mutation Active</span>
    </div>
</div>

<?php if (Auth::isOwner() && !empty($pendingProposals)): ?>
<!-- Owner Pending Approvals Banner -->
<div class="card mb-4 border-warning bg-warning-subtle">
    <div class="card-body p-3">
        <div class="d-flex align-center gap-2 mb-2">
            <i class="ti ti-shield-alert text-warning text-xl"></i>
            <strong>You have <?= count($pendingProposals) ?> pending AI action proposal(s) requiring your sign-off:</strong>
        </div>
        <div class="d-flex flex-column gap-2">
            <?php foreach ($pendingProposals as $p): 
                $pData = json_decode($p['proposal_data'], true) ?: [];
            ?>
            <div class="ai-proposal-card bg-white p-3 rounded d-flex justify-between align-center">
                <div>
                    <strong><?= ucfirst(str_replace('_', ' ', $p['action_type'])) ?>:</strong>
                    <span><?= e($pData['description'] ?? 'Action Item') ?></span>
                    <?php if (!empty($pData['amount'])): ?>
                        <span class="font-bold text-primary ml-1">(<?= formatCurrency($pData['amount']) ?>)</span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-primary" data-proposal-action="approve" data-proposal-id="<?= $p['id'] ?>">
                        <i class="ti ti-check mr-1"></i> Approve
                    </button>
                    <button type="button" class="btn btn-sm btn-ghost text-danger" data-proposal-action="reject" data-proposal-id="<?= $p['id'] ?>">
                        <i class="ti ti-x"></i> Reject
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main AI Chat Container -->
<div class="card ai-chat-card">
    <div class="card-header d-flex justify-between align-center">
        <div class="d-flex align-center gap-2">
            <div class="avatar avatar-sm bg-primary text-white avatar-circle">
                <i class="ti ti-sparkles"></i>
            </div>
            <div>
                <strong>Assistant Session</strong>
                <div class="text-xs text-muted">Connected to Apex Infotech Business Context</div>
            </div>
        </div>
        <span class="text-xs text-muted">Strict Owner Authorization Enforced</span>
    </div>

    <!-- Message History Stream -->
    <div class="card-body ai-chat-body" id="ai-messages-container" style="height: 440px; overflow-y: auto; padding: 20px;">
        <!-- Welcome Bot Message -->
        <div class="ai-message ai-message-assistant">
            <div class="ai-avatar bot-avatar"><i class="ti ti-sparkles"></i></div>
            <div class="ai-message-content">
                <div class="ai-bubble">
                    Hello <strong><?= e(Auth::name()) ?></strong>! I am your AI digital business manager. You can ask me questions about clients, invoices, debt collections, financial performance, or ask me to draft new billing records.
                </div>
            </div>
        </div>

        <?php foreach ($pastMessages as $msg): ?>
        <div class="ai-message ai-message-<?= e($msg['role']) ?>">
            <div class="ai-avatar <?= $msg['role'] === 'user' ? 'user-avatar' : 'bot-avatar' ?>">
                <i class="ti <?= $msg['role'] === 'user' ? 'ti-user' : 'ti-sparkles' ?>"></i>
            </div>
            <div class="ai-message-content">
                <div class="ai-bubble">
                    <?= nl2br(e($msg['content'])) ?>
                </div>
                <div class="ai-message-time"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Suggested Quick Prompts -->
    <div class="ai-prompts-bar p-2 bg-subtle d-flex flex-wrap gap-2 border-top">
        <button type="button" class="btn btn-sm btn-ghost ai-prompt-chip">Who has overdue invoices?</button>
        <button type="button" class="btn btn-sm btn-ghost ai-prompt-chip">Show revenue & profit summary</button>
        <button type="button" class="btn btn-sm btn-ghost ai-prompt-chip">List our top deals in pipeline</button>
        <button type="button" class="btn btn-sm btn-ghost ai-prompt-chip">Find customer Nova Retail</button>
        <button type="button" class="btn btn-sm btn-ghost ai-prompt-chip">Create draft invoice for client</button>
    </div>

    <!-- Chat Input Bar -->
    <div class="card-footer p-3">
        <form id="ai-chat-form" class="d-flex gap-2">
            <input type="text" id="ai-chat-input" class="form-input" placeholder="Ask a question or describe an action to prepare..." autocomplete="off" required>
            <button type="submit" class="btn btn-primary" id="ai-send-btn">
                <i class="ti ti-send mr-1"></i> Send
            </button>
        </form>
    </div>
</div>

<?php require YBT_ROOT . '/includes/footer.php'; ?>
