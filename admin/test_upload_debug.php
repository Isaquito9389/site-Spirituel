<?php
// Script de test pour diagnostiquer le problème d'upload
session_start();

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test de diagnostic d'upload</h1>";

// Test 1: Vérifier PHP et extensions
echo "<h2>1. Informations PHP</h2>";
echo "Version PHP: " . phpversion() . "<br>";
echo "Extensions PDO disponibles: " . implode(', ', PDO::getAvailableDrivers()) . "<br>";
echo "Extension PDO MySQL: " . (extension_loaded('pdo_mysql') ? 'OUI' : 'NON') . "<br>";
echo "Extension GD: " . (extension_loaded('gd') ? 'OUI' : 'NON') . "<br>";
echo "Upload max filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "Post max size: " . ini_get('post_max_size') . "<br>";
echo "Max execution time: " . ini_get('max_execution_time') . "<br>";

// Test 2: Vérifier les dossiers
echo "<h2>2. Vérification des dossiers</h2>";
$upload_dir = '../uploads/images/';
echo "Dossier uploads/images existe: " . (is_dir($upload_dir) ? 'OUI' : 'NON') . "<br>";
echo "Dossier uploads/images accessible en écriture: " . (is_writable($upload_dir) ? 'OUI' : 'NON') . "<br>";
echo "Chemin absolu: " . realpath($upload_dir) . "<br>";

// Test 3: Vérifier la base de données
echo "<h2>3. Test de connexion à la base de données</h2>";
try {
    require_once 'bootstrap.php';
    require_once 'includes/db_connect.php';
    
    if (isset($pdo) && $pdo !== null) {
        echo "Connexion à la base de données: <span style='color: green;'>RÉUSSIE</span><br>";
        
        // Tester une requête simple
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        echo "Test de requête: " . ($result ? '<span style="color: green;">RÉUSSIE</span>' : '<span style="color: red;">ÉCHOUÉE</span>') . "<br>";
        
        // Vérifier si la table image_library existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'image_library'");
        $table_exists = $stmt->rowCount() > 0;
        echo "Table image_library existe: " . ($table_exists ? '<span style="color: green;">OUI</span>' : '<span style="color: red;">NON</span>') . "<br>";
        
        if ($table_exists) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM image_library");
            $count = $stmt->fetch()['count'];
            echo "Nombre d'images en base: " . $count . "<br>";
        }
        
    } else {
        echo "Connexion à la base de données: <span style='color: red;'>ÉCHOUÉE</span><br>";
    }
} catch (Exception $e) {
    echo "Erreur de connexion: <span style='color: red;'>" . $e->getMessage() . "</span><br>";
}

// Test 4: Simuler un upload
echo "<h2>4. Test d'upload</h2>";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_image'])) {
    echo "Fichier reçu: " . $_FILES['test_image']['name'] . "<br>";
    echo "Taille: " . $_FILES['test_image']['size'] . " bytes<br>";
    echo "Type: " . $_FILES['test_image']['type'] . "<br>";
    echo "Erreur: " . $_FILES['test_image']['error'] . "<br>";
    echo "Fichier temporaire: " . $_FILES['test_image']['tmp_name'] . "<br>";
    echo "Fichier temporaire existe: " . (file_exists($_FILES['test_image']['tmp_name']) ? 'OUI' : 'NON') . "<br>";
    
    if ($_FILES['test_image']['error'] === UPLOAD_ERR_OK) {
        $target_file = $upload_dir . 'test_' . time() . '_' . $_FILES['test_image']['name'];
        echo "Fichier de destination: " . $target_file . "<br>";
        
        if (move_uploaded_file($_FILES['test_image']['tmp_name'], $target_file)) {
            echo "<span style='color: green;'>Upload réussi!</span><br>";
            echo "Fichier créé: " . (file_exists($target_file) ? 'OUI' : 'NON') . "<br>";
            echo "Taille du fichier créé: " . (file_exists($target_file) ? filesize($target_file) . ' bytes' : 'N/A') . "<br>";
        } else {
            echo "<span style='color: red;'>Échec de l'upload!</span><br>";
        }
    } else {
        echo "<span style='color: red;'>Erreur d'upload: " . $_FILES['test_image']['error'] . "</span><br>";
    }
}

// Test 5: Lister les images existantes
echo "<h2>5. Images existantes dans le dossier</h2>";
if (is_dir($upload_dir)) {
    $files = scandir($upload_dir);
    $image_files = array_filter($files, function($file) {
        return !in_array($file, ['.', '..']) && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file);
    });
    
    if (empty($image_files)) {
        echo "Aucune image trouvée dans le dossier.<br>";
    } else {
        echo "Images trouvées (" . count($image_files) . "):<br>";
        foreach ($image_files as $file) {
            echo "- " . $file . " (" . filesize($upload_dir . $file) . " bytes)<br>";
        }
    }
}

?>

<h2>Test d'upload</h2>
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="test_image" accept="image/*" required>
    <button type="submit">Tester l'upload</button>
</form>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h2 { color: #333; }
</style>
