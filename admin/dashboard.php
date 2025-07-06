<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
session_start();

// Include security functions
require_once 'includes/security_functions.php';

// Set secure headers
set_secure_headers();

// Check if session is valid
if (!is_session_valid()) {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header("Location: index.php");
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Get admin username and sanitize for output
$admin_username = sanitize_output($_SESSION['admin_username'] ?? 'Admin');

// Generate CSRF token for logout
$csrf_token = generate_csrf_token();

// Handle logout with CSRF protection
if (isset($_GET['logout']) && $_GET['logout'] === 'true' && isset($_GET['csrf_token'])) {
    // Verify CSRF token
    if (verify_csrf_token($_GET['csrf_token'])) {
        // Clear all session variables
        $_SESSION = array();
        
        // Destroy the session
        session_destroy();
        
        // Redirect to login page
        header("Location: index.php");
        exit;
    } else {
        // Invalid CSRF token, redirect to dashboard
        header("Location: dashboard.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Mystica Occulta</title>
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
                <span class="text-gray-300">
                    <i class="fas fa-user-circle mr-2"></i> <?php echo htmlspecialchars($admin_username); ?>
                </span>
                <a href="?logout=true&csrf_token=<?php echo $csrf_token; ?>" class="text-gray-300 hover:text-pink-500 transition duration-300">
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
                        <a href="dashboard.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white active">
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
                        <a href="consultations_new.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
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
                    <li>
                        <a href="image_library.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white" target="_blank">
                            <i class="fas fa-images w-6"></i>
                            <span>Bibliothèque Images</span>
                        </a>
                    </li>
                    <li>
                        <a href="protected_files_manager.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-shield-alt w-6"></i>
                            <span>Fichiers Protégés</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="mb-8">
                <h1 class="font-cinzel text-3xl font-bold text-white mb-2">Tableau de Bord</h1>
                <p class="text-gray-400">Bienvenue dans votre espace d'administration, <?php echo htmlspecialchars($admin_username); ?>.</p>
            </div>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="card rounded-xl p-6 border border-purple-900">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-purple-900 bg-opacity-50 flex items-center justify-center mr-4">
                            <i class="fas fa-magic text-pink-500 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-gray-400 text-sm">Rituels</h3>
                            <p class="text-white text-2xl font-bold">12</p>
                        </div>
                    </div>
                </div>
                
                <div class="card rounded-xl p-6 border border-purple-900">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-purple-900 bg-opacity-50 flex items-center justify-center mr-4">
                            <i class="fas fa-blog text-pink-500 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-gray-400 text-sm">Articles de Blog</h3>
                            <p class="text-white text-2xl font-bold">8</p>
                        </div>
                    </div>
                </div>
                
                <div class="card rounded-xl p-6 border border-purple-900">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-purple-900 bg-opacity-50 flex items-center justify-center mr-4">
                            <i class="fas fa-comments text-pink-500 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-gray-400 text-sm">Témoignages</h3>
                            <p class="text-white text-2xl font-bold">24</p>
                        </div>
                    </div>
                </div>
                
                <div class="card rounded-xl p-6 border border-purple-900">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-purple-900 bg-opacity-50 flex items-center justify-center mr-4">
                            <i class="fas fa-shopping-cart text-pink-500 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-gray-400 text-sm">Produits</h3>
                            <p class="text-white text-2xl font-bold">16</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="mb-8">
                <h2 class="font-cinzel text-2xl font-bold text-white mb-4">Actions Rapides</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <a href="rituals.php?action=new" class="card rounded-xl p-6 border border-purple-900 hover:border-pink-500">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 rounded-full bg-purple-900 bg-opacity-50 flex items-center justify-center mr-3">
                                <i class="fas fa-plus text-pink-500"></i>
                            </div>
                            <h3 class="font-cinzel text-xl font-bold text-white">Nouveau Rituel</h3>
                        </div>
                        <p class="text-gray-400 text-sm">
                            Ajouter un nouveau rituel à votre catalogue.
                        </p>
                    </a>
                    
                    <a href="blog.php?action=new" class="card rounded-xl p-6 border border-purple-900 hover:border-pink-500">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 rounded-full bg-purple-900 bg-opacity-50 flex items-center justify-center mr-3">
                                <i class="fas fa-plus text-pink-500"></i>
                            </div>
                            <h3 class="font-cinzel text-xl font-bold text-white">Nouvel Article</h3>
                        </div>
                        <p class="text-gray-400 text-sm">
                            Publier un nouvel article sur le blog.
                        </p>
                    </a>
                    
                    <a href="products.php?action=new" class="card rounded-xl p-6 border border-purple-900 hover:border-pink-500">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 rounded-full bg-purple-900 bg-opacity-50 flex items-center justify-center mr-3">
                                <i class="fas fa-plus text-pink-500"></i>
                            </div>
                            <h3 class="font-cinzel text-xl font-bold text-white">Nouveau Produit</h3>
                        </div>
                        <p class="text-gray-400 text-sm">
                            Ajouter un nouveau produit à la boutique.
                        </p>
                    </a>
                    
                    <a href="image_library.php" target="_blank" class="card rounded-xl p-6 border border-purple-900 hover:border-pink-500">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 rounded-full bg-purple-900 bg-opacity-50 flex items-center justify-center mr-3">
                                <i class="fas fa-images text-pink-500"></i>
                            </div>
                            <h3 class="font-cinzel text-xl font-bold text-white">Gérer Images</h3>
                        </div>
                        <p class="text-gray-400 text-sm">
                            Accéder à la bibliothèque d'images pour upload et gestion.
                        </p>
                    </a>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div>
                <h2 class="font-cinzel text-2xl font-bold text-white mb-4">Activité Récente</h2>
                <div class="card rounded-xl p-6 border border-purple-900">
                    <ul class="divide-y divide-purple-900">
                        <li class="py-3 flex items-start">
                            <div class="w-8 h-8 rounded-full bg-purple-900 bg-opacity-50 flex items-center justify-center mr-3 flex-shrink-0">
                                <i class="fas fa-edit text-pink-500 text-sm"></i>
                            </div>
                            <div>
                                <p class="text-white">Article de blog "Les Rituels Lunaires" modifié</p>
                                <p class="text-gray-500 text-sm">Il y a 2 heures</p>
                            </div>
                        </li>
                        <li class="py-3 flex items-start">
                            <div class="w-8 h-8 rounded-full bg-purple-900 bg-opacity-50 flex items-center justify-center mr-3 flex-shrink-0">
                                <i class="fas fa-plus text-pink-500 text-sm"></i>
                            </div>
                            <div>
                                <p class="text-white">Nouveau rituel "Pacte de Richesse Divine" ajouté</p>
                                <p class="text-gray-500 text-sm">Il y a 1 jour</p>
                            </div>
                        </li>
                        <li class="py-3 flex items-start">
                            <div class="w-8 h-8 rounded-full bg-purple-900 bg-opacity-50 flex items-center justify-center mr-3 flex-shrink-0">
                                <i class="fas fa-comment text-pink-500 text-sm"></i>
                            </div>
                            <div>
                                <p class="text-white">Nouveau témoignage de "Sophie K." approuvé</p>
                                <p class="text-gray-500 text-sm">Il y a 2 jours</p>
                            </div>
                        </li>
                        <li class="py-3 flex items-start">
                            <div class="w-8 h-8 rounded-full bg-purple-900 bg-opacity-50 flex items-center justify-center mr-3 flex-shrink-0">
                                <i class="fas fa-shopping-cart text-pink-500 text-sm"></i>
                            </div>
                            <div>
                                <p class="text-white">Nouveau produit "Miroir Noir Magique" ajouté</p>
                                <p class="text-gray-500 text-sm">Il y a 3 jours</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
