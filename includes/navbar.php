<?php
/**
 * Premium Header & Navigation
 *
 * Three-tier luxury header:
 *   1. Top Announcement Bar
 *   2. Main Header (logo, search, actions)
 *   3. Category Navigation with Mega Menu
 *
 * Fully responsive with mobile off-canvas menu.
 * Preserves all existing auth, cart, wishlist, and language functionality.
 */
declare(strict_types=1);

$currentPage = basename($_SERVER['SCRIPT_NAME']);
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$currentQuery = $_GET;
unset($currentQuery['lang']);
$langUrlBase = $currentPath . (!empty($currentQuery) ? '?' . http_build_query($currentQuery) . '&' : '?');

// Fetch categories for mega menu, grouped
$navLiquorCats = [];
$navMarketCats = [];
try {
    $navPdo = getDbConnection();
    $navLiquorCats = $navPdo->query("SELECT id, code, name, slug FROM categories WHERE status = 'active' AND `group` = 'liquor' ORDER BY name ASC LIMIT 15")->fetchAll();
    $navMarketCats = $navPdo->query("SELECT id, code, name, slug FROM categories WHERE status = 'active' AND `group` = 'mini_market' ORDER BY name ASC LIMIT 15")->fetchAll();
} catch (\Throwable $e) {
    error_log('Nav categories fetch failed: ' . $e->getMessage());
}

// ─── Precompute badge counts (avoids 9 redundant DB queries) ──────
$navCartCount    = 0;
$navWishlistCount = 0;
$navCompareCount  = 0;

try {
    $navBadgePdo = getDbConnection();

    // Cart count (logged-in users only)
    if (function_exists('isLoggedIn') && isLoggedIn()) {
        $s = $navBadgePdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = :uid');
        $s->execute([':uid' => (int) $_SESSION['user_id']]);
        $navCartCount = (int) $s->fetchColumn();
    }

    // Wishlist count (logged-in or guest)
    $wlUserId = (function_exists('isLoggedIn') && isLoggedIn()) ? (int) $_SESSION['user_id'] : null;
    $wlSessionId = $_SESSION['wishlist_session_id'] ?? null;
    if ($wlUserId) {
        $s = $navBadgePdo->prepare('SELECT COUNT(*) FROM wishlist WHERE user_id = :uid');
        $s->execute([':uid' => $wlUserId]);
        $navWishlistCount = (int) $s->fetchColumn();
    } elseif ($wlSessionId) {
        $s = $navBadgePdo->prepare('SELECT COUNT(*) FROM wishlist WHERE session_id = :sid');
        $s->execute([':sid' => $wlSessionId]);
        $navWishlistCount = (int) $s->fetchColumn();
    }

    // Compare count (logged-in or guest)
    $cmpUserId = (function_exists('isLoggedIn') && isLoggedIn()) ? (int) $_SESSION['user_id'] : null;
    $cmpSessionId = $_SESSION['compare_session_id'] ?? null;
    if ($cmpUserId) {
        $s = $navBadgePdo->prepare('SELECT COUNT(*) FROM compare_list WHERE user_id = :uid');
        $s->execute([':uid' => $cmpUserId]);
        $navCompareCount = (int) $s->fetchColumn();
    } elseif ($cmpSessionId) {
        $s = $navBadgePdo->prepare('SELECT COUNT(*) FROM compare_list WHERE session_id = :sid');
        $s->execute([':sid' => $cmpSessionId]);
        $navCompareCount = (int) $s->fetchColumn();
    }
} catch (\Throwable $e) {
    error_log('Nav badge counts fetch failed: ' . $e->getMessage());
}

/**
 * Map a category name to a Bootstrap icon for the mega menu.
 */
