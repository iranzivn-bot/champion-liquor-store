<?php
/**
 * Database Health Check Endpoint
 *
 * Full readiness probe (used manually / by uptime monitors, not by Render's
 * boot gate): returns 200 only when the database is reachable.
 * PHP 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

try {
    $pdo = getDbConnection();
    $pdo->query('SELECT 1');
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok', 'env' => ENVIRONMENT, 'db' => 'up']);
} catch (\Throwable $e) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'degraded', 'error' => 'database unavailable']);
}