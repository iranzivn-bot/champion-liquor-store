-- ============================================================================
-- Migration: RBAC (Role-Based Access Control)
-- Adds super_admin role, permissions table, and seed data.
-- Run once on your production database.
-- ============================================================================

-- ─── 1. Update users table to support super_admin ─────────────────────────
-- The ENUM currently has 'admin', 'customer'. We add 'super_admin'.
ALTER TABLE users
    MODIFY COLUMN role ENUM('super_admin', 'admin', 'customer') NOT NULL DEFAULT 'customer';

-- ─── 2. Create permissions table ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS permissions (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100)    NOT NULL COMMENT 'Permission key e.g. products.view',
    description VARCHAR(255)    NOT NULL DEFAULT '' COMMENT 'Human-readable description',
    module      VARCHAR(50)     NOT NULL COMMENT 'Module group e.g. products, users',
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_permissions_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Application permissions for RBAC';

-- ─── 3. Create role_permissions table ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS role_permissions (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    role            ENUM('super_admin', 'admin', 'customer') NOT NULL,
    permission_id   INT UNSIGNED    NOT NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_role_perm (role, permission_id),
    KEY idx_role (role),
    KEY idx_permission (permission_id),
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Maps roles to permissions';

-- ─── 4. Seed default permissions ───────────────────────────────────────────
INSERT INTO permissions (name, description, module) VALUES
-- Dashboard
('dashboard.view', 'View admin dashboard', 'dashboard'),

-- Categories
('categories.view',  'View categories list',  'categories'),
('categories.create','Create new categories',  'categories'),
('categories.edit',  'Edit existing categories','categories'),
('categories.delete','Delete categories',      'categories'),

-- Brands
('brands.view',  'View brands list',  'brands'),
('brands.create','Create new brands',  'brands'),
('brands.edit',  'Edit existing brands','brands'),
('brands.delete','Delete brands',      'brands'),

-- Products
('products.view',  'View products list',  'products'),
('products.create','Create new products',  'products'),
('products.edit',  'Edit existing products','products'),
('products.delete','Delete products',      'products'),

-- Orders
('orders.view',   'View orders list',    'orders'),
('orders.update', 'Update order status', 'orders'),
('orders.cancel', 'Cancel orders',       'orders'),

-- Customers
('customers.view', 'View customer list', 'customers'),

-- Reports
('reports.view',   'View reports',    'reports'),
('reports.export', 'Export reports',  'reports'),

-- Inventory
('inventory.view',    'View inventory',   'inventory'),
('inventory.stock_in','Stock-in products','inventory'),
('inventory.adjust',  'Adjust stock',     'inventory'),

-- Analytics
('analytics.view', 'View analytics', 'analytics'),

-- Audit
('audit.view', 'View audit logs', 'audit'),

-- Users
('users.view',   'View users list',  'users'),
('users.create', 'Create users',     'users'),
('users.edit',   'Edit users',       'users'),
('users.delete', 'Delete users',     'users'),

-- Permissions (super_admin only)
('permissions.manage', 'Manage role permissions', 'permissions'),

-- Settings
('settings.manage', 'Manage system settings', 'settings'),

-- POS
('pos.access', 'Access Point of Sale module', 'pos');

-- ─── 5. Assign all permissions to super_admin ──────────────────────────────
INSERT INTO role_permissions (role, permission_id)
SELECT 'super_admin', id FROM permissions;

-- ─── 6. Assign default view permissions to admin ───────────────────────────
-- Admins can view and manage most modules but not users/permissions/settings.
INSERT INTO role_permissions (role, permission_id)
SELECT 'admin', id FROM permissions WHERE name IN (
    'dashboard.view',
    'categories.view',  'categories.create',  'categories.edit',  'categories.delete',
    'brands.view',      'brands.create',      'brands.edit',      'brands.delete',
    'products.view',    'products.create',    'products.edit',    'products.delete',
    'orders.view',      'orders.update',      'orders.cancel',
    'customers.view',
    'reports.view',
    'inventory.view',   'inventory.stock_in', 'inventory.adjust',
    'analytics.view',
    'audit.view',
    'pos.access'
);

-- ─── 7. Seed default super_admin account ───────────────────────────────────
-- Password: Admin@12345 (bcrypt hash)
-- Email:    admin@championstore.com
-- Password: Admin@12345
-- Role:     super_admin
INSERT INTO users (full_name, email, phone, password, role, status)
SELECT 'System Administrator', 'admin@championstore.com', '+250 788 000 000',
       '$2y$12$3BouDJ6ma3D/qEtjKekurOB7tjxrjR3gZPYUHY6NJ4Jh14.knipRK',
       'super_admin', 'active'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@championstore.com');
