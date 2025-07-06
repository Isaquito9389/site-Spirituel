<?php
/**
 * Bootstrap File
 * 
 * This file initializes the application environment and should be included
 * at the beginning of all PHP files.
 */

// Define secure access constant to prevent direct file access
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

// Include configuration file
require_once __DIR__ . '/config.php';

// Set error reporting based on debug mode
if (DEBUG_MODE) {
    } else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// Custom error handler
set_error_handler(function(
    $errno, $errstr, $errfile, $errline
) {
    if (error_reporting() === 0) {
        return false;
    }
    
    $error_message = "Error [$errno] $errstr - $errfile:$errline";
    if (DEBUG_MODE) {
        echo "<div style=\"padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin-bottom: 20px;\">\n";
        echo "<h3>Une erreur est survenue</h3>\n";
        echo "<p>" . htmlspecialchars($error_message) . "</p>\n";
        echo "</div>";
    } else {
        echo "<div style=\"padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin-bottom: 20px;\">\n";
        echo "<h3>Une erreur est survenue</h3>\n";
        echo "<p>Nous avons rencontré un problème lors du traitement de votre demande. Veuillez réessayer plus tard ou contacter l'administrateur.</p>\n";
        echo "</div>";
    }
    
    return true;
}, E_ALL);

// Function to check if the request is from a valid source
if (!function_exists('is_valid_request')) {
    function is_valid_request() {
        // Add your validation logic here
        // For example, check referrer, CSRF token, etc.
        return true;
    }
}
