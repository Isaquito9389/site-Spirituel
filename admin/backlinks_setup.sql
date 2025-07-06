-- Script SQL pour créer les tables de backlinks
-- À exécuter directement dans phpMyAdmin

-- Table pour les liens externes (backlinks)
CREATE TABLE IF NOT EXISTS `backlinks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `content_type` ENUM('blog', 'ritual') NOT NULL,
    `content_id` INT NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `domain` VARCHAR(255),
    `type` ENUM('reference', 'source', 'inspiration', 'related') DEFAULT 'reference',
    `status` ENUM('active', 'broken', 'pending') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_checked` TIMESTAMP NULL,
    INDEX `idx_content` (`content_type`, `content_id`),
    INDEX `idx_domain` (`domain`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour les liens internes
CREATE TABLE IF NOT EXISTS `internal_links` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `source_type` ENUM('blog', 'ritual') NOT NULL,
    `source_id` INT NOT NULL,
    `target_type` ENUM('blog', 'ritual') NOT NULL,
    `target_id` INT NOT NULL,
    `anchor_text` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_link` (`source_type`, `source_id`, `target_type`, `target_id`),
    INDEX `idx_source` (`source_type`, `source_id`),
    INDEX `idx_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour les catégories de backlinks
CREATE TABLE IF NOT EXISTS `backlink_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `description` TEXT,
    `color` VARCHAR(7) DEFAULT '#6c757d',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion des catégories par défaut
INSERT IGNORE INTO `backlink_categories` (`name`, `description`, `color`) VALUES
('reference', 'Liens de référence vers des sources citées', '#3a0ca3'),
('source', 'Liens vers des textes ou documents originaux', '#7209b7'),
('inspiration', 'Liens vers du contenu inspirant', '#4cc9f0'),
('related', 'Liens vers du contenu connexe', '#f72585');

-- Vérification des tables créées
SELECT 'Tables créées avec succès!' as status;
SHOW TABLES LIKE '%backlink%';
SHOW TABLES LIKE '%internal_links%';
