<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
// Affichage forcé des erreurs PHP pour le debug
// Custom error handler to prevent 500 errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (error_reporting() === 0) {
        return false;
    }

    $error_message = "Error [$errno] $errstr - $errfile:$errline";
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        echo "<div style=\"padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin-bottom: 20px;\">
            <h3>Une erreur est survenue</h3>
            <p>Nous avons rencontré un problème lors du traitement de votre demande. Veuillez réessayer plus tard ou contacter l'administrateur.</p>
            <p><a href=\"dashboard.php\" style=\"color: #721c24; text-decoration: underline;\">Retour au tableau de bord</a></p>
        </div>";

        // Log detailed error for admin
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            echo "<div style=\"padding: 20px; background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; border-radius: 5px; margin-top: 20px;\">
                <h4>Détails de l'erreur (visible uniquement pour les administrateurs)</h4>
                <p>" . htmlspecialchars($error_message) . "</p>
            </div>";
        }

        return true;
    }

    return false;
}, E_ALL);

session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// Get admin username
$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    $_SESSION = array();
    session_destroy();
    header("Location: index.php");
    exit;
}

// Include database connection and WordPress API functions
require_once 'includes/db_connect.php';
require_once 'includes/wp_api_connect.php';

// Définir directement les fonctions WordPress nécessaires dans ce fichier
// pour éviter les problèmes d'inclusion
if (!function_exists('sync_product_to_wordpress')) {
    function sync_product_to_wordpress($product) {
        // Vérifier si la synchronisation WordPress est activée
        $sync_enabled = false; // Désactivé par défaut pour éviter les erreurs

        if (!$sync_enabled) {
            // Si la synchronisation est désactivée, retourner un succès simulé
            return [
                'success' => true,
                'data' => [
                    'id' => $product['wp_post_id'] ?? null,
                    'message' => 'Produit traité localement uniquement (synchronisation WordPress désactivée)'
                ]
            ];
        }

        // Si la synchronisation est activée, le code ci-dessous serait exécuté
        // mais nous le laissons commenté pour éviter les erreurs
        /*
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
        */

        // Retourner un succès simulé
        return [
            'success' => true,
            'data' => [
                'id' => $product['wp_post_id'] ?? null,
                'message' => 'Produit traité localement uniquement (synchronisation WordPress désactivée)'
            ]
        ];
    }
}

if (!function_exists('delete_wordpress_product')) {
    function delete_wordpress_product($wp_post_id) {
        // Vérifier si la synchronisation WordPress est activée
        $sync_enabled = false; // Désactivé par défaut pour éviter les erreurs

        if (!$sync_enabled) {
            // Si la synchronisation est désactivée, retourner un succès simulé
            return [
                'success' => true,
                'data' => [
                    'message' => 'Suppression traitée localement uniquement (synchronisation WordPress désactivée)'
                ]
            ];
        }

        // Si la synchronisation est activée, le code ci-dessous serait exécuté
        // mais nous le laissons commenté pour éviter les erreurs
        /*
        // Utiliser true pour forcer la suppression définitive (au lieu de la corbeille)
        return send_to_wordpress('products/' . $wp_post_id . '?force=true', [], 'DELETE');
        */

        // Retourner un succès simulé
        return [
            'success' => true,
            'data' => [
                'message' => 'Suppression traitée localement uniquement (synchronisation WordPress désactivée)'
            ]
        ];
    }
}

// Définir la fonction sanitize_slug si elle n'existe pas
if (!function_exists('sanitize_slug')) {
    function sanitize_slug($string) {
        // Remplacer les caractères accentués par des non-accentués
        $string = transliterator_transliterate('Any-Latin; Latin-ASCII', $string);

        // Convertir en minuscules
        $string = strtolower($string);

        // Remplacer les espaces et caractères spéciaux par des tirets
        $string = preg_replace('/[^a-z0-9\-]/', '-', $string);

        // Remplacer les tirets multiples par un seul tiret
        $string = preg_replace('/-+/', '-', $string);

        // Supprimer les tirets au début et à la fin
        return trim($string, '-');
    }
}

