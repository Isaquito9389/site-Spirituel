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
$per_page = 10; // Nombre de témoignages par page
$offset = ($page - 1) * $per_page;

// Construction de la requête
$sql_count = "SELECT COUNT(*) FROM testimonials WHERE status = 'approved'";
$sql = "SELECT * FROM testimonials WHERE status = 'approved'";

// Tri
$sql .= " ORDER BY created_at DESC";

// Limite pour pagination
$sql .= " LIMIT :offset, :per_page";

// Récupération des témoignages
$testimonials = [];
$total_testimonials = 0;

try {
    // Compte total pour pagination
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute();
    $total_testimonials = $stmt_count->fetchColumn();
    
    // Récupération des témoignages
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des témoignages: " . $e->getMessage());
}

// Calcul du nombre total de pages
$total_pages = ceil($total_testimonials / $per_page);

// Titre de la page
$page_title = "Témoignages de nos clients - Mystica Occulta";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <!-- Meta tags pour SEO -->
    <meta name="description" content="Découvrez les témoignages sincères de nos clients qui ont bénéficié de nos rituels et consultations spirituelles.">
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400;1,700;1,900&display=swap');
        
        body {
            font-family: 'Merriweather', serif;
            background-color: #0f0e17;
            color: #e8e8e8;
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
        
        .testimonial-card {
            background: linear-gradient(145deg, #16213e 0%, #1a1a2e 100%);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }
        
        .testimonial-card:hover {
            transform: translateY(-5px);
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
                    <a href="testimonials.php" class="px-4 py-2 text-pink-300 font-medium border-b-2 border-pink-500">Témoignages</a>
                    <a href="contact.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300">Contact</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="bg-mystic py-16">
        <div class="container mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-5xl font-cinzel font-bold text-white mb-6">Témoignages de nos Clients</h1>
            <p class="text-xl text-gray-300 max-w-3xl mx-auto">Découvrez les expériences transformatrices vécues par ceux qui ont fait confiance à nos rituels et services spirituels.</p>
        </div>
    </section>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-12">
        <?php if (empty($testimonials)): ?>
            <div class="text-center py-16">
                <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-purple-900 bg-opacity-30 flex items-center justify-center">
                    <i class="fas fa-comment-slash text-4xl text-gray-500"></i>
                </div>
                <h2 class="text-2xl font-cinzel text-gray-400 mb-4">Aucun témoignage disponible pour le moment</h2>
                <p class="text-gray-500 max-w-md mx-auto">Nous travaillons actuellement à recueillir les témoignages de nos clients satisfaits. Revenez bientôt pour les découvrir.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($testimonials as $testimonial): ?>
                    <div class="testimonial-card rounded-xl p-6">
                        <div class="flex items-center mb-4">
                            <?php if (!empty($testimonial['author_image'])): ?>
                                <div class="w-12 h-12 rounded-full bg-gradient-to-br overflow-hidden mr-4">
                                    <img src="<?php echo htmlspecialchars($testimonial['author_image']); ?>" alt="<?php echo htmlspecialchars($testimonial['author_name']); ?>" class="w-full h-full object-cover">
                                </div>
                            <?php else: ?>
                                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-600 to-pink-600 flex items-center justify-center text-white font-bold mr-4">
                                    <?php 
                                        $initials = '';
                                        $name_parts = explode(' ', $testimonial['author_name']);
                                        foreach ($name_parts as $part) {
                                            $initials .= substr($part, 0, 1);
                                        }
                                        echo htmlspecialchars($initials);
                                    ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h4 class="font-bold text-white"><?php echo htmlspecialchars($testimonial['author_name']); ?></h4>
                                <div class="flex text-yellow-400 text-sm">
                                    <?php for ($i = 0; $i < $testimonial['rating']; $i++): ?>
                                        <i class="fas fa-star"></i>
                                    <?php endfor; ?>
                                    <?php for ($i = $testimonial['rating']; $i < 5; $i++): ?>
                                        <i class="far fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        <p class="text-gray-300 italic mb-4">
                            "<?php echo htmlspecialchars($testimonial['content']); ?>"
                        </p>
                        <div class="text-sm text-gray-500">
                            <i class="fas fa-calendar-alt mr-2"></i> 
                            <?php echo date('d/m/Y', strtotime($testimonial['created_at'])); ?> 
                            <?php if (!empty($testimonial['service'])): ?>
                                - <?php echo htmlspecialchars($testimonial['service']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-12">
                <div class="inline-flex rounded-md shadow-sm">
                    <?php if ($page > 1): ?>
                    <a href="testimonials.php?page=<?php echo $page-1; ?>" class="px-4 py-2 mx-1 rounded-lg bg-gray-800 text-white hover:bg-gray-700 transition">
                        <i class="fas fa-chevron-left mr-1"></i> Précédent
                    </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($start_page + 4, $total_pages);
                    
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                    <a href="testimonials.php?page=<?php echo $i; ?>" class="px-4 py-2 mx-1 rounded-lg <?php echo ($i == $page) ? 'bg-purple-700 text-white' : 'bg-gray-800 text-white hover:bg-gray-700'; ?> transition">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="testimonials.php?page=<?php echo $page+1; ?>" class="px-4 py-2 mx-1 rounded-lg bg-gray-800 text-white hover:bg-gray-700 transition">
                        Suivant <i class="fas fa-chevron-right ml-1"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- CTA -->
        <div class="mt-16 bg-gradient-to-r from-purple-900 to-indigo-900 rounded-xl p-8 text-center">
            <h2 class="text-3xl font-cinzel font-bold text-white mb-4">Partagez votre expérience</h2>
            <p class="text-gray-300 mb-6 max-w-2xl mx-auto">Vous avez bénéficié de l'un de nos rituels ou services ? Nous serions honorés de connaître votre expérience. Votre témoignage pourrait aider d'autres personnes à trouver les solutions dont elles ont besoin.</p>
            <a href="contact.php?subject=testimonial" class="button-magic px-8 py-4 rounded-full text-white font-medium inline-block">
                Laisser un témoignage
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
                        <li><a href="testimonials.php" class="text-gray-400 hover:text-purple-400 transition">Témoignages</a></li>
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
                            <a href="https://wa.me/22967512021" target="_blank" class="text-gray-400 hover:text-purple-400 transition">+229 01 67 51 20 21</a>
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
