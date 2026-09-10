<?php
/**
 * Inventory Analytics Report
 *
 * Inventory value, stock levels, fast/slow/dead moving products
 *
 * Champion Liquor Store Ltd — Enterprise Reporting
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$pageTitle = 'Inventory Analytics';
require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= SITE_URL ?>assets/css/reports/reports.css">
<link rel="stylesheet" href="<?= SITE_URL ?>assets/css/reports/inventory.css">
<style>
.chart-container { position:relative; height:280px; width:100%; }
.mv-badge { font-size:0.75rem; padding:0.25em 0.6em; }
</style>

<div class="admin-content-wrapper">
<main class="admin-content">

<?php
$db = getDbConnection();

$categoryFilter = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$brandFilter    = isset($_GET['brand_id']) ? (int)$_GET['brand_id'] : 0;

$whereExtra = '';
$params = [];
if ($categoryFilter > 0) {
    $whereExtra .= " AND p.category_id = ?";
    $params[] = $categoryFilter;
}
if ($brandFilter > 0) {
    $whereExtra .= " AND p.brand_id = ?";
    $params[] = $brandFilter;
}

// ─── Inventory Value ─────────────────────────────────────────────────
$invValue = $db->query("
    SELECT
        COALESCE(SUM(price * stock_quantity), 0) AS retail_value,
        COALESCE(SUM(COALESCE(cost_price, 0) * stock_quantity), 0) AS cost_value,
        COUNT(*) AS total_products,
        COALESCE(SUM(stock_quantity), 0) AS total_stock
    FROM products WHERE status = 'active'
")->fetch(PDO::FETCH_ASSOC);

$retailValue = (float)$invValue['retail_value'];
$costValue   = (float)$invValue['cost_value'];
$totalProds  = (int)$invValue['total_products'];
$totalStock  = (int)$invValue['total_stock'];

$lowStockCountInv = (int)$db->prepare("SELECT COUNT(*) FROM products WHERE status='active' AND stock_quantity>0 AND stock_quantity<=?")->execute([LOW_STOCK_THRESHOLD]);
$lowStockCountInv = $db->query("SELECT COUNT(*) FROM products WHERE status='active' AND stock_quantity>0 AND stock_quantity<=" . LOW_STOCK_THRESHOLD)->fetchColumn();
$outStockCountInv = (int)$db->query("SELECT COUNT(*) FROM products WHERE status='active' AND stock_quantity=0")->fetchColumn();

// ─── Fast Moving Products (highest sales velocity) ───────────────────
$fastMoving = $db->query("
    SELECT p.name, p.code, p.stock_quantity, p.price,
           COALESCE(SUM(oi.quantity), 0) AS total_sold,
           COALESCE(SUM(oi.subtotal), 0) AS revenue
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'delivered'
    WHERE p.status = 'active'
    GROUP BY p.id, p.name, p.code, p.stock_quantity, p.price
    HAVING total_sold > 0
    ORDER BY total_sold DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// ─── Slow Moving Products (low sales, positive stock) ────────────────
$slowMoving = $db->query("
    SELECT p.name, p.code, p.stock_quantity, p.price,
           COALESCE(SUM(oi.quantity), 0) AS total_sold,
           COALESCE(SUM(oi.subtotal), 0) AS revenue
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'delivered'
    WHERE p.status = 'active' AND p.stock_quantity > 0
    GROUP BY p.id, p.name, p.code, p.stock_quantity, p.price
    HAVING total_sold > 0 AND total_sold <= 3
    ORDER BY total_sold ASC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// ─── Dead Stock (in stock but never sold) ────────────────────────────
$deadStock = $db->query("
    SELECT p.name, p.code, p.stock_quantity, p.price,
           (p.price * p.stock_quantity) AS stock_value
    FROM products p
    WHERE p.status = 'active' AND p.stock_quantity > 0
      AND p.id NOT IN (
          SELECT DISTINCT oi.product_id FROM order_items oi
          JOIN orders o ON oi.order_id = o.id
          WHERE o.status = 'delivered' AND oi.product_id IS NOT NULL
      )
    ORDER BY p.stock_quantity DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// ─── Inventory by Category ───────────────────────────────────────────
$invByCat = $db->query("
    SELECT c.name,
           COUNT(*) AS product_count,
           COALESCE(SUM(p.stock_quantity), 0) AS total_stock,
           COALESCE(SUM(p.price * p.stock_quantity), 0) AS stock_value
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'active'
    GROUP BY c.id, c.name
    ORDER BY stock_value DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ─── Categories and Brands for filters ───────────────────────────────
$allCats = $db->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$allBrands = $db->query("SELECT id, name FROM brands WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Chart data
$invCatLabels = []; $invCatValues = [];
foreach ($invByCat as $ic) {
    $invCatLabels[] = $ic['name'];
    $invCatValues[] = (float)$ic['stock_value'];
}

$fastNames = []; $fastSold = [];
foreach (array_slice($fastMoving, 0, 10) as $fm) {
    $fastNames[] = $fm['name'];
    $fastSold[] = (int)$fm['total_sold'];
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="fs-3 fw-bold" style="color:var(--admin-primary);">Inventory Analytics</h1>
        <p class="text-muted">Stock value, velocity analysis, and dead stock detection.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= SITE_URL ?>admin/reports/export.php?type=inventory&format=csv" class="btn btn-outline-success btn-sm fw-semibold"><i class="bi bi-filetype-csv"></i> CSV</a>
        <a href="<?= SITE_URL ?>admin/reports/export.php?type=inventory&format=excel" class="btn btn-outline-success btn-sm fw-semibold"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        <a href="javascript:window.print()" class="btn btn-outline-secondary btn-sm fw-semibold"><i class="bi bi-printer"></i> Print</a>
    </div>
</div>

<!-- Filters -->
<form method="get" class="inv-filters mb-4">
    <div>
        <label class="form-label">Category</label>
        <select name="category_id" class="form-select form-select-sm" style="width:180px;">
            <option value="">All Categories</option>
            <?php foreach ($allCats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $categoryFilter === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label">Brand</label>
        <select name="brand_id" class="form-select form-select-sm" style="width:180px;">
            <option value="">All Brands</option>
            <?php foreach ($allBrands as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= $brandFilter === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="display:flex;gap:4px;">
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Apply</button>
        <a href="<?= SITE_URL ?>admin/reports/inventory.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
    </div>
</form>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="inv-stat-card inv-stat-total">
            <div class="inv-stat-icon"><i class="bi bi-cash-stack"></i></div>
            <div class="inv-stat-body">
                <span class="inv-stat-number"><?= formatPrice($retailValue) ?></span>
                <span class="inv-stat-label">Retail Value</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="inv-stat-card inv-stat-qty">
            <div class="inv-stat-icon"><i class="bi bi-coin"></i></div>
            <div class="inv-stat-body">
                <span class="inv-stat-number"><?= formatPrice($costValue) ?></span>
                <span class="inv-stat-label">Cost Value</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="inv-stat-card inv-stat-instock">
            <div class="inv-stat-icon"><i class="bi bi-boxes"></i></div>
            <div class="inv-stat-body">
                <span class="inv-stat-number"><?= number_format($totalStock) ?></span>
                <span class="inv-stat-label">Total Stock Qty</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="inv-stat-card inv-stat-products">
            <div class="inv-stat-icon"><i class="bi bi-box"></i></div>
            <div class="inv-stat-body">
                <span class="inv-stat-number"><?= number_format($totalProds) ?></span>
                <span class="inv-stat-label">Active Products</span>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="inv-stat-card inv-stat-lowstock">
            <div class="inv-stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="inv-stat-body">
                <span class="inv-stat-number"><?= $lowStockCountInv ?></span>
                <span class="inv-stat-label">Low Stock</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="inv-stat-card inv-stat-outstock">
            <div class="inv-stat-icon"><i class="bi bi-x-circle"></i></div>
            <div class="inv-stat-body">
                <span class="inv-stat-number"><?= $outStockCountInv ?></span>
                <span class="inv-stat-label">Out of Stock</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="inv-stat-card" style="border-left-color:#EF4444;">
            <div class="inv-stat-icon" style="background:#fef2f2;color:#EF4444;"><i class="bi bi-trash"></i></div>
            <div class="inv-stat-body">
                <span class="inv-stat-number"><?= count($deadStock) ?></span>
                <span class="inv-stat-label">Dead Stock Items</span>
            </div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="rp-chart-box">
            <div class="rp-section-title"><i class="bi bi-bar-chart"></i> Inventory Value by Category</div>
            <div class="chart-container">
                <canvas id="invCategoryChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="rp-chart-box">
            <div class="rp-section-title"><i class="bi bi-lightning"></i> Fast Moving Products (Top 10)</div>
            <div class="chart-container">
                <canvas id="fastMovingChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Fast Moving Table -->
<div class="rp-section-title"><i class="bi bi-lightning text-warning"></i> Fast Moving Products</div>
<div class="rp-table-wrap mb-4">
    <table class="table table-hover">
        <thead><tr><th>Product</th><th>Code</th><th class="text-end">Stock</th><th class="text-end">Price</th><th class="text-end">Total Sold</th><th class="text-end">Revenue</th></tr></thead>
        <tbody>
        <?php foreach ($fastMoving as $fm): ?>
            <tr>
                <td><?= htmlspecialchars($fm['name']) ?></td>
                <td><code><?= htmlspecialchars($fm['code']) ?></code></td>
                <td class="text-end"><?= (int)$fm['stock_quantity'] ?></td>
                <td class="text-end"><?= formatPrice((float)$fm['price']) ?></td>
                <td class="text-end fw-semibold"><?= (int)$fm['total_sold'] ?></td>
                <td class="text-end"><?= formatPrice((float)$fm['revenue']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Slow Moving Table -->
<div class="rp-section-title"><i class="bi bi-snail text-warning"></i> Slow Moving Products</div>
<div class="rp-table-wrap mb-4">
    <table class="table table-hover">
        <thead><tr><th>Product</th><th>Code</th><th class="text-end">Stock</th><th class="text-end">Price</th><th class="text-end">Total Sold</th><th class="text-end">Revenue</th></tr></thead>
        <tbody>
        <?php foreach ($slowMoving as $sm): ?>
            <tr>
                <td><?= htmlspecialchars($sm['name']) ?></td>
                <td><code><?= htmlspecialchars($sm['code']) ?></code></td>
                <td class="text-end"><?= (int)$sm['stock_quantity'] ?></td>
                <td class="text-end"><?= formatPrice((float)$sm['price']) ?></td>
                <td class="text-end fw-semibold"><?= (int)$sm['total_sold'] ?></td>
                <td class="text-end"><?= formatPrice((float)$sm['revenue']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Dead Stock Table -->
<div class="rp-section-title"><i class="bi bi-trash text-danger"></i> Dead Stock (In stock, never sold)</div>
<div class="rp-table-wrap mb-4">
    <table class="table table-hover">
        <thead><tr><th>Product</th><th>Code</th><th class="text-end">Stock</th><th class="text-end">Price</th><th class="text-end">Stock Value</th></tr></thead>
        <tbody>
        <?php if ($deadStock): ?>
        <?php foreach ($deadStock as $ds): ?>
            <tr>
                <td><?= htmlspecialchars($ds['name']) ?></td>
                <td><code><?= htmlspecialchars($ds['code']) ?></code></td>
                <td class="text-end"><?= (int)$ds['stock_quantity'] ?></td>
                <td class="text-end"><?= formatPrice((float)$ds['price']) ?></td>
                <td class="text-end fw-semibold text-danger"><?= formatPrice((float)$ds['stock_value']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No dead stock items found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Inventory by Category Table -->
<div class="rp-section-title"><i class="bi bi-tags"></i> Inventory by Category</div>
<div class="rp-table-wrap">
    <table class="table table-hover">
        <thead><tr><th>Category</th><th class="text-end">Products</th><th class="text-end">Total Stock</th><th class="text-end">Stock Value</th></tr></thead>
        <tbody>
        <?php foreach ($invByCat as $ic): ?>
            <tr>
                <td class="fw-semibold"><?= htmlspecialchars($ic['name']) ?></td>
                <td class="text-end"><?= number_format((int)$ic['product_count']) ?></td>
                <td class="text-end"><?= number_format((int)$ic['total_stock']) ?></td>
                <td class="text-end fw-semibold"><?= formatPrice((float)$ic['stock_value']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('invCategoryChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($invCatLabels) ?>,
            datasets: [{
                label: 'Stock Value (RWF)',
                data: <?= json_encode($invCatValues) ?>,
                backgroundColor: 'rgba(0,31,91,0.75)',
                borderColor: '#001F5B',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, title: { display: true, text: 'Stock Value (RWF)' } } }
        }
    });

    new Chart(document.getElementById('fastMovingChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($fastNames) ?>,
            datasets: [{
                label: 'Units Sold',
                data: <?= json_encode($fastSold) ?>,
                backgroundColor: 'rgba(11,107,47,0.75)',
                borderColor: '#0B6B2F',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { stepSize: 1 }, title: { display: true, text: 'Units Sold' } } }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
