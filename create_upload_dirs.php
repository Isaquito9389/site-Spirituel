<?php
/**
 * Script pour créer les répertoires d'upload nécessaires
 * Ce script vérifie et crée les dossiers requis pour les uploads d'images
 */

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Définir les répertoires à créer
$directories = [
    'uploads',
    'uploads/blog',
    'uploads/rituals',
    'uploads/products',
    'uploads/testimonials'
];

// Fonction pour créer un répertoire s'il n'existe pas
function create_directory($path) {
    if (!file_exists($path)) {
        if (mkdir($path, 0755, true)) {
            return [
                'status' => 'success',
                'message' => "Le répertoire '$path' a été créé avec succès."
            ];
        } else {
            return [
                'status' => 'error',
                'message' => "Impossible de créer le répertoire '$path'. Vérifiez les permissions."
            ];
        }
    } else {
        if (is_writable($path)) {
            return [
                'status' => 'info',
                'message' => "Le répertoire '$path' existe déjà et est accessible en écriture."
            ];
        } else {
            return [
                'status' => 'warning',
                'message' => "Le répertoire '$path' existe mais n'est pas accessible en écriture."
            ];
        }
    }
}

// Créer un fichier .htaccess pour protéger le dossier uploads
function create_htaccess($path) {
    $htaccess_content = <<<EOT
# Autoriser l'accès aux fichiers mais pas le listing des répertoires
Options -Indexes

# Autoriser les types de fichiers multimédias
<FilesMatch ".(jpg|jpeg|png|gif|webp|mp3|mp4|pdf)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>

# Bloquer l'exécution de scripts
<FilesMatch ".(php|phtml|php3|php4|php5|php7|phps|cgi|pl|py|jsp|asp|aspx|shtml|sh|bash)$">
    Order Deny,Allow
    Deny from all
</FilesMatch>
EOT;

    $htaccess_file = $path . '/.htaccess';
    
    if (file_put_contents($htaccess_file, $htaccess_content)) {
        return [
            'status' => 'success',
            'message' => "Fichier .htaccess créé dans '$path'."
        ];
    } else {
        return [
            'status' => 'error',
            'message' => "Impossible de créer le fichier .htaccess dans '$path'."
        ];
    }
}

// Créer un fichier index.html vide pour plus de sécurité
function create_index_html($path) {
    $index_content = <<<EOT
<!DOCTYPE html>
<html>
<head>
    <title>Accès interdit</title>
    <meta http-equiv="refresh" content="0;url=../index.php">
</head>
<body>
    <p>Redirection en cours...</p>
</body>
</html>
EOT;

    $index_file = $path . '/index.html';
    
    if (file_put_contents($index_file, $index_content)) {
        return [
            'status' => 'success',
            'message' => "Fichier index.html créé dans '$path'."
        ];
    } else {
        return [
            'status' => 'error',
            'message' => "Impossible de créer le fichier index.html dans '$path'."
        ];
    }
}

// Résultats
$results = [];

// Créer les répertoires
foreach ($directories as $dir) {
    $results[] = create_directory($dir);
}

// Créer les fichiers .htaccess et index.html dans le répertoire principal d'uploads
if (file_exists('uploads') && is_writable('uploads')) {
    $results[] = create_htaccess('uploads');
    $results[] = create_index_html('uploads');
    
    // Créer des fichiers index.html dans chaque sous-répertoire
    foreach ($directories as $dir) {
        if ($dir !== 'uploads' && file_exists($dir) && is_writable($dir)) {
            $results[] = create_index_html($dir);
        }
    }
}

// Afficher les résultats
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Création des répertoires d'upload - Mystica Occulta</title>
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
    <div class="max-w-4xl mx-auto">
        <header class="mb-8 text-center">
            <h1 class="font-cinzel text-4xl font-bold bg-gradient-to-r from-purple-400 to-pink-600 bg-clip-text text-transparent mb-4">
                Création des répertoires d'upload
            </h1>
            <p class="text-gray-400 max-w-3xl mx-auto">
                Ce script vérifie et crée les répertoires nécessaires pour l'upload de fichiers sur votre site.
            </p>
        </header>
        
        <div class="card rounded-xl p-6 border border-purple-900 mb-8">
            <h2 class="font-cinzel text-xl font-bold text-white mb-4">
                <i class="fas fa-folder-plus mr-2 text-purple-500"></i> Résultats
            </h2>
            
            <ul class="space-y-2">
                <?php foreach ($results as $result): ?>
                    <li class="flex items-start">
                        <?php if ($result['status'] === 'success'): ?>
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <?php elseif ($result['status'] === 'error'): ?>
                            <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                        <?php elseif ($result['status'] === 'warning'): ?>
                            <i class="fas fa-exclamation-triangle text-yellow-500 mt-1 mr-2"></i>
                        <?php else: ?>
                            <i class="fas fa-info-circle text-blue-500 mt-1 mr-2"></i>
                        <?php endif; ?>
                        <span><?php echo $result['message']; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div class="card rounded-xl p-6 border border-purple-900 mb-8">
            <h2 class="font-cinzel text-xl font-bold text-white mb-4">
                <i class="fas fa-info-circle mr-2 text-blue-500"></i> Informations importantes
            </h2>
            
            <ul class="space-y-4">
                <li class="flex items-start">
                    <i class="fas fa-shield-alt text-purple-500 mt-1 mr-2"></i>
                    <div>
                        <p class="font-bold">Sécurité des uploads</p>
                        <p class="text-gray-400">Un fichier .htaccess a été créé pour protéger vos répertoires d'upload contre l'exécution de scripts malveillants.</p>
                    </div>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-upload text-purple-500 mt-1 mr-2"></i>
                    <div>
                        <p class="font-bold">Utilisation des uploads</p>
                        <p class="text-gray-400">Vous pouvez maintenant télécharger des images via les formulaires d'administration. Les fichiers seront stockés dans les répertoires appropriés.</p>
                    </div>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-exclamation-circle text-yellow-500 mt-1 mr-2"></i>
                    <div>
                        <p class="font-bold">Limitations d'InfinityFree</p>
                        <p class="text-gray-400">InfinityFree limite la taille des fichiers uploadés. Si vous rencontrez des problèmes avec de gros fichiers, utilisez plutôt des URL d'images externes.</p>
                    </div>
                </li>
            </ul>
        </div>
        
        <div class="text-center">
            <a href="admin/index.php" class="inline-block px-6 py-3 bg-gradient-to-r from-purple-600 to-pink-600 rounded-full text-white font-medium hover:from-purple-700 hover:to-pink-700 transition duration-300 shadow-lg">
                <i class="fas fa-arrow-right mr-2"></i> Accéder à l'administration
            </a>
        </div>
    </div>
</body>
</html>
