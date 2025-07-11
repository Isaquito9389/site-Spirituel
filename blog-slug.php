<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
// Affichage des erreurs en mode développement
// Inclusion de la connexion à la base de données
require_once 'includes/db_connect.php';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 6; // Nombre d'articles par page
$offset = ($page - 1) * $per_page;

// Filtres
$category = isset($_GET['category']) ? $_GET['category'] : null;
$tag = isset($_GET['tag']) ? $_GET['tag'] : null;

// Construction de la requête
$sql_count = "SELECT COUNT(*) FROM blog_posts WHERE status = 'published'";
$sql = "SELECT * FROM blog_posts WHERE status = 'published'";

// Application des filtres
if ($category) {
    $sql .= " AND category = :category";
    $sql_count .= " AND category = :category";
}
if ($tag) {
    $sql .= " AND tags LIKE :tag";
    $sql_count .= " AND tags LIKE :tag";
}

// Tri
$sql .= " ORDER BY created_at DESC";

// Limite pour pagination
$sql .= " LIMIT :offset, :per_page";

// Récupération des articles
$posts = [];
$total_posts = 0;

try {
    // Compte total pour pagination
    $stmt_count = $pdo->prepare($sql_count);
    if ($category) {
        $stmt_count->bindParam(':category', $category, PDO::PARAM_STR);
    }
    if ($tag) {
        $tag_param = "%$tag%";
        $stmt_count->bindParam(':tag', $tag_param, PDO::PARAM_STR);
    }
    $stmt_count->execute();
    $total_posts = $stmt_count->fetchColumn();
    
    // Récupération des articles
    $stmt = $pdo->prepare($sql);
    if ($category) {
        $stmt->bindParam(':category', $category, PDO::PARAM_STR);
    }
    if ($tag) {
        $tag_param = "%$tag%";
        $stmt->bindParam(':tag', $tag_param, PDO::PARAM_STR);
    }
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    }

// Calcul du nombre total de pages
$total_pages = ceil($total_posts / $per_page);

// Récupération des catégories pour le filtre
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM blog_categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    }

// Récupération des articles populaires pour la sidebar
$popular_posts = [];
try {
    $stmt = $pdo->query("SELECT id, title, slug, featured_image FROM blog_posts WHERE status = 'published' ORDER BY views DESC LIMIT 3");
    $popular_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    }

