<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();

$pageTitle = 'Categories';

$pdo = getDbConnection();

$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$where   = '';
$params  = [];
if ($search !== '') {
    $where = 'WHERE name LIKE :search1 OR slug LIKE :search2 OR code LIKE :search3';
    $params[':search1'] = "%{$search}%";
    $params[':search2'] = "%{$search}%";
    $params[':search3'] = "%{$search}%";
}

$countSql  = "SELECT COUNT(*) FROM categories {$where}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total      = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$dataSql  = "SELECT * FROM categories {$where} ORDER BY id ASC LIMIT :limit OFFSET :offset";
$dataStmt = $pdo->prepare($dataSql);
foreach ($params as $key => $val) {
    $dataStmt->bindValue($key, $val);
}
$dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();
$categories = $dataStmt->fetchAll();

$successMsg = getFlashMessage('success');
$errorMsg   = getFlashMessage('error');

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>

<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div>
                <h1 class="fs-3 fw-bold" style="color: var(--admin-primary);">Categories</h1>
                <p class="text-muted">Manage your product categories.</p>
            </div>
            <a href="<?= SITE_URL ?>admin/categories/create.php"
               class="btn" style="background: var(--admin-primary); color: #fff;">
                <i class="bi bi-plus-lg"></i> Add New Category
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
            <div class="col-auto flex-grow-1">
                <input type="text" name="search" class="form-control"
                       placeholder="Search by code, name or slug&hellip;"
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
            <?php if ($search !== ''): ?>
                <div class="col-auto">
                    <a href="<?= SITE_URL ?>admin/categories/index.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            <?php endif; ?>
        </form>

        <div class="admin-table-wrap">
            <?php if (count($categories) === 0): ?>
                <div class="text-center py-5">
                    <p class="text-muted mb-3">No categories found.</p>
                    <a href="<?= SITE_URL ?>admin/categories/create.php" class="btn"
                       style="background: var(--admin-primary); color: #fff;">
                        Add New Category
                    </a>
                </div>
            <?php else: ?>
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th>Group</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($cat['code']) ?></strong></td>
                                <td>
                                    <?php if ($cat['image']): ?>
                                        <img src="<?= SITE_URL ?>uploads/categories/<?= htmlspecialchars($cat['image']) ?>"
                                             alt="<?= htmlspecialchars($cat['name']) ?>"
                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($cat['name']) ?></td>
                                <td><code><?= htmlspecialchars($cat['slug']) ?></code></td>
                                <td>
                                    <?php if ($cat['status'] === ACTIVE_STATUS): ?>
                                        <span class="badge" style="background: var(--admin-green);">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($cat['group'] ?? 'liquor') === 'mini_market'): ?>
                                        <span class="badge bg-info text-dark">Mini Market</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: var(--admin-primary);">Liquor</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M j, Y', strtotime($cat['created_at'])) ?></td>
                                <td class="text-nowrap">
                                    <a href="<?= SITE_URL ?>admin/categories/edit.php?code=<?= urlencode($cat['code']) ?>"
                                       class="btn btn-sm" style="background: var(--admin-gold); color: #fff;">Edit</a>
                                    <button type="button" class="btn btn-sm btn-danger"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-delete-url="<?= SITE_URL ?>admin/categories/delete.php?code=<?= urlencode($cat['code']) ?>&amp;_csrf_token=<?= urlencode(generateCsrfToken()) ?>">
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
                <p class="mb-0">Are you sure you want to delete this category?</p>
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
            document.getElementById('confirmDeleteBtn').href = deleteUrl;
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/admin-footer.php';
