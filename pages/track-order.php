<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../helpers/format-helper.php';
require_once __DIR__ . '/../helpers/order-status-helper.php';

$lookedUp = false;
$order    = null;
$items    = [];
$history  = [];
$error    = '';
$searched = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['_csrf_token'] ?? '')) {
        $error = lang('invalid_csrf');
    }

    if (!$error) {
        $searched = trim($_POST['order_number'] ?? '');
        if ($searched === '') {
            $error = 'Please enter an order number.';
        } else {
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = :on');
                $stmt->execute([':on' => $searched]);
                $order = $stmt->fetch();

                if ($order) {
                    $lookedUp = true;

                    $itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :oid ORDER BY id ASC');
                    $itemStmt->execute([':oid' => (int) $order['id']]);
                    $items = $itemStmt->fetchAll();

                    $histStmt = $pdo->prepare('SELECT * FROM order_status_history WHERE order_id = :oid ORDER BY created_at ASC');
                    $histStmt->execute([':oid' => (int) $order['id']]);
                    $history = $histStmt->fetchAll();
                } else {
                    $error = 'No order found with that number. Please check and try again.';
                }
            } catch (\Throwable $e) {
                error_log('Track order lookup failed: ' . $e->getMessage());
                $error = 'An error occurred. Please try again later.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-hero-sm">
    <div class="container">
        <h1 class="fw-bold" style="color: #C9A227;"><i class="bi bi-truck me-2"></i> <?= lang('track_order') ?></h1>
        <p class="mb-0 text-white-50">Enter your order number to track its status and delivery progress.</p>
    </div>
</div>

<section class="section-padding pt-0">
    <div class="container">

        <div class="d-flex justify-content-center mb-4">
            <div class="card border-0 shadow-sm p-4" style="width: 100%; max-width: 600px;">
                    <form method="POST" id="trackForm">
                        <?= csrfField() ?>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-9">
                                <label for="orderNumber" class="form-label fw-semibold small text-uppercase" style="color: #001F5B;">
                                    <i class="bi bi-receipt me-1"></i> Order Number
                                </label>
                                <input type="text"
                                       class="form-control form-control-lg"
                                       id="orderNumber"
                                       name="order_number"
                                       value="<?= htmlspecialchars($searched) ?>"
                                       placeholder="e.g. CLS-2026-000001"
                                       required
                                       autocomplete="off">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="bi bi-search me-1"></i> <?= lang('track_order') ?>
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if ($error): ?>
                        <div class="alert alert-danger mt-3 mb-0">
                            <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                </div>

        </div>

        <?php if ($lookedUp && $order): ?>
                <div class="track-results">
                <!-- ─── Order Details ──────────────────── -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <h3 class="fw-bold mb-0" style="color: #001F5B;">
                        <i class="bi bi-receipt me-2"></i> Order <?= htmlspecialchars($order['order_number']) ?>
                    </h3>
                    <span class="badge fs-6 px-3 py-2 <?= orderBadge($order['status']) ?>">
                        <?= orderLabel($order['status']) ?>
                    </span>
                </div>

                    <!-- ─── Order Info + Timeline ─────────── -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="card shadow-sm h-100 cart-summary-card">
                                <div class="card-header fw-bold" style="background: #C9A227; color: #fff;">
                                    <i class="bi bi-info-circle me-1"></i> <?= lang('order_details') ?>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless mb-0 small">
                                        <tr>
                                            <td class="text-muted" style="width: 110px;">Status:</td>
                                            <td>
                                                <span class="badge <?= orderBadge($order['status']) ?>">
                                                    <?= orderLabel($order['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Payment:</td>
                                            <td>
                                                <span class="badge <?= match ($order['payment_status']) {
                                                    PAYMENT_UNPAID   => 'bg-danger',
                                                    PAYMENT_PAID     => 'bg-success',
                                                    PAYMENT_REFUNDED => 'bg-warning text-dark',
                                                    default          => 'bg-secondary',
                                                } ?>"><?= ucfirst(htmlspecialchars($order['payment_status'])) ?></span>
                                                <small class="text-muted ms-2">(<?= ucfirst(str_replace('_', ' ', htmlspecialchars($order['payment_method']))) ?>)</small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Placed:</td>
                                            <td><?= date('M j, Y \a\t g:i A', strtotime($order['created_at'])) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Delivery:</td>
                                            <td>
                                                <span class="badge <?= orderBadge($order['delivery_status'] ?? DELIVERY_PENDING) ?>">
                                                    <?= orderLabel($order['delivery_status'] ?? DELIVERY_PENDING) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php if (!empty($order['tracking_number'])): ?>
                                        <tr>
                                            <td class="text-muted">Tracking #:</td>
                                            <td><code class="fw-bold" style="color: #001F5B;"><?= htmlspecialchars($order['tracking_number']) ?></code></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if ($order['status'] === ORDER_CANCELLED && !empty($order['cancel_reason'])): ?>
                                        <tr>
                                            <td class="text-muted">Reason:</td>
                                            <td class="text-danger"><?= nl2br(htmlspecialchars($order['cancel_reason'])) ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card shadow-sm h-100 cart-summary-card">
                                <div class="card-header fw-bold" style="background: #0B6B2F; color: #fff;">
                                    <i class="bi bi-clock-history me-1"></i> <?= lang('order_timeline') ?? 'Order Timeline' ?>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (count($history) === 0 && $order['status'] === ORDER_PENDING): ?>
                                        <div class="p-4 text-center text-muted">
                                            <i class="bi bi-info-circle me-1"></i> Waiting for processing.
                                        </div>
                                    <?php else: ?>
                                        <ul class="list-group list-group-flush">
                                            <?php
                                            $allEntries = $history;
                                            $hasPending = false;
                                            foreach ($allEntries as $entry) {
                                                if ($entry['status'] === ORDER_PENDING) { $hasPending = true; break; }
                                            }
                                            if (!$hasPending) {
                                                array_unshift($allEntries, [
                                                    'status'     => ORDER_PENDING,
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
                                                            <small class="d-block mt-1 <?= $entry['status'] === ORDER_CANCELLED ? 'text-danger' : 'text-muted' ?>">
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
                    </div>

                    <!-- ─── Customer Info ────────────────── -->
                    <div class="mb-4">
                        <div class="card shadow-sm cart-summary-card">
                            <div class="card-header fw-bold" style="background: #001F5B; color: #fff;">
                                <i class="bi bi-person-badge me-1"></i> Customer
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless mb-0 small">
                                    <tr>
                                        <td class="text-muted" style="width: 110px;">Name:</td>
                                        <td class="fw-semibold"><?= htmlspecialchars($order['customer_name'] ?? $order['shipping_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Phone:</td>
                                        <td><?= htmlspecialchars($order['customer_phone'] ?? $order['shipping_phone']) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Address:</td>
                                        <td><?= nl2br(htmlspecialchars($order['customer_address'] ?? $order['shipping_address'])) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ─── Items ────────────────────────── -->
                    <div class="mb-4">
                        <div class="card shadow-sm cart-summary-card">
                            <div class="card-header fw-bold" style="background: #0B6B2F; color: #fff;">
                                <i class="bi bi-box-seam me-1"></i> Items (<?= count($items) ?>)
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Code</th>
                                                <th class="text-center">Qty</th>
                                                <th class="text-end">Price</th>
                                                <th class="text-end">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['product_name']) ?></td>
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

                    <!-- ─── Totals ──────────────────────── -->
                    <div class="d-flex justify-content-end mb-4">
                        <div class="card shadow-sm cart-summary-card" style="max-width: 420px; width: 100%;">
                            <div class="card-body">
                                <table class="table table-borderless mb-0">
                                    <tr>
                                        <td>Subtotal</td>
                                        <td class="text-end text-nowrap"><?= formatPrice((float) $order['subtotal']) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Shipping</td>
                                        <td class="text-end text-nowrap"><?= formatPrice((float) $order['shipping']) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Tax</td>
                                        <td class="text-end text-nowrap"><?= formatPrice((float) $order['tax']) ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="px-0 py-1"><hr class="my-1"></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold fs-6" style="color: #001F5B;">Grand Total</td>
                                        <td class="text-end text-nowrap fw-bold fs-6" style="color: #0B6B2F;"><?= formatPrice((float) $order['grand_total']) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <a href="<?= SITE_URL ?>pages/track-order.php" class="btn btn-outline-secondary me-2">
                            <i class="bi bi-search me-1"></i> Track Another Order
                        </a>
                        <a href="<?= SITE_URL ?>index.php" class="btn btn-primary">
                            <i class="bi bi-house-door me-1"></i> Back to Home
                        </a>
                    </div>
                </div><!-- /.track-results -->
                <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
