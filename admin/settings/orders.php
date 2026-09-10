<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';
requireAdmin();
requirePermission('settings.manage');
$pageTitle = 'Order Settings';
$success = getFlashMessage('success');
$error   = getFlashMessage('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page.';
    } else {
        $fields = [
            'default_tax_rate'     => str_replace(',', '.', trim($_POST['default_tax_rate'] ?? '0')),
            'default_shipping_fee' => str_replace(',', '.', trim($_POST['default_shipping_fee'] ?? '0')),
            'low_stock_threshold'  => trim($_POST['low_stock_threshold'] ?? '5'),
            'allow_guest_checkout' => $_POST['allow_guest_checkout'] ?? 'no',
        ];

        $updated = 0;
        foreach ($fields as $key => $value) {
            $old = setting($key);
            if ((string) $old !== $value) {
                if (updateSetting($key, $value)) {
                    $updated++;
                }
            }
        }

        setFlashMessage('success', "Order settings updated successfully ({$updated} change(s)).");
        redirect(SITE_URL . 'admin/settings/orders.php');
    }
}

$default_tax_rate     = setting('default_tax_rate', '0');
$default_shipping_fee = setting('default_shipping_fee', '2000');
$low_stock_threshold  = setting('low_stock_threshold', '5');
$allow_guest_checkout = setting('allow_guest_checkout', 'no');

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="container-fluid p-4">
            <h2 class="fw-bold mb-4" style="color:#001F5B;">
                <i class="bi bi-cart"></i> Order Settings
            </h2>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <form method="POST">
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label for="default_tax_rate" class="form-label">Default Tax Rate (%)</label>
                            <input type="number" class="form-control" id="default_tax_rate" name="default_tax_rate"
                                   value="<?= htmlspecialchars($default_tax_rate) ?>" step="0.01" min="0" max="100">
                            <div class="form-text">Percentage applied to product prices (e.g., 18 for 18%).</div>
                        </div>

                        <div class="mb-3">
                            <label for="default_shipping_fee" class="form-label">Default Shipping Fee</label>
                            <input type="number" class="form-control" id="default_shipping_fee" name="default_shipping_fee"
                                   value="<?= htmlspecialchars($default_shipping_fee) ?>" step="0.01" min="0">
                            <div class="form-text">Default shipping cost per order.</div>
                        </div>

                        <div class="mb-3">
                            <label for="low_stock_threshold" class="form-label">Low Stock Threshold</label>
                            <input type="number" class="form-control" id="low_stock_threshold" name="low_stock_threshold"
                                   value="<?= htmlspecialchars($low_stock_threshold) ?>" min="0" max="99999">
                            <div class="form-text">Products with stock at or below this number are flagged as low stock.</div>
                        </div>

                        <div class="mb-3">
                            <label for="allow_guest_checkout" class="form-label">Allow Guest Checkout</label>
                            <select class="form-select" id="allow_guest_checkout" name="allow_guest_checkout">
                                <option value="yes" <?= $allow_guest_checkout === 'yes' ? 'selected' : '' ?>>Yes</option>
                                <option value="no" <?= $allow_guest_checkout === 'no' ? 'selected' : '' ?>>No</option>
                            </select>
                            <div class="form-text">Allow customers to check out without creating an account.</div>
                        </div>

                        <button type="submit" class="btn px-4" style="background:#001F5B;color:#fff;">Save Order Settings</button>
                        <a href="index.php" class="btn btn-secondary ms-2">Cancel</a>
                    </form>
                </div>
            </div>
        </div>

    </main>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
