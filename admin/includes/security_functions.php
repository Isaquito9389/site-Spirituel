<?php
// Include bootstrap file for secure configuration and error handling
require_once '../bootstrap.php';
/**
 * Security Functions
 * 
 * This file provides functions for enhancing website security,
 * including protection against XSS, CSRF, and other common attacks.
 */

/**
 * Sanitize input to prevent XSS attacks
 * 
 * @param string $input The input to sanitize
 * @return string Sanitized input
 */
function sanitize_input($input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitize_input($value);
        }
        return $input;
    }
    
    // Remove unnecessary whitespace
    $input = trim($input);
    
    // Remove or encode potentially dangerous characters
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

/**
 * Sanitize output to prevent XSS attacks
 * 
 * @param string $output The output to sanitize
 * @return string Sanitized output
 */
function sanitize_output($output) {
    if (is_array($output)) {
        foreach ($output as $key => $value) {
            $output[$key] = sanitize_output($value);
        }
        return $output;
    }
    
    return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a CSRF token
 * 
 * @return string CSRF token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * 
 * @param string $token The token to verify
 * @return boolean Is valid
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Regenerate session ID to prevent session fixation
 */
function regenerate_session() {
    // If this session is obsolete it means there already is a new id
    if (isset($_SESSION['OBSOLETE']) && $_SESSION['OBSOLETE'] === true) {
        return;
    }

    // Set current session to expire in 10 seconds
    $_SESSION['OBSOLETE'] = true;
    $_SESSION['EXPIRES'] = time() + 10;

    // Create new session without destroying the old one
    session_regenerate_id(false);

    // Grab current session ID and close both sessions to allow other scripts to use them
    $newSession = session_id();
    session_write_close();

    // Set session ID to the new one, and start it back up again
    session_id($newSession);
    session_start();

    // Now we unset the obsolete and expiration values for the session we want to keep
    unset($_SESSION['OBSOLETE']);
    unset($_SESSION['EXPIRES']);
}

/**
 * Check if the session is valid
 */
function is_session_valid() {
    if (isset($_SESSION['OBSOLETE']) && !isset($_SESSION['EXPIRES'])) {
        return false;
    }

    if (isset($_SESSION['EXPIRES']) && $_SESSION['EXPIRES'] < time()) {
        return false;
    }

    return true;
}

/**
 * Set secure headers to enhance security
 */
function set_secure_headers() {
    // Protect against XSS attacks
    header("X-XSS-Protection: 1; mode=block");
    
    // Prevent MIME-type sniffing
    header("X-Content-Type-Options: nosniff");
    
    // Prevent clickjacking
    header("X-Frame-Options: SAMEORIGIN");
    
    // Enable strict transport security (HSTS)
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data:;");
    
    // Referrer Policy
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Feature Policy
    header("Feature-Policy: geolocation 'none'; microphone 'none'; camera 'none'");
}

/**
 * Validate and sanitize a URL
 * 
 * @param string $url The URL to validate
 * @return string|false Sanitized URL or false if invalid
 */
function validate_url($url) {
    // Remove leading/trailing whitespace
    $url = trim($url);
    
    // Validate URL format
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    
    // Get URL components
    $components = parse_url($url);
    
    // Check for required components
    if (!isset($components['scheme']) || !isset($components['host'])) {
        return false;
    }
    
    // Allow only http and https schemes
    if ($components['scheme'] !== 'http' && $components['scheme'] !== 'https') {
        return false;
    }
    
    return $url;
}

/**
 * Sanitize filename to prevent directory traversal attacks
 * 
 * @param string $filename The filename to sanitize
 * @return string Sanitized filename
 */
function sanitize_filename($filename) {
    // Remove any directory traversal attempts
    $filename = basename($filename);
    
    // Remove any characters that could be problematic
    $filename = preg_replace("/[^a-zA-Z0-9_.-]/", "", $filename);
    
    // Ensure the filename is not empty
    if (empty($filename)) {
        $filename = 'file_' . time();
    }
    
    return $filename;
}

/**
 * Rate limiting function to prevent brute force attacks
 * 
 * @param string $key Unique identifier for the rate limit (e.g., IP address)
 * @param int $max_attempts Maximum number of attempts allowed
 * @param int $period Time period in seconds
 * @return boolean True if rate limit not exceeded, false otherwise
 */
function check_rate_limit($key, $max_attempts = 5, $period = 300) {
    $rate_limit_file = sys_get_temp_dir() . '/rate_limits.json';
    
    // Load existing rate limits
    $rate_limits = [];
    if (file_exists($rate_limit_file)) {
        $rate_limits = json_decode(file_get_contents($rate_limit_file), true) ?: [];
    }
    
    // Clean up expired entries
    $now = time();
    foreach ($rate_limits as $k => $data) {
        if ($data['expires'] < $now) {
            unset($rate_limits[$k]);
        }
    }
    
    // Check if key exists
    if (!isset($rate_limits[$key])) {
        $rate_limits[$key] = [
            'attempts' => 1,
            'expires' => $now + $period
        ];
        file_put_contents($rate_limit_file, json_encode($rate_limits));
        return true;
    }
    
    // Increment attempts
    $rate_limits[$key]['attempts']++;
    
    // Check if max attempts exceeded
    if ($rate_limits[$key]['attempts'] > $max_attempts) {
        // Update file
        file_put_contents($rate_limit_file, json_encode($rate_limits));
        return false;
    }
    
    // Update file
    file_put_contents($rate_limit_file, json_encode($rate_limits));
    return true;
}
