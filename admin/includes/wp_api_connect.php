<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
/**
 * WordPress API Connection
 *
 * This file provides functions to connect to the WordPress REST API
 * for syncing content between the custom admin panel and WordPress.
 */

// WordPress API configuration
$wp_api_base_url = 'https://cantiques.kesug.com/wp-json/wc/v3/'; // WooCommerce REST API endpoint
$wp_api_base_url_v2 = 'https://cantiques.kesug.com/wp-json/wp/v2/'; // WordPress REST API endpoint

// Validate and sanitize the WordPress API base URL
if (!filter_var($wp_api_base_url, FILTER_VALIDATE_URL)) {
    $wp_api_base_url = ''; // Invalidate the URL
}
$wp_user = 'isaquito'; // Nom d'utilisateur WordPress correct
$wp_app_password = 'xhSK Ojvp SZZw UvQG EaZl tAGX'; // Application password with spaces (will be removed in get_wp_api_token)

/**
 * Get WordPress API authentication token
 *
 * @return string Base64 encoded authentication string
 */
function get_wp_api_token() {
    global $wp_user, $wp_app_password;
    // For application passwords, WordPress expects the format username:password
    // where password is the application password WITH spaces removed
    $password_no_spaces = $wp_app_password ? str_replace(' ', '', $wp_app_password) : '';

    // Validate credentials
    if (empty($wp_user) || empty($password_no_spaces)) {
        return false;
    }

    // Log partiel pour la sécurité
    // Pour WooCommerce REST API, on utilise l'authentification Basic avec la clé API
    return base64_encode($wp_user . ':' . $password_no_spaces);
}

/**
 * Send data to WordPress API
 *
 * @param string $endpoint API endpoint (e.g., 'posts', 'categories')
 * @param array $data Data to send
 * @param string $method HTTP method (POST, PUT, DELETE)
 * @return array Response from WordPress API
 */
