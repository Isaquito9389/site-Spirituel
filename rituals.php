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

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 9; // Nombre de rituels par page
$offset = ($page - 1) * $per_page;

// Filtres
$category = isset($_GET['category']) ? $_GET['category'] : null;

// Construction de la requête
$sql_count = "SELECT COUNT(*) FROM rituals WHERE status = 'published'";
$sql = "SELECT * FROM rituals WHERE status = 'published'";

// Application des filtres
if ($category) {
    $sql .= " AND category = :category";
    $sql_count .= " AND category = :category";
}

// Tri
$sql .= " ORDER BY created_at DESC";

// Limite pour pagination
$sql .= " LIMIT :offset, :per_page";

// Récupération des rituels
$rituals = [];
$total_rituals = 0;

try {
    // Compte total pour pagination
    $stmt_count = $pdo->prepare($sql_count);
    if ($category) {
        $stmt_count->bindParam(':category', $category, PDO::PARAM_STR);
    }
    $stmt_count->execute();
    $total_rituals = $stmt_count->fetchColumn();
    
    // Récupération des rituels
    $stmt = $pdo->prepare($sql);
    if ($category) {
        $stmt->bindParam(':category', $category, PDO::PARAM_STR);
    }
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $rituals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des rituels: " . $e->getMessage());
}

// Calcul du nombre total de pages
$total_pages = ceil($total_rituals / $per_page);

// Récupération des catégories pour le filtre
$categories = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM rituals WHERE status = 'published' AND category IS NOT NULL AND category != '' ORDER BY category");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categories[] = $row['category'];
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des catégories: " . $e->getMessage());
}

