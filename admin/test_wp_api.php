<?php
// Affichage forcé des erreurs PHP pour le debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include WordPress API functions
require_once 'includes/db_connect.php';
require_once 'includes/wp_api_connect.php';

// Test the WordPress API connection
echo "<h1>Test de connexion à l'API WordPress</h1>";
echo "<p>URL de base de l'API: " . htmlspecialchars($wp_api_base_url) . "</p>";

// Test 1: Get WordPress info
echo "<h2>Test 1: Récupération des informations WordPress</h2>";
$wp_api_base_url_parts = explode('/wp/v2/', $wp_api_base_url);
$wp_root_api = $wp_api_base_url_parts[0];

echo "<p>URL racine de l'API: " . htmlspecialchars($wp_root_api) . "</p>";

$ch = curl_init($wp_root_api);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_errno($ch) ? curl_error($ch) : null;

curl_close($ch);

echo "<p>Statut HTTP: " . $status . "</p>";
if ($curl_error) {
    echo "<p>Erreur cURL: " . htmlspecialchars($curl_error) . "</p>";
}
echo "<p>Réponse: <pre>" . htmlspecialchars(substr($response, 0, 500)) . "...</pre></p>";

// Test 2: Get posts with authentication
echo "<h2>Test 2: Récupération des articles avec authentification</h2>";
$test2_result = get_from_wordpress('posts', ['per_page' => 1]);

echo "<p>URL: " . htmlspecialchars($wp_api_base_url . 'posts?per_page=1') . "</p>";
echo "<p>Succès: " . ($test2_result['success'] ? 'Oui' : 'Non') . "</p>";
echo "<p>Statut HTTP: " . ($test2_result['status'] ?? 'N/A') . "</p>";

if (isset($test2_result['curl_error']) && $test2_result['curl_error']) {
    echo "<p>Erreur cURL: " . htmlspecialchars($test2_result['curl_error']) . "</p>";
}

if (isset($test2_result['error'])) {
    echo "<p>Erreur: <pre>" . htmlspecialchars(substr(print_r($test2_result['error'], true), 0, 500)) . "...</pre></p>";
} elseif (isset($test2_result['data'])) {
    echo "<p>Données: <pre>" . htmlspecialchars(substr(json_encode($test2_result['data'], JSON_PRETTY_PRINT), 0, 500)) . "...</pre></p>";
}

// Test 3: Create a test post
echo "<h2>Test 3: Création d'un article de test</h2>";
$test_data = [
    'title' => 'Article de test API - ' . date('Y-m-d H:i:s'),
    'content' => 'Ceci est un article de test créé via l\'API WordPress.',
    'excerpt' => 'Extrait de test',
    'status' => 'draft',
    'categories' => [1] // Default category
];

$test3_result = send_to_wordpress('posts', $test_data);

echo "<p>URL: " . htmlspecialchars($wp_api_base_url . 'posts') . "</p>";
echo "<p>Méthode: POST</p>";
echo "<p>Succès: " . ($test3_result['success'] ? 'Oui' : 'Non') . "</p>";
echo "<p>Statut HTTP: " . ($test3_result['status'] ?? 'N/A') . "</p>";

if (isset($test3_result['curl_error']) && $test3_result['curl_error']) {
    echo "<p>Erreur cURL: " . htmlspecialchars($test3_result['curl_error']) . "</p>";
}

if (isset($test3_result['error'])) {
    echo "<p>Erreur: <pre>" . htmlspecialchars(substr(print_r($test3_result['error'], true), 0, 500)) . "...</pre></p>";
} elseif (isset($test3_result['data'])) {
    echo "<p>Données: <pre>" . htmlspecialchars(substr(json_encode($test3_result['data'], JSON_PRETTY_PRINT), 0, 500)) . "...</pre></p>";
    
    // If post was created successfully, try to update it
    if (isset($test3_result['data']['id'])) {
        $post_id = $test3_result['data']['id'];
        
        echo "<h2>Test 4: Mise à jour de l'article de test</h2>";
        $update_data = [
            'title' => 'Article de test API (Mis à jour) - ' . date('Y-m-d H:i:s'),
            'content' => 'Ceci est un article de test mis à jour via l\'API WordPress.',
        ];
        
        $test4_result = send_to_wordpress('posts/' . $post_id, $update_data, 'PUT');
        
        echo "<p>URL: " . htmlspecialchars($wp_api_base_url . 'posts/' . $post_id) . "</p>";
        echo "<p>Méthode: PUT</p>";
        echo "<p>Succès: " . ($test4_result['success'] ? 'Oui' : 'Non') . "</p>";
        echo "<p>Statut HTTP: " . ($test4_result['status'] ?? 'N/A') . "</p>";
        
        if (isset($test4_result['curl_error']) && $test4_result['curl_error']) {
            echo "<p>Erreur cURL: " . htmlspecialchars($test4_result['curl_error']) . "</p>";
        }
        
        if (isset($test4_result['error'])) {
            echo "<p>Erreur: <pre>" . htmlspecialchars(substr(print_r($test4_result['error'], true), 0, 500)) . "...</pre></p>";
        } elseif (isset($test4_result['data'])) {
            echo "<p>Données: <pre>" . htmlspecialchars(substr(json_encode($test4_result['data'], JSON_PRETTY_PRINT), 0, 500)) . "...</pre></p>";
        }
    }
}

// Overall result
echo "<h2>Résultat global</h2>";
$overall_success = isset($test2_result['success']) && $test2_result['success'] && 
                  isset($test3_result['success']) && $test3_result['success'];

if ($overall_success) {
    echo "<p style='color: green; font-weight: bold;'>✅ La connexion à l'API WordPress fonctionne correctement!</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ Des problèmes ont été détectés avec la connexion à l'API WordPress.</p>";
}

// Link back to admin
echo "<p><a href='blog.php'>Retour à la gestion du blog</a></p>";
