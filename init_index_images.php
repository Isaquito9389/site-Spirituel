<?php
// Affichage des erreurs en mode développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusion de la connexion à la base de données
require_once 'admin/includes/db_connect.php';

// Vérifier si la table image_library existe
try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'image_library'");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // Créer la table si elle n'existe pas
        $pdo->exec("CREATE TABLE image_library (
            id INT AUTO_INCREMENT PRIMARY KEY,
            image_path VARCHAR(255) NOT NULL,
            is_external TINYINT(1) DEFAULT 0,
            uploaded_by VARCHAR(100) NOT NULL,
            uploaded_at DATETIME NOT NULL,
            category VARCHAR(100) DEFAULT NULL
        )");
        echo "Table image_library créée avec succès.<br>";
    }
} catch (PDOException $e) {
    die("Erreur lors de la vérification/création de la table: " . $e->getMessage());
}

// Définir les catégories d'images pour la page d'accueil
$image_categories = [
    'background_main' => 'Image de fond principale',
    'vodoun_bg' => 'Arrière-plan section Vodoun',
    'vodoun_ritual' => 'Image rituel Vodoun',
    'product_bougie' => 'Produit: Bougie rouge',
    'product_miroir' => 'Produit: Miroir noir',
    'product_encens' => 'Produit: Encens'
];

// Vérifier si les catégories existent déjà dans la table
$existing_categories = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM image_library WHERE category IS NOT NULL");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_categories[] = $row['category'];
    }
} catch (PDOException $e) {
    echo "Erreur lors de la récupération des catégories existantes: " . $e->getMessage() . "<br>";
}

// Ajouter les catégories manquantes avec des images par défaut
foreach ($image_categories as $category => $description) {
    if (!in_array($category, $existing_categories)) {
        // Définir le chemin d'image par défaut
        $default_path = 'assets/images/';
        switch ($category) {
            case 'background_main':
                $default_path .= 'background-main.jpg';
                break;
            case 'vodoun_bg':
                $default_path .= 'vodoun-bg.jpg';
                break;
            case 'vodoun_ritual':
                $default_path .= 'vodoun/ritual-main.jpg';
                break;
            case 'product_bougie':
                $default_path .= 'products/bougie-rouge.jpg';
                break;
            case 'product_miroir':
                $default_path .= 'products/miroir-noir.jpg';
                break;
            case 'product_encens':
                $default_path .= 'products/encens.jpg';
                break;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO image_library (image_path, is_external, uploaded_by, uploaded_at, category) VALUES (?, 0, 'System', NOW(), ?)");
            $stmt->execute([$default_path, $category]);
            echo "Catégorie '$category' ajoutée avec l'image par défaut.<br>";
        } catch (PDOException $e) {
            echo "Erreur lors de l'ajout de la catégorie '$category': " . $e->getMessage() . "<br>";
        }
    }
}

echo "<br>Initialisation des images pour la page d'accueil terminée.<br>";
echo "<a href='index.php'>Retour à la page d'accueil</a>";
?>
