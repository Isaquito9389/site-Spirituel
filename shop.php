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

// Vérification de l'existence de la table products
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'products'");
    if ($stmt->rowCount() === 0) {
        // Créer la table products si elle n'existe pas
        $pdo->exec("CREATE TABLE products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            category VARCHAR(100),
            featured_image VARCHAR(255),
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            stock INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Créer quelques produits de démonstration
        $demo_products = [
            [
                'title' => 'Bougie rituelle de protection',
                'slug' => 'bougie-rituelle-protection',
                'description' => 'Bougie noire conçue spécialement pour les rituels de protection. Efficace pour éloigner les énergies négatives.',
                'price' => 19.95,
                'category' => 'Bougies',
                'featured_image' => 'assets/images/products/bougie-protection.jpg',
                'status' => 'published',
                'stock' => 15
            ],
            [
                'title' => 'Encens d\'Améthyste',
                'slug' => 'encens-amethyste',
                'description' => 'Encens naturel à base d\'améthyste pour favoriser la méditation et la purification spirituelle.',
                'price' => 12.50,
                'category' => 'Encens',
                'featured_image' => 'assets/images/products/encens-amethyste.jpg',
                'status' => 'published',
                'stock' => 25
            ],
            [
                'title' => 'Pendentif de Chance',
                'slug' => 'pendentif-chance',
                'description' => 'Pendentif en argent conçu pour attirer chance et opportunités. Béni pendant la pleine lune.',
                'price' => 39.99,
                'category' => 'Bijoux',
                'featured_image' => 'assets/images/products/pendentif-chance.jpg',
                'status' => 'published',
                'stock' => 8
            ],
            [
                'title' => 'Cristal de Quartz Rose',
                'slug' => 'cristal-quartz-rose',
                'description' => 'Pierre d\'amour et d\'harmonie. Idéale pour les rituels d\'attraction amoureuse.',
                'price' => 24.75,
                'category' => 'Cristaux',
                'featured_image' => 'assets/images/products/quartz-rose.jpg',
                'status' => 'published',
                'stock' => 12
            ]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO products (title, slug, description, price, category, featured_image, status, stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($demo_products as $product) {
            $stmt->execute([
                $product['title'],
                $product['slug'],
                $product['description'],
                $product['price'],
                $product['category'],
                $product['featured_image'],
                $product['status'],
                $product['stock']
            ]);
        }
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la vérification/création de la table products: " . $e->getMessage());
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 12; // Nombre de produits par page
$offset = ($page - 1) * $per_page;

// Filtres
$category = isset($_GET['category']) ? $_GET['category'] : null;

// Construction de la requête
$sql_count = "SELECT COUNT(*) FROM products WHERE status = 'published'";
$sql = "SELECT * FROM products WHERE status = 'published'";

// Application des filtres
if ($category) {
    $sql .= " AND category = :category";
    $sql_count .= " AND category = :category";
}

// Tri
$sql .= " ORDER BY created_at DESC";

// Limite pour pagination
$sql .= " LIMIT :offset, :per_page";

// Récupération des produits
$products = [];
$total_products = 0;

try {
    // Compte total pour pagination
    $stmt_count = $pdo->prepare($sql_count);
    if ($category) {
        $stmt_count->bindParam(':category', $category, PDO::PARAM_STR);
    }
    $stmt_count->execute();
    $total_products = $stmt_count->fetchColumn();
    
    // Récupération des produits
    $stmt = $pdo->prepare($sql);
    if ($category) {
        $stmt->bindParam(':category', $category, PDO::PARAM_STR);
    }
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des produits: " . $e->getMessage());
}

// Calcul du nombre total de pages
$total_pages = ceil($total_products / $per_page);

// Récupération des catégories pour le filtre
$categories = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE status = 'published' AND category IS NOT NULL AND category != '' ORDER BY category");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categories[] = $row['category'];
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des catégories: " . $e->getMessage());
}

// Titre de la page
$page_title = "Boutique Ésotérique - Mystica Occulta";
if ($category) {
    $page_title = htmlspecialchars($category) . " - Boutique Mystica Occulta";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <!-- Meta tags pour SEO -->
    <meta name="description" content="Découvrez notre collection d'objets magiques et ésotériques pour amplifier vos rituels et pratiques spirituelles.">
    
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
            box-shadow: 0 4px 15px rgba(247, 37, 133, 0.4);
            transition: all 0.3s ease;
        }
        
        .button-magic:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(247, 37, 133, 0.6);
        }
        
        .category-filter.active {
            background-color: #7209b7;
            color: white;
        }
        
        .product-card {
            background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }
        
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(247, 37, 133, 0.4);
        }
    </style>
</head>
<body class="bg-dark">
    <!-- Header -->
    <header class="bg-gradient-to-r from-purple-900 to-indigo-900 text-white shadow-lg">
        <div class="container mx-auto px-4 py-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <a href="index.php" class="flex items-center mb-4 md:mb-0">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-600 to-pink-600 flex items-center justify-center mr-3">
                        <i class="fas fa-eye text-white text-xl"></i>
                    </div>
                    <span class="font-cinzel text-2xl font-bold">Mystica Occulta</span>
                </a>
                <nav class="flex space-x-6">
                    <a href="index.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Accueil</a>
                    <a href="rituals.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Rituels</a>
                    <a href="blog.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Blog</a>
                    <a href="shop.php" class="px-4 py-2 text-pink-300 font-medium border-b-2 border-pink-500">Boutique</a>
                    <a href="contact.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Contact</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="bg-mystic py-16">
        <div class="container mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-5xl font-cinzel font-bold text-white mb-6">Boutique Ésotérique</h1>
            <p class="text-xl text-gray-300 max-w-3xl mx-auto">Découvrez notre collection d'objets magiques et ésotériques pour amplifier vos rituels et pratiques spirituelles.</p>
        </div>
    </section>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-12">
        <!-- Filter by category -->
        <?php if (!empty($categories)): ?>
        <div class="mb-8">
            <h2 class="text-2xl font-cinzel font-bold text-white mb-4">Filtrer par catégorie</h2>
            <div class="flex flex-wrap gap-2">
                <a href="shop.php" class="category-filter px-4 py-2 rounded-full border border-purple-600 text-white hover:bg-purple-900 transition <?php echo !$category ? 'active' : ''; ?>">
                    Tous
                </a>
                <?php foreach ($categories as $cat): ?>
                <a href="shop.php?category=<?php echo urlencode($cat); ?>" class="category-filter px-4 py-2 rounded-full border border-purple-600 text-white hover:bg-purple-900 transition <?php echo $category === $cat ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($cat); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Products Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <?php if (empty($products)): ?>
                <div class="col-span-full text-center py-12">
                    <h2 class="text-2xl font-cinzel text-gray-400 mb-4">Aucun produit trouvé</h2>
                    <p class="text-gray-500">Aucun produit n'est disponible pour le moment dans cette catégorie.</p>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                <div class="product-card rounded-xl overflow-hidden">
                    <div class="relative">
                        <div class="w-full h-48 bg-gradient-to-br from-purple-900 to-indigo-900 flex items-center justify-center">
                            <?php if (isset($product['featured_image']) && !empty($product['featured_image'])): ?>
                                <?php if (substr($product['featured_image'], 0, 4) === 'http'): ?>
                                    <img src="<?php echo htmlspecialchars($product['featured_image']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($product['featured_image']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" class="w-full h-full object-cover">
                                <?php endif; ?>
                            <?php else: ?>
                                <i class="fas fa-magic text-6xl text-white"></i>
                            <?php endif; ?>
                        </div>
                        <?php if (isset($product['category']) && !empty($product['category'])): ?>
                        <div class="absolute top-3 left-3 bg-purple-900 bg-opacity-80 text-white px-3 py-1 rounded-full text-xs">
                            <?php echo htmlspecialchars($product['category']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-4">
                        <h3 class="font-cinzel text-xl font-bold mb-2 text-white"><?php echo htmlspecialchars($product['title']); ?></h3>
                        <p class="text-gray-400 text-sm mb-3">
                            <?php 
                                $description = isset($product['description']) ? $product['description'] : '';
                                echo htmlspecialchars(substr($description, 0, 100)) . (strlen($description) > 100 ? '...' : '');
                            ?>
                        </p>
                        <div class="flex justify-between items-center">
                            <span class="text-pink-500 font-bold"><?php echo number_format($product['price'], 2); ?>€</span>
                            <a href="product.php?slug=<?php echo urlencode($product['slug']); ?>" class="text-white bg-pink-600 hover:bg-pink-700 px-3 py-1 rounded-full text-sm transition duration-300">
                                <i class="fas fa-shopping-cart mr-1"></i> Voir détails
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="flex justify-center mt-12">
            <div class="inline-flex rounded-md shadow-sm">
                <?php if ($page > 1): ?>
                <a href="shop.php?page=<?php echo $page-1; ?><?php echo $category ? '&category='.urlencode($category) : ''; ?>" class="px-4 py-2 mx-1 rounded-lg bg-gray-800 text-white hover:bg-gray-700 transition">
                    <i class="fas fa-chevron-left mr-1"></i> Précédent
                </a>
                <?php endif; ?>
                
                <?php 
                $start_page = max(1, $page - 2);
                $end_page = min($start_page + 4, $total_pages);
                
                for ($i = $start_page; $i <= $end_page; $i++): 
                ?>
                <a href="shop.php?page=<?php echo $i; ?><?php echo $category ? '&category='.urlencode($category) : ''; ?>" class="px-4 py-2 mx-1 rounded-lg <?php echo ($i == $page) ? 'bg-purple-700 text-white' : 'bg-gray-800 text-white hover:bg-gray-700'; ?> transition">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="shop.php?page=<?php echo $page+1; ?><?php echo $category ? '&category='.urlencode($category) : ''; ?>" class="px-4 py-2 mx-1 rounded-lg bg-gray-800 text-white hover:bg-gray-700 transition">
                    Suivant <i class="fas fa-chevron-right ml-1"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Call to action -->
        <div class="bg-mystic rounded-lg p-8 text-center mt-16">
            <h2 class="text-3xl font-cinzel font-bold text-white mb-4">Vous cherchez un objet spécifique ?</h2>
            <p class="text-gray-300 mb-6 max-w-2xl mx-auto">Nous pouvons vous aider à trouver ou à créer des objets magiques personnalisés pour vos rituels et pratiques spirituelles.</p>
            <a href="contact.php?subject=custom-order" class="button-magic px-8 py-4 rounded-full text-white font-medium shadow-lg inline-block">
                Demander un produit sur mesure
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
                        <li><a href="shop.php" class="text-gray-400 hover:text-purple-400 transition">Boutique</a></li>
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

    <!-- JavaScript pour les fonctionnalités interactives -->
    <script>
        // Panier d'achat (version simple)
        let cart = JSON.parse(localStorage.getItem('mysticaCart')) || [];
        
        function addToCart(productId, productName, price) {
            cart.push({id: productId, name: productName, price: price});
            localStorage.setItem('mysticaCart', JSON.stringify(cart));
            alert(`${productName} a été ajouté à votre panier!`);
        }
    </script>
</body>
</html>
