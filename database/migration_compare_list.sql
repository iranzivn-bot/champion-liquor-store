-- ============================================================================
-- Migration: Compare List
-- Adds the compare_list table for product comparison (supports users + guests).
-- ============================================================================

CREATE TABLE IF NOT EXISTS compare_list (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    NULL DEFAULT NULL,
    product_id  INT UNSIGNED    NOT NULL,
    session_id  VARCHAR(64)     NULL DEFAULT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_compare_user_product (user_id, product_id),
    UNIQUE KEY uk_compare_session_product (session_id, product_id),
    INDEX idx_compare_user_id (user_id),
    INDEX idx_compare_session_id (session_id),
    INDEX idx_compare_product_id (product_id),
    CONSTRAINT fk_compare_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_compare_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product comparison list for users and guests';
