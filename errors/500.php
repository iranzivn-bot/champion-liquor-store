<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 &middot; Server Error &middot; Champion Liquor Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f8f9fa; display: flex; align-items: center; min-height: 100vh; }
        .error-code { font-size: 6rem; font-weight: 800; color: #C9A227; line-height: 1; }
    </style>
</head>
<body>
    <div class="container text-center">
        <div class="error-code">500</div>
        <h1 class="mt-3 fw-bold" style="color:#001F5B;">Internal Server Error</h1>
        <p class="text-muted mb-4">Something went wrong on our end. The team has been notified.</p>
        <a href="<?= defined('SITE_URL') ? SITE_URL : '/' ?>" class="btn btn-primary btn-lg px-4" style="background:#001F5B;border-color:#001F5B;">
            <i class="bi bi-house-door"></i> Back to Home
        </a>
    </div>
</body>
</html>
