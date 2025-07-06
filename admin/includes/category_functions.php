<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
/**
 * Category Management Functions
 *
 * This file provides functions for managing categories and their specific features.
 */

// Include database connection
require_once 'db_connect.php';

/**
 * Get all categories
 *
 * @param string $type Type of categories (blog, ritual, product)
 * @return array List of categories
 */
function get_all_categories($type = 'blog') {
    global $pdo;

    try {
        $table = $type . '_categories';
        $stmt = $pdo->query("SELECT * FROM $table ORDER BY name ASC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get category by ID
 *
 * @param int $category_id Category ID
 * @param string $type Type of category (blog, ritual, product)
 * @return array|bool Category data or false if not found
 */
function get_category_by_id($category_id, $type = 'blog') {
    global $pdo;

    try {
        $table = $type . '_categories';
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = :id");
        $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return false;
        }

        return $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Create a new category
 *
 * @param array $data Category data
 * @param string $type Type of category (blog, ritual, product)
 * @return array Status and message
 */
function create_category($data, $type = 'blog') {
    global $pdo;

    try {
        $table = $type . '_categories';

        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = strtolower(str_replace(' ', '-', $data['name']));
            // Remove special characters
            $data['slug'] = preg_replace('/[^a-z0-9\-]/', '', $data['slug']);
            // Remove multiple dashes
            $data['slug'] = preg_replace('/-+/', '-', $data['slug']);
        }

        // Check if slug already exists
        $stmt = $pdo->prepare("SELECT id FROM $table WHERE slug = :slug");
        $stmt->bindParam(':slug', $data['slug']);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return ['status' => false, 'message' => "Ce slug est déjà utilisé. Veuillez en choisir un autre."];
        }

        // Insert category
        $stmt = $pdo->prepare("INSERT INTO $table (name, slug, description, parent_id, icon, color, featured)
                              VALUES (:name, :slug, :description, :parent_id, :icon, :color, :featured)");

        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':slug', $data['slug']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':parent_id', $data['parent_id'], PDO::PARAM_INT);
        $stmt->bindParam(':icon', $data['icon']);
        $stmt->bindParam(':color', $data['color']);
        $stmt->bindParam(':featured', $data['featured'], PDO::PARAM_BOOL);

        $stmt->execute();

        // Add category-specific metadata if provided
        if (!empty($data['metadata'])) {
            $category_id = $pdo->lastInsertId();
            add_category_metadata($category_id, $data['metadata'], $type);
        }

        return ['status' => true, 'message' => "Catégorie créée avec succès."];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => "Erreur lors de la création de la catégorie: " . $e->getMessage()];
    }
}

/**
 * Update an existing category
 *
 * @param int $category_id Category ID
 * @param array $data Category data
 * @param string $type Type of category (blog, ritual, product)
 * @return array Status and message
 */
function update_category($category_id, $data, $type = 'blog') {
    global $pdo;

    try {
        $table = $type . '_categories';

        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = strtolower(str_replace(' ', '-', $data['name']));
            // Remove special characters
            $data['slug'] = preg_replace('/[^a-z0-9\-]/', '', $data['slug']);
            // Remove multiple dashes
            $data['slug'] = preg_replace('/-+/', '-', $data['slug']);
        }

        // Check if slug already exists (for another category)
        $stmt = $pdo->prepare("SELECT id FROM $table WHERE slug = :slug AND id != :category_id");
        $stmt->bindParam(':slug', $data['slug']);
        $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return ['status' => false, 'message' => "Ce slug est déjà utilisé. Veuillez en choisir un autre."];
        }

        // Update category
        $stmt = $pdo->prepare("UPDATE $table SET
                              name = :name,
                              slug = :slug,
                              description = :description,
                              parent_id = :parent_id,
                              icon = :icon,
                              color = :color,
                              featured = :featured
                              WHERE id = :category_id");

        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':slug', $data['slug']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':parent_id', $data['parent_id'], PDO::PARAM_INT);
        $stmt->bindParam(':icon', $data['icon']);
        $stmt->bindParam(':color', $data['color']);
        $stmt->bindParam(':featured', $data['featured'], PDO::PARAM_BOOL);
        $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);

        $stmt->execute();

        // Update category-specific metadata if provided
        if (!empty($data['metadata'])) {
            update_category_metadata($category_id, $data['metadata'], $type);
        }

        return ['status' => true, 'message' => "Catégorie mise à jour avec succès."];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => "Erreur lors de la mise à jour de la catégorie: " . $e->getMessage()];
    }
}

