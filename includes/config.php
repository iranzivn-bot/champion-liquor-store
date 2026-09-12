<?php
/**
 * Configuration File
 *
 * Contains global constants and settings for the
 * Champion Liquor Store Ltd ecommerce application.
 *
 * PHP 8.3
 */

// ─── Site Configuration ───────────────────────────────────────────────
define('SITE_NAME', 'Champion Liquor Store Ltd');
define('SITE_EMAIL', 'info@championliquorstore.com');

// Dynamically detect base URL.
// Uses __DIR__ relative to DOCUMENT_ROOT so SITE_URL always points to the
// project root, regardless of which script includes config.php.
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$docRoot = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $_SERVER['DOCUMENT_ROOT'] ?? '');
$projectDir = dirname(__DIR__); // One level up from includes/ -> project root

if ($docRoot !== '' && str_starts_with($projectDir, $docRoot)) {
    // Project is inside the document root — compute relative path
    $urlPath = str_replace([$docRoot, '\\'], ['', '/'], $projectDir);
} else {
    // Fallback: use SCRIPT_NAME (may break in subdirectories)
    $urlPath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
}
define('SITE_URL', $protocol . '://' . $host . $urlPath . '/');

// ─── Database Credentials ─────────────────────────────────────────────
// Priority: 1) DATABASE_URL/INTERNAL_DATABASE_URL (Render provides these)
//           2) individual DB_* environment variables
//           3) local defaults below
$dbUrl = getenv('INTERNAL_DATABASE_URL') ?: getenv('DATABASE_URL');
if ($dbUrl !== false && $dbUrl !== '') {
    // Format: mysql://user:password@host:port/dbname
    $dbParsed = parse_url($dbUrl);
    if ($dbParsed !== false && isset($dbParsed['host'])) {
        define('DB_HOST', $dbParsed['host']);
        define('DB_NAME', ltrim($dbParsed['path'] ?? '', '/') ?: 'champion_store');
        define('DB_USER', rawurldecode($dbParsed['user'] ?? 'root'));
        define('DB_PASS', rawurldecode($dbParsed['pass'] ?? ''));
        define('DB_PORT', $dbParsed['port'] ?? 3306);
        define('DB_CHARSET', 'utf8mb4');
    }
}
if (!defined('DB_HOST')) {
    // Fall back to the bundled-MariaDB env vars (Render single-container),
    // then to plain defaults for local development.
    define('DB_HOST', getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: 'localhost'));
    define('DB_NAME', getenv('DB_NAME') ?: (getenv('MYSQL_DATABASE') ?: 'champion_store'));
    define('DB_USER', getenv('DB_USER') ?: (getenv('MYSQL_USER') ?: 'root'));
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('MYSQL_PASSWORD') ?: ''));
    define('DB_PORT', getenv('DB_PORT') ?: (getenv('MYSQL_PORT') ?: 3306));
    define('DB_CHARSET', 'utf8mb4');
}

// ─── Paths ────────────────────────────────────────────────────────────
define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('INCLUDES_PATH', BASE_PATH . 'includes' . DIRECTORY_SEPARATOR);
define('ADMIN_PATH', BASE_PATH . 'admin' . DIRECTORY_SEPARATOR);
define('UPLOADS_PATH', BASE_PATH . 'uploads' . DIRECTORY_SEPARATOR);

// ─── Environment ──────────────────────────────────────────────────────
// ENVIRONMENT can be set by environment variable or falls back to file check.
// Priority: getenv('APP_ENV') > FILENAME check > default 'development'
$envFile = BASE_PATH . '.env';
$appEnv  = getenv('APP_ENV');

if ($appEnv !== false && $appEnv !== '') {
    define('ENVIRONMENT', $appEnv);
} elseif (file_exists($envFile) && trim((string) @file_get_contents($envFile)) === 'production') {
    define('ENVIRONMENT', 'production');
} else {
    define('ENVIRONMENT', 'development');
}

if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// ─── Upload Limits ────────────────────────────────────────────────────
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '12M');

// ─── Error Logging ────────────────────────────────────────────────────
// Centralized error/exception handler. Logs to storage/logs/errors/.
// Must come before any code that could produce output.
require_once __DIR__ . '/error-handler.php';

// ─── Session ──────────────────────────────────────────────────────────
// Use the centralized session handler for secure cookie settings
// and automatic session ID regeneration.
require_once __DIR__ . '/session.php';

// ─── Constants ────────────────────────────────────────────────────────
// Application-wide constants (roles, statuses, paths, defaults).
// Must come after session.php because constants.php depends on paths
// defined above (UPLOADS_PATH, SITE_URL).
require_once __DIR__ . '/constants.php';

// ─── Helpers ──────────────────────────────────────────────────────────
// Application-wide helper functions.
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/auth-helper.php';
require_once __DIR__ . '/../helpers/validation-helper.php';
require_once __DIR__ . '/../helpers/permission-helper.php';
require_once __DIR__ . '/../helpers/settings-helper.php';
require_once __DIR__ . '/../helpers/language-helper.php';
require_once __DIR__ . '/../helpers/format-helper.php';
require_once __DIR__ . '/../helpers/audit-helper.php';

// ─── Security ─────────────────────────────────────────────────────────
// Input sanitization, CSRF token generation & validation.
// Loaded globally so it's available on every page.
require_once __DIR__ . '/security.php';

// ─── Database ────────────────────────────────────────────────────────
// Defines getDbConnection() singleton PDO.
// Required here because helpers/language-helper.php and the language
// switcher below need DB access to validate language codes.
require_once __DIR__ . '/db.php';

// ─── Flash Messages ──────────────────────────────────────────────────
// setFlashMessage / getFlashMessage / clearFlashMessage are used
// extensively across all pages. Load globally.
require_once __DIR__ . '/messages.php';

// ─── Language Switcher ───────────────────────────────────────────────
// If a language code is passed via GET (?lang=fr), set it immediately.
if (isset($_GET['lang'])) {
    setLanguage($_GET['lang']);
    // Redirect to remove ?lang= from the URL to prevent accidental re-sets
    $redirectUrl = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    header('Location: ' . $redirectUrl);
    exit;
}

// ─── HTTPS Redirect ──────────────────────────────────────────────────
// In production, redirect all HTTP traffic to HTTPS — except health-check
// endpoints, which Render probes over plain HTTP to the web port.
if (ENVIRONMENT === 'production' && PHP_SAPI !== 'cli') {
    $healthz = strpos($_SERVER['REQUEST_URI'] ?? '', '/healthz') === 0;
    if (!$healthz && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
        $redirectUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '');
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// ─── Security Headers ────────────────────────────────────────────────
// Send security-related HTTP headers on every page.
if (PHP_SAPI !== 'cli') {
    addSecurityHeaders();
}

// ─── Maintenance Mode ─────────────────────────────────────────────────
// If maintenance.lock exists, non-admin visitors see a maintenance page.
$maintenanceLock = BASE_PATH . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'maintenance.lock';
if (file_exists($maintenanceLock) && ENVIRONMENT === 'production') {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $isAdminRoute = str_contains($requestUri, '/admin/');
    $isLoginPage = str_contains($requestUri, 'login.php');
    if (!$isAdminRoute && !$isLoginPage) {
        require_once __DIR__ . '/../errors/503.php';
        exit;
    }
}
