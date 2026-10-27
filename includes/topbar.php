<?php
/**
 * YBT Management Software — Topbar Header
 * Global search, quick create actions, notifications, theme toggle, and user menu
 */
if (!defined('YBT_ROOT')) exit;

$userName = Auth::name() ?: 'User';
$userEmail = Auth::email() ?: '';
$userInitial = strtoupper(substr($userName, 0, 1));
$isOwner = Auth::isOwner();
?>
<header class="topbar">
    <div class="topbar-left">
        <!-- Mobile Sidebar Toggle -->
        <button type="button" class="btn btn-icon btn-ghost mobile-menu-toggle" data-action="toggle-mobile-sidebar" aria-label="Open Navigation">
            <i class="ti ti-menu-2"></i>
        </button>

        <!-- Global Search -->
        <div class="global-search-container">
            <div class="search-input-wrapper">
                <i class="ti ti-search search-icon"></i>
                <input type="text" id="global-search-input" class="global-search-input" placeholder="Search customers, invoices, leads... (Ctrl+K)" autocomplete="off">
                <kbd class="search-shortcut">⌘K</kbd>
            </div>
            <div id="global-search-results" class="search-dropdown-results" style="display: none;"></div>
        </div>
    </div>

    <div class="topbar-right">
        <!-- Quick Create Dropdown -->
        <div class="dropdown quick-create-dropdown">
            <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-toggle="dropdown">
                <i class="ti ti-plus"></i>
                <span class="d-none-sm">Create</span>
                <i class="ti ti-chevron-down ml-1"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-right">
                <div class="dropdown-header">Quick Actions</div>
                <?php if (Permissions::has('invoices.create')): ?>
                    <a href="<?= url('invoice-create.php') ?>" class="dropdown-item">
                        <i class="ti ti-receipt text-primary"></i> New Invoice
                    </a>
                <?php endif; ?>
                <?php if (Permissions::has('quotations.create')): ?>
                    <a href="<?= url('quotation-create.php') ?>" class="dropdown-item">
                        <i class="ti ti-file-text text-secondary"></i> New Quotation
                    </a>
                <?php endif; ?>
                <?php if (Permissions::has('customers.create')): ?>
                    <a href="<?= url('customers.php?action=new') ?>" class="dropdown-item">
                        <i class="ti ti-user-plus text-success"></i> Add Customer
                    </a>
                <?php endif; ?>
                <?php if (Permissions::has('leads.create')): ?>
                    <a href="<?= url('leads.php?action=new') ?>" class="dropdown-item">
                        <i class="ti ti-star text-warning"></i> Add Lead
                    </a>
                <?php endif; ?>
                <?php if (Permissions::has('payments.record')): ?>
                    <a href="<?= url('payments.php?action=record') ?>" class="dropdown-item">
                        <i class="ti ti-cash text-info"></i> Record Payment
                    </a>
                <?php endif; ?>
                <?php if (Permissions::has('expenses.create')): ?>
                    <a href="<?= url('expenses.php?action=new') ?>" class="dropdown-item">
                        <i class="ti ti-wallet text-danger"></i> Log Expense
                    </a>
                <?php endif; ?>
                <?php if (Permissions::has('tasks.create')): ?>
                    <div class="dropdown-divider"></div>
                    <a href="<?= url('tasks.php?action=new') ?>" class="dropdown-item">
                        <i class="ti ti-checklist text-purple"></i> Add Task
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- AI Assistant Quick Launch -->
        <?php if (Permissions::has('ai.access')): ?>
        <a href="<?= url('ai-assistant.php') ?>" class="btn btn-icon btn-ghost ai-topbar-btn" title="AI Digital Manager">
            <i class="ti ti-sparkles text-primary"></i>
        </a>
        <?php endif; ?>

        <!-- Theme Toggle -->
        <button type="button" class="btn btn-icon btn-ghost theme-toggle-btn" data-action="toggle-theme" title="Switch Theme">
            <i class="ti ti-moon theme-toggle-icon"></i>
        </button>

        <!-- User Profile Dropdown -->
        <div class="dropdown user-menu-dropdown">
            <button type="button" class="user-avatar-btn dropdown-toggle" data-toggle="dropdown" aria-label="User Menu">
                <div class="avatar avatar-sm avatar-circle bg-primary text-white">
                    <?= e($userInitial) ?>
                </div>
            </button>
            <div class="dropdown-menu dropdown-menu-right user-profile-card">
                <div class="user-menu-header">
                    <div class="avatar avatar-md avatar-circle bg-primary text-white">
                        <?= e($userInitial) ?>
                    </div>
                    <div class="user-menu-info">
                        <div class="user-name"><?= e($userName) ?></div>
                        <div class="user-email"><?= e($userEmail) ?></div>
                        <span class="badge <?= $isOwner ? 'badge-primary' : 'badge-secondary' ?> mt-1">
                            <?= $isOwner ? 'Owner' : 'Manager' ?>
                        </span>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="<?= url('profile.php') ?>" class="dropdown-item">
                    <i class="ti ti-user"></i> My Profile
                </a>
                <?php if (Permissions::has('settings.access')): ?>
                <a href="<?= url('settings.php') ?>" class="dropdown-item">
                    <i class="ti ti-settings"></i> Business Settings
                </a>
                <?php endif; ?>
                <div class="dropdown-divider"></div>
                <a href="<?= url('logout.php') ?>" class="dropdown-item text-danger">
                    <i class="ti ti-logout"></i> Sign Out
                </a>
            </div>
        </div>
    </div>
</header>
