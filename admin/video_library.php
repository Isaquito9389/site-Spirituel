<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
// Affichage des erreurs PHP pour le débogage
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
$upload_dir = '../uploads/videos/';
$relative_upload_dir = 'uploads/videos/';

// Créer le dossier d'upload s'il n'existe pas
$upload_success = true;
if (!is_dir($upload_dir)) {
    if (!is_dir('../uploads/')) {
        if (!@mkdir('../uploads/', 0755, true)) {
            $upload_success = false;
            $message = "Impossible de créer le dossier 'uploads/'. Vérifiez les permissions du serveur.";
            $messageType = "error";
        }
    }
    if ($upload_success && !@mkdir($upload_dir, 0755, true)) {
        $upload_success = false;
        $message = "Impossible de créer le dossier 'uploads/videos/'. Vérifiez les permissions du serveur.";
        $messageType = "error";
    }
}

// Vérifier si le dossier est accessible en écriture
if ($upload_success && !is_writable($upload_dir)) {
    $upload_success = false;
    $message = "Le dossier 'uploads/videos/' existe mais n'est pas accessible en écriture. Vérifiez les permissions du serveur.";
    $messageType = "error";
}

// Fonction pour créer la table si elle n'existe pas
function createVideoLibraryTable($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS video_library (
            id INT AUTO_INCREMENT PRIMARY KEY,
            video_path VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) DEFAULT NULL,
            video_type ENUM('local', 'youtube', 'external') DEFAULT 'local',
            is_external TINYINT(1) DEFAULT 0,
            uploaded_by VARCHAR(100) NOT NULL,
            uploaded_at DATETIME NOT NULL,
            category VARCHAR(100) DEFAULT NULL,
            file_size INT DEFAULT NULL,
            duration VARCHAR(50) DEFAULT NULL,
            thumbnail_path VARCHAR(255) DEFAULT NULL,
            UNIQUE KEY unique_path (video_path)
        )");

        // Vérifier et ajouter les colonnes manquantes si la table existe déjà
        updateVideoLibraryTable($pdo);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Fonction pour mettre à jour la structure de la table existante
function updateVideoLibraryTable($pdo) {
    try {
        // Vérifier si la colonne original_name existe
        $stmt = $pdo->query("SHOW COLUMNS FROM video_library LIKE 'original_name'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE video_library ADD COLUMN original_name VARCHAR(255) DEFAULT NULL");
        }

        // Vérifier si la colonne video_type existe
        $stmt = $pdo->query("SHOW COLUMNS FROM video_library LIKE 'video_type'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE video_library ADD COLUMN video_type ENUM('local', 'youtube', 'external') DEFAULT 'local'");
        }

        // Vérifier si la colonne file_size existe
        $stmt = $pdo->query("SHOW COLUMNS FROM video_library LIKE 'file_size'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE video_library ADD COLUMN file_size INT DEFAULT NULL");
        }

        // Vérifier si la colonne duration existe
        $stmt = $pdo->query("SHOW COLUMNS FROM video_library LIKE 'duration'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE video_library ADD COLUMN duration VARCHAR(50) DEFAULT NULL");
        }

        // Vérifier si la colonne thumbnail_path existe
        $stmt = $pdo->query("SHOW COLUMNS FROM video_library LIKE 'thumbnail_path'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE video_library ADD COLUMN thumbnail_path VARCHAR(255) DEFAULT NULL");
        }

        // Mettre à jour les enregistrements existants sans original_name
        $pdo->exec("UPDATE video_library SET original_name = SUBSTRING_INDEX(video_path, '/', -1) WHERE original_name IS NULL OR original_name = ''");

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Fonction pour générer un nom de fichier sécurisé en gardant le nom original
function generateSafeFilename($originalName) {
    $pathinfo = pathinfo($originalName);
    $basename = $pathinfo['filename'];
    $extension = $pathinfo['extension'];

    // Nettoyer le nom de fichier (garder seulement les caractères alphanumériques, tirets et underscores)
    $safeName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $basename);
    $safeName = preg_replace('/_+/', '_', $safeName); // Remplacer les underscores multiples
    $safeName = trim($safeName, '_'); // Supprimer les underscores en début et fin

    // Si le nom est vide après nettoyage, utiliser un nom par défaut
    if (empty($safeName)) {
        $safeName = 'video_' . date('YmdHis');
    }

    // Limiter la longueur du nom
    if (strlen($safeName) > 50) {
        $safeName = substr($safeName, 0, 50);
    }

    return $safeName . '.' . $extension;
}

