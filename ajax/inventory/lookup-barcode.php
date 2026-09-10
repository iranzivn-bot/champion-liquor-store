<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$barcode = trim($_GET['barcode'] ?? '');
if ($barcode === '') {
    echo json_encode(['success' => false, 'message' => 'Barcode is required']);
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT id, code, name, stock_quantity, barcode FROM products WHERE barcode = :barcode LIMIT 1');
    $stmt->execute([':barcode' => $barcode]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'No product found with this barcode']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'product' => [
            'id'             => (int) $product['id'],
            'code'           => $product['code'],
            'name'           => $product['name'],
            'stock_quantity' => (int) $product['stock_quantity'],
            'barcode'        => $product['barcode'],
        ],
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    error_log('Barcode lookup failed: ' . $e->getMessage());
}
