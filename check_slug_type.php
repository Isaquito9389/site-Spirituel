<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';

// Fichier : check_slug_type.php
// Rôle : Déterminer le type de contenu basé sur le slug et rediriger vers la bonne page
// Utilisé par .htaccess pour les URLs propres

// Inclusion de la connexion à la base de données
require_once 'includes/db_connect.php';

// Récupérer le slug depuis l'URL
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    // Pas de slug, rediriger vers l'accueil
    header('Location: /');
    exit;
}

// Nettoyer le slug
$slug = preg_replace('/[^a-zA-Z0-9\-_]/', '', $slug);

if (empty($slug)) {
    // Slug invalide après nettoyage
    header('Location: /');
    exit;
}

try {
    // Vérifier d'abord si c'est un rituel
    $stmt = $pdo->prepare("SELECT id, slug, title FROM rituals WHERE slug = ? AND status = 'published' LIMIT 1");
    $stmt->execute([$slug]);
    $ritual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ritual) {
        // C'est un rituel, inclure la page ritual.php
        $_GET['slug'] = $slug;
        include 'ritual.php';
        exit;
    }
    
    // Vérifier si c'est un article de blog
    $stmt = $pdo->prepare("SELECT id, slug, title FROM blog_posts WHERE slug = ? AND status = 'published' LIMIT 1");
    $stmt->execute([$slug]);
    $blog_post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($blog_post) {
        // C'est un article de blog, rediriger vers blog-post.php
        header('Location: /blog-post.php?slug=' . urlencode($slug));
        exit;
    }
    
    // Vérifier si c'est un produit
    $stmt = $pdo->prepare("SELECT id, slug, title FROM products WHERE slug = ? AND status = 'published' LIMIT 1");
    $stmt->execute([$slug]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        // C'est un produit, rediriger vers product.php
        header('Location: /product.php?slug=' . urlencode($slug));
        exit;
    }
    
} catch (PDOException $e) {
    // Erreur de base de données, log et rediriger vers 404
    error_log("Erreur check_slug_type.php: " . $e->getMessage());
}

// Aucun contenu trouvé, rediriger vers 404
header('HTTP/1.1 404 Not Found');
header('Location: /error.php?code=404');
exit;
?>