/**
 * Delete a category
 *
 * @param int $category_id Category ID
 * @param string $type Type of category (blog, ritual, product)
 * @return array Status and message
 */
function delete_category($category_id, $type = 'blog') {
    global $pdo;

    try {
        $table = $type . '_categories';
        $metadata_table = $type . '_category_metadata';

        // Begin transaction
        $pdo->beginTransaction();

        // Delete category metadata
        $stmt = $pdo->prepare("DELETE FROM $metadata_table WHERE category_id = :category_id");
        $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->execute();

        // Delete category
        $stmt = $pdo->prepare("DELETE FROM $table WHERE id = :category_id");
        $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->execute();

        // Commit transaction
        $pdo->commit();

        return ['status' => true, 'message' => "Catégorie supprimée avec succès."];
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        return ['status' => false, 'message' => "Erreur lors de la suppression de la catégorie: " . $e->getMessage()];
    }
}

/**
 * Add metadata to a category
 *
 * @param int $category_id Category ID
 * @param array $metadata Metadata key-value pairs
 * @param string $type Type of category (blog, ritual, product)
 * @return bool Success status
 */
function add_category_metadata($category_id, $metadata, $type = 'blog') {
    global $pdo;

    try {
        $table = $type . '_category_metadata';

        // Create metadata table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS $table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            meta_key VARCHAR(100) NOT NULL,
            meta_value TEXT,
            UNIQUE KEY unique_meta (category_id, meta_key),
            FOREIGN KEY (category_id) REFERENCES {$type}_categories(id) ON DELETE CASCADE
        )");

        // Begin transaction
        $pdo->beginTransaction();

        foreach ($metadata as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO $table (category_id, meta_key, meta_value) VALUES (:category_id, :meta_key, :meta_value)");
            $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
            $stmt->bindParam(':meta_key', $key);
            $stmt->bindParam(':meta_value', $value);
            $stmt->execute();
        }

        // Commit transaction
        $pdo->commit();

        return true;
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        return false;
    }
}

/**
 * Update metadata for a category
 *
 * @param int $category_id Category ID
 * @param array $metadata Metadata key-value pairs
 * @param string $type Type of category (blog, ritual, product)
 * @return bool Success status
 */
function update_category_metadata($category_id, $metadata, $type = 'blog') {
    global $pdo;

    try {
        $table = $type . '_category_metadata';

        // Create metadata table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS $table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            meta_key VARCHAR(100) NOT NULL,
            meta_value TEXT,
            UNIQUE KEY unique_meta (category_id, meta_key),
            FOREIGN KEY (category_id) REFERENCES {$type}_categories(id) ON DELETE CASCADE
        )");

        // Begin transaction
        $pdo->beginTransaction();

        foreach ($metadata as $key => $value) {
            // Check if metadata exists
            $stmt = $pdo->prepare("SELECT id FROM $table WHERE category_id = :category_id AND meta_key = :meta_key");
            $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
            $stmt->bindParam(':meta_key', $key);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                // Update existing metadata
                $stmt = $pdo->prepare("UPDATE $table SET meta_value = :meta_value WHERE category_id = :category_id AND meta_key = :meta_key");
            } else {
                // Insert new metadata
                $stmt = $pdo->prepare("INSERT INTO $table (category_id, meta_key, meta_value) VALUES (:category_id, :meta_key, :meta_value)");
            }

            $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
            $stmt->bindParam(':meta_key', $key);
            $stmt->bindParam(':meta_value', $value);
            $stmt->execute();
        }

        // Commit transaction
        $pdo->commit();

        return true;
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        return false;
    }
}

