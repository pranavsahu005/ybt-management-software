<?php
/**
 * YBT Management Software — Tasks & Reminders
 */

require_once __DIR__ . '/includes/bootstrap.php';

Auth::requireLogin();
Permissions::require('tasks.view');

$businessId = Auth::businessId();
$userId = Auth::id();

// Handle Status Toggle / Completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_task') {
    Permissions::require('tasks.complete');
    if (Csrf::validate($_POST['_csrf_token'] ?? '')) {
        $taskId = (int)$_POST['task_id'];
        $newStatus = $_POST['status'] === 'completed' ? 'completed' : 'pending';
        Database::update('tasks', [
            'status' => $newStatus,
            'completed_at' => ($newStatus === 'completed') ? date('Y-m-d H:i:s') : null
        ], 'id = ? AND business_id = ?', [$taskId, $businessId]);

        ActivityLog::log($businessId, $userId, 'task_updated', 'task', $taskId, "Marked task #{$taskId} as {$newStatus}");
        Flash::success("Task marked as {$newStatus}.");
        header('Location: ' . url('tasks.php'));
        exit;
    }
}

// Handle Create Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_task') {
    Permissions::require('tasks.create');
    if (!Csrf::validate($_POST['_csrf_token'] ?? '')) {
        Flash::error('Invalid security token.');
    } else {
        $title = trim($_POST['title'] ?? '');
        if (empty($title)) {
            Flash::error('Task title is required.');
        } else {
            $newId = Database::insert('tasks', [
                'business_id' => $businessId,
                'title' => $title,
                'description' => trim($_POST['description'] ?? ''),
                'priority' => $_POST['priority'] ?? 'medium',
                'status' => 'pending',
                'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d'),
                'assigned_to' => !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : $userId,
                'created_by' => $userId
            ]);
            ActivityLog::log($businessId, $userId, 'task_created', 'task', $newId, "Added task '{$title}'");
            Flash::success("Task created.");
            header('Location: ' . url('tasks.php'));
            exit;
        }
    }
}

// Fetch users for assignment dropdown
$teamUsers = Database::fetchAll("SELECT id, name FROM users WHERE business_id = ? AND is_active = 1", [$businessId]);

// Filters
$statusFilter = $_GET['status'] ?? 'pending';
$where = ["t.business_id = ?"];
$params = [$businessId];

if ($statusFilter !== 'all') {
    if ($statusFilter === 'pending') {
        $where[] = "t.status != 'completed'";
    } else {
        $where[] = "t.status = ?";
        $params[] = $statusFilter;
    }
}

$whereClause = implode(' AND ', $where);
$tasks = Database::fetchAll(
    "SELECT t.*, u.name as assignee_name 
     FROM tasks t 
     LEFT JOIN users u ON t.assigned_to = u.id 
     WHERE {$whereClause} 
     ORDER BY (t.priority = 'urgent') DESC, (t.priority = 'high') DESC, t.due_date ASC",
    $params
);

$pageTitle = 'Tasks & Reminders';
require YBT_ROOT . '/includes/header.php';
?>

<div class="page-header d-flex justify-between align-center mb-4">
    <div>
        <h1 class="page-title">Tasks & Reminders</h1>
        <p class="page-subtitle">Operational deliverables, deadline tracking, and team assignments</p>
    </div>
    <div class="page-header-actions">
        <?php if (Permissions::has('tasks.create')): ?>
        <button type="button" class="btn btn-primary" data-modal-target="new-task-modal">
            <i class="ti ti-plus mr-1"></i> Add Task
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Status Filter Tabs -->
<div class="tabs-nav mb-4">
    <a href="<?= url('tasks.php?status=pending') ?>" class="tab-link <?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending & In Progress</a>
    <a href="<?= url('tasks.php?status=completed') ?>" class="tab-link <?= $statusFilter === 'completed' ? 'active' : '' ?>">Completed</a>
    <a href="<?= url('tasks.php?status=all') ?>" class="tab-link <?= $statusFilter === 'all' ? 'active' : '' ?>">All Tasks</a>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($tasks)): ?>
            <div class="empty-state p-5 text-center">
                <i class="ti ti-checkbox text-4xl text-muted mb-2"></i>
                <h3>No Tasks Found</h3>
                <p class="text-muted text-sm">No tasks match your current view.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th>
                            <th>Task Details</th>
                            <th>Priority</th>
                            <th>Due Date</th>
                            <th>Assignee</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $t): 
                            $isOverdue = ($t['status'] !== 'completed' && strtotime($t['due_date']) < strtotime(date('Y-m-d')));
                            $isToday = ($t['status'] !== 'completed' && $t['due_date'] === date('Y-m-d'));
                        ?>
                        <tr>
                            <td class="text-center">
                                <form method="POST" action="<?= url('tasks.php') ?>">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="action" value="toggle_task">
                                    <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $t['status'] === 'completed' ? 'pending' : 'completed' ?>">
                                    <button type="submit" class="btn btn-icon btn-ghost btn-sm" title="<?= $t['status'] === 'completed' ? 'Mark Incomplete' : 'Mark Completed' ?>">
                                        <i class="ti <?= $t['status'] === 'completed' ? 'ti-circle-check-filled text-success' : 'ti-circle text-muted' ?> text-lg"></i>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <strong class="<?= $t['status'] === 'completed' ? 'line-through text-muted' : '' ?>">
                                    <?= e($t['title']) ?>
                                </strong>
                                <?php if (!empty($t['description'])): ?>
                                    <div class="text-xs text-muted"><?= e($t['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $t['priority'] === 'urgent' ? 'danger' : ($t['priority'] === 'high' ? 'warning' : ($t['priority'] === 'medium' ? 'primary' : 'secondary')) ?>">
                                    <?= ucfirst($t['priority']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isOverdue): ?>
                                    <span class="text-danger font-bold"><i class="ti ti-alert-triangle mr-1"></i><?= formatDate($t['due_date']) ?> (Overdue)</span>
                                <?php elseif ($isToday): ?>
                                    <span class="text-warning font-bold"><i class="ti ti-clock mr-1"></i>Due Today</span>
                                <?php else: ?>
                                    <span><?= formatDate($t['due_date']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-subtle"><?= e($t['assignee_name'] ?: 'Unassigned') ?></span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $t['status'] === 'completed' ? 'success' : 'subtle' ?>">
                                    <?= ucfirst(str_replace('_', ' ', $t['status'])) ?>
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

<!-- Add Task Modal -->
<div class="modal-backdrop" id="new-task-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title"><i class="ti ti-checklist mr-1 text-primary"></i> Create Task</h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="<?= url('tasks.php') ?>" method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="create_task">

            <div class="modal-body">
                <div class="form-group mb-3">
                    <label class="form-label required">Task Title</label>
                    <input type="text" name="title" class="form-input" placeholder="e.g. Follow up on proposal with client" required>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Assignee</label>
                    <select name="assigned_to" class="form-select">
                        <?php foreach ($teamUsers as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $u['id'] === $userId ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Description / Details</label>
                    <textarea name="description" class="form-input" rows="2" placeholder="Task specifics, requirements, instructions..."></textarea>
                </div>
            </div>

            <div class="modal-footer d-flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="ti ti-check mr-1"></i> Save Task</button>
            </div>
        </form>
    </div>
</div>

<?php require YBT_ROOT . '/includes/footer.php'; ?>
