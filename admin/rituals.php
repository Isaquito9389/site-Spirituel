<?php
// Affichage forcé des erreurs PHP pour le debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Custom error handler to prevent 500 errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (error_reporting() === 0) {
        return false;
    }
    
    $error_message = "Error [$errno] $errstr - $errfile:$errline";
    error_log($error_message);
    
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        echo "<div style=\"padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin-bottom: 20px;\">
            <h3>Une erreur est survenue</h3>
            <p>Nous avons rencontré un problème lors du traitement de votre demande. Veuillez réessayer plus tard ou contacter l'administrateur.</p>
            <p><a href=\"dashboard.php\" style=\"color: #721c24; text-decoration: underline;\">Retour au tableau de bord</a></p>
        </div>";
        
        // Log detailed error for admin
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            echo "<div style=\"padding: 20px; background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; border-radius: 5px; margin-top: 20px;\">
                <h4>Détails de l'erreur (visible uniquement pour les administrateurs)</h4>
                <p>" . htmlspecialchars($error_message) . "</p>
            </div>";
        }
        
        return true;
    }
    
    return false;
}, E_ALL);

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

// Include database connection and WordPress API functions
require_once 'includes/db_connect.php';
require_once 'includes/wp_api_connect.php';

// Fonction pour générer un slug à partir d'un texte
function slugify($text) {
    // Convertir en minuscules
    $text = strtolower($text);
    // Remplacer les caractères spéciaux par des tirets
    $text = preg_replace('/[^\p{L}\p{Nd}]+/u', '-', $text);
    // Supprimer les tirets en début/fin
    $text = trim($text, '-');
    return $text;
}
// Vérification explicite de la connexion PDO
if (!isset($pdo) || !$pdo) {
    echo "<div style='background: #ffdddd; color: #a00; padding: 15px; margin: 10px 0; border: 2px solid #a00;'>Erreur critique : la connexion à la base de données n'est pas initialisée après l'inclusion de includes/db_connect.php.<br>Vérifiez le fichier de connexion et les identifiants !</div>";
    // On arrête tout pour éviter d'autres erreurs
    exit;
}

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$messageType = '';
$ritual = null;

// Get message from URL if redirected
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $messageType = $_GET['type'];
}

