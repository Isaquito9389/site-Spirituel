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

// Include database connection
require_once 'includes/db_connect.php';
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
        
        // Validate form data
        if (empty($title) || empty($content)) {
            $message = "Le titre et le contenu sont obligatoires.";
            $messageType = "error";
        } else {
            // Désactivation de l'upload de fichiers sur InfinityFree
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Message d'information pour l'utilisateur
                $message = "L'upload de fichiers n'est pas disponible sur cet hébergement. Merci d'utiliser une URL d'image externe.";
                $messageType = "error";
                if (isset($_POST['image_url']) && !empty($_POST['image_url'])) {
                    $featured_image = $_POST['image_url'];
                } elseif (isset($_POST['current_image']) && !empty($_POST['current_image'])) {
                    $featured_image = $_POST['current_image'];
                } else {
                    $featured_image = '';
                }
            } elseif (isset($_POST['image_url']) && !empty($_POST['image_url'])) {
                $featured_image = $_POST['image_url'];
            } elseif (isset($_POST['current_image']) && !empty($_POST['current_image'])) {
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
                            updated_at = NOW()
                            WHERE id = :ritual_id";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':ritual_id', $ritual_id, PDO::PARAM_INT);
                } else {
                    // Insert new ritual
                    $sql = "INSERT INTO rituals (title, content, excerpt, category, duration, price, status, featured_image, created_at) 
                            VALUES (:title, :content, :excerpt, :category, :duration, :price, :status, :featured_image, NOW())";
                    
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
                
                $stmt->execute();
                
                // Set success message
                if ($ritual_id > 0) {
                    $message = "Le rituel a été mis à jour avec succès.";
                } else {
                    $message = "Le rituel a été créé avec succès.";
                }
                $messageType = "success";
                
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
                                                <a href="../ritual.php?id=<?php echo $ritual['id']; ?>" target="_blank" class="text-green-400 hover:text-green-300 mx-1">
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
                            <div class="flex flex-col space-y-4">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-1">
                                        <input type="file" id="featured_image" name="featured_image" class="w-full p-2 bg-gray-800 border border-gray-700 rounded-lg text-white" disabled>
                                        <p class="text-red-400 text-sm mt-1">L'upload de fichiers est désactivé sur cet hébergement. Utilisez une URL d'image externe.</p>
                                    </div>
                                    <?php if (!empty($ritual['featured_image'])): ?>
                                    <div class="w-24 h-24 bg-gray-800 rounded-lg overflow-hidden">
                                        <img src="../<?php echo htmlspecialchars($ritual['featured_image']); ?>" alt="Image à la une" class="w-full h-full object-cover">
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex-1 mt-2">
                                    <label for="image_url" class="block text-white mb-2">OU utilisez une URL d'image</label>
                                    <input type="url" id="image_url" name="image_url" placeholder="https://exemple.com/image.jpg" class="w-full p-2 bg-gray-800 border border-gray-700 rounded-lg text-white">
                                    <p class="text-gray-400 text-sm mt-1">Si l'upload ne fonctionne pas, vous pouvez utiliser une URL d'image externe</p>
                                </div>
                            </div>
                            <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($ritual['featured_image'] ?? ''); ?>">
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

    <!-- Scripts - Simplifiés pour éviter les erreurs 500 -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script>
        // Configuration simple de Quill pour éviter les problèmes
        const quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link']
                ] // toolbar simplifié
            },
        });
        
        // Mise à jour du contenu avant soumission du formulaire
        document.querySelector('form').addEventListener('submit', function() {
            document.getElementById('content').value = quill.root.innerHTML;
        });
        
        // Fonctions pour la fenêtre modale de suppression
        function confirmDelete(ritualId, ritualTitle) {
            document.getElementById('deleteRitualId').value = ritualId;
            document.getElementById('deleteRitualTitle').textContent = ritualTitle;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
    </script>
</body>
</html>
