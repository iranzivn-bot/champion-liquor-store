<?php
declare(strict_types=1);

require_once __DIR__ . '/../middlewares/guest.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../helpers/validation-helper.php';

$pdo = getDbConnection();

$error       = '';
$success     = '';
$token       = $_GET['token'] ?? '';
$validToken  = false;
$userId      = null;

// ─── Validate token on GET ────────────────────────────────
if ($token !== '') {
    $hash   = hash('sha256', $token);
    $stmt   = $pdo->prepare(
        'SELECT id, user_id FROM password_resets
         WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([':hash' => $hash]);
    $row = $stmt->fetch();

    if ($row) {
        $validToken = true;
        $resetId    = (int) $row['id'];
        $userId     = (int) $row['user_id'];
    } else {
        $error = lang('reset_invalid_token');
    }
} else {
    $error = lang('reset_token_empty');
}

// ─── Handle form submission ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    if (!validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $error = lang('invalid_csrf');
    } else {
        $password    = $_POST['password'] ?? '';
        $confirm     = $_POST['password_confirm'] ?? '';
        $submittedToken = $_POST['token'] ?? '';

        // Re-validate the token from the hidden field
        if ($submittedToken === '') {
            $error = lang('reset_token_empty');
        } else {
            $submittedHash = hash('sha256', $submittedToken);
            $stmt = $pdo->prepare(
                'SELECT id, user_id FROM password_resets
                 WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW()
                 LIMIT 1'
            );
            $stmt->execute([':hash' => $submittedHash]);
            $row = $stmt->fetch();

            if (!$row) {
                $error = lang('reset_invalid_token');
            } else {
                $resetId = (int) $row['id'];
                $userId  = (int) $row['user_id'];

                // Validate both passwords
                if ($password === '') {
                    $error = 'Please enter a new password.';
                } elseif ($password !== $confirm) {
                    $error = lang('reset_password_mismatch');
                } else {
                    $pwValidation = validatePasswordStrength($password);
                    if ($pwValidation !== true) {
                        $error = $pwValidation;
                    } else {
                        // All good — update the password and mark token as used
                        $pdo->beginTransaction();
                        try {
                            $newHash = password_hash($password, PASSWORD_DEFAULT);
                            $updateUser = $pdo->prepare('UPDATE users SET password = :pw WHERE id = :id');
                            $updateUser->execute([':pw' => $newHash, ':id' => $userId]);

                            $markUsed = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
                            $markUsed->execute([':id' => $resetId]);

                            $pdo->commit();

                            $success = lang('reset_success');
                            $validToken = false; // hide the form
                        } catch (\Throwable $e) {
                            $pdo->rollBack();
                            error_log('Password reset failed: ' . $e->getMessage());
                            $error = lang('reset_password_failed');
                        }
                    }
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="auth-card">

                <div class="text-center mb-4">
                    <h2 class="fw-bold" style="color: var(--primary);"><?= lang('reset_title') ?></h2>
                    <p class="text-muted small"><?= lang('reset_subtitle') ?></p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                        <div><?= htmlspecialchars($success) ?></div>
                    </div>
                    <div class="text-center mt-3">
                        <a href="<?= SITE_URL ?>pages/login.php" class="btn btn-primary px-4 py-2 fw-semibold"
                           style="background: #001F5B; border-color: #001F5B;">
                            <i class="bi bi-box-arrow-in-right me-1"></i><?= lang('login') ?>
                        </a>
                    </div>

                <?php elseif ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                    <div class="text-center mt-3">
                        <a href="<?= SITE_URL ?>pages/forgot-password.php" class="fw-semibold" style="color: var(--primary);">
                            <i class="bi bi-arrow-left me-1"></i><?= lang('forgot_back') ?>
                        </a>
                    </div>

                <?php else: ?>
                    <!-- Password Reset Form -->
                    <form method="POST" novalidate>
                        <?= csrfField() ?>
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                        <div class="mb-3">
                            <label for="password" class="form-label"><?= lang('reset_password') ?> <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password"
                                   required minlength="8" placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;">
                            <div class="form-text small">Min. 8 characters, uppercase, lowercase, digit &amp; special character.</div>
                        </div>

                        <div class="mb-4">
                            <label for="password_confirm" class="form-label"><?= lang('reset_confirm') ?> <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                                   required minlength="8" placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;">
                        </div>

                        <button type="submit" class="btn w-100 py-2 fw-semibold" style="background: #001F5B; color: #fff;">
                            <i class="bi bi-shield-check me-1"></i><?= lang('reset_button') ?>
                        </button>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
