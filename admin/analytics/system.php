<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$pageTitle = 'System Analytics';

$db = getDbConnection();
$ajax = (int) ($_GET['ajax'] ?? 0);

// Recent audit logs
$recentAudit = $db->query("
    SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);

// Failed login attempts (today)
$failedLogins = (int) $db->query("
    SELECT COUNT(*) FROM audit_logs WHERE module='auth' AND action='login_failed' AND DATE(created_at)=CURDATE()
")->fetchColumn();

// Total audit records
$totalAudit = (int) $db->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();

// Admin activities (last 7 days)
$adminActivities = (int) $db->query("
    SELECT COUNT(*) FROM audit_logs WHERE user_role='admin' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
")->fetchColumn();

// Recent inventory adjustments
$recentAdjustments = $db->query("
    SELECT im.*, p.name AS product_name, p.code AS product_code
    FROM inventory_movements im
    JOIN products p ON im.product_id = p.id
    WHERE im.reference_type='adjustment'
    ORDER BY im.created_at DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// System stats
$totalUsers = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalOrders = (int) $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalProducts = (int) $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$dbSize = (float) $db->query("
    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
    FROM information_schema.tables WHERE table_schema = DATABASE()
")->fetchColumn();

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
.askpi.purple { border-left-color:#8B5CF6; } .askpi.purple .num { color:#8B5CF6; }
.card-section { background:#fff; border-radius:12px; box-shadow:0 2px 6px rgba(0,0,0,0.05); padding:1.25rem; margin-bottom:1.5rem; }
.card-section h6 { color:#001F5B; font-weight:700; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.3px; margin-bottom:0.75rem; padding-bottom:0.5rem; border-bottom:2px solid #f0f0f0; }
</style>

<div class="admin-content-wrapper">
<main class="admin-content">

<div id="analyticsRefreshWrap">

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1" style="color:#001F5B;"><i class="bi bi-gear me-2"></i>System Analytics</h1>
        <p class="text-muted mb-0">System health, audit trail, and administrative activity.</p>
    </div>
    <a href="<?= SITE_URL ?>admin/analytics/dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="askpi"><div class="num"><?= number_format($totalAudit) ?></div><div class="lbl">Total Audit Records</div></div></div>
    <div class="col-6 col-md-3"><div class="askpi red"><div class="num"><?= number_format($failedLogins) ?></div><div class="lbl">Failed Logins Today</div></div></div>
    <div class="col-6 col-md-3"><div class="askpi gold"><div class="num"><?= number_format($adminActivities) ?></div><div class="lbl">Admin Activities (7d)</div></div></div>
    <div class="col-6 col-md-3"><div class="askpi purple"><div class="num"><?= number_format($dbSize, 1) ?> MB</div><div class="lbl">Database Size</div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card-section">
            <h6><i class="bi bi-journal-text me-1"></i> Recent Audit Logs</h6>
            <div class="table-responsive" style="max-height:360px;overflow-y:auto;">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead><tr><th>Date</th><th>User</th><th>Action</th><th>Module</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentAudit as $ra): ?>
                        <tr>
                            <td class="small text-nowrap"><?= date('M j, Y h:i A', strtotime($ra['created_at'])) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($ra['user_name'] ?: '—') ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($ra['action']) ?></span></td>
                            <td><?= htmlspecialchars(ucfirst($ra['module'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($recentAudit)===0): ?><tr><td colspan="4" class="text-center text-muted py-3">No audit logs.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-2"><a href="<?= SITE_URL ?>admin/audit/index.php" class="btn btn-sm btn-outline-primary">View All Audit Logs <i class="bi bi-arrow-right ms-1"></i></a></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-section">
            <h6><i class="bi bi-tools me-1"></i> Recent Inventory Adjustments</h6>
            <div class="table-responsive" style="max-height:360px;overflow-y:auto;">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead><tr><th>Date</th><th>Product</th><th class="text-end">Qty</th><th>Reason</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentAdjustments as $adj): ?>
                        <tr>
                            <td class="small text-nowrap"><?= date('M j, Y', strtotime($adj['created_at'])) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($adj['product_name']) ?> <small class="text-muted">(<?= htmlspecialchars($adj['product_code']) ?>)</small></td>
                            <td class="text-end fw-semibold <?= $adj['quantity']>0?'text-success':'text-danger' ?>"><?= $adj['quantity']>0?'+':'' ?><?= (int)$adj['quantity'] ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($adj['notes'] ?: '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($recentAdjustments)===0): ?><tr><td colspan="4" class="text-center text-muted py-3">No adjustments yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-2"><a href="<?= SITE_URL ?>admin/inventory/index.php" class="btn btn-sm btn-outline-primary">View Inventory <i class="bi bi-arrow-right ms-1"></i></a></div>
        </div>
    </div>
</div>

<div class="card-section">
    <h6><i class="bi bi-info-circle me-1"></i> System Overview</h6>
    <div class="row g-3">
        <div class="col-md-3 col-6">
            <div class="p-3 bg-light rounded-3 text-center">
                <div class="fw-bold fs-4" style="color:#001F5B;"><?= number_format($totalUsers) ?></div>
                <div class="small text-muted">Total Users</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="p-3 bg-light rounded-3 text-center">
                <div class="fw-bold fs-4" style="color:#001F5B;"><?= number_format($totalOrders) ?></div>
                <div class="small text-muted">Total Orders</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="p-3 bg-light rounded-3 text-center">
                <div class="fw-bold fs-4" style="color:#001F5B;"><?= number_format($totalProducts) ?></div>
                <div class="small text-muted">Total Products</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="p-3 bg-light rounded-3 text-center">
                <div class="fw-bold fs-4" style="color:#001F5B;"><?= number_format($dbSize, 1) ?> MB</div>
                <div class="small text-muted">Database Size</div>
            </div>
        </div>
    </div>
</div>

</div>
</main>
</div>

<script src="<?= SITE_URL ?>assets/js/analytics.js"></script>

<?php if (!$ajax) require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
