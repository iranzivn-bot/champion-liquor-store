<?php
/**
 * Admin Top Navigation Bar
 *
 * Displays the brand logo, current page title,
 * admin profile section, language switcher, and logout button.
 */
declare(strict_types=1);
$pageTitle = $pageTitle ?? lang('dashboard');
$activeLangs = getActiveLanguages();
$currentLang = getCurrentLanguage();

// Build a language-switch URL that preserves the current admin page
$adminPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$adminQuery = $_GET;
unset($adminQuery['lang']);
$adminLangUrlBase = $adminPath . (!empty($adminQuery) ? '?' . http_build_query($adminQuery) . '&' : '?');
?>

<!-- Top Navbar -->
<header class="admin-navbar">
    <div class="admin-navbar-inner">

        <!-- Hamburger toggle for mobile -->
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
        </button>

        <!-- Brand logo -->
        <div class="admin-navbar-brand">
            <a href="<?= SITE_URL ?>admin/dashboard.php"><?= SITE_NAME ?></a>
        </div>

        <!-- Page title (visible on larger screens) -->
        <span class="admin-navbar-title"><?= htmlspecialchars($pageTitle) ?></span>

        <!-- Right section: language switcher, profile + logout -->
        <div class="admin-navbar-right">

            <!-- Language Switcher -->
            <div class="dropdown d-inline-block me-2">
                <a class="text-white text-decoration-none dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" style="font-size:0.9rem;">
                    <i class="bi bi-globe2"></i> <?= htmlspecialchars($activeLangs[$currentLang]['flag'] ?? '') ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php foreach ($activeLangs as $code => $info): ?>
                    <li>
                        <a class="dropdown-item <?= $code === $currentLang ? 'active' : '' ?>"
                           href="<?= htmlspecialchars($adminLangUrlBase) ?>lang=<?= htmlspecialchars($code) ?>">
                            <?= htmlspecialchars($info['flag']) ?> <?= htmlspecialchars($info['name']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php
            // Get user profile image
            $userImg = DEFAULT_USER_IMAGE;
            if (!empty($_SESSION['user_image'])) {
                $userImg = SITE_URL . 'uploads/users/' . rawurlencode($_SESSION['user_image']);
            }
            ?>
            <div class="dropdown d-inline-block">
                <a class="text-white text-decoration-none dropdown-toggle admin-profile" href="#" role="button" data-bs-toggle="dropdown">
                    <span class="admin-profile-avatar">
                        <img src="<?= $userImg ?>" alt=""
                             style="width:28px;height:28px;object-fit:cover;border-radius:50%;">
                    </span>
                    <span class="admin-profile-name"><?= htmlspecialchars($_SESSION['user_name'] ?? lang('admin_panel')) ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="<?= SITE_URL ?>admin/profile.php">
                            <i class="bi bi-person"></i> My Profile
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="<?= SITE_URL ?>admin/change-password.php">
                            <i class="bi bi-key"></i> Change Password
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="<?= SITE_URL ?>pages/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>

    </div>
</header>
