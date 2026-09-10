<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../helpers/audit-helper.php';

requireAdmin();

$pageTitle = 'Change Password';

$pdo = getDbConnection();
$userId = (int) $_SESSION['user_id'];

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '') {
            $error = 'Please enter your current password.';
        } elseif ($newPassword === '') {
            $error = 'Please enter a new password.';
        } else {
            $pwErr = validatePasswordStrength($newPassword);
            if ($pwErr !== null) {
                $error = $pwErr;
            }
        }

        if (!$error && $newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        }

        if (!$error) {
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id');
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($currentPassword, $user['password'])) {
                $error = 'Current password is incorrect.';
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
                $stmt->execute([':password' => $hashedPassword, ':id' => $userId]);

                logActivity($userId, $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'auth', 'password_changed', $userId, 'Admin password changed for user ID: ' . $userId);

                $success = 'Your password has been changed successfully.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/admin-navbar.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="py-3">
            <h2 class="fw-bold mb-1" style="color: var(--admin-primary);">
                <i class="bi bi-key"></i> Change Password
            </h2>
            <p class="text-muted">Update your admin account password.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <form method="POST" novalidate>
                            <?= csrfField() ?>

                            <div class="mb-3">
                                <label for="current_password" class="form-label fw-semibold">Current Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="current_password"
                                       name="current_password" required
                                       placeholder="Enter your current password" autocomplete="current-password">
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="new_password"
                                       name="new_password" required minlength="8"
                                       placeholder="At least 8 characters" autocomplete="new-password">
                                <div class="form-text">Minimum 8 characters with uppercase, lowercase, digit, and special character.</div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-semibold">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="confirm_password"
                                       name="confirm_password" required
                                       placeholder="Repeat your new password" autocomplete="new-password">
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn px-4 fw-semibold" style="background: var(--admin-primary); color: #fff;">
                                    <i class="bi bi-check-lg"></i> Update Password
                                </button>
                                <a href="<?= SITE_URL ?>admin/dashboard.php" class="btn btn-outline-secondary">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
