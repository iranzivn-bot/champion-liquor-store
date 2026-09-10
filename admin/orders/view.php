<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();

$pageTitle = 'Order Details';

$pdo = getDbConnection();

$orderId = max(0, (int) ($_GET['id'] ?? 0));
if ($orderId === 0) {
    setFlashMessage('error', 'Invalid order ID.');
    redirect(SITE_URL . 'admin/orders/index.php');
}

$stmt = $pdo->prepare('
    SELECT o.*, u.full_name, u.email, u.phone
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = :id
');
$stmt->execute([':id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    setFlashMessage('error', 'Order not found.');
    redirect(SITE_URL . 'admin/orders/index.php');
}

$itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :oid ORDER BY id ASC');
$itemStmt->execute([':oid' => $orderId]);
$items = $itemStmt->fetchAll();

$historyStmt = $pdo->prepare('SELECT * FROM order_status_history WHERE order_id = :oid ORDER BY created_at ASC');
$historyStmt->execute([':oid' => $orderId]);
$history = $historyStmt->fetchAll();

$currentStatus = $order['status'];

function getBadgeClass(string $status): string
{
    return match ($status) {
        ORDER_PENDING    => 'bg-secondary',
        ORDER_PROCESSING => 'bg-warning text-dark',
        ORDER_SHIPPED    => 'bg-primary',
        ORDER_DELIVERED  => 'bg-success',
        ORDER_CANCELLED  => 'bg-danger',
        default          => 'bg-secondary',
    };
}

function getBadgeLabel(string $status): string
{
    return match ($status) {
        ORDER_PENDING    => 'Pending',
        ORDER_PROCESSING => 'Processing',
        ORDER_SHIPPED    => 'Shipped',
        ORDER_DELIVERED  => 'Delivered',
        ORDER_CANCELLED  => 'Cancelled',
        default          => ucfirst($status),
    };
}

$allowablePayments = [PAYMENT_UNPAID, PAYMENT_PAID, PAYMENT_REFUNDED];

$deliveryStatuses = [
    DELIVERY_PENDING,
    DELIVERY_PACKED,
    DELIVERY_OUT_FOR_DELIVERY,
    DELIVERY_DELIVERED,
];

/**
 * Returns the allowed next status actions for the given current status.
 * cancel_reason is handled separately via modal.
 */
function getAvailableActions(string $status): array
{
    return match ($status) {
        ORDER_PENDING    => [ORDER_PROCESSING],
        ORDER_PROCESSING => [ORDER_SHIPPED],
        ORDER_SHIPPED    => [ORDER_DELIVERED],
        default          => [],
    };
}

function getDeliveryBadgeClass(string $status): string
{
    return match ($status) {
        DELIVERY_PENDING          => 'bg-secondary',
        DELIVERY_PACKED           => 'bg-warning text-dark',
        DELIVERY_OUT_FOR_DELIVERY => 'bg-primary',
        DELIVERY_DELIVERED        => 'bg-success',
        default                   => 'bg-secondary',
    };
}

function getDeliveryBadgeLabel(string $status): string
{
    return match ($status) {
        DELIVERY_PENDING          => 'Pending',
        DELIVERY_PACKED           => 'Packed',
        DELIVERY_OUT_FOR_DELIVERY => 'Out For Delivery',
        DELIVERY_DELIVERED        => 'Delivered',
        default                   => ucfirst($status),
    };
}

function getNextDeliveryStatus(string $current): ?string
{
    return match ($current) {
        DELIVERY_PENDING          => DELIVERY_PACKED,
        DELIVERY_PACKED           => DELIVERY_OUT_FOR_DELIVERY,
        DELIVERY_OUT_FOR_DELIVERY => DELIVERY_DELIVERED,
        default                   => null,
    };
}

$availableActions = getAvailableActions($currentStatus);
$canCancel = in_array($currentStatus, [ORDER_PENDING, ORDER_PROCESSING], true);

// ─── Handle status update ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_status') {
        $newStatus = trim($_POST['status'] ?? '');
        $validActions = getAvailableActions($currentStatus);

        if (!in_array($newStatus, $validActions, true)) {
            setFlashMessage('error', 'Invalid status transition.');
            redirect(SITE_URL . 'admin/orders/view.php?id=' . $orderId);
        }

        try {
            $pdo->beginTransaction();

            $upd = $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
            $upd->execute([':status' => $newStatus, ':id' => $orderId]);

            $histStmt = $pdo->prepare('INSERT INTO order_status_history (order_id, status, notes) VALUES (:oid, :status, :notes)');
            $histStmt->execute([':oid' => $orderId, ':status' => $newStatus, ':notes' => null]);

            $pdo->commit();

            logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'orders', 'status_updated', $order['order_number'], 'Order status updated: ' . $order['order_number'] . ' from ' . $currentStatus . ' to ' . $newStatus);

            /*
             * ─── Notification Preparation ─────────────────────────────
             * Future: Send email notification to customer when order status changes.
             *
             * When ORDER_SHIPPED:
             *   - Send "Your order has been shipped" email with tracking info.
             *
             * When ORDER_DELIVERED:
             *   - Send "Your order has been delivered" email with feedback request.
             *
             * Placeholder for email integration:
             *   $emailSent = sendOrderStatusEmail($order['user_id'], $order['order_number'], $newStatus);
             */

            setFlashMessage('success', 'Order status updated to ' . getBadgeLabel($newStatus) . '.');
        } catch (\Exception $e) {
            $pdo->rollBack();
            setFlashMessage('error', 'Failed to update order status.');
        }

        redirect(SITE_URL . 'admin/orders/view.php?id=' . $orderId);
    }

    if ($action === 'cancel_order') {
        if (!$canCancel) {
            setFlashMessage('error', 'This order cannot be cancelled in its current state.');
            redirect(SITE_URL . 'admin/orders/view.php?id=' . $orderId);
        }

        $cancelReason = trim($_POST['cancel_reason'] ?? '');
        $needsRestore = !(int) ($order['stock_restored'] ?? 0);

        try {
            $pdo->beginTransaction();

            // Always update status, reason, and mark stock_restored = 1
            $upd = $pdo->prepare('UPDATE orders SET status = :status, cancel_reason = :reason, stock_restored = 1 WHERE id = :id');
            $upd->execute([':status' => ORDER_CANCELLED, ':reason' => $cancelReason ?: null, ':id' => $orderId]);

            $histStmt = $pdo->prepare('INSERT INTO order_status_history (order_id, status, notes) VALUES (:oid, :status, :notes)');
            $histStmt->execute([':oid' => $orderId, ':status' => ORDER_CANCELLED, ':notes' => $cancelReason ?: null]);

            // Only restore stock if it hasn't been restored before
            if ($needsRestore) {
                // Capture before-stock for each item before restoration
                $invStmt = $pdo->prepare("
                    SELECT oi.product_id, oi.quantity, p.stock_quantity AS current_stock
                    FROM order_items oi
                    JOIN products p ON p.id = oi.product_id
                    WHERE oi.order_id = :oid AND oi.product_id IS NOT NULL
                ");
                $invStmt->execute([':oid' => $orderId]);
                $invItems = $invStmt->fetchAll();

                $restoreStmt = $pdo->prepare('
                    UPDATE products p
                    JOIN order_items oi ON p.id = oi.product_id
                    SET p.stock_quantity = p.stock_quantity + oi.quantity
                    WHERE oi.order_id = :oid AND oi.product_id IS NOT NULL
                ');
                $restoreStmt->execute([':oid' => $orderId]);

                // Log movements for restored stock
                $movStmt = $pdo->prepare("
                    INSERT INTO inventory_movements
                        (product_id, movement_type, quantity, stock_before, stock_after, reference_type, reference_id, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($invItems as $inv) {
                    $stockBefore = (int) $inv['current_stock'];
                    $stockAfter  = $stockBefore + (int) $inv['quantity'];
                    $movStmt->execute([
                        (int) $inv['product_id'],
                        INV_MOVEMENT_ORDER_CANCELLED,
                        (int) $inv['quantity'],
                        $stockBefore,
                        $stockAfter,
                        INV_REFERENCE_ORDER,
                        $orderId,
                        'Order ' . $order['order_number'] . ' cancelled',
                        (int) $_SESSION['user_id'],
                    ]);
                }
            }

            /*
             * ─── Notification Preparation ─────────────────────────────
             * Future: Send cancellation email to customer.
             *
             * When ORDER_CANCELLED:
             *   - Send "Your order has been cancelled" email with reason.
             *
             * Placeholder:
             *   $emailSent = sendOrderCancelledEmail($order['user_id'], $order['order_number'], $cancelReason);
             */

            $pdo->commit();

            $auditDesc = 'Order cancelled: ' . $order['order_number'];
            if ($needsRestore) $auditDesc .= ' (stock restored)';
            logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'orders', 'cancelled', $order['order_number'], $auditDesc);

            if ($needsRestore) {
                setFlashMessage('success', 'Order cancelled successfully. Stock has been restored.');
            } else {
                setFlashMessage('success', 'Order cancelled successfully.');
            }
        } catch (\Exception $e) {
            $pdo->rollBack();
            setFlashMessage('error', 'Failed to cancel order.');
        }

        redirect(SITE_URL . 'admin/orders/view.php?id=' . $orderId);
    }
}

// ─── Handle payment update ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $newPayment = trim($_POST['payment_status'] ?? '');
    if (in_array($newPayment, $allowablePayments, true)) {
        $upd = $pdo->prepare('UPDATE orders SET payment_status = :ps WHERE id = :id');
        $upd->execute([':ps' => $newPayment, ':id' => $orderId]);
        logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'orders', 'payment_updated', $order['order_number'], 'Payment status updated to ' . $newPayment . ' for order ' . $order['order_number']);
        setFlashMessage('success', 'Payment status updated successfully.');
    } else {
        setFlashMessage('error', 'Invalid payment status value.');
    }
    redirect(SITE_URL . 'admin/orders/view.php?id=' . $orderId);
}

