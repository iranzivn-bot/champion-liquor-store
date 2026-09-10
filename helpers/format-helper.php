<?php
/**
 * Format Helper Functions
 *
 * Reusable functions for formatting prices, dates, and text.
 *
 * Why this file exists:
 * Centralizes display formatting so every page doesn't repeat the same logic.
 * Uses the CURRENCY constant instead of hardcoded values.
 *
 * Dependencies:
 *   - includes/constants.php (for CURRENCY)
 *
 * PHP 8.3
 */

// ─── Format a price for display ────────────────────────────────────────
/**
 * Converts a number like 1500.5 into formatted currency (e.g. "RWF 1,500.50").
 *
 * @param float $amount  The price to format.
 * @return string        Formatted price with currency symbol.
 */
function formatPrice(float $amount): string
{
    return number_format($amount, 2) . ' ' . CURRENCY;
}

// ─── Format a date for display ─────────────────────────────────────────
/**
 * Converts a MySQL datetime or date string into a human-readable format.
 *
 * Examples:
 *   formatDate('2026-06-22 14:30:00') returns "June 22, 2026"
 *   formatDate('2026-06-22')          returns "June 22, 2026"
 *
 * @param string $dateStr     A date/datetime string (any format PHP can parse).
 * @param string $format      The output format (default: "F j, Y").
 * @return string             Formatted date string.
 */
function formatDate(string $dateStr, string $format = 'F j, Y'): string
{
    $timestamp = strtotime($dateStr);
    if ($timestamp === false) {
        return '—'; // Return a dash if the date is invalid
    }
    return date($format, $timestamp);
}

// ─── Truncate text to a maximum length ─────────────────────────────────
/**
 * Shortens long text and adds "..." at the end.
 * Useful for product descriptions in list views.
 *
 * Example:
 *   truncateText("This is a very long description", 10)
 *   returns "This is a ..."
 *
 * @param string $text    The text to truncate.
 * @param int    $maxLen  Maximum number of characters (default: 100).
 * @return string         Truncated text with "..." if it was trimmed.
 */
function truncateText(string $text, int $maxLen = 100): string
{
    if (mb_strlen($text) <= $maxLen) {
        return $text;
    }

    // Cut at the max length and find the last space to avoid breaking words
    $truncated = mb_substr($text, 0, $maxLen);
    $lastSpace = mb_strrpos($truncated, ' ');

    if ($lastSpace !== false) {
        $truncated = mb_substr($truncated, 0, $lastSpace);
    }

    return $truncated . '...';
}
