<?php
/**
 * Rituals Page
 * 
 * This file displays the list of rituals with filtering and pagination.
 */

// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';

// Inclusion de la connexion à la base de données
require_once INCLUDES_PATH . '/db_connect.php';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 9; // Nombre de rituels par page
$offset = ($page - 1) * $per_page;

// Filtres
$category = isset($_GET['category']) ? $_GET['category'] : null;

// Construction de la requête avec vérification stricte de l'existence
$sql_count = "SELECT COUNT(*) FROM rituals WHERE status = 'published' AND id IS NOT NULL";
$sql = "SELECT id, title, slug, excerpt, content, category, duration, price, status, featured_image, youtube_url, created_at, updated_at, author FROM rituals WHERE status = 'published' AND id IS NOT NULL";

// Application des filtres
if ($category) {
    $sql .= " AND category = :category";
    $sql_count .= " AND category = :category";
}

// TRI SYNCHRONISÉ AVEC L'ADMIN : Exactement la même logique que admin/rituals.php
// Tri rigoureux : les rituels récemment mis à jour ou marqués comme récents apparaissent en premier
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

// Vérification de la connexion à la base de données
$rituals = [];
$total_rituals = 0;

// Vérifier si la connexion à la base de données est établie
if (!isset($pdo) || !$pdo) {
    // Afficher un message d'erreur si DEBUG_MODE est activé
    // Enregistrer l'erreur dans le journal
    } else {
    try {
        // Compte total pour pagination
        $stmt_count = $pdo->prepare($sql_count);
        if ($category) {
            $stmt_count->bindParam(':category', $category, PDO::PARAM_STR);
        }
        $stmt_count->execute();
        $total_rituals = $stmt_count->fetchColumn();
        
        // Récupération des rituels avec le nouveau tri optimisé
        $stmt = $pdo->prepare($sql);
        if ($category) {
            $stmt->bindParam(':category', $category, PDO::PARAM_STR);
        }
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
        $stmt->execute();
        $rituals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
                } catch (PDOException $e) {
        // Afficher un message d'erreur si DEBUG_MODE est activé
        // Enregistrer l'erreur dans le journal
        }
}

// Calcul du nombre total de pages
$total_pages = ceil($total_rituals / $per_page);

// Récupération des catégories pour le filtre avec tri alphabétique
$categories = [];
if (isset($pdo) && $pdo) {
    try {
        $stmt = $pdo->query("SELECT DISTINCT category FROM rituals WHERE status = 'published' AND category IS NOT NULL AND category != '' ORDER BY category ASC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categories[] = $row['category'];
        }
    } catch (PDOException $e) {
        }
}

// Titre de la page avec indication du nombre de rituels
$page_title = "Rituels et Services Magiques";
if ($total_rituals > 0) {
    $page_title .= " (" . $total_rituals . " rituels disponibles)";
}
$page_title .= " - Mystica Occulta";

// Fonction utilitaire pour formater les dates (optionnelle)
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'À l\'instant';
    if ($time < 3600) return floor($time/60) . ' min';
    if ($time < 86400) return floor($time/3600) . ' h';
    if ($time < 2592000) return floor($time/86400) . ' j';
    if ($time < 31536000) return floor($time/2592000) . ' mois';
    return floor($time/31536000) . ' ans';
}

