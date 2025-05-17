<?php
// Affichage forcé des erreurs PHP pour le debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Include database connection and WordPress API functions
require_once 'includes/db_connect.php';
require_once 'includes/wp_api_connect.php';

$message = '';
$messageType = '';

// Handle form submission to update WordPress API settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_wp_settings'])) {
    $new_wp_url = trim($_POST['wp_api_url']);
    $new_wp_user = trim($_POST['wp_user']);
    $new_wp_password = trim($_POST['wp_password']);
    
    // Validate URL
    if (!filter_var($new_wp_url, FILTER_VALIDATE_URL)) {
        $message = "L'URL de l'API WordPress n'est pas valide.";
        $messageType = "error";
    } else {
        // Update the settings in the database
        try {
            // Check if settings table exists
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'settings'");
            $stmt->execute();
            $table_exists = $stmt->rowCount() > 0;
            
            if (!$table_exists) {
                // Create settings table if it doesn't exist
                $sql = "CREATE TABLE settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_name VARCHAR(255) NOT NULL UNIQUE,
                    setting_value TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )";
                $pdo->exec($sql);
            }
            
            // Update or insert WordPress API settings
            $settings = [
                'wp_api_url' => $new_wp_url,
                'wp_user' => $new_wp_user,
                'wp_password' => $new_wp_password
            ];
            
            foreach ($settings as $name => $value) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_name = :name");
                $stmt->bindParam(':name', $name);
                $stmt->execute();
                $exists = $stmt->fetchColumn() > 0;
                
                if ($exists) {
                    $stmt = $pdo->prepare("UPDATE settings SET setting_value = :value WHERE setting_name = :name");
                } else {
                    $stmt = $pdo->prepare("INSERT INTO settings (setting_name, setting_value) VALUES (:name, :value)");
                }
                
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':value', $value);
                $stmt->execute();
            }
            
            // Update global variables for immediate use
            $GLOBALS['wp_api_base_url'] = $new_wp_url;
            $GLOBALS['wp_user'] = $new_wp_user;
            $GLOBALS['wp_app_password'] = $new_wp_password;
            
            $message = "Paramètres WordPress mis à jour avec succès.";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Erreur lors de la mise à jour des paramètres: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Run connection tests
$test_results = test_wordpress_connection();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Connexion WordPress - Mystica Occulta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&family=MedievalSharp&family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400;1,700;1,900&display=swap');
        
        :root {
            --primary: #3a0ca3;
            --secondary: #7209b7;
            --accent: #f72585;
            --dark: #1a1a2e;
            --light: #f8f9fa;
        }
        
        body {
            font-family: 'Merriweather', serif;
            background-color: #0f0e17;
            color: #e8e8e8;
        }
        
        .font-cinzel {
            font-family: 'Cinzel Decorative', cursive;
        }
        
        .bg-mystic {
            background: radial-gradient(circle at center, #3a0ca3 0%, #1a1a2e 70%);
        }
        
        .btn-magic {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            box-shadow: 0 4px 15px rgba(247, 37, 133, 0.4);
            transition: all 0.3s ease;
        }
        
        .btn-magic:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(247, 37, 133, 0.6);
        }
        
        .sidebar {
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }
        
        .nav-link {
            transition: all 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            background: linear-gradient(90deg, rgba(114, 9, 183, 0.3) 0%, rgba(58, 12, 163, 0) 100%);
            border-left: 4px solid var(--accent);
        }
        
        .card {
            background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(247, 37, 133, 0.2);
        }
    </style>
