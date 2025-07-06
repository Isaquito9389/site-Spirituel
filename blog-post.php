<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';

// Affichage des erreurs en mode développement
// Gestionnaire d'erreur personnalisé pour éviter les erreurs 500
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (error_reporting() === 0) {
        return false;
    }
    $error_message = "Error [$errno] $errstr - $errfile:$errline";
    echo "<div style=\"padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin-bottom: 20px;\">\n<h3>Une erreur est survenue</h3>\n<p>Nous avons rencontré un problème lors du traitement de votre demande. Veuillez réessayer plus tard ou contacter l'administrateur.</p>\n</div>";
    return true;
}, E_ALL);

// Inclusion de la connexion à la base de données
require_once 'includes/db_connect.php';

// Include backlink functions if they exist
if (file_exists('admin/includes/backlink_functions.php')) {
    require_once 'admin/includes/backlink_functions.php';
}

// Vérifier si un slug d'article est fourni
$slug = '';
$use_slug = false;

// Méthode 1: Paramètre GET slug - ne rediriger que si on n'est pas déjà dans le contexte d'une URL ultra-propre
if (isset($_GET['slug']) && !empty($_GET['slug'])) {
    $slug = $_GET['slug'];
    
    // Vérifier si on vient de check_slug_type.php (URL ultra-propre)
    $is_from_clean_url = (strpos($_SERVER['REQUEST_URI'], 'blog-post.php') === false && 
                         strpos($_SERVER['REQUEST_URI'], '?slug=') === false);
    
    // Rediriger seulement si on accède directement à blog-post.php?slug=xxx
    if (!$is_from_clean_url && strpos($_SERVER['REQUEST_URI'], 'blog-post.php') !== false) {
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: /" . urlencode($slug));
        exit;
    }
    $use_slug = true;
}
// Méthode 2: Vérifier dans l'URL (formats propres)
else {
    $request_uri = $_SERVER['REQUEST_URI'];
    
    // Format /blog/nom-de-l-article - rediriger vers l'URL ultra-propre
    $pattern = '/\/blog\/([^\/\?]+)/i';
    if (preg_match($pattern, $request_uri, $matches)) {
        $slug = $matches[1];
        
        // Redirection 301 vers l'URL ultra-propre (priorité)
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: /" . urlencode($slug));
        exit;
    }
}

// Si aucun slug n'est trouvé, rediriger
if (empty($slug)) {
    header('Location: blog.php');
    exit;
}

$post = null;
$related_posts = [];

try {
    // Récupérer l'article par son slug
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE slug = :slug AND status = 'published'");
    $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        // Article non trouvé
        header('Location: blog.php');
        exit;
    }
    
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Incrémenter le nombre de vues
    $stmt = $pdo->prepare("UPDATE blog_posts SET views = views + 1 WHERE id = :id");
    $stmt->bindParam(':id', $post['id'], PDO::PARAM_INT);
    $stmt->execute();
    
    // Récupérer les articles associés (même catégorie)
    if (!empty($post['category'])) {
        $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE category = :category AND id != :id AND status = 'published' ORDER BY created_at DESC LIMIT 3");
        $stmt->bindParam(':category', $post['category'], PDO::PARAM_STR);
        $stmt->bindParam(':id', $post['id'], PDO::PARAM_INT);
        $stmt->execute();
        $related_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Rediriger en cas d'erreur
    header('Location: blog.php');
    exit;
}

