<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
// Affichage des erreurs en mode développement
// Gestionnaire d'erreur personnalisé pour éviter les erreurs 500
set_error_handler(function(
    $errno, $errstr, $errfile, $errline
) {
    if (error_reporting() === 0) {
        return false;
    }
    $error_message = "Error [$errno] $errstr - $errfile:$errline";
    echo "<div class=\"error-message\">\n<h3>Une erreur est survenue</h3>\n<p>Nous avons rencontré un problème lors du traitement de votre demande. Veuillez réessayer plus tard ou contacter l'administrateur.</p>\n</div>";
    return true;
}, E_ALL);

// Inclusion de la connexion à la base de données
require_once 'includes/db_connect.php';

// Include backlink functions if they exist
if (file_exists('admin/includes/backlink_functions.php')) {
    require_once 'admin/includes/backlink_functions.php';
}

// Récupération du slug du rituel
$slug = '';

// Méthode 1: Paramètre GET slug
if (isset($_GET['slug']) && !empty($_GET['slug'])) {
    $slug = $_GET['slug'];
}
// Méthode 2: Paramètre GET id (pour compatibilité)
elseif (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT slug FROM rituals WHERE id = ? AND status = 'published'");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['slug'])) {
            $slug = $result['slug'];
        }
    } catch (PDOException $e) {
        // Erreur silencieuse
    }
}

