<?php
/**
 * Admin Panel Fix Script
 * 
 * This script diagnoses and fixes common issues with the admin panel,
 * particularly focusing on database structure and error handling.
 */

// Start session
session_start();

// Include database connection
require_once 'admin/includes/db_connect.php';

// Set error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Function to check if a table exists
function table_exists($pdo, $table) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        return $result->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Function to check if a column exists in a table
function column_exists($pdo, $table, $column) {
    try {
        $result = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $result->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Function to create a table if it doesn't exist
function create_table_if_not_exists($pdo, $table, $sql) {
    if (!table_exists($pdo, $table)) {
        try {
            $pdo->exec($sql);
            echo "<p class='success'>Table '$table' created successfully.</p>";
        } catch (PDOException $e) {
            echo "<p class='error'>Error creating table '$table': " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p class='info'>Table '$table' already exists.</p>";
    }
}

// Function to add a column if it doesn't exist
function add_column_if_not_exists($pdo, $table, $column, $definition) {
    if (table_exists($pdo, $table) && !column_exists($pdo, $table, $column)) {
        try {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            echo "<p class='success'>Column '$column' added to table '$table'.</p>";
        } catch (PDOException $e) {
            echo "<p class='error'>Error adding column '$column' to table '$table': " . $e->getMessage() . "</p>";
        }
    }
}

// Function to check and fix foreign key constraints
function fix_foreign_key_constraints($pdo) {
    // Check blog_post_categories foreign keys
    if (table_exists($pdo, 'blog_post_categories')) {
        try {
            // Check if posts exist for each post_id in blog_post_categories
            $orphaned = $pdo->query("
                SELECT bpc.post_id, bpc.category_id 
                FROM blog_post_categories bpc 
                LEFT JOIN blog_posts bp ON bpc.post_id = bp.id 
                WHERE bp.id IS NULL
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($orphaned) > 0) {
                $pdo->exec("DELETE FROM blog_post_categories WHERE post_id IN (
                    SELECT bpc.post_id 
                    FROM blog_post_categories bpc 
                    LEFT JOIN blog_posts bp ON bpc.post_id = bp.id 
                    WHERE bp.id IS NULL
                )");
                echo "<p class='success'>Removed " . count($orphaned) . " orphaned records from blog_post_categories.</p>";
            }
            
            // Check if categories exist for each category_id in blog_post_categories
            $orphaned = $pdo->query("
                SELECT bpc.post_id, bpc.category_id 
                FROM blog_post_categories bpc 
                LEFT JOIN blog_categories bc ON bpc.category_id = bc.id 
                WHERE bc.id IS NULL
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($orphaned) > 0) {
                $pdo->exec("DELETE FROM blog_post_categories WHERE category_id IN (
                    SELECT bpc.category_id 
                    FROM blog_post_categories bpc 
                    LEFT JOIN blog_categories bc ON bpc.category_id = bc.id 
                    WHERE bc.id IS NULL
                )");
                echo "<p class='success'>Removed " . count($orphaned) . " orphaned records from blog_post_categories.</p>";
            }
        } catch (PDOException $e) {
            echo "<p class='error'>Error fixing foreign key constraints: " . $e->getMessage() . "</p>";
        }
    }
    
    // Similar checks for blog_post_tags
    if (table_exists($pdo, 'blog_post_tags')) {
        try {
            // Check if posts exist for each post_id in blog_post_tags
            $orphaned = $pdo->query("
                SELECT bpt.post_id, bpt.tag_id 
                FROM blog_post_tags bpt 
                LEFT JOIN blog_posts bp ON bpt.post_id = bp.id 
                WHERE bp.id IS NULL
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($orphaned) > 0) {
                $pdo->exec("DELETE FROM blog_post_tags WHERE post_id IN (
                    SELECT bpt.post_id 
                    FROM blog_post_tags bpt 
                    LEFT JOIN blog_posts bp ON bpt.post_id = bp.id 
                    WHERE bp.id IS NULL
                )");
                echo "<p class='success'>Removed " . count($orphaned) . " orphaned records from blog_post_tags.</p>";
            }
            
            // Check if tags exist for each tag_id in blog_post_tags
            $orphaned = $pdo->query("
                SELECT bpt.post_id, bpt.tag_id 
                FROM blog_post_tags bpt 
                LEFT JOIN blog_tags bt ON bpt.tag_id = bt.id 
                WHERE bt.id IS NULL
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($orphaned) > 0) {
                $pdo->exec("DELETE FROM blog_post_tags WHERE tag_id IN (
                    SELECT bpt.tag_id 
                    FROM blog_post_tags bpt 
                    LEFT JOIN blog_tags bt ON bpt.tag_id = bt.id 
                    WHERE bt.id IS NULL
                )");
                echo "<p class='success'>Removed " . count($orphaned) . " orphaned records from blog_post_tags.</p>";
            }
        } catch (PDOException $e) {
            echo "<p class='error'>Error fixing foreign key constraints: " . $e->getMessage() . "</p>";
        }
    }
}

// Function to create metadata tables for categories
function create_metadata_tables($pdo) {
    // Create blog_category_metadata table
    create_table_if_not_exists($pdo, 'blog_category_metadata', "
        CREATE TABLE blog_category_metadata (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            meta_key VARCHAR(100) NOT NULL,
            meta_value TEXT,
            UNIQUE KEY unique_meta (category_id, meta_key),
            FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE CASCADE
        )
    ");
    
    // Create ritual_category_metadata table
    create_table_if_not_exists($pdo, 'ritual_category_metadata', "
        CREATE TABLE ritual_category_metadata (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            meta_key VARCHAR(100) NOT NULL,
            meta_value TEXT,
            UNIQUE KEY unique_meta (category_id, meta_key)
        )
    ");
    
    // Create product_category_metadata table
    create_table_if_not_exists($pdo, 'product_category_metadata', "
        CREATE TABLE product_category_metadata (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            meta_key VARCHAR(100) NOT NULL,
            meta_value TEXT,
            UNIQUE KEY unique_meta (category_id, meta_key)
        )
    ");
}

// Function to add missing columns to existing tables
function add_missing_columns($pdo) {
    // Add missing columns to blog_categories
    add_column_if_not_exists($pdo, 'blog_categories', 'icon', "VARCHAR(50) DEFAULT 'fas fa-folder'");
    add_column_if_not_exists($pdo, 'blog_categories', 'color', "VARCHAR(20) DEFAULT '#7209b7'");
    add_column_if_not_exists($pdo, 'blog_categories', 'featured', "BOOLEAN DEFAULT FALSE");
    
    // Add missing columns to rituals
    add_column_if_not_exists($pdo, 'rituals', 'slug', "VARCHAR(255)");
    
    // Add missing columns to users
    add_column_if_not_exists($pdo, 'users', 'status', "ENUM('active', 'inactive') DEFAULT 'active'");
}

// Function to fix WordPress API connection issues
function fix_wp_api_connection() {
    $wp_api_file = 'admin/includes/wp_api_connect.php';
    
    if (file_exists($wp_api_file)) {
        $content = file_get_contents($wp_api_file);
        
        // Add error handling for API calls
        if (strpos($content, 'try {') === false) {
            $new_content = str_replace(
                'function send_to_wordpress($endpoint, $data, $method = \'POST\') {',
                'function send_to_wordpress($endpoint, $data, $method = \'POST\') {
    try {',
                $content
            );
            
            $new_content = str_replace(
                'return [
            \'success\' => false,
            \'error\' => $response,
            \'status\' => $status
        ];',
                'return [
            \'success\' => false,
            \'error\' => $response,
            \'status\' => $status
        ];
    } catch (Exception $e) {
        return [
            \'success\' => false,
            \'error\' => "API Connection Error: " . $e->getMessage(),
            \'status\' => 0
        ];
    }',
                $new_content
            );
            
            file_put_contents($wp_api_file, $new_content);
            echo "<p class='success'>WordPress API connection file updated with better error handling.</p>";
        } else {
            echo "<p class='info'>WordPress API connection file already has error handling.</p>";
        }
    } else {
        echo "<p class='error'>WordPress API connection file not found.</p>";
    }
}

// Function to add error handling to admin pages
function add_error_handling_to_admin_pages() {
    $admin_files = [
        'admin/rituals.php',
        'admin/categories.php',
        'admin/tags.php',
        'admin/blog.php'
    ];
    
    foreach ($admin_files as $file) {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            
            // Add error handling at the top of the file
            if (strpos($content, 'set_error_handler') === false) {
                $new_content = str_replace(
                    '<?php',
                    '<?php
// Custom error handler to prevent 500 errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (error_reporting() === 0) {
        return false;
    }
    
    $error_message = "Error [$errno] $errstr - $errfile:$errline";
    error_log($error_message);
    
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        echo "<div style=\"padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin-bottom: 20px;\">
            <h3>Une erreur est survenue</h3>
            <p>Nous avons rencontré un problème lors du traitement de votre demande. Veuillez réessayer plus tard ou contacter l\'administrateur.</p>
            <p><a href=\"dashboard.php\" style=\"color: #721c24; text-decoration: underline;\">Retour au tableau de bord</a></p>
        </div>";
        
        // Log detailed error for admin
        if (isset($_SESSION[\'admin_logged_in\']) && $_SESSION[\'admin_logged_in\'] === true) {
            echo "<div style=\"padding: 20px; background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; border-radius: 5px; margin-top: 20px;\">
                <h4>Détails de l\'erreur (visible uniquement pour les administrateurs)</h4>
                <p>" . htmlspecialchars($error_message) . "</p>
            </div>";
        }
        
        return true;
    }
    
    return false;
}, E_ALL);',
                    $content
                );
                
                file_put_contents($file, $new_content);
                echo "<p class='success'>Added error handling to $file.</p>";
            } else {
                echo "<p class='info'>File $file already has error handling.</p>";
            }
        } else {
            echo "<p class='error'>File $file not found.</p>";
        }
    }
}

// Function to create missing directories
function create_missing_directories() {
    $directories = [
        'uploads',
        'uploads/blog',
        'uploads/rituals',
        'admin/logs'
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            if (mkdir($dir, 0755, true)) {
                echo "<p class='success'>Directory '$dir' created successfully.</p>";
            } else {
                echo "<p class='error'>Failed to create directory '$dir'.</p>";
            }
        } else {
            echo "<p class='info'>Directory '$dir' already exists.</p>";
        }
    }
}

