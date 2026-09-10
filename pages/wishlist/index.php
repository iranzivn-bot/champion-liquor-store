<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../helpers/format-helper.php';

$pdo = getDbConnection();

$userId = null;
$sessionId = null;
if (function_exists('isLoggedIn') && isLoggedIn()) {
    $userId = (int) $_SESSION['user_id'];
} else {
    $sessionId = $_SESSION['wishlist_session_id'] ?? null;
}

if ($userId) {
    $stmt = $pdo->prepare('
        SELECT w.id AS wishlist_id, w.product_id, w.created_at AS added_at,
               p.code, p.name, p.image, p.price, p.stock_quantity, p.status,
               c.name AS category_name
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE w.user_id = :uid
        ORDER BY w.created_at DESC
    ');
    $stmt->execute([':uid' => $userId]);
} elseif ($sessionId) {
    $stmt = $pdo->prepare('
        SELECT w.id AS wishlist_id, w.product_id, w.created_at AS added_at,
               p.code, p.name, p.image, p.price, p.stock_quantity, p.status,
               c.name AS category_name
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE w.session_id = :sid
        ORDER BY w.created_at DESC
    ');
    $stmt->execute([':sid' => $sessionId]);
} else {
    $wishlistItems = [];
    $wishlistCount = 0;
}

if (!isset($wishlistItems)) {
    $wishlistItems = $stmt->fetchAll();
    $wishlistCount = count($wishlistItems);
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-hero-sm">
    <div class="container">
        <h1 class="fw-bold mb-1">My Wishlist</h1>
        <p class="text-white-50 mb-0"><?= $wishlistCount ?> saved item<?= $wishlistCount !== 1 ? 's' : '' ?></p>
    </div>
</div>

<section class="section-padding">
    <div class="container">

        <?php if ($wishlistCount === 0): ?>

            <div class="text-center py-5">
                <i class="bi bi-heart" style="font-size: 4rem; color: #ccc;"></i>
                <h4 class="mt-4 text-muted">Your wishlist is empty.</h4>
                <p class="text-muted mb-4">Start adding your favorite products!</p>
                <a href="<?= SITE_URL ?>pages/shop.php" class="btn btn-lg fw-semibold px-5"
                   style="background: #C9A227; color: #fff;">
                    <i class="bi bi-shop me-2"></i>Continue Shopping
                </a>
            </div>

        <?php else: ?>

            <div class="row g-4">
                <?php foreach ($wishlistItems as $item):
                    $inStock = (int) $item['stock_quantity'] > 0;
                ?>
                    <div class="col-6 col-md-4 col-lg-3 wishlist-item" data-product-id="<?= (int) $item['product_id'] ?>">
                        <div class="product-card">
                            <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($item['code']) ?>">
                                <img src="<?= $item['image'] ? SITE_URL . 'uploads/products/' . htmlspecialchars($item['image']) : DEFAULT_PRODUCT_IMAGE ?>"
                                     class="card-img-top" alt="<?= htmlspecialchars($item['name']) ?>">
                            </a>
                            <div class="card-body">
                                <p class="card-text"><?= htmlspecialchars($item['category_name'] ?? '') ?></p>
                                <h6 class="card-title">
                                    <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($item['code']) ?>"
                                       class="text-decoration-none text-dark"><?= htmlspecialchars($item['name']) ?></a>
                                </h6>
                                <div class="product-price mb-2">
                                    <?= formatPrice((float) $item['price']) ?>
                                </div>
                                <div class="mb-2">
                                    <?php if ($inStock): ?>
                                        <span class="badge bg-success" style="font-size: 0.7rem;">In Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger" style="font-size: 0.7rem;">Out of Stock</span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-1">
                                    <?php if ($inStock): ?>
                                        <button class="btn btn-sm wishlist-move-to-cart flex-fill"
                                                style="background: #001F5B; color: #fff;"
                                                data-product-id="<?= (int) $item['product_id'] ?>"
                                                data-product-name="<?= htmlspecialchars($item['name']) ?>"
                                                title="Move to cart">
                                            <i class="bi bi-cart-plus me-1"></i> Move to Cart
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-danger wishlist-remove-btn"
                                            data-product-id="<?= (int) $item['product_id'] ?>"
                                            data-bs-toggle="modal" data-bs-target="#wishlistRemoveModal"
                                            title="Remove from wishlist">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div>
</section>

<!-- Remove Confirmation Modal -->
<div class="modal fade" id="wishlistRemoveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0" style="background: #dc3545; color: #fff;">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-heartbreak me-1"></i> Remove from Wishlist
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4 text-center">
                <i class="bi bi-question-circle" style="font-size: 3rem; color: #dc3545;"></i>
                <p class="fw-semibold mt-3 mb-0" style="color: #001F5B;">
                    Remove this product from your wishlist?
                </p>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger px-4" id="confirmWishlistRemoveBtn">
                    <i class="bi bi-trash me-1"></i> Remove
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
