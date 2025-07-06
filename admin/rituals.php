<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
// Fichier : rituals.php
// Rôle : Gestion COMPLÈTE des rituels avec upload d'image intégré
// VERSION PRINCIPALE - REMPLACE L'ANCIEN SYSTÈME

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

$admin_username = $_SESSION['admin_username'] ?? 'Admin';

require_once 'includes/db_connect.php';
require_once 'includes/backlink_functions.php';

if (!isset($pdo)) { die("Erreur critique BDD."); }

// Configuration pour l'upload d'images et vidéos
$upload_dir_images = '../uploads/images/';
$upload_dir_videos = '../uploads/videos/';
$relative_upload_dir_images = 'uploads/images/';
$relative_upload_dir_videos = 'uploads/videos/';

// Créer les dossiers d'upload s'ils n'existent pas
if (!is_dir('../uploads/')) {
    @mkdir('../uploads/', 0755, true);
}
if (!is_dir($upload_dir_images)) {
    @mkdir($upload_dir_images, 0755, true);
}
if (!is_dir($upload_dir_videos)) {
    @mkdir($upload_dir_videos, 0755, true);
}

// Fonction pour créer la table image_library si elle n'existe pas
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
            ritual_id INT DEFAULT NULL,
            UNIQUE KEY unique_path (image_path),
            INDEX idx_ritual_id (ritual_id)
        )";
        $pdo->exec($sql);
        return true;
    } catch (PDOException $e) {
        error_log("Erreur création table image_library: " . $e->getMessage());
        return false;
    }
}

// Fonction pour créer la table video_library si elle n'existe pas
function createVideoLibraryTable($pdo) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS video_library (
            id INT AUTO_INCREMENT PRIMARY KEY,
            video_path VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) DEFAULT NULL,
            is_external TINYINT(1) DEFAULT 0,
            is_youtube TINYINT(1) DEFAULT 0,
            youtube_id VARCHAR(50) DEFAULT NULL,
            uploaded_by VARCHAR(100) NOT NULL,
            uploaded_at DATETIME NOT NULL,
            category VARCHAR(100) DEFAULT NULL,
            file_size INT DEFAULT NULL,
            ritual_id INT DEFAULT NULL,
            duration VARCHAR(20) DEFAULT NULL,
            thumbnail_path VARCHAR(255) DEFAULT NULL,
            UNIQUE KEY unique_path (video_path),
            INDEX idx_ritual_id (ritual_id),
            INDEX idx_youtube_id (youtube_id)
        )";
        $pdo->exec($sql);
        return true;
    } catch (PDOException $e) {
        error_log("Erreur création table video_library: " . $e->getMessage());
        return false;
    }
}

// Fonction pour générer un nom de fichier sécurisé
function generateSafeFilename($originalName) {
    $pathinfo = pathinfo($originalName);
    $basename = $pathinfo['filename'];
    $extension = strtolower($pathinfo['extension']);

    $safeName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $basename);
    $safeName = preg_replace('/_+/', '_', $safeName);
    $safeName = trim($safeName, '_');

    if (empty($safeName)) {
        $safeName = 'image_' . date('YmdHis');
    }

    if (strlen($safeName) > 50) {
        $safeName = substr($safeName, 0, 50);
    }

    return $safeName . '.' . $extension;
}

// Fonction pour valider le type de fichier image
function validateImageFile($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    return in_array($mime_type, $allowed_types) && in_array($file_extension, $allowed_extensions);
}

