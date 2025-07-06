<?php
/**
 * Backlinks Management Functions
 * 
 * This file contains utility functions for managing backlinks.
 */

// Prevent direct access to this file
if (!defined('SECURE_ACCESS')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access to this file is not allowed.');
}

// Include database connection if not already included
if (!isset($pdo)) {
    require_once __DIR__ . '/db_connect.php';
}

/**
 * Get all backlinks for a specific content
 */
function get_backlinks($content_type, $content_id, $status = 'active') {
    global $pdo;
    
    try {
        $sql = "SELECT b.*, bc.name as category_name, bc.color as category_color 
                FROM backlinks b 
                LEFT JOIN backlink_categories bc ON b.type = bc.name 
                WHERE b.content_type = ? AND b.content_id = ?";
        
        if ($status !== 'all') {
            $sql .= " AND b.status = ?";
        }
        
        $sql .= " ORDER BY b.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        
        if ($status !== 'all') {
            $stmt->execute([$content_type, $content_id, $status]);
        } else {
            $stmt->execute([$content_type, $content_id]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des backlinks: " . $e->getMessage());
        return [];
    }
}

/**
 * Add a new backlink
 */
function add_backlink($content_type, $content_id, $url, $title, $description = '', $type = 'reference') {
    global $pdo;
    
    try {
        // Extract domain from URL
        $domain = parse_url($url, PHP_URL_HOST);
        
        $stmt = $pdo->prepare("INSERT INTO backlinks (content_type, content_id, url, title, description, domain, type) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$content_type, $content_id, $url, $title, $description, $domain, $type]);
        
        if ($result) {
            return $pdo->lastInsertId();
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout du backlink: " . $e->getMessage());
        return false;
    }
}

/**
 * Update a backlink
 */
function update_backlink($id, $url, $title, $description = '', $type = 'reference', $status = 'active') {
    global $pdo;
    
    try {
        // Extract domain from URL
        $domain = parse_url($url, PHP_URL_HOST);
        
        $stmt = $pdo->prepare("UPDATE backlinks SET url = ?, title = ?, description = ?, domain = ?, type = ?, status = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$url, $title, $description, $domain, $type, $status, $id]);
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour du backlink: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a backlink
 */
function delete_backlink($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM backlinks WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression du backlink: " . $e->getMessage());
        return false;
    }
}

/**
 * Get internal links for a content
 */
function get_internal_links($source_type, $source_id) {
    global $pdo;
    
    try {
        $sql = "SELECT il.*, 
                CASE 
                    WHEN il.target_type = 'blog' THEN bp.title 
                    WHEN il.target_type = 'ritual' THEN r.title 
                END as target_title,
                CASE 
                    WHEN il.target_type = 'blog' THEN bp.slug 
                    WHEN il.target_type = 'ritual' THEN r.slug 
                END as target_slug
                FROM internal_links il
                LEFT JOIN blog_posts bp ON il.target_type = 'blog' AND il.target_id = bp.id
                LEFT JOIN rituals r ON il.target_type = 'ritual' AND il.target_id = r.id
                WHERE il.source_type = ? AND il.source_id = ?
                ORDER BY il.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$source_type, $source_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des liens internes: " . $e->getMessage());
        return [];
    }
}

/**
 * Add internal link
 */
function add_internal_link($source_type, $source_id, $target_type, $target_id, $anchor_text = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO internal_links (source_type, source_id, target_type, target_id, anchor_text) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$source_type, $source_id, $target_type, $target_id, $anchor_text]);
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout du lien interne: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete internal link
 */
function delete_internal_link($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM internal_links WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression du lien interne: " . $e->getMessage());
        return false;
    }
}

/**
 * Get backlink categories
 */
function get_backlink_categories() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM backlink_categories ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des catégories de backlinks: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if URL is accessible
 */
function check_url_status($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code >= 200 && $http_code < 400;
}

/**
 * Update backlink status based on URL check
 */
function update_backlink_status($id, $status) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE backlinks SET status = ?, last_checked = NOW() WHERE id = ?");
        return $stmt->execute([$status, $id]);
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour du statut du backlink: " . $e->getMessage());
        return false;
    }
}

/**
 * Get suggested internal links based on content keywords
 */
function get_suggested_internal_links($content_type, $content_id, $content_text, $limit = 5) {
    global $pdo;
    
    try {
        // Extract keywords from content (simple approach)
        $keywords = extract_keywords($content_text);
        
        if (empty($keywords)) {
            return [];
        }
        
        // Build search query
        $keyword_conditions = [];
        $params = [];
        
        foreach ($keywords as $keyword) {
            $keyword_conditions[] = "(title LIKE ? OR content LIKE ?)";
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
        }
        
        $keyword_sql = implode(' OR ', $keyword_conditions);
        
        // Search in blog posts
        $blog_sql = "SELECT 'blog' as type, id, title, slug FROM blog_posts 
                     WHERE status = 'published' AND ($keyword_sql)";
        
        // Search in rituals
        $ritual_sql = "SELECT 'ritual' as type, id, title, slug FROM rituals 
                       WHERE status = 'published' AND ($keyword_sql)";
        
        // Exclude current content
        if ($content_type === 'blog') {
            $blog_sql .= " AND id != ?";
            $params[] = $content_id;
        } else {
            $ritual_sql .= " AND id != ?";
            $params[] = $content_id;
        }
        
        $sql = "($blog_sql) UNION ($ritual_sql) LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la recherche de liens internes suggérés: " . $e->getMessage());
        return [];
    }
}

/**
 * Extract keywords from text (simple implementation)
 */
function extract_keywords($text, $limit = 10) {
    // Remove HTML tags
    $text = strip_tags($text);
    
    // Convert to lowercase
    $text = strtolower($text);
    
    // Remove common French stop words
    $stop_words = ['le', 'de', 'et', 'à', 'un', 'il', 'être', 'et', 'en', 'avoir', 'que', 'pour', 'dans', 'ce', 'son', 'une', 'sur', 'avec', 'ne', 'se', 'pas', 'tout', 'plus', 'par', 'grand', 'en', 'une', 'être', 'et', 'à', 'il', 'avoir', 'ne', 'je', 'son', 'que', 'se', 'qui', 'ce', 'dans', 'en', 'du', 'elle', 'au', 'de', 'ce', 'le', 'pour', 'sont', 'avec', 'ils', 'tout', 'nous', 'sa', 'sur', 'faire', 'mon', 'comme', 'était', 'lui', 'ses', 'mais', 'ou', 'si', 'leur', 'y', 'dire', 'elle', 'très', 'ce', 'quand', 'être', 'dès', 'son', 'votre', 'fait', 'vous', 'après', 'sans', 'peut', 'man', 'quel', 'aussi', 'autre', 'bien'];
    
    // Extract words
    preg_match_all('/\b[a-zàâäéèêëïîôöùûüÿç]{3,}\b/u', $text, $matches);
    $words = $matches[0];
    
    // Remove stop words
    $words = array_diff($words, $stop_words);
    
    // Count word frequency
    $word_count = array_count_values($words);
    
    // Sort by frequency
    arsort($word_count);
    
    // Return top keywords
    return array_slice(array_keys($word_count), 0, $limit);
}

/**
 * Format backlink for display
 */
function format_backlink_display($backlink) {
    $domain = $backlink['domain'] ?? parse_url($backlink['url'], PHP_URL_HOST);
    $type_colors = [
        'reference' => '#3a0ca3',
        'source' => '#7209b7',
        'related' => '#f72585',
        'inspiration' => '#4cc9f0'
    ];
    
    $color = $type_colors[$backlink['type']] ?? '#6c757d';
    
    return [
        'url' => $backlink['url'],
        'title' => $backlink['title'],
        'description' => $backlink['description'],
        'domain' => $domain,
        'type' => $backlink['type'],
        'type_label' => ucfirst($backlink['type']),
        'color' => $color,
        'status' => $backlink['status'],
        'created_at' => $backlink['created_at']
    ];
}
?>
