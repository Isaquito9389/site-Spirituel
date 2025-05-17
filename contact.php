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

// Vérification de l'existence de la table messages
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'messages'");
    if ($stmt->rowCount() === 0) {
        // Créer la table messages si elle n'existe pas
        $pdo->exec("CREATE TABLE messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50),
            subject VARCHAR(255),
            message TEXT NOT NULL,
            ritual_id INT,
            status VARCHAR(50) DEFAULT 'unread',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la vérification/création de la table messages: " . $e->getMessage());
}

// Récupération des informations du rituel si spécifié
$ritual = null;
$ritual_name = null;

// Vérification si une table rituals existe
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'rituals'");
    $ritualsTableExists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $ritualsTableExists = false;
    error_log("Erreur lors de la vérification de la table rituals: " . $e->getMessage());
}

// Si un rituel est spécifié dans l'URL (GET)
if (isset($_GET['ritual']) && !empty($_GET['ritual'])) {
    $ritual_param = htmlspecialchars(trim($_GET['ritual']), ENT_QUOTES, 'UTF-8');
    $ritual_name = $ritual_param; // Stocke le nom pour l'affichage même si on ne trouve pas le rituel
    
    // Si la table rituals existe, tente de récupérer le rituel par slug ou titre
    if ($ritualsTableExists) {
        try {
            // Essaie d'abord par slug
            $stmt = $pdo->prepare("SELECT * FROM rituals WHERE slug = :ritual AND status = 'published'");
            $stmt->bindParam(':ritual', $ritual_param, PDO::PARAM_STR);
            $stmt->execute();
            $ritual = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Si pas trouvé par slug, essaie par titre
            if (!$ritual) {
                $stmt = $pdo->prepare("SELECT * FROM rituals WHERE title LIKE :title AND status = 'published'");
                $search_title = "%{$ritual_param}%";
                $stmt->bindParam(':title', $search_title, PDO::PARAM_STR);
                $stmt->execute();
                $ritual = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la recherche du rituel: " . $e->getMessage());
            // Ne pas bloquer l'exécution - on peut toujours afficher un formulaire générique
        }
    }
}

// Traitement du formulaire
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sécurisation et filtrage des entrées pour prévenir les attaques XSS
    $name = isset($_POST['name']) ? htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8') : '';
    $email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
    $phone = isset($_POST['phone']) ? htmlspecialchars(trim($_POST['phone']), ENT_QUOTES, 'UTF-8') : '';
    $subject = isset($_POST['subject']) ? htmlspecialchars(trim($_POST['subject']), ENT_QUOTES, 'UTF-8') : '';
    $message = isset($_POST['message']) ? htmlspecialchars(trim($_POST['message']), ENT_QUOTES, 'UTF-8') : '';
    $ritual_id = isset($_POST['ritual_id']) ? intval($_POST['ritual_id']) : null;
    
    // Validation approfondie
    if (empty($name) || empty($email) || empty($message)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez entrer une adresse email valide.';
    } else {
        // Enregistrement du message dans la base de données avec protection contre les injections SQL
        try {
            // Vérifier si la table existe
            $stmt = $pdo->query("SHOW TABLES LIKE 'messages'");
            if ($stmt->rowCount() === 0) {
                throw new Exception("La table messages n'existe pas");
            }
            
            // Préparation de la requête avec paramètres pour éviter les injections SQL
            $stmt = $pdo->prepare("INSERT INTO messages (name, email, phone, subject, message, ritual_id, created_at) VALUES (:name, :email, :phone, :subject, :message, :ritual_id, NOW())");
            
            // Liaison des paramètres avec le type approprié
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
            $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
            $stmt->bindParam(':message', $message, PDO::PARAM_STR);
            $stmt->bindParam(':ritual_id', $ritual_id, PDO::PARAM_INT);
            
            // Exécution de la requête
            $stmt->execute();
            
            // Journalisation du succès
            error_log("Message enregistré avec succès de: " . $email);
            $success = true;
            
            // Envoi d'un email de confirmation (à activer en production)
            /*
            $headers = "From: Mystica Occulta <noreply@mysticaocculta.com>\r\n";
            $headers .= "Reply-To: contact@mysticaocculta.com\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            
            $message_body = '<p>Merci pour votre message, ' . $name . '.</p>';
            $message_body .= '<p>Nous avons bien reçu votre demande et vous répondrons dans les plus brefs délais.</p>';
            
            mail($email, 'Confirmation de votre message - Mystica Occulta', $message_body, $headers);
            
            // Notification à l'administrateur
            $admin_message = '<p>Nouveau message de: ' . $name . ' (' . $email . ')</p>';
            $admin_message .= '<p>Sujet: ' . $subject . '</p>';
            $admin_message .= '<p>Message: ' . $message . '</p>';
            
            mail('contact@mysticaocculta.com', 'Nouveau message: ' . $subject, $admin_message, $headers);
            */
            
        } catch (Exception $e) {
            $error = 'Une erreur est survenue lors de l\'envoi de votre message. Veuillez réessayer plus tard.';
            error_log("Erreur lors de l'enregistrement du message: " . $e->getMessage());
        }
    }
}

