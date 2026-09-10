<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$pageTitle = 'Sales Analytics';

$db = getDbConnection();
$ajax = (int) ($_GET['ajax'] ?? 0);

// Period filter
$period = trim($_GET['period'] ?? 'month');
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');

$dateWhere  = '';
if ($from !== '' && $to !== '') {
    $dateWhere = " AND DATE(created_at) >= :from AND DATE(created_at) <= :to";
} elseif ($period === 'today') {
    $dateWhere = " AND DATE(created_at) = CURDATE()";
} elseif ($period === 'week') {
    $dateWhere = " AND YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1)";
} elseif ($period === 'year') {
    $dateWhere = " AND YEAR(created_at) = YEAR(CURDATE())";
} else {
    $dateWhere = " AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
}
$params = [];

// KPI data — use direct query when no params are needed
if ($from !== '' && $to !== '') {
    $revStmt = $db->prepare("SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE status!='cancelled' AND DATE(created_at) >= :from AND DATE(created_at) <= :to");
    $revStmt->execute([':from' => $from, ':to' => $to]);
    $kpi['revenue'] = (float) $revStmt->fetchColumn();

    $ordStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) >= :from2 AND DATE(created_at) <= :to2");
    $ordStmt->execute([':from2' => $from, ':to2' => $to]);
    $kpi['orders'] = (int) $ordStmt->fetchColumn();
} else {
    $kpi['revenue'] = (float) $db->query("SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE status!='cancelled' $dateWhere")->fetchColumn();
    $kpi['orders']  = (int) $db->query("SELECT COUNT(*) FROM orders WHERE 1=1 $dateWhere")->fetchColumn();
}
$kpi['avg'] = $kpi['orders'] > 0 ? $kpi['revenue'] / $kpi['orders'] : 0;

