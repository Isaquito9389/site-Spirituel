<?php
/**
 * WordPress Database Update for Rituals
 * 
 * This script adds the wp_post_id column to the rituals table
 * to store WordPress post IDs for synchronization.
 */

// Include database connection
require_once 'db_connect.php';

// Check if the column already exists
$columnExists = false;
try {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM rituals LIKE 'wp_post_id'");
    $stmt->execute();
    $columnExists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    die("Erreur lors de la vérification de la colonne: " . $e->getMessage());
}

// Add the column if it doesn't exist
if (!$columnExists) {
    try {
        $sql = "ALTER TABLE rituals ADD COLUMN wp_post_id INT NULL DEFAULT NULL";
        $pdo->exec($sql);
        echo "La colonne wp_post_id a été ajoutée avec succès à la table rituals.<br>";
    } catch (PDOException $e) {
        die("Erreur lors de l'ajout de la colonne: " . $e->getMessage());
    }
} else {
    echo "La colonne wp_post_id existe déjà dans la table rituals.<br>";
}

echo "Mise à jour de la base de données terminée.";
