<?php
/**
 * Script de mise à jour de la base de données pour InfinityFree
 * Ce script ajoute la colonne wp_post_id à la table blog_posts
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
    echo "<p>Connexion à la base de données réussie.</p>";
    
    // Vérifier si la table blog_posts existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'blog_posts'");
    if ($stmt->rowCount() == 0) {
        die("<p style='color: red;'>La table blog_posts n'existe pas. Veuillez d'abord créer la table.</p>");
    }
    
    // Vérifier si la colonne wp_post_id existe déjà
    $stmt = $pdo->query("SHOW COLUMNS FROM blog_posts LIKE 'wp_post_id'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Ajouter la colonne wp_post_id
        $pdo->exec("ALTER TABLE blog_posts ADD COLUMN wp_post_id INT NULL DEFAULT NULL");
        echo "<p style='color: green;'>La colonne wp_post_id a été ajoutée avec succès à la table blog_posts.</p>";
    } else {
        echo "<p>La colonne wp_post_id existe déjà dans la table blog_posts.</p>";
    }
    
    echo "<p>Mise à jour de la base de données terminée.</p>";
    
} catch (PDOException $e) {
    die("<p style='color: red;'>Erreur de connexion à la base de données: " . $e->getMessage() . "</p>");
}

// Lien pour retourner à l'administration
echo "<p><a href='admin/blog.php'>Retourner à l'administration</a></p>";
?>
