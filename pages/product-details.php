<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$pdo = getDbConnection();

$code = trim($_GET['code'] ?? '');
if ($code === '') {
    header('Location: ' . SITE_URL . 'pages/shop.php');
    exit;
}

// Fetch the product with category and brand
$stmt = $pdo->prepare('
    SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.code AS category_code, c.id AS category_id,
           b.name AS brand_name, b.code AS brand_code
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE p.code = :code
');
$stmt->execute([':code' => $code]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: ' . SITE_URL . 'pages/shop.php');
    exit;
}

// Fetch related products from the same category (excluding current product)
$related = [];
if ($product['category_id']) {
    $stmtRel = $pdo->prepare('
        SELECT p.*, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.category_id = :cat_id AND p.code != :code AND p.status = \'active\'
        ORDER BY p.created_at DESC
        LIMIT 4
    ');
    $stmtRel->execute([':cat_id' => $product['category_id'], ':code' => $code]);
    $related = $stmtRel->fetchAll();
}

$inStock = (int) $product['stock_quantity'] > 0;

$inWishlist = false;
$wlUserId = (function_exists('isLoggedIn') && isLoggedIn()) ? (int) $_SESSION['user_id'] : null;
$wlSessionId = $_SESSION['wishlist_session_id'] ?? null;
if ($wlUserId) {
    $wlStmt = $pdo->prepare('SELECT id FROM wishlist WHERE user_id = :uid AND product_id = :pid');
    $wlStmt->execute([':uid' => $wlUserId, ':pid' => (int) $product['id']]);
    $inWishlist = (bool) $wlStmt->fetch();
} elseif ($wlSessionId) {
    $wlStmt = $pdo->prepare('SELECT id FROM wishlist WHERE session_id = :sid AND product_id = :pid');
    $wlStmt->execute([':sid' => $wlSessionId, ':pid' => (int) $product['id']]);
    $inWishlist = (bool) $wlStmt->fetch();
}

$inCompare = false;
$compareUserId = (function_exists('isLoggedIn') && isLoggedIn()) ? (int) $_SESSION['user_id'] : null;
$compareSessionId = $_SESSION['compare_session_id'] ?? null;
if ($compareUserId) {
    $cmpStmt = $pdo->prepare('SELECT id FROM compare_list WHERE user_id = :uid AND product_id = :pid');
    $cmpStmt->execute([':uid' => $compareUserId, ':pid' => (int) $product['id']]);
    $inCompare = (bool) $cmpStmt->fetch();
} elseif ($compareSessionId) {
    $cmpStmt = $pdo->prepare('SELECT id FROM compare_list WHERE session_id = :sid AND product_id = :pid');
    $cmpStmt->execute([':sid' => $compareSessionId, ':pid' => (int) $product['id']]);
    $inCompare = (bool) $cmpStmt->fetch();
}
?>

<div class="page-hero-sm">
    <div class="container">
        <a href="<?= SITE_URL ?>pages/shop.php" class="breadcrumb-back-link">
            <i class="bi bi-arrow-left me-1"></i>Back to Shop
        </a>
        <h1 class="page-hero-title fw-bold mt-2"><?= htmlspecialchars($product['name']) ?></h1>
    </div>
</div>

<section class="section-padding">
    <div class="container">
        <div class="row g-5">
            <!-- Product Image -->
            <div class="col-lg-6">
                <div class="product-image-wrapper">
                    <?php if ($product['discount_price'] !== null): ?>
                        <span class="sale-badge">Sale</span>
                    <?php endif; ?>
                    <img src="<?= $product['image'] ? SITE_URL . 'uploads/products/' . htmlspecialchars($product['image']) : DEFAULT_PRODUCT_IMAGE ?>"
                         class="product-detail-img w-100" alt="<?= htmlspecialchars($product['name']) ?>">
                </div>
            </div>

            <!-- Product Info -->
            <div class="col-lg-6">
                <!-- Availability Badge -->
                <div class="mb-3">
                    <?php if ($inStock): ?>
                        <span class="badge bg-success fs-6 px-3 py-2">In Stock</span>
                    <?php else: ?>
                        <span class="badge bg-danger fs-6 px-3 py-2">Out of Stock</span>
                    <?php endif; ?>
                </div>

                <h2 class="product-detail-name fw-bold"><?= htmlspecialchars($product['name']) ?></h2>

                <p class="text-muted mb-2">
                    <span class="detail-label"><?= lang('code') ?>:</span> <?= htmlspecialchars($product['code']) ?>
                </p>
                <?php if ($product['barcode']): ?>
                    <p class="text-muted mb-3">
                        <span class="detail-label"><?= lang('barcode') ?>:</span>
                        <span class="barcode-display" data-barcode="<?= htmlspecialchars($product['barcode']) ?>">
                            <svg class="barcode-svg" width="180" height="40"></svg>
                        </span>
                        <span class="small text-muted ms-2"><?= htmlspecialchars($product['barcode']) ?></span>
                    </p>
                <?php endif; ?>

                <div class="product-meta d-flex flex-wrap gap-4 mb-3">
                    <div class="product-meta-item">
                        <p class="detail-label mb-1"><?= lang('category') ?></p>
                        <p class="fw-semibold mb-0"><?= htmlspecialchars($product['category_name'] ?? '—') ?></p>
                    </div>
                    <div class="product-meta-item">
                        <p class="detail-label mb-1"><?= lang('brand') ?></p>
                        <p class="fw-semibold mb-0"><?= htmlspecialchars($product['brand_name'] ?? '—') ?></p>
                    </div>
                    <div class="product-meta-item">
                        <p class="detail-label mb-1"><?= lang('stock') ?></p>
                        <p class="fw-semibold mb-0">
                            <?php if ($inStock): ?>
                                <?= (int) $product['stock_quantity'] ?> <?= lang('available') ?>
                            <?php else: ?>
                                <span class="text-danger"><?= lang('out_of_stock') ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <hr>

                <!-- Price -->
                <div class="mb-3">
                    <span class="product-price-current fs-3 fw-bold"><?= number_format((float) $product['price'], 2) ?> RWF</span>
                    <?php if ($product['discount_price'] !== null): ?>
                        <span class="product-price-original fs-5 text-muted text-decoration-line-through ms-2"><?= number_format((float) $product['discount_price'], 2) ?> RWF</span>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <?php if ($product['description']): ?>
                    <div class="mb-4">
                        <p class="detail-label mb-2"><?= lang('description') ?></p>
                        <p class="product-description mb-0"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Quantity Selector -->
                <div class="mb-4">
                    <label class="detail-label mb-2"><?= lang('quantity') ?></label>
                    <div class="qty-selector">
                        <button type="button" class="qty-btn qty-minus" aria-label="Decrease quantity">−</button>
                        <input type="number" class="qty-input" id="qtyInput" value="1" min="1" max="<?= max(1, (int) $product['stock_quantity']) ?>" readonly>
                        <button type="button" class="qty-btn qty-plus" aria-label="Increase quantity">+</button>
                    </div>
                </div>

                <!-- Wishlist, Compare & Add to Cart -->
                <div class="d-flex gap-2 mb-3">
                    <button class="btn btn-lg compare-toggle <?= $inCompare ? 'compare-active' : '' ?> flex-shrink-0"
                            data-product-id="<?= (int) $product['id'] ?>"
                            data-in-compare="<?= $inCompare ? '1' : '0' ?>"
                            title="<?= $inCompare ? lang('remove_from_compare') : lang('add_to_compare') ?>">
                        <i class="bi bi-arrow-left-right"></i>
                    </button>
                    <button class="btn btn-lg wishlist-toggle <?= $inWishlist ? 'wishlist-active' : '' ?> flex-shrink-0"
                            data-product-id="<?= (int) $product['id'] ?>"
                            data-in-wishlist="<?= $inWishlist ? '1' : '0' ?>"
                            title="<?= $inWishlist ? lang('remove_from_wishlist') : lang('add_to_wishlist') ?>">
                        <i class="bi <?= $inWishlist ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                    </button>

                    <?php if ($inStock): ?>
                        <button class="btn btn-gold btn-lg flex-fill add-to-cart-btn"
                                data-product-id="<?= (int) $product['id'] ?>"
                                data-product-name="<?= htmlspecialchars($product['name']) ?>">
                            <i class="bi bi-cart-plus"></i> <?= lang('add_to_cart') ?>
                        </button>
                    <?php else: ?>
                        <button class="btn btn-lg flex-fill btn-secondary" disabled>
                            <i class="bi bi-cart-x"></i> <?= lang('out_of_stock') ?>
                        </button>
                    <?php endif; ?>
                </div>
                <div id="add-to-cart-feedback" class="mt-2"></div>
            </div>
        </div>
    </div>
</section>

<!-- Reviews Section -->
<section class="mt-5 pt-4 border-top">
    <div class="row">
        <div class="col-lg-8">
            <h3 class="fw-bold mb-4 text-primary">Customer Reviews</h3>
            <div id="reviews-container">
                <div class="text-center py-4">
                    <div class="spinner-border text-gold" role="status"></div>
                    <p class="text-muted mt-2">Loading reviews...</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm p-4">
                <h5 class="fw-bold mb-3">Write a Review</h5>
                <form id="reviewForm" onsubmit="return false;">
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                    <?= csrfField() ?>
                    <?php if (!isLoggedIn()): ?>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Your Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Rating</label>
                        <div class="star-rating">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
                                <label for="star<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"><i class="bi bi-star-fill"></i></label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Review Title</label>
                        <input type="text" name="title" class="form-control" placeholder="Summarize your review">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Your Review</label>
                        <textarea name="text" class="form-control" rows="4" placeholder="Share your experience..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-gold w-100">Submit Review</button>
                </form>
                <div id="review-feedback" class="mt-3"></div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var productId = <?= (int) $product['id'] ?>;
    var baseUrl = window.SITE_URL || '/';

    function loadReviews() {
        fetch(baseUrl + 'ajax/reviews/load.php?product_id=' + productId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) return;
                var html = '';
                if (data.total_reviews === 0) {
                    html = '<div class="text-center py-5"><i class="bi bi-chat-dots text-muted" style="font-size: 3rem;"></i><p class="text-muted mt-2">No reviews yet. Be the first!</p></div>';
                } else {
                    html += '<div class="d-flex align-items-center gap-3 mb-4 p-3 bg-light rounded-3"><div class="fs-1 fw-bold text-gold">' + data.average_rating.toFixed(1) + '</div><div><div class="text-gold fs-5">' + '★'.repeat(Math.round(data.average_rating)) + '</div><small class="text-muted">' + data.total_reviews + ' review' + (data.total_reviews !== 1 ? 's' : '') + '</small></div></div>';
                    data.reviews.forEach(function(r) {
                        html += '<div class="review-card p-3 mb-3 border rounded-3"><div class="d-flex justify-content-between align-items-start"><div><h6 class="fw-bold mb-1">' + r.name + '</h6><div class="text-gold small mb-1">' + '★'.repeat(r.rating) + '☆'.repeat(5 - r.rating) + '</div></div><small class="text-muted">' + r.created_at + '</small></div>';
                        if (r.title) html += '<h6 class="fw-semibold mt-2 mb-1">' + r.title + '</h6>';
                        html += '<p class="mb-0 small text-muted">' + r.text + '</p></div>';
                    });
                }
                document.getElementById('reviews-container').innerHTML = html;
            });
    }

    loadReviews();

    document.getElementById('reviewForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';

        var formData = new URLSearchParams();
        var inputs = this.querySelectorAll('input, textarea');
        inputs.forEach(function(inp) { formData.append(inp.name, inp.value); });
        formData.append('_csrf_token', window.CSRF_TOKEN);

        fetch(baseUrl + 'ajax/reviews/add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
        .then(function(r) { return r.json(); })
        .then(function(result) {
            var feedback = document.getElementById('review-feedback');
            if (result.success) {
                feedback.innerHTML = '<div class="alert alert-success mb-0">' + result.message + '</div>';
                document.getElementById('reviewForm').reset();
                loadReviews();
            } else {
                feedback.innerHTML = '<div class="alert alert-danger mb-0">' + (result.message || 'Failed to submit review.') + '</div>';
            }
            btn.disabled = false;
            btn.innerHTML = 'Submit Review';
        })
        .catch(function() {
            document.getElementById('review-feedback').innerHTML = '<div class="alert alert-danger mb-0">Network error. Please try again.</div>';
            btn.disabled = false;
            btn.innerHTML = 'Submit Review';
        });
    });
});
</script>