// Fonction pour valider le type de fichier vidéo
function validateVideoFile($file) {
    $allowed_types = ['video/mp4', 'video/webm', 'video/ogg', 'video/avi', 'video/mov', 'video/wmv'];
    $allowed_extensions = ['mp4', 'webm', 'ogg', 'avi', 'mov', 'wmv'];
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    return in_array($mime_type, $allowed_types) && in_array($file_extension, $allowed_extensions);
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

function slugify($text) {
    $text = mb_strtolower($text, 'UTF-8');
    if (function_exists('transliterator_transliterate')) {
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII; [\u0100-\u7fff] remove', $text);
    }
    $text = preg_replace('/[^\p{L}\p{Nd}]+/u', '-', $text);
    $text = trim($text, '-');
    $text = preg_replace('/-+/', '-', $text);
    return $text;
}

// Fonction pour nettoyer le texte des caractères indésirables (BOM, points d'interrogation, etc.)
function cleanText($text) {
    if (empty($text)) return $text;
    
    // Supprimer le BOM UTF-8 s'il existe
    $text = str_replace("\xEF\xBB\xBF", '', $text);
    
    // Supprimer les caractères de remplacement Unicode
    $text = str_replace("\xEF\xBF\xBD", '', $text);
    $text = str_replace('??', '', $text);
    
    // Supprimer les points d'interrogation en début de chaîne
    $text = preg_replace('/^\?+/', '', $text);
    
    // Supprimer les espaces invisibles et caractères de contrôle
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    
    // Supprimer les espaces multiples
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Nettoyer les guillemets et tirets problématiques
    $text = str_replace(chr(8220), '"', $text); // "
    $text = str_replace(chr(8221), '"', $text); // "
    $text = str_replace(chr(8216), "'", $text); // '
    $text = str_replace(chr(8217), "'", $text); // '
    $text = str_replace(chr(8211), '-', $text); // –
    $text = str_replace(chr(8212), '-', $text); // —
    
    // Supprimer les espaces en début et fin
    $text = trim($text);
    
    return $text;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = isset($_GET['message']) ? urldecode($_GET['message']) : '';
$messageType = isset($_GET['type']) ? $_GET['type'] : '';
$ritual = null;

// Créer les tables si nécessaires
createImageLibraryTable($pdo);
createVideoLibraryTable($pdo);

// Traitement AJAX pour l'upload de vidéo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_upload_video'])) {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => '', 'video_path' => ''];
    
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        if (!validateVideoFile($_FILES['video'])) {
            $response['message'] = "Type de fichier non autorisé. Seuls les fichiers MP4, WebM, OGG, AVI, MOV et WMV sont acceptés.";
        } elseif ($_FILES['video']['size'] > 50 * 1024 * 1024) { // 50MB max pour vidéos
            $response['message'] = "La vidéo est trop volumineuse. La taille maximum est de 50 Mo.";
        } else {
            $original_name = $_FILES['video']['name'];
            $safe_filename = generateSafeFilename($original_name);

            $counter = 
            $final_filename = $safe_filename;
            while (file_exists($upload_dir_videos . $final_filename)) {
                $pathinfo = pathinfo($safe_filename);
                $final_filename = $pathinfo['filename'] . '_' . $counter . '.' . $pathinfo['extension'];
                $counter++;
            }

            $target_file = $upload_dir_videos . $final_filename;

            if (move_uploaded_file($_FILES['video']['tmp_name'], $target_file)) {
                try {
                    $ritual_id = !empty($_POST['ritual_id']) ? intval($_POST['ritual_id']) : null;
                    $stmt = $pdo->prepare("INSERT INTO video_library (video_path, original_name, is_external, uploaded_by, uploaded_at, file_size, category, ritual_id) VALUES (?, ?, 0, ?, NOW(), ?, ?, ?)");
                    $result = $stmt->execute([
                        $relative_upload_dir_videos . $final_filename,
                        $original_name,
                        $admin_username,
                        $_FILES['video']['size'],
                        $_POST['category'] ?? 'rituel',
                        $ritual_id
                    ]);
                    
                    if ($result) {
                        $response['success'] = true;
                        $response['message'] = "Vidéo uploadée avec succès";
                        $response['video_path'] = $relative_upload_dir_videos . $final_filename;
                        $response['video_name'] = $original_name;
                    } else {
                        throw new Exception("Échec de l'insertion en base de données");
                    }
                } catch (Exception $e) {
                    @unlink($target_file);
                    $response['message'] = "Erreur lors de l'enregistrement: " . $e->getMessage();
                }
            } else {
                $response['message'] = "Erreur lors du déplacement du fichier uploadé.";
            }
        }
    } elseif (isset($_POST['youtube_url']) && !empty($_POST['youtube_url'])) {
        $youtube_url = filter_var($_POST['youtube_url'], FILTER_VALIDATE_URL);
        if ($youtube_url && extractYouTubeId($youtube_url)) {
            try {
                $youtube_id = extractYouTubeId($youtube_url);
                $ritual_id = !empty($_POST['ritual_id']) ? intval($_POST['ritual_id']) : null;
                $stmt = $pdo->prepare("INSERT INTO video_library (video_path, original_name, is_external, is_youtube, youtube_id, uploaded_by, uploaded_at, category, ritual_id) VALUES (?, ?, 1, 1, ?, ?, NOW(), ?, ?)");
                $result = $stmt->execute([
                    $youtube_url,
                    'Vidéo YouTube - ' . $youtube_id,
                    $youtube_id,
                    $admin_username,
                    $_POST['category'] ?? 'rituel',
                    $ritual_id
                ]);
                
                if ($result) {
                    $response['success'] = true;
                    $response['message'] = "URL YouTube ajoutée avec succès";
                    $response['video_path'] = $youtube_url;
                    $response['video_name'] = 'Vidéo YouTube - ' . $youtube_id;
                } else {
                    throw new Exception("Échec de l'insertion en base de données");
                }
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), "Duplicate entry") !== false) {
                    $response['message'] = "Cette URL YouTube existe déjà dans la bibliothèque.";
                } else {
                    $response['message'] = "Erreur lors de l'ajout de l'URL YouTube: " . $e->getMessage();
                }
            }
        } else {
            $response['message'] = "L'URL YouTube fournie n'est pas valide.";
        }
    } else {
        $response['message'] = "Veuillez sélectionner une vidéo à uploader ou fournir une URL YouTube.";
    }
    
    echo json_encode($response);
    exit;
}

