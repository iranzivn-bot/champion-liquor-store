<?php
/**
 * AJAX: Clear Cart
 *
 * Removes ALL items from the logged-in user's cart.
 *
 * Security:
 *   - Requires authentication
 *   - Deletes only items belonging to the current user
 *   - Uses prepared statements
 *
 * Expected POST parameters: (none)
 *
 * Response (JSON):
 *   Success: { "success": true, "message": "Cart cleared successfully.", "cart_count": 0 }
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
$pdo    = getDbConnection();

// ─── Delete all cart items for this user ──────────────────────────────
$stmt = $pdo->prepare('DELETE FROM cart WHERE user_id = :uid');
$stmt->execute([':uid' => $userId]);

echo json_encode([
    'success'    => true,
    'message'    => 'Cart cleared successfully.',
    'cart_count' => 0,
]);
exit;
