<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();

$pageTitle = 'Edit Collection';

$uploadDir = COLLECTION_UPLOAD_PATH;

$slugParam = trim($_GET['slug'] ?? '');
if ($slugParam === '') {
    setFlashMessage('error', 'Invalid collection slug.');
    redirect(SITE_URL . 'admin/collections/index.php');
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('SELECT * FROM collections WHERE slug = :slug');
$stmt->execute([':slug' => $slugParam]);
$collection = $stmt->fetch();

if (!$collection) {
    setFlashMessage('error', 'Collection not found.');
    redirect(SITE_URL . 'admin/collections/index.php');
}

$id             = $collection['id'];
$name           = $collection['name'];
$slug           = $collection['slug'];
$description    = $collection['description'] ?? '';
$categoryId     = $collection['category_id'] ?? '';
$sortOrder      = $collection['sort_order'] ?? '0';
$status         = $collection['status'];
$existingImage  = $collection['image'];
$errors         = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh the page.';
    }

    if (count($errors) === 0) {
    $name        = trim($_POST['name']        ?? '');
    $slug        = trim($_POST['slug']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $catIdPost   = trim($_POST['category_id'] ?? '');
    $sortOrder   = trim($_POST['sort_order']  ?? '0');
    $status      = $_POST['status'] ?? ACTIVE_STATUS;

    if ($name === '') {
        $errors[] = 'Collection name is required.';
    }
    if ($slug === '') {
        $errors[] = 'Slug is required.';
    }
    if (!in_array($status, [ACTIVE_STATUS, INACTIVE_STATUS], true)) {
        $status = ACTIVE_STATUS;
    }

    if ($name !== '') {
        $check = $pdo->prepare('SELECT COUNT(*) FROM collections WHERE name = :name AND id != :id');
        $check->execute([':name' => $name, ':id' => $id]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'A collection with this name already exists.';
        }
    }

    if ($slug !== '') {
        $check = $pdo->prepare('SELECT COUNT(*) FROM collections WHERE slug = :slug AND id != :id');
        $check->execute([':slug' => $slug, ':id' => $id]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'A collection with this slug already exists.';
        }
    }

    $imageName = $existingImage;
    if (!empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 2 * 1024 * 1024;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload failed with error code ' . $file['error'] . '.';
        } elseif (!in_array($file['type'], $allowedTypes, true)) {
            $errors[] = 'Image must be JPEG, PNG, GIF, or WebP.';
        } elseif ($file['size'] > $maxFileSize) {
            $errors[] = 'Image must be smaller than 2 MB.';
        } else {
            $newName = generateUniqueFilename($file['name']);
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                if ($existingImage && file_exists($uploadDir . $existingImage)) {
                    unlink($uploadDir . $existingImage);
                }
                $imageName = $newName;
            } else {
                $errors[] = 'Failed to move uploaded file.';
            }
        }
    }

    if (count($errors) === 0) {
        $stmt = $pdo->prepare('
            UPDATE collections
            SET name = :name, slug = :slug, description = :description, image = :image,
                category_id = :category_id, sort_order = :sort_order, status = :status
            WHERE id = :id
        ');
        $stmt->execute([
            ':name'        => $name,
            ':slug'        => $slug,
            ':description' => $description ?: null,
            ':image'       => $imageName,
            ':category_id' => $catIdPost !== '' ? (int) $catIdPost : null,
            ':sort_order'  => (int) $sortOrder,
            ':status'      => $status,
            ':id'          => $id,
        ]);

        logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'collections', 'updated', $slug, 'Updated collection: ' . $name);

        setFlashMessage('success', 'Collection "' . htmlspecialchars($name) . '" updated successfully.');
        redirect(SITE_URL . 'admin/collections/index.php');
    }
    }
}

$allCategories = $pdo->query('SELECT id, name FROM categories WHERE status = \'active\' ORDER BY name ASC')->fetchAll();

require_once __DIR__ . '/../../includes/admin-header.php';
require_once __DIR__ . '/../../includes/admin-navbar.php';
require_once __DIR__ . '/../../includes/admin-sidebar.php';
?>

<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="admin-card">
            <h1>Edit Collection</h1>
            <p class="text-muted">Update the collection details.</p>

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
                    <label for="name" class="form-label">Collection Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name"
                           value="<?= htmlspecialchars($name) ?>" required maxlength="100"
                           autocomplete="off">
                </div>

                <div class="mb-3">
                    <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="slug" name="slug"
                           value="<?= htmlspecialchars($slug) ?>" required maxlength="120">
                    <div class="form-text">URL-friendly identifier.</div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4"
                              maxlength="1000"><?= htmlspecialchars($description) ?></textarea>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="category_id" class="form-label">Linked Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">— None —</option>
                            <?php foreach ($allCategories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Products from this category will appear in the collection.</div>
                    </div>
                    <div class="col-md-3">
                        <label for="sort_order" class="form-label">Sort Order</label>
                        <input type="number" class="form-control" id="sort_order" name="sort_order"
                               value="<?= (int) $sortOrder ?>" min="0">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?= $status === ACTIVE_STATUS ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status === INACTIVE_STATUS ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="image" class="form-label">Image</label>
                    <?php if ($existingImage): ?>
                        <div class="mb-2">
                            <img src="<?= SITE_URL ?>uploads/collections/<?= htmlspecialchars($existingImage) ?>"
                                 alt="<?= htmlspecialchars($name) ?>"
                                 style="width: 120px; height: 120px; object-fit: cover; border-radius: 4px; border: 1px solid #dee2e6;">
                        </div>
                    <?php endif; ?>
                    <input type="file" class="form-control" id="image" name="image"
                           accept="image/jpeg,image/png,image/gif,image/webp">
                    <div class="form-text">Accepted: JPEG, PNG, GIF, WebP. Max size: 2 MB. Leave empty to keep current image.</div>
                    <img id="imagePreview" src="#" alt="Preview"
                         style="display: none; width: 120px; height: 120px; object-fit: cover; border-radius: 4px; border: 1px solid #dee2e6; margin-top: 8px;">
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn" style="background: var(--admin-primary); color: #fff;">Update Collection</button>
                    <a href="<?= SITE_URL ?>admin/collections/index.php" class="btn btn-secondary">Cancel</a>
                </div>

            </form>
        </div>

    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
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
