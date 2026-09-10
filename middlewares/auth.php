<?php
/**
 * Authentication Middleware
 *
 * Protects pages that require a logged-in user.
 *
 * How it works:
 * 1. Loads session and helper functions.
 * 2. Checks if the user is logged in (via isLoggedIn()).
 * 3. If NOT logged in: saves the current page URL, sets a flash message,
 *    and redirects to the login page.
 * 4. If logged in: does nothing — the page loads normally.
 *
 * Usage — Add this line at the TOP of any page that needs login:
 *   require_once __DIR__ . '/../middlewares/auth.php';
 *
 * PHP 8.3
 */

// Load dependencies
// Note: session.php must come before config.php because config.php
// uses require_once to load session.php internally.
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if the user is logged in
if (!isLoggedIn()) {

    // ── Remember where the user was trying to go ──────────────────────
    // After login, we can redirect them back to this page.
    $currentUrl = $_SERVER['REQUEST_URI'] ?? SITE_URL . 'index.php';
    $_SESSION['redirect_after_login'] = $currentUrl;

    // ── Show a friendly message ───────────────────────────────────────
    setFlashMessage('error', 'Please log in to access this page.');

    // ── Redirect to login ─────────────────────────────────────────────
    redirect(SITE_URL . 'pages/login.php');
}
