<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

$pdo    = getDbConnection();
$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT full_name FROM users WHERE id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    redirect(SITE_URL . 'pages/login.php');
}

$stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id = :uid ORDER BY created_at DESC LIMIT 10');
$stmt->execute([':uid' => $userId]);
$recentOrders = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = :uid');
$stmt->execute([':uid' => $userId]);
$totalOrders = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :uid AND status = 'pending'");
$stmt->execute([':uid' => $userId]);
$pendingOrders = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :uid AND status = 'processing'");
$stmt->execute([':uid' => $userId]);
$processingOrders = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :uid AND status = 'shipped'");
$stmt->execute([':uid' => $userId]);
$shippedOrders = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :uid AND status = 'delivered'");
$stmt->execute([':uid' => $userId]);
$completedOrders = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :uid AND status = 'cancelled'");
$stmt->execute([':uid' => $userId]);
$cancelledOrders = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM wishlist WHERE user_id = :uid');
$stmt->execute([':uid' => $userId]);
$wishlistCount = (int) $stmt->fetchColumn();

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
.dashboard-stat-card .card-body {
    padding: 1.5rem;
}
.dashboard-stat-card .stat-icon {
    font-size: 2rem;
    opacity: 0.8;
}
.dashboard-stat-card .stat-number {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1.2;
}
.dashboard-stat-card .stat-label {
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    opacity: 0.85;
}
</style>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>"><?= lang('home') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= lang('dashboard') ?></li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <h2 class="fw-bold mb-0" style="color: #001F5B;">
            <i class="bi bi-speedometer2 me-2"></i> <?= lang('dashboard') ?>
        </h2>
        <span class="text-muted"><?= lang('welcome') ?>, <strong><?= htmlspecialchars($user['full_name']) ?></strong></span>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-6 col-lg-3">
            <div class="card dashboard-stat-card border-start border-primary border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number text-primary"><?= $totalOrders ?></div>
                            <div class="stat-label text-muted"><?= lang('total_orders') ?></div>
                        </div>
                        <div class="stat-icon text-primary"><i class="bi bi-bag-check"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card dashboard-stat-card border-start border-warning border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number text-gold"><?= $pendingOrders ?></div>
                            <div class="stat-label text-muted"><?= lang('pending') ?></div>
                        </div>
                        <div class="stat-icon text-gold"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card dashboard-stat-card border-start border-success border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number text-green"><?= $completedOrders ?></div>
                            <div class="stat-label text-muted"><?= lang('completed') ?></div>
                        </div>
                        <div class="stat-icon text-green"><i class="bi bi-check2-circle"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card dashboard-stat-card border-start border-danger border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number text-danger"><?= $wishlistCount ?></div>
                            <div class="stat-label text-muted"><?= lang('wishlist') ?></div>
                        </div>
                        <div class="stat-icon text-danger"><i class="bi bi-heart"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="row g-3 mb-5">
        <div class="col-6 col-lg-3">
            <a href="<?= SITE_URL ?>pages/dashboard/profile.php" class="text-decoration-none">
                <div class="card dashboard-stat-card text-center py-3 h-100">
                    <div class="card-body">
                        <div class="stat-icon text-primary mb-2"><i class="bi bi-person-gear"></i></div>
                        <div class="fw-semibold small"><?= lang('profile') ?></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="<?= SITE_URL ?>pages/dashboard/change-password.php" class="text-decoration-none">
                <div class="card dashboard-stat-card text-center py-3 h-100">
                    <div class="card-body">
                        <div class="stat-icon text-gold mb-2"><i class="bi bi-shield-lock"></i></div>
                        <div class="fw-semibold small"><?= lang('change_password') ?></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="<?= SITE_URL ?>pages/dashboard/orders.php" class="text-decoration-none">
                <div class="card dashboard-stat-card text-center py-3 h-100">
                    <div class="card-body">
                        <div class="stat-icon text-green mb-2"><i class="bi bi-box-seam"></i></div>
                        <div class="fw-semibold small"><?= lang('my_orders') ?></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="<?= SITE_URL ?>pages/wishlist.php" class="text-decoration-none">
                <div class="card dashboard-stat-card text-center py-3 h-100">
                    <div class="card-body">
                        <div class="stat-icon text-danger mb-2"><i class="bi bi-heart"></i></div>
                        <div class="fw-semibold small"><?= lang('wishlist') ?></div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h4 class="fw-bold mb-0" style="color: #001F5B;">
            <i class="bi bi-clock-history me-2"></i> <?= lang('recent_orders') ?>
        </h4>
        <a href="<?= SITE_URL ?>pages/dashboard/orders.php" class="btn btn-gold-sm">
            <i class="bi bi-eye me-1"></i> <?= lang('view_all') ?>
        </a>
    </div>

    <?php if (count($recentOrders) === 0): ?>
        <div class="text-center py-5">
            <i class="bi bi-inbox text-muted" style="font-size: 5rem; opacity: 0.3;"></i>
            <h4 class="text-muted mt-3"><?= lang('no_orders_yet') ?></h4>
            <p class="text-muted mb-4"><?= lang('start_shopping_text') ?></p>
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
                        <th><?= lang('status') ?></th>
                        <th><?= lang('total') ?></th>
                        <th><?= lang('payment') ?></th>
                        <th><?= lang('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $order):
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
                            <td><span class="badge <?= $statusBadge ?>"><?= ucfirst(htmlspecialchars($order['status'])) ?></span></td>
                            <td><strong style="color: #0B6B2F;"><?= formatPrice((float) $order['grand_total']) ?></strong></td>
                            <td><span class="badge <?= $payBadge ?>"><?= ucfirst(htmlspecialchars($order['payment_status'])) ?></span></td>
                            <td>
                                <a href="<?= SITE_URL ?>pages/dashboard/order-view.php?id=<?= (int) $order['id'] ?>" class="btn btn-gold-sm">
                                    <i class="bi bi-eye"></i> <?= lang('view') ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
