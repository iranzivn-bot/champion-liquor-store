<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../helpers/audit-helper.php';

requireAdmin();

$pageTitle = 'My Profile';

$pdo = getDbConnection();
$userId = (int) $_SESSION['user_id'];

$uploadDir = USER_UPLOAD_PATH;

// Fetch current user
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    setFlashMessage('error', 'User not found.');
    redirect(SITE_URL . 'admin/dashboard.php');
}

$success = getFlashMessage('success');
$error   = '';

// ─── Handle form submission ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');

        if ($fullName === '') {
            $error = 'Full name is required.';
        } elseif ($phone === '') {
            $error = 'Phone number is required.';
        }

        if (!$error) {
            // Handle image upload
            $imageName = $user['image'];
            if (!empty($_FILES['image']['name'])) {
                $file = $_FILES['image'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $allowedExts  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $maxFileSize  = 2 * 1024 * 1024;
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error = 'Image upload failed with error code ' . $file['error'] . '.';
                } elseif (!in_array($mimeType, $allowedTypes, true)) {
                    $error = 'Image must be JPEG, PNG, GIF, or WebP.';
                } elseif (!in_array($ext, $allowedExts, true)) {
                    $error = 'Image file extension must be jpg, jpeg, png, gif, or webp.';
                } elseif ($file['size'] > $maxFileSize) {
                    $error = 'Image must be smaller than 2 MB.';
                } else {
                    $newImage = generateUniqueFilename($file['name']);
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newImage)) {
                        // Delete old image
                        if ($imageName && file_exists($uploadDir . $imageName)) {
                            unlink($uploadDir . $imageName);
                        }
                        $imageName = $newImage;
                    } else {
                        $error = 'Failed to move uploaded file.';
                    }
                }
            }

            if (!$error) {
                $stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, phone = :phone, image = :image WHERE id = :id');
                $stmt->execute([
                    ':full_name' => $fullName,
                    ':phone'     => $phone,
                    ':image'     => $imageName,
                    ':id'        => $userId,
                ]);

                // Update session
                $_SESSION['user_name']  = $fullName;
                $_SESSION['user_image'] = $imageName;

                logActivity($userId, $fullName, $user['role'], 'users', 'profile_updated', $userId, 'Profile updated for user ID: ' . $userId);

                setFlashMessage('success', 'Profile updated successfully.');
                redirect(SITE_URL . 'admin/profile.php');
            }
        }
    }
}

require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/admin-navbar.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<div class="admin-content-wrapper">
    <main class="admin-content">

        <div class="py-3">
            <h2 class="fw-bold mb-1" style="color: var(--admin-primary);">
                <i class="bi bi-person-circle"></i> My Profile
            </h2>
            <p class="text-muted">Manage your account information and profile photo.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Profile Info & Photo -->
            <div class="col-lg-4">
                <div class="card shadow-sm text-center">
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <img src="<?= $user['image'] ? SITE_URL . 'uploads/users/' . htmlspecialchars($user['image']) : DEFAULT_USER_IMAGE ?>"
                                 alt="<?= htmlspecialchars($user['full_name']) ?>"
                                 class="rounded-circle"
                                 style="width: 120px; height: 120px; object-fit: cover; border: 3px solid var(--admin-primary);">
                        </div>
                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($user['full_name']) ?></h5>
                        <p class="text-muted small mb-1"><?= htmlspecialchars($user['email']) ?></p>
                        <span class="badge <?= $user['role'] === SUPER_ADMIN_ROLE ? 'bg-danger' : 'bg-primary' ?>">
                            <?= htmlspecialchars(str_replace('_', ' ', ucfirst($user['role']))) ?>
                        </span>
                        <hr>
                        <a href="<?= SITE_URL ?>admin/change-password.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-key"></i> Change Password
                        </a>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <form method="POST" enctype="multipart/form-data">
                            <?= csrfField() ?>

                            <div class="mb-3">
                                <label for="full_name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name"
                                       value="<?= htmlspecialchars($user['full_name']) ?>" required maxlength="100">
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label fw-semibold">Email</label>
                                <input type="email" class="form-control bg-light" id="email"
                                       value="<?= htmlspecialchars($user['email']) ?>" readonly>
                                <div class="form-text">Email cannot be changed.</div>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="phone" name="phone"
                                       value="<?= htmlspecialchars($user['phone']) ?>" required maxlength="20">
                            </div>

                            <div class="mb-3">
                                <label for="image" class="form-label fw-semibold">Profile Photo</label>
                                <?php if ($user['image']): ?>
                                    <div class="mb-2">
                                        <img src="<?= SITE_URL ?>uploads/users/<?= htmlspecialchars($user['image']) ?>"
                                             alt="Current photo" style="width:64px;height:64px;object-fit:cover;border-radius:50%;border:2px solid #dee2e6;">
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="image" name="image"
                                       accept="image/jpeg,image/png,image/gif,image/webp">
                                <div class="form-text">Accepted: JPEG, PNG, GIF, WebP. Max 2 MB. Leave empty to keep current photo.</div>
                                <img id="imagePreview" src="#" alt="Preview"
                                     style="display:none;width:64px;height:64px;object-fit:cover;border-radius:50%;border:2px solid #dee2e6;margin-top:8px;">
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn px-4 fw-semibold" style="background: var(--admin-primary); color: #fff;">
                                    <i class="bi bi-check-lg"></i> Save Changes
                                </button>
                                <a href="<?= SITE_URL ?>admin/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
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

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
