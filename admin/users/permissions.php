<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();
requirePermission('permissions.manage');

$pageTitle = 'Manage Permissions';

$pdo = getDbConnection();

// Ensure user_permissions table exists for per-user permission overrides
$pdo->exec("
    CREATE TABLE IF NOT EXISTS user_permissions (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id       INT UNSIGNED NOT NULL,
        permission_id INT UNSIGNED NOT NULL,
        granted       TINYINT(1) NOT NULL DEFAULT 1,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_user_perm (user_id, permission_id),
        CONSTRAINT fk_up_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_up_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$successMsg = getFlashMessage('success');
$errorMsg   = getFlashMessage('error');

// Fetch all admin/super_admin users
$stmt = $pdo->prepare("SELECT id, full_name, email, role, status FROM users WHERE role IN ('super_admin', 'admin') ORDER BY role, full_name");
$stmt->execute();
$adminUsers = $stmt->fetchAll();

// Selected user
$selectedUserId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$selectedUser   = null;
if ($selectedUserId > 0) {
    foreach ($adminUsers as $u) {
        if ((int) $u['id'] === $selectedUserId) {
            $selectedUser = $u;
            break;
        }
    }
}

// Fetch all permissions grouped by module
$stmt = $pdo->query("SELECT id, name, description, module FROM permissions ORDER BY module, name");
$allPermissions = $stmt->fetchAll();

$groupedPermissions = [];
foreach ($allPermissions as $perm) {
    $groupedPermissions[$perm['module']][] = $perm;
}

// Admin role default permission IDs
$stmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role = :role");
$stmt->execute([':role' => ADMIN_ROLE]);
$adminRolePermIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
$adminRolePermIds = array_map('intval', $adminRolePermIds);

// Individual user overrides
$userOverrides = [];
if ($selectedUser) {
    $stmt = $pdo->prepare("SELECT permission_id, granted FROM user_permissions WHERE user_id = :uid");
    $stmt->execute([':uid' => $selectedUserId]);
    while ($row = $stmt->fetch()) {
        $userOverrides[(int) $row['permission_id']] = (bool) $row['granted'];
    }
}

// Effective permission check for a user
function userHasPermission(int $permId, string $userRole, array $adminRolePermIds, array $userOverrides): bool {
    if ($userRole === SUPER_ADMIN_ROLE) {
        return true;
    }
    if (array_key_exists($permId, $userOverrides)) {
        return $userOverrides[$permId];
    }
    return in_array($permId, $adminRolePermIds, true);
}

// Count effective permissions for a user
function countUserPermissions(string $userRole, array $adminRolePermIds, array $allPermissions, array $overrides): int {
    if ($userRole === SUPER_ADMIN_ROLE) {
        return count($allPermissions);
    }
    $count = count($adminRolePermIds);
    foreach ($overrides as $pid => $granted) {
        $inRole = in_array($pid, $adminRolePermIds, true);
        if ($granted && !$inRole) {
            $count++;
        } elseif (!$granted && $inRole) {
            $count--;
        }
    }
    return max(0, $count);
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedUser) {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $errorMsg = 'Invalid security token. Please refresh the page.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_permissions' && $selectedUser['role'] !== SUPER_ADMIN_ROLE) {
            $grantedPerms = $_POST['perms'] ?? [];
            $grantedPermIds = [];
            foreach ($grantedPerms as $pid) {
                $grantedPermIds[] = (int) $pid;
            }

            foreach ($allPermissions as $perm) {
                $permId       = (int) $perm['id'];
                $granted      = in_array($permId, $grantedPermIds, true);
                $roleDefault  = in_array($permId, $adminRolePermIds, true);

                if ($granted === $roleDefault) {
                    $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = :uid AND permission_id = :pid");
                    $stmt->execute([':uid' => $selectedUserId, ':pid' => $permId]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_permissions (user_id, permission_id, granted)
                        VALUES (:uid, :pid, :granted)
                        ON DUPLICATE KEY UPDATE granted = :granted2
                    ");
                    $stmt->execute([
                        ':uid'     => $selectedUserId,
                        ':pid'     => $permId,
                        ':granted' => $granted ? 1 : 0,
                        ':granted2'=> $granted ? 1 : 0,
                    ]);
                }
            }

            logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'permissions', 'updated', (string) $selectedUserId, 'Updated individual permissions for: ' . $selectedUser['full_name']);
            clearPermissionCache();
            setFlashMessage('success', 'Permissions updated successfully for "' . htmlspecialchars($selectedUser['full_name']) . '".');
            redirect(SITE_URL . 'admin/users/permissions.php?id=' . $selectedUserId);
        }

        if ($action === 'reset_permissions' && $selectedUser['role'] !== SUPER_ADMIN_ROLE) {
            $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = :uid");
            $stmt->execute([':uid' => $selectedUserId]);

            logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'permissions', 'reset', (string) $selectedUserId, 'Reset permissions to defaults for: ' . $selectedUser['full_name']);
            clearPermissionCache();
            setFlashMessage('success', 'Permissions reset to defaults for "' . htmlspecialchars($selectedUser['full_name']) . '".');
            redirect(SITE_URL . 'admin/users/permissions.php?id=' . $selectedUserId);
        }
    }
}

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div>
                <h1 class="fs-3 fw-bold" style="color: var(--admin-primary);">Manage Permissions</h1>
                <p class="text-muted">Configure individual permissions for admin users.</p>
            </div>
            <a href="<?= SITE_URL ?>admin/users/index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Users
            </a>
        </div>

        <?php if ($successMsg): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($successMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($errorMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Admin Users Table -->
        <div class="admin-card mb-4">
            <h1>Admin Accounts</h1>
            <p class="text-muted">Select a user to manage their individual permissions.</p>

            <div class="admin-table-wrap">
                <?php if (count($adminUsers) === 0): ?>
                    <p class="text-muted mb-0">No admin accounts found.</p>
                <?php else: ?>
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Permissions</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($adminUsers as $u):
                                $uOverrides = [];
                                if ($selectedUser && (int) $u['id'] === $selectedUserId) {
                                    $uOverrides = $userOverrides;
                                } elseif ($selectedUser) {
                                    // Fetch per-user overrides for non-selected users in the table
                                    $stmt2 = $pdo->prepare("SELECT permission_id, granted FROM user_permissions WHERE user_id = :uid");
                                    $stmt2->execute([':uid' => (int) $u['id']]);
                                    while ($row2 = $stmt2->fetch()) {
                                        $uOverrides[(int) $row2['permission_id']] = (bool) $row2['granted'];
                                    }
                                }
                                $permCount = countUserPermissions($u['role'], $adminRolePermIds, $allPermissions, $uOverrides);
                                $hasOverrides = count($uOverrides) > 0;
                            ?>
                                <tr class="<?= $selectedUser && (int) $u['id'] === $selectedUserId ? 'table-active' : '' ?>">
                                    <td><strong><?= (int) $u['id'] ?></strong></td>
                                    <td><?= htmlspecialchars($u['full_name']) ?></td>
                                    <td><a href="mailto:<?= htmlspecialchars($u['email']) ?>"><?= htmlspecialchars($u['email']) ?></a></td>
                                    <td>
                                        <?php if ($u['role'] === SUPER_ADMIN_ROLE): ?>
                                            <span class="badge bg-danger">Super Admin</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: var(--admin-primary);">Admin</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($u['status'] === ACTIVE_STATUS): ?>
                                            <span class="badge" style="background: var(--admin-green);">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($u['role'] === SUPER_ADMIN_ROLE): ?>
                                            <span class="badge bg-dark">All (<?= $permCount ?>)</span>
                                        <?php else: ?>
                                            <span class="badge bg-info"><?= $permCount ?> / <?= count($allPermissions) ?></span>
                                            <?php if ($hasOverrides): ?>
                                                <span class="badge bg-warning text-dark ms-1">Modified</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?id=<?= (int) $u['id'] ?>" class="btn btn-sm <?= $selectedUser && (int) $u['id'] === $selectedUserId ? 'btn-primary' : 'btn-outline-primary' ?>">
                                            <i class="bi bi-shield-check"></i> Manage
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selectedUser): ?>
            <!-- Permission Editor -->
            <div class="admin-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h1>Permissions for <?= htmlspecialchars($selectedUser['full_name']) ?></h1>
                        <p class="text-muted">
                            <?php if ($selectedUser['role'] === SUPER_ADMIN_ROLE): ?>
                                Super Admin accounts automatically have all permissions. Individual permissions cannot be modified.
                            <?php else: ?>
                                Toggle individual permissions below. Changes override the admin role defaults.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <?php if ($selectedUser['role'] === SUPER_ADMIN_ROLE): ?>
                    <div class="alert alert-info">
                        <strong>Super Admin</strong> — this user has unrestricted access to all features.
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <?= csrfField() ?>

                        <?php foreach ($groupedPermissions as $module => $perms): ?>
                            <div class="mb-4">
                                <h5 class="text-capitalize border-bottom pb-2"><?= htmlspecialchars(ucfirst($module)) ?></h5>
                                <div class="row">
                                    <?php foreach ($perms as $perm):
                                        $permId  = (int) $perm['id'];
                                        $checked = userHasPermission($permId, $selectedUser['role'], $adminRolePermIds, $userOverrides);
                                        $isOverride = array_key_exists($permId, $userOverrides);
                                    ?>
                                        <div class="col-md-4 mb-2">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="perm_<?= $permId ?>"
                                                       name="perms[]" value="<?= $permId ?>" <?= $checked ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="perm_<?= $permId ?>">
                                                    <?= htmlspecialchars($perm['description']) ?>
                                                    <?php if ($isOverride): ?>
                                                        <span class="badge bg-warning text-dark">Override</span>
                                                    <?php endif; ?>
                                                </label>
                                                <br><small class="text-muted"><?= htmlspecialchars($perm['name']) ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn" style="background: var(--admin-primary); color: #fff;" name="action" value="save_permissions">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                            <button type="submit" class="btn btn-warning" name="action" value="reset_permissions"
                                    onclick="return confirm('Reset all permission overrides for <?= htmlspecialchars($selectedUser['full_name'], ENT_QUOTES) ?> to admin defaults?');">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset to Defaults
                            </button>
                            <a href="<?= SITE_URL ?>admin/users/permissions.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </main>
</div>
<?php
require_once __DIR__ . '/../../includes/admin-footer.php';
