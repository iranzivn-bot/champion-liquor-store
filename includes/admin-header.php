<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getCurrentLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= SITE_NAME ?> Admin Panel">
    <title><?= htmlspecialchars($pageTitle ?? lang('dashboard')) ?> | <?= SITE_NAME ?> Admin</title>

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Admin Stylesheet -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>assets/css/admin.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>assets/css/design-system.css">
</head>
<body>
<div class="admin-layout">
