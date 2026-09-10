<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$pageTitle = 'Products Report';

$pdo = getDbConnection();

$from     = trim($_GET['from'] ?? '');
$to       = trim($_GET['to'] ?? '');
$catSlug  = trim($_GET['category'] ?? '');
$tab      = trim($_GET['tab'] ?? 'bestsellers');

$orderWhere = '';
$orderParams = [];
if ($from !== '') {
    $orderWhere .= ' AND DATE(o.created_at) >= :from_date';
    $orderParams[':from_date'] = $from;
}
if ($to !== '') {
    $orderWhere .= ' AND DATE(o.created_at) <= :to_date';
    $orderParams[':to_date'] = $to;
}

$catWhere = '';
$catParams = [];
if ($catSlug !== '') {
    $catWhere = ' AND c.slug = :cat_slug';
    $catParams[':cat_slug'] = $catSlug;
}

$bestSellers = $pdo->prepare("
    SELECT p.name, p.code, p.stock_quantity, c.name AS category_name,
           SUM(oi.quantity) AS sold_qty, COALESCE(SUM(oi.subtotal), 0) AS revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE o.status = 'delivered' {$orderWhere} {$catWhere}
    GROUP BY p.id, p.name, p.code, p.stock_quantity, c.name
    ORDER BY sold_qty DESC
");
$bestSellers->execute(array_merge($orderParams, $catParams));
$bestSellers = $bestSellers->fetchAll();

$lowStock = $pdo->query("
    SELECT p.name, p.code, p.stock_quantity, p.price, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.stock_quantity > 0 AND p.stock_quantity < 5
    ORDER BY p.stock_quantity ASC
")->fetchAll();

$outOfStock = $pdo->query("
    SELECT p.name, p.code, p.stock_quantity, p.price, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.stock_quantity = 0
    ORDER BY p.name ASC
")->fetchAll();

$categories = $pdo->query('SELECT slug, name FROM categories ORDER BY name ASC')->fetchAll();

$totalProducts = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$activeProducts = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
$totalStock = (int) $pdo->query('SELECT COALESCE(SUM(stock_quantity), 0) FROM products')->fetchColumn();
$avgPrice = (float) $pdo->query("SELECT COALESCE(AVG(price), 0) FROM products WHERE status = 'active'")->fetchColumn();

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= SITE_URL ?>assets/css/reports/reports.css">

<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="rp-page-header">
            <div>
                <h1>Products Report</h1>
                <p class="text-muted mb-0">Product performance and inventory insights.</p>
            </div>
            <div class="rp-export-bar">
                <a href="<?= SITE_URL ?>admin/reports/export.php?type=products&tab=<?= $tab ?>&format=csv" class="btn btn-sm btn-success fw-semibold">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV
                </a>
                <a href="javascript:window.print()" class="btn btn-sm btn-outline-secondary fw-semibold">
                    <i class="bi bi-printer me-1"></i> Print
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="rp-stat-card rp-stat-products">
                    <div class="rp-stat-icon"><i class="bi bi-box-seam"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $totalProducts ?></span>
                        <span class="rp-stat-label">Total Products</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rp-stat-card rp-stat-delivered">
                    <div class="rp-stat-icon"><i class="bi bi-check-circle"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $activeProducts ?></span>
                        <span class="rp-stat-label">Active Products</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rp-stat-card rp-stat-orders">
                    <div class="rp-stat-icon"><i class="bi bi-boxes"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $totalStock ?></span>
                        <span class="rp-stat-label">Total Stock</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rp-stat-card rp-stat-customers">
                    <div class="rp-stat-icon"><i class="bi bi-cash"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= formatPrice($avgPrice) ?></span>
                        <span class="rp-stat-label">Avg Price</span>
                    </div>
                </div>
            </div>
        </div>

        <form method="GET" class="rp-filters">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <div>
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
            </div>
            <div>
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
            </div>
            <div>
                <label class="form-label">Category</label>
                <select name="category" class="form-select" style="min-width: 150px;">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= htmlspecialchars($c['slug']) ?>" <?= $catSlug === $c['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn fw-semibold px-3" style="background: #001F5B; color: #fff;">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="<?= SITE_URL ?>admin/reports/products.php" class="btn btn-outline-secondary fw-semibold px-3">
                    <i class="bi bi-x-circle me-1"></i> Clear
                </a>
            </div>
        </form>

        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link fw-semibold <?= $tab === 'bestsellers' ? 'active' : '' ?>" style="<?= $tab === 'bestsellers' ? 'color: #001F5B; border-color: #001F5B;' : 'color: #6B7280;' ?>" href="?tab=bestsellers<?= $from ? '&from=' . urlencode($from) : '' ?><?= $to ? '&to=' . urlencode($to) : '' ?><?= $catSlug ? '&category=' . urlencode($catSlug) : '' ?>">
                    <i class="bi bi-trophy me-1"></i> Best Sellers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link fw-semibold <?= $tab === 'lowstock' ? 'active' : '' ?>" style="<?= $tab === 'lowstock' ? 'color: #001F5B; border-color: #001F5B;' : 'color: #6B7280;' ?>" href="?tab=lowstock">
                    <i class="bi bi-exclamation-triangle me-1"></i> Low Stock (<?= count($lowStock) ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link fw-semibold <?= $tab === 'outofstock' ? 'active' : '' ?>" style="<?= $tab === 'outofstock' ? 'color: #001F5B; border-color: #001F5B;' : 'color: #6B7280;' ?>" href="?tab=outofstock">
                    <i class="bi bi-x-circle me-1"></i> Out of Stock (<?= count($outOfStock) ?>)
                </a>
            </li>
        </ul>

        <?php if ($tab === 'bestsellers'): ?>
            <div class="rp-chart-box">
                <h3 class="rp-section-title"><i class="bi bi-bar-chart"></i> Top Selling Products</h3>
                <canvas id="topSellersChart" height="180"></canvas>
            </div>
            <div class="rp-table-wrap">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th class="text-center">Stock</th>
                            <th class="text-center">Sold</th>
                            <th class="text-end">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bestSellers) === 0): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No sales data found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($bestSellers as $p): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($p['name']) ?></td>
                                    <td><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                                    <td class="text-center"><?= (int) $p['stock_quantity'] ?></td>
                                    <td class="text-center fw-semibold"><?= (int) $p['sold_qty'] ?></td>
                                    <td class="text-end fw-semibold" style="color: #0B6B2F;"><?= formatPrice((float) $p['revenue']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($tab === 'lowstock'): ?>
            <div class="rp-table-wrap">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th class="text-center">Stock</th>
                            <th class="text-end">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($lowStock) === 0): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No low stock products.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lowStock as $p): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($p['name'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-warning text-dark"><?= (int) $p['stock_quantity'] ?></span>
                                    </td>
                                    <td class="text-end"><?= formatPrice((float) $p['price']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($tab === 'outofstock'): ?>
            <div class="rp-table-wrap">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th class="text-end">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($outOfStock) === 0): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">No out of stock products.</td></tr>
                        <?php else: ?>
                            <?php foreach ($outOfStock as $p): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($p['name'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                                    <td class="text-end">
                                        <span class="badge bg-danger">Out of Stock</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'bestsellers' && count($bestSellers) > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('topSellersChart'), {
        type: 'bar',
        data: {
            labels: [<?php foreach ($bestSellers as $p) { echo "'" . str_replace("'", "\'", $p['name']) . "',"; } ?>],
            datasets: [{
                label: 'Sold Quantity',
                data: [<?php foreach ($bestSellers as $p) { echo (int) $p['sold_qty'] . ","; } ?>],
                backgroundColor: '#C9A227',
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
});
</script>
<?php endif; ?>

        <!-- ═══════════════════════════════════════════════════════════════
             EXTENSION: Worst Selling + Revenue by Category / Brand
             ═══════════════════════════════════════════════════════════════ -->
        <?php
        // --- Worst Selling Products (bottom of active products) ---
        $worstSellers = $pdo->query("
            SELECT p.name, p.code, p.stock_quantity, p.price, c.name AS category_name,
                   COALESCE(SUM(oi.quantity), 0) AS sold_qty, COALESCE(SUM(oi.subtotal), 0) AS revenue
            FROM products p
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'delivered'
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active'
            GROUP BY p.id, p.name, p.code, p.stock_quantity, p.price, c.name
            ORDER BY sold_qty ASC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);

        // --- Revenue by Category ---
        $revByCategory = $pdo->query("
            SELECT c.name, COALESCE(SUM(oi.subtotal), 0) AS revenue,
                   COUNT(DISTINCT oi.order_id) AS order_count,
                   COUNT(DISTINCT oi.product_id) AS product_count
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN products p ON oi.product_id = p.id
            RIGHT JOIN categories c ON p.category_id = c.id
            WHERE o.status = 'delivered'
            GROUP BY c.id, c.name
            ORDER BY revenue DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // --- Revenue by Brand ---
        $revByBrand = $pdo->query("
            SELECT b.name, COALESCE(SUM(oi.subtotal), 0) AS revenue,
                   COUNT(DISTINCT oi.order_id) AS order_count
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN products p ON oi.product_id = p.id
            RIGHT JOIN brands b ON p.brand_id = b.id
            WHERE o.status = 'delivered'
            GROUP BY b.id, b.name
            ORDER BY revenue DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // --- Best Performing Categories (chart data) ---
        $catChartLabels = []; $catChartData = [];
        foreach ($revByCategory as $rc) {
            $catChartLabels[] = $rc['name'];
            $catChartData[] = (float)$rc['revenue'];
        }
        $brandChartLabels = []; $brandChartData = [];
        foreach ($revByBrand as $rb) {
            $brandChartLabels[] = $rb['name'];
            $brandChartData[] = (float)$rb['revenue'];
        }
        ?>

        <!-- Worst Selling Products -->
        <div class="rp-section-title"><i class="bi bi-arrow-down-circle text-danger"></i> Worst Selling Products</div>
        <div class="rp-table-wrap mb-4">
            <table class="table table-hover">
                <thead><tr><th>Product</th><th>Code</th><th>Category</th><th class="text-end">Stock</th><th class="text-end">Sold</th><th class="text-end">Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($worstSellers as $ws): ?>
                <tr>
                    <td><?= htmlspecialchars($ws['name']) ?></td>
                    <td><code><?= htmlspecialchars($ws['code']) ?></code></td>
                    <td><?= htmlspecialchars($ws['category_name'] ?? '—') ?></td>
                    <td class="text-end"><?= (int)$ws['stock_quantity'] ?></td>
                    <td class="text-end fw-semibold text-danger"><?= (int)$ws['sold_qty'] ?></td>
                    <td class="text-end"><?= formatPrice((float)$ws['revenue']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Revenue by Category & Brand Charts -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="rp-chart-box">
                    <div class="rp-section-title"><i class="bi bi-tags"></i> Revenue by Category</div>
                    <div style="position:relative;height:300px;width:100%;">
                        <canvas id="revCategoryChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="rp-chart-box">
                    <div class="rp-section-title"><i class="bi bi-award"></i> Revenue by Brand</div>
                    <div style="position:relative;height:300px;width:100%;">
                        <canvas id="revBrandChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue by Category Table -->
        <div class="rp-section-title"><i class="bi bi-table"></i> Revenue by Category</div>
        <div class="rp-table-wrap mb-4">
            <table class="table table-hover">
                <thead><tr><th>Category</th><th class="text-end">Orders</th><th class="text-end">Products</th><th class="text-end">Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($revByCategory as $rc): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($rc['name']) ?></td>
                    <td class="text-end"><?= number_format((int)$rc['order_count']) ?></td>
                    <td class="text-end"><?= (int)$rc['product_count'] ?></td>
                    <td class="text-end fw-semibold" style="color:#0B6B2F;"><?= formatPrice((float)$rc['revenue']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Revenue by Brand Table -->
        <div class="rp-section-title"><i class="bi bi-table"></i> Revenue by Brand</div>
        <div class="rp-table-wrap">
            <table class="table table-hover">
                <thead><tr><th>Brand</th><th class="text-end">Orders</th><th class="text-end">Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($revByBrand as $rb): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($rb['name']) ?></td>
                    <td class="text-end"><?= number_format((int)$rb['order_count']) ?></td>
                    <td class="text-end fw-semibold" style="color:#0B6B2F;"><?= formatPrice((float)$rb['revenue']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (count($catChartLabels) > 0): ?>
        <script>
        new Chart(document.getElementById('revCategoryChart'), {
            type: 'pie',
            data: {
                labels: <?= json_encode($catChartLabels) ?>,
                datasets: [{
                    data: <?= json_encode($catChartData) ?>,
                    backgroundColor: ['#001F5B','#C9A227','#0B6B2F','#8B5CF6','#F59E0B','#EF4444','#3B82F6','#EC4899','#14B8A6','#F97316'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10, font: { size: 11 } } } }
            }
        });
        new Chart(document.getElementById('revBrandChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($brandChartLabels) ?>,
                datasets: [{
                    label: 'Revenue (RWF)',
                    data: <?= json_encode($brandChartData) ?>,
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
                scales: { x: { beginAtZero: true, title: { display: true, text: 'Revenue (RWF)' } } }
            }
        });
        </script>
        <?php endif; ?>

    </main>
</div>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
