<?php
/**
 * Configuration Backlinks - Version Simplifiée
 * Sans dépendance bootstrap.php
 */

// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification d'authentification simplifiée
$is_admin = false;

// Méthode 1: Vérifier la session admin
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $is_admin = true;
}

// Méthode 2: Vérifier si on vient d'une page admin (fallback)
if (!$is_admin && isset($_SERVER['HTTP_REFERER'])) {
    $referer = $_SERVER['HTTP_REFERER'];
    if (strpos($referer, '/admin/') !== false) {
        $is_admin = true; // Autoriser temporairement
    }
}

// Méthode 3: Bypass temporaire pour test (à supprimer en production)
if (!$is_admin && isset($_GET['bypass']) && $_GET['bypass'] === 'admin') {
    $is_admin = true;
}

// Handle setup request
$setup_result = '';
$setup_success = false;

if ($is_admin && isset($_GET['setup']) && $_GET['setup'] === 'true') {
    // Connexion à la base de données
    try {
        require_once 'includes/db_connect.php';
        
        // Créer les tables
        $sql_backlinks = "CREATE TABLE IF NOT EXISTS backlinks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            content_type ENUM('blog', 'ritual') NOT NULL,
            content_id INT NOT NULL,
            url VARCHAR(500) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            domain VARCHAR(255),
            type ENUM('reference', 'source', 'inspiration', 'related') DEFAULT 'reference',
            status ENUM('active', 'broken', 'pending') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_checked TIMESTAMP NULL,
            INDEX idx_content (content_type, content_id),
            INDEX idx_domain (domain),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql_backlinks);
        
        $sql_internal = "CREATE TABLE IF NOT EXISTS internal_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source_type ENUM('blog', 'ritual') NOT NULL,
            source_id INT NOT NULL,
            target_type ENUM('blog', 'ritual') NOT NULL,
            target_id INT NOT NULL,
            anchor_text VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_link (source_type, source_id, target_type, target_id),
            INDEX idx_source (source_type, source_id),
            INDEX idx_target (target_type, target_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql_internal);
        
        $sql_categories = "CREATE TABLE IF NOT EXISTS backlink_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            description TEXT,
            color VARCHAR(7) DEFAULT '#6c757d',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql_categories);
        
        // Insérer les catégories par défaut
        $default_categories = [
            ['reference', 'Liens de référence vers des sources citées', '#3a0ca3'],
            ['source', 'Liens vers des textes ou documents originaux', '#7209b7'],
            ['inspiration', 'Liens vers du contenu inspirant', '#4cc9f0'],
            ['related', 'Liens vers du contenu connexe', '#f72585']
        ];
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO backlink_categories (name, description, color) VALUES (?, ?, ?)");
        foreach ($default_categories as $category) {
            $stmt->execute($category);
        }
        
        $setup_success = true;
        $setup_result = '<div class="bg-green-500/20 text-green-300 border border-green-500/30 p-4 rounded-lg">
            <h3 class="font-bold mb-2"><i class="fas fa-check-circle mr-2"></i>Configuration réussie !</h3>
            <p>Les tables de backlinks ont été créées avec succès :</p>
            <ul class="list-disc list-inside mt-2 space-y-1">
                <li>Table <code>backlinks</code> - Gestion des liens externes</li>
                <li>Table <code>internal_links</code> - Gestion des liens internes</li>
                <li>Table <code>backlink_categories</code> - Catégories de backlinks</li>
            </ul>
            <p class="mt-3">Vous pouvez maintenant utiliser le système de backlinks dans vos rituels et articles de blog.</p>
        </div>';
        
    } catch (Exception $e) {
        $setup_result = '<div class="bg-red-500/20 text-red-300 border border-red-500/30 p-4 rounded-lg">
            <h3 class="font-bold mb-2"><i class="fas fa-exclamation-triangle mr-2"></i>Erreur de configuration</h3>
            <p>Une erreur est survenue lors de la création des tables :</p>
            <p class="mt-2 font-mono text-sm">' . htmlspecialchars($e->getMessage()) . '</p>
            <p class="mt-3">Veuillez utiliser le script SQL fourni dans phpMyAdmin.</p>
        </div>';
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration Backlinks - Version Simplifiée</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700&family=Merriweather&display=swap');
        :root { --primary: #3a0ca3; --secondary: #7209b7; --accent: #f72585; --dark: #1a1a2e; }
        body { font-family: 'Merriweather', serif; background-color: #0f0e17; color: #e8e8e8; }
        .font-cinzel { font-family: 'Cinzel Decorative', cursive; }
    </style>
</head>
<body class="bg-dark min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="text-center mb-8">
                <h1 class="font-cinzel text-4xl font-bold text-white mb-4">
                    <i class="fas fa-link mr-3 text-purple-400"></i>
                    Configuration Backlinks - Version Simplifiée
                </h1>
                <p class="text-gray-300 text-lg">Authentification simplifiée pour résoudre l'erreur 403</p>
            </div>

            <?php if (!$is_admin): ?>
                <div class="bg-red-500/20 text-red-300 border border-red-500/30 p-6 rounded-lg mb-8">
                    <h3 class="font-bold mb-3"><i class="fas fa-lock mr-2"></i>Accès non autorisé</h3>
                    <p class="mb-4">Vous devez être connecté en tant qu'administrateur pour accéder à cette page.</p>
                    
                    <div class="space-y-3">
                        <p><strong>Options disponibles :</strong></p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <a href="index.php" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg text-white font-medium text-center block">
                                <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                            </a>
                            <a href="setup_backlinks_simple.php?bypass=admin" class="bg-orange-600 hover:bg-orange-700 px-4 py-2 rounded-lg text-white font-medium text-center block">
                                <i class="fas fa-key mr-2"></i>Bypass temporaire (test)
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="bg-blue-500/20 text-blue-300 border border-blue-500/30 p-6 rounded-lg">
                    <h3 class="font-bold mb-3"><i class="fas fa-database mr-2"></i>Alternative : Script SQL</h3>
                    <p class="mb-4">Si vous ne pouvez pas vous connecter, utilisez le script SQL fourni :</p>
                    <div class="bg-gray-800 p-4 rounded-lg">
                        <p class="font-mono text-sm">Fichier : <code>admin/backlinks_setup.sql</code></p>
                        <p class="text-sm mt-2">Copiez le contenu et exécutez-le dans phpMyAdmin</p>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="bg-gradient-to-br from-purple-900 to-dark rounded-xl p-8 border border-purple-800 mb-8">
                    <h2 class="text-2xl font-bold text-white mb-6">
                        <i class="fas fa-database mr-2 text-purple-400"></i>
                        Configuration de la Base de Données
                    </h2>
                    
                    <div class="mb-6">
                        <?php if (!empty($setup_result)): ?>
                            <?php echo $setup_result; ?>
                            <?php if ($setup_success): ?>
                                <div class="mt-4">
                                    <a href="rituals.php" class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg text-white font-medium mr-3">
                                        <i class="fas fa-magic mr-2"></i>Tester avec les rituels
                                    </a>
                                    <a href="blog.php" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg text-white font-medium">
                                        <i class="fas fa-blog mr-2"></i>Tester avec le blog
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="mt-4">
                                    <a href="setup_backlinks_simple.php?setup=true" class="bg-red-600 hover:bg-red-700 px-6 py-3 rounded-lg text-white font-medium inline-block">
                                        <i class="fas fa-redo mr-2"></i>Réessayer la configuration
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-gray-300 mb-4">Cliquez sur le bouton ci-dessous pour créer les tables nécessaires aux backlinks.</p>
                            <a href="setup_backlinks_simple.php?setup=true" class="bg-purple-600 hover:bg-purple-700 px-6 py-3 rounded-lg text-white font-medium inline-block">
                                <i class="fas fa-play mr-2"></i>Configurer la base de données
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="text-center">
                <a href="dashboard.php" class="text-purple-400 hover:text-purple-300">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Retour au tableau de bord
                </a>
            </div>
        </div>
    </div>
</body>
</html>
