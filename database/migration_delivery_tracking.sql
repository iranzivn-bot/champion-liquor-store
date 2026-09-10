-- ============================================================================
-- Migration: Delivery Tracking
-- ============================================================================
-- Adds tracking_number and delivery_status columns to the orders table
-- for granular delivery management (Packed, Out For Delivery).
-- ============================================================================

USE champion_store;

ALTER TABLE orders
  ADD COLUMN tracking_number  VARCHAR(100)                                       NULL                      AFTER notes,
  ADD COLUMN delivery_status  ENUM('pending','packed','out_for_delivery','delivered')
                               NOT NULL DEFAULT 'pending'                        AFTER tracking_number;
