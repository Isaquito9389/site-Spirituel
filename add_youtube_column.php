<?php
// Script pour ajouter la colonne youtube_url à la table rituals

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

// Vérifier si la colonne existe déjà
try {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM rituals LIKE 'youtube_url'");
    $stmt->execute();
    $column_exists = $stmt->rowCount() > 0;
    
    if ($column_exists) {
        echo "<p>La colonne 'youtube_url' existe déjà dans la table 'rituals'.</p>";
    } else {
        // Ajouter la colonne
        $stmt = $pdo->prepare("ALTER TABLE rituals ADD COLUMN youtube_url VARCHAR(255) DEFAULT NULL AFTER featured_image");
        $stmt->execute();
        echo "<p>La colonne 'youtube_url' a été ajoutée avec succès à la table 'rituals'.</p>";
    }
} catch (PDOException $e) {
    echo "<p>Erreur lors de la modification de la table : " . $e->getMessage() . "</p>";
}
?>
