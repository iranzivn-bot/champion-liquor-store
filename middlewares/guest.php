<?php
/**
 * Guest-Only Middleware
 *
 * Prevents authenticated users from accessing guest-only pages
 * like login, register, and forgot-password.
 *
 * How it works:
 * 1. Loads session and helper functions.
 * 2. If the user IS already logged in, redirects them to the homepage.
 * 3. If NOT logged in: does nothing — the page loads normally.
 *
 * Why this matters:
 * - If a logged-in user visits the login page, they probably clicked the
 *   link by accident. Redirecting to the homepage is user-friendly.
 * - It also prevents confusion (e.g., seeing a "Login" form while already
 *   logged in).
 *
 * Usage — Add this line at the TOP of guest-only pages:
 *   require_once __DIR__ . '/../middlewares/guest.php';
 *
 * PHP 8.3
 */

// Load dependencies
// Note: session.php must come before config.php because config.php
// uses require_once to load session.php internally.
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if the user is already logged in
if (isLoggedIn()) {
    // Already authenticated — send them to the homepage
    redirect(SITE_URL . 'index.php');
}
