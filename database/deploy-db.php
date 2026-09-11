<?php
/**
 * Deployment Database Bootstrap
 *
 * Used by the Docker/entrypoint flow on Render:
 *   1. Waits for the database to accept connections (up to ~60s) so the
 *      web service can boot reliably on Render where the DB may start late.
 *   2. Ensures the target database exists (CREATE DATABASE IF NOT EXISTS).
 *   3. On a FRESH database, imports the full schema (database/champion_store.sql)
 *      so the store works immediately.
 *   4. Always applies outstanding migration_*.sql files (idempotent).
 *
 * Run manually for local testing:
 *   php database/deploy-db.php
 *
 * PHP 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

function out(string $line): void
{
    echo $line . PHP_EOL;
}

// 1. Wait for the database to become reachable (retry loop)
out('');
out('=== Champion Liquor Store — Deployment DB Bootstrap ===');
out('');
out('  → Waiting for database ' . DB_HOST . ':' . DB_PORT . '...');
$loop = 0;
$connected = false;
while ($loop < 60) {
    $test = @fsockopen(DB_HOST, DB_PORT, $errno, $errstr, 2);
    if ($test !== false) {
        fclose($test);
        $connected = true;
        break;
    }
    $loop++;
    sleep(1);
}

if (!$connected) {
    out('  ! Database not reachable after 60s — continuing anyway (app will retry on request).');
} else {
    out('    ✓ Database reachable.');
}

// 2. Ensure database exists
if (DB_NAME !== '') {
    getDbConnection()->exec(sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        str_replace('`', '', DB_NAME)
    ));
    out('  ✓ Database ensured: ' . DB_NAME);
} else {
    out('  ! DB_NAME not set — skipping database creation.');
}

// 3. Check whether this is a fresh database
$isFresh = false;
$stmt = getDbConnection()->query("SHOW TABLES LIKE 'users'");
if ($stmt->rowCount() === 0) {
    $isFresh = true;
    out('  → Fresh database detected. Importing full schema...');
    $seed = __DIR__ . '/champion_store.sql';
    if (is_file($seed)) {
        $sql = file_get_contents($seed);
        $sql = str_replace('%DB_NAME%', str_replace('`', '', DB_NAME), $sql);
        try {
            getDbConnection()->exec($sql);
            out('    ✓ Schema imported.');
        } catch (PDOException $e) {
            out('    ✗ Schema import failed: ' . $e->getMessage());
        }
    } else {
        out('    ✗ champion_store.sql not found — skipping schema import.');
    }
} else {
    out('  → Existing database detected. Skipping schema import.');
}

// 4. Apply outstanding migrations (idempotent, tracks applied migrations)
out('');
out('  → Applying outstanding migrations...');
$migrate = __DIR__ . '/migrate.php';
if (is_file($migrate)) {
    require $migrate;
} else {
    out('  ! migrate.php not found — skipping migrations.');
}

out('');
out($isFresh ? 'Database bootstrap complete (fresh install).' : 'Database bootstrap complete (existing install).');
out('');