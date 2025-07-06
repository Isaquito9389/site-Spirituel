<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
// Redirection vers la nouvelle version de la page
header("Location: consultations_new.php");
exit;

// Le code ci-dessous ne sera pas exécuté en raison de la redirection
// Affichage forcé des erreurs PHP pour le debug
// Custom error handler to prevent 500 errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (error_reporting() === 0) {
        return false;
    }

    $error_message = "Error [$errno] $errstr - $errfile:$errline";
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        echo "<div style=\"padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin-bottom: 20px;\">
            <h3>Une erreur est survenue</h3>
            <p>Nous avons rencontré un problème lors du traitement de votre demande. Veuillez réessayer plus tard ou contacter l'administrateur.</p>
            <p><a href=\"dashboard.php\" style=\"color: #721c24; text-decoration: underline;\">Retour au tableau de bord</a></p>
        </div>";

        // Log detailed error for admin
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            echo "<div style=\"padding: 20px; background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; border-radius: 5px; margin-top: 20px;\">
                <h4>Détails de l'erreur (visible uniquement pour les administrateurs)</h4>
                <p>" . htmlspecialchars($error_message) . "</p>
            </div>";
        }

        return true;
    }

    return false;
}, E_ALL);

// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté, sinon rediriger vers la page de connexion
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Récupérer le nom d'utilisateur admin
$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Inclure la connexion à la base de données
require_once 'includes/db_connect.php';

// Vérification explicite de la connexion PDO
if (!isset($pdo) || !$pdo) {
    echo "<div style='background: #ffdddd; color: #a00; padding: 15px; margin: 10px 0; border: 2px solid #a00;'>Erreur critique : la connexion à la base de données n'est pas initialisée après l'inclusion de includes/db_connect.php.<br>Vérifiez le fichier de connexion et les identifiants !</div>";
    // On arrête tout pour éviter d'autres erreurs
    exit;
}

