<?php
// Affichage des erreurs en mode développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusion de la connexion à la base de données
require_once 'admin/includes/db_connect.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Correction des slugs pour les articles de blog</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        h1, h2 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #ffeeba;
            border-radius: 5px;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
        }
        .button {
            display: inline-block;
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
        }
        .button:hover {
            background-color: #45a049;
        }
        .button-secondary {
            background-color: #6c757d;
        }
        .button-secondary:hover {
            background-color: #5a6268;
        }
        .update-item {
            margin-bottom: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .update-item.updated {
            border-left: 4px solid #28a745;
        }
        .update-item.skipped {
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <h1>Correction des slugs pour les articles de blog</h1>";

// Vérifier si la table blog_posts existe
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'blog_posts'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "<div class='error'>
            <h3>Erreur : Table manquante</h3>
            <p>La table 'blog_posts' n'existe pas dans la base de données.</p>
            <p>Vous devez créer la table 'blog_posts' avant de pouvoir utiliser les fonctionnalités de blog.</p>
        </div>";
        exit;
    }
    
    // Vérifier si la colonne slug existe
    $stmt = $pdo->query("SHOW COLUMNS FROM blog_posts LIKE 'slug'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Ajouter la colonne slug
        $pdo->exec("ALTER TABLE blog_posts ADD COLUMN slug VARCHAR(255) UNIQUE AFTER title");
        echo "<div class='success'>
            <h3>Colonne ajoutée</h3>
            <p>La colonne 'slug' a été ajoutée à la table blog_posts.</p>
        </div>";
    }
    
    // Fonction pour générer un slug à partir d'un titre
    function generateSlug($title) {
        // Convertir en minuscules
        $slug = strtolower($title);
        
        // Remplacer les caractères accentués
        $slug = str_replace(
            ['à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'œ', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ'],
            ['a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'oe', 'u', 'u', 'u', 'u', 'y', 'y'],
            $slug
        );
        
        // Remplacer les espaces et caractères spéciaux par des tirets
        $slug = preg_replace('/[^a-z0-9]/', '-', $slug);
        
        // Remplacer les tirets multiples par un seul tiret
        $slug = preg_replace('/-+/', '-', $slug);
        
        // Supprimer les tirets au début et à la fin
        $slug = trim($slug, '-');
        
        return $slug;
    }
    
    // Récupérer tous les articles
    $stmt = $pdo->query("SELECT id, title, slug FROM blog_posts");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($posts) === 0) {
        echo "<div class='warning'>
            <h3>Aucun article trouvé</h3>
            <p>Aucun article n'a été trouvé dans la table blog_posts.</p>
        </div>";
    } else {
        echo "<h2>Mise à jour des slugs pour " . count($posts) . " article(s)</h2>";
        
        $updatedCount = 0;
        $skippedCount = 0;
        
        foreach ($posts as $post) {
            // Si le slug est vide, générer un nouveau slug
            if (empty($post['slug'])) {
                $baseSlug = generateSlug($post['title']);
                $slug = $baseSlug;
                $counter = 1;
                
                // Vérifier si le slug existe déjà
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE slug = ? AND id != ?");
                $checkStmt->execute([$slug, $post['id']]);
                $exists = $checkStmt->fetchColumn();
                
                // Si le slug existe déjà, ajouter un compteur
                while ($exists) {
                    $slug = $baseSlug . '-' . $counter;
                    $checkStmt->execute([$slug, $post['id']]);
                    $exists = $checkStmt->fetchColumn();
                    $counter++;
                }
                
                // Mettre à jour le slug
                $updateStmt = $pdo->prepare("UPDATE blog_posts SET slug = ? WHERE id = ?");
                $updateStmt->execute([$slug, $post['id']]);
                
                echo "<div class='update-item updated'>
                    <strong>Article ID {$post['id']} mis à jour</strong><br>
                    Titre : \"" . htmlspecialchars($post['title']) . "\"<br>
                    Nouveau slug : \"" . htmlspecialchars($slug) . "\"
                </div>";
                
                $updatedCount++;
            } else {
                echo "<div class='update-item skipped'>
                    <strong>Article ID {$post['id']} ignoré</strong><br>
                    Titre : \"" . htmlspecialchars($post['title']) . "\"<br>
                    Slug existant : \"" . htmlspecialchars($post['slug']) . "\"
                </div>";
                
                $skippedCount++;
            }
        }
        
        echo "<div class='success'>
            <h3>Mise à jour terminée</h3>
            <p><strong>Résumé :</strong></p>
            <ul>
                <li>{$updatedCount} article(s) mis à jour avec de nouveaux slugs</li>
                <li>{$skippedCount} article(s) ignorés (slugs déjà existants)</li>
            </ul>
        </div>";
    }
    
    // Vérifier s'il y a des slugs en double
    $stmt = $pdo->query("SELECT slug, COUNT(*) as count FROM blog_posts GROUP BY slug HAVING count > 1");
    $duplicateSlugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($duplicateSlugs) > 0) {
        echo "<div class='warning'>
            <h3>Attention : Slugs en double détectés</h3>
            <p>Les slugs suivants sont utilisés par plusieurs articles :</p>
            <ul>";
        
        foreach ($duplicateSlugs as $duplicate) {
            echo "<li>'{$duplicate['slug']}' est utilisé {$duplicate['count']} fois</li>";
        }
        
        echo "</ul>
            <p>Cela peut causer des problèmes lors de l'accès aux articles. Veuillez corriger manuellement ces slugs dans la base de données.</p>
        </div>";
    }
    
} catch (PDOException $e) {
    echo "<div class='error'>
        <h3>Erreur</h3>
        <p>Une erreur est survenue lors de la mise à jour des slugs : " . htmlspecialchars($e->getMessage()) . "</p>
    </div>";
}

echo "<div style='margin-top: 30px;'>
    <a href='test_blog_links.php' class='button'>Tester les liens de blog</a>
    <a href='index.php' class='button button-secondary' style='margin-left: 10px;'>Retour à l'accueil</a>
</div>
</body>
</html>";
?>
