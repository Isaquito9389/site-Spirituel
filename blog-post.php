<?php
// Affichage des erreurs en mode développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusion de la connexion à la base de données
require_once 'admin/includes/db_connect.php';

// Vérification que l'ID de l'article est bien fourni
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: blog.php');
    exit;
}

// Récupération de l'ID de l'article
$post_id = intval($_GET['id']);

// Récupération de l'article depuis la base de données
$post = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ? AND status = 'published'");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        // Article non trouvé ou non publié
        header('Location: blog.php');
        exit;
    }
    
    // Incrémenter le compteur de vues
    $views = isset($post['views']) ? $post['views'] + 1 : 1;
    $update = $pdo->prepare("UPDATE blog_posts SET views = ? WHERE id = ?");
    $update->execute([$views, $post_id]);
    
} catch (PDOException $e) {
    // En cas d'erreur, on redirige vers la liste des articles
    error_log("Erreur lors de la récupération de l'article: " . $e->getMessage());
    header('Location: blog.php');
    exit;
}

// Récupération des articles similaires (même catégorie)
$similar_posts = [];
if (!empty($post['category'])) {
    try {
        $stmt = $pdo->prepare("SELECT id, title, featured_image FROM blog_posts 
                              WHERE category = ? AND id != ? AND status = 'published' 
                              ORDER BY created_at DESC LIMIT 3");
        $stmt->execute([$post['category'], $post_id]);
        $similar_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Silencieux - ce n'est pas critique
    }
}

// Récupérer les informations de l'auteur
$author_name = "Admin";
if (!empty($post['author_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$post['author_id']]);
        $author = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($author) {
            $author_name = $author['username'];
        }
    } catch (PDOException $e) {
        // Silencieux - ce n'est pas critique
    }
}

// Récupération des tags si présents
$tags = [];
if (!empty($post['tags'])) {
    $tags = explode(',', $post['tags']);
    // Nettoyage des tags (suppression des espaces)
    $tags = array_map('trim', $tags);
}

// Récupération des articles récents pour la sidebar
$recent_posts = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, featured_image FROM blog_posts 
                          WHERE id != ? AND status = 'published' 
                          ORDER BY created_at DESC LIMIT 4");
    $stmt->execute([$post_id]);
    $recent_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silencieux - ce n'est pas critique
}

// Récupération des catégories pour la sidebar
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM blog_categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silencieux - ce n'est pas critique
}

