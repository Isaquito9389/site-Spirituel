<?php
/**
 * WordPress API Connection
 * 
 * This file provides functions to connect to the WordPress REST API
 * for syncing content between the custom admin panel and WordPress.
 */

// WordPress API configuration
$wp_api_base_url = 'http://cantiques.kesug.com/wp/wp-json/wp/v2/';
$wp_user = 'admin'; // Vous devrez créer un mot de passe d'application dans WordPress
$wp_app_password = 'lQr4 u5l4 9iWl JmQg qgKK zLqC'; // À remplacer après avoir généré un mot de passe d'application

/**
 * Get WordPress API authentication token
 * 
 * @return string Base64 encoded authentication string
 */
function get_wp_api_token() {
    global $wp_user, $wp_app_password;
    return base64_encode($wp_user . ':' . $wp_app_password);
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
    
    $ch = curl_init($wp_api_url);
    
    $headers = [
        'Authorization: Basic ' . get_wp_api_token(),
        'Content-Type: application/json',
    ];
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    if ($status >= 200 && $status < 300) {
        return [
            'success' => true,
            'data' => json_decode($response, true)
        ];
    } else {
        return [
            'success' => false,
            'error' => $response,
            'status' => $status
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
    
    $headers = [
        'Authorization: Basic ' . get_wp_api_token(),
    ];
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    if ($status >= 200 && $status < 300) {
        return [
            'success' => true,
            'data' => json_decode($response, true)
        ];
    } else {
        return [
            'success' => false,
            'error' => $response,
            'status' => $status
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
    
    $headers = [
        'Authorization: Basic ' . get_wp_api_token(),
        'Content-Disposition: attachment; filename=' . $file_name,
    ];
    
    $file_data = file_get_contents($file_path);
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $file_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Longer timeout for uploads
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    if ($status >= 200 && $status < 300) {
        return [
            'success' => true,
            'data' => json_decode($response, true)
        ];
    } else {
        return [
            'success' => false,
            'error' => $response,
            'status' => $status
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
