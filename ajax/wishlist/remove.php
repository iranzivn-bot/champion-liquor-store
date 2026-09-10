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

// ─── Identify user (logged-in or guest) ─────────────────────────
$userId = null;
$sessionId = null;
if (function_exists('isLoggedIn') && isLoggedIn()) {
    $userId = (int) $_SESSION['user_id'];
} else {
    $sessionId = $_SESSION['wishlist_session_id'] ?? null;
    if (!$sessionId) {
        echo json_encode(['success' => false, 'message' => 'Nothing to remove.', 'in_wishlist' => false]);
        exit;
    }
}

// ─── Find record ────────────────────────────────────────────────
if ($userId) {
    $stmt = $pdo->prepare('SELECT id FROM wishlist WHERE user_id = :uid AND product_id = :pid');
    $stmt->execute([':uid' => $userId, ':pid' => $productId]);
} else {
    $stmt = $pdo->prepare('SELECT id FROM wishlist WHERE session_id = :sid AND product_id = :pid');
    $stmt->execute([':sid' => $sessionId, ':pid' => $productId]);
}
$wish = $stmt->fetch();

if (!$wish) {
    echo json_encode(['success' => false, 'message' => 'Item not found in wishlist.', 'in_wishlist' => false]);
    exit;
}

// ─── Delete ─────────────────────────────────────────────────────
if ($userId) {
    $stmt = $pdo->prepare('DELETE FROM wishlist WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $wish['id'], ':uid' => $userId]);
} else {
    $stmt = $pdo->prepare('DELETE FROM wishlist WHERE id = :id AND session_id = :sid');
    $stmt->execute([':id' => $wish['id'], ':sid' => $sessionId]);
}

if ($userId) {
    logActivity($userId, $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'wishlist', 'deleted', $productId, 'Product removed from wishlist. Product ID: ' . $productId);
}

if ($userId) {
    $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM wishlist WHERE user_id = :uid');
    $cntStmt->execute([':uid' => $userId]);
} else {
    $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM wishlist WHERE session_id = :sid');
    $cntStmt->execute([':sid' => $sessionId]);
}
$wishlistCount = (int) $cntStmt->fetchColumn();

echo json_encode([
    'success'        => true,
    'message'        => 'Product removed from wishlist.',
    'in_wishlist'    => false,
    'wishlist_count' => $wishlistCount,
]);
exit;
