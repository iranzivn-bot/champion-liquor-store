<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();

$pageTitle = 'Blog Posts';

$pdo = getDbConnection();

$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$where   = '';
$params  = [];
if ($search !== '') {
    $where = 'WHERE title LIKE :search OR slug LIKE :search2';
    $params[':search']  = "%{$search}%";
    $params[':search2'] = "%{$search}%";
}

$countSql  = "SELECT COUNT(*) FROM blog_posts {$where}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total      = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$dataSql  = "SELECT p.*, u.full_name AS author_name FROM blog_posts p LEFT JOIN users u ON p.author_id = u.id {$where} ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";
$dataStmt = $pdo->prepare($dataSql);
foreach ($params as $key => $val) {
    $dataStmt->bindValue($key, $val);
}
$dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();
$posts = $dataStmt->fetchAll();

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
                <h1 class="fs-3 fw-bold" style="color: var(--admin-primary);">Blog Posts</h1>
                <p class="text-muted">Manage your blog posts.</p>
            </div>
            <a href="<?= SITE_URL ?>admin/blog/create.php"
               class="btn" style="background: var(--admin-primary); color: #fff;">
                <i class="bi bi-plus-lg"></i> Add New Post
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
                       placeholder="Search by title or slug&hellip;"
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
            <?php if ($search !== ''): ?>
                <div class="col-auto">
                    <a href="<?= SITE_URL ?>admin/blog/index.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            <?php endif; ?>
        </form>

        <div class="admin-table-wrap">
            <?php if (count($posts) === 0): ?>
                <div class="text-center py-5">
                    <p class="text-muted mb-3">No blog posts found.</p>
                    <a href="<?= SITE_URL ?>admin/blog/create.php" class="btn"
                       style="background: var(--admin-primary); color: #fff;">
                        Add New Post
                    </a>
                </div>
            <?php else: ?>
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Title</th>
                            <th>Slug</th>
                            <th>Author</th>
                            <th>Status</th>
                            <th>Published</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $p): ?>
                            <tr>
                                <td><strong><?= (int) $p['id'] ?></strong></td>
                                <td>
                                    <?php if ($p['image']): ?>
                                        <img src="<?= SITE_URL ?>uploads/blogs/<?= htmlspecialchars($p['image']) ?>"
                                             alt="<?= htmlspecialchars($p['title']) ?>"
                                             style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px;">
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($p['title']) ?></td>
                                <td><code><?= htmlspecialchars($p['slug']) ?></code></td>
                                <td><?= htmlspecialchars($p['author_name'] ?? 'Unknown') ?></td>
                                <td>
                                    <?php if ($p['status'] === 'published'): ?>
                                        <span class="badge" style="background: var(--admin-green);">Published</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Draft</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $p['published_at'] ? date('M j, Y', strtotime($p['published_at'])) : '<span class="text-muted">&mdash;</span>' ?>
                                </td>
                                <td><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                                <td class="text-nowrap">
                                    <a href="<?= SITE_URL ?>admin/blog/edit.php?id=<?= (int) $p['id'] ?>"
                                       class="btn btn-sm" style="background: var(--admin-gold); color: #fff;">Edit</a>
                                    <button type="button" class="btn btn-sm btn-danger"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-delete-url="<?= SITE_URL ?>admin/blog/delete.php?id=<?= (int) $p['id'] ?>&amp;_csrf_token=<?= urlencode(generateCsrfToken()) ?>">
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
                <p class="mb-0">Are you sure you want to delete this blog post?</p>
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
