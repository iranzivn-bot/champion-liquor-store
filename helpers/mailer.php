<?php
/**
 * Mailer Helper
 *
 * Sends emails via SMTP (using PHP stream sockets) or falls back to PHP's
 * built-in mail() function. SMTP configuration is pulled from the system
 * settings table (set via admin/settings/email.php).
 *
 * Provides a single sendMail() function that returns a result array.
 *
 * Dependencies:
 *   - includes/config.php  (for SITE_URL, BASE_PATH, setting())
 *   - helpers/settings-helper.php  (for setting())
 *
 * PHP 8.3
 */

/**
 * Send an email using the configured mail method.
 *
 * Priority:
 *   1. SMTP (if smtp_host setting is non-empty)
 *   2. PHP mail() (fallback)
 *
 * @param string $to      Recipient email address
 * @param string $subject Email subject
 * @param string $body    HTML body content
 * @return array  ['success' => bool, 'error' => string|null]
 */
function sendMail(string $to, string $subject, string $body): array
{
    $smtpHost = setting('smtp_host', '');

    if ($smtpHost !== '') {
        $result = sendMailSmtp($to, $subject, $body);
        if ($result['success']) {
            return $result;
        }
        error_log('sendMail: SMTP failed: ' . ($result['error'] ?? 'unknown error'));
    }

    $result = sendMailNative($to, $subject, $body);
    if ($result['success']) {
        return $result;
    }

    // Both SMTP and mail() failed — write to file log in development
    if (ENVIRONMENT === 'development') {
        logMailToFile($to, $subject, $body);
        return ['success' => true, 'error' => null];
    }

    return $result;
}

/**
 * Send email via PHP's built-in mail() function.
 */
function sendMailNative(string $to, string $subject, string $body): array
{
    $fromName  = setting('smtp_from_name', setting('site_name', SITE_NAME));
    $fromEmail = setting('smtp_from_email', setting('company_email', SITE_EMAIL));

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . encodeHeader($fromName) . " <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: Champion Liquor Store\r\n";

    if (@mail($to, encodeHeader($subject), $body, $headers)) {
        return ['success' => true, 'error' => null];
    }

    return ['success' => false, 'error' => 'PHP mail() returned false'];
}

/**
 * Send email via SMTP using PHP stream sockets.
 *
 * Supports:
 *   - EHLO / HELO
 *   - STARTTLS (automatic if smtp_encryption = 'tls')
 *   - SSL (if smtp_encryption = 'ssl' — uses SSL context from the start)
 *   - AUTH LOGIN
 *   - MAIL FROM, RCPT TO, DATA
 *
 * @return array  ['success' => bool, 'error' => string|null]
 */
