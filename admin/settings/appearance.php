<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';
requireAdmin();
requirePermission('settings.manage');
$pageTitle = 'Appearance Settings';
$success = getFlashMessage('success');
$error   = getFlashMessage('error');

$uploadDir = UPLOADS_PATH . 'settings' . DIRECTORY_SEPARATOR;
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
$maxFileSize  = 2 * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page.';
    } else {
        $logoUploaded = false;
        $faviconUploaded = false;
        $megaMenuUploaded = false;
        $logoIconUploaded = false;

        if (!empty($_FILES['site_logo']['name'])) {
            $file = $_FILES['site_logo'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Logo upload failed.';
            } elseif ($file['size'] > $maxFileSize) {
                $error = 'Logo must be smaller than 2 MB.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowedMimes, true)) {
                    $error = 'Logo must be JPG, PNG, or WebP.';
                } else {
                    $oldLogo = setting('site_logo');
                    $filename = generateUniqueFilename($file['name']);
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                        if (updateSetting('site_logo', $filename)) {
                            if ($oldLogo && $oldLogo !== $filename && file_exists($uploadDir . $oldLogo)) {
                                unlink($uploadDir . $oldLogo);
                            }
                            $logoUploaded = true;
                        }
                    } else {
                        $error = 'Failed to save logo file.';
                    }
                }
            }
        }

        if (empty($error) && !empty($_FILES['favicon']['name'])) {
            $file = $_FILES['favicon'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Favicon upload failed.';
            } elseif ($file['size'] > $maxFileSize) {
                $error = 'Favicon must be smaller than 2 MB.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowedMimes, true)) {
                    $error = 'Favicon must be JPG, PNG, or WebP.';
                } else {
                    $oldFavicon = setting('favicon');
                    $filename = generateUniqueFilename($file['name']);
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                        if (updateSetting('favicon', $filename)) {
                            if ($oldFavicon && $oldFavicon !== $filename && file_exists($uploadDir . $oldFavicon)) {
                                unlink($uploadDir . $oldFavicon);
                            }
                            $faviconUploaded = true;
                        }
                    } else {
                        $error = 'Failed to save favicon file.';
                    }
                }
            }
        }

        if (empty($error) && !empty($_FILES['mega_menu_promo_image']['name'])) {
            $file = $_FILES['mega_menu_promo_image'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Mega menu promo image upload failed.';
            } elseif ($file['size'] > $maxFileSize) {
                $error = 'Mega menu promo image must be smaller than 2 MB.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowedMimes, true)) {
                    $error = 'Mega menu promo image must be JPG, PNG, or WebP.';
                } else {
                    $oldImage = setting('mega_menu_promo_image');
                    $filename = generateUniqueFilename($file['name']);
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                        if (updateSetting('mega_menu_promo_image', $filename)) {
                            if ($oldImage && $oldImage !== $filename && file_exists($uploadDir . $oldImage)) {
                                unlink($uploadDir . $oldImage);
                            }
                            $megaMenuUploaded = true;
                        }
                    } else {
                        $error = 'Failed to save mega menu promo image file.';
                    }
                }
            }
        }

        if (empty($error) && !empty($_FILES['site_logo_icon']['name'])) {
            $file = $_FILES['site_logo_icon'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Logo icon upload failed.';
            } elseif ($file['size'] > $maxFileSize) {
                $error = 'Logo icon must be smaller than 2 MB.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowedMimes, true)) {
                    $error = 'Logo icon must be JPG, PNG, or WebP.';
                } else {
                    $oldImage = setting('site_logo_icon');
                    $filename = generateUniqueFilename($file['name']);
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                        if (updateSetting('site_logo_icon', $filename)) {
                            if ($oldImage && $oldImage !== $filename && file_exists($uploadDir . $oldImage)) {
                                unlink($uploadDir . $oldImage);
                            }
                            $logoIconUploaded = true;
                        }
                    } else {
                        $error = 'Failed to save logo icon file.';
                    }
                }
            }
        }

        if (empty($error) && ($logoUploaded || $faviconUploaded || $megaMenuUploaded || $logoIconUploaded)) {
            setFlashMessage('success', 'Appearance settings updated successfully.');
            redirect(SITE_URL . 'admin/settings/appearance.php');
        } elseif (empty($error) && !$logoUploaded && !$faviconUploaded && !$megaMenuUploaded && !$logoIconUploaded) {
            setFlashMessage('success', 'No changes were made.');
            redirect(SITE_URL . 'admin/settings/appearance.php');
        }
    }
}

$siteLogo     = setting('site_logo', '');
$favicon      = setting('favicon', '');
$megaMenuImg  = setting('mega_menu_promo_image', '');
$logoIcon     = setting('site_logo_icon', '');

