<?php
// Affichage des erreurs en mode développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusion de la connexion à la base de données
require_once 'admin/includes/db_connect.php';

// Vérification du slug de l'article
$slug = '';

// Récupérer l'URL requête
$request_uri = $_SERVER['REQUEST_URI'];

// Méthode 1: Vérifier si le slug est dans le chemin (format URL propre /blog/titre-article)
$pattern = '/\/blog\/([^\/\?]+)/i';
if (preg_match($pattern, $request_uri, $matches)) {
    $slug = urldecode($matches[1]);
}
// Méthode 2: Vérifier si le slug est passé dans l'URL sous forme de paramètre GET (ancien format)
elseif (isset($_GET['slug']) && !empty($_GET['slug'])) {
    $slug = $_GET['slug'];
    
    // Si on arrive via le paramètre GET, on redirige vers l'URL propre
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: /blog/' . urlencode($slug));
    exit;
}
// Méthode 3: Format alternatif /blog-post.php/titre-article
else {
    $pattern = '/\/blog-post\.php\/([^\/\?]+)/i';
    if (preg_match($pattern, $request_uri, $matches)) {
        $slug = urldecode($matches[1]);
        
        // Rediriger vers l'URL propre
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: /blog/' . urlencode($slug));
        exit;
    }
}

// Vérifier si un slug a été trouvé
if (empty($slug)) {
    header('Location: /blog.php');
    exit;
}

