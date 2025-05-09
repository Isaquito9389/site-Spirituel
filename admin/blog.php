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
            // Handle image upload if present
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/blog/';
                
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
                    $featured_image = 'uploads/blog/' . $filename;
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
                        // Insert new post
                        $sql = "INSERT INTO blog_posts (title, content, excerpt, category, status, featured_image, created_at) 
                                VALUES (:title, :content, :excerpt, :category, :status, :featured_image, NOW())";
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
                    $stmt->execute();
                    
                    // Set success message
                    if ($post_id > 0) {
                        $message = "L'article a été mis à jour avec succès.";
                    } else {
                        $message = "L'article a été créé avec succès.";
                    }
                    $messageType = "success";
                    
                    // Redirect to list view
                    header("Location: blog.php?message=" . urlencode($message) . "&type=" . $messageType);
                    exit;
                } catch (PDOException $e) {
                    $message = "Erreur de base de données: " . $e->getMessage();
                    $messageType = "error";
                }
            }
        }
    } elseif (isset($_POST['delete_post'])) {
        // Delete post
        $post_id = intval($_POST['post_id']);
        
        try {
            // Get the featured image path before deleting
            $stmt = $pdo->prepare("SELECT featured_image FROM blog_posts WHERE id = :post_id");
            $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
            $stmt->execute();
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete the post
            $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = :post_id");
            $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Delete the featured image if it exists
            if (!empty($post['featured_image'])) {
                $image_path = '../' . $post['featured_image'];
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
            
            $message = "L'article a été supprimé avec succès.";
            $messageType = "success";
            
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

// Get all posts if listing
$posts = [];
if ($action === 'list') {
    try {
        $stmt = $pdo->query("SELECT * FROM blog_posts ORDER BY created_at DESC");
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
                            <label for="title" class="block text-gray-300 mb-2">Titre de l'article *</label>
                            <input type="text" id="title" name="title" required class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="Entrez le titre de l'article" value="<?php echo $post ? htmlspecialchars($post['title']) : ''; ?>">
                        </div>
                        
                        <div>
                            <label for="category" class="block text-gray-300 mb-2">Catégorie</label>
                            <input type="text" id="category" name="category" class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="Ex: Rituels, Spiritualité, etc." value="<?php echo $post ? htmlspecialchars($post['category']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label for="excerpt" class="block text-gray-300 mb-2">Extrait</label>
                        <textarea id="excerpt" name="excerpt" rows="2" class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="Bref résumé de l'article (affiché dans les listes)"><?php echo $post ? htmlspecialchars($post['excerpt']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-6">
                        <label for="editor" class="block text-gray-300 mb-2">Contenu de l'article *</label>
                        <div id="editor"><?php echo $post ? $post['content'] : ''; ?></div>
                        <input type="hidden" name="content" id="content">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="featured_image" class="block text-gray-300 mb-2">Image à la une</label>
                            <input type="file" id="featured_image" name="featured_image" class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300" accept="image/*">
                            <?php if ($post && !empty($post['featured_image'])): ?>
                                <div class="mt-2 flex items-center">
                                    <img src="../<?php echo $post['featured_image']; ?>" alt="Image actuelle" class="w-16 h-16 object-cover rounded mr-2">
                                    <span class="text-gray-400 text-sm">Image actuelle</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label for="status" class="block text-gray-300 mb-2">Statut</label>
                            <select id="status" name="status" class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300">
                                <option value="draft" <?php echo ($post && $post['status'] === 'draft') ? 'selected' : ''; ?>>Brouillon</option>
                                <option value="published" <?php echo ($post && $post['status'] === 'published') ? 'selected' : ''; ?>>Publié</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
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
    <script>
        <?php if ($action !== 'list'): ?>
        // Initialize Quill editor
        var quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['link', 'image', 'video'],
                    ['clean']
                ]
            },
            placeholder: 'Rédigez votre article ici...'
        });
        
        // Update hidden form field before submit
        document.querySelector('form').addEventListener('submit', function() {
            document.getElementById('content').value = quill.root.innerHTML;
        });
        <?php endif; ?>
        
        // Delete confirmation modal
        function confirmDelete(postId, postTitle) {
            document.getElementById('deletePostId').value = postId;
            document.getElementById('deletePostTitle').textContent = postTitle;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
    </script>
</body>
</html>
