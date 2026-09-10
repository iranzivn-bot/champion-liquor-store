<?php
/**
 * Authentication Helper Functions
 *
 * Reusable functions for checking user authentication status.
 *
 * Why this file exists:
 * These functions duplicate the ones in includes/functions.php but use
 * the new constants (ADMIN_ROLE, CUSTOMER_ROLE) instead of hardcoded
 * strings. Both files work — this one is the "new way" going forward.
 *
 * Dependencies:
 *   - includes/config.php (for SITE_URL, via session)
 *   - includes/session.php (for $_SESSION access)
 *   - includes/constants.php (for ADMIN_ROLE, CUSTOMER_ROLE)
 *
 * PHP 8.3
 */

// ─── Check if a user is logged in ──────────────────────────────────────
/**
 * Returns TRUE if a user is currently logged in.
 * We check for 'user_id' in the session — if it exists, someone is logged in.
 *
 * @return bool
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

// ─── Check if the user is an admin ─────────────────────────────────────
/**
 * Returns TRUE if the logged-in user has the admin or super_admin role.
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

// ─── Check if the user is a customer ───────────────────────────────────
/**
 * Returns TRUE if the logged-in user has the customer role.
 * Useful for showing/hiding customer-specific features.
 *
 * @return bool
 */
function isCustomer(): bool
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === CUSTOMER_ROLE;
}

// ─── Login Rate Limiting ──────────────────────────────────────────────
/**
 * Check if a user is currently locked out due to too many failed attempts.
 *
 * Returns an error message string if locked out, or null if login is allowed.
 *
 * @param PDO    $pdo
 * @param string $email
 * @return string|null
 */
function checkLoginLockout(PDO $pdo, string $email): ?string
{
    $stmt = $pdo->prepare('SELECT login_attempts, lockout_until FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();

    if (!$row) {
        return null; // unknown email — no lockout to enforce
    }

    $lockoutUntil = $row['lockout_until'] ?? null;
    if ($lockoutUntil !== null) {
        $now = new DateTimeImmutable();
        $lockout = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $lockoutUntil);
        if ($lockout && $now < $lockout) {
            $remaining = $now->diff($lockout);
            $minutes = $remaining->i + ($remaining->h * 60) + ($remaining->days * 1440);
            return "Too many failed login attempts. Please try again in {$minutes} minute(s).";
        }
    }

    return null;
}

/**
 * Record a failed login attempt for the given email.
 * After MAX_ATTEMPTS (5) failures, lock the account for LOCKOUT_DURATION (15) minutes.
 *
 * @param PDO    $pdo
 * @param string $email
 */
function recordFailedLogin(PDO $pdo, string $email): void
{
    $maxAttempts = 3;
    $lockoutMinutes = 15;

    $stmt = $pdo->prepare('UPDATE users SET login_attempts = login_attempts + 1 WHERE email = :email');
    $stmt->execute([':email' => $email]);

    $stmt = $pdo->prepare('SELECT login_attempts FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $attempts = (int) $stmt->fetchColumn();

    if ($attempts >= $maxAttempts) {
        $lockoutUntil = (new DateTimeImmutable())->modify("+{$lockoutMinutes} minutes")->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare('UPDATE users SET lockout_until = :lockout_until WHERE email = :email');
        $stmt->execute([':lockout_until' => $lockoutUntil, ':email' => $email]);
    }
}

/**
 * Reset login attempts and lockout on successful login.
 *
 * @param PDO    $pdo
 * @param string $email
 */
function resetLoginAttempts(PDO $pdo, string $email): void
{
    $stmt = $pdo->prepare('UPDATE users SET login_attempts = 0, lockout_until = NULL WHERE email = :email');
    $stmt->execute([':email' => $email]);
}
