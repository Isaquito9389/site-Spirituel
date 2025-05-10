<?php
session_start();

// Activer l'affichage des erreurs pour le diagnostic
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Vérifier si l'utilisateur est connecté
$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$admin_username = $is_logged_in ? $_SESSION['admin_username'] : 'Visiteur';

// Fonction pour vérifier si une table existe
function table_exists($pdo, $table) {
    try {
        $result = $pdo->query("SELECT 1 FROM $table LIMIT 1");
        return true;
    } catch (Exception $e) {
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

// Fonction pour vérifier les extensions PHP requises
function check_php_extensions() {
    $required_extensions = ['pdo', 'pdo_mysql', 'json', 'curl', 'mbstring'];
    $missing_extensions = [];
    
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $missing_extensions[] = $ext;
        }
    }
    
    return [
        'valid' => empty($missing_extensions),
        'missing' => $missing_extensions
    ];
}

// Fonction pour tester la connexion à l'API WordPress
function test_wp_api_connection() {
    if (!file_exists('admin/includes/wp_api_connect.php')) {
        return [
            'valid' => false,
            'message' => 'Fichier wp_api_connect.php introuvable'
        ];
    }
    
    // Inclure le fichier de connexion à l'API WordPress
    include_once 'admin/includes/wp_api_connect.php';
    
    // Vérifier si la fonction existe
    if (!function_exists('send_to_wordpress')) {
        return [
            'valid' => false,
            'message' => 'Fonction send_to_wordpress non définie'
        ];
    }
    
    // Tester une requête simple
    try {
        $response = send_to_wordpress('posts', [], 'GET', true);
        if ($response && isset($response['success'])) {
            return [
                'valid' => $response['success'],
                'message' => $response['success'] ? 'Connexion à l\'API WordPress réussie' : 'Échec de la connexion à l\'API WordPress: ' . ($response['error'] ?? 'Erreur inconnue')
            ];
        } else {
            return [
                'valid' => false,
                'message' => 'Réponse de l\'API WordPress invalide'
            ];
        }
    } catch (Exception $e) {
        return [
            'valid' => false,
            'message' => 'Erreur lors de la connexion à l\'API WordPress: ' . $e->getMessage()
        ];
    }
}

// Fonction pour vérifier les permissions des dossiers
function check_directory_permissions($directories) {
    $results = [];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            $results[$dir] = [
                'exists' => false,
                'writable' => false,
                'message' => 'Le répertoire n\'existe pas'
            ];
            continue;
        }
        
        $is_writable = is_writable($dir);
        $results[$dir] = [
            'exists' => true,
            'writable' => $is_writable,
            'message' => $is_writable ? 'Le répertoire est accessible en écriture' : 'Le répertoire n\'est pas accessible en écriture'
        ];
    }
    
    return $results;
}

// Fonction pour vérifier les limitations de l'hébergeur
function check_host_limitations() {
    $limitations = [];
    
    // Vérifier si exec est désactivé
    if (!function_exists('exec')) {
        $limitations[] = 'La fonction exec() est désactivée';
    }
    
    // Vérifier si shell_exec est désactivé
    if (!function_exists('shell_exec')) {
        $limitations[] = 'La fonction shell_exec() est désactivée';
    }
    
    // Vérifier si system est désactivé
    if (!function_exists('system')) {
        $limitations[] = 'La fonction system() est désactivée';
    }
    
    // Vérifier la limite de mémoire
    $memory_limit = ini_get('memory_limit');
    $limitations[] = "Limite de mémoire PHP: $memory_limit";
    
    // Vérifier le temps maximum d'exécution
    $max_execution_time = ini_get('max_execution_time');
    $limitations[] = "Temps maximum d'exécution: $max_execution_time secondes";
    
    // Vérifier la taille maximale de téléchargement
    $upload_max_filesize = ini_get('upload_max_filesize');
    $limitations[] = "Taille maximale de téléchargement: $upload_max_filesize";
    
    return $limitations;
}

// Connexion à la base de données
$db_connected = false;
$db_message = '';
$tables_status = [];

