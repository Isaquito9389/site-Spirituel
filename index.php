<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';

// Affichage des erreurs en mode développement
// Inclure la connexion à la base de données avant tout
require_once 'includes/db_connect.php';

// Définir ce fichier comme la page d'accueil de WordPress
define('WP_USE_THEMES', false);

// Vérifier si WordPress est installé dans le sous-dossier /wp
if (file_exists('./wp/wp-load.php')) {
    // Définir une constante pour indiquer que nous sommes sur la page d'accueil
    define('IS_HOME', true);

    // Définir WP_USE_THEMES à false pour éviter de charger le thème WordPress
    if (!defined('WP_USE_THEMES')) {
        define('WP_USE_THEMES', false);
    }

    // Sauvegarder les constantes de base de données actuelles
    $our_db_constants = [];
    if (defined('DB_NAME')) $our_db_constants['DB_NAME'] = DB_NAME;
    if (defined('DB_USER')) $our_db_constants['DB_USER'] = DB_USER;
    if (defined('DB_PASS')) $our_db_constants['DB_PASS'] = DB_PASS;
    if (defined('DB_HOST')) $our_db_constants['DB_HOST'] = DB_HOST;
    if (defined('DB_CHARSET')) $our_db_constants['DB_CHARSET'] = DB_CHARSET;

    // Charger WordPress en utilisant un hack pour éviter les redéfinitions de constantes
    try {
        // Modifier le contenu de wp-config.php temporairement en mémoire
        $wp_config_file = file_get_contents('./wp/wp-config.php');
        $modified_wp_config = preg_replace(
            [
                "/define\(\s*'DB_NAME',\s*'([^']*)'\s*\);/",
                "/define\(\s*'DB_USER',\s*'([^']*)'\s*\);/",
                "/define\(\s*'DB_PASSWORD',\s*'([^']*)'\s*\);/",
                "/define\(\s*'DB_HOST',\s*'([^']*)'\s*\);/",
                "/define\(\s*'DB_CHARSET',\s*'([^']*)'\s*\);/"
            ],
            [
                "if (!defined('DB_NAME')) define('DB_NAME', '$1');",
                "if (!defined('DB_USER')) define('DB_USER', '$1');",
                "if (!defined('DB_PASSWORD')) define('DB_PASSWORD', '$1');",
                "if (!defined('DB_HOST')) define('DB_HOST', '$1');",
                "if (!defined('DB_CHARSET')) define('DB_CHARSET', '$1');"
            ],
            $wp_config_file
        );

        // Écrire le fichier modifié temporairement
        $temp_config_file = tempnam(sys_get_temp_dir(), 'wp_config_');
        file_put_contents($temp_config_file, $modified_wp_config);

        // Inclure le fichier temporaire au lieu du wp-config.php original
        define('ABSPATH', dirname(__FILE__) . '/wp/');
        require_once($temp_config_file);

        // Inclure wp-settings.php pour charger WordPress
        require_once('./wp/wp-settings.php');

        // Supprimer le fichier temporaire
        unlink($temp_config_file);
    } catch (Exception $e) {
        // Log d'erreur si le chargement de WordPress échoue
        // Restaurer nos constantes de base de données
        foreach ($our_db_constants as $const => $value) {
            if (!defined($const)) define($const, $value);
        }
    }
} else {
    // Log d'erreur si WordPress n'est pas trouvé
    }

// Inclure le fichier qui récupère les images du site
$site_images = require_once 'get_site_images.php';

// Récupérer les derniers rituels
function get_latest_rituals($limit = 3) {
    global $pdo;
    $rituals = [];

    try {
        // Récupérer les rituels publiés
        $stmt = $pdo->prepare("SELECT * FROM rituals WHERE status = 'published' ORDER BY created_at DESC LIMIT :limit");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rituals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        }

    return $rituals;
}

