<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();
requirePermission('reviews.manage');

$pageTitle = 'Manage Reviews';

$pdo = getDbConnection();

$statusFilter = trim($_GET['status'] ?? '');
if (!in_array($statusFilter, ['', 'pending', 'approved', 'rejected'], true)) {
    $statusFilter = '';
}

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$where  = '';
$params = [];
if ($statusFilter !== '') {
    $where  = 'WHERE r.status = :status';
    $params[':status'] = $statusFilter;
}

$countSql  = "SELECT COUNT(*) FROM reviews r {$where}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total      = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$dataSql = "
    SELECT r.*, u.full_name AS user_name, p.name AS product_name, p.code AS product_code
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN products p ON r.product_id = p.id
    {$where}
    ORDER BY r.created_at DESC
    LIMIT :limit OFFSET :offset
";
$dataStmt = $pdo->prepare($dataSql);
foreach ($params as $key => $val) {
    $dataStmt->bindValue($key, $val);
}
$dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();
$reviews = $dataStmt->fetchAll();

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
                <h1 class="fs-3 fw-bold" style="color: var(--admin-primary);">Manage Reviews</h1>
                <p class="text-muted">Approve, reject, or delete customer product reviews. Total: <?= $total ?></p>
            </div>
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

        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link <?= $statusFilter === '' ? 'active' : '' ?>" href="?">All</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $statusFilter === 'pending' ? 'active' : '' ?>" href="?status=pending">Pending</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $statusFilter === 'approved' ? 'active' : '' ?>" href="?status=approved">Approved</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $statusFilter === 'rejected' ? 'active' : '' ?>" href="?status=rejected">Rejected</a>
            </li>
        </ul>

        <div class="admin-table-wrap">
            <?php if (count($reviews) === 0): ?>
                <div class="text-center py-5">
                    <p class="text-muted mb-3">No reviews found.</p>
                </div>
            <?php else: ?>
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Customer</th>
                            <th>Rating</th>
                            <th>Title</th>
                            <th>Review</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $r): ?>
                            <tr>
                                <td><strong><?= (int) $r['id'] ?></strong></td>
                                <td>
                                    <?php if ($r['product_name']): ?>
                                        <a href="<?= SITE_URL ?>admin/products/edit.php?code=<?= urlencode($r['product_code']) ?>" class="text-decoration-none">
                                            <?= htmlspecialchars(mb_substr($r['product_name'], 0, 40)) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Deleted</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($r['user_name'] ?? $r['name']) ?></td>
                                <td class="text-nowrap text-gold">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?= $i <= (int) $r['rating'] ? '★' : '☆' ?>
                                    <?php endfor; ?>
                                </td>
                                <td><?= $r['title'] ? htmlspecialchars(mb_substr($r['title'], 0, 30)) : '<span class="text-muted">&mdash;</span>' ?></td>
                                <td><?= htmlspecialchars(mb_substr($r['text'], 0, 60)) ?><?= mb_strlen($r['text']) > 60 ? '&hellip;' : '' ?></td>
                                <td class="text-nowrap"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                                <td>
                                    <?php if ($r['status'] === 'approved'): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php elseif ($r['status'] === 'rejected'): ?>
                                        <span class="badge bg-danger">Rejected</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <a href="<?= SITE_URL ?>admin/reviews/action.php?action=approve&id=<?= (int) $r['id'] ?>&_csrf_token=<?= urlencode(generateCsrfToken()) ?>"
                                           class="btn btn-sm" style="background: var(--admin-green); color: #fff;">Approve</a>
                                        <a href="<?= SITE_URL ?>admin/reviews/action.php?action=reject&id=<?= (int) $r['id'] ?>&_csrf_token=<?= urlencode(generateCsrfToken()) ?>"
                                           class="btn btn-sm btn-warning text-dark">Reject</a>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-danger"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-delete-url="<?= SITE_URL ?>admin/reviews/action.php?action=delete&id=<?= (int) $r['id'] ?>&_csrf_token=<?= urlencode(generateCsrfToken()) ?>">
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
                <p class="mb-0">Are you sure you want to delete this review?</p>
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