// Titre de la page
$page_title = htmlspecialchars($post['title']) . " - Blog Mystica Occulta";

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
    
    return 'image';
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
    
    <!-- Meta tags pour SEO -->
    <meta name="description" content="<?php echo htmlspecialchars(substr(strip_tags($post['content'] ?? $post['excerpt'] ?? ''), 0, 160)); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($post['title']); ?>, <?php echo htmlspecialchars($post['category'] ?? ''); ?>, blog ésotérique, spiritualité, magie, rituels, mystica occulta">
    <meta name="author" content="Mystica Occulta">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://www.maitrespirituel.com/<?php echo $post['slug']; ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="article">
    <meta property="og:url" content="https://www.maitrespirituel.com/<?php echo $post['slug']; ?>">
    <meta property="og:title" content="<?php echo $page_title; ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(substr(strip_tags($post['content'] ?? $post['excerpt'] ?? ''), 0, 160)); ?>">
    <meta property="og:image" content="<?php echo !empty($post['featured_image']) ? htmlspecialchars($post['featured_image']) : 'https://www.maitrespirituel.com/assets/images/og-image-default.jpg'; ?>">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://www.maitrespirituel.com/<?php echo $post['slug']; ?>">
    <meta property="twitter:title" content="<?php echo $page_title; ?>">
    <meta property="twitter:description" content="<?php echo htmlspecialchars(substr(strip_tags($post['content'] ?? $post['excerpt'] ?? ''), 0, 160)); ?>">
    <meta property="twitter:image" content="<?php echo !empty($post['featured_image']) ? htmlspecialchars($post['featured_image']) : 'https://www.maitrespirituel.com/assets/images/og-image-default.jpg'; ?>">
    
    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    
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
            line-height: 1.8;
            overflow-x: hidden;
        }
        
        .font-cinzel {
            font-family: 'Cinzel Decorative', cursive;
        }
        
        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            cursor: pointer;
            font-weight: 500;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .back-button:hover {
            background: rgba(255,255,255,1);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .article-header {
            text-align: center;
            margin-bottom: 3rem;
            padding-top: 4rem;
        }
        
        .breadcrumbs {
            margin-bottom: 2rem;
            color: var(--text-gray);
        }
        
        .breadcrumbs a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .breadcrumbs a:hover {
            color: var(--accent-color);
        }
        
        .category-badge {
            display: inline-block;
            background: var(--gradient-primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .article-title {
            font-size: clamp(2rem, 4vw, 3rem);
            margin-bottom: 1rem;
            background: linear-gradient(45deg, var(--text-white), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .article-meta {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 2rem;
            color: var(--text-gray);
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .article-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .article-meta i {
            color: var(--primary-color);
        }
        
        .featured-media {
            margin-bottom: 3rem;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
        }
        
        .featured-media img,
        .featured-media video {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .article-content {
            background: var(--gradient-card);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: var(--shadow-card);
            margin-bottom: 3rem;
        }
        
        .article-content h1,
        .article-content h2,
        .article-content h3,
        .article-content h4,
        .article-content h5,
        .article-content h6 {
            color: var(--text-white);
            margin: 2rem 0 1rem 0;
        }
        
        .article-content p {
            margin-bottom: 1.5rem;
            color: var(--text-gray);
        }
        
        .article-content ul,
        .article-content ol {
            margin-bottom: 1.5rem;
            padding-left: 2rem;
            color: var(--text-gray);
        }
        
        .article-content blockquote {
            border-left: 4px solid var(--primary-color);
            padding-left: 2rem;
            margin: 2rem 0;
            font-style: italic;
            color: var(--text-gray);
        }
        
        .article-content a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .article-content a:hover {
            color: var(--accent-color);
        }
        
        .related-posts {
            margin-top: 4rem;
        }
        
        .related-title {
            text-align: center;
            font-size: 2rem;
            margin-bottom: 2rem;
            color: var(--text-white);
        }
        
        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .related-card {
            background: var(--gradient-card);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        
        .related-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-hover);
        }
        
        .related-image {
            height: 200px;
            overflow: hidden;
            background: var(--gradient-primary);
        }
        
        .related-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .related-card:hover .related-image img {
            transform: scale(1.1);
        }
        
        .related-content {
            padding: 1.5rem;
        }
        
        .related-content h3 {
            color: var(--text-white);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .related-content p {
            color: var(--text-gray);
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .article-content {
                padding: 2rem;
            }
            
            .article-meta {
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <a href="blog.php" class="back-button">← Retour au blog</a>
    
    <div class="container">
        <article>
            <header class="article-header">
                <div class="breadcrumbs">
                    <a href="index.php">Accueil</a> 
                    <span> > </span> 
                    <a href="blog.php">Blog</a>
                    <?php if (!empty($post['category'])): ?>
                        <span> > </span> 
                        <a href="blog.php?category=<?php echo urlencode($post['category']); ?>"><?php echo htmlspecialchars($post['category']); ?></a>
                    <?php endif; ?>
                    <span> > </span> 
                    <span><?php echo htmlspecialchars($post['title']); ?></span>
                </div>
                
                <?php if (!empty($post['category'])): ?>
                    <div class="category-badge"><?php echo htmlspecialchars($post['category']); ?></div>
                <?php endif; ?>
                
                <h1 class="article-title font-cinzel"><?php echo htmlspecialchars($post['title']); ?></h1>
                
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
                    <?php if (!empty($post['author'])): ?>
                    <span>
                        <i class="far fa-user"></i>
                        <?php echo htmlspecialchars($post['author']); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </header>
            
            <?php if (!empty($post['featured_image'])): ?>
            <div class="featured-media">
                <?php 
                $media_type = getMediaType($post['featured_image']);
                
                if ($media_type === 'youtube'): 
                    $youtube_id = getYouTubeId($post['featured_image']);
                ?>
                    <iframe width="100%" height="400" src="https://www.youtube.com/embed/<?php echo $youtube_id; ?>" frameborder="0" allowfullscreen></iframe>
                <?php elseif ($media_type === 'vimeo'): 
                    $vimeo_id = getVimeoId($post['featured_image']);
                ?>
                    <iframe width="100%" height="400" src="https://player.vimeo.com/video/<?php echo $vimeo_id; ?>" frameborder="0" allowfullscreen></iframe>
                <?php elseif ($media_type === 'video'): ?>
                    <video controls width="100%">
                        <source src="<?php echo htmlspecialchars($post['featured_image']); ?>" type="video/mp4">
                        Votre navigateur ne supporte pas la lecture de vidéos.
                    </video>
                <?php else: ?>
                    <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="article-content">
                <?php echo $post['content'] ?? $post['excerpt'] ?? ''; ?>
                
                <!-- Backlinks Section -->
                <?php if (function_exists('get_backlinks')): ?>
                    <?php $backlinks = get_backlinks('blog', $post['id']); ?>
                    <?php if (!empty($backlinks)): ?>
                        <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid rgba(114, 9, 183, 0.3);">
                            <h3 style="font-family: 'Cinzel Decorative', cursive; color: var(--accent-color); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-external-link-alt"></i>
                                Références et Sources
                            </h3>
                            <div style="display: grid; gap: 1rem;">
                                <?php foreach ($backlinks as $backlink): ?>
                                    <?php $formatted = format_backlink_display($backlink); ?>
                                    <div style="background: rgba(26, 26, 46, 0.5); border-radius: 10px; padding: 1.5rem; border: 1px solid rgba(114, 9, 183, 0.3); transition: all 0.3s ease;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                            <span style="background-color: <?php echo $formatted['color']; ?>20; color: <?php echo $formatted['color']; ?>; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.8rem; font-weight: 600;">
                                                <?php echo $formatted['type_label']; ?>
                                            </span>
                                            <span style="color: var(--text-muted); font-size: 0.9rem;"><?php echo $formatted['domain']; ?></span>
                                        </div>
                                        <h4 style="margin: 0 0 0.5rem 0; font-size: 1.1rem; font-weight: 600;">
                                            <a href="<?php echo htmlspecialchars($formatted['url']); ?>" target="_blank" rel="noopener" style="color: var(--text-white); text-decoration: none; transition: color 0.3s ease;">
                                                <?php echo htmlspecialchars($formatted['title']); ?>
                                                <i class="fas fa-external-link-alt" style="font-size: 0.8rem; margin-left: 0.5rem;"></i>
                                            </a>
                                        </h4>
                                        <?php if (!empty($formatted['description'])): ?>
                                            <p style="color: var(--text-gray); font-size: 0.95rem; line-height: 1.5; margin: 0;"><?php echo htmlspecialchars($formatted['description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </article>
        
        <?php if (!empty($related_posts)): ?>
        <section class="related-posts">
            <h2 class="related-title font-cinzel">Articles similaires</h2>
            
            <div class="related-grid">
                <?php foreach ($related_posts as $related): ?>
                <a href="/<?php echo urlencode($related['slug']); ?>" class="related-card">
                    <div class="related-image">
                        <?php if (!empty($related['featured_image'])): ?>
                            <img src="<?php echo htmlspecialchars($related['featured_image']); ?>" alt="<?php echo htmlspecialchars($related['title']); ?>">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-scroll" style="font-size: 3rem; opacity: 0.5;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="related-content">
                        <h3><?php echo htmlspecialchars($related['title']); ?></h3>
                        <?php if (!empty($related['excerpt'])): ?>
                        <p><?php echo htmlspecialchars(substr($related['excerpt'], 0, 100)) . (strlen($related['excerpt']) > 100 ? '...' : ''); ?></p>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</body>
</html>
