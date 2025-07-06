<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';

// Frontend pour afficher les témoignages
// Affichage des erreurs pour le debug (à retirer en production)
// Include database connection
require_once 'includes/db_connect.php';

// Variables pour la pagination
$testimonials_per_page = 9; // 3x3 grid
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $testimonials_per_page;

// Variables pour le filtrage
$filter_rating = isset($_GET['rating']) ? intval($_GET['rating']) : 0;
$filter_service = isset($_GET['service']) ? trim($_GET['service']) : '';

try {
    // Construire la requête avec filtres
    $where_conditions = ["(status = 'approved' OR status = 'pending')"]; // Afficher les témoignages approuvés et en attente
    $params = [];

    if ($filter_rating > 0) {
        $where_conditions[] = "rating = :rating";
        $params[':rating'] = $filter_rating;
    }

    if (!empty($filter_service)) {
        $where_conditions[] = "service = :service";
        $params[':service'] = $filter_service;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Compter le total des témoignages
    $count_sql = "SELECT COUNT(*) FROM testimonials WHERE $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_testimonials = $count_stmt->fetchColumn();

    // Calculer le nombre total de pages
    $total_pages = ceil($total_testimonials / $testimonials_per_page);

    // Récupérer les témoignages avec pagination
    $sql = "SELECT * FROM testimonials
            WHERE $where_clause
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $testimonials_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les statistiques globales
    $stats_sql = "SELECT
                    COUNT(*) as total_count,
                    AVG(rating) as avg_rating,
                    COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star_count
                  FROM testimonials
                  WHERE status = 'approved'";
    $stats_stmt = $pdo->query($stats_sql);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    // Récupérer la liste des services pour le filtre
    $services_sql = "SELECT DISTINCT service FROM testimonials WHERE status = 'approved' AND service IS NOT NULL ORDER BY service";
    $services_stmt = $pdo->query($services_sql);
    $services = $services_stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $error_message = "Erreur de base de données : " . $e->getMessage();
    $testimonials = [];
    $stats = ['total_count' => 0, 'avg_rating' => 0, 'five_star_count' => 0];
    $services = [];
}

// Fonction pour afficher les étoiles
function displayStars($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $stars .= '<i class="star filled">★</i>';
        } else {
            $stars .= '<i class="star">☆</i>';
        }
    }
    return $stars;
}

// Fonction pour formater la date
function formatDate($date) {
    $datetime = new DateTime($date);
    return $datetime->format('d/m/Y');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Témoignages Clients - Tous nos avis</title>
    <meta name="description" content="Découvrez les témoignages de nos clients satisfaits. Plus de <?php echo $stats['total_count']; ?> avis avec une note moyenne de <?php echo round($stats['avg_rating'], 1); ?>/5 étoiles.">

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

        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            background: white;
            font-size: 14px;
        }

        .filter-group select:focus {
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

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .testimonial-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 48px rgba(0,0,0,0.15);
        }

        .testimonial-card::before {
            content: '"';
            position: absolute;
            top: 10px;
            left: 20px;
            font-size: 4rem;
            color: #4f46e5;
            opacity: 0.2;
            font-family: serif;
        }

        .testimonial-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .client-info h4 {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 5px;
        }

        .service-tag {
            background: #e0e7ff;
            color: #4f46e5;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .rating {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 15px;
        }

        .star {
            font-size: 1.2rem;
            color: #ddd;
        }

        .star.filled {
            color: #fbbf24;
        }

        .testimonial-content {
            color: #555;
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .testimonial-date {
            color: #999;
            font-size: 0.9rem;
            text-align: right;
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

        .no-testimonials {
            text-align: center;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 60px 30px;
            color: #666;
        }

        .no-testimonials h3 {
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

            .testimonials-grid {
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
    <a href="index.html#testimonials" class="back-button">← Retour</a>

    <div class="container">
        <div class="header">
            <h1>Témoignages Clients</h1>
            <p>Découvrez ce que nos clients pensent de nos services. Leur satisfaction est notre priorité.</p>
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
                    <p>Témoignages</p>
                </div>
                <div class="stat-item">
                    <h3><?php echo round($stats['avg_rating'], 1); ?>/5</h3>
                    <p>Note moyenne</p>
                </div>
                <div class="stat-item">
                    <h3><?php echo round(($stats['five_star_count'] / $stats['total_count']) * 100); ?>%</h3>
                    <p>5 étoiles</p>
                </div>
            </div>

            <div class="filters">
                <h3>Filtrer les témoignages</h3>
                <form method="GET" class="filters-grid">
                    <div class="filter-group">
                        <label for="rating">Note</label>
                        <select name="rating" id="rating">
                            <option value="">Toutes les notes</option>
                            <option value="5" <?php echo $filter_rating == 5 ? 'selected' : ''; ?>>5 étoiles</option>
                            <option value="4" <?php echo $filter_rating == 4 ? 'selected' : ''; ?>>4 étoiles</option>
                            <option value="3" <?php echo $filter_rating == 3 ? 'selected' : ''; ?>>3 étoiles</option>
                            <option value="2" <?php echo $filter_rating == 2 ? 'selected' : ''; ?>>2 étoiles</option>
                            <option value="1" <?php echo $filter_rating == 1 ? 'selected' : ''; ?>>1 étoile</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="service">Service</label>
                        <select name="service" id="service">
                            <option value="">Tous les services</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo htmlspecialchars($service); ?>" <?php echo $filter_service == $service ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($service); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <button type="submit" class="btn">Filtrer</button>
                        <a href="testimonials.php" class="btn btn-secondary" style="display: inline-block; text-align: center; text-decoration: none; margin-left: 10px;">Réinitialiser</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if (!empty($testimonials)): ?>
            <div class="testimonials-grid">
                <?php foreach ($testimonials as $testimonial): ?>
                    <div class="testimonial-card">
                        <div class="testimonial-header">
                            <div class="client-info">
                                <h4><?php echo htmlspecialchars($testimonial['author_name']); ?></h4>
                                <?php if (!empty($testimonial['service'])): ?>
                                    <span class="service-tag"><?php echo htmlspecialchars($testimonial['service']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="rating">
                            <?php echo displayStars($testimonial['rating']); ?>
                            <span>(<?php echo $testimonial['rating']; ?>/5)</span>
                        </div>

                        <div class="testimonial-content">
                            <?php echo htmlspecialchars($testimonial['content']); ?>
                        </div>

                        <div class="testimonial-date">
                            <?php echo formatDate($testimonial['created_at']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?php echo $current_page - 1; ?><?php echo $filter_rating ? '&rating=' . $filter_rating : ''; ?><?php echo $filter_service ? '&service=' . urlencode($filter_service) : ''; ?>">« Précédent</a>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);

                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <?php if ($i == $current_page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo $filter_rating ? '&rating=' . $filter_rating : ''; ?><?php echo $filter_service ? '&service=' . urlencode($filter_service) : ''; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?><?php echo $filter_rating ? '&rating=' . $filter_rating : ''; ?><?php echo $filter_service ? '&service=' . urlencode($filter_service) : ''; ?>">Suivant »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-testimonials">
                <h3>Aucun témoignage trouvé</h3>
                <p>
                    <?php if ($filter_rating || $filter_service): ?>
                        Aucun témoignage ne correspond à vos critères de filtrage.
                        <a href="testimonials.php">Voir tous les témoignages</a>
                    <?php else: ?>
                        Il n'y a pas encore de témoignages à afficher.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Animation d'apparition progressive des cartes
        const cards = document.querySelectorAll('.testimonial-card');

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
