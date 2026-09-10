<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDbConnection();

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    redirect(SITE_URL . 'pages/blog/index.php');
}

$stmt = $pdo->prepare("
    SELECT p.*, u.full_name AS author_name
    FROM blog_posts p
    LEFT JOIN users u ON p.author_id = u.id
    WHERE p.slug = :slug AND p.status = 'published'
");
$stmt->execute([':slug' => $slug]);
$post = $stmt->fetch();

if (!$post) {
    require_once __DIR__ . '/../../includes/header.php';
    require_once __DIR__ . '/../../includes/navbar.php';
    ?>
    <div class="page-hero-sm">
        <div class="container">
            <h1 class="fw-bold mb-1">Post Not Found</h1>
            <p class="text-white-50 mb-0">The blog post you're looking for doesn't exist.</p>
        </div>
    </div>
    <section class="section-padding">
        <div class="container text-center">
            <p class="text-muted">The post may have been removed or is no longer published.</p>
            <a href="<?= SITE_URL ?>pages/blog/index.php" class="btn" style="background: var(--gold); color: #fff;">Back to Blog</a>
        </div>
    </section>
    <?php
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Recent posts for sidebar
$recentStmt = $pdo->prepare("SELECT id, title, slug, published_at FROM blog_posts WHERE status = 'published' AND id != :id ORDER BY published_at DESC LIMIT 5");
$recentStmt->execute([':id' => (int) $post['id']]);
$recentPosts = $recentStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-hero-sm">
    <div class="container">
        <a href="<?= SITE_URL ?>pages/blog/index.php" class="text-white-50 text-decoration-none mb-2 d-inline-block">
            <i class="bi bi-arrow-left me-1"></i> Back to Blog
        </a>
        <h1 class="fw-bold mb-1"><?= htmlspecialchars($post['title']) ?></h1>
        <p class="text-white-50 mb-0">
            <i class="bi bi-person me-1"></i><?= htmlspecialchars($post['author_name'] ?? 'Admin') ?>
            &middot;
            <i class="bi bi-calendar3 me-1"></i><?= date('F j, Y', strtotime($post['published_at'] ?? $post['created_at'])) ?>
        </p>
    </div>
</div>

<section class="section-padding">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-8">

                <?php if ($post['image']): ?>
                    <img src="<?= SITE_URL ?>uploads/blogs/<?= htmlspecialchars($post['image']) ?>"
                         class="w-100 rounded mb-4" alt="<?= htmlspecialchars($post['title']) ?>"
                         style="max-height: 400px; object-fit: cover;">
                <?php endif; ?>

                <div class="blog-post-content">
                    <?= $post['content'] ?>
                </div>

                <hr class="my-4">
                <div class="d-flex justify-content-between">
                    <div>
                        <?php if ($post['published_at']): ?>
                            <small class="text-muted">Published on <?= date('F j, Y', strtotime($post['published_at'])) ?></small>
                        <?php endif; ?>
                    </div>
                    <a href="<?= SITE_URL ?>pages/blog/index.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> All Posts
                    </a>
                </div>

            </div>

            <div class="col-lg-4">
                <div class="blog-sidebar">
                    <div class="blog-sidebar-widget">
                        <h6 class="fw-bold mb-3">Recent Posts</h6>
                        <?php if (count($recentPosts) === 0): ?>
                            <p class="text-muted small mb-0">No other posts yet.</p>
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
