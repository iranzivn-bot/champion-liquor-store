<?php
/**
 * Health Check Endpoint
 *
 * Liveness probe for Render. Returns 200 as soon as the web server answers —
 * the DB comes up a few seconds later during first-boot bootstrap, so we do
 * not block the deploy. Use /healthz-db.php for a full DB check.
 * PHP 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status' => 'ok', 'env' => ENVIRONMENT]);