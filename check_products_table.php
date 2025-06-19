<?php
// Affichage des erreurs en mode développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusion de la connexion à la base de données
require_once 'admin/includes/db_connect.php';

// Vérifier si la table products existe
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'products'");
    echo 'Products table exists: ' . ($stmt->rowCount() > 0 ? 'Yes' : 'No') . '<br>';
    
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM products");
        echo 'Number of products: ' . $stmt->fetchColumn() . '<br>';
        
        // Get all products
        $stmt = $pdo->query("SELECT * FROM products");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($products)) {
            echo '<h3>Products List:</h3>';
            echo '<ul>';
            foreach ($products as $product) {
                echo '<li>' . htmlspecialchars($product['title']) . ' - ' . 
                     htmlspecialchars($product['category']) . ' - ' . 
                     number_format($product['price'], 2) . '€</li>';
            }
            echo '</ul>';
        }
    }
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
