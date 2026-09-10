<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$productId = (int) ($_GET['product_id'] ?? 0);

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit;
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('
    SELECT r.*, u.full_name AS user_name
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.product_id = :pid AND r.status = \'approved\'
    ORDER BY r.created_at DESC
');
$stmt->execute([':pid' => $productId]);
$reviews = $stmt->fetchAll();

$countStmt = $pdo->prepare('
    SELECT rating, COUNT(*) AS count
    FROM reviews
    WHERE product_id = :pid AND status = \'approved\'
    GROUP BY rating
');
$countStmt->execute([':pid' => $productId]);
$ratingCounts = $countStmt->fetchAll();

$ratingCountsMap = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
foreach ($ratingCounts as $row) {
    $ratingCountsMap[(int) $row['rating']] = (int) $row['count'];
}

$totalReviews = array_sum($ratingCountsMap);
$totalStars   = 0;
foreach ($ratingCountsMap as $star => $cnt) {
    $totalStars += $star * $cnt;
}
$averageRating = $totalReviews > 0 ? round($totalStars / $totalReviews, 1) : 0;

$formattedReviews = [];
foreach ($reviews as $r) {
    $formattedReviews[] = [
        'id'         => (int) $r['id'],
        'name'       => htmlspecialchars($r['user_name'] ?? $r['name']),
        'rating'     => (int) $r['rating'],
        'title'      => $r['title'] ? htmlspecialchars($r['title']) : null,
        'text'       => htmlspecialchars($r['text']),
        'created_at' => date('M j, Y', strtotime($r['created_at'])),
    ];
}

echo json_encode([
    'success'        => true,
    'average_rating' => $averageRating,
    'total_reviews'  => $totalReviews,
    'rating_counts'  => $ratingCountsMap,
    'reviews'        => $formattedReviews,
]);
exit;
