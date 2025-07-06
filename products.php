<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
// Frontend pour afficher les produits
// Affichage des erreurs pour le debug (à retirer en production)
// Include database connection
require_once 'includes/db_connect.php';

// Variables pour la pagination
$products_per_page = 9; // 3x3 grid
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $products_per_page;

// Variables pour le filtrage
$filter_category = isset($_GET['category']) ? trim($_GET['category']) : '';
$filter_price_min = isset($_GET['price_min']) ? floatval($_GET['price_min']) : 0;
$filter_price_max = isset($_GET['price_max']) ? floatval($_GET['price_max']) : 1000;

try {
    // Construire la requête avec filtres
    $where_conditions = ["status = 'published'"]; // Afficher uniquement les produits publiés
    $params = [];
    
    if (!empty($filter_category)) {
        $where_conditions[] = "category = :category";
        $params[':category'] = $filter_category;
    }
    
    if ($filter_price_min > 0) {
        $where_conditions[] = "price >= :price_min";
        $params[':price_min'] = $filter_price_min;
    }
    
    if ($filter_price_max < 1000) {
        $where_conditions[] = "price <= :price_max";
        $params[':price_max'] = $filter_price_max;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Compter le total des produits
    $count_sql = "SELECT COUNT(*) FROM products WHERE $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetchColumn();
    
    // Calculer le nombre total de pages
    $total_pages = ceil($total_products / $products_per_page);
    
    // Récupérer les produits avec pagination
    $sql = "SELECT * FROM products 
            WHERE $where_clause 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $products_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les statistiques globales
    $stats_sql = "SELECT 
                    COUNT(*) as total_count,
                    AVG(price) as avg_price,
                    MIN(price) as min_price,
                    MAX(price) as max_price
                  FROM products 
                  WHERE status = 'published'";
    $stats_stmt = $pdo->query($stats_sql);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Récupérer la liste des catégories pour le filtre
    $categories_sql = "SELECT DISTINCT category FROM products WHERE status = 'published' AND category IS NOT NULL ORDER BY category";
    $categories_stmt = $pdo->query($categories_sql);
    $categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error_message = "Erreur de base de données : " . $e->getMessage();
    $products = [];
    $stats = ['total_count' => 0, 'avg_price' => 0, 'min_price' => 0, 'max_price' => 0];
    $categories = [];
}

// Fonction pour formater le prix
function formatPrice($price) {
    return number_format($price, 2, ',', ' ') . ' €';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boutique Ésotérique - Mystica Occulta</title>
    <meta name="description" content="Découvrez notre collection d'objets magiques et ésotériques pour amplifier vos rituels et pratiques spirituelles.">
    
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
        
        .header h1 {
            font-size: 3rem;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .stats-bar {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            text-align: center;
        }
        
        .stat-item h3 {
            font-size: 2.5rem;
            color: #4f46e5;
            margin-bottom: 10px;
        }
        
        .stat-item p {
            color: #666;
            font-weight: 500;
        }
        
        .filters {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 40px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }
        
        .filters h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }
        
        .filter-group select, .filter-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            background: white;
            font-size: 14px;
        }
        
        .filter-group select:focus, .filter-group input:focus {
            outline: none;
            border-color: #4f46e5;
        }
        
        .btn {
            background: #4f46e5;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s ease;
        }
        
        .btn:hover {
            background: #3730a3;
        }
        
        .btn-secondary {
            background: #6b7280;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .product-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 48px rgba(0,0,0,0.15);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 20px;
            background: #f8f9fa;
            position: relative;
            border: 2px solid #e9ecef;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.3s ease;
            border-radius: 8px;
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
        
        .product-card:hover .product-image img {
            transform: scale(1.02);
        }
        
        .product-image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(79, 70, 229, 0.1), rgba(139, 92, 246, 0.1));
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 12px;
        }
        
        .product-card:hover .product-image-overlay {
            opacity: 1;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .stock-tag {
            background: #fee2e2;
            color: #dc2626;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            position: absolute;
            top: 10px;
            right: 10px;
        }
        
        .product-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #4f46e5;
            margin-bottom: 15px;
        }
        
        .product-description {
            color: #555;
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        
        .product-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn-buy {
            background: #4f46e5;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            text-decoration: none;
        }
        
        .btn-buy:hover {
            background: #3730a3;
            transform: translateY(-2px);
        }
        
        .btn-details {
            background: #f59e0b;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            text-decoration: none;
        }
        
        .btn-details:hover {
            background: #d97706;
            transform: translateY(-2px);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
        }
        
        .pagination a, .pagination span {
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            transition: background 0.3s ease;
        }
        
        .pagination a:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .pagination .current {
            background: #4f46e5;
            font-weight: bold;
        }
        
        .no-products {
            text-align: center;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 60px 30px;
            color: #666;
        }
        
        .no-products h3 {
            margin-bottom: 15px;
            color: #333;
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
        
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-bar {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .container {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <a href="index.html#shop" class="back-button">← Retour</a>
    
    <div class="container">
        <div class="header">
            <h1>Boutique Ésotérique</h1>
            <p>Découvrez notre collection d'objets magiques et ésotériques pour amplifier vos rituels et pratiques spirituelles.</p>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($stats['total_count'] > 0): ?>
            <div class="stats-bar">
                <div class="stat-item">
                    <h3><?php echo $stats['total_count']; ?></h3>
                    <p>Produits disponibles</p>
                </div>
                <div class="stat-item">
                    <h3><?php echo formatPrice($stats['min_price']); ?></h3>
                    <p>Prix minimum</p>
                </div>
                <div class="stat-item">
                    <h3><?php echo formatPrice($stats['max_price']); ?></h3>
                    <p>Prix maximum</p>
                </div>
            </div>
            
            <div class="filters">
                <h3>Filtrer les produits</h3>
                <form method="GET" class="filters-grid">
                    <div class="filter-group">
                        <label for="category">Catégorie</label>
                        <select name="category" id="category">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $filter_category == $category ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="price_min">Prix minimum</label>
                        <input type="number" name="price_min" id="price_min" min="0" step="1" value="<?php echo $filter_price_min; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="price_max">Prix maximum</label>
                        <input type="number" name="price_max" id="price_max" min="0" step="1" value="<?php echo $filter_price_max ?: ''; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="btn">Filtrer</button>
                        <a href="products.php" class="btn btn-secondary" style="display: inline-block; text-align: center; text-decoration: none; margin-left: 10px;">Réinitialiser</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($products)): ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if (isset($product['featured_image']) && !empty($product['featured_image'])): ?>
                                <?php if (substr($product['featured_image'], 0, 4) === 'http'): ?>
                                    <img src="<?php echo htmlspecialchars($product['featured_image']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>">
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($product['featured_image']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>">
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #e0e7ff;">
                                    <span style="font-size: 3rem; color: #4f46e5;">✦</span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($product['stock']) && $product['stock'] <= 5): ?>
                                <div class="stock-tag">Stock limité</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-header">
                            <div class="product-info">
                                <h4><?php echo htmlspecialchars($product['title']); ?></h4>
                                <?php if (!empty($product['category'])): ?>
                                    <span class="category-tag"><?php echo htmlspecialchars($product['category']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="product-price">
                            <?php echo formatPrice($product['price']); ?>
                        </div>
                        
                        <div class="product-description">
                            <?php 
                                $description = strip_tags(isset($product['description']) ? $product['description'] : '');
                                echo htmlspecialchars(substr($description, 0, 100)) . (strlen($description) > 100 ? '...' : '');
                            ?>
                        </div>
                        
                        <div class="product-actions">
                            <a href="https://api.whatsapp.com/send?phone=22967512021&text=<?php echo urlencode('Bonjour, je souhaite commander le produit: ' . $product['title'] . ' (' . $product['price'] . '€). Pouvez-vous me donner les informations sur les moyens de paiement disponibles ?'); ?>" class="btn-buy" target="_blank">
                                <i style="margin-right: 5px;">🛒</i> Acheter
                            </a>
                            <a href="product.php?slug=<?php echo urlencode($product['slug']); ?>" class="btn-details">
                                <i style="margin-right: 5px;">👁️</i> Détails
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?php echo $current_page - 1; ?><?php echo $filter_category ? '&category=' . urlencode($filter_category) : ''; ?><?php echo $filter_price_min ? '&price_min=' . $filter_price_min : ''; ?><?php echo $filter_price_max ? '&price_max=' . $filter_price_max : ''; ?>">« Précédent</a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <?php if ($i == $current_page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo $filter_category ? '&category=' . urlencode($filter_category) : ''; ?><?php echo $filter_price_min ? '&price_min=' . $filter_price_min : ''; ?><?php echo $filter_price_max ? '&price_max=' . $filter_price_max : ''; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?><?php echo $filter_category ? '&category=' . urlencode($filter_category) : ''; ?><?php echo $filter_price_min ? '&price_min=' . $filter_price_min : ''; ?><?php echo $filter_price_max ? '&price_max=' . $filter_price_max : ''; ?>">Suivant »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-products">
                <h3>Aucun produit trouvé</h3>
                <p>
                    <?php if ($filter_category || $filter_price_min > 0 || $filter_price_max < 1000): ?>
                        Aucun produit ne correspond à vos critères de filtrage. 
                        <a href="products.php">Voir tous les produits</a>
                    <?php else: ?>
                        Il n'y a pas encore de produits à afficher.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Animation d'apparition progressive des cartes
        const cards = document.querySelectorAll('.product-card');
        
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
        
        // Smooth scroll pour les liens de pagination
        document.querySelectorAll('.pagination a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = this.href;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    </script>
</body>
</html>
