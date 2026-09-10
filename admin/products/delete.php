<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    setFlashMessage('error', 'Invalid product ID.');
    redirect(SITE_URL . 'admin/products/index.php');
}

// CSRF check: validate token from query string
if (!validateCsrfToken($_GET['_csrf_token'] ?? '')) {
    setFlashMessage('error', 'Invalid security token. Please try again.');
    redirect(SITE_URL . 'admin/products/index.php');
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
$stmt->execute([':id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    setFlashMessage('error', 'Product not found.');
    redirect(SITE_URL . 'admin/products/index.php');
}

$uploadDir = PRODUCT_UPLOAD_PATH;
if ($product['image'] && file_exists($uploadDir . $product['image'])) {
    unlink($uploadDir . $product['image']);
}

$stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
$stmt->execute([':id' => $id]);

logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'products', 'deleted', $product['code'], 'Deleted product: ' . $product['name'] . ' (' . $product['code'] . ')');

setFlashMessage('success', 'Product deleted successfully.');
redirect(SITE_URL . 'admin/products/index.php');
