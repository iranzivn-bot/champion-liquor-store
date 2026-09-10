<?php
/**
 * AJAX: Add to Cart
 *
 * Adds a product to the logged-in user's cart.
 * If the product already exists in the cart, the quantity is increased
 * by the requested amount (capped by stock_quantity).
 *
 * Security:
 *   - Requires authentication via auth middleware
 *   - Validates product exists, is active, and has sufficient stock
 *   - Uses prepared statements for all DB queries
 *
 * Expected POST parameters:
 *   - product_id : int  — The product to add
 *   - quantity   : int  — Quantity to add (default: 1)
 *
 * Response (JSON):
 *   Success: { "success": true, "message": "...", "cart_count": 3 }
 *   Error:   { "success": false, "message": "..." }
 *
 * PHP 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// ─── Require logged-in user ───────────────────────────────────────────
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please log in to add items to your cart.']);
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

$userId    = (int) $_SESSION['user_id'];
$productId = (int) ($_POST['product_id'] ?? 0);
$quantity  = max(1, (int) ($_POST['quantity'] ?? 1));

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit;
}

$pdo = getDbConnection();

// ─── Validate product exists, is active, and has stock ────────────────
$stmt = $pdo->prepare('SELECT id, status, stock_quantity, price FROM products WHERE id = :id');
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

$stock = (int) $product['stock_quantity'];
if ($stock <= 0) {
    echo json_encode(['success' => false, 'message' => 'This product is out of stock.']);
    exit;
}

// ─── Check if product already in cart ─────────────────────────────────
$stmt = $pdo->prepare('SELECT id, quantity, price FROM cart WHERE user_id = :uid AND product_id = :pid');
$stmt->execute([':uid' => $userId, ':pid' => $productId]);
$existing = $stmt->fetch();

if ($existing) {
    // Product already in cart — increase quantity (capped by stock)
    $newQty = min($stock, (int) $existing['quantity'] + $quantity);

    if ($newQty <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid quantity.']);
        exit;
    }

    // Use the price already stored in the cart (price snapshot)
    $price    = (float) $existing['price'];
    $subtotal = $price * $newQty;

    $stmt = $pdo->prepare('UPDATE cart SET quantity = :qty, subtotal = :sub, updated_at = NOW() WHERE id = :id');
    $stmt->execute([':qty' => $newQty, ':sub' => $subtotal, ':id' => $existing['id']]);

    $addedQty = $newQty - (int) $existing['quantity'];

} else {
    // New product — insert with quantity capped by stock
    $newQty = min($stock, $quantity);

    if ($newQty <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid quantity.']);
        exit;
    }

    // Snapshot the product's current price at the time of adding
    $price    = (float) $product['price'];
    $subtotal = $price * $newQty;

    $stmt = $pdo->prepare('INSERT INTO cart (user_id, product_id, price, quantity, subtotal) VALUES (:uid, :pid, :price, :qty, :sub)');
    $stmt->execute([':uid' => $userId, ':pid' => $productId, ':price' => $price, ':qty' => $newQty, ':sub' => $subtotal]);

    $addedQty = $newQty;
}

// ─── Get updated cart count ───────────────────────────────────────────
$stmt = $pdo->prepare('SELECT SUM(quantity) FROM cart WHERE user_id = :uid');
$stmt->execute([':uid' => $userId]);
$cartCount = (int) $stmt->fetchColumn();

echo json_encode([
    'success'    => true,
    'message'    => 'Product added to cart successfully.',
    'cart_count' => $cartCount,
]);
exit;
