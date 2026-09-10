<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';
require_once __DIR__ . '/../../helpers/mailer.php';

$pdo    = getDbConnection();
$userId = (int) $_SESSION['user_id'];

// Fetch user info for pre-filling the form
$userStmt = $pdo->prepare('SELECT full_name, email, phone FROM users WHERE id = :id');
$userStmt->execute([':id' => $userId]);
$user = $userStmt->fetch();

if (!$user) {
    session_destroy();
    redirect(SITE_URL . 'pages/login.php');
}

// Fetch cart items
$stmt = $pdo->prepare('
    SELECT c.id AS cart_id, c.quantity, c.price, c.subtotal,
           p.id AS product_id, p.code, p.name, p.image, p.stock_quantity, p.status
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = :uid
    ORDER BY c.created_at DESC
');
$stmt->execute([':uid' => $userId]);
$items = $stmt->fetchAll();

if (count($items) === 0) {
    setFlashMessage('error', 'Your cart is empty.');
    redirect(SITE_URL . 'pages/cart/index.php');
}

$cartSubtotal = 0.0;
$hasOutOfStock = false;
foreach ($items as $item) {
    $cartSubtotal += (float) $item['subtotal'];
    if ((int) $item['stock_quantity'] <= 0) {
        $hasOutOfStock = true;
    }
}

if ($hasOutOfStock) {
    setFlashMessage('error', 'Some items in your cart are out of stock. Please remove them before checking out.');
    redirect(SITE_URL . 'pages/cart/index.php');
}

$shipping = DEFAULT_SHIPPING;
$tax      = DEFAULT_TAX;
$grandTotal = $cartSubtotal + $shipping + $tax;

$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh the page.';
    }

    if (count($errors) === 0) {
    $shippingName    = trim($_POST['shipping_name']    ?? '');
    $shippingPhone   = trim($_POST['shipping_phone']   ?? '');
    $shippingAddress = trim($_POST['shipping_address'] ?? '');
    $paymentMethod   = trim($_POST['payment_method']   ?? PAYMENT_COD);
    $notes           = trim($_POST['notes']            ?? '');

    if ($shippingName === '') {
        $errors[] = 'Full name is required.';
    }
    if ($shippingPhone === '') {
        $errors[] = 'Phone number is required.';
    }
    if ($shippingAddress === '') {
        $errors[] = 'Delivery address is required.';
    }

    $validMethods = [PAYMENT_COD, PAYMENT_BANK_TRANSFER, PAYMENT_MOBILE_MONEY, PAYMENT_CARD];
    if (!in_array($paymentMethod, $validMethods, true)) {
        $paymentMethod = PAYMENT_COD;
    }

    if (count($errors) === 0) {
        try {
            $pdo->beginTransaction();

            $stockStmt = $pdo->prepare('
                SELECT c.id AS cart_id, c.quantity, c.price,
                       p.id AS product_id, p.name, p.code, p.stock_quantity
                FROM cart c
                JOIN products p ON c.product_id = p.id
                WHERE c.user_id = :uid FOR UPDATE
            ');
            $stockStmt->execute([':uid' => $userId]);
            $cartRows = $stockStmt->fetchAll();

            foreach ($cartRows as $row) {
                if ((int) $row['quantity'] > (int) $row['stock_quantity']) {
                    throw new \RuntimeException('Insufficient stock for ' . htmlspecialchars($row['name']));
                }
            }

            $year = date('Y');
            $seqStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_number LIKE :prefix");
            $seqStmt->execute([':prefix' => "CLS-{$year}-%"]);
            $seqNumber = str_pad((string) ((int) $seqStmt->fetchColumn() + 1), 6, '0', STR_PAD_LEFT);
            $orderNumber = "CLS-{$year}-{$seqNumber}";

            $orderStmt = $pdo->prepare('
                INSERT INTO orders (order_number, user_id, shipping_name, shipping_phone,
                                    shipping_address, payment_method, subtotal, shipping,
                                    tax, grand_total, status, payment_status, notes,
                                    customer_name, customer_email, customer_phone, customer_address)
                VALUES (:order_number, :user_id, :shipping_name, :shipping_phone,
                        :shipping_address, :payment_method, :subtotal, :shipping,
                        :tax, :grand_total, :status, :payment_status, :notes,
                        :customer_name, :customer_email, :customer_phone, :customer_address)
            ');
            $orderStmt->execute([
                ':order_number'     => $orderNumber,
                ':user_id'          => $userId,
                ':shipping_name'    => $shippingName,
                ':shipping_phone'   => $shippingPhone,
                ':shipping_address' => $shippingAddress,
                ':payment_method'   => $paymentMethod,
                ':subtotal'         => $cartSubtotal,
                ':shipping'         => $shipping,
                ':tax'              => $tax,
                ':grand_total'      => $grandTotal,
                ':status'           => ORDER_PENDING,
                ':payment_status'   => PAYMENT_UNPAID,
                ':notes'            => $notes !== '' ? $notes : null,
                ':customer_name'    => $shippingName,
                ':customer_email'   => $user['email'] ?? '',
                ':customer_phone'   => $shippingPhone,
                ':customer_address' => $shippingAddress,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare('
                INSERT INTO order_items (order_id, product_id, product_name, product_code,
                                         price, quantity, subtotal)
                VALUES (:order_id, :product_id, :product_name, :product_code,
                        :price, :quantity, :subtotal)
            ');

            foreach ($cartRows as $row) {
                $itemStmt->execute([
                    ':order_id'     => $orderId,
                    ':product_id'   => (int) $row['product_id'],
                    ':product_name' => $row['name'],
                    ':product_code' => $row['code'],
                    ':price'        => (float) $row['price'],
                    ':quantity'     => (int) $row['quantity'],
                    ':subtotal'     => (float) $row['price'] * (int) $row['quantity'],
                ]);
            }

            $deductStmt = $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity - :qty WHERE id = :pid AND stock_quantity >= :qty2');
            $invMovStmt = $pdo->prepare("
                INSERT INTO inventory_movements
                    (product_id, movement_type, quantity, stock_before, stock_after, reference_type, reference_id, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($cartRows as $row) {
                $deductStmt->execute([
                    ':qty'  => (int) $row['quantity'],
                    ':pid'  => (int) $row['product_id'],
                    ':qty2' => (int) $row['quantity'],
                ]);

                $stockBefore = (int) $row['stock_quantity'];
                $stockAfter  = $stockBefore - (int) $row['quantity'];

                $invMovStmt->execute([
                    (int) $row['product_id'],
                    INV_MOVEMENT_ORDER,
                    -1 * (int) $row['quantity'],
                    $stockBefore,
                    $stockAfter,
                    INV_REFERENCE_ORDER,
                    $orderId,
                    'Order ' . $orderNumber,
                    $userId,
                ]);
            }

            $clearStmt = $pdo->prepare('DELETE FROM cart WHERE user_id = :uid');
            $clearStmt->execute([':uid' => $userId]);

            $pdo->commit();

            logActivity($userId, $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'orders', 'created', $orderNumber, 'Order placed: ' . $orderNumber . ' (Total: ' . formatPrice($grandTotal) . ', Payment: ' . $paymentMethod . ')');

            // Send order confirmation email (non-blocking)
            try {
                $emailItems = [];
                foreach ($cartRows as $row) {
                    $emailItems[] = [
                        'name'  => $row['name'],
                        'qty'   => (int) $row['quantity'],
                        'price' => formatPrice((float) $row['price']),
                    ];
                }
                $userName   = $user['full_name'] ?? 'Valued Customer';
                $orderDate  = date('F j, Y \a\t g:i A');
                $orderTotal = formatPrice($grandTotal);
                $items      = $emailItems;

                ob_start();
                include __DIR__ . '/../../templates/emails/order-confirmation.php';
                $emailBody = ob_get_clean();

                $emailResult = sendMail($user['email'], 'Order Confirmation — ' . $orderNumber, $emailBody);
                if (!$emailResult['success']) {
                    error_log('Checkout: order confirmation email failed for ' . $orderNumber . ': ' . ($emailResult['error'] ?? 'unknown'));
                }
            } catch (\Throwable $e) {
                error_log('Checkout: order confirmation email error for order ' . $orderNumber . ': ' . $e->getMessage());
            }

            redirect(SITE_URL . 'pages/cart/success.php?order_id=' . $orderId);

        } catch (\RuntimeException $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        } catch (\Exception $e) {
            $pdo->rollBack();
            $errors[] = 'An error occurred while processing your order. Please try again.';
        }
    }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>pages/cart/index.php">Cart</a></li>
            <li class="breadcrumb-item active" aria-current="page">Checkout</li>
        </ol>
    </nav>

    <h2 class="fw-bold mb-4 text-primary">
        <i class="bi bi-credit-card me-2"></i> Checkout
    </h2>

    <?php if (count($errors) > 0): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle-fill me-1"></i>
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="checkoutForm" novalidate>
        <?= csrfField() ?>
        <div class="row g-4">

            <!-- Left: Customer & Delivery Details -->
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-header fw-bold bg-primary text-white">
                        <i class="bi bi-person-lines-fill me-1"></i> Delivery Details
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="shipping_name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-lg" id="shipping_name" name="shipping_name"
                                   value="<?= htmlspecialchars($_POST['shipping_name'] ?? $user['full_name'] ?? '') ?>" required>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="shipping_phone" class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control form-control-lg" id="shipping_phone" name="shipping_phone"
                                       value="<?= htmlspecialchars($_POST['shipping_phone'] ?? $user['phone'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="payment_method" class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                                <select class="form-select form-select-lg" id="payment_method" name="payment_method" required>
                                    <option value="<?= PAYMENT_COD ?>"          <?= ($_POST['payment_method'] ?? PAYMENT_COD) === PAYMENT_COD          ? 'selected' : '' ?>>Cash on Delivery</option>
                                    <option value="<?= PAYMENT_MOBILE_MONEY ?>" <?= ($_POST['payment_method'] ?? '') === PAYMENT_MOBILE_MONEY         ? 'selected' : '' ?>>Mobile Money</option>
                                    <option value="<?= PAYMENT_BANK_TRANSFER ?>" <?= ($_POST['payment_method'] ?? '') === PAYMENT_BANK_TRANSFER       ? 'selected' : '' ?>>Bank Transfer</option>
                                    <option value="<?= PAYMENT_CARD ?>"          <?= ($_POST['payment_method'] ?? '') === PAYMENT_CARD                  ? 'selected' : '' ?>>Card Payment</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="shipping_address" class="form-label fw-semibold">Delivery Address <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-lg" id="shipping_address" name="shipping_address"
                                      rows="3" required><?= htmlspecialchars($_POST['shipping_address'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label fw-semibold">Order Notes <span class="text-muted">(optional)</span></label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"
                                      placeholder="Special delivery instructions, etc."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Order Summary -->
            <div class="col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-header fw-bold bg-gold text-white">
                        <i class="bi bi-cart-check me-1"></i> Order Summary
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-borderless mb-0 small">
                                <thead>
                                    <tr class="border-bottom">
                                        <th class="ps-3">Product</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end pe-3">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <span class="fw-semibold"><?= htmlspecialchars($item['name']) ?></span>
                                                <br><small class="text-muted"><?= htmlspecialchars($item['code']) ?></small>
                                            </td>
                                            <td class="text-center"><?= (int) $item['quantity'] ?></td>
                                            <td class="text-end pe-3"><?= formatPrice((float) $item['subtotal']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-top">
                            <table class="table table-borderless mb-0 small">
                                <tr>
                                    <td class="text-muted ps-0">Subtotal</td>
                                    <td class="text-end pe-0 fw-semibold"><?= formatPrice($cartSubtotal) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Shipping</td>
                                    <td class="text-end pe-0"><?= formatPrice($shipping) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Tax</td>
                                    <td class="text-end pe-0"><?= formatPrice($tax) ?></td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="px-0 py-1"><hr class="my-1"></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold fs-6 ps-0 text-primary">Grand Total</td>
                                    <td class="text-end fw-bold fs-6 pe-0 text-green"><?= formatPrice($grandTotal) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-lg py-3 fw-semibold rounded-3 btn-green"
                            id="placeOrderBtn">
                        <i class="bi bi-check2-circle me-1"></i> Place Order
                    </button>
                    <a href="<?= SITE_URL ?>pages/cart/index.php" class="btn btn-outline-secondary py-2">
                        <i class="bi bi-arrow-left me-1"></i> Back to Cart
                    </a>
                </div>
            </div>

        </div>
    </form>
</div>

<script>
document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
    var btn = document.getElementById('placeOrderBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
