<?php
// Affichage des erreurs en mode développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusion de la connexion à la base de données
require_once 'admin/includes/db_connect.php';

echo "<h1>Vérification de la table blog_posts</h1>";

try {
    // Vérifier si la table blog_posts existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'blog_posts'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        echo "<p style='color: green;'>✓ La table 'blog_posts' existe.</p>";
        
        // Vérifier la structure de la table
        $stmt = $pdo->query("DESCRIBE blog_posts");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>Structure de la table blog_posts</h2>";
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr>";
        
        $hasSlugColumn = false;
        
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "<td>{$column['Extra']}</td>";
            echo "</tr>";
            
            if ($column['Field'] === 'slug') {
                $hasSlugColumn = true;
            }
        }
        
        echo "</table>";
        
        if ($hasSlugColumn) {
            echo "<p style='color: green;'>✓ La colonne 'slug' existe dans la table 'blog_posts'.</p>";
            
            // Vérifier si des articles ont des slugs vides
            $stmt = $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE slug IS NULL OR slug = ''");
            $emptySlugCount = $stmt->fetchColumn();
            
            if ($emptySlugCount > 0) {
                echo "<p style='color: orange;'>⚠ {$emptySlugCount} article(s) n'ont pas de slug défini.</p>";
                echo "<p>Utilisez le script <a href='update_blog_slugs.php'>update_blog_slugs.php</a> pour générer les slugs manquants.</p>";
            } else {
                echo "<p style='color: green;'>✓ Tous les articles ont un slug défini.</p>";
            }
            
            // Vérifier s'il y a des slugs en double
            $stmt = $pdo->query("SELECT slug, COUNT(*) as count FROM blog_posts GROUP BY slug HAVING count > 1");
            $duplicateSlugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($duplicateSlugs) > 0) {
                echo "<p style='color: red;'>✗ Il y a des slugs en double dans la table :</p>";
                echo "<ul>";
                foreach ($duplicateSlugs as $duplicate) {
                    echo "<li>'{$duplicate['slug']}' est utilisé {$duplicate['count']} fois</li>";
                }
                echo "</ul>";
                echo "<p>Utilisez le script <a href='update_blog_slugs.php'>update_blog_slugs.php</a> pour corriger les slugs en double.</p>";
            } else {
                echo "<p style='color: green;'>✓ Aucun slug en double détecté.</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ La colonne 'slug' n'existe pas dans la table 'blog_posts'.</p>";
            echo "<p>Utilisez le script <a href='update_blog_slugs.php'>update_blog_slugs.php</a> pour ajouter la colonne 'slug' et générer les slugs.</p>";
        }
        
        // Afficher quelques articles pour vérification
        $stmt = $pdo->query("SELECT id, title, slug FROM blog_posts ORDER BY id LIMIT 10");
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($posts) > 0) {
            echo "<h2>Exemples d'articles (10 premiers)</h2>";
            echo "<table border='1' cellpadding='5' cellspacing='0'>";
            echo "<tr><th>ID</th><th>Titre</th><th>Slug</th></tr>";
            
            foreach ($posts as $post) {
                echo "<tr>";
                echo "<td>{$post['id']}</td>";
                echo "<td>{$post['title']}</td>";
                echo "<td>" . (isset($post['slug']) ? $post['slug'] : '<em>non défini</em>') . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p>Aucun article trouvé dans la table 'blog_posts'.</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ La table 'blog_posts' n'existe pas dans la base de données.</p>";
        echo "<p>Vous devez créer la table 'blog_posts' avant de pouvoir utiliser les fonctionnalités de blog.</p>";
    }
} catch (PDOException $e) {
    echo "<h1 style='color: red;'>Erreur</h1>";
    echo "<p>Une erreur est survenue lors de la vérification de la table : " . $e->getMessage() . "</p>";
}
?>
