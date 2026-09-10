<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$pageTitle = 'Orders Report';

$pdo = getDbConnection();

$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');
$status = trim($_GET['status'] ?? '');

$whereClause = 'WHERE 1=1';
$params = [];

if ($from !== '') {
    $whereClause .= ' AND DATE(o.created_at) >= :from_date';
    $params[':from_date'] = $from;
}
if ($to !== '') {
    $whereClause .= ' AND DATE(o.created_at) <= :to_date';
    $params[':to_date'] = $to;
}
if ($status !== '') {
    $whereClause .= ' AND o.status = :status';
    $params[':status'] = $status;
}

$totalOrders      = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$pendingOrders    = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$processingOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn();
$shippedOrders    = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'shipped'")->fetchColumn();
$deliveredOrders  = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered'")->fetchColumn();
$cancelledOrders  = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'")->fetchColumn();

$stmt = $pdo->prepare("
    SELECT o.id, o.order_number, o.grand_total, o.status, o.payment_status, o.created_at,
           COALESCE(o.customer_name, u.full_name) AS customer_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    {$whereClause}
    ORDER BY o.created_at DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$statuses = [
    ORDER_PENDING    => ['label' => 'Pending',    'badge' => 'bg-secondary'],
    ORDER_PROCESSING => ['label' => 'Processing', 'badge' => 'bg-warning text-dark'],
    ORDER_SHIPPED    => ['label' => 'Shipped',    'badge' => 'bg-primary'],
    ORDER_DELIVERED  => ['label' => 'Delivered',  'badge' => 'bg-success'],
    ORDER_CANCELLED  => ['label' => 'Cancelled',  'badge' => 'bg-danger'],
];

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= SITE_URL ?>assets/css/reports/reports.css">

<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="rp-page-header">
            <div>
                <h1>Orders Report</h1>
                <p class="text-muted mb-0">Order statistics and detailed breakdown.</p>
            </div>
            <div class="rp-export-bar">
                <a href="<?= SITE_URL ?>admin/reports/export.php?type=orders&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&status=<?= urlencode($status) ?>&format=csv" class="btn btn-sm btn-success fw-semibold">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV
                </a>
                <a href="<?= SITE_URL ?>admin/reports/export.php?type=orders&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&status=<?= urlencode($status) ?>&format=excel" class="btn btn-sm btn-success fw-semibold">
                    <i class="bi bi-file-earmark-excel me-1"></i> Excel
                </a>
                <a href="javascript:window.print()" class="btn btn-sm btn-outline-secondary fw-semibold">
                    <i class="bi bi-printer me-1"></i> Print
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-4 col-md-2">
                <div class="rp-stat-card rp-stat-orders">
                    <div class="rp-stat-icon"><i class="bi bi-bag"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $totalOrders ?></span>
                        <span class="rp-stat-label">Total</span>
                    </div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="rp-stat-card rp-stat-pending">
                    <div class="rp-stat-icon"><i class="bi bi-clock"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $pendingOrders ?></span>
                        <span class="rp-stat-label">Pending</span>
                    </div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="rp-stat-card" style="border-left-color: #F59E0B;">
                    <div class="rp-stat-icon" style="background: #fff8e6; color: #F59E0B;"><i class="bi bi-arrow-repeat"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $processingOrders ?></span>
                        <span class="rp-stat-label">Processing</span>
                    </div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="rp-stat-card" style="border-left-color: #001F5B;">
                    <div class="rp-stat-icon" style="background: #e3e8f0; color: #001F5B;"><i class="bi bi-truck"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $shippedOrders ?></span>
                        <span class="rp-stat-label">Shipped</span>
                    </div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="rp-stat-card rp-stat-delivered">
                    <div class="rp-stat-icon"><i class="bi bi-check2-circle"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $deliveredOrders ?></span>
                        <span class="rp-stat-label">Delivered</span>
                    </div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="rp-stat-card rp-stat-lowstock">
                    <div class="rp-stat-icon"><i class="bi bi-x-circle"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $cancelledOrders ?></span>
                        <span class="rp-stat-label">Cancelled</span>
                    </div>
                </div>
            </div>
        </div>

        <form method="GET" class="rp-filters">
            <div>
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
            </div>
            <div>
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select" style="min-width: 140px;">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $key => $info): ?>
                        <option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>><?= $info['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn fw-semibold px-3" style="background: #001F5B; color: #fff;">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="<?= SITE_URL ?>admin/reports/orders.php" class="btn btn-outline-secondary fw-semibold px-3">
                    <i class="bi bi-x-circle me-1"></i> Clear
                </a>
            </div>
        </form>

        <div class="rp-table-wrap">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th class="text-end">Total</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) === 0): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No orders found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td><a href="<?= SITE_URL ?>admin/orders/view.php?id=<?= (int) $o['id'] ?>" class="fw-semibold" style="color: #001F5B;"><?= htmlspecialchars($o['order_number']) ?></a></td>
                                <td><?= htmlspecialchars($o['customer_name'] ?? '—') ?></td>
                                <td>
                                    <span class="badge <?= $statuses[$o['status']]['badge'] ?? 'bg-secondary' ?>">
                                        <?= htmlspecialchars(ucfirst($o['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-end fw-semibold" style="color: #0B6B2F;"><?= formatPrice((float) $o['grand_total']) ?></td>
                                <td class="text-muted"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
