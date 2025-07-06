<?php
/**
 * Database Connection
 * 
 * This file establishes a connection to the MySQL database using PDO.
 * It uses constants defined in the bootstrap file for secure configuration.
 */

// Prevent direct access to this file
if (!defined('SECURE_ACCESS')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access to this file is not allowed.');
}

// Check if the database connection has already been included
if (!defined('DB_CONNECT_INCLUDED')) {
    // Define a constant to prevent duplicate inclusion
    define('DB_CONNECT_INCLUDED', true);
    
    // DSN (Data Source Name)
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

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
        // Check if the MySQL PDO driver is available
        if (!in_array('mysql', PDO::getAvailableDrivers())) {
            throw new PDOException("Le driver MySQL PDO n'est pas disponible. Veuillez l'installer ou l'activer dans votre configuration PHP.");
        }
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Verify connection by executing a simple query
        $stmt = $pdo->query('SELECT 1');
        if ($stmt) {
            $db_connection_status = true;
        }
    } catch (PDOException $e) {
        // Log error to a file instead of displaying it directly
        $error_message = date('[Y-m-d H:i:s] ') . "Erreur de connexion à la base de données: " . $e->getMessage() . PHP_EOL;
        
        // Make sure the logs directory exists and is writable
        if (defined('LOGS_PATH') && is_dir(LOGS_PATH) && is_writable(LOGS_PATH)) {
            } else {
            // Fallback to PHP's error log
            }
        
        // Show appropriate error message based on debug mode
        if (!DEBUG_MODE) {
            echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; margin: 15px 0; border-radius: 5px; border: 1px solid #f5c6cb;'>";
            echo "<h3>Erreur de connexion à la base de données</h3>";
            echo "<p>Une erreur est survenue lors de la connexion à la base de données. Veuillez réessayer plus tard.</p>";
            echo "</div>";
        } else {
            echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; margin: 15px 0; border-radius: 5px; border: 1px solid #f5c6cb;'>";
            echo "<h3>Erreur de connexion à la base de données</h3>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p>DSN: " . htmlspecialchars($dsn) . "</p>";
            echo "<p>Available PDO Drivers: " . implode(', ', PDO::getAvailableDrivers()) . "</p>";
            echo "</div>";
        }
        
        // Set $pdo to null to indicate that the connection failed
        $pdo = null;
        $db_connection_status = false;
    }

    // Function to check database connection status
    function is_db_connected() {
        global $db_connection_status;
        return $db_connection_status;
    }
}
