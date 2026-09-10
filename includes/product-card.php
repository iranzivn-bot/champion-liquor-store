<?php
declare(strict_types=1);
/**
 * Reusable Premium Product Card
 *
 * Expects in scope:
 *   $product       - array with id, code, name, slug, price, discount_price,
 *                    stock_quantity, image, category_name, category_code
 *   $inWishlist    - bool
 *   $inCompare     - bool
 *   $badge         - ?string  (e.g. 'New', 'Best Seller', 'Sale', 'Limited')
 *
 * All other globals (SITE_URL, lang(), DEFAULT_PRODUCT_IMAGE, CSRF_TOKEN, etc.)
 * come from the including page.
 */
?>
<div class="premium-product-card">
    <!-- Action Buttons -->
    <button class="premium-card-action premium-card-wishlist wishlist-toggle <?= $inWishlist ? 'wishlist-active' : '' ?>"
            data-product-id="<?= (int) $product['id'] ?>"
            data-in-wishlist="<?= $inWishlist ? '1' : '0' ?>"
            title="<?= $inWishlist ? lang('remove_from_wishlist') : lang('add_to_wishlist') ?>"
            aria-label="<?= $inWishlist ? lang('remove_from_wishlist') : lang('add_to_wishlist') ?>">
        <i class="bi <?= $inWishlist ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
    </button>

    <button class="premium-card-action premium-card-compare compare-toggle <?= $inCompare ? 'compare-active' : '' ?>"
            data-product-id="<?= (int) $product['id'] ?>"
            data-in-compare="<?= $inCompare ? '1' : '0' ?>"
            title="<?= $inCompare ? lang('remove_from_compare') : lang('add_to_compare') ?>"
            aria-label="<?= $inCompare ? lang('remove_from_compare') : lang('add_to_compare') ?>">
        <i class="bi bi-arrow-left-right"></i>
    </button>

    <!-- Badges -->
    <?php
    $hasDiscount = $product['discount_price'] !== null;
    $pct = $hasDiscount && (float) $product['price'] > 0
        ? round((1 - (float) $product['discount_price'] / (float) $product['price']) * 100)
        : 0;
    ?>
    <?php if (!empty($badge)): ?>
        <span class="premium-card-badge premium-badge-<?= strtolower(str_replace(' ', '-', $badge)) ?>"><?= htmlspecialchars($badge) ?></span>
    <?php endif; ?>
    <?php if ($hasDiscount): ?>
        <span class="premium-card-badge premium-badge-sale">-<?= $pct ?>%</span>
    <?php endif; ?>

    <!-- Image -->
    <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($product['code']) ?>"
       class="premium-card-image-link" tabindex="-1" aria-hidden="true">
        <div class="premium-card-image">
            <img src="<?= $product['image'] ? SITE_URL . 'uploads/products/' . rawurlencode($product['image']) : DEFAULT_PRODUCT_IMAGE ?>"
                 alt="<?= htmlspecialchars($product['name']) ?>"
                 loading="lazy">
        </div>
    </a>

    <!-- Body -->
    <div class="premium-card-body">
        <?php if (!empty($product['category_name'])): ?>
            <span class="premium-card-category"><?= htmlspecialchars($product['category_name']) ?></span>
        <?php endif; ?>

        <h3 class="premium-card-title">
            <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($product['code']) ?>">
                <?= htmlspecialchars($product['name']) ?>
            </a>
        </h3>

        <!-- Rating -->
        <div class="premium-card-rating">
            <?php
            $avgRating = (float) ($product['avg_rating'] ?? 0);
            $reviewCount = (int) ($product['review_count'] ?? 0);
            $full = floor($avgRating);
            $half = ($avgRating - $full) >= 0.5;
            ?>
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <?php if ($i <= $full): ?>
                    <i class="bi bi-star-fill text-gold"></i>
                <?php elseif ($i === $full + 1 && $half): ?>
                    <i class="bi bi-star-half text-gold"></i>
                <?php else: ?>
                    <i class="bi bi-star text-gold"></i>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($reviewCount > 0): ?>
                <span class="premium-card-review-count">(<?= $reviewCount ?>)</span>
            <?php endif; ?>
        </div>

        <!-- Price -->
        <div class="premium-card-pricing">
            <?php if ($hasDiscount): ?>
                <span class="premium-card-price premium-card-price-current"><?= formatPrice((float) $product['discount_price']) ?></span>
                <span class="premium-card-price premium-card-price-old"><?= formatPrice((float) $product['price']) ?></span>
                <span class="premium-card-price-discount">-<?= $pct ?>%</span>
            <?php else: ?>
                <span class="premium-card-price premium-card-price-current"><?= formatPrice((float) $product['price']) ?></span>
            <?php endif; ?>
        </div>

        <!-- Stock -->
        <div class="premium-card-stock">
            <?php if ((int) $product['stock_quantity'] > 0): ?>
                <span class="premium-card-stock-badge in-stock"><i class="bi bi-check-circle-fill"></i> <?= lang('in_stock') ?></span>
            <?php else: ?>
                <span class="premium-card-stock-badge out-of-stock"><i class="bi bi-x-circle-fill"></i> <?= lang('out_of_stock') ?></span>
            <?php endif; ?>
        </div>

        <!-- Add to Cart -->
        <?php if ((int) $product['stock_quantity'] > 0): ?>
            <button class="premium-card-add-btn add-to-cart-btn"
                    data-product-id="<?= (int) $product['id'] ?>"
                    data-product-name="<?= htmlspecialchars($product['name']) ?>"
                    aria-label="<?= lang('add_to_cart') ?>">
                <i class="bi bi-cart-plus"></i>
                <span><?= lang('add_to_cart') ?></span>
            </button>
        <?php else: ?>
            <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($product['code']) ?>"
               class="premium-card-add-btn premium-card-add-btn-outline"
               aria-label="<?= lang('view_details') ?>">
                <i class="bi bi-eye"></i>
                <span><?= lang('view_details') ?></span>
            </a>
        <?php endif; ?>
    </div>
</div>
