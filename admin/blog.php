<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
// Affichage forcé des erreurs PHP pour le debug
// Custom error handler to prevent 500 errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (error_reporting() === 0) {
        return false;
    }
    
    $error_message = "Error [$errno] $errstr - $errfile:$errline";
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

// Include database connection and WordPress API functions
require_once 'includes/db_connect.php';
require_once 'includes/wp_api_connect.php';
require_once 'includes/backlink_functions.php';

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_post'])) {
        // Get form data
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $excerpt = trim($_POST['excerpt']);
        $category = trim($_POST['category']);
        $status = $_POST['status'];
        $featured_image = ''; // Will be updated if an image is uploaded
        
        // Validate form data
        if (empty($title) || empty($content)) {
            $message = "Le titre et le contenu sont obligatoires.";
            $messageType = "error";
        } else {
            // Gestion des images avec support pour l'upload et les URL
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                try {
                    $upload_dir = '../uploads/blog/';
                    
                    // Créer le répertoire s'il n'existe pas
                    if (!file_exists($upload_dir)) {
                        if (!mkdir($upload_dir, 0755, true)) {
                            throw new Exception("Impossible de créer le dossier d'upload");
                        }
                    }
                    
                    // Générer un nom de fichier unique
                    $file_extension = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
                    $filename = uniqid() . '.' . $file_extension;
                    $target_file = $upload_dir . $filename;
                    
                    // Déplacer le fichier uploadé
                    if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $target_file)) {
                        $featured_image = 'uploads/blog/' . $filename;
                    } else {
                        throw new Exception("Échec du déplacement du fichier uploadé");
                    }
                } catch (Exception $e) {
                    // En cas d'erreur avec l'upload, on utilise l'URL si disponible
                    if (isset($_POST['image_url']) && !empty($_POST['image_url'])) {
                        $featured_image = $_POST['image_url'];
                    } elseif (isset($_POST['current_image']) && !empty($_POST['current_image'])) {
                        $featured_image = $_POST['current_image'];
                    }
                }
            } elseif (isset($_POST['image_url']) && !empty($_POST['image_url'])) {
                $featured_image = $_POST['image_url'];
            } elseif (isset($_POST['current_image']) && !empty($_POST['current_image'])) {
                $featured_image = $_POST['current_image'];
            }
            
            // If no errors, save to database
            if (empty($message)) {
                try {
                    // Prepare SQL statement based on whether it's an insert or update
                    if ($post_id > 0) {
                        // Update existing post
                        $sql = "UPDATE blog_posts SET 
                                title = :title, 
                                content = :content, 
                                excerpt = :excerpt, 
                                category = :category, 
                                status = :status";
                        
                        // Only update featured_image if a new one was uploaded
                        if (!empty($featured_image)) {
                            $sql .= ", featured_image = :featured_image";
                        }
                        
                        $sql .= " WHERE id = :post_id";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
                    } else {
                        // Insert new post - Construire la requête SQL en fonction de la présence ou non d'une image
                        if (!empty($featured_image)) {
                            $sql = "INSERT INTO blog_posts (title, content, excerpt, category, status, featured_image, created_at) 
                                    VALUES (:title, :content, :excerpt, :category, :status, :featured_image, NOW())";
                        } else {
                            $sql = "INSERT INTO blog_posts (title, content, excerpt, category, status, created_at) 
                                    VALUES (:title, :content, :excerpt, :category, :status, NOW())";
                        }
                        $stmt = $pdo->prepare($sql);
                    }
                    
                    // Bind common parameters
                    $stmt->bindParam(':title', $title);
                    $stmt->bindParam(':content', $content);
                    $stmt->bindParam(':excerpt', $excerpt);
                    $stmt->bindParam(':category', $category);
                    $stmt->bindParam(':status', $status);
                    
                    // Bind featured_image if it exists
                    if (!empty($featured_image)) {
                        $stmt->bindParam(':featured_image', $featured_image);
                    }
                    
                    // Execute the statement
                    if ($stmt->execute()) {
                        // Get the post ID if it's a new post
                        if ($post_id == 0) {
                            $post_id = $pdo->lastInsertId();
                        }
                        
                        // Sync with WordPress
                        $wp_featured_image_id = null;
                        
                        // Upload featured image to WordPress if exists
                        if (!empty($featured_image) && file_exists('../' . $featured_image)) {
                            $file_path = '../' . $featured_image;
                            $file_name = basename($featured_image);
                            $media_response = upload_media_to_wordpress($file_path, $file_name);
                            
                            if ($media_response['success']) {
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
                        }
                        
                        // Get existing WordPress post ID if updating
                        $wp_post_id = null;
                        if ($post_id > 0) {
                            $stmt = $pdo->prepare("SELECT wp_post_id FROM blog_posts WHERE id = :post_id");
                            $stmt->bindParam(':post_id', $post_id);
                            $stmt->execute();
                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($result && !empty($result['wp_post_id'])) {
                                $wp_post_id = $result['wp_post_id'];
                            }
                        }
                        
                        // Send to WordPress
                        $wp_response = null;
                        if ($wp_post_id) {
                            // Update existing WordPress post
                            $wp_response = send_to_wordpress('posts/' . $wp_post_id, $wp_data, 'PUT');
                        } else {
                            // Create new WordPress post
                            $wp_response = send_to_wordpress('posts', $wp_data);
                            
                            // If successful, store the WordPress post ID
                            if ($wp_response['success'] && isset($wp_response['data']['id'])) {
                                $new_wp_post_id = $wp_response['data']['id'];
                                $update_stmt = $pdo->prepare("UPDATE blog_posts SET wp_post_id = :wp_post_id WHERE id = :post_id");
                                $update_stmt->bindParam(':wp_post_id', $new_wp_post_id);
                                $update_stmt->bindParam(':post_id', $post_id);
                                $update_stmt->execute();
                            }
                        }
                        
                        $message = $post_id > 0 ? "L'article a été mis à jour avec succès." : "L'article a été créé avec succès.";
                        
                        // Add WordPress sync status to message with detailed error information
                        if ($wp_response && $wp_response['success']) {
                            $message .= " Synchronisé avec WordPress.";
                            $messageType = "success";
                        } else if (!isset($wp_response['success']) || !$wp_response['success']) {
                            // Detailed error message for WordPress sync failure
                            $wp_error_details = $wp_response['error'] ?? $wp_response;
                            $message .= " Synchronisation WordPress échouée.";
                            $messageType = "error";
                            
                            // Log detailed error for debugging
                            // Add more specific error information
                            if (is_array($wp_error_details)) {
                                if (isset($wp_error_details['code'])) {
                                    $message .= " Code: " . $wp_error_details['code'];
                                }
                                if (isset($wp_error_details['message'])) {
                                    $message .= " Message: " . $wp_error_details['message'];
                                }
                            }
                            if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
                                $error_details = "";
                                if (isset($wp_response['method'])) {
                                    $error_details .= " Méthode: " . $wp_response['method'] . ",";
                                }
                                if (isset($wp_response['status'])) {
                                    $error_details .= " Code HTTP: " . $wp_response['status'];
                                }
                                if (isset($wp_response['curl_error'])) {
                                    $error_details .= " Erreur cURL: " . $wp_response['curl_error'];
                                }
                                if (isset($wp_response['error'])) {
                                    // Safely handle different error formats
                                    $error_str = '';
                                    if (is_string($wp_response['error'])) {
                                        $error_str = $wp_response['error'];
                                    } elseif (is_array($wp_response['error'])) {
                                        $error_str = json_encode($wp_response['error']);
                                    } else {
                                        $error_str = 'Unknown error format';
                                    }
                                    $error_details .= " Réponse: " . substr($error_str, 0, 100) . "...";
                                }
                                
                                if (!empty($error_details)) {
                                    $message .= " Détails: " . $error_details;
                                }
                                
                                // Add link to WordPress connection test page
                                $message .= " <a href='test_wp_api.php' class='text-blue-400 hover:text-blue-300'>Tester la connexion WordPress</a>";
                            }
                        } else {
                            // If no WordPress response at all (local save only)
                            $messageType = "success";
                        }
                        
                        // Redirect to list view
                        header("Location: blog.php?message=" . urlencode($message) . "&type=" . $messageType);
                        exit;
                    }
                } catch (PDOException $e) {
                    $message = "Erreur de base de données: " . $e->getMessage();
                    $messageType = "error";
                }
            }
        }
    } // Close the if (isset($_POST['save_post'])) block
    
    if (isset($_POST['delete_post'])) {
            // Delete post
            $post_id = intval($_POST['post_id']);
            
            try {
                // Get the post details before deleting
                $stmt = $pdo->prepare("SELECT featured_image, wp_post_id FROM blog_posts WHERE id = :post_id");
                $stmt->bindParam(':post_id', $post_id);
                $stmt->execute();
                $post = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Delete the post from database
                $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = :post_id");
                $stmt->bindParam(':post_id', $post_id);
                
                if ($stmt->execute()) {
                    // Delete the featured image file if it exists
                    if ($post && !empty($post['featured_image'])) {
                        $image_path = '../' . $post['featured_image'];
                        if (file_exists($image_path)) {
                            unlink($image_path);
                        }
                    }
                    
                    // Delete from WordPress if wp_post_id exists
                    if ($post && !empty($post['wp_post_id'])) {
                        $wp_response = send_to_wordpress('posts/' . $post['wp_post_id'], [], 'DELETE');
                        
                        if ($wp_response && $wp_response['success']) {
                            $message = "L'article a été supprimé avec succès et de WordPress.";
                            $messageType = "success";
                        } else {
                            $message = "L'article a été supprimé localement, mais la suppression de WordPress a échoué.";
                            $messageType = "error"; // Change message type to error when WordPress sync fails
                            
                            // Add detailed error information for debugging
                            if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
                                $error_details = "";
                                if (isset($wp_response['status'])) {
                                    $error_details .= " Code HTTP: " . $wp_response['status'];
                                }
                                if (isset($wp_response['curl_error'])) {
                                    $error_details .= " Erreur cURL: " . $wp_response['curl_error'];
                                }
                                if (isset($wp_response['error'])) {
                                    // Safely handle different error formats
                                    $error_str = '';
                                    if (is_string($wp_response['error'])) {
                                        $error_str = $wp_response['error'];
                                    } elseif (is_array($wp_response['error'])) {
                                        $error_str = json_encode($wp_response['error']);
                                    } else {
                                        $error_str = 'Unknown error format';
                                    }
                                    $error_details .= " Réponse: " . substr($error_str, 0, 100) . "...";
                                }
                                
                                if (!empty($error_details)) {
                                    $message .= " Détails: " . $error_details;
                                }
                                
                                // Add link to WordPress connection test page
                                $message .= " <a href='test_wp_api.php' class='text-blue-400 hover:text-blue-300'>Tester la connexion WordPress</a>";
                            }
                        }
                    } else {
                        $message = "L'article a été supprimé avec succès.";
                        $messageType = "success";
                    }
                }
                
                // Redirect to list view
                header("Location: blog.php?message=" . urlencode($message) . "&type=" . $messageType);
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

    // Get post data if editing
    $post = null;
    if ($action === 'edit' && isset($_GET['id'])) {
        $post_id = intval($_GET['id']);
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = :post_id");
            $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
            $stmt->execute();
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$post) {
                $message = "Article introuvable.";
                $messageType = "error";
                $action = 'list';
            }
        } catch (PDOException $e) {
            $message = "Erreur de base de données: " . $e->getMessage();
            $messageType = "error";
            $action = 'list';
        }
    }

    // Get all posts for listing
    if ($action === 'list') {
        try {
            // TRI SYNCHRONISÉ AVEC LE FRONTEND : Exactement la même logique que blog.php
            // Tri rigoureux : les articles récemment mis à jour ou marqués comme récents apparaissent en premier
            $stmt = $pdo->query("SELECT * FROM blog_posts 
                                ORDER BY 
                                CASE 
                                    WHEN updated_at > created_at THEN updated_at 
                                    ELSE created_at 
                                END DESC, 
                                updated_at DESC, 
                                created_at DESC, 
                                id DESC");
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $message = "Erreur de base de données: " . $e->getMessage();
            $messageType = "error";
        }
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
                        <a href="rituals.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-magic w-6"></i>
                            <span>Rituels</span>
                        </a>
                    </li>
                    <li>
                        <a href="blog.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white active">
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
                <h1 class="font-cinzel text-3xl font-bold text-white">
                    <?php if ($action === 'new'): ?>
                        Nouvel Article de Blog
                    <?php elseif ($action === 'edit'): ?>
                        Modifier l'Article
                    <?php else: ?>
                        Gestion du Blog
                    <?php endif; ?>
                </h1>
                
                <?php if ($action === 'list'): ?>
                    <a href="?action=new" class="btn-magic px-4 py-2 rounded-full text-white font-medium inline-flex items-center">
                        <i class="fas fa-plus mr-2"></i> Nouvel Article
                    </a>
                <?php else: ?>
                    <a href="blog.php" class="px-4 py-2 rounded-full border border-purple-600 text-white font-medium inline-flex items-center hover:bg-purple-900 transition duration-300">
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
                <!-- Blog Posts List -->
                <div class="card rounded-xl p-6 border border-purple-900">
                    <?php if (empty($posts)): ?>
                        <p class="text-gray-400 text-center py-8">Aucun article de blog trouvé. Commencez par en créer un nouveau.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-purple-900">
                                        <th class="px-4 py-3 text-left text-gray-300">Titre</th>
                                        <th class="px-4 py-3 text-left text-gray-300">Catégorie</th>
                                        <th class="px-4 py-3 text-left text-gray-300">Statut</th>
                                        <th class="px-4 py-3 text-left text-gray-300">Date</th>
                                        <th class="px-4 py-3 text-left text-gray-300">WordPress</th>
                                        <th class="px-4 py-3 text-right text-gray-300">Actions</th>
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
                                            <td class="px-4 py-3 text-right">
                                                <a href="?action=edit&id=<?php echo $post['id']; ?>" class="text-blue-400 hover:text-blue-300 mx-1">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $post['id']; ?>, '<?php echo addslashes($post['title']); ?>')" class="text-red-400 hover:text-red-300 mx-1">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                                <a href="../blog-post.php?id=<?php echo $post['id']; ?>" target="_blank" class="text-green-400 hover:text-green-300 mx-1">
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
                        <p class="text-gray-300 mb-6">Êtes-vous sûr de vouloir supprimer l'article "<span id="deletePostTitle"></span>" ? Cette action est irréversible.</p>
                        <div class="flex justify-end space-x-4">
                            <button onclick="closeDeleteModal()" class="px-4 py-2 rounded-full border border-purple-600 text-white font-medium hover:bg-purple-900 transition duration-300">
                                Annuler
                            </button>
                            <form id="deleteForm" method="POST" action="blog.php">
                                <input type="hidden" name="post_id" id="deletePostId">
                                <button type="submit" name="delete_post" class="px-4 py-2 rounded-full bg-red-700 text-white font-medium hover:bg-red-800 transition duration-300">
                                    Supprimer
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Blog Post Form -->
                <form method="POST" action="blog.php" enctype="multipart/form-data" class="card rounded-xl p-6 border border-purple-900">
                    <?php if ($action === 'edit' && $post): ?>
                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                        <?php if (!empty($post['featured_image'])): ?>
                            <input type="hidden" name="current_image" value="<?php echo $post['featured_image']; ?>">
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="md:col-span-2">
                            <label for="title" class="block text-gray-200 font-medium mb-2">Titre de l'article *</label>
                            <input type="text" id="title" name="title" required class="w-full px-4 py-3 rounded-lg bg-gray-800 border border-purple-700 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="Entrez le titre de l'article" value="<?php echo $post ? htmlspecialchars($post['title']) : ''; ?>">
                        </div>
                        
                        <div>
                            <label for="category" class="block text-gray-200 font-medium mb-2">Catégorie</label>
                            <input type="text" id="category" name="category" class="w-full px-4 py-3 rounded-lg bg-gray-800 border border-purple-700 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="Ex: Rituels, Spiritualité, etc." value="<?php echo $post ? htmlspecialchars($post['category']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label for="excerpt" class="block text-gray-200 font-medium mb-2">Extrait</label>
                        <textarea id="excerpt" name="excerpt" rows="2" class="w-full px-4 py-3 rounded-lg bg-gray-800 border border-purple-700 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="Bref résumé de l'article (affiché dans les listes)"><?php echo $post ? htmlspecialchars($post['excerpt']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-6">
                        <label for="editor" class="block text-gray-300 mb-2">Contenu de l'article *</label>
                        <div id="editor"><?php echo $post ? $post['content'] : ''; ?></div>
                        <input type="hidden" name="content" id="content">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="featured_image" class="block text-gray-200 font-medium mb-2">Image à la une</label>
                                
                                <!-- Prévisualisation de l'image sélectionnée -->
                                <div id="image-preview" class="mb-4 <?php echo empty($post['featured_image']) ? 'hidden' : ''; ?>">
                                    <div class="relative w-full h-40 bg-gray-800 rounded-lg overflow-hidden">
                                        <img id="preview-image" src="<?php echo !empty($post['featured_image']) ? ((strpos($post['featured_image'], 'http') === 0) ? $post['featured_image'] : '../' . $post['featured_image']) : ''; ?>" alt="Prévisualisation" class="w-full h-full object-cover">
                                        <button type="button" onclick="clearImage()" class="absolute top-2 right-2 bg-red-600 hover:bg-red-700 text-white rounded-full w-8 h-8 flex items-center justify-center shadow-lg">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Tabs pour sélection d'image -->
                                <div class="flex border-b border-gray-700 mb-4 rounded-t-lg overflow-hidden">
                                    <button type="button" id="tab-upload" class="flex-1 px-4 py-3 text-gray-300 hover:text-white hover:bg-purple-800 transition-all duration-200 focus:outline-none flex items-center justify-center" onclick="switchImageTab('upload')">
                                        <i class="fas fa-upload mr-2"></i> Upload
                                    </button>
                                    <button type="button" id="tab-library" class="flex-1 px-4 py-3 bg-purple-900 text-white flex items-center justify-center focus:outline-none" onclick="switchImageTab('library')">
                                        <i class="fas fa-images mr-2"></i> Bibliothèque
                                    </button>
                                    <button type="button" id="tab-url" class="flex-1 px-4 py-3 text-gray-300 hover:text-white hover:bg-purple-800 transition-all duration-200 focus:outline-none flex items-center justify-center" onclick="switchImageTab('url')">
                                        <i class="fas fa-link mr-2"></i> URL
                                    </button>
                                </div>
                                
                                <!-- Tab content: Upload -->
                                <div id="tab-content-upload" class="tab-content hidden">
                                    <input type="file" id="featured_image" name="featured_image" class="w-full p-2 bg-gray-800 border border-gray-700 rounded-lg text-white" accept="image/*">
                                    <p class="text-gray-400 text-sm mt-1">Téléchargez une image depuis votre ordinateur</p>
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
                                    <input type="url" id="image_url" name="image_url" placeholder="https://exemple.com/image.jpg" class="w-full p-2 bg-gray-800 border border-gray-700 rounded-lg text-white mb-2" value="<?php echo isset($post['featured_image']) && substr($post['featured_image'], 0, 4) === 'http' ? htmlspecialchars($post['featured_image']) : ''; ?>">
                                    <p class="text-gray-400 text-sm">
                                        Entrez l'URL complète d'une image hébergée sur Internet
                                    </p>
                                    <button type="button" onclick="previewExternalImage()" class="mt-2 bg-purple-700 hover:bg-purple-600 text-white px-4 py-2 rounded">
                                        <i class="fas fa-eye mr-1"></i> Prévisualiser
                                    </button>
                                </div>
                                
                                <input type="hidden" id="selected_image" name="current_image" value="<?php echo htmlspecialchars($post['featured_image'] ?? ''); ?>">
                            </div>
                            </div>
                            
                            <div>
                                <label for="status" class="block text-gray-300 mb-2">Statut</label>
                                <select id="status" name="status" class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300">
                                    <option value="draft" <?php echo ($post && $post['status'] === 'draft') ? 'selected' : ''; ?>>Brouillon</option>
                                    <option value="published" <?php echo ($post && $post['status'] === 'published') ? 'selected' : ''; ?>>Publié</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Backlinks Section -->
                        <?php if ($action === 'edit' && $post): ?>
                        <div class="mt-8 p-6 bg-gray-800 rounded-lg border border-purple-700">
                            <h3 class="text-xl font-bold text-white mb-4 flex items-center">
                                <i class="fas fa-external-link-alt mr-2 text-purple-400"></i>
                                Gestion des Backlinks
                            </h3>
                            
                            <div id="backlinks-container">
                                <!-- Backlinks will be loaded here via AJAX -->
                                <div class="text-center py-4">
                                    <i class="fas fa-spinner fa-spin text-purple-400"></i>
                                    <span class="text-gray-400 ml-2">Chargement des backlinks...</span>
                                </div>
                            </div>
                            
                            <!-- Add new backlink form -->
                            <div class="mt-6 p-4 bg-gray-900 rounded-lg border border-gray-700">
                                <h4 class="text-lg font-semibold text-white mb-3">Ajouter un nouveau backlink</h4>
                                <form id="add-backlink-form" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <input type="hidden" id="content_type" value="blog">
                                    <input type="hidden" id="content_id" value="<?php echo $post['id']; ?>">
                                    
                                    <div>
                                        <label class="block text-gray-300 text-sm font-medium mb-1">URL *</label>
                                        <input type="url" id="backlink_url" required class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-md text-white focus:border-purple-500 focus:outline-none" placeholder="https://exemple.com/article">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-300 text-sm font-medium mb-1">Type</label>
                                        <select id="backlink_type" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-md text-white focus:border-purple-500 focus:outline-none">
                                            <option value="reference">Référence</option>
                                            <option value="source">Source</option>
                                            <option value="inspiration">Inspiration</option>
                                            <option value="resource">Ressource</option>
                                            <option value="study">Étude</option>
                                            <option value="article">Article</option>
                                        </select>
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <label class="block text-gray-300 text-sm font-medium mb-1">Titre (optionnel)</label>
                                        <input type="text" id="backlink_title" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-md text-white focus:border-purple-500 focus:outline-none" placeholder="Titre du lien (sera extrait automatiquement si vide)">
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <label class="block text-gray-300 text-sm font-medium mb-1">Description (optionnelle)</label>
                                        <textarea id="backlink_description" rows="2" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-md text-white focus:border-purple-500 focus:outline-none" placeholder="Description du lien (sera extraite automatiquement si vide)"></textarea>
                                    </div>
                                    
                                    <div class="md:col-span-2 flex justify-end">
                                        <button type="submit" class="bg-purple-700 hover:bg-purple-600 text-white px-4 py-2 rounded-md transition duration-300">
                                            <i class="fas fa-plus mr-2"></i>Ajouter le backlink
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex justify-end space-x-4 mt-6">
                            <a href="blog.php" class="px-6 py-3 rounded-full border border-purple-600 text-white font-medium hover:bg-purple-900 transition duration-300">
                                Annuler
                            </a>
                            <button type="submit" name="save_post" class="btn-magic px-6 py-3 rounded-full text-white font-medium">
                                <?php echo $action === 'edit' ? 'Mettre à jour' : 'Publier'; ?> l'article
                            </button>
                        </div>
                </form>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script>
        <?php if ($action !== 'list'): ?>
        // Configuration améliorée de Quill avec support des images
        var quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],        // Formatage de texte
                    ['blockquote', 'code-block'],                      // Blocs
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],        // Titres avec plus d'options
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],      // Listes
                    [{ 'script': 'sub'}, { 'script': 'super' }],       // Exposant/Indice
                    [{ 'color': [] }, { 'background': [] }],           // Couleur texte/fond
                    [{ 'font': [] }],                                  // Police
                    [{ 'size': ['small', false, 'large', 'huge'] }],   // Taille du texte
                    [{ 'align': [] }],                                // Alignement
                    ['link', 'image', 'video'],                        // Liens, Images et Vidéos
                    ['clean']                                          // Effacer formatage
                ]
            },
            placeholder: 'Rédigez votre article ici...'
        });
        
        // Gestionnaire pour le téléchargement d'images
        const toolbar = quill.getModule('toolbar');
        toolbar.addHandler('image', function() {
            // Ouvrir la bibliothèque d'images dans une popup
            const popup = window.open('image_library.php', 'Bibliothèque d\'images', 'width=800,height=600');
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
        
        // Update hidden form field before submit
        document.querySelector('form').addEventListener('submit', function() {
            document.getElementById('content').value = quill.root.innerHTML;
        });
        <?php endif; ?>
        
        // Image tab switching
        function switchImageTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Show the selected tab content
            document.getElementById('tab-content-' + tabName).classList.remove('hidden');
            
            // Update tab button styles
            document.querySelectorAll('[id^="tab-"]').forEach(tab => {
                tab.classList.remove('bg-purple-900', 'text-white');
                tab.classList.add('text-gray-300');
            });
            
            document.getElementById('tab-' + tabName).classList.add('bg-purple-900', 'text-white');
            document.getElementById('tab-' + tabName).classList.remove('text-gray-300');
        }
        
        // Select image from library
        function selectImage(imagePath) {
            // Update the hidden input
            document.getElementById('selected_image').value = imagePath;
            
            // Update the preview image
            const previewImage = document.getElementById('preview-image');
            const imagePreview = document.getElementById('image-preview');
            
            // Determine if it's an external URL or local path
            const isExternal = imagePath.startsWith('http');
            previewImage.src = isExternal ? imagePath : '../' + imagePath;
            
            // Show the preview
            imagePreview.classList.remove('hidden');
        }
        
        // Preview external image from URL
        function previewExternalImage() {
            const imageUrl = document.getElementById('image_url').value;
            
            if (imageUrl) {
                // Update the hidden input
                document.getElementById('selected_image').value = imageUrl;
                
                // Update the preview
                const previewImage = document.getElementById('preview-image');
                const imagePreview = document.getElementById('image-preview');
                
                previewImage.src = imageUrl;
                imagePreview.classList.remove('hidden');
            } else {
                alert('Veuillez entrer une URL d\'image valide.');
            }
        }
        
        // Clear selected image
        function clearImage() {
            document.getElementById('selected_image').value = '';
            document.getElementById('image_url').value = '';
            document.getElementById('image-preview').classList.add('hidden');
        }
        
        // Delete confirmation modal
        function confirmDelete(postId, postTitle) {
            document.getElementById('deletePostId').value = postId;
            document.getElementById('deletePostTitle').textContent = postTitle;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
        
        // Backlinks management
        <?php if ($action === 'edit' && $post): ?>
        // Load backlinks when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadBacklinks();
        });
        
        // Load backlinks via AJAX
        function loadBacklinks() {
            const contentType = document.getElementById('content_type').value;
            const contentId = document.getElementById('content_id').value;
            
            fetch('includes/ajax_backlinks.php?action=get&content_type=' + contentType + '&content_id=' + contentId)
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('backlinks-container');
                    
                    if (data.success && data.backlinks.length > 0) {
                        let html = '<div class="space-y-3">';
                        data.backlinks.forEach(backlink => {
                            const typeColors = {
                                'reference': '#3b82f6',
                                'source': '#10b981',
                                'inspiration': '#f59e0b',
                                'resource': '#8b5cf6',
                                'study': '#ef4444',
                                'article': '#06b6d4'
                            };
                            
                            const typeLabels = {
                                'reference': 'Référence',
                                'source': 'Source',
                                'inspiration': 'Inspiration',
                                'resource': 'Ressource',
                                'study': 'Étude',
                                'article': 'Article'
                            };
                            
                            const color = typeColors[backlink.type] || '#6b7280';
                            const label = typeLabels[backlink.type] || backlink.type;
                            
                            html += `
                                <div class="bg-gray-900 rounded-lg p-4 border border-gray-700">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-1 rounded text-xs font-medium" style="background-color: ${color}20; color: ${color};">
                                                ${label}
                                            </span>
                                            <span class="text-gray-400 text-sm">${backlink.domain}</span>
                                        </div>
                                        <button onclick="deleteBacklink(${backlink.id})" class="text-red-400 hover:text-red-300 text-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    <h5 class="text-white font-medium mb-1">
                                        <a href="${backlink.url}" target="_blank" rel="noopener" class="hover:text-purple-400 transition-colors">
                                            ${backlink.title}
                                            <i class="fas fa-external-link-alt text-xs ml-1"></i>
                                        </a>
                                    </h5>
                                    ${backlink.description ? `<p class="text-gray-400 text-sm">${backlink.description}</p>` : ''}
                                </div>
                            `;
                        });
                        html += '</div>';
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<div class="text-center py-4 text-gray-400">Aucun backlink configuré pour cet article.</div>';
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des backlinks:', error);
                    document.getElementById('backlinks-container').innerHTML = '<div class="text-center py-4 text-red-400">Erreur lors du chargement des backlinks.</div>';
                });
        }
        
        // Add new backlink
        document.getElementById('add-backlink-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('content_type', document.getElementById('content_type').value);
            formData.append('content_id', document.getElementById('content_id').value);
            formData.append('url', document.getElementById('backlink_url').value);
            formData.append('type', document.getElementById('backlink_type').value);
            formData.append('title', document.getElementById('backlink_title').value);
            formData.append('description', document.getElementById('backlink_description').value);
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Ajout en cours...';
            submitBtn.disabled = true;
            
            fetch('includes/ajax_backlinks.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reset form
                    this.reset();
                    // Reload backlinks
                    loadBacklinks();
                    // Show success message
                    showMessage('Backlink ajouté avec succès!', 'success');
                } else {
                    showMessage(data.message || 'Erreur lors de l\'ajout du backlink', 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showMessage('Erreur lors de l\'ajout du backlink', 'error');
            })
            .finally(() => {
                // Restore button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        // Delete backlink
        function deleteBacklink(backlinkId) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer ce backlink ?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', backlinkId);
            
            fetch('includes/ajax_backlinks.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadBacklinks();
                    showMessage('Backlink supprimé avec succès!', 'success');
                } else {
                    showMessage(data.message || 'Erreur lors de la suppression', 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showMessage('Erreur lors de la suppression du backlink', 'error');
            });
        }
        
        // Show message function
        function showMessage(message, type) {
            // Create message element
            const messageDiv = document.createElement('div');
            messageDiv.className = `fixed top-4 right-4 p-4 rounded-lg z-50 ${type === 'success' ? 'bg-green-900 bg-opacity-90 text-green-300' : 'bg-red-900 bg-opacity-90 text-red-300'}`;
            messageDiv.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            // Add to page
            document.body.appendChild(messageDiv);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.parentNode.removeChild(messageDiv);
                }
            }, 5000);
        }
        <?php endif; ?>
    </script>
</body>
</html>