// Récupérer les derniers articles de blog
function get_latest_blog_posts($limit = 3) {
    global $pdo;
    $posts = [];

    try {
        // Récupérer les articles publiés
        $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE status = 'published' ORDER BY created_at DESC LIMIT :limit");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        }

    return $posts;
}

// Récupérer les derniers rituels et articles
$rituals = get_latest_rituals(3);
$blog_posts = get_latest_blog_posts(3);

// Lire le contenu de index.html
$html_content = file_get_contents('index.html');

// Vérifier si l'image background_main existe
if (isset($site_images['background_main'])) {
    // Vérifier si le chemin est absolu ou relatif
    $background_image = $site_images['background_main'];

    // Vérifier si le fichier existe réellement
    if (!file_exists($background_image)) {
        // Essayer avec un chemin relatif
        if (file_exists('uploads/images/background-main.png')) {
            $background_image = 'uploads/images/background-main.png';
        } else {
            // L'image n'existe pas, on utilise l'image par défaut
            $background_image = 'assets/images/background-main.jpg';
        }
    }
} else {
    // L'image n'existe pas, on utilise l'image par défaut
    $background_image = 'assets/images/background-main.jpg';
}

// Remplacer les chemins d'images par ceux de la bibliothèque
$replacements = [
    "url('assets/images/background-main.jpg')" => "url('" . $background_image . "')",
    "url('assets/images/vodoun-bg.png')" => "url('" . $site_images['vodoun_bg'] . "')",
    "src=\"assets/images/vodoun-bg.png\"" => "src=\"" . $site_images['vodoun_bg'] . "\"",
    "src=\"assets/images/products/bougie-rouge.jpg\"" => "src=\"" . $site_images['product_bougie'] . "\"",
    "src=\"assets/images/products/miroir-noir.jpg\"" => "src=\"" . $site_images['product_miroir'] . "\"",
    "src=\"assets/images/products/encens.jpg\"" => "src=\"" . $site_images['product_encens'] . "\""
];

// Effectuer les remplacements
foreach ($replacements as $search => $replace) {
    $html_content = str_replace($search, $replace, $html_content);
}

// Ajouter un commentaire et des styles CSS personnalisés pour ajuster l'affichage des images
$custom_styles = <<<CSS
<!-- Généré dynamiquement par index.php - Page d'accueil principale -->
<style>
    /* Augmenter légèrement la hauteur des conteneurs d'images */
    .ritual-card .w-full.h-48 {
        height: 13rem !important; /* Augmenter la hauteur de 48px (3rem) à 52px (13rem) */
    }

    .w-full.h-96 {
        height: 25rem !important; /* Augmenter la hauteur de 384px (24rem) à 400px (25rem) */
    }

    /* Ajustements pour les images dans les cadres */
    .ritual-card .w-full.h-48 img,
    .w-full.h-96 img {
        object-fit: cover; /* Garder le remplissage du cadre */
        object-position: center 40%; /* Montrer plus de contenu vers le bas */
        transform: scale(1.05); /* Légèrement agrandir pour éviter les coupures aux bords */
    }

    /* Animation au survol */
    .overflow-hidden:hover img.object-cover {
        transform: scale(1.15); /* Zoom un peu plus au survol */
        transition: transform 0.5s ease;
    }

    /* Transition douce pour toutes les images */
    .overflow-hidden img {
        transition: transform 0.3s ease, opacity 0.3s ease;
    }

    /* Ajustement pour les images de produits dans la boutique */
    #shop .w-full.h-48 img {
        object-position: center 35%; /* Ajuster la position verticale pour mieux voir les produits */
    }

    /* Ajustement pour les images de blog */
    #blog .ritual-card .w-full.h-48 img {
        object-position: center 30%; /* Ajuster pour voir le contenu principal des images de blog */
    }

    /* Ajustement pour les images de fond */
    [style*="background-image"] {
        background-position: center 40% !important; /* Montrer plus de contenu vers le bas pour les images de fond */
    }
</style>
CSS;

$html_content = str_replace('</head>', $custom_styles . "\n</head>", $html_content);

// Ajuster le padding pour éviter le chevauchement du texte "Transformez Votre Vie"
$html_content = str_replace('text-center pt-16 sm:pt-0', 'text-center pt-24 md:pt-16 sm:pt-0', $html_content);

