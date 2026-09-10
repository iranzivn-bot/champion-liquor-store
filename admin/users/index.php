<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();
requirePermission('users.view');

$pageTitle = 'Users';

$pdo = getDbConnection();

$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$conditions = [];
$params = [];

if ($search !== '') {
    $conditions[] = '(full_name LIKE :search1 OR email LIKE :search2 OR phone LIKE :search3 OR id LIKE :search4)';
    $params[':search1'] = "%{$search}%";
    $params[':search2'] = "%{$search}%";
    $params[':search3'] = "%{$search}%";
    $params[':search4'] = "%{$search}%";
}
if ($roleFilter !== '' && in_array($roleFilter, [ADMIN_ROLE, CUSTOMER_ROLE], true)) {
    $conditions[] = 'role = :role';
    $params[':role'] = $roleFilter;
}
if ($statusFilter !== '' && in_array($statusFilter, [ACTIVE_STATUS, INACTIVE_STATUS], true)) {
    $conditions[] = 'status = :status';
    $params[':status'] = $statusFilter;
}

$where = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countSql = "SELECT COUNT(*) FROM users {$where}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$dataSql = "SELECT * FROM users {$where} ORDER BY id DESC LIMIT :limit OFFSET :offset";
$dataStmt = $pdo->prepare($dataSql);
foreach ($params as $key => $val) {
    $dataStmt->bindValue($key, $val);
}
$dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();
$users = $dataStmt->fetchAll();

$successMsg = getFlashMessage('success');
$errorMsg = getFlashMessage('error');

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div>
                <h1 class="fs-3 fw-bold" style="color: var(--admin-primary);">Users</h1>
                <p class="text-muted">Manage registered user accounts.</p>
            </div>
            <a href="<?= SITE_URL ?>admin/users/create.php"
               class="btn" style="background: var(--admin-primary); color: #fff;">
                <i class="bi bi-plus-lg"></i> Add New User
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

        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control"
                       placeholder="Search by name, email, phone or ID&hellip;"
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="role" class="form-select">
                    <option value="">All Roles</option>
                    <option value="<?= ADMIN_ROLE ?>" <?= $roleFilter === ADMIN_ROLE ? 'selected' : '' ?>>Admin</option>
                    <option value="<?= CUSTOMER_ROLE ?>" <?= $roleFilter === CUSTOMER_ROLE ? 'selected' : '' ?>>Customer</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="<?= ACTIVE_STATUS ?>" <?= $statusFilter === ACTIVE_STATUS ? 'selected' : '' ?>>Active</option>
                    <option value="<?= INACTIVE_STATUS ?>" <?= $statusFilter === INACTIVE_STATUS ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
            <?php if ($search !== '' || $roleFilter !== '' || $statusFilter !== ''): ?>
                <div class="col-auto">
                    <a href="<?= SITE_URL ?>admin/users/index.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            <?php endif; ?>
        </form>

        <div class="admin-table-wrap">
            <?php if (count($users) === 0): ?>
                <div class="text-center py-5">
                    <p class="text-muted mb-3">No users found.</p>
                    <a href="<?= SITE_URL ?>admin/users/create.php" class="btn"
                       style="background: var(--admin-primary); color: #fff;">
                        Add New User
                    </a>
                </div>
            <?php else: ?>
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Login Attempts</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><strong><?= (int) $u['id'] ?></strong></td>
                                <td><?= htmlspecialchars($u['full_name']) ?></td>
                                <td><a href="mailto:<?= htmlspecialchars($u['email']) ?>"><?= htmlspecialchars($u['email']) ?></a></td>
                                <td><?= htmlspecialchars($u['phone'] ?? '&mdash;') ?></td>
                                <td>
                                    <?php if ($u['role'] === SUPER_ADMIN_ROLE): ?>
                                        <span class="badge bg-danger">Super Admin</span>
                                    <?php elseif ($u['role'] === ADMIN_ROLE): ?>
                                        <span class="badge" style="background: var(--admin-primary);">Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Customer</span>
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
                                    <?php $attempts = (int) ($u['login_attempts'] ?? 0); ?>
                                    <?php if ($attempts >= 3): ?>
                                        <span class="badge bg-danger"><?= $attempts ?></span>
                                    <?php elseif ($attempts > 0): ?>
                                        <span class="badge" style="background: #fd7e14;"><?= $attempts ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                                <td class="text-nowrap">
                                    <a href="<?= SITE_URL ?>admin/users/edit.php?id=<?= (int) $u['id'] ?>"
                                       class="btn btn-sm" style="background: var(--admin-gold); color: #fff;">Edit</a>
                                    <?php if ($u['role'] === ADMIN_ROLE || $u['role'] === SUPER_ADMIN_ROLE): ?>
                                        <a href="<?= SITE_URL ?>admin/users/permissions.php?id=<?= (int) $u['id'] ?>"
                                           class="btn btn-sm btn-outline-info">Permissions</a>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-danger"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-delete-url="<?= SITE_URL ?>admin/users/delete.php?id=<?= (int) $u['id'] ?>&amp;_csrf_token=<?= urlencode(generateCsrfToken()) ?>"
                                            data-user-name="<?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </main>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <p class="mb-0">Are you sure you want to delete <strong id="deleteUserName"></strong>?</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" class="btn btn-danger" id="confirmDeleteBtn">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var deleteUrl = button.getAttribute('data-delete-url');
            var userName = button.getAttribute('data-user-name');
            document.getElementById('confirmDeleteBtn').href = deleteUrl;
            document.getElementById('deleteUserName').textContent = userName;
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/admin-footer.php';