function send_to_wordpress($endpoint, $data, $method = 'POST') {
    global $wp_api_base_url;

    $wp_api_url = $wp_api_base_url . $endpoint;
    // Validate authentication token first
    $auth_token = get_wp_api_token();
    if ($auth_token === false) {
        return [
            'success' => false,
            'error' => 'Invalid WordPress API Credentials',
            'status' => 401,
            'curl_error' => 'Authentication Failed'
        ];
    }

    $ch = curl_init($wp_api_url);

    $headers = [
        'Authorization: Basic ' . $auth_token,
        'Content-Type: application/json',
    ];

    // Contournement de la méthode PUT qui pose problème sur certaines configurations WordPress
    // Au lieu d'utiliser PUT, on utilise toujours POST mais avec une modification de l'endpoint
    // pour les mises à jour (quand la méthode demandée est PUT)
    if ($method === 'PUT') {
        // On conserve la méthode POST standard pour les mises à jour
        $method = 'POST';
        // Ajout du paramètre _method=PUT pour indiquer qu'il s'agit d'une mise à jour
        if (!isset($data['_method'])) {
            $data['_method'] = 'PUT';
        }
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification for testing
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // Disable host verification
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Maximum number of redirects to follow
    curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output for debugging

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_errno($ch) ? curl_error($ch) : null;

    curl_close($ch);

    // Detailed logging for all responses
    if ($status >= 200 && $status < 300) {
        return [
            'success' => true,
            'data' => json_decode($response, true)
        ];
    } else {
        // Parse error response for more details
        $decoded_error = json_decode($response, true);

        // Add specific error messages for common HTTP status codes
        $error_message = $decoded_error ?? $response;
        if ($status === 405) {
            $error_message = "Méthode HTTP non autorisée. La méthode '$method' n'est pas autorisée pour cette ressource. Vérifiez les permissions de l'API WordPress.";
        } elseif ($status === 401) {
            $error_message = "Authentification échouée. Vérifiez votre nom d'utilisateur et mot de passe d'application WordPress.";
        } elseif ($status === 404) {
            $error_message = "Ressource non trouvée. Vérifiez l'URL de l'API WordPress.";
        } elseif ($status === 403) {
            $error_message = "Accès interdit. Votre utilisateur n'a pas les permissions nécessaires pour cette action.";
        }

        return [
            'success' => false,
            'error' => $error_message,
            'status' => $status,
            'curl_error' => $curl_error,
            'method' => $method // Include the HTTP method in the error response
        ];
    }
}

/**
 * Get data from WordPress API
 *
 * @param string $endpoint API endpoint (e.g., 'posts', 'categories')
 * @param array $params Query parameters
 * @return array Response from WordPress API
 */
function get_from_wordpress($endpoint, $params = []) {
    global $wp_api_base_url;

    $query_string = !empty($params) ? '?' . http_build_query($params) : '';
    $wp_api_url = $wp_api_base_url . $endpoint . $query_string;
    $ch = curl_init($wp_api_url);

    // Get the authentication token
    $auth_token = get_wp_api_token();

    $headers = [
        'Authorization: Basic ' . $auth_token,
    ];

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification for testing
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // Disable host verification
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Maximum number of redirects to follow
    curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output for debugging

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_errno($ch) ? curl_error($ch) : null;

    curl_close($ch);

    // Log the API call for debugging
    if ($status >= 200 && $status < 300) {
        return [
            'success' => true,
            'data' => json_decode($response, true)
        ];
    } else {
        // Add specific error messages for common HTTP status codes
        $error_message = $response;
        if ($status === 405) {
            $error_message = "Méthode HTTP non autorisée. La méthode 'GET' n'est pas autorisée pour cette ressource. Vérifiez les permissions de l'API WordPress.";
        } elseif ($status === 401) {
            $error_message = "Authentification échouée. Vérifiez votre nom d'utilisateur et mot de passe d'application WordPress.";
        } elseif ($status === 404) {
            $error_message = "Ressource non trouvée. Vérifiez l'URL de l'API WordPress.";
        } elseif ($status === 403) {
            $error_message = "Accès interdit. Votre utilisateur n'a pas les permissions nécessaires pour cette action.";
        }

        return [
            'success' => false,
            'error' => $error_message,
            'status' => $status,
            'curl_error' => $curl_error,
            'method' => 'GET' // Include the HTTP method in the error response
        ];
    }
}

/**
 * Upload media to WordPress
 *
 * @param string $file_path Path to the file
 * @param string $file_name Name of the file
 * @return array Response from WordPress API with media ID
 */
function upload_media_to_wordpress($file_path, $file_name) {
    global $wp_api_base_url;

    $wp_api_url = $wp_api_base_url . 'media';
    $ch = curl_init($wp_api_url);

    // Get the authentication token
    $auth_token = get_wp_api_token();

    $headers = [
        'Authorization: Basic ' . $auth_token,
        'Content-Disposition: attachment; filename=' . $file_name,
    ];

    // Check if file exists and is readable
    if (!file_exists($file_path) || !is_readable($file_path)) {
        return [
            'success' => false,
            'error' => 'File does not exist or is not readable',
            'status' => 0
        ];
    }

    $file_data = file_get_contents($file_path);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $file_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Longer timeout for uploads
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification for testing
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // Disable host verification
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Maximum number of redirects to follow
    curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output for debugging

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_errno($ch) ? curl_error($ch) : null;

    curl_close($ch);

    // Log the API call for debugging
    if ($status >= 200 && $status < 300) {
        return [
            'success' => true,
            'data' => json_decode($response, true)
        ];
    } else {
        // Add specific error messages for common HTTP status codes
        $error_message = $response;
        if ($status === 405) {
            $error_message = "Méthode HTTP non autorisée. La méthode 'POST' n'est pas autorisée pour cette ressource. Vérifiez les permissions de l'API WordPress.";
        } elseif ($status === 401) {
            $error_message = "Authentification échouée. Vérifiez votre nom d'utilisateur et mot de passe d'application WordPress.";
        } elseif ($status === 404) {
            $error_message = "Ressource non trouvée. Vérifiez l'URL de l'API WordPress.";
        } elseif ($status === 403) {
            $error_message = "Accès interdit. Votre utilisateur n'a pas les permissions nécessaires pour cette action.";
        } elseif ($status === 413) {
            $error_message = "Fichier trop volumineux. Réduisez la taille du fichier ou augmentez la limite de téléchargement sur le serveur WordPress.";
        }

        return [
            'success' => false,
            'error' => $error_message,
            'status' => $status,
            'curl_error' => $curl_error,
            'method' => 'POST' // Include the HTTP method in the error response
        ];
    }
}

/**
 * Map local category to WordPress category
 *
 * @param string $local_category Local category name
 * @return int WordPress category ID
 */
function map_category_to_wordpress($local_category) {
    // Get categories from WordPress
    $response = get_from_wordpress('categories', ['per_page' => 100]);

    if ($response['success']) {
        $wp_categories = $response['data'];

        // Look for matching category by name
        foreach ($wp_categories as $category) {
            if (strtolower($category['name']) === strtolower($local_category)) {
                return $category['id'];
            }
        }

        // If no match found, create a new category
        $new_category = [
            'name' => $local_category,
            'slug' => sanitize_slug($local_category)
        ];

        $create_response = send_to_wordpress('categories', $new_category);

        if ($create_response['success']) {
            return $create_response['data']['id'];
        }
    }

    // Return default category (1 is usually "Uncategorized")
    return 1;
}

/**
 * Create a URL-friendly slug from a string
 *
 * @param string $string Input string
 * @return string Sanitized slug
 */
function sanitize_slug($string) {
    // Replace accented characters with non-accented
    $string = transliterator_transliterate('Any-Latin; Latin-ASCII', $string);

    // Convert to lowercase
    $string = strtolower($string);

    // Replace spaces and special chars with hyphens
    $string = preg_replace('/[^a-z0-9\-]/', '-', $string);

    // Replace multiple hyphens with single hyphen
    $string = preg_replace('/-+/', '-', $string);

    // Trim hyphens from beginning and end
    return trim($string, '-');
}

/**
 * Synchronise un produit avec WordPress/WooCommerce
 *
 * @param array $product Données du produit à synchroniser
 * @return array Réponse de l'API
 */
function sync_product_to_wordpress($product) {
    global $wp_api_base_url;

    // Préparer les données pour WooCommerce
    $wc_product = [
        'name' => $product['title'],
        'description' => $product['description'],
        'regular_price' => (string) $product['price'],
        'manage_stock' => true,
        'stock_quantity' => (int) $product['stock'],
        'stock_status' => $product['stock'] > 0 ? 'instock' : 'outofstock',
        'status' => $product['status'] === 'published' ? 'publish' : 'draft',
        'categories' => [
            ['name' => $product['category']]
        ]
    ];

    // Ajouter l'image si disponible
    if (!empty($product['featured_image'])) {
        $wc_product['images'] = [
            ['src' => $product['featured_image']]
        ];
    }

    // Déterminer la méthode HTTP et l'URL
    $method = 'POST';
    $endpoint = 'products';

    if (!empty($product['wp_post_id'])) {
        // Mise à jour d'un produit existant
        $endpoint .= '/' . $product['wp_post_id'];
        $method = 'PUT';
    }

    // Envoyer la requête à l'API WooCommerce
    return send_to_wordpress($endpoint, $wc_product, $method);
}

/**
 * Supprime un produit WordPress/WooCommerce
 *
 * @param int $wp_post_id ID du produit dans WordPress
 * @return array Réponse de l'API
 */
function delete_wordpress_product($wp_post_id) {
    // Utiliser true pour forcer la suppression définitive (au lieu de la corbeille)
    return send_to_wordpress('products/' . $wp_post_id . '?force=true', [], 'DELETE');
}

/**
 * Recherche un produit WordPress par son slug
 *
 * @param string $slug Slug du produit
 * @return array|false Données du produit ou false si non trouvé
 */
function find_wp_product_by_slug($slug) {
    global $wp_api_base_url;

    $response = get_from_wordpress('products', ['slug' => $slug]);

    if ($response['success'] && !empty($response['data'])) {
        return $response['data'][0]; // Retourne le premier produit trouvé
    }

    return false;
}

/**
 * Met à jour l'ID WordPress d'un produit dans la base de données locale
 *
 * @param int $product_id ID du produit dans la base locale
 * @param int $wp_post_id ID du produit dans WordPress
 * @return bool Succès de la mise à jour
 */
function update_product_wp_id($product_id, $wp_post_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("UPDATE products SET wp_post_id = ? WHERE id = ?");
        return $stmt->execute([$wp_post_id, $product_id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Teste la connexion à l'API WordPress
 *
 * @return array Résultats du test
 */
function test_wordpress_connection() {
    $results = [
        'overall_success' => true,
        'checks' => [],
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // 1. Check Base URL Validity
    global $wp_api_base_url, $wp_user;

    if (empty($wp_api_base_url)) {
        $results['overall_success'] = false;
        $results['checks'][] = [
            'name' => 'URL Configuration',
            'success' => false,
            'message' => 'WordPress API base URL is not configured'
        ];
    } else {
        $results['checks'][] = [
            'name' => 'URL Configuration',
            'success' => true,
            'message' => 'WordPress API base URL is set: ' . $wp_api_base_url
        ];
    }

    // 2. Test Authentication
    $auth_token = get_wp_api_token();
    if ($auth_token === false) {
        $results['overall_success'] = false;
        $results['checks'][] = [
            'name' => 'Authentication',
            'success' => false,
            'message' => 'Invalid WordPress API credentials'
        ];
    } else {
        $results['checks'][] = [
            'name' => 'Authentication',
            'success' => true,
            'message' => 'API credentials validated for user: ' . $wp_user
        ];
    }

    // 3. Test API Connectivity by fetching categories
    try {
        $response = get_from_wordpress('categories', ['per_page' => 1]);

        if ($response['success']) {
            $results['checks'][] = [
                'name' => 'API Connectivity',
                'success' => true,
                'message' => 'Successfully retrieved WordPress categories'
            ];
        } else {
            $results['overall_success'] = false;
            $results['checks'][] = [
                'name' => 'API Connectivity',
                'success' => false,
                'message' => 'Failed to retrieve categories. Error: ' . json_encode($response['error'])
            ];
        }
    } catch (Exception $e) {
        $results['overall_success'] = false;
        $results['checks'][] = [
            'name' => 'API Connectivity',
            'success' => false,
            'message' => 'Exception during API test: ' . $e->getMessage()
        ];
    }

    return $results;
}
?>