function sendMailSmtp(string $to, string $subject, string $body): array
{
    $host       = setting('smtp_host', '');
    $port       = (int) setting('smtp_port', '587');
    $username   = setting('smtp_username', '');
    $password   = setting('smtp_password', '');
    $encryption = setting('smtp_encryption', 'tls');
    $fromName   = setting('smtp_from_name', setting('site_name', SITE_NAME));
    $fromEmail  = setting('smtp_from_email', setting('company_email', SITE_EMAIL));

    if ($host === '' || $fromEmail === '') {
        return ['success' => false, 'error' => 'SMTP host or from-email not configured'];
    }

    // Build the full HTML message with headers
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . encodeHeader($fromName) . " <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: Champion Liquor Store\r\n";

    $message  = "Subject: " . encodeHeader($subject) . "\r\n";
    $message .= $headers . "\r\n";
    $message .= $body . "\r\n";

    // Open socket
    $remote = $host . ':' . $port;
    $context = stream_context_create();

    if ($encryption === 'ssl') {
        $remote = 'ssl://' . $remote;
    }

    $socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return ['success' => false, 'error' => "Socket connection failed: {$errstr} ({$errno})"];
    }

    stream_set_timeout($socket, 15);
    if (!smtpReadResponse($socket, 220)) {
        fclose($socket);
        return ['success' => false, 'error' => 'SMTP server did not send greeting'];
    }

    // EHLO
    $localHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    smtpSendCommand($socket, "EHLO {$localHost}");
    if (!smtpReadResponse($socket, 250)) {
        // Try HELO
        smtpSendCommand($socket, "HELO {$localHost}");
        if (!smtpReadResponse($socket, 250)) {
            fclose($socket);
            return ['success' => false, 'error' => 'EHLO/HELO failed'];
        }
    }

    // STARTTLS
    if ($encryption === 'tls') {
        smtpSendCommand($socket, 'STARTTLS');
        if (smtpReadResponse($socket, 220)) {
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return ['success' => false, 'error' => 'TLS negotiation failed'];
            }
            // Re-send EHLO after TLS
            smtpSendCommand($socket, "EHLO {$localHost}");
            if (!smtpReadResponse($socket, 250)) {
                fclose($socket);
                return ['success' => false, 'error' => 'EHLO after STARTTLS failed'];
            }
        }
    }

    // AUTH LOGIN
    if ($username !== '') {
        smtpSendCommand($socket, 'AUTH LOGIN');
        if (!smtpReadResponse($socket, 334)) {
            fclose($socket);
            return ['success' => false, 'error' => 'AUTH LOGIN not supported'];
        }

        smtpSendCommand($socket, base64_encode($username));
        if (!smtpReadResponse($socket, 334)) {
            fclose($socket);
            return ['success' => false, 'error' => 'AUTH LOGIN username rejected'];
        }

        smtpSendCommand($socket, base64_encode($password));
        if (!smtpReadResponse($socket, 235)) {
            fclose($socket);
            return ['success' => false, 'error' => 'AUTH LOGIN password rejected'];
        }
    }

    // MAIL FROM
    smtpSendCommand($socket, "MAIL FROM:<{$fromEmail}>");
    if (!smtpReadResponse($socket, 250)) {
        fclose($socket);
        return ['success' => false, 'error' => 'MAIL FROM rejected'];
    }

    // RCPT TO
    smtpSendCommand($socket, "RCPT TO:<{$to}>");
    if (!smtpReadResponse($socket, 250)) {
        fclose($socket);
        return ['success' => false, 'error' => 'RCPT TO rejected'];
    }

    // DATA
    smtpSendCommand($socket, 'DATA');
    if (!smtpReadResponse($socket, 354)) {
        fclose($socket);
        return ['success' => false, 'error' => 'DATA command rejected'];
    }

    smtpSendCommand($socket, $message . "\r\n.");
    if (!smtpReadResponse($socket, 250)) {
        fclose($socket);
        return ['success' => false, 'error' => 'Message data rejected'];
    }

    // QUIT
    smtpSendCommand($socket, 'QUIT');
    smtpReadResponse($socket, 221);
    fclose($socket);

    return ['success' => true, 'error' => null];
}

/**
 * Send a command to the SMTP server.
 */
function smtpSendCommand($socket, string $command): void
{
    fwrite($socket, $command . "\r\n");
}

/**
 * Read SMTP response codes from the server.
 * Reads lines until the response code pattern is found.
 *
 * @param resource $socket
 * @param int      $expectedCode  The expected 3-digit SMTP response code
 * @return bool  TRUE if the expected code was received
 */