// Si aucun slug trouvé, rediriger vers la liste des rituels
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400;1,700;1,900&display=swap');
        
        :root {
            --primary-purple: #7209b7;
            --secondary-purple: #3a0ca3;
            --accent-pink: #f72585;
            --dark-bg: #0f0e17;
            --card-bg: #1a1a2e;
            --text-light: #fffffe;
            --text-gray: #d8d8d8;
            --text-muted: #a0a0a0;
            --border-color: rgba(114, 9, 183, 0.3);
            --shadow-glow: rgba(114, 9, 183, 0.4);
            --gradient-main: linear-gradient(135deg, var(--primary-purple), var(--secondary-purple));
            --gradient-accent: linear-gradient(135deg, var(--accent-pink), var(--primary-purple));
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Merriweather', serif;
            background: var(--dark-bg);
            color: var(--text-light);
            line-height: 1.6;
            overflow-x: hidden;
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
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .container-wide {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header */
        header {
            background: var(--gradient-main);
            padding: 2rem 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(from 0deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: rotate 20s linear infinite;
            pointer-events: none;
        }
        
        @keyframes rotate {
            100% { transform: rotate(360deg); }
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 2rem;
            position: relative;
            z-index: 2;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logo-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            animation: pulse-glow 3s ease-in-out infinite;
        }
        
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 20px rgba(247, 37, 133, 0.5); }
            50% { box-shadow: 0 0 30px rgba(247, 37, 133, 0.8); }
        }
        
        .logo-text {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--text-light), var(--accent-pink));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .nav {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }
        
        .nav a {
            color: var(--text-light);
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
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .nav a:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }
        
        .nav a:hover::before {
            left: 100%;
        }
        
        /* Error Message */
        .error-message {
            padding: 20px;
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border-radius: 10px;
            margin: 20px;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        /* Main Content */
        main {
            padding: 3rem 0;
        }
        
        /* Breadcrumbs */
        .breadcrumbs {
            margin-bottom: 2rem;
            color: var(--text-muted);
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .breadcrumbs a {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .breadcrumbs a:hover {
            color: var(--accent-pink);
        }
        
        .breadcrumbs .current {
            color: var(--primary-purple);
        }
        
        /* Ritual Header */
        .ritual-header {
            background: var(--gradient-main);
            border-radius: 20px;
            padding: 3rem;
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        
        .ritual-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: floating 6s ease-in-out infinite;
        }
        
        @keyframes floating {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }
        
        .ritual-header-content {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        
        @media (min-width: 768px) {
            .ritual-header-content {
                grid-template-columns: 1fr 2fr;
            }
        }
        
        .ritual-image {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            max-width: 100%;
            height: auto;
        }
        
        .ritual-image img {
            width: 100%;
            height: auto;
            max-width: 100%;
            object-fit: contain;
            transition: transform 0.5s ease;
            border-radius: 15px;
        }
        
        .ritual-image:hover img {
            transform: scale(1.05);
        }
        
        .ritual-image::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transform: skewX(-25deg);
            animation: image-shine 4s ease-in-out infinite;
        }
        
        @keyframes image-shine {
            0% { left: -100%; }
            20% { left: 100%; }
            100% { left: 100%; }
        }
        
        .ritual-video {
            border-radius: 15px;
            overflow: hidden;
            aspect-ratio: 16/9;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            margin-bottom: 2rem;
        }
        
        .ritual-video iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .ritual-info h1 {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 900;
            margin-bottom: 1.5rem;
            background: var(--gradient-accent);
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
        
        .ritual-excerpt {
            font-size: 1.2rem;
            color: var(--text-gray);
            margin-bottom: 2rem;
            line-height: 1.8;
        }
        
        .ritual-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .meta-item {
            background: rgba(26, 26, 46, 0.7);
            border-radius: 25px;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .meta-item:hover {
            background: rgba(114, 9, 183, 0.2);
            border-color: var(--primary-purple);
            transform: translateY(-2px);
        }
        
        .meta-item i {
            color: var(--primary-purple);
        }
        
        .meta-value {
            color: var(--accent-pink);
            font-weight: 600;
        }
        
        .ritual-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .btn {
            padding: 1rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            min-width: 200px;
            justify-content: center;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: var(--gradient-accent);
            color: var(--text-light);
            box-shadow: 0 4px 15px var(--shadow-glow);
            animation: pulse-button 3s ease-in-out infinite;
        }
        
        @keyframes pulse-button {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #25d366, #128c7e);
            color: var(--text-light);
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px var(--shadow-glow);
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        /* Divider */
        .mystic-divider {
            height: 3px;
            background: linear-gradient(to right, transparent, var(--primary-purple), transparent);
            margin: 3rem 0;
            position: relative;
        }
        
        .mystic-divider::before {
            content: '✧';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--dark-bg);
            padding: 0 1rem;
            color: var(--primary-purple);
            font-size: 1.5rem;
            animation: sparkle 2s ease-in-out infinite;
        }
        
        @keyframes sparkle {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Content Area */
        .content-container {
            background: linear-gradient(145deg, var(--card-bg), rgba(58, 12, 163, 0.1));
            border-radius: 20px;
            padding: 3rem;
            margin-bottom: 3rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
        }
        
        .content-area {
            font-size: 1.125rem;
            line-height: 2;
        }
        
        .content-area p {
            margin-bottom: 2rem;
        }
        
        .content-area h2 {
            font-family: 'Cinzel Decorative', cursive;
            font-size: 2rem;
            margin: 2.5rem 0 1.5rem 0;
            color: var(--accent-pink);
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
            background: var(--gradient-accent);
            border-radius: 2px;
        }
        
        .content-area h3 {
            font-family: 'Cinzel Decorative', cursive;
            font-size: 1.5rem;
            margin: 2rem 0 1rem 0;
            color: var(--primary-purple);
        }
        
        .content-area ul, .content-area ol {
            margin: 1.5rem 0;
            padding-left: 2rem;
        }
        
        .content-area li {
            margin-bottom: 0.5rem;
            position: relative;
        }
        
        .content-area ul li::before {
            content: '✦';
            color: var(--primary-purple);
            position: absolute;
            left: -1.5rem;
        }
        
        /* Backlinks Section */
        .backlinks-section {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }
        
        .backlinks-section h3 {
            font-family: 'Cinzel Decorative', cursive;
            color: var(--accent-pink);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .backlink-item {
            background: rgba(26, 26, 46, 0.5);
            border-radius: 10px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }
        
        .backlink-item:hover {
            background: rgba(114, 9, 183, 0.1);
            border-color: var(--primary-purple);
            transform: translateY(-2px);
        }
        
        .backlink-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .backlink-type {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .backlink-domain {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .backlink-title {
            margin: 0 0 0.5rem 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .backlink-title a {
            color: var(--text-light);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .backlink-title a:hover {
            color: var(--accent-pink);
        }
        
        .backlink-description {
            color: var(--text-gray);
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .media-container {
            margin: 2.5rem 0;
            background: rgba(26, 26, 46, 0.7);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .media-container::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: var(--gradient-accent);
            border-radius: 15px;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .media-container:hover::before {
            opacity: 1;
        }
        
        .media-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(114, 9, 183, 0.3);
        }
        
        .media-container img, .media-container iframe {
            width: 100%;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        .media-caption {
            text-align: center;
            font-style: italic;
            color: var(--text-gray);
            position: relative;
            padding-top: 1rem;
        }
        
        .media-caption::before {
            content: '';
            position: absolute;
            top: 0;
            left: 30%;
            right: 30%;
            height: 1px;
            background: var(--gradient-accent);
        }

        /* Styles pour les médias intégrés dans le contenu */
        .content-area img {
            margin: 2.5rem 0;
            background: rgba(26, 26, 46, 0.7);
            border-radius: 15px;
            padding: 1rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            width: 100%;
            height: auto;
        }

        .content-area img:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(114, 9, 183, 0.3);
        }

        .content-area iframe {
            margin: 2.5rem 0;
            background: rgba(26, 26, 46, 0.7);
            border-radius: 15px;
            padding: 1rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            width: 100%;
            min-height: 400px;
            aspect-ratio: 16/9;
        }

        .content-area iframe:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(114, 9, 183, 0.3);
        }

        /* Style spécial pour les vidéos YouTube intégrées */
        .content-area iframe[src*="youtube.com"] {
            border-radius: 15px;
            overflow: hidden;
        }
        
        /* Similar Rituals */
        .similar-rituals {
            margin-bottom: 4rem;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1rem;
        }
        
        .section-title::before {
            content: '';
            width: 4px;
            height: 40px;
            background: var(--gradient-accent);
            border-radius: 2px;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 900;
            background: var(--gradient-accent);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .rituals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .ritual-card {
            background: var(--card-bg);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            transition: all 0.5s ease;
            position: relative;
            border: 1px solid var(--border-color);
            text-decoration: none;
            color: inherit;
        }
        
        .ritual-card:hover {
            transform: translateY(-10px) rotateX(5deg);
            box-shadow: 0 20px 40px rgba(114, 9, 183, 0.3);
        }
        
        .ritual-card-image {
            position: relative;
            aspect-ratio: 16/9;
            overflow: hidden;
        }
        
        .ritual-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .ritual-card:hover .ritual-card-image img {
            transform: scale(1.1);
        }
        
        .ritual-card-price {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--gradient-accent);
            color: var(--text-light);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .ritual-card-content {
            padding: 1.5rem;
        }
        
        .ritual-card-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
            transition: color 0.3s ease;
        }
        
        .ritual-card:hover .ritual-card-title {
            color: var(--accent-pink);
        }
        
        .ritual-card-link {
            color: var(--primary-purple);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .ritual-card:hover .ritual-card-link {
            gap: 1rem;
        }
        
        /* Call to Action */
        .cta-section {
            position: relative;
            background: var(--gradient-main);
            border-radius: 20px;
            padding: 4rem 2rem;
            text-align: center;
            margin-bottom: 4rem;
            overflow: hidden;
        }
        
        .cta-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(from 0deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: rotate 30s linear infinite;
        }
        
        .cta-content {
            position: relative;
            z-index: 2;
        }
        
        .cta-icon {
            width: 80px;
            height: 80px;
            background: var(--gradient-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem auto;
            font-size: 2rem;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .cta-title {
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--text-light), var(--accent-pink));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .cta-text {
            font-size: 1.2rem;
            color: var(--text-gray);
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Footer */
        footer {
            background: var(--card-bg);
            padding: 3rem 0;
            border-top: 1px solid var(--border-color);
            margin-top: 4rem;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .footer-section h3 {
            font-family: 'Cinzel Decorative', cursive;
            color: var(--accent-pink);
            margin-bottom: 1rem;
        }
        
        .footer-section p, .footer-section a {
            color: var(--text-gray);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer-section a:hover {
            color: var(--accent-pink);
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
            color: var(--text-muted);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .header-content {
                text-align: center;
            }
            
            .nav {
                justify-content: center;
            }
            
            .ritual-header {
                padding: 2rem 1rem;
            }
            
            .content-container {
                padding: 2rem 1rem;
            }
            
            .ritual-actions {
                justify-content: center;
            }
            
            .btn {
                min-width: 150px;
            }
            
            .cta-section {
                padding: 3rem 1rem;
            }
            
            .cta-title {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 480px) {
            .logo-text {
                font-size: 1.8rem;
            }
            
            .ritual-info h1 {
                font-size: 2rem;
            }
            
            .ritual-meta {
                justify-content: center;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
        }
        
        /* Loading Animation */
        .loading {
            display: inline-block;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Scroll to top button */
        .scroll-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--gradient-accent);
            color: var(--text-light);
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 15px var(--shadow-glow);
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(100px);
            z-index: 1000;
        }
        
        .scroll-top.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        .scroll-top:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px var(--shadow-glow);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-moon"></i>
                    </div>
                    <div class="logo-text font-cinzel">Mystica Occulta</div>
                </div>
                <nav class="nav">
                    <a href="index.php"><i class="fas fa-home"></i> Accueil</a>
                    <a href="rituals.php"><i class="fas fa-magic"></i> Rituels</a>
                    <a href="contact.php"><i class="fas fa-envelope"></i> Contact</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <div class="container">
            <!-- Breadcrumbs -->
            <div class="breadcrumbs">
                <a href="index.php">Accueil</a>
                <span>/</span>
                <a href="rituals.php">Rituels</a>
                <span>/</span>
                <span class="current"><?php echo htmlspecialchars($ritual['title']); ?></span>
            </div>

            <!-- Ritual Header -->
            <div class="ritual-header">
                <div class="ritual-header-content">
                    <div class="ritual-media">
                        <?php if (!empty($ritual['youtube_url'])): ?>
                            <div class="ritual-video">
                                <?php
                                $video_url = $ritual['youtube_url'];
                                
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
                                    
                                    // Si ce n'est pas YouTube, retourner l'URL originale
                                    return $url;
                                }
                                
                                // Convertir l'URL
                                $embed_url = convertToYouTubeEmbed($video_url);
                                ?>
                                <iframe src="<?php echo htmlspecialchars($embed_url); ?>" 
                                        frameborder="0" 
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                        allowfullscreen 
                                        loading="lazy"></iframe>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($ritual['featured_image'])): ?>
                            <div class="ritual-image">
                                <img src="<?php echo htmlspecialchars($ritual['featured_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($ritual['title']); ?>" 
                                     loading="lazy">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="ritual-info">
                        <h1 class="font-cinzel"><?php echo htmlspecialchars($ritual['title']); ?></h1>
                        
                        <?php if (!empty($ritual['excerpt'])): ?>
                            <div class="ritual-excerpt">
                                <?php echo nl2br(htmlspecialchars($ritual['excerpt'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="ritual-meta">
                            <?php if (!empty($ritual['category'])): ?>
                                <div class="meta-item">
                                    <i class="fas fa-tag"></i>
                                    <span>Catégorie:</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($ritual['category']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($ritual['duration'])): ?>
                                <div class="meta-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Durée:</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($ritual['duration']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($ritual['price'])): ?>
                                <div class="meta-item">
                                    <i class="fas fa-euro-sign"></i>
                                    <span>Prix:</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($ritual['price']); ?>€</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span>Publié le:</span>
                                <span class="meta-value"><?php echo date('d/m/Y', strtotime($ritual['created_at'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="ritual-actions">
                            <?php if (!empty($ritual['price'])): ?>
                                <a href="#contact" class="btn btn-primary">
                                    <i class="fas fa-shopping-cart"></i>
                                    Commander ce rituel
                                </a>
                            <?php endif; ?>
                            
                            <a href="https://wa.me/67512021?text=Bonjour, je suis intéressé(e) par le rituel: <?php echo urlencode($ritual['title']); ?>" 
                               class="btn btn-secondary" target="_blank">
                                <i class="fab fa-whatsapp"></i>
                                Contacter sur WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Divider -->
            <div class="mystic-divider"></div>

            <!-- Content -->
            <div class="content-container">
                <div class="content-area">
                    <?php echo $ritual['content']; ?>
                </div>
                
                <!-- Backlinks Section -->
                <?php if (function_exists('get_backlinks')): ?>
                    <?php $backlinks = get_backlinks('ritual', $ritual['id']); ?>
                    <?php if (!empty($backlinks)): ?>
                        <div class="backlinks-section">
                            <h3>
                                <i class="fas fa-external-link-alt"></i>
                                Références et Sources
                            </h3>
                            <div class="backlinks-grid">
                                <?php foreach ($backlinks as $backlink): ?>
                                    <?php $formatted = format_backlink_display($backlink); ?>
                                    <div class="backlink-item">
                                        <div class="backlink-meta">
                                            <span class="backlink-type" style="background-color: <?php echo $formatted['color']; ?>20; color: <?php echo $formatted['color']; ?>;">
                                                <?php echo $formatted['type_label']; ?>
                                            </span>
                                            <span class="backlink-domain"><?php echo $formatted['domain']; ?></span>
                                        </div>
                                        <h4 class="backlink-title">
                                            <a href="<?php echo htmlspecialchars($formatted['url']); ?>" target="_blank" rel="noopener">
                                                <?php echo htmlspecialchars($formatted['title']); ?>
                                                <i class="fas fa-external-link-alt" style="font-size: 0.8rem; margin-left: 0.5rem;"></i>
                                            </a>
                                        </h4>
                                        <?php if (!empty($formatted['description'])): ?>
                                            <p class="backlink-description"><?php echo htmlspecialchars($formatted['description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Similar Rituals -->
            <?php if (!empty($similar_rituals)): ?>
                <section class="similar-rituals">
                    <div class="section-title">
                        <h2 class="font-cinzel">Rituels Similaires</h2>
                    </div>
                    
                    <div class="rituals-grid">
                        <?php foreach ($similar_rituals as $similar): ?>
                            <a href="/<?php echo urlencode($similar['slug']); ?>" class="ritual-card">
                                <?php if (!empty($similar['featured_image'])): ?>
                                    <div class="ritual-card-image">
                                        <img src="<?php echo htmlspecialchars($similar['featured_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($similar['title']); ?>" 
                                             loading="lazy">
                                        <?php if (!empty($similar['price'])): ?>
                                            <div class="ritual-card-price"><?php echo htmlspecialchars($similar['price']); ?>€</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="ritual-card-content">
                                    <h3 class="ritual-card-title"><?php echo htmlspecialchars($similar['title']); ?></h3>
                                    <div class="ritual-card-link">
                                        <span>Découvrir ce rituel</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Call to Action -->
            <section class="cta-section">
                <div class="cta-content">
                    <div class="cta-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h2 class="cta-title font-cinzel">Besoin d'un Rituel Personnalisé ?</h2>
                    <p class="cta-text">
                        Chaque situation est unique. Contactez-moi pour un rituel sur mesure adapté à vos besoins spécifiques.
                    </p>
                    <a href="contact.php" class="btn btn-primary">
                        <i class="fas fa-envelope"></i>
                        Me Contacter
                    </a>
                </div>
            </section>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Mystica Occulta</h3>
                    <p>Découvrez les mystères de l'occultisme et de la magie à travers nos rituels authentiques et puissants.</p>
                </div>
                <div class="footer-section">
                    <h3>Navigation</h3>
                    <p><a href="index.php">Accueil</a></p>
                    <p><a href="rituals.php">Rituels</a></p>
                    <p><a href="contact.php">Contact</a></p>
                </div>
                <div class="footer-section">
                    <h3>Contact</h3>
                    <p><i class="fas fa-envelope"></i> contact@mysticaocculta.com</p>
                    <p><i class="fab fa-whatsapp"></i> WhatsApp</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Mystica Occulta. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top Button -->
    <button class="scroll-top" id="scrollTop">
        <i class="fas fa-chevron-up"></i>
    </button>

    <!-- JavaScript -->
    <script>
        // Scroll to top functionality
        const scrollTopBtn = document.getElementById('scrollTop');
        
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 100) {
                scrollTopBtn.classList.add('visible');
            } else {
                scrollTopBtn.classList.remove('visible');
            }
        });
        
        scrollTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Smooth scrolling for anchor links
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

        // Lazy loading for images
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src || img.src;
                        img.classList.remove('lazy');
                        observer.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img[loading="lazy"]').forEach(img => {
                imageObserver.observe(img);
            });
        }

        // Enhanced error handling for media
        document.querySelectorAll('img').forEach(img => {
            img.addEventListener('error', function() {
                this.style.display = 'none';
                const container = this.closest('.ritual-image, .ritual-card-image');
                if (container) {
                    container.style.background = 'linear-gradient(135deg, var(--primary-purple), var(--secondary-purple))';
                    container.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: white; font-size: 3rem;"><i class="fas fa-image"></i></div>';
                }
            });
        });

        // Add loading animation for buttons
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (this.href && !this.href.startsWith('#')) {
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.className = 'fas fa-spinner loading';
                    }
                }
            });
        });
    </script>
</body>
</html>
