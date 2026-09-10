<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/audit-helper.php';

requireAdmin();

$pageTitle = 'Edit Blog Post';

$uploadDir = BLOG_UPLOAD_PATH;

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlashMessage('error', 'Invalid post ID.');
    redirect(SITE_URL . 'admin/blog/index.php');
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE id = :id');
$stmt->execute([':id' => $id]);
$post = $stmt->fetch();

if (!$post) {
    setFlashMessage('error', 'Blog post not found.');
    redirect(SITE_URL . 'admin/blog/index.php');
}

$postId       = (int) $post['id'];
$title        = $post['title'];
$slug         = $post['slug'];
$content      = $post['content'];
$excerpt      = $post['excerpt'] ?? '';
$status       = $post['status'];
$existingImage = $post['image'];
$errors       = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh the page.';
    }

    if (count($errors) === 0) {
    $title      = trim($_POST['title']   ?? '');
    $slug       = trim($_POST['slug']    ?? '');
    $content    = trim($_POST['content'] ?? '');
    $excerpt    = trim($_POST['excerpt'] ?? '');
    $status     = $_POST['status'] ?? 'draft';

    if ($title === '') {
        $errors[] = 'Post title is required.';
    }
    if ($slug === '') {
        $errors[] = 'Slug is required.';
    }
    if ($content === '') {
        $errors[] = 'Post content is required.';
    }
    if (!in_array($status, ['draft', 'published'], true)) {
        $status = 'draft';
    }

    if ($slug !== '') {
        $check = $pdo->prepare('SELECT COUNT(*) FROM blog_posts WHERE slug = :slug AND id != :id');
        $check->execute([':slug' => $slug, ':id' => $postId]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'Another post with this slug already exists.';
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
        $publishedAt = $post['published_at'];
        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        $stmt = $pdo->prepare('
            UPDATE blog_posts
               SET title = :title, slug = :slug, content = :content, excerpt = :excerpt,
                   image = :image, status = :status, published_at = :published_at
             WHERE id = :id
        ');
        $stmt->execute([
            ':title'        => $title,
            ':slug'         => $slug,
            ':content'      => $content,
            ':excerpt'      => $excerpt ?: null,
            ':image'        => $imageName,
            ':status'       => $status,
            ':published_at' => $publishedAt,
            ':id'           => $postId,
        ]);

        logActivity((int) $_SESSION['user_id'], $_SESSION['user_name'] ?? '', $_SESSION['user_role'] ?? '', 'blog', 'updated', (string) $postId, 'Updated blog post: ' . $title);

        setFlashMessage('success', 'Blog post "' . htmlspecialchars($title) . '" updated successfully.');
        redirect(SITE_URL . 'admin/blog/index.php');
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
            <h1>Edit Blog Post</h1>
            <p class="text-muted">Update the blog post below.</p>

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
                    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title"
                           value="<?= htmlspecialchars($title) ?>" required maxlength="255"
                           autocomplete="off">
                </div>

                <div class="mb-3">
                    <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="slug" name="slug"
                           value="<?= htmlspecialchars($slug) ?>" required maxlength="255">
                    <div class="form-text">URL-friendly identifier.</div>
                </div>

                <div class="mb-3">
                    <label for="excerpt" class="form-label">Excerpt</label>
                    <textarea class="form-control" id="excerpt" name="excerpt" rows="3"
                              maxlength="500"><?= htmlspecialchars($excerpt) ?></textarea>
                    <div class="form-text">Short summary displayed in the blog listing.</div>
                </div>

                <div class="mb-3">
                    <label for="content" class="form-label">Content <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="content" name="content" rows="16"
                              style="font-family: monospace;"><?= htmlspecialchars($content) ?></textarea>
                    <div class="form-text">HTML content supported.</div>
                </div>

                <?php if ($existingImage): ?>
                    <div class="mb-3">
                        <label class="form-label">Current Image</label>
                        <div>
                            <img src="<?= SITE_URL ?>uploads/blogs/<?= htmlspecialchars($existingImage) ?>"
                                 alt="<?= htmlspecialchars($title) ?>"
                                 style="width: 200px; height: 120px; object-fit: cover; border-radius: 4px; border: 1px solid #dee2e6;">
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="image" class="form-label">
                        <?= $existingImage ? 'Replace Image' : 'Featured Image' ?>
                    </label>
                    <input type="file" class="form-control" id="image" name="image"
                           accept="image/jpeg,image/png,image/gif,image/webp">
                    <div class="form-text">
                        <?= $existingImage ? 'Leave empty to keep the current image.' : 'Accepted: JPEG, PNG, GIF, WebP. Max size: 2 MB.' ?>
                    </div>
                    <img id="imagePreview" src="#" alt="Preview"
                         style="display: none; width: 200px; height: 120px; object-fit: cover; border-radius: 4px; border: 1px solid #dee2e6; margin-top: 8px;">
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                    </select>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn" style="background: var(--admin-primary); color: #fff;">Update Post</button>
                    <a href="<?= SITE_URL ?>admin/blog/index.php" class="btn btn-secondary">Cancel</a>
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