// ─── Handle delivery update ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_delivery'])) {
    $tracking = trim($_POST['tracking_number'] ?? '');
    $newDelivery = trim($_POST['delivery_status'] ?? '');

    if (!in_array($newDelivery, $deliveryStatuses, true)) {
        setFlashMessage('error', 'Invalid delivery status.');
        redirect(SITE_URL . 'admin/orders/view.php?id=' . $orderId);
    }

    $expectedNext = getNextDeliveryStatus($order['delivery_status'] ?? DELIVERY_PENDING);
    if ($newDelivery !== $order['delivery_status'] && $newDelivery !== $expectedNext) {
        setFlashMessage('error', 'Invalid delivery status transition.');
        redirect(SITE_URL . 'admin/orders/view.php?id=' . $orderId);
    }

    try {
        $pdo->beginTransaction();

        $upd = $pdo->prepare('UPDATE orders SET tracking_number = :tracking, delivery_status = :ds WHERE id = :id');
        $upd->execute([
            ':tracking' => $tracking ?: null,
            ':ds'       => $newDelivery,
            ':id'       => $orderId,
        ]);

        // Log delivery status change in order_status_history
        if ($newDelivery !== $order['delivery_status']) {
            $histStmt = $pdo->prepare('INSERT INTO order_status_history (order_id, status, notes) VALUES (:oid, :status, :notes)');
            $histStmt->execute([
                ':oid'    => $orderId,
                ':status' => $newDelivery,
                ':notes'  => 'Delivery status updated',
            ]);
        }

        $pdo->commit();

        logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'orders', 'delivery_updated', $order['order_number'], 'Delivery updated for ' . $order['order_number'] . ': status=' . $newDelivery . ', tracking=' . ($tracking ?: 'N/A'));

        setFlashMessage('success', 'Delivery information saved successfully.');
    } catch (\Exception $e) {
        $pdo->rollBack();
        setFlashMessage('error', 'Failed to update delivery information.');
    }

    redirect(SITE_URL . 'admin/orders/view.php?id=' . $orderId);
}

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
                <h1 class="fs-3 fw-bold" style="color: var(--admin-primary);">
                    Order <?= htmlspecialchars($order['order_number']) ?>
                </h1>
                <p class="text-muted">Placed on <?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?></p>
            </div>
            <a href="<?= SITE_URL ?>admin/orders/index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Orders
            </a>
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

        <div class="row g-4">

            <!-- ════════════════════════════════════════════════════════════
                 PART 2+3 — Status Action Buttons
                 ════════════════════════════════════════════════════════════ -->
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header fw-bold" style="background: #001F5B; color: #fff;">
                        <i class="bi bi-gear me-1"></i> Order Status
                        <span class="badge <?= getBadgeClass($currentStatus) ?> ms-2" style="font-size: 0.8rem;">
                            <?= getBadgeLabel($currentStatus) ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center g-3">
                            <div class="col-md">
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if (count($availableActions) > 0): ?>
                                        <?php foreach ($availableActions as $action):
                                            $btnLabel = getBadgeLabel($action);
                                            $btnColor = match ($action) {
                                                ORDER_PROCESSING => '#C9A227',
                                                ORDER_SHIPPED    => '#001F5B',
                                                ORDER_DELIVERED  => '#0B6B2F',
                                                default          => '#6c757d',
                                            };
                                        ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="status" value="<?= $action ?>">
                                                <button type="submit" class="btn px-4 py-2 fw-semibold"
                                                        style="background: <?= $btnColor ?>; color: #fff;"
                                                        onclick="return confirm('Mark this order as <?= $btnLabel ?>?')">
                                                    <i class="bi bi-<?= match ($action) {
                                                        ORDER_PROCESSING => 'arrow-right-circle',
                                                        ORDER_SHIPPED    => 'truck',
                                                        ORDER_DELIVERED  => 'check2-circle',
                                                        default          => 'gear',
                                                    } ?> me-1"></i>
                                                    <?= $btnLabel ?>
                                                </button>
                                            </form>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <?php if ($canCancel): ?>
                                        <button type="button" class="btn px-4 py-2 fw-semibold btn-outline-danger"
                                                data-bs-toggle="modal" data-bs-target="#cancelModal">
                                            <i class="bi bi-x-circle me-1"></i> Cancel Order
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($currentStatus === ORDER_DELIVERED): ?>
                                        <span class="text-success fw-semibold align-self-center ms-2">
                                            <i class="bi bi-check-circle-fill me-1"></i> Order Completed
                                        </span>
                                    <?php elseif ($currentStatus === ORDER_CANCELLED): ?>
                                        <span class="text-danger fw-semibold align-self-center ms-2">
                                            <i class="bi bi-x-circle-fill me-1"></i> Order Cancelled
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($currentStatus === ORDER_CANCELLED): ?>
                            <?php if ($order['cancel_reason']): ?>
                            <div class="mt-3 p-3 bg-danger bg-opacity-10 rounded-3">
                                <strong class="text-danger"><i class="bi bi-info-circle me-1"></i> Cancellation Reason:</strong>
                                <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($order['cancel_reason'])) ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="mt-2">
                                <span class="text-muted small">Stock Restored:</span>
                                <?php if ((int) ($order['stock_restored'] ?? 0) === 1): ?>
                                    <span class="badge bg-success ms-1"><i class="bi bi-check-lg me-1"></i>Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-danger ms-1"><i class="bi bi-x-lg me-1"></i>No</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($order['notes']): ?>
                            <div class="mt-3 p-3 bg-info bg-opacity-10 rounded-3">
                                <strong style="color: #001F5B;"><i class="bi bi-sticky me-1"></i> Order Notes:</strong>
                                <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════
                 PART 4 — Order Timeline
                 ════════════════════════════════════════════════════════════ -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-bold" style="background: #0B6B2F; color: #fff;">
                        <i class="bi bi-clock-history me-1"></i> Order Timeline
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($history) === 0 && $currentStatus === ORDER_PENDING): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-info-circle me-1"></i> No status changes yet.
                            </div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php
                                $allEntries = $history;

                                // If no "pending" history entry exists, derive it from order creation
                                $hasPendingEntry = false;
                                foreach ($allEntries as $entry) {
                                    if ($entry['status'] === ORDER_PENDING) {
                                        $hasPendingEntry = true;
                                        break;
                                    }
                                }
                                if (!$hasPendingEntry) {
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
                                            <?php
                                            $iconColor = match ($entry['status']) {
                                                ORDER_PENDING          => 'secondary',
                                                ORDER_PROCESSING       => 'warning',
                                                ORDER_SHIPPED          => 'primary',
                                                ORDER_DELIVERED        => 'success',
                                                ORDER_CANCELLED        => 'danger',
                                                DELIVERY_PACKED        => 'warning',
                                                DELIVERY_OUT_FOR_DELIVERY => 'primary',
                                                default                => 'secondary',
                                            };
                                            ?>
                                            <span class="d-inline-flex align-items-center justify-content-center"
                                                  style="width: 32px; height: 32px; border-radius: 50%; background: var(--bs-<?= $iconColor ?>); color: #fff;">
                                                <i class="bi bi-<?= match ($entry['status']) {
                                                    ORDER_PENDING          => 'hourglass',
                                                    ORDER_PROCESSING       => 'arrow-repeat',
                                                    ORDER_SHIPPED          => 'truck',
                                                    ORDER_DELIVERED        => 'check2',
                                                    ORDER_CANCELLED        => 'x',
                                                    DELIVERY_PACKED        => 'box-seam',
                                                    DELIVERY_OUT_FOR_DELIVERY => 'truck-flatbed',
                                                    default                => 'circle',
                                                } ?>"></i>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1">
                                        <div class="fw-semibold">
                                            <?= getDeliveryBadgeLabel($entry['status']) ?>
                                        </div>
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

            <!-- ════════════════════════════════════════════════════════════
                 Customer Snapshot + Payment Info (side by side)
                 ════════════════════════════════════════════════════════════ -->
            <div class="col-md-6">
                <div class="row g-4">
                    <!-- Customer Snapshot (stored at checkout time) -->
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header fw-bold" style="background: var(--admin-primary); color: #fff;">
                                <i class="bi bi-person-badge me-1"></i> Customer Snapshot
                                <span class="badge bg-light text-dark ms-2" style="font-size: 0.7rem;">at checkout</span>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless mb-0 small">
                                    <tr>
                                        <td class="text-muted" style="width: 120px;">Name:</td>
                                        <td class="fw-semibold"><?= htmlspecialchars($order['customer_name'] ?? $order['full_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Email:</td>
                                        <td><?= htmlspecialchars($order['customer_email'] ?? $order['email']) ?></td>
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

                    <!-- Payment Info -->
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header fw-bold" style="background: var(--admin-green); color: #fff;">
                                <i class="bi bi-credit-card me-1"></i> Payment Information
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless mb-0 small">
                                    <tr>
                                        <td class="text-muted" style="width: 120px;">Method:</td>
                                        <td class="fw-semibold"><?= ucfirst(str_replace('_', ' ', htmlspecialchars($order['payment_method']))) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Status:</td>
                                        <td>
                                            <?php
                                            $payBadge = match ($order['payment_status']) {
                                                PAYMENT_UNPAID   => 'bg-danger',
                                                PAYMENT_PAID     => 'bg-success',
                                                PAYMENT_REFUNDED => 'bg-warning text-dark',
                                                default          => 'bg-secondary',
                                            };
                                            ?>
                                            <span class="badge <?= $payBadge ?>"><?= ucfirst(htmlspecialchars($order['payment_status'])) ?></span>
                                        </td>
                                    </tr>
                                </table>

                                <form method="POST" class="mt-3 border-top pt-3">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-auto flex-grow-1">
                                            <label class="form-label small text-muted mb-1">Update Payment Status</label>
                                            <select name="payment_status" class="form-select form-select-sm">
                                                <option value="<?= PAYMENT_UNPAID ?>"   <?= $order['payment_status'] === PAYMENT_UNPAID   ? 'selected' : '' ?>>Unpaid</option>
                                                <option value="<?= PAYMENT_PAID ?>"     <?= $order['payment_status'] === PAYMENT_PAID     ? 'selected' : '' ?>>Paid</option>
                                                <option value="<?= PAYMENT_REFUNDED ?>" <?= $order['payment_status'] === PAYMENT_REFUNDED ? 'selected' : '' ?>>Refunded</option>
                                            </select>
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" name="update_payment" class="btn btn-sm btn-success">Update</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Shipping Info -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-bold" style="background: var(--admin-gold); color: #fff;">
                        <i class="bi bi-truck me-1"></i> Shipping Information
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0 small">
                            <tr>
                                <td class="text-muted" style="width: 120px;">Recipient:</td>
                                <td class="fw-semibold"><?= htmlspecialchars($order['shipping_name']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Phone:</td>
                                <td><?= htmlspecialchars($order['shipping_phone']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Address:</td>
                                <td><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Delivery Management -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-bold" style="background: #6B21A8; color: #fff;">
                        <i class="bi bi-truck-flatbed me-1"></i> Delivery Management
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label small text-muted mb-1">Tracking Number</label>
                                <input type="text" name="tracking_number" class="form-control form-control-sm"
                                       value="<?= htmlspecialchars($order['tracking_number'] ?? '') ?>"
                                       placeholder="e.g. CLS-TRK-001">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted mb-1">Delivery Status</label>
                                <select name="delivery_status" class="form-select form-select-sm">
                                    <?php foreach ($deliveryStatuses as $ds): ?>
                                        <?php
                                        $disabled = false;
                                        $currentD = $order['delivery_status'] ?? DELIVERY_PENDING;
                                        $next = getNextDeliveryStatus($currentD);
                                        // Only allow current, next, or previous values
                                        if ($ds !== $currentD && $ds !== $next) {
                                            $disabled = true;
                                        }
                                        ?>
                                        <option value="<?= $ds ?>" <?= $ds === $currentD ? 'selected' : '' ?> <?= $disabled ? 'disabled' : '' ?>>
                                            <?= getDeliveryBadgeLabel($ds) ?>
                                            <?= $ds === $currentD ? '(current)' : ($ds === $next ? '(next)' : '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="mt-2">
                                    <span class="badge <?= getDeliveryBadgeClass($order['delivery_status'] ?? DELIVERY_PENDING) ?>">
                                        <?= getDeliveryBadgeLabel($order['delivery_status'] ?? DELIVERY_PENDING) ?>
                                    </span>
                                </div>
                            </div>
                            <button type="submit" name="update_delivery" class="btn btn-sm fw-semibold"
                                    style="background: #6B21A8; color: #fff;">
                                <i class="bi bi-save me-1"></i> Save Delivery Information
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header fw-bold" style="background: #001F5B; color: #fff;">
                        <i class="bi bi-box-seam me-1"></i> Order Items (<?= count($items) ?>)
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
                                            <td class="text-end fw-semibold"><?= formatPrice((float) $item['subtotal']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Totals -->
            <div class="col-md-5 ms-auto">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted">Subtotal</td>
                                    <td class="text-end"><?= formatPrice((float) $order['subtotal']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Shipping</td>
                                    <td class="text-end"><?= formatPrice((float) $order['shipping']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Tax</td>
                                    <td class="text-end"><?= formatPrice((float) $order['tax']) ?></td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="px-0 py-1">
                                        <hr class="my-1">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold fs-6" style="color: #001F5B;">Grand Total</td>
                                    <td class="text-end fw-bold fs-6" style="color: #0B6B2F;">
                                        <?= formatPrice((float) $order['grand_total']) ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     PART 5 — Cancel Order Modal
     ═══════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0" style="background: #dc3545; color: #fff;">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-x-circle me-1"></i> Cancel Order
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="cancel_order">
                <div class="modal-body py-4">
                    <p class="fw-semibold mb-3" style="color: #001F5B;">
                        Are you sure you want to cancel this order?
                    </p>
                    <div class="alert alert-warning small py-2 mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Product stock will be restored automatically.
                    </div>
                    <div class="mb-0">
                        <label for="cancel_reason" class="form-label fw-semibold">
                            Cancellation Reason <span class="text-muted">(optional)</span>
                        </label>
                        <textarea class="form-control" id="cancel_reason" name="cancel_reason"
                                  rows="3" placeholder="Why is this order being cancelled?"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="bi bi-x-circle me-1"></i> Cancel Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/admin-footer.php';
