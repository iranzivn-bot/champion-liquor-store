<?php
declare(strict_types=1);
/**
 * Render a premium product section on the homepage.
 *
 * @param string $badge      Badge label (e.g. 'New Arrivals')
 * @param string $title      Section heading
 * @param string $subtitle   Section subtitle
 * @param array  $products   Product rows
 * @param string $viewAllUrl Optional View All link
 * @param array  $wishlistIds Product IDs currently in the user's wishlist
 * @param array  $compareIds  Product IDs currently in the user's compare list
 */
function renderProductSection(string $badge, string $title, string $subtitle, array $products, string $viewAllUrl, array $wishlistIds, array $compareIds): void {
    if (count($products) === 0) return;
?>
<section class="premium-section" aria-label="<?= htmlspecialchars($title) ?>">
    <div class="container-wide">
        <div class="premium-section-header">
            <div>
                <span class="premium-section-badge"><?= htmlspecialchars($badge) ?></span>
                <h2 class="premium-section-title"><?= htmlspecialchars($title) ?></h2>
                <p class="premium-section-subtitle"><?= htmlspecialchars($subtitle) ?></p>
            </div>
            <?php if ($viewAllUrl): ?>
                <a href="<?= htmlspecialchars($viewAllUrl) ?>" class="premium-section-link">
                    <?= lang('view_all') ?> <i class="bi bi-arrow-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <div class="premium-products-grid">
            <?php foreach ($products as $product):
                $inWishlist = in_array((int) $product['id'], $wishlistIds, true);
                $inCompare  = in_array((int) $product['id'], $compareIds, true);
            ?>
                <?php require __DIR__ . '/../includes/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php
}
