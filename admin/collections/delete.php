<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    setFlashMessage('error', 'Invalid collection slug.');
    redirect(SITE_URL . 'admin/collections/index.php');
}

if (!validateCsrfToken($_GET['_csrf_token'] ?? '')) {
    setFlashMessage('error', 'Invalid security token. Please try again.');
    redirect(SITE_URL . 'admin/collections/index.php');
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('SELECT * FROM collections WHERE slug = :slug');
$stmt->execute([':slug' => $slug]);
$collection = $stmt->fetch();

if (!$collection) {
    setFlashMessage('error', 'Collection not found.');
    redirect(SITE_URL . 'admin/collections/index.php');
}

$uploadDir = COLLECTION_UPLOAD_PATH;
if ($collection['image'] && file_exists($uploadDir . $collection['image'])) {
    unlink($uploadDir . $collection['image']);
}

$stmt = $pdo->prepare('DELETE FROM collections WHERE slug = :slug');
$stmt->execute([':slug' => $slug]);

logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'collections', 'deleted', $collection['slug'], 'Deleted collection: ' . $collection['name']);

setFlashMessage('success', 'Collection "' . htmlspecialchars($collection['name']) . '" deleted successfully.');
redirect(SITE_URL . 'admin/collections/index.php');
