<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

$pdo    = getDbConnection();
$userId = (int) $_SESSION['user_id'];

$orderId = max(0, (int) ($_GET['order_id'] ?? 0));
if ($orderId === 0) {
    redirect(SITE_URL . 'pages/cart/index.php');
}

$stmt = $pdo->prepare('
    SELECT o.*
    FROM orders o
    WHERE o.id = :id AND o.user_id = :uid
');
$stmt->execute([':id' => $orderId, ':uid' => $userId]);
$order = $stmt->fetch();

if (!$order) {
    redirect(SITE_URL . 'pages/cart/index.php');
}

// Fetch order items
$itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :oid ORDER BY id ASC');
$itemStmt->execute([':oid' => $orderId]);
$items = $itemStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Order",
  "orderNumber": "<?= htmlspecialchars($order['order_number'] ?? '') ?>",
  "priceCurrency": "RWF",
  "price": "<?= (float)($order['grand_total'] ?? 0) ?>",
  "acceptedOffer": [
    {
      "@type": "Offer",
      "itemOffered": { "@type": "Product", "name": "Order from <?= SITE_NAME ?>" }
    }
  ]
}
</script>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>pages/cart/index.php">Cart</a></li>
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>pages/cart/checkout.php">Checkout</a></li>
            <li class="breadcrumb-item active" aria-current="page">Order Confirmed</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card auth-card">
                <div class="card-body p-5 text-center">

                    <div class="py-3">
                        <i class="bi bi-check-circle-fill" style="font-size: 5rem; color: #0B6B2F;"></i>
                    </div>

                    <h2 class="fw-bold mb-2" style="color: #001F5B;">Order Confirmed!</h2>
                    <p class="text-muted mb-1" style="font-size: 1.1rem;">
                        Thank you for your purchase, <strong><?= htmlspecialchars($order['customer_name'] ?? '') ?></strong>!
                    </p>
                    <p class="text-muted mb-4">
                        Your order has been placed successfully.
                    </p>

                    <div class="d-inline-block text-start bg-light rounded-3 p-4 mb-4 w-100" style="max-width: 480px;">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Order Number</span>
                            <span class="fw-bold" style="color: #001F5B;"><?= htmlspecialchars($order['order_number']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Date</span>
                            <span><?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Payment Method</span>
                            <span><?= ucfirst(str_replace('_', ' ', htmlspecialchars($order['payment_method']))) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Payment Status</span>
                            <span class="badge bg-warning text-dark"><?= ucfirst(htmlspecialchars($order['payment_status'])) ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold" style="color: #001F5B;"><?= ($order['payment_status'] ?? '') === 'paid' ? 'Total Charged' : 'Total Due' ?></span>
                            <span class="fw-bold fs-5" style="color: #0B6B2F;"><?= formatPrice((float) $order['grand_total']) ?></span>
                        </div>
                    </div>

                    <!-- Order Items Summary -->
                    <div class="text-start bg-light rounded-3 p-4 mb-4 w-100" style="max-width: 480px; margin: 0 auto;">
                        <h6 class="fw-bold mb-3" style="color: #001F5B;">
                            <i class="bi bi-box-seam me-1"></i> Items Ordered (<?= count($items) ?>)
                        </h6>
                        <?php foreach ($items as $item): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <span class="fw-semibold"><?= htmlspecialchars($item['product_name']) ?></span>
                                    <small class="text-muted d-block">Qty: <?= (int) $item['quantity'] ?> &times; <?= formatPrice((float) $item['price']) ?></small>
                                </div>
                                <span class="fw-semibold" style="color: #0B6B2F;"><?= formatPrice((float) $item['subtotal']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Shipping Info -->
                    <div class="text-start bg-light rounded-3 p-4 mb-4 w-100" style="max-width: 480px; margin: 0 auto;">
                        <h6 class="fw-bold mb-3" style="color: #001F5B;">
                            <i class="bi bi-truck me-1"></i> Shipping Details
                        </h6>
                        <p class="mb-1"><strong><?= htmlspecialchars($order['shipping_name']) ?></strong></p>
                        <p class="mb-1"><?= htmlspecialchars($order['shipping_phone']) ?></p>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></p>
                    </div>

                    <p class="text-muted small mb-4">
                        You will receive an email confirmation shortly.
                    </p>

                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        <a href="<?= SITE_URL ?>pages/dashboard/orders.php" class="btn px-4 py-2 fw-semibold"
                           style="background: #001F5B; color: #fff;">
                            <i class="bi bi-bag-check me-1"></i> View My Orders
                        </a>
                        <a href="<?= SITE_URL ?>index.php" class="btn btn-outline-secondary px-4 py-2 fw-semibold">
                            <i class="bi bi-house-door-fill me-1"></i> Home
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
