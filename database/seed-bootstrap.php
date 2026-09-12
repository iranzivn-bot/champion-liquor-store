<?php
/**
 * Seed & Migration Bootstrap (standalone, container-only)
 *
 * Connects to the bundled MariaDB (unix socket during docker-bootstrap.sh's
 * temporary phase, or plain TCP for the running server), imports the full
 * schema on a fresh database, then applies outstanding idempotent migrations.
 *
 * Used by docker-bootstrap.sh (temp phase, socket).
 *
 * PHP 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

function seederOut(string $line): void
{
    echo $line . PHP_EOL;
}

/**
 * Return a PDO connection to the bundled MariaDB. Falls back from the unix
 * socket to plain TCP (localhost) so the same script works both during the
 * temporary (socket) bootstrap phase and against the live server.
 */
function seederConnection(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $socket = getenv('SEED_SOCKET') ?: '/run/mysqld/mysqld.sock';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $attempts = [
        'socket' => sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, DB_NAME, 'utf8mb4'),
        'tcp'    => sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, 'utf8mb4'),
    ];

    foreach ($attempts as $kind => $dsn) {
        try {
            $user = $kind === 'socket' ? 'root' : DB_USER;
            $pass = $kind === 'socket' ? ''      : DB_PASS;
            $pdo = new PDO($dsn, $user, $pass, $options);
            return $pdo;
        } catch (PDOException $e) {
            seederOut('  ! socket/TCP connect failed (' . $kind . '): ' . $e->getMessage());
        }
    }

    throw new RuntimeException('Unable to reach the bundled MariaDB server.');
}

// ---- wait for the server to accept connections (up to ~3 min) ----------
$connected = false;
for ($i = 0; $i < 180; $i++) {
    try {
        seederConnection()->query('SELECT 1');
        $connected = true;
        break;
    } catch (\Throwable $e) {
        sleep(1);
    }
}

if (!$connected) {
    seederOut('  ! database not reachable after 180s — aborting seed.');
    exit(1);
}

seederOut('  ✓ database reachable.');

// ---- schema (fresh database only) --------------------------------------
$hasUsers = (bool) seederConnection()->query("SHOW TABLES LIKE 'users'")->fetchColumn();
if (!$hasUsers) {
    seederOut('  → fresh database. importing full schema...');
    $seed = __DIR__ . '/champion_store.sql';
    if (!is_file($seed)) {
        seederOut('  ✗ champion_store.sql not found.');
        exit(1);
    }
    try {
        seederConnection()->exec(file_get_contents($seed));
        seederOut('    ✓ schema imported.');
    } catch (PDOException $e) {
        seederOut('    ✗ schema import failed: ' . $e->getMessage());
        exit(1);
    }
} else {
    seederOut('  → existing database detected. skipping schema import.');
}

// ---- migrations (idempotent) -------------------------------------------
seederOut('  → applying outstanding migrations...');
$migrate = __DIR__ . '/migrate.php';
if (is_file($migrate)) {
    require $migrate;
    if (function_exists('runMigrations')) {
        runMigrations(seederConnection());
    }
} else {
    seederOut('  ! migrate.php not found — skipping.');
}

seederOut('  ✓ seed & migrations complete.');