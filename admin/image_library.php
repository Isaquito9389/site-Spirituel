<?php
// Affichage des erreurs PHP pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Obtenir le nom d'utilisateur admin
$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Inclure la connexion à la base de données
require_once 'includes/db_connect.php';

// Définir le dossier d'upload
$upload_dir = '../uploads/images/';
$relative_upload_dir = 'uploads/images/';

// Créer le dossier d'upload s'il n'existe pas
$upload_success = true;
if (!is_dir($upload_dir)) {
    // Créer d'abord le dossier uploads s'il n'existe pas
    if (!is_dir('../uploads/')) {
        if (!@mkdir('../uploads/', 0755, true)) {
            $upload_success = false;
            $message = "Impossible de créer le dossier 'uploads/'. Vérifiez les permissions du serveur.";
            $messageType = "error";
        }
    }
    // Puis créer le sous-dossier images
    if ($upload_success && !@mkdir($upload_dir, 0755, true)) {
        $upload_success = false;
        $message = "Impossible de créer le dossier 'uploads/images/'. Vérifiez les permissions du serveur.";
        $messageType = "error";
    }
}

// Vérifier si le dossier est accessible en écriture
if ($upload_success && !is_writable($upload_dir)) {
    $upload_success = false;
    $message = "Le dossier 'uploads/images/' existe mais n'est pas accessible en écriture. Vérifiez les permissions du serveur.";
    $messageType = "error";
}

// Traitement de l'upload d'image
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_image'])) {
    // Vérifier si un fichier a été uploadé
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Si le dossier d'upload n'est pas accessible en écriture, afficher un message d'erreur
        if (!$upload_success) {
            $message = "L'upload d'images est désactivé car le dossier de destination n'est pas accessible en écriture. Utilisez plutôt une URL d'image externe.";
            $messageType = "error";
        } else {
            // Vérifier le type de fichier
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                // Vérifier la taille du fichier (max 2 Mo)
                if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                    $message = "L'image est trop volumineuse. La taille maximum est de 2 Mo.";
                    $messageType = "error";
                } else {
                    // Générer un nom de fichier unique
                    $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $new_filename = uniqid('img_') . '.' . $file_extension;
                    $target_file = $upload_dir . $new_filename;
                    
                    // Tenter de déplacer le fichier uploadé
                    if (@move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        // Essayer d'ajouter l'entrée dans la base de données
                        try {
                            $stmt = $pdo->prepare("INSERT INTO image_library (image_path, is_external, uploaded_by, uploaded_at) VALUES (?, 0, ?, NOW())");
                            $stmt->execute([$relative_upload_dir . $new_filename, $admin_username]);
                        } catch (PDOException $e) {
                            // Si la table n'existe pas, on l'ignore pour le moment
                        }
                        
                        $message = "L'image a été uploadée avec succès.";
                        $messageType = "success";
                    } else {
                        $message = "Erreur lors de l'upload de l'image. Vérifiez les permissions sur InfinityFree. Utilisez plutôt une URL d'image externe.";
                        $messageType = "error";
                    }
                }
            } else {
                $message = "Seuls les fichiers JPG, PNG, GIF et WEBP sont autorisés.";
                $messageType = "error";
            }
        }
    } elseif (isset($_POST['image_url']) && !empty($_POST['image_url'])) {
        // Sauvegarder l'URL externe dans la base de données
        try {
            $stmt = $pdo->prepare("INSERT INTO image_library (image_path, is_external, uploaded_by, uploaded_at) VALUES (?, 1, ?, NOW())");
            $stmt->execute([$_POST['image_url'], $admin_username]);
            $message = "L'URL d'image externe a été ajoutée à la bibliothèque.";
            $messageType = "success";
        } catch (PDOException $e) {
            // Vérifier si la table existe, sinon la créer
            if (strpos($e->getMessage(), "Table") !== false && strpos($e->getMessage(), "doesn't exist") !== false) {
                try {
                    $pdo->exec("CREATE TABLE image_library (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        image_path VARCHAR(255) NOT NULL,
                        is_external TINYINT(1) DEFAULT 0,
                        uploaded_by VARCHAR(100) NOT NULL,
                        uploaded_at DATETIME NOT NULL,
                        category VARCHAR(100) DEFAULT NULL
                    )");
                    
                    // Réessayer l'insertion
                    $stmt = $pdo->prepare("INSERT INTO image_library (image_path, is_external, uploaded_by, uploaded_at) VALUES (?, 1, ?, NOW())");
                    $stmt->execute([$_POST['image_url'], $admin_username]);
                    $message = "L'URL d'image externe a été ajoutée à la bibliothèque.";
                    $messageType = "success";
                } catch (PDOException $e2) {
                    $message = "Erreur lors de la création de la table ou de l'ajout de l'URL: " . $e2->getMessage();
                    $messageType = "error";
                }
            } else {
                $message = "Erreur lors de l'ajout de l'URL d'image: " . $e->getMessage();
                $messageType = "error";
            }
        }
    } else {
        $message = "Veuillez sélectionner une image à uploader ou fournir une URL d'image.";
        $messageType = "error";
    }
}

