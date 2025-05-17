<?php
// Script pour initialiser la table image_library dans la base de données

// Affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure la connexion à la base de données
require_once 'admin/includes/db_connect.php';

// Vérification de la connexion à la base de données
if (!isset($pdo) || !$pdo) {
    die("Erreur : Impossible de se connecter à la base de données.");
}

// Créer la table image_library si elle n'existe pas déjà
try {
    // Vérifier si la table existe
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'image_library'");
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        // La table n'existe pas, la créer
        $sql = "CREATE TABLE image_library (
            id INT AUTO_INCREMENT PRIMARY KEY,
            image_path VARCHAR(255) NOT NULL,
            is_external TINYINT(1) DEFAULT 0,
            uploaded_by VARCHAR(100) NOT NULL,
            uploaded_at DATETIME NOT NULL,
            category VARCHAR(100) DEFAULT NULL
        )";
        
        $pdo->exec($sql);
        echo "<p>La table 'image_library' a été créée avec succès.</p>";
        
        // Ajouter quelques images d'exemple
        $sample_images = [
            ['https://images.pexels.com/photos/414612/pexels-photo-414612.jpeg', 'Admin'],
            ['https://images.pexels.com/photos/67636/rose-blue-flower-rose-blooms-67636.jpeg', 'Admin'],
            ['https://images.pexels.com/photos/326055/pexels-photo-326055.jpeg', 'Admin']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO image_library (image_path, is_external, uploaded_by, uploaded_at) VALUES (?, 1, ?, NOW())");
        
        foreach ($sample_images as $image) {
            $stmt->execute($image);
        }
        
        echo "<p>Des images d'exemple ont été ajoutées à la bibliothèque.</p>";
    } else {
        echo "<p>La table 'image_library' existe déjà.</p>";
    }
    
    echo "<p><a href='admin/image_library.php'>Accéder à la bibliothèque d'images</a></p>";
} catch (PDOException $e) {
    echo "<p>Erreur lors de la création de la table : " . $e->getMessage() . "</p>";
}
?>
