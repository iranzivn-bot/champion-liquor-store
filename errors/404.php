<?php
/**
 * 404 Not Found Error Page
 *
 * Displayed when a user tries to access a page that doesn't exist.
 *
 * PHP 8.3
 */

// Set the HTTP status code to 404 Not Found
http_response_code(404);

// Load the site configuration for SITE_URL, SITE_NAME, etc.
require_once __DIR__ . '/../includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found — <?= SITE_NAME ?></title>

    <!-- Bootstrap 5 CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom styles -->
    <link rel="stylesheet" href="<?= SITE_URL ?>assets/css/style.css">

    <style>
        .error-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
        }
        .error-card {
            text-align: center;
            padding: 3rem;
            max-width: 550px;
        }
        .error-code {
            font-size: 7rem;
            font-weight: 900;
            color: #C9A227;
            line-height: 1;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 8px rgba(0,0,0,0.1);
        }
        .error-icon {
            font-size: 4rem;
            color: #001F5B;
            margin-bottom: 1rem;
        }
        .error-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #001F5B;
            margin-bottom: 0.75rem;
        }
        .error-message {
            font-size: 1.05rem;
            color: #6c757d;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-card">
            <div class="error-icon">
                <i class="bi bi-emoji-frown icon-lg" style="font-size: 5rem;"></i>
            </div>
            <div class="error-code">404</div>
            <h1 class="error-title">Page Not Found</h1>
            <p class="error-message">
                The page you're looking for doesn't exist or has been moved.
                Check the URL or head back to the homepage.
            </p>
            <div class="d-flex justify-content-center gap-3">
                <a href="<?= SITE_URL ?>index.php" class="btn btn-primary px-4 py-2 fw-semibold" style="background: #001F5B; border-color: #001F5B;">
                    <i class="bi bi-house-door-fill me-1"></i> Home
                </a>
                <a href="<?= SITE_URL ?>pages/shop.php" class="btn btn-outline-secondary px-4 py-2 fw-semibold">
                    <i class="bi bi-shop me-1"></i> Browse Products
                </a>
            </div>
        </div>
    </div>
</body>
</html>
