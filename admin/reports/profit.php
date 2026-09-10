<?php
/**
 * Profit Report
 *
 * Selling Price, Cost Price, Profit, Margin %, Top/Bottom products
 *
 * Champion Liquor Store Ltd — Enterprise Reporting
 *
 * NOTE: Requires cost_price column on products table.
 * Run database/migration_report_upgrade.sql if missing.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$pageTitle = 'Profit Report';
require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= SITE_URL ?>assets/css/reports/reports.css">
<style>
.chart-container { position:relative; height:300px; width:100%; }
.profit-positive { color:#0B6B2F; }
.profit-negative { color:#dc3545; }
</style>

<div class="admin-content-wrapper">
<main class="admin-content">

<?php
$db = getDbConnection();

$fromDate = $_GET['from'] ?? '';
$toDate   = $_GET['to'] ?? '';

$whereDate = '';
$params = [];
if ($fromDate !== '') {
    $whereDate .= " AND DATE(o.created_at) >= ?";
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $whereDate .= " AND DATE(o.created_at) <= ?";
    $params[] = $toDate;
}

// ─── Overall Profit Metrics ──────────────────────────────────────────
$profitMetrics = $db->prepare("
    SELECT
        COALESCE(SUM(oi.subtotal), 0) AS total_sales,
        COALESCE(SUM(oi.quantity * COALESCE(p.cost_price, 0)), 0) AS total_cost,
        COUNT(DISTINCT oi.order_id) AS order_count,
        COUNT(DISTINCT oi.product_id) AS product_count
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status = 'delivered' $whereDate
");
$profitMetrics->execute($params);
$pm = $profitMetrics->fetch(PDO::FETCH_ASSOC);

$totalSales   = (float)$pm['total_sales'];
$totalCost    = (float)$pm['total_cost'];
$totalProfit  = $totalSales - $totalCost;
$marginPct    = $totalSales > 0 ? round($totalProfit / $totalSales * 100, 2) : 0;

// ─── Top Profitable Products ─────────────────────────────────────────
$topProfit = $db->prepare("
    SELECT p.name, p.code, p.price, p.cost_price,
           SUM(oi.quantity) AS qty_sold,
           SUM(oi.subtotal) AS sales,
           SUM(oi.quantity * COALESCE(p.cost_price, 0)) AS cost,
           SUM(oi.subtotal - (oi.quantity * COALESCE(p.cost_price, 0))) AS profit,
           CASE WHEN SUM(oi.subtotal) > 0
             THEN ROUND((SUM(oi.subtotal - (oi.quantity * COALESCE(p.cost_price, 0))) / SUM(oi.subtotal)) * 100, 2)
             ELSE 0 END AS margin
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status = 'delivered' $whereDate
    GROUP BY p.id, p.name, p.code, p.price, p.cost_price
    ORDER BY profit DESC
    LIMIT 20
");
$topProfit->execute($params);
$topProfitProducts = $topProfit->fetchAll(PDO::FETCH_ASSOC);

// ─── Least Profitable Products ───────────────────────────────────────
$bottomProfit = $db->prepare("
    SELECT p.name, p.code, p.price, p.cost_price,
           SUM(oi.quantity) AS qty_sold,
           SUM(oi.subtotal) AS sales,
           SUM(oi.quantity * COALESCE(p.cost_price, 0)) AS cost,
           SUM(oi.subtotal - (oi.quantity * COALESCE(p.cost_price, 0))) AS profit,
           CASE WHEN SUM(oi.subtotal) > 0
             THEN ROUND((SUM(oi.subtotal - (oi.quantity * COALESCE(p.cost_price, 0))) / SUM(oi.subtotal)) * 100, 2)
             ELSE 0 END AS margin
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status = 'delivered' $whereDate
    GROUP BY p.id, p.name, p.code, p.price, p.cost_price
    HAVING profit >= 0
    ORDER BY profit ASC
    LIMIT 20
");
$bottomProfit->execute($params);
$bottomProfitProducts = $bottomProfit->fetchAll(PDO::FETCH_ASSOC);

// ─── Profit by Category ──────────────────────────────────────────────
$profitByCat = $db->prepare("
    SELECT c.name,
           SUM(oi.subtotal) AS sales,
           SUM(oi.quantity * COALESCE(p.cost_price, 0)) AS cost,
           SUM(oi.subtotal - (oi.quantity * COALESCE(p.cost_price, 0))) AS profit,
           CASE WHEN SUM(oi.subtotal) > 0
             THEN ROUND((SUM(oi.subtotal - (oi.quantity * COALESCE(p.cost_price, 0))) / SUM(oi.subtotal)) * 100, 2)
             ELSE 0 END AS margin
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status = 'delivered' $whereDate
    GROUP BY c.id, c.name
    ORDER BY profit DESC
");
$profitByCat->execute($params);
$profitCategories = $profitByCat->fetchAll(PDO::FETCH_ASSOC);

// ─── Chart Data ──────────────────────────────────────────────────────
$topProfitNames = []; $topProfitValues = []; $topProfitMargins = [];
foreach (array_slice($topProfitProducts, 0, 10) as $tp) {
    $topProfitNames[] = $tp['name'];
    $topProfitValues[] = (float)$tp['profit'];
    $topProfitMargins[] = (float)$tp['margin'];
}

$catProfitLabels = []; $catProfitData = [];
foreach ($profitCategories as $pc) {
    $catProfitLabels[] = $pc['name'];
    $catProfitData[] = (float)$pc['profit'];
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="fs-3 fw-bold" style="color:var(--admin-primary);">Profit Report</h1>
        <p class="text-muted">Gross profit, margin analysis, and product profitability.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= SITE_URL ?>admin/reports/export.php?type=profit&format=csv<?= $fromDate ? '&from='.$fromDate : '' ?><?= $toDate ? '&to='.$toDate : '' ?>" class="btn btn-outline-success btn-sm fw-semibold"><i class="bi bi-filetype-csv"></i> CSV</a>
        <a href="<?= SITE_URL ?>admin/reports/export.php?type=profit&format=excel<?= $fromDate ? '&from='.$fromDate : '' ?><?= $toDate ? '&to='.$toDate : '' ?>" class="btn btn-outline-success btn-sm fw-semibold"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        <a href="javascript:window.print()" class="btn btn-outline-secondary btn-sm fw-semibold"><i class="bi bi-printer"></i> Print</a>
    </div>
</div>

<!-- Filters -->
<form method="get" class="rp-filters mb-4">
    <div>
        <label class="form-label">From</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($fromDate) ?>" style="width:150px;">
    </div>
    <div>
        <label class="form-label">To</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($toDate) ?>" style="width:150px;">
    </div>
    <div style="display:flex;gap:4px;">
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Apply</button>
        <a href="<?= SITE_URL ?>admin/reports/profit.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
    </div>
</form>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="rp-stat-card rp-stat-revenue">
            <div class="rp-stat-icon"><i class="bi bi-cart"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= formatPrice($totalSales) ?></span>
                <span class="rp-stat-label">Total Sales</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rp-stat-card" style="border-left-color:#dc3545;">
            <div class="rp-stat-icon" style="background:#fde8ea;color:#dc3545;"><i class="bi bi-cash"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= formatPrice($totalCost) ?></span>
                <span class="rp-stat-label">Total Cost</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rp-stat-card" style="border-left-color:#0B6B2F;">
            <div class="rp-stat-icon" style="background:#e8f5e9;color:#0B6B2F;"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number profit-<?= $totalProfit >= 0 ? 'positive' : 'negative' ?>"><?= formatPrice($totalProfit) ?></span>
                <span class="rp-stat-label">Gross Profit</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rp-stat-card" style="border-left-color:#8B5CF6;">
            <div class="rp-stat-icon" style="background:#f3eeff;color:#8B5CF6;"><i class="bi bi-percent"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= $marginPct ?>%</span>
                <span class="rp-stat-label">Profit Margin</span>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="rp-chart-box">
            <div class="rp-section-title"><i class="bi bi-trophy"></i> Top 10 Profitable Products</div>
            <div class="chart-container">
                <canvas id="topProfitChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="rp-chart-box">
            <div class="rp-section-title"><i class="bi bi-pie-chart"></i> Profit by Category</div>
            <div class="chart-container">
                <canvas id="profitCategoryChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Top Profitable Products Table -->
<div class="rp-section-title"><i class="bi bi-trophy"></i> Top Profitable Products</div>
<div class="rp-table-wrap mb-4">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Product</th>
                <th>Code</th>
                <th class="text-end">Selling Price</th>
                <th class="text-end">Cost Price</th>
                <th class="text-end">Qty Sold</th>
                <th class="text-end">Sales</th>
                <th class="text-end">Cost</th>
                <th class="text-end">Profit</th>
                <th class="text-end">Margin</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($topProfitProducts as $tp): ?>
            <?php $profit = (float)$tp['profit']; ?>
            <tr>
                <td><?= htmlspecialchars($tp['name']) ?></td>
                <td><code><?= htmlspecialchars($tp['code']) ?></code></td>
                <td class="text-end"><?= formatPrice((float)$tp['price']) ?></td>
                <td class="text-end"><?= formatPrice((float)$tp['cost_price']) ?></td>
                <td class="text-end"><?= (int)$tp['qty_sold'] ?></td>
                <td class="text-end"><?= formatPrice((float)$tp['sales']) ?></td>
                <td class="text-end"><?= formatPrice((float)$tp['cost']) ?></td>
                <td class="text-end fw-semibold profit-<?= $profit >= 0 ? 'positive' : 'negative' ?>"><?= formatPrice($profit) ?></td>
                <td class="text-end"><span class="badge bg-<?= (float)$tp['margin'] >= 30 ? 'success' : ((float)$tp['margin'] >= 10 ? 'warning text-dark' : 'danger') ?>"><?= (float)$tp['margin'] ?>%</span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Least Profitable Products Table -->
<div class="rp-section-title"><i class="bi bi-arrow-down-circle"></i> Least Profitable Products</div>
<div class="rp-table-wrap mb-4">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Product</th>
                <th>Code</th>
                <th class="text-end">Selling Price</th>
                <th class="text-end">Cost Price</th>
                <th class="text-end">Qty Sold</th>
                <th class="text-end">Sales</th>
                <th class="text-end">Profit</th>
                <th class="text-end">Margin</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($bottomProfitProducts as $bp): ?>
            <?php $profit = (float)$bp['profit']; ?>
            <tr>
                <td><?= htmlspecialchars($bp['name']) ?></td>
                <td><code><?= htmlspecialchars($bp['code']) ?></code></td>
                <td class="text-end"><?= formatPrice((float)$bp['price']) ?></td>
                <td class="text-end"><?= formatPrice((float)$bp['cost_price']) ?></td>
                <td class="text-end"><?= (int)$bp['qty_sold'] ?></td>
                <td class="text-end"><?= formatPrice((float)$bp['sales']) ?></td>
                <td class="text-end fw-semibold profit-<?= $profit >= 0 ? 'positive' : 'negative' ?>"><?= formatPrice($profit) ?></td>
                <td class="text-end"><span class="badge bg-<?= (float)$bp['margin'] >= 30 ? 'success' : ((float)$bp['margin'] >= 10 ? 'warning text-dark' : 'danger') ?>"><?= (float)$bp['margin'] ?>%</span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Profit by Category Table -->
<div class="rp-section-title"><i class="bi bi-tags"></i> Profit by Category</div>
<div class="rp-table-wrap">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Category</th>
                <th class="text-end">Sales</th>
                <th class="text-end">Cost</th>
                <th class="text-end">Profit</th>
                <th class="text-end">Margin</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($profitCategories as $pc): ?>
            <?php $p = (float)$pc['profit']; ?>
            <tr>
                <td class="fw-semibold"><?= htmlspecialchars($pc['name']) ?></td>
                <td class="text-end"><?= formatPrice((float)$pc['sales']) ?></td>
                <td class="text-end"><?= formatPrice((float)$pc['cost']) ?></td>
                <td class="text-end fw-semibold profit-<?= $p >= 0 ? 'positive' : 'negative' ?>"><?= formatPrice($p) ?></td>
                <td class="text-end"><span class="badge bg-<?= (float)$pc['margin'] >= 30 ? 'success' : 'warning text-dark' ?>"><?= (float)$pc['margin'] ?>%</span></td>
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
    new Chart(document.getElementById('topProfitChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($topProfitNames) ?>,
            datasets: [{
                label: 'Profit (RWF)',
                data: <?= json_encode($topProfitValues) ?>,
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
            scales: { x: { beginAtZero: true, title: { display: true, text: 'Profit (RWF)' } } }
        }
    });

    new Chart(document.getElementById('profitCategoryChart'), {
        type: 'pie',
        data: {
            labels: <?= json_encode($catProfitLabels) ?>,
            datasets: [{
                data: <?= json_encode($catProfitData) ?>,
                backgroundColor: ['#001F5B','#C9A227','#0B6B2F','#8B5CF6','#F59E0B','#EF4444','#3B82F6','#EC4899'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } } }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
