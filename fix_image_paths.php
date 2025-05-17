<?php
// Affichage des erreurs en mode développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusion de la connexion à la base de données
require_once 'admin/includes/db_connect.php';

// Fonction pour créer un dossier récursivement
function create_directory($path) {
    if (!file_exists($path)) {
        if (!mkdir($path, 0755, true)) {
            return false;
        }
    }
    return true;
}

// Vérifier et créer le dossier uploads/images s'il n'existe pas
$uploads_dir = 'uploads/images/';
if (!is_dir($uploads_dir)) {
    if (!create_directory($uploads_dir)) {
        die("Impossible de créer le dossier $uploads_dir. Vérifiez les permissions.");
    }
    echo "Dossier $uploads_dir créé avec succès.<br>";
} else {
    echo "Le dossier $uploads_dir existe déjà.<br>";
}

// Vérifier si l'image existe dans htdocs/uploads/images/ (correction du chemin htdoc -> htdocs)
$htdoc_path = 'htdocs/uploads/images/background-main.png';
$target_path = 'uploads/images/background-main.png';

if (file_exists($htdoc_path)) {
    // Copier l'image vers le bon dossier
    if (copy($htdoc_path, $target_path)) {
        echo "L'image a été copiée de $htdoc_path vers $target_path avec succès.<br>";
    } else {
        echo "Erreur lors de la copie de l'image de $htdoc_path vers $target_path.<br>";
    }
} else {
    echo "L'image $htdoc_path n'existe pas.<br>";
}

// Vérifier si l'image existe maintenant dans uploads/images/
if (file_exists($target_path)) {
    echo "L'image $target_path existe et est prête à être utilisée.<br>";
    
    // Vérifier les permissions du fichier
    if (!is_readable($target_path)) {
        chmod($target_path, 0644);
        echo "Les permissions de l'image ont été ajustées pour la lecture.<br>";
    }
    
    // Mettre à jour la base de données pour utiliser ce chemin
    try {
        $stmt = $pdo->prepare("UPDATE image_library SET image_path = ? WHERE category = 'background_main'");
        $stmt->execute([$target_path]);
        
        if ($stmt->rowCount() > 0) {
            echo "La base de données a été mise à jour pour utiliser le chemin $target_path.<br>";
        } else {
            echo "Aucune mise à jour nécessaire dans la base de données.<br>";
        }
    } catch (PDOException $e) {
        echo "Erreur lors de la mise à jour de la base de données: " . $e->getMessage() . "<br>";
    }
} else {
    echo "L'image $target_path n'existe toujours pas. Veuillez la télécharger manuellement.<br>";
}

// Vérifier toutes les images de la catégorie background_main dans la base de données
try {
    $stmt = $pdo->prepare("SELECT * FROM image_library WHERE category = 'background_main'");
    $stmt->execute();
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($images) > 0) {
        echo "<h3>Images de la catégorie 'background_main' dans la base de données:</h3>";
        echo "<ul>";
        foreach ($images as $image) {
            echo "<li>ID: " . $image['id'] . ", Chemin: " . $image['image_path'] . ", Externe: " . ($image['is_external'] ? 'Oui' : 'Non') . "</li>";
        }
        echo "</ul>";
    } else {
        echo "Aucune image trouvée dans la catégorie 'background_main'.<br>";
        
        // Ajouter l'image à la base de données si elle existe
        if (file_exists($target_path)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO image_library (image_path, is_external, uploaded_by, uploaded_at, category) VALUES (?, 0, 'System', NOW(), 'background_main')");
                $stmt->execute([$target_path]);
                echo "Une nouvelle entrée a été ajoutée à la base de données pour l'image $target_path.<br>";
            } catch (PDOException $e) {
                echo "Erreur lors de l'ajout de l'image à la base de données: " . $e->getMessage() . "<br>";
            }
        }
    }
} catch (PDOException $e) {
    echo "Erreur lors de la vérification des images dans la base de données: " . $e->getMessage() . "<br>";
}

echo "<br><a href='index.php'>Voir la page d'accueil</a> | <a href='debug_images.php'>Déboguer les images</a>";
?>
