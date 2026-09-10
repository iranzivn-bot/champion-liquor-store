<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$pageTitle = 'Product Analytics';

$db = getDbConnection();
$ajax = (int) ($_GET['ajax'] ?? 0);

// Top 10 Best Selling
$bestSellers = $db->query("
    SELECT p.name, p.code, p.stock_quantity, p.price,
           SUM(oi.quantity) AS sold, COALESCE(SUM(oi.subtotal),0) AS revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE o.status='delivered'
    GROUP BY p.id, p.name, p.code, p.stock_quantity, p.price
    ORDER BY sold DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Top 10 Most Wishlisted
$mostWishlisted = $db->query("
    SELECT p.id, p.name, p.code, p.price, COUNT(w.id) AS wishlist_count
    FROM wishlist w
    JOIN products p ON w.product_id = p.id
    GROUP BY p.id, p.name, p.code, p.price
    ORDER BY wishlist_count DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Lowest stock
$lowestStock = $db->query("
    SELECT name, code, stock_quantity, price FROM products
    WHERE stock_quantity > 0
    ORDER BY stock_quantity ASC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Highest revenue products
$highestRevenue = $db->query("
    SELECT p.name, p.code, SUM(oi.quantity) AS sold, COALESCE(SUM(oi.subtotal),0) AS revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE o.status='delivered'
    GROUP BY p.id, p.name, p.code
    ORDER BY revenue DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

if (!$ajax) {
    require_once __DIR__ . '/../../includes/admin-header.php';
    require_once __DIR__ . '/../../includes/admin-navbar.php';
    require_once __DIR__ . '/../../includes/admin-sidebar.php';
}
?>
<style>
.chart-card { background:#fff; border-radius:12px; box-shadow:0 2px 6px rgba(0,0,0,0.05); padding:1.25rem; margin-bottom:1.5rem; }
.chart-card h6 { color:#001F5B; font-weight:700; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.3px; margin-bottom:1rem; }
.chart-container { position:relative; height:260px; width:100%; }
</style>

<div class="admin-content-wrapper">
<main class="admin-content">

<div id="analyticsRefreshWrap">

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1" style="color:#001F5B;"><i class="bi bi-box-seam me-2"></i>Product Analytics</h1>
        <p class="text-muted mb-0">Best sellers, wishlist performance, and stock analysis.</p>
    </div>
    <a href="<?= SITE_URL ?>admin/analytics/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="chart-card">
            <h6><i class="bi bi-trophy me-1"></i> Top 10 Best Selling Products</h6>
            <div class="chart-container"><canvas id="chartBestSellers"></canvas></div>
        </div>
        <div class="chart-card">
            <h6><i class="bi bi-heart me-1"></i> Top 10 Most Wishlisted</h6>
            <div class="chart-container"><canvas id="chartWishlisted"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="chart-card">
            <h6><i class="bi bi-currency-dollar me-1"></i> Top 10 Highest Revenue</h6>
            <div class="chart-container"><canvas id="chartRevenue"></canvas></div>
        </div>
        <div class="chart-card">
            <h6><i class="bi bi-exclamation-triangle me-1"></i> Lowest Stock Products</h6>
            <div class="table-responsive" style="max-height:260px;overflow-y:auto;">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead><tr><th>Product</th><th>Code</th><th class="text-end">Stock</th><th class="text-end">Price</th></tr></thead>
                    <tbody>
                        <?php foreach ($lowestStock as $ls): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($ls['name']) ?></td>
                            <td><code><?= htmlspecialchars($ls['code']) ?></code></td>
                            <td class="text-end"><span class="badge bg-warning text-dark"><?= (int)$ls['stock_quantity'] ?></span></td>
                            <td class="text-end"><?= formatPrice((float)$ls['price']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($lowestStock)===0): ?><tr><td colspan="4" class="text-center text-muted py-3">No data.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= SITE_URL ?>assets/js/analytics.js"></script>
<script>
function initAnalyticsCharts() {
    var bl = <?= json_encode(array_map(function($r){return $r['name'];}, $bestSellers)) ?>;
    var bd = <?= json_encode(array_map(function($r){return (int)$r['sold'];}, $bestSellers)) ?>;
    if (bl.length) { new Chart(document.getElementById('chartBestSellers'), { type:'bar', data:{ labels:bl, datasets:[{ label:'Sold', data:bd, backgroundColor:'rgba(0,31,91,0.7)', borderRadius:3 }] }, options:{ responsive:true, maintainAspectRatio:false, indexAxis:'y', plugins:{legend:{display:false}}, scales:{ x:{beginAtZero:true,ticks:{stepSize:1,font:{size:10}}}, y:{ticks:{font:{size:9}}} } } }); }

    var wl = <?= json_encode(array_map(function($r){return $r['name'];}, $mostWishlisted)) ?>;
    var wd = <?= json_encode(array_map(function($r){return (int)$r['wishlist_count'];}, $mostWishlisted)) ?>;
    if (wl.length) { new Chart(document.getElementById('chartWishlisted'), { type:'bar', data:{ labels:wl, datasets:[{ label:'Wishlist Count', data:wd, backgroundColor:'rgba(201,162,39,0.7)', borderColor:'#C9A227', borderRadius:3 }] }, options:{ responsive:true, maintainAspectRatio:false, indexAxis:'y', plugins:{legend:{display:false}}, scales:{ x:{beginAtZero:true,ticks:{stepSize:1,font:{size:10}}}, y:{ticks:{font:{size:9}}} } } }); }

    var rl = <?= json_encode(array_map(function($r){return $r['name'];}, $highestRevenue)) ?>;
    var rd = <?= json_encode(array_map(function($r){return (float)$r['revenue'];}, $highestRevenue)) ?>;
    if (rl.length) { new Chart(document.getElementById('chartRevenue'), { type:'bar', data:{ labels:rl, datasets:[{ label:'Revenue', data:rd, backgroundColor:'rgba(11,107,47,0.7)', borderRadius:3 }] }, options:{ responsive:true, maintainAspectRatio:false, indexAxis:'y', plugins:{legend:{display:false}}, scales:{ x:{beginAtZero:true,ticks:{font:{size:10}}}, y:{ticks:{font:{size:9}}} } } }); }
}
document.addEventListener('DOMContentLoaded', initAnalyticsCharts);
</script>

<?php if (!$ajax) require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
