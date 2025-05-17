<?php
/**
 * Fichier pour récupérer les images du site depuis la bibliothèque d'images
 * Ce fichier est utilisé par index.php pour charger dynamiquement les images
 */

// Fonction pour récupérer une image par sa catégorie
function get_image_by_category($pdo, $category, $default_image) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM image_library WHERE category = ? ORDER BY uploaded_at DESC LIMIT 1");
        $stmt->execute([$category]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($image) {
            // Si l'image est externe, retourner directement le chemin
            if ($image['is_external']) {
                return $image['image_path'];
            } else {
                // Si l'image est locale, vérifier si elle est dans le dossier d'upload
                $image_path = $image['image_path'];
                // Si l'image est dans le dossier uploads/images/, retourner le chemin
                if (strpos($image_path, 'uploads/images/') !== false) {
                    return $image_path;
                }
                
                // Vérifier si l'image existe dans le dossier uploads/images/ avec le même nom de base
                $pathinfo = pathinfo($default_image);
                $basename = $pathinfo['filename'];
                $extensions = ['png', 'jpg', 'jpeg', 'webp'];
                foreach ($extensions as $ext) {
                    $server_path = 'uploads/images/' . $basename . '.' . $ext;
                    if (file_exists($server_path)) {
                        return $server_path;
                    }
                }
                
                // Vérifier également dans htdocs/uploads/images/ (ancienne structure)
                foreach ($extensions as $ext) {
                    $htdoc_path = 'htdocs/uploads/images/' . $basename . '.' . $ext;
                    if (file_exists($htdoc_path)) {
                        // Si trouvé dans htdocs, copier vers uploads pour normaliser
                        $target_path = 'uploads/images/' . $basename . '.' . $ext;
                        if (!file_exists($target_path)) {
                            copy($htdoc_path, $target_path);
                        }
                        return $target_path;
                    }
                }
                
                // Si on n'a pas trouvé d'image uploadée, utiliser le chemin de la DB
                return $image_path;
            }
        }
    } catch (PDOException $e) {
        // En cas d'erreur, logger l'erreur mais continuer avec l'image par défaut
        error_log("Erreur lors de la récupération de l'image '$category': " . $e->getMessage());
    }
    
    // Vérifier si une version png existe dans uploads/images/
    $pathinfo = pathinfo($default_image);
    $basename = $pathinfo['filename'];
    $png_server_path = 'uploads/images/' . $basename . '.png';
    if (file_exists($png_server_path)) {
        return $png_server_path;
    }
    
    // Vérifier également dans htdocs/uploads/images/
    $htdoc_png_path = 'htdocs/uploads/images/' . $basename . '.png';
    if (file_exists($htdoc_png_path)) {
        // Si trouvé dans htdocs, copier vers uploads pour normaliser
        if (!file_exists($png_server_path)) {
            copy($htdoc_png_path, $png_server_path);
        }
        return $png_server_path;
    }
    
    // Si aucune image n'est trouvée ou en cas d'erreur, retourner l'image par défaut
    return $default_image;
}

// Vérifier si la connexion à la base de données est disponible
if (!isset($pdo)) {
    // Si ce fichier est appelé directement, inclure la connexion à la base de données
    if (!defined('DB_INCLUDED')) {
        require_once 'admin/includes/db_connect.php';
        define('DB_INCLUDED', true);
    }
}

// Définir les catégories d'images et leurs valeurs par défaut
$image_categories = [
    'background_main' => 'assets/images/background-main.jpg', // Accepte aussi .png
    'vodoun_bg' => 'assets/images/vodoun-bg.png', // Changé en .png
    'vodoun_ritual' => 'assets/images/vodoun/ritual-main.jpg',
    'love_ritual' => 'https://images.unsplash.com/photo-1516589178581-6cd7833ae3b2?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80', // Image par défaut pour la section Magie de l'Amour
    'product_bougie' => 'assets/images/products/bougie-rouge.jpg',
    'product_miroir' => 'assets/images/products/miroir-noir.jpg',
    'product_encens' => 'assets/images/products/encens.jpg'
];

// Récupérer toutes les images
$site_images = [];
foreach ($image_categories as $category => $default_image) {
    $site_images[$category] = get_image_by_category($pdo, $category, $default_image);
}

// Déterminer si ce fichier est inclus ou appelé directement
$is_included = (basename($_SERVER['SCRIPT_FILENAME']) !== basename(__FILE__));

if ($is_included) {
    // Retourner les images si ce fichier est inclus dans un autre script
    return $site_images;
} else {
    // Afficher les informations si appelé directement
    echo '<html><head><title>Images du site</title><style>body{font-family:Arial,sans-serif;margin:20px;line-height:1.6;color:#333}h1{color:#3a0ca3}table{width:100%;border-collapse:collapse;margin-bottom:20px}th,td{padding:8px;text-align:left;border-bottom:1px solid #ddd}th{background-color:#f2f2f2}img{max-width:200px;max-height:100px;border:1px solid #ddd}</style></head><body>';
    echo '<h1>Bibliothèque d\'images du site</h1>';
    echo '<p>Ce fichier est utilisé pour récupérer les images du site depuis la bibliothèque d\'images. Voici les images actuellement configurées :</p>';
    echo '<table>';
    echo '<tr><th>Catégorie</th><th>Chemin de l\'image</th><th>Aperçu</th></tr>';
    
    foreach ($site_images as $category => $path) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($category) . '</td>';
        echo '<td>' . htmlspecialchars($path) . '</td>';
        echo '<td><img src="' . htmlspecialchars($path) . '" alt="' . htmlspecialchars($category) . '"></td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '<p><a href="index.php">Retour à la page d\'accueil</a> | <a href="admin/image_library.php">Gérer les images</a></p>';
    echo '</body></html>';
}
?>
