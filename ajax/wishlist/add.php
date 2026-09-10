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

$stmt = $pdo->prepare('SELECT id, status FROM products WHERE id = :id');
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found.']);
    exit;
}

if ($product['status'] !== ACTIVE_STATUS) {
    echo json_encode(['success' => false, 'message' => 'This product is not available.']);
    exit;
}

// ─── Identify user (logged-in or guest) ─────────────────────────
$userId = null;
$sessionId = null;
if (function_exists('isLoggedIn') && isLoggedIn()) {
    $userId = (int) $_SESSION['user_id'];
} else {
    if (empty($_SESSION['wishlist_session_id'])) {
        $_SESSION['wishlist_session_id'] = bin2hex(random_bytes(16));
    }
    $sessionId = $_SESSION['wishlist_session_id'];
}

// ─── Check duplicate ────────────────────────────────────────────
if ($userId) {
    $stmt = $pdo->prepare('SELECT id FROM wishlist WHERE user_id = :uid AND product_id = :pid');
    $stmt->execute([':uid' => $userId, ':pid' => $productId]);
} else {
    $stmt = $pdo->prepare('SELECT id FROM wishlist WHERE session_id = :sid AND product_id = :pid');
    $stmt->execute([':sid' => $sessionId, ':pid' => $productId]);
}

if ($stmt->fetch()) {
    if ($userId) {
        $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM wishlist WHERE user_id = :uid');
        $cntStmt->execute([':uid' => $userId]);
    } else {
        $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM wishlist WHERE session_id = :sid');
        $cntStmt->execute([':sid' => $sessionId]);
    }
    echo json_encode([
        'success'        => false,
        'message'        => 'Product already exists in wishlist.',
        'in_wishlist'    => true,
        'wishlist_count' => (int) $cntStmt->fetchColumn(),
    ]);
    exit;
}

// ─── Insert ─────────────────────────────────────────────────────
$stmt = $pdo->prepare('INSERT INTO wishlist (user_id, product_id, session_id) VALUES (:uid, :pid, :sid)');
$stmt->execute([':uid' => $userId, ':pid' => $productId, ':sid' => $sessionId]);

if ($userId) {
    logActivity($userId, $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'wishlist', 'created', $productId, 'Product added to wishlist. Product ID: ' . $productId);
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
    'message'        => 'Product added to wishlist.',
    'in_wishlist'    => true,
    'wishlist_count' => $wishlistCount,
]);
exit;
