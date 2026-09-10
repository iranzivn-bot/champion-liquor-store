<?php
declare(strict_types=1);

// Prevent logged-in users from seeing the login page
require_once __DIR__ . '/../middlewares/guest.php';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../helpers/audit-helper.php';

$error   = '';
$email   = '';
$remember = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page.';
    }

    if (!$error) {
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        $remember = isset($_POST['remember']);

        if ($email === '' || $password === '') {
            $error = 'Please enter both email and password.';
        } else {
            $pdo = getDbConnection();

            // ── Rate limiting check ────────────────────────────────────
            $lockoutMsg = checkLoginLockout($pdo, $email);
            if ($lockoutMsg !== null) {
                $error = $lockoutMsg;
            } else {
                $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($password, $user['password'])) {
                    $error = 'Invalid email or password.';
                    recordFailedLogin($pdo, $email);
                    logActivity(null, '', '', 'auth', 'login_failed', null, 'Failed login attempt for email: ' . $email);
                } elseif ($user['status'] !== ACTIVE_STATUS) {
                    $error = 'Your account has been deactivated. Please contact support.';
                } else {
                    // Reset rate limiting counters
                    resetLoginAttempts($pdo, $email);

                    // Set session variables
                    $_SESSION['user_id']   = (int) $user['id'];
                    $_SESSION['user_name']  = $user['full_name'];
                    $_SESSION['user_role']  = $user['role'];
                    $_SESSION['user_image'] = $user['profile_image'];

                    // Remember Me: set a cookie for 30 days
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        $expires = time() + (30 * 24 * 60 * 60);

                        // Store token in a cookie
                        setcookie('remember_token', $token, $expires, '/', '', false, true);
                    }

                    logActivity((int) $user['id'], $user['full_name'], $user['role'], 'auth', 'login', $user['id'], 'User logged in: ' . $user['email']);

                    // Redirect based on role
                    if ($user['role'] === ADMIN_ROLE) {
                        redirect(SITE_URL . 'admin/dashboard.php');
                    }
                    redirect(SITE_URL . 'index.php');
                }
            }
        }
    }
}

$successMsg = getFlashMessage('success');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<link rel="stylesheet" href="<?= SITE_URL ?>assets/css/auth.css">

<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card-luxury">
            <!-- Logo -->
            <img src="<?= SITE_URL ?>assets/images/logo.png"
                 alt="<?= SITE_NAME ?>"
                 class="auth-logo"
                 onerror="this.style.display='none'">

            <!-- Title -->
            <h1 class="auth-title text-center"><?= lang('login_title') ?></h1>
            <p class="auth-subtitle text-center"><?= lang('login_subtitle') ?></p>

            <!-- Success Alert -->
            <?php if ($successMsg): ?>
                <div class="auth-alert auth-alert-success">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?= htmlspecialchars($successMsg) ?></span>
                </div>
            <?php endif; ?>

            <!-- Error Alert -->
            <?php if ($error): ?>
                <div class="auth-alert auth-alert-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" novalidate>
                <?= csrfField() ?>

                <div class="auth-input-group">
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= htmlspecialchars($email) ?>" required
                           placeholder="<?= lang('email') ?>">
                    <i class="bi bi-envelope input-icon"></i>
                </div>

                <div class="auth-input-group">
                    <input type="password" class="form-control" id="password" name="password"
                           required placeholder="<?= lang('password') ?>">
                    <i class="bi bi-lock input-icon"></i>
                    <button type="button" class="password-toggle" onclick="togglePassword('password', this)" tabindex="-1" aria-label="Toggle password visibility">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>

                <div class="auth-row-between">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember"
                               value="1" <?= $remember ? 'checked' : '' ?>>
                        <label class="form-check-label" for="remember"><?= lang('remember_me') ?></label>
                    </div>
                    <a href="<?= SITE_URL ?>pages/forgot-password.php" class="auth-forgot-link">
                        <i class="bi bi-question-circle"></i>
                        <?= lang('forgot_password') ?>
                    </a>
                </div>

                <button type="submit" class="auth-btn auth-btn-primary">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <?= lang('login') ?>
                </button>
            </form>

            <!-- OR Divider -->
            <div class="auth-divider">
                <span class="auth-divider-line"></span>
                <span class="auth-divider-text"><?= lang('or') ?></span>
                <span class="auth-divider-line"></span>
            </div>

            <!-- Google Button -->
            <button type="button" class="auth-btn auth-btn-google" onclick="alert('Google login coming soon!')">
                <svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M43.611 20.083H42V20H24V28H35.303C34.066 32.022 30.374 35 24 35C18.477 35 14 30.523 14 25C14 19.477 18.477 15 24 15C26.582 15 28.92 15.997 30.689 17.561L36.412 11.838C33.413 9.098 29.415 7.4 25 7.4C14.507 7.4 6 15.907 6 26.4C6 36.893 14.507 45.4 25 45.4C35.493 45.4 44 36.893 44 26.4C44 25.088 43.868 23.807 43.611 20.083Z" fill="#FFC107"/>
                    <path d="M6.306 15.691L12.877 20.325C14.204 16.525 17.544 13.7 21.5 13.1L21.5 13.1C18.5 10.1 14.5 9 10.5 10.5L10.5 10.5C8.5 11.2 6.9 13.1 6.306 15.691Z" fill="#FF3D00"/>
                    <path d="M25 45.4C29.3 45.4 33.2 43.8 36.1 41.2L36.1 41.2C33.3 43.6 29.6 45.2 25 45.2C20.4 45.2 15.9 42.9 13.1 39.5L13.1 39.5L13.1 39.5L6.5 44.2C9.8 48.7 16.1 51.9 24.9 51.9C35.4 51.9 44 44.9 44 34.8C44 33.1 43.8 31.4 43.4 29.8H25V24H43.6C44 25.6 44.2 27.3 44.2 29C44.2 38.7 36.2 45.4 25 45.4Z" fill="#4CAF50"/>
                    <path d="M43.611 20.083H42V20H24V28H35.303C34.718 29.999 33.609 31.784 32.088 33.106C32.088 33.106 32.088 33.106 32.088 33.106L38.9 38.3C41.5 36 43.5 32.6 44.2 28.9C44.4 27.8 44.5 26.6 44.5 25.4C44.5 23.9 44.3 21.9 43.611 20.083Z" fill="#1976D2"/>
                </svg>
                <?= lang('continue_with_google') ?>
            </button>

            <!-- Bottom Link -->
            <div class="auth-bottom-text">
                <p><?= lang('no_account') ?>
                    <a href="<?= SITE_URL ?>pages/register.php">
                        <i class="bi bi-person-plus me-1"></i><?= lang('register') ?>
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
