<?php
/**
 * Flash Messages Utility
 *
 * Functions for setting, retrieving, and clearing flash messages.
 *
 * What is a flash message?
 * A flash message is a one-time notification stored in the session.
 * It is shown to the user on the NEXT page load, then automatically
 * removed. This is perfect for "success" or "error" feedback after
 * a form submission or redirect.
 *
 * Dependencies:
 *   - includes/session.php (for $_SESSION access)
 *
 * PHP 8.3
 */

// ─── Set a flash message ───────────────────────────────────────────────

/**
 * Stores a message in the session to be displayed on the next page load.
 *
 * @param string $key      A unique name for the message (e.g., 'success', 'error').
 * @param string $message  The message text to display.
 *
 * @return void
 */
function setFlashMessage(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

// ─── Get a flash message ───────────────────────────────────────────────

/**
 * Retrieves a flash message and removes it from the session.
 * After calling this, the message will NOT appear again on refresh.
 *
 * @param string $key  The name of the message to retrieve.
 *
 * @return string|null  The message text, or NULL if no message exists with that key.
 */
function getFlashMessage(string $key): ?string
{
    if (isset($_SESSION['flash'][$key])) {
        $message = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $message;
    }
    return null;
}

// ─── Clear all flash messages ──────────────────────────────────────────

/**
 * Removes ALL flash messages from the session at once.
 * Useful when you want to discard old messages before setting new ones.
 *
 * @return void
 */
function clearFlashMessage(): void
{
    unset($_SESSION['flash']);
}
