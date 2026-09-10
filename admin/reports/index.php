<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$pageTitle = 'Reports Dashboard';

$pdo = getDbConnection();

$totalRevenue   = (float) $pdo->query("SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE status != 'cancelled'")->fetchColumn();
$totalOrders    = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$totalCustomers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$totalProducts  = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$pendingOrders  = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$deliveredOrders= (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered'")->fetchColumn();
$lowStockItems  = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity < 5")->fetchColumn();

$salesStmt = $pdo->query("
    SELECT DATE(created_at) AS day, COUNT(*) AS orders, SUM(grand_total) AS revenue
    FROM orders
    WHERE status != 'cancelled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");
$salesTrend = $salesStmt->fetchAll();

$topStmt = $pdo->query("
    SELECT p.id, p.name, SUM(oi.quantity) AS sold_qty, SUM(oi.subtotal) AS revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE o.status = 'delivered'
    GROUP BY p.id, p.name
    ORDER BY sold_qty DESC
    LIMIT 5
");
$topProducts = $topStmt->fetchAll();

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= SITE_URL ?>assets/css/reports/reports.css">

<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="rp-page-header">
            <div>
                <h1>Reports Dashboard</h1>
                <p class="text-muted mb-0">Business overview and key metrics at a glance.</p>
            </div>
            <div class="rp-export-bar">
                <a href="<?= SITE_URL ?>admin/reports/index.php" class="btn btn-sm fw-semibold active"
                   style="background: #001F5B; color: #fff;">
                    <i class="bi bi-speedometer2 me-1"></i> Dashboard
                </a>
                <a href="<?= SITE_URL ?>admin/reports/sales.php" class="btn btn-sm btn-outline-secondary fw-semibold">
                    <i class="bi bi-currency-exchange me-1"></i> Sales
                </a>
                <a href="<?= SITE_URL ?>admin/reports/orders.php" class="btn btn-sm btn-outline-secondary fw-semibold">
                    <i class="bi bi-bag-check me-1"></i> Orders
                </a>
                <a href="<?= SITE_URL ?>admin/reports/products.php" class="btn btn-sm btn-outline-secondary fw-semibold">
                    <i class="bi bi-box-seam me-1"></i> Products
                </a>
                <a href="<?= SITE_URL ?>admin/reports/customers.php" class="btn btn-sm btn-outline-secondary fw-semibold">
                    <i class="bi bi-people me-1"></i> Customers
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-lg-3">
                <div class="rp-stat-card rp-stat-revenue">
                    <div class="rp-stat-icon"><i class="bi bi-currency-exchange"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= formatPrice($totalRevenue) ?></span>
                        <span class="rp-stat-label">Total Revenue</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="rp-stat-card rp-stat-orders">
                    <div class="rp-stat-icon"><i class="bi bi-bag-check"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $totalOrders ?></span>
                        <span class="rp-stat-label">Total Orders</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="rp-stat-card rp-stat-customers">
                    <div class="rp-stat-icon"><i class="bi bi-people"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $totalCustomers ?></span>
                        <span class="rp-stat-label">Total Customers</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="rp-stat-card rp-stat-products">
                    <div class="rp-stat-icon"><i class="bi bi-box-seam"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $totalProducts ?></span>
                        <span class="rp-stat-label">Total Products</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="rp-stat-card rp-stat-pending">
                    <div class="rp-stat-icon"><i class="bi bi-clock"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $pendingOrders ?></span>
                        <span class="rp-stat-label">Pending Orders</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="rp-stat-card rp-stat-delivered">
                    <div class="rp-stat-icon"><i class="bi bi-check2-circle"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $deliveredOrders ?></span>
                        <span class="rp-stat-label">Delivered Orders</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="rp-stat-card rp-stat-lowstock">
                    <div class="rp-stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $lowStockItems ?></span>
                        <span class="rp-stat-label">Low Stock Items</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="rp-chart-box">
                    <h3 class="rp-section-title"><i class="bi bi-graph-up"></i> Sales Trend (Last 30 Days)</h3>
                    <canvas id="salesTrendChart" height="220"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="rp-chart-box">
                    <h3 class="rp-section-title"><i class="bi bi-trophy"></i> Top Products</h3>
                    <canvas id="topProductsChart" height="220"></canvas>
                </div>
            </div>
        </div>

        <div class="rp-chart-box">
            <h3 class="rp-section-title"><i class="bi bi-table"></i> Recent Sales (Last 30 Days)</h3>
            <div class="rp-table-wrap">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-center">Orders</th>
                            <th class="text-end">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($salesTrend) === 0): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">No sales data for the last 30 days.</td></tr>
                        <?php else: ?>
                            <?php foreach ($salesTrend as $row): ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($row['day'])) ?></td>
                                    <td class="text-center"><?= (int) $row['orders'] ?></td>
                                    <td class="text-end fw-semibold" style="color: #0B6B2F;"><?= formatPrice((float) $row['revenue']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var trendCtx = document.getElementById('salesTrendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: [<?php foreach ($salesTrend as $r) { echo "'" . date('M j', strtotime($r['day'])) . "',"; } ?>],
                datasets: [{
                    label: 'Revenue',
                    data: [<?php foreach ($salesTrend as $r) { echo number_format((float) $r['revenue'], 2, '.', '') . ","; } ?>],
                    borderColor: '#0B6B2F',
                    backgroundColor: 'rgba(11, 107, 47, 0.1)',
                    fill: true,
                    tension: 0.4,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: function(v) { return v.toLocaleString() + ' RWF'; } } }
                }
            }
        });
    }

    var topCtx = document.getElementById('topProductsChart');
    if (topCtx) {
        new Chart(topCtx, {
            type: 'bar',
            data: {
                labels: [<?php foreach ($topProducts as $r) { echo "'" . str_replace("'", "\'", $r['name']) . "',"; } ?>],
                datasets: [{
                    label: 'Sold',
                    data: [<?php foreach ($topProducts as $r) { echo (int) $r['sold_qty'] . ","; } ?>],
                    backgroundColor: '#C9A227',
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
