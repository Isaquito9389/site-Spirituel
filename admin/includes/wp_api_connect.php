<?php
/**
 * WordPress API Connection
 * 
 * This file provides functions to connect to the WordPress REST API
 * for syncing content between the custom admin panel and WordPress.
 */

// WordPress API configuration
$wp_api_base_url = 'https://cantiques.kesug.com/wp/wp-json/wp/v2/'; // Verified WordPress API endpoint with correct subdirectory path

// Validate and sanitize the WordPress API base URL
if (!filter_var($wp_api_base_url, FILTER_VALIDATE_URL)) {
    error_log("Invalid WordPress API Base URL: $wp_api_base_url");
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
        error_log("WordPress API Error: Missing authentication credentials");
        return false;
    }
    
    // Log partial credentials for security
    error_log("WordPress API Authentication: User = " . $wp_user . ", Password Length = " . strlen($password_no_spaces));
    
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
    error_log("WordPress API URL: $wp_api_url");
    
    // Validate authentication token first
    $auth_token = get_wp_api_token();
    if ($auth_token === false) {
        error_log("WordPress API Authentication Failed: Invalid Credentials");
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
    
    error_log("WordPress API Headers: " . json_encode($headers));
    
    // Contournement de la méthode PUT qui pose problème sur certaines configurations WordPress
    // Au lieu d'utiliser PUT, on utilise toujours POST mais avec une modification de l'endpoint
    // pour les mises à jour (quand la méthode demandée est PUT)
    if ($method === 'PUT') {
        error_log("Converting PUT request to POST for WordPress API compatibility");
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
    error_log("WordPress API Call Details:");
    error_log("Endpoint: $endpoint");
    error_log("Method: $method");
    error_log("HTTP Status: $status");
    error_log("Response: " . ($response ?? 'No response'));
    error_log("Curl Error: " . ($curl_error ?? 'None'));
    
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
    error_log("WordPress API GET URL: $wp_api_url");
    
    $ch = curl_init($wp_api_url);
    
    // Get the authentication token
    $auth_token = get_wp_api_token();
    
    $headers = [
        'Authorization: Basic ' . $auth_token,
    ];
    
    error_log("WordPress API GET Headers: " . json_encode($headers));
    
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
    error_log("WordPress API GET call to $endpoint: Status $status, Error: " . ($curl_error ?? 'None'));
    
    if ($status >= 200 && $status < 300) {
        return [
            'success' => true,
            'data' => json_decode($response, true)
        ];
    } else {
        error_log("WordPress API GET error: $response");
        
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
    error_log("WordPress Media Upload URL: $wp_api_url");
    
    $ch = curl_init($wp_api_url);
    
    // Get the authentication token
    $auth_token = get_wp_api_token();
    
    $headers = [
        'Authorization: Basic ' . $auth_token,
        'Content-Disposition: attachment; filename=' . $file_name,
    ];
    
    error_log("WordPress Media Upload Headers: " . json_encode($headers));
    
    // Check if file exists and is readable
    if (!file_exists($file_path) || !is_readable($file_path)) {
        error_log("WordPress Media Upload Error: File does not exist or is not readable: $file_path");
        return [
            'success' => false,
            'error' => 'File does not exist or is not readable',
            'status' => 0
        ];
    }
    
    $file_data = file_get_contents($file_path);
    error_log("WordPress Media Upload: File size: " . strlen($file_data) . " bytes");
    
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
    error_log("WordPress Media Upload: Status $status, Error: " . ($curl_error ?? 'None'));
    
    if ($status >= 200 && $status < 300) {
        return [
            'success' => true,
            'data' => json_decode($response, true)
        ];
    } else {
        error_log("WordPress Media Upload error: $response");
        
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
 * Test WordPress API connection
 * 
 * @return array Test results with connection status and details
 */
function test_wordpress_connection() {
    error_log("Testing WordPress API connection...");
    
    // Test 1: Get WordPress info
    $wp_api_base_url_parts = explode('/wp/v2/', $GLOBALS['wp_api_base_url']);
    $wp_root_api = $wp_api_base_url_parts[0];
    
    $ch = curl_init($wp_root_api);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // Disable host verification
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Maximum number of redirects to follow
    curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output for debugging
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_errno($ch) ? curl_error($ch) : null;
    
    curl_close($ch);
    
    $test1 = [
        'name' => 'WordPress Root API',
        'url' => $wp_root_api,
        'success' => ($status >= 200 && $status < 300),
        'status' => $status,
        'error' => $curl_error,
        'response' => substr($response, 0, 500) // Limit response size
    ];
    
    // Test 2: Get posts with authentication
    $test2_result = get_from_wordpress('posts', ['per_page' => 1]);
    $test2 = [
        'name' => 'WordPress Posts API with Auth',
        'url' => $GLOBALS['wp_api_base_url'] . 'posts?per_page=1',
        'success' => $test2_result['success'],
        'status' => $test2_result['status'] ?? 0,
        'error' => $test2_result['curl_error'] ?? null,
        'response' => isset($test2_result['error']) ? substr($test2_result['error'], 0, 500) : null
    ];
    
    // Test 3: Check user permissions
    $test3_result = get_from_wordpress('users/me');
    $test3 = [
        'name' => 'WordPress User Permissions',
        'url' => $GLOBALS['wp_api_base_url'] . 'users/me',
        'success' => $test3_result['success'],
        'status' => $test3_result['status'] ?? 0,
        'error' => $test3_result['curl_error'] ?? null,
        'response' => isset($test3_result['data']) ? json_encode($test3_result['data']) : null
    ];
    
    return [
        'tests' => [$test1, $test2, $test3],
        'overall_success' => ($test1['success'] && $test2['success'] && $test3['success']),
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

 
 / * * 
   *   T e s t   W o r d P r e s s   A P I   c o n n e c t i o n 
   *   
   *   @ r e t u r n   a r r a y   T e s t   r e s u l t s   w i t h   o v e r a l l   s u c c e s s   a n d   d e t a i l e d   c h e c k s 
   * / 
 f u n c t i o n   t e s t _ w o r d p r e s s _ c o n n e c t i o n ( )   { 
         g l o b a l   \ ,   \ ; 
         
         / /   I n i t i a l i z e   t e s t   r e s u l t s 
         \   =   [ 
                 ' o v e r a l l _ s u c c e s s '   = >   t r u e , 
                 ' c h e c k s '   = >   [ ] 
         ] ; 
         
         / /   1 .   C h e c k   B a s e   U R L   V a l i d i t y 
         i f   ( e m p t y ( \ ) )   { 
                 \ [ ' o v e r a l l _ s u c c e s s ' ]   =   f a l s e ; 
                 \ [ ' c h e c k s ' ] [ ]   =   [ 
                         ' n a m e '   = >   ' U R L   C o n f i g u r a t i o n ' , 
                         ' s u c c e s s '   = >   f a l s e , 
                         ' m e s s a g e '   = >   ' W o r d P r e s s   A P I   b a s e   U R L   i s   n o t   c o n f i g u r e d ' 
                 ] ; 
         }   e l s e   { 
                 \ [ ' c h e c k s ' ] [ ]   =   [ 
                         ' n a m e '   = >   ' U R L   C o n f i g u r a t i o n ' , 
                         ' s u c c e s s '   = >   t r u e , 
                         ' m e s s a g e '   = >   ' W o r d P r e s s   A P I   b a s e   U R L   i s   s e t :   '   .   \ 
                 ] ; 
         } 
         
         / /   2 .   T e s t   A u t h e n t i c a t i o n 
         \   =   g e t _ w p _ a p i _ t o k e n ( ) ; 
         i f   ( \   = = =   f a l s e )   { 
                 \ [ ' o v e r a l l _ s u c c e s s ' ]   =   f a l s e ; 
                 \ [ ' c h e c k s ' ] [ ]   =   [ 
                         ' n a m e '   = >   ' A u t h e n t i c a t i o n ' , 
                         ' s u c c e s s '   = >   f a l s e , 
                         ' m e s s a g e '   = >   ' I n v a l i d   W o r d P r e s s   A P I   c r e d e n t i a l s ' 
                 ] ; 
         }   e l s e   { 
                 \ [ ' c h e c k s ' ] [ ]   =   [ 
                         ' n a m e '   = >   ' A u t h e n t i c a t i o n ' , 
                         ' s u c c e s s '   = >   t r u e , 
                         ' m e s s a g e '   = >   ' A P I   c r e d e n t i a l s   v a l i d a t e d   f o r   u s e r :   '   .   \ 
                 ] ; 
         } 
         
         / /   3 .   T e s t   A P I   C o n n e c t i v i t y   b y   f e t c h i n g   c a t e g o r i e s 
         t r y   { 
                 \   =   g e t _ f r o m _ w o r d p r e s s ( ' c a t e g o r i e s ' ,   [ ' p e r _ p a g e '   = >   1 ] ) ; 
                 
                 i f   ( \ [ ' s u c c e s s ' ] )   { 
                         \ [ ' c h e c k s ' ] [ ]   =   [ 
                                 ' n a m e '   = >   ' A P I   C o n n e c t i v i t y ' , 
                                 ' s u c c e s s '   = >   t r u e , 
                                 ' m e s s a g e '   = >   ' S u c c e s s f u l l y   r e t r i e v e d   W o r d P r e s s   c a t e g o r i e s ' 
                         ] ; 
                 }   e l s e   { 
                         \ [ ' o v e r a l l _ s u c c e s s ' ]   =   f a l s e ; 
                         \ [ ' c h e c k s ' ] [ ]   =   [ 
                                 ' n a m e '   = >   ' A P I   C o n n e c t i v i t y ' , 
                                 ' s u c c e s s '   = >   f a l s e , 
                                 ' m e s s a g e '   = >   ' F a i l e d   t o   r e t r i e v e   c a t e g o r i e s .   E r r o r :   '   .   j s o n _ e n c o d e ( \ [ ' e r r o r ' ] ) 
                         ] ; 
                 } 
         }   c a t c h   ( E x c e p t i o n   \ )   { 
                 \ [ ' o v e r a l l _ s u c c e s s ' ]   =   f a l s e ; 
                 \ [ ' c h e c k s ' ] [ ]   =   [ 
                         ' n a m e '   = >   ' A P I   C o n n e c t i v i t y ' , 
                         ' s u c c e s s '   = >   f a l s e , 
                         ' m e s s a g e '   = >   ' E x c e p t i o n   d u r i n g   A P I   t e s t :   '   .   \ - > g e t M e s s a g e ( ) 
                 ] ; 
         } 
         
         r e t u r n   \ ; 
 } 
 
 
