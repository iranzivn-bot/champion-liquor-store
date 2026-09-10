-- ============================================================================
-- Migration: Inventory Movements
-- ============================================================================
-- Tracks every stock change with before/after snapshots for full audit trail.
--
-- Usage:
--   mysql -u root -p champion_store < migration_inventory_movements.sql
-- ============================================================================

USE champion_store;

CREATE TABLE IF NOT EXISTS inventory_movements (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    product_id      INT UNSIGNED      NOT NULL,
    movement_type   ENUM('stock_in','stock_out','adjustment','order','order_cancelled')
                                      NOT NULL,
    quantity        INT               NOT NULL COMMENT 'Positive for additions, negative for reductions',
    stock_before    INT               NOT NULL DEFAULT 0,
    stock_after     INT               NOT NULL DEFAULT 0,
    reference_type  ENUM('manual','order','purchase','adjustment')
                                      NOT NULL DEFAULT 'manual',
    reference_id    INT UNSIGNED      NULL COMMENT 'order_id or NULL for manual',
    notes           TEXT              NULL,
    created_by      INT UNSIGNED      NOT NULL COMMENT 'Admin user who performed the action',
    created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_im_product_id (product_id),
    INDEX idx_im_movement_type (movement_type),
    INDEX idx_im_created_at (created_at),
    INDEX idx_im_reference (reference_type, reference_id),
    CONSTRAINT fk_im_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_im_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stock movement audit trail for all inventory changes';
