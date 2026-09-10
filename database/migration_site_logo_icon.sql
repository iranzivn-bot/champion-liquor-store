-- ============================================================================
-- Migration: Site Logo Icon Setting
-- Adds a setting for an uploaded logo icon image to replace the default bi-shop.
-- ============================================================================

INSERT IGNORE INTO settings (setting_key, setting_value, setting_group, description) VALUES
('site_logo_icon', '', 'appearance', 'Header logo icon image filename (in uploads/settings/). Falls back to bi-shop icon if empty.');