// Supprimer une image
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $image_id = intval($_GET['id']);
    
    try {
        // Récupérer l'information de l'image
        $stmt = $pdo->prepare("SELECT * FROM image_library WHERE id = ?");
        $stmt->execute([$image_id]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($image) {
            // Supprimer l'image du système de fichiers si elle est locale
            if (!$image['is_external'] && file_exists($upload_dir . basename($image['image_path']))) {
                unlink($upload_dir . basename($image['image_path']));
            }
            
            // Supprimer l'entrée de la base de données
            $stmt = $pdo->prepare("DELETE FROM image_library WHERE id = ?");
            $stmt->execute([$image_id]);
            
            $message = "L'image a été supprimée avec succès.";
            $messageType = "success";
        } else {
            $message = "Image non trouvée.";
            $messageType = "error";
        }
    } catch (PDOException $e) {
        $message = "Erreur lors de la suppression de l'image: " . $e->getMessage();
        $messageType = "error";
    }
}

// Lire les images du dossier et de la base de données
$images = [];

// Lire les images locales du dossier uploads
if (is_dir($upload_dir)) {
    $files = scandir($upload_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && is_file($upload_dir . $file)) {
            $fileinfo = pathinfo($upload_dir . $file);
            if (in_array(strtolower($fileinfo['extension']), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $images[] = [
                    'id' => 'local_' . $file,
                    'path' => $relative_upload_dir . $file,
                    'name' => $file,
                    'is_external' => false,
                    'uploaded_by' => 'Système',
                    'uploaded_at' => date("Y-m-d H:i:s", filemtime($upload_dir . $file))
                ];
            }
        }
    }
}

// Lire les images de la base de données
try {
    // Vérifier si la table existe
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'image_library'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("SELECT * FROM image_library ORDER BY uploaded_at DESC");
        $stmt->execute();
        $db_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($db_images as $image) {
            // Éviter les doublons
            $exists = false;
            foreach ($images as $existing) {
                if (!$image['is_external'] && basename($image['image_path']) === basename($existing['path'])) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $images[] = [
                    'id' => $image['id'],
                    'path' => $image['image_path'],
                    'name' => basename($image['image_path']),
                    'is_external' => (bool)$image['is_external'],
                    'uploaded_by' => $image['uploaded_by'],
                    'uploaded_at' => $image['uploaded_at']
                ];
            }
        }
    }
} catch (PDOException $e) {
    $message = "Erreur lors de la lecture des images de la base de données: " . $e->getMessage();
    $messageType = "error";
}

// Trier les images par date (les plus récentes en premier)
usort($images, function($a, $b) {
    return strtotime($b['uploaded_at']) - strtotime($a['uploaded_at']);
});