// Fonction pour extraire l'ID YouTube et convertir en format embed
function convertToYouTubeEmbed($url) {
    $patterns = [
        '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/',
        '/youtu\.be\/([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/v\/([a-zA-Z0-9_-]+)/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1] . '?rel=0&modestbranding=1&showinfo=0';
        }
    }
    
    return $url;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <!-- Meta tags pour SEO -->
    <meta name="description" content="Découvrez notre collection de rituels magiques et services ésotériques pour l'amour, la prospérité, la protection et plus encore. Nouveaux rituels régulièrement ajoutés.">
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400;1,700;1,900&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Merriweather', serif;
            background: linear-gradient(135deg, #0f0e17 0%, #1a1a2e 50%, #16213e 100%);
            color: #fffffe;
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        .font-cinzel {
            font-family: 'Cinzel Decorative', cursive;
        }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, #7209b7 0%, #3a0ca3 50%, #240046 100%);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: headerShine 10s linear infinite;
        }
        
        @keyframes headerShine {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            position: relative;
            z-index: 2;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(45deg, #ff006e, #8338ec);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            animation: logoFloat 3s ease-in-out infinite;
        }
        
        @keyframes logoFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }
        
        .logo-text {
            font-size: 2rem;
            font-weight: 900;
            background: linear-gradient(45deg, #ff006e, #8338ec, #3a86ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .nav {
            display: flex;
            gap: 30px;
        }
        
        .nav a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
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
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .nav a:hover::before {
            left: 100%;
        }
        
        .nav a:hover,
        .nav a.active {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        /* Hero */
        .hero {
            background: radial-gradient(ellipse at center, #3a0ca3 0%, #0f0e17 70%);
            padding: 80px 0;
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
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="stars" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="0.5" fill="white" opacity="0.3"/></pattern></defs><rect width="100" height="100" fill="url(%23stars)"/></svg>');
            animation: starTwinkle 20s linear infinite;
        }
        
        @keyframes starTwinkle {
            0% { opacity: 0.3; }
            50% { opacity: 0.8; }
            100% { opacity: 0.3; }
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            background: linear-gradient(45deg, #ff006e, #8338ec, #3a86ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: textGlow 2s ease-in-out infinite alternate;
        }
        
        @keyframes textGlow {
            from { filter: drop-shadow(0 0 10px rgba(255,0,110,0.5)); }
            to { filter: drop-shadow(0 0 20px rgba(255,0,110,0.8)); }
        }
        
        .hero p {
            font-size: 1.2rem;
            color: #e0e0e0;
            max-width: 600px;
            margin: 0 auto;
            animation: fadeInUp 1s ease-out;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Stats info */
        .stats-info {
            background: rgba(26, 26, 46, 0.6);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin-top: 30px;
            display: inline-block;
        }
        
        .stats-info span {
            color: #8338ec;
            font-weight: bold;
        }
        
        /* Main Content */
        .main {
            padding: 60px 0;
        }
        
        /* Filter Section */
        .filter-section {
            margin-bottom: 60px;
            text-align: center;
        }
        
        .filter-title {
            font-size: 2rem;
            margin-bottom: 30px;
            color: #ff006e;
        }
        
        .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
        }
        
        .filter-btn {
            padding: 12px 25px;
            border: 2px solid #7209b7;
            border-radius: 25px;
            background: transparent;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            font-weight: 500;
        }
        
        .filter-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, #7209b7, #3a0ca3);
            transition: left 0.3s ease;
            z-index: -1;
        }
        
        .filter-btn:hover::before,
        .filter-btn.active::before {
            left: 0;
        }
        
        .filter-btn:hover,
        .filter-btn.active {
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(114, 9, 183, 0.4);
        }
        
        /* Rituals Grid */
        .rituals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 40px;
            margin-bottom: 60px;
        }
        
        .ritual-card {
            background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            transition: all 0.4s ease;
            position: relative;
            text-decoration: none;
            color: inherit;
            animation: fadeInUp 0.6s ease-out;
            display: flex;
            flex-direction: column;
            height: 600px; /* Hauteur fixe pour toutes les cartes */
        }
        
        .ritual-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
        }
        
        .ritual-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,0,110,0.1), rgba(131,56,236,0.1));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .ritual-card:hover::before {
            opacity: 1;
        }
        
        /* Badge pour nouveau rituel */
        .new-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(45deg, #ff006e, #ff4081);
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
            z-index: 3;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .ritual-image {
            position: relative;
            height: 250px;
            overflow: hidden;
        }
        
        .ritual-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        
        .ritual-image iframe {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
            border: none;
            border-radius: 0;
        }
        
        .ritual-card:hover .ritual-image img,
        .ritual-card:hover .ritual-image iframe {
            transform: scale(1.1);
        }
        
        .ritual-image .overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.7) 100%);
        }
        
        .ritual-image .default-bg {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #7209b7, #3a0ca3, #240046);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
        }
        
        .price-tag {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: linear-gradient(45deg, #ff006e, #8338ec);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .category-tag {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(26, 26, 46, 0.9);
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            backdrop-filter: blur(10px);
        }
        
        .ritual-content {
            padding: 25px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .ritual-content-body {
            flex: 1;
        }
        
        .ritual-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #ff006e;
        }
        
        .ritual-excerpt {
            color: #e0e0e0;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .ritual-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            color: #b0b0b0;
            font-size: 0.9rem;
        }
        
        .ritual-meta i {
            color: #8338ec;
        }
        
        .ritual-date {
            font-size: 0.8rem;
            color: #999;
        }
        
        .ritual-btn {
            background: linear-gradient(45deg, #7209b7, #3a0ca3);
            color: white;
            padding: 12px 0;
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .ritual-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .ritual-card:hover .ritual-btn::before {
            left: 100%;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            grid-column: 1 / -1;
        }
        
        .empty-state h2 {
            font-size: 2rem;
            color: #666;
            margin-bottom: 15px;
        }
        
        .empty-state p {
            color: #999;
            font-size: 1.1rem;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 60px 0;
        }
        
        .pagination a {
            padding: 12px 18px;
            border-radius: 10px;
            background: #1a1a2e;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .pagination a:hover {
            background: #7209b7;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(114, 9, 183, 0.4);
        }
        
        .pagination a.active {
            background: linear-gradient(45deg, #7209b7, #3a0ca3);
            border-color: #ff006e;
        }
        
        /* CTA Section */
        .cta-section {
            background: radial-gradient(ellipse at center, #3a0ca3 0%, #1a1a2e 70%);
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            margin: 80px 0;
            position: relative;
            overflow: hidden;
        }
        
        .cta-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(from 0deg, transparent, rgba(255,0,110,0.1), transparent, rgba(131,56,236,0.1), transparent);
            animation: ctaRotate 20s linear infinite;
        }
        
        @keyframes ctaRotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .cta-content {
            position: relative;
            z-index: 2;
        }
        
        .cta-title {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: #ff006e;
        }
        
        .cta-text {
            font-size: 1.1rem;
            color: #e0e0e0;
            margin-bottom: 30px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .cta-btn {
            display: inline-block;
            padding: 15px 40px;
            background: linear-gradient(45deg, #ff006e, #8338ec);
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(255,0,110,0.3);
        }
        
        .cta-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(255,0,110,0.5);
        }
        
        /* Footer */
        .footer {
            background: #0f0e17;
            padding: 60px 0 30px;
            border-top: 1px solid #333;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .footer-section h3 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: #ff006e;
        }
        
        .footer-section p {
            color: #b0b0b0;
            line-height: 1.6;
        }
        
        .footer-section ul {
            list-style: none;
        }
        
        .footer-section ul li {
            margin-bottom: 10px;
        }
        
        .footer-section ul li a {
            color: #b0b0b0;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer-section ul li a:hover {
            color: #8338ec;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-links a {
            width: 40px;
            height: 40px;
            background: linear-gradient(45deg, #7209b7, #3a0ca3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s ease;
        }
        
        .social-links a:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(114, 9, 183, 0.4);
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #333;
            color: #666;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
            }
            
            .nav {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .rituals-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .filter-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .pagination {
                flex-wrap: wrap;
            }
            
            .cta-section {
                padding: 40px 20px;
            }
            
            .cta-title {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 0 15px;
            }
            
            .hero {
                padding: 60px 0;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .rituals-grid {
                grid-template-columns: 1fr;
            }
            
            .ritual-card {
                min-width: unset;
            }
            
            .ritual-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-moon"></i>
                    </div>
                    <h1 class="logo-text font-cinzel">Mystica Occulta</h1>
                </div>
                
                <nav class="nav">
                    <a href="index.php">Accueil</a>
                    <a href="rituals.php" class="active">Rituels</a>
                    <a href="blog.php">Blog</a>
                    <a href="about.php">À propos</a>
                    <a href="contact.php">Contact</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1 class="font-cinzel">Rituels et Services Magiques</h1>
                <p>Explorez notre collection exclusive de rituels magiques, talismans et services ésotériques. 
                   Chaque rituel est soigneusement conçu pour vous accompagner dans votre quête spirituelle.</p>
                
                <?php if ($total_rituals > 0): ?>
                    <div class="stats-info">
                        <i class="fas fa-magic"></i>
                        <span><?php echo $total_rituals; ?></span> rituels disponibles
                        <?php if ($category): ?>
                            dans la catégorie <span><?php echo htmlspecialchars($category); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="main">
        <div class="container">
            
            <!-- Filter Section -->
            <?php if (!empty($categories)): ?>
            <section class="filter-section">
                <h2 class="filter-title font-cinzel">
                    <i class="fas fa-filter"></i>
                    Filtrer par Catégorie
                </h2>
                <div class="filter-buttons">
                    <a href="rituals.php" class="filter-btn <?php echo !$category ? 'active' : ''; ?>">
                        <i class="fas fa-th-large"></i>
                        Tous les Rituels
                    </a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="rituals.php?category=<?php echo urlencode($cat); ?>" 
                           class="filter-btn <?php echo $category === $cat ? 'active' : ''; ?>">
                            <i class="fas fa-star"></i>
                            <?php echo htmlspecialchars(ucfirst($cat)); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Rituals Grid -->
            <section class="rituals-section">
                <?php if (!empty($rituals)): ?>
                    <div class="rituals-grid">
                        <?php foreach ($rituals as $index => $ritual): ?>
                            <?php
                            // Vérifier si c'est un nouveau rituel (ajouté dans les 7 derniers jours)
                            $is_new = (time() - strtotime($ritual['created_at'])) < (7 * 24 * 60 * 60);
                            
                            // Vérifier si c'est récemment mis à jour (dans les 7 derniers jours)
                            $is_updated = $ritual['updated_at'] && $ritual['updated_at'] !== $ritual['created_at'] && 
                                         (time() - strtotime($ritual['updated_at'])) < (7 * 24 * 60 * 60);
                            
                            // URL du rituel - Format propre
                            $ritual_url = '/' . urlencode($ritual['slug']);
                            
                            // Image par défaut ou image personnalisée
                            $has_image = !empty($ritual['featured_image']);
                            
                            // Vérifier différents chemins possibles pour l'image
                            $image_path = '';
                            if ($has_image) {
                                $image_file = $ritual['featured_image'];
                                
                                // Si c'est déjà une URL complète
                                if (strpos($image_file, 'http') === 0) {
                                    $image_path = $image_file;
                                    $has_image = true;
                                }
                                // Si c'est un chemin relatif, essayer différents dossiers
                                elseif (file_exists('uploads/' . $image_file)) {
                                    $image_path = 'uploads/' . $image_file;
                                }
                                elseif (file_exists($image_file)) {
                                    $image_path = $image_file;
                                }
                                elseif (file_exists('admin/uploads/' . $image_file)) {
                                    $image_path = 'admin/uploads/' . $image_file;
                                }
                                else {
                                    $has_image = false;
                                }
                            }
                            ?>
                            
                            <article class="ritual-card" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                                <a href="<?php echo $ritual_url; ?>">
                                    
                                    <!-- Badge pour nouveau rituel ou mise à jour -->
                                    <?php if ($is_new): ?>
                                        <div class="new-badge">
                                            <i class="fas fa-sparkles"></i> Nouveau
                                        </div>
                                    <?php elseif ($is_updated): ?>
                                        <div class="new-badge" style="background: linear-gradient(45deg, #ff9500, #ffb347);">
                                            <i class="fas fa-sync"></i> Mis à jour
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Image ou Vidéo du rituel -->
                                    <div class="ritual-image">
                                        <?php if (!empty($ritual['youtube_url'])): ?>
                                            <?php
                                            $embed_url = convertToYouTubeEmbed($ritual['youtube_url']);
                                            ?>
                                            <iframe src="<?php echo htmlspecialchars($embed_url); ?>" 
                                                    frameborder="0" 
                                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                                    allowfullscreen 
                                                    loading="lazy"
                                                    style="width: 100%; height: 100%; object-fit: cover;"></iframe>
                                        <?php elseif ($has_image && !empty($image_path)): ?>
                                            <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                                 alt="<?php echo htmlspecialchars($ritual['title']); ?>" 
                                                 loading="lazy"
                                                 onerror="this.parentElement.innerHTML='<div class=\'default-bg\'><i class=\'fas fa-magic\'></i></div>';">
                                        <?php else: ?>
                                            <div class="default-bg">
                                                <i class="fas fa-magic"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="overlay"></div>
                                        
                                        <!-- Catégorie -->
                                        <?php if (!empty($ritual['category'])): ?>
                                            <div class="category-tag">
                                                <i class="fas fa-tag"></i>
                                                <?php echo htmlspecialchars(ucfirst($ritual['category'])); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Prix -->
                                        <?php if (!empty($ritual['price']) && $ritual['price'] > 0): ?>
                                            <div class="price-tag">
                                                <i class="fas fa-euro-sign"></i>
                                                <?php echo number_format($ritual['price'], 2); ?>€
                                            </div>
                                        <?php else: ?>
                                            <div class="price-tag" style="background: linear-gradient(45deg, #28a745, #20c997);">
                                                <i class="fas fa-gift"></i>
                                                Gratuit
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Contenu de la carte -->
                                    <div class="ritual-content">
                                        <div class="ritual-content-body">
                                            <h3 class="ritual-title"><?php echo htmlspecialchars($ritual['title']); ?></h3>
                                            
                                            <?php if (!empty($ritual['excerpt'])): ?>
                                                <p class="ritual-excerpt">
                                                    <?php 
                                                    $excerpt = strip_tags($ritual['excerpt']);
                                                    echo htmlspecialchars(mb_substr($excerpt, 0, 80)) . 
                                                         (mb_strlen($excerpt) > 80 ? '...' : ''); 
                                                    ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <!-- Métadonnées -->
                                            <div class="ritual-meta">
                                                <?php if (!empty($ritual['duration'])): ?>
                                                    <span>
                                                        <i class="fas fa-clock"></i>
                                                        <?php echo htmlspecialchars($ritual['duration']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Date de publication/mise à jour -->
                                            <div class="ritual-date">
                                                <?php if ($is_updated): ?>
                                                    <i class="fas fa-sync"></i>
                                                    Mis à jour <?php echo timeAgo($ritual['updated_at']); ?>
                                                <?php else: ?>
                                                    <i class="fas fa-calendar"></i>
                                                    Publié <?php echo timeAgo($ritual['created_at']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Bouton d'action -->
                                        <div class="ritual-btn">
                                            <i class="fas fa-eye"></i>
                                            Découvrir ce rituel
                                        </div>
                                    </div>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- État vide -->
                    <div class="empty-state">
                        <i class="fas fa-search" style="font-size: 4rem; color: #666; margin-bottom: 20px;"></i>
                        <h2>Aucun rituel trouvé</h2>
                        <p>
                            <?php if ($category): ?>
                                Aucun rituel n'a été trouvé dans la catégorie "<?php echo htmlspecialchars($category); ?>".
                                <br><a href="rituals.php" style="color: #8338ec;">Voir tous les rituels</a>
                            <?php else: ?>
                                Notre collection de rituels sera bientôt disponible.
                                <br>Revenez nous voir prochainement !
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="rituals.php?page=<?php echo $page - 1; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?>">
                            <i class="fas fa-chevron-left"></i>
                            Précédent
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    // Logique de pagination intelligente
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1): ?>
                        <a href="rituals.php?page=1<?php echo $category ? '&category=' . urlencode($category) : ''; ?>">1</a>
                        <?php if ($start_page > 2): ?>
                            <span style="color: #666; padding: 12px;">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="rituals.php?page=<?php echo $i; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?>" 
                           class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <span style="color: #666; padding: 12px;">...</span>
                        <?php endif; ?>
                        <a href="rituals.php?page=<?php echo $total_pages; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?>"><?php echo $total_pages; ?></a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="rituals.php?page=<?php echo $page + 1; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?>">
                            Suivant
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

            <!-- Section CTA -->
            <section class="cta-section">
                <div class="cta-content">
                    <h2 class="cta-title font-cinzel">Besoin d'un Rituel Personnalisé ?</h2>
                    <p class="cta-text">
                        Nos experts en arts occultes peuvent créer un rituel sur mesure selon vos besoins spécifiques. 
                        Contactez-nous pour une consultation personnalisée et découvrez le pouvoir de la magie adaptée à votre situation.
                    </p>
                    <a href="contact.php" class="cta-btn">
                        <i class="fas fa-envelope"></i>
                        Demander une Consultation
                    </a>
                </div>
            </section>

        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3 class="font-cinzel">Mystica Occulta</h3>
                    <p>Votre guide dans l'univers des arts occultes et des pratiques magiques. 
                       Découvrez la sagesse ancestrale adaptée au monde moderne.</p>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h3>Navigation</h3>
                    <ul>
                        <li><a href="index.php">Accueil</a></li>
                        <li><a href="rituals.php">Rituels</a></li>
                        <li><a href="blog.php">Blog</a></li>
                        <li><a href="about.php">À propos</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Services</h3>
                    <ul>
                        <li><a href="rituals.php?category=amour">Rituels d'Amour</a></li>
                        <li><a href="rituals.php?category=protection">Protection</a></li>
                        <li><a href="rituals.php?category=prosperite">Prospérité</a></li>
                        <li><a href="rituals.php?category=purification">Purification</a></li>
                        <li><a href="contact.php">Consultation</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Informations</h3>
                    <ul>
                        <li><a href="privacy.php">Politique de Confidentialité</a></li>
                        <li><a href="terms.php">Conditions d'Utilisation</a></li>
                        <li><a href="faq.php">FAQ</a></li>
                        <li><a href="contact.php">Support</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Mystica Occulta. Tous droits réservés. 
                   Créé avec passion pour les arts mystiques.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Animation d'apparition progressive des cartes
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.ritual-card');
            
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
                observer.observe(card);
            });
        });

        // Smooth scroll pour les liens d'ancrage
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Effet de parallaxe subtil sur le header
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const header = document.querySelector('.header');
            if (header) {
                header.style.transform = `translateY(${scrolled * 0.5}px)`;
            }
        });
    </script>
</body>
</html>
