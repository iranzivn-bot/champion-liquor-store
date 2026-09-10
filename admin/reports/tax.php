<?php
/**
 * Tax Report
 *
 * Total Tax Collected, Tax Per Month, Tax Per Order, Tax Summary
 *
 * Champion Liquor Store Ltd — Enterprise Reporting
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$pageTitle = 'Tax Report';
require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= SITE_URL ?>assets/css/reports/reports.css">
<style>
.chart-container { position:relative; height:300px; width:100%; }
</style>

<div class="admin-content-wrapper">
<main class="admin-content">

<?php
$db = getDbConnection();

$fromDate = $_GET['from'] ?? '';
$toDate   = $_GET['to'] ?? '';
$paymentFilter = $_GET['payment_status'] ?? '';

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
if ($paymentFilter !== '' && in_array($paymentFilter, ['unpaid','paid','refunded'], true)) {
    $where .= " AND o.payment_status = ?";
    $params[] = $paymentFilter;
}

// ─── Tax Summary ─────────────────────────────────────────────────────
$taxSummary = $db->prepare("
    SELECT
        COALESCE(SUM(o.tax), 0) AS total_tax,
        COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.tax ELSE 0 END), 0) AS paid_tax,
        COALESCE(SUM(CASE WHEN o.payment_status = 'unpaid' THEN o.tax ELSE 0 END), 0) AS unpaid_tax,
        COALESCE(SUM(o.grand_total), 0) AS total_revenue,
        COUNT(*) AS total_orders,
        COALESCE(SUM(CASE WHEN o.tax > 0 THEN 1 ELSE 0 END), 0) AS taxable_orders
    FROM orders o
    $where
");
$taxSummary->execute($params);
$ts = $taxSummary->fetch(PDO::FETCH_ASSOC);

$totalTax     = (float)$ts['total_tax'];
$paidTax      = (float)$ts['paid_tax'];
$unpaidTax    = (float)$ts['unpaid_tax'];
$totalTaxRev  = (float)$ts['total_revenue'];
$totalTaxOrds = (int)$ts['total_orders'];
$taxableOrds  = (int)$ts['taxable_orders'];
$taxRate      = $totalTaxRev > 0 ? round($totalTax / $totalTaxRev * 100, 2) : 0;

// ─── Tax Per Month (current year) ────────────────────────────────────
$taxPerMonth = $db->query("
    SELECT MONTH(created_at) AS m,
           COALESCE(SUM(tax), 0) AS tax,
           COUNT(*) AS orders
    FROM orders WHERE YEAR(created_at) = YEAR(CURDATE()) AND status != 'cancelled'
    GROUP BY MONTH(created_at) ORDER BY m ASC
")->fetchAll(PDO::FETCH_ASSOC);

$monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$taxMonthData = array_fill(0, 12, 0);
$taxMonthOrders = array_fill(0, 12, 0);
foreach ($taxPerMonth as $tm) {
    $idx = (int)$tm['m'] - 1;
    $taxMonthData[$idx] = (float)$tm['tax'];
    $taxMonthOrders[$idx] = (int)$tm['orders'];
}

// ─── Tax Per Order (recent) ──────────────────────────────────────────
$taxPerOrder = $db->prepare("
    SELECT o.order_number, COALESCE(o.customer_name, u.full_name) AS customer,
           o.grand_total, o.tax, o.payment_status, o.created_at
    FROM orders o JOIN users u ON o.user_id = u.id
    $where AND o.tax > 0
    ORDER BY o.created_at DESC
    LIMIT 50
");
$taxPerOrder->execute($params);
$taxOrders = $taxPerOrder->fetchAll(PDO::FETCH_ASSOC);

// ─── Payment Method Tax Breakdown ────────────────────────────────────
$taxByMethod = $db->prepare("
    SELECT o.payment_method,
           COALESCE(SUM(o.tax), 0) AS tax,
           COUNT(*) AS orders
    FROM orders o
    $where
    GROUP BY o.payment_method
    ORDER BY tax DESC
");
$taxByMethod->execute($params);
$taxMethods = $taxByMethod->fetchAll(PDO::FETCH_ASSOC);

// Chart data
$taxMethodLabels = []; $taxMethodData = [];
foreach ($taxMethods as $tm) {
    $taxMethodLabels[] = ucfirst(str_replace('_', ' ', $tm['payment_method']));
    $taxMethodData[] = (float)$tm['tax'];
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="fs-3 fw-bold" style="color:var(--admin-primary);">Tax Report</h1>
        <p class="text-muted">Tax collection analysis and breakdown.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= SITE_URL ?>admin/reports/export.php?type=tax&format=csv" class="btn btn-outline-success btn-sm fw-semibold"><i class="bi bi-filetype-csv"></i> CSV</a>
        <a href="<?= SITE_URL ?>admin/reports/export.php?type=tax&format=excel" class="btn btn-outline-success btn-sm fw-semibold"><i class="bi bi-file-earmark-excel"></i> Excel</a>
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
    <div>
        <label class="form-label">Payment Status</label>
        <select name="payment_status" class="form-select form-select-sm" style="width:140px;">
            <option value="">All</option>
            <option value="paid" <?= $paymentFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
            <option value="unpaid" <?= $paymentFilter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
            <option value="refunded" <?= $paymentFilter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
        </select>
    </div>
    <div style="display:flex;gap:4px;">
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Apply</button>
        <a href="<?= SITE_URL ?>admin/reports/tax.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
    </div>
</form>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="rp-stat-card rp-stat-revenue">
            <div class="rp-stat-icon"><i class="bi bi-receipt"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= formatPrice($totalTax) ?></span>
                <span class="rp-stat-label">Total Tax Collected</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rp-stat-card rp-stat-orders">
            <div class="rp-stat-icon"><i class="bi bi-check-circle"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= formatPrice($paidTax) ?></span>
                <span class="rp-stat-label">Paid Tax</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rp-stat-card" style="border-left-color:#F59E0B;">
            <div class="rp-stat-icon" style="background:#fff8e6;color:#F59E0B;"><i class="bi bi-hourglass"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= formatPrice($unpaidTax) ?></span>
                <span class="rp-stat-label">Unpaid Tax</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rp-stat-card" style="border-left-color:#8B5CF6;">
            <div class="rp-stat-icon" style="background:#f3eeff;color:#8B5CF6;"><i class="bi bi-percent"></i></div>
            <div class="rp-stat-body">
                <span class="rp-stat-number"><?= $taxRate ?>%</span>
                <span class="rp-stat-label">Effective Tax Rate</span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Tax by Month Chart -->
    <div class="col-md-6">
        <div class="rp-chart-box">
            <div class="rp-section-title"><i class="bi bi-bar-chart"></i> Tax Per Month (<?= date('Y') ?>)</div>
            <div class="chart-container">
                <canvas id="taxMonthChart"></canvas>
            </div>
        </div>
    </div>
    <!-- Tax by Payment Method -->
    <div class="col-md-6">
        <div class="rp-chart-box">
            <div class="rp-section-title"><i class="bi bi-credit-card"></i> Tax by Payment Method</div>
            <div class="chart-container">
                <canvas id="taxMethodChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Tax Summary Table -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="rp-section-title"><i class="bi bi-table"></i> Tax Summary</div>
        <div class="rp-table-wrap">
            <table class="table">
                <thead><tr><th>Metric</th><th class="text-end">Value</th></tr></thead>
                <tbody>
                    <tr><td>Total Tax Collected</td><td class="text-end fw-semibold"><?= formatPrice($totalTax) ?></td></tr>
                    <tr><td>Total Revenue (Taxable)</td><td class="text-end"><?= formatPrice($totalTaxRev) ?></td></tr>
                    <tr><td>Total Orders</td><td class="text-end"><?= number_format($totalTaxOrds) ?></td></tr>
                    <tr><td>Taxable Orders</td><td class="text-end"><?= number_format($taxableOrds) ?></td></tr>
                    <tr><td>Average Tax Per Order</td><td class="text-end fw-semibold"><?= $totalTaxOrds > 0 ? formatPrice($totalTax / $totalTaxOrds) : formatPrice(0) ?></td></tr>
                    <tr><td>Effective Tax Rate</td><td class="text-end fw-semibold"><?= $taxRate ?>%</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="rp-section-title"><i class="bi bi-list-columns"></i> Tax by Payment Method</div>
        <div class="rp-table-wrap">
            <table class="table">
                <thead><tr><th>Payment Method</th><th class="text-end">Orders</th><th class="text-end">Tax</th></tr></thead>
                <tbody>
                    <?php foreach ($taxMethods as $tm): ?>
                    <tr>
                        <td><?= ucfirst(str_replace('_', ' ', htmlspecialchars($tm['payment_method']))) ?></td>
                        <td class="text-end"><?= number_format((int)$tm['orders']) ?></td>
                        <td class="text-end fw-semibold"><?= formatPrice((float)$tm['tax']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Tax Per Order Table -->
<div class="rp-section-title"><i class="bi bi-receipt-cutoff"></i> Tax Per Order (Recent)</div>
<div class="rp-table-wrap">
    <table class="table table-hover">
        <thead><tr><th>Order</th><th>Customer</th><th class="text-end">Total</th><th class="text-end">Tax</th><th class="text-end">Tax %</th><th>Payment</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($taxOrders as $to): ?>
            <tr>
                <td><a href="<?= SITE_URL ?>admin/orders/view.php?id=<?= (int)$to['order_number'] ?>" class="text-primary"><?= htmlspecialchars($to['order_number']) ?></a></td>
                <td><?= htmlspecialchars($to['customer']) ?></td>
                <td class="text-end"><?= formatPrice((float)$to['grand_total']) ?></td>
                <td class="text-end fw-semibold"><?= formatPrice((float)$to['tax']) ?></td>
                <td class="text-end"><?= (float)$to['grand_total'] > 0 ? round((float)$to['tax'] / (float)$to['grand_total'] * 100, 1) : 0 ?>%</td>
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
    new Chart(document.getElementById('taxMonthChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthLabels) ?>,
            datasets: [{
                label: 'Tax (RWF)',
                data: <?= json_encode($taxMonthData) ?>,
                backgroundColor: 'rgba(139,92,246,0.75)',
                borderColor: '#8B5CF6',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, title: { display: true, text: 'Tax (RWF)' } } }
        }
    });

    new Chart(document.getElementById('taxMethodChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($taxMethodLabels) ?>,
            datasets: [{
                data: <?= json_encode($taxMethodData) ?>,
                backgroundColor: ['#001F5B','#C9A227','#0B6B2F','#8B5CF6'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } } }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
