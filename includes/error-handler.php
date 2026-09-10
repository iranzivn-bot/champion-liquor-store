<?php
declare(strict_types=1);

/**
 * Error & Exception Handler
 *
 * Central error and exception handler for the application.
 * Logs all errors to storage/logs/ and shows friendly messages
 * to users in production mode.
 *
 * PHP 8.3
 */

// ─── Ensure logs directory exists ──────────────────────────
$logDir = __DIR__ . '/../storage/logs';
$errDir = $logDir . '/errors';

if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}
if (!is_dir($errDir)) {
    mkdir($errDir, 0775, true);
}

// Log file path
define('ERROR_LOG_FILE', $errDir . '/application.log');

// ─── Set PHP error logging directives ─────────────────────
ini_set('log_errors', '1');
ini_set('error_log', ERROR_LOG_FILE);
ini_set('log_errors_max_len', '1024');

// ─── Custom error handler ─────────────────────────────────
set_error_handler(function (
    int    $severity,
    string $message,
    string $file,
    int    $line
): bool {
    $level = match ($severity) {
        E_WARNING, E_USER_WARNING       => 'WARNING',
        E_NOTICE, E_USER_NOTICE,
        E_DEPRECATED, E_USER_DEPRECATED => 'NOTICE',
        E_STRICT                         => 'STRICT',
        default                          => 'ERROR',
    };

    $log = sprintf(
        "[%s] %s: %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $level,
        $message,
        $file,
        $line
    );
    error_log($log, 3, ERROR_LOG_FILE);

    // In production, suppress errors from display
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        return true;
    }
    return false;
});

// ─── Custom exception handler ─────────────────────────────
set_exception_handler(function (Throwable $e): void {
    $log = sprintf(
        "[%s] UNCAUGHT EXCEPTION: %s in %s:%d\nStack trace:\n%s\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log($log, 3, ERROR_LOG_FILE);

    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        http_response_code(500);
        require_once __DIR__ . '/../errors/500.php';
        exit;
    }
    throw $e;
});
