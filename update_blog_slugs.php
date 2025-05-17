<?php
// Affichage des erreurs en mode développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusion de la connexion à la base de données
require_once 'admin/includes/db_connect.php';

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

// Vérifier si la colonne slug existe dans la table blog_posts
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM blog_posts LIKE 'slug'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Ajouter la colonne slug
        $pdo->exec("ALTER TABLE blog_posts ADD COLUMN slug VARCHAR(255) UNIQUE AFTER title");
        echo "<p>Colonne 'slug' ajoutée à la table blog_posts.</p>";
    }
    
    // Récupérer tous les articles
    $stmt = $pdo->query("SELECT id, title, slug FROM blog_posts");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h1>Mise à jour des slugs pour les articles</h1>";
    
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
            
            echo "<p>Article ID {$post['id']} : \"{$post['title']}\" => Slug mis à jour : \"{$slug}\"</p>";
        } else {
            echo "<p>Article ID {$post['id']} : \"{$post['title']}\" => Slug déjà existant : \"{$post['slug']}\"</p>";
        }
    }
    
    echo "<p>Mise à jour terminée !</p>";
    
} catch (PDOException $e) {
    echo "<h1>Erreur</h1>";
    echo "<p>Une erreur est survenue lors de la mise à jour des slugs : " . $e->getMessage() . "</p>";
}
?>
