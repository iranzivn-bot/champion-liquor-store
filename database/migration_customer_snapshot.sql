-- ============================================================================
-- Migration: Customer Info Snapshot
-- ============================================================================
-- Adds columns to store customer info at the time of checkout,
-- so orders remain accurate even if the user later changes their profile.
--
-- Usage:
--   mysql -u root champion_store < migration_customer_snapshot.sql
-- ============================================================================

USE champion_store;

-- ─── Add snapshot columns ─────────────────────────────────────────────
ALTER TABLE orders
    ADD COLUMN customer_name    VARCHAR(100) NULL AFTER payment_status,
    ADD COLUMN customer_email   VARCHAR(255) NULL AFTER customer_name,
    ADD COLUMN customer_phone   VARCHAR(20)  NULL AFTER customer_email,
    ADD COLUMN customer_address TEXT         NULL AFTER customer_phone;

-- ─── Backfill existing orders from users table ────────────────────────
-- For orders placed before this migration, copy the user's current profile
-- as the snapshot. Future orders will capture values at checkout time.
UPDATE orders o
JOIN users u ON o.user_id = u.id
SET o.customer_name    = u.full_name,
    o.customer_email   = u.email,
    o.customer_phone   = o.shipping_phone,
    o.customer_address = o.shipping_address
WHERE o.customer_name IS NULL;
