<?php
declare(strict_types=1);

// Prevent logged-in users from seeing the registration page
require_once __DIR__ . '/../middlewares/guest.php';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../helpers/audit-helper.php';

$errors   = [];
$fullName = '';
$email    = '';
$phone    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh the page.';
    }

    if (count($errors) === 0) {
        $fullName       = trim($_POST['full_name']       ?? '');
        $email          = trim($_POST['email']            ?? '');
        $phone          = trim($_POST['phone']            ?? '');
        $password       = $_POST['password']              ?? '';
        $confirmPass    = $_POST['confirm_password']      ?? '';

        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }
        if ($email === '') {
            $errors[] = 'Email is required.';
        }
        if ($phone === '') {
            $errors[] = 'Phone number is required.';
        }
        if ($password === '') {
            $errors[] = 'Password is required.';
        }
        if ($confirmPass === '') {
            $errors[] = 'Please confirm your password.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($password !== '') {
            $pwErr = validatePasswordStrength($password);
            if ($pwErr !== null) {
                $errors[] = $pwErr;
            }
        }
        if ($password !== '' && $confirmPass !== '' && $password !== $confirmPass) {
            $errors[] = 'Passwords do not match.';
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
            $stmt->execute([':email' => $email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'An account with this email already exists.';
            }
        }

        if (count($errors) === 0) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $pdo = getDbConnection();
            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, phone, password, role, status) VALUES (:full_name, :email, :phone, :password, :role, :status)');
            $stmt->execute([
                ':full_name' => $fullName,
                ':email'     => $email,
                ':phone'     => $phone,
                ':password'  => $hashedPassword,
                ':role'      => CUSTOMER_ROLE,
                ':status'    => ACTIVE_STATUS,
            ]);
            $newUserId = (int) $pdo->lastInsertId();
            logActivity($newUserId, $fullName, CUSTOMER_ROLE, 'auth', 'created', $newUserId, 'New account registered: ' . $email);
            setFlashMessage('success', 'Registration successful! You can now log in.');
            redirect(SITE_URL . 'pages/login.php');
        }
    }
}

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
            <h1 class="auth-title text-center"><?= lang('register_title') ?></h1>
            <p class="auth-subtitle text-center"><?= lang('register_subtitle') ?></p>

            <!-- Error Alert -->
            <?php if (count($errors) > 0): ?>
                <div class="auth-alert auth-alert-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Register Form -->
            <form method="POST" novalidate>
                <?= csrfField() ?>

                <div class="auth-input-group">
                    <input type="text" class="form-control" id="full_name" name="full_name"
                           value="<?= htmlspecialchars($fullName) ?>" required maxlength="100"
                           placeholder="<?= lang('full_name') ?>">
                    <i class="bi bi-person input-icon"></i>
                </div>

                <div class="auth-input-group">
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= htmlspecialchars($email) ?>" required maxlength="255"
                           placeholder="<?= lang('email') ?>">
                    <i class="bi bi-envelope input-icon"></i>
                </div>

                <div class="auth-input-group">
                    <input type="tel" class="form-control" id="phone" name="phone"
                           value="<?= htmlspecialchars($phone) ?>" required maxlength="20"
                           placeholder="<?= lang('phone') ?>">
                    <i class="bi bi-telephone input-icon"></i>
                </div>

                <div class="auth-input-group">
                    <input type="password" class="form-control" id="password" name="password"
                           required minlength="8" placeholder="<?= lang('password') ?>">
                    <i class="bi bi-lock input-icon"></i>
                    <button type="button" class="password-toggle" onclick="togglePassword('password', this)" tabindex="-1" aria-label="Toggle password visibility">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>

                <div class="auth-input-group">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                           required placeholder="<?= lang('confirm_password') ?>">
                    <i class="bi bi-lock-fill input-icon"></i>
                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)" tabindex="-1" aria-label="Toggle password visibility">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>

                <div class="auth-row-between">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="terms" required>
                        <label class="form-check-label" for="terms"><?= lang('agree_terms') ?></label>
                    </div>
                </div>

                <button type="submit" class="auth-btn auth-btn-primary">
                    <i class="bi bi-person-plus"></i>
                    <?= lang('register') ?>
                </button>
            </form>

            <!-- OR Divider -->
            <div class="auth-divider">
                <span class="auth-divider-line"></span>
                <span class="auth-divider-text"><?= lang('or') ?></span>
                <span class="auth-divider-line"></span>
            </div>

            <!-- Google Button -->
            <button type="button" class="auth-btn auth-btn-google" onclick="alert('Google signup coming soon!')">
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
                <p><?= lang('already_account') ?>
                    <a href="<?= SITE_URL ?>pages/login.php">
                        <i class="bi bi-box-arrow-in-right me-1"></i><?= lang('login') ?>
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
