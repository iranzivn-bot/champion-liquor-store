<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$db = getDbConnection();

$filterProduct    = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$filterMovement   = $_GET['movement_type'] ?? '';
$filterDateFrom   = $_GET['date_from'] ?? '';
$filterDateTo     = $_GET['date_to'] ?? '';
$exportFormat     = $_GET['export'] ?? '';

$validMovements = [
    INV_MOVEMENT_STOCK_IN,
    INV_MOVEMENT_STOCK_OUT,
    INV_MOVEMENT_ADJUSTMENT,
    INV_MOVEMENT_ORDER,
    INV_MOVEMENT_ORDER_CANCELLED,
];
if ($filterMovement && !in_array($filterMovement, $validMovements, true)) {
    $filterMovement = '';
}

$where = [];
$params = [];

if ($filterProduct > 0) {
    $where[] = 'im.product_id = ?';
    $params[] = $filterProduct;
}
if ($filterMovement !== '') {
    $where[] = 'im.movement_type = ?';
    $params[] = $filterMovement;
}
if ($filterDateFrom !== '') {
    $where[] = 'DATE(im.created_at) >= ?';
    $params[] = $filterDateFrom;
}
if ($filterDateTo !== '') {
    $where[] = 'DATE(im.created_at) <= ?';
    $params[] = $filterDateTo;
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$movementLabels = [
    INV_MOVEMENT_STOCK_IN        => 'Stock In',
    INV_MOVEMENT_STOCK_OUT       => 'Stock Out',
    INV_MOVEMENT_ADJUSTMENT      => 'Adjustment',
    INV_MOVEMENT_ORDER           => 'Order',
    INV_MOVEMENT_ORDER_CANCELLED => 'Order Cancelled',
];
$movementBadges = [
    INV_MOVEMENT_STOCK_IN        => 'bg-success',
    INV_MOVEMENT_STOCK_OUT       => 'bg-danger',
    INV_MOVEMENT_ADJUSTMENT      => 'bg-warning text-dark',
    INV_MOVEMENT_ORDER           => 'bg-primary',
    INV_MOVEMENT_ORDER_CANCELLED => 'bg-info text-dark',
];

// Export
if ($exportFormat === 'csv' || $exportFormat === 'excel') {
    $stmt = $db->prepare("
        SELECT im.created_at, p.code, p.name,
               im.movement_type, im.quantity, im.stock_before, im.stock_after,
               im.notes, u.full_name
        FROM inventory_movements im
        JOIN products p ON p.id = im.product_id
        LEFT JOIN users u ON u.id = im.created_by
        $whereClause
        ORDER BY im.created_at DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $isExcel = $exportFormat === 'excel';
    $filename = 'inventory_history_' . date('Ymd_His') . ($isExcel ? '.xls' : '.csv');

    header('Content-Type: ' . ($isExcel ? 'application/vnd.ms-excel' : 'text/csv; charset=utf-8'));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Product Code', 'Product Name', 'Movement Type', 'Quantity', 'Stock Before', 'Stock After', 'Notes', 'Created By']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['created_at'],
            $r['code'],
            $r['name'],
            $movementLabels[$r['movement_type']] ?? $r['movement_type'],
            $r['quantity'],
            $r['stock_before'],
            $r['stock_after'],
            $r['notes'],
            $r['full_name'],
        ]);
    }
    fclose($out);
    exit;
}

// Pagination
$page     = max(1, (int)($_GET['p'] ?? 1));
$perPage  = 30;
$offset   = ($page - 1) * $perPage;

$countStmt = $db->prepare("SELECT COUNT(*) FROM inventory_movements im $whereClause");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $perPage);

$stmt = $db->prepare("
    SELECT im.*, p.code AS product_code, p.name AS product_name,
           u.full_name AS created_by_name
    FROM inventory_movements im
    JOIN products p ON p.id = im.product_id
    LEFT JOIN users u ON u.id = im.created_by
    $whereClause
    ORDER BY im.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$allProducts = $db->query("SELECT id, code, name FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Inventory History';
require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= SITE_URL ?>assets/css/reports/inventory.css">

<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div>
                <h1 class="fs-3 fw-bold" style="color: var(--admin-primary);">Inventory History</h1>
                <p class="text-muted">View and export stock movement records.</p>
            </div>
            <a href="<?= SITE_URL ?>admin/inventory/index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
        </div>

        <form method="get" class="inv-filters">
            <div>
                <label class="form-label">Product</label>
                <select name="product_id" class="form-select form-select-sm" style="width:180px;">
                    <option value="">All Products</option>
                    <?php foreach ($allProducts as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $filterProduct === (int)$p['id'] ? 'selected' : '' ?>>
                        [<?= htmlspecialchars($p['code']) ?>] <?= htmlspecialchars(mb_substr($p['name'], 0, 40)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Movement Type</label>
                <select name="movement_type" class="form-select form-select-sm" style="width:150px;">
                    <option value="">All Types</option>
                    <?php foreach ($movementLabels as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val) ?>" <?= $filterMovement === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filterDateFrom) ?>" style="width:140px;">
            </div>
            <div>
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filterDateTo) ?>" style="width:140px;">
            </div>
            <div style="display:flex;gap:4px;">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filter</button>
                <a href="<?= SITE_URL ?>admin/inventory/history.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>

        <?php
        $queryParams = [];
        if ($filterProduct > 0) $queryParams['product_id'] = $filterProduct;
        if ($filterMovement !== '') $queryParams['movement_type'] = $filterMovement;
        if ($filterDateFrom !== '') $queryParams['date_from'] = $filterDateFrom;
        if ($filterDateTo !== '') $queryParams['date_to'] = $filterDateTo;
        $baseUrl = SITE_URL . 'admin/inventory/history.php?' . http_build_query($queryParams);
        ?>

        <div class="d-flex gap-2 mb-3">
            <a href="<?= $baseUrl ?>&export=csv" class="btn btn-outline-success btn-sm"><i class="bi bi-filetype-csv"></i> CSV</a>
            <a href="<?= $baseUrl ?>&export=excel" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        </div>

        <div class="inv-table">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Before</th>
                        <th>After</th>
                        <th>Notes</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($movements): ?>
                    <?php foreach ($movements as $m): ?>
                    <tr>
                        <td class="inv-td-date"><?= htmlspecialchars(date('d M Y H:i', strtotime($m['created_at']))) ?></td>
                        <td>
                            <span class="inv-td-code"><?= htmlspecialchars($m['product_code']) ?></span><br>
                            <?= htmlspecialchars($m['product_name']) ?>
                        </td>
                        <td>
                            <span class="badge <?= $movementBadges[$m['movement_type']] ?? 'bg-secondary' ?>">
                                <?= $movementLabels[$m['movement_type']] ?? htmlspecialchars($m['movement_type']) ?>
                            </span>
                        </td>
                        <td class="fw-semibold"><?= (int)$m['quantity'] ?></td>
                        <td><?= (int)$m['stock_before'] ?></td>
                        <td><?= (int)$m['stock_after'] ?></td>
                        <td class="inv-td-notes"><?= htmlspecialchars($m['notes'] ?? '') ?></td>
                        <td><?= htmlspecialchars($m['created_by_name'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No movements found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <?php
        $pageUrl = SITE_URL . 'admin/inventory/history.php?' . http_build_query(array_merge($queryParams, ['p' => '']));
        ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $pageUrl . ($page - 1) ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $pageUrl . $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $pageUrl . ($page + 1) ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

    </main>
</div>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
