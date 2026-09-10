<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = 'Dashboard';

$pdo = getDbConnection();

$totalProducts   = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$totalCategories = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$totalBrands     = (int) $pdo->query('SELECT COUNT(*) FROM brands')->fetchColumn();
$totalUsers      = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalOrders     = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$pendingOrders   = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$processingOrders= (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn();
$shippedOrders   = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'shipped'")->fetchColumn();
$deliveredOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered'")->fetchColumn();
$cancelledOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'")->fetchColumn();

// ─── Delivery tracking stats ──────────────────────────────────────────
$deliveryPending       = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE delivery_status = 'pending' AND status NOT IN ('cancelled','delivered')")->fetchColumn();
$deliveryPacked        = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE delivery_status = 'packed'")->fetchColumn();
$deliveryOutForDelivery= (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE delivery_status = 'out_for_delivery'")->fetchColumn();
$deliveryDelivered     = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE delivery_status = 'delivered'")->fetchColumn();

require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../includes/admin-navbar.php';
require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<div class="admin-content-wrapper">

    <main class="admin-content">

        <div class="welcome-section">
            <h1>Welcome to <?= SITE_NAME ?> Admin Panel</h1>
            <p>Manage your products, categories, brands, and users from one place.</p>
        </div>

        <section class="stats-cards">

            <div class="stat-card stat-card--products">
                <div class="stat-card-icon"><i class="bi bi-box-seam"></i></div>
                <div class="stat-card-body">
                    <span class="stat-card-number"><?= $totalProducts ?></span>
                    <span class="stat-card-label">Products</span>
                </div>
            </div>

            <div class="stat-card stat-card--categories">
                <div class="stat-card-icon"><i class="bi bi-grid-fill"></i></div>
                <div class="stat-card-body">
                    <span class="stat-card-number"><?= $totalCategories ?></span>
                    <span class="stat-card-label">Categories</span>
                </div>
            </div>

            <div class="stat-card stat-card--brands">
                <div class="stat-card-icon"><i class="bi bi-tag"></i></div>
                <div class="stat-card-body">
                    <span class="stat-card-number"><?= $totalBrands ?></span>
                    <span class="stat-card-label">Brands</span>
                </div>
            </div>

            <div class="stat-card stat-card--users">
                <div class="stat-card-icon"><i class="bi bi-people"></i></div>
                <div class="stat-card-body">
                    <span class="stat-card-number"><?= $totalUsers ?></span>
                    <span class="stat-card-label">Users</span>
                </div>
            </div>

            <div class="stat-card stat-card--orders-total">
                <div class="stat-card-icon"><i class="bi bi-bag-check"></i></div>
                <div class="stat-card-body">
                    <span class="stat-card-number"><?= $totalOrders ?></span>
                    <span class="stat-card-label">Total Orders</span>
                </div>
            </div>

            <div class="stat-card stat-card--pending">
                <div class="stat-card-icon"><i class="bi bi-clock"></i></div>
                <div class="stat-card-body">
                    <span class="stat-card-number"><?= $pendingOrders ?></span>
                    <span class="stat-card-label">Pending</span>
                </div>
            </div>

            <div class="stat-card stat-card--processing">
                <div class="stat-card-icon"><i class="bi bi-arrow-repeat"></i></div>
                <div class="stat-card-body">
                    <span class="stat-card-number"><?= $processingOrders ?></span>
                    <span class="stat-card-label">Processing</span>
                </div>
            </div>

            <div class="stat-card stat-card--shipped">
                <div class="stat-card-icon"><i class="bi bi-truck"></i></div>
                <div class="stat-card-body">
                    <span class="stat-card-number"><?= $shippedOrders ?></span>
                    <span class="stat-card-label">Shipped</span>
                </div>
            </div>

            <div class="stat-card stat-card--delivered">
                <div class="stat-card-icon"><i class="bi bi-check2-circle"></i></div>
                <div class="stat-card-body">
                    <span class="stat-card-number"><?= $deliveredOrders ?></span>
                    <span class="stat-card-label">Delivered</span>
                </div>
            </div>

            <div class="stat-card stat-card--cancelled">
                <div class="stat-card-icon"><i class="bi bi-x-circle"></i></div>
                <div class="stat-card-body">
                    <span class="stat-card-number"><?= $cancelledOrders ?></span>
                    <span class="stat-card-label">Cancelled</span>
                </div>
            </div>

            <!-- Delivery Stats -->
            <div class="stat-card stat-card--delivery-pending">
                <div class="stat-card-icon"><i class="bi bi-clock-history"></i></div>
                <div class="stat-card-body">
                    <span class="stat-card-number"><?= $deliveryPending ?></span>
                    <span class="stat-card-label">Pending Deliveries</span>
                </div>
            </div>

            <div class="stat-card stat-card--delivery-packed">
                <div class="stat-card-icon"><i class="bi bi-box-seam"></i></div>
                <div class="stat-card-body">
                    <span class="stat-card-number"><?= $deliveryPacked ?></span>
                    <span class="stat-card-label">Packed Orders</span>
                </div>
            </div>

            <div class="stat-card stat-card--delivery-out">
                <div class="stat-card-icon"><i class="bi bi-truck-flatbed"></i></div>
                <div class="stat-card-body">
                    <span class="stat-card-number"><?= $deliveryOutForDelivery ?></span>
                    <span class="stat-card-label">Out For Delivery</span>
                </div>
            </div>

            <div class="stat-card stat-card--delivery-delivered">
                <div class="stat-card-icon"><i class="bi bi-check2-all"></i></div>
                <div class="stat-card-body">
                    <span class="stat-card-number"><?= $deliveryDelivered ?></span>
                    <span class="stat-card-label">Delivered Orders</span>
                </div>
            </div>

        </section>

        <section class="quick-actions">
            <h2>Quick Actions</h2>
            <div class="quick-actions-grid">

                <a href="<?= SITE_URL ?>admin/categories/index.php" class="btn btn-action btn-action--categories">
                    <i class="bi bi-plus-lg"></i> Add Category
                </a>

                <a href="<?= SITE_URL ?>admin/brands/index.php" class="btn btn-action btn-action--brands">
                    <i class="bi bi-plus-lg"></i> Add Brand
                </a>

                <a href="<?= SITE_URL ?>admin/products/create.php" class="btn btn-action btn-action--products">
                    <i class="bi bi-plus-lg"></i> Add Product
                </a>

                <a href="<?= SITE_URL ?>admin/users/index.php" class="btn btn-action btn-action--users">
                    <i class="bi bi-people"></i> Manage Users
                </a>

                <a href="<?= SITE_URL ?>admin/orders/index.php" class="btn btn-action btn-action--orders">
                    <i class="bi bi-bag-check"></i> View Orders
                </a>

            </div>
        </section>

    </main>

</div>

<?php
require_once __DIR__ . '/../includes/admin-footer.php';
