<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';
requireAdmin();
requirePermission('settings.manage');
$pageTitle = 'Backup Database';
$success = getFlashMessage('success');
$error   = getFlashMessage('error');

$backupDir = BASE_PATH . 'backups' . DIRECTORY_SEPARATOR;
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

/**
 * Find the mysqldump executable path.
 */
function findMysqldump(): string
{
    $candidates = [
        'C:\xampp\mysql\bin\mysqldump.exe',
        'C:\wamp64\bin\mysql\mysql8.0.31\bin\mysqldump.exe',
        'C:\wamp64\bin\mysql\mysql5.7.36\bin\mysqldump.exe',
        getenv('MYSQL_HOME') . '\bin\mysqldump.exe',
        'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe',
        'C:\Program Files\MySQL\MySQL Server 5.7\bin\mysqldump.exe',
        'mysqldump',
    ];

    // Try shell command first
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        $which = trim(shell_exec('which mysqldump 2>/dev/null') ?? '');
        if ($which !== '') {
            return $which;
        }
    }

    foreach ($candidates as $path) {
        if (is_file($path) && is_executable($path)) {
            return $path;
        }
    }

    // Fallback: try to find via where / whereis
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $where = trim(shell_exec('where mysqldump 2>NUL') ?? '');
        if ($where !== '' && strpos($where, 'Could not find') === false) {
            return explode("\n", $where)[0];
        }
    }

    return 'mysqldump'; // last resort — let exec fail with a clear return code
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'backup') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page.';
    } else {
        $mysqldump = findMysqldump();

        // Check if the executable actually exists
        if ($mysqldump !== 'mysqldump' && !is_file($mysqldump)) {
            setFlashMessage('error', 'Backup failed: mysqldump executable not found. Please install MySQL client tools or configure MYSQL_HOME.');
            redirect(SITE_URL . 'admin/settings/backup.php');
        }

        $filename = 'db-' . date('Y-m-d-Hi') . '.sql';
        $filepath = $backupDir . $filename;

        // Use a temp file for stderr so we can capture errors separately
        $stderrFile = $backupDir . '_backup_error.tmp';
        $command = sprintf(
            '"%s" --host=%s --user=%s --password=%s %s > "%s" 2>"%s"',
            $mysqldump,
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASS),
            escapeshellarg(DB_NAME),
            $filepath,
            $stderrFile
        );
        $returnVar = null;
        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            @unlink($stderrFile);
            logActivity(
                (int) $_SESSION['user_id'],
                $_SESSION['user_name'] ?? '',
                $_SESSION['user_role'] ?? '',
                'settings',
                'backup',
                $filename,
                'Database backup created: ' . $filename
            );
            setFlashMessage('success', 'Database backup created successfully: ' . $filename);
        } else {
            $errorMsg = 'Unknown error.';
            if (file_exists($stderrFile)) {
                $stderr = trim(file_get_contents($stderrFile));
                if ($stderr !== '') {
                    $errorMsg = $stderr;
                }
                @unlink($stderrFile);
            }
            // Also try isp
            if ($errorMsg === 'Unknown error.' && !empty($output)) {
                $errorMsg = implode("\n", $output);
            }
            setFlashMessage('error', 'Backup failed: ' . $errorMsg);
        }
        redirect(SITE_URL . 'admin/settings/backup.php');
    }
}

$backupFiles = [];
$handle = opendir($backupDir);
if ($handle) {
    while (($entry = readdir($handle)) !== false) {
        if ($entry === '.' || $entry === '..' || $entry === '.gitkeep') {
            continue;
        }
        $path = $backupDir . $entry;
        if (is_file($path)) {
            $backupFiles[] = [
                'name' => $entry,
                'size' => filesize($path),
                'time' => filemtime($path),
            ];
        }
    }
    closedir($handle);
}
usort($backupFiles, function ($a, $b) {
    return $b['time'] - $a['time'];
});

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="container-fluid p-4">
            <h2 class="fw-bold mb-4" style="color:#001F5B;">
                <i class="bi bi-cloud-arrow-down"></i> Database Backup
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
                <div class="col-lg-4 mb-4">
                    <div class="card shadow-sm border-0 text-center">
                        <div class="card-body p-4">
                            <i class="bi bi-database" style="font-size:3rem;color:#001F5B;"></i>
                            <h5 class="fw-bold mt-3">Create a Backup</h5>
                            <p class="text-muted small">Dump the entire database to a .sql file in the backups folder.</p>
                            <form method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="backup">
                                <button type="submit" class="btn btn-lg px-5" style="background:#001F5B;color:#fff;"
                                        onclick="return confirm('Start a new database backup? This may take a moment.');">
                                    <i class="bi bi-database-down"></i> Backup Database
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header" style="background:#001F5B;color:#fff;">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-files"></i> Existing Backups</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($backupFiles)): ?>
                                <div class="p-4 text-muted text-center">No backup files found.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Filename</th>
                                                <th>Date</th>
                                                <th>Size</th>
                                                <th>Download</th>
                                                <th>Restore</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($backupFiles as $bf): ?>
                                                <tr>
                                                    <td><code><?= htmlspecialchars($bf['name']) ?></code></td>
                                                    <td><small><?= date('Y-m-d H:i:s', $bf['time']) ?></small></td>
                                                    <td><small><?= formatFileSize($bf['size']) ?></small></td>
                                                    <td>
                                                        <a href="<?= SITE_URL ?>backups/<?= rawurlencode($bf['name']) ?>"
                                                           class="btn btn-sm btn-outline-primary" download>
                                                            <i class="bi bi-download"></i>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-warning" disabled title="Restore not yet implemented">
                                                            <i class="bi bi-arrow-counterclockwise"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
