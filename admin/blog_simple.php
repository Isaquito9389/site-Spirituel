<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Get admin username
$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header("Location: index.php");
    exit;
}

// Initialiser les variables
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$messageType = '';
$posts = [];

// Connexion à la base de données
require_once 'includes/db_connect.php';

// Récupérer la liste des articles
try {
    $stmt = $pdo->query("SELECT id, title, category, status, created_at, wp_post_id FROM blog_posts ORDER BY created_at DESC");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Erreur lors de la récupération des articles: " . $e->getMessage();
    $messageType = "error";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du Blog - Mystica Occulta</title>
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
        
        .sidebar {
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
        }
        
        .card {
            background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
            transition: all 0.3s ease;
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
                        <a href="dashboard.php" class="flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-tachometer-alt w-6"></i>
                            <span>Tableau de Bord</span>
                        </a>
                    </li>
                    <li>
                        <a href="rituals_simple.php" class="flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-magic w-6"></i>
                            <span>Rituels</span>
                        </a>
                    </li>
                    <li>
                        <a href="blog_simple.php" class="flex items-center px-6 py-3 text-gray-300 hover:text-white border-l-4 border-pink-500 bg-opacity-30 bg-purple-900">
                            <i class="fas fa-blog w-6"></i>
                            <span>Blog</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="flex justify-between items-center mb-8">
                <h1 class="font-cinzel text-3xl font-bold text-white">
                    Gestion du Blog (Version Simplifiée)
                </h1>
                
                <a href="dashboard.php" class="px-4 py-2 rounded-full border border-purple-600 text-white font-medium inline-flex items-center hover:bg-purple-900 transition duration-300">
                    <i class="fas fa-arrow-left mr-2"></i> Retour au tableau de bord
                </a>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-900 bg-opacity-50' : 'bg-red-900 bg-opacity-50'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="card rounded-xl p-6 border border-purple-900 mb-8">
                <h2 class="font-cinzel text-xl font-bold text-white mb-4">Note importante</h2>
                <p class="text-gray-400 mb-4">
                    Ceci est une version simplifiée de la page de gestion du blog. Elle a été créée pour contourner 
                    l'erreur 500 que vous rencontriez. Pour accéder à toutes les fonctionnalités, vous devrez résoudre 
                    les problèmes dans le fichier blog.php original.
                </p>
            </div>
            
            <!-- Blog Posts List -->
            <div class="card rounded-xl p-6 border border-purple-900">
                <h2 class="font-cinzel text-xl font-bold text-white mb-4">Liste des Articles</h2>
                
                <?php if (empty($posts)): ?>
                    <p class="text-gray-400 text-center py-8">Aucun article de blog trouvé. Commencez par en créer un nouveau.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-purple-900">
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Titre</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Catégorie</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Statut</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Date</th>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">WordPress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($posts as $post): ?>
                                    <tr class="border-b border-purple-900 hover:bg-purple-900 hover:bg-opacity-20">
                                        <td class="px-4 py-3 text-white"><?php echo htmlspecialchars($post['title']); ?></td>
                                        <td class="px-4 py-3 text-gray-400"><?php echo htmlspecialchars($post['category']); ?></td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 rounded-full text-xs <?php echo $post['status'] === 'published' ? 'bg-green-900 text-green-300' : 'bg-yellow-900 text-yellow-300'; ?>">
                                                <?php echo $post['status'] === 'published' ? 'Publié' : 'Brouillon'; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-400"><?php echo date('d/m/Y', strtotime($post['created_at'])); ?></td>
                                        <td class="px-4 py-3 text-sm">
                                            <span class="<?php echo !empty($post['wp_post_id']) ? 'text-green-500' : 'text-yellow-500'; ?>">
                                                <?php echo !empty($post['wp_post_id']) ? 'Oui' : 'Non'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
