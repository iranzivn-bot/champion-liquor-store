<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$pageTitle = 'Sales Report';

$pdo = getDbConnection();

$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');
$period = trim($_GET['period'] ?? 'daily');

$whereClause = "WHERE o.status != 'cancelled'";
$params = [];

if ($from !== '') {
    $whereClause .= ' AND DATE(o.created_at) >= :from_date';
    $params[':from_date'] = $from;
}
if ($to !== '') {
    $whereClause .= ' AND DATE(o.created_at) <= :to_date';
    $params[':to_date'] = $to;
}

switch ($period) {
    case 'weekly':
        $dateGroup = "YEARWEEK(o.created_at, 1)";
        $dateLabel = "CONCAT(YEAR(o.created_at), '-W', LPAD(WEEK(o.created_at, 1), 2, '0'))";
        break;
    case 'monthly':
        $dateGroup = "DATE_FORMAT(o.created_at, '%Y-%m')";
        $dateLabel = "DATE_FORMAT(o.created_at, '%Y-%m')";
        break;
    case 'yearly':
        $dateGroup = "YEAR(o.created_at)";
        $dateLabel = "YEAR(o.created_at)";
        break;
    default:
        $period = 'daily';
        $dateGroup = "DATE(o.created_at)";
        $dateLabel = "DATE(o.created_at)";
        break;
}

