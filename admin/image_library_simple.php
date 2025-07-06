<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';

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
$upload_error = '';

// Créer le dossier uploads principal
if (!is_dir('../uploads/')) {
    if (!@mkdir('../uploads/', 0755, true)) {
        $upload_success = false;
        $upload_error = "Impossible de créer le dossier 'uploads/'. Permissions insuffisantes.";
    }
}

// Créer le dossier images
if ($upload_success && !is_dir($upload_dir)) {
    if (!@mkdir($upload_dir, 0755, true)) {
        $upload_success = false;
        $upload_error = "Impossible de créer le dossier 'uploads/images/'. Permissions insuffisantes.";
    }
}

// Vérifier si le dossier est accessible en écriture
if ($upload_success && !is_writable($upload_dir)) {
    $upload_success = false;
    $upload_error = "Le dossier 'uploads/images/' n'est pas accessible en écriture. Permissions insuffisantes.";
}

// Fonction pour créer la table si elle n'existe pas
function createImageLibraryTable($pdo) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS image_library (
            id INT AUTO_INCREMENT PRIMARY KEY,
            image_path VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) DEFAULT NULL,
            is_external TINYINT(1) DEFAULT 0,
            uploaded_by VARCHAR(100) NOT NULL,
            uploaded_at DATETIME NOT NULL,
            category VARCHAR(100) DEFAULT NULL,
            file_size INT DEFAULT NULL,
            UNIQUE KEY unique_path (image_path)
        )";
        
        $pdo->exec($sql);
        return true;
    } catch (PDOException $e) {
        error_log("Erreur création table image_library: " . $e->getMessage());
        return false;
    }
}

// Fonction pour mettre à jour la structure de la table existante
function updateImageLibraryTable($pdo) {
    try {
        // Vérifier si la colonne original_name existe
        $stmt = $pdo->query("SHOW COLUMNS FROM image_library LIKE 'original_name'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE image_library ADD COLUMN original_name VARCHAR(255) DEFAULT NULL");
        }

        // Vérifier si la colonne file_size existe
        $stmt = $pdo->query("SHOW COLUMNS FROM image_library LIKE 'file_size'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE image_library ADD COLUMN file_size INT DEFAULT NULL");
        }

        // Mettre à jour les enregistrements existants sans original_name
        $pdo->exec("UPDATE image_library SET original_name = SUBSTRING_INDEX(image_path, '/', -1) WHERE original_name IS NULL OR original_name = ''");

        return true;
    } catch (PDOException $e) {
        error_log("Erreur mise à jour table image_library: " . $e->getMessage());
        return false;
    }
}

// Fonction pour générer un nom de fichier sécurisé
function generateSafeFilename($originalName) {
    $pathinfo = pathinfo($originalName);
    $basename = $pathinfo['filename'];
    $extension = strtolower($pathinfo['extension']);

    // Nettoyer le nom de fichier
    $safeName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $basename);
    $safeName = preg_replace('/_+/', '_', $safeName);
    $safeName = trim($safeName, '_');

    // Si le nom est vide après nettoyage, utiliser un nom par défaut
    if (empty($safeName)) {
        $safeName = 'image_' . date('YmdHis');
    }

    // Limiter la longueur du nom
    if (strlen($safeName) > 50) {
        $safeName = substr($safeName, 0, 50);
    }

    return $safeName . '.' . $extension;
}

