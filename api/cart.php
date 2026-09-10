<?php
/**
 * Cart RESTful API
 *
 * Unified API endpoint for cart operations.
 * Routes requests based on HTTP method.
 *
 * Methods:
 *   GET    — Returns the current user's cart contents
 *   POST   — Adds a product to the cart
 *   PUT    — Updates an item's quantity
 *   DELETE — Removes an item or clears the cart
 *
 * All responses are JSON.
 *
 * IMPORTANT: This is a secondary API. For AJAX requests from the frontend,
 * use the dedicated ajax/cart/ endpoints instead.
 *
 * PHP 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$pdo    = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ─── GET: Retrieve cart contents ──────────────────────────────────
    case 'GET':
        $stmt = $pdo->prepare('
            SELECT c.id AS cart_id, c.quantity,
                   p.id AS product_id, p.code, p.name, p.price, p.image, p.stock_quantity
            FROM cart c
            JOIN products p ON c.product_id = p.id
            WHERE c.user_id = :uid
            ORDER BY c.created_at DESC
        ');
        $stmt->execute([':uid' => $userId]);
        $items = $stmt->fetchAll();

        $total = 0;
        foreach ($items as &$item) {
            $item['subtotal'] = (float) $item['price'] * (int) $item['quantity'];
            $total += $item['subtotal'];
        }
        unset($item);

        echo json_encode([
            'success' => true,
            'data'    => [
                'items'      => $items,
                'total'      => $total,
                'cart_count' => array_sum(array_column($items, 'quantity')),
            ],
        ]);
        break;

    // ─── POST: Add item ───────────────────────────────────────────────
    case 'POST':
        // Delegate to the AJAX add handler
        require __DIR__ . '/../ajax/cart/add.php';
        break;

    // ─── PUT: Update quantity ─────────────────────────────────────────
    case 'PUT':
        parse_str(file_get_contents('php://input'), $_PUT);
        $_POST['cart_id']  = (int) ($_PUT['cart_id'] ?? 0);
        $_POST['quantity'] = (int) ($_PUT['quantity'] ?? 1);
        require __DIR__ . '/../ajax/cart/update.php';
        break;

    // ─── DELETE: Remove item or clear cart ────────────────────────────
    case 'DELETE':
        parse_str(file_get_contents('php://input'), $_DELETE);

        if (isset($_DELETE['clear']) && $_DELETE['clear'] === '1') {
            require __DIR__ . '/../ajax/cart/clear.php';
        } else {
            $_POST['cart_id'] = (int) ($_DELETE['cart_id'] ?? 0);
            require __DIR__ . '/../ajax/cart/remove.php';
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
        break;
}