// Le reste du script HTML commence ici
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bibliothèque d'images - Administration</title>
    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .dark-gradient {
            background: linear-gradient(135deg, #2d1b4e 0%, #1f1235 100%);
        }
        .btn-magic {
            background: linear-gradient(45deg, #9d4edd, #c77dff);
            transition: all 0.3s ease;
        }
        .btn-magic:hover {
            background: linear-gradient(45deg, #7b2cbf, #9d4edd);
            transform: translateY(-2px);
        }
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }
        .image-item {
            position: relative;
            aspect-ratio: 1 / 1;
            overflow: hidden;
            border-radius: 0.5rem;
            transition: transform 0.3s ease;
        }
        .image-item:hover {
            transform: scale(1.03);
        }
        .image-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 0.5rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .image-item:hover .image-overlay {
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <!-- Header avec navigation -->
    <header class="dark-gradient shadow-md">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <a href="dashboard.php" class="text-xl font-bold text-purple-300">Administration</a>
                    <span class="mx-2 text-purple-500">|</span>
                    <span class="text-gray-300">Bibliothèque d'images</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-400">Connecté en tant que <span class="text-purple-300"><?php echo htmlspecialchars($admin_username); ?></span></span>
                    <a href="dashboard.php?logout=true" class="text-red-400 hover:text-red-300 transition"><i class="fas fa-sign-out-alt mr-1"></i> Déconnexion</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Contenu principal -->
    <main class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-purple-300">Bibliothèque d'images</h1>
            <a href="dashboard.php" class="text-purple-400 hover:text-purple-300 transition">
                <i class="fas fa-arrow-left mr-1"></i> Retour au tableau de bord
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-800 text-green-100' : 'bg-red-800 text-red-100'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire d'upload -->
        <div class="bg-gray-800 rounded-lg p-6 mb-8 shadow-lg">
            <h2 class="text-xl font-bold mb-4 text-purple-300">Ajouter une nouvelle image</h2>
            
            <form method="POST" enctype="multipart/form-data" action="image_library.php" class="space-y-4">
                <div>
                    <label for="image" class="block text-gray-300 mb-2">Upload d'image</label>
                    <input type="file" id="image" name="image" class="w-full p-2 bg-gray-700 border border-gray-600 rounded-lg text-white">
                    <p class="text-gray-400 text-sm mt-1">Formats acceptés: JPG, PNG, GIF, WEBP</p>
                </div>

                <div class="text-center my-2 text-gray-400">- OU -</div>
                
                <div>
                    <label for="image_url" class="block text-gray-300 mb-2">URL d'image externe</label>
                    <input type="url" id="image_url" name="image_url" placeholder="https://exemple.com/image.jpg" class="w-full p-2 bg-gray-700 border border-gray-600 rounded-lg text-white">
                    <p class="text-gray-400 text-sm mt-1">Entrez l'URL complète d'une image hébergée en ligne</p>
                </div>
                
                <div>
                    <button type="submit" name="upload_image" class="btn-magic px-6 py-2 rounded-full text-white font-medium shadow-lg">
                        <i class="fas fa-upload mr-2"></i> Ajouter à la bibliothèque
                    </button>
                </div>
            </form>
        </div>

        <!-- Galerie d'images -->
        <div class="bg-gray-800 rounded-lg p-6 shadow-lg">
            <h2 class="text-xl font-bold mb-4 text-purple-300">Images disponibles (<?php echo count($images); ?>)</h2>
            
            <?php if (empty($images)): ?>
                <p class="text-gray-400">Aucune image n'est disponible dans la bibliothèque.</p>
            <?php else: ?>
                <div class="image-grid">
                    <?php foreach ($images as $image): ?>
                        <div class="image-item">
                            <img src="<?php echo htmlspecialchars($image['is_external'] ? $image['path'] : '../' . $image['path']); ?>" 
                                alt="<?php echo htmlspecialchars($image['name']); ?>" 
                                class="w-full h-full object-cover"
                                onclick="useImage('<?php echo htmlspecialchars($image['path']); ?>')">
                            
                            <div class="image-overlay">
                                <div class="flex justify-between items-center">
                                    <button onclick="copyImagePath('<?php echo htmlspecialchars($image['path']); ?>')" class="text-green-400 hover:text-green-300 transition" title="Copier le chemin">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    
                                    <?php if (is_numeric($image['id'])): ?>
                                        <a href="image_library.php?action=delete&id=<?php echo $image['id']; ?>" class="text-red-400 hover:text-red-300 transition" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette image?');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs mt-1 truncate" title="<?php echo htmlspecialchars($image['name']); ?>">
                                    <?php echo htmlspecialchars($image['name']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Pied de page -->
    <footer class="bg-gray-900 text-gray-500 py-4 border-t border-gray-800 mt-8">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; <?php echo date('Y'); ?> Mystica Occulta - Administration</p>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        // Vérifier si nous sommes ouverts dans une fenêtre ou un onglet
        const isPopup = window.opener && window.opener !== window;
        
        function useImage(path) {
            // Si nous sommes dans une fenêtre popup et que l'ouvreur est disponible
            if (isPopup && window.opener) {
                // Envoyer le chemin d'image à la fenêtre parente
                window.opener.postMessage({ imagePath: path }, window.location.origin);
                window.close(); // Fermer la fenêtre après sélection sans message
            } else {
                // Comportement normal - sélectionner et afficher sans notification
                // Mettre à jour un élément caché avec le chemin sélectionné
                if (document.getElementById('selected_image_path')) {
                    document.getElementById('selected_image_path').value = path;
                }
                
                // Mettre en évidence l'image sélectionnée visuellement
                document.querySelectorAll('.image-item').forEach(item => {
                    item.classList.remove('ring-2', 'ring-purple-500');
                });
                
                // Trouver et mettre en évidence l'image actuelle
                const imgContainer = event.currentTarget.closest('.image-item');
                if (imgContainer) {
                    imgContainer.classList.add('ring-2', 'ring-purple-500');
                }
            }
        }
        
        // Si nous sommes dans une popup, ajouter des informations pour l'utilisateur
        document.addEventListener('DOMContentLoaded', function() {
            if (isPopup) {
                const header = document.querySelector('.container h1');
                if (header) {
                    const selectionInfo = document.createElement('div');
                    selectionInfo.className = 'mt-2 text-yellow-300 text-sm';
                    selectionInfo.innerHTML = 'Mode sélection : cliquez sur une image pour la choisir';
                    header.insertAdjacentElement('afterend', selectionInfo);
                }
            }
        });
    </script>
</body>
</html>
