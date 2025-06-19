<?php
// Affichage des erreurs en mode développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusion de la connexion à la base de données
require_once 'admin/includes/db_connect.php';

// Vérifier si la colonne updated_at existe déjà
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'updated_at'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Ajouter la colonne updated_at
        $pdo->exec("ALTER TABLE products ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo '<div style="padding: 20px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 5px; margin-bottom: 20px;">
            La colonne "updated_at" a été ajoutée avec succès à la table products.
        </div>';
    } else {
        echo '<div style="padding: 20px; background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; border-radius: 5px; margin-bottom: 20px;">
            La colonne "updated_at" existe déjà dans la table products.
        </div>';
    }
    
    // Afficher la structure actuelle de la table
    $stmt = $pdo->query("DESCRIBE products");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<h3>Structure actuelle de la table products:</h3>';
    echo '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse;">';
    echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
    
    foreach ($columns as $column) {
        echo '<tr>';
        foreach ($column as $key => $value) {
            echo '<td>' . htmlspecialchars($value ?? 'NULL') . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table>';
    
} catch (PDOException $e) {
    echo '<div style="padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin-bottom: 20px;">
        Erreur: ' . htmlspecialchars($e->getMessage()) . '
    </div>';
}
