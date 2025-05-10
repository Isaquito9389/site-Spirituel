<?php
/**
 * Database Connection
 * 
 * This file establishes a connection to the MySQL database using PDO.
 * Includes connection verification and improved error handling.
 */

// Database configuration
$db_host = 'sql310.infinityfree.com';
$db_name = 'if0_36264299_mysticaoculta';
$db_user = 'if0_36264299';
$db_pass = 'wkqR0EhzO8CILv';
$db_charset = 'utf8mb4';

// DSN (Data Source Name)
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";

// PDO options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Define a global connection status variable
$db_connection_status = false;

// Try to connect to the database
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
    // Verify connection by executing a simple query
    $stmt = $pdo->query('SELECT 1');
    if ($stmt) {
        $db_connection_status = true;
    }
} catch (PDOException $e) {
    // Log error to a file instead of displaying it directly
    $error_message = date('[Y-m-d H:i:s] ') . "Erreur de connexion à la base de données: " . $e->getMessage() . PHP_EOL;
    error_log($error_message, 3, __DIR__ . '/../logs/db_errors.log');
    
    // If in production, show a user-friendly message
    // If in development, show detailed error
    $is_production = false; // Set to true in production
    if ($is_production) {
        die("Une erreur est survenue lors de la connexion à la base de données. Veuillez réessayer plus tard.");
    } else {
        die("Erreur de connexion à la base de données: " . $e->getMessage());
    }
}

// Function to check database connection status
function is_db_connected() {
    global $db_connection_status;
    return $db_connection_status;
}
