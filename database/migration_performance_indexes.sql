-- ================================================================
-- Migration: Performance Indexes
--
-- Adds missing indexes for query optimization across all tables.
-- These indexes speed up JOINs, WHERE clauses, ORDER BY, GROUP BY,
-- and aggregation queries used in reports and analytics.
-- ================================================================

-- ─── Products ──────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_products_status ON products (status);
CREATE INDEX IF NOT EXISTS idx_products_stock_quantity ON products (stock_quantity);
CREATE INDEX IF NOT EXISTS idx_products_created_at ON products (created_at);

-- Composite index for product listing queries (status + stock)
CREATE INDEX IF NOT EXISTS idx_products_status_stock ON products (status, stock_quantity);

-- ─── Orders ────────────────────────────────────────────────
-- Already has: idx_orders_user_id, idx_orders_status, idx_orders_payment_status, idx_orders_created_at
-- Add composite for status-based aggregation
CREATE INDEX IF NOT EXISTS idx_orders_status_created ON orders (status, created_at);

-- Composite for user order history
CREATE INDEX IF NOT EXISTS idx_orders_user_created ON orders (user_id, created_at);

-- ─── Order Items ───────────────────────────────────────────
-- Already has: idx_order_items_order_id, idx_order_items_product_id
-- Already has composite: idx_order_items_order_product

-- ─── Order Status History ──────────────────────────────────
-- Already has: idx_osh_order_id, idx_osh_created_at

-- ─── Cart ──────────────────────────────────────────────────
-- Already has: idx_cart_user_id

-- ─── Wishlist ──────────────────────────────────────────────
-- Already has: idx_wishlist_user_id, idx_wishlist_product_id

-- ─── Inventory Movements ──────────────────────────────────
-- Already has: idx_im_product_id, idx_im_movement_type, idx_im_created_at, idx_im_reference

-- ─── Audit Logs ────────────────────────────────────────────
-- Already has: idx_audit_user_id, idx_audit_module, idx_audit_created

-- ─── Users ─────────────────────────────────────────────────
-- Already has composite: idx_users_role_created
CREATE INDEX IF NOT EXISTS idx_users_status ON users (status);
CREATE INDEX IF NOT EXISTS idx_users_created_at ON users (created_at);
