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

// Si le formulaire de paramètres est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        // Récupérer les valeurs du formulaire
        $site_title = trim($_POST['site_title']);
        $site_description = trim($_POST['site_description']);
        $contact_email = trim($_POST['contact_email']);
        
        // Mettre à jour les paramètres dans la base de données
        // Vérifier si la table settings existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
        if ($stmt->rowCount() === 0) {
            // Créer la table si elle n'existe pas
            $pdo->exec("CREATE TABLE settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(255) NOT NULL UNIQUE,
                setting_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");
        }
        
        // Pour chaque paramètre, insérer ou mettre à jour
        $params = [
            'site_title' => $site_title,
            'site_description' => $site_description,
            'contact_email' => $contact_email
        ];
        
        foreach ($params as $key => $value) {
            // Vérifier si le paramètre existe déjà
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            if ($stmt->fetchColumn() > 0) {
                // Mettre à jour
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            } else {
                // Insérer
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
        }
        
        $message = "Les paramètres ont été mis à jour avec succès.";
        $messageType = "success";
    } catch (PDOException $e) {
        $message = "Erreur de base de données: " . $e->getMessage();
        $messageType = "error";
    }
}

// Récupérer les paramètres actuels
$settings = [];
try {
    // Vérifier si la table settings existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (PDOException $e) {
    // Silencieux en cas d'erreur
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres du Site - Version Simple</title>
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
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, textarea { 
            width: 100%; 
            padding: 8px; 
            border: 1px solid #444; 
            background-color: #222; 
            color: #e0e0e0;
            border-radius: 4px;
        }
        .message { 
            padding: 10px; 
            margin-bottom: 15px; 
            border-radius: 4px; 
        }
        .message-error { background-color: rgba(220, 38, 38, 0.3); }
        .message-success { background-color: rgba(22, 163, 74, 0.3); }
        .card {
            background-color: #222;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Paramètres du Site</h1>
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
    
    <div class="card">
        <h2>Paramètres généraux</h2>
        <form method="post" action="settings.php">
            <div class="form-group">
                <label for="site_title">Titre du site</label>
                <input type="text" id="site_title" name="site_title" value="<?php echo htmlspecialchars($settings['site_title'] ?? 'Mystica Occulta'); ?>">
            </div>
            
            <div class="form-group">
                <label for="site_description">Description du site</label>
                <textarea id="site_description" name="site_description" rows="3"><?php echo htmlspecialchars($settings['site_description'] ?? 'Voyance, rituels et magie'); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="contact_email">Email de contact</label>
                <input type="email" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email'] ?? 'contact@exemple.com'); ?>">
            </div>
            
            <div style="text-align: right; margin-top: 20px;">
                <button type="submit" name="save_settings" class="button">Enregistrer les paramètres</button>
            </div>
        </form>
    </div>
    
</body>
</html>
