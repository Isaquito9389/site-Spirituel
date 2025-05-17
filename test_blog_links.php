<?php
// Affichage des erreurs en mode développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusion de la connexion à la base de données
require_once 'admin/includes/db_connect.php';

// Récupérer un article de blog pour le test
try {
    $stmt = $pdo->query("SELECT id, title, slug FROM blog_posts WHERE status = 'published' AND slug IS NOT NULL AND slug != '' ORDER BY created_at DESC LIMIT 1");
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; margin-bottom: 20px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
        echo "<h3>Aucun article de blog avec slug trouvé</h3>";
        echo "<p>Il semble qu'aucun article de blog avec un slug valide n'a été trouvé dans la base de données.</p>";
        echo "<p>Veuillez exécuter le script <a href='update_blog_slugs.php' style='color: #721c24; text-decoration: underline;'>update_blog_slugs.php</a> pour générer les slugs manquants.</p>";
        echo "</div>";
        
        // Vérifier si des articles existent mais sans slug
        $stmt = $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'");
        $totalPosts = $stmt->fetchColumn();
        
        if ($totalPosts > 0) {
            echo "<p>Il y a {$totalPosts} article(s) publié(s) dans la base de données, mais aucun n'a de slug défini.</p>";
        } else {
            echo "<p>Aucun article publié n'a été trouvé dans la base de données.</p>";
        }
        
        exit;
    }
} catch (PDOException $e) {
    echo "<p>Erreur lors de la récupération de l'article: " . $e->getMessage() . "</p>";
    exit;
}

// Informations sur l'URL actuelle
$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

// Générer les différentes URL pour le test
$clean_url = $base_url . "/blog/" . urlencode($post['slug']);
$old_url = $base_url . "/blog-post.php?slug=" . urlencode($post['slug']);
$slug_url = $base_url . "/blog-post-slug.php?slug=" . urlencode($post['slug']);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test des liens de blog</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        h1 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .test-section {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .test-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .url {
            background-color: #f5f5f5;
            padding: 8px;
            border-radius: 3px;
            font-family: monospace;
            word-break: break-all;
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
        .note {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <h1>Test des liens de blog</h1>
    
    <div class="note">
        <p>Cette page permet de tester si les liens vers les articles de blog fonctionnent correctement avec les différents formats d'URL.</p>
        <p>L'article de test utilisé est : <strong><?php echo htmlspecialchars($post['title']); ?></strong> (ID: <?php echo $post['id']; ?>, Slug: <?php echo htmlspecialchars($post['slug']); ?>)</p>
    </div>
    
    <div class="test-section">
        <div class="test-title">1. Format d'URL propre (recommandé)</div>
        <div class="url"><?php echo htmlspecialchars($clean_url); ?></div>
        <p>Ce format utilise la réécriture d'URL pour une meilleure SEO et une meilleure expérience utilisateur.</p>
        <a href="<?php echo htmlspecialchars($clean_url); ?>" class="button" target="_blank">Tester ce lien</a>
    </div>
    
    <div class="test-section">
        <div class="test-title">2. Format d'URL avec blog-post.php</div>
        <div class="url"><?php echo htmlspecialchars($old_url); ?></div>
        <p>Ce format utilise le fichier blog-post.php avec le paramètre slug. Il devrait rediriger vers le format d'URL propre.</p>
        <a href="<?php echo htmlspecialchars($old_url); ?>" class="button" target="_blank">Tester ce lien</a>
    </div>
    
    <div class="test-section">
        <div class="test-title">3. Format d'URL avec blog-post-slug.php (ancien)</div>
        <div class="url"><?php echo htmlspecialchars($slug_url); ?></div>
        <p>Ce format utilise l'ancien fichier blog-post-slug.php. Il pourrait ne pas fonctionner correctement si ce fichier n'est plus utilisé.</p>
        <a href="<?php echo htmlspecialchars($slug_url); ?>" class="button" target="_blank">Tester ce lien</a>
    </div>
    
    <div class="note">
        <p><strong>Note:</strong> Si les liens fonctionnent correctement, les formats 2 et 3 devraient rediriger vers le format 1 (URL propre).</p>
    </div>
    
    <p><a href="index.php" style="color: #0066cc;">Retour à l'accueil</a></p>
</body>
</html>
