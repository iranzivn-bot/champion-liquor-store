<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();
requirePermission('users.edit');

$pageTitle = 'Edit User';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    setFlashMessage('error', 'Invalid user ID.');
    redirect(SITE_URL . 'admin/users/index.php');
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    setFlashMessage('error', 'User not found.');
    redirect(SITE_URL . 'admin/users/index.php');
}

// Cannot edit another super_admin account unless current user is super_admin
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$isTargetSuperAdmin = $user['role'] === SUPER_ADMIN_ROLE;
$isCurrentSuperAdmin = isSuperAdmin();
$isOwnAccount = $currentUserId === $id;

if ($isTargetSuperAdmin && !$isCurrentSuperAdmin) {
    setFlashMessage('error', 'You cannot edit another Super Admin account.');
    redirect(SITE_URL . 'admin/users/index.php');
}

$fullName = $user['full_name'];
$email = $user['email'];
$phone = $user['phone'];
$role = $user['role'];
$status = $user['status'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh the page.';
    }

    if (count($errors) === 0) {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $role = $_POST['role'] ?? $user['role'];
        $status = $_POST['status'] ?? $user['status'];

        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }
        if ($email === '') {
            $errors[] = 'Email is required.';
        }
        if ($phone === '') {
            $errors[] = 'Phone number is required.';
        }

        // Role validation based on permissions
        if ($isCurrentSuperAdmin) {
            if (!in_array($role, [SUPER_ADMIN_ROLE, ADMIN_ROLE, CUSTOMER_ROLE], true)) {
                $role = $user['role'];
            }
            // Cannot promote self to super_admin (if not already)
            if ($isOwnAccount && $role === SUPER_ADMIN_ROLE && $user['role'] !== SUPER_ADMIN_ROLE) {
                $errors[] = 'You cannot promote your own account to Super Admin.';
                $role = $user['role'];
            }
        } else {
            // Admin can only select admin or customer
            if (!in_array($role, [ADMIN_ROLE, CUSTOMER_ROLE], true)) {
                $role = $user['role'];
            }
        }

        if (!in_array($status, [ACTIVE_STATUS, INACTIVE_STATUS], true)) {
            $status = $user['status'];
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if ($newPassword !== '') {
            $pwErr = validatePasswordStrength($newPassword);
            if ($pwErr !== null) {
                $errors[] = $pwErr;
            }
        }

        if (count($errors) === 0) {
            if (strtolower($email) !== strtolower($user['email'])) {
                $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id != :id');
                $check->execute([':email' => $email, ':id' => $id]);
                if ($check->fetchColumn() > 0) {
                    $errors[] = 'Another user with this email already exists.';
                }
            }
        }

        if (count($errors) === 0) {
            if ($newPassword !== '') {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('
                    UPDATE users
                       SET full_name = :full_name, email = :email, phone = :phone,
                           password = :password, role = :role, status = :status
                     WHERE id = :id
                ');
                $stmt->execute([
                    ':full_name' => $fullName,
                    ':email'     => $email,
                    ':phone'     => $phone,
                    ':password'  => $hashedPassword,
                    ':role'      => $role,
                    ':status'    => $status,
                    ':id'        => $id,
                ]);
            } else {
                $stmt = $pdo->prepare('
                    UPDATE users
                       SET full_name = :full_name, email = :email, phone = :phone,
                           role = :role, status = :status
                     WHERE id = :id
                ');
                $stmt->execute([
                    ':full_name' => $fullName,
                    ':email'     => $email,
                    ':phone'     => $phone,
                    ':role'      => $role,
                    ':status'    => $status,
                    ':id'        => $id,
                ]);
            }

            logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'users', 'updated', (string) $id, 'Updated user: ' . $fullName . ' (' . $email . ')');

            setFlashMessage('success', 'User "' . htmlspecialchars($fullName) . '" updated successfully.');
            redirect(SITE_URL . 'admin/users/index.php');
        }
    }
}

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="admin-card">
            <h1>Edit User</h1>
            <p class="text-muted">Update user details and account settings.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?= csrfField() ?>

                <div class="mb-3">
                    <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="full_name" name="full_name"
                           value="<?= htmlspecialchars($fullName) ?>" required maxlength="100"
                           autocomplete="off">
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= htmlspecialchars($email) ?>" required maxlength="255"
                           autocomplete="off">
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="phone" name="phone"
                           value="<?= htmlspecialchars($phone) ?>" required maxlength="20"
                           placeholder="+250 7XX XXX XXX">
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password"
                           minlength="8" placeholder="Leave blank to keep current password">
                    <div class="form-text">Leave blank to keep the current password. Otherwise, min. 8 characters with uppercase, lowercase, digit, and special character.</div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role">
                            <option value="<?= CUSTOMER_ROLE ?>" <?= $role === CUSTOMER_ROLE ? 'selected' : '' ?>>Customer</option>
                            <option value="<?= ADMIN_ROLE ?>" <?= $role === ADMIN_ROLE ? 'selected' : '' ?>>Admin</option>
                            <?php if ($isCurrentSuperAdmin): ?>
                                <option value="<?= SUPER_ADMIN_ROLE ?>" <?= $role === SUPER_ADMIN_ROLE ? 'selected' : '' ?><?= $isOwnAccount && $user['role'] !== SUPER_ADMIN_ROLE ? ' disabled' : '' ?>>
                                    Super Admin<?= $isOwnAccount && $user['role'] !== SUPER_ADMIN_ROLE ? ' (disabled - cannot self-promote)' : '' ?>
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="<?= ACTIVE_STATUS ?>" <?= $status === ACTIVE_STATUS ? 'selected' : '' ?>>Active</option>
                            <option value="<?= INACTIVE_STATUS ?>" <?= $status === INACTIVE_STATUS ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn" style="background: var(--admin-primary); color: #fff;">Update User</button>
                    <a href="<?= SITE_URL ?>admin/users/index.php" class="btn btn-secondary">Cancel</a>
                </div>

            </form>
        </div>

    </main>
</div>

<?php
require_once __DIR__ . '/../../includes/admin-footer.php';