// Sales by day (30 days)
$dailySales = $db->query("
    SELECT DATE(created_at) AS lbl, COALESCE(SUM(grand_total),0) AS rev, COUNT(*) AS cnt
    FROM orders WHERE status!='cancelled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at) ORDER BY lbl ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Sales by month (12 months)
$monthlySales = $db->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS lbl, COALESCE(SUM(grand_total),0) AS rev, COUNT(*) AS cnt
    FROM orders WHERE status!='cancelled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY lbl ORDER BY lbl ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Sales by payment method
$byPayment = $db->query("
    SELECT payment_method, COALESCE(SUM(grand_total),0) AS rev, COUNT(*) AS cnt
    FROM orders WHERE status!='cancelled'
    GROUP BY payment_method ORDER BY rev DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Sales by status
$byStatus = $db->query("
    SELECT status, COUNT(*) AS cnt, COALESCE(SUM(grand_total),0) AS rev
    FROM orders GROUP BY status ORDER BY cnt DESC
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
.chart-card { background:#fff; border-radius:12px; box-shadow:0 2px 6px rgba(0,0,0,0.05); padding:1.25rem; margin-bottom:1.5rem; }
.chart-card h6 { color:#001F5B; font-weight:700; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.3px; margin-bottom:1rem; }
.chart-container { position:relative; height:250px; width:100%; }
.filter-bar { background:#fff; border-radius:12px; padding:0.75rem 1.25rem; box-shadow:0 2px 6px rgba(0,0,0,0.05); margin-bottom:1.5rem; }
</style>

<div class="admin-content-wrapper">
<main class="admin-content">

<div id="analyticsRefreshWrap">

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1" style="color:#001F5B;"><i class="bi bi-graph-up me-2"></i>Sales Analytics</h1>
        <p class="text-muted mb-0">Detailed sales performance metrics.</p>
    </div>
    <a href="<?= SITE_URL ?>admin/analytics/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
</div>

<form method="GET" class="filter-bar">
    <div class="row g-2 align-items-end">
        <div class="col-auto">
            <label class="form-label mb-0 small fw-semibold">Period</label>
            <select name="period" id="periodSelect" class="form-select form-select-sm" style="min-width:130px;">
                <option value="today" <?= $period==='today'?'selected':'' ?>>Today</option>
                <option value="week"  <?= $period==='week'?'selected':'' ?>>This Week</option>
                <option value="month" <?= $period==='month'?'selected':'' ?>>This Month</option>
                <option value="year"  <?= $period==='year'?'selected':'' ?>>This Year</option>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label mb-0 small fw-semibold">From</label>
            <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($from) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label mb-0 small fw-semibold">To</label>
            <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($to) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label mb-0 small fw-semibold">&nbsp;</label>
            <button type="submit" class="btn btn-sm fw-semibold px-3" style="background:#001F5B;color:#fff;"><i class="bi bi-funnel me-1"></i> Apply</button>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="askpi"><div class="num"><?= formatPrice($kpi['revenue']) ?></div><div class="lbl">Total Revenue</div></div></div>
    <div class="col-md-4"><div class="askpi gold"><div class="num"><?= number_format($kpi['orders']) ?></div><div class="lbl">Total Orders</div></div></div>
    <div class="col-md-4"><div class="askpi green"><div class="num"><?= formatPrice($kpi['avg']) ?></div><div class="lbl">Average Order Value</div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="chart-card">
            <h6><i class="bi bi-calendar-day me-1"></i> Daily Sales (30 Days)</h6>
            <div class="chart-container"><canvas id="chartDailySales"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="chart-card">
            <h6><i class="bi bi-calendar-month me-1"></i> Monthly Sales (12 Months)</h6>
            <div class="chart-container"><canvas id="chartMonthlySales"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="chart-card">
            <h6><i class="bi bi-pie-chart me-1"></i> Revenue by Payment Method</h6>
            <div class="chart-container"><canvas id="chartPaymentMethod"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="chart-card">
            <h6><i class="bi bi-bar-chart me-1"></i> Orders by Status</h6>
            <div class="chart-container"><canvas id="chartOrderStatus"></canvas></div>
        </div>
    </div>
</div>

<div class="chart-card">
    <h6><i class="bi bi-table me-1"></i> Orders by Status Breakdown</h6>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead><tr><th>Status</th><th class="text-center">Orders</th><th class="text-end">Revenue</th></tr></thead>
            <tbody>
                <?php $totalCnt = array_sum(array_column($byStatus, 'cnt')); $totalRev = array_sum(array_column($byStatus, 'rev')); ?>
                <?php foreach ($byStatus as $bs): ?>
                <tr>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($bs['status'])) ?></span></td>
                    <td class="text-center"><?= number_format((int)$bs['cnt']) ?></td>
                    <td class="text-end fw-semibold"><?= formatPrice((float)$bs['rev']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="table-active fw-bold"><td>Total</td><td class="text-center"><?= number_format($totalCnt) ?></td><td class="text-end"><?= formatPrice($totalRev) ?></td></tr>
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
    var dl = <?= json_encode(array_column($dailySales,'lbl')) ?>;
    var dr = <?= json_encode(array_map(function($r){return (float)$r['rev'];}, $dailySales)) ?>;
    if (dl.length) { new Chart(document.getElementById('chartDailySales'), { type:'bar', data:{ labels:dl, datasets:[{ label:'Revenue', data:dr, backgroundColor:'rgba(0,31,91,0.7)', borderRadius:3 }] }, options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true,ticks:{font:{size:10}}}, x:{ticks:{font:{size:9},maxRotation:45}} } } }); }

    var ml = <?= json_encode(array_column($monthlySales,'lbl')) ?>;
    var mr = <?= json_encode(array_map(function($r){return (float)$r['rev'];}, $monthlySales)) ?>;
    if (ml.length) { new Chart(document.getElementById('chartMonthlySales'), { type:'line', data:{ labels:ml, datasets:[{ label:'Revenue', data:mr, borderColor:'#C9A227', backgroundColor:'rgba(201,162,39,0.1)', fill:true, tension:0.3 }] }, options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true,ticks:{font:{size:10}}}, x:{ticks:{font:{size:9}}} } } }); }

    var pml = <?= json_encode(array_map(function($r){return ucfirst(str_replace('_',' ',$r['payment_method']));}, $byPayment)) ?>;
    var pmd = <?= json_encode(array_map(function($r){return (float)$r['rev'];}, $byPayment)) ?>;
    if (pml.length) { new Chart(document.getElementById('chartPaymentMethod'), { type:'pie', data:{ labels:pml, datasets:[{ data:pmd, backgroundColor:['#001F5B','#C9A227','#0B6B2F','#8B5CF6'], borderWidth:1 }] }, options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12, padding:8, font:{size:10} } } } } }); }

    var osl = <?= json_encode(array_map(function($r){return ucfirst($r['status']);}, $byStatus)) ?>;
    var osc = <?= json_encode(array_map(function($r){return (int)$r['cnt'];}, $byStatus)) ?>;
    if (osl.length) { new Chart(document.getElementById('chartOrderStatus'), { type:'doughnut', data:{ labels:osl, datasets:[{ data:osc, backgroundColor:['#6B7280','#F59E0B','#3B82F6','#0B6B2F','#EF4444'], borderWidth:1 }] }, options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12, padding:8, font:{size:10} } } } } }); }
}
document.addEventListener('DOMContentLoaded', initAnalyticsCharts);
</script>

<?php if (!$ajax) require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
