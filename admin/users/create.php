<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();
requirePermission('users.create');

$pageTitle = 'Create User';

$isSuperAdmin = isSuperAdmin();

$fullName = '';
$email = '';
$phone = '';
$password = '';
$role = CUSTOMER_ROLE;
$status = ACTIVE_STATUS;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh the page.';
    }

    if (count($errors) === 0) {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? CUSTOMER_ROLE;
        $status = $_POST['status'] ?? ACTIVE_STATUS;

        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }
        if ($email === '') {
            $errors[] = 'Email is required.';
        }
        if ($phone === '') {
            $errors[] = 'Phone number is required.';
        }
        if ($password === '') {
            $errors[] = 'Password is required.';
        }

        // Only super_admin can create admin or super_admin accounts
        if (!$isSuperAdmin) {
            $role = CUSTOMER_ROLE;
        } elseif (!in_array($role, [SUPER_ADMIN_ROLE, ADMIN_ROLE, CUSTOMER_ROLE], true)) {
            $role = CUSTOMER_ROLE;
        }

        if (!in_array($status, [ACTIVE_STATUS, INACTIVE_STATUS], true)) {
            $status = ACTIVE_STATUS;
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if ($password !== '') {
            $pwErr = validatePasswordStrength($password);
            if ($pwErr !== null) {
                $errors[] = $pwErr;
            }
        }

        if (count($errors) === 0) {
            $pdo = getDbConnection();

            $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
            $check->execute([':email' => $email]);
            if ($check->fetchColumn() > 0) {
                $errors[] = 'A user with this email already exists.';
            }
        }

        if (count($errors) === 0) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare('
                INSERT INTO users (full_name, email, phone, password, role, status)
                VALUES (:full_name, :email, :phone, :password, :role, :status)
            ');
            $stmt->execute([
                ':full_name' => $fullName,
                ':email'     => $email,
                ':phone'     => $phone,
                ':password'  => $hashedPassword,
                ':role'      => $role,
                ':status'    => $status,
            ]);

            $userId = (int) $pdo->lastInsertId();

            logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'users', 'created', (string) $userId, 'Created user: ' . $fullName . ' (' . $email . ')');

            setFlashMessage('success', 'User "' . htmlspecialchars($fullName) . '" created successfully.');
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
            <h1>Create User</h1>
            <p class="text-muted">Fill in the fields below to add a new user.</p>

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
                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="password" name="password" required
                           minlength="8" placeholder="Min. 8 characters, upper, lower, digit, special">
                    <div class="form-text">Must be at least 8 characters with uppercase, lowercase, digit, and special character.</div>
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label">Role</label>
                    <?php if ($isSuperAdmin): ?>
                        <select class="form-select" id="role" name="role">
                            <option value="<?= CUSTOMER_ROLE ?>" <?= $role === CUSTOMER_ROLE ? 'selected' : '' ?>>Customer</option>
                            <option value="<?= ADMIN_ROLE ?>" <?= $role === ADMIN_ROLE ? 'selected' : '' ?>>Admin</option>
                            <option value="<?= SUPER_ADMIN_ROLE ?>" <?= $role === SUPER_ADMIN_ROLE ? 'selected' : '' ?>>Super Admin</option>
                        </select>
                    <?php else: ?>
                        <input type="hidden" name="role" value="<?= CUSTOMER_ROLE ?>">
                        <input type="text" class="form-control" value="Customer" disabled>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="<?= ACTIVE_STATUS ?>" <?= $status === ACTIVE_STATUS ? 'selected' : '' ?>>Active</option>
                        <option value="<?= INACTIVE_STATUS ?>" <?= $status === INACTIVE_STATUS ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn" style="background: var(--admin-primary); color: #fff;">Save User</button>
                    <a href="<?= SITE_URL ?>admin/users/index.php" class="btn btn-secondary">Cancel</a>
                </div>

            </form>
        </div>

    </main>
</div>

<?php
require_once __DIR__ . '/../../includes/admin-footer.php';
