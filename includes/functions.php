<?php
/**
 * Helper Functions File
 *
 * Reusable utility functions used across the application.
 *
 * PHP 8.3
 */

declare(strict_types=1);

// ─── Redirect ─────────────────────────────────────────────────────────

/**
 * Redirect to a given URL.
 *
 * @param string $url
 * @return never
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ─── Authentication Helpers ───────────────────────────────────────────

if (!function_exists('isLoggedIn')) {
/**
 * Check if a user is currently logged in.
 *
 * @return bool
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}
}

if (!function_exists('isAdmin')) {
/**
 * Check if the logged-in user is an admin or super_admin.
 *
 * @return bool
 */
function isAdmin(): bool
{
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    return $_SESSION['user_role'] === ADMIN_ROLE || $_SESSION['user_role'] === SUPER_ADMIN_ROLE;
}
}

/**
 * Require authentication. Redirects to login if not logged in.
 *
 * @return void
 */
function requireAuth(): void
{
    if (!isLoggedIn()) {
        redirect(SITE_URL . 'pages/login.php');
    }
}

/**
 * Require admin authentication. Redirects to login if not admin.
 *
 * @return void
 */
function requireAdmin(): void
{
    if (!isAdmin()) {
        redirect(SITE_URL . 'pages/login.php');
    }
}

// ─── File Upload ──────────────────────────────────────────────────────

/**
 * Generate a unique filename for uploaded files.
 *
 * @param string $originalName
 * @return string
 */
function generateUniqueFilename(string $originalName): string
{
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid('file_', true) . '.' . $extension;
}

/**
 * Get a human-readable file size string.
 *
 * @param int $bytes
 * @param int $precision
 * @return string
 */
function formatFileSize(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}


