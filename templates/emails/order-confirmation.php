<?php
/**
 * Order Confirmation Email Template
 *
 * Sent to customers after successfully placing an order.
 *
 * This is a STRUCTURE-ONLY template.
 * Order functionality and email sending will be implemented in a future iteration.
 *
 * Available variables (set before including this template):
 *   $userName      — The customer's full name
 *   $orderNumber   — The unique order reference number
 *   $orderDate     — Date the order was placed
 *   $orderTotal    — Total amount paid (formatted)
 *   $items         — Array of ordered items (name, qty, price)
 *   $shippingAddress — Delivery address
 *
 * PHP 8.3
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .email-wrapper {
            max-width: 600px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        .email-header {
            background-color: #0B6B2F;
            padding: 40px 30px;
            text-align: center;
        }
        .email-header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        .email-header p {
            color: #ffffff;
            margin: 8px 0 0 0;
            font-size: 16px;
            opacity: 0.9;
        }
        .email-body {
            padding: 40px 30px;
            color: #333333;
            line-height: 1.7;
        }
        .email-body h2 {
            color: #001F5B;
            font-size: 22px;
            margin-top: 0;
        }
        .email-body p {
            margin: 0 0 16px 0;
        }
        .order-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .order-details table {
            width: 100%;
            border-collapse: collapse;
        }
        .order-details th {
            text-align: left;
            padding: 8px 12px;
            border-bottom: 2px solid #001F5B;
            color: #001F5B;
            font-size: 14px;
        }
        .order-details td {
            padding: 10px 12px;
            border-bottom: 1px solid #eeeeee;
            font-size: 14px;
        }
        .order-details .total-row td {
            font-weight: 700;
            font-size: 16px;
            border-bottom: none;
            padding-top: 16px;
            color: #001F5B;
        }
        .btn-primary {
            display: inline-block;
            padding: 14px 32px;
            background-color: #001F5B;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            margin: 20px 0;
        }
        .btn-primary:hover {
            background-color: #001a4a;
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 24px 30px;
            text-align: center;
            font-size: 13px;
            color: #888888;
        }
        .email-footer a {
            color: #001F5B;
            text-decoration: none;
        }
        .divider {
            border: none;
            border-top: 1px solid #eeeeee;
            margin: 24px 0;
        }
        .success-icon {
            text-align: center;
            font-size: 48px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <!-- Header -->
        <div class="email-header">
            <h1><i class="bi bi-check-circle-fill"></i> Order Confirmed!</h1>
            <p><?= htmlspecialchars(SITE_NAME) ?></p>
        </div>

        <!-- Body -->
        <div class="email-body">
            <div class="success-icon"><i class="bi bi-emoji-smile"></i></div>

            <h2>Thank you, <?= htmlspecialchars($userName ?? 'Valued Customer') ?>!</h2>

            <p>
                Your order has been placed successfully. Here's a summary of your purchase.
            </p>

            <!-- Order Summary -->
            <div class="order-details">
                <table>
                    <tr>
                        <td style="padding: 4px 12px; font-size: 14px; color: #666666; border: none;">
                            <strong>Order Number:</strong>
                        </td>
                        <td style="padding: 4px 12px; font-size: 14px; border: none;">
                            #<?= htmlspecialchars((string)($orderNumber ?? '—')) ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 12px; font-size: 14px; color: #666666; border: none;">
                            <strong>Order Date:</strong>
                        </td>
                        <td style="padding: 4px 12px; font-size: 14px; border: none;">
                            <?= htmlspecialchars((string)($orderDate ?? '—')) ?>
                        </td>
                    </tr>
                </table>

                <hr class="divider">

                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th style="text-align: center;">Qty</th>
                            <th style="text-align: right;">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($items) && is_array($items)): ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['name'] ?? '—') ?></td>
                                    <td style="text-align: center;"><?= (int)($item['qty'] ?? 0) ?></td>
                                    <td style="text-align: right;"><?= htmlspecialchars($item['price'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: #999999;">
                                    Item details will appear here
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <table>
                    <tr class="total-row">
                        <td style="border: none;"></td>
                        <td style="text-align: right; border: none; padding-top: 16px;">
                            <strong>Total:</strong>
                        </td>
                        <td style="text-align: right; border: none; padding-top: 16px;">
                            <?= htmlspecialchars((string)($orderTotal ?? '—')) ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Shipping Info -->
            <div class="warning-box" style="background-color: #f0f4ff; border-left: 4px solid #001F5B; padding: 16px 20px; margin: 20px 0; border-radius: 4px; font-size: 14px;">
                <strong><i class="bi bi-box"></i> Shipping Address:</strong><br>
                <?= nl2br(htmlspecialchars($shippingAddress ?? 'To be provided during checkout')) ?>
            </div>

            <p style="text-align: center;">
                <a href="<?= SITE_URL ?>pages/dashboard/orders.php" class="btn-primary">
                    View My Orders
                </a>
            </p>

            <hr class="divider">

            <p style="font-size: 14px; color: #666666;">
                If you have any questions about your order, please
                <a href="<?= SITE_URL ?>pages/contact.php">contact our support team</a>.
            </p>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p>
                &copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?>. All rights reserved.<br>
                <a href="<?= SITE_URL ?>">Visit our website</a> &bull;
                <a href="<?= SITE_URL ?>pages/contact.php">Contact Us</a>
            </p>
        </div>
    </div>
</body>
</html>
