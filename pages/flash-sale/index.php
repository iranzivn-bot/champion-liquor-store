<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDbConnection();

$flashSaleEnd = setting('flash_sale_end', '');
$hasFlashSale = $flashSaleEnd !== '' && strtotime($flashSaleEnd) > time();

$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$sort     = trim($_GET['sort'] ?? 'savings_desc');
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 12;

$joins  = 'FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN brands b ON p.brand_id = b.id';
$where  = "WHERE p.status = 'active' AND p.discount_price IS NOT NULL AND p.discount_price > p.price";
$params = [];

if ($search !== '') {
    $where .= ' AND p.name LIKE :search';
    $params[':search'] = "%{$search}%";
}

if ($category !== '') {
    $where .= ' AND c.slug = :category';
    $params[':category'] = $category;
}

$orderBy = '(p.discount_price - p.price) DESC';
if ($sort === 'savings_asc') {
    $orderBy = '(p.discount_price - p.price) ASC';
} elseif ($sort === 'price_asc') {
    $orderBy = 'p.price ASC';
} elseif ($sort === 'price_desc') {
    $orderBy = 'p.price DESC';
} elseif ($sort === 'name_asc') {
    $orderBy = 'p.name ASC';
} elseif ($sort === 'name_desc') {
    $orderBy = 'p.name DESC';
} elseif ($sort === 'newest') {
    $orderBy = 'p.created_at DESC';
}

$countSql  = "SELECT COUNT(*) {$joins} {$where}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total      = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;

$dataSql  = "SELECT p.*, c.name AS category_name, c.code AS category_code, c.slug AS category_slug, b.name AS brand_name, ROUND((1 - p.price / p.discount_price) * 100) AS savings_percent, (p.discount_price - p.price) AS savings_amount {$joins} {$where} ORDER BY {$orderBy} LIMIT :limit OFFSET :offset";
$dataStmt = $pdo->prepare($dataSql);
foreach ($params as $key => $val) { $dataStmt->bindValue($key, $val); }
$dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();
$products = $dataStmt->fetchAll();