// Remplacer le cadre Vodoun Ritual pour utiliser l'image vodoun-bg.png
// Utiliser un pattern plus spécifique qui cible uniquement la section Vodoun
$vodoun_section_pattern = '/<section id="vodoun".*?<div class="md:w-1\/2 relative">\s*<div class="relative z-10">\s*<div class="w-full h-96 rounded-xl.*?<\/div>\s*<\/div>\s*<div class="absolute -bottom-5.*?<\/div>\s*<div class="absolute -top-5.*?<\/div>\s*<\/div>/s';
$vodoun_replacement = '<section id="vodoun" class="py-20 bg-dark" style="background-image: url(\'' . $site_images['vodoun_bg'] . '\'); background-size: cover; background-position: center; background-blend-mode: overlay;"><div class="container mx-auto px-4"><div class="flex flex-col md:flex-row items-center"><div class="md:w-1/2 mb-10 md:mb-0 md:pr-10"><h2 class="font-cinzel text-3xl md:text-5xl font-bold mb-6 text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-pink-600">Rituels Vodoun Authentiques</h2><div class="w-24 h-1 bg-gradient-to-r from-purple-500 to-pink-500 mb-6"></div><p class="text-gray-400 mb-6">Découvrez les rituels vodoun transmis de génération en génération par les grands prêtres du Bénin. Ces pratiques sacrées permettent de communiquer directement avec les forces invisibles.</p><ul class="space-y-3 mb-8"><li class="flex items-start"><i class="fas fa-check text-pink-500 mt-1 mr-3"></i><span class="text-gray-300">Rituels avec Mami Wata pour la beauté et la séduction</span></li><li class="flex items-start"><i class="fas fa-check text-pink-500 mt-1 mr-3"></i><span class="text-gray-300">Invocation de Legba pour ouvrir les chemins</span></li><li class="flex items-start"><i class="fas fa-check text-pink-500 mt-1 mr-3"></i><span class="text-gray-300">Pactes avec Ogoun pour la force et la protection</span></li><li class="flex items-start"><i class="fas fa-check text-pink-500 mt-1 mr-3"></i><span class="text-gray-300">Rituels Egungun pour communiquer avec les ancêtres</span></li></ul><button onclick="openCategoryModal(\'vodoun\')" class="btn-magic px-6 py-3 rounded-full text-white font-medium inline-flex items-center">Explorer les Rituels Vodoun <i class="fas fa-arrow-right ml-2"></i></button></div><div class="md:w-1/2 relative"><div class="relative z-10"><div class="w-full h-96 rounded-xl bg-gradient-to-br from-purple-900 to-pink-900 flex items-center justify-center overflow-hidden"><img src="' . $site_images['vodoun_bg'] . '" alt="Vodoun Ritual" class="w-full h-full object-cover opacity-80"></div></div><div class="absolute -bottom-5 -left-5 w-32 h-32 rounded-full bg-pink-800 opacity-20 z-0"></div><div class="absolute -top-5 -right-5 w-24 h-24 rounded-full bg-purple-800 opacity-20 z-0"></div></div>';

// Rechercher uniquement dans la section Vodoun
$start_pos = strpos($html_content, '<section id="vodoun"');
$end_pos = strpos($html_content, '</section>', $start_pos) + 10; // +10 pour inclure </section>

if ($start_pos !== false && $end_pos !== false) {
    $vodoun_section = substr($html_content, $start_pos, $end_pos - $start_pos);
    $new_vodoun_section = preg_replace($vodoun_section_pattern, $vodoun_replacement, $vodoun_section);
    $html_content = substr_replace($html_content, $new_vodoun_section, $start_pos, $end_pos - $start_pos);
}

// Ajouter une option pour changer l'image du cadre de la section Magie de l'Amour
// Nous ajoutons cette fonctionnalité mais nous ne modifions pas le cadre existant
if (isset($site_images['love_ritual']) && !empty($site_images['love_ritual'])) {
    $love_image_pattern = '/src="https:\/\/images\.unsplash\.com\/photo-1516589178581-6cd7833ae3b2\?ixlib=rb-1\.2\.1&auto=format&fit=crop&w=1350&q=80"/';
    $love_image_replacement = 'src="' . $site_images['love_ritual'] . '"';
    $html_content = preg_replace($love_image_pattern, $love_image_replacement, $html_content);
}

// Ajouter un hook pour WordPress si nécessaire
if (function_exists('add_action')) {
    // Indiquer à WordPress que c'est notre page d'accueil
    add_action('template_redirect', function() {
        if (is_home() || is_front_page()) {
            // Déjà géré par ce script, ne rien faire
            exit;
        }
    });

    // Exécuter les actions WordPress si nécessaire
    if (function_exists('do_action')) {
        do_action('wp');
    }
}