// Traitement AJAX pour l'upload d'image
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_upload_image'])) {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => '', 'image_path' => ''];
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        if (!validateImageFile($_FILES['image'])) {
            $response['message'] = "Type de fichier non autorisé. Seuls les fichiers JPG, PNG, GIF et WEBP sont acceptés.";
        } elseif ($_FILES['image']['size'] > 6 * 1024 * 1024) {
            $response['message'] = "L'image est trop volumineuse. La taille maximum est de 6 Mo.";
        } else {
            $original_name = $_FILES['image']['name'];
            $safe_filename = generateSafeFilename($original_name);

            $counter = 1;
            $final_filename = $safe_filename;
            while (file_exists($upload_dir_images . $final_filename)) {
                $pathinfo = pathinfo($safe_filename);
                $final_filename = $pathinfo['filename'] . '_' . $counter . '.' . $pathinfo['extension'];
                $counter++;
            }

            $target_file = $upload_dir_images . $final_filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                try {
                    $ritual_id = !empty($_POST['ritual_id']) ? intval($_POST['ritual_id']) : null;
                    $stmt = $pdo->prepare("INSERT INTO image_library (image_path, original_name, is_external, uploaded_by, uploaded_at, file_size, category, ritual_id) VALUES (?, ?, 0, ?, NOW(), ?, ?, ?)");
                    $result = $stmt->execute([
                        $relative_upload_dir_images . $final_filename,
                        $original_name,
                        $admin_username,
                        $_FILES['image']['size'],
                        $_POST['category'] ?? 'rituel',
                        $ritual_id
                    ]);
                    
                    if ($result) {
                        $response['success'] = true;
                        $response['message'] = "Image uploadée avec succès";
                        $response['image_path'] = $relative_upload_dir_images . $final_filename;
                        $response['image_name'] = $original_name;
                    } else {
                        throw new Exception("Échec de l'insertion en base de données");
                    }
                } catch (Exception $e) {
                    @unlink($target_file);
                    $response['message'] = "Erreur lors de l'enregistrement: " . $e->getMessage();
                }
            } else {
                $response['message'] = "Erreur lors du déplacement du fichier uploadé.";
            }
        }
    } elseif (isset($_POST['image_url']) && !empty($_POST['image_url'])) {
        $image_url = filter_var($_POST['image_url'], FILTER_VALIDATE_URL);
        if ($image_url) {
            try {
                $ritual_id = !empty($_POST['ritual_id']) ? intval($_POST['ritual_id']) : null;
                $stmt = $pdo->prepare("INSERT INTO image_library (image_path, original_name, is_external, uploaded_by, uploaded_at, category, ritual_id) VALUES (?, ?, 1, ?, NOW(), ?, ?)");
                $result = $stmt->execute([
                    $image_url,
                    basename(parse_url($image_url, PHP_URL_PATH)) ?: 'image_externe',
                    $admin_username,
                    $_POST['category'] ?? 'rituel',
                    $ritual_id
                ]);
                
                if ($result) {
                    $response['success'] = true;
                    $response['message'] = "URL d'image externe ajoutée avec succès";
                    $response['image_path'] = $image_url;
                    $response['image_name'] = basename(parse_url($image_url, PHP_URL_PATH)) ?: 'image_externe';
                } else {
                    throw new Exception("Échec de l'insertion en base de données");
                }
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), "Duplicate entry") !== false) {
                    $response['message'] = "Cette URL d'image existe déjà dans la bibliothèque.";
                } else {
                    $response['message'] = "Erreur lors de l'ajout de l'URL d'image: " . $e->getMessage();
                }
            }
        } else {
            $response['message'] = "L'URL fournie n'est pas valide.";
        }
    } else {
        $response['message'] = "Veuillez sélectionner une image à uploader ou fournir une URL d'image.";
    }
    
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_ritual'])) {
        $ritual_id = isset($_POST['ritual_id']) ? intval($_POST['ritual_id']) : 0;
        $title = cleanText(trim($_POST['title']));
        $excerpt = cleanText(trim($_POST['excerpt'] ?? ''));
        $content = cleanText(trim($_POST['content'] ?? ''));
        $category = cleanText(trim($_POST['category'] ?? ''));
        $duration = cleanText(trim($_POST['duration'] ?? ''));
        $price = trim($_POST['price'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        $featured_image = trim($_POST['featured_image'] ?? '');
        $youtube_url = trim($_POST['youtube_url'] ?? '');
        $mark_as_recent = isset($_POST['mark_as_recent']) && $_POST['mark_as_recent'] === '1';

        if (empty(trim(strip_tags($content)))) {
            $message = "Le contenu du rituel ne peut pas être vide. Veuillez ajouter du contenu.";
            $messageType = "error";
        }
        elseif (empty($title)) {
            $message = "Le titre est obligatoire.";
            $messageType = "error";
        } else {
            $slug = slugify($title);
            $check = $pdo->prepare("SELECT id FROM rituals WHERE slug = ? AND id != ?");
            $unique_slug = $slug;
            $i = 1;
            while (true) {
                $check->execute([$unique_slug, $ritual_id]);
                if ($check->fetch() === false) break;
                $unique_slug = $slug . '-' . $i++;
            }

            try {
                if ($ritual_id > 0) {
                    $sql = "UPDATE rituals SET title = :title, slug = :slug, excerpt = :excerpt, content = :content, category = :category, duration = :duration, price = :price, status = :status, featured_image = :featured_image, youtube_url = :youtube_url, updated_at = NOW() WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id', $ritual_id, PDO::PARAM_INT);
                } else {
                    $sql = "INSERT INTO rituals (title, slug, excerpt, content, category, duration, price, status, featured_image, youtube_url, created_at, updated_at, author) VALUES (:title, :slug, :excerpt, :content, :category, :duration, :price, :status, :featured_image, :youtube_url, NOW(), NOW(), :author)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':author', $admin_username, PDO::PARAM_STR);
                }

                $stmt->bindParam(':title', $title, PDO::PARAM_STR);
                $stmt->bindParam(':slug', $unique_slug, PDO::PARAM_STR);
                $stmt->bindParam(':excerpt', $excerpt, PDO::PARAM_STR);
                $stmt->bindParam(':content', $content, PDO::PARAM_STR);
                $stmt->bindParam(':category', $category, PDO::PARAM_STR);
                $stmt->bindParam(':duration', $duration, PDO::PARAM_STR);
                $stmt->bindParam(':price', $price, PDO::PARAM_STR);
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
                $stmt->bindParam(':featured_image', $featured_image, PDO::PARAM_STR);
                $stmt->bindParam(':youtube_url', $youtube_url, PDO::PARAM_STR);

                $stmt->execute();

                if ($ritual_id === 0) {
                    $ritual_id = $pdo->lastInsertId();
                }

                // Mettre à jour les images associées dans image_library
                if (!empty($featured_image)) {
                    try {
                        $stmt = $pdo->prepare("UPDATE image_library SET ritual_id = ? WHERE image_path = ?");
                        $stmt->execute([$ritual_id, $featured_image]);
                    } catch (PDOException $e) {
                        // Ignorer l'erreur si la table n'existe pas encore
                    }
                }

                if ($mark_as_recent && $ritual_id > 0) {
                    $updateStmt = $pdo->prepare("UPDATE rituals SET updated_at = NOW() WHERE id = :id");
                    $updateStmt->execute([':id' => $ritual_id]);
                    $message = "Le rituel a été enregistré avec succès et marqué comme récent.";
                } else {
                    $message = "Le rituel a été enregistré avec succès.";
                }

                header("Location: rituals.php?action=edit&id=" . $ritual_id . "&message=" . urlencode($message) . "&type=success");
                exit;

            } catch (PDOException $e) {
                $message = "Erreur de base de données : " . $e->getMessage();
                $messageType = "error";
            }
        }
    } elseif (isset($_POST['delete_ritual'])) {
        $ritual_id = intval($_POST['ritual_id']);
        try {
            $stmt = $pdo->prepare("DELETE FROM rituals WHERE id = :id");
            $stmt->execute([':id' => $ritual_id]);
            header("Location: rituals.php?message=" . urlencode("Le rituel a été supprimé.") . "&type=success");
            exit;
        } catch (PDOException $e) {
            $message = "Erreur lors de la suppression : " . $e->getMessage();
            $messageType = "error";
        }
    }
}

if ($action === 'edit' && isset($_GET['id'])) {
    $ritual_id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM rituals WHERE id = ?");
    $stmt->execute([$ritual_id]);
    $ritual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ritual) {
        header("Location: rituals.php?message=" . urlencode("Rituel introuvable.") . "&type=error");
        exit;
    }
}

