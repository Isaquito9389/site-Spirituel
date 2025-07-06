<?php
/**
 * Backlinks Database Setup
 * 
 * This file creates the necessary tables for the backlinks functionality.
 */

// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';

// Include database connection
require_once 'db_connect.php';

// Check if database connection is successful
if (!is_db_connected()) {
    die("Impossible de configurer la base de données: la connexion a échoué.");
}

// Array to track created tables
$tables_status = [];

// Function to create table and track status
function create_table($pdo, $table_name, $sql) {
    global $tables_status;
    try {
        $pdo->exec($sql);
        
        // Verify table exists
        $stmt = $pdo->query("SHOW TABLES LIKE '$table_name'");
        if ($stmt->rowCount() > 0) {
            $tables_status[$table_name] = true;
            return true;
        } else {
            $tables_status[$table_name] = false;
            return false;
        }
    } catch (PDOException $e) {
        $error_message = date('[Y-m-d H:i:s] ') . "Erreur lors de la création de la table $table_name: " . $e->getMessage() . PHP_EOL;
        $tables_status[$table_name] = false;
        return false;
    }
}

// Create backlinks table
create_table($pdo, 'backlinks', "CREATE TABLE IF NOT EXISTS backlinks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_type ENUM('blog', 'ritual') NOT NULL,
    content_id INT NOT NULL,
    url VARCHAR(500) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    domain VARCHAR(255),
    status ENUM('active', 'inactive', 'broken') DEFAULT 'active',
    type ENUM('reference', 'source', 'related', 'inspiration') DEFAULT 'reference',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_checked DATETIME,
    INDEX idx_content (content_type, content_id),
    INDEX idx_domain (domain),
    INDEX idx_status (status)
)");

// Create internal_links table (for cross-references between blog posts and rituals)
create_table($pdo, 'internal_links', "CREATE TABLE IF NOT EXISTS internal_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_type ENUM('blog', 'ritual') NOT NULL,
    source_id INT NOT NULL,
    target_type ENUM('blog', 'ritual') NOT NULL,
    target_id INT NOT NULL,
    anchor_text VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_source (source_type, source_id),
    INDEX idx_target (target_type, target_id),
    UNIQUE KEY unique_link (source_type, source_id, target_type, target_id)
)");

// Create backlink_categories table
create_table($pdo, 'backlink_categories', "CREATE TABLE IF NOT EXISTS backlink_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#3a0ca3',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name)
)");

// Insert default backlink categories
try {
    $default_categories = [
        ['name' => 'Sources Spirituelles', 'description' => 'Références à des textes sacrés, livres spirituels', 'color' => '#7209b7'],
        ['name' => 'Ressources Externes', 'description' => 'Liens vers des sites web externes utiles', 'color' => '#3a0ca3'],
        ['name' => 'Inspirations', 'description' => 'Sources d\'inspiration pour les rituels', 'color' => '#f72585'],
        ['name' => 'Références Académiques', 'description' => 'Articles scientifiques, études', 'color' => '#4cc9f0'],
        ['name' => 'Communauté', 'description' => 'Liens vers des communautés spirituelles', 'color' => '#7209b7']
    ];
    
    foreach ($default_categories as $category) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO backlink_categories (name, description, color) VALUES (?, ?, ?)");
        $stmt->execute([$category['name'], $category['description'], $category['color']]);
    }
} catch (PDOException $e) {
    // Ignore errors for default data insertion
}

// Add backlinks columns to existing tables if they don't exist
try {
    // Check if wp_post_id column exists in blog_posts
    $stmt = $pdo->query("SHOW COLUMNS FROM blog_posts LIKE 'wp_post_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE blog_posts ADD COLUMN wp_post_id INT DEFAULT NULL");
    }
    
    // Check if backlinks_enabled column exists in blog_posts
    $stmt = $pdo->query("SHOW COLUMNS FROM blog_posts LIKE 'backlinks_enabled'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE blog_posts ADD COLUMN backlinks_enabled BOOLEAN DEFAULT TRUE");
    }
    
    // Check if backlinks_enabled column exists in rituals
    $stmt = $pdo->query("SHOW COLUMNS FROM rituals LIKE 'backlinks_enabled'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE rituals ADD COLUMN backlinks_enabled BOOLEAN DEFAULT TRUE");
    }
    
} catch (PDOException $e) {
    // Log error but continue
    error_log("Erreur lors de l'ajout des colonnes backlinks: " . $e->getMessage());
}

// Check if all tables were created successfully
$all_tables_created = !in_array(false, $tables_status);

// Output status
if ($all_tables_created) {
    echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; margin: 15px 0; border-radius: 5px; border: 1px solid #c3e6cb;'>";
    echo "<h3>✅ Configuration des backlinks terminée avec succès!</h3>";
    echo "<p>Toutes les tables nécessaires pour les backlinks ont été créées.</p>";
    echo "</div>";
} else {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; margin: 15px 0; border-radius: 5px; border: 1px solid #f5c6cb;'>";
    echo "<h3>❌ Erreur lors de la configuration des backlinks</h3>";
    echo "<p>Certaines tables n'ont pas pu être créées:</p>";
    echo "<ul>";
    foreach ($tables_status as $table => $status) {
        echo "<li>$table: " . ($status ? '✅ Créée' : '❌ Échec') . "</li>";
    }
    echo "</ul>";
    echo "</div>";
}

// Display created tables info
echo "<div style='background-color: #d1ecf1; color: #0c5460; padding: 15px; margin: 15px 0; border-radius: 5px; border: 1px solid #bee5eb;'>";
echo "<h4>📋 Tables créées pour les backlinks:</h4>";
echo "<ul>";
echo "<li><strong>backlinks</strong> - Stockage des liens externes</li>";
echo "<li><strong>internal_links</strong> - Liens internes entre articles et rituels</li>";
echo "<li><strong>backlink_categories</strong> - Catégories de backlinks</li>";
echo "</ul>";
echo "<p><strong>Colonnes ajoutées:</strong></p>";
echo "<ul>";
echo "<li><strong>blog_posts.backlinks_enabled</strong> - Activer/désactiver les backlinks par article</li>";
echo "<li><strong>rituals.backlinks_enabled</strong> - Activer/désactiver les backlinks par rituel</li>";
echo "</ul>";
echo "</div>";
?>
