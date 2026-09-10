CREATE TABLE IF NOT EXISTS blog_posts (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    title           VARCHAR(255)    NOT NULL,
    slug            VARCHAR(255)    NOT NULL,
    content         TEXT            NOT NULL,
    excerpt         TEXT            NULL,
    image           VARCHAR(255)    NULL,
    author_id       INT UNSIGNED    NOT NULL,
    status          ENUM('draft','published') NOT NULL DEFAULT 'draft',
    published_at    DATETIME        NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_blog_slug (slug),
    KEY idx_blog_status_published (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Blog posts';
