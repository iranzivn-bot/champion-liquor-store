<?php
/**
 * Admin Sidebar Navigation
 *
 * Fixed sidebar with navigation links and icons.
 * Highlights the current active page.
 * Each menu item is wrapped in a permission check.
 */
declare(strict_types=1);

// Determine the current page filename for active highlighting
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>

<aside class="admin-sidebar" id="adminSidebar">

    <!-- Mobile close button -->
    <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar"><i class="bi bi-x"></i></button>

    <!-- Sidebar Navigation -->
    <nav>
        <ul class="sidebar-menu">

            <!-- Dashboard -->
            <?php if (hasPermission('dashboard.view')): ?>
            <li class="sidebar-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                <a href="<?= SITE_URL ?>admin/dashboard.php">
                    <span class="sidebar-icon"><i class="bi bi-speedometer2"></i></span>
                    <span class="sidebar-label"><?= lang('dashboard') ?></span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Analytics -->
            <?php if (hasPermission('analytics.view')): ?>
            <li class="sidebar-item <?= strpos($_SERVER['SCRIPT_NAME'], '/analytics/') !== false ? 'active' : '' ?>">
                <a href="<?= SITE_URL ?>admin/analytics/dashboard.php">
                    <span class="sidebar-icon"><i class="bi bi-graph-up-arrow"></i></span>
                    <span class="sidebar-label"><?= lang('analytics') ?></span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Categories -->
            <?php if (hasPermission('categories.view')): ?>
            <li class="sidebar-item <?= $currentPage === 'index.php' && strpos($_SERVER['SCRIPT_NAME'], '/categories/') !== false ? 'active' : '' ?>">
                <a href="<?= SITE_URL ?>admin/categories/index.php">
                    <span class="sidebar-icon"><i class="bi bi-grid-fill"></i></span>
                    <span class="sidebar-label"><?= lang('categories') ?></span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Brands -->
            <?php if (hasPermission('brands.view')): ?>
            <li class="sidebar-item <?= $currentPage === 'index.php' && strpos($_SERVER['SCRIPT_NAME'], '/brands/') !== false ? 'active' : '' ?>">
                <a href="<?= SITE_URL ?>admin/brands/index.php">
                    <span class="sidebar-icon"><i class="bi bi-tag"></i></span>
                    <span class="sidebar-label"><?= lang('brands') ?></span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Point of Sale -->
            <?php if (hasPermission('pos.access')): ?>
            <li class="sidebar-item <?= strpos($_SERVER['SCRIPT_NAME'], '/pos/') !== false ? 'active' : '' ?>">
                <a href="<?= SITE_URL ?>admin/pos/index.php">
                    <span class="sidebar-icon"><i class="bi bi-cart3"></i></span>
                    <span class="sidebar-label"><?= lang('pos') ?></span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Products -->
            <?php if (hasPermission('products.view')): ?>
            <li class="sidebar-item <?= $currentPage === 'index.php' && strpos($_SERVER['SCRIPT_NAME'], '/products/') !== false ? 'active' : '' ?>">
                <a href="<?= SITE_URL ?>admin/products/index.php">
                    <span class="sidebar-icon"><i class="bi bi-box-seam"></i></span>
                    <span class="sidebar-label"><?= lang('products') ?></span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Reviews -->
            <?php if (hasPermission('reviews.manage')): ?>
            <li class="sidebar-item <?= strpos($_SERVER['SCRIPT_NAME'], '/reviews/') !== false ? 'active' : '' ?>">
                <a href="<?= SITE_URL ?>admin/reviews/">
                    <span class="sidebar-icon"><i class="bi bi-star"></i></span>
                    <span class="sidebar-label"><?= lang('reviews') ?></span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Orders -->
            <?php if (hasPermission('orders.view')): ?>
            <li class="sidebar-item <?= $currentPage === 'index.php' && strpos($_SERVER['SCRIPT_NAME'], '/orders/') !== false ? 'active' : '' ?>">
                <a href="<?= SITE_URL ?>admin/orders/index.php">
                    <span class="sidebar-icon"><i class="bi bi-bag-check"></i></span>
                    <span class="sidebar-label"><?= lang('orders') ?></span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Users -->
            <?php if (hasPermission('users.view')): ?>
            <li class="sidebar-item <?= $currentPage === 'index.php' && strpos($_SERVER['SCRIPT_NAME'], '/users/') !== false ? 'active' : '' ?>">
                <a href="<?= SITE_URL ?>admin/users/index.php">
                    <span class="sidebar-icon"><i class="bi bi-people"></i></span>
                    <span class="sidebar-label"><?= lang('users') ?></span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Reports -->
            <?php if (hasPermission('reports.view')): ?>
            <li class="sidebar-item <?= strpos($_SERVER['SCRIPT_NAME'], '/reports/') !== false ? 'active' : '' ?>">
                <a href="<?= SITE_URL ?>admin/reports/index.php">
                    <span class="sidebar-icon"><i class="bi bi-graph-up-arrow"></i></span>
                    <span class="sidebar-label"><?= lang('reports') ?></span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Blog -->
            <?php if (hasPermission('blog.view')): ?>
            <li class="sidebar-item <?= strpos($_SERVER['SCRIPT_NAME'], '/blog/') !== false ? 'active' : '' ?>">
                <a href="<?= SITE_URL ?>admin/blog/index.php">
                    <span class="sidebar-icon"><i class="bi bi-pencil-square"></i></span>
                    <span class="sidebar-label"><?= lang('blog') ?></span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Collections -->
            <?php if (hasPermission('collections.view')): ?>
            <li class="sidebar-item <?= strpos($_SERVER['SCRIPT_NAME'], '/collections/') !== false ? 'active' : '' ?>">
                <a href="<?= SITE_URL ?>admin/collections/index.php">
                    <span class="sidebar-icon"><i class="bi bi-collection"></i></span>
                    <span class="sidebar-label"><?= lang('collections') ?></span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Inventory -->
            <?php if (hasPermission('inventory.view')): ?>
            <li class="sidebar-item <?= strpos($_SERVER['SCRIPT_NAME'], '/inventory/') !== false ? 'active' : '' ?>">
                <a href="<?= SITE_URL ?>admin/inventory/index.php">
                    <span class="sidebar-icon"><i class="bi bi-boxes"></i></span>
                    <span class="sidebar-label"><?= lang('inventory') ?></span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Audit Logs -->
            <?php if (hasPermission('audit.view')): ?>
            <li class="sidebar-item <?= strpos($_SERVER['SCRIPT_NAME'], '/audit/') !== false ? 'active' : '' ?>">
                <a href="<?= SITE_URL ?>admin/audit/index.php">
                    <span class="sidebar-icon"><i class="bi bi-journal-text"></i></span>
                    <span class="sidebar-label"><?= lang('audit_logs') ?></span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Settings -->
            <?php if (hasPermission('settings.manage')): ?>
            <li class="sidebar-item <?= strpos($_SERVER['SCRIPT_NAME'], '/settings/') !== false ? 'active' : '' ?>">
                <a href="<?= SITE_URL ?>admin/settings/index.php">
                    <span class="sidebar-icon"><i class="bi bi-gear"></i></span>
                    <span class="sidebar-label"><?= lang('settings') ?></span>
                </a>
            </li>
            <?php endif; ?>

            <li class="sidebar-divider"></li>

            <!-- Logout -->
            <li class="sidebar-item">
                <a href="<?= SITE_URL ?>pages/logout.php">
                    <span class="sidebar-icon"><i class="bi bi-box-arrow-right"></i></span>
                    <span class="sidebar-label"><?= lang('logout') ?></span>
                </a>
            </li>

        </ul>
    </nav>
</aside>

<!-- Overlay for mobile sidebar -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
