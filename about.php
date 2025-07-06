<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
require_once 'includes/db_connect.php';

// Définir les options du site par défaut
$siteName = 'Mystica Occulta';
$siteDescription = 'Votre portail vers l\'éveil spirituel';

// Essayer de récupérer les options du site depuis la base de données si une table existe
try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'site_options'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("SELECT option_name, option_value FROM site_options WHERE option_name IN ('site_name', 'site_description')");
        $stmt->execute();
        $options = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        if (isset($options['site_name'])) {
            $siteName = $options['site_name'];
        }

        if (isset($options['site_description'])) {
            $siteDescription = $options['site_description'];
        }
    }
} catch (PDOException $e) {
    // Silencieusement continuer avec les valeurs par défaut
}

// Contenu par défaut de la page À propos
$pageContent = [
    'title' => 'À propos de ' . $siteName,
    'meta_description' => 'Découvrez notre histoire, notre mission et nos valeurs.',
    'content' => '<h2>Notre Histoire</h2>
    <p>Bienvenue dans notre espace dédié à la spiritualité et au bien-être. ' . $siteName . ' a été créé avec la passion de partager des connaissances ancestrales et modernes pour vous accompagner dans votre cheminement spirituel.</p>

    <h2>Notre Mission</h2>
    <p>Nous nous engageons à vous offrir des rituels, des consultations et des produits de qualité qui vous aideront à vous connecter avec votre essence profonde et à trouver l\'harmonie.</p>

    <h2>Nos Valeurs</h2>
    <ul>
        <li>Authenticité dans notre approche</li>
        <li>Respect des traditions spirituelles</li>
        <li>Bienveillance envers tous les êtres</li>
        <li>Partage des connaissances sans jugement</li>
    </ul>'
];

// Essayer de récupérer le contenu depuis la base de données si la table existe
try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'pages'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE page_slug = 'about' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $pageContent = $result;
        }
    }
} catch (PDOException $e) {
    // Silencieusement continuer avec le contenu par défaut
}

$pageTitle = isset($pageContent['title']) ? $pageContent['title'] : 'À propos';
$metaDescription = isset($pageContent['meta_description']) ? $pageContent['meta_description'] : 'Découvrez notre histoire, notre mission et nos valeurs.';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | <?php echo htmlspecialchars($siteName); ?></title>

    <!-- Meta tags pour SEO -->
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <meta name="keywords" content="à propos, mystica occulta, spiritualité, ésotérisme, magie, histoire, mission, valeurs, services spirituels">
    <meta name="author" content="<?php echo htmlspecialchars($siteName); ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://www.mystica-occulta.com/about.php">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.mystica-occulta.com/about.php">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?> | <?php echo htmlspecialchars($siteName); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <meta property="og:image" content="https://www.mystica-occulta.com/assets/images/og-image-about.jpg">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://www.mystica-occulta.com/about.php">
    <meta property="twitter:title" content="<?php echo htmlspecialchars($pageTitle); ?> | <?php echo htmlspecialchars($siteName); ?>">
    <meta property="twitter:description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <meta property="twitter:image" content="https://www.mystica-occulta.com/assets/images/og-image-about.jpg">

    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Playfair Display', serif;
        }
        .gradient-bg {
            background: linear-gradient(to right, #8A2387, #E94057, #F27121);
        }
        .hover-grow {
            transition: transform 0.3s;
        }
        .hover-grow:hover {
            transform: scale(1.03);
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">
    <!-- Header -->
    <header class="bg-purple-900 text-white shadow-md">
        <div class="container mx-auto px-4 py-6">
            <div class="flex justify-between items-center">
                <div>
                    <a href="index.php" class="text-2xl font-bold font-serif"><?php echo htmlspecialchars($siteName); ?></a>
                    <p class="text-sm text-purple-200"><?php echo htmlspecialchars($siteDescription); ?></p>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button id="mobile-menu-button" class="text-white focus:outline-none">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                </div>

                <!-- Desktop Navigation -->
                <nav class="hidden md:flex space-x-1">
                    <a href="index.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Accueil</a>
                    <a href="shop.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Boutique</a>
                    <a href="rituals.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Rituels</a>
                    <a href="blog.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Blog</a>
                    <a href="testimonials.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Témoignages</a>
                    <a href="about.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300 border-b-2 border-pink-400">À propos</a>
                    <a href="contact.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Contact</a>
                </nav>
            </div>

            <!-- Mobile Navigation -->
            <div id="mobile-menu" class="md:hidden hidden mt-4 pb-4">
                <nav class="flex flex-col space-y-2">
                    <a href="index.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Accueil</a>
                    <a href="shop.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Boutique</a>
                    <a href="rituals.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Rituels</a>
                    <a href="blog.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Blog</a>
                    <a href="testimonials.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Témoignages</a>
                    <a href="about.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300 border-b-2 border-pink-400">À propos</a>
                    <a href="contact.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Contact</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="bg-gradient-to-r from-purple-900 via-purple-700 to-pink-700 text-white py-20">
        <div class="container mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-6"><?php echo htmlspecialchars($pageTitle); ?></h1>
            <p class="text-xl max-w-3xl mx-auto"><?php echo htmlspecialchars($metaDescription); ?></p>
        </div>
    </section>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-12">
        <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-8">
            <div class="prose prose-lg max-w-none">
                <?php echo isset($pageContent['content']) ? $pageContent['content'] : ''; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-purple-900 text-white pt-12 pb-6">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                <div>
                    <h3 class="text-2xl font-serif mb-4"><?php echo htmlspecialchars($siteName); ?></h3>
                    <p class="mb-4"><?php echo htmlspecialchars($siteDescription); ?></p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-white hover:text-purple-300 transition"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white hover:text-purple-300 transition"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white hover:text-purple-300 transition"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white hover:text-purple-300 transition"><i class="fab fa-pinterest"></i></a>
                    </div>
                </div>

                <div>
                    <h4 class="text-xl font-serif mb-4">Liens Rapides</h4>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-400 hover:text-purple-400 transition">Accueil</a></li>
                        <li><a href="shop.php" class="text-gray-400 hover:text-purple-400 transition">Boutique</a></li>
                        <li><a href="rituals.php" class="text-gray-400 hover:text-purple-400 transition">Rituels</a></li>
                        <li><a href="blog.php" class="text-gray-400 hover:text-purple-400 transition">Blog</a></li>
                        <li><a href="testimonials.php" class="text-gray-400 hover:text-purple-400 transition">Témoignages</a></li>
                        <li><a href="about.php" class="text-gray-400 hover:text-purple-400 transition">À propos</a></li>
                        <li><a href="contact.php" class="text-gray-400 hover:text-purple-400 transition">Contact</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-xl font-serif mb-4">Contactez-nous</h4>
                    <ul class="space-y-2">
                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt mt-1 mr-3 text-purple-400"></i>
                            <span>123 Rue de la Spiritualité, 75000 Paris</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-phone mt-1 mr-3 text-purple-400"></i>
                            <span>+33 1 23 45 67 89</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-envelope mt-1 mr-3 text-purple-400"></i>
                            <span>contact@sitespirituel.fr</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-purple-800 pt-6 mt-6 text-center text-sm">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?>. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript for Mobile Menu Toggle -->
    <script>
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        });
    </script>
</body>
</html>
