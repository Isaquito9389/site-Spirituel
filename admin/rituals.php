<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Include database connection
require_once 'includes/db_connect.php';

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$messageType = '';

// Handle form submissions
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
        $featured_image = ''; // Will be updated if an image is uploaded
        
        // Validate form data
        if (empty($title) || empty($content)) {
            $message = "Le titre et le contenu sont obligatoires.";
            $messageType = "error";
        } else {
            // Handle image upload if present
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/rituals/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $file_extension = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '.' . $file_extension;
                $target_file = $upload_dir . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $target_file)) {
                    $featured_image = 'uploads/rituals/' . $filename;
                } else {
                    $message = "Erreur lors du téléchargement de l'image.";
                    $messageType = "error";
                }
            } elseif (isset($_POST['current_image']) && !empty($_POST['current_image'])) {
                $featured_image = $_POST['current_image'];
            }
            
            // If no errors, save to database
            if (empty($message)) {
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
                                status = :status";
                        
                        // Only update featured_image if a new one was uploaded
                        if (!empty($featured_image)) {
                            $sql .= ", featured_image = :featured_image";
                        }
                        
                        $sql .= " WHERE id = :ritual_id";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->bindParam(':ritual_id', $ritual_id, PDO::PARAM_INT);
                    } else {
                        // Insert new ritual
                        $sql = "INSERT INTO rituals (title, content, excerpt, category, duration, price, status, featured_image, created_at) 
                                VALUES (:title, :content, :excerpt, :category, :duration, :price, :status, :featured_image, NOW())";
                        $stmt = $pdo->prepare($sql);
                    }
                    
                    // Bind common parameters
                    $stmt->bindParam(':title', $title);
                    $stmt->bindParam(':content', $content);
                    $stmt->bindParam(':excerpt', $excerpt);
                    $stmt->bindParam(':category', $category);
                    $stmt->bindParam(':duration', $duration);
                    $stmt->bindParam(':price', $price);
                    $stmt->bindParam(':status', $status);
                    
                    // Bind featured_image if it exists
                    if (!empty($featured_image)) {
                        $stmt->bindParam(':featured_image', $featured_image);
                    }
                    
                    // Execute the statement
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
        }
    } elseif (isset($_POST['delete_ritual'])) {
        // Delete ritual
        $ritual_id = intval($_POST['ritual_id']);
        
        try {
            // Get the featured image path before deleting
            $stmt = $pdo->prepare("SELECT featured_image FROM rituals WHERE id = :ritual_id");
            $stmt->bindParam(':ritual_id', $ritual_id, PDO::PARAM_INT);
            $stmt->execute();
            $ritual = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete the ritual
            $stmt = $pdo->prepare("DELETE FROM rituals WHERE id = :ritual_id");
            $stmt->bindParam(':ritual_id', $ritual_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Delete the featured image if it exists
            if (!empty($ritual['featured_image'])) {
                $image_path = '../' . $ritual['featured_image'];
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
            
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
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM rituals WHERE id = :ritual_id");
        $stmt->bindParam(':ritual_id', $ritual_id, PDO::PARAM_INT);
        $stmt->execute();
        $ritual = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ritual) {
            $message = "Rituel introuvable.";
            $messageType = "error";
            $action = 'list';
        }
    } catch (PDOException $e) {
        $message = "Erreur de base de données: " . $e->getMessage();
        $messageType = "error";
        $action = 'list';
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
                    
                    <div class="mb-6">
                        <label for="featured_image" class="block text-gray-300 mb-2">Image à la une</label>
                        <input type="file" id="featured_image" name="featured_image" class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300" accept="image/*">
                        <?php if ($ritual && !empty($ritual['featured_image'])): ?>
                            <div class="mt-2 flex items-center">
                                <img src="../<?php echo $ritual['featured_image']; ?>" alt="Image actuelle" class="w-16 h-16 object-cover rounded mr-2">
                                <span class="text-gray-400 text-sm">Image actuelle</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <a href="rituals.php" class="px-6 py-3 rounded-full border border-purple-600 text-white font-medium hover:bg-purple-900 transition duration-300">
                            Annuler
                        </a>
                        <button type="submit" name="save_ritual" class="btn-magic px-6 py-3 rounded-full text-white font-medium">
                            <?php echo $action === 'edit' ? 'Mettre à jour' : 'Publier'; ?> le rituel
                        </button>
                    </div>
