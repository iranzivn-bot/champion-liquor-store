<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();
requirePermission('users.delete');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    setFlashMessage('error', 'Invalid user ID.');
    redirect(SITE_URL . 'admin/users/index.php');
}

if (!validateCsrfToken($_GET['_csrf_token'] ?? '')) {
    setFlashMessage('error', 'Invalid security token. Please try again.');
    redirect(SITE_URL . 'admin/users/index.php');
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    setFlashMessage('error', 'User not found.');
    redirect(SITE_URL . 'admin/users/index.php');
}

if ((int) $user['id'] === (int) $_SESSION['user_id']) {
    setFlashMessage('error', 'You cannot delete your own account.');
    redirect(SITE_URL . 'admin/users/index.php');
}

if ($user['role'] === SUPER_ADMIN_ROLE && !isSuperAdmin()) {
    setFlashMessage('error', 'You cannot delete another Super Admin account.');
    redirect(SITE_URL . 'admin/users/index.php');
}

$stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
$stmt->execute([':id' => $id]);

logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'users', 'deleted', (string) $id, 'Deleted user: ' . $user['full_name'] . ' (' . $user['email'] . ')');

setFlashMessage('success', 'User "' . htmlspecialchars($user['full_name']) . '" deleted successfully.');
redirect(SITE_URL . 'admin/users/index.php');