function smtpReadResponse($socket, int $expectedCode): bool
{
    $code = '';
    while (($line = fgets($socket, 512)) !== false) {
        $code = substr($line, 0, 3);
        // If 4th char is space, it's the last line of the response
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $code !== '' && (int) $code === $expectedCode;
}

/**
 * Encode a header value that may contain non-ASCII characters
 * using UTF-8 base64 encoding (RFC 2047).
 */
function encodeHeader(string $value): string
{
    if (preg_match('/[^\x20-\x7E]/', $value)) {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
    return $value;
}

/**
 * Log an email to a file in storage/logs/mails/.
 *
 * Used as a development fallback when no SMTP or local MTA is available.
 * The logged file contains full headers + body so reset links etc. are
 * still accessible to the developer.
 */
function logMailToFile(string $to, string $subject, string $body): void
{
    $logDir = BASE_PATH . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'mails';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $filename = 'mail-' . date('Y-m-d_H-i-s') . '-' . bin2hex(random_bytes(4)) . '.html';
    $filepath = $logDir . DIRECTORY_SEPARATOR . $filename;

    $content = "<!--\n"
             . "To:      {$to}\n"
             . "Subject: {$subject}\n"
             . "Date:    " . date('Y-m-d H:i:s') . "\n"
             . "-->\n"
             . $body;

    @file_put_contents($filepath, $content);
}

/**
 * Send an order confirmation email to the customer.
 *
 * Builds a branded HTML email with order details summary table.
 *
 * @param array $order  Order data (order_number, grand_total, created_at, etc.)
 * @param array $user   User data (email, full_name)
 * @return bool
 */
function sendOrderConfirmation(array $order, array $user): bool
{
    $itemsHtml = '';
    if (!empty($order['items']) && is_array($order['items'])) {
        $itemsHtml .= '<table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;">';
        $itemsHtml .= '<thead><tr style="background:#001F5B;color:#fff;">';
        $itemsHtml .= '<th style="padding:10px 12px;text-align:left;">Product</th>';
        $itemsHtml .= '<th style="padding:10px 12px;text-align:center;">Qty</th>';
        $itemsHtml .= '<th style="padding:10px 12px;text-align:right;">Price</th>';
        $itemsHtml .= '<th style="padding:10px 12px;text-align:right;">Total</th>';
        $itemsHtml .= '</tr></thead><tbody>';
        foreach ($order['items'] as $item) {
            $name = htmlspecialchars($item['product_name'] ?? $item['name'] ?? 'Item');
            $qty  = (int) ($item['quantity'] ?? $item['qty'] ?? 0);
            $price = number_format((float) ($item['price'] ?? 0), 2);
            $total = number_format((float) ($item['total'] ?? $item['price'] * $qty), 2);
            $itemsHtml .= '<tr style="border-bottom:1px solid #E5E7EB;">';
            $itemsHtml .= '<td style="padding:8px 12px;">' . $name . '</td>';
            $itemsHtml .= '<td style="padding:8px 12px;text-align:center;">' . $qty . '</td>';
            $itemsHtml .= '<td style="padding:8px 12px;text-align:right;">RWF ' . $price . '</td>';
            $itemsHtml .= '<td style="padding:8px 12px;text-align:right;">RWF ' . $total . '</td>';
            $itemsHtml .= '</tr>';
        }
        $itemsHtml .= '</tbody></table>';
    }

    $orderNumber = htmlspecialchars($order['order_number'] ?? 'N/A');
    $orderDate   = htmlspecialchars(date('F j, Y', strtotime($order['created_at'] ?? 'now')));
    $total       = htmlspecialchars(number_format((float) ($order['grand_total'] ?? 0), 2));
    $shipping    = htmlspecialchars($order['shipping_address'] ?? $order['address'] ?? '');
    $payment     = htmlspecialchars($order['payment_method'] ?? $order['payment'] ?? 'N/A');
    $userName    = htmlspecialchars($user['full_name'] ?? $user['name'] ?? 'Valued Customer');
    $companyName = htmlspecialchars(setting('site_name', SITE_NAME));

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Order Confirmation</title></head>
<body style="margin:0;padding:0;background:#F5F6FA;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F5F6FA;padding:30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#FFFFFF;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,0.06);overflow:hidden;">
<tr><td style="background:#001F5B;padding:24px 30px;text-align:center;">
<h1 style="color:#C9A227;margin:0;font-size:22px;font-family:'Playfair Display',Georgia,serif;">{$companyName}</h1>
<p style="color:#FFFFFF;margin:6px 0 0;font-size:14px;">Order Confirmation</p>
</td></tr>
<tr><td style="padding:30px;">
<p style="font-size:16px;color:#333;margin:0 0 16px;">Dear <strong>{$userName}</strong>,</p>
<p style="font-size:14px;color:#555;margin:0 0 20px;line-height:1.6;">
Thank you for your order! Your order has been received and is being processed.
</p>
<table style="width:100%;border-collapse:collapse;margin:0 0 20px;font-size:14px;">
<tr><td style="padding:6px 0;color:#6B7280;width:140px;">Order Number</td><td style="padding:6px 0;font-weight:600;color:#001F5B;">{$orderNumber}</td></tr>
<tr><td style="padding:6px 0;color:#6B7280;">Order Date</td><td style="padding:6px 0;color:#333;">{$orderDate}</td></tr>
<tr><td style="padding:6px 0;color:#6B7280;">Payment Method</td><td style="padding:6px 0;color:#333;">{$payment}</td></tr>
</table>
HTML;

    if ($itemsHtml !== '') {
        $htmlBody .= $itemsHtml;
    }

    $htmlBody .= <<<HTML
<table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;">
<tr><td style="padding:8px 0;color:#6B7280;width:140px;">Shipping Address</td><td style="padding:8px 0;color:#333;">{$shipping}</td></tr>
<tr><td style="padding:8px 0;color:#6B7280;border-top:2px solid #C9A227;">Total Amount</td><td style="padding:8px 0;border-top:2px solid #C9A227;font-weight:700;color:#001F5B;font-size:18px;">RWF {$total}</td></tr>
</table>
<p style="font-size:14px;color:#555;margin:20px 0 0;line-height:1.6;">
We appreciate your business and will notify you when your order ships.
</p>
<p style="font-size:14px;color:#555;margin:8px 0 0;line-height:1.6;">
If you have any questions, please contact our support team.
</p>
</td></tr>
<tr><td style="background:#F5F6FA;padding:16px 30px;text-align:center;font-size:12px;color:#6B7280;">
&copy; 2026 {$companyName}. All rights reserved.
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;

    $result = sendMail($user['email'], 'Order Confirmation - ' . $orderNumber, $htmlBody);
    return $result['success'] ?? false;
}

/**
 * Send a shipping status update email to the customer.
 *
 * @param array $order  Order data (order_number, delivery_status, tracking_number, id)
 * @param array $user   User data (email, full_name)
 * @return bool
 */
function sendShippingUpdate(array $order, array $user): bool
{
    $orderNumber    = htmlspecialchars($order['order_number'] ?? 'N/A');
    $deliveryStatus = htmlspecialchars(ucfirst($order['delivery_status'] ?? $order['status'] ?? 'Processing'));
    $tracking       = htmlspecialchars($order['tracking_number'] ?? '');
    $orderId        = (int) ($order['id'] ?? 0);
    $userName       = htmlspecialchars($user['full_name'] ?? $user['name'] ?? 'Valued Customer');
    $companyName    = htmlspecialchars(setting('site_name', SITE_NAME));
    $trackUrl       = SITE_URL . 'pages/dashboard/order-view.php?id=' . $orderId;

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Shipping Update</title></head>
<body style="margin:0;padding:0;background:#F5F6FA;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F5F6FA;padding:30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#FFFFFF;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,0.06);overflow:hidden;">
<tr><td style="background:#001F5B;padding:24px 30px;text-align:center;">
<h1 style="color:#C9A227;margin:0;font-size:22px;font-family:'Playfair Display',Georgia,serif;">{$companyName}</h1>
<p style="color:#FFFFFF;margin:6px 0 0;font-size:14px;">Shipping Update</p>
</td></tr>
<tr><td style="padding:30px;">
<p style="font-size:16px;color:#333;margin:0 0 16px;">Dear <strong>{$userName}</strong>,</p>
<p style="font-size:14px;color:#555;margin:0 0 20px;line-height:1.6;">
Your order <strong>{$orderNumber}</strong> has a shipping update.
</p>
<table style="width:100%;border-collapse:collapse;margin:0 0 20px;font-size:14px;">
<tr><td style="padding:6px 0;color:#6B7280;width:140px;">Order Number</td><td style="padding:6px 0;font-weight:600;color:#001F5B;">{$orderNumber}</td></tr>
<tr><td style="padding:6px 0;color:#6B7280;">Delivery Status</td><td style="padding:6px 0;color:#0B6B2F;font-weight:600;">{$deliveryStatus}</td></tr>
HTML;

    if ($tracking !== '') {
        $htmlBody .= '<tr><td style="padding:6px 0;color:#6B7280;">Tracking Number</td><td style="padding:6px 0;color:#001F5B;font-weight:600;">' . $tracking . '</td></tr>';
    }

    $htmlBody .= <<<HTML
</table>
<p style="text-align:center;margin:24px 0;">
<a href="{$trackUrl}" style="display:inline-block;background:#C9A227;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px;">Track Your Order</a>
</p>
<p style="font-size:13px;color:#6B7280;margin:16px 0 0;line-height:1.5;">
If the button above does not work, copy and paste this link into your browser:<br>
<a href="{$trackUrl}" style="color:#001F5B;">{$trackUrl}</a>
</p>
</td></tr>
<tr><td style="background:#F5F6FA;padding:16px 30px;text-align:center;font-size:12px;color:#6B7280;">
&copy; 2026 {$companyName}. All rights reserved.
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;

    $result = sendMail($user['email'], 'Shipping Update - ' . $orderNumber, $htmlBody);
    return $result['success'] ?? false;
}

/**
 * Send a password reset email with a secure token link.
 *
 * @param string $email  Recipient email address
 * @param string $token  Password reset token
 * @return bool
 */
function sendPasswordResetEmail(string $email, string $token): bool
{
    $resetLink   = SITE_URL . 'pages/reset-password.php?token=' . urlencode($token);
    $companyName = htmlspecialchars(setting('site_name', SITE_NAME));

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Password Reset Request</title></head>
<body style="margin:0;padding:0;background:#F5F6FA;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F5F6FA;padding:30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#FFFFFF;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,0.06);overflow:hidden;">
<tr><td style="background:#001F5B;padding:24px 30px;text-align:center;">
<h1 style="color:#C9A227;margin:0;font-size:22px;font-family:'Playfair Display',Georgia,serif;">{$companyName}</h1>
<p style="color:#FFFFFF;margin:6px 0 0;font-size:14px;">Password Reset</p>
</td></tr>
<tr><td style="padding:30px;">
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hello,</p>
<p style="font-size:14px;color:#555;margin:0 0 20px;line-height:1.6;">
We received a request to reset the password for your account. Click the button below to set a new password.
</p>
<p style="text-align:center;margin:24px 0;">
<a href="{$resetLink}" style="display:inline-block;background:#C9A227;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px;">Reset Password</a>
</p>
<p style="font-size:13px;color:#6B7280;margin:16px 0 0;line-height:1.5;">
This link will expire in 1 hour for security reasons.<br>
If you did not request a password reset, please ignore this email. No changes have been made to your account.
</p>
<p style="font-size:13px;color:#6B7280;margin:12px 0 0;line-height:1.5;">
If the button above does not work, copy and paste this link into your browser:<br>
<a href="{$resetLink}" style="color:#001F5B;">{$resetLink}</a>
</p>
</td></tr>
<tr><td style="background:#F5F6FA;padding:16px 30px;text-align:center;font-size:12px;color:#6B7280;">
&copy; 2026 {$companyName}. All rights reserved.
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;

    $result = sendMail($email, 'Password Reset Request', $htmlBody);
    return $result['success'] ?? false;
}
