<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$pageTitle = 'Inventory Analytics';

$db = getDbConnection();
$ajax = (int) ($_GET['ajax'] ?? 0);

// KPI
$totalProducts = (int) $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$retailValue = (float) $db->query("SELECT COALESCE(SUM(price * stock_quantity),0) FROM products")->fetchColumn();
$costValue   = (float) $db->query("SELECT COALESCE(SUM(COALESCE(cost_price, price*0.6) * stock_quantity),0) FROM products")->fetchColumn();
$lowStock    = (int) $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity>0 AND stock_quantity<=5")->fetchColumn();
$outStock    = (int) $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity=0")->fetchColumn();

// Stock in/out last 30 days
$stockMovements = $db->query("
    SELECT DATE(created_at) AS lbl,
           COALESCE(SUM(CASE WHEN movement_type IN ('stock_in','adjustment') AND quantity>0 THEN quantity ELSE 0 END),0) AS stock_in,
           COALESCE(SUM(CASE WHEN movement_type IN ('stock_out','adjustment') AND quantity<0 THEN ABS(quantity) WHEN movement_type='order' THEN ABS(quantity) ELSE 0 END),0) AS stock_out
    FROM inventory_movements
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at) ORDER BY lbl ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Recent movements
$recentMovements = $db->query("
    SELECT im.*, p.name AS product_name, p.code AS product_code
    FROM inventory_movements im
    JOIN products p ON im.product_id = p.id
    ORDER BY im.created_at DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

if (!$ajax) {
    require_once __DIR__ . '/../../includes/admin-header.php';
    require_once __DIR__ . '/../../includes/admin-navbar.php';
    require_once __DIR__ . '/../../includes/admin-sidebar.php';
}
?>
<style>
.askpi { background:#fff; border-radius:12px; padding:1.1rem 1.25rem; box-shadow:0 2px 6px rgba(0,0,0,0.05); height:100%; border-left:4px solid #001F5B; }
.askpi .num { font-size:1.4rem; font-weight:700; color:#001F5B; line-height:1.2; }
.askpi .lbl { font-size:0.75rem; color:#6B7280; text-transform:uppercase; letter-spacing:0.3px; }
.askpi.gold { border-left-color:#C9A227; } .askpi.gold .num { color:#C9A227; }
.askpi.green { border-left-color:#0B6B2F; } .askpi.green .num { color:#0B6B2F; }
.askpi.red { border-left-color:#dc3545; } .askpi.red .num { color:#dc3545; }
.chart-card { background:#fff; border-radius:12px; box-shadow:0 2px 6px rgba(0,0,0,0.05); padding:1.25rem; margin-bottom:1.5rem; }
.chart-card h6 { color:#001F5B; font-weight:700; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.3px; margin-bottom:1rem; }
.chart-container { position:relative; height:260px; width:100%; }
</style>

<div class="admin-content-wrapper">
<main class="admin-content">

<div id="analyticsRefreshWrap">

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1" style="color:#001F5B;"><i class="bi bi-boxes me-2"></i>Inventory Analytics</h1>
        <p class="text-muted mb-0">Stock value, movement trends, and alerts.</p>
    </div>
    <a href="<?= SITE_URL ?>admin/analytics/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="askpi"><div class="num"><?= number_format($totalProducts) ?></div><div class="lbl">Total Products</div></div></div>
    <div class="col-6 col-md-3"><div class="askpi gold"><div class="num"><?= formatPrice($retailValue) ?></div><div class="lbl">Retail Value</div></div></div>
    <div class="col-6 col-md-3"><div class="askpi green"><div class="num"><?= formatPrice($costValue) ?></div><div class="lbl">Cost Value</div></div></div>
    <div class="col-6 col-md-3"><div class="askpi red"><div class="num"><?= number_format($lowStock + $outStock) ?></div><div class="lbl">Low / Out of Stock</div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="chart-card">
            <h6><i class="bi bi-arrow-up-down me-1"></i> Stock In vs Out (30 Days)</h6>
            <div class="chart-container"><canvas id="chartStockMovements"></canvas></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="chart-card">
            <h6><i class="bi bi-pie-chart me-1"></i> Stock Health</h6>
            <div class="chart-container"><canvas id="chartStockHealth"></canvas></div>
        </div>
    </div>
</div>

<div class="chart-card">
    <h6><i class="bi bi-activity me-1"></i> Recent Stock Movements</h6>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead><tr><th>Date</th><th>Product</th><th>Type</th><th class="text-end">Qty</th><th class="text-center">Stock Before</th><th class="text-center">Stock After</th></tr></thead>
            <tbody>
                <?php foreach ($recentMovements as $rm): ?>
                <tr>
                    <td class="small text-nowrap"><?= date('M j, Y h:i A', strtotime($rm['created_at'])) ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($rm['product_name']) ?> <small class="text-muted">(<?= htmlspecialchars($rm['product_code']) ?>)</small></td>
                    <td><span class="badge bg-<?= $rm['movement_type']==='stock_in'||($rm['movement_type']==='adjustment'&&$rm['quantity']>0)?'success':'danger' ?>"><?= htmlspecialchars($rm['movement_type']) ?></span></td>
                    <td class="text-end fw-semibold"><?= (int)$rm['quantity'] ?></td>
                    <td class="text-center"><?= (int)$rm['stock_before'] ?></td>
                    <td class="text-center"><?= (int)$rm['stock_after'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($recentMovements)===0): ?><tr><td colspan="6" class="text-center text-muted py-3">No movements yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= SITE_URL ?>assets/js/analytics.js"></script>
<script>
function initAnalyticsCharts() {
    var sl = <?= json_encode(array_column($stockMovements,'lbl')) ?>;
    var si = <?= json_encode(array_map(function($r){return (float)$r['stock_in'];}, $stockMovements)) ?>;
    var so = <?= json_encode(array_map(function($r){return (float)$r['stock_out'];}, $stockMovements)) ?>;
    if (sl.length) { new Chart(document.getElementById('chartStockMovements'), { type:'bar', data:{ labels:sl, datasets:[ { label:'Stock In', data:si, backgroundColor:'rgba(11,107,47,0.7)', borderRadius:3 }, { label:'Stock Out', data:so, backgroundColor:'rgba(220,53,69,0.7)', borderRadius:3 } ] }, options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'top', labels:{ boxWidth:12, font:{size:10} } } }, scales:{ y:{beginAtZero:true,ticks:{font:{size:10}}}, x:{ticks:{font:{size:9},maxRotation:45}} } } }); }

    var healthy = <?= (int)$totalProducts - $lowStock - $outStock ?>;
    if (healthy >= 0) { new Chart(document.getElementById('chartStockHealth'), { type:'doughnut', data:{ labels:['In Stock','Low Stock','Out of Stock'], datasets:[{ data:[healthy,<?= $lowStock ?>,<?= $outStock ?>], backgroundColor:['#0B6B2F','#F59E0B','#EF4444'], borderWidth:1 }] }, options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12, padding:8, font:{size:10} } } } } }); }
}
document.addEventListener('DOMContentLoaded', initAnalyticsCharts);
</script>

<?php if (!$ajax) require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
