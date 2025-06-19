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

// Vérifier si un slug de produit est fourni
if (!isset($_GET['slug']) || empty($_GET['slug'])) {
    header('Location: shop.php');
    exit;
}

$slug = $_GET['slug'];
$product = null;
$related_products = [];

try {
    // Récupérer le produit par son slug
    $stmt = $pdo->prepare("SELECT * FROM products WHERE slug = :slug AND status = 'published'");
    $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        // Produit non trouvé
        header('Location: shop.php');
        exit;
    }
    
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Récupérer les produits associés (même catégorie)
    if (!empty($product['category'])) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE category = :category AND id != :id AND status = 'published' ORDER BY RAND() LIMIT 3");
        $stmt->bindParam(':category', $product['category'], PDO::PARAM_STR);
        $stmt->bindParam(':id', $product['id'], PDO::PARAM_INT);
        $stmt->execute();
        $related_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération du produit: " . $e->getMessage());
    // Rediriger en cas d'erreur
    header('Location: shop.php');
    exit;
}

// Titre de la page
$page_title = htmlspecialchars($product['title']) . " - Mystica Occulta";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Meta tags pour SEO -->
    <meta name="description" content="<?php echo htmlspecialchars(substr($product['description'], 0, 160)); ?>">
    
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
<body>
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

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-12">
        <!-- Breadcrumbs -->
        <div class="mb-8 text-gray-400">
            <a href="index.php" class="hover:text-white">Accueil</a> 
            <i class="fas fa-chevron-right mx-2 text-xs"></i> 
            <a href="shop.php" class="hover:text-white">Boutique</a>
            <?php if (!empty($product['category'])): ?>
                <i class="fas fa-chevron-right mx-2 text-xs"></i> 
                <a href="shop.php?category=<?php echo urlencode($product['category']); ?>" class="hover:text-white"><?php echo htmlspecialchars($product['category']); ?></a>
            <?php endif; ?>
            <i class="fas fa-chevron-right mx-2 text-xs"></i> 
            <span class="text-gray-300"><?php echo htmlspecialchars($product['title']); ?></span>
        </div>

        <!-- Product Details -->
        <div class="bg-gray-900 rounded-xl overflow-hidden shadow-2xl mb-12">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Product Image -->
                <div class="p-6">
                    <div class="bg-gradient-to-br from-purple-900 to-indigo-900 rounded-lg h-80 flex items-center justify-center overflow-hidden">
                        <?php if (isset($product['featured_image']) && !empty($product['featured_image'])): ?>
                            <img src="<?php echo htmlspecialchars($product['featured_image']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fas fa-magic text-8xl text-white"></i>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Product Info -->
                <div class="p-6">
                    <?php if (!empty($product['category'])): ?>
                        <div class="inline-block bg-purple-900 text-white px-3 py-1 rounded-full text-xs uppercase tracking-wide mb-4">
                            <?php echo htmlspecialchars($product['category']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <h1 class="text-3xl md:text-4xl font-cinzel font-bold text-white mb-4"><?php echo htmlspecialchars($product['title']); ?></h1>
                    
                    <div class="text-2xl text-pink-500 font-bold mb-6"><?php echo number_format($product['price'], 2); ?>€</div>
                    
                    <div class="prose prose-lg text-gray-300 mb-8">
                        <?php echo $product['description']; ?>
                    </div>
                    
                    <div class="mb-6">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-box text-purple-500 mr-2"></i>
                            <span class="text-white">
                                <?php if ($product['stock'] > 0): ?>
                                    En stock (<?php echo $product['stock']; ?> disponibles)
                                <?php else: ?>
                                    En rupture de stock
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap gap-4">
                        <?php if ($product['stock'] > 0): ?>
                            <button class="button-magic px-6 py-3 rounded-full text-white font-medium" onclick="contactForOrder('<?php echo htmlspecialchars($product['title']); ?>', <?php echo $product['price']; ?>)">
                                <i class="fas fa-shopping-cart mr-2"></i> Commander
                            </button>
                        <?php else: ?>
                            <button class="bg-gray-700 px-6 py-3 rounded-full text-white font-medium opacity-70 cursor-not-allowed">
                                <i class="fas fa-shopping-cart mr-2"></i> Indisponible
                            </button>
                        <?php endif; ?>
                        
                        <a href="contact.php?subject=demande-produit&product=<?php echo urlencode($product['title']); ?>" class="border border-purple-600 hover:bg-purple-600 transition-colors px-6 py-3 rounded-full text-white font-medium">
                            <i class="fas fa-question-circle mr-2"></i> Demander plus d'informations
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Related Products -->
        <?php if (!empty($related_products)): ?>
        <div class="mt-16">
            <h2 class="text-2xl font-cinzel font-bold mb-8 text-white">Articles similaires</h2>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($related_products as $related): ?>
                <div class="product-card rounded-xl overflow-hidden">
                    <div class="relative">
                        <div class="w-full h-48 bg-gradient-to-br from-purple-900 to-indigo-900 flex items-center justify-center">
                            <?php if (isset($related['featured_image']) && !empty($related['featured_image'])): ?>
                                <img src="<?php echo htmlspecialchars($related['featured_image']); ?>" alt="<?php echo htmlspecialchars($related['title']); ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fas fa-magic text-6xl text-white"></i>
                            <?php endif; ?>
                        </div>
                        <?php if (isset($related['category']) && !empty($related['category'])): ?>
                        <div class="absolute top-3 left-3 bg-purple-900 bg-opacity-80 text-white px-3 py-1 rounded-full text-xs">
                            <?php echo htmlspecialchars($related['category']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-4">
                        <h3 class="font-cinzel text-xl font-bold mb-2 text-white"><?php echo htmlspecialchars($related['title']); ?></h3>
                        <p class="text-gray-400 text-sm mb-3">
                            <?php 
                                $description = isset($related['description']) ? $related['description'] : '';
                                echo htmlspecialchars(substr($description, 0, 100)) . (strlen($description) > 100 ? '...' : '');
                            ?>
                        </p>
                        <div class="flex justify-between items-center">
                            <span class="text-pink-500 font-bold"><?php echo number_format($related['price'], 2); ?>€</span>
                            <div class="flex space-x-2">
                                <button onclick="contactForOrder('<?php echo addslashes($related['title']); ?>', <?php echo $related['price']; ?>)" class="text-white bg-purple-600 hover:bg-purple-700 px-3 py-1 rounded-full text-sm transition duration-300">
                                    <i class="fas fa-cart-plus mr-1"></i> Acheter
                                </button>
                                <a href="product.php?slug=<?php echo urlencode($related['slug']); ?>" class="text-white bg-pink-600 hover:bg-pink-700 px-3 py-1 rounded-full text-sm transition duration-300">
                                    <i class="fas fa-eye mr-1"></i> Détails
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Call to action -->
        <div class="bg-mystic rounded-lg p-8 text-center mt-16">
            <h2 class="text-3xl font-cinzel font-bold text-white mb-4">Vous souhaitez consulter toute notre collection?</h2>
            <p class="text-gray-300 mb-6 max-w-2xl mx-auto">Découvrez notre gamme complète d'objets ésotériques et magiques pour tous vos besoins spirituels.</p>
            <a href="shop.php" class="button-magic px-8 py-4 rounded-full text-white font-medium shadow-lg inline-block">
                Voir tous les produits
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
                            <a href="https://wa.me/22967512021" target="_blank" class="text-gray-400 hover:text-purple-400 transition">+229 67 51 20 21</a>
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

    <!-- Modal de confirmation de commande -->
    <div id="orderConfirmModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
        <div class="bg-gray-900 border border-purple-500 rounded-xl p-6 max-w-md w-full mx-4 shadow-2xl transform transition-all">
            <div class="text-center mb-4">
                <i class="fas fa-shopping-cart text-purple-500 text-4xl mb-4"></i>
                <h3 class="text-xl font-cinzel font-bold text-white" id="modalProductTitle">Confirmation de commande</h3>
            </div>
            <div class="text-gray-300 mb-6 text-center">
                <p id="modalConfirmText">Vous allez commander ce produit. Continuer?</p>
                <p class="mt-3 text-sm text-gray-400">Vous serez redirigé vers WhatsApp pour finaliser votre commande.</p>
            </div>
            <div class="flex justify-center space-x-4">
                <button id="cancelOrderBtn" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-full transition-colors">
                    <i class="fas fa-times mr-2"></i>Annuler
                </button>
                <button id="confirmOrderBtn" class="px-4 py-2 button-magic text-white rounded-full">
                    <i class="fas fa-check mr-2"></i>Confirmer
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript pour les fonctionnalités -->
    <script>
        // Variables globales pour stocker les informations de commande
        let currentProductName = '';
        let currentProductPrice = 0;
        
        function contactForOrder(productName, price) {
            // Stocker les informations du produit
            currentProductName = productName;
            currentProductPrice = price;
            
            // Mettre à jour le texte du modal
            document.getElementById('modalProductTitle').textContent = 'Confirmation de commande';
            document.getElementById('modalConfirmText').textContent = 
                `Vous allez commander "${productName}" pour ${price}€. Continuer?`;
            
            // Afficher le modal
            document.getElementById('orderConfirmModal').classList.remove('hidden');
        }
        
        // Gestionnaire pour le bouton de confirmation
        document.getElementById('confirmOrderBtn').addEventListener('click', function() {
            // Cacher le modal
            document.getElementById('orderConfirmModal').classList.add('hidden');
            
            // Redirection vers WhatsApp avec le message préformaté
            const phoneNumber = "22967512021"; // Format correct pour WhatsApp
            const message = `Bonjour, je souhaite commander le produit: ${currentProductName} (${currentProductPrice}€). Pouvez-vous me donner les informations sur les moyens de paiement disponibles ?`;
            window.location.href = `https://api.whatsapp.com/send?phone=${phoneNumber}&text=${encodeURIComponent(message)}`;
        });
        
        // Gestionnaire pour le bouton d'annulation
        document.getElementById('cancelOrderBtn').addEventListener('click', function() {
            // Simplement cacher le modal
            document.getElementById('orderConfirmModal').classList.add('hidden');
        });
        
        // Fermer le modal si l'utilisateur clique en dehors
        document.getElementById('orderConfirmModal').addEventListener('click', function(event) {
            if (event.target === this) {
                this.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
