<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
// Affichage forcé des erreurs PHP pour le debug
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
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$messageType = '';
$testimonial = null;

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_testimonial'])) {
        // Récupérer les données du formulaire
        $testimonial_id = isset($_POST['testimonial_id']) ? intval($_POST['testimonial_id']) : 0;
        $name = trim($_POST['name']);
        $content = trim($_POST['content']);
        $status = $_POST['status'];

        // Valider les données
        if (empty($name) || empty($content)) {
            $message = "Le nom et le contenu sont obligatoires.";
            $messageType = "error";
        } else {
            try {
                // Vérifier si la table testimonials existe
                $stmt = $pdo->query("SHOW TABLES LIKE 'testimonials'");
                if ($stmt->rowCount() === 0) {
                    // Créer la table si elle n'existe pas
                    $pdo->exec("CREATE TABLE testimonials (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        author_name VARCHAR(255) NOT NULL,
                        content TEXT NOT NULL,
                        author_image VARCHAR(255) NULL,
                        rating INT DEFAULT 5,
                        service VARCHAR(255) NULL,
                        status VARCHAR(50) NOT NULL DEFAULT 'pending',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )");
                }

                // Préparer la requête SQL selon qu'il s'agit d'une insertion ou d'une mise à jour
                if ($testimonial_id > 0) {
                    // Mise à jour d'un témoignage existant
                    $sql = "UPDATE testimonials SET
                            author_name = :name,
                            content = :content,
                            status = :status
                            WHERE id = :id";

                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id', $testimonial_id, PDO::PARAM_INT);
                } else {
                    // Insertion d'un nouveau témoignage
                    $sql = "INSERT INTO testimonials (author_name, content, status)
                            VALUES (:name, :content, :status)";

                    $stmt = $pdo->prepare($sql);
                }

                // Lier les paramètres et exécuter
                $stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $stmt->bindParam(':content', $content, PDO::PARAM_STR);
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
                $stmt->execute();

                // Message de succès
                if ($testimonial_id > 0) {
                    $message = "Le témoignage a été mis à jour avec succès.";
                } else {
                    $message = "Le témoignage a été ajouté avec succès.";
                }
                $messageType = "success";

                // Redirection vers la liste
                header("Location: testimonials.php?message=" . urlencode($message) . "&type=" . $messageType);
                exit;
            } catch (PDOException $e) {
                $message = "Erreur de base de données: " . $e->getMessage();
                $messageType = "error";
            }
        }
    } elseif (isset($_POST['delete_testimonial'])) {
        // Suppression d'un témoignage
        $testimonial_id = intval($_POST['testimonial_id']);

        try {
            $stmt = $pdo->prepare("DELETE FROM testimonials WHERE id = :id");
            $stmt->bindParam(':id', $testimonial_id, PDO::PARAM_INT);
            $stmt->execute();

            $message = "Le témoignage a été supprimé avec succès.";
            $messageType = "success";

            // Redirection vers la liste
            header("Location: testimonials.php?message=" . urlencode($message) . "&type=" . $messageType);
            exit;
        } catch (PDOException $e) {
            $message = "Erreur de base de données: " . $e->getMessage();
            $messageType = "error";
        }
    } elseif (isset($_POST['approve_testimonial']) || isset($_POST['reject_testimonial'])) {
        // Approuver ou rejeter un témoignage
        $testimonial_id = intval($_POST['testimonial_id']);
        $new_status = isset($_POST['approve_testimonial']) ? 'approved' : 'rejected';

        try {
            $stmt = $pdo->prepare("UPDATE testimonials SET status = :status WHERE id = :id");
            $stmt->bindParam(':status', $new_status, PDO::PARAM_STR);
            $stmt->bindParam(':id', $testimonial_id, PDO::PARAM_INT);
            $stmt->execute();

            $message = "Le statut du témoignage a été mis à jour.";
            $messageType = "success";

            // Redirection vers la liste
            header("Location: testimonials.php?message=" . urlencode($message) . "&type=" . $messageType);
            exit;
        } catch (PDOException $e) {
            $message = "Erreur de base de données: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Récupérer un témoignage pour édition
if ($action === 'edit' && isset($_GET['id'])) {
    $testimonial_id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM testimonials WHERE id = ?");
        $stmt->execute([$testimonial_id]);
        $testimonial = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$testimonial) {
            $message = "Témoignage introuvable.";
            $messageType = "error";
            $action = 'list';
        }
    } catch (PDOException $e) {
        $message = "Erreur de base de données: " . $e->getMessage();
        $messageType = "error";
        $action = 'list';
    }
}

