<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';
requireAdmin();
requirePermission('settings.manage');
$pageTitle = 'Company Settings';
$success = getFlashMessage('success');
$error   = getFlashMessage('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page.';
    } else {
        $fields = [
            'company_name'    => trim($_POST['company_name'] ?? ''),
            'company_email'   => trim($_POST['company_email'] ?? ''),
            'company_phone'   => trim($_POST['company_phone'] ?? ''),
            'company_address' => trim($_POST['company_address'] ?? ''),
            'company_website' => trim($_POST['company_website'] ?? ''),
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

        setFlashMessage('success', "Company settings updated successfully ({$updated} change(s)).");
        redirect(SITE_URL . 'admin/settings/company.php');
    }
}

$company_name    = setting('company_name', '');
$company_email   = setting('company_email', '');
$company_phone   = setting('company_phone', '');
$company_address = setting('company_address', '');
$company_website = setting('company_website', '');

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="container-fluid p-4">
            <h2 class="fw-bold mb-4" style="color:#001F5B;">
                <i class="bi bi-building"></i> Company Settings
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
                <div class="card-body p-4">
                    <form method="POST">
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label for="company_name" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="company_name" name="company_name"
                                   value="<?= htmlspecialchars($company_name) ?>" maxlength="255">
                        </div>

                        <div class="mb-3">
                            <label for="company_email" class="form-label">Company Email</label>
                            <input type="email" class="form-control" id="company_email" name="company_email"
                                   value="<?= htmlspecialchars($company_email) ?>" maxlength="255">
                        </div>

                        <div class="mb-3">
                            <label for="company_phone" class="form-label">Company Phone</label>
                            <input type="text" class="form-control" id="company_phone" name="company_phone"
                                   value="<?= htmlspecialchars($company_phone) ?>" maxlength="50">
                        </div>

                        <div class="mb-3">
                            <label for="company_address" class="form-label">Company Address</label>
                            <textarea class="form-control" id="company_address" name="company_address"
                                      rows="4" maxlength="1000"><?= htmlspecialchars($company_address) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="company_website" class="form-label">Company Website</label>
                            <input type="url" class="form-control" id="company_website" name="company_website"
                                   value="<?= htmlspecialchars($company_website) ?>" maxlength="255" placeholder="https://example.com">
                        </div>

                        <button type="submit" class="btn px-4" style="background:#001F5B;color:#fff;">Save Company Settings</button>
                        <a href="index.php" class="btn btn-secondary ms-2">Cancel</a>
                    </form>
                </div>
            </div>
        </div>

    </main>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
