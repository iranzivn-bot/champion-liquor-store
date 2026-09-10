<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middlewares/auth.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo    = getDbConnection();
$userId = (int) $_SESSION['user_id'];

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $language = trim($_POST['language'] ?? '');
        $removeImage = !empty($_POST['remove_image']);

        if ($fullName === '') {
            $error = 'Full name is required.';
        } elseif ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            $error = 'Please enter a valid phone number.';
        } elseif ($language !== '' && !isValidLanguageCode($language)) {
            $error = 'Invalid language selection.';
        } else {
            $updateSql = 'UPDATE users SET full_name = :full_name, phone = :phone, language = :language';
            $params = [
                ':full_name' => $fullName,
                ':phone'     => $phone,
                ':language'  => $language,
                ':id'        => $userId,
            ];

            // Handle profile image removal
            if ($removeImage) {
                $stmt = $pdo->prepare('SELECT profile_image FROM users WHERE id = :id');
                $stmt->execute([':id' => $userId]);
                $old = $stmt->fetchColumn();
                if ($old && file_exists(BASE_PATH . 'uploads/users/' . $old)) {
                    unlink(BASE_PATH . 'uploads/users/' . $old);
                }
                $updateSql .= ', profile_image = NULL';
            }

            // Handle profile image upload
            if (!empty($_FILES['profile_image']['name']) && !$removeImage) {
                $file = $_FILES['profile_image'];
                $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $maxSize = 2 * 1024 * 1024; // 2MB

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error = 'File upload failed. Please try again.';
                } elseif ($file['size'] > $maxSize) {
                    $error = 'Image must be under 2MB.';
                } else {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);

                    if (!in_array($mime, $allowedMime, true)) {
                        $error = 'Only JPG, PNG, GIF, and WebP images are allowed.';
                    } else {
                        $ext = match ($mime) {
                            'image/jpeg' => 'jpg',
                            'image/png'  => 'png',
                            'image/gif'  => 'gif',
                            'image/webp' => 'webp',
                            default      => 'jpg',
                        };
                        $filename = 'user_' . $userId . '_' . time() . '.' . $ext;
                        $dest = USER_UPLOAD_PATH . $filename;

                        if (move_uploaded_file($file['tmp_name'], $dest)) {
                            // Delete old image
                            $stmt = $pdo->prepare('SELECT profile_image FROM users WHERE id = :id');
                            $stmt->execute([':id' => $userId]);
                            $old = $stmt->fetchColumn();
                            if ($old && file_exists(USER_UPLOAD_PATH . $old)) {
                                unlink(USER_UPLOAD_PATH . $old);
                            }
                            $updateSql .= ', profile_image = :profile_image';
                            $params[':profile_image'] = $filename;
                        } else {
                            $error = 'Failed to save the uploaded image.';
                        }
                    }
                }
            }

            if (!$error) {
                $updateSql .= ' WHERE id = :id';
                $stmt = $pdo->prepare($updateSql);
                $stmt->execute($params);

                $_SESSION['user_name'] = $fullName;
                if (isset($params[':profile_image'])) {
                    $_SESSION['user_image'] = $filename;
                } elseif ($removeImage) {
                    $_SESSION['user_image'] = null;
                }

                if ($language !== '') {
                    setLanguage($language);
                }

                setFlashMessage('success', 'Your profile has been updated successfully.');
                redirect(SITE_URL . 'pages/dashboard/profile.php');
            }
        }
    }
}

$stmt = $pdo->prepare('SELECT full_name, email, phone, language, profile_image FROM users WHERE id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    redirect(SITE_URL . 'pages/login.php');
}

$avatarUrl = $user['profile_image']
    ? SITE_URL . 'uploads/users/' . rawurlencode($user['profile_image'])
    : DEFAULT_USER_IMAGE;

