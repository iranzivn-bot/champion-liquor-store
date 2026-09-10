-- ============================================================================
-- Migration: Security Hardening
-- Adds columns for login rate limiting to the users table.
-- Run this once on your production database.
-- ============================================================================

ALTER TABLE users
    ADD COLUMN login_attempts  INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Consecutive failed login attempts; reset on successful login',
    ADD COLUMN lockout_until   DATETIME     NULL DEFAULT NULL
        COMMENT 'If set, user cannot log in until this timestamp';

-- ============================================================================
-- Audit: ensure we have an index for performance lookups on email
-- (already has UNIQUE KEY uk_users_email, so this is covered)
-- ============================================================================
