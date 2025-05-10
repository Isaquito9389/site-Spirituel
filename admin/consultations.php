<?php
// Affichage forcé des erreurs PHP pour le debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Get admin username
$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    $_SESSION = array();
    session_destroy();
    header("Location: index.php");
    exit;
}

// Include database connection
require_once 'includes/db_connect.php';
// Vérification explicite de la connexion PDO
if (!isset($pdo) || !$pdo) {
    echo "<div style='background: #ffdddd; color: #a00; padding: 15px; margin: 10px 0; border: 2px solid #a00;'>Erreur critique : la connexion à la base de données n'est pas initialisée après l'inclusion de includes/db_connect.php.<br>Vérifiez le fichier de connexion et les identifiants !</div>";
    exit;
}

// Initialize variables
$message = '';
$messageType = '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Consultations - Version Simple</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background-color: #171717; 
            color: #e0e0e0; 
        }
        h1, h2 { color: #c0c0c0; }
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 20px; 
            padding-bottom: 10px; 
            border-bottom: 1px solid #444; 
        }
        .button {
            display: inline-block;
            padding: 8px 16px;
            background-color: #4b0082;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        .button:hover { background-color: #6a0dad; }
        .message { 
            padding: 10px; 
            margin-bottom: 15px; 
            border-radius: 4px; 
        }
        .message-error { background-color: rgba(220, 38, 38, 0.3); }
        .message-success { background-color: rgba(22, 163, 74, 0.3); }
    </style>
</head>
<body>
    <div class="header">
        <h1>Gestion des Consultations</h1>
        <div>
            <a href="dashboard.php" class="button">Tableau de bord</a>
            <a href="?logout=true" class="button">Déconnexion</a>
        </div>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType === 'success' ? 'message-success' : 'message-error'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div style="text-align: center; padding: 50px; background-color: #222; border-radius: 10px; margin-top: 20px;">
        <h2>Module en cours de développement</h2>
        <p>La gestion des consultations sera bientôt disponible. Merci de votre patience.</p>
        <a href="dashboard.php" class="button" style="margin-top: 20px;">Retour au tableau de bord</a>
    </div>
    
</body>
</html>
