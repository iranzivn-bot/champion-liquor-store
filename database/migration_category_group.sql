-- Add `group` column to categories table to distinguish Liquor vs Mini Market

ALTER TABLE categories
    ADD COLUMN `group` ENUM('liquor', 'mini_market') NOT NULL DEFAULT 'liquor'
    AFTER status;
