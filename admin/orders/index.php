<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();

$pageTitle = 'Orders';

$pdo = getDbConnection();

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$conditions = [];
$params     = [];

if ($search !== '') {
    $conditions[] = '(o.order_number LIKE :search1 OR u.full_name LIKE :search2 OR u.email LIKE :search3)';
    $params[':search1'] = "%{$search}%";
    $params[':search2'] = "%{$search}%";
    $params[':search3'] = "%{$search}%";
}

if ($status !== '' && in_array($status, [ORDER_PENDING, ORDER_CONFIRMED, ORDER_PROCESSING, ORDER_SHIPPED, ORDER_DELIVERED, ORDER_CANCELLED], true)) {
    $conditions[] = 'o.status = :status';
    $params[':status'] = $status;
}

$where = '';
if (count($conditions) > 0) {
    $where = 'WHERE ' . implode(' AND ', $conditions);
}

$countSql  = "SELECT COUNT(*) FROM orders o JOIN users u ON o.user_id = u.id {$where}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total      = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$dataSql  = "SELECT o.*, u.full_name, u.email
             FROM orders o
             JOIN users u ON o.user_id = u.id
             {$where}
             ORDER BY o.created_at DESC
             LIMIT :limit OFFSET :offset";
$dataStmt = $pdo->prepare($dataSql);
foreach ($params as $key => $val) {
    $dataStmt->bindValue($key, $val);
}
$dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();
$orders = $dataStmt->fetchAll();

$successMsg = getFlashMessage('success');
$errorMsg   = getFlashMessage('error');

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>

<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div>
                <h1 class="fs-3 fw-bold" style="color: var(--admin-primary);">Orders</h1>
                <p class="text-muted">View and manage customer orders.</p>
            </div>
        </div>

        <?php if ($successMsg): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($successMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($errorMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="GET" class="row g-2 mb-3">
            <div class="col-auto flex-grow-1">
                <input type="text" name="search" class="form-control"
                       placeholder="Search by order number, customer name or email&hellip;"
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-auto" style="min-width: 150px;">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="<?= ORDER_PENDING ?>"    <?= $status === ORDER_PENDING    ? 'selected' : '' ?>>Pending</option>
                    <option value="<?= ORDER_CONFIRMED ?>"  <?= $status === ORDER_CONFIRMED  ? 'selected' : '' ?>>Confirmed</option>
                    <option value="<?= ORDER_PROCESSING ?>" <?= $status === ORDER_PROCESSING ? 'selected' : '' ?>>Processing</option>
                    <option value="<?= ORDER_SHIPPED ?>"    <?= $status === ORDER_SHIPPED    ? 'selected' : '' ?>>Shipped</option>
                    <option value="<?= ORDER_DELIVERED ?>"  <?= $status === ORDER_DELIVERED  ? 'selected' : '' ?>>Delivered</option>
                    <option value="<?= ORDER_CANCELLED ?>"  <?= $status === ORDER_CANCELLED  ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
            <?php if ($search !== '' || $status !== ''): ?>
                <div class="col-auto">
                    <a href="<?= SITE_URL ?>admin/orders/index.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            <?php endif; ?>
        </form>

        <div class="admin-table-wrap">
            <?php if (count($orders) === 0): ?>
                <div class="text-center py-5">
                    <p class="text-muted mb-3">No orders found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order):
                                $itemStmt = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE order_id = :oid');
                                $itemStmt->execute([':oid' => $order['id']]);
                                $itemCount = (int) $itemStmt->fetchColumn();
                            ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($order['order_number']) ?></strong></td>
                                    <td>
                                        <div><?= htmlspecialchars($order['full_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($order['email']) ?></small>
                                    </td>
                                    <td><?= $itemCount ?></td>
                                    <td><?= formatPrice((float) $order['grand_total']) ?></td>
                                    <td>
                                        <?php
                                        $statusBadge = match ($order['status']) {
                                            ORDER_PENDING    => 'bg-secondary',
                                            ORDER_CONFIRMED  => 'bg-info text-dark',
                                            ORDER_PROCESSING => 'bg-warning text-dark',
                                            ORDER_SHIPPED    => 'bg-primary',
                                            ORDER_DELIVERED  => 'bg-success',
                                            ORDER_CANCELLED  => 'bg-danger',
                                            default          => 'bg-secondary',
                                        };
                                        ?>
                                        <span class="badge <?= $statusBadge ?>">
                                            <?= ucfirst(htmlspecialchars($order['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $paymentBadge = match ($order['payment_status']) {
                                            PAYMENT_UNPAID   => 'bg-danger',
                                            PAYMENT_PAID     => 'bg-success',
                                            PAYMENT_REFUNDED => 'bg-warning text-dark',
                                            default          => 'bg-secondary',
                                        };
                                        ?>
                                        <span class="badge <?= $paymentBadge ?>">
                                            <?= ucfirst(htmlspecialchars($order['payment_status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                    <td class="text-nowrap">
                                        <a href="<?= SITE_URL ?>admin/orders/view.php?id=<?= (int) $order['id'] ?>"
                                           class="btn btn-sm" style="background: var(--admin-primary); color: #fff;">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </main>
</div>

<?php
require_once __DIR__ . '/../../includes/admin-footer.php';