// Titre de la page
$page_title = $post['title'] . " - Mystica Occulta";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <!-- Meta tags pour SEO -->
    <meta name="description" content="<?php echo htmlspecialchars(substr(strip_tags($post['excerpt'] ?: $post['content']), 0, 160)); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($post['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(substr(strip_tags($post['excerpt'] ?: $post['content']), 0, 160)); ?>">
    <?php if (!empty($post['featured_image'])): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($post['featured_image']); ?>">
    <?php endif; ?>
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400;1,700;1,900&display=swap');
        
        body {
            font-family: 'Merriweather', serif;
            background-color: #0f0e17;
            color: #fffffe;
        }
        
        .font-cinzel {
            font-family: 'Cinzel Decorative', cursive;
        }
        
        .bg-mystic {
            background: radial-gradient(circle at center, #3a0ca3 0%, #1a1a2e 70%);
        }
        
        .button-magic {
            background: linear-gradient(45deg, #7209b7, #3a0ca3);
            transition: all 0.3s ease;
        }
        
        .button-magic:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 15px rgba(58, 12, 163, 0.4);
        }
        
        .content-area {
            line-height: 1.8;
        }
        
        .content-area p {
            margin-bottom: 1.5rem;
        }
        
        .content-area h2 {
            font-family: 'Cinzel Decorative', cursive;
            font-size: 1.75rem;
            margin-top: 2rem;
            margin-bottom: 1rem;
            color: #f72585;
        }
        
        .content-area h3 {
            font-family: 'Cinzel Decorative', cursive;
            font-size: 1.5rem;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            color: #7209b7;
        }
        
        .content-area ul, .content-area ol {
            margin-left: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .content-area li {
            margin-bottom: 0.5rem;
        }
        
        .content-area img {
            max-width: 100%;
            height: auto;
            border-radius: 0.5rem;
            margin: 1.5rem 0;
        }
        
        .card {
            background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
        }
    </style>
</head>

<body class="bg-dark">
    <!-- Header -->
    <header class="bg-gradient-to-r from-purple-900 to-indigo-900 text-white shadow-lg">
        <div class="container mx-auto px-4 py-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="flex items-center mb-4 md:mb-0">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-600 to-pink-600 flex items-center justify-center mr-4">
                        <i class="fas fa-moon text-white text-xl"></i>
                    </div>
                    <h1 class="text-3xl font-cinzel font-bold text-white">Mystica Occulta</h1>
                </div>
                
                <nav class="flex flex-wrap justify-center">
                    <a href="index.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Accueil</a>
                    <a href="rituals.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Rituels</a>
                    <a href="blog.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300 border-b-2 border-pink-500">Blog</a>
                    <a href="about.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">À propos</a>
                    <a href="contact.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Contact</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-12">
        <div class="flex flex-col lg:flex-row">
            <!-- Article Content -->
            <div class="lg:w-3/4 lg:pr-8">
                <!-- Breadcrumbs -->
                <div class="mb-6 text-gray-400">
                    <a href="index.php" class="hover:text-pink-400 transition">Accueil</a> &raquo; 
                    <a href="blog.php" class="hover:text-pink-400 transition">Blog</a> 
                    <?php if (!empty($post['category'])): ?>
                    &raquo; <a href="blog.php?category=<?php echo urlencode($post['category']); ?>" class="hover:text-pink-400 transition"><?php echo htmlspecialchars($post['category']); ?></a>
                    <?php endif; ?>
                    &raquo; <span class="text-purple-400"><?php echo htmlspecialchars($post['title']); ?></span>
                </div>
                
                <!-- Article Header -->
                <div class="mb-8">
                    <h1 class="text-4xl md:text-5xl font-cinzel font-bold text-white mb-4"><?php echo htmlspecialchars($post['title']); ?></h1>
                    
                    <div class="flex flex-wrap items-center text-gray-400 mb-6">
                        <span class="mr-6 mb-2"><i class="far fa-user mr-2"></i><?php echo htmlspecialchars($author_name); ?></span>
                        <span class="mr-6 mb-2"><i class="far fa-calendar mr-2"></i><?php echo date('d/m/Y', strtotime($post['created_at'])); ?></span>
                        <?php if (!empty($post['category'])): ?>
                        <span class="mr-6 mb-2"><i class="far fa-folder mr-2"></i><?php echo htmlspecialchars($post['category']); ?></span>
                        <?php endif; ?>
                        <span class="mb-2"><i class="far fa-eye mr-2"></i><?php echo isset($post['views']) ? $post['views'] : '0'; ?> vues</span>
                    </div>
                    
                    <?php if (!empty($post['featured_image'])): ?>
                    <div class="mb-8 rounded-lg overflow-hidden shadow-lg">
                        <?php if (substr($post['featured_image'], 0, 4) === 'http'): ?>
                            <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="w-full h-auto">
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="w-full h-auto">
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Article Content -->
                <div class="card p-8 rounded-lg shadow-lg mb-8">
                    <div class="content-area text-gray-200">
                        <?php echo $post['content']; ?>
                    </div>
                </div>
                
                <!-- Tags -->
                <?php if (!empty($tags)): ?>
                <div class="mb-8">
                    <h3 class="text-xl font-cinzel font-bold text-white mb-3">Tags</h3>
                    <div class="flex flex-wrap">
                        <?php foreach ($tags as $tag): ?>
                        <a href="blog.php?tag=<?php echo urlencode($tag); ?>" class="px-3 py-1 rounded-full bg-purple-900 text-white text-sm mr-2 mb-2 hover:bg-purple-800 transition">
                            <?php echo htmlspecialchars($tag); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Social Sharing -->
                <div class="mb-12">
                    <h3 class="text-xl font-cinzel font-bold text-white mb-3">Partager cet article</h3>
                    <div class="flex space-x-3">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center hover:bg-blue-700 transition">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode($post['title']); ?>" target="_blank" class="w-10 h-10 rounded-full bg-blue-400 text-white flex items-center justify-center hover:bg-blue-500 transition">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://wa.me/?text=<?php echo urlencode($post['title'] . ' - https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="w-10 h-10 rounded-full bg-green-600 text-white flex items-center justify-center hover:bg-green-700 transition">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <a href="mailto:?subject=<?php echo urlencode($post['title']); ?>&body=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="w-10 h-10 rounded-full bg-gray-700 text-white flex items-center justify-center hover:bg-gray-800 transition">
                            <i class="fas fa-envelope"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Similar Articles -->
                <?php if (!empty($similar_posts)): ?>
                <div class="mb-12">
                    <h3 class="text-2xl font-cinzel font-bold text-white mb-6">Articles similaires</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php foreach ($similar_posts as $similar): ?>
                        <a href="blog-post.php?id=<?php echo $similar['id']; ?>" class="card rounded-lg overflow-hidden shadow-lg hover:shadow-xl">
                            <div class="relative h-40">
                                <?php if (!empty($similar['featured_image'])): ?>
                                    <?php if (substr($similar['featured_image'], 0, 4) === 'http'): ?>
                                        <img src="<?php echo htmlspecialchars($similar['featured_image']); ?>" alt="<?php echo htmlspecialchars($similar['title']); ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <img src="<?php echo htmlspecialchars($similar['featured_image']); ?>" alt="<?php echo htmlspecialchars($similar['title']); ?>" class="w-full h-full object-cover">
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="w-full h-full bg-gradient-to-br from-purple-900 to-indigo-900"></div>
                                <?php endif; ?>
                                <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent opacity-60"></div>
                            </div>
                            <div class="p-4">
                                <h4 class="text-white font-bold hover:text-purple-300 transition"><?php echo htmlspecialchars($similar['title']); ?></h4>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div class="lg:w-1/4 mt-8 lg:mt-0">
                <!-- Search -->
                <div class="card p-6 rounded-lg shadow-lg mb-8">
                    <h3 class="text-xl font-cinzel font-bold text-white mb-4">Rechercher</h3>
                    <form action="search.php" method="get">
                        <div class="flex">
                            <input type="text" name="query" placeholder="Rechercher..." class="flex-1 py-2 px-4 bg-gray-800 text-white rounded-l-lg focus:outline-none">
                            <button type="submit" class="bg-purple-700 text-white py-2 px-4 rounded-r-lg hover:bg-purple-600 transition">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Recent Posts -->
                <?php if (!empty($recent_posts)): ?>
                <div class="card p-6 rounded-lg shadow-lg mb-8">
                    <h3 class="text-xl font-cinzel font-bold text-white mb-4">Articles Récents</h3>
                    <div class="space-y-4">
                        <?php foreach ($recent_posts as $recent): ?>
                        <a href="blog-post.php?id=<?php echo $recent['id']; ?>" class="flex items-center group">
                            <?php if (!empty($recent['featured_image'])): ?>
                            <div class="w-16 h-16 rounded-lg overflow-hidden mr-4 flex-shrink-0">
                                <?php if (substr($recent['featured_image'], 0, 4) === 'http'): ?>
                                    <img src="<?php echo htmlspecialchars($recent['featured_image']); ?>" alt="<?php echo htmlspecialchars($recent['title']); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($recent['featured_image']); ?>" alt="<?php echo htmlspecialchars($recent['title']); ?>" class="w-full h-full object-cover">
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <h4 class="text-gray-300 group-hover:text-purple-400 transition"><?php echo htmlspecialchars($recent['title']); ?></h4>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Categories -->
                <?php if (!empty($categories)): ?>
                <div class="card p-6 rounded-lg shadow-lg mb-8">
                    <h3 class="text-xl font-cinzel font-bold text-white mb-4">Catégories</h3>
                    <ul class="space-y-2">
                        <?php foreach ($categories as $cat): ?>
                        <li>
                            <a href="blog.php?category=<?php echo urlencode($cat['name']); ?>" class="flex justify-between items-center text-gray-300 hover:text-purple-400 transition">
                                <span><?php echo htmlspecialchars($cat['name']); ?></span>
                                <?php 
                                try {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE category = ? AND status = 'published'");
                                    $stmt->execute([$cat['name']]);
                                    $count = $stmt->fetchColumn();
                                    echo "<span class='bg-gray-800 text-gray-400 px-2 py-1 rounded-full text-xs'>$count</span>";
                                } catch (PDOException $e) {
                                    echo "<span class='bg-gray-800 text-gray-400 px-2 py-1 rounded-full text-xs'>0</span>";
                                }
                                ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <!-- Newsletter -->
                <div class="card p-6 rounded-lg shadow-lg mb-8">
                    <h3 class="text-xl font-cinzel font-bold text-white mb-4">Newsletter</h3>
                    <p class="text-gray-400 mb-4">Abonnez-vous pour recevoir nos derniers articles et offres spéciales.</p>
                    <form action="subscribe.php" method="post">
                        <input type="email" name="email" placeholder="Votre email" class="w-full py-2 px-4 bg-gray-800 text-white rounded-lg mb-4 focus:outline-none">
                        <button type="submit" class="w-full button-magic py-2 rounded-lg text-white font-medium">
                            S'abonner
                        </button>
                    </form>
                </div>
                
                <!-- Call to action -->
                <div class="card p-6 rounded-lg shadow-lg">
                    <h3 class="text-xl font-cinzel font-bold text-white mb-4">Besoin d'aide ?</h3>
                    <p class="text-gray-400 mb-4">Découvrez nos rituels et consultations personnalisées pour transformer votre vie.</p>
                    <a href="rituals.php" class="block w-full button-magic py-2 rounded-lg text-white font-medium text-center">
                        Voir les rituels
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4 font-cinzel">Mystica Occulta</h3>
                    <p class="text-gray-400 mb-4">Votre portail vers le monde de l'ésotérisme, de la magie et des rituels ancestraux.</p>
                </div>
                
                <div>
                    <h3 class="text-xl font-bold mb-4 font-cinzel">Navigation</h3>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-400 hover:text-purple-400 transition">Accueil</a></li>
                        <li><a href="rituals.php" class="text-gray-400 hover:text-purple-400 transition">Rituels</a></li>
                        <li><a href="blog.php" class="text-gray-400 hover:text-purple-400 transition">Blog</a></li>
                        <li><a href="about.php" class="text-gray-400 hover:text-purple-400 transition">À propos</a></li>
                        <li><a href="contact.php" class="text-gray-400 hover:text-purple-400 transition">Contact</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-xl font-bold mb-4 font-cinzel">Contact</h3>
                    <ul class="space-y-2">
                        <li class="flex items-start">
                            <i class="fas fa-envelope mt-1 mr-3 text-purple-400"></i>
                            <span class="text-gray-400">contact@mysticaocculta.com</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fab fa-whatsapp mt-1 mr-3 text-purple-400"></i>
                            <span class="text-gray-400">+33 XX XX XX XX</span>
                        </li>
                    </ul>
                    
                    <div class="flex space-x-4 mt-6">
                        <a href="#" class="text-purple-400 hover:text-white transition"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-purple-400 hover:text-white transition"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-purple-400 hover:text-white transition"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-500">
                <p>&copy; <?php echo date('Y'); ?> Mystica Occulta. Tous droits réservés.</p>
            </div>
        </div>
    </footer>
</body>
</html>