// Titre de la page
$page_title = "Contact - Mystica Occulta";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <!-- Meta tags pour SEO -->
    <meta name="description" content="Contactez Mystica Occulta pour toute question sur nos rituels, services ésotériques ou pour une consultation personnalisée.">
    
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
        
        input, textarea, select {
            background-color: rgba(30, 30, 46, 0.8);
            border: 1px solid #3a0ca3;
            color: white;
        }
        
        input:focus, textarea:focus, select:focus {
            border-color: #7209b7;
            outline: none;
            box-shadow: 0 0 0 2px rgba(114, 9, 183, 0.3);
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
                    <a href="contact.php" class="px-4 py-2 text-white hover:text-pink-300 transition duration-300 border-b-2 border-pink-500">Contact</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="bg-mystic py-16">
        <div class="container mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-5xl font-cinzel font-bold text-white mb-6">Contactez-moi</h1>
            <p class="text-xl text-gray-300 max-w-3xl mx-auto">Pour toute question sur mes services, rituels ou pour une consultation personnalisée, n'hésitez pas à me contacter.</p>
        </div>
    </section>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-12">
        <div class="max-w-5xl mx-auto">
            <?php if ($success): ?>
                <div class="bg-green-800 bg-opacity-30 border border-green-700 text-green-100 px-6 py-4 rounded-lg mb-8">
                    <h3 class="text-xl font-bold mb-2">Message envoyé avec succès !</h3>
                    <p>Merci de m'avoir contacté. Je vous répondrai dans les plus brefs délais.</p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="bg-red-800 bg-opacity-30 border border-red-700 text-red-100 px-6 py-4 rounded-lg mb-8">
                    <h3 class="text-xl font-bold mb-2">Erreur</h3>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Contact Form -->
                <div class="lg:w-2/3">
                    <div class="card p-8 rounded-lg shadow-lg">
                        <h2 class="text-2xl font-cinzel font-bold text-white mb-6">Formulaire de contact</h2>
                        
                        <?php if ($ritual || $ritual_name): ?>
                        <div class="bg-purple-900 bg-opacity-30 border border-purple-700 text-purple-100 px-6 py-4 rounded-lg mb-6">
                            <h3 class="text-lg font-bold mb-2">Demande concernant le rituel : <?php echo $ritual ? htmlspecialchars($ritual['title']) : htmlspecialchars($ritual_name); ?></h3>
                            <p>Veuillez remplir le formulaire ci-dessous pour me contacter au sujet de ce rituel.</p>
                        </div>
                        <?php endif; ?>
                        
                        <form action="contact.php" method="post" class="space-y-6">
                            <?php if ($ritual): ?>
                            <input type="hidden" name="ritual_id" value="<?php echo $ritual['id']; ?>">
                            <input type="hidden" name="subject" value="Demande de rituel : <?php echo htmlspecialchars($ritual['title']); ?>">
                            <?php elseif ($ritual_name): ?>
                            <input type="hidden" name="subject" value="Demande de rituel : <?php echo htmlspecialchars($ritual_name); ?>">
                            <?php endif; ?>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="name" class="block text-gray-300 mb-2">Nom complet *</label>
                                    <input type="text" id="name" name="name" required class="w-full px-4 py-2 rounded-lg" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                                </div>
                                
                                <div>
                                    <label for="email" class="block text-gray-300 mb-2">Email *</label>
                                    <input type="email" id="email" name="email" required class="w-full px-4 py-2 rounded-lg" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="phone" class="block text-gray-300 mb-2">Téléphone</label>
                                    <input type="tel" id="phone" name="phone" class="w-full px-4 py-2 rounded-lg" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                </div>
                                
                                <div>
                                    <label for="subject" class="block text-gray-300 mb-2">Sujet *</label>
                                    <input type="text" id="subject" name="subject" required class="w-full px-4 py-2 rounded-lg" value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ($ritual ? 'Demande de rituel : ' . htmlspecialchars($ritual['title']) : ''); ?>">
                                </div>
                            </div>
                            
                            <div>
                                <label for="message" class="block text-gray-300 mb-2">Message *</label>
                                <textarea id="message" name="message" rows="6" required class="w-full px-4 py-2 rounded-lg"><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ($ritual ? "Bonjour,\n\nJe suis intéressé(e) par le rituel \"" . htmlspecialchars($ritual['title']) . "\".\n\nPourriez-vous me donner plus d'informations ?\n\nMerci." : ''); ?></textarea>
                            </div>
                            
                            <div>
                                <button type="submit" class="button-magic px-8 py-3 rounded-lg text-white font-medium shadow-lg">
                                    Envoyer le message
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Contact Info -->
                <div class="lg:w-1/3">
                    <div class="card p-8 rounded-lg shadow-lg mb-8">
                        <h2 class="text-2xl font-cinzel font-bold text-white mb-6">Informations de contact</h2>
                        
                        <ul class="space-y-4">
                            <li class="flex items-start">
                                <i class="fas fa-envelope mt-1 mr-4 text-purple-400 text-xl"></i>
                                <div>
                                    <h3 class="font-bold text-white">Email</h3>
                                    <p class="text-gray-400">contact@mysticaocculta.com</p>
                                </div>
                            </li>
                            
                            <li class="flex items-start">
                                <i class="fab fa-whatsapp mt-1 mr-4 text-purple-400 text-xl"></i>
                                <div>
                                    <h3 class="font-bold text-white">WhatsApp</h3>
                                    <p class="text-gray-400">+33 XX XX XX XX</p>
                                </div>
                            </li>
                            
                            <li class="flex items-start">
                                <i class="fas fa-clock mt-1 mr-4 text-purple-400 text-xl"></i>
                                <div>
                                    <h3 class="font-bold text-white">Horaires de réponse</h3>
                                    <p class="text-gray-400">Du lundi au vendredi, de 10h à 18h</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="card p-8 rounded-lg shadow-lg">
                        <h2 class="text-2xl font-cinzel font-bold text-white mb-6">Suivez-moi</h2>
                        
                        <div class="flex justify-center space-x-4">
                            <a href="#" class="w-12 h-12 flex items-center justify-center rounded-full bg-blue-900 text-white hover:bg-blue-800 transition"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="w-12 h-12 flex items-center justify-center rounded-full bg-pink-700 text-white hover:bg-pink-600 transition"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="w-12 h-12 flex items-center justify-center rounded-full bg-black text-white hover:bg-gray-900 transition"><i class="fab fa-tiktok"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- FAQ Section -->
            <div class="mt-16">
                <h2 class="text-3xl font-cinzel font-bold text-white mb-8 text-center">Questions fréquentes</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="card p-6 rounded-lg shadow-lg">
                        <h3 class="text-xl font-bold text-white mb-3">Comment se déroule une consultation ?</h3>
                        <p class="text-gray-400">Les consultations peuvent se faire par téléphone, WhatsApp ou email selon votre préférence. Après un premier échange pour comprendre votre situation, je vous proposerai les solutions les plus adaptées.</p>
                    </div>
                    
                    <div class="card p-6 rounded-lg shadow-lg">
                        <h3 class="text-xl font-bold text-white mb-3">Combien de temps faut-il pour réaliser un rituel ?</h3>
                        <p class="text-gray-400">La durée varie selon le type de rituel et votre situation spécifique. Certains rituels peuvent être réalisés en quelques jours, d'autres nécessitent plusieurs semaines. Je vous donnerai toujours une estimation précise.</p>
                    </div>
                    
                    <div class="card p-6 rounded-lg shadow-lg">
                        <h3 class="text-xl font-bold text-white mb-3">Comment effectuer le paiement ?</h3>
                        <p class="text-gray-400">Plusieurs options de paiement sont disponibles : virement bancaire, PayPal, ou autres méthodes selon votre pays. Les détails vous seront communiqués après notre premier échange.</p>
                    </div>
                    
                    <div class="card p-6 rounded-lg shadow-lg">
                        <h3 class="text-xl font-bold text-white mb-3">Les rituels fonctionnent-ils à distance ?</h3>
                        <p class="text-gray-400">Oui, tous mes rituels peuvent être réalisés à distance, quelle que soit votre localisation géographique. L'énergie n'est pas limitée par les distances physiques.</p>
                    </div>
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
