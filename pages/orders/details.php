<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
$id = (int) ($_GET['id'] ?? 0);
redirect(SITE_URL . 'pages/dashboard/order-view.php?id=' . $id);
