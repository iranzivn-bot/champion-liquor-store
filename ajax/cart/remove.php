<?php
/**
 * AJAX: Remove from Cart
 *
 * Removes a single item from the user's cart.
 * Only the item owner can remove it.
 *
 * Security:
 *   - Requires authentication
 *   - Validates cart item belongs to the current user
 *   - Uses prepared statements
 *
 * Expected POST parameters:
 *   - cart_id : int  — The cart item ID to remove
 *
 * Response (JSON):
 *   Success: { "success": true, "message": "...", "cart_count": 2 }
 *   Error:   { "success": false, "message": "..." }
 *
 * PHP 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please log in.']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!validateCSRFTokenRequest()) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$cartId = (int) ($_POST['cart_id'] ?? 0);

if ($cartId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid cart item.']);
    exit;
}

$pdo = getDbConnection();

// ─── Verify ownership before deleting ─────────────────────────────────
$stmt = $pdo->prepare('SELECT id FROM cart WHERE id = :id AND user_id = :uid');
$stmt->execute([':id' => $cartId, ':uid' => $userId]);

if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Cart item not found.']);
    exit;
}

// ─── Delete the item ──────────────────────────────────────────────────
$stmt = $pdo->prepare('DELETE FROM cart WHERE id = :id AND user_id = :uid');
$stmt->execute([':id' => $cartId, ':uid' => $userId]);

// ─── Get updated cart count ───────────────────────────────────────────
$stmt = $pdo->prepare('SELECT SUM(quantity) FROM cart WHERE user_id = :uid');
$stmt->execute([':uid' => $userId]);
$cartCount = (int) $stmt->fetchColumn();

echo json_encode([
    'success'    => true,
    'message'    => 'Item removed from cart.',
    'cart_count' => $cartCount,
]);
exit;
