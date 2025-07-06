<?php
// Start session
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || 
    !isset($_SESSION['admin_user_role']) || $_SESSION['admin_user_role'] !== 'admin') {
    // Redirect to login page if not logged in as admin
    header("Location: index.php");
    exit;
}

// Include security functions
require_once 'includes/security_functions.php';

// Set secure headers
set_secure_headers();

// Define protected directories and file patterns
$protected_directories = [
    '../includes/',
    'includes/',
    '../logs/',
    'logs/'
];

$protected_file_patterns = [
    'db_connect.php',
    'auth_functions.php',
    'setup_database.php',
    'wp_api_connect.php',
    'security_functions.php',
    'wp_db_update.php',
    'wp_rituals_update.php',
    'config.php',
    'bootstrap.php'
];

// Function to scan directory for files
function scanDirectoryForFiles($dir, $patterns = []) {
    $result = [];
    
    if (!is_dir($dir)) {
        return $result;
    }
    
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            // Skip subdirectories
            continue;
        } else {
            // Check if file matches any of the patterns
            if (empty($patterns)) {
                $result[] = $path;
            } else {
                foreach ($patterns as $pattern) {
                    if (strpos($file, $pattern) !== false) {
                        $result[] = $path;
                        break;
                    }
                }
            }
        }
    }
    
    return $result;
}

// Get all protected files
$all_protected_files = [];
foreach ($protected_directories as $dir) {
    $all_protected_files = array_merge($all_protected_files, scanDirectoryForFiles($dir));
}

// Also look for protected files in the root directory
$root_protected_files = scanDirectoryForFiles('../', $protected_file_patterns);
$all_protected_files = array_merge($all_protected_files, $root_protected_files);

// Also look for protected files in the admin directory
$admin_protected_files = scanDirectoryForFiles('./', $protected_file_patterns);
$all_protected_files = array_merge($all_protected_files, $admin_protected_files);

// Sort files by name
sort($all_protected_files);

// Handle file view request
$file_content = '';
$current_file = '';
$view_mode = false;

if (isset($_GET['view']) && !empty($_GET['view'])) {
    $view_mode = true;
    $requested_file = $_GET['view'];
    
    // Security check - prevent directory traversal
    $requested_file = str_replace('..', '', $requested_file);
    
    // Check if file exists and is in our list of protected files
    if (file_exists($requested_file) && in_array($requested_file, $all_protected_files)) {
        $current_file = $requested_file;
        $file_content = file_get_contents($requested_file);
    } else {
        $error_message = "Fichier non trouvé ou accès non autorisé.";
    }
}

// Get admin username and sanitize for output
$admin_username = sanitize_output($_SESSION['admin_username'] ?? 'Admin');

// Generate CSRF token for logout
$csrf_token = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionnaire de Fichiers Protégés - Mystica Occulta</title>
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
                <span class="text-gray-300">
                    <i class="fas fa-user-circle mr-2"></i> <?php echo htmlspecialchars($admin_username); ?>
                </span>
                <a href="dashboard.php" class="text-gray-300 hover:text-pink-500 transition duration-300">
                    <i class="fas fa-tachometer-alt mr-2"></i> Tableau de Bord
                </a>
                <a href="?logout=true&csrf_token=<?php echo $csrf_token; ?>" class="text-gray-300 hover:text-pink-500 transition duration-300">
                    <i class="fas fa-sign-out-alt mr-2"></i> Déconnexion
                </a>
            </div>
        </div>
    </header>

    <div class="flex flex-1">
        <!-- Sidebar with file list -->
        <aside class="sidebar w-64 border-r border-purple-900 flex-shrink-0 overflow-y-auto">
            <div class="p-4">
                <h2 class="font-cinzel text-xl font-bold text-white mb-4">Fichiers Protégés</h2>
                
                <div class="space-y-1">
                    <?php foreach ($all_protected_files as $file): ?>
                        <a href="?view=<?php echo urlencode($file); ?>" class="nav-link flex items-center px-3 py-2 text-sm text-gray-300 hover:text-white rounded <?php echo ($current_file === $file) ? 'active bg-purple-900 bg-opacity-30' : ''; ?>">
                            <i class="fas fa-file-code w-5"></i>
                            <span class="ml-2 truncate"><?php echo htmlspecialchars($file); ?></span>
                        </a>
                    <?php endforeach; ?>
                    
                    <?php if (empty($all_protected_files)): ?>
                        <p class="text-gray-500 text-sm italic">Aucun fichier protégé trouvé.</p>
                    <?php endif; ?>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-6 overflow-y-auto">
            <?php if ($view_mode && !empty($current_file)): ?>
                <div class="mb-6 flex justify-between items-center">
                    <h1 class="font-cinzel text-2xl font-bold text-white"><?php echo htmlspecialchars($current_file); ?></h1>
                    <a href="protected_files_manager.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition">
                        <i class="fas fa-times mr-2"></i> Fermer
                    </a>
                </div>
                
                <div class="bg-gray-900 rounded-xl p-4 border border-purple-900">
                    <pre class="text-gray-300 overflow-x-auto whitespace-pre-wrap" style="max-height: 70vh;"><?php echo htmlspecialchars($file_content); ?></pre>
                </div>
            <?php else: ?>
                <div class="mb-6">
                    <h1 class="font-cinzel text-3xl font-bold text-white mb-2">Gestionnaire de Fichiers Protégés</h1>
                    <p class="text-gray-400">Cet outil vous permet de consulter les fichiers protégés du site.</p>
                </div>
                
                <div class="bg-gray-900 rounded-xl p-6 border border-purple-900">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 rounded-full bg-purple-900 bg-opacity-50 flex items-center justify-center mr-4">
                            <i class="fas fa-shield-alt text-pink-500 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="font-cinzel text-xl font-bold text-white">Accès Administrateur</h2>
                            <p class="text-gray-400 text-sm">Sélectionnez un fichier dans la liste à gauche pour afficher son contenu.</p>
                        </div>
                    </div>
                    
                    <div class="mt-4 text-gray-300">
                        <p>En tant qu'administrateur, vous avez accès à tous les fichiers protégés du site. Cet outil vous permet de consulter ces fichiers sans avoir à modifier les restrictions de sécurité.</p>
                        <p class="mt-2">Nombre total de fichiers protégés: <span class="font-bold text-white"><?php echo count($all_protected_files); ?></span></p>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