// Récupération de l'article par slug
try {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE slug = ? AND status = 'published'");
    $stmt->execute([$slug]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        // Article non trouvé ou non publié
        header('Location: /blog.php');
        exit;
    }
    
    // Mise à jour du compteur de vues
    $stmt = $pdo->prepare("UPDATE blog_posts SET views = views + 1 WHERE id = ?");
    $stmt->execute([$post['id']]);
    
    // Récupération des articles similaires (même catégorie)
    $similar_posts = [];
    if (!empty($post['category'])) {
        $stmt = $pdo->prepare("SELECT id, title, slug, featured_image FROM blog_posts 
                              WHERE category = ? AND id != ? AND status = 'published' 
                              ORDER BY created_at DESC LIMIT 3");
        $stmt->execute([$post['category'], $post['id']]);
        $similar_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Récupération des articles populaires pour la sidebar
    $popular_posts = [];
    $stmt = $pdo->query("SELECT id, title, slug, featured_image FROM blog_posts WHERE status = 'published' ORDER BY views DESC LIMIT 3");
    $popular_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupération des catégories pour la sidebar
    $categories = [];
    $stmt = $pdo->query("SELECT * FROM blog_categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération de l'article: " . $e->getMessage());
    header('Location: /blog.php');
    exit;
}

// Titre de la page
$page_title = isset($post['title']) ? $post['title'] . " - Mystica Occulta" : "Article - Mystica Occulta";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <!-- Meta tags pour SEO -->
    <meta name="description" content="<?php echo isset($post['meta_description']) && !empty($post['meta_description']) ? htmlspecialchars($post['meta_description']) : htmlspecialchars(substr(strip_tags($post['excerpt'] ?: $post['content']), 0, 160)); ?>">
    <?php if (isset($post['meta_keywords']) && !empty($post['meta_keywords'])): ?>
    <meta name="keywords" content="<?php echo htmlspecialchars($post['meta_keywords']); ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?php echo isset($post['title']) ? htmlspecialchars($post['title']) : ''; ?>">
    <meta property="og:description" content="<?php echo isset($post['meta_description']) && !empty($post['meta_description']) ? htmlspecialchars($post['meta_description']) : htmlspecialchars(substr(strip_tags($post['excerpt'] ?: $post['content']), 0, 160)); ?>">
    <?php if (isset($post['featured_image']) && !empty($post['featured_image'])): ?>
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
                    <a href="/index.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Accueil</a>
                    <a href="/rituals.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Rituels</a>
                    <a href="/blog.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300 border-b-2 border-pink-500">Blog</a>
                    <a href="/about.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">À propos</a>
                    <a href="/contact.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Contact</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <div class="flex flex-col lg:flex-row">
            <!-- Article Content -->
            <div class="lg:w-3/4 lg:pr-8">
                <!-- Breadcrumbs -->
                <div class="mb-6 text-gray-400">
                    <a href="/index.php" class="hover:text-pink-400 transition">Accueil</a> &raquo; 
                    <a href="/blog.php" class="hover:text-pink-400 transition">Blog</a> 
                    <?php if (isset($post['category']) && !empty($post['category'])): ?>
                    &raquo; <a href="/blog.php?category=<?php echo urlencode($post['category']); ?>" class="hover:text-pink-400 transition"><?php echo htmlspecialchars($post['category']); ?></a>
                    <?php endif; ?>
                    &raquo; <span class="text-purple-400"><?php echo isset($post['title']) ? htmlspecialchars($post['title']) : ''; ?></span>
                </div>

                <!-- Article Header -->
                <div class="bg-gradient-to-r from-purple-900 to-indigo-900 rounded-lg overflow-hidden shadow-xl mb-8">
                    <?php if (isset($post['featured_image']) && !empty($post['featured_image'])): ?>
                    <div class="relative">
                        <div class="aspect-w-16 aspect-h-9 md:aspect-h-7 lg:aspect-h-5 rounded-t-lg overflow-hidden">
                            <?php if (substr($post['featured_image'], 0, 4) === 'http'): ?>
                                <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo isset($post['title']) ? htmlspecialchars($post['title']) : ''; ?>" class="w-full h-full object-cover transition-transform duration-1000 hover:scale-105">
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo isset($post['title']) ? htmlspecialchars($post['title']) : ''; ?>" class="w-full h-full object-cover transition-transform duration-1000 hover:scale-105">
                            <?php endif; ?>
                            <div class="absolute inset-0 bg-gradient-to-t from-black via-black/60 to-transparent"></div>
                        </div>
                        <div class="absolute bottom-0 left-0 right-0 p-6 md:p-8 lg:p-10">
                            <h1 class="text-3xl sm:text-4xl md:text-5xl font-cinzel font-bold text-white mb-4 drop-shadow-lg"><?php echo isset($post['title']) ? htmlspecialchars($post['title']) : ''; ?></h1>
                            
                            <div class="flex flex-wrap items-center space-y-2 md:space-y-0 text-gray-200 text-sm">
                                <?php if (isset($post['author']) && !empty($post['author'])): ?>
                                <span class="inline-flex items-center px-3 py-1 mr-2 mb-2 bg-purple-900/70 backdrop-blur-sm rounded-full">
                                    <i class="fas fa-user text-purple-300 mr-2"></i><?php echo htmlspecialchars($post['author']); ?>
                                </span>
                                <?php endif; ?>
                                
                                <span class="inline-flex items-center px-3 py-1 mr-2 mb-2 bg-purple-900/70 backdrop-blur-sm rounded-full">
                                    <i class="far fa-calendar text-purple-300 mr-2"></i><?php echo date('d/m/Y', strtotime($post['created_at'])); ?>
                                </span>
                                
                                <?php if (isset($post['category']) && !empty($post['category'])): ?>
                                <a href="blog.php?category=<?php echo urlencode($post['category']); ?>" class="inline-flex items-center px-3 py-1 mr-2 mb-2 bg-purple-900/70 backdrop-blur-sm rounded-full hover:bg-purple-800 transition">
                                    <i class="fas fa-tag text-purple-300 mr-2"></i><?php echo htmlspecialchars($post['category']); ?>
                                </a>
                                <?php endif; ?>
                                
                                <span class="inline-flex items-center px-3 py-1 mb-2 bg-purple-900/70 backdrop-blur-sm rounded-full">
                                    <i class="far fa-eye text-purple-300 mr-2"></i><?php echo isset($post['views']) ? $post['views'] : '0'; ?> vues
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="p-8">
                        <h1 class="text-4xl md:text-5xl font-cinzel font-bold text-white mb-4"><?php echo isset($post['title']) ? htmlspecialchars($post['title']) : ''; ?></h1>
                        
                        <div class="flex flex-wrap items-center text-gray-300 text-sm">
                            <?php if (isset($post['author']) && !empty($post['author'])): ?>
                            <span class="inline-block mr-6"><i class="fas fa-user mr-2"></i><?php echo htmlspecialchars($post['author']); ?></span>
                            <?php endif; ?>
                            
                            <span class="inline-block mr-6"><i class="far fa-calendar mr-2"></i><?php echo date('d/m/Y', strtotime($post['created_at'])); ?></span>
                            
                            <?php if (isset($post['category']) && !empty($post['category'])): ?>
                            <a href="blog.php?category=<?php echo urlencode($post['category']); ?>" class="inline-block mr-6 hover:text-purple-300 transition">
                                <i class="fas fa-tag mr-2"></i><?php echo htmlspecialchars($post['category']); ?>
                            </a>
                            <?php endif; ?>
                            
                            <span class="inline-block"><i class="far fa-eye mr-2"></i><?php echo isset($post['views']) ? $post['views'] : '0'; ?> vues</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($post['excerpt']) && !empty($post['excerpt'])): ?>
                <div class="bg-gray-900 bg-opacity-60 rounded-lg p-6 mb-8 text-lg text-gray-300 italic">
                    <?php echo nl2br(htmlspecialchars($post['excerpt'])); ?>
                </div>
                <?php endif; ?>

                <!-- Article Content -->
                <div class="bg-gray-900 bg-opacity-60 rounded-lg overflow-hidden shadow-xl p-6 md:p-8 mb-12">
                    <div class="content-area prose prose-lg prose-invert max-w-none text-gray-200">
                        <?php echo isset($post['content']) ? $post['content'] : ''; ?>
                    </div>
                </div>
                
                <?php if (isset($post['tags']) && !empty($post['tags'])): ?>
                <div class="mb-12">
                    <div class="flex items-center mb-6">
                        <div class="w-1 h-8 bg-purple-500 rounded mr-3"></div>
                        <h3 class="text-xl font-bold text-white">Tags</h3>
                    </div>
                    <div class="flex flex-wrap">
                        <?php 
                        $tags = explode(',', $post['tags']);
                        foreach ($tags as $tag): 
                            $tag = trim($tag);
                            if (!empty($tag)):
                        ?>
                        <a href="blog.php?tag=<?php echo urlencode($tag); ?>" class="bg-indigo-900 bg-opacity-60 text-white px-4 py-2 rounded-full text-sm mr-2 mb-2 hover:bg-indigo-800 transition-all transform hover:-translate-y-1 hover:shadow-lg border border-indigo-700">
                            <i class="fas fa-hashtag text-xs opacity-70 mr-1"></i> <?php echo htmlspecialchars($tag); ?>
                        </a>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Share Buttons -->
                <div class="mb-12 bg-gray-900 bg-opacity-60 rounded-lg p-6 md:p-8">
                    <div class="flex items-center mb-6">
                        <div class="w-1 h-8 bg-purple-500 rounded mr-3"></div>
                        <h3 class="text-xl font-bold text-white">Partager cet article</h3>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="flex items-center px-6 py-3 rounded-lg bg-[#1877F2] text-white hover:bg-opacity-90 transition-all hover:shadow-lg">
                            <i class="fab fa-facebook-f mr-3"></i> <span class="font-medium">Facebook</span>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode($post['title']); ?>" target="_blank" class="flex items-center px-6 py-3 rounded-lg bg-[#1DA1F2] text-white hover:bg-opacity-90 transition-all hover:shadow-lg">
                            <i class="fab fa-twitter mr-3"></i> <span class="font-medium">Twitter</span>
                        </a>
                        <a href="https://wa.me/?text=<?php echo urlencode($post['title'] . ' - https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="flex items-center px-6 py-3 rounded-lg bg-[#25D366] text-white hover:bg-opacity-90 transition-all hover:shadow-lg">
                            <i class="fab fa-whatsapp mr-3"></i> <span class="font-medium">WhatsApp</span>
                        </a>
                        <a href="mailto:?subject=<?php echo urlencode($post['title']); ?>&body=<?php echo urlencode('J\'ai trouvé cet article intéressant et je voulais le partager avec toi: ' . $post['title'] . ' - https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" class="flex items-center px-6 py-3 rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition-all hover:shadow-lg">
                            <i class="fas fa-envelope mr-3"></i> <span class="font-medium">Email</span>
                        </a>
                    </div>
                </div>

                <!-- Similar Articles -->
                <?php if (!empty($similar_posts)): ?>
                <div class="mb-12">
                    <h2 class="text-3xl font-cinzel font-bold text-white mb-6">Articles similaires</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php foreach ($similar_posts as $similar): ?>
                        <a href="/blog/<?php echo urlencode($similar['slug']); ?>" class="card rounded-xl overflow-hidden shadow-lg hover:shadow-xl transition duration-300">
                            <div class="relative h-48">
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
                            <div class="p-6">
                                <h3 class="text-xl font-bold text-white mb-2"><?php echo htmlspecialchars($similar['title']); ?></h3>
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
                        <?php foreach ($popular_posts as $popular): ?>
                        <a href="/blog/<?php echo urlencode($popular['slug']); ?>" class="flex items-center group">
                            <?php if (!empty($popular['featured_image'])): ?>
                            <div class="w-16 h-16 rounded-lg overflow-hidden mr-4 flex-shrink-0">
                                <?php if (substr($popular['featured_image'], 0, 4) === 'http'): ?>
                                    <img src="<?php echo htmlspecialchars($popular['featured_image']); ?>" alt="<?php echo htmlspecialchars($popular['title']); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($popular['featured_image']); ?>" alt="<?php echo htmlspecialchars($popular['title']); ?>" class="w-full h-full object-cover">
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <h4 class="text-gray-300 group-hover:text-purple-400 transition"><?php echo htmlspecialchars($popular['title']); ?></h4>
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
                        <li><a href="/index.php" class="text-gray-400 hover:text-purple-400 transition">Accueil</a></li>
                        <li><a href="/rituals.php" class="text-gray-400 hover:text-purple-400 transition">Rituels</a></li>
                        <li><a href="/blog.php" class="text-gray-400 hover:text-purple-400 transition">Blog</a></li>
                        <li><a href="/about.php" class="text-gray-400 hover:text-purple-400 transition">À propos</a></li>
                        <li><a href="/contact.php" class="text-gray-400 hover:text-purple-400 transition">Contact</a></li>
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
