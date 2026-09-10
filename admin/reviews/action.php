<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();
requirePermission('reviews.manage');

$action = trim($_GET['action'] ?? '');
$id     = (int) ($_GET['id'] ?? 0);

if (!in_array($action, ['approve', 'reject', 'delete'], true) || $id <= 0) {
    setFlashMessage('error', 'Invalid request.');
    redirect(SITE_URL . 'admin/reviews/');
}

$token = $_GET['_csrf_token'] ?? '';
if (!validateCsrfToken($token)) {
    setFlashMessage('error', 'Invalid security token.');
    redirect(SITE_URL . 'admin/reviews/');
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('SELECT id FROM reviews WHERE id = :id');
$stmt->execute([':id' => $id]);
if (!$stmt->fetch()) {
    setFlashMessage('error', 'Review not found.');
    redirect(SITE_URL . 'admin/reviews/');
}

if ($action === 'approve') {
    $stmt = $pdo->prepare("UPDATE reviews SET status = 'approved' WHERE id = :id");
    $stmt->execute([':id' => $id]);
    setFlashMessage('success', 'Review approved successfully.');
} elseif ($action === 'reject') {
    $stmt = $pdo->prepare("UPDATE reviews SET status = 'rejected' WHERE id = :id");
    $stmt->execute([':id' => $id]);
    setFlashMessage('success', 'Review rejected successfully.');
} elseif ($action === 'delete') {
    $stmt = $pdo->prepare('DELETE FROM reviews WHERE id = :id');
    $stmt->execute([':id' => $id]);
    setFlashMessage('success', 'Review deleted successfully.');
}

redirect(SITE_URL . 'admin/reviews/');
