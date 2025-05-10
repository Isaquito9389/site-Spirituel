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
    echo "<div style='background: #ffdddd; color: #a00; padding: 15px; margin: 10px 0; border: 2px solid #a00;'>
          Erreur critique : la connexion à la base de données n'est pas initialisée.</div>";
    exit;
}

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$messageType = '';
$ritual = null;

// Get message from URL if redirected
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $messageType = $_GET['type'];
}

// Handle form submissions with simplified approach
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_ritual'])) {
        // Get form data
        $ritual_id = isset($_POST['ritual_id']) ? intval($_POST['ritual_id']) : 0;
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $excerpt = trim($_POST['excerpt']);
        $category = trim($_POST['category']);
        $duration = trim($_POST['duration']);
        $price = trim($_POST['price']);
        $status = $_POST['status'];
        $featured_image = '';
        
        // Get image URL if provided
        if (isset($_POST['image_url']) && !empty($_POST['image_url'])) {
            $featured_image = $_POST['image_url'];
        } elseif (isset($_POST['current_image']) && !empty($_POST['current_image'])) {
            $featured_image = $_POST['current_image'];
        }
        
        // Validate form data
        if (empty($title) || empty($content)) {
            $message = "Le titre et le contenu sont obligatoires.";
            $messageType = "error";
        } else {
            try {
                // Prepare SQL statement based on whether it's an insert or update
                if ($ritual_id > 0) {
                    // Update existing ritual
                    $sql = "UPDATE rituals SET 
                            title = :title, 
                            content = :content, 
                            excerpt = :excerpt, 
                            category = :category,
                            duration = :duration,
                            price = :price,
                            status = :status,
                            featured_image = :featured_image,
                            updated_at = NOW()
                            WHERE id = :ritual_id";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':ritual_id', $ritual_id, PDO::PARAM_INT);
                } else {
                    // Insert new ritual
                    $sql = "INSERT INTO rituals (title, content, excerpt, category, duration, price, status, featured_image, created_at) 
                            VALUES (:title, :content, :excerpt, :category, :duration, :price, :status, :featured_image, NOW())";
                    
                    $stmt = $pdo->prepare($sql);
                }
                
                // Bind parameters
                $stmt->bindParam(':title', $title, PDO::PARAM_STR);
                $stmt->bindParam(':content', $content, PDO::PARAM_STR);
                $stmt->bindParam(':excerpt', $excerpt, PDO::PARAM_STR);
                $stmt->bindParam(':category', $category, PDO::PARAM_STR);
                $stmt->bindParam(':duration', $duration, PDO::PARAM_STR);
                $stmt->bindParam(':price', $price, PDO::PARAM_STR);
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
                $stmt->bindParam(':featured_image', $featured_image, PDO::PARAM_STR);
                
                $stmt->execute();
                
                // Set success message
                if ($ritual_id > 0) {
                    $message = "Le rituel a été mis à jour avec succès.";
                } else {
                    $message = "Le rituel a été créé avec succès.";
                }
                $messageType = "success";
                
                // Redirect to list view
                header("Location: rituals_ultra_simple.php?message=" . urlencode($message) . "&type=" . $messageType);
                exit;
            } catch (PDOException $e) {
                $message = "Erreur de base de données: " . $e->getMessage();
                $messageType = "error";
            }
        }
    } elseif (isset($_POST['delete_ritual'])) {
        // Delete ritual with simplified approach
        $ritual_id = intval($_POST['ritual_id']);
        
        try {
            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM rituals WHERE id = :id");
            $stmt->bindParam(':id', $ritual_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $message = "Le rituel a été supprimé avec succès.";
            $messageType = "success";
            
            // Redirect to list view
            header("Location: rituals_ultra_simple.php?message=" . urlencode($message) . "&type=" . $messageType);
            exit;
        } catch (PDOException $e) {
            $message = "Erreur de base de données: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Get ritual data if editing
$ritual = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $ritual_id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM rituals WHERE id = ?");
        $stmt->execute([$ritual_id]);
        $ritual = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ritual) {
            $message = "Rituel introuvable.";
            $messageType = "error";
            $action = 'list';
        }
    } catch (PDOException $e) {
        $message = "Erreur de base de données: " . $e->getMessage();
        $messageType = "error";
        $action = 'list';
    }
}

