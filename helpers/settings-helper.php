<?php
/**
 * Settings Helper
 *
 * Provides functions to get and update system settings.
 * Settings are cached in a static variable for the duration of the request.
 *
 * Dependencies:
 *   - includes/config.php
 *   - includes/db.php (for getDbConnection)
 *
 * PHP 8.3
 */

/**
 * Get a setting value by key.
 *
 * If the key does not exist, returns $default.
 * All settings are loaded once per request and cached.
 *
 * Usage:
 *   $siteName = setting('site_name');
 *   $taxRate  = setting('default_tax_rate', '5.00');
 *
 * @param string $key     The setting key.
 * @param mixed  $default Default value if key is not found.
 * @return string|null
 */
function setting(string $key, mixed $default = null): mixed
{
    static $settings = null;

    if ($settings === null) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (\Throwable $e) {
            error_log('Failed to load settings: ' . $e->getMessage());
            $settings = [];
        }
    }

    return $settings[$key] ?? $default;
}

/**
 * Update a setting value in the database.
 *
 * Logs the change to the audit log if a user is logged in.
 * Does NOT clear the static cache — call setting() again after update
 * if you need the new value within the same request.
 *
 * @param string $key   The setting key.
 * @param string $value The new value.
 * @return bool  TRUE on success.
 */
function updateSetting(string $key, string $value): bool
{
    try {
        $pdo = getDbConnection();

        // Fetch old value for audit log
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :key');
        $stmt->execute([':key' => $key]);
        $oldValue = $stmt->fetchColumn();

        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $userName = $_SESSION['user_name'] ?? 'System';
        $userRole = $_SESSION['user_role'] ?? '';

        // Upsert: update if exists, insert if not
        $stmt = $pdo->prepare('
            INSERT INTO settings (setting_key, setting_value, updated_by)
            VALUES (:key, :value, :updated_by)
            ON DUPLICATE KEY UPDATE setting_value = :value2, updated_by = :updated_by2
        ');
        $stmt->execute([
            ':key'         => $key,
            ':value'       => $value,
            ':updated_by'  => $userId,
            ':value2'      => $value,
            ':updated_by2' => $userId,
        ]);

        // Audit log
        if ($userId && $oldValue !== false && $oldValue !== $value) {
            $detail = sprintf(
                'Setting "%s" changed: "%s" → "%s"',
                $key,
                mb_substr($oldValue ?? '(empty)', 0, 100),
                mb_substr($value, 0, 100)
            );
            logActivity($userId, $userName, $userRole, 'settings', 'updated', $key, $detail);
        }

        return true;
    } catch (\Throwable $e) {
        error_log('Failed to update setting "' . $key . '": ' . $e->getMessage());
        return false;
    }
}

/**
 * Get all settings grouped by their group name.
 *
 * @return array  e.g. ['general' => [['key'=>'site_name','value'=>'...'], ...], ...]
 */
function getGroupedSettings(): array
{
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query('SELECT setting_key, setting_value, setting_group, description FROM settings ORDER BY setting_group, setting_key');
        $rows = $stmt->fetchAll();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['setting_group']][] = $row;
        }
        return $grouped;
    } catch (\Throwable $e) {
        error_log('Failed to load grouped settings: ' . $e->getMessage());
        return [];
    }
}