/**
 * Get metadata for a category
 *
 * @param int $category_id Category ID
 * @param string $type Type of category (blog, ritual, product)
 * @return array Metadata key-value pairs
 */
function get_category_metadata($category_id, $type = 'blog') {
    global $pdo;

    try {
        $table = $type . '_category_metadata';

        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() === 0) {
            return [];
        }

        $stmt = $pdo->prepare("SELECT meta_key, meta_value FROM $table WHERE category_id = :category_id");
        $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->execute();

        $metadata = [];
        while ($row = $stmt->fetch()) {
            $metadata[$row['meta_key']] = $row['meta_value'];
        }

        return $metadata;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get category-specific form fields
 *
 * @param string $category_slug Category slug
 * @param string $type Type of category (blog, ritual, product)
 * @return array Form fields configuration
 */
function get_category_form_fields($category_slug, $type = 'blog') {
    // Default fields for all categories
    $default_fields = [
        'title' => [
            'label' => 'Titre',
            'type' => 'text',
            'required' => true
        ],
        'content' => [
            'label' => 'Contenu',
            'type' => 'wysiwyg',
            'required' => true
        ],
        'excerpt' => [
            'label' => 'Extrait',
            'type' => 'textarea',
            'required' => false
        ],
        'featured_image' => [
            'label' => 'Image à la une',
            'type' => 'image',
            'required' => false
        ]
    ];

    // Category-specific fields
    $category_fields = [];

    // Love category fields
    if ($category_slug === 'love' || $category_slug === 'amour') {
        $category_fields = [
            'target_person' => [
                'label' => 'Personne cible',
                'type' => 'text',
                'required' => false,
                'description' => 'Nom de la personne visée par le rituel d\'amour'
            ],
            'relationship_type' => [
                'label' => 'Type de relation',
                'type' => 'select',
                'options' => [
                    'romantic' => 'Romantique',
                    'reconciliation' => 'Réconciliation',
                    'attraction' => 'Attraction',
                    'commitment' => 'Engagement'
                ],
                'required' => false
            ],
            'moon_phase' => [
                'label' => 'Phase lunaire recommandée',
                'type' => 'select',
                'options' => [
                    'new' => 'Nouvelle lune',
                    'waxing' => 'Lune croissante',
                    'full' => 'Pleine lune',
                    'waning' => 'Lune décroissante'
                ],
                'required' => false
            ],
            'ingredients' => [
                'label' => 'Ingrédients',
                'type' => 'repeater',
                'fields' => [
                    'name' => [
                        'label' => 'Nom',
                        'type' => 'text'
                    ],
                    'quantity' => [
                        'label' => 'Quantité',
                        'type' => 'text'
                    ]
                ],
                'required' => false
            ]
        ];
    }

    // Protection category fields
    else if ($category_slug === 'protection') {
        $category_fields = [
            'protection_type' => [
                'label' => 'Type de protection',
                'type' => 'select',
                'options' => [
                    'home' => 'Maison/Foyer',
                    'personal' => 'Personnelle',
                    'family' => 'Famille',
                    'business' => 'Entreprise',
                    'travel' => 'Voyage'
                ],
                'required' => false
            ],
            'duration' => [
                'label' => 'Durée de la protection',
                'type' => 'select',
                'options' => [
                    'temporary' => 'Temporaire',
                    'permanent' => 'Permanente',
                    'renewable' => 'Renouvelable'
                ],
                'required' => false
            ],
            'protection_level' => [
                'label' => 'Niveau de protection',
                'type' => 'select',
                'options' => [
                    'basic' => 'Basique',
                    'advanced' => 'Avancé',
                    'complete' => 'Complet'
                ],
                'required' => false
            ],
            'talismans' => [
                'label' => 'Talismans recommandés',
                'type' => 'repeater',
                'fields' => [
                    'name' => [
                        'label' => 'Nom',
                        'type' => 'text'
                    ],
                    'description' => [
                        'label' => 'Description',
                        'type' => 'textarea'
                    ]
                ],
                'required' => false
            ]
        ];
    }

    // Prosperity category fields
    else if ($category_slug === 'prosperity' || $category_slug === 'prosperite') {
        $category_fields = [
            'prosperity_area' => [
                'label' => 'Domaine de prospérité',
                'type' => 'select',
                'options' => [
                    'financial' => 'Financier',
                    'business' => 'Entreprise',
                    'career' => 'Carrière',
                    'abundance' => 'Abondance générale'
                ],
                'required' => false
            ],
            'manifestation_time' => [
                'label' => 'Temps de manifestation estimé',
                'type' => 'select',
                'options' => [
                    'short' => 'Court (1-4 semaines)',
                    'medium' => 'Moyen (1-3 mois)',
                    'long' => 'Long (3+ mois)'
                ],
                'required' => false
            ],
            'ritual_frequency' => [
                'label' => 'Fréquence du rituel',
                'type' => 'select',
                'options' => [
                    'once' => 'Une seule fois',
                    'daily' => 'Quotidien',
                    'weekly' => 'Hebdomadaire',
                    'monthly' => 'Mensuel'
                ],
                'required' => false
            ]
        ];
    }

    // Healing category fields
    else if ($category_slug === 'healing' || $category_slug === 'guerison') {
        $category_fields = [
            'healing_type' => [
                'label' => 'Type de guérison',
                'type' => 'select',
                'options' => [
                    'physical' => 'Physique',
                    'emotional' => 'Émotionnelle',
                    'spiritual' => 'Spirituelle',
                    'mental' => 'Mentale'
                ],
                'required' => false
            ],
            'healing_method' => [
                'label' => 'Méthode de guérison',
                'type' => 'select',
                'options' => [
                    'energy' => 'Énergétique',
                    'herbal' => 'Herbes',
                    'crystal' => 'Cristaux',
                    'ritual' => 'Rituel',
                    'combined' => 'Combinée'
                ],
                'required' => false
            ],
            'contraindications' => [
                'label' => 'Contre-indications',
                'type' => 'textarea',
                'required' => false
            ]
        ];
    }

    // Divination category fields
    else if ($category_slug === 'divination') {
        $category_fields = [
            'divination_method' => [
                'label' => 'Méthode de divination',
                'type' => 'select',
                'options' => [
                    'tarot' => 'Tarot',
                    'runes' => 'Runes',
                    'pendulum' => 'Pendule',
                    'scrying' => 'Scrutation',
                    'astrology' => 'Astrologie',
                    'numerology' => 'Numérologie',
                    'other' => 'Autre'
                ],
                'required' => false
            ],
            'accuracy_level' => [
                'label' => 'Niveau de précision',
                'type' => 'select',
                'options' => [
                    'general' => 'Général',
                    'specific' => 'Spécifique',
                    'detailed' => 'Détaillé'
                ],
                'required' => false
            ],
            'reading_duration' => [
                'label' => 'Durée de la lecture',
                'type' => 'text',
                'required' => false
            ]
        ];
    }

    // Merge default and category-specific fields
    return array_merge($default_fields, $category_fields);
}

/**
 * Get category-specific display settings
 *
 * @param string $category_slug Category slug
 * @param string $type Type of category (blog, ritual, product)
 * @return array Display settings
 */
function get_category_display_settings($category_slug, $type = 'blog') {
    // Default display settings
    $default_settings = [
        'icon' => 'fas fa-star',
        'color' => '#7209b7',
        'layout' => 'standard',
        'sidebar_widgets' => ['recent', 'categories', 'tags']
    ];

    // Category-specific settings
    $category_settings = [];

    // Love category settings
    if ($category_slug === 'love' || $category_slug === 'amour') {
        $category_settings = [
            'icon' => 'fas fa-heart',
            'color' => '#f72585',
            'layout' => 'featured',
            'sidebar_widgets' => ['recent', 'categories', 'related_love'],
            'header_image' => 'images/headers/love-header.jpg',
            'testimonials_section' => true,
            'related_products_section' => true,
            'call_to_action' => [
                'title' => 'Besoin d\'aide pour votre vie amoureuse?',
                'text' => 'Consultez nos experts en amour pour des conseils personnalisés.',
                'button_text' => 'Prendre rendez-vous',
                'button_link' => '/consultations.html'
            ]
        ];
    }

    // Protection category settings
    else if ($category_slug === 'protection') {
        $category_settings = [
            'icon' => 'fas fa-shield-alt',
            'color' => '#3a0ca3',
            'layout' => 'grid',
            'sidebar_widgets' => ['recent', 'categories', 'protection_levels'],
            'header_image' => 'images/headers/protection-header.jpg',
            'faq_section' => true,
            'related_products_section' => true,
            'call_to_action' => [
                'title' => 'Vous sentez-vous vulnérable?',
                'text' => 'Découvrez nos rituels de protection puissants.',
                'button_text' => 'Explorer les rituels',
                'button_link' => '/rituals.html?category=protection'
            ]
        ];
    }

    // Prosperity category settings
    else if ($category_slug === 'prosperity' || $category_slug === 'prosperite') {
        $category_settings = [
            'icon' => 'fas fa-coins',
            'color' => '#ffd700',
            'layout' => 'featured',
            'sidebar_widgets' => ['recent', 'categories', 'success_stories'],
            'header_image' => 'images/headers/prosperity-header.jpg',
            'testimonials_section' => true,
            'related_products_section' => true,
            'call_to_action' => [
                'title' => 'Prêt à attirer l\'abondance?',
                'text' => 'Nos rituels de prospérité peuvent vous aider à manifester la richesse.',
                'button_text' => 'Découvrir les rituels',
                'button_link' => '/rituals.html?category=prosperity'
            ]
        ];
    }

    // Healing category settings
    else if ($category_slug === 'healing' || $category_slug === 'guerison') {
        $category_settings = [
            'icon' => 'fas fa-hand-holding-medical',
            'color' => '#4cc9f0',
            'layout' => 'list',
            'sidebar_widgets' => ['recent', 'categories', 'healing_resources'],
            'header_image' => 'images/headers/healing-header.jpg',
            'faq_section' => true,
            'related_products_section' => true,
            'call_to_action' => [
                'title' => 'Besoin de guérison?',
                'text' => 'Explorez nos rituels de guérison pour le corps, l\'esprit et l\'âme.',
                'button_text' => 'Voir les rituels',
                'button_link' => '/rituals.html?category=healing'
            ]
        ];
    }

    // Divination category settings
    else if ($category_slug === 'divination') {
        $category_settings = [
            'icon' => 'fas fa-eye',
            'color' => '#8338ec',
            'layout' => 'grid',
            'sidebar_widgets' => ['recent', 'categories', 'divination_tools'],
            'header_image' => 'images/headers/divination-header.jpg',
            'faq_section' => true,
            'related_products_section' => true,
            'call_to_action' => [
                'title' => 'Curieux de connaître votre avenir?',
                'text' => 'Découvrez nos services de divination professionnels.',
                'button_text' => 'Réserver une lecture',
                'button_link' => '/consultations.html?type=divination'
            ]
        ];
    }

    // Merge default and category-specific settings
    return array_merge($default_settings, $category_settings);
}