// Fonction pour extraire l'ID YouTube d'une URL
function extractYouTubeId($url) {
    $patterns = [
        '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/',
        '/youtu\.be\/([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/v\/([a-zA-Z0-9_-]+)/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    return false;
}

// Traitement de l'upload de vidéo
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Créer la table si elle n'existe pas
    createVideoLibraryTable($pdo);

    if (isset($_POST['upload_video'])) {
        // Vérifier si un fichier a été uploadé
        if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            if (!$upload_success) {
                $message = "L'upload de vidéos est désactivé car le dossier de destination n'est pas accessible en écriture. Utilisez plutôt une URL de vidéo externe.";
                $messageType = "error";
            } else {
                // Vérifier le type de fichier
                $allowed_types = ['video/mp4', 'video/avi', 'video/mov', 'video/wmv', 'video/flv', 'video/webm', 'video/mkv'];
                $file_type = $_FILES['video']['type'];

                // Vérification supplémentaire avec l'extension
                $file_extension = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'];

                if (in_array($file_type, $allowed_types) || in_array($file_extension, $allowed_extensions)) {
                    // Vérifier la taille du fichier (max 100 Mo)
                    if ($_FILES['video']['size'] > 100 * 1024 * 1024) {
                        $message = "La vidéo est trop volumineuse. La taille maximum est de 100 Mo.";
                        $messageType = "error";
                    } else {
                        // Générer un nom de fichier sécurisé en gardant le nom original
                        $original_name = $_FILES['video']['name'];
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
                        if (@move_uploaded_file($_FILES['video']['tmp_name'], $target_file)) {
                            try {
                                // Vérifier d'abord si les colonnes existent
                                updateVideoLibraryTable($pdo);

                                $stmt = $pdo->prepare("INSERT INTO video_library (video_path, original_name, video_type, is_external, uploaded_by, uploaded_at, file_size) VALUES (?, ?, 'local', 0, ?, NOW(), ?)");
                                $stmt->execute([
                                    $relative_upload_dir . $final_filename,
                                    $original_name,
                                    $admin_username,
                                    $_FILES['video']['size']
                                ]);

                                $message = "La vidéo '{$original_name}' a été uploadée avec succès sous le nom '{$final_filename}'.";
                                $messageType = "success";
                            } catch (PDOException $e) {
                                // Si erreur BD, supprimer le fichier uploadé
                                @unlink($target_file);
                                $message = "Erreur lors de l'enregistrement en base de données: " . $e->getMessage();
                                $messageType = "error";
                            }
                        } else {
                            $message = "Erreur lors de l'upload de la vidéo. Vérifiez les permissions du serveur.";
                            $messageType = "error";
                        }
                    }
                } else {
                    $message = "Seuls les fichiers MP4, AVI, MOV, WMV, FLV, WEBM et MKV sont autorisés.";
                    $messageType = "error";
                }
            }
        } elseif (isset($_POST['video_url']) && !empty($_POST['video_url'])) {
            // Traitement des URLs de vidéo
            $video_url = filter_var($_POST['video_url'], FILTER_VALIDATE_URL);
            if ($video_url) {
                $video_type = 'external';
                $original_name = 'video_externe';
                
                // Vérifier si c'est une URL YouTube
                $youtube_id = extractYouTubeId($video_url);
                if ($youtube_id) {
                    $video_type = 'youtube';
                    $original_name = 'YouTube: ' . $youtube_id;
                    // Normaliser l'URL YouTube
                    $video_url = 'https://www.youtube.com/watch?v=' . $youtube_id;
                } else {
                    // Pour les autres URLs, essayer d'extraire un nom depuis l'URL
                    $path_parts = pathinfo(parse_url($video_url, PHP_URL_PATH));
                    if (!empty($path_parts['filename'])) {
                        $original_name = $path_parts['filename'];
                    }
                }

                try {
                    // Vérifier d'abord si les colonnes existent
                    updateVideoLibraryTable($pdo);

                    $stmt = $pdo->prepare("INSERT INTO video_library (video_path, original_name, video_type, is_external, uploaded_by, uploaded_at) VALUES (?, ?, ?, 1, ?, NOW())");
                    $stmt->execute([
                        $video_url,
                        $original_name,
                        $video_type,
                        $admin_username
                    ]);
                    $message = "L'URL de vidéo " . ($video_type === 'youtube' ? 'YouTube' : 'externe') . " a été ajoutée à la bibliothèque.";
                    $messageType = "success";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), "Duplicate entry") !== false) {
                        $message = "Cette URL de vidéo existe déjà dans la bibliothèque.";
                        $messageType = "error";
                    } else {
                        $message = "Erreur lors de l'ajout de l'URL de vidéo: " . $e->getMessage();
                        $messageType = "error";
                    }
                }
            } else {
                $message = "L'URL fournie n'est pas valide.";
                $messageType = "error";
            }
        } else {
            $message = "Veuillez sélectionner une vidéo à uploader ou fournir une URL de vidéo.";
            $messageType = "error";
        }
    }
}

