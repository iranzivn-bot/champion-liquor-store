<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();

$pageTitle = 'Edit Category';

$uploadDir = CATEGORY_UPLOAD_PATH;

$code = trim($_GET['code'] ?? '');
if ($code === '') {
    setFlashMessage('error', 'Invalid category code.');
    redirect(SITE_URL . 'admin/categories/index.php');
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('SELECT * FROM categories WHERE code = :code');
$stmt->execute([':code' => $code]);
$category = $stmt->fetch();

if (!$category) {
    setFlashMessage('error', 'Category not found.');
    redirect(SITE_URL . 'admin/categories/index.php');
}

$id            = $category['id'];
$categoryCode  = $category['code'];
$name          = $category['name'];
$slug          = $category['slug'];
$status        = $category['status'];
$group         = $category['group'] ?? 'liquor';
$existingImage = $category['image'];
$errors        = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh the page.';
    }

    if (count($errors) === 0) {
    $name        = trim($_POST['name']        ?? '');
    $slug        = trim($_POST['slug']        ?? '');
    $status      = $_POST['status'] ?? ACTIVE_STATUS;
    $group       = $_POST['group'] ?? 'liquor';

    if ($name === '') {
        $errors[] = 'Category name is required.';
    }
    if ($slug === '') {
        $errors[] = 'Slug is required.';
    }
    if (!in_array($status, [ACTIVE_STATUS, INACTIVE_STATUS], true)) {
        $status = ACTIVE_STATUS;
    }
    if (!in_array($group, ['liquor', 'mini_market'], true)) {
        $group = 'liquor';
    }

    if ($name !== '') {
        $check = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE name = :name AND id != :id');
        $check->execute([':name' => $name, ':id' => $id]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'Another category with this name already exists.';
        }
    }

    if ($slug !== '') {
        $check = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE slug = :slug AND id != :id');
        $check->execute([':slug' => $slug, ':id' => $id]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'Another category with this slug already exists.';
        }
    }

    $imageName = $existingImage;
    if (!empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 2 * 1024 * 1024;

        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            $errors[] = 'Image is too large. Maximum size is 2 MB.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload failed (error code ' . $file['error'] . '). Please try again.';
        } elseif (!in_array($file['type'], $allowedTypes, true)) {
            $errors[] = 'Image must be JPEG, PNG, GIF, or WebP.';
        } elseif ($file['size'] > $maxFileSize) {
            $errors[] = 'Image must be smaller than 2 MB.';
        } else {
            $newImage = generateUniqueFilename($file['name']);
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $newImage)) {
                if ($existingImage && file_exists($uploadDir . $existingImage)) {
                    unlink($uploadDir . $existingImage);
                }
                $imageName = $newImage;
            } else {
                $errors[] = 'Failed to move uploaded file.';
            }
        }
    }

    if (count($errors) === 0) {
        $stmt = $pdo->prepare('
            UPDATE categories
               SET name = :name, slug = :slug,
                   image = :image, status = :status, `group` = :grp
             WHERE id = :id
        ');
        $stmt->execute([
            ':name'        => $name,
            ':slug'        => $slug,
            ':image'       => $imageName,
            ':status'      => $status,
            ':grp'         => $group,
            ':id'          => $id,
        ]);

        logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'categories', 'updated', $categoryCode, 'Updated category: ' . $name . ' (' . $categoryCode . ')');

        setFlashMessage('success', 'Category "' . htmlspecialchars($name) . ' (' . $categoryCode . ')" updated successfully.');
        redirect(SITE_URL . 'admin/categories/index.php');
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
            <h1>Edit Category</h1>
            <p class="text-muted">Update the fields below.</p>

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
                    <label class="form-label">Category Code</label>
                    <input type="text" class="form-control bg-light"
                           value="<?= htmlspecialchars($categoryCode) ?>" readonly>
                </div>

                <div class="mb-3">
                    <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name"
                           value="<?= htmlspecialchars($name) ?>" required maxlength="100"
                           autocomplete="off">
                </div>

                <div class="mb-3">
                    <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="slug" name="slug"
                           value="<?= htmlspecialchars($slug) ?>" required maxlength="120"
                           placeholder="Auto-generated from name">
                    <div class="form-text">URL-friendly identifier. Auto-generated from the category name.</div>
                </div>

                <?php if ($existingImage): ?>
                    <div class="mb-3">
                        <label class="form-label">Current Image</label>
                        <div>
                            <img src="<?= SITE_URL ?>uploads/categories/<?= htmlspecialchars($existingImage) ?>"
                                 alt="<?= htmlspecialchars($name) ?>"
                                 style="width: 120px; height: 120px; object-fit: cover; border-radius: 4px; border: 1px solid #dee2e6;">
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="image" class="form-label">
                        <?= $existingImage ? 'Replace Image' : 'Image' ?>
                    </label>
                    <input type="file" class="form-control" id="image" name="image"
                           accept="image/jpeg,image/png,image/gif,image/webp">
                    <div class="form-text">
                        <?= $existingImage ? 'Leave empty to keep the current image.' : 'Accepted: JPEG, PNG, GIF, WebP. Max size: 2 MB.' ?>
                    </div>
                    <img id="imagePreview" src="#" alt="Preview"
                         style="display: none; width: 120px; height: 120px; object-fit: cover; border-radius: 4px; border: 1px solid #dee2e6; margin-top: 8px;">
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" <?= $status === ACTIVE_STATUS ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status === INACTIVE_STATUS ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="group" class="form-label">Group</label>
                    <select class="form-select" id="group" name="group">
                        <option value="liquor" <?= $group === 'liquor' ? 'selected' : '' ?>>Liquor</option>
                        <option value="mini_market" <?= $group === 'mini_market' ? 'selected' : '' ?>>Mini Market</option>
                    </select>
                    <div class="form-text">Choose which section this category appears under on the homepage.</div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn" style="background: var(--admin-primary); color: #fff;">Update Category</button>
                    <a href="<?= SITE_URL ?>admin/categories/index.php" class="btn btn-secondary">Cancel</a>
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

    var imageInput = document.getElementById('image');
    var imagePreview = document.getElementById('imagePreview');
    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function (e) {
            var file = e.target.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                imagePreview.src = '#';
                imagePreview.style.display = 'none';
            }
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/admin-footer.php';
