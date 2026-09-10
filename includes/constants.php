<?php
/**
 * Application Constants
 *
 * Central place for all application-wide constants.
 * Always include this file AFTER config.php so SITE_URL, BASE_PATH,
 * and UPLOADS_PATH are already defined.
 *
 * Why this file exists:
 * Instead of writing 'admin' or 'active' in many places, we define them
 * once here. This makes the code easier to maintain and less error-prone.
 *
 * Usage:
 *   require_once __DIR__ . '/config.php';
 *   require_once __DIR__ . '/constants.php';
 *
 * PHP 8.3
 */

// ─── Site Info ─────────────────────────────────────────────────────────
// SITE_NAME and SITE_URL are defined in config.php.
// This file adds additional site-related constants.

// ─── User Roles ────────────────────────────────────────────────────────
// Used when checking or assigning user permissions.
// Example: if ($_SESSION['user_role'] === ADMIN_ROLE) { ... }
define('SUPER_ADMIN_ROLE', 'super_admin');
define('ADMIN_ROLE',       'admin');
define('CUSTOMER_ROLE',    'customer');

// ─── Status Values ─────────────────────────────────────────────────────
// Used for categories, brands, products, and users.
// Example: $stmt->execute([':status' => ACTIVE_STATUS]);
define('ACTIVE_STATUS',   'active');
define('INACTIVE_STATUS', 'inactive');

// ─── Currency ─────────────────────────────────────────────────────────
// The currency label used for price display.
define('CURRENCY', 'RWF');

// ─── Upload Paths ──────────────────────────────────────────────────────
// These are filesystem paths (for PHP file operations like move_uploaded_file).
// They are built from UPLOADS_PATH (defined in config.php).
define('PRODUCT_UPLOAD_PATH',  UPLOADS_PATH . 'products'   . DIRECTORY_SEPARATOR);
define('CATEGORY_UPLOAD_PATH', UPLOADS_PATH . 'categories' . DIRECTORY_SEPARATOR);
define('BRAND_UPLOAD_PATH',    UPLOADS_PATH . 'brands'     . DIRECTORY_SEPARATOR);
define('USER_UPLOAD_PATH',     UPLOADS_PATH . 'users'      . DIRECTORY_SEPARATOR);
define('BLOG_UPLOAD_PATH',        UPLOADS_PATH . 'blogs'         . DIRECTORY_SEPARATOR);
define('COLLECTION_UPLOAD_PATH', UPLOADS_PATH . 'collections'   . DIRECTORY_SEPARATOR);

// ─── Default Images ────────────────────────────────────────────────────
// When a product or user has no image, these placeholders are used.
// These are URL paths (for <img src="..."> attributes).
define('DEFAULT_PRODUCT_IMAGE', SITE_URL . 'assets/images/no-image.png');
define('DEFAULT_USER_IMAGE',    SITE_URL . 'assets/images/default-user.png');
define('DEFAULT_BLOG_IMAGE',    SITE_URL . 'assets/images/no-image.png');

// ─── Order Statuses ───────────────────────────────────────────────────
define('ORDER_PENDING',    'pending');
define('ORDER_CONFIRMED',  'confirmed');
define('ORDER_PROCESSING', 'processing');
define('ORDER_SHIPPED',    'shipped');
define('ORDER_DELIVERED',  'delivered');
define('ORDER_CANCELLED',  'cancelled');

// ─── Payment Statuses ─────────────────────────────────────────────────
define('PAYMENT_UNPAID',   'unpaid');
define('PAYMENT_PAID',     'paid');
define('PAYMENT_REFUNDED', 'refunded');

// ─── Payment Methods ──────────────────────────────────────────────────
define('PAYMENT_CASH',         'cash');
define('PAYMENT_COD',          'cod');
define('PAYMENT_BANK_TRANSFER','bank_transfer');
define('PAYMENT_MOBILE_MONEY', 'mobile_money');
define('PAYMENT_CARD',         'card');

// ─── Delivery Statuses ───────────────────────────────────────────────
define('DELIVERY_PENDING',          'pending');
define('DELIVERY_PACKED',           'packed');
define('DELIVERY_OUT_FOR_DELIVERY', 'out_for_delivery');
define('DELIVERY_DELIVERED',        'delivered');

// ─── Inventory Constants ─────────────────────────────────────────────
define('INV_MOVEMENT_STOCK_IN',        'stock_in');
define('INV_MOVEMENT_STOCK_OUT',       'stock_out');
define('INV_MOVEMENT_ADJUSTMENT',      'adjustment');
define('INV_MOVEMENT_ORDER',           'order');
define('INV_MOVEMENT_ORDER_CANCELLED', 'order_cancelled');

define('INV_REFERENCE_MANUAL',     'manual');
define('INV_REFERENCE_ORDER',      'order');
define('INV_REFERENCE_PURCHASE',   'purchase');
define('INV_REFERENCE_ADJUSTMENT', 'adjustment');

define('LOW_STOCK_THRESHOLD', 5);

// ─── Compare ─────────────────────────────────────────────────────────
define('COMPARE_MAX_ITEMS', 4);

// ─── Order Defaults ───────────────────────────────────────────────────
define('DEFAULT_SHIPPING', 2000.00);
define('DEFAULT_TAX',      500.00);
