-- ============================================================================
-- Migration: System Settings
-- Creates the settings table and seeds default values.
-- Run once after RBAC migration.
-- ============================================================================

CREATE TABLE IF NOT EXISTS settings (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    setting_key   VARCHAR(100)    NOT NULL,
    setting_value TEXT            NULL,
    setting_group VARCHAR(50)     NOT NULL DEFAULT 'general' COMMENT 'Group e.g. general, company, email',
    description   VARCHAR(255)    NOT NULL DEFAULT '',
    updated_by    INT UNSIGNED    NULL COMMENT 'User ID who last updated',
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System-wide configuration settings';

INSERT IGNORE INTO settings (setting_key, setting_value, setting_group, description) VALUES
-- General
('site_name',              'Champion Liquor Store Ltd',  'general',    'Site display name'),
('site_tagline',           'Premium Wines & Spirits',    'general',    'Site tagline / slogan'),
('timezone',               'Africa/Kigali',              'general',    'Default timezone'),
('language',               'en',                         'general',    'Default language code'),

-- Currency
('currency',               'RWF',                        'currency',   'Currency code'),
('currency_symbol',        'RWF',                        'currency',   'Currency display symbol'),

-- Company
('company_name',           'Champion Liquor Store Ltd',  'company',    'Legal company name'),
('company_email',          'info@championliquorstore.com','company',   'Company contact email'),
('company_phone',          '+250 788 000 000',           'company',    'Company phone number'),
('company_address',        'KG 123 Ave, Kigali, Rwanda', 'company',    'Company physical address'),
('company_website',        'https://championliquorstore.com','company', 'Company website URL'),

-- Logo / Appearance
('site_logo',              '',                           'appearance', 'Site logo filename (in uploads/settings/)'),
('favicon',                '',                           'appearance', 'Favicon filename (in uploads/settings/)'),

-- Email / SMTP
('smtp_host',              '',                           'email',      'SMTP server hostname'),
('smtp_port',              '587',                        'email',      'SMTP server port'),
('smtp_username',          '',                           'email',      'SMTP username'),
('smtp_password',          '',                           'email',      'SMTP password'),
('smtp_encryption',        'tls',                        'email',      'SMTP encryption (tls / ssl / none)'),
('smtp_from_name',         'Champion Liquor Store Ltd',  'email',      'Sender name for outgoing emails'),
('smtp_from_email',        'noreply@championliquorstore.com','email',  'Sender email for outgoing emails'),

-- Orders
('default_tax_rate',       '5.00',                       'orders',     'Default tax rate percentage'),
('default_shipping_fee',   '2000.00',                    'orders',     'Default shipping fee'),
('low_stock_threshold',    '5',                          'orders',     'Low stock alert threshold'),
('allow_guest_checkout',   '0',                          'orders',     'Allow checkout without login (1=yes, 0=no)'),

-- Security
('max_login_attempts',     '3',                          'security',   'Maximum failed login attempts before lockout'),
('lockout_minutes',        '15',                         'security',   'Lockout duration in minutes'),
('session_timeout',        '1800',                       'security',   'Session idle timeout in seconds (1800 = 30 min)'),

-- Password Policy
('minimum_password_length','8',                          'password',   'Minimum password length'),
('require_uppercase',      '1',                          'password',   'Require uppercase letter (1=yes, 0=no)'),
('require_lowercase',      '1',                          'password',   'Require lowercase letter (1=yes, 0=no)'),
('require_number',         '1',                          'password',   'Require digit (1=yes, 0=no)'),
('require_special_char',   '1',                          'password',   'Require special character (1=yes, 0=no)');
