<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDbConnection();

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 6;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'");
$countStmt->execute();
$total      = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT p.*, u.full_name AS author_name
    FROM blog_posts p
    LEFT JOIN users u ON p.author_id = u.id
    WHERE p.status = 'published'
    ORDER BY p.published_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

// Get recent posts for sidebar
$recentStmt = $pdo->prepare("SELECT id, title, slug, published_at FROM blog_posts WHERE status = 'published' ORDER BY published_at DESC LIMIT 5");
$recentStmt->execute();
$recentPosts = $recentStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-hero-sm">
    <div class="container">
        <h1 class="fw-bold mb-1"><?= lang('blog') ?></h1>
        <p class="text-white-50 mb-0">Stories, guides, and news from Champion Liquor Store</p>
    </div>
</div>

<section class="section-padding">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-8">

                <?php if (count($posts) === 0): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-pencil-square" style="font-size: 3rem; color: #ccc;"></i>
                        <p class="text-muted mt-3 mb-0">No blog posts published yet. Check back soon!</p>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($posts as $post): ?>
                            <div class="col-md-6">
                                <div class="blog-card">
                                    <a href="<?= SITE_URL ?>pages/blog/post.php?slug=<?= urlencode($post['slug']) ?>">
                                        <img src="<?= $post['image'] ? SITE_URL . 'uploads/blogs/' . htmlspecialchars($post['image']) : DEFAULT_BLOG_IMAGE ?>"
                                             class="blog-card-img" alt="<?= htmlspecialchars($post['title']) ?>">
                                    </a>
                                    <div class="blog-card-body">
                                        <div class="blog-card-meta">
                                            <span><i class="bi bi-person me-1"></i><?= htmlspecialchars($post['author_name'] ?? 'Admin') ?></span>
                                            <span><i class="bi bi-calendar3 me-1"></i><?= date('M j, Y', strtotime($post['published_at'] ?? $post['created_at'])) ?></span>
                                        </div>
                                        <h5 class="blog-card-title">
                                            <a href="<?= SITE_URL ?>pages/blog/post.php?slug=<?= urlencode($post['slug']) ?>"
                                               class="text-decoration-none"><?= htmlspecialchars($post['title']) ?></a>
                                        </h5>
                                        <p class="blog-card-excerpt">
                                            <?= htmlspecialchars($post['excerpt'] ?: mb_substr(strip_tags($post['content']), 0, 150) . '...') ?>
                                        </p>
                                        <a href="<?= SITE_URL ?>pages/blog/post.php?slug=<?= urlencode($post['slug']) ?>"
                                           class="blog-read-more">Read More <i class="bi bi-arrow-right"></i></a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="mt-4">
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

            <div class="col-lg-4">
                <div class="blog-sidebar">
                    <div class="blog-sidebar-widget">
                        <h6 class="fw-bold mb-3">Recent Posts</h6>
                        <?php if (count($recentPosts) === 0): ?>
                            <p class="text-muted small mb-0">No posts yet.</p>
                        <?php else: ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($recentPosts as $rp): ?>
                                    <li class="mb-2">
                                        <a href="<?= SITE_URL ?>pages/blog/post.php?slug=<?= urlencode($rp['slug']) ?>"
                                           class="text-decoration-none">
                                            <i class="bi bi-chevron-right me-1 small"></i>
                                            <?= htmlspecialchars($rp['title']) ?>
                                        </a>
                                        <div class="small text-muted"><?= date('M j, Y', strtotime($rp['published_at'])) ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