// Titre de la page
$page_title = "Blog - Mystica Occulta";
if ($category) {
    foreach ($categories as $cat) {
        if ($cat['name'] === $category) {
            $page_title = htmlspecialchars($cat['name']) . " - Blog Mystica Occulta";
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Meta tags pour SEO -->
    <meta name="description" content="Explorez notre blog sur l'ésotérisme, la spiritualité et les pratiques magiques. Découvrez des articles fascinants sur les rituels, la divination et plus encore.">
    
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
        
        .card {
            background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
        }
        
        .category-filter.active {
            background-color: #7209b7;
            color: white;
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

    <!-- Hero Section -->
    <section class="bg-mystic py-16">
        <div class="container mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-5xl font-cinzel font-bold text-white mb-6">Blog Mystica Occulta</h1>
            <p class="text-xl text-gray-300 max-w-3xl mx-auto">Explorez notre blog pour découvrir des articles fascinants sur l'ésotérisme, la spiritualité et les pratiques magiques ancestrales.</p>
        </div>
    </section>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-12">
        <div class="flex flex-col lg:flex-row">
            <!-- Blog Posts -->
            <div class="lg:w-3/4 lg:pr-8">
                <?php if ($category): ?>
                    <div class="mb-6">
                        <a href="blog.php" class="text-purple-400 hover:text-purple-300 transition"><i class="fas fa-arrow-left mr-2"></i>Retour à tous les articles</a>
                        <h2 class="text-3xl font-cinzel font-bold text-white mt-2">Catégorie : <?php echo htmlspecialchars($category); ?></h2>
                    </div>
                <?php endif; ?>
                
                <?php if ($tag): ?>
                    <div class="mb-6">
                        <a href="blog.php" class="text-purple-400 hover:text-purple-300 transition"><i class="fas fa-arrow-left mr-2"></i>Retour à tous les articles</a>
                        <h2 class="text-3xl font-cinzel font-bold text-white mt-2">Articles avec le tag : <?php echo htmlspecialchars($tag); ?></h2>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($posts)): ?>
                    <div class="text-center py-16 card rounded-lg">
                        <h3 class="text-2xl font-cinzel text-gray-400 mb-4">Aucun article trouvé</h3>
                        <p class="text-gray-500">Aucun article n'est disponible pour le moment dans cette catégorie.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <?php foreach ($posts as $post): ?>
                        <a href="blog-post-slug.php?slug=<?php echo urlencode($post['slug']); ?>" class="card rounded-lg overflow-hidden shadow-lg hover:shadow-xl">
                            <div class="relative h-56">
                                <?php if (!empty($post['featured_image'])): ?>
                                    <?php if (substr($post['featured_image'], 0, 4) === 'http'): ?>
                                        <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="w-full h-full object-cover">
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="w-full h-full bg-gradient-to-br from-purple-900 to-indigo-900"></div>
                                <?php endif; ?>
                                <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent opacity-60"></div>
                                <?php if (!empty($post['category'])): ?>
                                <div class="absolute top-4 left-4 bg-indigo-900 bg-opacity-80 text-white px-3 py-1 rounded-full text-xs">
                                    <?php echo htmlspecialchars($post['category']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-6">
                                <h3 class="text-xl font-bold text-white mb-2"><?php echo htmlspecialchars($post['title']); ?></h3>
                                <?php if (!empty($post['excerpt'])): ?>
                                <p class="text-gray-400 mb-4 line-clamp-3"><?php echo htmlspecialchars(substr($post['excerpt'], 0, 120)) . (strlen($post['excerpt']) > 120 ? '...' : ''); ?></p>
                                <?php endif; ?>
                                <div class="flex items-center text-gray-500 text-sm">
                                    <span class="inline-block mr-4"><i class="far fa-calendar mr-1"></i><?php echo date('d/m/Y', strtotime($post['created_at'])); ?></span>
                                    <?php if (isset($post['views'])): ?>
                                    <span class="inline-block"><i class="far fa-eye mr-1"></i><?php echo $post['views']; ?> vues</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="flex justify-center mt-12">
                    <div class="inline-flex">
                        <?php if ($page > 1): ?>
                        <a href="blog.php?page=<?php echo $page-1; ?><?php echo $category ? '&category='.urlencode($category) : ''; ?><?php echo $tag ? '&tag='.urlencode($tag) : ''; ?>" class="px-4 py-2 mx-1 rounded-lg bg-gray-800 text-white hover:bg-gray-700 transition">
                            <i class="fas fa-chevron-left mr-1"></i> Précédent
                        </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="blog.php?page=<?php echo $i; ?><?php echo $category ? '&category='.urlencode($category) : ''; ?><?php echo $tag ? '&tag='.urlencode($tag) : ''; ?>" class="px-4 py-2 mx-1 rounded-lg <?php echo $i === $page ? 'bg-purple-700 text-white' : 'bg-gray-800 text-white hover:bg-gray-700'; ?> transition">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="blog.php?page=<?php echo $page+1; ?><?php echo $category ? '&category='.urlencode($category) : ''; ?><?php echo $tag ? '&tag='.urlencode($tag) : ''; ?>" class="px-4 py-2 mx-1 rounded-lg bg-gray-800 text-white hover:bg-gray-700 transition">
                            Suivant <i class="fas fa-chevron-right ml-1"></i>
                        </a>
                        <?php endif; ?>
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
                
                <!-- Popular Posts -->
                <?php if (!empty($popular_posts)): ?>
                <div class="card p-6 rounded-lg shadow-lg mb-8">
                    <h3 class="text-xl font-cinzel font-bold text-white mb-4">Articles Populaires</h3>
                    <div class="space-y-4">
                        <?php foreach ($popular_posts as $post): ?>
                        <a href="blog-post-slug.php?slug=<?php echo urlencode($post['slug']); ?>" class="flex items-center group">
                            <?php if (!empty($post['featured_image'])): ?>
                            <div class="w-16 h-16 rounded-lg overflow-hidden mr-4 flex-shrink-0">
                                <?php if (substr($post['featured_image'], 0, 4) === 'http'): ?>
                                    <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="w-full h-full object-cover">
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <h4 class="text-gray-300 group-hover:text-purple-400 transition"><?php echo htmlspecialchars($post['title']); ?></h4>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Social Media -->
                <div class="card p-6 rounded-lg shadow-lg mb-8">
                    <h3 class="text-xl font-cinzel font-bold text-white mb-4">Suivez-nous</h3>
                    <div class="flex justify-center space-x-4">
                        <a href="#" class="w-10 h-10 flex items-center justify-center rounded-full bg-blue-900 text-white hover:bg-blue-800 transition"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="w-10 h-10 flex items-center justify-center rounded-full bg-pink-700 text-white hover:bg-pink-600 transition"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="w-10 h-10 flex items-center justify-center rounded-full bg-black text-white hover:bg-gray-900 transition"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>
                
                <!-- Newsletter -->
                <div class="card p-6 rounded-lg shadow-lg">
                    <h3 class="text-xl font-cinzel font-bold text-white mb-4">Newsletter</h3>
                    <p class="text-gray-400 mb-4">Abonnez-vous pour recevoir nos derniers articles et offres spéciales.</p>
                    <form action="subscribe.php" method="post">
                        <input type="email" name="email" placeholder="Votre email" class="w-full py-2 px-4 bg-gray-800 text-white rounded-lg mb-4 focus:outline-none">
                        <button type="submit" class="w-full button-magic py-2 rounded-lg text-white font-medium">
                            S'abonner
                        </button>
                    </form>
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
