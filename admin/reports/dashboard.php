<?php
/**
 * Executive BI Dashboard
 *
 * Champion Liquor Store Ltd — Enterprise Reporting
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$pageTitle = 'Executive Dashboard';
require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= SITE_URL ?>assets/css/reports/reports.css">
<style>
.bi-kpi { font-size: 1.8rem; }
.kpi-up { color: #0B6B2F; }
.kpi-down { color: #dc3545; }
.insight-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); padding: 1.25rem; border-left: 4px solid #C9A227; margin-bottom: 1rem; }
.insight-card i { color: #C9A227; font-size: 1.2rem; margin-right: 0.5rem; }
.insight-card .insight-text { font-size: 0.92rem; color: #333; }
.chart-container { position: relative; height: 280px; width: 100%; }
.widget-stat { font-size: 1.4rem; font-weight: 700; color: #001F5B; }
.widget-label { font-size: 0.78rem; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; }
</style>

<div class="admin-content-wrapper">
<main class="admin-content">

<?php
$db = getDbConnection();

// ─── PART 1: KPI Queries ──────────────────────────────────────────────
// All queries run once and reused

// Today
$today = $db->prepare("SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE DATE(created_at)=CURDATE() AND status!=?");
$today->execute([ORDER_CANCELLED]); $todayRevenue = (float)$today->fetchColumn();

$todayOrdersStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()");
$todayOrdersStmt->execute(); $todayOrders = (int)$todayOrdersStmt->fetchColumn();

// This Week (ISO — Monday to Sunday)
$week = $db->prepare("SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE YEARWEEK(created_at,1)=YEARWEEK(CURDATE(),1) AND status!=?");
$week->execute([ORDER_CANCELLED]); $weekRevenue = (float)$week->fetchColumn();

// This Month
$month = $db->prepare("SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE()) AND status!=?");
$month->execute([ORDER_CANCELLED]); $monthRevenue = (float)$month->fetchColumn();

$monthOrdersStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
$monthOrdersStmt->execute(); $monthOrders = (int)$monthOrdersStmt->fetchColumn();

// This Year
$year = $db->prepare("SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE YEAR(created_at)=YEAR(CURDATE()) AND status!=?");
$year->execute([ORDER_CANCELLED]); $yearRevenue = (float)$year->fetchColumn();

// All-time metrics
$avgOrder = $db->prepare("SELECT COALESCE(AVG(grand_total),0) FROM orders WHERE status!=?");
$avgOrder->execute([ORDER_CANCELLED]); $avgOrderValue = (float)$avgOrder->fetchColumn();

$totalOrders = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();

$totalCust = $db->prepare("SELECT COUNT(*) FROM users WHERE role=?");
$totalCust->execute([CUSTOMER_ROLE]); $totalCustomers = (int)$totalCust->fetchColumn();

$activeProds = $db->prepare("SELECT COUNT(*) FROM products WHERE status=?");
$activeProds->execute(['active']); $activeProducts = (int)$activeProds->fetchColumn();

$lowStockCount = $db->prepare("SELECT COUNT(*) FROM products WHERE stock_quantity>0 AND stock_quantity<=?");
$lowStockCount->execute([LOW_STOCK_THRESHOLD]); $lowStockProd = (int)$lowStockCount->fetchColumn();

$outStockCount = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity=0"); $outOfStockProd = (int)$outStockCount->fetchColumn();

$cancelledStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE status=?");
$cancelledStmt->execute([ORDER_CANCELLED]); $cancelledOrders = (int)$cancelledStmt->fetchColumn();

// Net Profit (uses cost_price)
$profitStmt = $db->prepare("
    SELECT COALESCE(SUM(oi.subtotal - (oi.quantity * COALESCE(p.cost_price, 0))), 0)
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status = ?
");
$profitStmt->execute([ORDER_DELIVERED]); $netProfit = (float)$profitStmt->fetchColumn();

// ─── PART 2: Chart Data ───────────────────────────────────────────────

// 2a. Sales Trend (last 30 days)
$salesTrend = $db->query("
    SELECT DATE(created_at) AS dt, COALESCE(SUM(grand_total),0) AS revenue, COUNT(*) AS orders
    FROM orders WHERE status!='cancelled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at) ORDER BY dt ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 2b. Revenue by Month (current year)
$revenueByMonth = $db->query("
    SELECT MONTH(created_at) AS m, COALESCE(SUM(grand_total),0) AS revenue
    FROM orders WHERE YEAR(created_at)=YEAR(CURDATE()) AND status!='cancelled'
    GROUP BY MONTH(created_at) ORDER BY m ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 2c. Orders by Status (Pie)
$ordersByStatus = $db->query("
    SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);

// 2d. Top Categories (Bar)
$topCategories = $db->query("
    SELECT c.name, COALESCE(SUM(oi.subtotal),0) AS revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE o.status = 'delivered'
    GROUP BY c.id, c.name
    ORDER BY revenue DESC LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// 2e. Top Brands (Bar)
$topBrands = $db->query("
    SELECT b.name, COALESCE(SUM(oi.subtotal),0) AS revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    JOIN brands b ON p.brand_id = b.id
    WHERE o.status = 'delivered'
    GROUP BY b.id, b.name
    ORDER BY revenue DESC LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// 2f. Inventory Status (Doughnut)
$invStatusTotal = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$invActive = (int)$db->query("SELECT COUNT(*) FROM products WHERE status='active' AND stock_quantity>0")->fetchColumn();
$invLow = (int)$db->query("SELECT COUNT(*) FROM products WHERE status='active' AND stock_quantity>0 AND stock_quantity<=" . LOW_STOCK_THRESHOLD)->fetchColumn();
$invOut = (int)$db->query("SELECT COUNT(*) FROM products WHERE stock_quantity=0")->fetchColumn();
$invInactive = $invStatusTotal - $invActive - $invOut;

// 2g. Customer Growth (last 12 months)
$customerGrowth = $db->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym, COUNT(*) AS cnt
    FROM users WHERE role='customer' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY ym ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ─── PART 3: Widget Data ──────────────────────────────────────────────

// Best Selling Product (all time)
$bestProduct = $db->query("
    SELECT p.name, SUM(oi.quantity) AS qty, SUM(oi.subtotal) AS revenue
    FROM order_items oi JOIN orders o ON oi.order_id=o.id
    JOIN products p ON oi.product_id=p.id
    WHERE o.status='delivered'
    GROUP BY p.id, p.name ORDER BY qty DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

// Highest Revenue Category
$bestCategory = $db->query("
    SELECT c.name, SUM(oi.subtotal) AS revenue
    FROM order_items oi JOIN orders o ON oi.order_id=o.id
    JOIN products p ON oi.product_id=p.id
    JOIN categories c ON p.category_id=c.id
    WHERE o.status='delivered'
    GROUP BY c.id, c.name ORDER BY revenue DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

// Top Brand
$bestBrand = $db->query("
    SELECT b.name, SUM(oi.subtotal) AS revenue
    FROM order_items oi JOIN orders o ON oi.order_id=o.id
    JOIN products p ON oi.product_id=p.id
    JOIN brands b ON p.brand_id=b.id
    WHERE o.status='delivered'
    GROUP BY b.id, b.name ORDER BY revenue DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

// Low Stock List (top 5)
$lowStockList = $db->prepare("
    SELECT name, stock_quantity FROM products
    WHERE stock_quantity>0 AND stock_quantity<=?
    ORDER BY stock_quantity ASC LIMIT 5
");
$lowStockList->execute([LOW_STOCK_THRESHOLD]);
$lowStockItems = $lowStockList->fetchAll(PDO::FETCH_ASSOC);

// ─── PART 4: AI Insights ──────────────────────────────────────────────
$insights = [];

// 4a. Category growth compared to last month
$catGrowthCur = $db->query("
    SELECT c.name, COALESCE(SUM(oi.subtotal),0) AS rev
    FROM order_items oi JOIN orders o ON oi.order_id=o.id
    JOIN products p ON oi.product_id=p.id
    JOIN categories c ON p.category_id=c.id
    WHERE o.status='delivered'
      AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())
    GROUP BY c.id, c.name ORDER BY rev DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$catGrowthPrev = $db->query("
    SELECT c.name, COALESCE(SUM(oi.subtotal),0) AS rev
    FROM order_items oi JOIN orders o ON oi.order_id=o.id
    JOIN products p ON oi.product_id=p.id
    JOIN categories c ON p.category_id=c.id
    WHERE o.status='delivered'
      AND YEAR(created_at)=YEAR(CURDATE() - INTERVAL 1 MONTH)
      AND MONTH(created_at)=MONTH(CURDATE() - INTERVAL 1 MONTH)
    GROUP BY c.id, c.name ORDER BY rev DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if ($catGrowthCur && $catGrowthPrev && $catGrowthPrev['rev'] > 0) {
    $pct = round(($catGrowthCur['rev'] - $catGrowthPrev['rev']) / $catGrowthPrev['rev'] * 100, 1);
    $dir = $pct >= 0 ? 'increased' : 'decreased';
    $insights[] = [
        'icon' => $pct >= 0 ? 'bi-arrow-up' : 'bi-arrow-down',
        'class' => $pct >= 0 ? 'kpi-up' : 'kpi-down',
        'text' => htmlspecialchars($catGrowthCur['name']) . " sales {$dir} by " . abs($pct) . "% compared to last month."
    ];
}

// 4b. Category revenue contribution
$totalDelivered = $db->prepare("SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE status=?");
$totalDelivered->execute([ORDER_DELIVERED]);
$totalDelRev = (float)$totalDelivered->fetchColumn();

if ($totalDelRev > 0) {
    $catContrib = $db->query("
        SELECT c.name, COALESCE(SUM(oi.subtotal),0) AS rev
        FROM order_items oi JOIN orders o ON oi.order_id=o.id
        JOIN products p ON oi.product_id=p.id
        JOIN categories c ON p.category_id=c.id
        WHERE o.status='delivered'
        GROUP BY c.id, c.name ORDER BY rev DESC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    if ($catContrib) {
        $contribPct = round($catContrib['rev'] / $totalDelRev * 100, 1);
        $insights[] = [
            'icon' => 'bi-pie-chart',
            'class' => 'kpi-up',
            'text' => htmlspecialchars($catContrib['name']) . " contributes {$contribPct}% of total revenue."
        ];
    }
}

// 4c. Top customer this month
$topCustMonth = $db->prepare("
    SELECT u.full_name, SUM(o.grand_total) AS spent
    FROM orders o JOIN users u ON o.user_id=u.id
    WHERE o.status!='cancelled'
      AND YEAR(o.created_at)=YEAR(CURDATE()) AND MONTH(o.created_at)=MONTH(CURDATE())
    GROUP BY u.id, u.full_name ORDER BY spent DESC LIMIT 1
");
$topCustMonth->execute();
$topCust = $topCustMonth->fetch(PDO::FETCH_ASSOC);
if ($topCust) {
    $insights[] = [
        'icon' => 'bi-star',
        'class' => 'kpi-up',
        'text' => 'Top customer this month: ' . htmlspecialchars($topCust['full_name']) . ' spent ' . formatPrice((float)$topCust['spent']) . '.'
    ];
}

// 4d. Low stock alert
if ($lowStockProd > 0) {
    $insights[] = [
        'icon' => 'bi-exclamation-triangle',
        'class' => 'kpi-down',
        'text' => "{$lowStockProd} product(s) are low on stock and require restocking."
    ];
}

// 4e. Overall growth (this month vs last month)
$prevMonthRev = $db->prepare("SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE YEAR(created_at)=YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(created_at)=MONTH(CURDATE() - INTERVAL 1 MONTH) AND status!=?");
$prevMonthRev->execute([ORDER_CANCELLED]);
$prevMonth = (float)$prevMonthRev->fetchColumn();
if ($prevMonth > 0) {
    $growthPct = round(($monthRevenue - $prevMonth) / $prevMonth * 100, 1);
    $dirText = $growthPct >= 0 ? 'increased' : 'decreased';
    $insights[] = [
        'icon' => $growthPct >= 0 ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow',
        'class' => $growthPct >= 0 ? 'kpi-up' : 'kpi-down',
        'text' => "Monthly revenue {$dirText} by " . abs($growthPct) . "% compared to last month."
    ];
}

// 4f. AOV insight
$insights[] = [
    'icon' => 'bi-cart-check',
    'class' => 'kpi-up',
    'text' => 'Average order value is ' . formatPrice($avgOrderValue) . ' across ' . number_format($totalOrders) . ' orders.'
];

// ─── PART 5: Helper to format chart data ──────────────────────────────

// Build JS arrays from PHP
$salesTrendLabels = []; $salesTrendRevenue = []; $salesTrendOrders = [];
foreach ($salesTrend as $r) {
    $salesTrendLabels[] = date('d M', strtotime($r['dt']));
    $salesTrendRevenue[] = (float)$r['revenue'];
    $salesTrendOrders[] = (int)$r['orders'];
}

$monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$monthRevenueData = array_fill(0, 12, 0);
foreach ($revenueByMonth as $r) { $monthRevenueData[(int)$r['m'] - 1] = (float)$r['revenue']; }

$statusLabels = []; $statusCounts = []; $statusColors = [];
$statusColorMap = ['pending'=>'#6B7280','confirmed'=>'#8B5CF6','processing'=>'#F59E0B','shipped'=>'#3B82F6','delivered'=>'#0B6B2F','cancelled'=>'#EF4444'];
foreach ($ordersByStatus as $r) {
    $statusLabels[] = ucfirst($r['status']);
    $statusCounts[] = (int)$r['cnt'];
    $statusColors[] = $statusColorMap[$r['status']] ?? '#6B7280';
}

$catLabels = []; $catRevenue = [];
foreach ($topCategories as $r) { $catLabels[] = $r['name']; $catRevenue[] = (float)$r['revenue']; }

$brandLabels = []; $brandRevenue = [];
foreach ($topBrands as $r) { $brandLabels[] = $r['name']; $brandRevenue[] = (float)$r['revenue']; }

$custGrowthLabels = []; $custGrowthCounts = [];
foreach ($customerGrowth as $r) { $custGrowthLabels[] = $r['ym']; $custGrowthCounts[] = (int)$r['cnt']; }
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="fs-3 fw-bold" style="color: var(--admin-primary);">Executive Dashboard</h1>
        <p class="text-muted">Enterprise BI overview for <?= SITE_NAME ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= SITE_URL ?>admin/reports/sales.php" class="btn btn-sm fw-semibold" style="background:var(--admin-primary);color:#fff;">
            <i class="bi bi-graph-up"></i> Sales Analytics
        </a>
        <a href="<?= SITE_URL ?>admin/reports/finance.php" class="btn btn-sm btn-outline-secondary fw-semibold">
            <i class="bi bi-cash-stack"></i> Finance
        </a>
        <a href="<?= SITE_URL ?>admin/reports/profit.php" class="btn btn-sm btn-outline-secondary fw-semibold">
            <i class="bi bi-bar-chart-line"></i> Profit
        </a>
        <a href="<?= SITE_URL ?>admin/reports/inventory.php" class="btn btn-sm btn-outline-secondary fw-semibold">
            <i class="bi bi-boxes"></i> Inventory
        </a>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     KPI CARD ROW 1
     ═══════════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="rp-stat-card rp-stat-revenue">
            <div class="rp-stat-icon"><i class="bi bi-cash-coin"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= formatPrice($todayRevenue) ?></span>
                <span class="rp-stat-label">Today's Revenue</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rp-stat-card rp-stat-revenue">
            <div class="rp-stat-icon"><i class="bi bi-calendar-week"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= formatPrice($weekRevenue) ?></span>
                <span class="rp-stat-label">This Week</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rp-stat-card rp-stat-revenue">
            <div class="rp-stat-icon"><i class="bi bi-calendar-month"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= formatPrice($monthRevenue) ?></span>
                <span class="rp-stat-label">This Month</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rp-stat-card rp-stat-revenue">
            <div class="rp-stat-icon"><i class="bi bi-calendar-year"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= formatPrice($yearRevenue) ?></span>
                <span class="rp-stat-label">This Year</span>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     KPI CARD ROW 2
     ═══════════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="rp-stat-card rp-stat-orders">
            <div class="rp-stat-icon"><i class="bi bi-cart-check"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= number_format($totalOrders) ?></span>
                <span class="rp-stat-label">Total Orders</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rp-stat-card rp-stat-customers">
            <div class="rp-stat-icon"><i class="bi bi-people"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= number_format($totalCustomers) ?></span>
                <span class="rp-stat-label">Total Customers</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rp-stat-card" style="border-left-color:#8B5CF6;">
            <div class="rp-stat-icon" style="background:#f3eeff;color:#8B5CF6;"><i class="bi bi-receipt"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= formatPrice($avgOrderValue) ?></span>
                <span class="rp-stat-label">Avg Order Value</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rp-stat-card" style="border-left-color:#0B6B2F;">
            <div class="rp-stat-icon" style="background:#e8f5e9;color:#0B6B2F;"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= formatPrice($netProfit) ?></span>
                <span class="rp-stat-label">Net Profit</span>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     KPI CARD ROW 3
     ═══════════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="rp-stat-card rp-stat-products">
            <div class="rp-stat-icon"><i class="bi bi-box"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= number_format($activeProducts) ?></span>
                <span class="rp-stat-label">Active Products</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="rp-stat-card rp-stat-lowstock">
            <div class="rp-stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= $lowStockProd ?></span>
                <span class="rp-stat-label">Low Stock</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="rp-stat-card" style="border-left-color:#dc3545;">
            <div class="rp-stat-icon" style="background:#fde8ea;color:#dc3545;"><i class="bi bi-x-circle"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= $outOfStockProd ?></span>
                <span class="rp-stat-label">Out of Stock</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="rp-stat-card" style="border-left-color:#EF4444;">
            <div class="rp-stat-icon" style="background:#fef2f2;color:#EF4444;"><i class="bi bi-ban"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= number_format($cancelledOrders) ?></span>
                <span class="rp-stat-label">Cancelled Orders</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="rp-stat-card" style="border-left-color:#F59E0B;">
            <div class="rp-stat-icon" style="background:#fff8e6;color:#F59E0B;"><i class="bi bi-cart"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= $todayOrders ?></span>
                <span class="rp-stat-label">Today's Orders</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="rp-stat-card" style="border-left-color:#C9A227;">
            <div class="rp-stat-icon" style="background:#fcf6e3;color:#C9A227;"><i class="bi bi-coin"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= formatPrice($todayRevenue) ?></span>
                <span class="rp-stat-label">Today's Revenue</span>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     CHARTS ROW 1 — Sales Trend (Line) + Revenue by Month (Bar)
     ═══════════════════════════════════════════════════════════════════ -->
<div class="row g-4 mb-4">
    <div class="col-md-8">
        <div class="rp-chart-box">
            <div class="rp-section-title"><i class="bi bi-graph-up"></i> Sales Trend (Last 30 Days)</div>
            <div class="chart-container">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rp-chart-box">
            <div class="rp-section-title"><i class="bi bi-pie-chart"></i> Orders by Status</div>
            <div class="chart-container">
                <canvas id="ordersStatusChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     CHARTS ROW 2 — Revenue by Month + Doughnut
     ═══════════════════════════════════════════════════════════════════ -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="rp-chart-box">
            <div class="rp-section-title"><i class="bi bi-bar-chart"></i> Revenue by Month (<?= date('Y') ?>)</div>
            <div class="chart-container">
                <canvas id="revenueMonthChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="rp-chart-box">
            <div class="rp-section-title"><i class="bi bi-boxes"></i> Inventory Status</div>
            <div class="chart-container" style="height:240px;">
                <canvas id="invStatusChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="rp-chart-box">
            <div class="rp-section-title"><i class="bi bi-people"></i> Customer Growth</div>
            <div class="chart-container" style="height:240px;">
                <canvas id="custGrowthChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     CHARTS ROW 3 — Top Categories + Top Brands
     ═══════════════════════════════════════════════════════════════════ -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="rp-chart-box">
            <div class="rp-section-title"><i class="bi bi-tags"></i> Top Categories by Revenue</div>
            <div class="chart-container">
                <canvas id="topCategoriesChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="rp-chart-box">
            <div class="rp-section-title"><i class="bi bi-award"></i> Top Brands by Revenue</div>
            <div class="chart-container">
                <canvas id="topBrandsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     DASHBOARD WIDGETS
     ═══════════════════════════════════════════════════════════════════ -->
<div class="row g-4 mb-4">
    <!-- Best Selling Product -->
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                <div class="rp-stat-icon mx-auto mb-2" style="background:#001F5B;color:#fff;width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-trophy fs-4"></i>
                </div>
                <div class="widget-label">Best Selling Product</div>
                <div class="widget-stat"><?= $bestProduct ? htmlspecialchars($bestProduct['name']) : 'N/A' ?></div>
                <small class="text-muted"><?= $bestProduct ? (int)$bestProduct['qty'] . ' sold - ' . formatPrice((float)$bestProduct['revenue']) : '' ?></small>
            </div>
        </div>
    </div>
    <!-- Highest Revenue Category -->
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                <div class="rp-stat-icon mx-auto mb-2" style="background:#C9A227;color:#fff;width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-tags fs-4"></i>
                </div>
                <div class="widget-label">Top Category</div>
                <div class="widget-stat"><?= $bestCategory ? htmlspecialchars($bestCategory['name']) : 'N/A' ?></div>
                <small class="text-muted"><?= $bestCategory ? formatPrice((float)$bestCategory['revenue']) : '' ?></small>
            </div>
        </div>
    </div>
    <!-- Top Brand -->
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                <div class="rp-stat-icon mx-auto mb-2" style="background:#0B6B2F;color:#fff;width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-award fs-4"></i>
                </div>
                <div class="widget-label">Top Brand</div>
                <div class="widget-stat"><?= $bestBrand ? htmlspecialchars($bestBrand['name']) : 'N/A' ?></div>
                <small class="text-muted"><?= $bestBrand ? formatPrice((float)$bestBrand['revenue']) : '' ?></small>
            </div>
        </div>
    </div>
    <!-- Low Stock Alerts -->
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="widget-label mb-2"><i class="bi bi-exclamation-triangle text-warning"></i> Low Stock Alerts</div>
                <?php if ($lowStockItems): ?>
                <ul class="list-unstyled mb-0 small">
                    <?php foreach ($lowStockItems as $ls): ?>
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span><?= htmlspecialchars($ls['name']) ?></span>
                        <span class="badge bg-warning text-dark"><?= (int)$ls['stock_quantity'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="text-muted small mb-0">All products well-stocked.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     AI INSIGHTS
     ═══════════════════════════════════════════════════════════════════ -->
<?php if (!empty($insights)): ?>
<div class="mb-4">
    <div class="rp-section-title"><i class="bi bi-robot"></i> AI Insights</div>
    <?php foreach ($insights as $ins): ?>
    <div class="insight-card">
        <i class="bi <?= $ins['icon'] ?> <?= $ins['class'] ?>"></i>
        <span class="insight-text"><?= $ins['text'] ?></span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Chart.defaults.color = '#6B7280';
    Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";

    // 1. Sales Trend (Line)
    new Chart(document.getElementById('salesTrendChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($salesTrendLabels) ?>,
            datasets: [{
                label: 'Revenue (RWF)',
                data: <?= json_encode($salesTrendRevenue) ?>,
                borderColor: '#001F5B',
                backgroundColor: 'rgba(0,31,91,0.08)',
                fill: true,
                tension: 0.3,
                yAxisID: 'y'
            }, {
                label: 'Orders',
                data: <?= json_encode($salesTrendOrders) ?>,
                borderColor: '#C9A227',
                backgroundColor: 'rgba(201,162,39,0.08)',
                fill: true,
                tension: 0.3,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top' } },
            scales: {
                y: { type: 'linear', display: true, position: 'left', beginAtZero: true, title: { display: true, text: 'Revenue (RWF)' } },
                y1: { type: 'linear', display: true, position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'Orders' } }
            }
        }
    });

    // 2. Orders by Status (Pie)
    new Chart(document.getElementById('ordersStatusChart'), {
        type: 'pie',
        data: {
            labels: <?= json_encode($statusLabels) ?>,
            datasets: [{
                data: <?= json_encode($statusCounts) ?>,
                backgroundColor: <?= json_encode($statusColors) ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } } }
        }
    });

    // 3. Revenue by Month (Bar)
    new Chart(document.getElementById('revenueMonthChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthLabels) ?>,
            datasets: [{
                label: 'Revenue (RWF)',
                data: <?= json_encode($monthRevenueData) ?>,
                backgroundColor: 'rgba(0,31,91,0.75)',
                borderColor: '#001F5B',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, title: { display: true, text: 'Revenue (RWF)' } } }
        }
    });

    // 4. Inventory Status (Doughnut)
    new Chart(document.getElementById('invStatusChart'), {
        type: 'doughnut',
        data: {
            labels: ['In Stock', 'Low Stock', 'Out of Stock', 'Inactive'],
            datasets: [{
                data: [<?= max(0, $invActive - $invLow) ?>, <?= $invLow ?>, <?= $invOut ?>, <?= max(0, $invInactive) ?>],
                backgroundColor: ['#0B6B2F', '#F59E0B', '#EF4444', '#D1D5DB'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8, font: { size: 10 } } } }
        }
    });

    // 5. Customer Growth (Line)
    new Chart(document.getElementById('custGrowthChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($custGrowthLabels) ?>,
            datasets: [{
                label: 'New Customers',
                data: <?= json_encode($custGrowthCounts) ?>,
                borderColor: '#0B6B2F',
                backgroundColor: 'rgba(11,107,47,0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // 6. Top Categories (Bar)
    new Chart(document.getElementById('topCategoriesChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($catLabels) ?>,
            datasets: [{
                label: 'Revenue (RWF)',
                data: <?= json_encode($catRevenue) ?>,
                backgroundColor: 'rgba(201,162,39,0.75)',
                borderColor: '#C9A227',
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

    // 7. Top Brands (Bar)
    new Chart(document.getElementById('topBrandsChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($brandLabels) ?>,
            datasets: [{
                label: 'Revenue (RWF)',
                data: <?= json_encode($brandRevenue) ?>,
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
            scales: { x: { beginAtZero: true, title: { display: true, text: 'Revenue (RWF)' } } }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
