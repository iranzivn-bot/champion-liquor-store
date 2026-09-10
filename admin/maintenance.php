<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../helpers/format-helper.php';

requireAdmin();

$lockFile = BASE_PATH . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'maintenance.lock';
$action = $_GET['action'] ?? '';
$message = '';

if ($action === 'enable' && !file_exists($lockFile)) {
    file_put_contents($lockFile, date('Y-m-d H:i:s'));
    setFlashMessage('success', 'Maintenance mode enabled. Visitors will see the 503 page.');
    redirect(SITE_URL . 'admin/maintenance.php');
}

if ($action === 'disable' && file_exists($lockFile)) {
    unlink($lockFile);
    setFlashMessage('success', 'Maintenance mode disabled. Site is live.');
    redirect(SITE_URL . 'admin/maintenance.php');
}

$isActive = file_exists($lockFile);

$pageTitle = 'Maintenance Mode';
require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>
<div class="admin-content-wrapper">
    <div class="container-fluid p-4">
        <h2 class="fw-bold mb-4" style="color:#001F5B;">
            <i class="bi bi-tools"></i> Maintenance Mode
        </h2>

        <?php if ($flashMsg = getFlashMessage('success')): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= $flashMsg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4 text-center">
                <div class="mb-4">
                    <i class="bi bi-shield-check" style="font-size:4rem;color:<?= $isActive ? '#dc3545' : '#0B6B2F' ?>;"></i>
                </div>
                <h3 class="fw-bold mb-2">
                    Status: <?= $isActive ? '<span class="text-danger">Active</span>' : '<span class="text-success">Inactive</span>' ?>
                </h3>
                <p class="text-muted mb-4">
                    <?= $isActive
                        ? 'Your site is currently in maintenance mode. Only admins can access the frontend.'
                        : 'Maintenance mode is disabled. Enable it during updates or deployments.' ?>
                </p>

                <div class="alert alert-info d-inline-block text-start mb-4">
                    <strong>How it works:</strong><br>
                    When enabled, non-admin visitors see a "503 Service Unavailable" page.
                    The admin panel remains accessible. Toggle this before deploying updates.
                </div>

                <div>
                    <?php if ($isActive): ?>
                        <a href="?action=disable" class="btn btn-success btn-lg px-5">
                            <i class="bi bi-unlock"></i> Disable Maintenance Mode
                        </a>
                    <?php else: ?>
                        <a href="?action=enable" class="btn btn-danger btn-lg px-5"
                           onclick="return confirm('Enable maintenance mode? Visitors will see a 503 page.');">
                            <i class="bi bi-lock"></i> Enable Maintenance Mode
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
