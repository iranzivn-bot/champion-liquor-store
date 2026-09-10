<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';
requireAdmin();
requirePermission('settings.manage');
$pageTitle = 'Security Settings';
$success = getFlashMessage('success');
$error   = getFlashMessage('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page.';
    } else {
        $fields = [
            'max_login_attempts'       => trim($_POST['max_login_attempts'] ?? '5'),
            'lockout_minutes'          => trim($_POST['lockout_minutes'] ?? '15'),
            'session_timeout'          => trim($_POST['session_timeout'] ?? '120'),
            'minimum_password_length'  => trim($_POST['minimum_password_length'] ?? '8'),
            'require_uppercase'        => $_POST['require_uppercase'] ?? 'no',
            'require_lowercase'        => $_POST['require_lowercase'] ?? 'no',
            'require_number'           => $_POST['require_number'] ?? 'no',
            'require_special_char'     => $_POST['require_special_char'] ?? 'no',
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

        setFlashMessage('success', "Security settings updated successfully ({$updated} change(s)).");
        redirect(SITE_URL . 'admin/settings/security.php');
    }
}

$max_login_attempts      = setting('max_login_attempts', '5');
$lockout_minutes         = setting('lockout_minutes', '15');
$session_timeout         = setting('session_timeout', '120');
$minimum_password_length = setting('minimum_password_length', '8');
$require_uppercase       = setting('require_uppercase', 'no');
$require_lowercase       = setting('require_lowercase', 'no');
$require_number          = setting('require_number', 'no');
$require_special_char    = setting('require_special_char', 'no');

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="container-fluid p-4">
            <h2 class="fw-bold mb-4" style="color:#001F5B;">
                <i class="bi bi-shield-lock"></i> Security Settings
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

            <form method="POST">
                <?= csrfField() ?>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header" style="background:#001F5B;color:#fff;">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-lock"></i> Login & Session</h5>
                    </div>
                    <div class="card-body p-4">

                        <div class="mb-3">
                            <label for="max_login_attempts" class="form-label">Maximum Login Attempts</label>
                            <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts"
                                   value="<?= htmlspecialchars($max_login_attempts) ?>" min="1" max="50">
                            <div class="form-text">Number of failed attempts before the account is locked.</div>
                        </div>

                        <div class="mb-3">
                            <label for="lockout_minutes" class="form-label">Lockout Duration (minutes)</label>
                            <input type="number" class="form-control" id="lockout_minutes" name="lockout_minutes"
                                   value="<?= htmlspecialchars($lockout_minutes) ?>" min="1" max="1440">
                            <div class="form-text">How long the account remains locked after max attempts.</div>
                        </div>

                        <div class="mb-3">
                            <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                            <input type="number" class="form-control" id="session_timeout" name="session_timeout"
                                   value="<?= htmlspecialchars($session_timeout) ?>" min="1" max="1440">
                            <div class="form-text">Admin session idle timeout in minutes.</div>
                        </div>

                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header" style="background:#001F5B;color:#fff;">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-key"></i> Password Policy</h5>
                    </div>
                    <div class="card-body p-4">

                        <div class="mb-3">
                            <label for="minimum_password_length" class="form-label">Minimum Password Length</label>
                            <input type="number" class="form-control" id="minimum_password_length" name="minimum_password_length"
                                   value="<?= htmlspecialchars($minimum_password_length) ?>" min="4" max="128">
                        </div>

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="require_uppercase" class="form-label">Require Uppercase</label>
                                <select class="form-select" id="require_uppercase" name="require_uppercase">
                                    <option value="yes" <?= $require_uppercase === 'yes' ? 'selected' : '' ?>>Yes</option>
                                    <option value="no" <?= $require_uppercase === 'no' ? 'selected' : '' ?>>No</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="require_lowercase" class="form-label">Require Lowercase</label>
                                <select class="form-select" id="require_lowercase" name="require_lowercase">
                                    <option value="yes" <?= $require_lowercase === 'yes' ? 'selected' : '' ?>>Yes</option>
                                    <option value="no" <?= $require_lowercase === 'no' ? 'selected' : '' ?>>No</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="require_number" class="form-label">Require Number</label>
                                <select class="form-select" id="require_number" name="require_number">
                                    <option value="yes" <?= $require_number === 'yes' ? 'selected' : '' ?>>Yes</option>
                                    <option value="no" <?= $require_number === 'no' ? 'selected' : '' ?>>No</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="require_special_char" class="form-label">Require Special Char</label>
                                <select class="form-select" id="require_special_char" name="require_special_char">
                                    <option value="yes" <?= $require_special_char === 'yes' ? 'selected' : '' ?>>Yes</option>
                                    <option value="no" <?= $require_special_char === 'no' ? 'selected' : '' ?>>No</option>
                                </select>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn px-4" style="background:#001F5B;color:#fff;">Save Security Settings</button>
                    <a href="index.php" class="btn btn-secondary ms-2">Cancel</a>
                </div>

            </form>
        </div>

    </main>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
