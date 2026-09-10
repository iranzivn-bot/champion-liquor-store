<?php
/**
 * Front Controller / Entry Point
 *
 * All public requests route through this file.
 * Includes the home page by default.
 *
 * PHP 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

require_once __DIR__ . '/pages/home.php';

require_once __DIR__ . '/includes/footer.php';