function getNavCategoryIcon(string $name): string {
    $name = mb_strtolower(trim($name));
    $map = [
        'wine'       => 'bi-wine',
        'whisky'     => 'bi-cup-straw',
        'whiskey'    => 'bi-cup-straw',
        'vodka'      => 'bi-cup-straw',
        'beer'       => 'bi-cup-straw',
        'sparkling'  => 'bi-stars',
        'champagne'  => 'bi-stars',
        'soft drink' => 'bi-droplet',
        'juice'      => 'bi-droplet',
        'water'      => 'bi-droplet',
        'snack'      => 'bi-basket',
        'food'       => 'bi-basket',
        'spirit'     => 'bi-cup-straw',
        'liquor'     => 'bi-cup-straw',
        'brandy'     => 'bi-cup-straw',
        'gin'        => 'bi-cup-straw',
        'rum'        => 'bi-cup-straw',
        'tequila'    => 'bi-cup-straw',
        'cocktail'   => 'bi-emoji-wink',
        'premium'    => 'bi-star',
        'gift'       => 'bi-gift',
        'accessory'  => 'bi-box',
        'default'    => 'bi-tag',
    ];
    foreach ($map as $keyword => $icon) {
        if (str_contains($name, $keyword)) return $icon;
    }
    return $map['default'];
}
?>
<!-- ════════════════════════════════════════════════════
     SITE HEADER WRAPPER (sticky container)
     ════════════════════════════════════════════════════ -->
