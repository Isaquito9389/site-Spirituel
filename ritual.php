<?php
// Affichage des erreurs en mode développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Gestionnaire d'erreur personnalisé pour éviter les erreurs 500
set_error_handler(function(
    $errno, $errstr, $errfile, $errline
) {
    if (error_reporting() === 0) {
        return false;
    }
    $error_message = "Error [$errno] $errstr - $errfile:$errline";
    error_log($error_message);
    echo "<div style=\"padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin-bottom: 20px;\">\n<h3>Une erreur est survenue</h3>\n<p>Nous avons rencontré un problème lors du traitement de votre demande. Veuillez réessayer plus tard ou contacter l'administrateur.</p>\n</div>";
    return true;
}, E_ALL);

// Inclusion de la connexion à la base de données
require_once 'admin/includes/db_connect.php';

// Gestion des anciennes URL avec ID (redirection vers le format avec slug)
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT slug FROM rituals WHERE id = ? AND status = 'published'");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['slug'])) {
            // Redirection 301 (permanente) vers la nouvelle URL avec slug
            header("HTTP/1.1 301 Moved Permanently");
            header("Location: ritual.php?slug=" . urlencode($result['slug']));
            exit;
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la redirection ID vers slug: " . $e->getMessage());
    }
}

// Vérification du slug du rituel
$slug = '';

// Méthode 1: Vérifier si le slug est passé dans l'URL sous forme de paramètre GET (ancien format)
if (isset($_GET['slug']) && !empty($_GET['slug'])) {
    $slug = $_GET['slug'];
}
// Méthode 2: Vérifier si le slug est dans le chemin (nouveau format /ritual/titre-article)
else {
    // Obtenir le chemin de l'URL requête
    $request_uri = $_SERVER['REQUEST_URI'];
    // Extraire le slug du chemin
    $pattern = '/\/ritual\/([^\/\?]+)/i';
    if (preg_match($pattern, $request_uri, $matches)) {
        $slug = $matches[1];
    } else {
        // Format alternatif /ritual.php/titre-rituel
        $pattern = '/\/ritual\.php\/([^\/\?]+)/i';
        if (preg_match($pattern, $request_uri, $matches)) {
            $slug = $matches[1];
        }
    }
}

// Vérifier si un slug a été trouvé
if (empty($slug)) {
    header('Location: rituals.php');
    exit;
}
// Requête pour récupérer le rituel publié par slug
$stmt = $pdo->prepare("SELECT * FROM rituals WHERE slug = ? AND status = 'published'");
$stmt->execute([$slug]);
$ritual = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ritual) {
    // Rituel non trouvé ou non publié
    header('Location: rituals.php');
    exit;
}

// (Vérification de l'id supprimée, on utilise maintenant le slug)

// Le rituel a déjà été récupéré par slug au début du fichier

