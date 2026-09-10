-- ============================================================================
-- Migration: Orders & Order Items
-- ============================================================================
-- Adds the orders and order_items tables needed for the checkout module.
-- Run this against your existing champion_store database.
--
-- Usage:
--   mysql -u root -p champion_store < migration_orders.sql
-- ============================================================================

USE champion_store;

-- ============================================================================
-- 9. orders - Stores customer orders placed through checkout
-- ============================================================================
-- order_number:    Auto-generated format CLS-2026-XXXXXX (year + sequential).
-- status:          pending → confirmed → processing → shipped → delivered | cancelled
-- payment_status:  unpaid → paid | refunded
-- payment_method:  cod (Cash on Delivery), bank_transfer, mobile_money, card
-- subtotal:        Sum of order_items subtotals
-- shipping:        Shipping cost at time of order
-- tax:             Tax amount at time of order
-- grand_total:     subtotal + shipping + tax
CREATE TABLE IF NOT EXISTS orders (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    order_number    VARCHAR(30)       NOT NULL,
    user_id         INT UNSIGNED      NOT NULL,
    shipping_name   VARCHAR(100)      NOT NULL,
    shipping_phone  VARCHAR(20)       NOT NULL,
    shipping_address TEXT             NOT NULL,
    payment_method  VARCHAR(30)       NOT NULL DEFAULT 'cod',
    subtotal        DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    shipping        DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    tax             DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    grand_total     DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    status          ENUM('pending','confirmed','processing','shipped','delivered','cancelled')
                                      NOT NULL DEFAULT 'pending',
    payment_status  ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
    notes           TEXT              NULL,
    created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_orders_order_number (order_number),
    INDEX idx_orders_user_id (user_id),
    INDEX idx_orders_status (status),
    INDEX idx_orders_payment_status (payment_status),
    INDEX idx_orders_created_at (created_at),
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer orders with shipping and payment info';

-- ============================================================================
-- 10. order_items - Individual products within an order
-- ============================================================================
-- product_name, product_code, price are snapshots captured at order time
-- so the order record is never affected by future product edits.
CREATE TABLE IF NOT EXISTS order_items (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    order_id        INT UNSIGNED      NOT NULL,
    product_id      INT UNSIGNED      NULL,
    product_name    VARCHAR(255)      NOT NULL,
    product_code    VARCHAR(20)       NULL,
    price           DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    quantity        INT UNSIGNED      NOT NULL DEFAULT 1,
    subtotal        DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_order_items_order_id (order_id),
    INDEX idx_order_items_product_id (product_id),
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Line items belonging to an order';
