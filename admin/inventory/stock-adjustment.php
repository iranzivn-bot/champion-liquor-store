<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();

$pageTitle = 'Stock Adjustment';
require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= SITE_URL ?>assets/css/reports/inventory.css">

<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div>
                <h1 class="fs-3 fw-bold" style="color: var(--admin-primary);">Stock Adjustment</h1>
                <p class="text-muted">Manually increase or decrease stock levels.</p>
            </div>
            <a href="<?= SITE_URL ?>admin/inventory/index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
        </div>

        <?php
        $db = getDbConnection();
        $products = $db->query("SELECT id, code, name, stock_quantity FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $errors = [];
        $success = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $productId = (int)($_POST['product_id'] ?? 0);
            $adjustment = (int)($_POST['adjustment'] ?? 0);
            $reason     = trim($_POST['reason'] ?? '');

            if ($productId <= 0) { $errors[] = 'Please select a product.'; }
            if ($adjustment === 0) { $errors[] = 'Adjustment cannot be zero.'; }
            if (empty($reason)) { $errors[] = 'Reason is required for adjustments.'; }

            if (empty($errors)) {
                try {
                    $db->beginTransaction();

                    $stmt = $db->prepare("SELECT id, stock_quantity FROM products WHERE id = ? FOR UPDATE");
                    $stmt->execute([$productId]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$product) throw new Exception('Product not found.');

                    $stockBefore = (int)$product['stock_quantity'];
                    $stockAfter  = $stockBefore + $adjustment;

                    if ($stockAfter < 0) throw new Exception('Adjustment would result in negative stock. Current stock: ' . $stockBefore);

                    $stmt = $db->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                    $stmt->execute([$stockAfter, $productId]);

                    $movementType = $adjustment > 0 ? INV_MOVEMENT_STOCK_IN : INV_MOVEMENT_STOCK_OUT;

                    $stmt = $db->prepare("
                        INSERT INTO inventory_movements
                            (product_id, movement_type, quantity, stock_before, stock_after, reference_type, notes, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$productId, $movementType, $adjustment, $stockBefore, $stockAfter, INV_REFERENCE_ADJUSTMENT, 'Adjustment: ' . $reason, $_SESSION['user_id']]);

                    $db->commit();
                    logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'inventory', 'stock_adjustment', $productId, 'Stock adjustment: ' . ($adjustment > 0 ? '+' : '') . $adjustment . ' for product ID ' . $productId . ' (was: ' . $stockBefore . ', now: ' . $stockAfter . '). Reason: ' . $reason);
                    $success = true;
                } catch (Exception $e) {
                    $db->rollBack();
                    $errors[] = 'Error: ' . $e->getMessage();
                }
            }
        }
        ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> Stock adjusted successfully!
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
                <form method="post" onsubmit="return confirm('Are you sure you want to adjust stock for this product?');">
                    <div class="mb-3">
                        <label for="product_id" class="form-label fw-semibold">Product <span class="text-danger">*</span></label>
                        <select name="product_id" id="product_id" class="form-select" required>
                            <option value="">-- Select Product --</option>
                            <?php foreach ($products as $p): ?>
                            <option value="<?= (int)$p['id'] ?>">
                                [<?= htmlspecialchars($p['code']) ?>] <?= htmlspecialchars($p['name']) ?>
                                (Stock: <?= (int)$p['stock_quantity'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="adjustment" class="form-label fw-semibold">Adjustment <span class="text-danger">*</span></label>
                        <input type="number" name="adjustment" id="adjustment" class="form-control" step="1" required value="0"
                               placeholder="Positive to increase, negative to decrease">
                        <div class="form-text">
                            <span class="text-success">Positive</span> = increase stock,
                            <span class="text-danger">negative</span> = decrease stock.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="reason" class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" id="reason" class="form-control" rows="2"
                                  placeholder="e.g. Damage, theft, recount, found discrepancy..."></textarea>
                    </div>

                    <button type="submit" class="btn fw-semibold" style="background: var(--admin-primary); color: #fff;">
                        <i class="bi bi-pencil"></i> Apply Adjustment
                    </button>
                </form>
            </div>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
