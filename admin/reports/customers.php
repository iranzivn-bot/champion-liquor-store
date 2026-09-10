<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$pageTitle = 'Customers Report';

$pdo = getDbConnection();

$from  = trim($_GET['from'] ?? '');
$to    = trim($_GET['to'] ?? '');
$search = trim($_GET['search'] ?? '');
$sort  = trim($_GET['sort'] ?? 'spent_desc');

$totalCustomers  = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$newCustomers    = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$activeCustomers = (int) $pdo->query("SELECT COUNT(DISTINCT user_id) FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$avgSpentPerCustomer = $totalCustomers > 0
    ? (float) $pdo->query("SELECT COALESCE(SUM(grand_total), 0) / (SELECT COUNT(*) FROM users WHERE role = 'customer') FROM orders WHERE status = 'delivered'")->fetchColumn()
    : 0;

$whereClause = "WHERE u.role = 'customer'";
$params = [];

if ($search !== '') {
    $whereClause .= ' AND (u.full_name LIKE :search OR u.email LIKE :search2)';
    $params[':search'] = "%{$search}%";
    $params[':search2'] = "%{$search}%";
}

switch ($sort) {
    case 'name_asc':  $orderBy = 'u.full_name ASC';  break;
    case 'name_desc': $orderBy = 'u.full_name DESC'; break;
    case 'orders_desc': $orderBy = 'total_orders DESC'; break;
    case 'spent_asc': $orderBy = 'total_spent ASC'; break;
    default: $sort = 'spent_desc'; $orderBy = 'total_spent DESC'; break;
}

$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.email, u.phone, u.created_at,
           COUNT(DISTINCT o.id) AS total_orders,
           COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN o.grand_total ELSE 0 END), 0) AS total_spent
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    {$whereClause}
    GROUP BY u.id, u.full_name, u.email, u.phone, u.created_at
    ORDER BY {$orderBy}
");
$stmt->execute($params);
$customers = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= SITE_URL ?>assets/css/reports/reports.css">

