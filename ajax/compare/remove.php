<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!validateCSRFTokenRequest()) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

$productId = (int) ($_POST['product_id'] ?? 0);

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit;
}

$pdo = getDbConnection();

$userId = null;
$sessionId = null;
if (function_exists('isLoggedIn') && isLoggedIn()) {
    $userId = (int) $_SESSION['user_id'];
} else {
    $sessionId = $_SESSION['compare_session_id'] ?? null;
    if (!$sessionId) {
        echo json_encode(['success' => false, 'message' => 'Nothing to remove.', 'in_compare' => false]);
        exit;
    }
}

// ─── Find record ────────────────────────────────────
if ($userId) {
    $stmt = $pdo->prepare('SELECT id FROM compare_list WHERE user_id = :uid AND product_id = :pid');
    $stmt->execute([':uid' => $userId, ':pid' => $productId]);
} else {
    $stmt = $pdo->prepare('SELECT id FROM compare_list WHERE session_id = :sid AND product_id = :pid');
    $stmt->execute([':sid' => $sessionId, ':pid' => $productId]);
}
$item = $stmt->fetch();

if (!$item) {
    echo json_encode(['success' => false, 'message' => 'Item not found in compare list.', 'in_compare' => false]);
    exit;
}

// ─── Delete ─────────────────────────────────────────
if ($userId) {
    $stmt = $pdo->prepare('DELETE FROM compare_list WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $item['id'], ':uid' => $userId]);
} else {
    $stmt = $pdo->prepare('DELETE FROM compare_list WHERE id = :id AND session_id = :sid');
    $stmt->execute([':id' => $item['id'], ':sid' => $sessionId]);
}

if ($userId) {
    logActivity($userId, $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'compare', 'deleted', $productId, 'Product removed from compare. Product ID: ' . $productId);
}

if ($userId) {
    $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM compare_list WHERE user_id = :uid');
    $cntStmt->execute([':uid' => $userId]);
} else {
    $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM compare_list WHERE session_id = :sid');
    $cntStmt->execute([':sid' => $sessionId]);
}
$compareCount = (int) $cntStmt->fetchColumn();

echo json_encode([
    'success'       => true,
    'message'       => 'Product removed from compare.',
    'in_compare'    => false,
    'compare_count' => $compareCount,
]);
exit;