$allCategories = $pdo->query('SELECT slug, name FROM categories WHERE status = \'active\' ORDER BY name ASC')->fetchAll();

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

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="flash-sale-hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <span class="flash-sale-badge"><i class="bi bi-lightning-fill me-1"></i> <?= lang('flash_sales') ?></span>
                <h1 class="fw-bold mb-2"><?= lang('flash_sales') ?></h1>
                <p class="text-white-50 mb-0 fs-5">Limited-time deals at unbeatable prices</p>
            </div>
            <div class="col-lg-5 text-lg-end mt-4 mt-lg-0">
                <?php if ($hasFlashSale): ?>
                    <div class="flash-countdown" data-end="<?= htmlspecialchars($flashSaleEnd) ?>">
                        <div class="flash-countdown-label">Sale Ends In</div>
                        <div class="flash-countdown-display">
                            <div class="flash-countdown-block">
                                <span class="flash-countdown-num" id="fs-days">00</span>
                                <span class="flash-countdown-unit">Days</span>
                            </div>
                            <div class="flash-countdown-sep">:</div>
                            <div class="flash-countdown-block">
                                <span class="flash-countdown-num" id="fs-hours">00</span>
                                <span class="flash-countdown-unit">Hrs</span>
                            </div>
                            <div class="flash-countdown-sep">:</div>
                            <div class="flash-countdown-block">
                                <span class="flash-countdown-num" id="fs-mins">00</span>
                                <span class="flash-countdown-unit">Min</span>
                            </div>
                            <div class="flash-countdown-sep">:</div>
                            <div class="flash-countdown-block">
                                <span class="flash-countdown-num" id="fs-secs">00</span>
                                <span class="flash-countdown-unit">Sec</span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="flash-sale-ended">
                        <i class="bi bi-clock-history me-1"></i> No active flash sale right now
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<section class="section-padding">
    <div class="container">

        <form method="GET" class="shop-toolbar mb-4">
            <div class="row g-2 align-items-end">

                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">Search Products</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search products..."
                               value="<?= htmlspecialchars($search) ?>">
                        <button class="btn" style="background: var(--primary); color: #fff;" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-grid me-1"></i>Category</label>
                    <select name="category" class="form-select" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($allCategories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['slug']) ?>" <?= $category === $cat['slug'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-sort-down me-1"></i>Sort By</label>
                    <select name="sort" class="form-select" onchange="this.form.submit()">
                        <option value="savings_desc" <?= $sort === 'savings_desc' ? 'selected' : '' ?>>Biggest Savings</option>
                        <option value="savings_asc" <?= $sort === 'savings_asc' ? 'selected' : '' ?>>Smallest Savings</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price Low to High</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price High to Low</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A-Z</option>
                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z-A</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <?php if ($search || $category || $sort !== 'savings_desc'): ?>
                        <a href="<?= SITE_URL ?>pages/flash-sale/index.php" class="btn btn-outline-secondary w-100"><i class="bi bi-x-circle me-1"></i>Clear</a>
                    <?php endif; ?>
                </div>

            </div>
        </form>

        <p class="small text-muted mb-3">
            Showing <?= count($products) ?> of <?= $total ?> product<?= $total !== 1 ? 's' : '' ?>
            <?php if ($search): ?>for "<strong><?= htmlspecialchars($search) ?></strong>"<?php endif; ?>
        </p>

        <div class="row g-4">
            <?php if (count($products) === 0): ?>
                <div class="col-12 text-center py-5">
                    <i class="bi bi-lightning" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="text-muted mt-3 mb-0">No flash sale products found.</p>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product):
                    $inWishlist = in_array((int) $product['id'], $wishlistIds, true);
                    $inCompare  = in_array((int) $product['id'], $compareIds, true);
                ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="product-card position-relative">
                            <div class="position-absolute top-0 start-0 m-2" style="z-index: 3;">
                                <span class="badge bg-danger">-<?= (int) $product['savings_percent'] ?>%</span>
                            </div>
                            <?php if ($hasFlashSale): ?>
                                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 3;">
                                    <span class="badge flash-badge">FLASH</span>
                                </div>
                            <?php endif; ?>
                            <button class="btn compare-toggle <?= $inCompare ? 'compare-active' : '' ?> position-absolute top-0 start-0 m-2 p-1 border-0"
                                    style="z-index: 2; line-height: 1; background: none; color: #001F5B;"
                                    data-product-id="<?= (int) $product['id'] ?>"
                                    data-in-compare="<?= $inCompare ? '1' : '0' ?>"
                                    title="<?= $inCompare ? 'Remove from compare' : 'Add to compare' ?>">
                                <i class="bi bi-arrow-left-right"></i>
                            </button>
                            <button class="btn wishlist-toggle <?= $inWishlist ? 'wishlist-active' : '' ?> position-absolute top-0 end-0 m-2 p-1 border-0"
                                    style="z-index: 2; line-height: 1;"
                                    data-product-id="<?= (int) $product['id'] ?>"
                                    data-in-wishlist="<?= $inWishlist ? '1' : '0' ?>"
                                    title="<?= $inWishlist ? 'Remove from wishlist' : 'Add to wishlist' ?>">
                                <i class="bi <?= $inWishlist ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                            </button>
                            <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($product['code']) ?>">
                                <img src="<?= $product['image'] ? SITE_URL . 'uploads/products/' . htmlspecialchars($product['image']) : DEFAULT_PRODUCT_IMAGE ?>"
                                     class="card-img-top" alt="<?= htmlspecialchars($product['name']) ?>">
                            </a>
                            <div class="card-body">
                                <p class="card-text"><?= htmlspecialchars($product['category_name'] ?? '') ?></p>
                                <h6 class="card-title">
                                    <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($product['code']) ?>"
                                       class="text-decoration-none text-dark"><?= htmlspecialchars($product['name']) ?></a>
                                </h6>
                                <div class="mt-auto">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="product-price">
                                            <?= number_format((float) $product['price'], 2) ?> RWF
                                        </div>
                                        <?php if ((int) $product['stock_quantity'] > 0): ?>
                                            <span class="badge bg-success" style="font-size: 0.7rem;">In Stock</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger" style="font-size: 0.7rem;">Out of Stock</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted mb-2">
                                        <span class="text-decoration-line-through"><?= number_format((float) $product['discount_price'], 2) ?> RWF</span>
                                        <span class="ms-2 text-danger fw-semibold">-<?= (int) $product['savings_percent'] ?>%</span>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($product['code']) ?>"
                                           class="btn btn-sm flex-fill" style="background: var(--gold); color: #fff;">Details</a>
                                        <?php if ((int) $product['stock_quantity'] > 0): ?>
                                            <button class="btn btn-sm add-to-cart-btn"
                                                    style="background: #001F5B; color: #fff;"
                                                    data-product-id="<?= (int) $product['id'] ?>"
                                                    data-product-name="<?= htmlspecialchars($product['name']) ?>"
                                                    title="Add to cart">
                                                <i class="bi bi-cart-plus"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    var countdownEl = document.querySelector('.flash-countdown');
    if (!countdownEl) return;

    var endDate = new Date(countdownEl.getAttribute('data-end').replace(' ', 'T')).getTime();

    function updateCountdown() {
        var now = new Date().getTime();
        var diff = endDate - now;

        if (diff <= 0) {
            document.getElementById('fs-days').textContent = '00';
            document.getElementById('fs-hours').textContent = '00';
            document.getElementById('fs-mins').textContent = '00';
            document.getElementById('fs-secs').textContent = '00';
            return;
        }

        var days = Math.floor(diff / (1000 * 60 * 60 * 24));
        var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        var mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        var secs = Math.floor((diff % (1000 * 60)) / 1000);

        document.getElementById('fs-days').textContent = String(days).padStart(2, '0');
        document.getElementById('fs-hours').textContent = String(hours).padStart(2, '0');
        document.getElementById('fs-mins').textContent = String(mins).padStart(2, '0');
        document.getElementById('fs-secs').textContent = String(secs).padStart(2, '0');
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
