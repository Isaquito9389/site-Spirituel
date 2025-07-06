<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
// =========================================================================
// PHP BACKEND LOGIC (INCHANGÉ)
// =========================================================================

// Affichage des erreurs en mode développement
// Connexion à la base de données
require_once 'includes/db_connect.php';

// Le reste de votre logique PHP (création de table, récupération de rituel, traitement du formulaire)
// est conservé tel quel car il est fonctionnel.

// Récupération des informations du rituel
$ritual = null;
$ritual_name = null;

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'rituals'");
    $ritualsTableExists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $ritualsTableExists = false;
    }

if (isset($_GET['ritual']) && !empty($_GET['ritual'])) {
    $ritual_param = htmlspecialchars(trim($_GET['ritual']), ENT_QUOTES, 'UTF-8');
    $ritual_name = $ritual_param;
    
    if ($ritualsTableExists) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM rituals WHERE (slug = :param OR title LIKE :param_like) AND status = 'published' LIMIT 1");
            $search_like = "%{$ritual_param}%";
            $stmt->bindParam(':param', $ritual_param, PDO::PARAM_STR);
            $stmt->bindParam(':param_like', $search_like, PDO::PARAM_STR);
            $stmt->execute();
            $ritual = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            }
    }
}

// Traitement du formulaire
$success_message = '';
$error_message_form = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8') : '';
    $email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
    $phone = isset($_POST['phone']) ? htmlspecialchars(trim($_POST['phone']), ENT_QUOTES, 'UTF-8') : '';
    $subject = isset($_POST['subject']) ? htmlspecialchars(trim($_POST['subject']), ENT_QUOTES, 'UTF-8') : '';
    $message = isset($_POST['message']) ? htmlspecialchars(trim($_POST['message']), ENT_QUOTES, 'UTF-8') : '';
    $ritual_id = isset($_POST['ritual_id']) ? intval($_POST['ritual_id']) : null;
    
    if (empty($name) || empty($email) || empty($message) || empty($subject)) {
        $error_message_form = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message_form = 'Veuillez entrer une adresse email valide.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (name, email, phone, subject, message, ritual_id) VALUES (:name, :email, :phone, :subject, :message, :ritual_id)");
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
            $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
            $stmt->bindParam(':message', $message, PDO::PARAM_STR);
            $stmt->bindParam(':ritual_id', $ritual_id, $ritual_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->execute();
            
            $success_message = "Merci, votre message a été envoyé avec succès. Je vous répondrai dans les plus brefs délais.";
            $_POST = [];

        } catch (Exception $e) {
            $error_message_form = 'Une erreur interne est survenue. Veuillez réessayer plus tard.';
            }
    }
}

// Pré-remplissage du formulaire
$form_name = isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '';
$form_email = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '';
$form_phone = isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '';

if ($ritual) {
    $form_subject = 'Demande concernant le rituel : ' . htmlspecialchars($ritual['title']);
    $form_message = "Bonjour,\n\nJe suis intéressé(e) par votre rituel \"" . htmlspecialchars($ritual['title']) . "\" et j'aimerais recevoir plus d'informations.\n\nCordialement,";
} else {
    $form_subject = isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : '';
    $form_message = isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '';
}

