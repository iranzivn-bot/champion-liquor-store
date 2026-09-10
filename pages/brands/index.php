<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDbConnection();

// Fetch all active brands with product counts
$stmt = $pdo->query("
    SELECT b.*, COUNT(p.id) AS product_count
    FROM brands b
    LEFT JOIN products p ON p.brand_id = b.id AND p.status = 'active'
    WHERE b.status = 'active'
    GROUP BY b.id
    ORDER BY b.name ASC
");
$brands = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-hero-sm">
    <div class="container">
        <h1 class="fw-bold mb-1"><?= lang('brands') ?></h1>
        <p class="text-white-50 mb-0">Browse products by brand</p>
    </div>
</div>

<section class="section-padding">
    <div class="container">

        <?php if (count($brands) === 0): ?>
            <div class="text-center py-5">
                <i class="bi bi-tag" style="font-size: 3rem; color: #ccc;"></i>
                <p class="text-muted mt-3 mb-0">No brands available at this time.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($brands as $brand): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="<?= SITE_URL ?>pages/shop.php?brand=<?= urlencode($brand['slug']) ?>"
                           class="text-decoration-none">
                            <div class="brand-card">
                                <?php if ($brand['logo']): ?>
                                    <img src="<?= SITE_URL ?>uploads/brands/<?= htmlspecialchars($brand['logo']) ?>"
                                         class="brand-card-logo" alt="<?= htmlspecialchars($brand['name']) ?>">
                                <?php else: ?>
                                    <div class="brand-card-logo brand-card-placeholder">
                                        <i class="bi bi-building"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="brand-card-body">
                                    <h6 class="brand-card-name"><?= htmlspecialchars($brand['name']) ?></h6>
                                    <span class="brand-card-count"><?= (int) $brand['product_count'] ?> product<?= (int) $brand['product_count'] !== 1 ? 's' : '' ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
