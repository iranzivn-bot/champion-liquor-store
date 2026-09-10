<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$pageTitle = 'Inventory Dashboard';

$db = getDbConnection();

$stats = [];
$res = $db->query("SELECT COUNT(*) FROM products"); $stats['total'] = (int)$res->fetchColumn();
$res = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity > 0"); $stats['in_stock'] = (int)$res->fetchColumn();
$res = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity > 0 AND stock_quantity <= " . LOW_STOCK_THRESHOLD); $stats['low_stock'] = (int)$res->fetchColumn();
$res = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity = 0"); $stats['out_of_stock'] = (int)$res->fetchColumn();
$res = $db->query("SELECT COALESCE(SUM(stock_quantity), 0) FROM products"); $stats['total_qty'] = (int)$res->fetchColumn();

$lowStock = $db->query("
    SELECT p.id, p.code, p.name, p.stock_quantity
    FROM products p
    WHERE p.stock_quantity > 0 AND p.stock_quantity <= " . LOW_STOCK_THRESHOLD . "
    ORDER BY p.stock_quantity ASC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$outOfStock = $db->query("
    SELECT p.id, p.code, p.name
    FROM products p
    WHERE p.stock_quantity = 0
    ORDER BY p.name ASC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$recentMovements = $db->query("
    SELECT im.*, p.code AS product_code, p.name AS product_name,
           u.full_name AS created_by_name
    FROM inventory_movements im
    JOIN products p ON p.id = im.product_id
    LEFT JOIN users u ON u.id = im.created_by
    ORDER BY im.created_at DESC
    LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);

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

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= SITE_URL ?>assets/css/reports/inventory.css">

<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div>
                <h1 class="fs-3 fw-bold" style="color: var(--admin-primary);">Inventory Dashboard</h1>
                <p class="text-muted">Manage product stock levels and view movement history.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= SITE_URL ?>admin/inventory/stock-in.php" class="btn fw-semibold" style="background: var(--admin-primary); color: #fff;">
                    <i class="bi bi-plus-circle"></i> Stock In
                </a>
                <a href="<?= SITE_URL ?>admin/inventory/stock-adjustment.php" class="btn fw-semibold btn-outline-secondary">
                    <i class="bi bi-pencil"></i> Adjust
                </a>
                <a href="<?= SITE_URL ?>admin/inventory/history.php" class="btn fw-semibold btn-outline-secondary">
                    <i class="bi bi-clock-history"></i> History
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="inv-stat-card inv-stat-total">
                    <div class="inv-stat-icon"><i class="bi bi-box"></i></div>
                    <div class="inv-stat-body">
                        <span class="inv-stat-number"><?= $stats['total'] ?></span>
                        <span class="inv-stat-label">Total Products</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="inv-stat-card inv-stat-instock">
                    <div class="inv-stat-icon"><i class="bi bi-check-circle"></i></div>
                    <div class="inv-stat-body">
                        <span class="inv-stat-number"><?= $stats['in_stock'] ?></span>
                        <span class="inv-stat-label">In Stock</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="inv-stat-card inv-stat-lowstock">
                    <div class="inv-stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="inv-stat-body">
                        <span class="inv-stat-number"><?= $stats['low_stock'] ?></span>
                        <span class="inv-stat-label">Low Stock (≤<?= LOW_STOCK_THRESHOLD ?>)</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="inv-stat-card inv-stat-outstock">
                    <div class="inv-stat-icon"><i class="bi bi-x-circle"></i></div>
                    <div class="inv-stat-body">
                        <span class="inv-stat-number"><?= $stats['out_of_stock'] ?></span>
                        <span class="inv-stat-label">Out of Stock</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="inv-section-title"><i class="bi bi-exclamation-triangle"></i> Low Stock Products</div>
                <?php if ($lowStock): ?>
                <div class="inv-table">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Stock</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lowStock as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['code']) ?></td>
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td><span class="badge bg-warning text-dark"><?= (int)$p['stock_quantity'] ?></span></td>
                                <td class="text-end">
                                    <a href="<?= SITE_URL ?>admin/inventory/stock-in.php?product_id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-plus"></i> Stock In
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p class="text-muted">No low-stock products.</p>
                <?php endif; ?>
            </div>

            <div class="col-md-6">
                <div class="inv-section-title"><i class="bi bi-x-circle"></i> Out of Stock Products</div>
                <?php if ($outOfStock): ?>
                <div class="inv-table">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($outOfStock as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['code']) ?></td>
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td class="text-end">
                                    <a href="<?= SITE_URL ?>admin/inventory/stock-in.php?product_id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-plus"></i> Stock In
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p class="text-muted">All products are in stock.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="inv-section-title"><i class="bi bi-clock-history"></i> Recent Stock Movements</div>
        <?php if ($recentMovements): ?>
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
                <?php foreach ($recentMovements as $m): ?>
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
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="text-muted">No movements recorded yet.</p>
        <?php endif; ?>

    </main>
</div>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
