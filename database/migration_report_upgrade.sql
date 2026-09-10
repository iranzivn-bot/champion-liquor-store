-- ============================================================================
-- Migration: Report System Upgrade
--
-- Adds cost_price for profit reporting + performance indexes.
-- ============================================================================

USE champion_store;

-- ─── 1. Add cost_price to products ──────────────────────────────────────
ALTER TABLE products
  ADD COLUMN cost_price DECIMAL(10,2) NULL DEFAULT NULL AFTER discount_price;

-- ─── 2. Performance indexes for report queries ──────────────────────────
-- These are safe to execute — MySQL ignores CREATE INDEX if the index
-- already exists (no error, just a warning).

-- Composite index for date-range + status filtering (e.g. revenue by period)
CREATE INDEX idx_orders_created_at_status
  ON orders (created_at, status);

-- For payment_status filtering in financial reports
CREATE INDEX idx_orders_payment_status
  ON orders (payment_status);

-- Composite index for joining orders -> items -> products
CREATE INDEX idx_order_items_order_product
  ON order_items (order_id, product_id);

-- For customer segmentation queries
CREATE INDEX idx_users_role_created
  ON users (role, created_at);

-- ─── 3. Backfill sample cost_price for existing products ────────────────
-- Sets cost_price to ~60% of selling price as a reasonable default
UPDATE products
  SET cost_price = ROUND(price * 0.60, 2)
  WHERE cost_price IS NULL;
