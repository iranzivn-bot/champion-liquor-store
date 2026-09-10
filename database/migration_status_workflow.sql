-- ============================================================================
-- Migration: Order Status Workflow
-- ============================================================================
-- Adds order_status_history table and cancel_reason column.
-- Run this after migration_orders.sql.
--
-- Usage:
--   mysql -u root champion_store < migration_status_workflow.sql
-- ============================================================================

USE champion_store;

-- ============================================================================
-- order_status_history - Tracks every status change for an order
-- ============================================================================
CREATE TABLE IF NOT EXISTS order_status_history (
    id          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    order_id    INT UNSIGNED      NOT NULL,
    status      VARCHAR(30)       NOT NULL,
    notes       VARCHAR(255)      NULL,
    created_at  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_osh_order_id (order_id),
    INDEX idx_osh_created_at (created_at),
    CONSTRAINT fk_osh_order
        FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Order status change history';

-- ============================================================================
-- cancel_reason - Stores the reason when an order is cancelled
-- ============================================================================
ALTER TABLE orders
    ADD COLUMN cancel_reason TEXT NULL AFTER notes;
