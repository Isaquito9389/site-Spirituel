<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
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
    if (isset($_POST['save_category'])) {
        // Get form data
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $name = trim($_POST['name']);
        $slug = trim($_POST['slug']);
        $description = trim($_POST['description']);
        $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
        
        // Generate slug if empty
        if (empty($slug)) {
            $slug = strtolower(str_replace(' ', '-', $name));
            // Remove special characters
            $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
            // Remove multiple dashes
            $slug = preg_replace('/-+/', '-', $slug);
        }
        
        // Validate form data
        if (empty($name)) {
            $message = "Le nom de la catégorie est obligatoire.";
            $messageType = "error";
        } else {
            try {
                // Check if slug already exists (for another category)
                $stmt = $pdo->prepare("SELECT id FROM blog_categories WHERE slug = :slug AND id != :category_id");
                $stmt->bindParam(':slug', $slug);
                $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $message = "Ce slug est déjà utilisé. Veuillez en choisir un autre.";
                    $messageType = "error";
                } else {
                    // Prepare SQL statement based on whether it's an insert or update
                    if ($category_id > 0) {
                        // Update existing category
                        $sql = "UPDATE blog_categories SET 
                                name = :name, 
                                slug = :slug, 
                                description = :description, 
                                parent_id = :parent_id
                                WHERE id = :category_id";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
                    } else {
                        // Insert new category
                        $sql = "INSERT INTO blog_categories (name, slug, description, parent_id) 
                                VALUES (:name, :slug, :description, :parent_id)";
                        $stmt = $pdo->prepare($sql);
                    }
                    
                    // Bind common parameters
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':slug', $slug);
                    $stmt->bindParam(':description', $description);
                    
                    if ($parent_id !== null) {
                        $stmt->bindParam(':parent_id', $parent_id, PDO::PARAM_INT);
                    } else {
                        $stmt->bindValue(':parent_id', null, PDO::PARAM_NULL);
                    }
                    
                    // Execute the statement
                    $stmt->execute();
                    
                    // Set success message
                    if ($category_id > 0) {
                        $message = "La catégorie a été mise à jour avec succès.";
                    } else {
                        $message = "La catégorie a été créée avec succès.";
                    }
                    $messageType = "success";
                    
                    // Redirect to list view
                    header("Location: categories.php?message=" . urlencode($message) . "&type=" . $messageType);
                    exit;
                }
            } catch (PDOException $e) {
                $message = "Erreur de base de données: " . $e->getMessage();
                $messageType = "error";
            }
        }
    } elseif (isset($_POST['delete_category'])) {
        // Delete category
        $category_id = intval($_POST['category_id']);
        
        try {
            // First, update any child categories to have no parent
            $stmt = $pdo->prepare("UPDATE blog_categories SET parent_id = NULL WHERE parent_id = :category_id");
            $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Delete category
            $stmt = $pdo->prepare("DELETE FROM blog_categories WHERE id = :category_id");
            $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $message = "La catégorie a été supprimée avec succès.";
            $messageType = "success";
            
            // Redirect to list view
            header("Location: categories.php?message=" . urlencode($message) . "&type=" . $messageType);
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

// Get category data if editing
$category = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $category_id = intval($_GET['id']);
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM blog_categories WHERE id = :category_id");
        $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->execute();
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$category) {
            $message = "Catégorie introuvable.";
            $messageType = "error";
            $action = 'list';
        }
    } catch (PDOException $e) {
        $message = "Erreur de base de données: " . $e->getMessage();
        $messageType = "error";
        $action = 'list';
    }
}

