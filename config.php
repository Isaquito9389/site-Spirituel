<?php
/**
 * Configuration File
 * 
 * This file defines global constants and configuration settings for the application.
 * It should be included at the entry point of all scripts.
 */

// Prevent direct access to this file
if (!defined('SECURE_ACCESS')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access to this file is not allowed.');
}

// Define base paths (using absolute paths for security)
define('ROOT_PATH', realpath(dirname(__FILE__)));
define('ADMIN_PATH', ROOT_PATH . '/admin');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('ADMIN_INCLUDES_PATH', ADMIN_PATH . '/includes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('LOGS_PATH', ROOT_PATH . '/logs');

// Database configuration - Use WordPress constants if available
if (file_exists(__DIR__ . '/wp/wp-config.php')) {
    // If WordPress is installed, include wp-config.php to get database constants
    // But don't execute it, just get the constants
    $wp_config_file = file_get_contents(__DIR__ . '/wp/wp-config.php');
    
    // Extract database constants from wp-config.php
    preg_match("/define\(\s*'DB_NAME',\s*'([^']*)'\s*\)/", $wp_config_file, $db_name_matches);
    preg_match("/define\(\s*'DB_USER',\s*'([^']*)'\s*\)/", $wp_config_file, $db_user_matches);
    preg_match("/define\(\s*'DB_PASSWORD',\s*'([^']*)'\s*\)/", $wp_config_file, $db_pass_matches);
    preg_match("/define\(\s*'DB_HOST',\s*'([^']*)'\s*\)/", $wp_config_file, $db_host_matches);
    preg_match("/define\(\s*'DB_CHARSET',\s*'([^']*)'\s*\)/", $wp_config_file, $db_charset_matches);
    
    // Define our constants using WordPress values
    if (!empty($db_name_matches[1])) define('DB_NAME', $db_name_matches[1]);
    else define('DB_NAME', 'if0_36264299_mysticaoculta');
    
    if (!empty($db_user_matches[1])) define('DB_USER', $db_user_matches[1]);
    else define('DB_USER', 'if0_36264299');
    
    if (!empty($db_pass_matches[1])) define('DB_PASS', $db_pass_matches[1]);
    else define('DB_PASS', 'wkqR0EhzO8CILv');
    
    if (!empty($db_host_matches[1])) define('DB_HOST', $db_host_matches[1]);
    else define('DB_HOST', 'sql310.infinityfree.com');
    
    if (!empty($db_charset_matches[1])) define('DB_CHARSET', $db_charset_matches[1]);
    else define('DB_CHARSET', 'utf8mb4');
} else {
    // If WordPress is not installed, use our default constants
    // IMPORTANT: Remplacez ces valeurs par vos informations Hostinger
    if (!defined('DB_HOST')) define('DB_HOST', 'localhost'); // Généralement 'localhost' pour Hostinger
    if (!defined('DB_NAME')) define('DB_NAME', 'VOTRE_NOM_DE_BASE_HOSTINGER'); // Remplacez par votre nom de base
    if (!defined('DB_USER')) define('DB_USER', 'VOTRE_UTILISATEUR_HOSTINGER'); // Remplacez par votre utilisateur
    if (!defined('DB_PASS')) define('DB_PASS', 'VOTRE_MOT_DE_PASSE_HOSTINGER'); // Remplacez par votre mot de passe
    if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
}

// Application settings
define('DEBUG_MODE', true); // Set to false in production
define('SITE_NAME', 'Mystica Occulta');
define('SITE_URL', 'https://mysticaocculta.infinityfreeapp.com'); // Update with your actual domain

// Security settings
define('SECURE_SALT', 'mystica_occulta_salt_2025'); // Used for hashing and security functions