<div class="site-header-wrapper" id="siteHeader">

    <!-- ─── TOP ANNOUNCEMENT BAR ─────────────────────── -->
    <div class="top-announcement-bar">
        <div class="container-wide">
            <div class="announcement-inner">
                <div class="announcement-left">
                    <i class="bi bi-shield-check"></i>
                    <span><?= htmlspecialchars(setting('company_tagline', 'Welcome to ' . SITE_NAME)) ?></span>
                </div>
                <div class="announcement-right">
                    <span class="announcement-item">
                        <i class="bi bi-cup-straw"></i>
                        <?= lang('premium_liquor') ?>
                    </span>
                    <span class="announcement-item hide-lg">
                        <i class="bi bi-basket"></i>
                        <?= lang('mini_supermarket') ?>
                    </span>
                    <span class="announcement-item hide-md">
                        <i class="bi bi-truck"></i>
                        <?= lang('fast_delivery') ?>
                    </span>
                    <a href="<?= SITE_URL ?>pages/track-order.php" title="<?= lang('track_order') ?>">
                        <i class="bi bi-geo-alt"></i>
                        <?= lang('track_order') ?>
                    </a>
                    <a href="<?= SITE_URL ?>pages/contact.php" title="<?= lang('help_center') ?>">
                        <i class="bi bi-question-circle"></i>
                        <?= lang('help_center') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── MAIN HEADER ──────────────────────────────── -->
    <header class="main-header" id="mainHeader">
        <div class="container-wide">
            <div class="main-header-inner">

                <!-- Logo -->
                <div class="header-logo">
                    <a href="<?= SITE_URL ?>">
                        <span class="logo-icon">
                            <?php $navLogoIcon = setting('site_logo_icon', ''); if ($navLogoIcon): ?>
                                <img src="<?= SITE_URL ?>uploads/settings/<?= htmlspecialchars($navLogoIcon) ?>"
                                     alt="<?= SITE_NAME ?>" style="width:44px;height:44px;object-fit:cover;border-radius:6px;">
                            <?php else: ?>
                                <i class="bi bi-shop"></i>
                            <?php endif; ?>
                        </span>
                        <span class="logo-text">
                            <span class="logo-title"><?= SITE_NAME ?></span>
                            <span class="logo-tagline"><?= lang('premium_liquor') ?> &amp; <?= lang('mini_market') ?></span>
                        </span>
                    </a>
                </div>

                <!-- Search -->
                <div class="header-search">
                    <form action="<?= SITE_URL ?>pages/shop.php" method="GET" role="search">
                        <div class="search-wrapper">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" name="search" placeholder="<?= lang('search_products') ?>"
                                   value="<?= htmlspecialchars(trim($_GET['search'] ?? '')) ?>"
                                   autocomplete="off" aria-label="<?= lang('search') ?>">
                            <button type="submit" class="search-btn" aria-label="<?= lang('search') ?>">
                                <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Header Actions -->
                <div class="header-actions">

                    <!-- Compare -->
                    <a href="<?= SITE_URL ?>pages/compare/index.php"
                       class="header-action-btn"
                       title="<?= lang('compare') ?>"
                       aria-label="<?= lang('compare') ?>">
                        <span class="action-icon">
                            <i class="bi bi-arrow-left-right"></i>
                            <span class="action-badge" id="nav-compare-count"><?= $navCompareCount ?></span>
                        </span>
                        <span class="action-label"><?= lang('compare') ?></span>
                    </a>

                    <!-- Wishlist -->
                    <a href="<?= SITE_URL ?>pages/wishlist/index.php"
                       class="header-action-btn"
                       title="<?= lang('wishlist') ?>"
                       aria-label="<?= lang('wishlist') ?>">
                        <span class="action-icon">
                            <i class="bi bi-heart"></i>
                            <span class="action-badge" id="nav-wishlist-count"><?= $navWishlistCount ?></span>
                        </span>
                        <span class="action-label"><?= lang('wishlist') ?></span>
                    </a>

                    <!-- Account Dropdown -->
                    <div class="dropdown account-dropdown">
                        <button class="header-action-btn dropdown-toggle"
                                type="button" data-bs-toggle="dropdown"
                                aria-expanded="false"
                                aria-label="<?= lang('my_account') ?>">
                            <span class="action-icon">
                                <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                                    <?php
                                    $img = $_SESSION['user_image'] ?? null;
                                    $src = $img ? SITE_URL . 'uploads/users/' . rawurlencode($img) : null;
                                    ?>
                                    <?php if ($src): ?>
                                        <img src="<?= htmlspecialchars($src) ?>" alt=""
                                             class="rounded-circle" style="width:28px;height:28px;object-fit:cover;">
                                    <?php else: ?>
                                        <i class="bi bi-person-circle"></i>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <i class="bi bi-person"></i>
                                <?php endif; ?>
                            </span>
                            <span class="action-label">
                                <?= function_exists('isLoggedIn') && isLoggedIn()
                                    ? htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? '')[0])
                                    : lang('login')
                                ?>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                                <li>
                                    <h6 class="dropdown-header">
                                        <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>
                                    </h6>
                                </li>
                                <li><a class="dropdown-item" href="<?= SITE_URL ?>pages/dashboard/index.php">
                                    <i class="bi bi-person"></i> <?= lang('my_account') ?>
                                </a></li>
                                <li><a class="dropdown-item" href="<?= SITE_URL ?>pages/dashboard/orders.php">
                                    <i class="bi bi-box"></i> <?= lang('my_orders') ?>
                                </a></li>
                                <li><a class="dropdown-item" href="<?= SITE_URL ?>pages/dashboard/profile.php">
                                    <i class="bi bi-gear"></i> <?= lang('settings') ?>
                                </a></li>
                                <?php if (function_exists('isAdmin') && isAdmin()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= SITE_URL ?>admin/dashboard.php">
                                        <i class="bi bi-speedometer2"></i> <?= lang('admin_panel') ?>
                                    </a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= SITE_URL ?>pages/logout.php">
                                    <i class="bi bi-box-arrow-right"></i> <?= lang('logout') ?>
                                </a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="<?= SITE_URL ?>pages/login.php">
                                    <i class="bi bi-box-arrow-in-right"></i> <?= lang('login') ?>
                                </a></li>
                                <li><a class="dropdown-item" href="<?= SITE_URL ?>pages/register.php">
                                    <i class="bi bi-person-plus"></i> <?= lang('register') ?>
                                </a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <h6 class="dropdown-header"><?= lang('language') ?></h6>
                            </li>
                            <?php foreach ($activeLangs as $code => $info): ?>
                                <li>
                                    <a class="dropdown-item <?= $code === $currentLang ? 'active' : '' ?>"
                                       href="<?= htmlspecialchars($langUrlBase) ?>lang=<?= htmlspecialchars($code) ?>">
                                        <?= htmlspecialchars($info['flag']) ?> <?= htmlspecialchars($info['name']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Cart -->
                    <a href="<?= SITE_URL ?>pages/cart/index.php"
                       class="header-action-btn"
                       title="<?= lang('cart') ?>"
                       aria-label="<?= lang('cart') ?>">
                        <span class="action-icon">
                            <i class="bi bi-cart3"></i>
                            <span class="action-badge" id="nav-cart-count"><?= $navCartCount ?></span>
                        </span>
                        <span class="action-label"><?= lang('cart') ?></span>
                    </a>

                </div><!-- /header-actions -->
            </div><!-- /main-header-inner -->
        </div><!-- /container -->
    </header>

    <!-- ─── CATEGORY NAVIGATION ─────────────────────── -->
    <nav class="category-nav" aria-label="<?= lang('categories') ?>">
        <div class="container-wide">
            <div class="category-nav-inner">

                <!-- All Categories (with Mega Menu) -->
                <div class="all-categories-btn">
                    <button class="cat-trigger" aria-haspopup="true" aria-expanded="false"
                            aria-label="<?= lang('all_categories') ?>">
                        <i class="bi bi-list"></i>
                        <?= lang('all_categories') ?>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <?php if (!empty($navLiquorCats) || !empty($navMarketCats)): ?>
                    <div class="mega-menu" role="menu" aria-label="<?= lang('categories') ?>">
                        <div class="mega-menu-grid">
                            <div class="mega-menu-categories">
                                <div class="mega-menu-col">
                                    <h6><?= lang('liquor_shop') ?></h6>
                                    <ul>
                                        <?php foreach ($navLiquorCats as $cat): ?>
                                        <li>
                                            <a href="<?= SITE_URL ?>pages/shop.php?category=<?= urlencode($cat['slug'] ?? $cat['code']) ?>"
                                               role="menuitem">
                                                <i class="<?= getNavCategoryIcon($cat['name']) ?>"></i>
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </a>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <div class="mega-menu-col">
                                    <h6><?= lang('mini_market') ?></h6>
                                    <ul>
                                        <?php foreach ($navMarketCats as $cat): ?>
                                        <li>
                                            <a href="<?= SITE_URL ?>pages/shop.php?category=<?= urlencode($cat['slug'] ?? $cat['code']) ?>"
                                               role="menuitem">
                                                <i class="<?= getNavCategoryIcon($cat['name']) ?>"></i>
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </a>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                            <div class="mega-menu-featured">
                                <?php
                                $megaMenuImg = setting('mega_menu_promo_image', '');
                                if ($megaMenuImg):
                                ?>
                                <img src="<?= SITE_URL ?>uploads/settings/<?= htmlspecialchars($megaMenuImg) ?>"
                                     alt="Promo">
                                <?php else: ?>
                                <div class="featured-img-placeholder">
                                    <i class="bi bi-gem"></i>
                                </div>
                                <?php endif; ?>
                                <h5><?= lang('premium_liquor') ?></h5>
                                <p><?= lang('mini_supermarket') ?></p>
                            </div>
                        </div>
                        <div class="mega-menu-promo">
                            <p><i class="bi bi-truck me-1"></i> <?= lang('fast_delivery') ?> — <?= lang('free_delivery') ?? 'Free delivery on orders over 50,000 RWF' ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Nav Links (exact order: Home, Liquor, Mini Market, Flash Sale, Brands, Offers, Blog, Contact) -->
                <div class="category-nav-links">
                    <a href="<?= SITE_URL ?>" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                        <?= lang('home') ?>
                    </a>
                    <a href="<?= SITE_URL ?>pages/shop.php?group=liquor" class="<?= (isset($_GET['group']) && $_GET['group'] === 'liquor') ? 'active' : '' ?>">
                        <?= lang('liquor_shop') ?>
                    </a>
                    <a href="<?= SITE_URL ?>pages/shop.php?group=mini_market" class="<?= (isset($_GET['group']) && $_GET['group'] === 'mini_market') ? 'active' : '' ?>">
                        <?= lang('mini_market') ?>
                    </a>
                    <a href="<?= SITE_URL ?>pages/flash-sale/index.php" class="">
                        <?= lang('flash_sales') ?>
                    </a>
                    <a href="<?= SITE_URL ?>pages/brands/index.php" class="">
                        <?= lang('brands') ?>
                    </a>
                    <a href="<?= SITE_URL ?>pages/offers/index.php" title="<?= lang('offers') ?>" aria-label="<?= lang('offers') ?>">
                        <?= lang('offers') ?>
                    </a>
                    <a href="<?= SITE_URL ?>pages/blog/index.php" title="<?= lang('blog') ?>" aria-label="<?= lang('blog') ?>">
                        <?= lang('blog') ?>
                    </a>
                    <a href="<?= SITE_URL ?>pages/contact.php" class="<?= strpos($_SERVER['SCRIPT_NAME'], '/contact.php') !== false ? 'active' : '' ?>">
                        <?= lang('contact') ?>
                    </a>
                </div>

                <!-- Right promo / phone -->
                <div class="category-nav-right">
                    <span class="nav-phone">
                        <i class="bi bi-telephone-fill"></i>
                        <?= htmlspecialchars(setting('company_phone', '+250 784 266 545')) ?>
                    </span>
                </div>

            </div><!-- /category-nav-inner -->
        </div><!-- /container -->
    </nav>

</div><!-- /site-header-wrapper -->

<!-- ════════════════════════════════════════════════════
     MOBILE HEADER
     ════════════════════════════════════════════════════ -->
<div class="mobile-header" id="mobileHeader">
    <div class="container-wide">
        <div class="mobile-header-inner">

            <!-- Hamburger -->
            <button class="mobile-hamburger" id="mobileMenuToggle" aria-label="Toggle menu" type="button">
                <i class="bi bi-list"></i>
            </button>

            <!-- Logo -->
            <div class="mobile-logo">
                <a href="<?= SITE_URL ?>">
                    <span class="logo-icon-sm">
                        <?php if ($navLogoIcon): ?>
                            <img src="<?= SITE_URL ?>uploads/settings/<?= htmlspecialchars($navLogoIcon) ?>"
                                 alt="<?= SITE_NAME ?>" style="width:36px;height:36px;object-fit:cover;border-radius:6px;">
                        <?php else: ?>
                            <i class="bi bi-shop"></i>
                        <?php endif; ?>
                    </span>
                    <span class="logo-title-sm"><?= SITE_NAME ?></span>
                </a>
            </div>

            <!-- Mobile Actions -->
            <div class="mobile-actions">

                <!-- Search toggle -->
                <button class="mobile-action-btn" id="mobileSearchToggle" aria-label="<?= lang('search') ?>" type="button">
                    <i class="bi bi-search"></i>
                </button>

                <!-- Compare -->
                <a href="<?= SITE_URL ?>pages/compare/index.php"
                   class="mobile-action-btn"
                   aria-label="<?= lang('compare') ?>">
                    <i class="bi bi-arrow-left-right"></i>
                    <span class="action-badge" id="mobile-compare-count"><?= $navCompareCount ?></span>
                </a>

                <!-- Wishlist -->
                <a href="<?= SITE_URL ?>pages/wishlist/index.php"
                   class="mobile-action-btn"
                   aria-label="<?= lang('wishlist') ?>">
                    <i class="bi bi-heart"></i>
                    <span class="action-badge" id="mobile-wishlist-count"><?= $navWishlistCount ?></span>
                </a>

                <!-- Cart -->
                <a href="<?= SITE_URL ?>pages/cart/index.php"
                   class="mobile-action-btn"
                   aria-label="<?= lang('cart') ?>">
                    <i class="bi bi-cart3"></i>
                    <span class="action-badge" id="mobile-cart-count"><?= $navCartCount ?></span>
                </a>
            </div>

        </div><!-- /mobile-header-inner -->
    </div><!-- /container -->
</div>

<!-- ─── MOBILE SEARCH OVERLAY ─────────────────────── -->
<div class="mobile-search-overlay" id="mobileSearchOverlay">
    <div class="search-wrapper">
        <i class="bi bi-search search-icon" style="position:absolute;left:28px;top:50%;transform:translateY(-50%);color:var(--color-gray-400);z-index:2;pointer-events:none;"></i>
        <form action="<?= SITE_URL ?>pages/shop.php" method="GET" style="flex:1;position:relative;">
            <input type="text" class="form-control" name="search"
                   placeholder="<?= lang('search_products') ?>"
                   autocomplete="off" aria-label="<?= lang('search') ?>">
        </form>
        <button class="search-close" id="mobileSearchClose" aria-label="<?= lang('close') ?>" type="button">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
</div>

<!-- ─── MOBILE SLIDE MENU OVERLAY ────────────────── -->
<div class="mobile-slide-overlay" id="mobileSlideOverlay"></div>

<!-- ─── MOBILE SLIDE MENU ─────────────────────────── -->
<div class="mobile-slide-menu" id="mobileSlideMenu">
    <div class="mobile-slide-header">
        <span><i class="bi bi-shop me-1"></i> <?= SITE_NAME ?></span>
        <button class="mobile-slide-close" id="mobileMenuClose" aria-label="<?= lang('close') ?>" type="button">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="mobile-slide-body">

        <!-- User section -->
        <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
            <div class="slide-section-title"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></div>
            <a href="<?= SITE_URL ?>pages/dashboard/index.php" class="slide-link">
                <i class="bi bi-person"></i> <?= lang('my_account') ?>
            </a>
                    <a href="<?= SITE_URL ?>pages/dashboard/orders.php" class="slide-link">
                                <i class="bi bi-box"></i> <?= lang('my_orders') ?>
                            </a>
                    <a href="<?= SITE_URL ?>pages/dashboard/profile.php" class="slide-link">
                                <i class="bi bi-gear"></i> <?= lang('settings') ?>
                            </a>
            <?php if (function_exists('isAdmin') && isAdmin()): ?>
                <a href="<?= SITE_URL ?>admin/dashboard.php" class="slide-link">
                    <i class="bi bi-speedometer2"></i> <?= lang('admin_panel') ?>
                </a>
            <?php endif; ?>
            <a href="<?= SITE_URL ?>pages/logout.php" class="slide-link">
                <i class="bi bi-box-arrow-right"></i> <?= lang('logout') ?>
            </a>
        <?php else: ?>
            <div class="slide-section-title"><?= lang('my_account') ?></div>
            <a href="<?= SITE_URL ?>pages/login.php" class="slide-link">
                <i class="bi bi-box-arrow-in-right"></i> <?= lang('login') ?>
            </a>
            <a href="<?= SITE_URL ?>pages/register.php" class="slide-link">
                <i class="bi bi-person-plus"></i> <?= lang('register') ?>
            </a>
        <?php endif; ?>

        <div class="slide-divider"></div>
        <div class="slide-section-title"><?= lang('shop') ?></div>

        <a href="<?= SITE_URL ?>" class="slide-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">
            <i class="bi bi-house-door"></i> <?= lang('home') ?>
        </a>
        <a href="<?= SITE_URL ?>pages/shop.php?group=liquor" class="slide-link">
            <i class="bi bi-cup-straw"></i> <?= lang('liquor_shop') ?>
        </a>
        <a href="<?= SITE_URL ?>pages/shop.php?group=mini_market" class="slide-link">
            <i class="bi bi-basket"></i> <?= lang('mini_market') ?>
        </a>
        <a href="<?= SITE_URL ?>pages/flash-sale/index.php" class="slide-link">
            <i class="bi bi-lightning"></i> <?= lang('flash_sales') ?>
        </a>
        <a href="<?= SITE_URL ?>pages/brands/index.php" class="slide-link">
            <i class="bi bi-tag"></i> <?= lang('brands') ?>
        </a>
        <a href="<?= SITE_URL ?>pages/offers/index.php" class="slide-link">
            <i class="bi bi-percent"></i> <?= lang('offers') ?>
        </a>
        <a href="<?= SITE_URL ?>pages/blog/index.php" class="slide-link">
            <i class="bi bi-pencil-square"></i> <?= lang('blog') ?>
        </a>
        <a href="<?= SITE_URL ?>pages/compare/index.php" class="slide-link">
            <i class="bi bi-arrow-left-right"></i> <?= lang('compare') ?>
        </a>
        <a href="<?= SITE_URL ?>pages/wishlist/index.php" class="slide-link">
            <i class="bi bi-heart"></i> <?= lang('wishlist') ?>
        </a>

        <div class="slide-divider"></div>
        <div class="slide-section-title"><?= lang('pages') ?? 'Pages' ?></div>

        <a href="<?= SITE_URL ?>pages/about.php" class="slide-link">
            <i class="bi bi-info-circle"></i> <?= lang('about') ?>
        </a>
        <a href="<?= SITE_URL ?>pages/contact.php" class="slide-link">
            <i class="bi bi-envelope"></i> <?= lang('contact') ?>
        </a>

        <div class="slide-divider"></div>
        <div class="slide-section-title"><?= lang('language') ?></div>
        <?php foreach ($activeLangs as $code => $info): ?>
            <a href="<?= htmlspecialchars($langUrlBase) ?>lang=<?= htmlspecialchars($code) ?>"
               class="slide-link <?= $code === $currentLang ? 'active' : '' ?>">
                <?= htmlspecialchars($info['flag']) ?> <?= htmlspecialchars($info['name']) ?>
            </a>
        <?php endforeach; ?>

    </div><!-- /mobile-slide-body -->
</div>

<!-- ─── MOBILE BOTTOM NAVIGATION ──────────────────── -->
<div class="mobile-bottom-nav">
    <div class="mobile-bottom-nav-inner">
        <a href="<?= SITE_URL ?>" class="bot-nav-item <?= $currentPage === 'index.php' ? 'active' : '' ?>">
            <i class="bi bi-house-door"></i>
            <?= lang('home') ?>
        </a>
        <a href="<?= SITE_URL ?>pages/shop.php" class="bot-nav-item <?= strpos($_SERVER['SCRIPT_NAME'], '/shop.php') !== false ? 'active' : '' ?>">
            <i class="bi bi-grid"></i>
            <?= lang('shop') ?>
        </a>
        <a href="<?= SITE_URL ?>pages/compare/index.php" class="bot-nav-item">
            <span class="bot-badge">
                <i class="bi bi-arrow-left-right"></i>
                <span class="bot-badge-count" id="bot-compare-count"><?= $navCompareCount ?></span>
            </span>
            <?= lang('compare') ?>
        </a>
        <a href="<?= SITE_URL ?>pages/wishlist/index.php" class="bot-nav-item">
            <span class="bot-badge">
                <i class="bi bi-heart"></i>
                <span class="bot-badge-count" id="bot-wishlist-count"><?= $navWishlistCount ?></span>
            </span>
            <?= lang('wishlist') ?>
        </a>
        <a href="<?= SITE_URL ?>pages/cart/index.php" class="bot-nav-item">
            <span class="bot-badge">
                <i class="bi bi-cart3"></i>
                <span class="bot-badge-count" id="bot-cart-count"><?= $navCartCount ?></span>
            </span>
            <?= lang('cart') ?>
        </a>
        <button class="bot-nav-item" id="mobileAccountBtn" type="button"
                onclick="<?= function_exists('isLoggedIn') && isLoggedIn() ? "window.location.href='" . SITE_URL . "pages/dashboard/index.php'" : "window.location.href='" . SITE_URL . "pages/login.php'" ?>">
            <i class="bi bi-person"></i>
            <?= lang('my_account') ?>
        </button>
    </div>
</div>
