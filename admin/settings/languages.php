<?php
/**
 * Admin Language Settings
 *
 * Manage system languages: enable/disable, set default.
 * Super Admin only — requires permissions.manage permission.
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';
requireAdmin();
requirePermission('permissions.manage'); // Only super_admin by default

$pageTitle = lang('language_settings');
$success = getFlashMessage('success');
$error   = getFlashMessage('error');

$pdo = getDbConnection();

// ─── Handle Actions ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $error = lang('invalid_csrf');
    } else {
        $action = $_POST['action'] ?? '';
        $code   = $_POST['code'] ?? '';

        if (!preg_match('/^[a-z]{2,5}$/', $code)) {
            $error = lang('field_required', ['field' => lang('language')]);
        } elseif ($action === 'toggle_status') {
            // Toggle active/inactive
            $stmt = $pdo->prepare('SELECT status, is_default FROM languages WHERE code = :code');
            $stmt->execute([':code' => $code]);
            $lang = $stmt->fetch();

            if (!$lang) {
                $error = lang('no_results');
            } elseif ((int) $lang['is_default'] === 1) {
                $error = 'Cannot disable the default language. Set another language as default first.';
            } else {
                $newStatus = $lang['status'] === 'active' ? 'inactive' : 'active';
                $stmt = $pdo->prepare('UPDATE languages SET status = :status WHERE code = :code');
                $stmt->execute([':status' => $newStatus, ':code' => $code]);

                logActivity(
                    (int) $_SESSION['user_id'],
                    $_SESSION['user_name'] ?? '',
                    $_SESSION['user_role'] ?? '',
                    'settings',
                    'language_toggle',
                    $code,
                    "Language '{$code}' set to {$newStatus}"
                );
                setFlashMessage('success', "Language '{$code}' is now {$newStatus}.");
                redirect(SITE_URL . 'admin/settings/languages.php');
            }
        } elseif ($action === 'set_default') {
            // Unset current default
            $pdo->exec('UPDATE languages SET is_default = 0 WHERE is_default = 1');
            // Set new default
            $stmt = $pdo->prepare('UPDATE languages SET is_default = 1, status = "active" WHERE code = :code');
            $stmt->execute([':code' => $code]);
            // Update settings table
            updateSetting('default_language', $code);

            logActivity(
                (int) $_SESSION['user_id'],
                $_SESSION['user_name'] ?? '',
                $_SESSION['user_role'] ?? '',
                'settings',
                'language_default',
                $code,
                "Default language set to '{$code}'"
            );
            setFlashMessage('success', "Default language set to '{$code}'.");
            redirect(SITE_URL . 'admin/settings/languages.php');
        }
    }
}

// ─── Fetch Languages ────────────────────────────────────────────────
$stmt = $pdo->query('SELECT * FROM languages ORDER BY is_default DESC, name ASC');
$languages = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="container-fluid p-4">
            <h2 class="fw-bold mb-4" style="color:#001F5B;">
                <i class="bi bi-globe2"></i> <?= lang('language_settings') ?>
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
                <?php foreach ($languages as $lang): ?>
                    <?php
                    $isDefault = (int) $lang['is_default'] === 1;
                    $isActive  = $lang['status'] === 'active';
                    ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card shadow-sm border-0 h-100" style="<?= $isDefault ? 'border-left: 4px solid #C9A227 !important;' : '' ?>">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <span style="font-size:2.5rem;"><?= htmlspecialchars($lang['flag']) ?></span>
                                    <div class="ms-3">
                                        <h5 class="fw-bold mb-0"><?= htmlspecialchars($lang['name']) ?></h5>
                                        <code class="text-muted"><?= htmlspecialchars($lang['code']) ?></code>
                                    </div>
                                    <?php if ($isDefault): ?>
                                        <span class="badge ms-auto" style="background:#C9A227;color:#001F5B;">Default</span>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <?php if ($isActive): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex gap-2">
                                    <!-- Toggle Status -->
                                    <form method="POST" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="code" value="<?= htmlspecialchars($lang['code']) ?>">
                                        <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                                                <?= $isDefault ? 'disabled' : '' ?>>
                                            <?= $isActive ? lang('disable_maintenance') : lang('enable_maintenance') ?>
                                        </button>
                                    </form>

                                    <!-- Set as Default -->
                                    <?php if (!$isDefault): ?>
                                    <form method="POST" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="set_default">
                                        <input type="hidden" name="code" value="<?= htmlspecialchars($lang['code']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning">
                                            Set as Default
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header" style="background:#001F5B;color:#fff;">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-info-circle"></i> About Language System</h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>Language files are stored in <code>languages/</code> directory (en.php, fr.php, rw.php).</li>
                        <li>To add a new language, create a new file in <code>languages/</code> and add a row in the <code>languages</code> table.</li>
                        <li>Users can switch languages via the navbar dropdown.</li>
                        <li>Logged-in users have their preference saved to their profile.</li>
                        <li>Guest preferences are stored in the session.</li>
                        <li>Missing translations fall back to English, then to the key name.</li>
                    </ul>
                </div>
            </div>
        </div>

    </main>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
