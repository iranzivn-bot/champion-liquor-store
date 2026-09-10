<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$pageTitle = 'Analytics Dashboard';

$db = getDbConnection();
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

// ─── Period Filter ─────────────────────────────────────────
$period = trim($_GET['period'] ?? 'month');
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');
$ajax   = (int) ($_GET['ajax'] ?? 0);

$dateWhere  = '';
$dateParams = [];

if ($from !== '' && $to !== '') {
    $dateWhere  = ' AND DATE(o.created_at) >= :from_date AND DATE(o.created_at) <= :to_date';
    $dateParams = [':from_date' => $from, ':to_date' => $to];
} elseif ($period === 'today') {
    $dateWhere  = ' AND DATE(o.created_at) = CURDATE()';
} elseif ($period === 'week') {
    $dateWhere  = ' AND YEARWEEK(o.created_at,1) = YEARWEEK(CURDATE(),1)';
} elseif ($period === 'year') {
    $dateWhere  = ' AND YEAR(o.created_at) = YEAR(CURDATE())';
} else {
    $dateWhere  = ' AND YEAR(o.created_at) = YEAR(CURDATE()) AND MONTH(o.created_at) = MONTH(CURDATE())';
}

// Helper to apply the same date filter
function applyPeriod(string $column = 'created_at'): string {
    global $period, $from, $to;
    if ($from !== '' && $to !== '') return " AND DATE($column) >= :from_date2 AND DATE($column) <= :to_date2";
    if ($period === 'today') return " AND DATE($column) = CURDATE()";
    if ($period === 'week')  return " AND YEARWEEK($column,1) = YEARWEEK(CURDATE(),1)";
    if ($period === 'year')  return " AND YEAR($column) = YEAR(CURDATE())";
    return " AND YEAR($column) = YEAR(CURDATE()) AND MONTH($column) = MONTH(CURDATE())";
}

// ─── KPI Queries ───────────────────────────────────────────

