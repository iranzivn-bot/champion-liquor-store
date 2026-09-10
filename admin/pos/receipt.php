<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

requireAdmin();
requirePermission('pos.access');

$orderId = (int) ($_GET['id'] ?? 0);
if ($orderId <= 0) {
    die('Invalid order ID');
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
$stmt->execute([':id' => $orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die('Order not found');
}

$stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
$stmt->execute([':order_id' => $orderId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$siteName = setting('site_name', SITE_NAME);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt - <?= htmlspecialchars($order['order_number']) ?> - <?= htmlspecialchars($siteName) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 12px; color: #000; padding: 20px; max-width: 320px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 16px; }
        .header h1 { font-size: 16px; font-weight: 700; margin-bottom: 4px; }
        .header p { font-size: 11px; color: #555; }
        .divider { border-top: 1px dashed #333; margin: 10px 0; }
        .order-info { margin-bottom: 10px; }
        .order-info div { display: flex; justify-content: space-between; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        thead th { border-bottom: 1px solid #333; padding: 4px 0; text-align: left; font-size: 11px; }
        thead th:last-child { text-align: right; }
        thead th:nth-child(2) { text-align: center; }
        tbody td { padding: 4px 0; font-size: 11px; vertical-align: top; }
        tbody td:last-child { text-align: right; white-space: nowrap; }
        tbody td:nth-child(2) { text-align: center; }
        .item-name { max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .totals { margin-top: 8px; }
        .totals div { display: flex; justify-content: space-between; font-size: 11px; padding: 2px 0; }
        .grand-total { font-size: 14px; font-weight: 700; border-top: 2px solid #000; padding-top: 6px; margin-top: 4px; }
        .payment-info { margin-top: 10px; text-align: center; font-size: 11px; }
        .payment-info .method { font-weight: 700; font-size: 13px; }
        .footer { text-align: center; margin-top: 16px; font-size: 10px; color: #888; }
        .no-print { text-align: center; margin-bottom: 16px; }
        @media print { .no-print { display: none; } body { padding: 0; } }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" style="padding:8px 24px;background:#001F5B;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px;">
            <i class="bi bi-printer"></i> Print Receipt
        </button>
        <a href="<?= SITE_URL ?>admin/pos/index.php" style="display:inline-block;padding:8px 24px;background:#6c757d;color:#fff;border:none;border-radius:4px;text-decoration:none;font-size:14px;margin-left:8px;">Back to POS</a>
        <hr style="margin:16px 0;">
    </div>

    <div class="header">
        <h1><?= htmlspecialchars($siteName) ?></h1>
        <p><?= htmlspecialchars(setting('company_address', '')) ?></p>
        <p><?= htmlspecialchars(setting('company_phone', '')) ?></p>
    </div>

    <div class="divider"></div>

    <div class="order-info">
        <div><span>Receipt #:</span><span><?= htmlspecialchars($order['order_number']) ?></span></div>
        <div><span>Date:</span><span><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span></div>
        <div><span>Cashier:</span><span><?= htmlspecialchars($order['notes'] ?? '—') ?></span></div>
        <?php if ($order['customer_name'] && $order['customer_name'] !== 'POS Customer'): ?>
            <div><span>Customer:</span><span><?= htmlspecialchars($order['customer_name']) ?></span></div>
        <?php endif; ?>
    </div>

    <div class="divider"></div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td class="item-name"><?= htmlspecialchars($item['product_name']) ?></td>
                <td><?= (int) $item['quantity'] ?></td>
                <td><?= number_format((float) $item['subtotal'], 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="divider"></div>

    <div class="totals">
        <div><span>Subtotal</span><span><?= number_format((float) $order['subtotal'], 0) ?> RWF</span></div>
        <div><span>VAT (18%)</span><span><?= number_format((float) $order['tax'], 0) ?> RWF</span></div>
        <div class="grand-total"><span>Total</span><span><?= number_format((float) $order['grand_total'], 0) ?> RWF</span></div>
    </div>

    <div class="divider"></div>

    <div class="payment-info">
        <p>Payment Method</p>
        <p class="method">
            <?php
            $methodLabels = ['cash' => 'CASH', 'mobile_money' => 'MOBILE MONEY', 'card' => 'CARD', 'cod' => 'COD'];
            echo htmlspecialchars($methodLabels[$order['payment_method']] ?? strtoupper($order['payment_method']));
            ?>
        </p>
        <p style="margin-top:4px;">Status: <strong>PAID</strong></p>
    </div>

    <div class="divider"></div>

    <div class="footer">
        <p>Thank you for your purchase!</p>
        <p><?= htmlspecialchars($siteName) ?></p>
        <p style="margin-top:4px;"><?= date('Y-m-d H:i:s') ?></p>
    </div>

</body>
</html>