$settingsUploadUrl = SITE_URL . 'uploads/settings/';

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>
<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="container-fluid p-4">
            <h2 class="fw-bold mb-4" style="color:#001F5B;">
                <i class="bi bi-palette"></i> Appearance Settings
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
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrfField() ?>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Site Logo</label>
                            <div class="form-text mb-2">
                                <strong>Purpose:</strong> Brand logo displayed in the header navigation and used as the primary site branding.
                                <br><strong>Appears:</strong> Desktop header (next to site name), mobile header, and browser tab via Open Graph.
                            </div>
                            <?php if ($siteLogo): ?>
                                <div class="mb-2">
                                    <img src="<?= htmlspecialchars($settingsUploadUrl . $siteLogo) ?>" alt="Current Logo"
                                         style="max-height:80px;border:1px solid #dee2e6;border-radius:4px;padding:4px;">
                                    <div class="small text-muted mt-1">Current: <?= htmlspecialchars($siteLogo) ?></div>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="site_logo" name="site_logo"
                                   accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">Accepted: JPG, PNG, WebP. Max size: 2 MB.</div>
                            <img id="logoPreview" src="#" alt="Preview"
                                 style="display:none;max-height:80px;margin-top:8px;border:1px solid #dee2e6;border-radius:4px;padding:4px;">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Favicon</label>
                            <div class="form-text mb-2">
                                <strong>Purpose:</strong> Small browser tab icon that represents the site in bookmarks, tabs, and history.
                                <br><strong>Appears:</strong> Browser tab (next to page title), bookmarks bar, address bar, and mobile home screen shortcuts.
                            </div>
                            <?php if ($favicon): ?>
                                <div class="mb-2">
                                    <img src="<?= htmlspecialchars($settingsUploadUrl . $favicon) ?>" alt="Current Favicon"
                                         style="max-height:32px;border:1px solid #dee2e6;border-radius:4px;padding:2px;">
                                    <div class="small text-muted mt-1">Current: <?= htmlspecialchars($favicon) ?></div>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="favicon" name="favicon"
                                   accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">Accepted: JPG, PNG, WebP. Max size: 2 MB. Recommended size: 32x32 or 64x64.</div>
                            <img id="faviconPreview" src="#" alt="Preview"
                                 style="display:none;max-height:32px;margin-top:8px;border:1px solid #dee2e6;border-radius:4px;padding:2px;">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Header Logo Icon</label>
                            <div class="form-text mb-2">Replaces the default shop icon in the header logo. Leave empty to use the default icon.</div>
                            <?php if ($logoIcon): ?>
                                <div class="mb-2">
                                    <img src="<?= htmlspecialchars($settingsUploadUrl . $logoIcon) ?>" alt="Logo Icon"
                                         style="max-height:44px;border:1px solid #dee2e6;border-radius:4px;padding:2px;">
                                    <div class="small text-muted mt-1">Current: <?= htmlspecialchars($logoIcon) ?></div>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="site_logo_icon" name="site_logo_icon"
                                   accept="image/jpeg,image/png,image/webp">
                            <img id="logoIconPreview" src="#" alt="Preview"
                                 style="display:none;max-height:44px;margin-top:8px;border:1px solid #dee2e6;border-radius:4px;padding:2px;">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Mega Menu Promo Image</label>
                            <?php if ($megaMenuImg): ?>
                                <div class="mb-2">
                                    <img src="<?= htmlspecialchars($settingsUploadUrl . $megaMenuImg) ?>" alt="Mega Menu Promo"
                                         style="max-height:120px;border:1px solid #dee2e6;border-radius:4px;padding:4px;">
                                    <div class="small text-muted mt-1">Current: <?= htmlspecialchars($megaMenuImg) ?></div>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="mega_menu_promo_image" name="mega_menu_promo_image"
                                   accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">Accepted: JPG, PNG, WebP. Max size: 2 MB. Displayed in the navigation mega menu featured card.</div>
                            <img id="megaMenuPreview" src="#" alt="Preview"
                                 style="display:none;max-height:120px;margin-top:8px;border:1px solid #dee2e6;border-radius:4px;padding:4px;">
                        </div>

                        <button type="submit" class="btn px-4" style="background:#001F5B;color:#fff;">Save Appearance</button>
                        <a href="index.php" class="btn btn-secondary ms-2">Cancel</a>
                    </form>
                </div>
            </div>
        </div>

    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function setupPreview(inputId, previewId) {
        var input = document.getElementById(inputId);
        var preview = document.getElementById(previewId);
        if (input && preview) {
            input.addEventListener('change', function (e) {
                var file = e.target.files[0];
                if (file) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    preview.src = '#';
                    preview.style.display = 'none';
                }
            });
        }
    }
    setupPreview('site_logo', 'logoPreview');
    setupPreview('favicon', 'faviconPreview');
    setupPreview('mega_menu_promo_image', 'megaMenuPreview');
    setupPreview('site_logo_icon', 'logoIconPreview');
});
</script>

<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
