<?php
/**
 * AJAX: Product Search
 *
 * Handles AJAX requests to search for products dynamically.
 *
 * Expected GET parameters:
 *   - q        : string  — The search query
 *   - category : string  — Optional category slug filter
 *   - page     : int     — Pagination page number (default: 1)
 *
 * PHP 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$query = trim($_GET['q'] ?? '');
if (mb_strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Search query too short.', 'products' => [], 'total' => 0, 'page' => 1, 'pages' => 0]);
    exit;
}

$category = trim($_GET['category'] ?? '');
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 12;
$offset   = ($page - 1) * $perPage;

$pdo = getDbConnection();

$where  = "WHERE p.status = 'active' AND p.name LIKE :q";
$params = [':q' => "%{$query}%"];

if ($category !== '') {
    $where .= ' AND c.slug = :category';
    $params[':category'] = $category;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id {$where}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = (int) ceil($total / $perPage);

$stmt = $pdo->prepare("
    SELECT p.id, p.code, p.name, p.price, p.discount_price, p.image, p.stock_quantity,
           c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    {$where}
    ORDER BY p.name ASC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$products = $stmt->fetchAll();

$results = [];
foreach ($products as $p) {
    $results[] = [
        'id'             => (int) $p['id'],
        'code'           => $p['code'],
        'name'           => $p['name'],
        'price'          => $p['price'],
        'discount_price' => $p['discount_price'],
        'image'          => $p['image'],
        'stock_quantity' => (int) $p['stock_quantity'],
        'category_name'  => $p['category_name'],
        'url'            => SITE_URL . 'pages/product-details.php?code=' . urlencode($p['code']),
    ];
}

echo json_encode([
    'success'  => true,
    'products' => $results,
    'total'    => $total,
    'page'     => $page,
    'pages'    => $pages,
    'query'    => $query,
]);
