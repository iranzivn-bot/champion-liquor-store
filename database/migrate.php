<?php
/**
 * Database Migration Runner
 *
 * Applies all pending .sql migration files from the database/ directory.
 * Tracks which migrations have been applied in a `migrations` table.
 *
 * Usage:
 *   php database/migrate.php
 *
 * The full schema (champion_store.sql) is excluded — that file is for
 * fresh installs only. Only migration_*.sql files are processed.
 *
 * PHP 8.3
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// ─── Helpers ──────────────────────────────────────────────────────────

function writeln(string $line): void
{
    echo $line . PHP_EOL;
}

function runMigration(PDO $pdo, string $filepath, string $filename): bool
{
    $sql = file_get_contents($filepath);
    if ($sql === false || trim($sql) === '') {
        writeln("  ⚠ Skipping {$filename} — empty or unreadable.");
        return false;
    }

    try {
        $pdo->exec($sql);
        return true;
    } catch (PDOException $e) {
        // Ignore "already exists" errors — column/table/key was already applied
        $driverCode = $e->errorInfo[1] ?? null;
        $ignoreCodes = [1060, 1061, 1062, 1091];
        if ($driverCode !== null && in_array((int) $driverCode, $ignoreCodes, true)) {
            writeln("    ✓ Already applied (skipped).");
            return true;
        }
        writeln("  ✗ Error in {$filename}: " . $e->getMessage());
        return false;
    }
}

// ─── Main ─────────────────────────────────────────────────────────────

writeln('');
writeln('═══ Champion Liquor Store — Migration Runner ═══');
writeln('');

$pdo = getDbConnection();

// 1. Ensure migrations tracking table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
        migration   VARCHAR(255)    NOT NULL,
        batch       INT UNSIGNED    NOT NULL DEFAULT 1,
        executed_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_migrations_name (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='Track applied database migrations'
");

// 2. Get already-applied migrations
$applied = [];
$stmt = $pdo->query("SELECT migration FROM migrations ORDER BY migration ASC");
while ($row = $stmt->fetch()) {
    $applied[$row['migration']] = true;
}

// 3. Discover migration files
$files = glob(__DIR__ . '/migration_*.sql');
sort($files);

if (empty($files)) {
    writeln('No migration files found in database/ directory.');
    writeln('');
    exit(0);
}

// 4. Determine batch number
$stmt = $pdo->query("SELECT COALESCE(MAX(batch), 0) FROM migrations");
$batch = (int) $stmt->fetchColumn() + 1;

$pending = 0;
$appliedCount = 0;
$errorCount   = 0;

foreach ($files as $filepath) {
    $filename = basename($filepath);

    if (isset($applied[$filename])) {
        writeln("  ✓ {$filename} — already applied");
        $appliedCount++;
        continue;
    }

    writeln("  → {$filename} — applying...");
    $pending++;

    if (runMigration($pdo, $filepath, $filename)) {
        $stmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (:m, :b)");
        $stmt->execute([':m' => $filename, ':b' => $batch]);
        writeln("    ✓ Done.");
    } else {
        $errorCount++;
    }
}

// 5. Summary
writeln('');
writeln('═══ Summary ═══');
writeln("  Already applied: {$appliedCount}");
writeln("  Newly applied:   {$pending}");
writeln("  Errors:          {$errorCount}");

if ($errorCount > 0) {
    writeln('');
    writeln('⚠ Some migrations failed. Check the errors above and fix manually.');
    exit(1);
}

writeln('');
writeln('Migration complete.');
writeln('');
