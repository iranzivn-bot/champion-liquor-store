-- ================================================================
-- Migration: Audit Logs Table
-- 
-- Creates the audit_logs table for tracking all user activities
-- across the system. Every major action (create, update, delete,
-- login, logout, export, etc.) creates one audit log entry.
--
-- Foreign key: user_id -> users(id) ON DELETE SET NULL
-- Indexes: user_id, module, created_at for query performance
-- ================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------
-- Table: audit_logs
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;

CREATE TABLE `audit_logs` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED    DEFAULT NULL COMMENT 'Who performed the action (NULL if user deleted)',
    `user_name`    VARCHAR(100)    NOT NULL DEFAULT '' COMMENT 'Snapshot of user full name at time of action',
    `user_role`    VARCHAR(50)     NOT NULL DEFAULT '' COMMENT 'Snapshot of user role at time of action',
    `action`       VARCHAR(50)     NOT NULL COMMENT 'Type of action: created, updated, deleted, login, logout, etc.',
    `module`       VARCHAR(50)     NOT NULL COMMENT 'System module: auth, products, categories, brands, orders, inventory, users, reports, wishlist',
    `reference_id` VARCHAR(50)     DEFAULT NULL COMMENT 'ID or code of the affected record',
    `description`  TEXT            DEFAULT NULL COMMENT 'Human-readable details of what happened',
    `ip_address`   VARCHAR(45)     NOT NULL DEFAULT '' COMMENT 'Client IP address at time of action',
    `user_agent`   VARCHAR(500)    NOT NULL DEFAULT '' COMMENT 'Browser/device user agent string',
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_user_id` (`user_id`),
    KEY `idx_audit_module`  (`module`),
    KEY `idx_audit_created` (`created_at`),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
