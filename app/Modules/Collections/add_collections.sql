-- ============================================================
-- Коллекции (Series) — группировка статей в серии
-- ============================================================

CREATE TABLE `collections` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `author_id` INT UNSIGNED NOT NULL COMMENT 'Автор коллекции',
    `title` VARCHAR(200) NOT NULL COMMENT 'Название коллекции',
    `slug` VARCHAR(200) NOT NULL COMMENT 'URL slug',
    `description` TEXT NULL COMMENT 'Описание коллекции',
    `cover_image` VARCHAR(500) NULL COMMENT 'URL обложки',
    `is_public` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=публичная, 0=приватная',
    `stories_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Денормализованный счётчик статей',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Мягкое удаление',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_author_slug` (`author_id`, `slug`),
    KEY `idx_public_created` (`is_public`, `deleted_at`, `created_at` DESC),
    KEY `idx_author` (`author_id`, `deleted_at`),
    CONSTRAINT `fk_collection_author` FOREIGN KEY (`author_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `collection_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `collection_id` INT UNSIGNED NOT NULL,
    `story_id` INT UNSIGNED NOT NULL,
    `position` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Порядок в серии (1-based)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_collection_story` (`collection_id`, `story_id`),
    KEY `idx_collection_position` (`collection_id`, `position`),
    KEY `idx_story` (`story_id`),
    CONSTRAINT `fk_item_collection` FOREIGN KEY (`collection_id`) 
        REFERENCES `collections` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_item_story` FOREIGN KEY (`story_id`) 
        REFERENCES `stories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;