-- ============================================================================
-- Migration: Wishlist Guest Support
-- Adds session_id column and indexes for guest (non-logged-in) wishlist usage.
-- ============================================================================

-- Add session_id column (nullable, for guest users)
ALTER TABLE wishlist
    ADD COLUMN session_id VARCHAR(64) NULL DEFAULT NULL AFTER user_id,
    ADD INDEX idx_wishlist_session_id (session_id),
    ADD UNIQUE KEY uk_wishlist_session_product (session_id, product_id);