// Handle form submissions with simplified approach
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_ritual'])) {
        // Get form data
        $ritual_id = isset($_POST['ritual_id']) ? intval($_POST['ritual_id']) : 0;
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $excerpt = trim($_POST['excerpt']);
        $category = trim($_POST['category']);
        $duration = trim($_POST['duration']);
        $price = trim($_POST['price']);
        $status = $_POST['status'];
        $featured_image = isset($_POST['current_image']) ? $_POST['current_image'] : '';
        $youtube_url = isset($_POST['youtube_url']) ? trim($_POST['youtube_url']) : '';
        
        // Générer le slug à partir du titre
        $slug = slugify($title);
        
        // Vérifier l'unicité du slug
        $check = $pdo->prepare("SELECT COUNT(*) FROM rituals WHERE slug = ?" . ($ritual_id > 0 ? " AND id != ?" : ""));
        $unique_slug = $slug;
        $i = 1;
        while (true) {
            if ($ritual_id > 0) {
                $check->execute([$unique_slug, $ritual_id]);
            } else {
                $check->execute([$unique_slug]);
            }
            if ($check->fetchColumn() == 0) break;
            $unique_slug = $slug . '-' . $i++;
        }
        
        // Validate form data
        if (empty($title) || empty($content)) {
            $message = "Le titre et le contenu sont obligatoires.";
            $messageType = "error";
        } else {
            // Priorité au champ current_image qui contient la sélection de la bibliothèque
            if (isset($_POST['current_image']) && !empty($_POST['current_image'])) {
                $featured_image = $_POST['current_image'];
            } 
            // Puis au champ image_url si current_image est vide
            elseif (isset($_POST['image_url']) && !empty($_POST['image_url'])) {
                $featured_image = $_POST['image_url'];
            }
            // Finalement, essayer l'upload de fichier (désactivé sur InfinityFree)
            elseif (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Message d'information pour l'utilisateur
                $message = "L'upload de fichiers n'est pas disponible sur cet hébergement. Merci d'utiliser une URL d'image externe.";
                $messageType = "error";
                $featured_image = $_POST['current_image'];
            }
            
            try {
                // Prepare SQL statement based on whether it's an insert or update
                if ($ritual_id > 0) {
                    // Update existing ritual
                    $sql = "UPDATE rituals SET 
                            title = :title, 
                            content = :content, 
                            excerpt = :excerpt, 
                            category = :category,
                            duration = :duration,
                            price = :price,
                            status = :status,
                            featured_image = :featured_image,
                            youtube_url = :youtube_url,
                            slug = :slug,
                            updated_at = NOW()
                            WHERE id = :ritual_id";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':ritual_id', $ritual_id, PDO::PARAM_INT);
                } else {
                    // Insert new ritual
                    $sql = "INSERT INTO rituals (title, content, excerpt, category, duration, price, status, featured_image, youtube_url, slug, created_at, updated_at, author) 
                            VALUES (:title, :content, :excerpt, :category, :duration, :price, :status, :featured_image, :youtube_url, :slug, NOW(), NOW(), :author)";
                    
                    $stmt = $pdo->prepare($sql);
                }
                
                // Bind parameters
                $stmt->bindParam(':title', $title, PDO::PARAM_STR);
                $stmt->bindParam(':content', $content, PDO::PARAM_STR);
                $stmt->bindParam(':excerpt', $excerpt, PDO::PARAM_STR);
                $stmt->bindParam(':category', $category, PDO::PARAM_STR);
                $stmt->bindParam(':duration', $duration, PDO::PARAM_STR);
                $stmt->bindParam(':price', $price, PDO::PARAM_STR);
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
                $stmt->bindParam(':featured_image', $featured_image, PDO::PARAM_STR);
                $stmt->bindParam(':youtube_url', $youtube_url, PDO::PARAM_STR);
                $stmt->bindParam(':slug', $unique_slug, PDO::PARAM_STR);
                if ($ritual_id > 0) {
                    $stmt->bindParam(':ritual_id', $ritual_id, PDO::PARAM_INT);
                } else {
                    $stmt->bindParam(':author', $admin_username, PDO::PARAM_STR);
                }
                
                $stmt->execute();
                
                // Get the ritual ID if it's a new ritual
                if ($ritual_id == 0) {
                    $ritual_id = $pdo->lastInsertId();
                }
                
                // Sync with WordPress
                $wp_featured_image_id = null;
                
                // Upload featured image to WordPress if exists and it's a local file
                if (!empty($featured_image) && file_exists('../' . $featured_image) && substr($featured_image, 0, 4) !== 'http') {
                    $file_path = '../' . $featured_image;
                    $file_name = basename($featured_image);
                    $media_response = upload_media_to_wordpress($file_path, $file_name);
                    
                    if (isset($media_response['success']) && $media_response['success']) {
                        $wp_featured_image_id = $media_response['data']['id'];
                    }
                }
                
                // Map category to WordPress category ID
                $wp_category_id = map_category_to_wordpress($category);
                
                // Prepare data for WordPress
                $wp_data = [
                    'title' => $title,
                    'content' => $content,
                    'excerpt' => $excerpt,
                    'status' => $status === 'published' ? 'publish' : 'draft',
                    'categories' => [$wp_category_id]
                ];
                
                // Add featured image if available
                if ($wp_featured_image_id) {
                    $wp_data['featured_media'] = $wp_featured_image_id;
                } elseif (!empty($featured_image) && substr($featured_image, 0, 4) === 'http') {
                    // Si c'est une URL externe, on peut l'inclure directement dans le contenu
                    // mais WordPress ne la considérera pas comme image à la une
                    $wp_data['content'] = '<img src="' . $featured_image . '" alt="' . $title . '" class="featured-image" />' . $wp_data['content'];
                }
                
                // Get existing WordPress post ID if updating
                $wp_post_id = null;
                if ($ritual_id > 0) {
                    $stmt = $pdo->prepare("SELECT wp_post_id FROM rituals WHERE id = :ritual_id");
                    $stmt->bindParam(':ritual_id', $ritual_id);
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result && !empty($result['wp_post_id'])) {
                        $wp_post_id = $result['wp_post_id'];
                    }
                }
                
                // Send to WordPress
                if ($status === 'published') {
                    // Seulement synchroniser avec WordPress si le statut est 'published'
                    $wp_response = null;
                    
                    if ($wp_post_id) {
                        // Update existing WordPress post
                        $wp_response = send_to_wordpress('posts/' . $wp_post_id, $wp_data, 'PUT');
                    } else {
                        // Create new WordPress post
                        $wp_response = send_to_wordpress('posts', $wp_data, 'POST');
                    }
                    
                    // Check WordPress response
                    if (isset($wp_response['success']) && $wp_response['success']) {
                        // Store WordPress post ID in database
                        $new_wp_post_id = $wp_response['data']['id'];
                        $stmt = $pdo->prepare("UPDATE rituals SET wp_post_id = :wp_post_id WHERE id = :ritual_id");
                        $stmt->bindParam(':wp_post_id', $new_wp_post_id);
                        $stmt->bindParam(':ritual_id', $ritual_id);
                        $stmt->execute();
                        
                        $message = "Rituel enregistré avec succès et synchronisé avec WordPress.";
                        $messageType = "success";
                    } else {
                        // WordPress sync failed but local save succeeded
                        $wp_error_details = isset($wp_response['error']) ? $wp_response['error'] : 'Erreur inconnue';
                        $message = "Rituel enregistré localement mais la synchronisation WordPress a échoué: " . $wp_error_details;
                        $messageType = "warning";
                    }
                } else {
                    // Si le statut n'est pas 'published', on ne synchronise pas avec WordPress
                    $message = "Rituel enregistré avec succès (non publié sur WordPress).";
                    $messageType = "success";
                }
                // Le code de synchronisation avec WordPress a été entièrement déplacé dans le bloc précédent
                // Cette section est donc supprimée pour éviter les doublons et les erreurs de syntaxe
                
                // Redirect to list view
                header("Location: rituals.php?message=" . urlencode($message) . "&type=" . $messageType);
                exit;
            } catch (PDOException $e) {
                $message = "Erreur de base de données: " . $e->getMessage();
                $messageType = "error";
            }
        }
    } elseif (isset($_POST['delete_ritual'])) {
        // Delete ritual with simplified approach
        $ritual_id = intval($_POST['ritual_id']);
        
        try {
            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM rituals WHERE id = :id");
            $stmt->bindParam(':id', $ritual_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $message = "Le rituel a été supprimé avec succès.";
            $messageType = "success";
            
            // Redirect to list view
            header("Location: rituals.php?message=" . urlencode($message) . "&type=" . $messageType);
            exit;
        } catch (PDOException $e) {
            $message = "Erreur de base de données: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Get message from URL if redirected
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $messageType = $_GET['type'];
}

// Get ritual data if editing
$ritual = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $ritual_id = intval($_GET['id']);
    if (!isset($pdo) || !$pdo) {
        $message = "Erreur critique : la connexion à la base de données (\$pdo) n'est pas initialisée.";
        $messageType = "error";
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            echo "<div style='color: red; padding: 10px;'>Erreur critique : la connexion à la base de données (\$pdo) n'est pas initialisée.</div>";
        }
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM rituals WHERE id = ?");
            $stmt->execute([$ritual_id]);
            $ritual = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ritual) {
                $message = "Rituel introuvable.";
                $messageType = "error";
            }
        } catch (PDOException $e) {
            $message = "Erreur de base de données: " . $e->getMessage();
            $messageType = "error";
            if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
                echo "<div style='color: red; padding: 10px;'>Erreur PDO lors de la récupération du rituel : ".htmlspecialchars($e->getMessage())."</div>";
            }
        } catch (Throwable $e) {
            $message = "Erreur inattendue: " . $e->getMessage();
            $messageType = "error";
            if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
                echo "<div style='color: red; padding: 10px;'>Erreur inattendue lors de la récupération du rituel : ".htmlspecialchars($e->getMessage())."</div>";
            }
        }
    }
}

