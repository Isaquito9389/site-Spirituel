<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
// Affichage des erreurs en mode développement
// Gestionnaire d'erreur personnalisé pour éviter les erreurs 500
set_error_handler(function(
    $errno, $errstr, $errfile, $errline
) {
    if (error_reporting() === 0) {
        return false;
    }
    $error_message = "Error [$errno] $errstr - $errfile:$errline";
    echo "<div style=\"padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin-bottom: 20px;\">\n<h3>Une erreur est survenue</h3>\n<p>Nous avons rencontré un problème lors du traitement de votre demande. Veuillez réessayer plus tard ou contacter l'administrateur.</p>\n</div>";
    return true;
}, E_ALL);

// Inclusion de la connexion à la base de données
require_once 'includes/db_connect.php';

// Vérifier si un ID ou un slug de produit est fourni
$slug = '';
$product_id = 0;
$use_slug = false;

// Méthode 1: Paramètre GET slug - ne rediriger que si on n'est pas déjà dans le contexte d'une URL ultra-propre
if (isset($_GET['slug']) && !empty($_GET['slug'])) {
    $slug = $_GET['slug'];

    // Vérifier si on vient de check_slug_type.php (URL ultra-propre)
    $is_from_clean_url = (strpos($_SERVER['REQUEST_URI'], 'product.php') === false &&
                         strpos($_SERVER['REQUEST_URI'], '?slug=') === false);

    // Rediriger seulement si on accède directement à product.php?slug=xxx
    if (!$is_from_clean_url && strpos($_SERVER['REQUEST_URI'], 'product.php') !== false) {
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: /" . urlencode($slug));
        exit;
    }
    $use_slug = true;
}
// Méthode 2: Paramètre GET id (très ancien format) - convertir en slug puis rediriger
elseif (isset($_GET['id']) && !empty($_GET['id'])) {
    $product_id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT slug FROM products WHERE id = ? AND status = 'published'");
        $stmt->execute([$product_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['slug'])) {
            // Redirection 301 vers l'URL ultra-propre
            header("HTTP/1.1 301 Moved Permanently");
            header("Location: /" . urlencode($result['slug']));
            exit;
        }
    } catch (PDOException $e) {
        }
    $use_slug = false;
}
// Méthode 3: Vérifier dans l'URL (formats propres)
else {
    $request_uri = $_SERVER['REQUEST_URI'];

    // Format /product/nom-du-produit
    $pattern = '/\/product\/([^\/\?]+)/i';
    if (preg_match($pattern, $request_uri, $matches)) {
        $slug = $matches[1];
        $use_slug = true;
    }
    // Format ultra-propre /nom-du-produit (sans préfixe)
    else {
        $pattern = '/\/([^\/\?]+)$/i';
        if (preg_match($pattern, $request_uri, $matches)) {
            $potential_slug = $matches[1];
            // Vérifier que ce n'est pas une page PHP existante
            if (!in_array($potential_slug, ['index.php', 'about.php', 'contact.php', 'blog.php', 'rituals.php', 'products.php', 'shop.php', 'testimonials.php'])) {
                $slug = $potential_slug;
                $use_slug = true;
            }
        }
    }
}

// Si aucun slug ou ID n'est trouvé, rediriger
if (empty($slug) && $product_id === 0) {
    header('Location: products.php');
    exit;
}

$product = null;
$related_products = [];

try {
    // Récupérer le produit par son slug ou son ID
    if ($use_slug) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE slug = :slug AND status = 'published'");
        $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id AND status = 'published'");
        $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
    }

    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        // Produit non trouvé
        header('Location: products.php');
        exit;
    }

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    // Récupérer les produits associés (même catégorie)
    if (!empty($product['category'])) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE category = :category AND id != :id AND status = 'published' ORDER BY RAND() LIMIT 3");
        $stmt->bindParam(':category', $product['category'], PDO::PARAM_STR);
        $stmt->bindParam(':id', $product['id'], PDO::PARAM_INT);
        $stmt->execute();
        $related_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Rediriger en cas d'erreur
    header('Location: products.php');
    exit;
}

