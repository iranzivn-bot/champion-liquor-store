<?php
/**
 * Shopping Cart Page
 *
 * Displays the logged-in user's cart items with quantity controls,
 * line subtotals, cart summary (subtotal / shipping / tax / grand total),
 * stock validation, statistics, and AJAX-driven updates.
 *
 * PHP 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

$pdo    = getDbConnection();
$userId = (int) $_SESSION['user_id'];

// ─── Shipping & tax from constants ─────────────────────────────────────
$shipping = DEFAULT_SHIPPING;
$tax      = DEFAULT_TAX;

// ─── Fetch cart items with product details ────────────────────────────
// NOTE: c.price is the price snapshot stored when the item was added.
// c.subtotal = c.price * c.quantity (pre-computed for fast reads).
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

$cartSubtotal = 0;
$cartCount    = 0;
$hasOutOfStock = false;

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container py-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>index.php"><?= lang('home') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= lang('shopping_cart') ?></li>
        </ol>
    </nav>

    <h2 class="fw-bold mb-4 text-primary">
        <i class="bi bi-cart3"></i> <?= lang('shopping_cart') ?>
    </h2>

    <?php if (count($items) === 0): ?>

        <!-- ═══════════════════════════════════════════════════════════════
             PART 9 — Empty Cart State
             ═══════════════════════════════════════════════════════════════ -->
        <div class="text-center py-5">
            <i class="bi bi-cart-x text-muted" style="font-size: 5rem; opacity: 0.4;"></i>
            <h4 class="text-muted mt-3"><?= lang('cart_empty') ?></h4>
            <p class="text-muted mb-4">Looks like you haven't added any products yet.</p>
            <a href="<?= SITE_URL ?>pages/shop.php" class="btn btn-gold btn-lg px-5 py-3 fw-semibold">
                <i class="bi bi-shop"></i> <?= lang('continue_shopping') ?>
            </a>
        </div>

    <?php else: ?>

        <!-- ── Flash Messages ────────────────────────────────────────── -->
        <div id="cart-alerts"></div>

        <!-- ═══════════════════════════════════════════════════════════════
             PART 6 — Stock Validation Warning
             ═══════════════════════════════════════════════════════════════ -->
        <?php
        $hasOutOfStock = false;
        foreach ($items as $item) {
            if ((int) $item['stock_quantity'] <= 0) {
                $hasOutOfStock = true;
                break;
            }
        }
        ?>
        <?php if ($hasOutOfStock): ?>
            <div class="alert alert-warning d-flex align-items-center gap-2 rounded-3 shadow-sm mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <div><strong>Some products are out of stock.</strong> Please remove them before proceeding to checkout.</div>
            </div>
        <?php endif; ?>

        <!-- ═══════════════════════════════════════════════════════════════
             PART 10 — Cart Statistics (Items, Quantity, Total)
             ═══════════════════════════════════════════════════════════════ -->
        <?php
        $itemCount = count($items);
        $qtyCount  = 0;
        $subTotalCalc = 0.0;
        foreach ($items as $item) {
            $qtyCount     += (int) $item['quantity'];
            $subTotalCalc += (float) $item['subtotal'];
        }
        ?>
        <div class="cart-stats-bar d-flex flex-wrap gap-4 mb-4 p-3">
            <div class="d-flex align-items-center gap-2 stat-item">
                <i class="bi bi-box-seam text-primary fs-5"></i>
                <span class="text-muted small">Items:</span>
                <span class="fw-bold" id="cart-stat-items"><?= $itemCount ?></span>
            </div>
            <div class="d-flex align-items-center gap-2 stat-item">
                <i class="bi bi-sort-numeric-up text-gold fs-5"></i>
                <span class="text-muted small">Quantity:</span>
                <span class="fw-bold" id="cart-stat-qty"><?= $qtyCount ?></span>
            </div>
            <div class="d-flex align-items-center gap-2 stat-item">
                <i class="bi bi-currency-dollar text-green fs-5"></i>
                <span class="text-muted small">Total:</span>
                <span class="fw-bold"><?= formatPrice($subTotalCalc) ?></span>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             PART 5+7 — Cart Items Table (Product Image, Price, Quantity,
             Subtotal, Action) with stock/quantity validation
             ═══════════════════════════════════════════════════════════════ -->
        <div class="table-responsive rounded-3 shadow-sm">
            <table class="table cart-table align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 45%;">Product</th>
                        <th class="text-center" style="width: 15%;">Price</th>
                        <th class="text-center" style="width: 15%;">Quantity</th>
                        <th class="text-end" style="width: 15%;">Subtotal</th>
                        <th class="text-center" style="width: 10%;">Action</th>
                    </tr>
                </thead>
                <tbody id="cart-items-body">
                    <?php foreach ($items as $item):
                        $cartId   = (int) $item['cart_id'];
                        $qty      = (int) $item['quantity'];
                        $price    = (float) $item['price'];
                        $subtotal = (float) $item['subtotal'];
                        $stock    = (int) $item['stock_quantity'];
                        $inStock  = $stock > 0;
                    ?>
                        <tr class="cart-item-row" data-cart-id="<?= $cartId ?>"
                            data-price="<?= $price ?>" data-stock="<?= $stock ?>">

                            <!-- Product Image + Name + Code -->
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($item['code']) ?>"
                                       class="flex-shrink-0">
                                        <img src="<?= $item['image'] ? SITE_URL . 'uploads/products/' . htmlspecialchars($item['image']) : DEFAULT_PRODUCT_IMAGE ?>"
                                             alt="<?= htmlspecialchars($item['name']) ?>"
                                             class="cart-item-img">
                                    </a>
                                    <div class="min-w-0">
                                    <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($item['code']) ?>"
                                       class="text-decoration-none fw-semibold cart-product-name text-primary">
                                            <?= htmlspecialchars($item['name']) ?>
                                        </a>
                                        <p class="small text-muted mb-1">Code: <?= htmlspecialchars($item['code']) ?></p>
                                        <?php if (!$inStock): ?>
                                            <span class="badge bg-danger">
                                                <i class="bi bi-exclamation-circle"></i> <?= lang('out_of_stock') ?>
                                            </span>
                                        <?php elseif ($stock <= 5): ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="bi bi-exclamation-triangle"></i> Only <?= $stock ?> left
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Price (from cart snapshot) -->
                            <td class="text-center" data-label="Price">
                                <span class="cart-price"><?= formatPrice($price) ?></span>
                            </td>

                            <!-- Quantity Selector (min=1, max=stock) -->
                            <td class="text-center" data-label="Quantity">
                                <?php if ($inStock): ?>
                                    <div class="qty-selector cart-qty-selector">
                                        <button type="button" class="qty-btn cart-qty-btn cart-qty-minus"
                                                aria-label="Decrease quantity"
                                                data-cart-id="<?= $cartId ?>">&minus;</button>
                                        <input type="number" class="qty-input cart-qty-input"
                                               value="<?= $qty ?>" min="1"
                                               max="<?= $stock ?>"
                                               data-cart-id="<?= $cartId ?>" readonly>
                                        <button type="button" class="qty-btn cart-qty-btn cart-qty-plus"
                                                aria-label="Increase quantity"
                                                data-cart-id="<?= $cartId ?>">+</button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-danger small fw-semibold">Unavailable</span>
                                <?php endif; ?>
                            </td>

                            <!-- Subtotal (from cart snapshot) -->
                            <td class="text-end" data-label="Subtotal">
                                <span class="cart-subtotal fw-bold text-green">
                                    <?= formatPrice($subtotal) ?>
                                </span>
                            </td>

                            <!-- Remove button -->
                            <td class="text-center" data-label="Action">
                                <button type="button" class="btn btn-sm btn-outline-danger cart-remove-btn"
                                        data-cart-id="<?= $cartId ?>"
                                        data-bs-toggle="modal" data-bs-target="#removeModal"
                                        title="<?= lang('remove') ?>">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             PART 2 — Cart Summary + Action Buttons
             ═══════════════════════════════════════════════════════════════ -->
        <div class="row mt-4 g-4">
            <!-- Left: Action Buttons -->
            <div class="col-md-6 d-flex flex-wrap gap-2 align-items-start">
                <a href="<?= SITE_URL ?>pages/shop.php"
                   class="btn btn-outline-secondary px-4 py-2 fw-semibold rounded-3">
                    <i class="bi bi-arrow-left"></i> <?= lang('continue_shopping') ?>
                </a>
                <button type="button"
                        class="btn btn-outline-danger px-4 py-2 fw-semibold rounded-3"
                        id="clearCartBtn"
                        data-bs-toggle="modal" data-bs-target="#clearModal">
                    <i class="bi bi-cart-x"></i> <?= lang('clear_cart') ?>
                </button>
            </div>

            <!-- Right: Summary Card -->
            <div class="col-md-5 ms-auto">
                <div class="cart-summary-card rounded-3 shadow-sm p-4">
                    <h6 class="fw-bold mb-3 text-primary"><?= lang('cart_summary') ?></h6>

                    <table class="table table-borderless mb-0 small">
                        <tbody>
                            <tr>
                                <td class="text-muted ps-0">Subtotal</td>
                                <td class="text-end fw-semibold pe-0" id="summary-subtotal">
                                    <?= formatPrice($subTotalCalc) ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Shipping</td>
                                <td class="text-end pe-0" id="summary-shipping">
                                    <?= formatPrice($shipping) ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Tax</td>
                                <td class="text-end pe-0" id="summary-tax">
                                    <?= formatPrice($tax) ?>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" class="px-0 py-1">
                                    <hr class="my-1">
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold fs-6 ps-0 text-primary">Grand Total</td>
                                <td class="text-end fw-bold fs-6 pe-0 text-green"
                                    id="summary-grand-total">
                                    <?= formatPrice($subTotalCalc + $shipping + $tax) ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Checkout Button (disabled if any item is out of stock) -->
                    <a href="<?= $hasOutOfStock ? '#' : SITE_URL . 'pages/cart/checkout.php' ?>"
                       class="btn btn-lg w-100 mt-3 py-3 fw-semibold rounded-3 checkout-btn
                              <?= $hasOutOfStock ? 'disabled' : '' ?>"
                       style="background: #C9A227; color: #fff; <?= $hasOutOfStock ? 'opacity: 0.5; pointer-events: none;' : '' ?>"
                       id="checkoutBtn">
                        <i class="bi bi-credit-card"></i> Proceed to Checkout
                    </a>
                    <?php if ($hasOutOfStock): ?>
                        <p class="small text-danger mt-2 mb-0 text-center">
                            <i class="bi bi-exclamation-circle"></i>
                            Remove out-of-stock items to proceed.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             Remove Confirmation Modal
             ═══════════════════════════════════════════════════════════════ -->
        <div class="modal fade" id="removeModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-3">
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold" style="color: #001F5B;">
                            <i class="bi bi-trash3 me-1"></i> Remove Item
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center py-4">
                        <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                        <p class="fw-semibold mt-3 mb-0">Remove this item from your cart?</p>
                    </div>
                    <div class="modal-footer border-0 justify-content-center">
                        <button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger px-4 rounded-3" id="confirmRemoveBtn">Remove</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             Clear Cart Confirmation Modal
             ═══════════════════════════════════════════════════════════════ -->
        <div class="modal fade" id="clearModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-3">
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold" style="color: #001F5B;">
                            <i class="bi bi-cart-x me-1"></i> Clear Cart
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center py-4">
                        <i class="bi bi-cart-x text-danger" style="font-size: 3rem;"></i>
                        <p class="fw-semibold mt-3 mb-0">Remove all items from your cart?</p>
                    </div>
                    <div class="modal-footer border-0 justify-content-center">
                        <button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger px-4 rounded-3" id="confirmClearBtn">Clear Cart</button>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<!-- ── Hidden data for JS ─────────────────────────────────────────── -->
<script>
    window.cartItems = <?= json_encode(array_map(fn($item) => [
        'cart_id'  => (int) $item['cart_id'],
        'price'    => (float) $item['price'],
        'stock'    => (int) $item['stock_quantity'],
        'quantity' => (int) $item['quantity'],
    ], $items)) ?>;
    window.hasOutOfStock = <?= $hasOutOfStock ? 'true' : 'false' ?>;
    window.shipping = <?= $shipping ?>;
    window.tax      = <?= $tax ?>;
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
