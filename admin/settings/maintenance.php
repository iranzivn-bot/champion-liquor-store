<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';
requireAdmin();
requirePermission('settings.manage');
$pageTitle = 'Maintenance Settings';
$success = getFlashMessage('success');
$error   = getFlashMessage('error');

$lockFile = BASE_PATH . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'maintenance.lock';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page.';
    } elseif (isset($_POST['action'])) {
        if ($_POST['action'] === 'enable') {
            if (!file_exists($lockFile)) {
                file_put_contents($lockFile, date('Y-m-d H:i:s'));
            }
            updateSetting('maintenance_mode', '1');
            setFlashMessage('success', 'Maintenance mode has been enabled.');
            redirect(SITE_URL . 'admin/settings/maintenance.php');
        } elseif ($_POST['action'] === 'disable') {
            if (file_exists($lockFile)) {
                unlink($lockFile);
            }
            updateSetting('maintenance_mode', '0');
            setFlashMessage('success', 'Maintenance mode has been disabled.');
            redirect(SITE_URL . 'admin/settings/maintenance.php');
        } elseif ($_POST['action'] === 'update_message') {
            $message = trim($_POST['maintenance_message'] ?? '');
            updateSetting('maintenance_message', $message);
            setFlashMessage('success', 'Maintenance message updated.');
            redirect(SITE_URL . 'admin/settings/maintenance.php');
        }
    }
}

$isActive          = file_exists($lockFile);
$maintenanceMode   = setting('maintenance_mode', '0');
$maintenanceMessage = setting('maintenance_message', 'We are currently undergoing scheduled maintenance. Please check back shortly.');

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="container-fluid p-4">
            <h2 class="fw-bold mb-4" style="color:#001F5B;">
                <i class="bi bi-tools"></i> Maintenance Settings
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

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4 text-center">
                    <div class="mb-3">
                        <i class="bi bi-shield-check" style="font-size:4rem;color:<?= $isActive ? '#dc3545' : '#0B6B2F' ?>;"></i>
                    </div>
                    <h3 class="fw-bold mb-2">
                        Status: <?= $isActive ? '<span class="text-danger">Active</span>' : '<span class="text-success">Inactive</span>' ?>
                    </h3>
                    <p class="text-muted mb-3">
                        DB setting: <code>maintenance_mode = <?= htmlspecialchars($maintenanceMode) ?></code>
                        &middot; Lock file: <?= $isActive ? '<span class="text-success">exists</span>' : '<span class="text-muted">missing</span>' ?>
                    </p>

                    <form method="POST" class="d-inline">
                        <?= csrfField() ?>
                        <?php if ($isActive): ?>
                            <input type="hidden" name="action" value="disable">
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="bi bi-unlock"></i> Disable Maintenance Mode
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="enable">
                            <button type="submit" class="btn btn-danger btn-lg px-5"
                                    onclick="return confirm('Enable maintenance mode? Visitors will see the maintenance page.');">
                                <i class="bi bi-lock"></i> Enable Maintenance Mode
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3" style="color:#001F5B;"><i class="bi bi-chat-quote"></i> Maintenance Message</h5>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_message">
                        <div class="mb-3">
                            <textarea class="form-control" id="maintenance_message" name="maintenance_message"
                                      rows="3" maxlength="500"><?= htmlspecialchars($maintenanceMessage) ?></textarea>
                            <div class="form-text">Message shown to visitors during maintenance.</div>
                        </div>
                        <button type="submit" class="btn px-4" style="background:#001F5B;color:#fff;">Save Message</button>
                    </form>
                </div>
            </div>
        </div>

    </main>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
