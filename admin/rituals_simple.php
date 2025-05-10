<?php
// Custom error handler to prevent 500 errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (error_reporting() === 0) {
        return false;
    }
    
    $error_message = "Error [$errno] $errstr - $errfile:$errline";
    error_log($error_message);
    
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

// Start session
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
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header("Location: index.php");
    exit;
}

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$messageType = '';
$ritual = null;
$rituals = [];
$categories = [];

// Try to include database connection
try {
    require_once 'includes/db_connect.php';
    $db_connected = true;
    
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
            $featured_image = isset($_POST['current_image']) ? $_POST['current_image'] : '';
            
            // Validate form data
            if (empty($title) || empty($content)) {
                $message = "Le titre et le contenu sont obligatoires.";
                $messageType = "error";
            } else {
                // Gestion des images avec support pour l'upload et les URL
                if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                    try {
                        $upload_dir = '../uploads/rituals/';
                        
                        // Créer le répertoire s'il n'existe pas
                        if (!file_exists($upload_dir)) {
                            if (!mkdir($upload_dir, 0755, true)) {
                                throw new Exception("Impossible de créer le dossier d'upload");
                            }
                        }
                        
                        // Générer un nom de fichier unique
                        $file_extension = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
                        $filename = uniqid() . '.' . $file_extension;
                        $target_file = $upload_dir . $filename;
                        
                        // Déplacer le fichier uploadé
                        if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $target_file)) {
                            $featured_image = 'uploads/rituals/' . $filename;
                        } else {
                            throw new Exception("Échec du déplacement du fichier uploadé");
                        }
                    } catch (Exception $e) {
                        // En cas d'erreur avec l'upload, on utilise l'URL si disponible
                        if (isset($_POST['image_url']) && !empty($_POST['image_url'])) {
                            $featured_image = $_POST['image_url'];
                        } elseif (isset($_POST['current_image']) && !empty($_POST['current_image'])) {
                            $featured_image = $_POST['current_image'];
                        }
                    }
                } elseif (isset($_POST['image_url']) && !empty($_POST['image_url'])) {
                    $featured_image = $_POST['image_url'];
                } elseif (isset($_POST['current_image']) && !empty($_POST['current_image'])) {
                    $featured_image = $_POST['current_image'];
                }
                
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
                    header("Location: rituals_simple.php?message=" . urlencode($message) . "&type=" . $messageType);
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
                header("Location: rituals_simple.php?message=" . urlencode($message) . "&type=" . $messageType);
                exit;
            } catch (PDOException $e) {
                $message = "Erreur de base de données: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
    
    // Get ritual data if editing
    if ($action === 'edit' && isset($_GET['id'])) {
        $ritual_id = intval($_GET['id']);
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM rituals WHERE id = :ritual_id");
            $stmt->bindParam(':ritual_id', $ritual_id, PDO::PARAM_INT);
            $stmt->execute();
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
    try {
        $stmt = $pdo->query("SELECT * FROM blog_categories ORDER BY name ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Silently fail, not critical
    }
} catch (Exception $e) {
    $db_connected = false;
    $message = "Erreur de connexion à la base de données: " . $e->getMessage();
    $messageType = "error";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Rituels (Version Simplifiée) - Mystica Occulta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
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
