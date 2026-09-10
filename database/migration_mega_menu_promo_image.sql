-- ============================================================================
-- Migration: Mega Menu Promo Image Setting
-- Adds a system setting for the featured promo image in the navigation mega menu.
-- ============================================================================

INSERT IGNORE INTO settings (setting_key, setting_value, setting_group, description) VALUES
('mega_menu_promo_image', '', 'appearance', 'Mega menu featured promo image filename (in uploads/settings/)');