$activeLangs = getActiveLanguages();
$flashSuccess = getFlashMessage('success');
$flashError   = getFlashMessage('error');

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>"><?= lang('home') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>pages/dashboard/index.php"><?= lang('dashboard') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= lang('profile') ?></li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card auth-card">
                <div class="card-body p-5">

                    <h2 class="fw-bold mb-4" style="color: #001F5B;">
                        <i class="bi bi-person-gear"></i> <?= lang('profile') ?>
                    </h2>

                    <?php if ($flashSuccess): ?>
                        <div class="alert alert-success d-flex align-items-center alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?= htmlspecialchars($flashSuccess) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" novalidate>
                        <?= csrfField() ?>

                        <!-- Avatar -->
                        <div class="text-center mb-4">
                            <div class="position-relative d-inline-block">
                                <img src="<?= htmlspecialchars($avatarUrl) ?>"
                                     alt="<?= lang('profile') ?>"
                                     id="avatarPreview"
                                     class="rounded-circle border shadow-sm"
                                     style="width: 120px; height: 120px; object-fit: cover; border-color: #C9A227 !important;">
                                <label for="profile_image"
                                       class="position-absolute bottom-0 end-0 btn btn-sm rounded-circle p-1 shadow-sm"
                                       style="background: #C9A227; color: #fff; cursor: pointer; width: 34px; height: 34px; line-height: 1;">
                                    <i class="bi bi-camera-fill" style="font-size: 0.9rem;"></i>
                                </label>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted"><?= lang('click_to_change_photo') ?></small>
                            </div>
                            <input type="file" id="profile_image" name="profile_image"
                                   accept="image/jpeg,image/png,image/gif,image/webp"
                                   class="d-none" onchange="previewAvatar(event)">
                            <?php if ($user['profile_image']): ?>
                                <div class="mt-2">
                                    <button type="submit" name="remove_image" value="1" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('<?= lang('confirm_remove_photo') ?>')">
                                        <i class="bi bi-trash3"></i> <?= lang('remove_photo') ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="full_name" class="form-label"><?= lang('full_name') ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name"
                                   value="<?= htmlspecialchars($user['full_name']) ?>" required maxlength="100">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label"><?= lang('email') ?> <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($user['email']) ?>" readonly
                                   style="background: #f5f5f5; cursor: not-allowed;">
                            <small class="text-muted"><?= lang('email_readonly_hint') ?></small>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label"><?= lang('phone') ?></label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>" maxlength="20">
                        </div>

                        <div class="mb-4">
                            <label for="language" class="form-label"><?= lang('language') ?></label>
                            <select name="language" id="language" class="form-select">
                                <?php foreach ($activeLangs as $code => $info): ?>
                                <option value="<?= htmlspecialchars($code) ?>"
                                        <?= ($user['language'] === $code || (!empty($_SESSION['language']) && $_SESSION['language'] === $code)) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($info['flag']) ?> <?= htmlspecialchars($info['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="d-flex gap-3">
                            <button type="submit" class="btn px-4 py-2 fw-semibold" style="background: #C9A227; color: #fff;">
                                <i class="bi bi-floppy"></i> <?= lang('save_changes') ?>
                            </button>
                            <a href="<?= SITE_URL ?>pages/dashboard/change-password.php" class="btn btn-outline-primary px-4 py-2 fw-semibold">
                                <i class="bi bi-key"></i> <?= lang('change_password') ?>
                            </a>
                        </div>
                    </form>

                    <hr class="my-4">

                    <div class="d-flex flex-wrap gap-3">
                        <a href="<?= SITE_URL ?>pages/dashboard/index.php" class="btn btn-outline-secondary fw-semibold">
                            <i class="bi bi-speedometer2"></i> <?= lang('dashboard') ?>
                        </a>
                        <a href="<?= SITE_URL ?>pages/dashboard/orders.php" class="btn btn-outline-primary fw-semibold">
                            <i class="bi bi-bag-check"></i> <?= lang('my_orders') ?>
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
function previewAvatar(e) {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(ev) {
        document.getElementById('avatarPreview').src = ev.target.result;
    };
    reader.readAsDataURL(file);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
