<?php
declare(strict_types=1);

require_once __DIR__ . '/../middlewares/guest.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../helpers/mailer.php';

$message   = '';
$submitted = false;
$pdo       = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $message = lang('invalid_csrf');
    } else {
        $email = trim($_POST['email'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = lang('forgot_invalid_email');
        } else {
            // Always show the same success message regardless of whether
            // the email exists (prevents email enumeration).
            $submitted = true;
            $message   = lang('forgot_sent');

            // Check if the email exists
            $stmt = $pdo->prepare('SELECT id, full_name FROM users WHERE email = :email AND status = :active');
            $stmt->execute([':email' => $email, ':active' => ACTIVE_STATUS]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate a secure random token
                $token    = bin2hex(random_bytes(32));
                $hash     = hash('sha256', $token);
                $expires  = date('Y-m-d H:i:s', time() + 3600); // 1 hour

                // Store the hash in the database
                $insert = $pdo->prepare(
                    'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:uid, :hash, :expires)'
                );
                $insert->execute([
                    ':uid'     => (int) $user['id'],
                    ':hash'    => $hash,
                    ':expires' => $expires,
                ]);

                // Build the reset link
                $resetUrl = SITE_URL . 'pages/reset-password.php?token=' . $token;

                // Build email body
                $siteName = setting('site_name', SITE_NAME);
                $subject = lang('forgot_mail_subject', ['site' => $siteName]);

                $body = '<!DOCTYPE html><html><body style="font-family: Arial, sans-serif; padding: 24px; background: #f5f5f5;">';
                $body .= '<div style="max-width: 560px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">';
                $body .= '<div style="background: #001F5B; padding: 24px; text-align: center;">';
                $body .= '<h1 style="color: #C9A227; margin: 0; font-size: 1.5rem;">' . htmlspecialchars($siteName) . '</h1>';
                $body .= '</div>';
                $body .= '<div style="padding: 32px;">';
                $body .= '<p>' . lang('forgot_mail_intro', ['site' => htmlspecialchars($siteName)]) . '</p>';
                $body .= '<p>' . lang('forgot_mail_click') . '</p>';
                $body .= '<div style="text-align: center; margin: 28px 0;">';
                $body .= '<a href="' . htmlspecialchars($resetUrl) . '" style="display: inline-block; background: #001F5B; color: #fff; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600;">' . lang('forgot_mail_button') . '</a>';
                $body .= '</div>';
                $body .= '<p style="color: #999; font-size: 0.85rem;">' . lang('forgot_mail_ignore') . '</p>';
                $body .= '<p style="color: #999; font-size: 0.85rem;">' . lang('forgot_mail_footer') . '</p>';
                $body .= '<p style="color: #999; font-size: 0.85rem; word-break: break-all;">' . htmlspecialchars($resetUrl) . '</p>';
                $body .= '</div>';
                $body .= '<div style="background: #f5f5f5; padding: 16px; text-align: center; font-size: 0.75rem; color: #999;">';
                $body .= '&copy; ' . date('Y') . ' ' . htmlspecialchars($siteName) . '</div>';
                $body .= '</div></body></html>';

                $result = sendMail($email, $subject, $body);
                if (!$result['success']) {
                    error_log('Password reset email failed: ' . ($result['error'] ?? 'unknown error'));
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="auth-card">

                <div class="text-center mb-4">
                    <h2 class="fw-bold" style="color: var(--primary);"><?= lang('forgot_title') ?></h2>
                    <p class="text-muted small"><?= lang('forgot_subtitle') ?></p>
                </div>

                <?php if ($submitted): ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="bi bi-envelope-check-fill me-2 fs-5"></i>
                        <div><?= htmlspecialchars($message) ?></div>
                    </div>
                    <div class="text-center mt-3">
                        <a href="<?= SITE_URL ?>pages/login.php" class="btn btn-primary px-4 py-2 fw-semibold"
                           style="background: #001F5B; border-color: #001F5B;">
                            <i class="bi bi-arrow-left me-1"></i><?= lang('forgot_back') ?>
                        </a>
                    </div>

                <?php else: ?>
                    <?php if ($message): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <form method="POST" novalidate>
                        <?= csrfField() ?>
                        <div class="mb-4">
                            <label for="email" class="form-label"><?= lang('forgot_email') ?> <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email"
                                   required placeholder="john@example.com">
                        </div>

                        <button type="submit" class="btn w-100 py-2 fw-semibold" style="background: #C9A227; color: #fff;">
                            <i class="bi bi-send me-1"></i><?= lang('forgot_send') ?>
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <p class="small mb-1">
                            <a href="<?= SITE_URL ?>pages/login.php" class="fw-semibold" style="color: var(--primary);">
                                <i class="bi bi-arrow-left"></i> <?= lang('forgot_back') ?>
                            </a>
                        </p>
                        <p class="small mb-0"><?= lang('no_account') ?>
                            <a href="<?= SITE_URL ?>pages/register.php" class="fw-semibold" style="color: var(--primary);"><?= lang('register') ?></a>
                        </p>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
