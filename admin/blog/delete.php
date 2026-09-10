<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlashMessage('error', 'Invalid post ID.');
    redirect(SITE_URL . 'admin/blog/index.php');
}

if (!validateCsrfToken($_GET['_csrf_token'] ?? '')) {
    setFlashMessage('error', 'Invalid security token. Please try again.');
    redirect(SITE_URL . 'admin/blog/index.php');
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE id = :id');
$stmt->execute([':id' => $id]);
$post = $stmt->fetch();

if (!$post) {
    setFlashMessage('error', 'Blog post not found.');
    redirect(SITE_URL . 'admin/blog/index.php');
}

$uploadDir = BLOG_UPLOAD_PATH;
if ($post['image'] && file_exists($uploadDir . $post['image'])) {
    unlink($uploadDir . $post['image']);
}

$stmt = $pdo->prepare('DELETE FROM blog_posts WHERE id = :id');
$stmt->execute([':id' => $id]);

logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'blog', 'deleted', (string) $id, 'Deleted blog post: ' . $post['title']);

setFlashMessage('success', 'Blog post "' . htmlspecialchars($post['title']) . '" deleted successfully.');
redirect(SITE_URL . 'admin/blog/index.php');