// Today's Revenue
$todayRev = (float) $db->query("
    SELECT COALESCE(SUM(grand_total),0) FROM orders
    WHERE DATE(created_at)=CURDATE() AND status!='cancelled'
")->fetchColumn();

// This Month Revenue
$monthRev = (float) $db->query("
    SELECT COALESCE(SUM(grand_total),0) FROM orders
    WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE()) AND status!='cancelled'
")->fetchColumn();

// Today's Orders
$todayOrders = (int) $db->query("
    SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()
")->fetchColumn();

// Pending Orders
$pendingOrders = (int) $db->query("
    SELECT COUNT(*) FROM orders WHERE status='pending'
")->fetchColumn();

// Delivered Orders
$deliveredOrders = (int) $db->query("
    SELECT COUNT(*) FROM orders WHERE status='delivered'
")->fetchColumn();

// Cancelled Orders
$cancelledOrders = (int) $db->query("
    SELECT COUNT(*) FROM orders WHERE status='cancelled'
")->fetchColumn();

// Total Customers
$totalCustomers = (int) $db->query("
    SELECT COUNT(*) FROM users WHERE role='customer'
")->fetchColumn();

// New Customers (30 days)
$newCustomers = (int) $db->query("
    SELECT COUNT(*) FROM users WHERE role='customer' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
")->fetchColumn();

// Low Stock
$lowStockCount = (int) $db->query("
    SELECT COUNT(*) FROM products WHERE stock_quantity>0 AND stock_quantity<=5
")->fetchColumn();

// Out of Stock
$outOfStockCount = (int) $db->query("
    SELECT COUNT(*) FROM products WHERE stock_quantity=0
")->fetchColumn();

// Most Wishlisted Count
$wishlistCount = (int) $db->query("
    SELECT COUNT(*) FROM wishlist
")->fetchColumn();

// ─── Chart Data ────────────────────────────────────────────

// 1. Revenue Last 12 Months
$revenueMonths = $db->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS label,
           COALESCE(SUM(grand_total),0) AS revenue,
           COUNT(*) AS orders
    FROM orders
    WHERE status!='cancelled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY label ORDER BY label ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 2. Daily Sales Last 30 Days
$dailySales = $db->query("
    SELECT DATE(created_at) AS label,
           COALESCE(SUM(grand_total),0) AS revenue,
           COUNT(*) AS orders
    FROM orders
    WHERE status!='cancelled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at) ORDER BY label ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 3. Weekly Sales Last 12 Weeks
$weeklySales = $db->query("
    SELECT CONCAT(YEAR(created_at),'-W',LPAD(WEEK(created_at,1),2,'0')) AS label,
           COALESCE(SUM(grand_total),0) AS revenue
    FROM orders
    WHERE status!='cancelled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
    GROUP BY YEARWEEK(created_at,1) ORDER BY MIN(created_at) ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 4. Revenue by Payment Method
$paymentMethod = $db->query("
    SELECT payment_method, COALESCE(SUM(grand_total),0) AS revenue
    FROM orders WHERE status!='cancelled'
    GROUP BY payment_method ORDER BY revenue DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ─── Widget Data ───────────────────────────────────────────

// Recent Orders (latest 5)
$recentOrders = $db->query("
    SELECT id, order_number, grand_total, status, created_at
    FROM orders ORDER BY created_at DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Top Customers (5)
$topCustomers = $db->query("
    SELECT u.id, u.full_name, u.email,
           COUNT(o.id) AS order_count,
           COALESCE(SUM(o.grand_total),0) AS total_spent
    FROM users u
    JOIN orders o ON u.id = o.user_id
    WHERE o.status='delivered'
    GROUP BY u.id, u.full_name, u.email
    ORDER BY total_spent DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Top Products (5)
$topProducts = $db->query("
    SELECT p.name, p.code, SUM(oi.quantity) AS sold_qty, COALESCE(SUM(oi.subtotal),0) AS revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE o.status='delivered'
    GROUP BY p.id, p.name, p.code
    ORDER BY sold_qty DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Recent Inventory Changes (5)
$recentInv = $db->query("
    SELECT im.id, p.name AS product_name, im.movement_type, im.quantity, im.created_at
    FROM inventory_movements im
    JOIN products p ON im.product_id = p.id
    ORDER BY im.created_at DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Recent Audit Logs (5)
$recentAudit = $db->query("
    SELECT id, user_name, action, module, description, created_at
    FROM audit_logs ORDER BY created_at DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Low Stock Alerts
$lowStockAlerts = $db->query("
    SELECT id, name, code, stock_quantity FROM products
    WHERE stock_quantity>0 AND stock_quantity<=5
    ORDER BY stock_quantity ASC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

if (!$ajax) {
    require_once __DIR__ . '/../../includes/admin-header.php';
    require_once __DIR__ . '/../../includes/admin-navbar.php';
    require_once __DIR__ . '/../../includes/admin-sidebar.php';
}
?>
<style>
.akpi { background: #fff; border-radius: 12px; padding: 1.1rem 1.25rem; box-shadow: 0 2px 6px rgba(0,0,0,0.05); height: 100%; border-left: 4px solid #001F5B; transition: transform 0.2s; }
.akpi:hover { transform: translateY(-2px); }
.akpi .num { font-size: 1.5rem; font-weight: 700; color: #001F5B; line-height: 1.2; }
.akpi .lbl { font-size: 0.75rem; color: #6B7280; text-transform: uppercase; letter-spacing: 0.3px; margin-top: 2px; }
.akpi .ico { font-size: 1.5rem; opacity: 0.2; position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); }
.akpi.gold { border-left-color: #C9A227; } .akpi.gold .num { color: #C9A227; }
.akpi.green { border-left-color: #0B6B2F; } .akpi.green .num { color: #0B6B2F; }
.akpi.red { border-left-color: #dc3545; } .akpi.red .num { color: #dc3545; }
.akpi.purple { border-left-color: #8B5CF6; } .akpi.purple .num { color: #8B5CF6; }
.chart-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); padding: 1.25rem; margin-bottom: 1.5rem; }
.chart-card h6 { color: #001F5B; font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 1rem; }
.chart-container { position: relative; height: 260px; width: 100%; }
.widget-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); padding: 1.1rem 1.25rem; margin-bottom: 1rem; }
.widget-card h6 { color: #001F5B; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 2px solid #f0f0f0; }
.widget-card .list-item { font-size: 0.85rem; padding: 0.35rem 0; border-bottom: 1px solid #f5f5f5; display: flex; justify-content: space-between; align-items: center; }
.widget-card .list-item:last-child { border-bottom: none; }
.quick-link-btn { font-size: 0.82rem; padding: 0.4rem 0.9rem; border-radius: 50px; }
.period-form { background: #fff; border-radius: 12px; padding: 0.75rem 1.25rem; box-shadow: 0 2px 6px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
</style>

<div class="admin-content-wrapper">
<main class="admin-content">

<div id="analyticsRefreshWrap">

<?php if (!$ajax): ?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="h3 mb-1" style="color:#001F5B;"><i class="bi bi-graph-up-arrow me-2"></i>Analytics Dashboard</h1>
        <p class="text-muted mb-0">Real-time business performance overview.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="?export=excel" class="btn btn-sm btn-success fw-semibold" style="background:#0B6B2F;border-color:#0B6B2F;">
            <i class="bi bi-file-earmark-excel me-1"></i> Excel
        </a>
        <a href="javascript:window.print()" class="btn btn-sm btn-outline-secondary fw-semibold">
            <i class="bi bi-printer me-1"></i> Print
        </a>
    </div>
</div>

<form method="GET" class="period-form">
    <div class="row g-2 align-items-end">
        <div class="col-auto">
            <label class="form-label mb-0 small fw-semibold">Period</label>
            <select name="period" id="periodSelect" class="form-select form-select-sm" style="min-width:140px;">
                <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Today</option>
                <option value="week"  <?= $period === 'week'  ? 'selected' : '' ?>>This Week</option>
                <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>This Month</option>
                <option value="year"  <?= $period === 'year'  ? 'selected' : '' ?>>This Year</option>
                <option value="custom" <?= $from !== '' ? 'selected' : '' ?>>Custom</option>
            </select>
        </div>
        <div class="col-auto" id="customDates" style="<?= $from === '' ? 'display:none;' : '' ?>">
            <label class="form-label mb-0 small fw-semibold">From</label>
            <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($from) ?>">
        </div>
        <div class="col-auto" id="customDatesTo" style="<?= $from === '' ? 'display:none;' : '' ?>">
            <label class="form-label mb-0 small fw-semibold">To</label>
            <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($to) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label mb-0 small fw-semibold">&nbsp;</label>
            <button type="submit" class="btn btn-sm fw-semibold px-3" style="background:#001F5B;color:#fff;">
                <i class="bi bi-funnel"></i> Apply
            </button>
        </div>
    </div>
</form>

<script>
document.getElementById('periodSelect')?.addEventListener('change', function() {
    var show = this.value === 'custom';
    document.getElementById('customDates').style.display = show ? '' : 'none';
    document.getElementById('customDatesTo').style.display = show ? '' : 'none';
});
</script>
<?php endif; ?>

<?php if ($ajax): ?>
<div class="small text-muted text-end mb-2">
    <i class="bi bi-arrow-clockwise"></i> Updated <?= date('h:i A') ?>
</div>
<?php endif; ?>

<!-- ═══════════════ KPI CARDS ═══════════════ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3 col-xl-2">
        <div class="akpi position-relative">
            <div class="num"><?= formatPrice($todayRev) ?></div>
            <div class="lbl">Today's Revenue</div>
            <div class="ico"><i class="bi bi-cash-stack"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="akpi gold position-relative">
            <div class="num"><?= formatPrice($monthRev) ?></div>
            <div class="lbl">Month Revenue</div>
            <div class="ico"><i class="bi bi-calendar-check"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="akpi green position-relative">
            <div class="num"><?= number_format($todayOrders) ?></div>
            <div class="lbl">Today's Orders</div>
            <div class="ico"><i class="bi bi-bag-check"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="akpi position-relative">
            <div class="num"><?= number_format($pendingOrders) ?></div>
            <div class="lbl">Pending Orders</div>
            <div class="ico"><i class="bi bi-clock"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="akpi green position-relative">
            <div class="num"><?= number_format($deliveredOrders) ?></div>
            <div class="lbl">Delivered</div>
            <div class="ico"><i class="bi bi-check-circle"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="akpi red position-relative">
            <div class="num"><?= number_format($cancelledOrders) ?></div>
            <div class="lbl">Cancelled</div>
            <div class="ico"><i class="bi bi-x-circle"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="akpi position-relative">
            <div class="num"><?= number_format($totalCustomers) ?></div>
            <div class="lbl">Total Customers</div>
            <div class="ico"><i class="bi bi-people"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="akpi gold position-relative">
            <div class="num"><?= number_format($newCustomers) ?></div>
            <div class="lbl">New (30d)</div>
            <div class="ico"><i class="bi bi-person-plus"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="akpi purple position-relative">
            <div class="num"><?= number_format($lowStockCount) ?></div>
            <div class="lbl">Low Stock</div>
            <div class="ico"><i class="bi bi-exclamation-triangle"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="akpi red position-relative">
            <div class="num"><?= number_format($outOfStockCount) ?></div>
            <div class="lbl">Out of Stock</div>
            <div class="ico"><i class="bi bi-x-octagon"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <div class="akpi green position-relative">
            <div class="num"><?= number_format($wishlistCount) ?></div>
            <div class="lbl">Wishlisted</div>
            <div class="ico"><i class="bi bi-heart"></i></div>
        </div>
    </div>
</div>

<!-- ═══════════════ CHARTS ═══════════════ -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="chart-card">
            <h6><i class="bi bi-graph-up me-1"></i> Revenue (Last 12 Months)</h6>
            <div class="chart-container"><canvas id="chartRevenueMonths"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="chart-card">
            <h6><i class="bi bi-calendar-day me-1"></i> Daily Sales (Last 30 Days)</h6>
            <div class="chart-container"><canvas id="chartDailySales"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="chart-card">
            <h6><i class="bi bi-calendar-week me-1"></i> Weekly Sales (Last 12 Weeks)</h6>
            <div class="chart-container"><canvas id="chartWeeklySales"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="chart-card">
            <h6><i class="bi bi-pie-chart me-1"></i> Revenue by Payment Method</h6>
            <div class="chart-container"><canvas id="chartPaymentMethod"></canvas></div>
        </div>
    </div>
</div>

<!-- ═══════════════ WIDGETS ═══════════════ -->
<div class="row g-3 mb-4">
    <div class="col-md-6 col-lg-4">
        <div class="widget-card">
            <h6><i class="bi bi-bag-check me-1"></i> Recent Orders</h6>
            <?php if (count($recentOrders) === 0): ?>
                <div class="text-muted small py-2">No orders yet.</div>
            <?php else: ?>
                <?php foreach ($recentOrders as $ro): ?>
                <div class="list-item">
                    <span><a href="<?= SITE_URL ?>admin/orders/view.php?id=<?= (int)$ro['id'] ?>"><?= htmlspecialchars($ro['order_number']) ?></a></span>
                    <span class="fw-semibold" style="color:#0B6B2F;"><?= formatPrice((float)$ro['grand_total']) ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="widget-card">
            <h6><i class="bi bi-trophy me-1"></i> Top Customers</h6>
            <?php if (count($topCustomers) === 0): ?>
                <div class="text-muted small py-2">No data yet.</div>
            <?php else: ?>
                <?php foreach ($topCustomers as $tc): ?>
                <div class="list-item">
                    <span><span class="fw-semibold"><?= htmlspecialchars($tc['full_name']) ?></span><br><small class="text-muted"><?= (int)$tc['order_count'] ?> orders</small></span>
                    <span class="fw-semibold" style="color:#001F5B;"><?= formatPrice((float)$tc['total_spent']) ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="widget-card">
            <h6><i class="bi bi-box-seam me-1"></i> Top Products</h6>
            <?php if (count($topProducts) === 0): ?>
                <div class="text-muted small py-2">No data yet.</div>
            <?php else: ?>
                <?php foreach ($topProducts as $tp): ?>
                <div class="list-item">
                    <span><span class="fw-semibold"><?= htmlspecialchars($tp['name']) ?></span><br><small class="text-muted"><?= (int)$tp['sold_qty'] ?> sold</small></span>
                    <span class="fw-semibold" style="color:#0B6B2F;"><?= formatPrice((float)$tp['revenue']) ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="widget-card">
            <h6><i class="bi bi-arrow-up-down me-1"></i> Recent Inventory Changes</h6>
            <?php if (count($recentInv) === 0): ?>
                <div class="text-muted small py-2">No movements yet.</div>
            <?php else: ?>
                <?php foreach ($recentInv as $ri): ?>
                <div class="list-item">
                    <span><?= htmlspecialchars($ri['product_name']) ?></span>
                    <span class="small"><?= htmlspecialchars($ri['movement_type']) ?> (<?= (int)$ri['quantity'] ?>)</span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="widget-card">
            <h6><i class="bi bi-journal-text me-1"></i> Recent Audit Logs</h6>
            <?php if (count($recentAudit) === 0): ?>
                <div class="text-muted small py-2">No audit logs yet.</div>
            <?php else: ?>
                <?php foreach ($recentAudit as $ra): ?>
                <div class="list-item">
                    <span><span class="fw-semibold"><?= htmlspecialchars($ra['user_name'] ?: '—') ?></span><br><small class="text-muted"><?= htmlspecialchars($ra['action']) ?> · <?= htmlspecialchars($ra['module']) ?></small></span>
                    <span class="small text-muted"><?= date('h:i A', strtotime($ra['created_at'])) ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="widget-card">
            <h6><i class="bi bi-exclamation-triangle me-1"></i> Low Stock Alerts</h6>
            <?php if (count($lowStockAlerts) === 0): ?>
                <div class="text-muted small py-2">All products well stocked.</div>
            <?php else: ?>
                <?php foreach ($lowStockAlerts as $ls): ?>
                <div class="list-item">
                    <span><span class="fw-semibold"><?= htmlspecialchars($ls['name']) ?></span><br><small class="text-muted"><?= htmlspecialchars($ls['code']) ?></small></span>
                    <span class="badge bg-warning text-dark"><?= (int)$ls['stock_quantity'] ?> left</span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ═══════════════ QUICK LINKS ═══════════════ -->
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="<?= SITE_URL ?>admin/products/index.php" class="btn btn-outline-dark btn-sm quick-link-btn"><i class="bi bi-box-seam me-1"></i> Products</a>
    <a href="<?= SITE_URL ?>admin/orders/index.php" class="btn btn-outline-dark btn-sm quick-link-btn"><i class="bi bi-bag-check me-1"></i> Orders</a>
    <a href="<?= SITE_URL ?>admin/reports/dashboard.php" class="btn btn-outline-dark btn-sm quick-link-btn"><i class="bi bi-graph-up-arrow me-1"></i> Reports</a>
    <a href="<?= SITE_URL ?>admin/inventory/index.php" class="btn btn-outline-dark btn-sm quick-link-btn"><i class="bi bi-boxes me-1"></i> Inventory</a>
    <a href="<?= SITE_URL ?>admin/audit/index.php" class="btn btn-outline-dark btn-sm quick-link-btn"><i class="bi bi-journal-text me-1"></i> Audit Logs</a>
    <a href="<?= SITE_URL ?>admin/analytics/sales.php" class="btn btn-outline-dark btn-sm quick-link-btn"><i class="bi bi-graph-up me-1"></i> Sales Analytics</a>
    <a href="<?= SITE_URL ?>admin/analytics/customers.php" class="btn btn-outline-dark btn-sm quick-link-btn"><i class="bi bi-people me-1"></i> Customer Analytics</a>
    <a href="<?= SITE_URL ?>admin/analytics/products.php" class="btn btn-outline-dark btn-sm quick-link-btn"><i class="bi bi-box-seam me-1"></i> Product Analytics</a>
</div>

</div> <!-- /analyticsRefreshWrap -->

</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= SITE_URL ?>assets/js/analytics.js"></script>
<script>
function initAnalyticsCharts() {

    // ── Revenue Months ──
    var rmLabels = <?= json_encode(array_column($revenueMonths, 'label')) ?>;
    var rmRevenue = <?= json_encode(array_map(function($r){return (float)$r['revenue'];}, $revenueMonths)) ?>;
    var rmOrders = <?= json_encode(array_map(function($r){return (int)$r['orders'];}, $revenueMonths)) ?>;
    if (rmLabels.length) {
        new Chart(document.getElementById('chartRevenueMonths'), {
            type: 'line',
            data: {
                labels: rmLabels,
                datasets: [
                    { label: 'Revenue', data: rmRevenue, borderColor: '#001F5B', backgroundColor: 'rgba(0,31,91,0.1)', fill: true, tension: 0.3, yAxisID: 'y' },
                    { label: 'Orders', data: rmOrders, borderColor: '#C9A227', backgroundColor: 'rgba(201,162,39,0.1)', fill: true, tension: 0.3, yAxisID: 'y1' }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } } },
                scales: {
                    y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Revenue (RWF)', font: { size: 10 } } },
                    y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Orders', font: { size: 10 } } }
                }
            }
        });
    }

    // ── Daily Sales ──
    var dsLabels = <?= json_encode(array_column($dailySales, 'label')) ?>;
    var dsRevenue = <?= json_encode(array_map(function($r){return (float)$r['revenue'];}, $dailySales)) ?>;
    if (dsLabels.length) {
        new Chart(document.getElementById('chartDailySales'), {
            type: 'bar',
            data: {
                labels: dsLabels,
                datasets: [{ label: 'Revenue', data: dsRevenue, backgroundColor: 'rgba(0,31,91,0.7)', borderRadius: 3 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { font: { size: 10 } } }, x: { ticks: { font: { size: 9 }, maxRotation: 45 } } }
            }
        });
    }

    // ── Weekly Sales ──
    var wsLabels = <?= json_encode(array_column($weeklySales, 'label')) ?>;
    var wsRevenue = <?= json_encode(array_map(function($r){return (float)$r['revenue'];}, $weeklySales)) ?>;
    if (wsLabels.length) {
        new Chart(document.getElementById('chartWeeklySales'), {
            type: 'line',
            data: {
                labels: wsLabels,
                datasets: [{ label: 'Revenue', data: wsRevenue, borderColor: '#0B6B2F', backgroundColor: 'rgba(11,107,47,0.1)', fill: true, tension: 0.3 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { font: { size: 10 } } }, x: { ticks: { font: { size: 9 } } } }
            }
        });
    }

    // ── Payment Method Pie ──
    var pmLabels = <?= json_encode(array_map(function($r){return ucfirst(str_replace('_',' ',$r['payment_method']));}, $paymentMethod)) ?>;
    var pmData = <?= json_encode(array_map(function($r){return (float)$r['revenue'];}, $paymentMethod)) ?>;
    if (pmLabels.length) {
        new Chart(document.getElementById('chartPaymentMethod'), {
            type: 'pie',
            data: {
                labels: pmLabels,
                datasets: [{
                    data: pmData,
                    backgroundColor: ['#001F5B','#C9A227','#0B6B2F','#8B5CF6','#F59E0B'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8, font: { size: 10 } } } }
            }
        });
    }
}
document.addEventListener('DOMContentLoaded', initAnalyticsCharts);
</script>

<?php
if (!$ajax) {
    require_once __DIR__ . '/../../includes/admin-footer.php';
}
?>
