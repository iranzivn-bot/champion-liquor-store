-- Barcode column for products
ALTER TABLE products
    ADD COLUMN barcode VARCHAR(50) NULL AFTER image,
    ADD UNIQUE KEY uk_products_barcode (barcode);
