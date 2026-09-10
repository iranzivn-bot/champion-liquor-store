<?php
http_response_code(503);
header('Retry-After: 3600');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 &middot; Maintenance &middot; Champion Liquor Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f8f9fa; display: flex; align-items: center; min-height: 100vh; }
        .icon-maintenance { font-size: 5rem; color: #C9A227; }
    </style>
</head>
<body>
    <div class="container text-center">
        <div class="icon-maintenance"><i class="bi bi-tools"></i></div>
        <h1 class="mt-3 fw-bold" style="color:#001F5B;">We'll Be Right Back</h1>
        <p class="text-muted mb-4">We're performing scheduled maintenance. Please check back shortly.</p>
        <a href="<?= defined('SITE_URL') ? SITE_URL : '/' ?>" class="btn btn-primary btn-lg px-4" style="background:#001F5B;border-color:#001F5B;">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </a>
    </div>
</body>
</html>
