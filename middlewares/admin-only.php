<?php
/**
 * Admin-Only Middleware
 *
 * Protects pages that require admin-level access.
 *
 * How it works:
 * 1. First runs the auth middleware (user must be logged in).
 * 2. Checks if the logged-in user has the 'admin' role.
 * 3. If NOT admin: sets a flash message, shows a 403 Forbidden page, and stops.
 * 4. If admin: does nothing — the page loads normally.
 *
 * Usage — Add this line at the TOP of any admin-only page:
 *   require_once __DIR__ . '/../middlewares/admin-only.php';
 *
 * PHP 8.3
 */

// First, make sure the user is logged in
require_once __DIR__ . '/auth.php';

// Now check if the user has admin privileges
if (!isAdmin()) {

    // ── Show error message ────────────────────────────────────────────
    setFlashMessage('error', 'You do not have permission to access this area.');

    // ── Show a professional 403 page ──────────────────────────────────
    // The http_response_code() sets the actual HTTP status code.
    http_response_code(403);
    require_once __DIR__ . '/../errors/403.php';

    // Stop the script — the 403 page has its own layout. We should not
    // continue rendering the page that was originally requested.
    exit;
}
