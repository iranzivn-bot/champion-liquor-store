<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

$pdo    = getDbConnection();
$userId = (int) $_SESSION['user_id'];

$statusFilter = trim($_GET['status'] ?? '');
$allowedStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$countSql = 'SELECT COUNT(*) FROM orders WHERE user_id = :uid';
$countParams = [':uid' => $userId];
if ($statusFilter !== '') {
    $countSql .= ' AND status = :status';
    $countParams[':status'] = $statusFilter;
}
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$total      = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$sql = 'SELECT * FROM orders WHERE user_id = :uid';
$sqlParams = [':uid' => $userId];
if ($statusFilter !== '') {
    $sql .= ' AND status = :status';
    $sqlParams[':status'] = $statusFilter;
}
$sql .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';

$stmt = $pdo->prepare($sql);
foreach ($sqlParams as $key => $val) {
    $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();

$queryString = $_GET;
unset($queryString['page']);
$baseQuery = http_build_query($queryString);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>
<style>
.dashboard-stat-card {
    border: none;
    border-radius: 12px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.dashboard-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
}
.status-filter-btn {
    border-radius: 20px;
    padding: 0.35rem 1rem;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
}
.status-filter-btn:hover {
    transform: translateY(-1px);
}
</style>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>"><?= lang('home') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>pages/dashboard/index.php"><?= lang('dashboard') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= lang('my_orders') ?></li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <h2 class="fw-bold mb-0" style="color: #001F5B;">
            <i class="bi bi-bag-check me-2"></i> <?= lang('my_orders') ?>
            <span class="badge bg-secondary fs-6 align-middle ms-2"><?= $total ?></span>
        </h2>
        <a href="<?= SITE_URL ?>pages/dashboard/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> <?= lang('back_to_dashboard') ?>
        </a>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="<?= SITE_URL ?>pages/dashboard/orders.php"
           class="status-filter-btn btn <?= $statusFilter === '' ? 'btn-dark' : 'btn-outline-secondary' ?>">
            <?= lang('all') ?>
        </a>
        <?php foreach ($allowedStatuses as $s): ?>
            <a href="<?= SITE_URL ?>pages/dashboard/orders.php?status=<?= $s ?>"
               class="status-filter-btn btn <?= $statusFilter === $s ? 'btn-dark' : 'btn-outline-secondary' ?>">
                <?= ucfirst($s) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (count($orders) === 0): ?>
        <div class="text-center py-5">
            <i class="bi bi-inbox text-muted" style="font-size: 5rem; opacity: 0.3;"></i>
            <h4 class="text-muted mt-3"><?= lang('no_orders_found') ?></h4>
            <p class="text-muted mb-4"><?= lang('try_different_filter') ?></p>
            <a href="<?= SITE_URL ?>pages/shop.php" class="btn btn-lg px-5 py-3 fw-semibold" style="background: #C9A227; color: #fff;">
                <i class="bi bi-shop me-1"></i> <?= lang('start_shopping') ?>
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive rounded-3 shadow-sm">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= lang('order_number') ?></th>
                        <th><?= lang('date') ?></th>
                        <th><?= lang('items') ?></th>
                        <th><?= lang('total') ?></th>
                        <th><?= lang('status') ?></th>
                        <th><?= lang('payment') ?></th>
                        <th><?= lang('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order):
                        $itemStmt = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE order_id = :oid');
                        $itemStmt->execute([':oid' => $order['id']]);
                        $itemCount = (int) $itemStmt->fetchColumn();

                        $statusBadge = match ($order['status']) {
                            ORDER_PENDING    => 'bg-secondary',
                            ORDER_CONFIRMED  => 'bg-info text-dark',
                            ORDER_PROCESSING => 'bg-warning text-dark',
                            ORDER_SHIPPED    => 'bg-primary',
                            ORDER_DELIVERED  => 'bg-success',
                            ORDER_CANCELLED  => 'bg-danger',
                            default          => 'bg-secondary',
                        };
                        $payBadge = match ($order['payment_status']) {
                            PAYMENT_UNPAID   => 'bg-danger',
                            PAYMENT_PAID     => 'bg-success',
                            PAYMENT_REFUNDED => 'bg-warning text-dark',
                            default          => 'bg-secondary',
                        };
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($order['order_number']) ?></strong></td>
                            <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                            <td><?= $itemCount ?></td>
                            <td><strong style="color: #0B6B2F;"><?= formatPrice((float) $order['grand_total']) ?></strong></td>
                            <td><span class="badge <?= $statusBadge ?>"><?= ucfirst(htmlspecialchars($order['status'])) ?></span></td>
                            <td><span class="badge <?= $payBadge ?>"><?= ucfirst(htmlspecialchars($order['payment_status'])) ?></span></td>
                            <td>
                                <a href="<?= SITE_URL ?>pages/dashboard/order-view.php?id=<?= (int) $order['id'] ?>"
                                   class="btn btn-gold-sm">
                                    <i class="bi bi-eye"></i> <?= lang('view') ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= $baseQuery ?><?= $baseQuery ? '&' : '' ?>page=<?= $page - 1 ?>"><?= lang('previous') ?></a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= $baseQuery ?><?= $baseQuery ? '&' : '' ?>page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= $baseQuery ?><?= $baseQuery ? '&' : '' ?>page=<?= $page + 1 ?>"><?= lang('next') ?></a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
