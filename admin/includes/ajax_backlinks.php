<?php
/**
 * AJAX Handler for Backlinks Management
 * 
 * This file handles AJAX requests for backlinks operations.
 */

// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

// Include database connection and backlink functions
require_once 'db_connect.php';
require_once 'backlink_functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if database connection is successful
if (!is_db_connected()) {
    echo json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']);
    exit;
}

// Get the action from POST data
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            // Add a new backlink
            $content_type = $_POST['content_type'] ?? '';
            $content_id = intval($_POST['content_id'] ?? 0);
            $url = trim($_POST['url'] ?? '');
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $type = $_POST['type'] ?? 'reference';
            
            // Validate required fields
            if (empty($content_type) || $content_id <= 0 || empty($url) || empty($title)) {
                echo json_encode(['success' => false, 'error' => 'Champs obligatoires manquants']);
                exit;
            }
            
            // Validate content type
            if (!in_array($content_type, ['blog', 'ritual'])) {
                echo json_encode(['success' => false, 'error' => 'Type de contenu invalide']);
                exit;
            }
            
            // Validate URL format
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                echo json_encode(['success' => false, 'error' => 'URL invalide']);
                exit;
            }
            
            // Add the backlink
            $backlink_id = add_backlink($content_type, $content_id, $url, $title, $description, $type);
            
            if ($backlink_id) {
                echo json_encode(['success' => true, 'id' => $backlink_id]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'ajout du backlink']);
            }
            break;
            
        case 'update':
            // Update an existing backlink
            $id = intval($_POST['id'] ?? 0);
            $url = trim($_POST['url'] ?? '');
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $type = $_POST['type'] ?? 'reference';
            $status = $_POST['status'] ?? 'active';
            
            // Validate required fields
            if ($id <= 0 || empty($url) || empty($title)) {
                echo json_encode(['success' => false, 'error' => 'Champs obligatoires manquants']);
                exit;
            }
            
            // Validate URL format
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                echo json_encode(['success' => false, 'error' => 'URL invalide']);
                exit;
            }
            
            // Update the backlink
            $result = update_backlink($id, $url, $title, $description, $type, $status);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour du backlink']);
            }
            break;
            
        case 'delete':
            // Delete a backlink
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID invalide']);
                exit;
            }
            
            $result = delete_backlink($id);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression du backlink']);
            }
            break;
            
        case 'add_internal':
            // Add an internal link
            $source_type = $_POST['source_type'] ?? '';
            $source_id = intval($_POST['source_id'] ?? 0);
            $target_type = $_POST['target_type'] ?? '';
            $target_id = intval($_POST['target_id'] ?? 0);
            $anchor_text = trim($_POST['anchor_text'] ?? '');
            
            // Validate required fields
            if (empty($source_type) || $source_id <= 0 || empty($target_type) || $target_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Champs obligatoires manquants']);
                exit;
            }
            
            // Validate content types
            if (!in_array($source_type, ['blog', 'ritual']) || !in_array($target_type, ['blog', 'ritual'])) {
                echo json_encode(['success' => false, 'error' => 'Type de contenu invalide']);
                exit;
            }
            
            // Prevent self-linking
            if ($source_type === $target_type && $source_id === $target_id) {
                echo json_encode(['success' => false, 'error' => 'Impossible de créer un lien vers soi-même']);
                exit;
            }
            
            // Add the internal link
            $result = add_internal_link($source_type, $source_id, $target_type, $target_id, $anchor_text);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'ajout du lien interne (peut-être existe-t-il déjà)']);
            }
            break;
            
        case 'delete_internal':
            // Delete an internal link
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID invalide']);
                exit;
            }
            
            $result = delete_internal_link($id);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression du lien interne']);
            }
            break;
            
        case 'check_url':
            // Check if a URL is accessible
            $url = trim($_POST['url'] ?? '');
            
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                echo json_encode(['success' => false, 'error' => 'URL invalide']);
                exit;
            }
            
            $is_accessible = check_url_status($url);
            
            echo json_encode([
                'success' => true,
                'accessible' => $is_accessible,
                'status' => $is_accessible ? 'active' : 'broken'
            ]);
            break;
            
        case 'get_suggestions':
            // Get suggested internal links
            $content_type = $_POST['content_type'] ?? '';
            $content_id = intval($_POST['content_id'] ?? 0);
            $content_text = $_POST['content_text'] ?? '';
            $limit = intval($_POST['limit'] ?? 5);
            
            if (empty($content_type) || $content_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
                exit;
            }
            
            $suggestions = get_suggested_internal_links($content_type, $content_id, $content_text, $limit);
            
            echo json_encode([
                'success' => true,
                'suggestions' => $suggestions
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Erreur AJAX backlinks: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur interne du serveur']);
}
?>