<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="rp-page-header">
            <div>
                <h1>Customers Report</h1>
                <p class="text-muted mb-0">Customer base analysis and top spenders.</p>
            </div>
            <div class="rp-export-bar">
                <a href="<?= SITE_URL ?>admin/reports/export.php?type=customers&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&format=csv" class="btn btn-sm btn-success fw-semibold">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV
                </a>
                <a href="<?= SITE_URL ?>admin/reports/export.php?type=customers&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&format=excel" class="btn btn-sm btn-success fw-semibold">
                    <i class="bi bi-file-earmark-excel me-1"></i> Excel
                </a>
                <a href="javascript:window.print()" class="btn btn-sm btn-outline-secondary fw-semibold">
                    <i class="bi bi-printer me-1"></i> Print
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="rp-stat-card rp-stat-customers">
                    <div class="rp-stat-icon"><i class="bi bi-people"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $totalCustomers ?></span>
                        <span class="rp-stat-label">Total Customers</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rp-stat-card rp-stat-delivered">
                    <div class="rp-stat-icon"><i class="bi bi-person-plus"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $newCustomers ?></span>
                        <span class="rp-stat-label">New (30 Days)</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rp-stat-card rp-stat-orders">
                    <div class="rp-stat-icon"><i class="bi bi-cart-check"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= $activeCustomers ?></span>
                        <span class="rp-stat-label">Active (30 Days)</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rp-stat-card rp-stat-revenue">
                    <div class="rp-stat-icon"><i class="bi bi-cash-coin"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= formatPrice($avgSpentPerCustomer) ?></span>
                        <span class="rp-stat-label">Avg Spent/Customer</span>
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
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name or email..."
                       value="<?= htmlspecialchars($search) ?>" style="min-width: 180px;">
            </div>
            <div>
                <label class="form-label">Sort By</label>
                <select name="sort" class="form-select" style="min-width: 150px;">
                    <option value="spent_desc" <?= $sort === 'spent_desc' ? 'selected' : '' ?>>Highest Spent</option>
                    <option value="spent_asc" <?= $sort === 'spent_asc' ? 'selected' : '' ?>>Lowest Spent</option>
                    <option value="orders_desc" <?= $sort === 'orders_desc' ? 'selected' : '' ?>>Most Orders</option>
                    <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A-Z</option>
                    <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z-A</option>
                </select>
            </div>
            <div>
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn fw-semibold px-3" style="background: #001F5B; color: #fff;">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="<?= SITE_URL ?>admin/reports/customers.php" class="btn btn-outline-secondary fw-semibold px-3">
                    <i class="bi bi-x-circle me-1"></i> Clear
                </a>
            </div>
        </form>

        <div class="rp-table-wrap">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Email</th>
                        <th class="text-center">Total Orders</th>
                        <th class="text-end">Total Spent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($customers) === 0): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No customers found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($c['full_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($c['email'] ?? '—') ?></td>
                                <td class="text-center"><?= (int) $c['total_orders'] ?></td>
                                <td class="text-end fw-semibold" style="color: #0B6B2F;"><?= formatPrice((float) $c['total_spent']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             EXTENSION: Customer Analytics
             ═══════════════════════════════════════════════════════════════ -->
        <?php
        // Repeat customers (have > 1 order)
        $repeatStmt = $pdo->prepare("
            SELECT COUNT(*) FROM (
                SELECT user_id FROM orders
                WHERE status = 'delivered'
                GROUP BY user_id HAVING COUNT(*) > 1
            ) AS repeat_customers
        ");
        $repeatStmt->execute();
        $repeatCustomers = (int)$repeatStmt->fetchColumn();

        // New customers this month
        $newCustStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
        $newCustStmt->execute([CUSTOMER_ROLE]);
        $newCustomers = (int)$newCustStmt->fetchColumn();

        // Inactive customers (no orders in last 90 days)
        $inactiveStmt = $pdo->prepare("
            SELECT COUNT(*) FROM users WHERE role = ? AND id NOT IN (
                SELECT DISTINCT user_id FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            )
        ");
        $inactiveStmt->execute([CUSTOMER_ROLE]);
        $inactiveCustomers = (int)$inactiveStmt->fetchColumn();

        // Average customer spend (delivered orders only)
        $avgSpendStmt = $pdo->prepare("
            SELECT COALESCE(AVG(customer_total), 0) FROM (
                SELECT SUM(grand_total) AS customer_total
                FROM orders WHERE status = 'delivered'
                GROUP BY user_id
            ) AS totals
        ");
        $avgSpendStmt->execute();
        $avgCustomerSpend = (float)$avgSpendStmt->fetchColumn();

        // Customers with most orders (repeat loyalty)
        $topRepeat = $pdo->prepare("
            SELECT u.full_name, u.email, COUNT(*) AS order_count, COALESCE(SUM(o.grand_total), 0) AS total_spent
            FROM orders o JOIN users u ON o.user_id = u.id
            WHERE o.status = 'delivered'
            GROUP BY u.id, u.full_name, u.email
            HAVING order_count > 1
            ORDER BY order_count DESC
            LIMIT 10
        ");
        $topRepeat->execute();
        $topRepeatCustomers = $topRepeat->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="rp-stat-card" style="border-left-color:#0B6B2F;">
                    <div class="rp-stat-icon" style="background:#e8f5e9;color:#0B6B2F;"><i class="bi bi-arrow-repeat"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= number_format($repeatCustomers) ?></span>
                        <span class="rp-stat-label">Repeat Customers</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rp-stat-card" style="border-left-color:#001F5B;">
                    <div class="rp-stat-icon" style="background:#e3e8f0;color:#001F5B;"><i class="bi bi-person-plus"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= number_format($newCustomers) ?></span>
                        <span class="rp-stat-label">New This Month</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rp-stat-card" style="border-left-color:#dc3545;">
                    <div class="rp-stat-icon" style="background:#fde8ea;color:#dc3545;"><i class="bi bi-person-x"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= number_format($inactiveCustomers) ?></span>
                        <span class="rp-stat-label">Inactive (90 days)</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rp-stat-card" style="border-left-color:#8B5CF6;">
                    <div class="rp-stat-icon" style="background:#f3eeff;color:#8B5CF6;"><i class="bi bi-cash-coin"></i></div>
                    <div class="rp-stat-body">
                        <span class="rp-stat-number"><?= formatPrice($avgCustomerSpend) ?></span>
                        <span class="rp-stat-label">Avg Customer Spend</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Most Loyal / Repeat Customers -->
        <div class="rp-section-title"><i class="bi bi-arrow-repeat"></i> Most Loyal Customers (Repeat Buyers)</div>
        <div class="rp-table-wrap">
            <table class="table table-hover">
                <thead><tr><th>Customer</th><th>Email</th><th class="text-center">Orders</th><th class="text-end">Total Spent</th></tr></thead>
                <tbody>
                <?php if ($topRepeatCustomers): ?>
                <?php foreach ($topRepeatCustomers as $rc): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($rc['full_name']) ?></td>
                    <td><?= htmlspecialchars($rc['email']) ?></td>
                    <td class="text-center"><span class="badge bg-primary"><?= (int)$rc['order_count'] ?></span></td>
                    <td class="text-end fw-semibold" style="color:#0B6B2F;"><?= formatPrice((float)$rc['total_spent']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No repeat customers yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
