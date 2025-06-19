<?php
// Affichage des erreurs en mode développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test des fonctions WordPress API</h1>";

// Vérifier si le fichier wp_api_connect.php existe
$file_path = 'admin/includes/wp_api_connect.php';
echo "<p>Vérification du fichier: $file_path</p>";
if (file_exists($file_path)) {
    echo "<p style='color:green'>✓ Le fichier existe</p>";
    
    // Afficher le contenu du fichier pour vérification
    echo "<h2>Début du contenu du fichier:</h2>";
    $content = file_get_contents($file_path);
    echo "<pre>" . htmlspecialchars(substr($content, 0, 500)) . "...</pre>";
    
    // Inclure le fichier
    echo "<h2>Tentative d'inclusion du fichier:</h2>";
    try {
        require_once $file_path;
        echo "<p style='color:green'>✓ Fichier inclus avec succès</p>";
        
        // Vérifier si la fonction existe
        if (function_exists('sync_product_to_wordpress')) {
            echo "<p style='color:green'>✓ La fonction sync_product_to_wordpress() existe</p>";
        } else {
            echo "<p style='color:red'>✗ La fonction sync_product_to_wordpress() n'existe pas après inclusion</p>";
        }
        
        // Vérifier les autres fonctions importantes
        $functions = [
            'get_wp_api_token',
            'send_to_wordpress',
            'get_from_wordpress',
            'delete_wordpress_product'
        ];
        
        echo "<h2>Vérification des autres fonctions:</h2>";
        foreach ($functions as $function) {
            if (function_exists($function)) {
                echo "<p style='color:green'>✓ La fonction $function() existe</p>";
            } else {
                echo "<p style='color:red'>✗ La fonction $function() n'existe pas</p>";
            }
        }
        
        // Vérifier les variables globales
        echo "<h2>Vérification des variables globales:</h2>";
        if (isset($wp_api_base_url)) {
            echo "<p style='color:green'>✓ La variable \$wp_api_base_url est définie: " . htmlspecialchars($wp_api_base_url) . "</p>";
        } else {
            echo "<p style='color:red'>✗ La variable \$wp_api_base_url n'est pas définie</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color:red'>✗ Erreur lors de l'inclusion du fichier: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red'>✗ Le fichier n'existe pas</p>";
}

// Vérifier le chemin d'inclusion
echo "<h2>Chemins d'inclusion PHP:</h2>";
echo "<pre>" . htmlspecialchars(get_include_path()) . "</pre>";

// Informations sur le serveur
echo "<h2>Informations sur le serveur:</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "</p>";

// Vérifier les permissions du fichier
if (file_exists($file_path)) {
    echo "<h2>Permissions du fichier:</h2>";
    echo "<p>Permissions: " . substr(sprintf('%o', fileperms($file_path)), -4) . "</p>";
    echo "<p>Propriétaire: " . fileowner($file_path) . "</p>";
    echo "<p>Groupe: " . filegroup($file_path) . "</p>";
}
?>
