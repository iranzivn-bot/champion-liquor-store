<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();
requirePermission('pos.access');

$pageTitle = 'Point of Sale';

$pdo = getDbConnection();

// Initialize POS cart session
if (!isset($_SESSION['pos_cart']) || !is_array($_SESSION['pos_cart'])) {
    $_SESSION['pos_cart'] = [];
}

// ─── Handle AJAX actions ──────────────────────────────────────────────
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add_by_barcode' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $barcode = trim($_POST['barcode'] ?? '');
    if ($barcode === '') {
        echo json_encode(['success' => false, 'message' => 'Barcode is required']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT id, code, name, price, stock_quantity, image FROM products WHERE barcode = :barcode AND status = :active LIMIT 1');
    $stmt->execute([':barcode' => $barcode, ':active' => ACTIVE_STATUS]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'No active product found with this barcode']);
        exit;
    }
    if ((int) $product['stock_quantity'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Product "' . htmlspecialchars($product['name']) . '" is out of stock']);
        exit;
    }
    $pid = (int) $product['id'];
    if (isset($_SESSION['pos_cart'][$pid])) {
        $_SESSION['pos_cart'][$pid]['quantity']++;
    } else {
        $_SESSION['pos_cart'][$pid] = [
            'id'       => $pid,
            'code'     => $product['code'],
            'name'     => $product['name'],
            'price'    => (float) $product['price'],
            'quantity' => 1,
            'image'    => $product['image'],
        ];
    }
    echo json_encode(['success' => true, 'product' => $product]);
    exit;
}

if ($action === 'search' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if ($q === '') {
        echo json_encode(['success' => false, 'products' => []]);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id, code, name, price, stock_quantity, image FROM products WHERE (name LIKE :q OR code LIKE :q2 OR barcode LIKE :q3) AND status = :active ORDER BY name ASC LIMIT 20");
    $stmt->execute([':q' => "%{$q}%", ':q2' => "%{$q}%", ':q3' => "%{$q}%", ':active' => ACTIVE_STATUS]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'products' => $products]);
    exit;
}

