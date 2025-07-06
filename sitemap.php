<?php
// Sitemap dynamique pour le site spirituel
header('Content-Type: application/xml; charset=utf-8');

// Inclure la configuration et la connexion à la base de données
require_once 'bootstrap.php';
require_once 'includes/db_connect.php';

// Configuration du site
$site_url = 'https://maitrespirituel.com'; // À remplacer par votre vrai domaine
$current_date = date('Y-m-d');

// Fonction pour échapper les caractères XML
function xmlEscape($string) {
    return htmlspecialchars($string, ENT_XML1, 'UTF-8');
}

// Fonction pour formater une URL
function formatUrl($path) {
    global $site_url;
    return $site_url . '/' . ltrim($path, '/');
}

// Début du XML
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">

    <!-- Page d'accueil -->
    <url>
        <loc><?php echo formatUrl(''); ?></loc>
        <lastmod><?php echo $current_date; ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>

    <!-- Pages principales statiques -->
    <url>
        <loc><?php echo formatUrl('about'); ?></loc>
        <lastmod><?php echo $current_date; ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>

    <url>
        <loc><?php echo formatUrl('contact'); ?></loc>
        <lastmod><?php echo $current_date; ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>

    <!-- Section Rituels -->
    <url>
        <loc><?php echo formatUrl('rituals'); ?></loc>
        <lastmod><?php echo $current_date; ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
    </url>

    <!-- Section Blog -->
    <url>
        <loc><?php echo formatUrl('blog'); ?></loc>
        <lastmod><?php echo $current_date; ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>

    <!-- Section Boutique/Produits -->
    <url>
        <loc><?php echo formatUrl('shop'); ?></loc>
        <lastmod><?php echo $current_date; ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>

    <url>
        <loc><?php echo formatUrl('products'); ?></loc>
        <lastmod><?php echo $current_date; ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>

    <!-- Témoignages -->
    <url>
        <loc><?php echo formatUrl('testimonials'); ?></loc>
        <lastmod><?php echo $current_date; ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>

<?php
// Rituels dynamiques depuis la base de données
try {
    $stmt = $pdo->prepare("SELECT slug, updated_at, created_at FROM rituals WHERE status = 'published' ORDER BY created_at DESC");
    $stmt->execute();
    $rituals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rituals as $ritual) {
        $lastmod = !empty($ritual['updated_at']) ? date('Y-m-d', strtotime($ritual['updated_at'])) : date('Y-m-d', strtotime($ritual['created_at']));
        echo "    <url>\n";
        echo "        <loc>" . formatUrl('ritual/' . xmlEscape($ritual['slug'])) . "</loc>\n";
        echo "        <lastmod>" . $lastmod . "</lastmod>\n";
        echo "        <changefreq>monthly</changefreq>\n";
        echo "        <priority>0.7</priority>\n";
        echo "    </url>\n\n";
    }
} catch (PDOException $e) {
    // Log l'erreur mais continue le sitemap
    error_log("Erreur sitemap rituels: " . $e->getMessage());
}

// Articles de blog dynamiques depuis la base de données
try {
    $stmt = $pdo->prepare("SELECT slug, updated_at, created_at FROM blog_posts WHERE status = 'published' ORDER BY created_at DESC");
    $stmt->execute();
    $blog_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($blog_posts as $post) {
        $lastmod = !empty($post['updated_at']) ? date('Y-m-d', strtotime($post['updated_at'])) : date('Y-m-d', strtotime($post['created_at']));
        echo "    <url>\n";
        echo "        <loc>" . formatUrl('blog/' . xmlEscape($post['slug'])) . "</loc>\n";
        echo "        <lastmod>" . $lastmod . "</lastmod>\n";
        echo "        <changefreq>monthly</changefreq>\n";
        echo "        <priority>0.6</priority>\n";
        echo "    </url>\n\n";
    }
} catch (PDOException $e) {
    // Log l'erreur mais continue le sitemap
    error_log("Erreur sitemap blog: " . $e->getMessage());
}

// Produits dynamiques depuis la base de données
try {
    $stmt = $pdo->prepare("SELECT slug, updated_at, created_at FROM products WHERE status = 'active' ORDER BY created_at DESC");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $product) {
        $lastmod = !empty($product['updated_at']) ? date('Y-m-d', strtotime($product['updated_at'])) : date('Y-m-d', strtotime($product['created_at']));
        echo "    <url>\n";
        echo "        <loc>" . formatUrl('product/' . xmlEscape($product['slug'])) . "</loc>\n";
        echo "        <lastmod>" . $lastmod . "</lastmod>\n";
        echo "        <changefreq>weekly</changefreq>\n";
        echo "        <priority>0.6</priority>\n";
        echo "    </url>\n\n";
    }
} catch (PDOException $e) {
    // Log l'erreur mais continue le sitemap
    error_log("Erreur sitemap produits: " . $e->getMessage());
}

// Catégories de rituels si elles existent
try {
    $stmt = $pdo->prepare("SELECT DISTINCT category, MAX(updated_at) as last_update FROM rituals WHERE status = 'published' AND category IS NOT NULL GROUP BY category");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($categories as $category) {
        $slug = strtolower(str_replace([' ', 'é', 'è', 'à', 'ç'], ['-', 'e', 'e', 'a', 'c'], $category['category']));
        $lastmod = !empty($category['last_update']) ? date('Y-m-d', strtotime($category['last_update'])) : $current_date;
        echo "    <url>\n";
        echo "        <loc>" . formatUrl('rituals/category/' . xmlEscape($slug)) . "</loc>\n";
        echo "        <lastmod>" . $lastmod . "</lastmod>\n";
        echo "        <changefreq>weekly</changefreq>\n";
        echo "        <priority>0.5</priority>\n";
        echo "    </url>\n\n";
    }
} catch (PDOException $e) {
    // Log l'erreur mais continue le sitemap
    error_log("Erreur sitemap catégories: " . $e->getMessage());
}
?>

</urlset>