<!-- Related Products -->
<?php if (count($related) > 0): ?>
<section class="section-padding pt-0">
    <div class="container">
        <h2 class="section-title text-center"><?= lang('related_products') ?></h2>
        <p class="section-subtitle text-center"><?= lang('you_might_also_like') ?></p>
        <div class="row g-4">
            <?php foreach ($related as $rel): ?>
                <div class="col-6 col-md-3">
                    <div class="product-card">
                        <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($rel['code']) ?>">
                            <img src="<?= $rel['image'] ? SITE_URL . 'uploads/products/' . htmlspecialchars($rel['image']) : DEFAULT_PRODUCT_IMAGE ?>"
                                 class="card-img-top" alt="<?= htmlspecialchars($rel['name']) ?>">
                        </a>
                        <div class="card-body">
                            <h6 class="card-title">
                                <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($rel['code']) ?>"
                                   class="text-decoration-none text-dark"><?= htmlspecialchars($rel['name']) ?></a>
                            </h6>
                            <div class="product-price small mt-auto">
                                <?= number_format((float) $rel['price'], 2) ?> RWF
                            </div>
                            <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($rel['code']) ?>"
                               class="btn btn-gold btn-sm w-100 mt-2"><?= lang('view_details') ?></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Structured Data -->
<script type="application/ld+json">
{
  "@context": "https://schema.org/",
  "@type": "Product",
  "name": "<?= htmlspecialchars($product['name']) ?>",
  "description": "<?= htmlspecialchars(substr($product['description'] ?? '', 0, 200)) ?>",
  "sku": "<?= htmlspecialchars($product['code']) ?>",
  "gtin13": "<?= htmlspecialchars($product['barcode'] ?? '') ?>",
  "brand": {
    "@type": "Brand",
    "name": "<?= htmlspecialchars($product['brand_name'] ?? SITE_NAME) ?>"
  },
  "offers": {
    "@type": "Offer",
    "priceCurrency": "RWF",
    "price": "<?= (float) $product['price'] ?>",
    "availability": "<?= $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' ?>"
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
