<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDbConnection();

$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$brand    = trim($_GET['brand'] ?? '');
$sort     = trim($_GET['sort'] ?? 'latest');
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 12;

$joins  = 'FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN brands b ON p.brand_id = b.id';
$where  = 'WHERE p.status = \'active\'';
$params = [];

// Search by product name only
if ($search !== '') {
    $where .= ' AND p.name LIKE :search';
    $params[':search'] = "%{$search}%";
}

// Category filter
if ($category !== '') {
    $where .= ' AND c.slug = :category';
    $params[':category'] = $category;
}

// Group filter (liquor / mini_market)
$groupFilter = trim($_GET['group'] ?? '');
if ($groupFilter !== '' && in_array($groupFilter, ['liquor', 'mini_market'], true)) {
    $where .= ' AND c.`group` = :grp';
    $params[':grp'] = $groupFilter;
}

// Brand filter
if ($brand !== '') {
    $where .= ' AND b.slug = :brand';
    $params[':brand'] = $brand;
}

// Sort order
$orderBy = 'p.created_at DESC';
if ($sort === 'price_asc') {
    $orderBy = 'p.price ASC';
} elseif ($sort === 'price_desc') {
    $orderBy = 'p.price DESC';
} elseif ($sort === 'name_asc') {
    $orderBy = 'p.name ASC';
} elseif ($sort === 'name_desc') {
    $orderBy = 'p.name DESC';
}

// Count total
$countSql  = "SELECT COUNT(*) {$joins} {$where}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total      = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;

// Fetch products
$dataSql  = "SELECT p.*, c.name AS category_name, c.code AS category_code, c.slug AS category_slug, b.name AS brand_name {$joins} {$where} ORDER BY {$orderBy} LIMIT :limit OFFSET :offset";
$dataStmt = $pdo->prepare($dataSql);
foreach ($params as $key => $val) { $dataStmt->bindValue($key, $val); }
$dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();
$products = $dataStmt->fetchAll();

// Fetch all categories and brands for dropdowns
$allCategories = $pdo->query('SELECT slug, name FROM categories WHERE status = \'active\' ORDER BY name ASC')->fetchAll();
$allBrands     = $pdo->query('SELECT slug, name FROM brands WHERE status = \'active\' ORDER BY name ASC')->fetchAll();

// Fetch wishlist product IDs (logged-in or guest)
$wishlistIds = [];
$wlUserId = (function_exists('isLoggedIn') && isLoggedIn()) ? (int) $_SESSION['user_id'] : null;
$wlSessionId = $_SESSION['wishlist_session_id'] ?? null;
if ($wlUserId) {
    $wlStmt = $pdo->prepare('SELECT product_id FROM wishlist WHERE user_id = :uid');
    $wlStmt->execute([':uid' => $wlUserId]);
    $wishlistIds = array_column($wlStmt->fetchAll(), 'product_id');
} elseif ($wlSessionId) {
    $wlStmt = $pdo->prepare('SELECT product_id FROM wishlist WHERE session_id = :sid');
    $wlStmt->execute([':sid' => $wlSessionId]);
    $wishlistIds = array_column($wlStmt->fetchAll(), 'product_id');
}