// Get all rituals if listing
$rituals = [];
if ($action === 'list') {
    try {
        $stmt = $pdo->query("SELECT * FROM rituals ORDER BY created_at DESC");
        $rituals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $message = "Erreur de base de données: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get categories for dropdown
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM blog_categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silently fail, not critical
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Rituels - Version Ultra Simple</title>
    <!-- Styles minimaux pour éviter les erreurs 500 -->
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
        .button-danger { background-color: #b91c1c; }
        .button-danger:hover { background-color: #991b1b; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, textarea, select { 
            width: 100%; 
            padding: 8px; 
            border: 1px solid #444; 
            background-color: #222; 
            color: #e0e0e0;
            border-radius: 4px;
        }
        textarea { min-height: 200px; }
        .message { 
            padding: 10px; 
            margin-bottom: 15px; 
            border-radius: 4px; 
        }
        .message-error { background-color: rgba(220, 38, 38, 0.3); }
        .message-success { background-color: rgba(22, 163, 74, 0.3); }
        table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        th, td { 
            padding: 8px; 
            text-align: left; 
            border-bottom: 1px solid #444; 
        }
        th { background-color: #222; }
        tr:hover { background-color: #272727; }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo $action === 'list' ? 'Gestion des Rituels' : ($action === 'edit' ? 'Modifier un Rituel' : 'Nouveau Rituel'); ?></h1>
        <div>
            <?php if ($action === 'list'): ?>
                <a href="?action=new" class="button">Nouveau Rituel</a>
            <?php else: ?>
                <a href="rituals_ultra_simple.php" class="button">Retour à la liste</a>
            <?php endif; ?>
            <a href="dashboard.php" class="button">Tableau de bord</a>
            <a href="?logout=true" class="button">Déconnexion</a>
        </div>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType === 'success' ? 'message-success' : 'message-error'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($action === 'list'): ?>
        <?php if (empty($rituals)): ?>
            <p>Aucun rituel trouvé. Commencez par en créer un nouveau.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Catégorie</th>
                        <th>Durée</th>
                        <th>Prix</th>
                        <th>Statut</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rituals as $ritual): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ritual['title']); ?></td>
                            <td><?php echo htmlspecialchars($ritual['category'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($ritual['duration'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($ritual['price'] ?? ''); ?> €</td>
                            <td><?php echo $ritual['status'] === 'published' ? 'Publié' : 'Brouillon'; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($ritual['created_at'])); ?></td>
                            <td>
                                <a href="?action=edit&id=<?php echo $ritual['id']; ?>" class="button">Éditer</a>
                                <form method="post" style="display:inline-block;">
                                    <input type="hidden" name="ritual_id" value="<?php echo $ritual['id']; ?>">
                                    <button type="submit" name="delete_ritual" class="button button-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce rituel ?');">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php else: ?>
        <form method="post" action="rituals_ultra_simple.php">
            <?php if ($action === 'edit' && $ritual): ?>
                <input type="hidden" name="ritual_id" value="<?php echo $ritual['id']; ?>">
                <?php if (!empty($ritual['featured_image'])): ?>
                    <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($ritual['featured_image']); ?>">
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="title">Titre du rituel *</label>
                <input type="text" id="title" name="title" required value="<?php echo $ritual ? htmlspecialchars($ritual['title']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="category">Catégorie</label>
                <select id="category" name="category">
                    <option value="">Sélectionnez une catégorie</option>
                    <option value="Amour" <?php echo ($ritual && $ritual['category'] === 'Amour') ? 'selected' : ''; ?>>Amour</option>
                    <option value="Prospérité" <?php echo ($ritual && $ritual['category'] === 'Prospérité') ? 'selected' : ''; ?>>Prospérité</option>
                    <option value="Protection" <?php echo ($ritual && $ritual['category'] === 'Protection') ? 'selected' : ''; ?>>Protection</option>
                    <option value="Vodoun" <?php echo ($ritual && $ritual['category'] === 'Vodoun') ? 'selected' : ''; ?>>Vodoun</option>
                    <option value="Magie de Salomon" <?php echo ($ritual && $ritual['category'] === 'Magie de Salomon') ? 'selected' : ''; ?>>Magie de Salomon</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['name']); ?>" <?php echo ($ritual && $ritual['category'] === $category['name']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="excerpt">Extrait</label>
                <textarea id="excerpt" name="excerpt" rows="2"><?php echo $ritual ? htmlspecialchars($ritual['excerpt']) : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="content">Description du rituel *</label>
                <textarea id="content" name="content" required><?php echo $ritual ? htmlspecialchars($ritual['content']) : ''; ?></textarea>
            </div>
            
            <div class="form-group" style="display: flex; gap: 10px;">
                <div style="flex: 1;">
                    <label for="duration">Durée du rituel</label>
                    <input type="text" id="duration" name="duration" value="<?php echo $ritual ? htmlspecialchars($ritual['duration']) : ''; ?>">
                </div>
                
                <div style="flex: 1;">
                    <label for="price">Prix (€)</label>
                    <input type="text" id="price" name="price" value="<?php echo $ritual ? htmlspecialchars($ritual['price']) : ''; ?>">
                </div>
                
                <div style="flex: 1;">
                    <label for="status">Statut</label>
                    <select id="status" name="status">
                        <option value="draft" <?php echo ($ritual && $ritual['status'] === 'draft') ? 'selected' : ''; ?>>Brouillon</option>
                        <option value="published" <?php echo ($ritual && $ritual['status'] === 'published') ? 'selected' : ''; ?>>Publié</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="image_url">URL d'image</label>
                <input type="url" id="image_url" name="image_url" placeholder="https://exemple.com/image.jpg" value="<?php echo $ritual && !empty($ritual['featured_image']) && substr($ritual['featured_image'], 0, 4) === 'http' ? htmlspecialchars($ritual['featured_image']) : ''; ?>">
                <small style="display: block; margin-top: 5px;">Utilisez une URL d'image externe (hébergement d'image en ligne)</small>
                
                <?php if (!empty($ritual['featured_image'])): ?>
                    <div style="margin-top: 10px;">
                        <p>Image actuelle: <?php echo htmlspecialchars($ritual['featured_image']); ?></p>
                        <?php if (substr($ritual['featured_image'], 0, 4) === 'http'): ?>
                            <img src="<?php echo htmlspecialchars($ritual['featured_image']); ?>" alt="Image actuelle" style="max-width: 200px; max-height: 200px; margin-top: 5px;">
                        <?php else: ?>
                            <img src="../<?php echo htmlspecialchars($ritual['featured_image']); ?>" alt="Image actuelle" style="max-width: 200px; max-height: 200px; margin-top: 5px;">
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="text-align: right; margin-top: 20px;">
                <button type="submit" name="save_ritual" class="button">
                    <?php echo $action === 'edit' ? 'Mettre à jour' : 'Publier'; ?> le rituel
                </button>
            </div>
        </form>
    <?php endif; ?>
</body>
</html>
