<?php
/**
 * Centralized Session Handler
 *
 * Sets secure session cookie parameters and starts the session.
 * Include this file at the very top of any page that needs session access.
 *
 * Why this file exists:
 * - Centralizes session configuration in one place.
 * - Sets secure cookie flags (httponly, samesite, secure) to prevent attacks.
 * - Regenerates session ID every 30 minutes to prevent session fixation.
 *
 * Usage:
 *   require_once __DIR__ . '/includes/session.php';
 *
 * PHP 8.3
 */

// ─── Session Cookie Security Settings ──────────────────────────────────
// These must be set BEFORE session_start() takes effect.

$cookieParams = [
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict',
];

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params($cookieParams);
    session_start();
}

// ─── Session ID Regeneration ───────────────────────────────────────────
// This prevents "session fixation" attacks where an attacker tricks a user
// into using a known session ID.
//
// We regenerate the ID every 30 minutes so stolen session cookies expire quickly.

$regenerateInterval = 1800; // 30 minutes in seconds

if (!isset($_SESSION['_last_regenerated'])) {
    if (headers_sent() === false) {
        session_regenerate_id(true);
    }
    $_SESSION['_last_regenerated'] = time();
} elseif (time() - $_SESSION['_last_regenerated'] > $regenerateInterval) {
    if (headers_sent() === false) {
        session_regenerate_id(true);
    }
    $_SESSION['_last_regenerated'] = time();
}

// ─── Session Idle Timeout ───────────────────────────────────────────────
// If the user is logged in but inactive for 30 minutes, log them out.
// This prevents stale sessions from being reused.

$idleTimeout = 1800;

if (isset($_SESSION['user_id'], $_SESSION['_last_activity']) && time() - $_SESSION['_last_activity'] > $idleTimeout) {
    $_SESSION = [];
    session_regenerate_id(true);
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 86400, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    header('Location: login.php?timeout=1');
    exit;
}

if (isset($_SESSION['user_id'])) {
    $_SESSION['_last_activity'] = time();
}
