<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$pageTitle = 'Customer Analytics';

$db = getDbConnection();
$ajax = (int) ($_GET['ajax'] ?? 0);

// KPI
$totalCustomers = (int) $db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$newThisMonth   = (int) $db->query("SELECT COUNT(*) FROM users WHERE role='customer' AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())")->fetchColumn();
$repeatCustomers = (int) $db->query("SELECT COUNT(*) FROM (SELECT user_id FROM orders WHERE status='delivered' GROUP BY user_id HAVING COUNT(*) > 1) AS sub")->fetchColumn();
$avgSpend = (float) $db->query("SELECT COALESCE(AVG(grand_total),0) FROM orders WHERE status='delivered'")->fetchColumn();

// Customer growth (monthly, last 12)
$growth = $db->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS lbl, COUNT(*) AS cnt
    FROM users WHERE role='customer' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY lbl ORDER BY lbl ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Top 10 customers
$topCustomers = $db->query("
    SELECT u.id, u.full_name, u.email, u.created_at,
           COUNT(o.id) AS orders, COALESCE(SUM(o.grand_total),0) AS spent
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id AND o.status='delivered'
    WHERE u.role='customer'
    GROUP BY u.id, u.full_name, u.email, u.created_at
    ORDER BY spent DESC LIMIT 10
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
.askpi.purple { border-left-color:#8B5CF6; } .askpi.purple .num { color:#8B5CF6; }
.chart-card { background:#fff; border-radius:12px; box-shadow:0 2px 6px rgba(0,0,0,0.05); padding:1.25rem; margin-bottom:1.5rem; }
.chart-card h6 { color:#001F5B; font-weight:700; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.3px; margin-bottom:1rem; }
.chart-container { position:relative; height:280px; width:100%; }
</style>

<div class="admin-content-wrapper">
<main class="admin-content">

<div id="analyticsRefreshWrap">

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1" style="color:#001F5B;"><i class="bi bi-people me-2"></i>Customer Analytics</h1>
        <p class="text-muted mb-0">Customer behaviour and growth metrics.</p>
    </div>
    <a href="<?= SITE_URL ?>admin/analytics/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="askpi"><div class="num"><?= number_format($totalCustomers) ?></div><div class="lbl">Total Customers</div></div></div>
    <div class="col-6 col-md-3"><div class="askpi gold"><div class="num"><?= number_format($newThisMonth) ?></div><div class="lbl">New This Month</div></div></div>
    <div class="col-6 col-md-3"><div class="askpi green"><div class="num"><?= number_format($repeatCustomers) ?></div><div class="lbl">Repeat Customers</div></div></div>
    <div class="col-6 col-md-3"><div class="askpi purple"><div class="num"><?= formatPrice($avgSpend) ?></div><div class="lbl">Avg Order Value</div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="chart-card">
            <h6><i class="bi bi-graph-up me-1"></i> Customer Growth (12 Months)</h6>
            <div class="chart-container"><canvas id="chartGrowth"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="chart-card">
            <h6><i class="bi bi-trophy me-1"></i> Top 10 Customers by Spending</h6>
            <div class="chart-container"><canvas id="chartTopCustomers"></canvas></div>
        </div>
    </div>
</div>

<div class="chart-card">
    <h6><i class="bi bi-table me-1"></i> Top Customers Detail</h6>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead><tr><th>#</th><th>Customer</th><th>Email</th><th class="text-center">Orders</th><th class="text-end">Total Spent</th><th>Joined</th></tr></thead>
            <tbody>
                <?php $i=0; foreach ($topCustomers as $tc): $i++; ?>
                <tr>
                    <td><?= $i ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($tc['full_name'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($tc['email']) ?></td>
                    <td class="text-center"><?= (int)$tc['orders'] ?></td>
                    <td class="text-end fw-semibold" style="color:#0B6B2F;"><?= formatPrice((float)$tc['spent']) ?></td>
                    <td class="text-muted small"><?= date('M j, Y', strtotime($tc['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($topCustomers)===0): ?><tr><td colspan="6" class="text-center text-muted py-3">No customer data.</td></tr><?php endif; ?>
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
    var gl = <?= json_encode(array_column($growth,'lbl')) ?>;
    var gc = <?= json_encode(array_map(function($r){return (int)$r['cnt'];}, $growth)) ?>;
    if (gl.length) { new Chart(document.getElementById('chartGrowth'), { type:'line', data:{ labels:gl, datasets:[{ label:'New Customers', data:gc, borderColor:'#001F5B', backgroundColor:'rgba(0,31,91,0.1)', fill:true, tension:0.3 }] }, options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true,ticks:{stepSize:1,font:{size:10}}}, x:{ticks:{font:{size:9}}} } } }); }

    var tl = <?= json_encode(array_map(function($r){return $r['full_name'] ?: '—';}, $topCustomers)) ?>;
    var td = <?= json_encode(array_map(function($r){return (float)$r['spent'];}, $topCustomers)) ?>;
    if (tl.length) { new Chart(document.getElementById('chartTopCustomers'), { type:'bar', data:{ labels:tl, datasets:[{ label:'Total Spent', data:td, backgroundColor:'rgba(201,162,39,0.7)', borderColor:'#C9A227', borderWidth:1, borderRadius:3 }] }, options:{ responsive:true, maintainAspectRatio:false, indexAxis:'y', plugins:{legend:{display:false}}, scales:{ x:{beginAtZero:true,ticks:{font:{size:9}}}, y:{ticks:{font:{size:9}}} } } }); }
}
document.addEventListener('DOMContentLoaded', initAnalyticsCharts);
</script>

<?php if (!$ajax) require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
