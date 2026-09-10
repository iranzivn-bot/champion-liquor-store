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

$userId = null;
$sessionId = null;
if (function_exists('isLoggedIn') && isLoggedIn()) {
    $userId = (int) $_SESSION['user_id'];
} else {
    if (empty($_SESSION['compare_session_id'])) {
        $_SESSION['compare_session_id'] = bin2hex(random_bytes(16));
    }
    $sessionId = $_SESSION['compare_session_id'];
}

// ─── Check duplicate ────────────────────────────────
if ($userId) {
    $stmt = $pdo->prepare('SELECT id FROM compare_list WHERE user_id = :uid AND product_id = :pid');
    $stmt->execute([':uid' => $userId, ':pid' => $productId]);
} else {
    $stmt = $pdo->prepare('SELECT id FROM compare_list WHERE session_id = :sid AND product_id = :pid');
    $stmt->execute([':sid' => $sessionId, ':pid' => $productId]);
}

if ($stmt->fetch()) {
    if ($userId) {
        $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM compare_list WHERE user_id = :uid');
        $cntStmt->execute([':uid' => $userId]);
    } else {
        $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM compare_list WHERE session_id = :sid');
        $cntStmt->execute([':sid' => $sessionId]);
    }
    echo json_encode([
        'success'       => false,
        'message'       => 'Product already in comparison list.',
        'in_compare'    => true,
        'compare_count' => (int) $cntStmt->fetchColumn(),
    ]);
    exit;
}

// ─── Check max limit ────────────────────────────────
if ($userId) {
    $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM compare_list WHERE user_id = :uid');
    $cntStmt->execute([':uid' => $userId]);
} else {
    $cntStmt = $pdo->prepare('SELECT COUNT(*) FROM compare_list WHERE session_id = :sid');
    $cntStmt->execute([':sid' => $sessionId]);
}
$currentCount = (int) $cntStmt->fetchColumn();

if ($currentCount >= COMPARE_MAX_ITEMS) {
    echo json_encode([
        'success'       => false,
        'message'       => 'You can compare up to ' . COMPARE_MAX_ITEMS . ' products at a time.',
        'in_compare'    => false,
        'compare_count' => $currentCount,
    ]);
    exit;
}

// ─── Insert ─────────────────────────────────────────
$stmt = $pdo->prepare('INSERT INTO compare_list (user_id, product_id, session_id) VALUES (:uid, :pid, :sid)');
$stmt->execute([':uid' => $userId, ':pid' => $productId, ':sid' => $sessionId]);

if ($userId) {
    logActivity($userId, $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'compare', 'created', $productId, 'Product added to compare. Product ID: ' . $productId);
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
    'message'       => 'Product added to compare.',
    'in_compare'    => true,
    'compare_count' => $compareCount,
]);
exit;