// Fonction pour valider le type de fichier
function validateImageFile($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    $file_type = $file['type'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Vérification du type MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    return in_array($mime_type, $allowed_types) && in_array($file_extension, $allowed_extensions);
}

// Traitement de l'upload d'image
$message = '';
$messageType = '';

// Créer la table si elle n'existe pas
if (!createImageLibraryTable($pdo)) {
    $message = "Erreur lors de la création de la table de la bibliothèque d'images.";
    $messageType = "error";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload_image'])) {
        // Vérifier si un fichier a été uploadé
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            
            if (!$upload_success) {
                $message = "Erreur de configuration du serveur: " . $upload_error;
                $messageType = "error";
            } else {
                // Valider le type de fichier
                if (!validateImageFile($_FILES['image'])) {
                    $message = "Type de fichier non autorisé. Seuls les fichiers JPG, PNG, GIF et WEBP sont acceptés.";
                    $messageType = "error";
                } elseif ($_FILES['image']['size'] > 6 * 1024 * 1024) {
                    $message = "L'image est trop volumineuse. La taille maximum est de 6 Mo.";
                    $messageType = "error";
                } else {
                    // Générer un nom de fichier sécurisé
                    $original_name = $_FILES['image']['name'];
                    $safe_filename = generateSafeFilename($original_name);

                    // Vérifier si le fichier existe déjà et ajouter un suffixe si nécessaire
                    $counter = 1;
                    $final_filename = $safe_filename;
                    while (file_exists($upload_dir . $final_filename)) {
                        $pathinfo = pathinfo($safe_filename);
                        $final_filename = $pathinfo['filename'] . '_' . $counter . '.' . $pathinfo['extension'];
                        $counter++;
                    }

                    $target_file = $upload_dir . $final_filename;

                    // Tenter de déplacer le fichier uploadé
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        try {
                            // Mettre à jour la structure de la table
                            updateImageLibraryTable($pdo);
                            
                            // Insérer en base de données
                            $stmt = $pdo->prepare("INSERT INTO image_library (image_path, original_name, is_external, uploaded_by, uploaded_at, file_size, category) VALUES (?, ?, 0, ?, NOW(), ?, ?)");
                            $result = $stmt->execute([
                                $relative_upload_dir . $final_filename,
                                $original_name,
                                $admin_username,
                                $_FILES['image']['size'],
                                $_POST['category'] ?? null
                            ]);
                            
                            if ($result) {
                                $message = "L'image '{$original_name}' a été uploadée avec succès.";
                                $messageType = "success";
                            } else {
                                throw new Exception("Échec de l'insertion en base de données");
                            }
                        } catch (Exception $e) {
                            // Si erreur BD, supprimer le fichier uploadé
                            @unlink($target_file);
                            $message = "Erreur lors de l'enregistrement: " . $e->getMessage();
                            $messageType = "error";
                        }
                    } else {
                        $message = "Erreur lors du déplacement du fichier uploadé.";
                        $messageType = "error";
                    }
                }
            }
        } elseif (isset($_POST['image_url']) && !empty($_POST['image_url'])) {
            // Traitement de l'URL externe
            $image_url = filter_var($_POST['image_url'], FILTER_VALIDATE_URL);
            if ($image_url) {
                // Vérifier si l'URL pointe vers une image
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 10,
                        'user_agent' => 'Mozilla/5.0 (compatible; ImageLibrary/1.0)'
                    ]
                ]);
                
                $headers = @get_headers($image_url, 1, $context);
                
                if ($headers && strpos($headers[0], '200') !== false) {
                    $content_type = '';
                    if (isset($headers['Content-Type'])) {
                        $content_type = is_array($headers['Content-Type']) ? $headers['Content-Type'][0] : $headers['Content-Type'];
                    }
                    
                    if (strpos($content_type, 'image/') === 0) {
                        try {
                            updateImageLibraryTable($pdo);
                            
                            $stmt = $pdo->prepare("INSERT INTO image_library (image_path, original_name, is_external, uploaded_by, uploaded_at, category) VALUES (?, ?, 1, ?, NOW(), ?)");
                            $result = $stmt->execute([
                                $image_url,
                                basename(parse_url($image_url, PHP_URL_PATH)) ?: 'image_externe',
                                $admin_username,
                                $_POST['category'] ?? null
                            ]);
                            
                            if ($result) {
                                $message = "L'URL d'image externe a été ajoutée à la bibliothèque.";
                                $messageType = "success";
                            } else {
                                throw new Exception("Échec de l'insertion en base de données");
                            }
                        } catch (PDOException $e) {
                            if (strpos($e->getMessage(), "Duplicate entry") !== false) {
                                $message = "Cette URL d'image existe déjà dans la bibliothèque.";
                                $messageType = "error";
                            } else {
                                $message = "Erreur lors de l'ajout de l'URL d'image: " . $e->getMessage();
                                $messageType = "error";
                            }
                        }
                    } else {
                        $message = "L'URL fournie ne semble pas pointer vers une image valide.";
                        $messageType = "error";
                    }
                } else {
                    $message = "Impossible d'accéder à l'URL fournie.";
                    $messageType = "error";
                }
            } else {
                $message = "L'URL fournie n'est pas valide.";
                $messageType = "error";
            }
        } else {
            $message = "Veuillez sélectionner une image à uploader ou fournir une URL d'image.";
            $messageType = "error";
        }
    }
}

