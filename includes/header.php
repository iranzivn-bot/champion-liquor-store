<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getCurrentLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= lang('footer_about') ?>">
    <meta name="keywords" content="liquor store, wine, spirits, Rwanda, premium beverages, champion liquor">
    <meta name="author" content="<?= SITE_NAME ?>">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="<?= SITE_NAME ?> - <?= lang('footer_about') ?>">
    <meta property="og:description" content="<?= lang('footer_about') ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= SITE_URL ?>">
    <meta property="og:site_name" content="<?= SITE_NAME ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= SITE_NAME ?>">
    <meta name="twitter:description" content="<?= lang('footer_about') ?>">
    <link rel="canonical" href="<?= SITE_URL . ltrim($_SERVER['REQUEST_URI'] ?? '/', '/') ?>">

    <!-- hreflang links for SEO -->
    <?php
    $activeLangs = getActiveLanguages();
    $currentLang = getCurrentLanguage();
    $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
    foreach ($activeLangs as $code => $info):
    ?>
    <link rel="alternate" hreflang="<?= htmlspecialchars($code) ?>" href="<?= SITE_URL ?>index.php?lang=<?= htmlspecialchars($code) ?>">
    <?php endforeach; ?>
    <link rel="alternate" hreflang="x-default" href="<?= SITE_URL ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>assets/css/design-system.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>assets/css/style.css">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>favicon.ico">
    <link rel="apple-touch-icon" href="<?= SITE_URL ?>apple-touch-icon.png">

    <link rel="manifest" href="<?= SITE_URL ?>manifest.json">
    <meta name="theme-color" content="#001F5B">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= SITE_NAME ?>">

    <title><?= SITE_NAME ?></title>
</head>
<body>