// Get all rituals if listing
$rituals = [];
if ($action === 'list') {
    try {
        $stmt = $pdo->query("SELECT * FROM rituals ORDER BY created_at DESC");
        $rituals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $message = "Erreur de base de données: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get categories for dropdown
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM blog_categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silently fail, not critical
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Rituels - Mystica Occulta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
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
        
        /* Quill editor custom styles */
        .ql-toolbar.ql-snow {
            background-color: #1a1a2e;
            border-color: #3a0ca3;
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
        }
        
        .ql-container.ql-snow {
            border-color: #3a0ca3;
            background-color: #0f0e17;
            color: #e8e8e8;
            min-height: 200px;
            border-bottom-left-radius: 0.5rem;
            border-bottom-right-radius: 0.5rem;
        }
        
        .ql-editor {
            min-height: 200px;
        }
        
        .ql-snow .ql-stroke {
            stroke: #e8e8e8;
        }
        
        .ql-snow .ql-fill, .ql-snow .ql-stroke.ql-fill {
            fill: #e8e8e8;
        }
        
        .ql-snow .ql-picker {
            color: #e8e8e8;
        }
        
        .ql-snow .ql-picker-options {
            background-color: #1a1a2e;
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
                        <a href="rituals.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white active">
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
                        <a href="categories.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-folder w-6"></i>
                            <span>Catégories</span>
                        </a>
                    </li>
                    <li>
                        <a href="tags.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-tags w-6"></i>
                            <span>Tags</span>
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
                <h1 class="font-cinzel text-3xl font-bold text-white">
                    <?php if ($action === 'new'): ?>
                        Nouveau Rituel
                    <?php elseif ($action === 'edit'): ?>
                        Modifier le Rituel
                    <?php else: ?>
                        Gestion des Rituels
                    <?php endif; ?>
                </h1>
                
                <?php if ($action === 'list'): ?>
                    <a href="?action=new" class="btn-magic px-4 py-2 rounded-full text-white font-medium inline-flex items-center">
                        <i class="fas fa-plus mr-2"></i> Nouveau Rituel
                    </a>
                <?php else: ?>
                    <a href="rituals.php" class="px-4 py-2 rounded-full border border-purple-600 text-white font-medium inline-flex items-center hover:bg-purple-900 transition duration-300">
                        <i class="fas fa-arrow-left mr-2"></i> Retour à la liste
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-900 bg-opacity-50' : 'bg-red-900 bg-opacity-50'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($action === 'list'): ?>
                <!-- Rituals List -->
                <div class="card rounded-xl p-6 border border-purple-900">
                    <?php if (empty($rituals)): ?>
                        <p class="text-gray-400 text-center py-8">Aucun rituel trouvé. Commencez par en créer un nouveau.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-purple-900">
                                        <th class="px-4 py-3 text-left text-gray-300">Titre</th>
                                        <th class="px-4 py-3 text-left text-gray-300">Catégorie</th>
                                        <th class="px-4 py-3 text-left text-gray-300">Durée</th>
                                        <th class="px-4 py-3 text-left text-gray-300">Prix</th>
                                        <th class="px-4 py-3 text-left text-gray-300">Statut</th>
                                        <th class="px-4 py-3 text-left text-gray-300">Date</th>
                                        <th class="px-4 py-3 text-right text-gray-300">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rituals as $ritual): ?>
                                        <tr class="border-b border-purple-900 hover:bg-purple-900 hover:bg-opacity-20">
                                            <td class="px-4 py-3 text-white"><?php echo htmlspecialchars($ritual['title']); ?></td>
                                            <td class="px-4 py-3 text-gray-400"><?php echo htmlspecialchars($ritual['category']); ?></td>
                                            <td class="px-4 py-3 text-gray-400"><?php echo htmlspecialchars($ritual['duration']); ?></td>
                                            <td class="px-4 py-3 text-gray-400"><?php echo htmlspecialchars($ritual['price']); ?>€</td>
                                            <td class="px-4 py-3">
                                                <span class="px-2 py-1 rounded-full text-xs <?php echo $ritual['status'] === 'published' ? 'bg-green-900 text-green-300' : 'bg-yellow-900 text-yellow-300'; ?>">
                                                    <?php echo $ritual['status'] === 'published' ? 'Publié' : 'Brouillon'; ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-gray-400"><?php echo date('d/m/Y', strtotime($ritual['created_at'])); ?></td>
                                            <td class="px-4 py-3 text-right">
                                                <a href="?action=edit&id=<?php echo $ritual['id']; ?>" class="text-blue-400 hover:text-blue-300 mx-1">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $ritual['id']; ?>, '<?php echo addslashes($ritual['title']); ?>')" class="text-red-400 hover:text-red-300 mx-1">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                                <a href="../ritual.php?slug=<?php echo urlencode($ritual['slug']); ?>" target="_blank" class="text-green-400 hover:text-green-300 mx-1">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Delete Confirmation Modal -->
                <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
                    <div class="bg-gradient-to-br from-purple-900 to-dark rounded-xl shadow-2xl overflow-hidden border border-purple-800 p-8 max-w-md w-full">
                        <h2 class="font-cinzel text-2xl font-bold text-white mb-4">Confirmer la suppression</h2>
                        <p class="text-gray-300 mb-6">Êtes-vous sûr de vouloir supprimer le rituel "<span id="deleteRitualTitle"></span>" ? Cette action est irréversible.</p>
                        <div class="flex justify-end space-x-4">
                            <button onclick="closeDeleteModal()" class="px-4 py-2 rounded-full border border-purple-600 text-white font-medium hover:bg-purple-900 transition duration-300">
                                Annuler
                            </button>
                            <form id="deleteForm" method="POST" action="rituals.php">
                                <input type="hidden" name="ritual_id" id="deleteRitualId">
                                <button type="submit" name="delete_ritual" class="px-4 py-2 rounded-full bg-red-700 text-white font-medium hover:bg-red-800 transition duration-300">
                                    Supprimer
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Ritual Form -->
                <form method="POST" action="rituals.php" enctype="multipart/form-data" class="card rounded-xl p-6 border border-purple-900">
                    <?php if ($action === 'edit' && $ritual): ?>
                        <input type="hidden" name="ritual_id" value="<?php echo $ritual['id']; ?>">
                        <?php if (!empty($ritual['featured_image'])): ?>
                            <input type="hidden" name="current_image" value="<?php echo $ritual['featured_image']; ?>">
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="md:col-span-2">
                            <label for="title" class="block text-gray-300 mb-2">Titre du rituel *</label>
                            <input type="text" id="title" name="title" required class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="Entrez le titre du rituel" value="<?php echo $ritual ? htmlspecialchars($ritual['title']) : ''; ?>">
                        </div>
                        
                        <div>
                            <label for="category" class="block text-gray-300 mb-2">Catégorie</label>
                            <select id="category" name="category" class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300">
                                <option value="">Sélectionnez une catégorie</option>
                                <option value="Amour" <?php echo ($ritual && $ritual['category'] === 'Amour') ? 'selected' : ''; ?>>Amour</option>
                                <option value="Prospérité" <?php echo ($ritual && $ritual['category'] === 'Prospérité') ? 'selected' : ''; ?>>Prospérité</option>
                                <option value="Protection" <?php echo ($ritual && $ritual['category'] === 'Protection') ? 'selected' : ''; ?>>Protection</option>
                                <option value="Vodoun" <?php echo ($ritual && $ritual['category'] === 'Vodoun') ? 'selected' : ''; ?>>Vodoun</option>
                                <option value="Magie de Salomon" <?php echo ($ritual && $ritual['category'] === 'Magie de Salomon') ? 'selected' : ''; ?>>Magie de Salomon</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['name']); ?>" <?php echo ($ritual && $ritual['category'] === $category['name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label for="excerpt" class="block text-gray-300 mb-2">Extrait</label>
                        <textarea id="excerpt" name="excerpt" rows="2" class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="Bref résumé du rituel (affiché dans les listes)"><?php echo $ritual ? htmlspecialchars($ritual['excerpt']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-6">
                        <label for="editor" class="block text-gray-300 mb-2">Description du rituel *</label>
                        <div id="editor"><?php echo $ritual ? $ritual['content'] : ''; ?></div>
                        <input type="hidden" name="content" id="content">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label for="duration" class="block text-gray-300 mb-2">Durée du rituel</label>
                            <input type="text" id="duration" name="duration" class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="Ex: 72 heures, 7 jours, etc." value="<?php echo $ritual ? htmlspecialchars($ritual['duration']) : ''; ?>">
                        </div>
                        
                        <div>
                            <label for="price" class="block text-gray-300 mb-2">Prix (€)</label>
                            <input type="text" id="price" name="price" class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="Ex: 199" value="<?php echo $ritual ? htmlspecialchars($ritual['price']) : ''; ?>">
                        </div>
                        
                        <div>
                            <label for="status" class="block text-gray-300 mb-2">Statut</label>
                            <select id="status" name="status" class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300">
                                <option value="draft" <?php echo ($ritual && $ritual['status'] === 'draft') ? 'selected' : ''; ?>>Brouillon</option>
                                <option value="published" <?php echo ($ritual && $ritual['status'] === 'published') ? 'selected' : ''; ?>>Publié</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="featured_image" class="block text-white mb-2">Image à la une</label>
                            
                            <!-- Prévisualisation de l'image sélectionnée -->
                            <div id="image-preview" class="mb-4 <?php echo empty($ritual['featured_image']) ? 'hidden' : ''; ?>">
                                <div class="relative w-full h-48 bg-gray-800 rounded-lg overflow-hidden">
                                    <img id="preview-image" src="<?php echo !empty($ritual['featured_image']) ? (substr($ritual['featured_image'], 0, 4) === 'http' ? $ritual['featured_image'] : '../' . $ritual['featured_image']) : ''; ?>" alt="Aperçu" class="w-full h-full object-cover">
                                    <button type="button" onclick="clearImage()" class="absolute top-2 right-2 bg-red-600 hover:bg-red-700 text-white p-1 rounded-full">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Onglets pour choisir la source d'image -->
                            <div class="mb-4">
                                <div class="flex border-b border-gray-700">
                                    <button type="button" class="py-2 px-4 bg-purple-900 text-white" onclick="switchImageTab('library')" id="tab-library">Bibliothèque d'images</button>
                                    <button type="button" class="py-2 px-4 text-gray-400" onclick="switchImageTab('url')" id="tab-url">URL d'image</button>
                                </div>
                            </div>
                            
                            <!-- Tab content: Bibliothèque d'images -->
                            <div id="tab-content-library" class="tab-content">
                                <div class="grid grid-cols-3 gap-2 max-h-60 overflow-y-auto p-2 bg-gray-800 rounded-lg mb-4">
                                    <?php
                                    // Charger les images de la bibliothèque
                                    try {
                                        // Vérifier si la table existe
                                        $stmt = $pdo->prepare("SHOW TABLES LIKE 'image_library'");
                                        $stmt->execute();
                                        $table_exists = $stmt->rowCount() > 0;
                                        
                                        $library_images = [];
                                        
                                        if ($table_exists) {
                                            $stmt = $pdo->prepare("SELECT * FROM image_library ORDER BY uploaded_at DESC LIMIT 12");
                                            $stmt->execute();
                                            $library_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        }
                                        
                                        // Charger aussi les images du dossier uploads si elles ne sont pas dans la base
                                        $uploads_dir = '../uploads/images/';
                                        if (is_dir($uploads_dir)) {
                                            $files = scandir($uploads_dir);
                                            foreach ($files as $file) {
                                                if ($file !== '.' && $file !== '..' && is_file($uploads_dir . $file)) {
                                                    $fileinfo = pathinfo($uploads_dir . $file);
                                                    if (in_array(strtolower($fileinfo['extension']), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                                        $path = 'uploads/images/' . $file;
                                                        $exists = false;
                                                        foreach ($library_images as $image) {
                                                            if (basename($image['image_path']) === $file) {
                                                                $exists = true;
                                                                break;
                                                            }
                                                        }
                                                        if (!$exists) {
                                                            $library_images[] = [
                                                                'id' => 'file_' . $file,
                                                                'image_path' => $path,
                                                                'is_external' => 0
                                                            ];
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                        
                                        if (!empty($library_images)) {
                                            foreach ($library_images as $image) {
                                                $image_url = $image['is_external'] ? $image['image_path'] : '../' . $image['image_path'];
                                                $image_path = $image['image_path'];
                                                echo '<div class="relative aspect-square bg-gray-900 rounded overflow-hidden cursor-pointer hover:opacity-90" onclick="selectImage(\''.htmlspecialchars($image_path).'\')">';                                                
                                                echo '<img src="'.htmlspecialchars($image_url).'" alt="Image de la bibliothèque" class="w-full h-full object-cover">';                                                
                                                echo '</div>';
                                            }
                                        } else {
                                            echo '<div class="col-span-3 text-gray-400 text-center py-8">Aucune image disponible dans la bibliothèque. <a href="image_library.php" target="_blank" class="text-purple-400">Ajouter des images</a></div>';
                                        }
                                    } catch (PDOException $e) {
                                        echo '<div class="col-span-3 text-red-400 text-center py-8">Erreur lors du chargement des images: ' . htmlspecialchars($e->getMessage()) . '</div>';
                                    }
                                    ?>
                                </div>
                                <div class="text-right">
                                    <a href="image_library.php" target="_blank" class="text-purple-400 hover:text-purple-300 text-sm">
                                        <i class="fas fa-plus-circle mr-1"></i> Ajouter plus d'images à la bibliothèque
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Tab content: URL d'image -->
                            <div id="tab-content-url" class="tab-content hidden">
                                <input type="url" id="image_url" name="image_url" placeholder="https://exemple.com/image.jpg" class="w-full p-2 bg-gray-800 border border-gray-700 rounded-lg text-white mb-2" value="<?php echo isset($ritual['featured_image']) && substr($ritual['featured_image'], 0, 4) === 'http' ? htmlspecialchars($ritual['featured_image']) : ''; ?>">
                                <p class="text-gray-400 text-sm">
                                    Entrez l'URL complète d'une image hébergée sur Internet
                                </p>
                                <button type="button" onclick="previewExternalImage()" class="mt-2 bg-purple-700 hover:bg-purple-600 text-white px-4 py-2 rounded">
                                    <i class="fas fa-eye mr-1"></i> Prévisualiser
                                </button>
                            </div>
                            
                            <input type="hidden" id="selected_image" name="current_image" value="<?php echo htmlspecialchars($ritual['featured_image'] ?? ''); ?>">
                        </div>
                        
                        <div>
                            <label for="youtube_url" class="block text-white mb-2">Vidéo YouTube (optionnel)</label>
                            <input type="url" id="youtube_url" name="youtube_url" placeholder="https://www.youtube.com/watch?v=XXXXXXXXXXX" class="w-full p-2 bg-gray-800 border border-gray-700 rounded-lg text-white" value="<?php echo $ritual && isset($ritual['youtube_url']) ? htmlspecialchars($ritual['youtube_url']) : ''; ?>">
                            <p class="text-gray-400 text-sm mt-1">Collez l'URL complète de la vidéo YouTube</p>
                            
                            <?php if (isset($ritual['youtube_url']) && !empty($ritual['youtube_url'])): 
                                // Extraire l'ID de la vidéo YouTube
                                $youtube_id = '';
                                if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|\/watch\?(?:.*&)?v=|\/embed\/|\/v\/)|youtu\.be\/)([^\?&\"\'<>\#\/\s]+)/', $ritual['youtube_url'], $matches)) {
                                    $youtube_id = $matches[1];
                                }
                                if (!empty($youtube_id)): ?>
                                <div class="mt-3 bg-gray-800 rounded-lg overflow-hidden">
                                    <div class="aspect-w-16 aspect-h-9">
                                        <iframe src="https://www.youtube.com/embed/<?php echo htmlspecialchars($youtube_id); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen class="w-full h-full"></iframe>
                                    </div>
                                </div>
                            <?php endif; endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <a href="rituals.php" class="px-6 py-3 rounded-full border border-purple-600 text-white font-medium hover:bg-purple-900 transition duration-300">
                            Annuler
                        </a>
                        <button type="submit" name="save_ritual" class="btn-magic px-6 py-3 rounded-full text-white font-medium">
                            <?php echo $action === 'edit' ? 'Mettre à jour' : 'Publier'; ?> le rituel
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </main>
    </div>

    <!-- Scripts - Support complet pour images et vidéos -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script>
        // Configuration améliorée de Quill avec support des images et vidéos
        const quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'script': 'sub'}, { 'script': 'super' }],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'font': [] }],
                    [{ 'align': [] }],
                    ['link', 'image', 'video'],
                    ['clean']
                ]
            }
        });
        
        // Gestionnaire pour le téléchargement d'images
        const toolbar = quill.getModule('toolbar');
        toolbar.addHandler('image', function() {
            // Proposer différentes options pour l'ajout d'images
            const input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', 'image/*');
            input.click();
            
            // Quand une image est sélectionnée
            input.onchange = () => {
                const file = input.files[0];
                if (file) {
                    // Option 1: Utiliser FileReader pour prévisualiser l'image localement
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const range = quill.getSelection(true);
                        quill.insertEmbed(range.index, 'image', e.target.result);
                    };
                    reader.readAsDataURL(file);
                    
                    // Option 2: Upload de l'image (désactivé sur InfinityFree)
                    // Afficher un message d'information
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'alert alert-info mt-3';
                    messageDiv.innerHTML = 'Note: L\'upload d\'images n\'est pas disponible sur cet hébergement. L\'image est insérée localement.';
                    document.querySelector('.ql-editor').parentNode.insertAdjacentElement('afterend', messageDiv);
                    setTimeout(() => messageDiv.remove(), 5000); // Disparaît après 5 secondes
                }
            };
        });
        
        // Gestionnaire pour l'ajout de vidéos
        toolbar.addHandler('video', function() {
            const url = prompt('Entrez l\'URL de la vidéo YouTube ou Vimeo:');
            if (url) {
                // Convertir les URLs YouTube en format embed
                let embedUrl = url;
                const youtubeRegex = /(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/;
                const match = url.match(youtubeRegex);
                
                if (match && match[1]) {
                    // C'est une URL YouTube, la convertir en format embed
                    embedUrl = 'https://www.youtube.com/embed/' + match[1];
                }
                
                const range = quill.getSelection(true);
                quill.insertEmbed(range.index, 'video', embedUrl);
            }
        });
        
        // Recevoir l'image sélectionnée depuis la bibliothèque
        window.addEventListener('message', function(event) {
            // Vérifier l'origine pour la sécurité
            if (event.origin === window.location.origin) {
                if (event.data && event.data.imagePath) {
                    // Construire l'URL complète de l'image
                    const imagePath = event.data.imagePath;
                    let imageUrl;
                    if (imagePath.startsWith('http')) {
                        imageUrl = imagePath;
                    } else {
                        imageUrl = '../' + imagePath;
                    }
                    
                    // Insérer l'image à la position du curseur dans l'éditeur
                    const range = quill.getSelection();
                    quill.insertEmbed(range ? range.index : 0, 'image', imageUrl);
                }
            }
        });
        
        // Mise à jour du contenu avant soumission du formulaire
        document.querySelector('form').addEventListener('submit', function() {
            document.getElementById('content').value = quill.root.innerHTML;
        });
        
        // Gestion des onglets de sélection d'image
        function switchImageTab(tabName) {
            // Masquer tous les contenus d'onglets
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Afficher le contenu de l'onglet sélectionné
            document.getElementById('tab-content-' + tabName).classList.remove('hidden');
            
            // Mettre à jour les styles des onglets
            document.querySelectorAll('[id^="tab-"]').forEach(tab => {
                tab.classList.remove('bg-purple-900');
                tab.classList.add('text-gray-400');
                tab.classList.remove('text-white');
            });
            
            document.getElementById('tab-' + tabName).classList.add('bg-purple-900');
            document.getElementById('tab-' + tabName).classList.add('text-white');
            document.getElementById('tab-' + tabName).classList.remove('text-gray-400');
        }
        
        // Sélection d'une image depuis la bibliothèque
        function selectImage(imagePath) {
            // Mettre à jour le champ caché
            document.getElementById('selected_image').value = imagePath;
            
            // Mettre à jour la prévisualisation
            const previewImage = document.getElementById('preview-image');
            const imagePreview = document.getElementById('image-preview');
            
            // Déterminer si c'est une URL externe ou un chemin local
            const isExternal = imagePath.startsWith('http');
            previewImage.src = isExternal ? imagePath : '../' + imagePath;
            
            // Afficher la prévisualisation
            imagePreview.classList.remove('hidden');
        }
        
        // Prévisualisation d'une image externe depuis URL
        function previewExternalImage() {
            const imageUrl = document.getElementById('image_url').value;
            
            if (imageUrl) {
                // Mettre à jour le champ caché
                document.getElementById('selected_image').value = imageUrl;
                
                // Mettre à jour la prévisualisation
                const previewImage = document.getElementById('preview-image');
                const imagePreview = document.getElementById('image-preview');
                
                previewImage.src = imageUrl;
                imagePreview.classList.remove('hidden');
            } else {
                alert('Veuillez entrer une URL d\'image valide.');
            }
        }
        
        // Effacer l'image sélectionnée
        function clearImage() {
            document.getElementById('selected_image').value = '';
            document.getElementById('image_url').value = '';
            document.getElementById('image-preview').classList.add('hidden');
        }
        
        // Fonctions pour la fenêtre modale de suppression
        function confirmDelete(ritualId, ritualTitle) {
            document.getElementById('deleteRitualId').value = ritualId;
            document.getElementById('deleteRitualTitle').textContent = ritualTitle;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
        
        // Écouteur pour recevoir les messages de la fenêtre de la bibliothèque d'images
        window.addEventListener('message', function(event) {
            // Vérifier l'origine du message pour la sécurité
            if (event.origin === window.location.origin) {
                // Vérifier si le message contient un chemin d'image
                if (event.data && event.data.imagePath) {
                    // Utiliser le chemin d'image reçu
                    selectImage(event.data.imagePath);
                }
            }
        });
    </script>
</body>
</html>