// Remplacer les rituels statiques par les rituels dynamiques si disponibles
if (!empty($rituals)) {
    // Préparer le HTML pour les rituels
    $rituals_html = '';
    foreach ($rituals as $ritual) {
        $image = !empty($ritual['featured_image']) ? $ritual['featured_image'] : 'assets/images/rituals/default.jpg';
        $title = htmlspecialchars($ritual['title']);
        $excerpt = htmlspecialchars($ritual['excerpt']);
        $slug = htmlspecialchars($ritual['slug']);

        $rituals_html .= <<<HTML
        <div class="ritual-card rounded-xl overflow-hidden">
            <div class="relative">
                <div class="w-full h-48 overflow-hidden">
                    <img src="{$image}" alt="{$title}" class="w-full h-full object-cover transition-transform duration-500 hover:scale-110">
                </div>
                <div class="absolute top-0 right-0 bg-gradient-to-l from-purple-900 to-transparent px-4 py-2">
                    <span class="text-white text-sm font-medium">Rituel Puissant</span>
                </div>
            </div>
            <div class="p-6">
                <h3 class="font-cinzel text-xl font-bold mb-3 text-white">{$title}</h3>
                <p class="text-gray-400 mb-4">{$excerpt}</p>
                <a href="/ritual/{$slug}" class="inline-flex items-center text-purple-400 hover:text-pink-500 transition">
                    Découvrir ce rituel <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
        HTML;
    }

    // Remplacer la section des rituels par le contenu dynamique
    $ritual_section_pattern = '/<div class="grid grid-cols-1 md:grid-cols-3 gap-8">(.*?)<\/div>\s*<div class="text-center mt-12">/s';
    $ritual_replacement = '<div class="grid grid-cols-1 md:grid-cols-3 gap-8">' . $rituals_html . '</div><div class="text-center mt-12">';
    $html_content = preg_replace($ritual_section_pattern, $ritual_replacement, $html_content, 1);
}

// Remplacer les articles de blog statiques par les articles dynamiques si disponibles
if (!empty($blog_posts)) {
    // Préparer le HTML pour les articles de blog
    $blog_html = '';
    foreach ($blog_posts as $post) {
        $image = !empty($post['featured_image']) ? $post['featured_image'] : 'assets/images/blog/default.jpg';
        $title = htmlspecialchars($post['title']);
        // Limiter l'extrait à 150 caractères maximum et ajouter "..." si tronqué
        $excerpt_text = !empty($post['excerpt']) ? $post['excerpt'] : (isset($post['content']) ? strip_tags($post['content']) : '');
        $max_length = 150;
        $excerpt = strlen($excerpt_text) > $max_length ? htmlspecialchars(substr($excerpt_text, 0, $max_length)) . '...' : htmlspecialchars($excerpt_text);
        $slug = htmlspecialchars($post['slug']);
        $date = date('d M Y', strtotime($post['created_at']));

        $blog_html .= <<<HTML
        <div class="ritual-card rounded-xl overflow-hidden">
            <div class="w-full h-48 overflow-hidden">
                <img src="{$image}" alt="{$title}" class="w-full h-full object-cover transition-transform duration-500 hover:scale-110">
            </div>
            <div class="p-6">
                <div class="flex items-center text-gray-500 text-sm mb-2">
                    <i class="far fa-calendar-alt mr-2"></i> {$date}
                </div>
                <h3 class="font-cinzel text-xl font-bold mb-3 text-white">{$title}</h3>
                <p class="text-gray-400 mb-4">{$excerpt}</p>
                <a href="/blog/{$slug}" class="inline-flex items-center text-purple-400 hover:text-pink-500 transition">
                    Lire l'article <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
        HTML;
    }

    // Remplacer la section du blog par le contenu dynamique
    $blog_section_pattern = '/<section id="blog".*?<div class="grid grid-cols-1 md:grid-cols-3 gap-8">(.*?)<\/div>\s*<div class="text-center mt-12">/s';
    $blog_replacement = '<section id="blog" class="py-20 bg-gradient-to-b from-dark to-purple-900"><div class="container mx-auto px-4"><div class="text-center mb-16"><h2 class="font-cinzel text-3xl md:text-5xl font-bold mb-4 text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-pink-600">Blog Spirituel</h2><p class="text-xl text-gray-300 max-w-3xl mx-auto">Explorez nos articles sur la spiritualité, la magie et le développement personnel.</p></div><div class="grid grid-cols-1 md:grid-cols-3 gap-8">' . $blog_html . '</div><div class="text-center mt-12">';
    $html_content = preg_replace($blog_section_pattern, $blog_replacement, $html_content, 1);
}

// Afficher le contenu HTML modifié
echo $html_content;
?>