// Get all categories
$categories = [];
try {
    $stmt = $pdo->query("SELECT c.*, p.name as parent_name 
                         FROM blog_categories c
                         LEFT JOIN blog_categories p ON c.parent_id = p.id
                         ORDER BY c.name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Erreur de base de données: " . $e->getMessage();
    $messageType = "error";
}

// Count posts per category
$category_post_counts = [];
try {
    $stmt = $pdo->query("SELECT category_id, COUNT(*) as post_count 
                         FROM blog_post_categories 
                         GROUP BY category_id");
    $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($counts as $count) {
        $category_post_counts[$count['category_id']] = $count['post_count'];
    }
} catch (PDOException $e) {
    // Silently fail, not critical
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Catégories - Mystica Occulta</title>
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
                        <a href="blog.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-blog w-6"></i>
                            <span>Blog</span>
                        </a>
                    </li>
                    <li>
                        <a href="categories.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white active">
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
                        <a href="comments.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-comments w-6"></i>
                            <span>Commentaires</span>
                        </a>
                    </li>
                    <li>
                        <a href="media.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-images w-6"></i>
                            <span>Médiathèque</span>
                        </a>
                    </li>
                    <li>
                        <a href="testimonials.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-quote-left w-6"></i>
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
                        Nouvelle Catégorie
                    <?php elseif ($action === 'edit'): ?>
                        Modifier la Catégorie
                    <?php else: ?>
                        Gestion des Catégories
                    <?php endif; ?>
                </h1>
                
                <?php if ($action === 'list'): ?>
                    <a href="?action=new" class="btn-magic px-4 py-2 rounded-full text-white font-medium inline-flex items-center">
                        <i class="fas fa-plus mr-2"></i> Nouvelle Catégorie
                    </a>
                <?php else: ?>
                    <a href="categories.php" class="px-4 py-2 rounded-full border border-purple-600 text-white font-medium inline-flex items-center hover:bg-purple-900 transition duration-300">
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
                <!-- Categories List -->
                <div class="card rounded-xl p-6 border border-purple-900">
                    <?php if (empty($categories)): ?>
                        <p class="text-gray-400 text-center py-8">Aucune catégorie trouvée. Commencez par en créer une nouvelle.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-purple-900">
                                        <th class="px-4 py-3 text-left text-gray-300">Nom</th>
                                        <th class="px-4 py-3 text-left text-gray-300">Slug</th>
                                        <th class="px-4 py-3 text-left text-gray-300">Description</th>
                                        <th class="px-4 py-3 text-left text-gray-300">Catégorie Parent</th>
                                        <th class="px-4 py-3 text-center text-gray-300">Articles</th>
                                        <th class="px-4 py-3 text-right text-gray-300">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr class="border-b border-purple-900 hover:bg-purple-900 hover:bg-opacity-20">
                                            <td class="px-4 py-3 text-white"><?php echo htmlspecialchars($cat['name']); ?></td>
                                            <td class="px-4 py-3 text-gray-400"><?php echo htmlspecialchars($cat['slug']); ?></td>
                                            <td class="px-4 py-3 text-gray-400">
                                                <?php 
                                                    $desc = htmlspecialchars($cat['description'] ?? '');
                                                    echo strlen($desc) > 50 ? substr($desc, 0, 50) . '...' : $desc; 
                                                ?>
                                            </td>
                                            <td class="px-4 py-3 text-gray-400">
                                                <?php echo $cat['parent_name'] ? htmlspecialchars($cat['parent_name']) : '-'; ?>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="px-2 py-1 rounded-full text-xs bg-purple-900 text-purple-300">
                                                    <?php echo isset($category_post_counts[$cat['id']]) ? $category_post_counts[$cat['id']] : '0'; ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <a href="?action=edit&id=<?php echo $cat['id']; ?>" class="text-blue-400 hover:text-blue-300 mx-1">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $cat['id']; ?>, '<?php echo addslashes($cat['name']); ?>')" class="text-red-400 hover:text-red-300 mx-1">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                                <a href="../category.php?slug=<?php echo $cat['slug']; ?>" target="_blank" class="text-green-400 hover:text-green-300 mx-1">
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
                        <p class="text-gray-300 mb-6">Êtes-vous sûr de vouloir supprimer la catégorie "<span id="deleteCategoryName"></span>" ? Cette action est irréversible.</p>
                        <div class="flex justify-end space-x-4">
                            <button onclick="closeDeleteModal()" class="px-4 py-2 rounded-full border border-purple-600 text-white font-medium hover:bg-purple-900 transition duration-300">
                                Annuler
                            </button>
                            <form id="deleteForm" method="POST" action="categories.php">
                                <input type="hidden" name="category_id" id="deleteCategoryId">
                                <button type="submit" name="delete_category" class="px-4 py-2 rounded-full bg-red-700 text-white font-medium hover:bg-red-800 transition duration-300">
                                    Supprimer
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Category Form -->
                <form method="POST" action="categories.php" class="card rounded-xl p-6 border border-purple-900">
                    <?php if ($action === 'edit' && $category): ?>
                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="name" class="block text-gray-300 mb-2">Nom de la catégorie *</label>
                            <input type="text" id="name" name="name" required class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="Entrez le nom de la catégorie" value="<?php echo $category ? htmlspecialchars($category['name']) : ''; ?>">
                        </div>
                        
                        <div>
                            <label for="slug" class="block text-gray-300 mb-2">Slug (URL)</label>
                            <input type="text" id="slug" name="slug" class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="Ex: ma-categorie (laissez vide pour générer automatiquement)" value="<?php echo $category ? htmlspecialchars($category['slug']) : ''; ?>">
                            <p class="text-gray-500 text-sm mt-1">Le slug est utilisé dans l'URL de la catégorie. Utilisez uniquement des lettres minuscules, des chiffres et des tirets.</p>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label for="description" class="block text-gray-300 mb-2">Description</label>
                        <textarea id="description" name="description" rows="3" class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="Description de la catégorie (optionnel)"><?php echo $category ? htmlspecialchars($category['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-6">
                        <label for="parent_id" class="block text-gray-300 mb-2">Catégorie parent</label>
                        <select id="parent_id" name="parent_id" class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300">
                            <option value="">Aucune (catégorie principale)</option>
                            <?php foreach ($categories as $cat): ?>
                                <?php 
                                // Skip the current category (can't be its own parent)
                                if ($action === 'edit' && $category && $cat['id'] == $category['id']) continue; 
                                ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($category && $category['parent_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <a href="categories.php" class="px-6 py-3 rounded-full border border-purple-600 text-white font-medium hover:bg-purple-900 transition duration-300">
                            Annuler
                        </a>
                        <button type="submit" name="save_category" class="btn-magic px-6 py-3 rounded-full text-white font-medium">
                            <?php echo $action === 'edit' ? 'Mettre à jour' : 'Créer'; ?> la catégorie
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        // Delete confirmation modal
        function confirmDelete(categoryId, categoryName) {
            document.getElementById('deleteCategoryId').value = categoryId;
            document.getElementById('deleteCategoryName').textContent = categoryName;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
        
        // Auto-generate slug from name
        const nameInput = document.getElementById('name');
        const slugInput = document.getElementById('slug');
        
        if (nameInput && slugInput) {
            nameInput.addEventListener('input', function() {
                // Only auto-generate if slug is empty or hasn't been manually edited
                if (slugInput.value === '' || slugInput._autoGenerated) {
                    const slug = this.value
                        .toLowerCase()
                        .replace(/[^\w\s-]/g, '') // Remove special chars
                        .replace(/\s+/g, '-')     // Replace spaces with -
                        .replace(/-+/g, '-');     // Replace multiple - with single -
                    
                    slugInput.value = slug;
                    slugInput._autoGenerated = true;
                }
            });
            
            slugInput.addEventListener('input', function() {
                // If user manually edits the slug, stop auto-generating
                slugInput._autoGenerated = false;
            });
        }
    </script>
</body>
</html>
