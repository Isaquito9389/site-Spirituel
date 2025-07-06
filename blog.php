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

// TRI SYNCHRONISÉ AVEC L'ADMIN : Exactement la même logique que admin/blog.php
// Tri rigoureux : les articles récemment mis à jour ou marqués comme récents apparaissent en premier
$sql .= " ORDER BY 
    CASE 
        WHEN updated_at > created_at THEN updated_at 
        ELSE created_at 
    END DESC, 
    updated_at DESC, 
    created_at DESC, 
    id DESC";

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

// Fonction pour détecter le type de média
function getMediaType($url) {
    if (empty($url)) return 'none';
    
    $video_extensions = ['mp4', 'webm', 'ogg', 'avi', 'mov'];
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    
    $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    
    if (in_array($extension, $video_extensions)) return 'video';
    if (in_array($extension, $image_extensions)) return 'image';
    
    // Détection par URL (YouTube, Vimeo, etc.)
    if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) return 'youtube';
    if (strpos($url, 'vimeo.com') !== false) return 'vimeo';
    
    return 'image'; // Par défaut
}

// Fonction pour extraire l'ID YouTube
function getYouTubeId($url) {
    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
    return isset($matches[1]) ? $matches[1] : false;
}

// Fonction pour extraire l'ID Vimeo
function getVimeoId($url) {
    preg_match('/vimeo\.com\/(\d+)/', $url, $matches);
    return isset($matches[1]) ? $matches[1] : false;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <meta name="description" content="Explorez notre blog sur l'ésotérisme, la spiritualité et les pratiques magiques. Découvrez des articles fascinants sur les rituels, la divination et plus encore.">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400;1,700;1,900&display=swap');
        
        :root {
            --primary-color: #7209b7;
            --secondary-color: #3a0ca3;
            --accent-color: #f72585;
            --bg-dark: #0f0e17;
            --bg-card: #1a1a2e;
            --bg-card-hover: #16213e;
            --text-white: #fffffe;
            --text-gray: #a7a9be;
            --text-muted: #6c757d;
            --gradient-primary: linear-gradient(135deg, #7209b7 0%, #3a0ca3 100%);
            --gradient-card: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
            --shadow-card: 0 8px 32px rgba(0, 0, 0, 0.3);
            --shadow-hover: 0 16px 48px rgba(114, 9, 183, 0.2);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Merriweather', serif;
            background: var(--bg-dark);
            color: var(--text-white);
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        .font-cinzel {
            font-family: 'Cinzel Decorative', cursive;
        }
        
        /* Header */
        .header {
            background: var(--gradient-primary);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logo-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }
        
        .logo h1 {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .nav {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }
        
        .nav a {
            color: var(--text-white);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .nav a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transition: left 0.3s ease;
        }
        
        .nav a:hover::before,
        .nav a.active::before {
            left: 0;
        }
        
        .nav a:hover,
        .nav a.active {
            color: var(--accent-color);
            transform: translateY(-2px);
        }
        
        /* Hero Section */
        .hero {
            background: radial-gradient(ellipse at center, var(--secondary-color) 0%, var(--bg-dark) 70%);
            padding: 6rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="stars" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23stars)"/></svg>');
            animation: twinkle 10s infinite;
        }
        
        @keyframes twinkle {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.8; }
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .hero h1 {
            font-size: clamp(2.5rem, 5vw, 4rem);
            margin-bottom: 1.5rem;
            background: linear-gradient(45deg, var(--text-white), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero p {
            font-size: 1.2rem;
            color: var(--text-gray);
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 4rem 2rem;
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 3rem;
        }
        
        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
                padding: 2rem 1rem;
            }
        }
        
        /* Filter Navigation */
        .filter-nav {
            margin-bottom: 2rem;
        }
        
        .filter-nav a {
            color: var(--primary-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .filter-nav a:hover {
            background: rgba(114, 9, 183, 0.1);
            transform: translateX(-5px);
        }
        
        .filter-title {
            font-size: 2rem;
            margin-top: 1rem;
            color: var(--text-white);
        }
        
        /* Articles Grid */
        .articles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        @media (max-width: 768px) {
            .articles-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Article Card */
        .article-card {
            background: var(--gradient-card);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            text-decoration: none;
            color: inherit;
            transform: translateY(0);
        }
        
        .article-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-hover);
        }
        
        .article-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, rgba(114, 9, 183, 0.1));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .article-card:hover::before {
            opacity: 1;
        }
        
        /* Media Container */
        .media-container {
            position: relative;
            height: 250px;
            overflow: hidden;
            background: var(--gradient-primary);
        }
        
        .media-container img,
        .media-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        
        .article-card:hover .media-container img,
        .article-card:hover .media-container video {
            transform: scale(1.1);
        }
        
        .media-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 50%;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
            pointer-events: none;
        }
        
        .category-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: rgba(114, 9, 183, 0.9);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
            z-index: 2;
        }
        
        .play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary-color);
            transition: all 0.3s ease;
            z-index: 2;
        }
        
        .article-card:hover .play-button {
            transform: translate(-50%, -50%) scale(1.1);
            background: white;
        }
        
        /* Article Content */
        .article-content {
            padding: 1.5rem;
            position: relative;
            z-index: 2;
        }
        
        .article-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-white);
            line-height: 1.4;
        }
        
        .article-excerpt {
            color: var(--text-gray);
            margin-bottom: 1rem;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .article-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .article-meta i {
            color: var(--primary-color);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--gradient-card);
            border-radius: 20px;
            box-shadow: var(--shadow-card);
        }
        
        .empty-state h3 {
            font-size: 2rem;
            color: var(--text-gray);
            margin-bottom: 1rem;
        }
        
        .empty-state p {
            color: var(--text-muted);
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 3rem;
            flex-wrap: wrap;
        }
        
        .pagination a {
            padding: 0.75rem 1rem;
            background: var(--bg-card);
            color: var(--text-white);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            min-width: 45px;
            text-align: center;
        }
        
        .pagination a:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .pagination a.active {
            background: var(--primary-color);
        }
        
        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .sidebar-card {
            background: var(--gradient-card);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-card);
            transition: all 0.3s ease;
        }
        
        .sidebar-card:hover {
            transform: translateY(-5px);
        }
        
        .sidebar-card h3 {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            color: var(--text-white);
        }
        
        /* Search Form */
        .search-form {
            display: flex;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .search-form input {
            flex: 1;
            padding: 0.75rem 1rem;
            background: var(--bg-dark);
            border: none;
            color: var(--text-white);
            outline: none;
        }
        
        .search-form input::placeholder {
            color: var(--text-muted);
        }
        
        .search-form button {
            padding: 0.75rem 1rem;
            background: var(--primary-color);
            border: none;
            color: white;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .search-form button:hover {
            background: var(--secondary-color);
        }
        
        /* Categories List */
        .categories-list {
            list-style: none;
        }
        
        .categories-list li {
            margin-bottom: 0.5rem;
        }
        
        .categories-list a {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            color: var(--text-gray);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .categories-list a:hover {
            color: var(--primary-color);
        }
        
        .category-count {
            background: var(--bg-dark);
            color: var(--text-muted);
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
            font-size: 0.8rem;
        }
        
        /* Popular Posts */
        .popular-post {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
        }
        
        .popular-post:hover {
            transform: translateX(5px);
        }
        
        .popular-post img {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            object-fit: cover;
        }
        
        .popular-post-content h4 {
            color: var(--text-gray);
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .popular-post:hover h4 {
            color: var(--primary-color);
        }
        
        /* Social Media */
        .social-links {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        
        .social-link {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .social-link.facebook { background: #1877f2; }
        .social-link.instagram { background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888); }
        .social-link.tiktok { background: #000; }
        
        .social-link:hover {
            transform: translateY(-3px) scale(1.1);
        }
        
        /* Newsletter */
        .newsletter-form input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--bg-dark);
            border: none;
            border-radius: 10px;
            color: var(--text-white);
            margin-bottom: 1rem;
            outline: none;
        }
        
        .newsletter-form input::placeholder {
            color: var(--text-muted);
        }
        
        .newsletter-form button {
            width: 100%;
            padding: 0.75rem;
            background: var(--gradient-primary);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .newsletter-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(114, 9, 183, 0.4);
        }
        
        /* Footer */
        .footer {
            background: var(--bg-card);
            padding: 3rem 2rem 1rem;
            margin-top: 4rem;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .footer-section h3 {
            margin-bottom: 1rem;
            color: var(--text-white);
        }
        
        .footer-section p,
        .footer-section a {
            color: var(--text-gray);
            text-decoration: none;
            line-height: 1.8;
        }
        
        .footer-section a:hover {
            color: var(--primary-color);
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 2rem;
            padding-top: 2rem;
            text-align: center;
            color: var(--text-muted);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav {
                gap: 1rem;
            }
            
            .hero {
                padding: 4rem 1rem;
            }
            
            .sidebar {
                order: -1;
            }
        }
        
        /* Animation d'entrée */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .article-card {
            animation: fadeInUp 0.6s ease forwards;
        }
        
        .article-card:nth-child(1) { animation-delay: 0.1s; }
        .article-card:nth-child(2) { animation-delay: 0.2s; }
        .article-card:nth-child(3) { animation-delay: 0.3s; }
        .article-card:nth-child(4) { animation-delay: 0.4s; }
        .article-card:nth-child(5) { animation-delay: 0.5s; }
        .article-card:nth-child(6) { animation-delay: 0.6s; }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-moon"></i>
                </div>
                <h1 class="font-cinzel">Mystica Occulta</h1>
            </div>
            
            <nav class="nav">
                <a href="index.php">Accueil</a>
                <a href="rituals.php">Rituels</a>
                <a href="blog.php" class="active">Blog</a>
                <a href="about.php">À propos</a>
                <a href="contact.php">Contact</a>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1 class="font-cinzel">Blog Mystica Occulta</h1>
            <p>Explorez notre blog pour découvrir des articles fascinants sur l'ésotérisme, la spiritualité et les pratiques magiques ancestrales.</p>
        </div>
    </section>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Blog Posts -->
        <div class="blog-content">
            <?php if ($category): ?>
                <div class="filter-nav">
                    <a href="blog.php">
                        <i class="fas fa-arrow-left"></i>
                        Retour à tous les articles
                    </a>
                    <h2 class="filter-title font-cinzel">Catégorie : <?php echo htmlspecialchars($category); ?></h2>
                </div>
            <?php endif; ?>
            
            <?php if ($tag): ?>
                <div class="filter-nav">
                    <a href="blog.php">
                        <i class="fas fa-arrow-left"></i>
                        Retour à tous les articles
                    </a>
                    <h2 class="filter-title font-cinzel">Articles avec le tag : <?php echo htmlspecialchars($tag); ?></h2>
                </div>
            <?php endif; ?>
            
            <?php if (empty($posts)): ?>
                <div class="empty-state">
                    <h3 class="font-cinzel">Aucun article trouvé</h3>
                    <p>Aucun article n'est disponible pour le moment dans cette catégorie.</p>
                </div>
            <?php else: ?>
                <div class="articles-grid">
                    <?php foreach ($posts as $post): ?>
                    <a href="/<?php echo urlencode($post['slug'] ?? ''); ?>" class="article-card">
                        <div class="media-container">
                            <?php 
                            $media_type = getMediaType($post['featured_image'] ?? '');
                            
                            if ($media_type === 'youtube'): 
                                $youtube_id = getYouTubeId($post['featured_image']);
                            ?>
                                <img src="https://img.youtube.com/vi/<?php echo $youtube_id; ?>/maxresdefault.jpg" alt="<?php echo htmlspecialchars($post['title']); ?>">
                                <div class="play-button">
                                    <i class="fab fa-youtube"></i>
                                </div>
                            <?php elseif ($media_type === 'vimeo'): 
                                $vimeo_id = getVimeoId($post['featured_image']);
                            ?>
                                <img src="https://vumbnail.com/<?php echo $vimeo_id; ?>.jpg" alt="<?php echo htmlspecialchars($post['title']); ?>">
                                <div class="play-button">
                                    <i class="fab fa-vimeo"></i>
                                </div>
                            <?php elseif ($media_type === 'video'): ?>
                                <video muted loop>
                                    <source src="<?php echo htmlspecialchars($post['featured_image']); ?>" type="video/mp4">
                                </video>
                                <div class="play-button">
                                    <i class="fas fa-play"></i>
                                </div>
                            <?php elseif ($media_type === 'image' && !empty($post['featured_image'])): ?>
                                <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                            <?php else: ?>
                                <div style="background: var(--gradient-primary); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-scroll" style="font-size: 3rem; opacity: 0.5;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="media-overlay"></div>
                            
                            <?php if (!empty($post['category'])): ?>
                            <div class="category-badge">
                                <?php echo htmlspecialchars($post['category']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="article-content">
                            <h3 class="article-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                            
                            <?php if (!empty($post['excerpt'])): ?>
                            <p class="article-excerpt"><?php echo htmlspecialchars(substr($post['excerpt'], 0, 150)) . (strlen($post['excerpt']) > 150 ? '...' : ''); ?></p>
                            <?php endif; ?>
                            
                            <div class="article-meta">
                                <span>
                                    <i class="far fa-calendar"></i>
                                    <?php echo date('d/m/Y', strtotime($post['created_at'])); ?>
                                </span>
                                <?php if (isset($post['views'])): ?>
                                <span>
                                    <i class="far fa-eye"></i>
                                    <?php echo $post['views']; ?> vues
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="blog.php?page=<?php echo $page-1; ?><?php echo $category ? '&category='.urlencode($category) : ''; ?><?php echo $tag ? '&tag='.urlencode($tag) : ''; ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="blog.php?page=<?php echo $i; ?><?php echo $category ? '&category='.urlencode($category) : ''; ?><?php echo $tag ? '&tag='.urlencode($tag) : ''; ?>" 
                   class="<?php echo $i === $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="blog.php?page=<?php echo $page+1; ?><?php echo $category ? '&category='.urlencode($category) : ''; ?><?php echo $tag ? '&tag='.urlencode($tag) : ''; ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <aside class="sidebar">
            <!-- Search -->
            <div class="sidebar-card">
                <h3 class="font-cinzel">Rechercher</h3>
                <form action="search.php" method="get" class="search-form">
                    <input type="text" name="query" placeholder="Rechercher un article...">
                    <button type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            
            <!-- Categories -->
            <?php if (!empty($categories)): ?>
            <div class="sidebar-card">
                <h3 class="font-cinzel">Catégories</h3>
                <ul class="categories-list">
                    <?php foreach ($categories as $cat): ?>
                    <li>
                        <a href="blog.php?category=<?php echo urlencode($cat['name']); ?>">
                            <span><?php echo htmlspecialchars($cat['name']); ?></span>
                            <?php 
                            try {
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE category = ? AND status = 'published'");
                                $stmt->execute([$cat['name']]);
                                $count = $stmt->fetchColumn();
                                echo "<span class='category-count'>$count</span>";
                            } catch (PDOException $e) {
                                echo "<span class='category-count'>0</span>";
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
            <div class="sidebar-card">
                <h3 class="font-cinzel">Articles Populaires</h3>
                <div>
                    <?php foreach ($popular_posts as $post): ?>
                    <a href="/<?php echo urlencode($post['slug'] ?? ''); ?>" class="popular-post">
                        <?php if (!empty($post['featured_image'])): ?>
                        <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                        <?php else: ?>
                        <div style="width: 60px; height: 60px; background: var(--gradient-primary); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-scroll" style="opacity: 0.7;"></i>
                        </div>
                        <?php endif; ?>
                        <div class="popular-post-content">
                            <h4><?php echo htmlspecialchars($post['title']); ?></h4>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Social Media -->
            <div class="sidebar-card">
                <h3 class="font-cinzel">Suivez-nous</h3>
                <div class="social-links">
                    <a href="#" class="social-link facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="social-link instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="social-link tiktok">
                        <i class="fab fa-tiktok"></i>
                    </a>
                </div>
            </div>
            
            <!-- Newsletter -->
            <div class="sidebar-card">
                <h3 class="font-cinzel">Newsletter</h3>
                <p style="color: var(--text-gray); margin-bottom: 1rem;">Abonnez-vous pour recevoir nos derniers articles et offres spéciales.</p>
                <form action="subscribe.php" method="post" class="newsletter-form">
                    <input type="email" name="email" placeholder="Votre adresse email..." required>
                    <button type="submit">
                        <i class="fas fa-paper-plane"></i>
                        S'abonner
                    </button>
                </form>
            </div>
        </aside>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3 class="font-cinzel">Mystica Occulta</h3>
                <p>Votre portail vers le monde de l'ésotérisme, de la magie et des rituels ancestraux. Découvrez les secrets de l'univers mystique.</p>
            </div>
            
            <div class="footer-section">
                <h3 class="font-cinzel">Navigation</h3>
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <a href="index.php">Accueil</a>
                    <a href="rituals.php">Rituels</a>
                    <a href="blog.php">Blog</a>
                    <a href="about.php">À propos</a>
                    <a href="contact.php">Contact</a>
                </div>
            </div>
            
            <div class="footer-section">
                <h3 class="font-cinzel">Contact</h3>
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-envelope" style="color: var(--primary-color);"></i>
                        <span>contact@mysticaocculta.com</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fab fa-whatsapp" style="color: var(--primary-color);"></i>
                        <span>+33 XX XX XX XX</span>
                    </div>
                </div>
                
                <div class="social-links" style="justify-content: flex-start; margin-top: 1rem;">
                    <a href="#" class="social-link facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="social-link instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="social-link tiktok">
                        <i class="fab fa-tiktok"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Mystica Occulta. Tous droits réservés.</p>
        </div>
    </footer>

    <script>
        // Animation pour les vidéos au survol
        document.addEventListener('DOMContentLoaded', function() {
            const videoCards = document.querySelectorAll('.article-card video');
            
            videoCards.forEach(video => {
                const card = video.closest('.article-card');
                
                card.addEventListener('mouseenter', () => {
                    video.play();
                });
                
                card.addEventListener('mouseleave', () => {
                    video.pause();
                });
            });
            
            // Effet de parallaxe pour le hero
            window.addEventListener('scroll', () => {
                const scrolled = window.pageYOffset;
                const hero = document.querySelector('.hero');
                if (hero) {
                    hero.style.transform = `translateY(${scrolled * 0.5}px)`;
                }
            });
        });
    </script>
</body>
</html>
