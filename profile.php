<?php
/**
 * YBT Management Software — User Profile & Security Settings
 */

require_once __DIR__ . '/includes/bootstrap.php';

Auth::requireLogin();

$userId = Auth::id();
$user = Database::fetch("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?", [$userId]);

// Handle Profile Details Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!Csrf::validate($_POST['_csrf_token'] ?? '')) {
        Flash::error('Invalid security token.');
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name)) {
            Flash::error('Name cannot be empty.');
        } else {
            Database::update('users', ['name' => $name, 'phone' => $phone], 'id = ?', [$userId]);
            $_SESSION['user_name'] = $name;
            Flash::success('Profile updated successfully.');
            header('Location: ' . url('profile.php'));
            exit;
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!Csrf::validate($_POST['_csrf_token'] ?? '')) {
        Flash::error('Invalid security token.');
    } else {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password'])) {
            Flash::error('Your current password is incorrect.');
        } elseif (strlen($new) < 6) {
            Flash::error('New password must be at least 6 characters long.');
        } elseif ($new !== $confirm) {
            Flash::error('New passwords do not match.');
        } else {
            Database::update('users', ['password' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [$userId]);
            Flash::success('Password changed successfully.');
            header('Location: ' . url('profile.php'));
            exit;
        }
    }
}

$pageTitle = 'My Profile';
require YBT_ROOT . '/includes/header.php';
?>

<div class="page-header mb-4">
    <h1 class="page-title">User Account & Profile</h1>
    <p class="page-subtitle">Personal credentials, security settings, and interface preferences</p>
</div>

<div class="grid grid-cols-3 gap-4">
    <!-- Left Column: User Card (col-span-1) -->
    <div class="col-span-1">
        <div class="card text-center p-4">
            <div class="avatar avatar-xl avatar-circle bg-primary text-white mx-auto mb-3" style="width: 80px; height: 80px; font-size: 32px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <h3 class="font-bold text-lg"><?= e($user['name']) ?></h3>
            <p class="text-xs text-muted mb-2"><?= e($user['email']) ?></p>
            <div>
                <span class="badge badge-<?= $user['is_owner'] ? 'primary' : 'secondary' ?>">
                    <?= e($user['role_name']) ?>
                </span>
            </div>
            <div class="border-top mt-3 pt-3 text-xs text-muted text-left">
                <div class="mb-1"><strong>Business:</strong> <?= e(Auth::businessName()) ?></div>
                <div class="mb-1"><strong>Account Created:</strong> <?= formatDate($user['created_at']) ?></div>
                <div><strong>Last Login:</strong> <?= !empty($user['last_login_at']) ? timeAgo($user['last_login_at']) : 'Current Session' ?></div>
            </div>
        </div>
    </div>

    <!-- Right Column: Settings Forms (col-span-2) -->
    <div class="col-span-2 d-flex flex-column gap-4">
        <!-- Edit Personal Info -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-user mr-1 text-primary"></i> Personal Information</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= url('profile.php') ?>">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div class="form-group">
                            <label class="form-label required">Full Name</label>
                            <input type="text" name="name" class="form-input" value="<?= e($user['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-input" value="<?= e($user['phone'] ?? '') ?>" placeholder="+91 98765 43210">
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Email Address (Login Identity)</label>
                        <input type="email" class="form-input" value="<?= e($user['email']) ?>" disabled>
                        <span class="text-xs text-muted">Email address can only be changed by system administration.</span>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy mr-1"></i> Update Details
                    </button>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-lock mr-1 text-warning"></i> Change Password</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= url('profile.php') ?>">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group mb-3">
                        <label class="form-label required">Current Password</label>
                        <input type="password" name="current_password" class="form-input" required>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div class="form-group">
                            <label class="form-label required">New Password</label>
                            <input type="password" name="new_password" class="form-input" placeholder="Min. 6 characters" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-input" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-secondary">
                        <i class="ti ti-key mr-1"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require YBT_ROOT . '/includes/footer.php'; ?>
