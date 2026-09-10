<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';
require_once __DIR__ . '/../../helpers/mailer.php';
requireAdmin();
requirePermission('settings.manage');
$pageTitle = 'Email Settings';
$success = getFlashMessage('success');
$error   = getFlashMessage('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page.';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'test_email') {
        $to = trim($_POST['test_email_to'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address for the test.';
        } else {
            $subject = 'Test Email from ' . setting('site_name', SITE_NAME);
            $body = '<p>This is a test email from ' . SITE_NAME . '. If you received this, your SMTP settings are working correctly.</p>';
            $result = sendMail($to, $subject, $body);
            if ($result['success']) {
                setFlashMessage('success', 'Test email sent successfully to ' . htmlspecialchars($to) . '.');
            } else {
                setFlashMessage('error', 'Failed to send test email: ' . htmlspecialchars($result['error'] ?? 'Check your SMTP settings.'));
            }
            redirect(SITE_URL . 'admin/settings/email.php');
        }
    } else {
        $fields = [
            'smtp_host'       => trim($_POST['smtp_host'] ?? ''),
            'smtp_port'       => trim($_POST['smtp_port'] ?? ''),
            'smtp_username'   => trim($_POST['smtp_username'] ?? ''),
            'smtp_encryption' => in_array($_POST['smtp_encryption'] ?? '', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_encryption'] : 'none',
            'smtp_from_name'  => trim($_POST['smtp_from_name'] ?? ''),
            'smtp_from_email' => trim($_POST['smtp_from_email'] ?? ''),
        ];

        if (!empty($_POST['smtp_password'])) {
            $fields['smtp_password'] = $_POST['smtp_password'];
        }

        $updated = 0;
        foreach ($fields as $key => $value) {
            $old = setting($key);
            if ((string) $old !== $value) {
                if (updateSetting($key, $value)) {
                    $updated++;
                }
            }
        }

        setFlashMessage('success', "Email settings updated successfully ({$updated} change(s)).");
        redirect(SITE_URL . 'admin/settings/email.php');
    }
}

$smtp_host       = setting('smtp_host', '');
$smtp_port       = setting('smtp_port', '587');
$smtp_username   = setting('smtp_username', '');
$smtp_encryption = setting('smtp_encryption', 'tls');
$smtp_from_name  = setting('smtp_from_name', '');
$smtp_from_email = setting('smtp_from_email', '');

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="container-fluid p-4">
            <h2 class="fw-bold mb-4" style="color:#001F5B;">
                <i class="bi bi-envelope"></i> Email (SMTP) Settings
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

            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <form method="POST">
                                <?= csrfField() ?>

                                <div class="mb-3">
                                    <label for="smtp_host" class="form-label">SMTP Host</label>
                                    <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                                           value="<?= htmlspecialchars($smtp_host) ?>" maxlength="255" placeholder="smtp.example.com">
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="smtp_port" class="form-label">SMTP Port</label>
                                        <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                                               value="<?= htmlspecialchars($smtp_port) ?>" min="1" max="65535" placeholder="587">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="smtp_encryption" class="form-label">Encryption</label>
                                        <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                            <option value="tls" <?= $smtp_encryption === 'tls' ? 'selected' : '' ?>>TLS</option>
                                            <option value="ssl" <?= $smtp_encryption === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                            <option value="none" <?= $smtp_encryption === 'none' ? 'selected' : '' ?>>None</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="smtp_username" class="form-label">SMTP Username</label>
                                    <input type="text" class="form-control" id="smtp_username" name="smtp_username"
                                           value="<?= htmlspecialchars($smtp_username) ?>" maxlength="255" autocomplete="off">
                                </div>

                                <div class="mb-3">
                                    <label for="smtp_password" class="form-label">SMTP Password</label>
                                    <input type="password" class="form-control" id="smtp_password" name="smtp_password"
                                           maxlength="255" autocomplete="new-password" placeholder="Leave blank to keep current">
                                    <div class="form-text">Leave empty to keep the existing password.</div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="smtp_from_name" class="form-label">From Name</label>
                                        <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name"
                                               value="<?= htmlspecialchars($smtp_from_name) ?>" maxlength="255">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="smtp_from_email" class="form-label">From Email</label>
                                        <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email"
                                               value="<?= htmlspecialchars($smtp_from_email) ?>" maxlength="255">
                                    </div>
                                </div>

                                <button type="submit" class="btn px-4" style="background:#001F5B;color:#fff;">Save Email Settings</button>
                                <a href="index.php" class="btn btn-secondary ms-2">Cancel</a>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <h5 class="fw-bold" style="color:#001F5B;"><i class="bi bi-send"></i> Send Test Email</h5>
                            <p class="text-muted small">Send a test email to verify your SMTP configuration.</p>
                            <form method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="test_email">
                                <div class="mb-3">
                                    <label for="test_email_to" class="form-label">Send To</label>
                                    <input type="email" class="form-control" id="test_email_to" name="test_email_to"
                                           placeholder="you@example.com" required>
                                </div>
                                <button type="submit" class="btn px-3" style="background:#0B6B2F;color:#fff;">
                                    <i class="bi bi-send"></i> Send Test
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Settings</a>
            </div>
        </div>

    </main>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
