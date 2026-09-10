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
    }

    if (!$error) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '') {
            $error = 'Please enter your current password.';
        } elseif ($newPassword === '') {
            $error = 'Please enter a new password.';
        } else {
            $pwErr = validatePasswordStrength($newPassword);
            if ($pwErr !== null) {
                $error = $pwErr;
            }
        }

        if (!$error && $newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        }

        if (!$error) {
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id');
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($currentPassword, $user['password'])) {
                $error = 'Current password is incorrect.';
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
                $stmt->execute([
                    ':password' => $hashedPassword,
                    ':id'       => $userId,
                ]);

                setFlashMessage('success', 'Your password has been changed successfully.');
                redirect(SITE_URL . 'pages/dashboard/change-password.php');
            }
        }
    }
}

$flashSuccess = getFlashMessage('success');

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>"><?= lang('home') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>pages/dashboard/index.php"><?= lang('dashboard') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>pages/dashboard/profile.php"><?= lang('profile') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= lang('change_password') ?></li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-6 mx-auto">
            <div class="card auth-card">
                <div class="card-body p-5">

                    <h2 class="fw-bold mb-4" style="color: #001F5B;">
                        <i class="bi bi-key"></i> <?= lang('change_password') ?>
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

                    <form method="POST" novalidate>
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label for="current_password" class="form-label"><?= lang('current_password') ?> <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="current_password"
                                   name="current_password" required
                                   placeholder="<?= lang('enter_current_password') ?>">
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label"><?= lang('new_password') ?> <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="new_password"
                                   name="new_password" required minlength="8"
                                   placeholder="<?= lang('enter_new_password') ?>">
                            <div class="form-text">
                                <?= lang('password_requirements') ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label"><?= lang('confirm_new_password') ?> <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password"
                                   name="confirm_password" required
                                   placeholder="<?= lang('repeat_new_password') ?>">
                        </div>

                        <div class="d-flex gap-3">
                            <button type="submit" class="btn px-4 py-2 fw-semibold" style="background: #C9A227; color: #fff;">
                                <i class="bi bi-check-lg"></i> <?= lang('update_password') ?>
                            </button>
                            <a href="<?= SITE_URL ?>pages/dashboard/profile.php" class="btn btn-outline-secondary px-4 py-2 fw-semibold">
                                <?= lang('cancel') ?>
                            </a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
