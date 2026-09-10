-- Password Reset Tokens
--
-- Stores time-limited, single-use password reset tokens.
-- Tokens are stored as SHA-256 hashes (the raw token goes in the email link).
-- Expired tokens can be purged periodically.
--
-- Usage:
--   mysql -u root champion_store < database/migration_password_reset.sql
--
-- PHP 8.3

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED    NOT NULL,
    `token_hash` VARCHAR(64)     NOT NULL COMMENT 'SHA-256 hash of the raw reset token',
    `expires_at` DATETIME        NOT NULL COMMENT 'Token is invalid after this time',
    `used_at`    DATETIME        NULL     DEFAULT NULL COMMENT 'Set to NOW() when consumed',
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_token_hash` (`token_hash`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_expires` (`expires_at`),
    CONSTRAINT `fk_password_resets_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