$stmt = $pdo->prepare("
    SELECT {$dateLabel} AS label, {$dateGroup} AS grp,
           COUNT(*) AS orders, COALESCE(SUM(grand_total), 0) AS revenue
    FROM orders o
    {$whereClause}
    GROUP BY grp
    ORDER BY grp DESC
");
$stmt->execute($params);
$salesData = $stmt->fetchAll();

$todayRevenue    = (float) $pdo->query("SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE status != 'cancelled' AND DATE(created_at) = CURDATE()")->fetchColumn();
$todayOrders     = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status != 'cancelled' AND DATE(created_at) = CURDATE()")->fetchColumn();
$monthRevenue    = (float) $pdo->query("SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE status != 'cancelled' AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")->fetchColumn();
$monthOrders     = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status != 'cancelled' AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")->fetchColumn();
$yearRevenue     = (float) $pdo->query("SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE status != 'cancelled' AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
$yearOrders      = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status != 'cancelled' AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
$totalRevenue    = (float) $pdo->query("SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE status != 'cancelled'")->fetchColumn();
$totalOrders     = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status != 'cancelled'")->fetchColumn();
$avgOrderValue   = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= SITE_URL ?>assets/css/reports/reports.css">

<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="rp-page-header">
            <div>
                <h1>Sales Report</h1>
                <p class="text-muted mb-0">Revenue and order metrics across periods.</p>
            </div>
            <div class="rp-export-bar">
                <a href="<?= SITE_URL ?>admin/reports/export.php?type=sales&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&period=<?= $period ?>&format=csv" class="btn btn-sm btn-success fw-semibold">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV
                </a>
                <a href="<?= SITE_URL ?>admin/reports/export.php?type=sales&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&period=<?= $period ?>&format=excel" class="btn btn-sm btn-success fw-semibold">
                    <i class="bi bi-file-earmark-excel me-1"></i> Excel
                </a>
                <a href="javascript:window.print()" class="btn btn-sm btn-outline-secondary fw-semibold">
                    <i class="bi bi-printer me-1"></i> Print
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="rp-stat-card rp-stat-revenue">
                    <div class="rp-stat-icon"><i class="bi bi-cash-stack"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= formatPrice($todayRevenue) ?></span>
                        <span class="rp-stat-label">Today's Revenue</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rp-stat-card rp-stat-orders">
                    <div class="rp-stat-icon"><i class="bi bi-bag-check"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $todayOrders ?></span>
                        <span class="rp-stat-label">Today's Orders</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rp-stat-card rp-stat-customers">
                    <div class="rp-stat-icon"><i class="bi bi-calendar-month"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= formatPrice($monthRevenue) ?></span>
                        <span class="rp-stat-label">This Month</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rp-stat-card rp-stat-products">
                    <div class="rp-stat-icon"><i class="bi bi-calendar-year"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= formatPrice($yearRevenue) ?></span>
                        <span class="rp-stat-label">This Year</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rp-stat-card" style="border-left-color: #8B5CF6;">
                    <div class="rp-stat-icon" style="background: #f3eeff; color: #8B5CF6;"><i class="bi bi-receipt"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $monthOrders ?></span>
                        <span class="rp-stat-label">Monthly Orders</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rp-stat-card" style="border-left-color: #6B7280;">
                    <div class="rp-stat-icon" style="background: #f0f0f0; color: #6B7280;"><i class="bi bi-calculator"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= formatPrice($avgOrderValue) ?></span>
                        <span class="rp-stat-label">Avg Order Value</span>
                    </div>
                </div>
            </div>
        </div>

        <form method="GET" class="rp-filters">
            <div>
                <label class="form-label">Period</label>
                <select name="period" class="form-select" style="min-width: 130px;">
                    <option value="daily" <?= $period === 'daily' ? 'selected' : '' ?>>Daily</option>
                    <option value="weekly" <?= $period === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                    <option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                    <option value="yearly" <?= $period === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                </select>
            </div>
            <div>
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
            </div>
            <div>
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
            </div>
            <div>
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn fw-semibold px-3" style="background: #001F5B; color: #fff;">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="<?= SITE_URL ?>admin/reports/sales.php" class="btn btn-outline-secondary fw-semibold px-3">
                    <i class="bi bi-x-circle me-1"></i> Clear
                </a>
            </div>
        </form>

        <div class="rp-chart-box">
            <h3 class="rp-section-title"><i class="bi bi-graph-up"></i> Sales Chart</h3>
            <canvas id="salesChart" height="200"></canvas>
        </div>

        <div class="rp-table-wrap">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th><?= $period === 'yearly' ? 'Year' : ($period === 'monthly' ? 'Month' : ($period === 'weekly' ? 'Week' : 'Date')) ?></th>
                        <th class="text-center">Orders</th>
                        <th class="text-end">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($salesData) === 0): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">No sales data found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($salesData as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['label']) ?></td>
                                <td class="text-center"><?= (int) $row['orders'] ?></td>
                                <td class="text-end fw-semibold" style="color: #0B6B2F;"><?= formatPrice((float) $row['revenue']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             EXTENSION: Growth Analytics
             ═══════════════════════════════════════════════════════════════ -->
        <?php
        // Previous period comparison for growth %
        $prevPeriodRevenue = 0;
        $prevPeriodOrders = 0;
        $currentPeriodRevenue = $totalRevenue;
        $currentPeriodOrders = $totalOrders;

        // Compare to previous month if we have date range
        if ($from !== '' && $to !== '') {
            $fromDate = new DateTime($from);
            $toDate = new DateTime($to);
            $interval = $fromDate->diff($toDate);
            $days = $interval->days + 1;

            $prevTo = clone $fromDate;
            $prevTo->modify('-1 day');
            $prevFrom = clone $prevTo;
            $prevFrom->modify("-{$days} days");

            $prevStmt = $pdo->prepare("
                SELECT COALESCE(SUM(grand_total),0) AS revenue, COUNT(*) AS orders
                FROM orders WHERE status != 'cancelled'
                  AND DATE(created_at) >= :pf AND DATE(created_at) <= :pt
            ");
            $prevStmt->execute([':pf' => $prevFrom->format('Y-m-d'), ':pt' => $prevTo->format('Y-m-d')]);
            $prevData = $prevStmt->fetch();
            $prevPeriodRevenue = (float)$prevData['revenue'];
            $prevPeriodOrders = (int)$prevData['orders'];
        } else {
            // Compare this month to last month
            $prevStmt = $pdo->prepare("
                SELECT COALESCE(SUM(grand_total),0) AS revenue, COUNT(*) AS orders
                FROM orders WHERE status != 'cancelled'
                  AND YEAR(created_at) = YEAR(CURDATE() - INTERVAL 1 MONTH)
                  AND MONTH(created_at) = MONTH(CURDATE() - INTERVAL 1 MONTH)
            ");
            $prevStmt->execute();
            $prevData = $prevStmt->fetch();
            $prevPeriodRevenue = (float)$prevData['revenue'];
            $prevPeriodOrders = (int)$prevData['orders'];
        }

        $revGrowth = $prevPeriodRevenue > 0 ? round(($currentPeriodRevenue - $prevPeriodRevenue) / $prevPeriodRevenue * 100, 1) : 0;
        $ordGrowth = $prevPeriodOrders > 0 ? round(($currentPeriodOrders - $prevPeriodOrders) / $prevPeriodOrders * 100, 1) : 0;

        // AOV trend (monthly for current year)
        $aovTrend = $pdo->query("
            SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym,
                   COALESCE(SUM(grand_total),0) / COUNT(*) AS aov,
                   COUNT(*) AS orders,
                   COALESCE(SUM(grand_total),0) AS revenue
            FROM orders WHERE YEAR(created_at)=YEAR(CURDATE()) AND status!='cancelled'
            GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY ym ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $aovLabels = []; $aovData = [];
        foreach ($aovTrend as $a) { $aovLabels[] = $a['ym']; $aovData[] = round((float)$a['aov'], 2); }
        ?>

        <!-- Growth KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="rp-stat-card" style="border-left-color: <?= $revGrowth >= 0 ? '#0B6B2F' : '#dc3545' ?>;">
                    <div class="rp-stat-icon" style="background:<?= $revGrowth >= 0 ? '#e8f5e9' : '#fde8ea' ?>;color:<?= $revGrowth >= 0 ? '#0B6B2F' : '#dc3545' ?>;">
                        <i class="bi bi-<?= $revGrowth >= 0 ? 'graph-up-arrow' : 'graph-down-arrow' ?>"></i>
                    </div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number" style="color:<?= $revGrowth >= 0 ? '#0B6B2F' : '#dc3545' ?>;"><?= ($revGrowth >= 0 ? '+' : '') . $revGrowth ?>%</span>
                        <span class="rp-stat-label">Revenue Growth</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rp-stat-card" style="border-left-color: <?= $ordGrowth >= 0 ? '#0B6B2F' : '#dc3545' ?>;">
                    <div class="rp-stat-icon" style="background:<?= $ordGrowth >= 0 ? '#e8f5e9' : '#fde8ea' ?>;color:<?= $ordGrowth >= 0 ? '#0B6B2F' : '#dc3545' ?>;">
                        <i class="bi bi-<?= $ordGrowth >= 0 ? 'graph-up-arrow' : 'graph-down-arrow' ?>"></i>
                    </div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number" style="color:<?= $ordGrowth >= 0 ? '#0B6B2F' : '#dc3545' ?>;"><?= ($ordGrowth >= 0 ? '+' : '') . $ordGrowth ?>%</span>
                        <span class="rp-stat-label">Order Growth</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rp-stat-card" style="border-left-color: #8B5CF6;">
                    <div class="rp-stat-icon" style="background:#f3eeff;color:#8B5CF6;"><i class="bi bi-calculator"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= formatPrice($avgOrderValue) ?></span>
                        <span class="rp-stat-label">Overall AOV</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rp-stat-card" style="border-left-color: #C9A227;">
                    <div class="rp-stat-icon" style="background:#fcf6e3;color:#C9A227;"><i class="bi bi-arrow-repeat"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= number_format($totalOrders) ?></span>
                        <span class="rp-stat-label">Total Orders</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- AOV Trend Chart -->
        <?php if (count($aovLabels) > 0): ?>
        <div class="rp-chart-box mb-4">
            <div class="rp-section-title"><i class="bi bi-graph-up"></i> Monthly AOV Trend (<?= date('Y') ?>)</div>
            <canvas id="aovTrendChart" height="200"></canvas>
        </div>
        <script>
        new Chart(document.getElementById('aovTrendChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($aovLabels) ?>,
                datasets: [{
                    label: 'Avg Order Value (RWF)',
                    data: <?= json_encode($aovData) ?>,
                    borderColor: '#8B5CF6',
                    backgroundColor: 'rgba(139,92,246,0.1)',
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#8B5CF6'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true, title: { display: true, text: 'AOV (RWF)' } } }
            }
        });
        </script>
        <?php endif; ?>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var ctx = document.getElementById('salesChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [<?php foreach ($salesData as $r) { echo "'" . htmlspecialchars($r['label'], ENT_QUOTES) . "',"; } ?>],
                datasets: [
                    {
                        label: 'Revenue',
                        data: [<?php foreach ($salesData as $r) { echo number_format((float) $r['revenue'], 2, '.', '') . ","; } ?>],
                        backgroundColor: '#0B6B2F',
                        borderRadius: 4,
                        order: 1,
                    },
                    {
                        label: 'Orders',
                        data: [<?php foreach ($salesData as $r) { echo (int) $r['orders'] . ","; } ?>],
                        type: 'line',
                        borderColor: '#C9A227',
                        backgroundColor: 'rgba(201, 162, 39, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#C9A227',
                        yAxisID: 'y1',
                        order: 0,
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: function(v) { return v.toLocaleString() + ' RWF'; } }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
