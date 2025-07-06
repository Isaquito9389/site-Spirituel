<?php
// Start session
session_start();

// Check if user is logged in as admin
function is_admin_logged_in() {
    return isset($_SESSION['admin_logged_in']) && 
           $_SESSION['admin_logged_in'] === true && 
           isset($_SESSION['admin_user_role']) && 
           $_SESSION['admin_user_role'] === 'admin';
}

// Get the requested file path from the query string
$requested_file = isset($_GET['file']) ? $_GET['file'] : '';

// Security check - prevent directory traversal
$requested_file = str_replace('..', '', $requested_file);

// Define allowed directories for admin access
$allowed_directories = [
    'includes/',
    'admin/includes/',
    'logs/',
    'admin/logs/'
];

// Check if the requested file is in an allowed directory
$is_allowed_directory = false;
foreach ($allowed_directories as $dir) {
    if (strpos($requested_file, $dir) === 0) {
        $is_allowed_directory = true;
        break;
    }
}

// Define allowed file patterns
$allowed_file_patterns = [
    'db_connect.php',
    'auth_functions.php',
    'setup_database.php',
    'wp_api_connect.php',
    'security_functions.php',
    'wp_db_update.php',
    'wp_rituals_update.php',
    'config.php',
    'bootstrap.php'
];

// Check if the requested file matches an allowed pattern
$is_allowed_file = false;
foreach ($allowed_file_patterns as $pattern) {
    if (strpos(basename($requested_file), $pattern) !== false) {
        $is_allowed_file = true;
        break;
    }
}

// Only allow access if user is logged in as admin and the file is in an allowed directory or matches an allowed pattern
if (is_admin_logged_in() && ($is_allowed_directory || $is_allowed_file)) {
    // File path relative to the document root
    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $requested_file;
    
    // Check if file exists
    if (file_exists($file_path)) {
        // Get file extension
        $extension = pathinfo($file_path, PATHINFO_EXTENSION);
        
        // Set appropriate content type
        if ($extension === 'php') {
            // For PHP files, include them instead of displaying source
            include($file_path);
        } else {
            // For other files, display their content
            $content = file_get_contents($file_path);
            
            // Set content type based on file extension
            switch ($extension) {
                case 'css':
                    header('Content-Type: text/css');
                    break;
                case 'js':
                    header('Content-Type: application/javascript');
                    break;
                case 'json':
                    header('Content-Type: application/json');
                    break;
                case 'txt':
                    header('Content-Type: text/plain');
                    break;
                case 'html':
                case 'htm':
                    header('Content-Type: text/html');
                    break;
                default:
                    header('Content-Type: text/plain');
            }
            
            echo $content;
        }
        exit;
    } else {
        // File not found
        header('HTTP/1.0 404 Not Found');
        echo "File not found: " . htmlspecialchars($requested_file);
    }
} else {
    // Access denied
    header('HTTP/1.0 403 Forbidden');
    echo "Access denied. You must be logged in as an administrator to access this file.";
}
?>
