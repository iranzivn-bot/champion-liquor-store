<?php
/**
 * Password Reset Email Template
 *
 * Sent to users who request a password reset link.
 *
 * This is a STRUCTURE-ONLY template.
 * Email sending and token generation will be implemented in a future iteration.
 *
 * Available variables (set before including this template):
 *   $userName     — The user's full name
 *   $resetLink    — The password reset URL (with token)
 *   $expiryHours  — Number of hours until the reset link expires
 *
 * PHP 8.3
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password — <?= htmlspecialchars(SITE_NAME) ?></title>
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
        .warning-box {
            background-color: #fff8e6;
            border-left: 4px solid #C9A227;
            padding: 16px 20px;
            margin: 20px 0;
            border-radius: 4px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <!-- Header -->
        <div class="email-header">
            <h1><?= htmlspecialchars(SITE_NAME) ?></h1>
            <p>Password Reset Request</p>
        </div>

        <!-- Body -->
        <div class="email-body">
            <h2>Hello, <?= htmlspecialchars($userName ?? 'Valued Customer') ?>!</h2>

            <p>
                We received a request to reset the password for your
                <strong><?= htmlspecialchars(SITE_NAME) ?></strong> account.
            </p>

            <p style="text-align: center;">
                <a href="<?= htmlspecialchars($resetLink ?? '#') ?>" class="btn-primary">
                    Reset My Password
                </a>
            </p>

            <div class="warning-box">
                <strong><i class="bi bi-clock"></i> Link expires in <?= (int)($expiryHours ?? 1) ?> hour(s).</strong><br>
                If you didn't request a password reset, please ignore this email.
                Your password will remain unchanged.
            </div>

            <p style="font-size: 14px; color: #666666;">
                If the button above doesn't work, copy and paste this URL into your browser:<br>
                <span style="color: #001F5B; word-break: break-all;">
                    <?= htmlspecialchars($resetLink ?? '#') ?>
                </span>
            </p>

            <hr class="divider">

            <p style="font-size: 14px; color: #666666;">
                Need help? <a href="<?= SITE_URL ?>pages/contact.php">Contact our support team</a>.
            </p>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p>
                &copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?>. All rights reserved.<br>
                <a href="<?= SITE_URL ?>">Visit our website</a>
            </p>
        </div>
    </div>
</body>
</html>
