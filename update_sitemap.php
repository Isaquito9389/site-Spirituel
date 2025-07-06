<?php
/**
 * Script de mise à jour automatique du sitemap
 * À exécuter via cron job ou manuellement
 */

// Inclure la configuration
require_once 'bootstrap.php';
require_once 'includes/db_connect.php';

// Configuration
$site_url = 'https://maitrespirituel.com'; // À remplacer par votre vrai domaine
$sitemap_file = 'sitemap.xml';
$log_file = 'logs/sitemap_update.log';

// Fonction de logging
function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message" . PHP_EOL;
    
    // Créer le dossier logs s'il n'existe pas
    if (!is_dir('logs')) {
        mkdir('logs', 0755, true);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Fonction pour échapper les caractères XML
function xmlEscape($string) {
    return htmlspecialchars($string, ENT_XML1, 'UTF-8');
}

// Fonction pour formater une URL
function formatUrl($path) {
    global $site_url;
    return $site_url . '/' . ltrim($path, '/');
}

// Début de la génération du sitemap
logMessage("Début de la mise à jour du sitemap");

try {
    $current_date = date('Y-m-d');
    
    // Commencer le XML
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
    $xml .= '        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n";
    $xml .= '        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9' . "\n";
    $xml .= '        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . "\n\n";
    
    // Page d'accueil
    $xml .= "    <url>\n";
    $xml .= "        <loc>" . formatUrl('') . "</loc>\n";
    $xml .= "        <lastmod>$current_date</lastmod>\n";
    $xml .= "        <changefreq>weekly</changefreq>\n";
    $xml .= "        <priority>1.0</priority>\n";
    $xml .= "    </url>\n\n";
    
    // Pages principales statiques
    $static_pages = [
        ['path' => 'about', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['path' => 'contact', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['path' => 'rituals', 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['path' => 'blog', 'priority' => '0.8', 'changefreq' => 'daily'],
        ['path' => 'shop', 'priority' => '0.8', 'changefreq' => 'weekly'],
        ['path' => 'products', 'priority' => '0.8', 'changefreq' => 'weekly'],
        ['path' => 'testimonials', 'priority' => '0.6', 'changefreq' => 'weekly']
    ];
    
    foreach ($static_pages as $page) {
        $xml .= "    <url>\n";
        $xml .= "        <loc>" . formatUrl($page['path']) . "</loc>\n";
        $xml .= "        <lastmod>$current_date</lastmod>\n";
        $xml .= "        <changefreq>{$page['changefreq']}</changefreq>\n";
        $xml .= "        <priority>{$page['priority']}</priority>\n";
        $xml .= "    </url>\n\n";
    }
    
    // Rituels dynamiques
    $ritual_count = 0;
    try {
        $stmt = $pdo->prepare("SELECT slug, updated_at, created_at FROM rituals WHERE status = 'published' ORDER BY created_at DESC");
        $stmt->execute();
        $rituals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rituals as $ritual) {
            $lastmod = !empty($ritual['updated_at']) ? date('Y-m-d', strtotime($ritual['updated_at'])) : date('Y-m-d', strtotime($ritual['created_at']));
            $xml .= "    <url>\n";
            $xml .= "        <loc>" . formatUrl('ritual/' . xmlEscape($ritual['slug'])) . "</loc>\n";
            $xml .= "        <lastmod>$lastmod</lastmod>\n";
            $xml .= "        <changefreq>monthly</changefreq>\n";
            $xml .= "        <priority>0.7</priority>\n";
            $xml .= "    </url>\n\n";
            $ritual_count++;
        }
        logMessage("$ritual_count rituels ajoutés au sitemap");
    } catch (PDOException $e) {
        logMessage("Erreur lors de la récupération des rituels: " . $e->getMessage());
    }
    
    // Articles de blog dynamiques
    $blog_count = 0;
    try {
        $stmt = $pdo->prepare("SELECT slug, updated_at, created_at FROM blog_posts WHERE status = 'published' ORDER BY created_at DESC");
        $stmt->execute();
        $blog_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($blog_posts as $post) {
            $lastmod = !empty($post['updated_at']) ? date('Y-m-d', strtotime($post['updated_at'])) : date('Y-m-d', strtotime($post['created_at']));
            $xml .= "    <url>\n";
            $xml .= "        <loc>" . formatUrl('blog/' . xmlEscape($post['slug'])) . "</loc>\n";
            $xml .= "        <lastmod>$lastmod</lastmod>\n";
            $xml .= "        <changefreq>monthly</changefreq>\n";
            $xml .= "        <priority>0.6</priority>\n";
            $xml .= "    </url>\n\n";
            $blog_count++;
        }
        logMessage("$blog_count articles de blog ajoutés au sitemap");
    } catch (PDOException $e) {
        logMessage("Erreur lors de la récupération des articles de blog: " . $e->getMessage());
    }
    
    // Produits dynamiques
    $product_count = 0;
    try {
        $stmt = $pdo->prepare("SELECT slug, updated_at, created_at FROM products WHERE status = 'active' ORDER BY created_at DESC");
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($products as $product) {
            $lastmod = !empty($product['updated_at']) ? date('Y-m-d', strtotime($product['updated_at'])) : date('Y-m-d', strtotime($product['created_at']));
            $xml .= "    <url>\n";
            $xml .= "        <loc>" . formatUrl('product/' . xmlEscape($product['slug'])) . "</loc>\n";
            $xml .= "        <lastmod>$lastmod</lastmod>\n";
            $xml .= "        <changefreq>weekly</changefreq>\n";
            $xml .= "        <priority>0.6</priority>\n";
            $xml .= "    </url>\n\n";
            $product_count++;
        }
        logMessage("$product_count produits ajoutés au sitemap");
    } catch (PDOException $e) {
        logMessage("Erreur lors de la récupération des produits: " . $e->getMessage());
    }
    
    // Catégories de rituels
    $category_count = 0;
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT category, MAX(updated_at) as last_update FROM rituals WHERE status = 'published' AND category IS NOT NULL GROUP BY category");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($categories as $category) {
            $slug = strtolower(str_replace([' ', 'é', 'è', 'à', 'ç'], ['-', 'e', 'e', 'a', 'c'], $category['category']));
            $lastmod = !empty($category['last_update']) ? date('Y-m-d', strtotime($category['last_update'])) : $current_date;
            $xml .= "    <url>\n";
            $xml .= "        <loc>" . formatUrl('rituals/category/' . xmlEscape($slug)) . "</loc>\n";
            $xml .= "        <lastmod>$lastmod</lastmod>\n";
            $xml .= "        <changefreq>weekly</changefreq>\n";
            $xml .= "        <priority>0.5</priority>\n";
            $xml .= "    </url>\n\n";
            $category_count++;
        }
        logMessage("$category_count catégories ajoutées au sitemap");
    } catch (PDOException $e) {
        logMessage("Erreur lors de la récupération des catégories: " . $e->getMessage());
    }
    
    // Fermer le XML
    $xml .= "</urlset>\n";
    
    // Sauvegarder le sitemap
    if (file_put_contents($sitemap_file, $xml)) {
        $total_urls = 7 + $ritual_count + $blog_count + $product_count + $category_count; // 7 pages statiques
        logMessage("Sitemap mis à jour avec succès - $total_urls URLs générées");
        
        // Mettre à jour robots.txt avec la date
        $robots_content = file_get_contents('robots.txt');
        $robots_content = preg_replace('/# Généré le \d{2}\/\d{2}\/\d{4}/', '# Généré le ' . date('d/m/Y'), $robots_content);
        file_put_contents('robots.txt', $robots_content);
        
        echo "Sitemap mis à jour avec succès!\n";
        echo "Total URLs: $total_urls\n";
        echo "- Pages statiques: 7\n";
        echo "- Rituels: $ritual_count\n";
        echo "- Articles de blog: $blog_count\n";
        echo "- Produits: $product_count\n";
        echo "- Catégories: $category_count\n";
        
    } else {
        logMessage("Erreur lors de la sauvegarde du sitemap");
        echo "Erreur lors de la sauvegarde du sitemap\n";
    }
    
} catch (Exception $e) {
    logMessage("Erreur générale: " . $e->getMessage());
    echo "Erreur: " . $e->getMessage() . "\n";
}

logMessage("Fin de la mise à jour du sitemap");
?>
