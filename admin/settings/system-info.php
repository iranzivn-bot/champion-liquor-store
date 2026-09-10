<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';
requireAdmin();
requirePermission('settings.manage');
$pageTitle = 'System Information';
$success = getFlashMessage('success');
$error   = getFlashMessage('error');

$phpVersion       = PHP_VERSION;
$mysqlVersion     = '';
$serverSoftware   = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$uploadMaxFilesize = ini_get('upload_max_filesize');
$timezoneSetting  = setting('timezone', 'UTC');
$appVersion       = '1.0.0';
$totalSettings    = 0;
$diskFreeSpace    = disk_free_space(BASE_PATH);

try {
    $pdo = getDbConnection();
    $stmt = $pdo->query('SELECT VERSION() AS ver');
    $row = $stmt->fetch();
    $mysqlVersion = $row['ver'] ?? 'Unknown';
} catch (Exception $e) {
    $mysqlVersion = 'Error: ' . $e->getMessage();
}

try {
    $stmt = $pdo->query('SELECT COUNT(*) FROM settings');
    $totalSettings = (int) $stmt->fetchColumn();
} catch (Exception $e) {
    $totalSettings = 0;
}

$diskFreeFormatted = $diskFreeSpace !== false ? formatFileSize((int) $diskFreeSpace) : 'Unknown';

$infoItems = [
    'PHP Version'       => $phpVersion,
    'MySQL Version'     => $mysqlVersion,
    'Server Software'   => $serverSoftware,
    'Upload Max Filesize' => $uploadMaxFilesize,
    'Timezone'          => $timezoneSetting,
    'Application Version' => $appVersion,
    'Total Settings'    => (string) $totalSettings,
    'Disk Free Space'   => $diskFreeFormatted,
];

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="container-fluid p-4">
            <h2 class="fw-bold mb-4" style="color:#001F5B;">
                <i class="bi bi-info-circle"></i> System Information
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

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <tbody>
                            <?php foreach ($infoItems as $label => $value): ?>
                                <tr>
                                    <th scope="row" class="ps-4" style="width:250px;color:#001F5B;"><?= htmlspecialchars($label) ?></th>
                                    <td class="pe-4"><code><?= htmlspecialchars((string) $value) ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-3">
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Settings</a>
            </div>
        </div>

    </main>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