if ($action === 'update_qty' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $pid = (int) ($_POST['product_id'] ?? 0);
    $qty = (int) ($_POST['quantity'] ?? 0);
    if ($pid <= 0 || $qty < 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    if ($qty === 0) {
        unset($_SESSION['pos_cart'][$pid]);
    } elseif (isset($_SESSION['pos_cart'][$pid])) {
        // Check stock
        $stmt = $pdo->prepare('SELECT stock_quantity FROM products WHERE id = :id');
        $stmt->execute([':id' => $pid]);
        $maxStock = (int) $stmt->fetchColumn();
        if ($qty > $maxStock) {
            echo json_encode(['success' => false, 'message' => 'Not enough stock. Available: ' . $maxStock]);
            exit;
        }
        $_SESSION['pos_cart'][$pid]['quantity'] = $qty;
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $pid = (int) ($_POST['product_id'] ?? 0);
    unset($_SESSION['pos_cart'][$pid]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $_SESSION['pos_cart'] = [];
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'cart_data') {
    header('Content-Type: application/json');
    $cart = array_values($_SESSION['pos_cart']);
    echo json_encode(['success' => true, 'cart' => $cart]);
    exit;
}

if ($action === 'add_by_id' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $pid = (int) ($_POST['product_id'] ?? 0);
    if ($pid <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT id, code, name, price, stock_quantity, image FROM products WHERE id = :id AND status = :active LIMIT 1');
    $stmt->execute([':id' => $pid, ':active' => ACTIVE_STATUS]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found or inactive']);
        exit;
    }
    if ((int) $product['stock_quantity'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Product "' . htmlspecialchars($product['name']) . '" is out of stock']);
        exit;
    }
    if (isset($_SESSION['pos_cart'][$pid])) {
        $_SESSION['pos_cart'][$pid]['quantity']++;
    } else {
        $_SESSION['pos_cart'][$pid] = [
            'id'       => $pid,
            'code'     => $product['code'],
            'name'     => $product['name'],
            'price'    => (float) $product['price'],
            'quantity' => 1,
            'image'    => $product['image'],
        ];
    }
    echo json_encode(['success' => true, 'product' => $product]);
    exit;
}

// ─── Complete Sale ────────────────────────────────────────────────────
$saleResult = '';
$saleError  = '';
$lastOrderId = null;

if ($action === 'checkout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $saleError = 'Invalid security token.';
    } elseif (empty($_SESSION['pos_cart'])) {
        $saleError = 'Cart is empty.';
    } else {
        $customerName  = trim($_POST['customer_name'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $paymentMethod = $_POST['payment_method'] ?? PAYMENT_CASH;
        $amountTendered = (float) ($_POST['amount_tendered'] ?? 0);

        $validMethods = [PAYMENT_CASH, PAYMENT_MOBILE_MONEY, PAYMENT_CARD];
        if (!in_array($paymentMethod, $validMethods, true)) {
            $paymentMethod = PAYMENT_CASH;
        }

        // Calculate totals
        $subtotal = 0;
        foreach ($_SESSION['pos_cart'] as $item) {
            $subtotal += (float) $item['price'] * (int) $item['quantity'];
        }
        $tax        = $subtotal * 0.18; // 18% VAT
        $grandTotal = $subtotal + $tax;

        if ($paymentMethod === PAYMENT_CASH && $amountTendered < $grandTotal) {
            $saleError = 'Amount tendered is less than the total.';
        } else {
            try {
                $pdo->beginTransaction();

                // Generate order number
                $year = date('Y');
                $seqStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_number LIKE :prefix");
                $seqStmt->execute([':prefix' => "CLS-{$year}-%"]);
                $seqNumber = str_pad((string) ((int) $seqStmt->fetchColumn() + 1), 6, '0', STR_PAD_LEFT);
                $orderNumber = "CLS-{$year}-{$seqNumber}";

                // Create order
                $orderStmt = $pdo->prepare('
                    INSERT INTO orders (order_number, user_id, shipping_name, shipping_phone, shipping_address,
                                        payment_method, subtotal, shipping, tax, grand_total, status, payment_status, notes,
                                        customer_name, customer_email, customer_phone, customer_address, delivery_status)
                    VALUES (:order_number, :user_id, :shipping_name, :shipping_phone, :shipping_address,
                            :payment_method, :subtotal, :shipping, :tax, :grand_total, :status, :payment_status, :notes,
                            :customer_name, :customer_email, :customer_phone, :customer_address, :delivery_status)
                ');
                $orderStmt->execute([
                    ':order_number'     => $orderNumber,
                    ':user_id'          => (int) $_SESSION['user_id'],
                    ':shipping_name'    => $customerName ?: 'POS Customer',
                    ':shipping_phone'   => $customerPhone ?: 'N/A',
                    ':shipping_address' => 'POS Sale',
                    ':payment_method'   => $paymentMethod,
                    ':subtotal'         => $subtotal,
                    ':shipping'         => 0,
                    ':tax'              => $tax,
                    ':grand_total'      => $grandTotal,
                    ':status'           => ORDER_CONFIRMED,
                    ':payment_status'   => PAYMENT_PAID,
                    ':notes'            => 'POS sale by ' . ($_SESSION['user_name'] ?? 'admin'),
                    ':customer_name'    => $customerName ?: 'POS Customer',
                    ':customer_email'   => '',
                    ':customer_phone'   => $customerPhone ?: 'N/A',
                    ':customer_address' => 'POS Sale',
                    ':delivery_status'  => DELIVERY_DELIVERED,
                ]);
                $orderId = (int) $pdo->lastInsertId();

                // Insert order items and deduct stock
                $itemStmt = $pdo->prepare('
                    INSERT INTO order_items (order_id, product_id, product_name, product_code, price, quantity, subtotal)
                    VALUES (:order_id, :product_id, :product_name, :product_code, :price, :quantity, :subtotal)
                ');
                $stockStmt = $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity - :qty WHERE id = :id AND stock_quantity >= :qty2');

                foreach ($_SESSION['pos_cart'] as $item) {
                    $itemSubtotal = (float) $item['price'] * (int) $item['quantity'];
                    $itemStmt->execute([
                        ':order_id'     => $orderId,
                        ':product_id'   => (int) $item['id'],
                        ':product_name' => $item['name'],
                        ':product_code' => $item['code'],
                        ':price'        => $item['price'],
                        ':quantity'     => (int) $item['quantity'],
                        ':subtotal'     => $itemSubtotal,
                    ]);

                    $stockStmt->execute([
                        ':qty'  => (int) $item['quantity'],
                        ':id'   => (int) $item['id'],
                        ':qty2' => (int) $item['quantity'],
                    ]);

                    // Inventory movement
                    $movStmt = $pdo->prepare("
                        INSERT INTO inventory_movements (product_id, movement_type, quantity, stock_before, stock_after, reference_type, reference_id, notes, created_by)
                        VALUES (?, ?, ?, (SELECT stock_quantity + ? FROM products WHERE id = ?), (SELECT stock_quantity FROM products WHERE id = ?), 'order', ?, ?, ?)
                    ");
                    $movStmt->execute([
                        (int) $item['id'],
                        INV_MOVEMENT_STOCK_OUT,
                        (int) $item['quantity'],
                        (int) $item['quantity'],
                        (int) $item['id'],
                        (int) $item['id'],
                        $orderId,
                        'POS sale - ' . $orderNumber,
                        (int) $_SESSION['user_id'],
                    ]);
                }

                $pdo->commit();

                $lastOrderId = $orderId;
                $saleResult = $orderNumber;
                $_SESSION['pos_cart'] = [];

                logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'orders', 'pos_sale', $orderNumber, 'POS sale completed: ' . $orderNumber . ' - ' . number_format($grandTotal, 2) . ' RWF (' . $paymentMethod . ')');

            } catch (\Throwable $e) {
                $pdo->rollBack();
                $saleError = 'Error processing sale: ' . $e->getMessage();
            }
        }
    }
}

// ─── Page Load ────────────────────────────────────────────────────────
require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>

<style>
.pos-layout { display: flex; gap: 1.5rem; min-height: calc(100vh - 140px); }
.pos-left { flex: 1; min-width: 0; }
.pos-right { width: 380px; flex-shrink: 0; }
.pos-cart-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-bottom: 1px solid #e9ecef; }
.pos-cart-item:last-child { border-bottom: none; }
.pos-cart-item .item-info { flex: 1; min-width: 0; }
.pos-cart-item .item-name { font-weight: 600; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pos-cart-item .item-meta { font-size: 0.8rem; color: #6c757d; }
.pos-qty-btn { width: 28px; height: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem; }
.pos-qty-input { width: 40px; text-align: center; border: 1px solid #dee2e6; border-radius: 4px; padding: 2px; font-size: 0.85rem; }
.pos-total-row { display: flex; justify-content: space-between; padding: 0.5rem 0; font-size: 0.95rem; }
.pos-grand-total { font-size: 1.3rem; font-weight: 700; color: #001F5B; border-top: 2px solid #001F5B; padding-top: 0.75rem; margin-top: 0.5rem; }
.product-search-result { cursor: pointer; padding: 0.5rem 0.75rem; border-bottom: 1px solid #f0f0f0; transition: background 0.15s; }
.product-search-result:hover { background: #f0f4ff; }
#searchResults { max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 6px; display: none; position: absolute; background: #fff; z-index: 1050; width: 100%; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
#barcodeInput { font-size: 1.15rem; letter-spacing: 1px; }
@media (max-width: 768px) { .pos-layout { flex-direction: column; } .pos-right { width: 100%; } }
</style>

<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div>
                <h1 class="fs-3 fw-bold" style="color: var(--admin-primary);">
                    <i class="bi bi-cart3"></i> Point of Sale
                </h1>
                <p class="text-muted">Scan barcode or search products to build a sale.</p>
            </div>
            <a href="<?= SITE_URL ?>admin/dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </div>

        <?php if ($saleResult): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i>
                Sale <strong><?= htmlspecialchars($saleResult) ?></strong> completed successfully!
                <a href="<?= SITE_URL ?>admin/pos/receipt.php?id=<?= (int) $lastOrderId ?>" class="btn btn-sm btn-outline-success ms-3" target="_blank">
                    <i class="bi bi-receipt"></i> Print Receipt
                </a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($saleError): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($saleError) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="pos-layout">

            <!-- ─── LEFT: Scanner & Products ─────────────────────────────── -->
            <div class="pos-left">
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <label class="form-label fw-semibold"><i class="bi bi-upc-scan"></i> Scan Barcode</label>
                        <div class="input-group">
                            <input type="text" id="barcodeInput" class="form-control form-control-lg"
                                   placeholder="Scan barcode..." autocomplete="off" autofocus>
                            <button class="btn btn-primary" type="button" id="addBarcodeBtn">
                                <i class="bi bi-plus-lg"></i> Add
                            </button>
                        </div>
                        <div id="barcodeFeedback" class="form-text mt-1"></div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3" style="position:relative;">
                    <div class="card-body">
                        <label class="form-label fw-semibold"><i class="bi bi-search"></i> Search Products</label>
                        <input type="text" id="searchInput" class="form-control"
                               placeholder="Type to search by name, code or barcode..." autocomplete="off">
                        <div id="searchResults"></div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3" style="color: var(--admin-primary);">
                            <i class="bi bi-people"></i> Customer (Optional)
                        </h6>
                        <div class="row g-2">
                            <div class="col">
                                <input type="text" id="customerName" class="form-control" placeholder="Customer name">
                            </div>
                            <div class="col">
                                <input type="text" id="customerPhone" class="form-control" placeholder="Phone number">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── RIGHT: Cart & Checkout ────────────────────────────────── -->
            <div class="pos-right">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center"
                         style="background: var(--admin-primary); color: #fff;">
                        <span class="fw-bold"><i class="bi bi-basket"></i> Current Sale</span>
                        <span class="badge bg-light text-dark" id="cartCount">0</span>
                    </div>
                    <div class="card-body p-0" id="cartItems">
                        <div class="text-center text-muted py-4" id="emptyCart">
                            <i class="bi bi-cart" style="font-size:2rem;"></i>
                            <p class="mt-2 mb-0">Scan or search products to add</p>
                        </div>
                    </div>
                    <div class="card-body border-top" id="cartSummary">
                        <div class="pos-total-row">
                            <span>Subtotal</span>
                            <span id="cartSubtotal">0.00 RWF</span>
                        </div>
                        <div class="pos-total-row">
                            <span>VAT (18%)</span>
                            <span id="cartTax">0.00 RWF</span>
                        </div>
                        <div class="pos-total-row pos-grand-total">
                            <span>Total</span>
                            <span id="cartTotal">0.00 RWF</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="checkoutForm">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="checkout">
                            <input type="hidden" name="customer_name" id="hiddenCustomerName" value="">
                            <input type="hidden" name="customer_phone" id="hiddenCustomerPhone" value="">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Payment Method</label>
                                <div class="d-flex gap-2">
                                    <label class="btn btn-outline-success payment-method-btn active" data-method="cash">
                                        <input type="radio" name="payment_method" value="cash" checked hidden>
                                        <i class="bi bi-cash"></i> Cash
                                    </label>
                                    <label class="btn btn-outline-primary payment-method-btn" data-method="mobile_money">
                                        <input type="radio" name="payment_method" value="mobile_money" hidden>
                                        <i class="bi bi-phone"></i> Mobile
                                    </label>
                                    <label class="btn btn-outline-info payment-method-btn" data-method="card">
                                        <input type="radio" name="payment_method" value="card" hidden>
                                        <i class="bi bi-credit-card"></i> Card
                                    </label>
                                </div>
                            </div>

                            <div id="cashInputGroup" class="mb-3">
                                <label class="form-label fw-semibold">Amount Tendered</label>
                                <div class="input-group">
                                    <span class="input-group-text">RWF</span>
                                    <input type="number" name="amount_tendered" id="amountTendered"
                                           class="form-control" min="0" step="100" value="0">
                                </div>
                                <div id="changeDue" class="form-text text-success fw-semibold"></div>
                            </div>

                            <button type="submit" class="btn w-100 py-2 fw-bold" id="checkoutBtn"
                                    style="background: var(--admin-primary); color: #fff; font-size:1.1rem;">
                                <i class="bi bi-check2-circle"></i> Complete Sale
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>

    </main>
</div>

<script>
var SITE_URL = '<?= SITE_URL ?>';

function htmlEsc(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }

function formatPrice(n) { return Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' RWF'; }

function renderCart() {
    fetch(SITE_URL + 'admin/pos/index.php?action=cart_data&t=' + Date.now())
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var container = document.getElementById('cartItems');
            var empty = document.getElementById('emptyCart');
            var summary = document.getElementById('cartSummary');
            var count = document.getElementById('cartCount');

            if (!data.cart || data.cart.length === 0) {
                container.innerHTML = '<div class="text-center text-muted py-4" id="emptyCart"><i class="bi bi-cart" style="font-size:2rem;"></i><p class="mt-2 mb-0">Scan or search products to add</p></div>';
                summary.querySelector('#cartSubtotal').textContent = '0.00 RWF';
                summary.querySelector('#cartTax').textContent = '0.00 RWF';
                summary.querySelector('#cartTotal').textContent = '0.00 RWF';
                count.textContent = '0';
                return;
            }

            var html = '';
            var subtotal = 0;
            data.cart.forEach(function(item) {
                var itemTotal = item.price * item.quantity;
                subtotal += itemTotal;
                var imgSrc = item.image ? SITE_URL + 'uploads/products/' + encodeURIComponent(item.image) : '<?= DEFAULT_PRODUCT_IMAGE ?>';
                html += '<div class="pos-cart-item">' +
                    '<img src="' + imgSrc + '" style="width:40px;height:40px;object-fit:cover;border-radius:4px;" alt="">' +
                    '<div class="item-info">' +
                    '<div class="item-name">' + htmlEsc(item.name) + '</div>' +
                    '<div class="item-meta">' + htmlEsc(item.code) + ' &middot; ' + formatPrice(item.price) + ' each</div>' +
                    '</div>' +
                    '<div class="d-flex align-items-center gap-1 flex-shrink-0">' +
                    '<button class="btn btn-outline-secondary pos-qty-btn" onclick="updateQty(' + item.id + ', ' + (item.quantity - 1) + ')">−</button>' +
                    '<input type="text" class="pos-qty-input" value="' + item.quantity + '" onchange="updateQty(' + item.id + ', parseInt(this.value) || 0)" onfocus="this.select()">' +
                    '<button class="btn btn-outline-secondary pos-qty-btn" onclick="updateQty(' + item.id + ', ' + (item.quantity + 1) + ')">+</button>' +
                    '</div>' +
                    '<div class="fw-bold text-nowrap" style="width:80px;text-align:right;">' + formatPrice(itemTotal) + '</div>' +
                    '<button class="btn btn-sm btn-outline-danger ms-1" onclick="removeItem(' + item.id + ')" title="Remove"><i class="bi bi-x"></i></button>' +
                    '</div>';
            });
            container.innerHTML = html;

            var tax = subtotal * 0.18;
            var total = subtotal + tax;
            summary.querySelector('#cartSubtotal').textContent = formatPrice(subtotal);
            summary.querySelector('#cartTax').textContent = formatPrice(tax);
            summary.querySelector('#cartTotal').textContent = formatPrice(total);
            count.textContent = data.cart.reduce(function(s, i) { return s + i.quantity; }, 0);

            calcChange();
        });
}

function addToCart(id) {
    fetch(SITE_URL + 'admin/pos/index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=add_by_barcode&barcode=' + encodeURIComponent(id)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            renderCart();
            document.getElementById('barcodeInput').value = '';
            document.getElementById('barcodeInput').focus();
            document.getElementById('barcodeFeedback').innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Added: ' + htmlEsc(data.product.name) + '</span>';
        } else {
            document.getElementById('barcodeFeedback').innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> ' + htmlEsc(data.message) + '</span>';
        }
    });
}

function updateQty(pid, qty) {
    if (qty < 0) qty = 0;
    fetch(SITE_URL + 'admin/pos/index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=update_qty&product_id=' + pid + '&quantity=' + qty
    })
    .then(function(r) { return r.json(); })
    .then(function(data) { renderCart(); });
}

function removeItem(pid) {
    fetch(SITE_URL + 'admin/pos/index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=remove&product_id=' + pid
    })
    .then(function(r) { return r.json(); })
    .then(function(data) { renderCart(); });
}

function calcChange() {
    var totalText = document.getElementById('cartTotal').textContent.replace(/[^0-9.]/g, '');
    var total = parseFloat(totalText) || 0;
    var tendered = parseFloat(document.getElementById('amountTendered').value) || 0;
    var change = tendered - total;
    var changeEl = document.getElementById('changeDue');
    if (tendered >= total && total > 0) {
        changeEl.textContent = 'Change due: ' + formatPrice(change);
    } else {
        changeEl.textContent = '';
    }
}

function selectProduct(id, name) {
    // Add product by ID via barcode action (pass ID as barcode lookup won't work)
    // Instead, use a hidden approach: add via a simple API
    fetch(SITE_URL + 'admin/pos/index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=add_by_id&product_id=' + id
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            renderCart();
            document.getElementById('searchInput').value = '';
            document.getElementById('searchResults').style.display = 'none';
            document.getElementById('barcodeInput').focus();
        } else {
            alert(data.message);
        }
    });
}

// ─── Event Listeners ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    renderCart();

    // Barcode scan: Enter or button click
    var barcodeInput = document.getElementById('barcodeInput');
    document.getElementById('addBarcodeBtn').addEventListener('click', function() {
        var val = barcodeInput.value.trim();
        if (val) addToCart(val);
    });
    barcodeInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var val = barcodeInput.value.trim();
            if (val) addToCart(val);
        }
    });
    barcodeInput.focus();

    // Product search
    var searchInput = document.getElementById('searchInput');
    var searchResults = document.getElementById('searchResults');
    var searchTimer = null;
    searchInput.addEventListener('input', function() {
        var q = searchInput.value.trim();
        if (searchTimer) clearTimeout(searchTimer);
        if (q.length < 2) { searchResults.style.display = 'none'; return; }
        searchTimer = setTimeout(function() {
            fetch(SITE_URL + 'admin/pos/index.php?action=search&q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.products.length > 0) {
                        var html = '<div class="p-2 text-muted small">' + data.products.length + ' product(s) found</div>';
                        data.products.forEach(function(p) {
                            var img = p.image ? SITE_URL + 'uploads/products/' + encodeURIComponent(p.image) : '<?= DEFAULT_PRODUCT_IMAGE ?>';
                            html += '<div class="product-search-result" onclick="selectProduct(' + p.id + ', \'' + htmlEsc(p.name).replace(/'/g, "\\'") + '\')">' +
                                '<div class="d-flex align-items-center gap-2">' +
                                '<img src="' + img + '" style="width:32px;height:32px;object-fit:cover;border-radius:3px;">' +
                                '<div class="flex-grow-1">' +
                                '<div class="fw-semibold small">' + htmlEsc(p.name) + '</div>' +
                                '<div class="text-muted small">' + htmlEsc(p.code) + ' &middot; ' + formatPrice(p.price) + ' &middot; Stock: ' + p.stock_quantity + '</div>' +
                                '</div>' +
                                '<button class="btn btn-sm btn-primary" onclick="event.stopPropagation();selectProduct(' + p.id + ')">+</button>' +
                                '</div></div>';
                        });
                        searchResults.innerHTML = html;
                        searchResults.style.display = 'block';
                    } else {
                        searchResults.innerHTML = '<div class="p-2 text-muted small">No products found</div>';
                        searchResults.style.display = 'block';
                    }
                });
        }, 300);
    });
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    // Payment method toggle
    document.querySelectorAll('.payment-method-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.payment-method-btn').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            btn.querySelector('input[type="radio"]').checked = true;
            var method = btn.getAttribute('data-method');
            document.getElementById('cashInputGroup').style.display = method === 'cash' ? 'block' : 'none';
        });
    });

    // Change calculation
    document.getElementById('amountTendered').addEventListener('input', calcChange);

    // Customer info sync
    document.getElementById('customerName').addEventListener('input', function() {
        document.getElementById('hiddenCustomerName').value = this.value;
    });
    document.getElementById('customerPhone').addEventListener('input', function() {
        document.getElementById('hiddenCustomerPhone').value = this.value;
    });

    // Checkout form validation
    document.getElementById('checkoutForm').addEventListener('submit', function(e) {
        var activeMethod = document.querySelector('.payment-method-btn.active');
        if (activeMethod) {
            var method = activeMethod.getAttribute('data-method');
            if (method === 'cash') {
                var totalText = document.getElementById('cartTotal').textContent.replace(/[^0-9.]/g, '');
                var total = parseFloat(totalText) || 0;
                var tendered = parseFloat(document.getElementById('amountTendered').value) || 0;
                if (tendered < total) {
                    e.preventDefault();
                    alert('Amount tendered is less than the total. Please enter a correct amount.');
                    return;
                }
            }
        }
        var cartCount = document.getElementById('cartCount').textContent;
        if (parseInt(cartCount) === 0) {
            e.preventDefault();
            alert('Cart is empty. Add products before completing the sale.');
            return;
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
