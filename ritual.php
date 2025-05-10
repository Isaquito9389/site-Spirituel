<?php
// Affichage des erreurs en mode développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusion de la connexion à la base de données
require_once 'admin/includes/db_connect.php';

// Vérification que l'ID du rituel est bien fourni
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: rituals.php');
    exit;
}

// Récupération de l'ID du rituel
$ritual_id = intval($_GET['id']);

// Récupération du rituel depuis la base de données
$ritual = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM rituals WHERE id = ? AND status = 'published'");
    $stmt->execute([$ritual_id]);
    $ritual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ritual) {
        // Rituel non trouvé ou non publié
        header('Location: rituals.php');
        exit;
    }
} catch (PDOException $e) {
    // En cas d'erreur, on redirige vers la liste des rituels
    error_log("Erreur lors de la récupération du rituel: " . $e->getMessage());
    header('Location: rituals.php');
    exit;
}

// Récupération des rituels similaires (même catégorie)
$similar_rituals = [];
if (!empty($ritual['category'])) {
    try {
        $stmt = $pdo->prepare("SELECT id, title, featured_image, price FROM rituals 
                              WHERE category = ? AND id != ? AND status = 'published' 
                              ORDER BY created_at DESC LIMIT 3");
        $stmt->execute([$ritual['category'], $ritual_id]);
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
    <meta property="og:title" content="<?php echo htmlspecialchars($ritual['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(substr(strip_tags($ritual['excerpt'] ?: $ritual['content']), 0, 160)); ?>">
    <?php if (!empty($ritual['featured_image'])): ?>
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
        }
        
        .content-area img {
            max-width: 100%;
            height: auto;
            border-radius: 0.5rem;
            margin: 1.5rem 0;
        }
        
        .card {
            background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
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
                    <a href="rituals.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Rituels</a>
                    <a href="blog.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Blog</a>
                    <a href="about.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">À propos</a>
                    <a href="contact.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Contact</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <div class="max-w-5xl mx-auto">
            <!-- Breadcrumbs -->
            <div class="mb-6 text-gray-400">
                <a href="index.php" class="hover:text-pink-400 transition">Accueil</a> &raquo; 
                <a href="rituals.php" class="hover:text-pink-400 transition">Rituels</a> &raquo; 
                <span class="text-purple-400"><?php echo htmlspecialchars($ritual['title']); ?></span>
            </div>

            <!-- Ritual Header -->
            <div class="bg-gradient-to-r from-purple-900 to-indigo-900 rounded-lg overflow-hidden shadow-xl mb-8">
                <div class="p-8 md:p-12">
                    <div class="flex flex-col md:flex-row">
                        <?php if (!empty($ritual['featured_image'])): ?>
                        <div class="md:w-1/3 mb-6 md:mb-0 md:mr-8">
                            <div class="relative h-64 md:h-full rounded-lg overflow-hidden shadow-lg">
                                <?php if (substr($ritual['featured_image'], 0, 4) === 'http'): ?>
                                    <img src="<?php echo htmlspecialchars($ritual['featured_image']); ?>" alt="<?php echo htmlspecialchars($ritual['title']); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($ritual['featured_image']); ?>" alt="<?php echo htmlspecialchars($ritual['title']); ?>" class="w-full h-full object-cover">
                                <?php endif; ?>
                                <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent opacity-50"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="<?php echo !empty($ritual['featured_image']) ? 'md:w-2/3' : 'w-full'; ?>">
                            <h1 class="text-4xl md:text-5xl font-cinzel font-bold text-white mb-4"><?php echo htmlspecialchars($ritual['title']); ?></h1>
                            
                            <?php if (!empty($ritual['excerpt'])): ?>
                            <div class="text-lg text-gray-300 mb-6">
                                <?php echo nl2br(htmlspecialchars($ritual['excerpt'])); ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex flex-wrap mb-6">
                                <?php if (!empty($ritual['category'])): ?>
                                <div class="mr-6 mb-3">
                                    <span class="text-gray-400"><i class="fas fa-tag mr-2"></i>Catégorie:</span>
                                    <span class="text-purple-300 ml-1"><?php echo htmlspecialchars($ritual['category']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($ritual['duration'])): ?>
                                <div class="mr-6 mb-3">
                                    <span class="text-gray-400"><i class="fas fa-clock mr-2"></i>Durée:</span>
                                    <span class="text-purple-300 ml-1"><?php echo htmlspecialchars($ritual['duration']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($ritual['price'])): ?>
                                <div class="mb-3">
                                    <span class="text-gray-400"><i class="fas fa-coins mr-2"></i>Prix:</span>
                                    <span class="text-pink-300 font-bold ml-1"><?php echo htmlspecialchars($ritual['price']); ?> €</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex flex-wrap">
                                <a href="contact.php?ritual=<?php echo $ritual_id; ?>" class="button-magic px-6 py-3 rounded-full text-white font-medium shadow-lg mr-4 mb-2">
                                    <i class="fas fa-envelope mr-2"></i>Demander ce rituel
                                </a>
                                <a href="https://wa.me/?text=<?php echo urlencode('Je suis intéressé(e) par votre rituel: ' . $ritual['title']); ?>" target="_blank" class="px-6 py-3 rounded-full bg-green-600 text-white font-medium shadow-lg hover:bg-green-700 transition mb-2">
                                    <i class="fab fa-whatsapp mr-2"></i>WhatsApp
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ritual Content -->
            <div class="bg-gray-900 bg-opacity-60 rounded-lg overflow-hidden shadow-xl p-8 mb-12">
                <div class="content-area text-gray-200">
                    <?php echo $ritual['content']; ?>
                </div>
            </div>

            <!-- Similar Rituals -->
            <?php if (!empty($similar_rituals)): ?>
            <div class="mb-12">
                <h2 class="text-3xl font-cinzel font-bold text-white mb-6">Rituels similaires</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($similar_rituals as $similar): ?>
                    <a href="ritual.php?id=<?php echo $similar['id']; ?>" class="card rounded-xl overflow-hidden shadow-lg hover:shadow-xl transition duration-300">
                        <div class="relative h-48">
                            <?php if (!empty($similar['featured_image'])): ?>
                                <?php if (substr($similar['featured_image'], 0, 4) === 'http'): ?>
                                    <img src="<?php echo htmlspecialchars($similar['featured_image']); ?>" alt="<?php echo htmlspecialchars($similar['title']); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($similar['featured_image']); ?>" alt="<?php echo htmlspecialchars($similar['title']); ?>" class="w-full h-full object-cover">
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="w-full h-full bg-gradient-to-br from-purple-900 to-indigo-900"></div>
                            <?php endif; ?>
                            <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent opacity-60"></div>
                            <?php if (!empty($similar['price'])): ?>
                            <div class="absolute bottom-4 right-4 bg-purple-600 text-white px-3 py-1 rounded-full text-sm font-bold">
                                <?php echo htmlspecialchars($similar['price']); ?> €
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-6">
                            <h3 class="text-xl font-bold text-white mb-2"><?php echo htmlspecialchars($similar['title']); ?></h3>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Call to action -->
            <div class="bg-mystic rounded-lg p-8 text-center mb-12">
                <h2 class="text-3xl font-cinzel font-bold text-white mb-4">Vous avez des questions ?</h2>
                <p class="text-gray-300 mb-6">N'hésitez pas à me contacter pour plus d'informations sur ce rituel ou pour discuter de vos besoins spécifiques.</p>
                <div class="flex justify-center">
                    <a href="contact.php" class="button-magic px-8 py-4 rounded-full text-white font-medium shadow-lg text-lg">
                        Contactez-moi maintenant
                    </a>
                </div>
            </div>
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