$page_title = "Contact - Mystica Occulta";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <meta name="description" content="Contactez Mystica Occulta pour toute question sur nos rituels, services ésotériques ou pour une consultation personnalisée.">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700&family=Merriweather:wght@300;400;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --font-body: 'Merriweather', serif;
            --font-display: 'Cinzel Decorative', cursive;
            
            --color-background: #0a090f;
            --color-text: #e0e0e0;
            --color-text-muted: #8a8a9e;
            --color-purple: #9d4edd;
            --color-purple-dark: #3c096c;
            
            --color-card-bg: rgba(26, 26, 46, 0.4);
            --color-card-border: rgba(60, 9, 108, 0.5);

            --color-success: #2ddc83;
            --color-error: #e74c3c;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-body);
            background-color: var(--color-background);
            color: var(--color-text);
            line-height: 1.7;
            background-image: url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40"%3E%3Cg fill-rule="evenodd"%3E%3Cg fill="%231a1a2e" fill-opacity="0.2"%3E%3Cpath d="M0 38.59l2.83-2.83 1.41 1.41L1.41 40H0v-1.41zM0 1.4l2.83 2.83 1.41-1.41L1.41 0H0v1.41zM38.59 40l-2.83-2.83 1.41-1.41L40 38.59V40h-1.41zM40 1.41l-2.83 2.83-1.41-1.41L38.59 0H40v1.41zM20 18.6l2.83-2.83 1.41 1.41L21.41 20l2.83 2.83-1.41 1.41L20 21.41l-2.83 2.83-1.41-1.41L18.59 20l-2.83-2.83 1.41-1.41L20 18.59z"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        h1, h2, h3 { font-family: var(--font-display); color: white; }
        p { margin-bottom: 1rem; }
        a { color: var(--color-purple); text-decoration: none; transition: color 0.3s ease; }
        a:hover { color: white; }

        .main-header {
            padding: 20px 0;
            background: rgba(10, 9, 15, 0.5);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--color-card-border);
        }
        .main-header .container { display: flex; justify-content: space-between; align-items: center; }
        .main-header .logo { font-size: 1.8rem; font-family: var(--font-display); color: white; }
        .main-header nav a { margin-left: 25px; font-weight: bold; }
        .main-header nav a.active { border-bottom: 2px solid var(--color-purple); }

        .hero-section {
            padding: 6rem 0;
            text-align: center;
        }
        .hero-section h1 { font-size: 3.5rem; }
        .hero-section p { font-size: 1.2rem; color: var(--color-text-muted); max-width: 600px; margin: 1rem auto 0; }

        .contact-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
            margin-top: 2rem;
        }

        .card {
            background: var(--color-card-bg);
            border: 1px solid var(--color-card-border);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            /* NOUVEAU : Transition pour l'effet de survol */
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        }

        /* NOUVEAU : Effet de survol sur les cartes */
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            border-color: var(--color-purple);
        }

        .alert {
            padding: 15px; margin-bottom: 30px; border-radius: 8px;
            display: flex; align-items: center; gap: 15px; border-left: 4px solid;
        }
        .alert i { font-size: 1.5rem; }
        .alert-success { background-color: rgba(45, 220, 131, 0.1); border-color: var(--color-success); color: var(--color-success); }
        .alert-error { background-color: rgba(231, 76, 60, 0.1); border-color: var(--color-error); color: var(--color-error); }
        .alert h4 { color: white; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: var(--color-text); }
        .form-input {
            width: 100%; padding: 12px 15px; background-color: rgba(0,0,0,0.3);
            border: 1px solid var(--color-purple-dark); border-radius: 8px;
            color: var(--color-text); font-family: var(--font-body); font-size: 1rem;
            transition: all 0.3s ease;
        }
        .form-input:focus {
            outline: none; border-color: var(--color-purple);
            box-shadow: 0 0 0 3px rgba(157, 78, 221, 0.3);
        }
        textarea.form-input { height: 150px; resize: vertical; }
        
        .btn {
            display: inline-block; padding: 12px 30px; border: none; border-radius: 8px;
            background: linear-gradient(45deg, #7e22ce, #c026d3); color: white;
            font-weight: bold; font-size: 1rem; cursor: pointer;
            transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(126, 34, 206, 0.2);
        }
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(126, 34, 206, 0.4);
        }
        .btn i { margin-right: 8px; }

        .info-list { list-style: none; }
        .info-list li { display: flex; align-items: flex-start; margin-bottom: 20px; }
        .info-list i { font-size: 1.2rem; color: var(--color-purple); width: 30px; margin-top: 4px; }
        .info-list strong { display: block; color: white; }
        
        .social-links a {
            font-size: 1.5rem; margin-right: 15px; color: var(--color-text-muted);
            /* NOUVEAU : Transition pour survol */
            transition: transform 0.3s ease, color 0.3s ease;
        }
        /* NOUVEAU : Effet survol icônes sociales */
        .social-links a:hover {
            color: white;
            transform: translateY(-3px);
        }

        .main-footer {
            margin-top: 4rem; padding: 2rem 0; text-align: center;
            border-top: 1px solid var(--color-card-border); color: var(--color-text-muted);
        }

        /* NOUVEAU : Définition de l'animation d'entrée */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* NOUVEAU : Classe utilitaire pour appliquer l'animation */
        .animate-on-load {
            /* Applique l'animation et la maintient dans son état final */
            animation: fadeInUp 0.7s ease-out forwards;
            /* L'élément est initialement transparent */
            opacity: 0;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .contact-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .hero-section h1 { font-size: 2.5rem; }
            .main-header nav { display: none; }
        }
    </style>
