<?php
/**
 * AJAX: Update Cart Item Quantity
 *
 * Updates the quantity of a specific item in the user's cart.
 * Only the item owner can update it.
 *
 * Security:
 *   - Requires authentication
 *   - Validates cart item belongs to the current user
 *   - Validates quantity against product stock
 *   - Prevents quantity below 1
 *   - Uses prepared statements
 *
 * Expected POST parameters:
 *   - cart_id  : int  — The cart item ID to update
 *   - quantity : int  — The new quantity (min 1, max stock)
 *
 * Response (JSON):
 *   Success: { "success": true, "message": "...", "subtotal": 15000.00, "total": 45000.00, "cart_count": 3 }
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

$userId   = (int) $_SESSION['user_id'];
$cartId   = (int) ($_POST['cart_id'] ?? 0);
$quantity = max(1, (int) ($_POST['quantity'] ?? 1));

if ($cartId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid cart item.']);
    exit;
}

$pdo = getDbConnection();

// ─── Fetch cart item with product stock — ensure ownership ────────────
// NOTE: We use c.price (the cart's price snapshot) rather than p.price
// so that future product price changes do not affect existing cart items.
$stmt = $pdo->prepare('
    SELECT c.id, c.quantity AS current_qty, c.price AS cart_price,
           p.stock_quantity, p.status
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.id = :cid AND c.user_id = :uid
');
$stmt->execute([':cid' => $cartId, ':uid' => $userId]);
$item = $stmt->fetch();

if (!$item) {
    echo json_encode(['success' => false, 'message' => 'Cart item not found.']);
    exit;
}

if ($item['status'] !== ACTIVE_STATUS) {
    echo json_encode(['success' => false, 'message' => 'This product is no longer available.']);
    exit;
}

// ─── Cap quantity at available stock ──────────────────────────────────
$stock  = (int) $item['stock_quantity'];
$newQty = min($quantity, $stock);

if ($newQty < 1) {
    echo json_encode(['success' => false, 'message' => 'Insufficient stock.']);
    exit;
}

// ─── Use the cart's stored price snapshot ─────────────────────────────
$price    = (float) $item['cart_price'];
$subtotal = $price * $newQty;

// ─── Update the quantity AND subtotal ─────────────────────────────────
$stmt = $pdo->prepare('UPDATE cart SET quantity = :qty, subtotal = :sub, updated_at = NOW() WHERE id = :id');
$stmt->execute([':qty' => $newQty, ':sub' => $subtotal, ':id' => $cartId]);

// ─── Calculate cart total from cart subtotals ─────────────────────────
$stmt = $pdo->prepare('
    SELECT SUM(c.subtotal)
    FROM cart c
    WHERE c.user_id = :uid
');
$stmt->execute([':uid' => $userId]);
$total = (float) $stmt->fetchColumn();

// ─── Get updated cart count ───────────────────────────────────────────
$stmt = $pdo->prepare('SELECT SUM(quantity) FROM cart WHERE user_id = :uid');
$stmt->execute([':uid' => $userId]);
$cartCount = (int) $stmt->fetchColumn();

echo json_encode([
    'success'    => true,
    'message'    => 'Cart updated.',
    'subtotal'   => $subtotal,
    'total'      => $total,
    'cart_count' => $cartCount,
    'quantity'   => $newQty,
]);
exit;