// Function to fix database connection issues
function fix_db_connection() {
    $db_file = 'admin/includes/db_connect.php';
    
    if (file_exists($db_file)) {
        $content = file_get_contents($db_file);
        
        // Add better error handling
        if (strpos($content, 'PDO::ATTR_PERSISTENT') === false) {
            $new_content = str_replace(
                '$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];',
                '$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => true, // Use persistent connections
    PDO::ATTR_TIMEOUT            => 5,    // 5 second timeout
];',
                $content
            );
            
            file_put_contents($db_file, $new_content);
            echo "<p class='success'>Database connection file updated with better connection options.</p>";
        } else {
            echo "<p class='info'>Database connection file already has optimized options.</p>";
        }
    } else {
        echo "<p class='error'>Database connection file not found.</p>";
    }
}

// HTML header
echo '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel Fix - Mystica Occulta</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #3a0ca3;
            border-bottom: 2px solid #7209b7;
            padding-bottom: 10px;
        }
        .success {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .info {
            color: #0c5460;
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .button {
            display: inline-block;
            background-color: #7209b7;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .button:hover {
            background-color: #3a0ca3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Panel Fix - Mystica Occulta</h1>
        <p>Cet outil va diagnostiquer et corriger les problèmes courants avec le panneau d\'administration.</p>
        
        <h2>Diagnostic et réparation</h2>';

// Run fixes if requested
if (isset($_GET['fix']) && $_GET['fix'] === 'true') {
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Create missing directories
        create_missing_directories();
        
        // Create metadata tables
        create_metadata_tables($pdo);
        
        // Add missing columns
        add_missing_columns($pdo);
        
        // Fix foreign key constraints
        fix_foreign_key_constraints($pdo);
        
        // Fix WordPress API connection
        fix_wp_api_connection();
        
        // Add error handling to admin pages
        add_error_handling_to_admin_pages();
        
        // Fix database connection
        fix_db_connection();
        
        // Commit transaction
        $pdo->commit();
        
        echo '<div class="success">
            <strong>Réparation terminée!</strong> Les problèmes potentiels ont été diagnostiqués et corrigés.
        </div>';
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        echo '<div class="error">
            <strong>Erreur:</strong> ' . $e->getMessage() . '
        </div>';
    }
    
    echo '<a href="admin/dashboard.php" class="button">Retour au tableau de bord</a>';
} else {
    // Show confirmation button
    echo '<p>Cliquez sur le bouton ci-dessous pour lancer le diagnostic et la réparation automatique.</p>
    <p><strong>Note:</strong> Cette opération va:</p>
    <ul>
        <li>Créer les tables manquantes dans la base de données</li>
        <li>Ajouter les colonnes manquantes aux tables existantes</li>
        <li>Corriger les contraintes de clé étrangère</li>
        <li>Améliorer la gestion des erreurs dans les fichiers PHP</li>
        <li>Créer les répertoires manquants</li>
    </ul>
    
    <a href="?fix=true" class="button">Lancer la réparation</a>';
}

// HTML footer
echo '
    </div>
</body>
</html>';
