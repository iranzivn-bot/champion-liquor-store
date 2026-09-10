<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';
requireAdmin();
requirePermission('settings.manage');
$pageTitle = 'General Settings';
$success = getFlashMessage('success');
$error   = getFlashMessage('error');

$timezones = [
    'UTC' => 'UTC',
    'Africa/Kigali' => 'Africa/Kigali (CAT)',
    'Africa/Nairobi' => 'Africa/Nairobi (EAT)',
    'Africa/Johannesburg' => 'Africa/Johannesburg (SAST)',
    'Africa/Lagos' => 'Africa/Lagos (WAT)',
    'Europe/London' => 'Europe/London (GMT/BST)',
    'Europe/Paris' => 'Europe/Paris (CET/CEST)',
    'America/New_York' => 'America/New_York (EST/EDT)',
    'America/Chicago' => 'America/Chicago (CST/CDT)',
    'America/Denver' => 'America/Denver (MST/MDT)',
    'America/Los_Angeles' => 'America/Los_Angeles (PST/PDT)',
    'Asia/Dubai' => 'Asia/Dubai (GST)',
    'Asia/Singapore' => 'Asia/Singapore (SGT)',
    'Asia/Tokyo' => 'Asia/Tokyo (JST)',
    'Australia/Sydney' => 'Australia/Sydney (AEST/AEDT)',
    'Pacific/Auckland' => 'Pacific/Auckland (NZST/NZDT)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page.';
    } else {
        $fields = [
            'site_name'      => trim($_POST['site_name'] ?? ''),
            'site_tagline'   => trim($_POST['site_tagline'] ?? ''),
            'timezone'       => in_array(trim($_POST['timezone'] ?? ''), array_keys($timezones), true) ? trim($_POST['timezone']) : 'UTC',
            'language'       => $_POST['language'] ?? 'en',
            'currency'       => in_array($_POST['currency'] ?? '', ['RWF', 'USD', 'EUR'], true) ? $_POST['currency'] : 'RWF',
            'currency_symbol'=> trim($_POST['currency_symbol'] ?? ''),
            'flash_sale_end'  => trim($_POST['flash_sale_end'] ?? ''),
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

        setFlashMessage('success', "General settings updated successfully ({$updated} change(s)).");
        redirect(SITE_URL . 'admin/settings/general.php');
    }
}

$site_name       = setting('site_name', SITE_NAME);
$site_tagline    = setting('site_tagline', '');
$timezone        = setting('timezone', 'UTC');
$language        = setting('language', 'en');
$currency        = setting('currency', 'RWF');
$currency_symbol = setting('currency_symbol', 'RWF');
$flash_sale_end  = setting('flash_sale_end', '');

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="container-fluid p-4">
            <h2 class="fw-bold mb-4" style="color:#001F5B;">
                <i class="bi bi-sliders2"></i> General Settings
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
                            <label for="site_name" class="form-label">Site Name</label>
                            <input type="text" class="form-control" id="site_name" name="site_name"
                                   value="<?= htmlspecialchars($site_name) ?>" maxlength="255" required>
                        </div>

                        <div class="mb-3">
                            <label for="site_tagline" class="form-label">Site Tagline</label>
                            <input type="text" class="form-control" id="site_tagline" name="site_tagline"
                                   value="<?= htmlspecialchars($site_tagline) ?>" maxlength="500">
                            <div class="form-text">A short description or motto for the site.</div>
                        </div>

                        <div class="mb-3">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select class="form-select" id="timezone" name="timezone">
                                <?php foreach ($timezones as $tz => $label): ?>
                                    <option value="<?= htmlspecialchars($tz) ?>" <?= $timezone === $tz ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="language" class="form-label">Language</label>
                            <select class="form-select" id="language" name="language">
                                <option value="en" <?= $language === 'en' ? 'selected' : '' ?>>English</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="currency" class="form-label">Currency</label>
                            <select class="form-select" id="currency" name="currency">
                                <option value="RWF" <?= $currency === 'RWF' ? 'selected' : '' ?>>RWF (Rwandan Franc)</option>
                                <option value="USD" <?= $currency === 'USD' ? 'selected' : '' ?>>USD (US Dollar)</option>
                                <option value="EUR" <?= $currency === 'EUR' ? 'selected' : '' ?>>EUR (Euro)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="currency_symbol" class="form-label">Currency Symbol</label>
                            <input type="text" class="form-control" id="currency_symbol" name="currency_symbol"
                                   value="<?= htmlspecialchars($currency_symbol) ?>" maxlength="10" required>
                            <div class="form-text">E.g., RWF, $, €, Frw</div>
                        </div>

                        <hr>
                        <h5 class="fw-bold mb-3"><i class="bi bi-lightning-fill me-1" style="color: #C9A227;"></i> Flash Sale</h5>

                        <div class="mb-3">
                            <label for="flash_sale_end" class="form-label">Flash Sale End Date/Time</label>
                            <input type="text" class="form-control" id="flash_sale_end" name="flash_sale_end"
                                   value="<?= htmlspecialchars($flash_sale_end) ?>" maxlength="19"
                                   placeholder="Y-m-d H:i:s (e.g. 2026-07-10 23:59:59)">
                            <div class="form-text">Set the end date/time for the flash sale countdown timer. Leave empty to disable the flash sale.</div>
                        </div>

                        <button type="submit" class="btn px-4" style="background:#001F5B;color:#fff;">Save General Settings</button>
                        <a href="index.php" class="btn btn-secondary ms-2">Cancel</a>
                    </form>
                </div>
            </div>
        </div>

    </main>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