</head>
<body class="bg-dark min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-dark bg-opacity-90 backdrop-blur-sm border-b border-purple-900 py-3">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-900 to-pink-600 flex items-center justify-center">
                    <i class="fas fa-eye text-white text-xl"></i>
                </div>
                <span class="font-cinzel text-2xl font-bold bg-gradient-to-r from-purple-400 to-pink-600 bg-clip-text text-transparent">MYSTICA OCCULTA</span>
            </div>
            
            <div class="flex items-center space-x-4">
                <a href="dashboard.php" class="text-gray-300 hover:text-pink-500 transition duration-300">
                    <i class="fas fa-tachometer-alt mr-2"></i> Tableau de Bord
                </a>
                <a href="?logout=true" class="text-gray-300 hover:text-pink-500 transition duration-300">
                    <i class="fas fa-sign-out-alt mr-2"></i> Déconnexion
                </a>
            </div>
        </div>
    </header>

    <div class="flex flex-1">
        <!-- Sidebar -->
        <aside class="sidebar w-64 border-r border-purple-900 flex-shrink-0">
            <nav class="py-6">
                <ul>
                    <li>
                        <a href="dashboard.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-tachometer-alt w-6"></i>
                            <span>Tableau de Bord</span>
                        </a>
                    </li>
                    <li>
                        <a href="rituals.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-magic w-6"></i>
                            <span>Rituels</span>
                        </a>
                    </li>
                    <li>
                        <a href="blog.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-blog w-6"></i>
                            <span>Blog</span>
                        </a>
                    </li>
                    <li>
                        <a href="testimonials.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-comments w-6"></i>
                            <span>Témoignages</span>
                        </a>
                    </li>
                    <li>
                        <a href="products.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-shopping-cart w-6"></i>
                            <span>Produits</span>
                        </a>
                    </li>
                    <li>
                        <a href="consultations.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-calendar-alt w-6"></i>
                            <span>Consultations</span>
                        </a>
                    </li>
                    <li>
                        <a href="settings.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-cog w-6"></i>
                            <span>Paramètres</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="flex justify-between items-center mb-8">
                <h1 class="font-cinzel text-3xl font-bold text-white">Test de Connexion WordPress</h1>
                
                <a href="blog.php" class="px-4 py-2 rounded-full border border-purple-600 text-white font-medium inline-flex items-center hover:bg-purple-900 transition duration-300">
                    <i class="fas fa-arrow-left mr-2"></i> Retour au Blog
                </a>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-900 bg-opacity-50' : 'bg-red-900 bg-opacity-50'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Connection Status -->
            <div class="card rounded-xl p-6 border border-purple-900 mb-8">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <i class="fas fa-wifi mr-3 <?php echo $test_results['overall_success'] ? 'text-green-500' : 'text-red-500'; ?>"></i>
                    Statut de la connexion WordPress
                </h2>
                
                <div class="flex items-center mb-6">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center <?php echo $test_results['overall_success'] ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300'; ?>">
                        <i class="fas <?php echo $test_results['overall_success'] ? 'fa-check' : 'fa-times'; ?> text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-xl font-bold <?php echo $test_results['overall_success'] ? 'text-green-400' : 'text-red-400'; ?>">
                            <?php echo $test_results['overall_success'] ? 'Connexion réussie' : 'Problème de connexion'; ?>
                        </h3>
                        <p class="text-gray-400">
                            <?php echo $test_results['overall_success'] 
                                ? 'Votre site est correctement connecté à WordPress.' 
                                : 'Des problèmes ont été détectés avec votre connexion WordPress.'; ?>
                        </p>
                        <p class="text-sm text-gray-500 mt-1">
                            Testé le <?php echo $test_results['timestamp'] ?? date('Y-m-d H:i:s'); ?>
                        </p>
                    </div>
                </div>
                
                <!-- Test Details -->
                <div class="space-y-4">
                    <?php foreach ($test_results['tests'] as $test): ?>
                    <div class="p-4 rounded-lg <?php echo $test['success'] ? 'bg-green-900 bg-opacity-20' : 'bg-red-900 bg-opacity-20'; ?> border <?php echo $test['success'] ? 'border-green-800' : 'border-red-800'; ?>">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <i class="fas <?php echo $test['success'] ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500'; ?> mr-3"></i>
                                <h4 class="font-bold"><?php echo htmlspecialchars($test['name']); ?></h4>
                            </div>
                            <span class="px-2 py-1 rounded text-xs <?php echo $test['success'] ? 'bg-green-800 text-green-200' : 'bg-red-800 text-red-200'; ?>">
                                <?php echo $test['success'] ? 'Réussi' : 'Échoué'; ?>
                            </span>
                        </div>
                        
                        <div class="mt-2 text-sm">
                            <p class="text-gray-300">URL: <?php echo htmlspecialchars($test['url']); ?></p>
                            <?php if (!$test['success']): ?>
                                <p class="text-red-400 mt-1">
                                    Status: <?php echo $test['status'] ?? 'N/A'; ?>
                                    <?php if (!empty($test['error'])): ?>
                                        - Erreur: <?php echo htmlspecialchars($test['error']); ?>
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($test['response'])): ?>
                                    <div class="mt-2 p-2 bg-gray-800 rounded overflow-auto max-h-32">
                                        <pre class="text-xs text-gray-400"><?php echo htmlspecialchars($test['response']); ?></pre>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- WordPress Settings Form -->
            <div class="card rounded-xl p-6 border border-purple-900">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                    <i class="fas fa-cog mr-3 text-purple-500"></i>
                    Configuration de l'API WordPress
                </h2>
                
                <form method="POST" action="test_wp_connection.php" class="space-y-6">
                    <div>
                        <label for="wp_api_url" class="block text-gray-300 mb-2">URL de l'API WordPress</label>
                        <input type="url" id="wp_api_url" name="wp_api_url" class="w-full px-4 py-3 rounded-lg bg-gray-800 border border-purple-700 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="https://votre-site.com/wp-json/wp/v2/" value="<?php echo htmlspecialchars($GLOBALS['wp_api_base_url']); ?>">
                        <p class="text-sm text-gray-500 mt-1">Format: https://votre-site.com/wp-json/wp/v2/</p>
                    </div>
                    
                    <div>
                        <label for="wp_user" class="block text-gray-300 mb-2">Nom d'utilisateur WordPress</label>
                        <input type="text" id="wp_user" name="wp_user" class="w-full px-4 py-3 rounded-lg bg-gray-800 border border-purple-700 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="admin" value="<?php echo htmlspecialchars($GLOBALS['wp_user']); ?>">
                    </div>
                    
                    <div>
                        <label for="wp_password" class="block text-gray-300 mb-2">Mot de passe d'application WordPress</label>
                        <input type="text" id="wp_password" name="wp_password" class="w-full px-4 py-3 rounded-lg bg-gray-800 border border-purple-700 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" value="<?php echo htmlspecialchars($GLOBALS['wp_app_password']); ?>">
                        <p class="text-sm text-gray-500 mt-1">Créez un mot de passe d'application dans les paramètres de sécurité de votre compte WordPress.</p>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" name="update_wp_settings" class="btn-magic px-6 py-3 rounded-full text-white font-medium">
                            <i class="fas fa-save mr-2"></i> Mettre à jour les paramètres
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Troubleshooting Tips -->
            <div class="mt-8 p-6 rounded-xl border border-blue-800 bg-blue-900 bg-opacity-20">
                <h2 class="text-xl font-bold mb-4 text-blue-400">
                    <i class="fas fa-info-circle mr-2"></i> Conseils de dépannage
                </h2>
                
                <ul class="list-disc pl-6 space-y-2 text-gray-300">
                    <li>Vérifiez que votre site WordPress est accessible et en ligne.</li>
                    <li>Assurez-vous que l'API REST WordPress est activée dans les paramètres de WordPress.</li>
                    <li>Vérifiez que le mot de passe d'application a été correctement créé avec les permissions suffisantes.</li>
                    <li>Si vous utilisez un pare-feu ou un plugin de sécurité, assurez-vous qu'il n'empêche pas l'accès à l'API REST.</li>
                    <li>Vérifiez que l'URL de l'API est correcte et inclut le chemin complet vers l'API v2.</li>
                    <li>Si vous utilisez HTTPS, assurez-vous que votre certificat SSL est valide.</li>
                </ul>
            </div>
        </main>
    </div>
</body>
</html>
