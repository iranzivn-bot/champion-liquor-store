<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();

$pageTitle = 'Stock In';
require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= SITE_URL ?>assets/css/reports/inventory.css">

<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div>
                <h1 class="fs-3 fw-bold" style="color: var(--admin-primary);">Stock In</h1>
                <p class="text-muted">Add product stock to inventory.</p>
            </div>
            <a href="<?= SITE_URL ?>admin/inventory/index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
        </div>

        <?php
        $db = getDbConnection();
        $products = $db->query("SELECT id, code, name, stock_quantity, barcode FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $selectedProductId = $_GET['product_id'] ?? null;
        $errors = [];
        $success = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $productId = (int)($_POST['product_id'] ?? 0);
            $quantity  = (int)($_POST['quantity'] ?? 0);
            $notes     = trim($_POST['notes'] ?? '');

            if ($productId <= 0) { $errors[] = 'Please select a product.'; }
            if ($quantity <= 0)  { $errors[] = 'Quantity must be greater than zero.'; }

            if (empty($errors)) {
                try {
                    $db->beginTransaction();

                    $stmt = $db->prepare("SELECT id, stock_quantity FROM products WHERE id = ? FOR UPDATE");
                    $stmt->execute([$productId]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$product) throw new Exception('Product not found.');

                    $stockBefore = (int)$product['stock_quantity'];
                    $stockAfter  = $stockBefore + $quantity;

                    $stmt = $db->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                    $stmt->execute([$stockAfter, $productId]);

                    $stmt = $db->prepare("
                        INSERT INTO inventory_movements
                            (product_id, movement_type, quantity, stock_before, stock_after, reference_type, notes, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$productId, INV_MOVEMENT_STOCK_IN, $quantity, $stockBefore, $stockAfter, INV_REFERENCE_MANUAL, $notes ?: 'Manual stock in', $_SESSION['user_id']]);

                    $db->commit();
                    logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'inventory', 'stock_in', $productId, 'Stock in: ' . $quantity . ' units added to product ID ' . $productId . ' (was: ' . $stockBefore . ', now: ' . $stockAfter . ')');
                    $success = true;
                    $selectedProductId = null;
                } catch (Exception $e) {
                    $db->rollBack();
                    $errors[] = 'Error: ' . $e->getMessage();
                }
            }
        }
        ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> Stock added successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm" style="max-width: 600px;">
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-upc-scan"></i> Scan Barcode
                        </label>
                        <div class="input-group">
                            <input type="text" id="barcodeInput" class="form-control"
                                   placeholder="Scan or type barcode..." autocomplete="off"
                                   style="font-size:1.1rem;letter-spacing:1px;">
                            <button type="button" id="lookupBarcodeBtn" class="btn btn-outline-primary">
                                <i class="bi bi-search"></i> Find
                            </button>
                        </div>
                        <div id="barcodeResult" class="form-text"></div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label for="product_id" class="form-label fw-semibold">Product <span class="text-danger">*</span></label>
                        <select name="product_id" id="product_id" class="form-select" required>
                            <option value="">-- Select Product --</option>
                            <?php foreach ($products as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= $selectedProductId == $p['id'] ? 'selected' : '' ?>>
                                [<?= htmlspecialchars($p['code']) ?>] <?= htmlspecialchars($p['name']) ?>
                                (Stock: <?= (int)$p['stock_quantity'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="quantity" class="form-label fw-semibold">Quantity to Add <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" id="quantity" class="form-control" min="1" step="1" required value="1">
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="e.g. Supplier delivery, restock reason..."></textarea>
                    </div>

                    <button type="submit" class="btn fw-semibold" style="background: var(--admin-primary); color: #fff;">
                        <i class="bi bi-plus-circle"></i> Add Stock
                    </button>
                </form>
            </div>
        </div>

    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var barcodeInput = document.getElementById('barcodeInput');
    var lookupBtn = document.getElementById('lookupBarcodeBtn');
    var barcodeResult = document.getElementById('barcodeResult');
    var productSelect = document.getElementById('product_id');

    function lookupBarcode() {
        var barcode = barcodeInput.value.trim();
        if (!barcode) {
            barcodeResult.innerHTML = '<span class="text-muted">Enter a barcode to search</span>';
            return;
        }

        barcodeResult.innerHTML = '<span class="text-info"><i class="bi bi-hourglass-split"></i> Searching...</span>';

        fetch('<?= SITE_URL ?>ajax/inventory/lookup-barcode.php?barcode=' + encodeURIComponent(barcode))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    var p = data.product;
                    // Select the product in the dropdown
                    for (var i = 0; i < productSelect.options.length; i++) {
                        if (parseInt(productSelect.options[i].value) === p.id) {
                            productSelect.value = p.id;
                            break;
                        }
                    }
                    barcodeResult.innerHTML =
                        '<span class="text-success"><i class="bi bi-check-circle"></i> Found: ' +
                        '<strong>' + htmlEsc(p.code) + '</strong> — ' + htmlEsc(p.name) +
                        ' (Stock: ' + p.stock_quantity + ')</span>';
                    // Focus the quantity field for quick entry
                    document.getElementById('quantity').focus();
                } else {
                    productSelect.value = '';
                    barcodeResult.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> ' +
                        htmlEsc(data.message) + '</span>';
                }
            })
            .catch(function () {
                barcodeResult.innerHTML = '<span class="text-danger">Network error. Please try again.</span>';
            });
    }

    function htmlEsc(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    lookupBtn.addEventListener('click', lookupBarcode);
    barcodeInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            lookupBarcode();
        }
    });

    // Auto-submit on barcode scanner input (scanners send Enter key after scan)
    var scanTimer = null;
    barcodeInput.addEventListener('input', function () {
        if (scanTimer) clearTimeout(scanTimer);
        scanTimer = setTimeout(lookupBarcode, 500);
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
