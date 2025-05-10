<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Get admin username
$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Activer l'affichage des erreurs pour le diagnostic
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure la connexion à la base de données
require_once 'includes/db_connect.php';

// Fonction pour vérifier si une table existe
function table_exists($pdo, $table) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '{$table}'");
        return $result->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Fonction pour vérifier si un fichier est lisible
function check_file_readable($file) {
    if (is_readable($file)) {
        return [
            'valid' => true,
            'message' => 'Fichier lisible'
        ];
    } else {
        return [
            'valid' => false,
            'message' => 'Fichier non lisible'
        ];
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic des Rituels - Mystica Occulta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&family=Merriweather:wght@400;700&display=swap');
        
        body {
            font-family: 'Merriweather', serif;
            background-color: #0f0e17;
            color: #e8e8e8;
        }
        
        .font-cinzel {
            font-family: 'Cinzel Decorative', cursive;
        }
        
        .card {
            background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
        }
        
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            background-color: #1a1a2e;
            padding: 1rem;
            border-radius: 0.5rem;
            overflow-x: auto;
        }
    </style>
</head>
<body class="bg-dark min-h-screen p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="font-cinzel text-3xl font-bold text-white mb-6">Diagnostic des Rituels</h1>
        
        <div class="card rounded-xl p-6 border border-purple-900 mb-8">
            <h2 class="font-cinzel text-xl font-bold text-white mb-4">Vérification de la base de données</h2>
            
            <?php
            // Vérifier la connexion à la base de données
            if (isset($pdo)) {
                echo '<div class="text-green-500 mb-2"><i class="fas fa-check-circle mr-2"></i> Connexion à la base de données réussie</div>';
                
                // Vérifier si la table rituals existe
                if (table_exists($pdo, 'rituals')) {
                    echo '<div class="text-green-500 mb-2"><i class="fas fa-check-circle mr-2"></i> La table "rituals" existe</div>';
                    
                    // Vérifier la structure de la table
                    try {
                        $stmt = $pdo->query("DESCRIBE rituals");
                        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        echo '<div class="text-green-500 mb-2"><i class="fas fa-check-circle mr-2"></i> Structure de la table "rituals" vérifiée</div>';
                        echo '<div class="text-gray-400 mb-4">Colonnes: ' . implode(', ', $columns) . '</div>';
                        
                        // Compter le nombre de rituels
                        $stmt = $pdo->query("SELECT COUNT(*) FROM rituals");
                        $count = $stmt->fetchColumn();
                        
                        echo '<div class="text-gray-400">Nombre de rituels dans la base de données: ' . $count . '</div>';
                    } catch (PDOException $e) {
                        echo '<div class="text-red-500 mb-2"><i class="fas fa-times-circle mr-2"></i> Erreur lors de la vérification de la structure: ' . $e->getMessage() . '</div>';
                    }
                } else {
                    echo '<div class="text-red-500 mb-2"><i class="fas fa-times-circle mr-2"></i> La table "rituals" n\'existe pas</div>';
                }
            } else {
                echo '<div class="text-red-500 mb-2"><i class="fas fa-times-circle mr-2"></i> Erreur de connexion à la base de données</div>';
            }
            ?>
        </div>
        
        <div class="card rounded-xl p-6 border border-purple-900 mb-8">
            <h2 class="font-cinzel text-xl font-bold text-white mb-4">Vérification des fichiers</h2>
            
            <?php
            $files_to_check = [
                'rituals.php',
                'includes/db_connect.php',
                'includes/auth_functions.php',
                'includes/setup_database.php'
            ];
            
            foreach ($files_to_check as $file) {
                $file_path = __DIR__ . '/' . $file;
                
                if (file_exists($file_path)) {
                    echo '<div class="text-green-500 mb-2"><i class="fas fa-check-circle mr-2"></i> Le fichier "' . $file . '" existe</div>';
                    
                    // Vérifier si le fichier est lisible
                    $readable_check = check_file_readable($file_path);
                    if ($readable_check['valid']) {
                        echo '<div class="text-green-500 mb-2 ml-6"><i class="fas fa-file-alt mr-2"></i> ' . $readable_check['message'] . '</div>';
                    } else {
                        echo '<div class="text-red-500 mb-2 ml-6"><i class="fas fa-exclamation-triangle mr-2"></i> ' . $readable_check['message'] . '</div>';
                    }
                } else {
                    echo '<div class="text-red-500 mb-2"><i class="fas fa-times-circle mr-2"></i> Le fichier "' . $file . '" n\'existe pas</div>';
                }
            }
            ?>
        </div>
        
        <div class="card rounded-xl p-6 border border-purple-900 mb-8">
            <h2 class="font-cinzel text-xl font-bold text-white mb-4">Solution temporaire</h2>
            
            <p class="text-gray-400 mb-4">
                Pour résoudre temporairement l'erreur 500, nous allons créer une version simplifiée de la page des rituels.
                Cette version ne contiendra que les fonctionnalités de base, mais vous permettra d'accéder à la page sans erreur.
            </p>
            
            <div class="flex space-x-4">
                <a href="rituals_simple.php" class="bg-gradient-to-r from-purple-600 to-pink-600 text-white px-4 py-2 rounded-full hover:opacity-90 transition-opacity">
                    <i class="fas fa-magic mr-2"></i> Accéder à la version simplifiée
                </a>
                
                <a href="dashboard.php" class="border border-purple-600 text-white px-4 py-2 rounded-full hover:bg-purple-900 hover:bg-opacity-30 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i> Retour au tableau de bord
                </a>
            </div>
        </div>
        
        <div class="card rounded-xl p-6 border border-purple-900">
            <h2 class="font-cinzel text-xl font-bold text-white mb-4">Informations de débogage</h2>
            
            <div class="mb-4">
                <h3 class="text-white font-bold mb-2">Version PHP</h3>
                <pre><?php echo phpversion(); ?></pre>
            </div>
            
            <div class="mb-4">
                <h3 class="text-white font-bold mb-2">Extensions PHP chargées</h3>
                <pre><?php echo implode(', ', get_loaded_extensions()); ?></pre>
            </div>
            
            <div>
                <h3 class="text-white font-bold mb-2">Variables de session</h3>
                <pre><?php print_r($_SESSION); ?></pre>
            </div>
        </div>
    </div>
</body>
</html>
