<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDbConnection();

$userId = null;
$sessionId = null;
if (function_exists('isLoggedIn') && isLoggedIn()) {
    $userId = (int) $_SESSION['user_id'];
} else {
    $sessionId = $_SESSION['compare_session_id'] ?? null;
}

$items = [];
if ($userId || $sessionId) {
    if ($userId) {
        $stmt = $pdo->prepare('
            SELECT c.id AS compare_id, c.product_id, c.created_at AS added_at,
                   p.code, p.name, p.price, p.image, p.stock_quantity, p.status, p.description,
                   p.category_id, p.brand_id
            FROM compare_list c
            JOIN products p ON c.product_id = p.id
            WHERE c.user_id = :uid
            ORDER BY c.created_at DESC
            LIMIT ' . COMPARE_MAX_ITEMS . '
        ');
        $stmt->execute([':uid' => $userId]);
    } else {
        $stmt = $pdo->prepare('
            SELECT c.id AS compare_id, c.product_id, c.created_at AS added_at,
                   p.code, p.name, p.price, p.image, p.stock_quantity, p.status, p.description,
                   p.category_id, p.brand_id
            FROM compare_list c
            JOIN products p ON c.product_id = p.id
            WHERE c.session_id = :sid
            ORDER BY c.created_at DESC
            LIMIT ' . COMPARE_MAX_ITEMS . '
        ');
        $stmt->execute([':sid' => $sessionId]);
    }
    $items = $stmt->fetchAll();
}

$compareCount = count($items);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="page-hero-sm">
    <div class="container">
        <h1 class="fw-bold" style="color: #C9A227;"><i class="bi bi-arrow-left-right me-2"></i> <?= lang('my_compare') ?></h1>
        <p class="mb-0 text-white-50"><?= lang('compare_max') ?></p>
    </div>
</div>

<section class="section-padding">
    <div class="container">

        <?php if ($compareCount === 0): ?>
            <div class="text-center py-5">
                <i class="bi bi-arrow-left-right" style="font-size: 4rem; color: #ccc;"></i>
                <h4 class="mt-3 text-muted"><?= lang('compare_empty') ?></h4>
                <a href="<?= SITE_URL ?>pages/shop.php" class="btn btn-primary btn-lg mt-3 px-5">
                    <i class="bi bi-grid me-2"></i> <?= lang('browse_products') ?>
                </a>
            </div>
        <?php else: ?>

            <div class="table-responsive">
                <table class="table table-bordered compare-table align-middle">
                    <tbody>
                        <!-- Image -->
                        <tr>
                            <th class="compare-label" style="width: 160px;"><?= lang('image') ?></th>
                            <?php foreach ($items as $item): ?>
                                <td class="compare-cell text-center" style="min-width: 220px;">
                                    <div class="position-relative">
                                        <button class="btn compare-remove position-absolute top-0 end-0 p-1 border-0"
                                                data-product-id="<?= (int) $item['product_id'] ?>"
                                                title="<?= lang('remove_from_compare') ?>"
                                                style="z-index: 2; background: none; color: #dc3545; font-size: 1.2rem;">
                                            <i class="bi bi-x-circle-fill"></i>
                                        </button>
                                        <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($item['code']) ?>">
                                            <img src="<?= $item['image'] ? SITE_URL . 'uploads/products/' . htmlspecialchars($item['image']) : DEFAULT_PRODUCT_IMAGE ?>"
                                                 alt="<?= htmlspecialchars($item['name']) ?>"
                                                 style="width: 180px; height: 180px; object-fit: contain;"
                                                 class="img-fluid">
                                        </a>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Name -->
                        <tr>
                            <th class="compare-label"><?= lang('product_name') ?></th>
                            <?php foreach ($items as $item): ?>
                                <td class="compare-cell text-center fw-semibold" style="color: #001F5B;">
                                    <a href="<?= SITE_URL ?>pages/product-details.php?code=<?= urlencode($item['code']) ?>"
                                       class="text-decoration-none" style="color: #001F5B;">
                                        <?= htmlspecialchars($item['name']) ?>
                                    </a>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Price -->
                        <tr>
                            <th class="compare-label"><?= lang('price') ?></th>
                            <?php foreach ($items as $item): ?>
                                <td class="compare-cell text-center">
                                    <span class="fw-bold" style="color: #C9A227; font-size: 1.2rem;">
                                        <?= number_format((float) $item['price'], 2) ?> RWF
                                    </span>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Stock -->
                        <tr>
                            <th class="compare-label"><?= lang('availability') ?></th>
                            <?php foreach ($items as $item): ?>
                                <td class="compare-cell text-center">
                                    <?php if ((int) $item['stock_quantity'] > 0): ?>
                                        <span class="badge bg-success"><?= lang('in_stock') ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><?= lang('out_of_stock') ?></span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Description -->
                        <tr>
                            <th class="compare-label"><?= lang('description') ?></th>
                            <?php foreach ($items as $item): ?>
                                <td class="compare-cell">
                                    <small><?= htmlspecialchars(mb_substr($item['description'] ?? 'N/A', 0, 200)) ?></small>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Add to Cart -->
                        <tr>
                            <th class="compare-label"><?= lang('add_to_cart') ?></th>
                            <?php foreach ($items as $item): ?>
                                <td class="compare-cell text-center">
                                    <?php if ((int) $item['stock_quantity'] > 0): ?>
                                        <button class="btn btn-primary add-to-cart-btn"
                                                data-product-id="<?= (int) $item['product_id'] ?>"
                                                data-product-name="<?= htmlspecialchars($item['name']) ?>">
                                            <i class="bi bi-cart-plus me-1"></i> <?= lang('add_to_cart') ?>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled>
                                            <i class="bi bi-cart-x me-1"></i> <?= lang('out_of_stock') ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Remove compare modal -->
            <div class="modal fade" id="compareRemoveModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-sm modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-0">
                            <h6 class="modal-title fw-bold"><?= lang('remove_from_compare') ?></h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body pt-0">
                            <p class="mb-0 small text-muted"><?= lang('confirm_remove_compare') ?></p>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal"><?= lang('cancel') ?></button>
                            <button type="button" class="btn btn-sm btn-danger" id="confirmCompareRemoveBtn"><?= lang('delete') ?></button>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
