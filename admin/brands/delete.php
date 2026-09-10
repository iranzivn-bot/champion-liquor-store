<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();

$code = trim($_GET['code'] ?? '');
if ($code === '') {
    setFlashMessage('error', 'Invalid brand code.');
    redirect(SITE_URL . 'admin/brands/index.php');
}

if (!validateCsrfToken($_GET['_csrf_token'] ?? '')) {
    setFlashMessage('error', 'Invalid security token. Please try again.');
    redirect(SITE_URL . 'admin/brands/index.php');
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('SELECT * FROM brands WHERE code = :code');
$stmt->execute([':code' => $code]);
$brand = $stmt->fetch();

if (!$brand) {
    setFlashMessage('error', 'Brand not found.');
    redirect(SITE_URL . 'admin/brands/index.php');
}

$uploadDir = BRAND_UPLOAD_PATH;
if ($brand['logo'] && file_exists($uploadDir . $brand['logo'])) {
    unlink($uploadDir . $brand['logo']);
}

$stmt = $pdo->prepare('DELETE FROM brands WHERE code = :code');
$stmt->execute([':code' => $code]);

logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'brands', 'deleted', $brand['code'], 'Deleted brand: ' . $brand['name'] . ' (' . $brand['code'] . ')');

setFlashMessage('success', 'Brand "' . htmlspecialchars($brand['name']) . ' (' . $brand['code'] . ')" deleted successfully.');
redirect(SITE_URL . 'admin/brands/index.php');
