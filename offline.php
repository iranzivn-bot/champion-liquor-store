<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5 text-center" style="min-height: 60vh; display: flex; align-items: center; justify-content: center;">
    <div>
        <i class="bi bi-wifi-off" style="font-size: 5rem; color: #C9A227; opacity: 0.5;"></i>
        <h1 class="fw-bold mt-4" style="color: #001F5B;">You're Offline</h1>
        <p class="text-muted mb-4">Please check your internet connection and try again.</p>
        <a href="<?= SITE_URL ?>" class="btn btn-gold btn-lg"><i class="bi bi-arrow-clockwise me-1"></i> Try Again</a>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
