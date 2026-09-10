<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/constants.php';

requireAdmin();

$pageTitle = 'Audit Logs';

$pdo = getDbConnection();

$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

// ─── Filter Inputs ─────────────────────────────────────────
$fromDate = trim($_GET['from'] ?? '');
$toDate   = trim($_GET['to'] ?? '');
$userFilter = trim($_GET['user'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$moduleFilter = trim($_GET['module'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');
$quickFilter  = trim($_GET['quick'] ?? '');
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

// ─── Quick Filter Dates ─────────────────────────────────────
if ($quickFilter === 'today') {
    $fromDate = $toDate = date('Y-m-d');
} elseif ($quickFilter === '7days') {
    $fromDate = date('Y-m-d', strtotime('-7 days'));
    $toDate   = date('Y-m-d');
} elseif ($quickFilter === '30days') {
    $fromDate = date('Y-m-d', strtotime('-30 days'));
    $toDate   = date('Y-m-d');
}

// ─── Build WHERE Clause ─────────────────────────────────────
$where  = [];
$params = [];

if ($fromDate !== '') {
    $where[] = 'DATE(al.created_at) >= :from_date';
    $params[':from_date'] = $fromDate;
}
if ($toDate !== '') {
    $where[] = 'DATE(al.created_at) <= :to_date';
    $params[':to_date'] = $toDate;
}
if ($userFilter !== '') {
    $where[] = 'al.user_id = :user_id';
    $params[':user_id'] = (int) $userFilter;
}
if ($roleFilter !== '') {
    $where[] = 'al.user_role = :user_role';
    $params[':user_role'] = $roleFilter;
}
if ($moduleFilter !== '') {
    $where[] = 'al.module = :module';
    $params[':module'] = $moduleFilter;
}
if ($actionFilter !== '') {
    $where[] = 'al.action = :action';
    $params[':action'] = $actionFilter;
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// ─── Count Total ────────────────────────────────────────────
$countSql = "SELECT COUNT(*) FROM audit_logs al {$whereClause}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int) $countStmt->fetchColumn();
$totalPages   = (int) ceil($totalRecords / $perPage);
$offset       = ($page - 1) * $perPage;

// ─── Fetch Records ──────────────────────────────────────────
$dataSql = "SELECT al.* FROM audit_logs al {$whereClause} ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset";
$dataStmt = $pdo->prepare($dataSql);
foreach ($params as $k => $v) {
    $dataStmt->bindValue($k, $v);
}
$dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();
$logs = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

// ─── Summary Stats ──────────────────────────────────────────
$todayCount = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$weekCount  = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1)")->fetchColumn();
$totalCount = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$failedLogins = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE module='auth' AND action='login_failed' AND DATE(created_at)=CURDATE()")->fetchColumn();

// ─── Filter Dropdowns ───────────────────────────────────────
$users = $pdo->query("SELECT DISTINCT al.user_id, al.user_name FROM audit_logs al WHERE al.user_id IS NOT NULL ORDER BY al.user_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$modules = $pdo->query("SELECT DISTINCT module FROM audit_logs ORDER BY module ASC")->fetchAll(PDO::FETCH_COLUMN);
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
$roles   = $pdo->query("SELECT DISTINCT user_role FROM audit_logs WHERE user_role != '' ORDER BY user_role ASC")->fetchAll(PDO::FETCH_COLUMN);

// ─── Export Logic ───────────────────────────────────────────
$export = trim($_GET['export'] ?? '');
if ($export !== '' && in_array($export, ['csv', 'excel', 'pdf'])) {

    $filename = 'audit-logs-' . date('Y-m-d');

    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $out = fopen('php://output', 'w');
    } elseif ($export === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        $out = fopen('php://output', 'w');
    } else {
        setFlashMessage('error', 'PDF export is not available. Use CSV or Excel.');
        redirect(SITE_URL . 'admin/audit/index.php');
        exit;
    }

    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Date', 'User', 'Role', 'Module', 'Action', 'Description', 'IP Address', 'Browser']);

    $exportParams = $params;
    $exportSql = "SELECT al.* FROM audit_logs al {$whereClause} ORDER BY al.created_at DESC";
    $exportStmt = $pdo->prepare($exportSql);
    $exportStmt->execute($exportParams);
    while ($row = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $row['created_at'],
            $row['user_name'],
            $row['user_role'],
            $row['module'],
            $row['action'],
            $row['description'],
            $row['ip_address'],
            $row['user_agent'],
        ]);
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<style>
.audit-stat-card {
    background: #fff; border-radius: 12px; padding: 1.25rem;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    border-left: 4px solid #001F5B; height: 100%;
}
.audit-stat-card .stat-icon { font-size: 1.8rem; color: #001F5B; margin-bottom: 0.5rem; }
.audit-stat-card .stat-number { font-size: 1.8rem; font-weight: 700; color: #001F5B; }
.audit-stat-card .stat-label { font-size: 0.82rem; color: #6B7280; text-transform: uppercase; letter-spacing: 0.3px; }
.audit-stat-card.gold { border-left-color: #C9A227; }
.audit-stat-card.gold .stat-icon, .audit-stat-card.gold .stat-number { color: #C9A227; }
.audit-stat-card.green { border-left-color: #0B6B2F; }
.audit-stat-card.green .stat-icon, .audit-stat-card.green .stat-number { color: #0B6B2F; }
.audit-stat-card.danger { border-left-color: #dc3545; }
.audit-stat-card.danger .stat-icon, .audit-stat-card.danger .stat-number { color: #dc3545; }
.audit-table th { background: #F5F6FA; color: #001F5B; font-weight: 600; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.3px; white-space: nowrap; }
.audit-table td { vertical-align: middle; font-size: 0.9rem; }
.role-badge { font-size: 0.75rem; padding: 0.2rem 0.6rem; border-radius: 50px; }
.audit-filters { background: #fff; border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 4px rgba(0,0,0,0.04); margin-bottom: 1.5rem; }
.audit-filters .form-label { font-weight: 600; font-size: 0.8rem; color: #333; margin-bottom: 0.25rem; }
.quick-filter-btn { font-size: 0.8rem; padding: 0.25rem 0.75rem; border-radius: 50px; }
.quick-filter-btn.active { background: #001F5B; color: #fff; border-color: #001F5B; }
</style>

<div class="admin-content-wrapper">
<main class="admin-content">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1" style="color:#001F5B;">Audit Logs</h1>
            <p class="text-muted mb-0">Track all user activities across the system.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="?export=csv<?= $fromDate ? '&from=' . urlencode($fromDate) : '' ?><?= $toDate ? '&to=' . urlencode($toDate) : '' ?>" class="btn btn-sm btn-success fw-semibold">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV
            </a>
            <a href="?export=excel<?= $fromDate ? '&from=' . urlencode($fromDate) : '' ?><?= $toDate ? '&to=' . urlencode($toDate) : '' ?>" class="btn btn-sm btn-success fw-semibold" style="background:#0B6B2F;border-color:#0B6B2F;">
                <i class="bi bi-file-earmark-excel me-1"></i> Excel
            </a>
            <a href="javascript:window.print()" class="btn btn-sm btn-outline-secondary fw-semibold">
                <i class="bi bi-printer me-1"></i> Print
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="audit-stat-card">
                <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                <div class="stat-number"><?= number_format($todayCount) ?></div>
                <div class="stat-label">Today's Activities</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="audit-stat-card gold">
                <div class="stat-icon"><i class="bi bi-calendar-week"></i></div>
                <div class="stat-number"><?= number_format($weekCount) ?></div>
                <div class="stat-label">This Week</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="audit-stat-card green">
                <div class="stat-icon"><i class="bi bi-activity"></i></div>
                <div class="stat-number"><?= number_format($totalCount) ?></div>
                <div class="stat-label">Total Activities</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="audit-stat-card danger">
                <div class="stat-icon"><i class="bi bi-shield-exclamation"></i></div>
                <div class="stat-number"><?= number_format($failedLogins) ?></div>
                <div class="stat-label">Failed Logins Today</div>
            </div>
        </div>
    </div>

    <div class="audit-filters">
        <form method="GET" class="row g-2 align-items-end">

            <div class="col-12 mb-2">
                <div class="d-flex gap-1">
                    <a href="?quick=today" class="btn btn-sm quick-filter-btn <?= $quickFilter === 'today' ? 'active' : 'btn-outline-secondary' ?>">Today</a>
                    <a href="?quick=7days" class="btn btn-sm quick-filter-btn <?= $quickFilter === '7days' ? 'active' : 'btn-outline-secondary' ?>">Last 7 Days</a>
                    <a href="?quick=30days" class="btn btn-sm quick-filter-btn <?= $quickFilter === '30days' ? 'active' : 'btn-outline-secondary' ?>">Last 30 Days</a>
                    <a href="<?= SITE_URL ?>admin/audit/index.php" class="btn btn-sm btn-outline-secondary quick-filter-btn">All</a>
                </div>
            </div>

            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($fromDate) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($toDate) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">User</label>
                <select name="user" class="form-select form-select-sm">
                    <option value="">All Users</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['user_id'] ?>" <?= $userFilter === (string)$u['user_id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['user_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Role</label>
                <select name="role" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= htmlspecialchars($r) ?>" <?= $roleFilter === $r ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($r)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Module</label>
                <select name="module" class="form-select form-select-sm">
                    <option value="">All Modules</option>
                    <?php foreach ($modules as $m): ?>
                        <option value="<?= htmlspecialchars($m) ?>" <?= $moduleFilter === $m ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($m)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Action</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>" <?= $actionFilter === $a ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($a)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <div>
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-sm fw-semibold px-3" style="background:#001F5B;color:#fff;">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                </div>
                <div>
                    <label class="form-label">&nbsp;</label>
                    <a href="<?= SITE_URL ?>admin/audit/index.php" class="btn btn-sm btn-outline-secondary fw-semibold px-3">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle audit-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP Address</th>
                    <th>Browser</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($logs) === 0): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No audit records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="text-nowrap" style="font-size:0.82rem;">
                                <a href="view.php?id=<?= (int) $log['id'] ?>" class="text-decoration-none">
                                    <?= date('M j, Y', strtotime($log['created_at'])) ?>
                                </a>
                                <br><small class="text-muted"><?= date('h:i A', strtotime($log['created_at'])) ?></small>
                            </td>
                            <td>
                                <span class="fw-semibold"><?= htmlspecialchars($log['user_name'] ?: '—') ?></span>
                                <?php if ($log['user_id']): ?>
                                    <br><small class="text-muted">#<?= (int) $log['user_id'] ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log['user_role'] === 'admin'): ?>
                                    <span class="badge bg-dark role-badge">Admin</span>
                                <?php elseif ($log['user_role'] === 'customer'): ?>
                                    <span class="badge bg-primary role-badge">Customer</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary role-badge"><?= htmlspecialchars(ucfirst($log['user_role'] ?: '—')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border role-badge"><?= htmlspecialchars(ucfirst($log['module'])) ?></span>
                            </td>
                            <td>
                                <?php
                                $badgeClass = 'bg-secondary';
                                if ($log['action'] === 'created' || $log['action'] === 'login') $badgeClass = 'bg-success';
                                elseif ($log['action'] === 'updated' || $log['action'] === 'password_changed') $badgeClass = 'bg-info text-dark';
                                elseif ($log['action'] === 'deleted' || $log['action'] === 'logout') $badgeClass = 'bg-danger';
                                elseif ($log['action'] === 'cancelled' || $log['action'] === 'login_failed') $badgeClass = 'bg-warning text-dark';
                                ?>
                                <span class="badge <?= $badgeClass ?> role-badge"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $log['action']))) ?></span>
                            </td>
                            <td style="max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars($log['description'] ?? '—') ?>
                            </td>
                            <td><code style="font-size:0.8rem;"><?= htmlspecialchars($log['ip_address']) ?></code></td>
                            <td style="max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size:0.78rem; color:#6B7280;">
                                <?= htmlspecialchars($log['user_agent'] ? substr($log['user_agent'], 0, 60) . '…' : '—') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav aria-label="Audit log pagination" class="mt-3">
        <ul class="pagination pagination-sm justify-content-center flex-wrap">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>&from=<?= urlencode($fromDate) ?>&to=<?= urlencode($toDate) ?>&user=<?= urlencode($userFilter) ?>&role=<?= urlencode($roleFilter) ?>&module=<?= urlencode($moduleFilter) ?>&action=<?= urlencode($actionFilter) ?>">Previous</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&from=<?= urlencode($fromDate) ?>&to=<?= urlencode($toDate) ?>&user=<?= urlencode($userFilter) ?>&role=<?= urlencode($roleFilter) ?>&module=<?= urlencode($moduleFilter) ?>&action=<?= urlencode($actionFilter) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>&from=<?= urlencode($fromDate) ?>&to=<?= urlencode($toDate) ?>&user=<?= urlencode($userFilter) ?>&role=<?= urlencode($roleFilter) ?>&module=<?= urlencode($moduleFilter) ?>&action=<?= urlencode($actionFilter) ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

    <div class="text-muted text-center mt-2" style="font-size:0.85rem;">
        Showing <?= count($logs) ?> of <?= number_format($totalRecords) ?> records
        <?php if ($totalPages > 1): ?>
            (Page <?= $page ?> of <?= $totalPages ?>)
        <?php endif; ?>
    </div>

</main>
</div>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