// Supprimer une image (méthode POST sécurisée)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    // Générer un token CSRF si nécessaire
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    // Vérifier le token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Token de sécurité invalide. Veuillez réessayer.";
        $messageType = "error";
    } else {
        $image_id = intval($_POST['id']);

        try {
            // Récupérer l'information de l'image
            $stmt = $pdo->prepare("SELECT * FROM image_library WHERE id = ?");
            $stmt->execute([$image_id]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($image) {
                // Supprimer l'image du système de fichiers si elle est locale
                if (!$image['is_external']) {
                    $file_path = $upload_dir . basename($image['image_path']);
                    if (file_exists($file_path)) {
                        @unlink($file_path);
                    }
                }

                // Supprimer l'entrée de la base de données
                $stmt = $pdo->prepare("DELETE FROM image_library WHERE id = ?");
                $stmt->execute([$image_id]);

                $message = "L'image '" . ($image['original_name'] ?? basename($image['image_path'])) . "' a été supprimée avec succès.";
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
}

// Générer un token CSRF pour la sécurité
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Lire les images de la base de données
$images = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM image_library ORDER BY uploaded_at DESC");
    $stmt->execute();
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Vérifier l'existence des fichiers locaux et nettoyer les entrées orphelines
    $orphaned_ids = [];
    foreach ($images as $key => $image) {
        if (!$image['is_external']) {
            $file_path = $upload_dir . basename($image['image_path']);
            if (!file_exists($file_path)) {
                $orphaned_ids[] = $image['id'];
                unset($images[$key]);
            }
        }
    }

    // Supprimer les entrées orphelines de la base de données
    if (!empty($orphaned_ids)) {
        $placeholders = str_repeat('?,', count($orphaned_ids) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM image_library WHERE id IN ($placeholders)");
        $stmt->execute($orphaned_ids);
    }

} catch (PDOException $e) {
    $message = "Erreur lors de la lecture des images: " . $e->getMessage();
    $messageType = "error";
}

// Fonction pour formater la taille de fichier
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

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
        .btn-magic:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        .image-item {
            position: relative;
            background: #374151;
            border-radius: 0.75rem;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .image-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
        }
        .image-container {
            aspect-ratio: 1 / 1;
            overflow: hidden;
            background: #4b5563;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.3s ease;
        }
        .image-container .error-placeholder {
            color: #9ca3af;
            text-align: center;
            padding: 1rem;
        }
        .image-item:hover .image-container img {
            transform: scale(1.05);
        }
        .image-info {
            padding: 1rem;
        }
        .image-actions {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            display: flex;
            gap: 0.5rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .image-item:hover .image-actions {
            opacity: 1;
        }
        .action-btn {
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .action-btn:hover {
            background: rgba(0, 0, 0, 0.9);
            transform: scale(1.1);
        }
        .debug-info {
            background: #374151;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            font-family: monospace;
            font-size: 0.875rem;
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
                    <span class="text-gray-300">Bibliothèque d'images (Version Simple)</span>
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
                <div class="flex items-center">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($debug_info)): ?>
            <div class="debug-info">
                <h3 class="font-bold mb-2 text-yellow-300">Informations de débogage :</h3>
                <div class="space-y-1 text-gray-300">
                    <?php foreach ($debug_info as $info): ?>
                        <div><?php echo htmlspecialchars($info); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Statut du système -->
        <div class="mb-6 p-4 rounded-lg bg-gray-800 border border-gray-700">
            <h3 class="font-bold mb-2 text-blue-300">Statut du système d'upload :</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div class="flex items-center">
                    <i class="fas fa-<?php echo $upload_success ? 'check-circle text-green-400' : 'times-circle text-red-400'; ?> mr-2"></i>
                    Dossier d'upload
                </div>
                <div class="flex items-center">
                    <i class="fas fa-<?php echo is_writable($upload_dir) ? 'check-circle text-green-400' : 'times-circle text-red-400'; ?> mr-2"></i>
                    Permissions écriture
                </div>
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-blue-400 mr-2"></i>
                    Max: <?php echo ini_get('upload_max_filesize'); ?>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-blue-400 mr-2"></i>
                    POST max: <?php echo ini_get('post_max_size'); ?>
                </div>
            </div>
            <?php if (!$upload_success): ?>
                <div class="mt-2 p-2 bg-red-900 rounded text-red-100 text-sm">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <?php echo htmlspecialchars($upload_error); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Formulaire d'upload simple -->
        <div class="bg-gray-800 rounded-lg p-6 mb-8 border border-gray-700">
            <h2 class="text-xl font-bold mb-4 text-purple-300">Ajouter une nouvelle image</h2>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <!-- Upload de fichier -->
                <div class="space-y-3">
                    <label for="image" class="block text-sm font-medium text-gray-300">
                        <i class="fas fa-upload mr-2"></i>Sélectionner une image
                    </label>
                    <input type="file" name="image" id="image" accept="image/*" 
                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-white">
                    <div class="text-xs text-gray-500">
                        Formats supportés: JPG, PNG, GIF, WEBP - Max: 5MB
                    </div>
                </div>

                <!-- Séparateur -->
                <div class="flex items-center my-6">
                    <div class="flex-1 border-t border-gray-600"></div>
                    <span class="px-4 text-gray-400 text-sm">OU</span>
                    <div class="flex-1 border-t border-gray-600"></div>
                </div>

                <!-- Upload par URL -->
                <div class="space-y-3">
                    <label for="image_url" class="block text-sm font-medium text-gray-300">
                        <i class="fas fa-link mr-2"></i>URL d'image externe
                    </label>
                    <input type="url" name="image_url" id="image_url" 
                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-white placeholder-gray-400"
                           placeholder="https://example.com/image.jpg">
                    <div class="text-xs text-gray-500">
                        L'image sera ajoutée comme référence externe (non téléchargée sur le serveur)
                    </div>
                </div>

                <!-- Options -->
                <div class="bg-gray-700 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-300 mb-3">Options</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-300 mb-2">
                                Catégorie
                            </label>
                            <select name="category" id="category" 
                                    class="w-full px-3 py-2 bg-gray-600 border border-gray-500 rounded-lg focus:ring-2 focus:ring-purple-500 text-white">
                                <option value="">Aucune catégorie</option>
                                <option value="banniere">Bannière</option>
                                <option value="produit">Produit</option>
                                <option value="galerie">Galerie</option>
                                <option value="avatar">Avatar</option>
                                <option value="background">Arrière-plan</option>
                                <option value="logo">Logo</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Bouton d'action -->
                <div class="flex gap-3">
                    <button type="submit" name="upload_image" 
                            class="btn-magic flex-1 px-6 py-3 text-white font-semibold rounded-lg transition-all duration-300">
                        <i class="fas fa-upload mr-2"></i>
                        Uploader l'image
                    </button>
                </div>

                <!-- Token CSRF -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            </form>
        </div>

        <!-- Statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-400">Total Images</p>
                        <p class="text-2xl font-bold text-purple-300"><?php echo count($images); ?></p>
                    </div>
                    <i class="fas fa-images text-3xl text-purple-400"></i>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-400">Images locales</p>
                        <p class="text-2xl font-bold text-blue-300"><?php echo count(array_filter($images, function($img) { return !$img['is_external']; })); ?></p>
                    </div>
                    <i class="fas fa-hdd text-3xl text-blue-400"></i>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-400">Images externes</p>
                        <p class="text-2xl font-bold text-green-300"><?php echo count(array_filter($images, function($img) { return $img['is_external']; })); ?></p>
                    </div>
                    <i class="fas fa-link text-3xl text-green-400"></i>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-400">Espace utilisé</p>
                        <p class="text-2xl font-bold text-orange-300">
                            <?php 
                            $totalSize = array_sum(array_map(function($img) { return $img['file_size'] ?? 0; }, $images));
                            echo formatFileSize($totalSize);
                            ?>
                        </p>
                    </div>
                    <i class="fas fa-database text-3xl text-orange-400"></i>
                </div>
            </div>
        </div>

        <!-- Liste des images -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-purple-300">Images dans la bibliothèque</h2>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="px-3 py-1 bg-purple-600 text-white text-sm rounded hover:bg-purple-700 transition-colors">
                    <i class="fas fa-sync-alt mr-1"></i>Actualiser
                </a>
            </div>

            <?php if (empty($images)): ?>
                <div class="text-center py-12 text-gray-400">
                    <i class="fas fa-image text-6xl mb-4"></i>
                    <p class="text-xl mb-2">Aucune image dans la bibliothèque</p>
                    <p class="text-sm">Commencez par uploader votre première image</p>
                </div>
            <?php else: ?>
                <div class="image-grid">
                    <?php foreach ($images as $image): ?>
                        <div class="image-item">
                            <!-- Actions -->
                            <div class="image-actions">
                                <button class="action-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($image['image_path']); ?>')" title="Copier l'URL">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <?php if (!$image['is_external']): ?>
                                    <a href="../<?php echo htmlspecialchars($image['image_path']); ?>" download class="action-btn" title="Télécharger">
                                        <i class="fas fa-download"></i>
                                    </a>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette image ?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $image['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <button type="submit" class="action-btn" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>

                            <!-- Container de l'image -->
                            <div class="image-container">
                                <?php if ($image['is_external']): ?>
                                    <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($image['original_name'] ?? 'Image externe'); ?>"
                                         loading="lazy"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <div class="error-placeholder" style="display: none;">
                                        <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                                        <p class="text-sm">Image externe indisponible</p>
                                    </div>
                                <?php else: ?>
                                    <img src="../<?php echo htmlspecialchars($image['image_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($image['original_name'] ?? basename($image['image_path'])); ?>"
                                         loading="lazy"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <div class="error-placeholder" style="display: none;">
                                        <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                                        <p class="text-sm">Image non trouvée</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Informations de l'image -->
                            <div class="image-info">
                                <h3 class="font-semibold text-white truncate mb-1" title="<?php echo htmlspecialchars($image['original_name'] ?? basename($image['image_path'])); ?>">
                                    <?php echo htmlspecialchars($image['original_name'] ?? basename($image['image_path'])); ?>
                                </h3>
                                <div class="text-xs text-gray-400 space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span>
                                            <i class="fas fa-<?php echo $image['is_external'] ? 'link' : 'hdd'; ?> mr-1"></i>
                                            <?php echo $image['is_external'] ? 'Externe' : 'Local'; ?>
                                        </span>
                                        <?php if ($image['category']): ?>
                                            <span class="bg-purple-600 text-white px-2 py-1 rounded-full text-xs">
                                                <?php echo htmlspecialchars($image['category']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($image['file_size']): ?>
                                        <div>
                                            <i class="fas fa-weight mr-1"></i>
                                            <?php echo formatFileSize($image['file_size']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <i class="fas fa-calendar mr-1"></i>
                                        <?php echo date('d/m/Y H:i', strtotime($image['uploaded_at'])); ?>
                                    </div>
                                    <div>
                                        <i class="fas fa-user mr-1"></i>
                                        <?php echo htmlspecialchars($image['uploaded_by']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Script JavaScript simple -->
    <script>
        // Fonction pour copier dans le presse-papiers
        function copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    showNotification('URL copiée dans le presse-papiers', 'success');
                });
            } else {
                // Fallback pour les navigateurs plus anciens
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    showNotification('URL copiée dans le presse-papiers', 'success');
                } catch (err) {
                    console.error('Erreur lors de la copie:', err);
                    showNotification('Erreur lors de la copie', 'error');
                }
                document.body.removeChild(textArea);
            }
        }

        // Système de notifications simple
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm`;
            
            const bgColor = {
                'success': 'bg-green-600',
                'error': 'bg-red-600',
                'warning': 'bg-yellow-600',
                'info': 'bg-blue-600'
            };
            
            const icon = {
                'success': 'fas fa-check-circle',
                'error': 'fas fa-exclamation-circle',
                'warning': 'fas fa-exclamation-triangle',
                'info': 'fas fa-info-circle'
            };
            
            notification.className += ` ${bgColor[type]} text-white`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="${icon[type]} mr-2"></i>
                    <span>${message}</span>
                    <button class="ml-2 text-white hover:text-gray-200" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Supprimer automatiquement après 5 secondes
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>
