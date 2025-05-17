<?php
// Disable error reporting for production
error_reporting(0);

// Enable error reporting for debugging
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// WordPress API Proxy Script
// This script helps diagnose and proxy WordPress API requests

// Security: Check admin login
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized access']));
}

// WordPress API configuration (duplicate from wp_api_connect.php for independence)
$wp_api_base_url = 'https://cantiques.kesug.com/wp-json/wp/v2/';
$wp_user = 'admin';
$wp_app_password = 'lQr4u5l49iWlJmQgqgKKzLqC';

// Validate input
if (!isset($_GET['endpoint'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing endpoint parameter']));
}

$endpoint = $_GET['endpoint'];
$method = $_SERVER['REQUEST_METHOD'];

// Construct full API URL
$full_url = $wp_api_base_url . $endpoint;

// Prepare cURL request
$ch = curl_init($full_url);

// Prepare headers
$headers = [
    'Authorization: Basic ' . base64_encode($wp_user . ':' . str_replace(' ', '', $wp_app_password)),
    'Content-Type: application/json',
];

// Set cURL options
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable for testing, enable in production

// Handle different HTTP methods
switch ($method) {
    case 'POST':
        $data = file_get_contents('php://input');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        break;
    case 'PUT':
        $data = file_get_contents('php://input');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        break;
    case 'DELETE':
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        break;
}

// Execute request
$response = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

curl_close($ch);

// Log detailed error information
if ($curl_error) {
    error_log("WordPress Proxy Error: $curl_error");
}

// Set response headers
header('Content-Type: application/json');
http_response_code($http_status);

// Output response
echo $response;
exit;