try {
    require_once 'admin/includes/db_connect.php';
    $db_connected = true;
    
    // Vérifier les tables importantes
    $important_tables = [
        'users' => 'Utilisateurs',
        'blog_posts' => 'Articles de blog',
        'rituals' => 'Rituels',
        'blog_categories' => 'Catégories'
    ];
    
    foreach ($important_tables as $table => $description) {
        $exists = table_exists($pdo, $table);
        $tables_status[$table] = [
            'exists' => $exists,
            'description' => $description
        ];
        
        if ($exists) {
            // Compter les entrées
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            $tables_status[$table]['count'] = $count;
            
            // Obtenir la structure de la table
            $stmt = $pdo->query("DESCRIBE $table");
            $tables_status[$table]['columns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    $db_connected = false;
    $db_message = "Erreur de connexion à la base de données: " . $e->getMessage();
}

// Vérifier les fichiers importants
$important_files = [
    'admin/index.php' => 'Page de connexion admin',
    'admin/dashboard.php' => 'Tableau de bord admin',
    'admin/blog.php' => 'Gestion du blog',
    'admin/rituals.php' => 'Gestion des rituels',
    'admin/includes/db_connect.php' => 'Connexion à la base de données',
    'admin/includes/wp_api_connect.php' => 'Connexion à l\'API WordPress',
    'admin/includes/auth_functions.php' => 'Fonctions d\'authentification'
];

$files_status = [];
foreach ($important_files as $file => $description) {
    $file_path = $file;
    $files_status[$file] = [
        'exists' => file_exists($file_path),
        'description' => $description
    ];
    
    if ($files_status[$file]['exists']) {
        $readable_check = check_file_readable($file_path);
        $files_status[$file]['readable'] = $readable_check['valid'];
        $files_status[$file]['size'] = filesize($file_path);
        $files_status[$file]['modified'] = date("Y-m-d H:i:s", filemtime($file_path));
    }
}

// Vérifier les extensions PHP
$extensions_check = check_php_extensions();

// Vérifier les permissions des dossiers
$directories_check = check_directory_permissions([
    'admin',
    'admin/includes',
    'uploads',
    'uploads/blog',
    'uploads/rituals'
]);

// Vérifier les limitations de l'hébergeur
$host_limitations = check_host_limitations();

// Tester la connexion à l'API WordPress
$wp_api_check = test_wp_api_connection();

// Informations sur la version de PHP
$php_info = [
    'version' => phpversion(),
    'sapi' => php_sapi_name(),
    'os' => PHP_OS
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic du Site - Mystica Occulta</title>
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
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="min-h-screen p-4 md:p-8">
    <div class="max-w-6xl mx-auto">
        <header class="mb-8 text-center">
            <h1 class="font-cinzel text-4xl font-bold bg-gradient-to-r from-purple-400 to-pink-600 bg-clip-text text-transparent mb-4">
                Diagnostic du Site Mystica Occulta
            </h1>
            <p class="text-gray-400 max-w-3xl mx-auto">
                Cet outil analyse votre installation et identifie les problèmes potentiels avec votre site sur InfinityFree.
            </p>
        </header>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="card rounded-xl p-6 border border-purple-900">
                <h2 class="font-cinzel text-xl font-bold text-white mb-4 flex items-center">
                    <i class="fas fa-info-circle text-blue-500 mr-2"></i> Informations Générales
                </h2>
                <ul class="space-y-2">
                    <li class="flex justify-between">
                        <span class="text-gray-400">Utilisateur:</span>
                        <span class="text-white"><?php echo htmlspecialchars($admin_username); ?></span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-400">Statut de connexion:</span>
                        <span class="<?php echo $is_logged_in ? 'text-green-500' : 'text-yellow-500'; ?>">
                            <?php echo $is_logged_in ? 'Connecté' : 'Non connecté'; ?>
                        </span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-400">Version PHP:</span>
                        <span class="text-white"><?php echo $php_info['version']; ?></span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-400">Interface PHP:</span>
                        <span class="text-white"><?php echo $php_info['sapi']; ?></span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-400">Système d'exploitation:</span>
                        <span class="text-white"><?php echo $php_info['os']; ?></span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-400">Date et heure du serveur:</span>
                        <span class="text-white"><?php echo date('Y-m-d H:i:s'); ?></span>
                    </li>
                </ul>
            </div>
            
            <div class="card rounded-xl p-6 border border-purple-900">
                <h2 class="font-cinzel text-xl font-bold text-white mb-4 flex items-center">
                    <i class="fas fa-database text-green-500 mr-2"></i> Base de Données
                </h2>
                
                <?php if ($db_connected): ?>
                    <div class="text-green-500 mb-4">
                        <i class="fas fa-check-circle mr-2"></i> Connexion à la base de données réussie
                    </div>
                    
                    <h3 class="font-bold text-white mt-4 mb-2">Tables importantes:</h3>
                    <div class="space-y-2">
                        <?php foreach ($tables_status as $table => $status): ?>
                            <div class="flex items-start">
                                <div class="mt-1 mr-2">
                                    <?php if ($status['exists']): ?>
                                        <i class="fas fa-check-circle text-green-500"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times-circle text-red-500"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="flex justify-between">
                                        <span class="font-medium"><?php echo $table; ?></span>
                                        <span class="text-gray-400"><?php echo $status['description']; ?></span>
                                    </div>
                                    <?php if ($status['exists']): ?>
                                        <div class="text-sm text-gray-400">
                                            Nombre d'entrées: <?php echo $status['count']; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-red-500 mb-4">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Erreur de connexion à la base de données
                    </div>
                    <div class="text-gray-400">
                        <?php echo $db_message; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="grid grid-cols-1 gap-6 mb-8">
            <div class="card rounded-xl p-6 border border-purple-900">
                <h2 class="font-cinzel text-xl font-bold text-white mb-4 flex items-center">
                    <i class="fas fa-file-code text-yellow-500 mr-2"></i> Fichiers Importants
                </h2>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-purple-900">
                                <th class="px-4 py-2 text-left">Fichier</th>
                                <th class="px-4 py-2 text-left">Description</th>
                                <th class="px-4 py-2 text-left">Statut</th>
                                <th class="px-4 py-2 text-left">Taille</th>
                                <th class="px-4 py-2 text-left">Dernière modification</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files_status as $file => $status): ?>
                                <tr class="border-b border-purple-900">
                                    <td class="px-4 py-2 font-mono text-sm"><?php echo $file; ?></td>
                                    <td class="px-4 py-2 text-gray-400"><?php echo $status['description']; ?></td>
                                    <td class="px-4 py-2">
                                        <?php if ($status['exists']): ?>
                                            <span class="text-green-500"><i class="fas fa-check-circle mr-1"></i> Existe</span>
                                            <?php if (isset($status['readable'])): ?>
                                                <?php if ($status['readable']): ?>
                                                    <span class="text-green-500 block text-xs"><i class="fas fa-file-alt mr-1"></i> Lisible</span>
                                                <?php else: ?>
                                                    <span class="text-red-500 block text-xs"><i class="fas fa-lock mr-1"></i> Non lisible</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-red-500"><i class="fas fa-times-circle mr-1"></i> Manquant</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-gray-400">
                                        <?php if ($status['exists']): ?>
                                            <?php echo round($status['size'] / 1024, 2); ?> KB
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-gray-400">
                                        <?php if ($status['exists']): ?>
                                            <?php echo $status['modified']; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="card rounded-xl p-6 border border-purple-900">
                <h2 class="font-cinzel text-xl font-bold text-white mb-4 flex items-center">
                    <i class="fas fa-plug text-purple-500 mr-2"></i> Extensions PHP
                </h2>
                
                <?php if ($extensions_check['valid']): ?>
                    <div class="text-green-500 mb-4">
                        <i class="fas fa-check-circle mr-2"></i> Toutes les extensions requises sont installées
                    </div>
                <?php else: ?>
                    <div class="text-red-500 mb-4">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Extensions manquantes
                    </div>
                    <ul class="list-disc list-inside text-gray-400">
                        <?php foreach ($extensions_check['missing'] as $ext): ?>
                            <li><?php echo $ext; ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <h3 class="font-bold text-white mt-4 mb-2">Extensions chargées:</h3>
                <div class="text-sm text-gray-400 max-h-40 overflow-y-auto">
                    <?php echo implode(', ', get_loaded_extensions()); ?>
                </div>
            </div>
            
            <div class="card rounded-xl p-6 border border-purple-900">
                <h2 class="font-cinzel text-xl font-bold text-white mb-4 flex items-center">
                    <i class="fas fa-server text-red-500 mr-2"></i> Limitations de l'Hébergeur
                </h2>
                
                <ul class="space-y-2 text-gray-400">
                    <?php foreach ($host_limitations as $limitation): ?>
                        <li>
                            <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                            <?php echo $limitation; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <div class="mt-4 p-3 bg-yellow-900 bg-opacity-50 rounded-lg text-yellow-300 text-sm">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Note:</strong> InfinityFree désactive certaines fonctions PHP comme exec(), shell_exec() et system() pour des raisons de sécurité. Cela peut affecter certaines fonctionnalités de votre site.
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="card rounded-xl p-6 border border-purple-900">
                <h2 class="font-cinzel text-xl font-bold text-white mb-4 flex items-center">
                    <i class="fas fa-folder text-orange-500 mr-2"></i> Permissions des Dossiers
                </h2>
                
                <div class="space-y-3">
                    <?php foreach ($directories_check as $dir => $status): ?>
                        <div>
                            <div class="flex items-center">
                                <?php if ($status['exists']): ?>
                                    <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-red-500 mr-2"></i>
                                <?php endif; ?>
                                <span class="font-mono text-sm"><?php echo $dir; ?></span>
                            </div>
                            
                            <?php if ($status['exists']): ?>
                                <div class="ml-6 text-sm">
                                    <?php if ($status['writable']): ?>
                                        <span class="text-green-500"><i class="fas fa-pencil-alt mr-1"></i> Accessible en écriture</span>
                                    <?php else: ?>
                                        <span class="text-red-500"><i class="fas fa-lock mr-1"></i> Non accessible en écriture</span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="ml-6 text-sm text-gray-400">
                                    <i class="fas fa-info-circle mr-1"></i> <?php echo $status['message']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="card rounded-xl p-6 border border-purple-900">
                <h2 class="font-cinzel text-xl font-bold text-white mb-4 flex items-center">
                    <i class="fab fa-wordpress text-blue-500 mr-2"></i> Connexion WordPress
                </h2>
                
                <div class="mb-4 <?php echo $wp_api_check['valid'] ? 'text-green-500' : 'text-red-500'; ?>">
                    <i class="fas <?php echo $wp_api_check['valid'] ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                    <?php echo $wp_api_check['message']; ?>
                </div>
                
                <div class="p-3 bg-blue-900 bg-opacity-50 rounded-lg text-blue-300 text-sm">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Conseil:</strong> Assurez-vous que les informations de connexion à l'API WordPress sont correctes dans le fichier <code>admin/includes/wp_api_connect.php</code>. Vérifiez également que les identifiants d'application sont valides.
                </div>
            </div>
        </div>
        
        <div class="card rounded-xl p-6 border border-purple-900 mb-8">
            <h2 class="font-cinzel text-xl font-bold text-white mb-4 flex items-center">
                <i class="fas fa-wrench text-pink-500 mr-2"></i> Solutions aux Problèmes Courants
            </h2>
            
            <div class="space-y-4">
                <div>
                    <h3 class="font-bold text-white mb-2">Erreur 500 Internal Server Error</h3>
                    <p class="text-gray-400 mb-2">
                        Cette erreur est souvent causée par des fonctions PHP désactivées ou des problèmes de configuration.
                    </p>
                    <ul class="list-disc list-inside text-gray-400 ml-4">
                        <li>Utilisez les versions simplifiées des pages (rituals_simple.php, blog_simple.php)</li>
                        <li>Évitez d'utiliser les fonctions exec(), shell_exec() ou system()</li>
                        <li>Utilisez des URL d'images au lieu de télécharger des fichiers</li>
                        <li>Vérifiez que les fichiers .htaccess n'ont pas de directives incompatibles</li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="font-bold text-white mb-2">Problèmes de Base de Données</h3>
                    <p class="text-gray-400 mb-2">
                        Si vous rencontrez des problèmes avec la base de données:
                    </p>
                    <ul class="list-disc list-inside text-gray-400 ml-4">
                        <li>Vérifiez les informations de connexion dans db_connect.php</li>
                        <li>Exécutez setup_database_infinity.php pour initialiser les tables</li>
                        <li>Assurez-vous que les tables ont la bonne structure</li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="font-bold text-white mb-2">Problèmes avec WordPress</h3>
                    <p class="text-gray-400 mb-2">
                        Pour résoudre les problèmes de synchronisation avec WordPress:
                    </p>
                    <ul class="list-disc list-inside text-gray-400 ml-4">
                        <li>Vérifiez que l'URL de base dans wp_api_connect.php est correcte</li>
                        <li>Assurez-vous que les identifiants d'application sont valides</li>
                        <li>Utilisez wp-proxy.php pour les requêtes API côté client</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <footer class="text-center text-gray-500 text-sm mt-8">
            <p>Diagnostic généré le <?php echo date('d/m/Y à H:i:s'); ?></p>
            <p class="mt-2">
                <a href="admin/index.php" class="text-purple-400 hover:text-purple-300 transition">
                    <i class="fas fa-arrow-left mr-1"></i> Retour à l'administration
                </a>
            </p>
        </footer>
    </div>
</body>
</html>
