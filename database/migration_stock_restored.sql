-- ============================================================================
-- Migration: stock_restored guard column
-- ============================================================================
-- Prevents duplicate stock restoration if an already-cancelled order
-- is somehow processed again.
--
-- Usage:
--   mysql -u root champion_store < migration_stock_restored.sql
-- ============================================================================

USE champion_store;

ALTER TABLE orders
    ADD COLUMN stock_restored TINYINT(1) NOT NULL DEFAULT 0
    AFTER cancel_reason;