// Titre de la page
$page_title = "Rituels et Services Magiques - Mystica Occulta";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <!-- Meta tags pour SEO -->
    <meta name="description" content="Découvrez notre collection de rituels magiques et services ésotériques pour l'amour, la prospérité, la protection et plus encore.">
    
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
                    <a href="rituals.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300 border-b-2 border-pink-500">Rituels</a>
                    <a href="blog.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Blog</a>
                    <a href="about.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">À propos</a>
                    <a href="contact.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Contact</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="bg-mystic py-16">
        <div class="container mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-5xl font-cinzel font-bold text-white mb-6">Rituels & Services Magiques</h1>
            <p class="text-xl text-gray-300 max-w-3xl mx-auto mb-8">Découvrez notre collection de rituels pour l'amour, la prospérité, la protection et bien plus encore. Chaque rituel est conçu pour vous aider à atteindre vos objectifs et transformer votre vie.</p>
        </div>
    </section>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-12">
        <!-- Filter by category -->
        <?php if (!empty($categories)): ?>
        <div class="mb-8">
            <h2 class="text-2xl font-cinzel font-bold text-white mb-4">Filtrer par catégorie</h2>
            <div class="flex flex-wrap gap-2">
                <a href="rituals.php" class="category-filter px-4 py-2 rounded-full border border-purple-600 text-white hover:bg-purple-800 transition <?php echo !$category ? 'active' : ''; ?>">
                    Tous les rituels
                </a>
                <?php foreach ($categories as $cat): ?>
                <a href="rituals.php?category=<?php echo urlencode($cat); ?>" class="category-filter px-4 py-2 rounded-full border border-purple-600 text-white hover:bg-purple-800 transition <?php echo $category === $cat ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($cat); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Rituals Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php if (empty($rituals)): ?>
                <div class="col-span-3 text-center py-12">
                    <h2 class="text-2xl font-cinzel text-gray-400 mb-4">Aucun rituel trouvé</h2>
                    <p class="text-gray-500">Aucun rituel n'est disponible pour le moment dans cette catégorie.</p>
                </div>
            <?php else: ?>
                <?php foreach ($rituals as $ritual): ?>
                <a href="ritual.php?slug=<?php echo urlencode($ritual['slug']); ?>" class="card rounded-xl overflow-hidden shadow-lg">
                    <div class="relative h-64">
                        <?php if (isset($ritual['featured_image']) && !empty($ritual['featured_image'])): ?>
                            <?php if (substr($ritual['featured_image'], 0, 4) === 'http'): ?>
                                <img src="<?php echo htmlspecialchars($ritual['featured_image']); ?>" alt="<?php echo isset($ritual['title']) ? htmlspecialchars($ritual['title']) : ''; ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($ritual['featured_image']); ?>" alt="<?php echo isset($ritual['title']) ? htmlspecialchars($ritual['title']) : ''; ?>" class="w-full h-full object-cover">
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="w-full h-full bg-gradient-to-br from-purple-900 to-indigo-900"></div>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent opacity-60"></div>
                        <?php if (isset($ritual['price']) && !empty($ritual['price'])): ?>
                        <div class="absolute bottom-4 right-4 bg-purple-600 text-white px-3 py-1 rounded-full text-sm font-bold">
                            <?php echo htmlspecialchars($ritual['price']); ?> €
                        </div>
                        <?php endif; ?>
                        <?php if (isset($ritual['category']) && !empty($ritual['category'])): ?>
                        <div class="absolute top-4 left-4 bg-indigo-900 bg-opacity-80 text-white px-3 py-1 rounded-full text-xs">
                            <?php echo htmlspecialchars($ritual['category']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-white mb-2"><?php echo isset($ritual['title']) ? htmlspecialchars($ritual['title']) : ''; ?></h3>
                        <?php if (isset($ritual['excerpt']) && !empty($ritual['excerpt'])): ?>
                        <p class="text-gray-400 mb-4 line-clamp-3"><?php echo htmlspecialchars(substr($ritual['excerpt'], 0, 120)) . (strlen($ritual['excerpt']) > 120 ? '...' : ''); ?></p>
                        <?php endif; ?>
                        <?php if (isset($ritual['duration']) && !empty($ritual['duration'])): ?>
                        <div class="text-gray-500 text-sm mb-4">
                            <span class="inline-block mr-4"><i class="fas fa-clock mr-1"></i><?php echo htmlspecialchars($ritual['duration']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="button-magic w-full py-2 rounded-lg text-white text-center font-medium">
                            Voir le rituel
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="flex justify-center mt-12">
            <div class="inline-flex">
                <?php if ($page > 1): ?>
                <a href="rituals.php?page=<?php echo $page-1; ?><?php echo $category ? '&category='.urlencode($category) : ''; ?>" class="px-4 py-2 mx-1 rounded-lg bg-gray-800 text-white hover:bg-gray-700 transition">
                    <i class="fas fa-chevron-left mr-1"></i> Précédent
                </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="rituals.php?page=<?php echo $i; ?><?php echo $category ? '&category='.urlencode($category) : ''; ?>" class="px-4 py-2 mx-1 rounded-lg <?php echo $i === $page ? 'bg-purple-700 text-white' : 'bg-gray-800 text-white hover:bg-gray-700'; ?> transition">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="rituals.php?page=<?php echo $page+1; ?><?php echo $category ? '&category='.urlencode($category) : ''; ?>" class="px-4 py-2 mx-1 rounded-lg bg-gray-800 text-white hover:bg-gray-700 transition">
                    Suivant <i class="fas fa-chevron-right ml-1"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Call to action -->
        <div class="bg-mystic rounded-lg p-8 text-center mt-16">
            <h2 class="text-3xl font-cinzel font-bold text-white mb-4">Besoin d'un rituel personnalisé ?</h2>
            <p class="text-gray-300 mb-6 max-w-2xl mx-auto">Chaque situation est unique. Si vous ne trouvez pas le rituel adapté à vos besoins spécifiques, contactez-moi pour discuter d'une solution sur mesure.</p>
            <a href="contact.php" class="button-magic px-8 py-4 rounded-full text-white font-medium shadow-lg inline-block">
                Demander une consultation
            </a>
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
