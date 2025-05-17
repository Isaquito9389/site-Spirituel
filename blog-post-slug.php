<?php
// Affichage des erreurs en mode développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusion de la connexion à la base de données
require_once 'admin/includes/db_connect.php';

// Vérification du slug de l'article
$slug = '';

// Méthode 1: Vérifier si le slug est passé dans l'URL sous forme de paramètre GET
if (isset($_GET['slug']) && !empty($_GET['slug'])) {
    $slug = $_GET['slug'];
    
    // Rediriger vers l'URL propre
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: /blog/' . urlencode($slug));
    exit;
}
// Méthode 2: Vérifier si le slug est dans le chemin (format alternatif /blog-post-slug.php/titre-article)
else {
    // Obtenir le chemin de l'URL requête
    $request_uri = $_SERVER['REQUEST_URI'];
    // Extraire le slug du chemin
    $pattern = '/\/blog-post-slug\.php\/([^\/\?]+)/i';
    if (preg_match($pattern, $request_uri, $matches)) {
        $slug = urldecode($matches[1]);
        
        // Rediriger vers l'URL propre
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: /blog/' . urlencode($slug));
        exit;
    }
}

// Si aucun slug n'a été trouvé, rediriger vers la page blog
if (empty($slug)) {
    header('Location: /blog.php');
    exit;
}

// Si on arrive ici, c'est qu'il y a un problème avec le slug
// Rediriger vers la page blog
header('Location: /blog.php');
exit;
?>
