<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();

$code = trim($_GET['code'] ?? '');
if ($code === '') {
    setFlashMessage('error', 'Invalid category code.');
    redirect(SITE_URL . 'admin/categories/index.php');
}

// CSRF check: validate token from query string
if (!validateCsrfToken($_GET['_csrf_token'] ?? '')) {
    setFlashMessage('error', 'Invalid security token. Please try again.');
    redirect(SITE_URL . 'admin/categories/index.php');
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('SELECT * FROM categories WHERE code = :code');
$stmt->execute([':code' => $code]);
$category = $stmt->fetch();

if (!$category) {
    setFlashMessage('error', 'Category not found.');
    redirect(SITE_URL . 'admin/categories/index.php');
}

$uploadDir = CATEGORY_UPLOAD_PATH;
if ($category['image'] && file_exists($uploadDir . $category['image'])) {
    unlink($uploadDir . $category['image']);
}

$stmt = $pdo->prepare('DELETE FROM categories WHERE code = :code');
$stmt->execute([':code' => $code]);

logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'categories', 'deleted', $category['code'], 'Deleted category: ' . $category['name'] . ' (' . $category['code'] . ')');

setFlashMessage('success', 'Category "' . htmlspecialchars($category['name']) . ' (' . $category['code'] . ')" deleted successfully.');
redirect(SITE_URL . 'admin/categories/index.php');
