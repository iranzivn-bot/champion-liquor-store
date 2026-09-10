<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';
require_once __DIR__ . '/../../helpers/order-status-helper.php';

$pdo    = getDbConnection();
$userId = (int) $_SESSION['user_id'];

$orderId = max(0, (int) ($_GET['id'] ?? 0));
if ($orderId === 0) {
    redirect(SITE_URL . 'pages/dashboard/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id AND user_id = :uid');
$stmt->execute([':id' => $orderId, ':uid' => $userId]);
$order = $stmt->fetch();

if (!$order) {
    redirect(SITE_URL . 'pages/dashboard/index.php');
}

$itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :oid ORDER BY id ASC');
$itemStmt->execute([':oid' => $orderId]);
$items = $itemStmt->fetchAll();

$historyStmt = $pdo->prepare('SELECT * FROM order_status_history WHERE order_id = :oid ORDER BY created_at ASC');
$historyStmt->execute([':oid' => $orderId]);
$history = $historyStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>"><?= lang('home') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>pages/dashboard/index.php"><?= lang('dashboard') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>pages/dashboard/orders.php"><?= lang('my_orders') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($order['order_number']) ?></li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <h2 class="fw-bold mb-0" style="color: #001F5B;">
            <i class="bi bi-receipt me-2"></i> <?= htmlspecialchars($order['order_number']) ?>
        </h2>
        <a href="<?= SITE_URL ?>pages/dashboard/orders.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> <?= lang('back_to_orders') ?>
        </a>
    </div>

    <div class="row g-4">

        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-bold" style="background: #C9A227; color: #fff;">
                    <i class="bi bi-info-circle me-1"></i> <?= lang('order_info') ?>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0 small">
                        <tr>
                            <td class="text-muted" style="width: 130px;"><?= lang('status') ?>:</td>
                            <td>
                                <span class="badge <?= orderBadge($order['status']) ?>"><?= orderLabel($order['status']) ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted"><?= lang('payment') ?>:</td>
                            <td>
                                <?php
                                $payBadge = match ($order['payment_status']) {
                                    'unpaid'   => 'bg-danger',
                                    'paid'     => 'bg-success',
                                    'refunded' => 'bg-warning text-dark',
                                    default    => 'bg-secondary',
                                };
                                ?>
                                <span class="badge <?= $payBadge ?>"><?= ucfirst(htmlspecialchars($order['payment_status'])) ?></span>
                                <small class="text-muted ms-2">(<?= ucfirst(str_replace('_', ' ', htmlspecialchars($order['payment_method']))) ?>)</small>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted"><?= lang('placed_on') ?>:</td>
                            <td><?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?></td>
                        </tr>
                        <?php if ($order['notes']): ?>
                        <tr>
                            <td class="text-muted"><?= lang('notes') ?>:</td>
                            <td><?= nl2br(htmlspecialchars($order['notes'])) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($order['tracking_number']): ?>
                        <tr>
                            <td class="text-muted"><?= lang('tracking') ?>:</td>
                            <td><code><?= htmlspecialchars($order['tracking_number']) ?></code></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($order['status'] === 'cancelled' && $order['cancel_reason']): ?>
                        <tr>
                            <td class="text-muted"><?= lang('cancel_reason') ?>:</td>
                            <td class="text-danger"><?= nl2br(htmlspecialchars($order['cancel_reason'])) ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-bold" style="background: #0B6B2F; color: #fff;">
                    <i class="bi bi-clock-history me-1"></i> <?= lang('order_timeline') ?>
                </div>
                <div class="card-body p-0">
                    <?php if (count($history) === 0 && $order['status'] === 'pending'): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-info-circle me-1"></i> <?= lang('waiting_processing') ?>
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php
                            $allEntries = $history;
                            $hasPendingEntry = false;
                            foreach ($allEntries as $entry) {
                                if ($entry['status'] === 'pending') {
                                    $hasPendingEntry = true;
                                    break;
                                }
                            }
                            if (!$hasPendingEntry) {
                                array_unshift($allEntries, [
                                    'status'     => 'pending',
                                    'notes'      => null,
                                    'created_at' => $order['created_at'],
                                ]);
                            }
                            ?>
                            <?php foreach ($allEntries as $entry): ?>
                                <li class="list-group-item d-flex align-items-start gap-3 py-3">
                                    <div class="flex-shrink-0 mt-1">
                                        <span class="d-inline-flex align-items-center justify-content-center"
                                              style="width: 32px; height: 32px; border-radius: 50%; background: var(--bs-<?= orderTimelineIconColor($entry['status']) ?>); color: #fff;">
                                            <i class="bi bi-<?= orderTimelineIcon($entry['status']) ?>"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold"><?= orderLabel($entry['status']) ?></div>
                                        <?php if (!empty($entry['notes'])): ?>
                                            <small class="d-block mt-1 <?= $entry['status'] === 'cancelled' ? 'text-danger' : 'text-muted' ?>">
                                                <i class="bi bi-info-circle me-1"></i><?= htmlspecialchars($entry['notes']) ?>
                                            </small>
                                        <?php endif; ?>
                                        <small class="text-muted d-block mt-1">
                                            <?= date('M j, Y \a\t g:i A', strtotime($entry['created_at'])) ?>
                                        </small>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-bold" style="background: #001F5B; color: #fff;">
                    <i class="bi bi-person-badge me-1"></i> <?= lang('shipping_info') ?>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0 small">
                        <tr>
                            <td class="text-muted" style="width: 130px;"><?= lang('recipient') ?>:</td>
                            <td class="fw-semibold"><?= htmlspecialchars($order['shipping_name']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted"><?= lang('phone') ?>:</td>
                            <td><?= htmlspecialchars($order['shipping_phone']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted"><?= lang('address') ?>:</td>
                            <td><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></td>
                        </tr>
                        <?php if ($order['payment_method']): ?>
                        <tr>
                            <td class="text-muted"><?= lang('payment_method') ?>:</td>
                            <td><?= ucfirst(str_replace('_', ' ', htmlspecialchars($order['payment_method']))) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($order['notes']): ?>
                        <tr>
                            <td class="text-muted"><?= lang('notes') ?>:</td>
                            <td><?= nl2br(htmlspecialchars($order['notes'])) ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header fw-bold" style="background: #0B6B2F; color: #fff;">
                    <i class="bi bi-box-seam me-1"></i> <?= lang('items_ordered') ?> (<?= count($items) ?>)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th><?= lang('product') ?></th>
                                    <th><?= lang('code') ?></th>
                                    <th class="text-center"><?= lang('qty') ?></th>
                                    <th class="text-end"><?= lang('price') ?></th>
                                    <th class="text-end"><?= lang('subtotal') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($item['product_image'])): ?>
                                                    <img src="<?= SITE_URL ?>uploads/products/<?= htmlspecialchars($item['product_image']) ?>"
                                                         alt="<?= htmlspecialchars($item['product_name']) ?>"
                                                         style="width: 48px; height: 48px; object-fit: cover; border-radius: 6px;">
                                                <?php else: ?>
                                                    <span class="d-inline-flex align-items-center justify-content-center"
                                                          style="width: 48px; height: 48px; border-radius: 6px; background: #f0f0f0;">
                                                        <i class="bi bi-box text-muted"></i>
                                                    </span>
                                                <?php endif; ?>
                                                <span><?= htmlspecialchars($item['product_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><code><?= htmlspecialchars($item['product_code'] ?? '—') ?></code></td>
                                        <td class="text-center"><?= (int) $item['quantity'] ?></td>
                                        <td class="text-end"><?= formatPrice((float) $item['price']) ?></td>
                                        <td class="text-end fw-semibold" style="color: #0B6B2F;"><?= formatPrice((float) $item['subtotal']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-5 ms-auto">
            <div class="card shadow-sm">
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="text-muted"><?= lang('subtotal') ?></td>
                            <td class="text-end"><?= formatPrice((float) $order['subtotal']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted"><?= lang('shipping') ?></td>
                            <td class="text-end"><?= formatPrice((float) $order['shipping']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted"><?= lang('tax') ?></td>
                            <td class="text-end"><?= formatPrice((float) $order['tax']) ?></td>
                        </tr>
                        <tr>
                            <td colspan="2" class="px-0 py-1"><hr class="my-1"></td>
                        </tr>
                        <tr>
                            <td class="fw-bold fs-6" style="color: #001F5B;"><?= lang('grand_total') ?></td>
                            <td class="text-end fw-bold fs-6" style="color: #0B6B2F;"><?= formatPrice((float) $order['grand_total']) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