</head>
<body>

    <header class="main-header">
        <div class="container">
            <a href="/" class="logo">Mystica Occulta</a>
            <nav>
                <a href="index.html">Accueil</a>
                <a href="index.html" class="back-button"><i class="fas fa-arrow-left mr-1"></i> Retour</a>
                <a href="contact.php" class="active">Contact</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero-section">
            <div class="container">
                <h1 class="animate-on-load" style="animation-delay: 0.2s;">Entrez en Connexion</h1>
                <p class="animate-on-load" style="animation-delay: 0.4s;">Que ce soit pour une question, une demande de rituel ou une guidance, je suis à votre écoute.</p>
            </div>
        </section>

        <section class="container">
            <div class="contact-grid">
                
                <div class="form-column animate-on-load" style="animation-delay: 0.6s;">
                    <div class="card">
                        <h2>Formulaire de Contact</h2>
                        <p style="color: var(--color-text-muted);">Remplissez les champs ci-dessous pour m'envoyer votre message.</p>
                        
                        <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div><h4>Message Envoyé !</h4><?php echo htmlspecialchars($success_message); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($error_message_form)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                             <div><h4>Erreur de validation</h4><?php echo htmlspecialchars($error_message_form); ?></div>
                        </div>
                        <?php endif; ?>

                        <form action="contact.php" method="POST">
                            <?php if ($ritual): ?><input type="hidden" name="ritual_id" value="<?php echo $ritual['id']; ?>"><?php endif; ?>
                            <div class="form-group"><label for="name">Nom Complet *</label><input type="text" id="name" name="name" required class="form-input" value="<?php echo $form_name; ?>" placeholder="Votre nom et prénom"></div>
                            <div class="form-group"><label for="email">Adresse Email *</label><input type="email" id="email" name="email" required class="form-input" value="<?php echo $form_email; ?>" placeholder="votre.email@exemple.com"></div>
                            <div class="form-group"><label for="phone">Téléphone (Optionnel)</label><input type="tel" id="phone" name="phone" class="form-input" value="<?php echo $form_phone; ?>" placeholder="Pour un contact direct"></div>
                            <div class="form-group"><label for="subject">Sujet *</label><input type="text" id="subject" name="subject" required class="form-input" value="<?php echo $form_subject; ?>" placeholder="Objet de votre message"></div>
                            <div class="form-group"><label for="message">Votre Message *</label><textarea id="message" name="message" required class="form-input"><?php echo $form_message; ?></textarea></div>
                            <button type="submit" class="btn"><i class="fas fa-paper-plane"></i> Envoyer le Message</button>
                        </form>
                    </div>
                </div>
                
                <div class="info-column animate-on-load" style="animation-delay: 0.8s;">
                    <div class="card" style="margin-bottom: 30px;">
                        <h3>Mes Coordonnées</h3>
                        <ul class="info-list">
                            <li><i class="fas fa-envelope"></i><div><strong>Email</strong>contact@mysticaocculta.com</div></li>
                            <li><i class="fab fa-whatsapp"></i><div><strong>WhatsApp</strong><a href="https://wa.me/22967512021" target="_blank">+229 67 51 20 21</a></div></li>
                            <li><i class="fas fa-clock"></i><div><strong>Disponibilité</strong>Réponse sous 24-48h</div></li>
                        </ul>
                    </div>
                    <div class="card">
                        <h3>Suivez-moi</h3>
                        <div class="social-links">
                            <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                            <a href="#" aria-label="Tiktok"><i class="fab fa-tiktok"></i></a>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </main>

    <footer class="main-footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Mystica Occulta. Tous droits réservés.</p>
        </div>
    </footer>

</body>
</html>
