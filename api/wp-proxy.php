<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
/**
 * WordPress API Proxy
 *
 * Ce fichier sert de proxy entre le frontend et l'API WordPress.
 * Il permet de récupérer le contenu WordPress sans exposer les détails d'authentification.
 */

// Autoriser les requêtes CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Inclure la configuration WordPress API
require_once '../admin/includes/wp_api_connect.php';

// Récupérer l'endpoint demandé ou utiliser 'posts' par défaut
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : 'posts';

// Récupérer tous les paramètres de requête
$params = $_GET;
unset($params['endpoint']); // Supprimer l'endpoint des paramètres

// Récupérer les données depuis WordPress
$response = get_from_wordpress($endpoint, $params);

// Renvoyer la réponse au format JSON
echo json_encode($response['data'] ?? ['error' => 'Erreur lors de la récupération des données']);
