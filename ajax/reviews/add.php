<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$token = $_POST['_csrf_token'] ?? '';
if (!validateCsrfToken($token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

$productId = (int) ($_POST['product_id'] ?? 0);
$rating    = (int) ($_POST['rating'] ?? 0);
$title     = trim($_POST['title'] ?? '');
$text      = trim($_POST['text'] ?? '');

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5.']);
    exit;
}

if ($text === '') {
    echo json_encode(['success' => false, 'message' => 'Review text is required.']);
    exit;
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('SELECT id FROM products WHERE id = :id');
$stmt->execute([':id' => $productId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Product not found.']);
    exit;
}

$userId   = null;
$name     = '';
$sessionId = null;

if (function_exists('isLoggedIn') && isLoggedIn()) {
    $userId = (int) $_SESSION['user_id'];
    $name   = trim($_SESSION['user_name'] ?? '');
} else {
    $guestName = trim($_POST['name'] ?? '');
    if ($guestName === '') {
        echo json_encode(['success' => false, 'message' => 'Name is required for guest reviews.']);
        exit;
    }
    $name      = $guestName;
    if (empty($_SESSION['review_session_id'])) {
        $_SESSION['review_session_id'] = bin2hex(random_bytes(16));
    }
    $sessionId = $_SESSION['review_session_id'];
}

if ($userId) {
    $checkStmt = $pdo->prepare("SELECT id, status FROM reviews WHERE product_id = :pid AND user_id = :uid AND status IN ('pending', 'approved')");
    $checkStmt->execute([':pid' => $productId, ':uid' => $userId]);
} else {
    $checkStmt = $pdo->prepare("SELECT id, status FROM reviews WHERE product_id = :pid AND session_id = :sid AND status IN ('pending', 'approved')");
    $checkStmt->execute([':pid' => $productId, ':sid' => $sessionId]);
}
$existing = $checkStmt->fetch();

if ($existing) {
    $msg = $existing['status'] === 'approved' ? 'You have already reviewed this product.' : 'You already have a pending review for this product.';
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$insertStmt = $pdo->prepare('INSERT INTO reviews (product_id, user_id, session_id, name, rating, title, text, status) VALUES (:pid, :uid, :sid, :name, :rating, :title, :text, \'pending\')');
$insertStmt->execute([
    ':pid'    => $productId,
    ':uid'    => $userId,
    ':sid'    => $sessionId,
    ':name'   => $name,
    ':rating' => $rating,
    ':title'  => $title !== '' ? $title : null,
    ':text'   => $text,
]);

echo json_encode(['success' => true, 'message' => 'Review submitted! Awaiting approval.']);
exit;
