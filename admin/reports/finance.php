<?php
/**
 * Financial Report
 *
 * Gross Revenue, Shipping, Tax, Discount, Refund, Net Revenue
 *
 * Champion Liquor Store Ltd — Enterprise Reporting
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$pageTitle = 'Financial Report';
require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= SITE_URL ?>assets/css/reports/reports.css">
<style>
.fin-table { background:#fff; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.08); overflow:hidden; }
.fin-table .table { margin-bottom:0; }
.fin-table .table thead th { background:#001F5B; color:#fff; font-size:0.82rem; text-transform:uppercase; letter-spacing:0.5px; padding:0.75rem 1rem; border:none; }
.fin-table .table tbody td { padding:0.7rem 1rem; font-size:0.88rem; }
.fin-summary-row { font-weight:700; background:#f8f9fa; border-top:2px solid #001F5B; }
.chart-container { position:relative; height:300px; width:100%; }
</style>

<div class="admin-content-wrapper">
<main class="admin-content">

<?php
$db = getDbConnection();

// Filters
$fromDate = $_GET['from'] ?? '';
$toDate   = $_GET['to'] ?? '';

$where = "WHERE o.status != ?";
$params = [ORDER_CANCELLED];

if ($fromDate !== '') {
    $where .= " AND DATE(o.created_at) >= ?";
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $where .= " AND DATE(o.created_at) <= ?";
    $params[] = $toDate;
}

// Financial totals
$finStmt = $db->prepare("
    SELECT
        COALESCE(SUM(o.grand_total), 0) AS gross_revenue,
        COALESCE(SUM(o.shipping), 0)     AS shipping_revenue,
        COALESCE(SUM(o.tax), 0)          AS tax_collected,
        COUNT(*)                          AS total_orders,
        COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.grand_total ELSE 0 END), 0) AS paid_revenue,
        COALESCE(SUM(CASE WHEN o.payment_status = 'refunded' THEN o.grand_total ELSE 0 END), 0) AS refund_amount
    FROM orders o
    $where
");
$finStmt->execute($params);
$fin = $finStmt->fetch(PDO::FETCH_ASSOC);

$grossRevenue   = (float)$fin['gross_revenue'];
$shippingRev    = (float)$fin['shipping_revenue'];
$taxCollected   = (float)$fin['tax_collected'];
$totalFinOrders = (int)$fin['total_orders'];
$paidRevenue    = (float)$fin['paid_revenue'];
$refundAmount   = (float)$fin['refund_amount'];
$netRevenue     = $grossRevenue - $refundAmount;
$discountAmount = 0.00; // No discount column in orders table yet

// Paid vs unpaid breakdown
$payStmt = $db->prepare("
    SELECT o.payment_status, COUNT(*) AS cnt, COALESCE(SUM(o.grand_total),0) AS total
    FROM orders o $where GROUP BY o.payment_status
");
$payStmt->execute($params);
$paymentBreakdown = $payStmt->fetchAll(PDO::FETCH_ASSOC);

// Top financial orders (largest grand_total)
$topFinOrders = $db->prepare("
    SELECT o.order_number, COALESCE(o.customer_name, u.full_name) AS customer,
           o.grand_total, o.tax, o.shipping, o.payment_status, o.created_at
    FROM orders o JOIN users u ON o.user_id = u.id
    $where ORDER BY o.grand_total DESC LIMIT 20
");
$topFinOrders->execute($params);
$topOrders = $topFinOrders->fetchAll(PDO::FETCH_ASSOC);

// Monthly financial trend (current year)
$monthlyFin = $db->query("
    SELECT MONTH(created_at) AS m,
           COALESCE(SUM(grand_total),0) AS gross,
           COALESCE(SUM(shipping),0) AS shipping,
           COALESCE(SUM(tax),0) AS tax
    FROM orders WHERE YEAR(created_at)=YEAR(CURDATE()) AND status!='cancelled'
    GROUP BY MONTH(created_at) ORDER BY m ASC
")->fetchAll(PDO::FETCH_ASSOC);

$monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$monthGross = array_fill(0,12,0); $monthShipping = array_fill(0,12,0); $monthTax = array_fill(0,12,0);
foreach ($monthlyFin as $r) {
    $idx = (int)$r['m'] - 1;
    $monthGross[$idx] = (float)$r['gross'];
    $monthShipping[$idx] = (float)$r['shipping'];
    $monthTax[$idx] = (float)$r['tax'];
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="fs-3 fw-bold" style="color:var(--admin-primary);">Financial Report</h1>
        <p class="text-muted">Revenue, tax, shipping, and payment analysis.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= SITE_URL ?>admin/reports/export.php?type=finance&format=csv<?= $fromDate ? '&from='.$fromDate : '' ?><?= $toDate ? '&to='.$toDate : '' ?>" class="btn btn-outline-success btn-sm fw-semibold"><i class="bi bi-filetype-csv"></i> CSV</a>
        <a href="<?= SITE_URL ?>admin/reports/export.php?type=finance&format=excel<?= $fromDate ? '&from='.$fromDate : '' ?><?= $toDate ? '&to='.$toDate : '' ?>" class="btn btn-outline-success btn-sm fw-semibold"><i class="bi bi-file-earmark-excel"></i> Excel</a>
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
        <a href="<?= SITE_URL ?>admin/reports/finance.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
    </div>
</form>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="rp-stat-card rp-stat-revenue">
            <div class="rp-stat-icon"><i class="bi bi-cash-stack"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= formatPrice($grossRevenue) ?></span>
                <span class="rp-stat-label">Gross Revenue</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="rp-stat-card rp-stat-orders">
            <div class="rp-stat-icon"><i class="bi bi-truck"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= formatPrice($shippingRev) ?></span>
                <span class="rp-stat-label">Shipping Revenue</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="rp-stat-card" style="border-left-color:#8B5CF6;">
            <div class="rp-stat-icon" style="background:#f3eeff;color:#8B5CF6;"><i class="bi bi-receipt"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= formatPrice($taxCollected) ?></span>
                <span class="rp-stat-label">Tax Collected</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="rp-stat-card" style="border-left-color:#EF4444;">
            <div class="rp-stat-icon" style="background:#fef2f2;color:#EF4444;"><i class="bi bi-arrow-return-left"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= formatPrice($refundAmount) ?></span>
                <span class="rp-stat-label">Refund Amount</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="rp-stat-card" style="border-left-color:#F59E0B;">
            <div class="rp-stat-icon" style="background:#fff8e6;color:#F59E0B;"><i class="bi bi-percent"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= formatPrice($discountAmount) ?></span>
                <span class="rp-stat-label">Discounts</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="rp-stat-card" style="border-left-color:#0B6B2F;">
            <div class="rp-stat-icon" style="background:#e8f5e9;color:#0B6B2F;"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= formatPrice($netRevenue) ?></span>
                <span class="rp-stat-label">Net Revenue</span>
            </div>
        </div>
    </div>
</div>

<!-- Breakdown Table -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="rp-section-title"><i class="bi bi-table"></i> Financial Summary</div>
        <div class="fin-table">
            <table class="table">
                <thead><tr><th>Metric</th><th class="text-end">Amount (RWF)</th><th class="text-end">% of Gross</th></tr></thead>
                <tbody>
                    <?php
                    $items = [
                        ['Gross Revenue', $grossRevenue, 100],
                        ['Shipping Revenue', $shippingRev, $grossRevenue > 0 ? round($shippingRev/$grossRevenue*100,1) : 0],
                        ['Tax Collected', $taxCollected, $grossRevenue > 0 ? round($taxCollected/$grossRevenue*100,1) : 0],
                        ['Discounts', -$discountAmount, $grossRevenue > 0 ? round(-$discountAmount/$grossRevenue*100,1) : 0],
                        ['Refunds', -$refundAmount, $grossRevenue > 0 ? round(-$refundAmount/$grossRevenue*100,1) : 0],
                    ];
                    foreach ($items as $i):
                    ?>
                    <tr>
                        <td><?= $i[0] ?></td>
                        <td class="text-end fw-semibold"><?= formatPrice($i[1]) ?></td>
                        <td class="text-end"><?= $i[2] ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="fin-summary-row">
                        <td>Net Revenue</td>
                        <td class="text-end"><?= formatPrice($netRevenue) ?></td>
                        <td class="text-end"><?= $grossRevenue > 0 ? round($netRevenue/$grossRevenue*100,1) : 0 ?>%</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payment Breakdown -->
    <div class="col-md-6">
        <div class="rp-section-title"><i class="bi bi-credit-card"></i> Payment Status Breakdown</div>
        <div class="fin-table">
            <table class="table">
                <thead><tr><th>Status</th><th class="text-end">Orders</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                    <?php foreach ($paymentBreakdown as $pb): ?>
                    <tr>
                        <td><span class="badge bg-<?= match($pb['payment_status']){'paid'=>'success','unpaid'=>'danger','refunded'=>'warning','default'=>'secondary'} ?>"><?= ucfirst($pb['payment_status']) ?></span></td>
                        <td class="text-end"><?= number_format((int)$pb['cnt']) ?></td>
                        <td class="text-end fw-semibold"><?= formatPrice((float)$pb['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="fin-summary-row">
                        <td>Total</td>
                        <td class="text-end"><?= number_format($totalFinOrders) ?></td>
                        <td class="text-end"><?= formatPrice($grossRevenue) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Monthly Financial Chart -->
<div class="rp-section-title"><i class="bi bi-bar-chart-line"></i> Monthly Financial Breakdown (<?= date('Y') ?>)</div>
<div class="rp-chart-box">
    <div class="chart-container">
        <canvas id="monthlyFinChart"></canvas>
    </div>
</div>

<!-- Top Financial Orders Table (visible on print) -->
<div class="rp-section-title mt-4"><i class="bi bi-list-ol"></i> Top Orders by Value</div>
<div class="fin-table">
    <table class="table table-hover">
        <thead><tr><th>Order</th><th>Customer</th><th>Tax</th><th>Shipping</th><th class="text-end">Total</th><th>Payment</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($topOrders as $to): ?>
        <tr>
            <td><a href="<?= SITE_URL ?>admin/orders/view.php?id=<?= (int)$to['order_number'] ?? '#' ?>" class="text-primary"><?= htmlspecialchars($to['order_number']) ?></a></td>
            <td><?= htmlspecialchars($to['customer']) ?></td>
            <td><?= formatPrice((float)$to['tax']) ?></td>
            <td><?= formatPrice((float)$to['shipping']) ?></td>
            <td class="text-end fw-semibold"><?= formatPrice((float)$to['grand_total']) ?></td>
            <td><span class="badge bg-<?= $to['payment_status']==='paid'?'success':'danger' ?>"><?= ucfirst($to['payment_status']) ?></span></td>
            <td><?= date('d M Y', strtotime($to['created_at'])) ?></td>
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
    new Chart(document.getElementById('monthlyFinChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthLabels) ?>,
            datasets: [
                { label: 'Gross Revenue', data: <?= json_encode($monthGross) ?>, backgroundColor: 'rgba(0,31,91,0.75)', borderRadius: 4 },
                { label: 'Shipping', data: <?= json_encode($monthShipping) ?>, backgroundColor: 'rgba(201,162,39,0.75)', borderRadius: 4 },
                { label: 'Tax', data: <?= json_encode($monthTax) ?>, backgroundColor: 'rgba(11,107,47,0.75)', borderRadius: 4 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true, title: { display: true, text: 'Amount (RWF)' } }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