// Initialisation des variables
$message = '';
$messageType = '';
$consultations = [];

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Supprimer un message
    if (isset($_POST['delete_msg']) && isset($_POST['msg_id'])) {
        $id = intval($_POST['msg_id']);
        try {
            $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Message supprimé avec succès.";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Erreur lors de la suppression: " . $e->getMessage();
            $messageType = "error";
        }
    }

    // Mise à jour du statut
    if (isset($_POST['update_status']) && isset($_POST['msg_id']) && isset($_POST['status'])) {
        $id = intval($_POST['msg_id']);
        $status = $_POST['status'] === 'read' ? 'read' : 'unread';

        try {
            $stmt = $pdo->prepare("UPDATE messages SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $id]);
            $message = "Statut mis à jour avec succès.";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Erreur lors de la mise à jour du statut: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Récupérer les messages de la base de données
try {
    // Filtrage par statut si demandé
    $status_filter = isset($_GET['status']) ? $_GET['status'] : null;

    if ($status_filter) {
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE status = ? ORDER BY created_at DESC");
        $stmt->execute([$status_filter]);
    } else {
        $stmt = $pdo->query("SELECT * FROM messages ORDER BY created_at DESC");
    }

    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Erreur lors de la récupération des messages: " . $e->getMessage();
    $messageType = "error";
}

// Récupérer un message spécifique si demandé
$consultation_details = null;
if (isset($_GET['view']) && !empty($_GET['view'])) {
    $id = intval($_GET['view']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ?");
        $stmt->execute([$id]);
        $consultation_details = $stmt->fetch(PDO::FETCH_ASSOC);

        // Marquer comme lu si non lu
        if ($consultation_details && $consultation_details['status'] === 'unread') {
            $stmt = $pdo->prepare("UPDATE messages SET status = 'read', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $consultation_details['status'] = 'read';
        }
    } catch (PDOException $e) {
        $message = "Erreur lors de la récupération du message: " . $e->getMessage();
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Consultations | Mystica Oculta</title>
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&family=MedievalSharp&family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400;1,700;1,900&display=swap');

        :root {
            --primary: #3a0ca3;
            --secondary: #7209b7;
            --accent: #f72585;
            --dark: #1a1a2e;
            --light: #f8f9fa;
        }

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

        .btn-magic {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            box-shadow: 0 4px 15px rgba(247, 37, 133, 0.4);
            transition: all 0.3s ease;
        }

        .btn-magic:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(247, 37, 133, 0.6);
        }

        .sidebar {
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .nav-link {
            transition: all 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            background: linear-gradient(90deg, rgba(114, 9, 183, 0.3) 0%, rgba(58, 12, 163, 0) 100%);
            border-left: 4px solid var(--accent);
        }

        .card {
            background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(247, 37, 133, 0.2);
        }

        /* Styles généraux */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Style d'en-tête */
        header {
            background-color: #0f0f0f;
            padding: 15px 0;
            text-align: center;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .logo {
            color: #6a0dad;
            text-decoration: none;
            font-size: 1.5em;
            font-weight: bold;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-name {
            margin-right: 10px;
        }

        /* Style de navigation */
        nav {
            background-color: #171717;
            padding: 10px 0;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }

        nav ul {
            display: flex;
            justify-content: center;
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        nav li {
            margin: 0 15px;
        }

        nav a {
            color: #fff;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        nav a:hover {
            color: #6a0dad;
        }

        nav a.active {
            color: #6a0dad;
            border-bottom: 2px solid #6a0dad;
            padding-bottom: 5px;
        }

        /* Titre de page */
        .page-title {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Style de bouton */
        .button {
            background-color: #6a0dad;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            margin: 5px;
            transition: background-color 0.3s ease;
        }

        .button:hover {
            background-color: #5a0b8d;
        }

        .button.active {
            background-color: #5a0b8d;
            font-weight: bold;
        }

        /* Style de message */
        .message {
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }

        .message-error { background-color: rgba(220, 38, 38, 0.3); }
        .message-success { background-color: rgba(22, 163, 74, 0.3); }

        /* Tableau de données */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table th {
            text-align: left;
            padding: 12px 15px;
            background-color: #222;
            border-bottom: 1px solid #444;
        }

        table td {
            padding: 10px 15px;
            border-bottom: 1px solid #333;
        }

        table tr:hover {
            background-color: #222;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-unread {
            background-color: #4F46E5;
            color: white;
        }

        .status-read {
            background-color: #10B981;
            color: white;
        }

        .unread-row {
            background-color: rgba(79, 70, 229, 0.1);
        }

        /* Détails de message */
        .message-details {
            background-color: #222;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .message-content {
            background-color: #333;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
            white-space: pre-wrap;
        }

        /* Boutons spéciaux */
        .btn-green {
            background-color: #10B981;
        }

        .btn-blue {
            background-color: #4F46E5;
        }

        .btn-red {
            background-color: #e53e3e;
        }

        .btn-red:hover {
            background-color: #c53030;
        }

        /* Tabs/filters */
        .tabs {
            display: flex;
            margin-bottom: 20px;
        }

        .back-link {
            margin-bottom: 20px;
            display: block;
        }
    </style>
</head>
<body class="bg-dark min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-dark bg-opacity-90 backdrop-blur-sm border-b border-purple-900 py-3">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-900 to-pink-600 flex items-center justify-center">
                    <i class="fas fa-eye text-white text-xl"></i>
                </div>
                <span class="font-cinzel text-2xl font-bold bg-gradient-to-r from-purple-400 to-pink-600 bg-clip-text text-transparent">MYSTICA OCCULTA</span>
            </div>

            <div class="flex items-center space-x-4">
                <span class="text-gray-300">
                    <i class="fas fa-user-circle mr-2"></i> <?php echo htmlspecialchars($admin_username); ?>
                </span>
                <a href="?logout=true" class="text-gray-300 hover:text-pink-500 transition duration-300">
                    <i class="fas fa-sign-out-alt mr-2"></i> Déconnexion
                </a>
            </div>
        </div>
    </header>

    <div class="flex flex-1">
        <!-- Sidebar -->
        <aside class="sidebar w-64 border-r border-purple-900 flex-shrink-0">
            <nav class="py-6">
                <ul>
                    <li>
                        <a href="dashboard.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-tachometer-alt w-6"></i>
                            <span>Tableau de Bord</span>
                        </a>
                    </li>
                    <li>
                        <a href="rituals.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-magic w-6"></i>
                            <span>Rituels</span>
                        </a>
                    </li>
                    <li>
                        <a href="blog.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-blog w-6"></i>
                            <span>Blog</span>
                        </a>
                    </li>
                    <li>
                        <a href="testimonials.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-comments w-6"></i>
                            <span>Témoignages</span>
                        </a>
                    </li>
                    <li>
                        <a href="products.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-shopping-cart w-6"></i>
                            <span>Produits</span>
                        </a>
                    </li>
                    <li>
                        <a href="consultations_new.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white active">
                            <i class="fas fa-calendar-alt w-6"></i>
                            <span>Consultations</span>
                        </a>
                    </li>
                    <li>
                        <a href="settings.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-cog w-6"></i>
                            <span>Paramètres</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="flex justify-between items-center mb-8">
                <h1 class="font-cinzel text-3xl font-bold text-white">Gestion des Consultations</h1>
            </div>

            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-900 bg-opacity-50' : 'bg-red-900 bg-opacity-50'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($consultation_details): ?>
                <!-- Affichage détaillé d'un message -->
                <a href="consultations_new.php" class="button back-link">← Retour à la liste</a>

                <div class="message-details">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2><?php echo htmlspecialchars($consultation_details['subject'] ?: 'Sans sujet'); ?></h2>
                        <span class="status-badge status-<?php echo $consultation_details['status']; ?>">
                            <?php echo $consultation_details['status'] === 'unread' ? 'Non lu' : 'Lu'; ?>
                        </span>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <strong>De:</strong> <?php echo htmlspecialchars($consultation_details['name']); ?>
                        (<a href="mailto:<?php echo htmlspecialchars($consultation_details['email']); ?>"><?php echo htmlspecialchars($consultation_details['email']); ?></a>)
                    </div>

                    <div style="margin-bottom: 15px;">
                        <strong>Téléphone:</strong> <?php echo htmlspecialchars($consultation_details['phone'] ?: 'Non spécifié'); ?>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <strong>Date:</strong> <?php echo date('d/m/Y H:i', strtotime($consultation_details['created_at'])); ?>
                    </div>

                    <?php if (!empty($consultation_details['ritual_id'])): ?>
                    <div style="margin-bottom: 15px;">
                        <strong>Rituel demandé:</strong> ID #<?php echo $consultation_details['ritual_id']; ?>
                    </div>
                    <?php endif; ?>

                    <div class="message-content">
                        <?php echo htmlspecialchars($consultation_details['message']); ?>
                    </div>

                    <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                        <div>
                            <form method="post">
                                <input type="hidden" name="msg_id" value="<?php echo $consultation_details['id']; ?>">
                                <?php if ($consultation_details['status'] === 'read'): ?>
                                    <input type="hidden" name="status" value="unread">
                                    <button type="submit" name="update_status" class="button btn-blue">Marquer comme non lu</button>
                                <?php else: ?>
                                    <input type="hidden" name="status" value="read">
                                    <button type="submit" name="update_status" class="button btn-green">Marquer comme lu</button>
                                <?php endif; ?>
                            </form>
                        </div>

                        <div>
                            <form method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce message ?');">
                                <input type="hidden" name="msg_id" value="<?php echo $consultation_details['id']; ?>">
                                <button type="submit" name="delete_msg" class="button btn-red">Supprimer</button>
                            </form>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Liste des messages -->
                <div class="card rounded-xl p-6 border border-purple-900">
                    <div class="tabs">
                        <a href="consultations_new.php" class="button <?php echo !isset($_GET['status']) ? 'active' : ''; ?>">Tous</a>
                        <a href="consultations_new.php?status=unread" class="button <?php echo isset($_GET['status']) && $_GET['status'] === 'unread' ? 'active' : ''; ?>">Non lus</a>
                        <a href="consultations_new.php?status=read" class="button <?php echo isset($_GET['status']) && $_GET['status'] === 'read' ? 'active' : ''; ?>">Lus</a>
                    </div>

                    <?php if (empty($consultations)): ?>
                        <div style="text-align: center; padding: 50px; background-color: #222; border-radius: 10px;">
                            <h2>Aucun message</h2>
                            <p>Aucun message de consultation n'a encore été reçu<?php echo $status_filter ? ' avec ce statut' : ''; ?>.</p>
                        </div>
                    <?php else: ?>
                        <p>Affichage de <?php echo count($consultations); ?> message(s) de consultation.</p>

                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-purple-900">
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Nom</th>
                                        <th>Email</th>
                                        <th>Sujet</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($consultations as $item): ?>
                                        <tr class="<?php echo $item['status'] === 'unread' ? 'unread-row' : ''; ?> border-b border-purple-900 hover:bg-purple-900 hover:bg-opacity-20">
                                            <td><?php echo $item['id']; ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($item['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['email']); ?></td>
                                            <td>
                                                <?php
                                                    $subject = $item['subject'] ?: 'Sans sujet';
                                                    echo htmlspecialchars(substr($subject, 0, 30)) . (strlen($subject) > 30 ? '...' : '');
                                                ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $item['status']; ?>">
                                                    <?php echo $item['status'] === 'unread' ? 'Non lu' : 'Lu'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="consultations_new.php?view=<?php echo $item['id']; ?>" class="button">Voir</a>

                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="msg_id" value="<?php echo $item['id']; ?>">
                                                    <?php if ($item['status'] === 'read'): ?>
                                                        <input type="hidden" name="status" value="unread">
                                                        <button type="submit" name="update_status" class="button btn-blue">Non lu</button>
                                                    <?php else: ?>
                                                        <input type="hidden" name="status" value="read">
                                                        <button type="submit" name="update_status" class="button btn-green">Lu</button>
                                                    <?php endif; ?>
                                                </form>

                                                <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce message ?');">
                                                    <input type="hidden" name="msg_id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" name="delete_msg" class="button btn-red">Supprimer</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
