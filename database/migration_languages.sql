-- ============================================================================
-- Migration: Languages Table
-- Creates the languages table and seeds default languages.
-- Run after settings migration.
-- ============================================================================

CREATE TABLE IF NOT EXISTS languages (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    code        VARCHAR(10)     NOT NULL COMMENT 'Language code e.g. en, fr, rw',
    name        VARCHAR(50)     NOT NULL COMMENT 'Language name e.g. English, French',
    flag        VARCHAR(10)     NOT NULL DEFAULT '' COMMENT 'Flag emoji or filename',
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    is_default  TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Supported languages for i18n';

INSERT IGNORE INTO languages (code, name, flag, status, is_default) VALUES
('en', 'English',     '🇬🇧', 'active', 1),
('fr', 'Français',   '🇫🇷', 'active', 0),
('rw', 'Kinyarwanda','🇷🇼', 'active', 0);
