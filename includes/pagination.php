<?php
declare(strict_types=1);

/**
 * Reusable Pagination Partial
 *
 * Variables expected (must be set before including this file):
 *   $currentPage  (int) - Current page number (1-based)
 *   $totalPages   (int) - Total number of pages
 *
 * Usage:
 *   $currentPage = 3;
 *   $totalPages  = 10;
 *   require __DIR__ . '/includes/pagination.php';
 */

if (!isset($currentPage) || !isset($totalPages) || $totalPages <= 1) {
    return;
}

$currentPage = max(1, (int) $currentPage);
$totalPages  = max(1, (int) $totalPages);
?>

<nav class="mt-5">
    <ul class="pagination justify-content-center mb-0">
        <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage - 1])) ?>">Previous</a>
        </li>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= ($i === $currentPage) ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage + 1])) ?>">Next</a>
        </li>
    </ul>
</nav>