// Fetch user's compare product IDs (logged-in or guest)
$compareIds = [];
$compareUserId = (function_exists('isLoggedIn') && isLoggedIn()) ? (int) $_SESSION['user_id'] : null;
$compareSessionId = $_SESSION['compare_session_id'] ?? null;
if ($compareUserId) {
    $cmpStmt = $pdo->prepare('SELECT product_id FROM compare_list WHERE user_id = :uid');
    $cmpStmt->execute([':uid' => $compareUserId]);
    $compareIds = array_column($cmpStmt->fetchAll(), 'product_id');
} elseif ($compareSessionId) {
    $cmpStmt = $pdo->prepare('SELECT product_id FROM compare_list WHERE session_id = :sid');
    $cmpStmt->execute([':sid' => $compareSessionId]);
    $compareIds = array_column($cmpStmt->fetchAll(), 'product_id');
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-hero-sm position-relative overflow-hidden">
    <div class="container position-relative z-1">
        <h1 class="fw-bold mb-1">Shop</h1>
        <p class="text-white-50 mb-0">Browse our complete product catalog</p>
    </div>
</div>

<section class="section-padding">
    <div class="container">

        <!-- Search, Filter, Sort Bar -->
        <form method="GET" class="shop-toolbar rounded-3 shadow-sm p-4 bg-white mb-4">
            <?php if ($groupFilter !== ''): ?>
                <input type="hidden" name="group" value="<?= htmlspecialchars($groupFilter) ?>">
            <?php endif; ?>
            <div class="row g-3 align-items-end">

                <!-- Search -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1">Search Products</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search by product name..."
                               value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-gold" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>

                <!-- Category Dropdown -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1"><i class="bi bi-grid me-1"></i>Category</label>
                    <select name="category" class="form-select" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($allCategories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['slug']) ?>" <?= $category === $cat['slug'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Brand Dropdown -->
                <div class="col-md-2">
                    <label class="form-label small fw-bold mb-1"><i class="bi bi-tag me-1"></i>Brand</label>
                    <select name="brand" class="form-select" onchange="this.form.submit()">
                        <option value="">All Brands</option>
                        <?php foreach ($allBrands as $b): ?>
                            <option value="<?= htmlspecialchars($b['slug']) ?>" <?= $brand === $b['slug'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Sort -->
                <div class="col-md-2">
                    <label class="form-label small fw-bold mb-1"><i class="bi bi-sort-down me-1"></i>Sort By</label>
                    <select name="sort" class="form-select" onchange="this.form.submit()">
                        <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Latest Products</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price Low to High</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price High to Low</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A-Z</option>
                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z-A</option>
                    </select>
                </div>

                <!-- Clear -->
                <div class="col-md-2 d-flex align-items-end">
                    <?php if ($search || $category || $brand || $sort !== 'latest'): ?>
                        <a href="<?= SITE_URL ?>pages/shop.php" class="btn btn-outline-secondary w-100"><i class="bi bi-x-circle me-1"></i>Clear</a>
                    <?php endif; ?>
                </div>

            </div>
        </form>

        <!-- Results count -->
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
            <p class="small text-muted mb-0">
                Showing <strong><?= count($products) ?></strong> of <strong><?= $total ?></strong> product<?= $total !== 1 ? 's' : '' ?>
                <?php if ($search): ?>for "<strong><?= htmlspecialchars($search) ?></strong>"<?php endif; ?>
            </p>
            <?php if ($brand): ?>
                <span class="badge bg-gold fs-6" style="font-size: 0.85rem !important;">
                    Brand: <?= htmlspecialchars($brand) ?> <a href="<?= SITE_URL ?>pages/shop.php" class="text-white text-decoration-none ms-1">&times;</a>
                </span>
            <?php endif; ?>
        </div>

        <!-- Product Grid -->
        <div class="row g-4">
            <?php if (count($products) === 0): ?>
                <div class="col-12 text-center py-5">
                    <i class="bi bi-box-seam" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="text-muted mt-3 mb-0">No products found.</p>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product):
                    $inWishlist = in_array((int) $product['id'], $wishlistIds, true);
                    $inCompare  = in_array((int) $product['id'], $compareIds, true);
                ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="featured-product-card">
                            <button class="btn compare-toggle position-absolute top-0 start-0 m-2 p-1 border-0 <?= $inCompare ? 'compare-active' : '' ?>"
                                    style="z-index: 3; line-height: 1;"
                                    data-product-id="<?= (int) $product['id'] ?>"
                                    data-in-compare="<?= $inCompare ? '1' : '0' ?>"
                                    title="<?= $inCompare ? lang('remove_from_compare') : lang('add_to_compare') ?>">
                                <i class="bi <?= $inCompare ? 'bi-arrow-left-right' : 'bi-arrow-left-right' ?>"></i>
                            </button>
                            <button class="btn wishlist-toggle position-absolute top-0 end-0 m-2 p-1 border-0 <?= $inWishlist ? 'wishlist-active' : '' ?>"
                                    style="z-index: 3; line-height: 1;"
                                    data-product-id="<?= (int) $product['id'] ?>"
                                    data-in-wishlist="<?= $inWishlist ? '1' : '0' ?>"
                                    title="<?= $inWishlist ? 'Remove from wishlist' : 'Add to wishlist' ?>">
                                <i class="bi <?= $inWishlist ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                            </button>
                            <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($product['code']) ?>" class="featured-product-image">
                                <img src="<?= $product['image'] ? SITE_URL . 'uploads/products/' . htmlspecialchars($product['image']) : DEFAULT_PRODUCT_IMAGE ?>"
                                     alt="<?= htmlspecialchars($product['name']) ?>">
                            </a>
                            <div class="featured-product-body">
                                <div class="featured-product-category"><?= htmlspecialchars($product['category_name'] ?? '') ?></div>
                                <div class="featured-product-name">
                                    <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($product['code']) ?>"><?= htmlspecialchars($product['name']) ?></a>
                                </div>
                                <div class="featured-product-pricing">
                                    <span class="featured-product-price"><?= number_format((float) $product['price'], 2) ?> RWF</span>
                                    <?php if ($product['discount_price'] !== null): ?>
                                        <span class="featured-product-old"><?= number_format((float) $product['discount_price'], 2) ?> RWF</span>
                                    <?php endif; ?>
                                </div>
                                <div class="featured-product-meta d-flex align-items-center justify-content-between">
                                    <?php if ((int) $product['stock_quantity'] > 0): ?>
                                        <span class="featured-product-stock in-stock"><i class="bi bi-check-circle-fill"></i> In Stock</span>
                                    <?php else: ?>
                                        <span class="featured-product-stock out-of-stock"><i class="bi bi-x-circle-fill"></i> Out of Stock</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ((int) $product['stock_quantity'] > 0): ?>
                                    <button class="featured-product-btn mt-auto add-to-cart-btn"
                                            data-product-id="<?= (int) $product['id'] ?>"
                                            data-product-name="<?= htmlspecialchars($product['name']) ?>"
                                            title="Add to cart">
                                        <i class="bi bi-cart-plus"></i> Add to Cart
                                    </button>
                                <?php else: ?>
                                    <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($product['code']) ?>"
                                       class="featured-product-btn mt-auto" style="background: var(--color-primary);">
                                        <i class="bi bi-eye"></i> View Details
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-5">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
