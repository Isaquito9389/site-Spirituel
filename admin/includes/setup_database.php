<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
/**
 * Database Setup
 * 
 * This file creates the necessary tables for the blog functionality if they don't exist.
 * Includes verification of table creation and improved error handling.
 */

// Include database connection
require_once 'db_connect.php';

// Check if database connection is successful
if (!is_db_connected()) {
    die("Impossible de configurer la base de données: la connexion a échoué.");
}

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
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

// Create blog_posts table
create_table($pdo, 'blog_posts', "CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    excerpt TEXT,
    category VARCHAR(100),
    status ENUM('draft', 'published') DEFAULT 'draft',
    featured_image VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    author VARCHAR(100) DEFAULT 'Admin',
    views INT DEFAULT 0,
    meta_description TEXT,
    meta_keywords TEXT,
    slug VARCHAR(255),
    allow_comments BOOLEAN DEFAULT TRUE
)");

// Create blog_categories table
create_table($pdo, 'blog_categories', "CREATE TABLE IF NOT EXISTS blog_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT,
    parent_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_slug (slug)
)");

// Create blog_post_categories table (for many-to-many relationship)
create_table($pdo, 'blog_post_categories', "CREATE TABLE IF NOT EXISTS blog_post_categories (
    post_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (post_id, category_id),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE CASCADE
)");

// Create blog_tags table
create_table($pdo, 'blog_tags', "CREATE TABLE IF NOT EXISTS blog_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_slug (slug)
)");

// Create blog_post_tags table (for many-to-many relationship)
create_table($pdo, 'blog_post_tags', "CREATE TABLE IF NOT EXISTS blog_post_tags (
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
)");

// Create blog_comments table
create_table($pdo, 'blog_comments', "CREATE TABLE IF NOT EXISTS blog_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    author_name VARCHAR(100) NOT NULL,
    author_email VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    status ENUM('pending', 'approved', 'spam') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES blog_comments(id) ON DELETE SET NULL
)");

// Create rituals table if it doesn't exist
create_table($pdo, 'rituals', "CREATE TABLE IF NOT EXISTS rituals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    excerpt TEXT,
    category VARCHAR(100),
    duration VARCHAR(100),
    price DECIMAL(10,2),
    status ENUM('draft', 'published') DEFAULT 'draft',
    featured_image VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    author VARCHAR(100) DEFAULT 'Admin',
    views INT DEFAULT 0,
    meta_description TEXT,
    meta_keywords TEXT,
    slug VARCHAR(255)
)");

// Create users table if it doesn't exist
create_table($pdo, 'users', "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100),
    role ENUM('admin', 'editor', 'author') DEFAULT 'author',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME,
    status ENUM('active', 'inactive') DEFAULT 'active'
)");

// Check if all tables were created successfully
$all_tables_created = !in_array(false, $tables_status);

// Output status
if ($all_tables_created) {
    echo "<div class='success'>Toutes les tables ont été créées avec succès!</div>";
} else {
    echo "<div class='error'>Certaines tables n'ont pas pu être créées. Consultez les logs pour plus de détails.</div>";
    echo "<ul>";
    foreach ($tables_status as $table => $status) {
        echo "<li>$table: " . ($status ? 'Créée' : 'Échec') . "</li>";
    }
    echo "</ul>";
}
