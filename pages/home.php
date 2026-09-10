<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../helpers/product-section-helper.php';

$pdo = getDbConnection();

// Fetch active categories grouped by Liquor / Mini Market
$liquorCategories = $pdo->query('SELECT id, code, name, slug, image FROM categories WHERE status = \'active\' AND `group` = \'liquor\' ORDER BY name ASC')->fetchAll();
$marketCategories = $pdo->query('SELECT id, code, name, slug, image FROM categories WHERE status = \'active\' AND `group` = \'mini_market\' ORDER BY name ASC')->fetchAll();

// ─── Rating aggregation helper (reused by all product queries) ─────
$ratingJoin = '';
$ratingCols = '0 AS avg_rating, 0 AS review_count';
$hasReviewsTable = (bool) $pdo->query("SHOW TABLES LIKE 'reviews'")->fetchColumn();
if ($hasReviewsTable) {
    $ratingJoin = 'LEFT JOIN (SELECT product_id, ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE status = \'approved\' GROUP BY product_id) r ON p.id = r.product_id';
    $ratingCols = 'COALESCE(r.avg_rating, 0) AS avg_rating, COALESCE(r.review_count, 0) AS review_count';
}

// Fetch latest 8 active products with ratings
$featured = $pdo->query("
    SELECT p.*, c.name AS category_name, c.code AS category_code, {$ratingCols}
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    {$ratingJoin}
    WHERE p.status = 'active'
    ORDER BY p.created_at DESC
    LIMIT 8
")->fetchAll();

// Fetch best sellers (products with most orders)
$bestSellers = $pdo->query("
    SELECT p.*, c.name AS category_name, c.code AS category_code, {$ratingCols}
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    {$ratingJoin}
    LEFT JOIN (SELECT oi.product_id, COUNT(*) AS order_count FROM order_items oi GROUP BY oi.product_id) o ON p.id = o.product_id
    WHERE p.status = 'active'
    ORDER BY o.order_count DESC, p.created_at DESC
    LIMIT 8
")->fetchAll();

// Fetch new arrivals (same as featured but with "New" badge)
$newArrivals = $pdo->query("
    SELECT p.*, c.name AS category_name, c.code AS category_code, {$ratingCols}
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    {$ratingJoin}
    WHERE p.status = 'active'
    ORDER BY p.created_at DESC
    LIMIT 8
")->fetchAll();

// Fetch recommended products (random mix)
$recommended = $pdo->query("
    SELECT p.*, c.name AS category_name, c.code AS category_code, {$ratingCols}
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    {$ratingJoin}
    WHERE p.status = 'active'
    ORDER BY RAND()
    LIMIT 8
")->fetchAll();

// Fetch special offers (discounted products)
$specialOffers = $pdo->query("
    SELECT p.*, c.name AS category_name, c.code AS category_code, {$ratingCols}
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    {$ratingJoin}
    WHERE p.status = 'active' AND p.discount_price IS NOT NULL AND p.discount_price > 0
    ORDER BY (p.price - p.discount_price) DESC
    LIMIT 8
")->fetchAll();

// ─── Wishlist & Compare state for homepage products ────────────────
$homeProductIds = [];
foreach (array_merge($featured, $bestSellers, $newArrivals, $recommended, $specialOffers) as $hp) {
    $homeProductIds[(int) $hp['id']] = true;
}
$homeProductIdList = array_keys($homeProductIds);

$homeWishlistIds = [];
$homeCompareIds = [];
$wlUserId = (function_exists('isLoggedIn') && isLoggedIn()) ? (int) $_SESSION['user_id'] : null;
$wlSessionId = $_SESSION['wishlist_session_id'] ?? null;
$cmpUserId = (function_exists('isLoggedIn') && isLoggedIn()) ? (int) $_SESSION['user_id'] : null;
$cmpSessionId = $_SESSION['compare_session_id'] ?? null;

if (!empty($homeProductIdList)) {
    $placeholders = implode(',', array_fill(0, count($homeProductIdList), '?'));
    if ($wlUserId) {
        $st = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ? AND product_id IN ({$placeholders})");
        $st->execute(array_merge([$wlUserId], $homeProductIdList));
        $homeWishlistIds = array_column($st->fetchAll(), 'product_id');
    } elseif ($wlSessionId) {
        $st = $pdo->prepare("SELECT product_id FROM wishlist WHERE session_id = ? AND product_id IN ({$placeholders})");
        $st->execute(array_merge([$wlSessionId], $homeProductIdList));
        $homeWishlistIds = array_column($st->fetchAll(), 'product_id');
    }
    if ($cmpUserId) {
        $st = $pdo->prepare("SELECT product_id FROM compare_list WHERE user_id = ? AND product_id IN ({$placeholders})");
        $st->execute(array_merge([$cmpUserId], $homeProductIdList));
        $homeCompareIds = array_column($st->fetchAll(), 'product_id');
    } elseif ($cmpSessionId) {
        $st = $pdo->prepare("SELECT product_id FROM compare_list WHERE session_id = ? AND product_id IN ({$placeholders})");
        $st->execute(array_merge([$cmpSessionId], $homeProductIdList));
        $homeCompareIds = array_column($st->fetchAll(), 'product_id');
    }
}

// Fetch one discounted product for hero image
$heroProduct = $pdo->query('
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = \'active\' AND p.image IS NOT NULL AND p.image != \'\'
    ORDER BY p.discount_price IS NOT NULL AND p.discount_price > p.price DESC, p.created_at DESC
    LIMIT 1
')->fetch();

// Fetch product counts per category for the showcase badges
$catCounts = $pdo->query('
    SELECT category_id, COUNT(*) AS cnt FROM products WHERE status = \'active\' GROUP BY category_id
')->fetchAll(PDO::FETCH_KEY_PAIR);

// Fetch active collections with linked category info and product count
$collections = $pdo->query('
    SELECT col.*, cat.name AS category_name, cat.slug AS category_slug,
           (SELECT COUNT(*) FROM products WHERE category_id = col.category_id AND status = \'active\') AS product_count
    FROM collections col
    LEFT JOIN categories cat ON col.category_id = cat.id
    WHERE col.status = \'active\'
    ORDER BY col.sort_order ASC, col.id ASC
    LIMIT 6
')->fetchAll();

// Fetch flash sale data
$flashSaleEnd = setting('flash_sale_end', '');
$hasFlashSale = $flashSaleEnd !== '' && strtotime($flashSaleEnd) > time();

$flashProducts = $pdo->query('
    SELECT p.*, c.name AS category_name, c.code AS category_code
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = \'active\' AND p.discount_price IS NOT NULL AND p.discount_price > p.price
    ORDER BY (p.discount_price - p.price) DESC
    LIMIT 4
')->fetchAll();

// Fetch active brands with product counts
$brands = $pdo->query('
    SELECT b.*, COUNT(p.id) AS product_count
    FROM brands b
    LEFT JOIN products p ON p.brand_id = b.id AND p.status = \'active\'
    WHERE b.status = \'active\'
    GROUP BY b.id
    ORDER BY b.name ASC
')->fetchAll();

// Promotional banners configuration (dynamic from DB)
$promoBanners = [];

$promoFeaturedProduct = $pdo->query('
    SELECT p.image, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = \'active\' AND p.image IS NOT NULL AND p.image != \'\'
    AND p.discount_price IS NOT NULL AND p.discount_price > 0
    ORDER BY (p.price - p.discount_price) DESC
    LIMIT 1
')->fetch();

$promoLiquorProduct = $pdo->query('
    SELECT p.image
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = \'active\' AND p.image IS NOT NULL AND p.image != \'\' AND c.`group` = \'liquor\'
    ORDER BY RAND() LIMIT 1
')->fetch();

$promoMarketProduct = $pdo->query('
    SELECT p.image
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = \'active\' AND p.image IS NOT NULL AND p.image != \'\' AND c.`group` = \'mini_market\'
    ORDER BY RAND() LIMIT 1
')->fetch();

$promoBanners['featured'] = [
    'image' => !empty($promoFeaturedProduct['image']) ? $promoFeaturedProduct['image'] : '',
    'badge' => 'Limited Offer',
    'headline' => 'Premium Wine Collection',
    'subtitle' => 'Discover our handpicked selection of the finest wines from around the world.',
    'cta' => 'Shop Wines',
    'url' => SITE_URL . 'pages/shop.php?category=wines',
    'gradient' => 'linear-gradient(135deg, rgba(0,31,91,0.88) 0%, rgba(0,31,91,0.35) 100%)',
];

$promoBanners['right_top'] = [
    'image' => !empty($promoLiquorProduct['image']) ? $promoLiquorProduct['image'] : '',
    'badge' => 'New Arrivals',
    'headline' => 'Craft Beers & Spirits',
    'subtitle' => 'Explore our latest collection of premium beers and spirits.',
    'cta' => 'Explore',
    'url' => SITE_URL . 'pages/shop.php?group=liquor',
    'gradient' => 'linear-gradient(135deg, rgba(11,107,47,0.88) 0%, rgba(11,107,47,0.25) 100%)',
];

$promoBanners['right_bottom'] = [
    'image' => !empty($promoMarketProduct['image']) ? $promoMarketProduct['image'] : '',
    'badge' => 'Free Delivery',
    'headline' => 'Order Over 50,000 RWF',
    'subtitle' => 'Enjoy free delivery on all orders above 50,000 RWF within Kigali.',
    'cta' => 'Learn More',
    'url' => SITE_URL . 'pages/shop.php',
    'gradient' => 'linear-gradient(135deg, rgba(201,162,39,0.85) 0%, rgba(201,162,39,0.18) 100%)',
];

// Reviews data (placeholder if table does not exist)
$customerReviews = [];
if ($hasReviewsTable) {
    $customerReviews = $pdo->query('
        SELECT r.*, u.full_name, u.profile_image
        FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.status = \'approved\'
        ORDER BY r.created_at DESC
        LIMIT 10
    ')->fetchAll();
}
if (count($customerReviews) === 0) {
    $customerReviews = [
        ['full_name' => 'Jean Paul', 'rating' => 5, 'review' => 'Champion Liquor Store has the best selection of wines in Kigali. Highly recommend!', 'created_at' => '2026-06-15 14:30:00', 'profile_image' => null],
        ['full_name' => 'Alice M.', 'rating' => 5, 'review' => 'Fast delivery and great prices. My go-to store for all my beverage needs.', 'created_at' => '2026-06-10 10:00:00', 'profile_image' => null],
        ['full_name' => 'David K.', 'rating' => 5, 'review' => 'Excellent customer service and premium quality products. Always a pleasure shopping here.', 'created_at' => '2026-06-05 09:15:00', 'profile_image' => null],
        ['full_name' => 'Grace U.', 'rating' => 4, 'review' => 'The mini-market section is amazing! Fresh products delivered right to my door.', 'created_at' => '2026-05-28 16:45:00', 'profile_image' => null],
        ['full_name' => 'Patrick N.', 'rating' => 5, 'review' => 'Best prices in town and the loyalty program is fantastic. Highly satisfied!', 'created_at' => '2026-05-20 11:00:00', 'profile_image' => null],
    ];
}

// Social media links
// Fetch products for Instagram gallery (random 6 with images)
$instaProducts = $pdo->query('
    SELECT id, code, image, name FROM products
    WHERE status = \'active\' AND image IS NOT NULL AND image != \'\'
    ORDER BY RAND() LIMIT 6
')->fetchAll();

$socialLinks = [
    ['platform' => 'Facebook', 'icon' => 'bi-facebook', 'url' => 'https://www.facebook.com/championliquorstore', 'color' => '#1877F2'],
    ['platform' => 'Instagram', 'icon' => 'bi-instagram', 'url' => 'https://www.instagram.com/championliquorstore', 'color' => '#E4405F'],
    ['platform' => 'TikTok', 'icon' => 'bi-tiktok', 'url' => 'https://www.tiktok.com/@championliquorstore', 'color' => '#000000'],
    ['platform' => 'YouTube', 'icon' => 'bi-youtube', 'url' => 'https://www.youtube.com/@championliquorstore', 'color' => '#FF0000'],
    ['platform' => 'WhatsApp', 'icon' => 'bi-whatsapp', 'url' => 'https://wa.me/250784266545', 'color' => '#25D366'],
];
?>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Store",
  "name": "<?= SITE_NAME ?>",
  "description": "<?= lang('footer_about') ?>",
  "url": "<?= SITE_URL ?>",
  "telephone": "<?= htmlspecialchars(setting('company_phone', '+250 784 266 545')) ?>",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Kicukiro-Niboyi",
    "addressLocality": "Kigali",
    "addressCountry": "RW"
  },
  "priceRange": "$$"
}
</script>

<section class="hero-section" aria-label="Hero banner">
    <div class="hero-container">
        <div class="hero-grid">
            <!-- ─── Left Main Banner (70%) ─────────────────────── -->
            <div class="hero-main">
                <div class="hero-main-bg">
                    <div class="hero-accent-line"></div>
                </div>
                <div class="hero-main-content">
                    <span class="hero-badge"><?= SITE_NAME ?></span>
                    <h1 class="hero-headline">Premium Spirits<br><span class="hero-headline-gold">&amp; Fine Wines</span></h1>
                    <p class="hero-subtitle">Discover our curated collection of the world's finest liquors, wines, and premium beverages delivered to your doorstep.</p>
                    <div class="hero-actions">
                        <a href="<?= SITE_URL ?>pages/shop.php" class="btn btn-gold btn-lg">
                            Shop Now <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                        <a href="#categories" class="btn btn-outline-gold btn-lg">
                            Explore Categories
                        </a>
                    </div>
                </div>
                <div class="hero-image-wrap">
                    <?php if ($heroProduct && $heroProduct['image']): ?>
                        <img src="<?= SITE_URL ?>uploads/products/<?= htmlspecialchars($heroProduct['image']) ?>"
                             alt="<?= htmlspecialchars($heroProduct['name']) ?>"
                             loading="lazy"
                             class="hero-product-img">
                    <?php else: ?>
                        <div class="hero-image-placeholder" role="img" aria-label="Featured product placeholder">
                            <i class="bi bi-cup-straw"></i>
                        </div>
                    <?php endif; ?>
                    <div class="hero-image-tag" aria-hidden="true">
                        <span class="hero-tag-text">Premium</span>
                        <span class="hero-tag-sub">Selection</span>
                    </div>
                </div>
            </div>

            <!-- ─── Right Promo Cards (30%) ────────────────────── -->
            <div class="hero-side">
                <a href="<?= SITE_URL ?>pages/offers/index.php" class="hero-promo-card hero-promo-green" aria-label="View special offers">
                    <div class="hero-promo-shine"></div>
                    <div class="hero-promo-body">
                        <span class="hero-promo-badge">Limited Time</span>
                        <h3 class="hero-promo-title">Special<br>Offers</h3>
                        <p class="hero-promo-desc">Up to 40% off selected items</p>
                        <span class="hero-promo-link">Shop Deals <i class="bi bi-arrow-right ms-1"></i></span>
                    </div>
                </a>
                <a href="<?= SITE_URL ?>pages/flash-sale/index.php" class="hero-promo-card hero-promo-gold" aria-label="View flash sale deals">
                    <div class="hero-promo-shine"></div>
                    <div class="hero-promo-body">
                        <span class="hero-promo-badge hero-promo-badge-gold">Flash Sale</span>
                        <h3 class="hero-promo-title">Daily<br>Deals</h3>
                        <p class="hero-promo-desc">New deals every day. Don't miss out!</p>
                        <span class="hero-promo-link">View Sale <i class="bi bi-arrow-right ms-1"></i></span>
                    </div>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Feature Strip -->
<section class="feature-strip" aria-label="Service features">
    <div class="container-wide">
        <div class="feature-strip-grid">
            <div class="feature-strip-card">
                <div class="feature-strip-icon">
                    <i class="bi bi-patch-check-fill"></i>
                </div>
                <div class="feature-strip-body">
                    <h4 class="feature-strip-title">100% Authentic</h4>
                    <p class="feature-strip-desc">Guaranteed genuine products from trusted brands</p>
                </div>
            </div>
            <div class="feature-strip-card">
                <div class="feature-strip-icon">
                    <i class="bi bi-truck"></i>
                </div>
                <div class="feature-strip-body">
                    <h4 class="feature-strip-title">Fast Delivery</h4>
                    <p class="feature-strip-desc">Same-day delivery within Kigali city limits</p>
                </div>
            </div>
            <div class="feature-strip-card">
                <div class="feature-strip-icon">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
                <div class="feature-strip-body">
                    <h4 class="feature-strip-title">Secure Payments</h4>
                    <p class="feature-strip-desc">Encrypted transactions for your peace of mind</p>
                </div>
            </div>
            <div class="feature-strip-card">
                <div class="feature-strip-icon">
                    <i class="bi bi-tags-fill"></i>
                </div>
                <div class="feature-strip-body">
                    <h4 class="feature-strip-title">Best Prices</h4>
                    <p class="feature-strip-desc">Competitive pricing with exclusive daily deals</p>
                </div>
            </div>
            <div class="feature-strip-card">
                <div class="feature-strip-icon">
                    <i class="bi bi-headset"></i>
                </div>
                <div class="feature-strip-body">
                    <h4 class="feature-strip-title">24/7 Support</h4>
                    <p class="feature-strip-desc">Dedicated customer care whenever you need us</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Category Showcase -->
<section class="category-showcase" id="categories" aria-label="Category showcase">
    <div class="container-wide">
        <div class="category-showcase-grid">
            <!-- Left: Shop Liquor -->
            <div class="category-panel">
                <div class="category-panel-header">
                    <h2 class="category-panel-title"><?= lang('shop_liquor') ?></h2>
                    <a href="<?= SITE_URL ?>pages/shop.php?group=liquor" class="category-panel-link">
                        <?= lang('view_all') ?> <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="category-panel-grid">
                    <?php if (count($liquorCategories) === 0): ?>
                        <p class="text-muted small mb-0"><?= lang('no_categories') ?></p>
                    <?php else: ?>
                        <?php foreach ($liquorCategories as $cat): ?>
                            <a href="<?= SITE_URL ?>pages/shop.php?category=<?= urlencode($cat['slug']) ?>"
                               class="category-card" aria-label="Browse <?= htmlspecialchars($cat['name']) ?>">
                                <div class="category-card-image">
                                    <?php if ($cat['image']): ?>
                                        <img src="<?= SITE_URL ?>uploads/categories/<?= htmlspecialchars($cat['image']) ?>"
                                             alt="<?= htmlspecialchars($cat['name']) ?>"
                                             loading="lazy">
                                    <?php else: ?>
                                        <div class="category-card-placeholder">
                                            <i class="bi bi-tag"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <h3 class="category-card-title"><?= htmlspecialchars($cat['name']) ?></h3>
                                <span class="category-card-count"><?= (int) ($catCounts[$cat['id']] ?? 0) ?> Products</span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Right: Mini Market Categories -->
            <div class="category-panel">
                <div class="category-panel-header">
                    <h2 class="category-panel-title"><?= lang('mini_market_categories') ?></h2>
                    <a href="<?= SITE_URL ?>pages/shop.php?group=mini_market" class="category-panel-link">
                        <?= lang('view_all') ?> <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="category-panel-grid">
                    <?php if (count($marketCategories) === 0): ?>
                        <p class="text-muted small mb-0"><?= lang('no_categories') ?></p>
                    <?php else: ?>
                        <?php foreach ($marketCategories as $cat): ?>
                            <a href="<?= SITE_URL ?>pages/shop.php?category=<?= urlencode($cat['slug']) ?>"
                               class="category-card" aria-label="Browse <?= htmlspecialchars($cat['name']) ?>">
                                <div class="category-card-image">
                                    <?php if ($cat['image']): ?>
                                        <img src="<?= SITE_URL ?>uploads/categories/<?= htmlspecialchars($cat['image']) ?>"
                                             alt="<?= htmlspecialchars($cat['name']) ?>"
                                             loading="lazy">
                                    <?php else: ?>
                                        <div class="category-card-placeholder">
                                            <i class="bi bi-tag"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <h3 class="category-card-title"><?= htmlspecialchars($cat['name']) ?></h3>
                                <span class="category-card-count"><?= (int) ($catCounts[$cat['id']] ?? 0) ?> Products</span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Flash Sale Section -->
<?php if (count($flashProducts) > 0): ?>
<section class="flash-section" aria-label="Flash sale deals">
    <div class="container-wide">
        <div class="flash-header">
            <div>
                <span class="flash-section-badge"><i class="bi bi-lightning-fill me-1"></i> Flash Sale</span>
                <h2 class="flash-section-title">Limited Time Offers</h2>
                <p class="flash-section-subtitle">Grab these deals before they're gone</p>
            </div>
            <?php if ($hasFlashSale): ?>
            <div class="flash-countdown-wrapper">
                <span class="flash-countdown-label">Ends in</span>
                <div class="flash-countdown" data-end="<?= htmlspecialchars($flashSaleEnd) ?>">
                    <div class="flash-countdown-block">
                        <span class="flash-countdown-num" id="flash-days">00</span>
                        <span class="flash-countdown-unit">Days</span>
                    </div>
                    <span class="flash-countdown-sep">:</span>
                    <div class="flash-countdown-block">
                        <span class="flash-countdown-num" id="flash-hours">00</span>
                        <span class="flash-countdown-unit">Hrs</span>
                    </div>
                    <span class="flash-countdown-sep">:</span>
                    <div class="flash-countdown-block">
                        <span class="flash-countdown-num" id="flash-mins">00</span>
                        <span class="flash-countdown-unit">Mins</span>
                    </div>
                    <span class="flash-countdown-sep">:</span>
                    <div class="flash-countdown-block">
                        <span class="flash-countdown-num" id="flash-secs">00</span>
                        <span class="flash-countdown-unit">Secs</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <a href="<?= SITE_URL ?>pages/flash-sale/index.php" class="flash-view-all">
                View All <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <div class="flash-grid">
            <?php foreach ($flashProducts as $product): ?>
                <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($product['code']) ?>"
                   class="flash-card" aria-label="View <?= htmlspecialchars($product['name']) ?>">
                    <div class="flash-card-badge">-<?= number_format((1 - (float)$product['discount_price'] / (float)$product['price']) * 100, 0) ?>%</div>
                    <div class="flash-card-image">
                        <img src="<?= $product['image'] ? SITE_URL . 'uploads/products/' . htmlspecialchars($product['image']) : DEFAULT_PRODUCT_IMAGE ?>"
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             loading="lazy">
                    </div>
                    <div class="flash-card-body">
                        <span class="flash-card-cat"><?= htmlspecialchars($product['category_name'] ?? '') ?></span>
                        <h3 class="flash-card-title"><?= htmlspecialchars($product['name']) ?></h3>
                        <div class="flash-card-pricing">
                            <span class="flash-card-price"><?= number_format((float) $product['discount_price'], 0) ?> RWF</span>
                            <span class="flash-card-old"><?= number_format((float) $product['price'], 0) ?> RWF</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Featured Collections -->
<?php if (count($collections) > 0): ?>
<section class="collections-section" aria-label="Featured collections">
    <div class="container-wide">
        <div class="collections-header">
            <span class="collections-header-badge"><?= lang('collections') ?></span>
            <h2 class="collections-header-title"><?= lang('featured_collections') ?></h2>
            <p class="collections-header-subtitle"><?= lang('curated_selections') ?></p>
        </div>
        <div class="collections-grid">
            <?php foreach ($collections as $col): ?>
                <a href="<?= SITE_URL ?>pages/shop.php?category=<?= urlencode($col['category_slug'] ?? '') ?>"
                   class="collection-card" aria-label="Browse <?= htmlspecialchars($col['name']) ?>">
                    <div class="collection-card-bg"
                         <?php if ($col['image']): ?>style="background-image: url('<?= SITE_URL ?>uploads/collections/<?= htmlspecialchars($col['image']) ?>');"<?php endif; ?>></div>
                    <div class="collection-card-overlay"></div>
                    <div class="collection-card-body">
                        <?php if (!$col['image']): ?>
                            <div class="collection-card-icon"><i class="bi bi-collection"></i></div>
                        <?php endif; ?>
                        <h3 class="collection-card-title"><?= htmlspecialchars($col['name']) ?></h3>
                        <p class="collection-card-desc"><?= htmlspecialchars(mb_substr($col['description'] ?? '', 0, 80)) ?></p>
                        <span class="collection-card-link"><?= (int) $col['product_count'] ?> Products <i class="bi bi-arrow-right ms-1"></i></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Premium Product Sections -->
<?php
renderProductSection(
    lang('new_arrivals_badge'),
    lang('new_arrivals_title'),
    lang('new_arrivals_subtitle'),
    $newArrivals,
    SITE_URL . 'pages/shop.php',
    $homeWishlistIds,
    $homeCompareIds
);

renderProductSection(
    lang('best_sellers_badge'),
    lang('best_sellers_title'),
    lang('best_sellers_subtitle'),
    $bestSellers,
    SITE_URL . 'pages/shop.php',
    $homeWishlistIds,
    $homeCompareIds
);

renderProductSection(
    lang('special_offers_badge'),
    lang('special_offers_title'),
    lang('special_offers_subtitle'),
    $specialOffers,
    SITE_URL . 'pages/shop.php?sort=discount',
    $homeWishlistIds,
    $homeCompareIds
);

renderProductSection(
    lang('recommended_badge'),
    lang('recommended_title'),
    lang('recommended_subtitle'),
    $recommended,
    SITE_URL . 'pages/shop.php',
    $homeWishlistIds,
    $homeCompareIds
);

renderProductSection(
    lang('featured_badge'),
    lang('featured_title'),
    lang('featured_subtitle'),
    $featured,
    SITE_URL . 'pages/shop.php',
    $homeWishlistIds,
    $homeCompareIds
);
?>

<!-- ════════════════════════════════════════════════════════════
     SECTION 1: PREMIUM PROMOTIONAL BANNERS
     ════════════════════════════════════════════════════════════ -->
<section class="promo-banners-section" aria-label="Promotional banners">
    <div class="container-wide">
        <div class="promo-banners-grid">
            <!-- Large Featured Banner -->
            <a href="<?= htmlspecialchars($promoBanners['featured']['url']) ?>"
               class="promo-banner-large"
               aria-label="<?= htmlspecialchars($promoBanners['featured']['headline']) ?>">
                <div class="promo-banner-bg" style="background-image: <?= htmlspecialchars($promoBanners['featured']['gradient']) ?>, url('<?= $promoBanners['featured']['image'] ? SITE_URL . 'uploads/products/' . rawurlencode($promoBanners['featured']['image']) : 'none' ?>');"></div>
                <div class="promo-banner-overlay"></div>
                <div class="promo-banner-content">
                    <span class="promo-banner-badge"><?= htmlspecialchars($promoBanners['featured']['badge']) ?></span>
                    <h3 class="promo-banner-headline"><?= htmlspecialchars($promoBanners['featured']['headline']) ?></h3>
                    <p class="promo-banner-text"><?= htmlspecialchars($promoBanners['featured']['subtitle']) ?></p>
                    <span class="promo-banner-cta"><?= htmlspecialchars($promoBanners['featured']['cta']) ?> <i class="bi bi-arrow-right"></i></span>
                </div>
            </a>
            <!-- Right Stacked Banners -->
            <div class="promo-banners-stacked">
                <a href="<?= htmlspecialchars($promoBanners['right_top']['url']) ?>"
                   class="promo-banner-small"
                   aria-label="<?= htmlspecialchars($promoBanners['right_top']['headline']) ?>">
                    <div class="promo-banner-bg" style="background-image: <?= htmlspecialchars($promoBanners['right_top']['gradient']) ?>, url('<?= $promoBanners['right_top']['image'] ? SITE_URL . 'uploads/products/' . rawurlencode($promoBanners['right_top']['image']) : 'none' ?>');"></div>
                    <div class="promo-banner-overlay"></div>
                    <div class="promo-banner-content">
                        <span class="promo-banner-badge"><?= htmlspecialchars($promoBanners['right_top']['badge']) ?></span>
                        <h4 class="promo-banner-headline"><?= htmlspecialchars($promoBanners['right_top']['headline']) ?></h4>
                        <p class="promo-banner-text"><?= htmlspecialchars($promoBanners['right_top']['subtitle']) ?></p>
                        <span class="promo-banner-cta"><?= htmlspecialchars($promoBanners['right_top']['cta']) ?> <i class="bi bi-arrow-right"></i></span>
                    </div>
                </a>
                <a href="<?= htmlspecialchars($promoBanners['right_bottom']['url']) ?>"
                   class="promo-banner-small"
                   aria-label="<?= htmlspecialchars($promoBanners['right_bottom']['headline']) ?>">
                    <div class="promo-banner-bg" style="background-image: <?= htmlspecialchars($promoBanners['right_bottom']['gradient']) ?>, url('<?= $promoBanners['right_bottom']['image'] ? SITE_URL . 'uploads/products/' . rawurlencode($promoBanners['right_bottom']['image']) : 'none' ?>');"></div>
                    <div class="promo-banner-overlay"></div>
                    <div class="promo-banner-content">
                        <span class="promo-banner-badge"><?= htmlspecialchars($promoBanners['right_bottom']['badge']) ?></span>
                        <h4 class="promo-banner-headline"><?= htmlspecialchars($promoBanners['right_bottom']['headline']) ?></h4>
                        <p class="promo-banner-text"><?= htmlspecialchars($promoBanners['right_bottom']['subtitle']) ?></p>
                        <span class="promo-banner-cta"><?= htmlspecialchars($promoBanners['right_bottom']['cta']) ?> <i class="bi bi-arrow-right"></i></span>
                    </div>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════════════════════════════════════════════
     SECTION 2: FEATURED BRANDS
     ════════════════════════════════════════════════════════════ -->
<?php if (count($brands) > 0): ?>
<section class="brands-section" aria-label="Featured brands">
    <div class="container-wide">
        <div class="brands-header">
            <span class="brands-section-badge"><?= lang('our_brands') ?></span>
            <h2 class="brands-section-title"><?= lang('brands_title') ?></h2>
            <p class="brands-section-subtitle"><?= lang('brands_subtitle') ?></p>
        </div>
        <div class="brands-grid">
            <?php foreach ($brands as $brand): ?>
                <a href="<?= SITE_URL ?>pages/shop.php?brand=<?= urlencode($brand['slug']) ?>"
                   class="brand-card"
                   aria-label="<?= htmlspecialchars($brand['name']) ?>">
                    <?php if ($brand['logo']): ?>
                        <img src="<?= SITE_URL ?>uploads/brands/<?= htmlspecialchars($brand['logo']) ?>"
                             alt="<?= htmlspecialchars($brand['name']) ?>"
                             loading="lazy"
                             class="brand-card-logo">
                    <?php else: ?>
                        <div class="brand-card-placeholder">
                            <i class="bi bi-building"></i>
                        </div>
                    <?php endif; ?>
                    <span class="brand-card-name"><?= htmlspecialchars($brand['name']) ?></span>
                    <span class="brand-card-count"><?= (int) $brand['product_count'] ?> <?= lang('products') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <!-- Mobile slider hint -->
        <div class="brands-slider-hint d-sm-none text-center mt-3">
            <span class="badge bg-light text-muted"><i class="bi bi-arrows-horizontal me-1"></i><?= lang('swipe_to_browse') ?></span>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════════
     SECTION 3: WHY CHOOSE US
     ════════════════════════════════════════════════════════════ -->
<section class="why-choose-section" aria-label="Why choose us">
    <div class="container-wide">
        <div class="why-choose-header">
            <span class="why-choose-badge"><?= lang('why_choose_badge') ?></span>
            <h2 class="why-choose-title"><?= lang('why_choose_title') ?></h2>
            <p class="why-choose-subtitle"><?= lang('why_choose_subtitle') ?></p>
        </div>
        <div class="why-choose-grid">
            <div class="why-choose-card">
                <div class="why-choose-icon">
                    <i class="bi bi-patch-check-fill"></i>
                </div>
                <h4 class="why-choose-card-title"><?= lang('authentic_products') ?></h4>
                <p class="why-choose-card-desc"><?= lang('authentic_products_desc') ?></p>
            </div>
            <div class="why-choose-card">
                <div class="why-choose-icon">
                    <i class="bi bi-truck"></i>
                </div>
                <h4 class="why-choose-card-title"><?= lang('fast_delivery_title') ?></h4>
                <p class="why-choose-card-desc"><?= lang('fast_delivery_desc') ?></p>
            </div>
            <div class="why-choose-card">
                <div class="why-choose-icon">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
                <h4 class="why-choose-card-title"><?= lang('secure_payments') ?></h4>
                <p class="why-choose-card-desc"><?= lang('secure_payments_desc') ?></p>
            </div>
            <div class="why-choose-card">
                <div class="why-choose-icon">
                    <i class="bi bi-arrow-return-left"></i>
                </div>
                <h4 class="why-choose-card-title"><?= lang('easy_returns') ?></h4>
                <p class="why-choose-card-desc"><?= lang('easy_returns_desc') ?></p>
            </div>
            <div class="why-choose-card">
                <div class="why-choose-icon">
                    <i class="bi bi-headset"></i>
                </div>
                <h4 class="why-choose-card-title"><?= lang('support_247') ?></h4>
                <p class="why-choose-card-desc"><?= lang('support_247_desc') ?></p>
            </div>
            <div class="why-choose-card">
                <div class="why-choose-icon">
                    <i class="bi bi-award-fill"></i>
                </div>
                <h4 class="why-choose-card-title"><?= lang('premium_quality') ?></h4>
                <p class="why-choose-card-desc"><?= lang('premium_quality_desc') ?></p>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════════════════════════════════════════════
     SECTION 4: CUSTOMER REVIEWS
     ════════════════════════════════════════════════════════════ -->
<section class="reviews-section" aria-label="Customer reviews">
    <div class="container-wide">
        <div class="reviews-header">
            <span class="reviews-section-badge"><?= lang('reviews_badge') ?></span>
            <h2 class="reviews-section-title"><?= lang('reviews_title') ?></h2>
            <p class="reviews-section-subtitle"><?= lang('reviews_subtitle') ?></p>
        </div>
        <div class="reviews-carousel-wrapper">
            <div class="reviews-track" id="reviewsTrack">
                <?php foreach ($customerReviews as $rv): ?>
                    <div class="review-card-item">
                        <div class="review-card-inner">
                            <div class="review-card-header">
                                <div class="review-card-avatar">
                                    <?php if (!empty($rv['profile_image'])): ?>
                                        <img src="<?= SITE_URL ?>uploads/users/<?= htmlspecialchars($rv['profile_image']) ?>"
                                             alt="<?= htmlspecialchars($rv['full_name'] ?? '') ?>"
                                             loading="lazy"
                                             class="review-avatar-img">
                                    <?php else: ?>
                                        <span class="review-card-avatar-letter"><?= strtoupper(substr($rv['full_name'] ?? '?', 0, 1)) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h5 class="review-card-name"><?= htmlspecialchars($rv['full_name'] ?? 'Anonymous') ?></h5>
                                    <span class="review-card-badge"><i class="bi bi-patch-check-fill"></i> <?= lang('verified_purchase') ?></span>
                                </div>
                            </div>
                            <div class="review-card-stars">
                                <?php $rating = (int) ($rv['rating'] ?? 5); ?>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi <?= $i <= $rating ? 'bi-star-fill' : 'bi-star' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <p class="review-card-text">"<?= htmlspecialchars(mb_substr($rv['review'] ?? $rv['text'] ?? '', 0, 200)) ?>"</p>
                            <span class="review-card-date"><?= date('F j, Y', strtotime($rv['created_at'] ?? 'now')) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="reviews-nav reviews-nav-prev" id="reviewsPrev" aria-label="<?= lang('previous_reviews') ?>">
                <i class="bi bi-chevron-left"></i>
            </button>
            <button class="reviews-nav reviews-nav-next" id="reviewsNext" aria-label="<?= lang('next_reviews') ?>">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
        <div class="reviews-dots" id="reviewsDots"></div>
    </div>
</section>

<!-- ════════════════════════════════════════════════════════════
     SECTION 5: DOWNLOAD APP
     ════════════════════════════════════════════════════════════ -->
<section class="app-section" aria-label="Download our app">
    <div class="container-wide">
        <div class="app-section-inner">
            <div class="app-section-content">
                <span class="app-section-badge"><?= lang('coming_soon') ?></span>
                <h2 class="app-section-title"><?= lang('app_title') ?></h2>
                <p class="app-section-desc"><?= lang('app_subtitle') ?></p>
                <div class="app-store-badges app-store-placeholder">
                    <span class="app-store-btn app-store-disabled" aria-disabled="true">
                        <i class="bi bi-google-play"></i>
                        <span class="app-store-text">
                            <small><?= lang('get_it_on') ?></small>
                            <strong>Google Play</strong>
                        </span>
                    </span>
                    <span class="app-store-btn app-store-disabled" aria-disabled="true">
                        <i class="bi bi-apple"></i>
                        <span class="app-store-text">
                            <small><?= lang('get_it_on') ?></small>
                            <strong>App Store</strong>
                        </span>
                    </span>
                </div>
            </div>
            <div class="app-section-visual">
                <div class="app-phone-mockup">
                    <div class="app-phone-notch"></div>
                    <div class="app-phone-screen">
                        <i class="bi bi-phone"></i>
                        <span><?= lang('app_placeholder') ?></span>
                    </div>
                </div>
                <div class="app-qr-code">
                    <div class="app-qr-placeholder">
                        <i class="bi bi-qr-code"></i>
                    </div>
                    <span class="app-qr-label"><?= lang('scan_to_download') ?></span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════════════════════════════════════════════
     SECTION 6: SOCIAL MEDIA
     ════════════════════════════════════════════════════════════ -->
<section class="social-section" aria-label="Follow us on social media">
    <div class="container-wide">
        <div class="social-header">
            <span class="social-section-badge"><?= lang('stay_connected') ?></span>
            <h2 class="social-section-title"><?= lang('follow_us') ?></h2>
            <p class="social-section-subtitle"><?= lang('social_subtitle') ?></p>
        </div>
        <div class="social-grid">
            <?php foreach ($socialLinks as $sl): ?>
                <a href="<?= htmlspecialchars($sl['url']) ?>"
                   class="social-link"
                   target="_blank"
                   rel="noopener noreferrer"
                   aria-label="<?= htmlspecialchars($sl['platform']) ?>"
                   style="--social-color: <?= htmlspecialchars($sl['color']) ?>;">
                    <div class="social-link-icon">
                        <i class="bi <?= htmlspecialchars($sl['icon']) ?>"></i>
                    </div>
                    <span class="social-link-name"><?= htmlspecialchars($sl['platform']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ════════════════════════════════════════════════════════════
     SECTION 7: INSTAGRAM GALLERY
     ════════════════════════════════════════════════════════════ -->
<section class="instagram-section" aria-label="Instagram gallery">
    <div class="container-wide">
        <div class="instagram-header">
            <span class="instagram-section-badge"><?= lang('instagram') ?></span>
            <h2 class="instagram-section-title"><?= lang('instagram_title') ?></h2>
            <p class="instagram-section-subtitle"><?= lang('instagram_subtitle') ?></p>
        </div>
        <div class="instagram-grid">
            <?php if (count($instaProducts) > 0): ?>
                <?php foreach ($instaProducts as $ig): ?>
                    <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($ig['code'] ?? '') ?>"
                       class="instagram-item"
                       aria-label="<?= htmlspecialchars($ig['name']) ?>">
                        <div class="instagram-item-inner">
                            <img src="<?= SITE_URL ?>uploads/products/<?= htmlspecialchars($ig['image']) ?>"
                                 alt="<?= htmlspecialchars($ig['name']) ?>"
                                 loading="lazy"
                                 class="instagram-img">
                            <div class="instagram-overlay">
                                <i class="bi bi-heart-fill"></i>
                                <span><?= lang('follow_us_ig') ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <?php for ($i = 1; $i <= 6; $i++): ?>
                    <div class="instagram-item">
                        <div class="instagram-item-inner">
                            <div class="instagram-placeholder">
                                <i class="bi bi-camera"></i>
                            </div>
                            <div class="instagram-overlay">
                                <i class="bi bi-instagram"></i>
                                <span><?= lang('follow_us_ig') ?></span>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            <?php endif; ?>
        </div>
        <div class="instagram-cta text-center mt-4">
            <a href="#" class="btn btn-outline-gold" target="_blank" rel="noopener noreferrer">
                <i class="bi bi-instagram me-2"></i><?= lang('follow_us_ig_cta') ?>
            </a>
        </div>
    </div>
</section>

<!-- ════════════════════════════════════════════════════════════
     SECTION 8: NEWSLETTER
     ════════════════════════════════════════════════════════════ -->
<section class="newsletter-section" aria-label="Newsletter signup">
    <div class="newsletter-decor-left"></div>
    <div class="newsletter-decor-right"></div>
    <div class="container position-relative">
        <div class="row justify-content-center">
            <div class="col-lg-7 text-center">
                <span class="newsletter-badge"><i class="bi bi-envelope-open"></i> <?= lang('stay_connected') ?></span>
                <h2 class="newsletter-title"><?= lang('newsletter_title') ?></h2>
                <p class="newsletter-desc"><?= lang('newsletter_desc') ?></p>
                <form class="newsletter-form" id="newsletterForm">
                    <div class="input-group">
                        <span class="input-group-text newsletter-input-icon"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" placeholder="<?= lang('email_placeholder') ?>" required aria-label="<?= lang('email_placeholder') ?>">
                        <button class="btn btn-gold" type="submit">
                            <?= lang('subscribe') ?> <i class="bi bi-send ms-1"></i>
                        </button>
                    </div>
                </form>
                <div class="newsletter-success d-none mt-4" id="newsletterSuccess">
                    <i class="bi bi-check-circle-fill" style="font-size: 2.5rem; color: var(--color-accent);"></i>
                    <h5 class="mt-3 mb-2 text-white"><?= lang('newsletter_thanks') ?></h5>
                    <p class="text-white-50 mb-0 newsletter-success-msg" style="font-size: 0.9rem;"><?= lang('newsletter_success_msg') ?></p>
                </div>
                <p class="newsletter-disclaimer mt-3 mb-0"><i class="bi bi-shield-check me-1"></i> <?= lang('newsletter_privacy') ?></p>
            </div>
        </div>
    </div>
</section>

<?php
$homepageProductCodes = [];
foreach (array_slice($featured, 0, 3) as $p) {
    $homepageProductCodes[] = ['code' => $p['code'], 'position' => count($homepageProductCodes) + 1];
}
foreach (array_slice($bestSellers, 0, 3) as $p) {
    $homepageProductCodes[] = ['code' => $p['code'], 'position' => count($homepageProductCodes) + 1];
}
foreach (array_slice($newArrivals, 0, 2) as $p) {
    $homepageProductCodes[] = ['code' => $p['code'], 'position' => count($homepageProductCodes) + 1];
}
foreach (array_slice($recommended, 0, 2) as $p) {
    $homepageProductCodes[] = ['code' => $p['code'], 'position' => count($homepageProductCodes) + 1];
}

if (count($homepageProductCodes) > 0):
?>
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"ItemList","name":"Champion Liquor Store","description":"Premium liquor and mini market products in Kigali, Rwanda","url":"<?= SITE_URL ?>","numberOfItems":<?= count($homepageProductCodes) ?>,"itemListElement":[<?php
$items = [];
foreach ($homepageProductCodes as $item):
    $url = SITE_URL . 'pages/product-details.php?code=' . urlencode($item['code']);
    $items[] = '{"@type":"ListItem","position":' . $item['position'] . ',"url":"' . htmlspecialchars($url) . '"';
endforeach;
echo implode(',', $items);
?>]}
</script>
<?php endif; ?>