// Supprimer une vidéo (méthode POST sécurisée)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    // Vérifier le token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Token de sécurité invalide. Veuillez réessayer.";
        $messageType = "error";
    } else {
        $video_id = intval($_POST['id']);

        try {
            // Récupérer l'information de la vidéo
            $stmt = $pdo->prepare("SELECT * FROM video_library WHERE id = ?");
            $stmt->execute([$video_id]);
            $video = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($video) {
                // Supprimer la vidéo du système de fichiers si elle est locale
                if ($video['video_type'] === 'local' && !$video['is_external']) {
                    $file_path = $upload_dir . basename($video['video_path']);
                    if (file_exists($file_path)) {
                        @unlink($file_path);
                    }
                }

                // Supprimer l'entrée de la base de données
                $stmt = $pdo->prepare("DELETE FROM video_library WHERE id = ?");
                $stmt->execute([$video_id]);

                $message = "La vidéo '" . ($video['original_name'] ?? basename($video['video_path'])) . "' a été supprimée avec succès.";
                $messageType = "success";
            } else {
                $message = "Vidéo non trouvée.";
                $messageType = "error";
            }
        } catch (PDOException $e) {
            $message = "Erreur lors de la suppression de la vidéo: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Générer un token CSRF pour la sécurité
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Lire les vidéos de la base de données
$videos = [];
try {
    createVideoLibraryTable($pdo);

    $stmt = $pdo->prepare("SELECT * FROM video_library ORDER BY uploaded_at DESC");
    $stmt->execute();
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Vérifier l'existence des fichiers locaux et nettoyer les entrées orphelines
    $orphaned_ids = [];
    foreach ($videos as $key => $video) {
        if ($video['video_type'] === 'local' && !$video['is_external']) {
            $file_path = $upload_dir . basename($video['video_path']);
            if (!file_exists($file_path)) {
                $orphaned_ids[] = $video['id'];
                unset($videos[$key]);
            }
        }
    }

    // Supprimer les entrées orphelines de la base de données
    if (!empty($orphaned_ids)) {
        $placeholders = str_repeat('?,', count($orphaned_ids) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM video_library WHERE id IN ($placeholders)");
        $stmt->execute($orphaned_ids);
    }

} catch (PDOException $e) {
    $message = "Erreur lors de la lecture des vidéos: " . $e->getMessage();
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

// Fonction pour générer une miniature YouTube
function getYouTubeThumbnail($video_path) {
    $youtube_id = extractYouTubeId($video_path);
    if ($youtube_id) {
        return "https://img.youtube.com/vi/{$youtube_id}/mqdefault.jpg";
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <title>Bibliothèque de vidéos - Administration</title>
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
        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .video-item {
            position: relative;
            background: #374151;
            border-radius: 0.75rem;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .video-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
        }
        .video-item.selected {
            border-color: #9d4edd;
            box-shadow: 0 0 20px rgba(157, 78, 221, 0.4);
        }
        .video-container {
            aspect-ratio: 16/9;
            overflow: hidden;
            background: #4b5563;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .video-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }
        .video-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }
        .video-container .video-placeholder {
            color: #9ca3af;
            text-align: center;
            padding: 1rem;
        }
        .video-type-badge {
            position: absolute;
            top: 0.5rem;
            left: 0.5rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .video-type-local {
            background: rgba(34, 197, 94, 0.8);
            color: white;
        }
        .video-type-youtube {
            background: rgba(239, 68, 68, 0.8);
            color: white;
        }
        .video-type-external {
            background: rgba(59, 130, 246, 0.8);
            color: white;
        }
        .video-info {
            padding: 1rem;
        }
        .video-actions {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            display: flex;
            gap: 0.5rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .video-item:hover .video-actions {
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
        .upload-area {
            border: 2px dashed #6b7280;
            border-radius: 0.75rem;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .upload-area:hover, .upload-area.dragover {
            border-color: #9d4edd;
            background-color: rgba(157, 78, 221, 0.1);
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            color: white;
            font-weight: 500;
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        .notification.show {
            transform: translateX(0);
        }
        .notification.success {
            background: linear-gradient(45deg, #10b981, #34d399);
        }
        .notification.error {
            background: linear-gradient(45deg, #ef4444, #f87171);
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
                    <span class="text-gray-300">Bibliothèque de vidéos</span>
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
            <h1 class="text-3xl font-bold text-purple-300">Bibliothèque de vidéos</h1>
            <a href="dashboard.php" class="text-purple-400 hover:text-purple-300 transition">
                <i class="fas fa-arrow-left mr-1"></i> Retour au tableau de bord
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-800 text-green-100' : 'bg-red-800 text-red-100'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire d'upload amélioré -->
        <div class="bg-gray-800 rounded-lg p-6 mb-8 shadow-lg">
            <h2 class="text-xl font-bold mb-4 text-purple-300">Ajouter une nouvelle vidéo</h2>

            <form method="POST" enctype="multipart/form-data" action="video_library.php" id="uploadForm" class="space-y-6">
                <!-- Zone de drag & drop -->
                <div class="upload-area" id="uploadArea">
                    <div class="text-center">
                        <i class="fas fa-video text-4xl text-gray-400 mb-4"></i>
                        <p class="text-lg mb-2">Glissez-déposez votre vidéo ici</p>
                        <p class="text-gray-400 mb-4">ou</p>
                        <label for="video" class="btn-magic px-6 py-2 rounded-full text-white font-medium shadow-lg cursor-pointer inline-block">
                            <i class="fas fa-folder-open mr-2"></i> Parcourir les fichiers
                        </label>
                        <input type="file" id="video" name="video" class="hidden" accept="video/*">
                        <p class="text-gray-400 text-sm mt-4">Formats acceptés: MP4, AVI, MOV, WMV, FLV, WEBM, MKV (max 100 Mo)</p>
                    </div>
                </div>

                <!-- Prévisualisation -->
                <div id="videoPreview" class="hidden">
                    <h3 class="text-lg font-semibold mb-2 text-purple-300">Aperçu de la vidéo:</h3>
                    <div class="flex items-center space-x-4 p-4 bg-gray-700 rounded-lg">
                        <div class="w-20 h-20 bg-gray-600 rounded flex items-center justify-center">
                            <i class="fas fa-video text-gray-400"></i>
                        </div>
                        <div>
                            <p id="previewName" class="font-medium"></p>
                            <p id="previewSize" class="text-sm text-gray-400"></p>
                        </div>
                        <button type="button" id="removePreview" class="text-red-400 hover:text-red-300 ml-auto">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="text-center my-4 text-gray-400">- OU -</div>

                <div>
                    <label for="video_url" class="block text-gray-300 mb-2">URL de vidéo (YouTube ou autre)</label>
                    <input type="url" id="video_url" name="video_url" placeholder="https://www.youtube.com/watch?v=... ou https://exemple.com/video.mp4" class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-purple-500 focus:outline-none">
                    <p class="text-gray-400 text-sm mt-1">Entrez l'URL complète d'une vidéo YouTube ou hébergée en ligne</p>
                </div>

                <div>
                    <button type="submit" name="upload_video" class="btn-magic px-8 py-3 rounded-full text-white font-medium shadow-lg" id="submitBtn">
                        <i class="fas fa-upload mr-2"></i> Ajouter à la bibliothèque
                    </button>
                </div>
            </form>
        </div>

        <!-- Galerie de vidéos améliorée -->
        <div class="bg-gray-800 rounded-lg p-6 shadow-lg">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-purple-300">Vidéos disponibles (<?php echo count($videos); ?>)</h2>
                <div class="flex items-center space-x-4">
                    <input type="text" id="searchVideos" placeholder="Rechercher..." class="px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm focus:border-purple-500 focus:outline-none">
                    <button onclick="refreshGallery()" class="text-purple-400 hover:text-purple-300 transition" title="Actualiser">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>

            <?php if (empty($videos)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-video text-6xl text-gray-600 mb-4"></i>
                    <p class="text-gray-400 text-lg">Aucune vidéo n'est disponible dans la bibliothèque.</p>
                    <p class="text-gray-500 text-sm mt-2">Ajoutez votre première vidéo ci-dessus pour commencer.</p>
                </div>
            <?php else: ?>
                <div class="video-grid" id="videoGrid">
                    <?php foreach ($videos as $video): ?>
                        <div class="video-item" data-name="<?php echo htmlspecialchars(strtolower($video['original_name'])); ?>" data-path="<?php echo htmlspecialchars($video['video_path']); ?>" data-type="<?php echo htmlspecialchars($video['video_type']); ?>">
                            <div class="video-container">
                                <?php if ($video['video_type'] === 'youtube'): ?>
                                    <?php $thumbnail = getYouTubeThumbnail($video['video_path']); ?>
                                    <?php if ($thumbnail): ?>
                                        <img src="<?php echo htmlspecialchars($thumbnail); ?>" alt="<?php echo htmlspecialchars($video['original_name']); ?>" loading="lazy">
                                    <?php else: ?>
                                        <div class="video-placeholder">
                                            <i class="fab fa-youtube text-6xl text-red-500"></i>
                                            <p class="mt-2">Vidéo YouTube</p>
                                        </div>
                                    <?php endif; ?>
                                <?php elseif ($video['video_type'] === 'local'): ?>
                                    <video preload="metadata" muted>
                                        <source src="<?php echo htmlspecialchars('../' . $video['video_path']); ?>" type="video/mp4">
                                        <div class="video-placeholder">
                                            <i class="fas fa-video text-6xl"></i>
                                            <p class="mt-2">Vidéo locale</p>
                                        </div>
                                    </video>
                                <?php else: ?>
                                    <div class="video-placeholder">
                                        <i class="fas fa-external-link-alt text-6xl text-blue-500"></i>
                                        <p class="mt-2">Vidéo externe</p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="video-type-badge video-type-<?php echo $video['video_type']; ?>">
                                    <?php echo ucfirst($video['video_type']); ?>
                                </div>
                            </div>

                            <div class="video-actions">
                                <button onclick="selectVideo('<?php echo htmlspecialchars($video['video_path']); ?>', '<?php echo htmlspecialchars($video['video_type']); ?>', this)" class="action-btn" title="Sélectionner">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button onclick="copyVideoPath('<?php echo htmlspecialchars($video['video_path']); ?>')" class="action-btn" title="Copier le chemin">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <button onclick="deleteVideo(<?php echo $video['id']; ?>, '<?php echo htmlspecialchars($video['original_name']); ?>')" class="action-btn" title="Supprimer">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>

                            <div class="video-info">
                                <h3 class="font-medium text-sm mb-1 truncate" title="<?php echo htmlspecialchars($video['original_name']); ?>">
                                    <?php echo htmlspecialchars($video['original_name']); ?>
                                </h3>
                                <div class="text-xs text-gray-400 space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span><?php echo ucfirst($video['video_type']); ?></span>
                                        <?php if (isset($video['file_size']) && $video['file_size']): ?>
                                            <span><?php echo formatFileSize($video['file_size']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div><?php echo date('d/m/Y H:i', strtotime($video['uploaded_at'])); ?></div>
                                    <div>Par: <?php echo htmlspecialchars($video['uploaded_by'] ?? 'Inconnu'); ?></div>
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

    <!-- JavaScript intégré -->
    <script src="video_library_complete.js"></script>
</body>
</html>
