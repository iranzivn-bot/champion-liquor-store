<?php
/**
 * Permission Helper
 *
 * Provides RBAC functions for checking user permissions.
 * Relies on the permissions and role_permissions tables.
 *
 * Dependencies:
 *   - includes/config.php (for session + DB)
 *   - includes/db.php     (for getDbConnection)
 *   - includes/constants.php (for role constants)
 *
 * PHP 8.3
 */

/**
 * Check if the current logged-in user has a specific permission.
 *
 * Super Admin always has all permissions.
 * Customer has no admin permissions.
 * Admin permissions are loaded from role_permissions + user_permissions overrides.
 *
 * @param string $permission  Permission key (e.g. 'products.view')
 * @return bool
 */
function hasPermission(string $permission): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $role = $_SESSION['user_role'] ?? '';

    if ($role === SUPER_ADMIN_ROLE) {
        return true;
    }

    if ($role === CUSTOMER_ROLE) {
        return false;
    }

    // Load and cache effective permissions (role base + user overrides)
    if (!isset($_SESSION['_permissions'])) {
        try {
            $pdo = getDbConnection();
            $userId = (int) $_SESSION['user_id'];

            // 1. Get role-based permissions
            $stmt = $pdo->prepare('
                SELECT p.id, p.name
                FROM role_permissions rp
                JOIN permissions p ON p.id = rp.permission_id
                WHERE rp.role = :role
            ');
            $stmt->execute([':role' => $role]);
            $rolePerms = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // 2. Apply per-user overrides if user_permissions table exists
            try {
                $stmt = $pdo->prepare('
                    SELECT up.permission_id, up.granted, p.name
                    FROM user_permissions up
                    JOIN permissions p ON p.id = up.permission_id
                    WHERE up.user_id = :uid
                ');
                $stmt->execute([':uid' => $userId]);
                while ($row = $stmt->fetch()) {
                    $pid = (int) $row['permission_id'];
                    if ((bool) $row['granted']) {
                        $rolePerms[$pid] = $row['name'];
                    } else {
                        unset($rolePerms[$pid]);
                    }
                }
            } catch (\Throwable $e) {
                // user_permissions table may not exist yet — ignore overrides
            }

            $_SESSION['_permissions'] = array_values($rolePerms);
        } catch (\Throwable $e) {
            error_log('Permission lookup failed: ' . $e->getMessage());
            return false;
        }
    }

    return in_array($permission, $_SESSION['_permissions'], true);
}

/**
 * Check if the current user is a Super Admin.
 *
 * @return bool
 */
function isSuperAdmin(): bool
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === SUPER_ADMIN_ROLE;
}

if (!function_exists('isAdmin')) {
/**
 * Check if the current user is an Admin (including Super Admin).
 *
 * @return bool
 */
function isAdmin(): bool
{
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    return $_SESSION['user_role'] === ADMIN_ROLE || $_SESSION['user_role'] === SUPER_ADMIN_ROLE;
}
}

/**
 * Require a permission. If the user lacks it, redirect with error.
 *
 * @param string $permission  Permission key required.
 */
function requirePermission(string $permission): void
{
    if (!hasPermission($permission)) {
        setFlashMessage('error', 'Access denied. You do not have permission for this action.');
        // Redirect to admin dashboard if logged in as admin, otherwise to login
        $target = isset($_SESSION['user_id'])
            ? (SITE_URL . 'admin/dashboard.php')
            : (SITE_URL . 'pages/login.php');
        redirect($target);
    }
}

/**
 * Clear cached permissions (call after permission changes).
 */
function clearPermissionCache(): void
{
    unset($_SESSION['_permissions']);
}