// Titre de la page
$page_title = htmlspecialchars($product['title']) . " - Mystica Occulta";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <!-- Meta tags pour SEO -->
    <meta name="description" content="<?php echo htmlspecialchars(substr(strip_tags($product['description']), 0, 160)); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($product['title']); ?>, <?php echo htmlspecialchars($product['category']); ?>, boutique ésotérique, objets magiques, rituels, magie, spiritualité, mystica occulta">
    <meta name="author" content="Mystica Occulta">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://www.mystica-occulta.com/product.php?slug=<?php echo $product['slug']; ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="product">
    <meta property="og:url" content="https://www.mystica-occulta.com/product.php?slug=<?php echo $product['slug']; ?>">
    <meta property="og:title" content="<?php echo $page_title; ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(substr(strip_tags($product['description']), 0, 160)); ?>">
    <meta property="og:image" content="<?php echo !empty($product['featured_image']) ? htmlspecialchars($product['featured_image']) : 'https://www.mystica-occulta.com/assets/images/og-image-default.jpg'; ?>">
    <meta property="product:price:amount" content="<?php echo $product['price']; ?>">
    <meta property="product:price:currency" content="EUR">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://www.mystica-occulta.com/product.php?slug=<?php echo $product['slug']; ?>">
    <meta property="twitter:title" content="<?php echo $page_title; ?>">
    <meta property="twitter:description" content="<?php echo htmlspecialchars(substr(strip_tags($product['description']), 0, 160)); ?>">
    <meta property="twitter:image" content="<?php echo !empty($product['featured_image']) ? htmlspecialchars($product['featured_image']) : 'https://www.mystica-occulta.com/assets/images/og-image-default.jpg'; ?>">

    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
            padding: 40px 0;
        }

        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            cursor: pointer;
            font-weight: 500;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .back-button:hover {
            background: rgba(255,255,255,1);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        .breadcrumbs {
            margin-bottom: 30px;
            color: white;
        }

        .breadcrumbs a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .breadcrumbs a:hover {
            color: white;
        }

        .product-container {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 50px rgba(0,0,0,0.2);
            margin-bottom: 40px;
        }

        .product-grid {
            display: grid;
            grid-template-columns: 1fr;
        }

        @media (min-width: 768px) {
            .product-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .product-image {
            padding: 20px;
            background: #f8f9fa;
            min-height: 400px;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        /* Style alternatif pour les images qui doivent être contenues */
        .product-image.contain-image {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image.contain-image img {
            width: auto;
            height: auto;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .product-image:hover img {
            transform: scale(1.02);
        }

        .product-details {
            padding: 30px;
        }

        .category-tag {
            display: inline-block;
            background: #e0e7ff;
            color: #4f46e5;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 15px;
        }

        .product-title {
            font-size: 2rem;
            color: #333;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .product-price {
            font-size: 1.8rem;
            color: #4f46e5;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .product-description {
            color: #555;
            margin-bottom: 30px;
            line-height: 1.8;
        }

        .stock-info {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            color: #555;
        }

        .stock-info i {
            color: #4f46e5;
            margin-right: 10px;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background: #4f46e5;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: #3730a3;
            transform: translateY(-3px);
            box-shadow: 0 7px 14px rgba(79, 70, 229, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: #4f46e5;
            border: 2px solid #4f46e5;
        }

        .btn-secondary:hover {
            background: rgba(79, 70, 229, 0.1);
            transform: translateY(-3px);
        }

        .btn i {
            margin-right: 8px;
        }

        .related-products {
            margin-top: 60px;
        }

        .related-title {
            color: white;
            font-size: 1.8rem;
            margin-bottom: 30px;
            text-align: center;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .related-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .related-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        .related-image {
            height: 200px;
            overflow: hidden;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            position: relative;
        }

        .related-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.3s ease;
            border-radius: 8px;
        }

        /* Style alternatif pour les images des produits similaires */
        .related-image.contain-image {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .related-image.contain-image img {
            width: auto;
            height: auto;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .related-card:hover .related-image img {
            transform: scale(1.05);
            color: #333;
            margin-bottom: 10px;
        }

        .related-price {
            font-weight: bold;
            color: #4f46e5;
            margin-bottom: 15px;
        }

        .related-actions {
            display: flex;
            justify-content: space-between;
        }

        .related-btn {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .related-btn-buy {
            background: #4f46e5;
            color: white;
        }

        .related-btn-buy:hover {
            background: #3730a3;
        }

        .related-btn-details {
            background: #f59e0b;
            color: white;
        }

        .related-btn-details:hover {
            background: #d97706;
        }

        .cta-section {
            background: rgba(79, 70, 229, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            margin-top: 60px;
        }

        .cta-title {
            color: white;
            font-size: 2rem;
            margin-bottom: 20px;
        }

        .cta-text {
            color: rgba(255,255,255,0.8);
            margin-bottom: 30px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
    </style>
</head>
<body>
    <a href="products.php" class="back-button">← Retour à la boutique</a>

    <div class="container">
        <div class="breadcrumbs">
            <a href="index.php">Accueil</a>
            <span> > </span>
            <a href="products.php">Boutique</a>
            <?php if (!empty($product['category'])): ?>
                <span> > </span>
                <a href="products.php?category=<?php echo urlencode($product['category']); ?>"><?php echo htmlspecialchars($product['category']); ?></a>
            <?php endif; ?>
            <span> > </span>
            <span><?php echo htmlspecialchars($product['title']); ?></span>
        </div>

        <div class="product-container">
            <div class="product-grid">
                <div class="product-image">
                    <?php if (isset($product['featured_image']) && !empty($product['featured_image'])): ?>
                        <img src="<?php echo htmlspecialchars($product['featured_image']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>">
                    <?php else: ?>
                        <div style="width: 300px; height: 300px; display: flex; align-items: center; justify-content: center; background: #e0e7ff; border-radius: 10px;">
                            <span style="font-size: 5rem; color: #4f46e5;">✦</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="product-details">
                    <?php if (!empty($product['category'])): ?>
                        <div class="category-tag"><?php echo htmlspecialchars($product['category']); ?></div>
                    <?php endif; ?>

                    <h1 class="product-title"><?php echo htmlspecialchars($product['title']); ?></h1>

                    <div class="product-price"><?php echo number_format($product['price'], 2, ',', ' '); ?> €</div>

                    <div class="product-description">
                        <?php echo $product['description']; ?>
                    </div>

                    <div class="stock-info">
                        <i class="fas fa-box"></i>
                        <span>
                            <?php if ($product['stock'] > 0): ?>
                                En stock (<?php echo $product['stock']; ?> disponibles)
                            <?php else: ?>
                                En rupture de stock
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="action-buttons">
                        <?php if ($product['stock'] > 0): ?>
                            <a href="https://api.whatsapp.com/send?phone=22967512021&text=<?php echo urlencode('Bonjour, je souhaite commander le produit: ' . $product['title'] . ' (' . $product['price'] . '€). Pouvez-vous me donner les informations sur les moyens de paiement disponibles ?'); ?>" class="btn btn-primary" target="_blank">
                                <i class="fas fa-shopping-cart"></i> Commander
                            </a>
                        <?php else: ?>
                            <button class="btn btn-primary" style="opacity: 0.7; cursor: not-allowed;">
                                <i class="fas fa-shopping-cart"></i> Indisponible
                            </button>
                        <?php endif; ?>

                        <a href="contact.php?subject=demande-produit&product=<?php echo urlencode($product['title']); ?>" class="btn btn-secondary">
                            <i class="fas fa-question-circle"></i> Demander plus d'informations
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($related_products)): ?>
        <div class="related-products">
            <h2 class="related-title">Articles similaires</h2>

            <div class="related-grid">
                <?php foreach ($related_products as $related): ?>
                <div class="related-card">
                    <div class="related-image">
                        <?php if (isset($related['featured_image']) && !empty($related['featured_image'])): ?>
                            <img src="<?php echo htmlspecialchars($related['featured_image']); ?>" alt="<?php echo htmlspecialchars($related['title']); ?>">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #e0e7ff;">
                                <span style="font-size: 3rem; color: #4f46e5;">✦</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="related-content">
                        <?php if (!empty($related['category'])): ?>
                            <div class="related-category"><?php echo htmlspecialchars($related['category']); ?></div>
                        <?php endif; ?>

                        <h3 class="related-name"><?php echo htmlspecialchars($related['title']); ?></h3>

                        <div class="related-price"><?php echo number_format($related['price'], 2, ',', ' '); ?> €</div>

                        <div class="related-actions">
                            <a href="https://api.whatsapp.com/send?phone=22967512021&text=<?php echo urlencode('Bonjour, je souhaite commander le produit: ' . $related['title'] . ' (' . $related['price'] . '€). Pouvez-vous me donner les informations sur les moyens de paiement disponibles ?'); ?>" class="related-btn related-btn-buy" target="_blank">
                                <i class="fas fa-cart-plus"></i> Acheter
                            </a>
                            <a href="/<?php echo urlencode($related['slug']); ?>" class="related-btn related-btn-details">
                                <i class="fas fa-eye"></i> Détails
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="cta-section">
            <h2 class="cta-title">Vous souhaitez consulter toute notre collection?</h2>
            <p class="cta-text">Découvrez notre gamme complète d'objets ésotériques et magiques pour tous vos besoins spirituels.</p>
            <a href="products.php" class="btn btn-primary">
                Voir tous les produits
            </a>
        </div>
    </div>
</body>
</html>