// Récupérer tous les témoignages pour la liste
$testimonials = [];
if ($action === 'list') {
    try {
        // Vérifier si la table testimonials existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'testimonials'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SELECT * FROM testimonials ORDER BY created_at DESC");
            $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $message = "Erreur de base de données: " . $e->getMessage();
        $messageType = "error";
    }
}

// Récupérer le message de l'URL si redirection
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $messageType = $_GET['type'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Témoignages - Version Simple</title>
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
            margin-right: 5px;
        }
        .button:hover { background-color: #6a0dad; }
        .button-danger { background-color: #b91c1c; }
        .button-danger:hover { background-color: #991b1b; }
        .button-success { background-color: #047857; }
        .button-success:hover { background-color: #065f46; }
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
        textarea { min-height: 100px; }
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
        .status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 12px;
        }
        .status-pending { background-color: #92400e; color: #fef3c7; }
        .status-approved { background-color: #065f46; color: #d1fae5; }
        .status-rejected { background-color: #991b1b; color: #fee2e2; }
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
        <h1><?php echo $action === 'list' ? 'Gestion des Témoignages' : ($action === 'edit' ? 'Modifier un Témoignage' : 'Nouveau Témoignage'); ?></h1>
        <div>
            <?php if ($action === 'list'): ?>
                <a href="?action=new" class="button">Nouveau Témoignage</a>
            <?php else: ?>
                <a href="testimonials.php" class="button">Retour à la liste</a>
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
        <?php if (empty($testimonials)): ?>
            <div class="card" style="text-align: center; padding: 40px;">
                <p>Aucun témoignage trouvé. Commencez par en ajouter un nouveau.</p>
                <a href="?action=new" class="button" style="margin-top: 15px;">Ajouter un témoignage</a>
            </div>
        <?php else: ?>
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Témoignage</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($testimonials as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['author_name'] ?? $item['name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars(substr($item['content'], 0, 100)) . (strlen($item['content']) > 100 ? '...' : ''); ?></td>
                                <td>
                                    <span class="status status-<?php echo $item['status']; ?>">
                                        <?php
                                            switch($item['status']) {
                                                case 'pending': echo 'En attente'; break;
                                                case 'approved': echo 'Approuvé'; break;
                                                case 'rejected': echo 'Rejeté'; break;
                                                default: echo $item['status'];
                                            }
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($item['created_at'])); ?></td>
                                <td>
                                    <a href="?action=edit&id=<?php echo $item['id']; ?>" class="button">Éditer</a>

                                    <?php if ($item['status'] === 'pending'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="testimonial_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="approve_testimonial" class="button button-success">Approuver</button>
                                        </form>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="testimonial_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="reject_testimonial" class="button button-danger">Rejeter</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="post" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce témoignage ?');">
                                        <input type="hidden" name="testimonial_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="delete_testimonial" class="button button-danger">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="card">
            <form method="post" action="testimonials.php">
                <?php if ($action === 'edit' && $testimonial): ?>
                    <input type="hidden" name="testimonial_id" value="<?php echo $testimonial['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="name">Nom du client *</label>
                    <input type="text" id="name" name="name" required value="<?php echo $testimonial ? htmlspecialchars($testimonial['author_name'] ?? $testimonial['name'] ?? '') : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="content">Témoignage *</label>
                    <textarea id="content" name="content" required><?php echo $testimonial ? htmlspecialchars($testimonial['content']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="status">Statut</label>
                    <select id="status" name="status">
                        <option value="pending" <?php echo ($testimonial && $testimonial['status'] === 'pending') ? 'selected' : ''; ?>>En attente</option>
                        <option value="approved" <?php echo ($testimonial && $testimonial['status'] === 'approved') ? 'selected' : ''; ?>>Approuvé</option>
                        <option value="rejected" <?php echo ($testimonial && $testimonial['status'] === 'rejected') ? 'selected' : ''; ?>>Rejeté</option>
                    </select>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <a href="testimonials.php" class="button" style="background-color: #4b5563;">Annuler</a>
                    <button type="submit" name="save_testimonial" class="button">
                        <?php echo $action === 'edit' ? 'Mettre à jour' : 'Ajouter'; ?> le témoignage
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</body>
</html>