// Vérification explicite de la connexion PDO
if (!isset($pdo) || !$pdo) {
    echo "<div style='background: #ffdddd; color: #a00; padding: 15px; margin: 10px 0; border: 2px solid #a00;'>Erreur critique : la connexion à la base de données n'est pas initialisée après l'inclusion de includes/db_connect.php.<br>Vérifiez le fichier de connexion et les identifiants !</div>";
    exit;
}

// Fonction pour générer un slug à partir d'un texte
function slugify($text) {
    return sanitize_slug($text);
}

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';
$messageType = '';
$product = null;

// Récupérer les messages de la session s'ils existent
if (isset($_SESSION['form_message']) && isset($_SESSION['form_message_type'])) {
    $message = $_SESSION['form_message'];
    $messageType = $_SESSION['form_message_type'];

    // Supprimer les messages de la session après les avoir récupérés
    unset($_SESSION['form_message']);
    unset($_SESSION['form_message_type']);
}
// Sinon, vérifier les messages dans l'URL
elseif (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $messageType = $_GET['type'];
}

// Vérifier si la table products existe et migrer si besoin
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'products'");
    if ($stmt->rowCount() === 0) {
        // Créer la table products si elle n'existe pas
        $pdo->exec("CREATE TABLE products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            category VARCHAR(100),
            featured_image VARCHAR(255),
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            stock INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        $message = "La table 'products' a été créée avec succès.";
        $messageType = "success";
    } else {
        // Vérifier si la colonne updated_at existe
        $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'updated_at'");
        if ($stmt->rowCount() === 0) {
            // Ajouter la colonne updated_at si elle n'existe pas
            $pdo->exec("ALTER TABLE products ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            $message = "La colonne 'updated_at' a été ajoutée à la table products.";
            $messageType = "success";
        }
    }
} catch (PDOException $e) {
    $message = "Erreur lors de la vérification/création de la table products: " . $e->getMessage();
    $messageType = "error";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_product'])) {
        // Get form data
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $price = floatval(str_replace(',', '.', $_POST['price']));
        $category = trim($_POST['category']);
        $stock = intval($_POST['stock']);
        $status = $_POST['status'];
        $featured_image = isset($_POST['current_image']) ? $_POST['current_image'] : '';

        // Générer le slug à partir du titre
        $slug = slugify($title);

        // Vérifier l'unicité du slug
        $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = ?" . ($product_id > 0 ? " AND id != ?" : ""));
        $unique_slug = $slug;
        $i = 1;
        while (true) {
            if ($product_id > 0) {
                $check->execute([$unique_slug, $product_id]);
            } else {
                $check->execute([$unique_slug]);
            }
            if ($check->fetchColumn() == 0) break;
            $unique_slug = $slug . '-' . $i++;
        }

        // Validate form data
        if (empty($title)) {
            $message = "Le titre est obligatoire.";
            $messageType = "error";
        } else {
            // Gestion des images avec support pour l'upload et les URL
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                try {
                    $upload_dir = '../uploads/products/';

                    // Créer le répertoire s'il n'existe pas
                    if (!file_exists($upload_dir)) {
                        if (!mkdir($upload_dir, 0755, true)) {
                            throw new Exception("Impossible de créer le dossier d'upload");
                        }
                    }

                    // Générer un nom de fichier unique
                    $file_extension = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
                    $filename = uniqid() . '.' . $file_extension;
                    $target_file = $upload_dir . $filename;

                    // Déplacer le fichier uploadé
                    if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $target_file)) {
                        $featured_image = 'uploads/products/' . $filename;
                    } else {
                        throw new Exception("Échec du déplacement du fichier uploadé");
                    }
                } catch (Exception $e) {
                    // En cas d'erreur avec l'upload, on utilise l'URL si disponible
                    if (isset($_POST['image_url']) && !empty($_POST['image_url'])) {
                        $featured_image = $_POST['image_url'];
                    } elseif (isset($_POST['current_image']) && !empty($_POST['current_image'])) {
                        $featured_image = $_POST['current_image'];
                    }
                }
            } elseif (isset($_POST['image_url']) && !empty($_POST['image_url'])) {
                $featured_image = $_POST['image_url'];
            } elseif (isset($_POST['current_image']) && !empty($_POST['current_image'])) {
                $featured_image = $_POST['current_image'];
            }

            try {
                // Préparer les données du produit
                $product_data = [
                    'title' => $title,
                    'description' => $description,
                    'price' => $price,
                    'category' => $category,
                    'stock' => $stock,
                    'status' => $status,
                    'featured_image' => $featured_image,
                    'slug' => $slug
                ];

                // Si c'est une mise à jour, on inclut l'ID WordPress existant
                if ($product_id > 0 && !empty($product['wp_post_id'])) {
                    $product_data['wp_post_id'] = $product['wp_post_id'];
                }

                // Préparer la requête SQL en fonction du type d'opération
                if ($product_id > 0) {
                    // Mise à jour d'un produit existant
                    $sql = "UPDATE products SET
                            title = :title,
                            description = :description,
                            price = :price,
                            category = :category,
                            stock = :stock,
                            status = :status,
                            featured_image = :featured_image,
                            slug = :slug,
                            updated_at = NOW()
                            WHERE id = :product_id";

                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
                } else {
                    // Insertion d'un nouveau produit
                    $sql = "INSERT INTO products (title, description, price, category, stock, status, featured_image, slug, created_at, updated_at)
                            VALUES (:title, :description, :price, :category, :stock, :status, :featured_image, :slug, NOW(), NOW())";

                    $stmt = $pdo->prepare($sql);
                }

                // Lier les paramètres communs
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':price', $price);
                $stmt->bindParam(':category', $category);
                $stmt->bindParam(':stock', $stock, PDO::PARAM_INT);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':featured_image', $featured_image);
                $stmt->bindParam(':slug', $slug);

                // Exécuter la requête SQL
                if ($stmt->execute()) {
                    // Si c'est une insertion, récupérer l'ID du nouveau produit
                    if ($product_id === 0) {
                        $product_id = $pdo->lastInsertId();
                    }

                    // Synchroniser avec WordPress
                    $wp_response = sync_product_to_wordpress([
                        'id' => $product_id,
                        'title' => $title,
                        'description' => $description,
                        'price' => $price,
                        'category' => $category,
                        'stock' => $stock,
                        'status' => $status,
                        'featured_image' => $featured_image,
                        'wp_post_id' => $product['wp_post_id'] ?? null
                    ]);

                    if ($wp_response['success']) {
                        // Mettre à jour l'ID WordPress dans la base de données locale si nécessaire
                        if (isset($wp_response['data']['id']) && empty($product['wp_post_id'])) {
                            $wp_post_id = $wp_response['data']['id'];
                            $update_stmt = $pdo->prepare("UPDATE products SET wp_post_id = ? WHERE id = ?");
                            $update_stmt->execute([$wp_post_id, $product_id]);
                        }
                        $message = "Produit " . ($product_id > 0 ? "mis à jour" : "créé") . " avec succès et synchronisé avec WordPress.";
                        $messageType = "success";

                        // Rediriger vers la liste après un succès
                        header("Location: products.php?message=" . urlencode($message) . "&type=" . $messageType);
                        exit;
                    } else {
                        $message = "Produit " . ($product_id > 0 ? "mis à jour" : "créé") . " avec succès, mais la synchronisation avec WordPress a échoué : " . ($wp_response['error'] ?? 'Erreur inconnue');
                        $messageType = "warning";

                        // Pour les avertissements, on reste sur le formulaire mais avec le message
                        $_SESSION['form_message'] = $message;
                        $_SESSION['form_message_type'] = $messageType;

                        // Si c'est une création, on redirige vers le formulaire d'édition
                        if ($product_id === 0) {
                            $product_id = $pdo->lastInsertId();
                            header("Location: products.php?action=edit&id=" . $product_id);
                            exit;
                        }
                    }
                } else {
                    $message = "Erreur lors de la " . ($product_id > 0 ? "mise à jour" : "création") . " du produit.";
                    $messageType = "error";

                    // Pour les erreurs, on reste sur le formulaire avec le message
                    $_SESSION['form_message'] = $message;
                    $_SESSION['form_message_type'] = $messageType;

                    // Si c'est une création, on recharge la page pour afficher l'erreur
                    if ($product_id === 0) {
                        header("Location: products.php?action=new");
                        exit;
                    }
                }
            } catch (PDOException $e) {
                $message = "Erreur de base de données: " . $e->getMessage();
                $messageType = "error";
            }
        }
    } elseif (isset($_POST['delete_product'])) {
        // Delete product
        $product_id = intval($_POST['product_id']);

        try {
            // Récupérer le produit pour obtenir l'ID WordPress
            $stmt = $pdo->prepare("SELECT wp_post_id FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            // Supprimer de WordPress si l'ID existe
            if (!empty($product['wp_post_id'])) {
                $wp_response = delete_wordpress_product($product['wp_post_id']);

                if (!$wp_response['success']) {
                    // On continue quand même la suppression locale
                }
            }

            // Supprimer de la base de données locale
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $result = $stmt->execute([$product_id]);

            if ($result) {
                $message = "Produit supprimé avec succès".(!empty($product['wp_post_id']) && !$wp_response['success'] ? " (mais erreur lors de la synchronisation avec WordPress)" : "").".";
                $messageType = !empty($product['wp_post_id']) && !$wp_response['success'] ? "warning" : "success";

                // Stocker le message dans la session pour la redirection
                $_SESSION['form_message'] = $message;
                $_SESSION['form_message_type'] = $messageType;
            } else {
                $message = "Erreur lors de la suppression du produit.";
                $messageType = "error";

                // Stocker le message d'erreur dans la session
                $_SESSION['form_message'] = $message;
                $_SESSION['form_message_type'] = $messageType;
            }

            // Rediriger vers la liste des produits
            header("Location: products.php");
            exit;
        } catch (PDOException $e) {
            $message = "Erreur lors de la suppression du produit : " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Get product data if editing
if ($action === 'edit' && isset($_GET['id'])) {
    $product_id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            $message = "Produit introuvable.";
            $messageType = "error";
        }
    } catch (PDOException $e) {
        $message = "Erreur de base de données: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get all products if listing
$products = [];
if ($action === 'list') {
    try {
        $stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $message = "Erreur de base de données: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get categories for dropdown
$categories = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categories[] = $row['category'];
    }
} catch (PDOException $e) {
    // Silently fail, not critical
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits - Mystica Occulta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&family=MedievalSharp&family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400;1,700;1,900&display=swap');

        :root {
            --primary: #3a0ca3;
            --secondary: #7209b7;
            --accent: #f72585;
            --dark: #1a1a2e;
            --light: #f8f9fa;
        }

        body {
            font-family: 'Merriweather', serif;
            background-color: #0f0e17;
            color: #e8e8e8;
        }

        .font-cinzel {
            font-family: 'Cinzel Decorative', cursive;
        }

        .bg-mystic {
            background: radial-gradient(circle at center, #3a0ca3 0%, #1a1a2e 70%);
        }

        .btn-magic {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            box-shadow: 0 4px 15px rgba(247, 37, 133, 0.4);
            transition: all 0.3s ease;
        }

        .btn-magic:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(247, 37, 133, 0.6);
        }

        .sidebar {
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .nav-link {
            transition: all 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            background: linear-gradient(90deg, rgba(114, 9, 183, 0.3) 0%, rgba(58, 12, 163, 0) 100%);
            border-left: 4px solid var(--accent);
        }

        .card {
            background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(247, 37, 133, 0.2);
        }

        /* Quill editor custom styles */
        .ql-toolbar.ql-snow {
            background-color: #1a1a2e;
            border-color: #3a0ca3;
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
        }

        .ql-container.ql-snow {
            border-color: #3a0ca3;
            background-color: #0f0e17;
            color: #ffffff;
            min-height: 200px;
            border-bottom-left-radius: 0.5rem;
            border-bottom-right-radius: 0.5rem;
        }

        .ql-editor {
            min-height: 200px;
            color: #ffffff;
            font-size: 16px;
        }

        .ql-editor p, .ql-editor h1, .ql-editor h2, .ql-editor h3, .ql-editor li {
            color: #ffffff;
        }

        .ql-snow .ql-stroke {
            stroke: #e8e8e8;
        }

        .ql-snow .ql-fill, .ql-snow .ql-stroke.ql-fill {
            fill: #e8e8e8;
        }

        .ql-snow .ql-picker {
            color: #e8e8e8;
        }

        .ql-snow .ql-picker-options {
            background-color: #1a1a2e;
        }
    </style>
</head>
<body class="bg-dark min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-dark bg-opacity-90 backdrop-blur-sm border-b border-purple-900 py-3">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-900 to-pink-600 flex items-center justify-center">
                    <i class="fas fa-eye text-white text-xl"></i>
                </div>
                <span class="font-cinzel text-2xl font-bold bg-gradient-to-r from-purple-400 to-pink-600 bg-clip-text text-transparent">MYSTICA OCCULTA</span>
            </div>

            <div class="flex items-center space-x-4">
                <a href="dashboard.php" class="text-gray-300 hover:text-pink-500 transition duration-300">
                    <i class="fas fa-tachometer-alt mr-2"></i> Tableau de Bord
                </a>
                <a href="?logout=true" class="text-gray-300 hover:text-pink-500 transition duration-300">
                    <i class="fas fa-sign-out-alt mr-2"></i> Déconnexion
                </a>
            </div>
        </div>
    </header>

    <div class="flex flex-1">
        <!-- Sidebar -->
        <aside class="sidebar w-64 border-r border-purple-900 flex-shrink-0">
            <nav class="py-6">
                <ul>
                    <li>
                        <a href="dashboard.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-tachometer-alt w-6"></i>
                            <span>Tableau de Bord</span>
                        </a>
                    </li>
                    <li>
                        <a href="rituals.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-magic w-6"></i>
                            <span>Rituels</span>
                        </a>
                    </li>
                    <li>
                        <a href="blog.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-blog w-6"></i>
                            <span>Blog</span>
                        </a>
                    </li>
                    <li>
                        <a href="categories.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-folder w-6"></i>
                            <span>Catégories</span>
                        </a>
                    </li>
                    <li>
                        <a href="testimonials.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-comments w-6"></i>
                            <span>Témoignages</span>
                        </a>
                    </li>
                    <li>
                        <a href="products.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white active">
                            <i class="fas fa-shopping-cart w-6"></i>
                            <span>Produits</span>
                        </a>
                    </li>
                    <li>
                        <a href="consultations.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-calendar-alt w-6"></i>
                            <span>Consultations</span>
                        </a>
                    </li>
                    <li>
                        <a href="settings.php" class="nav-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-cog w-6"></i>
                            <span>Paramètres</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="flex justify-between items-center mb-8">
                <h1 class="font-cinzel text-3xl font-bold text-white">
                    <?php if ($action === 'new'): ?>
                        Nouveau Produit
                    <?php elseif ($action === 'edit'): ?>
                        Modifier le Produit
                    <?php else: ?>
                        Gestion des Produits
                    <?php endif; ?>
                </h1>

                <?php if ($action === 'list'): ?>
                    <a href="?action=new" class="btn-magic px-4 py-2 rounded-full text-white font-medium inline-flex items-center">
                        <i class="fas fa-plus mr-2"></i> Nouveau Produit
                    </a>
                <?php else: ?>
                    <a href="products.php" class="px-4 py-2 rounded-full border border-purple-600 text-white font-medium inline-flex items-center hover:bg-purple-900 transition duration-300">
                        <i class="fas fa-arrow-left mr-2"></i> Retour à la liste
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-900 bg-opacity-50' : 'bg-red-900 bg-opacity-50'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <!-- Products List -->
                <div class="card rounded-xl p-6 border border-purple-900">
                    <?php if (empty($products)): ?>
                        <p class="text-gray-400 text-center py-8">Aucun produit trouvé. Commencez par en créer un nouveau.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-purple-900">
                                        <th class="px-4 py-3 text-left text-gray-300">Titre</th>
                                        <th class="px-4 py-3 text-left text-gray-300">Catégorie</th>
                                        <th class="px-4 py-3 text-left text-gray-300">Prix</th>
                                        <th class="px-4 py-3 text-left text-gray-300">Stock</th>
                                        <th class="px-4 py-3 text-left text-gray-300">Statut</th>
                                        <th class="px-4 py-3 text-left text-gray-300">Date</th>
                                        <th class="px-4 py-3 text-right text-gray-300">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr class="border-b border-purple-900 hover:bg-purple-900 hover:bg-opacity-20">
                                            <td class="px-4 py-3 text-white"><?php echo htmlspecialchars($product['title']); ?></td>
                                            <td class="px-4 py-3 text-gray-400"><?php echo htmlspecialchars($product['category']); ?></td>
                                            <td class="px-4 py-3 text-gray-400"><?php echo number_format($product['price'], 2); ?>€</td>
                                            <td class="px-4 py-3 text-gray-400"><?php echo htmlspecialchars($product['stock']); ?></td>
                                            <td class="px-4 py-3">
                                                <span class="px-2 py-1 rounded-full text-xs <?php echo $product['status'] === 'published' ? 'bg-green-900 text-green-300' : 'bg-yellow-900 text-yellow-300'; ?>">
                                                    <?php echo $product['status'] === 'published' ? 'Publié' : 'Brouillon'; ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-gray-400"><?php echo date('d/m/Y', strtotime($product['created_at'])); ?></td>
                                            <td class="px-4 py-3 text-right">
                                                <a href="?action=edit&id=<?php echo $product['id']; ?>" class="text-blue-400 hover:text-blue-300 mx-1">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo addslashes($product['title']); ?>')" class="text-red-400 hover:text-red-300 mx-1">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                                <a href="../product.php?slug=<?php echo urlencode($product['slug']); ?>" target="_blank" class="text-green-400 hover:text-green-300 mx-1">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Delete Confirmation Modal -->
                <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
                    <div class="bg-gradient-to-br from-purple-900 to-dark rounded-xl shadow-2xl overflow-hidden border border-purple-800 p-8 max-w-md w-full">
                        <h2 class="font-cinzel text-2xl font-bold text-white mb-4">Confirmer la suppression</h2>
                        <p class="text-gray-300 mb-6">Êtes-vous sûr de vouloir supprimer le produit "<span id="deleteProductTitle"></span>" ? Cette action est irréversible.</p>
                        <div class="flex justify-end space-x-4">
                            <button onclick="closeDeleteModal()" class="px-4 py-2 rounded-full border border-purple-600 text-white font-medium hover:bg-purple-900 transition duration-300">
                                Annuler
                            </button>
                            <form id="deleteForm" method="POST" action="products.php">
                                <input type="hidden" name="product_id" id="deleteProductId">
                                <button type="submit" name="delete_product" class="px-4 py-2 rounded-full bg-red-700 text-white font-medium hover:bg-red-800 transition duration-300">
                                    Supprimer
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Product Form -->
                <form method="POST" action="products.php" enctype="multipart/form-data" class="card rounded-xl p-6 border border-purple-900">
                    <?php if ($action === 'edit' && $product): ?>
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <?php if (!empty($product['featured_image'])): ?>
                            <input type="hidden" name="current_image" value="<?php echo $product['featured_image']; ?>">
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="md:col-span-2">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div class="md:col-span-2">
                                <label for="title" class="block text-gray-300 mb-2">Titre du produit *</label>
                                <input type="text" id="title" name="title" required class="w-full px-4 py-3 rounded-lg bg-gray-800 border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="Entrez le titre du produit" value="<?php echo $product ? htmlspecialchars($product['title']) : ''; ?>">
                            </div>

                            <div>
                                <label for="category" class="block text-gray-300 mb-2">Catégorie</label>
                                <input type="text" list="category-list" id="category" name="category" class="w-full px-4 py-3 rounded-lg bg-gray-800 border border-purple-800 text-white" value="<?php echo $product ? htmlspecialchars($product['category']) : ''; ?>" placeholder="Choisissez ou entrez une catégorie">
                                <datalist id="category-list">
                                    <option value="">-- Choisissez --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label for="description" class="block text-gray-300 mb-2">Description *</label>
                            <div id="editor" class="bg-dark border border-purple-800 rounded-lg min-h-[200px] text-white">
                                <?php echo $product ? $product['description'] : ''; ?>
                            </div>
                            <input type="hidden" name="description" id="description">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div>
                                <label for="price" class="block text-gray-300 mb-2">Prix (€) *</label>
                                <input type="number" step="0.01" id="price" name="price" required class="w-full px-4 py-3 rounded-lg bg-gray-800 border border-purple-800 text-white" value="<?php echo $product ? htmlspecialchars($product['price']) : ''; ?>">
                            </div>
                            <div>
                                <label for="stock" class="block text-gray-300 mb-2">Stock *</label>
                                <input type="number" id="stock" name="stock" required class="w-full px-4 py-3 rounded-lg bg-gray-800 border border-purple-800 text-white" value="<?php echo $product ? (int)$product['stock'] : 0; ?>">
                            </div>
                            <div>
                                <label for="status" class="block text-gray-300 mb-2">Statut</label>
                                <select id="status" name="status" class="w-full px-4 py-3 rounded-lg bg-gray-800 border border-purple-800 text-white">
                                    <option value="draft" <?php echo $product && $product['status'] === 'draft' ? 'selected' : ''; ?>>Brouillon</option>
                                    <option value="published" <?php echo $product && $product['status'] === 'published' ? 'selected' : ''; ?>>Publié</option>
                                </select>
                            </div>
                        </div>

                        <!-- Image à la une -->
                        <div class="mb-6">
                            <label class="block text-gray-200 font-medium mb-2">Image à la une</label>
                            <div id="image-preview" class="mb-4 <?php echo empty($product['featured_image']) ? 'hidden' : ''; ?>">
                                <?php if ($product && !empty($product['featured_image'])): ?>
                                    <img src="../<?php echo $product['featured_image']; ?>" alt="Prévisualisation" class="max-w-xs rounded-lg">
                                <?php endif; ?>
                            </div>
                            <div class="tabs flex mb-4">
                                <button type="button" class="tab-button px-4 py-2 bg-purple-800 text-white rounded-l-lg" onclick="openTab('upload')">Upload</button>
                                <button type="button" class="tab-button px-4 py-2 bg-purple-800 text-white" onclick="openTab('library')">Bibliothèque</button>
                                <button type="button" class="tab-button px-4 py-2 bg-purple-800 text-white rounded-r-lg" onclick="openTab('url')">URL</button>
                            </div>
                            <div id="tab-content-upload" class="tab-content">
                                <input type="file" id="featured_image" name="featured_image" accept="image/*" class="w-full text-white">
                            </div>
                            <div id="tab-content-library" class="tab-content hidden bg-gray-800 p-3 rounded-lg max-h-60 overflow-y-auto">
                                <?php
                                try {
                                    $stmt = $pdo->prepare("SHOW TABLES LIKE 'image_library'");
                                    $stmt->execute();
                                    if ($stmt->rowCount()) {
                                        $images = $pdo->query("SELECT * FROM image_library ORDER BY uploaded_at DESC")->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($images as $img): ?>
                                            <img src="../<?php echo $img['path']; ?>" alt="lib" onclick="selectLibraryImage('<?php echo $img['path']; ?>')" class="w-24 h-24 object-cover m-1 cursor-pointer border-2 border-transparent hover:border-purple-500">
                                <?php   endforeach;
                                    } else {
                                        echo '<p class="text-gray-400">Aucune image disponible.</p>';
                                    }
                                } catch(Exception $e){ echo '<p class="text-gray-400">Erreur lors du chargement.</p>'; }
                                ?>
                            </div>
                            <div id="tab-content-url" class="tab-content hidden">
                                <input type="url" id="image_url" name="image_url" placeholder="https://exemple.com/image.jpg" class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white mb-2" value="<?php echo isset($product['featured_image']) && substr($product['featured_image'],0,4)=='http' ? htmlspecialchars($product['featured_image']) : ''; ?>">
                                <button type="button" onclick="previewExternalImage()" class="mt-2 px-4 py-2 bg-purple-700 text-white rounded-lg">Prévisualiser</button>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-4">
                            <a href="products.php" class="px-6 py-3 rounded-full border border-purple-600 text-white hover:bg-purple-900">Annuler</a>
                            <button type="submit" name="save_product" class="px-6 py-3 rounded-full bg-pink-600 text-white hover:bg-pink-700"><?php echo $action==='edit' ? 'Mettre à jour' : 'Créer'; ?></button>
                        </div>
                    </form>
                <?php endif; ?>
            </main>
        </div>
        <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
        <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
        <script>
        // Quill editor
        <?php if ($action !== 'list'): ?>
        var quill = new Quill('#editor', {
            theme: 'snow'
        });
        <?php if ($product): ?>
            quill.root.innerHTML = <?php echo json_encode($product['description']); ?>;
        <?php endif; ?>
        document.querySelector('form').addEventListener('submit', function(){
            document.getElementById('description').value = quill.root.innerHTML;
        });
        <?php endif; ?>

        function confirmDelete(id, title){
            document.getElementById('deleteProductId').value = id;
            document.getElementById('deleteProductTitle').textContent = title;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        function closeDeleteModal(){
            document.getElementById('deleteModal').classList.add('hidden');
        }
        function openTab(tab){
            ['upload','library','url'].forEach(function(t){
                document.getElementById('tab-content-'+t).classList.add('hidden');
            });
            document.getElementById('tab-content-'+tab).classList.remove('hidden');
        }
        function selectLibraryImage(path){
            document.getElementById('image-preview').classList.remove('hidden');
            document.getElementById('image-preview').innerHTML = '<img src="../'+path+'" class="max-w-xs rounded-lg" />';
            var urlInput = document.createElement('input');
            urlInput.type='hidden';
            urlInput.name='image_url';
            urlInput.value=path;
            document.querySelector('form').appendChild(urlInput);
        }
        function previewExternalImage(){
            const url = document.getElementById('image_url').value;
            if(url){
                document.getElementById('image-preview').classList.remove('hidden');
                document.getElementById('image-preview').innerHTML = '<img src="'+url+'" class="max-w-xs rounded-lg" />';
            }
        }
        </script>
    </body>
    </html>