// Récupération des rituels similaires (même catégorie)
$similar_rituals = [];
if (!empty($ritual['category'])) {
    try {
        $stmt = $pdo->prepare("SELECT id, title, slug, featured_image, price FROM rituals 
                              WHERE category = ? AND id != ? AND status = 'published' 
                              ORDER BY created_at DESC LIMIT 3");
        $stmt->execute([$ritual['category'], $ritual['id']]);
        $similar_rituals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Silencieux - ce n'est pas critique
    }
}

// Titre de la page
$page_title = $ritual['title'] . " - Mystica Occulta";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <!-- Meta tags pour SEO -->
    <meta name="description" content="<?php echo htmlspecialchars(substr(strip_tags($ritual['excerpt'] ?: $ritual['content']), 0, 160)); ?>">
    <meta property="og:title" content="<?php echo isset($ritual['title']) ? htmlspecialchars($ritual['title']) : ''; ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(substr(strip_tags($ritual['excerpt'] ?: $ritual['content']), 0, 160)); ?>">
    <?php if (isset($ritual['featured_image']) && !empty($ritual['featured_image'])): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($ritual['featured_image']); ?>">
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
        
        /* Animation de particules en arrière-plan */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(1px 1px at 40px 60px, rgba(255, 255, 255, 0.15), rgba(0, 0, 0, 0)),
                radial-gradient(1px 1px at 20px 50px, rgba(255, 255, 255, 0.1), rgba(0, 0, 0, 0)),
                radial-gradient(2px 2px at 30px 100px, rgba(255, 255, 255, 0.15), rgba(0, 0, 0, 0)),
                radial-gradient(2px 2px at 40px 60px, rgba(255, 255, 255, 0.1), rgba(0, 0, 0, 0)),
                radial-gradient(2px 2px at 90px 40px, rgba(255, 255, 255, 0.12), rgba(0, 0, 0, 0)),
                radial-gradient(2px 2px at 130px 80px, rgba(255, 255, 255, 0.11), rgba(0, 0, 0, 0));
            background-size: 200px 200px;
            animation: particles-animation 60s linear infinite;
            pointer-events: none;
            z-index: -1;
            opacity: 0.3;
        }
        
        @keyframes particles-animation {
            0% { background-position: 0 0; }
            100% { background-position: 200px 200px; }
        }
        
        .font-cinzel {
            font-family: 'Cinzel Decorative', cursive;
        }
        
        /* Dégradé de texte pour les titres */
        .text-gradient {
            background: linear-gradient(to right, #f72585, #7209b7, #3a0ca3);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            animation: gradient-shift 8s ease infinite;
            background-size: 200% auto;
        }
        
        @keyframes gradient-shift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .bg-mystic {
            background: radial-gradient(circle at center, #3a0ca3 0%, #1a1a2e 70%);
            position: relative;
            overflow: hidden;
        }
        
        /* Cercles lumineux en arrière-plan */
        .bg-mystic::before {
            content: '';
            position: absolute;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(114, 9, 183, 0.4) 0%, rgba(58, 12, 163, 0) 70%);
            top: -50px;
            right: -50px;
            animation: glow-pulse 8s ease-in-out infinite alternate;
        }
        
        .bg-mystic::after {
            content: '';
            position: absolute;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(247, 37, 133, 0.3) 0%, rgba(58, 12, 163, 0) 70%);
            bottom: -30px;
            left: 10%;
            animation: glow-pulse 6s ease-in-out infinite alternate-reverse;
        }
        
        @keyframes glow-pulse {
            0% { opacity: 0.3; transform: scale(1); }
            100% { opacity: 0.7; transform: scale(1.3); }
        }
        
        .button-magic {
            background: linear-gradient(45deg, #7209b7, #3a0ca3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        /* Effet de brillance sur les boutons */
        .button-magic::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                to right,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, 0.3) 50%,
                rgba(255, 255, 255, 0) 100%
            );
            transform: rotate(30deg);
            animation: shine-effect 6s ease-in-out infinite;
            opacity: 0;
        }
        
        .button-magic:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(114, 9, 183, 0.6);
        }
        
        .button-magic:hover::before {
            opacity: 1;
        }
        
        @keyframes shine-effect {
            0% { left: -100%; opacity: 0; }
            10% { left: -100%; opacity: 0.5; }
            20% { left: 100%; opacity: 0.5; }
            30% { left: 100%; opacity: 0; }
            100% { left: 100%; opacity: 0; }
        }
        
        /* Séparateur stylisé */
        .mystic-divider {
            height: 3px;
            background: linear-gradient(to right, rgba(114, 9, 183, 0), rgba(114, 9, 183, 0.7), rgba(114, 9, 183, 0));
            margin: 2rem 0;
            position: relative;
        }
        
        .mystic-divider::before {
            content: '✧';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #0f0e17;
            padding: 0 15px;
            color: #7209b7;
            font-size: 1.2rem;
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
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        .content-area h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(to right, #f72585, rgba(247, 37, 133, 0));
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
            position: relative;
        }
        
        .content-area ul li::before {
            content: '✦';
            color: #7209b7;
            position: absolute;
            left: -1.2rem;
        }
        
        /* Amélioration du style des images avec effet de brillance */
        .content-area img {
            max-width: 100%;
            height: auto;
            border-radius: 0.5rem;
            margin: 1.5rem auto;
            display: block;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(122, 9, 183, 0.3);
            transition: transform 0.5s ease, box-shadow 0.5s ease;
            position: relative;
            z-index: 1;
        }
        
        .content-area img:hover {
            transform: scale(1.03) translateY(-5px);
            box-shadow: 0 15px 30px rgba(122, 9, 183, 0.5);
            filter: brightness(1.05);
        }
        
        /* Effet de brillance sur les images */
        .ritual-image {
            position: relative;
            overflow: hidden;
        }
        
        .ritual-image::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(
                to right,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, 0.2) 50%,
                rgba(255, 255, 255, 0) 100%
            );
            transform: skewX(-25deg);
            animation: image-shine 6s ease-in-out infinite;
        }
        
        @keyframes image-shine {
            0% { left: -100%; }
            20% { left: 100%; }
            100% { left: 100%; }
        }
        
        /* Style pour les vidéos avec ratio d'aspect amélioré */
        .content-area iframe,
        .content-area .ql-video {
            width: 100%;
            max-width: 700px;
            aspect-ratio: 16/9;
            height: auto;
            margin: 2rem auto;
            display: block;
            border-radius: 0.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(122, 9, 183, 0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .content-area iframe:hover,
        .content-area .ql-video:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 30px rgba(122, 9, 183, 0.5);
        }
        
        /* Conteneur pour les médias avec légende et bordure animée */
        .media-container {
            margin: 2rem 0;
            background: rgba(26, 26, 46, 0.7);
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(122, 9, 183, 0.2);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .media-container:hover {
            box-shadow: 0 8px 30px rgba(122, 9, 183, 0.4);
            border-color: rgba(122, 9, 183, 0.5);
        }
        
        /* Bordure animée pour le conteneur de médias */
        .media-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 0.75rem;
            padding: 2px;
            background: linear-gradient(
                45deg,
                rgba(114, 9, 183, 0.3),
                rgba(58, 12, 163, 0.3),
                rgba(247, 37, 133, 0.3),
                rgba(114, 9, 183, 0.3)
            );
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            background-size: 300% 300%;
            animation: border-animation 8s linear infinite;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .media-container:hover::before {
            opacity: 1;
        }
        
        @keyframes border-animation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .media-container img,
        .media-container iframe,
        .media-container .ql-video {
            margin: 0 auto 1rem auto;
            transition: transform 0.5s ease;
        }
        
        .media-container:hover img,
        .media-container:hover iframe,
        .media-container:hover .ql-video {
            transform: scale(1.01);
        }
        
        .media-caption {
            text-align: center;
            font-style: italic;
            color: #d8d8d8;
            font-size: 0.95rem;
            position: relative;
            padding-top: 0.5rem;
        }
        
        .media-caption::before {
            content: '';
            position: absolute;
            top: 0;
            left: 30%;
            right: 30%;
            height: 1px;
            background: linear-gradient(to right, rgba(114, 9, 183, 0), rgba(114, 9, 183, 0.5), rgba(114, 9, 183, 0));
        }
        
        /* Cartes avec effet 3D léger */
        .card {
            background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
            transition: all 0.5s ease;
            transform-style: preserve-3d;
            perspective: 1000px;
        }
        
        .card:hover {
            transform: translateY(-8px) rotateX(5deg);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4), 
                        0 5px 15px rgba(114, 9, 183, 0.3);
        }
        
        /* Effet de pulsation pour les éléments importants */
        .pulse-effect {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        /* Optimisations pour mobile */
        @media (max-width: 640px) {
            .media-container {
                padding: 1rem;
            }
            
            .content-area h2 {
                font-size: 1.5rem;
            }
            
            .content-area h3 {
                font-size: 1.3rem;
            }
            
            .button-magic, 
            .rounded-full {
                width: 100%;
                justify-content: center;
                margin-right: 0;
                margin-bottom: 1rem;
            }
        }
        
        /* Lazy loading pour les images */
        .lazy-load {
            opacity: 0;
            transition: opacity 0.5s ease;
        }
        
        .lazy-load.loaded {
            opacity: 1;
        }
        
        /* Étapes numérotées pour les rituels */
        .ritual-step {
            position: relative;
            padding-left: 3rem;
            margin-bottom: 2rem;
            counter-increment: ritual-steps;
        }
        
        .ritual-step::before {
            content: counter(ritual-steps);
            position: absolute;
            left: 0;
            top: 0;
            width: 2.5rem;
            height: 2.5rem;
            background: linear-gradient(135deg, #7209b7, #3a0ca3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
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
                    <a href="/blog.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Blog</a>
                    <a href="/about.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">À propos</a>
                    <a href="/contact.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Contact</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <div class="max-w-5xl mx-auto">
            <!-- Breadcrumbs -->
            <div class="mb-6 text-gray-400">
                <a href="/index.php" class="hover:text-pink-400 transition">Accueil</a> &raquo; 
                <a href="/rituals.php" class="hover:text-pink-400 transition">Rituels</a> &raquo; 
                <span class="text-purple-400"><?php echo isset($ritual['title']) ? htmlspecialchars($ritual['title']) : ''; ?></span>
            </div>
            
            <!-- Particules scintillantes en arrière-plan -->
            <div class="fixed inset-0 pointer-events-none z-0" aria-hidden="true">
                <div class="particles-js" id="particles-js"></div>
            </div>

            <!-- Ritual Header -->
            <div class="bg-gradient-to-r from-purple-900 to-indigo-900 rounded-lg overflow-hidden shadow-xl mb-8">
                <div class="p-6 md:p-12">
                    <div class="flex flex-col lg:flex-row">
                        <?php if (isset($ritual['featured_image']) && !empty($ritual['featured_image'])): ?>
                        <div class="lg:w-2/5 mb-6 lg:mb-0 lg:mr-8">
                            <div class="relative rounded-lg overflow-hidden shadow-lg aspect-w-16 aspect-h-9 md:aspect-w-4 md:aspect-h-3">
                                <?php 
                                // Déterminer la source de l'image selon qu'il s'agit d'une URL externe ou d'un fichier local
                                $image_src = substr($ritual['featured_image'], 0, 4) === 'http' 
                                    ? $ritual['featured_image'] 
                                    : $ritual['featured_image']; // Déjà relatif à la racine
                                ?>
                                <img src="<?php echo htmlspecialchars($image_src); ?>" alt="<?php echo isset($ritual['title']) ? htmlspecialchars($ritual['title']) : ''; ?>" class="w-full h-full object-cover transition-transform duration-700 hover:scale-105">
                                <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent opacity-50"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($ritual['youtube_url']) && !empty($ritual['youtube_url'])): 
                            // Extraire l'ID de la vidéo YouTube
                            $youtube_id = '';
                            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|\/watch\?(?:.*&)?v=|\/embed\/|\/v\/)|youtu\.be\/)([^\?&\"\'<>\#\/\s]+)/', $ritual['youtube_url'], $matches)) {
                                $youtube_id = $matches[1];
                            }
                            if (!empty($youtube_id)): ?>
                        <div class="md:w-full <?php echo !empty($ritual['featured_image']) ? 'mt-6' : ''; ?> mb-6">
                            <div class="aspect-w-16 aspect-h-9 rounded-lg overflow-hidden shadow-lg">
                                <iframe src="https://www.youtube.com/embed/<?php echo htmlspecialchars($youtube_id); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen class="w-full h-full"></iframe>
                            </div>
                        </div>
                        <?php endif; endif; ?>
                        
                        <div class="<?php echo !empty($ritual['featured_image']) ? 'md:w-2/3' : 'w-full'; ?>">
                            <h1 class="text-4xl md:text-5xl font-cinzel font-bold text-gradient mb-4"><?php echo isset($ritual['title']) ? htmlspecialchars($ritual['title']) : ''; ?></h1>
                            
                            <?php if (isset($ritual['excerpt']) && !empty($ritual['excerpt'])): ?>
                            <div class="text-lg text-gray-300 mb-6">
                                <?php echo isset($ritual['excerpt']) ? nl2br(htmlspecialchars($ritual['excerpt'])) : ''; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex flex-wrap mb-6 space-y-3 md:space-y-0">
                                <?php if (isset($ritual['category']) && !empty($ritual['category'])): ?>
                                <div class="bg-gray-800 bg-opacity-50 rounded-full px-4 py-2 mr-3 mb-3 flex items-center">
                                    <i class="fas fa-tag text-purple-400 mr-2"></i>
                                    <span class="text-gray-300">Catégorie:</span>
                                    <span class="text-purple-300 ml-1 font-medium"><?php echo isset($ritual['category']) ? htmlspecialchars($ritual['category']) : ''; ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($ritual['duration']) && !empty($ritual['duration'])): ?>
                                <div class="bg-gray-800 bg-opacity-50 rounded-full px-4 py-2 mr-3 mb-3 flex items-center">
                                    <i class="fas fa-clock text-purple-400 mr-2"></i>
                                    <span class="text-gray-300">Durée:</span>
                                    <span class="text-purple-300 ml-1 font-medium"><?php echo isset($ritual['duration']) ? htmlspecialchars($ritual['duration']) : ''; ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($ritual['price']) && !empty($ritual['price'])): ?>
                                <div class="bg-gray-800 bg-opacity-50 rounded-full px-4 py-2 mb-3 flex items-center">
                                    <i class="fas fa-coins text-purple-400 mr-2"></i>
                                    <span class="text-gray-300">Prix:</span>
                                    <span class="text-pink-300 font-bold ml-1"><?php echo isset($ritual['price']) ? htmlspecialchars($ritual['price']) : ''; ?> €</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex flex-wrap mt-6">
                                <a href="contact.php?ritual=<?php echo urlencode($ritual['slug']); ?>" class="button-magic px-6 py-3 rounded-full text-white font-medium shadow-lg mr-4 mb-3 flex items-center justify-center transition-all hover:translate-y-[-2px] hover:shadow-xl min-w-[180px] pulse-effect">
                                    <i class="fas fa-envelope mr-2 text-pink-300"></i>Demander ce rituel
                                </a>
                                <a href="https://wa.me/?text=<?php echo isset($ritual['title']) ? urlencode('Je suis intéressé(e) par votre rituel: ' . $ritual['title']) : ''; ?>" target="_blank" class="px-6 py-3 rounded-full bg-green-600 text-white font-medium shadow-lg hover:bg-green-700 hover:translate-y-[-2px] hover:shadow-xl transition-all mb-3 flex items-center justify-center min-w-[180px]">
                                    <i class="fab fa-whatsapp mr-2"></i>WhatsApp
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Séparateur mystique -->
            <div class="mystic-divider mb-8"></div>
            
            <!-- Ritual Content -->
            <div class="bg-gradient-to-br from-gray-900 to-indigo-900 bg-opacity-90 rounded-lg overflow-hidden shadow-2xl p-6 md:p-8 mb-12 border border-purple-900 ritual-container">
                <div class="content-area prose prose-lg prose-invert max-w-none text-gray-200">
                    <?php 
                    // Traitement du contenu pour améliorer l'affichage des médias
                    $content = isset($ritual['content']) ? $ritual['content'] : '';
                    
                    // 1. Amélioration des vidéos YouTube
                    $content = preg_replace_callback('/<iframe[^>]*src=["\'](.*?)["\'].*?><\/iframe>/s', function($matches) {
                        $src = $matches[1];
                        return '<div class="media-container"><iframe src="' . $src . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe><div class="media-caption">Vidéo en rapport avec le rituel</div></div>';
                    }, $content);
                    
                    // 2. Amélioration des images (sans remplacer celles qui sont déjà dans un conteneur)
                    $content = preg_replace_callback('/<img(?![^>]*class=["\']*media-container["\']*)[^>]*src=["\'](.*?)["\'].*?>/s', function($matches) {
                        $src = $matches[1];
                        $alt = '';
                        if (preg_match('/alt=["\'](.*?)["\']/s', $matches[0], $alt_matches)) {
                            $alt = $alt_matches[1];
                        }
                        return '<div class="media-container"><img src="' . $src . '" alt="' . $alt . '" class="ritual-image">' . 
                               '<div class="media-caption">' . ($alt ? $alt : 'Image illustrant le rituel') . '</div></div>';
                    }, $content);
                    
                    // 3. Traitement des éléments Quill Video
                    $content = preg_replace_callback('/<div class="ql-video".*?>.*?<iframe.*?src=["\'](.*?)["\'].*?><\/iframe>.*?<\/div>/s', function($matches) {
                        $src = $matches[1];
                        return '<div class="media-container"><div class="ql-video"><iframe src="' . $src . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div><div class="media-caption">Vidéo en rapport avec le rituel</div></div>';
                    }, $content);
                    
                    echo $content;
                    ?>
                </div>
            </div>

            <!-- Séparateur mystique -->
            <div class="mystic-divider mb-8"></div>
            
            <!-- Similar Rituals -->
            <?php if (!empty($similar_rituals)): ?>
            <div class="mb-16">
                <div class="flex items-center mb-8">
                    <div class="w-1 h-8 bg-purple-500 rounded mr-3"></div>
                    <h2 class="text-3xl font-cinzel font-bold text-gradient">Rituels similaires</h2>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($similar_rituals as $similar): ?>
                    <a href="ritual.php?slug=<?php echo urlencode($similar['slug']); ?>" class="card rounded-xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2 group">
                        <div class="relative aspect-w-16 aspect-h-9">
                            <?php if (!empty($similar['featured_image'])): ?>
                                <?php if (substr($similar['featured_image'], 0, 4) === 'http'): ?>
                                    <img src="<?php echo htmlspecialchars($similar['featured_image']); ?>" alt="<?php echo htmlspecialchars($similar['title']); ?>" class="w-full h-full object-cover transform transition-transform duration-500 group-hover:scale-110">
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($similar['featured_image']); ?>" alt="<?php echo htmlspecialchars($similar['title']); ?>" class="w-full h-full object-cover transform transition-transform duration-500 group-hover:scale-110">
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="w-full h-full bg-gradient-to-br from-purple-900 to-indigo-900"></div>
                            <?php endif; ?>
                            <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent opacity-70"></div>
                            <?php if (!empty($similar['price'])): ?>
                            <div class="absolute top-4 right-4 bg-purple-600 text-white px-4 py-1.5 rounded-full text-sm font-bold shadow-lg backdrop-blur-sm bg-opacity-90">
                                <?php echo htmlspecialchars($similar['price']); ?> €
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-6">
                            <h3 class="text-xl font-bold text-white mb-2 group-hover:text-purple-300 transition-colors duration-300"><?php echo htmlspecialchars($similar['title']); ?></h3>
                            <div class="text-purple-400 font-medium text-sm">
                                <i class="fas fa-arrow-right mr-1 opacity-0 group-hover:opacity-100 transition-all duration-300 transform group-hover:translate-x-1"></i> Découvrir ce rituel
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Call to action -->
            <div class="relative bg-mystic rounded-xl p-8 md:p-12 text-center mb-12 shadow-2xl overflow-hidden">
                <!-- Animated background particles -->
                <div class="absolute inset-0 opacity-20">
                    <div class="stars-bg"></div>
                </div>
                
                <div class="relative z-10">
                    <div class="inline-block mb-6 p-2 rounded-full bg-purple-900 bg-opacity-50">
                        <div class="bg-purple-800 rounded-full w-12 h-12 flex items-center justify-center">
                            <i class="fas fa-magic text-pink-300 text-xl"></i>
                        </div>
                    </div>
                    
                    <h2 class="text-3xl md:text-4xl font-cinzel font-bold text-gradient mb-4">Vous avez des questions ?</h2>
                    <p class="text-gray-300 mb-8 max-w-2xl mx-auto text-lg">N'hésitez pas à me contacter pour plus d'informations sur ce rituel ou pour discuter de vos besoins spécifiques.</p>
                    
                    <div class="flex flex-col sm:flex-row justify-center items-center space-y-4 sm:space-y-0 sm:space-x-4">
                        <a href="contact.php" class="button-magic px-8 py-4 rounded-full text-white font-medium shadow-lg text-lg transition-all hover:shadow-xl hover:-translate-y-1 flex items-center justify-center min-w-[220px] pulse-effect">
                            <i class="fas fa-envelope mr-2 text-pink-300"></i> Contactez-moi
                        </a>
                        <a href="rituals.php" class="px-8 py-4 rounded-full bg-gray-800 bg-opacity-50 text-white font-medium border border-purple-500 text-lg hover:bg-purple-900 transition-all flex items-center justify-center min-w-[220px]">
                            <i class="fas fa-arrow-left mr-2"></i> Voir tous les rituels
                        </a>
                    </div>
                </div>
            </div>
            
            <style>
            .stars-bg {
                background-image: 
                    radial-gradient(2px 2px at 20px 30px, #eaeaea, rgba(0,0,0,0)),
                    radial-gradient(2px 2px at 40px 70px, #f7f7f7, rgba(0,0,0,0)),
                    radial-gradient(2px 2px at 50px 160px, #ddd, rgba(0,0,0,0)),
                    radial-gradient(3px 3px at 120px 10px, #fff, rgba(0,0,0,0)),
                    radial-gradient(2px 2px at 140px 30px, #f7f7f7, rgba(0,0,0,0)),
                    radial-gradient(2px 2px at 180px 50px, #fff, rgba(0,0,0,0)),
                    radial-gradient(3px 3px at 220px 70px, #eee, rgba(0,0,0,0)),
                    radial-gradient(2px 2px at 240px 100px, #fff, rgba(0,0,0,0));
                background-size: 400px 400px;
                animation: stars-move 60s linear infinite;
                width: 100%;
                height: 100%;
                opacity: 0.6;
            }
            
            @keyframes stars-move {
                0% { background-position: 0 0; }
                100% { background-position: 400px 400px; }
            }
            </style>
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
