<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$pdo    = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

$userId = null;
$sessionId = null;
if (function_exists('isLoggedIn') && isLoggedIn()) {
    $userId = (int) $_SESSION['user_id'];
} else {
    $sessionId = $_SESSION['wishlist_session_id'] ?? null;
    if (!$sessionId && $method !== 'POST') {
        echo json_encode(['success' => true, 'data' => ['items' => [], 'wishlist_count' => 0]]);
        exit;
    }
}

switch ($method) {

    case 'GET':
        if ($userId) {
            $stmt = $pdo->prepare('
                SELECT w.id AS wishlist_id, w.product_id, w.created_at AS added_at,
                       p.code, p.name, p.price, p.image, p.stock_quantity, p.status
                FROM wishlist w
                JOIN products p ON w.product_id = p.id
                WHERE w.user_id = :uid
                ORDER BY w.created_at DESC
            ');
            $stmt->execute([':uid' => $userId]);
        } else {
            $stmt = $pdo->prepare('
                SELECT w.id AS wishlist_id, w.product_id, w.created_at AS added_at,
                       p.code, p.name, p.price, p.image, p.stock_quantity, p.status
                FROM wishlist w
                JOIN products p ON w.product_id = p.id
                WHERE w.session_id = :sid
                ORDER BY w.created_at DESC
            ');
            $stmt->execute([':sid' => $sessionId]);
        }
        $items = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data'    => [
                'items'          => $items,
                'wishlist_count' => count($items),
            ],
        ]);
        break;

    case 'POST':
        require __DIR__ . '/../ajax/wishlist/add.php';
        break;

    case 'DELETE':
        parse_str(file_get_contents('php://input'), $_DELETE);
        $_POST['product_id'] = (int) ($_DELETE['product_id'] ?? 0);
        require __DIR__ . '/../ajax/wishlist/remove.php';
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
        break;
}
