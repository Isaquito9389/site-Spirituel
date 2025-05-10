<?php
/**
 * Database Setup for InfinityFree
 * 
 * Ce script crée toutes les tables nécessaires pour le site sur InfinityFree
 */

// Configuration de la base de données
$db_host = 'sql310.infinityfree.com';
$db_name = 'if0_36264299_mysticaoculta';
$db_user = 'if0_36264299';
$db_pass = 'wkqR0EhzO8CILv';
$db_charset = 'utf8mb4';

// Connexion à la base de données
try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    echo "<p style='color: green;'>Connexion à la base de données réussie.</p>";
    
    // Array pour suivre les tables créées
    $tables_status = [];
    
    // Fonction pour créer une table et suivre son statut
    function create_table($pdo, $table_name, $sql) {
        global $tables_status;
        try {
            $pdo->exec($sql);
            
            // Vérifier si la table existe
            $stmt = $pdo->query("SHOW TABLES LIKE '$table_name'");
            if ($stmt->rowCount() > 0) {
                $tables_status[$table_name] = true;
                echo "<p>Table '$table_name' créée avec succès.</p>";
                return true;
            } else {
                $tables_status[$table_name] = false;
                echo "<p style='color: red;'>Échec de la création de la table '$table_name'.</p>";
                return false;
            }
        } catch (PDOException $e) {
            $tables_status[$table_name] = false;
            echo "<p style='color: red;'>Erreur lors de la création de la table '$table_name': " . $e->getMessage() . "</p>";
            return false;
        }
    }
    
    // Créer la table blog_posts
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
        allow_comments BOOLEAN DEFAULT TRUE,
        wp_post_id INT NULL DEFAULT NULL
    )");
    
    // Créer la table blog_categories
    create_table($pdo, 'blog_categories', "CREATE TABLE IF NOT EXISTS blog_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL,
        description TEXT,
        parent_id INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_slug (slug)
    )");
    
    // Créer la table blog_post_categories (pour la relation many-to-many)
    create_table($pdo, 'blog_post_categories', "CREATE TABLE IF NOT EXISTS blog_post_categories (
        post_id INT NOT NULL,
        category_id INT NOT NULL,
        PRIMARY KEY (post_id, category_id),
        FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE CASCADE
    )");
    
    // Créer la table blog_tags
    create_table($pdo, 'blog_tags', "CREATE TABLE IF NOT EXISTS blog_tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_slug (slug)
    )");
    
    // Créer la table blog_post_tags (pour la relation many-to-many)
    create_table($pdo, 'blog_post_tags', "CREATE TABLE IF NOT EXISTS blog_post_tags (
        post_id INT NOT NULL,
        tag_id INT NOT NULL,
        PRIMARY KEY (post_id, tag_id),
        FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
    )");
    
    // Créer la table blog_comments
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
    
    // Créer la table rituals
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
    
    // Créer la table users
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
    
    // Vérifier si toutes les tables ont été créées avec succès
    $all_tables_created = !in_array(false, $tables_status);
    
    // Afficher le statut
    if ($all_tables_created) {
        echo "<p style='color: green; font-weight: bold;'>Toutes les tables ont été créées avec succès!</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>Certaines tables n'ont pas pu être créées.</p>";
        echo "<ul>";
        foreach ($tables_status as $table => $status) {
            echo "<li>$table: " . ($status ? '<span style="color: green;">Créée</span>' : '<span style="color: red;">Échec</span>') . "</li>";
        }
        echo "</ul>";
    }
    
    // Vérifier si un utilisateur admin existe déjà
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $admin_exists = $stmt->fetchColumn() > 0;
    
    // Créer un utilisateur admin par défaut si aucun n'existe
    if (!$admin_exists) {
        try {
            $username = 'admin';
            $password = password_hash('admin123', PASSWORD_DEFAULT); // Mot de passe par défaut
            $email = 'admin@example.com';
            
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'admin')");
            $stmt->execute([$username, $password, $email]);
            
            echo "<p style='color: green;'>Utilisateur admin créé avec succès. Nom d'utilisateur: admin, Mot de passe: admin123</p>";
            echo "<p style='color: orange; font-weight: bold;'>IMPORTANT: Veuillez changer ce mot de passe dès que possible!</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Erreur lors de la création de l'utilisateur admin: " . $e->getMessage() . "</p>";
        }
    }
    
} catch (PDOException $e) {
    die("<p style='color: red;'>Erreur de connexion à la base de données: " . $e->getMessage() . "</p>");
}

// Lien pour retourner à l'administration
echo "<p><a href='admin/index.php'>Aller au panneau d'administration</a></p>";
?>