$rituals = [];
if ($action === 'list') {
    $stmt = $pdo->query("SELECT id, title, category, status, created_at, updated_at, slug FROM rituals
                        ORDER BY
                        CASE
                            WHEN updated_at > created_at THEN updated_at
                            ELSE created_at
                        END DESC,
                        updated_at DESC,
                        created_at DESC,
                        id DESC");
    $rituals = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$categories = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT category as name FROM rituals WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* Ignoré */ }

// Récupérer les images de la bibliothèque
$images = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM image_library ORDER BY uploaded_at DESC LIMIT 20");
    $stmt->execute();
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Ignorer l'erreur si la table n'existe pas encore
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Rituels - Mystica Occulta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700&family=Merriweather&display=swap');
        :root { --primary: #3a0ca3; --secondary: #7209b7; --accent: #f72585; --dark: #1a1a2e; }
        body { font-family: 'Merriweather', serif; background-color: #0f0e17; color: #e8e8e8; }
        .font-cinzel { font-family: 'Cinzel Decorative', cursive; }
        .nav-link:hover, .nav-link.active { background: linear-gradient(90deg, rgba(114, 9, 183, 0.3) 0%, transparent 100%); border-left: 4px solid var(--accent); }
        .card { background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%); }
        input, select, textarea { background-color: var(--dark); border: 1px solid #3a0ca3; transition: all 0.2s ease-in-out; }
        input:focus, select:focus, textarea:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 0 2px rgba(247, 37, 133, 0.3); }
        
        /* Styles pour la section d'upload d'image intégrée */
        .image-upload-section {
            background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
            border: 2px dashed #3a0ca3;
            border-radius: 15px;
            padding: 2rem;
            margin: 1rem 0;
            transition: all 0.3s ease;
        }
        
        .image-upload-section:hover {
            border-color: #f72585;
            background: linear-gradient(145deg, #1a1a2e 0%, rgba(247, 37, 133, 0.1) 100%);
        }
        
        .image-preview {
            max-width: 300px;
            max-height: 200px;
            border-radius: 10px;
            margin: 1rem 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }
        
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            max-height: 300px;
            overflow-y: auto;
            padding: 1rem;
            background: rgba(26, 26, 46, 0.5);
            border-radius: 10px;
            margin: 1rem 0;
        }
        
        .image-gallery-item {
            position: relative;
            aspect-ratio: 1;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .image-gallery-item:hover {
            transform: scale(1.05);
            border-color: #f72585;
        }
        
        .image-gallery-item.selected {
            border-color: #f72585;
            box-shadow: 0 0 15px rgba(247, 37, 133, 0.5);
        }
        
        .image-gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .upload-progress {
            display: none;
            width: 100%;
            height: 4px;
            background: rgba(58, 12, 163, 0.3);
            border-radius: 2px;
            overflow: hidden;
            margin: 1rem 0;
        }
        
        .upload-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #f72585, #3a0ca3);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            color: white;
            font-weight: 500;
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .notification.error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
    </style>
</head>
<body class="bg-dark min-h-screen flex flex-col">
    <header class="bg-dark bg-opacity-90 backdrop-blur-sm border-b border-purple-900 py-3 sticky top-0 z-20">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <a href="dashboard.php" class="flex items-center space-x-2">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-900 to-pink-600 flex items-center justify-center">
                    <i class="fas fa-moon text-white text-xl"></i>
                </div>
                <span class="font-cinzel text-2xl font-bold bg-gradient-to-r from-purple-400 to-pink-600 bg-clip-text text-transparent">MYSTICA OCCULTA</span>
            </a>
            <a href="?logout=true" class="text-gray-300 hover:text-pink-500 transition">
                <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
            </a>
        </div>
    </header>

    <div class="flex flex-1">
        <aside class="w-64 border-r border-purple-900 flex-shrink-0" style="background: #1a1a2e;">
            <nav class="py-6">
                <ul>
                    <li><a href="dashboard.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white"><i class="fas fa-tachometer-alt w-6"></i><span>Tableau de Bord</span></a></li>
                    <li><a href="rituals.php" class="nav-link flex items-center px-6 py-3 text-white active"><i class="fas fa-magic w-6"></i><span>Rituels</span></a></li>
                    <li><a href="image_library.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white" target="_blank"><i class="fas fa-images w-6"></i><span>Bibliothèque Images</span></a></li>
                </ul>
            </nav>
        </aside>

        <main class="flex-1 p-4 sm:p-8">
            <div class="flex justify-between items-center mb-8">
                <h1 class="font-cinzel text-2xl sm:text-3xl font-bold text-white">
                    <?php echo $action === 'edit' ? 'Modifier le Rituel' : ($action === 'new' ? 'Nouveau Rituel' : 'Gestion des Rituels'); ?>
                </h1>
                <?php if ($action === 'list'): ?>
                    <a href="?action=new" class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg text-white font-medium inline-flex items-center shadow-lg">
                        <i class="fas fa-plus mr-2"></i>Nouveau Rituel
                    </a>
                <?php else: ?>
                    <a href="rituals.php" class="px-4 py-2 rounded-lg border border-purple-600 text-white inline-flex items-center hover:bg-purple-900 transition">
                        <i class="fas fa-arrow-left mr-2"></i>Retour à la liste
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-500/20 text-green-300 border border-green-500/30' : 'bg-red-500/20 text-red-300 border border-red-500/30'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <div class="card rounded-xl p-0 sm:p-2 border border-purple-900/50">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="hidden sm:table-header-group">
                                <tr class="border-b border-purple-800">
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-purple-300">Titre</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-purple-300">Statut</th>
                                    <th class="px-4 py-3 text-right text-sm font-semibold text-purple-300">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rituals as $r): ?>
                                    <tr class="border-b border-purple-900/50 hover:bg-purple-900/20">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-white"><?php echo htmlspecialchars($r['title']); ?></div>
                                            <div class="text-sm text-gray-400 sm:hidden"><?php echo htmlspecialchars($r['category']); ?></div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php 
                                            $status = $r['status'] ?? 'draft'; 
                                            $status_class = ($status === 'published') ? 'bg-green-500/20 text-green-300' : 'bg-yellow-500/20 text-yellow-300'; 
                                            ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $status_class; ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right text-lg space-x-2">
                                            <a href="?action=edit&id=<?php echo $r['id']; ?>" class="text-blue-400 hover:text-blue-300" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="../<?php echo urlencode($r['slug']); ?>" target="_blank" class="text-purple-400 hover:text-purple-300" title="Voir la page">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" onclick="confirmDelete(<?php echo $r['id']; ?>, '<?php echo addslashes(htmlspecialchars($r['title'])); ?>')" class="text-red-400 hover:text-red-300 bg-transparent border-none cursor-pointer" title="Supprimer">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <form id="ritualForm" method="POST" action="rituals.php" class="card rounded-xl p-6 sm:p-8 border border-purple-900/50">
                    <input type="hidden" name="ritual_id" value="<?php echo $ritual['id'] ?? 0; ?>">
                    
                    <div class="space-y-8">
                        <!-- Titre -->
                        <div>
                            <label for="title" class="block text-gray-300 mb-2 font-medium">Titre *</label>
                            <input type="text" id="title" name="title" required class="w-full px-4 py-2 rounded-lg" value="<?php echo htmlspecialchars($ritual['title'] ?? ''); ?>">
                        </div>

                        <!-- Extrait -->
                        <div>
                            <label for="excerpt" class="block text-gray-300 mb-2 font-medium">Extrait</label>
                            <textarea id="excerpt" name="excerpt" rows="3" class="w-full px-4 py-2 rounded-lg"><?php echo htmlspecialchars($ritual['excerpt'] ?? ''); ?></textarea>
                        </div>

                        <!-- Section Upload de Média (Image ou Vidéo) -->
                        <div class="pt-6 border-t border-purple-800/50">
                            <h3 class="text-xl font-cinzel font-bold text-white mb-4">
                                <i class="fas fa-photo-video mr-2 text-purple-400"></i>
                                Média du Rituel (Image ou Vidéo)
                            </h3>
                            
                            <!-- Sélecteur de type de média -->
                            <div class="mb-6">
                                <div class="flex gap-4 mb-4">
                                    <button type="button" id="selectImageTab" class="media-tab-btn active bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg text-white font-medium">
                                        <i class="fas fa-image mr-2"></i>Image
                                    </button>
                                    <button type="button" id="selectVideoTab" class="media-tab-btn bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg text-white font-medium">
                                        <i class="fas fa-video mr-2"></i>Vidéo
                                    </button>
                                </div>
                            </div>
                            
                            <div class="image-upload-section">
                                <div class="text-center mb-4">
                                    <i class="fas fa-cloud-upload-alt text-4xl text-purple-400 mb-2"></i>
                                    <h4 class="text-lg font-medium text-white mb-2">Ajouter une Image</h4>
                                    <p class="text-gray-400 text-sm">Glissez-déposez une image ou cliquez pour sélectionner</p>
                                </div>

                                <!-- Zone de drop pour les fichiers -->
                                <div id="dropZone" class="border-2 border-dashed border-purple-600 rounded-lg p-6 text-center cursor-pointer hover:border-pink-500 transition-colors">
                                    <input type="file" id="imageUpload" accept="image/*" class="hidden">
                                    <div id="dropText">
                                        <i class="fas fa-upload text-2xl text-purple-400 mb-2"></i>
                                        <p class="text-white">Cliquez ici ou glissez une image</p>
                                        <p class="text-sm text-gray-400">JPG, PNG, GIF, WEBP - Max 6MB</p>
                                    </div>
                                </div>

                                <!-- Barre de progression -->
                                <div class="upload-progress" id="uploadProgress">
                                    <div class="upload-progress-bar" id="uploadProgressBar"></div>
                                </div>

                                <!-- Séparateur OU -->
                                <div class="flex items-center my-4">
                                    <div class="flex-1 border-t border-gray-600"></div>
                                    <span class="px-4 text-gray-400 text-sm">OU</span>
                                    <div class="flex-1 border-t border-gray-600"></div>
                                </div>

                                <!-- URL externe -->
                                <div class="flex gap-2">
                                    <input type="url" id="externalImageUrl" placeholder="https://exemple.com/image.jpg" class="flex-1 px-4 py-2 rounded-lg">
                                    <button type="button" id="addExternalImage" class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg text-white">
                                        <i class="fas fa-link mr-1"></i>Ajouter URL
                                    </button>
                                </div>

                                <!-- Aperçu de l'image sélectionnée -->
                                <div id="imagePreviewContainer" class="mt-4" style="display: none;">
                                    <h5 class="text-white font-medium mb-2">Aperçu :</h5>
                                    <img id="imagePreview" class="image-preview" alt="Aperçu">
                                    <div class="flex gap-2 mt-2">
                                        <button type="button" id="useThisImage" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded-lg text-white">
                                            <i class="fas fa-check mr-1"></i>Utiliser cette image
                                        </button>
                                        <button type="button" id="removeImage" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg text-white">
                                            <i class="fas fa-times mr-1"></i>Supprimer
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Galerie d'images existantes -->
                            <?php if (!empty($images)): ?>
                                <div class="mt-6">
                                    <h4 class="text-lg font-medium text-white mb-3">
                                        <i class="fas fa-images mr-2"></i>
                                        Ou choisir dans la bibliothèque
                                    </h4>
                                    <div class="image-gallery">
                                        <?php foreach ($images as $img): ?>
                                            <div class="image-gallery-item" data-image-path="<?php echo htmlspecialchars($img['image_path']); ?>" data-image-name="<?php echo htmlspecialchars($img['original_name'] ?? basename($img['image_path'])); ?>">
                                                <?php if ($img['is_external']): ?>
                                                    <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="<?php echo htmlspecialchars($img['original_name'] ?? 'Image'); ?>" loading="lazy">
                                                <?php else: ?>
                                                    <img src="../<?php echo htmlspecialchars($img['image_path']); ?>" alt="<?php echo htmlspecialchars($img['original_name'] ?? 'Image'); ?>" loading="lazy">
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Section Upload de Vidéo (cachée par défaut) -->
                            <div id="videoUploadSection" class="image-upload-section" style="display: none;">
                                <div class="text-center mb-4">
                                    <i class="fas fa-video text-4xl text-red-400 mb-2"></i>
                                    <h4 class="text-lg font-medium text-white mb-2">Ajouter une Vidéo</h4>
                                    <p class="text-gray-400 text-sm">Glissez-déposez une vidéo ou cliquez pour sélectionner</p>
                                </div>

                                <!-- Zone de drop pour les vidéos -->
                                <div id="videoDropZone" class="border-2 border-dashed border-red-600 rounded-lg p-6 text-center cursor-pointer hover:border-pink-500 transition-colors">
                                    <input type="file" id="videoUpload" accept="video/*" class="hidden">
                                    <div id="videoDropText">
                                        <i class="fas fa-upload text-2xl text-red-400 mb-2"></i>
                                        <p class="text-white">Cliquez ici ou glissez une vidéo</p>
                                        <p class="text-sm text-gray-400">MP4, WebM, OGG, AVI, MOV, WMV - Max 50MB</p>
                                    </div>
                                </div>

                                <!-- Barre de progression vidéo -->
                                <div class="upload-progress" id="videoUploadProgress">
                                    <div class="upload-progress-bar" id="videoUploadProgressBar"></div>
                                </div>

                                <!-- Séparateur OU -->
                                <div class="flex items-center my-4">
                                    <div class="flex-1 border-t border-gray-600"></div>
                                    <span class="px-4 text-gray-400 text-sm">OU</span>
                                    <div class="flex-1 border-t border-gray-600"></div>
                                </div>

                                <!-- URL YouTube -->
                                <div class="flex gap-2">
                                    <input type="url" id="youtubeVideoUrl" placeholder="https://www.youtube.com/watch?v=..." class="flex-1 px-4 py-2 rounded-lg">
                                    <button type="button" id="addYouTubeVideo" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg text-white">
                                        <i class="fab fa-youtube mr-1"></i>Ajouter YouTube
                                    </button>
                                </div>

                                <!-- Aperçu de la vidéo sélectionnée -->
                                <div id="videoPreviewContainer" class="mt-4" style="display: none;">
                                    <h5 class="text-white font-medium mb-2">Aperçu :</h5>
                                    <div id="videoPreview" class="bg-gray-800 rounded-lg p-4 text-center">
                                        <i class="fas fa-video text-4xl text-gray-400 mb-2"></i>
                                        <p id="videoPreviewName" class="text-white"></p>
                                    </div>
                                    <div class="flex gap-2 mt-2">
                                        <button type="button" id="useThisVideo" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded-lg text-white">
                                            <i class="fas fa-check mr-1"></i>Utiliser cette vidéo
                                        </button>
                                        <button type="button" id="removeVideo" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg text-white">
                                            <i class="fas fa-times mr-1"></i>Supprimer
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Champ caché pour l'image sélectionnée -->
                            <input type="hidden" name="featured_image" id="featuredImage" value="<?php echo htmlspecialchars($ritual['featured_image'] ?? ''); ?>">
                        </div>

                        <!-- Contenu -->
                        <div class="pt-6 border-t border-purple-800/50">
                            <label class="block text-gray-300 mb-4 text-lg font-medium">Contenu Détaillé *</label>
                            <textarea name="content" id="content" rows="15" class="w-full px-4 py-2 rounded-lg font-mono text-sm" required><?php echo htmlspecialchars($ritual['content'] ?? '<p>Commencez à écrire...</p>'); ?></textarea>
                        </div>

                        <!-- Métadonnées -->
                        <div class="pt-6 border-t border-purple-800/50 grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="category" class="block text-gray-300 mb-2 font-medium">Catégorie</label>
                                <input type="text" list="category-list" id="category" name="category" class="w-full px-4 py-2 rounded-lg" value="<?php echo htmlspecialchars($ritual['category'] ?? ''); ?>" placeholder="Entrez ou choisissez">
                                <datalist id="category-list">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['name']); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div>
                                <label for="price" class="block text-gray-300 mb-2 font-medium">Prix (€)</label>
                                <input type="text" id="price" name="price" class="w-full px-4 py-2 rounded-lg" value="<?php echo htmlspecialchars($ritual['price'] ?? ''); ?>">
                            </div>
                            <div>
                                <label for="duration" class="block text-gray-300 mb-2 font-medium">Durée</label>
                                <input type="text" id="duration" name="duration" class="w-full px-4 py-2 rounded-lg" value="<?php echo htmlspecialchars($ritual['duration'] ?? ''); ?>" placeholder="ex: 7 jours">
                            </div>
                        </div>

                        <!-- Vidéo YouTube -->
                        <div class="pt-6 border-t border-purple-800/50">
                            <label for="youtube_url" class="block text-gray-300 mb-2 font-medium">
                                <i class="fab fa-youtube mr-2 text-red-400"></i>
                                URL Vidéo YouTube (optionnel)
                            </label>
                            <input type="url" id="youtube_url" name="youtube_url" class="w-full px-4 py-2 rounded-lg" value="<?php echo htmlspecialchars($ritual['youtube_url'] ?? ''); ?>" placeholder="https://www.youtube.com/watch?v=...">
                        </div>

                        <!-- Statut -->
                        <div class="pt-6 border-t border-purple-800/50">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="status" class="block text-gray-300 mb-2 font-medium">Statut</label>
                                    <select id="status" name="status" class="w-full px-4 py-2 rounded-lg">
                                        <option value="draft" <?php echo (!isset($ritual['status']) || $ritual['status'] === 'draft') ? 'selected' : ''; ?>>Brouillon</option>
                                        <option value="published" <?php echo (isset($ritual['status']) && $ritual['status'] === 'published') ? 'selected' : ''; ?>>Publié</option>
                                    </select>
                                </div>
                                <?php if (isset($ritual['id']) && $ritual['id'] > 0): ?>
                                    <div class="flex items-center">
                                        <input type="checkbox" id="mark_as_recent" name="mark_as_recent" value="1" class="w-4 h-4 text-purple-600 bg-gray-800 border-gray-600 rounded focus:ring-purple-500">
                                        <label for="mark_as_recent" class="ml-2 text-gray-300 font-medium">Marquer comme récent</label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Boutons d'action -->
                    <div class="mt-8 flex justify-end space-x-4">
                        <a href="rituals.php" class="px-6 py-3 rounded-lg border border-gray-600 text-white font-medium hover:bg-gray-700 transition">
                            Annuler
                        </a>
                        <button type="submit" name="save_ritual" class="bg-purple-600 hover:bg-purple-700 px-8 py-3 rounded-lg text-white font-semibold shadow-lg">
                            <i class="fas fa-save mr-2"></i>
                            Enregistrer et Publier
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modal de suppression -->
    <div id="deleteModal" class="fixed inset-0 bg-black/75 flex items-center justify-center z-50 hidden">
        <div class="card rounded-xl p-8 max-w-md w-full border border-purple-800">
            <h2 class="font-cinzel text-2xl mb-4">Confirmer la suppression</h2>
            <p class="text-gray-300 mb-6">Êtes-vous sûr de vouloir supprimer : <strong id="deleteRitualTitle" class="font-semibold text-white"></strong> ?</p>
            <div class="flex justify-end space-x-4">
                <button onclick="closeDeleteModal()" class="px-4 py-2 rounded-lg border border-gray-600 text-white hover:bg-gray-700">Annuler</button>
                <form id="deleteForm" method="POST" action="rituals.php">
                    <input type="hidden" name="ritual_id" id="deleteRitualId">
                    <button type="submit" name="delete_ritual" class="px-4 py-2 rounded-lg bg-red-700 text-white hover:bg-red-800">Supprimer</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Container pour les notifications -->
    <div id="notificationContainer"></div>

    <script>
        // Variables globales
        let currentImagePath = '';
        let currentVideoPath = '';
        let isUploading = false;
        let currentMediaType = 'image'; // 'image' ou 'video'

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            initializeMediaTabs();
            initializeImageUpload();
            initializeVideoUpload();
            initializeImageGallery();
            initializeForm();
        });

        // Initialiser les onglets média
        function initializeMediaTabs() {
            const imageTab = document.getElementById('selectImageTab');
            const videoTab = document.getElementById('selectVideoTab');
            const imageSection = document.querySelector('.image-upload-section');
            const videoSection = document.getElementById('videoUploadSection');

            imageTab.addEventListener('click', function() {
                // Activer l'onglet image
                imageTab.classList.remove('bg-gray-600');
                imageTab.classList.add('bg-purple-600', 'active');
                videoTab.classList.remove('bg-purple-600', 'active');
                videoTab.classList.add('bg-gray-600');
                
                // Afficher la section image
                imageSection.style.display = 'block';
                videoSection.style.display = 'none';
                
                currentMediaType = 'image';
            });

            videoTab.addEventListener('click', function() {
                // Activer l'onglet vidéo
                videoTab.classList.remove('bg-gray-600');
                videoTab.classList.add('bg-purple-600', 'active');
                imageTab.classList.remove('bg-purple-600', 'active');
                imageTab.classList.add('bg-gray-600');
                
                // Afficher la section vidéo
                videoSection.style.display = 'block';
                imageSection.style.display = 'none';
                
                currentMediaType = 'video';
            });
        }

        // Initialiser l'upload de vidéo
        function initializeVideoUpload() {
            const videoDropZone = document.getElementById('videoDropZone');
            const videoUpload = document.getElementById('videoUpload');
            const youtubeVideoUrl = document.getElementById('youtubeVideoUrl');
            const addYouTubeVideo = document.getElementById('addYouTubeVideo');

            // Gestion du drag & drop pour vidéos
            videoDropZone.addEventListener('click', () => videoUpload.click());
            videoDropZone.addEventListener('dragover', handleVideoDragOver);
            videoDropZone.addEventListener('drop', handleVideoDrop);
            videoDropZone.addEventListener('dragleave', handleVideoDragLeave);

            // Gestion de la sélection de fichier vidéo
            videoUpload.addEventListener('change', handleVideoFileSelect);

            // Gestion de l'URL YouTube
            addYouTubeVideo.addEventListener('click', handleYouTubeVideo);
            youtubeVideoUrl.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handleYouTubeVideo();
                }
            });

            // Boutons d'action sur l'aperçu vidéo
            document.getElementById('useThisVideo').addEventListener('click', confirmVideoSelection);
            document.getElementById('removeVideo').addEventListener('click', removeVideoSelection);
        }

        // Gestion du drag & drop pour vidéos
        function handleVideoDragOver(e) {
            e.preventDefault();
            e.currentTarget.classList.add('border-pink-500');
        }

        function handleVideoDragLeave(e) {
            e.currentTarget.classList.remove('border-pink-500');
        }

        function handleVideoDrop(e) {
            e.preventDefault();
            e.currentTarget.classList.remove('border-pink-500');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleVideoFile(files[0]);
            }
        }

        // Gestion de la sélection de fichier vidéo
        function handleVideoFileSelect(e) {
            const file = e.target.files[0];
            if (file) {
                handleVideoFile(file);
            }
        }

        // Traitement du fichier vidéo
        function handleVideoFile(file) {
            if (!file.type.startsWith('video/')) {
                showNotification('Veuillez sélectionner un fichier vidéo.', 'error');
                return;
            }

            if (file.size > 50 * 1024 * 1024) {
                showNotification('La vidéo est trop volumineuse. Maximum 50MB.', 'error');
                return;
            }

            uploadVideo(file);
        }

        // Upload de vidéo via AJAX
        function uploadVideo(file) {
            if (isUploading) return;
            
            isUploading = true;
            showVideoUploadProgress(true);

            const formData = new FormData();
            formData.append('video', file);
            formData.append('ajax_upload_video', '1');
            formData.append('ritual_id', document.querySelector('input[name="ritual_id"]').value);
            formData.append('category', 'rituel');

            fetch('rituals.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    showVideoPreview(data.video_path, data.video_name);
                    currentVideoPath = data.video_path;
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('Erreur lors de l\'upload de la vidéo.', 'error');
            })
            .finally(() => {
                isUploading = false;
                showVideoUploadProgress(false);
            });
        }

        // Gestion de l'URL YouTube
        function handleYouTubeVideo() {
            const url = document.getElementById('youtubeVideoUrl').value.trim();
            if (!url) {
                showNotification('Veuillez entrer une URL YouTube.', 'error');
                return;
            }

            if (isUploading) return;
            
            isUploading = true;
            showVideoUploadProgress(true);

            const formData = new FormData();
            formData.append('youtube_url', url);
            formData.append('ajax_upload_video', '1');
            formData.append('ritual_id', document.querySelector('input[name="ritual_id"]').value);
            formData.append('category', 'rituel');

            fetch('rituals.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    showVideoPreview(data.video_path, data.video_name);
                    currentVideoPath = data.video_path;
                    document.getElementById('youtubeVideoUrl').value = '';
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('Erreur lors de l\'ajout de l\'URL YouTube.', 'error');
            })
            .finally(() => {
                isUploading = false;
                showVideoUploadProgress(false);
            });
        }

        // Afficher l'aperçu de la vidéo
        function showVideoPreview(videoPath, videoName) {
            const container = document.getElementById('videoPreviewContainer');
            const previewName = document.getElementById('videoPreviewName');
            
            previewName.textContent = videoName;
            container.style.display = 'block';
        }

        // Confirmer la sélection de vidéo
        function confirmVideoSelection() {
            if (currentVideoPath) {
                document.getElementById('youtube_url').value = currentVideoPath;
                showNotification('Vidéo sélectionnée pour le rituel.', 'success');
            }
        }

        // Supprimer la sélection de vidéo
        function removeVideoSelection() {
            document.getElementById('videoPreviewContainer').style.display = 'none';
            document.getElementById('youtube_url').value = '';
            currentVideoPath = '';
            
            showNotification('Vidéo supprimée de la sélection.', 'success');
        }

        // Afficher/masquer la barre de progression vidéo
        function showVideoUploadProgress(show) {
            const progress = document.getElementById('videoUploadProgress');
            const progressBar = document.getElementById('videoUploadProgressBar');
            
            if (show) {
                progress.style.display = 'block';
                progressBar.style.width = '0%';
                setTimeout(() => progressBar.style.width = '100%', 100);
            } else {
                setTimeout(() => {
                    progress.style.display = 'none';
                    progressBar.style.width = '0%';
                }, 500);
            }
        }

        // Initialiser l'upload d'image
        function initializeImageUpload() {
            const dropZone = document.getElementById('dropZone');
            const imageUpload = document.getElementById('imageUpload');
            const externalImageUrl = document.getElementById('externalImageUrl');
            const addExternalImage = document.getElementById('addExternalImage');

            // Gestion du drag & drop
            dropZone.addEventListener('click', () => imageUpload.click());
            dropZone.addEventListener('dragover', handleDragOver);
            dropZone.addEventListener('drop', handleDrop);
            dropZone.addEventListener('dragleave', handleDragLeave);

            // Gestion de la sélection de fichier
            imageUpload.addEventListener('change', handleFileSelect);

            // Gestion de l'URL externe
            addExternalImage.addEventListener('click', handleExternalImage);
            externalImageUrl.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handleExternalImage();
                }
            });

            // Boutons d'action sur l'aperçu
            document.getElementById('useThisImage').addEventListener('click', confirmImageSelection);
            document.getElementById('removeImage').addEventListener('click', removeImageSelection);
        }

        // Initialiser la galerie d'images
        function initializeImageGallery() {
            const galleryItems = document.querySelectorAll('.image-gallery-item');
            galleryItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Désélectionner les autres
                    galleryItems.forEach(i => i.classList.remove('selected'));
                    // Sélectionner celui-ci
                    this.classList.add('selected');
                    
                    const imagePath = this.dataset.imagePath;
                    const imageName = this.dataset.imageName;
                    
                    showImagePreview(imagePath, imageName);
                    currentImagePath = imagePath;
                });
            });
        }

        // Initialiser le formulaire
        function initializeForm() {
            const form = document.getElementById('ritualForm');
            form.addEventListener('submit', function(e) {
                // Vérifier que le contenu n'est pas vide
                const content = document.getElementById('content').value.trim();
                if (!content || content === '<p>Commencez à écrire...</p>') {
                    e.preventDefault();
                    showNotification('Le contenu du rituel ne peut pas être vide.', 'error');
                    return false;
                }

                // Mettre à jour le champ image si une image est sélectionnée
                if (currentImagePath) {
                    document.getElementById('featuredImage').value = currentImagePath;
                }
            });
        }

        // Gestion du drag & drop
        function handleDragOver(e) {
            e.preventDefault();
            e.currentTarget.classList.add('border-pink-500');
        }

        function handleDragLeave(e) {
            e.currentTarget.classList.remove('border-pink-500');
        }

        function handleDrop(e) {
            e.preventDefault();
            e.currentTarget.classList.remove('border-pink-500');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFile(files[0]);
            }
        }

        // Gestion de la sélection de fichier
        function handleFileSelect(e) {
            const file = e.target.files[0];
            if (file) {
                handleFile(file);
            }
        }

        // Traitement du fichier
        function handleFile(file) {
            if (!file.type.startsWith('image/')) {
                showNotification('Veuillez sélectionner un fichier image.', 'error');
                return;
            }

            if (file.size > 6 * 1024 * 1024) {
                showNotification('L\'image est trop volumineuse. Maximum 6MB.', 'error');
                return;
            }

            uploadImage(file);
        }

        // Upload d'image via AJAX
        function uploadImage(file) {
            if (isUploading) return;
            
            isUploading = true;
            showUploadProgress(true);

            const formData = new FormData();
            formData.append('image', file);
            formData.append('ajax_upload_image', '1');
            formData.append('ritual_id', document.querySelector('input[name="ritual_id"]').value);
            formData.append('category', 'rituel');

            fetch('rituals.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    showImagePreview(data.image_path, data.image_name);
                    currentImagePath = data.image_path;
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('Erreur lors de l\'upload de l\'image.', 'error');
            })
            .finally(() => {
                isUploading = false;
                showUploadProgress(false);
            });
        }

        // Gestion de l'URL externe
        function handleExternalImage() {
            const url = document.getElementById('externalImageUrl').value.trim();
            if (!url) {
                showNotification('Veuillez entrer une URL d\'image.', 'error');
                return;
            }

            if (isUploading) return;
            
            isUploading = true;
            showUploadProgress(true);

            const formData = new FormData();
            formData.append('image_url', url);
            formData.append('ajax_upload_image', '1');
            formData.append('ritual_id', document.querySelector('input[name="ritual_id"]').value);
            formData.append('category', 'rituel');

            fetch('rituals.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    showImagePreview(data.image_path, data.image_name);
                    currentImagePath = data.image_path;
                    document.getElementById('externalImageUrl').value = '';
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('Erreur lors de l\'ajout de l\'URL d\'image.', 'error');
            })
            .finally(() => {
                isUploading = false;
                showUploadProgress(false);
            });
        }

        // Afficher l'aperçu de l'image
        function showImagePreview(imagePath, imageName) {
            const container = document.getElementById('imagePreviewContainer');
            const preview = document.getElementById('imagePreview');
            
            // Déterminer l'URL complète de l'image
            let fullImagePath = imagePath;
            if (!imagePath.startsWith('http') && !imagePath.startsWith('../')) {
                fullImagePath = '../' + imagePath;
            }
            
            preview.src = fullImagePath;
            preview.alt = imageName;
            container.style.display = 'block';
        }

        // Confirmer la sélection d'image
        function confirmImageSelection() {
            if (currentImagePath) {
                document.getElementById('featuredImage').value = currentImagePath;
                showNotification('Image sélectionnée pour le rituel.', 'success');
            }
        }

        // Supprimer la sélection d'image
        function removeImageSelection() {
            document.getElementById('imagePreviewContainer').style.display = 'none';
            document.getElementById('featuredImage').value = '';
            currentImagePath = '';
            
            // Désélectionner dans la galerie
            document.querySelectorAll('.image-gallery-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            showNotification('Image supprimée de la sélection.', 'success');
        }

        // Afficher/masquer la barre de progression
        function showUploadProgress(show) {
            const progress = document.getElementById('uploadProgress');
            const progressBar = document.getElementById('uploadProgressBar');
            
            if (show) {
                progress.style.display = 'block';
                progressBar.style.width = '0%';
                // Animation de progression
                setTimeout(() => progressBar.style.width = '100%', 100);
            } else {
                setTimeout(() => {
                    progress.style.display = 'none';
                    progressBar.style.width = '0%';
                }, 500);
            }
        }

        // Système de notifications
        function showNotification(message, type = 'info') {
            const container = document.getElementById('notificationContainer');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <div class="flex items-center justify-between">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            container.appendChild(notification);
            
            // Afficher avec animation
            setTimeout(() => notification.classList.add('show'), 100);
            
            // Supprimer automatiquement après 5 secondes
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.classList.remove('show');
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }

        // Fonctions pour la modal de suppression
        function confirmDelete(id, title) {
            document.getElementById('deleteRitualId').value = id;
            document.getElementById('deleteRitualTitle').textContent = title;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Initialiser l'image existante si présente
        document.addEventListener('DOMContentLoaded', function() {
            const existingImage = document.getElementById('featuredImage').value;
            if (existingImage) {
                currentImagePath = existingImage;
                showImagePreview(existingImage, 'Image actuelle');
                
                // Sélectionner dans la galerie si présent
                const galleryItem = document.querySelector(`[data-image-path="${existingImage}"]`);
                if (galleryItem) {
                    galleryItem.classList.add('selected');
                }
            }
        });
    </script>
</body>
</html>
