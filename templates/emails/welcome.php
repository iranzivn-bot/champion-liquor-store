<?php
/**
 * Welcome Email Template
 *
 * Sent to new users after successful registration.
 *
 * This is a STRUCTURE-ONLY template.
 * Email sending will be implemented in a future iteration.
 *
 * Available variables (set before including this template):
 *   $userName  — The new user's full name
 *   $userEmail — The new user's email address
 *
 * PHP 8.3
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to <?= htmlspecialchars(SITE_NAME) ?></title>
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
            background-color: #001F5B;
            padding: 40px 30px;
            text-align: center;
        }
        .email-header h1 {
            color: #C9A227;
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
        .btn-primary {
            display: inline-block;
            padding: 14px 32px;
            background-color: #C9A227;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            margin: 20px 0;
        }
        .btn-primary:hover {
            background-color: #b08a1f;
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
        .highlight-box {
            background-color: #f0f4ff;
            border-left: 4px solid #001F5B;
            padding: 16px 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <!-- Header -->
        <div class="email-header">
            <h1><?= htmlspecialchars(SITE_NAME) ?></h1>
            <p>Premium Wines, Spirits &amp; Beverages</p>
        </div>

        <!-- Body -->
        <div class="email-body">
            <h2>Welcome, <?= htmlspecialchars($userName ?? 'Valued Customer') ?>! <i class="bi bi-emoji-smile"></i></h2>

            <p>
                Thank you for creating an account with <strong><?= htmlspecialchars(SITE_NAME) ?></strong>.
                We're excited to have you on board!
            </p>

            <div class="highlight-box">
                <p style="margin: 0;">
                    <strong>Your Account Details:</strong><br>
                    Name: <?= htmlspecialchars($userName ?? '—') ?><br>
                    Email: <?= htmlspecialchars($userEmail ?? '—') ?>
                </p>
            </div>

            <p>
                With your new account you can:
            </p>
            <ul>
                <li>Browse our wide selection of premium wines, spirits, and beverages</li>
                <li>Save your favourite products to your wishlist</li>
                <li>Track your orders and view your order history</li>
                <li>Enjoy a fast and secure checkout experience</li>
            </ul>

            <p style="text-align: center;">
                <a href="<?= SITE_URL ?>pages/shop.php" class="btn-primary">
                    Start Shopping Now
                </a>
            </p>

            <hr class="divider">

            <p style="font-size: 14px; color: #666666;">
                If you have any questions, feel free to
                <a href="<?= SITE_URL ?>pages/contact.php">contact our support team</a>.
                We're always happy to help!
            </p>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p>
                &copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?>. All rights reserved.<br>
                <a href="<?= SITE_URL ?>">Visit our website</a> &bull;
                <a href="<?= SITE_URL ?>pages/contact.php">Contact Us</a>
            </p>
            <p style="font-size: 11px; color: #aaaaaa; margin-top: 12px;">
                You received this email because you created an account on <?= htmlspecialchars(SITE_NAME) ?>.
            </p>
        </div>
    </div>
</body>
</html>
