CREATE TABLE IF NOT EXISTS collections (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100)    NOT NULL,
    slug        VARCHAR(120)    NOT NULL,
    description TEXT            NULL,
    image       VARCHAR(255)    NULL,
    category_id INT UNSIGNED    NULL DEFAULT NULL,
    status      VARCHAR(20)     NOT NULL DEFAULT 'active',
    sort_order  INT             NOT NULL DEFAULT 0,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_collections_slug (slug),
    INDEX idx_collections_status (status),
    INDEX idx_collections_sort (sort_order),
    CONSTRAINT fk_collections_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO collections (name, slug, description, sort_order, status) VALUES
('Premium Whiskey', 'premium-whiskey', 'Explore our finest selection of premium whiskeys from around the world.', 1, 'active'),
('Fine Wines', 'fine-wines', 'Curated collection of exquisite wines for every occasion.', 2, 'active'),
('Craft Beers', 'craft-beers', 'Discover unique and flavorful craft brews.', 3, 'active'),
('Champagne & Sparkling', 'champagne-sparkling', 'Celebrate with the finest champagne and sparkling wines.', 4, 'active'),
('Gift Sets', 'gift-sets', 'Perfect gift sets for the connoisseur in your life.', 5, 'active');
