-- ============================================================================
-- Migration: Fix Foreign Keys + Add Missing Indexes
-- Adds ON UPDATE CASCADE to FKs and indexes on frequently queried columns.
-- ============================================================================

USE champion_store;

-- ─── 1. Add missing ON UPDATE CASCADE to foreign keys ──────────────────

-- password_resets.user_id
ALTER TABLE `password_resets`
    DROP FOREIGN KEY `fk_password_resets_user`,
    ADD CONSTRAINT `fk_password_resets_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE;

-- reviews.product_id
ALTER TABLE `reviews`
    DROP FOREIGN KEY `reviews_ibfk_1`,
    ADD CONSTRAINT `fk_reviews_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE;

-- reviews.user_id
ALTER TABLE `reviews`
    DROP FOREIGN KEY `reviews_ibfk_2`,
    ADD CONSTRAINT `fk_reviews_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- role_permissions.permission_id
ALTER TABLE `role_permissions`
    DROP FOREIGN KEY `fk_rp_permission`,
    ADD CONSTRAINT `fk_rp_permission`
        FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE;

-- collections.category_id
ALTER TABLE `collections`
    DROP FOREIGN KEY `fk_collections_category`,
    ADD CONSTRAINT `fk_collections_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- ─── 2. Add missing indexes ────────────────────────────────────────────

CREATE INDEX IF NOT EXISTS `idx_blog_author` ON `blog_posts` (`author_id`);
CREATE INDEX IF NOT EXISTS `idx_reviews_created` ON `reviews` (`created_at`);
CREATE INDEX IF NOT EXISTS `idx_collections_category` ON `collections` (`category_id`);
