-- ============================================================================
-- Champion Liquor Store Ltd - Database Schema
-- ============================================================================
-- Database  : champion_store
-- Engine    : InnoDB
-- Charset   : utf8mb4
-- Collation : utf8mb4_unicode_ci
-- ============================================================================

CREATE DATABASE IF NOT EXISTS champion_store
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE champion_store;

-- ============================================================================
-- 1. users - Stores customer and admin account information
-- ============================================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    full_name   VARCHAR(100)    NOT NULL,
    email       VARCHAR(255)    NOT NULL,
    phone       VARCHAR(20)     NOT NULL,
    password    VARCHAR(255)    NOT NULL,
    role        ENUM('admin', 'customer') NOT NULL DEFAULT 'customer',
    status      ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer and admin user accounts';

-- ============================================================================
-- 2. categories - Product categories (Beer, Wine, Whisky, etc.)
-- ============================================================================
CREATE TABLE IF NOT EXISTS categories (
    code        VARCHAR(20)     NOT NULL,
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100)    NOT NULL,
    slug        VARCHAR(120)    NOT NULL,
    description TEXT            NULL,
    image       VARCHAR(255)    NULL,
    status      ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_categories_slug (slug),
    UNIQUE KEY uk_categories_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product categories taxonomy';

-- ============================================================================
-- 3. brands - Product brands (Heineken, Guinness, Jack Daniel's, etc.)
-- ============================================================================
CREATE TABLE IF NOT EXISTS brands (
    code        VARCHAR(20)     NOT NULL,
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100)    NOT NULL,
    slug        VARCHAR(120)    NOT NULL,
    logo        VARCHAR(255)    NULL,
    status      ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_brands_slug (slug),
    UNIQUE KEY uk_brands_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product brands';

-- ============================================================================
-- 4. products - Core product catalog
-- ============================================================================
CREATE TABLE IF NOT EXISTS products (
    code            VARCHAR(20)     NOT NULL,
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    category_id     INT UNSIGNED    NOT NULL,
    brand_id        INT UNSIGNED    NOT NULL,
    name            VARCHAR(200)    NOT NULL,
    slug            VARCHAR(220)    NOT NULL,
    description     TEXT            NULL,
    price           DECIMAL(10,2)   NOT NULL,
    discount_price  DECIMAL(10,2)   NULL,
    stock_quantity  INT             NOT NULL DEFAULT 0,
    image           VARCHAR(255)    NULL,
    barcode         VARCHAR(50)     NULL,
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_products_slug (slug),
    UNIQUE KEY uk_products_code (code),
    UNIQUE KEY uk_products_barcode (barcode),
    INDEX idx_products_category_id (category_id),
    INDEX idx_products_brand_id (brand_id),
    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_products_brand
        FOREIGN KEY (brand_id) REFERENCES brands (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product catalog with pricing and inventory';

-- ============================================================================
-- 5. product_images - Multiple images per product
-- ============================================================================
CREATE TABLE IF NOT EXISTS product_images (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    product_id  INT UNSIGNED    NOT NULL,
    image       VARCHAR(255)    NOT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_product_images_product_id (product_id),
    CONSTRAINT fk_product_images_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Additional product images gallery';

-- ============================================================================
-- 6. cart - Shopping cart items
-- ============================================================================
-- price:      Snapshot of the product's price when the item was added.
--             This prevents future price changes from affecting existing cart
--             items.
-- subtotal:   quantity * price, stored for fast reads.
CREATE TABLE IF NOT EXISTS cart (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    NOT NULL,
    product_id  INT UNSIGNED    NOT NULL,
    price       DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    quantity    INT UNSIGNED    NOT NULL DEFAULT 1,
    subtotal    DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cart_user_product (user_id, product_id),
    INDEX idx_cart_user_id (user_id),
    CONSTRAINT fk_cart_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cart_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Shopping cart items with price snapshot';

-- ============================================================================
-- 7. coupons - Discount coupons (structure only — logic not yet applied)
-- ============================================================================
-- discount_type: 'percentage' (e.g. 10% off) or 'fixed' (e.g. 5,000 RWF off).
-- Coupon application logic is not yet implemented.
CREATE TABLE IF NOT EXISTS coupons (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    code            VARCHAR(50)     NOT NULL,
    discount_type   ENUM('percentage','fixed') NOT NULL,
    discount_value  DECIMAL(10,2)   NOT NULL,
    start_date      DATE            NOT NULL,
    end_date        DATE            NOT NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_coupons_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Discount coupons (not yet applied)';

-- ============================================================================
-- 8. wishlist - Saved-for-later items (structure only — Wishlist module not
--    yet implemented)
-- ============================================================================
CREATE TABLE IF NOT EXISTS wishlist (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    NOT NULL,
    product_id  INT UNSIGNED    NOT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_wishlist_user_product (user_id, product_id),
    INDEX idx_wishlist_user_id (user_id),
    INDEX idx_wishlist_product_id (product_id),
    CONSTRAINT fk_wishlist_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_wishlist_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User wishlist items';

-- ============================================================================
-- 9. orders - Stores customer orders placed through checkout
-- ============================================================================
-- order_number:    Auto-generated format CLS-2026-XXXXXX (year + sequential).
-- status:          pending → confirmed → processing → shipped → delivered | cancelled
-- payment_status:  unpaid → paid | refunded
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
    tracking_number VARCHAR(100)      NULL,
    delivery_status ENUM('pending','packed','out_for_delivery','delivered')
                                      NOT NULL DEFAULT 'pending',
    created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_orders_order_number (order_number),
    INDEX idx_orders_user_id (user_id),
    INDEX idx_orders_status (status),
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer orders with shipping and payment info';

-- ============================================================================
-- 10. order_items - Individual products within an order
-- ============================================================================
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

-- ============================================================================
-- Sample Data
-- ============================================================================

-- Categories
INSERT INTO categories (code, name, slug, description, status) VALUES
('CAT-001', 'Beer',       'beer',       'Refreshing brews and ales from around the world',     'active'),
('CAT-002', 'Wine',       'wine',       'Fine wines red, white, and sparkling',                'active'),
('CAT-003', 'Whisky',     'whisky',     'Premium Scotch, Bourbon, and Irish whiskies',         'active'),
('CAT-004', 'Vodka',      'vodka',      'Smooth vodkas for every occasion',                    'active'),
('CAT-005', 'Soft Drinks','soft-drinks','Non-alcoholic beverages and mixers',                  'active');

-- Brands
INSERT INTO brands (code, name, slug, status) VALUES
('BRD-001', 'Heineken',    'heineken',    'active'),
('BRD-002', 'Guinness',    'guinness',    'active'),
('BRD-003', 'Jack Daniel''s', 'jack-daniels', 'active'),
('BRD-004', 'Jameson',     'jameson',     'active'),
('BRD-005', 'Coca-Cola',   'coca-cola',   'active');

-- ============================================================================
-- 14. compare_list - Product comparison for logged-in users and guests
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
