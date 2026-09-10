<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();

$pageTitle = 'Create Brand';

$uploadDir = BRAND_UPLOAD_PATH;

$name        = '';
$slug        = '';
$status      = ACTIVE_STATUS;
$errors      = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh the page.';
    }

    if (count($errors) === 0) {
    $name   = trim($_POST['name']   ?? '');
    $slug   = trim($_POST['slug']   ?? '');
    $status = $_POST['status'] ?? ACTIVE_STATUS;

    if ($name === '') {
        $errors[] = 'Brand name is required.';
    }
    if ($slug === '') {
        $errors[] = 'Slug is required.';
    }
    if (!in_array($status, [ACTIVE_STATUS, INACTIVE_STATUS], true)) {
        $status = ACTIVE_STATUS;
    }

    $pdo = getDbConnection();

    if ($name !== '') {
        $check = $pdo->prepare('SELECT COUNT(*) FROM brands WHERE name = :name');
        $check->execute([':name' => $name]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'A brand with this name already exists.';
        }
    }

    if ($slug !== '') {
        $check = $pdo->prepare('SELECT COUNT(*) FROM brands WHERE slug = :slug');
        $check->execute([':slug' => $slug]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'A brand with this slug already exists.';
        }
    }

    $logoName = null;
    if (!empty($_FILES['logo']['name'])) {
        $file = $_FILES['logo'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 2 * 1024 * 1024;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Logo upload failed with error code ' . $file['error'] . '.';
        } elseif (!in_array($file['type'], $allowedTypes, true)) {
            $errors[] = 'Logo must be JPEG, PNG, GIF, or WebP.';
        } elseif ($file['size'] > $maxFileSize) {
            $errors[] = 'Logo must be smaller than 2 MB.';
        } else {
            $logoName = generateUniqueFilename($file['name']);
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $logoName)) {
                $errors[] = 'Failed to move uploaded file.';
                $logoName = null;
            }
        }
    }

    if (count($errors) === 0) {
        $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(code, 5) AS UNSIGNED)) AS max_num FROM brands");
        $row = $stmt->fetch();
        $nextNum = ($row['max_num'] ?? 0) + 1;
        $code = 'BRD-' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare('
            INSERT INTO brands (code, name, slug, logo, status)
            VALUES (:code, :name, :slug, :logo, :status)
        ');
        $stmt->execute([
            ':code'   => $code,
            ':name'   => $name,
            ':slug'   => $slug,
            ':logo'   => $logoName,
            ':status' => $status,
        ]);

        logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'brands', 'created', $code, 'Created brand: ' . $name . ' (' . $code . ')');

        setFlashMessage('success', 'Brand "' . htmlspecialchars($name) . ' (' . $code . ')" created successfully.');
        redirect(SITE_URL . 'admin/brands/index.php');
    }
    }
}

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>

<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="admin-card">
            <h1>Create Brand</h1>
            <p class="text-muted">Fill in the fields below to add a new brand.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>

                <div class="mb-3">
                    <label for="name" class="form-label">Brand Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name"
                           value="<?= htmlspecialchars($name) ?>" required maxlength="100"
                           autocomplete="off">
                </div>

                <div class="mb-3">
                    <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="slug" name="slug"
                           value="<?= htmlspecialchars($slug) ?>" required maxlength="120"
                           placeholder="Auto-generated from name">
                    <div class="form-text">URL-friendly identifier. Auto-generated from the brand name.</div>
                </div>

                <div class="mb-3">
                    <label for="logo" class="form-label">Logo</label>
                    <input type="file" class="form-control" id="logo" name="logo"
                           accept="image/jpeg,image/png,image/gif,image/webp">
                    <div class="form-text">Accepted: JPEG, PNG, GIF, WebP. Max size: 2 MB.</div>
                    <img id="logoPreview" src="#" alt="Preview"
                         style="display: none; width: 120px; height: 120px; object-fit: cover; border-radius: 4px; border: 1px solid #dee2e6; margin-top: 8px;">
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" <?= $status === ACTIVE_STATUS ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status === INACTIVE_STATUS ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn" style="background: var(--admin-primary); color: #fff;">Save Brand</button>
                    <a href="<?= SITE_URL ?>admin/brands/index.php" class="btn btn-secondary">Cancel</a>
                </div>

            </form>
        </div>

    </main>
</div>

<script>
function slugify(text) {
    return text
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-+|-+$/g, '');
}

document.addEventListener('DOMContentLoaded', function () {
    var nameInput = document.getElementById('name');
    var slugInput = document.getElementById('slug');
    if (nameInput && slugInput) {
        nameInput.addEventListener('input', function () {
            slugInput.value = slugify(nameInput.value);
        });
    }

    var logoInput = document.getElementById('logo');
    var logoPreview = document.getElementById('logoPreview');
    if (logoInput && logoPreview) {
        logoInput.addEventListener('change', function (e) {
            var file = e.target.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    logoPreview.src = e.target.result;
                    logoPreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                logoPreview.src = '#';
                logoPreview.style.display = 'none';
            }
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/admin-footer.php';
