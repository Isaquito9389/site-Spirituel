<?php
// Affichage des erreurs en mode développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclusion de la connexion à la base de données
require_once 'admin/includes/db_connect.php';

// Inclure le fichier qui récupère les images du site
$site_images = require_once 'get_site_images.php';

// Vérifier si le dossier uploads/images existe
$uploads_dir = 'uploads/images/';
$uploads_exists = is_dir($uploads_dir);

// Lister les fichiers dans le dossier uploads/images
$files_in_uploads = [];
if ($uploads_exists) {
    $files = scandir($uploads_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && is_file($uploads_dir . $file)) {
            $files_in_uploads[] = $file;
        }
    }
}

// Vérifier les chemins d'images pour chaque catégorie
$image_checks = [];
foreach ($site_images as $category => $path) {
    $image_checks[$category] = [
        'path' => $path,
        'exists' => file_exists($path),
        'is_readable' => is_readable($path),
        'absolute_path' => realpath($path) ?: 'Non trouvé'
    ];
}

// Vérifier spécifiquement l'image background-main.png
$specific_checks = [
    'uploads/images/background-main.png' => [
        'exists' => file_exists('uploads/images/background-main.png'),
        'is_readable' => is_readable('uploads/images/background-main.png'),
        'absolute_path' => realpath('uploads/images/background-main.png') ?: 'Non trouvé'
    ],
    'htdoc/uploads/images/background-main.png' => [
        'exists' => file_exists('htdoc/uploads/images/background-main.png'),
        'is_readable' => is_readable('htdoc/uploads/images/background-main.png'),
        'absolute_path' => realpath('htdoc/uploads/images/background-main.png') ?: 'Non trouvé'
    ]
];

// Récupérer les informations de la base de données
$db_images = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM image_library WHERE category IS NOT NULL");
    $stmt->execute();
    $db_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = $e->getMessage();
}

// Afficher les informations
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Débogage des images</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        h1, h2 {
            color: #3a0ca3;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
        img {
            max-width: 200px;
            max-height: 100px;
            border: 1px solid #ddd;
        }
        pre {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>Débogage des images du site</h1>
    
    <div class="section">
        <h2>Informations sur le serveur</h2>
        <table>
            <tr>
                <th>Variable</th>
                <th>Valeur</th>
            </tr>
            <tr>
                <td>Document Root</td>
                <td><?php echo htmlspecialchars($_SERVER['DOCUMENT_ROOT']); ?></td>
            </tr>
            <tr>
                <td>Script Filename</td>
                <td><?php echo htmlspecialchars($_SERVER['SCRIPT_FILENAME']); ?></td>
            </tr>
            <tr>
                <td>Current Directory</td>
                <td><?php echo htmlspecialchars(getcwd()); ?></td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <h2>Dossier uploads/images</h2>
        <p>
            Le dossier uploads/images 
            <?php echo $uploads_exists ? '<span class="success">existe</span>' : '<span class="error">n\'existe pas</span>'; ?>.
        </p>
        
        <?php if ($uploads_exists): ?>
            <h3>Fichiers dans le dossier uploads/images</h3>
            <?php if (empty($files_in_uploads)): ?>
                <p>Aucun fichier trouvé dans le dossier.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($files_in_uploads as $file): ?>
                        <li><?php echo htmlspecialchars($file); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>Vérification des chemins d'images</h2>
        <table>
            <tr>
                <th>Catégorie</th>
                <th>Chemin</th>
                <th>Existe</th>
                <th>Lisible</th>
                <th>Chemin absolu</th>
                <th>Aperçu</th>
            </tr>
            <?php foreach ($image_checks as $category => $check): ?>
                <tr>
                    <td><?php echo htmlspecialchars($category); ?></td>
                    <td><?php echo htmlspecialchars($check['path']); ?></td>
                    <td><?php echo $check['exists'] ? '<span class="success">Oui</span>' : '<span class="error">Non</span>'; ?></td>
                    <td><?php echo $check['is_readable'] ? '<span class="success">Oui</span>' : '<span class="error">Non</span>'; ?></td>
                    <td><?php echo htmlspecialchars($check['absolute_path']); ?></td>
                    <td>
                        <?php if ($check['exists']): ?>
                            <img src="<?php echo htmlspecialchars($check['path']); ?>" alt="<?php echo htmlspecialchars($category); ?>">
                        <?php else: ?>
                            <span class="error">Image non disponible</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <div class="section">
        <h2>Vérification spécifique de background-main.png</h2>
        <table>
            <tr>
                <th>Chemin</th>
                <th>Existe</th>
                <th>Lisible</th>
                <th>Chemin absolu</th>
            </tr>
            <?php foreach ($specific_checks as $path => $check): ?>
                <tr>
                    <td><?php echo htmlspecialchars($path); ?></td>
                    <td><?php echo $check['exists'] ? '<span class="success">Oui</span>' : '<span class="error">Non</span>'; ?></td>
                    <td><?php echo $check['is_readable'] ? '<span class="success">Oui</span>' : '<span class="error">Non</span>'; ?></td>
                    <td><?php echo htmlspecialchars($check['absolute_path']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <div class="section">
        <h2>Images dans la base de données</h2>
        <?php if (isset($db_error)): ?>
            <p class="error">Erreur lors de la récupération des images: <?php echo htmlspecialchars($db_error); ?></p>
        <?php elseif (empty($db_images)): ?>
            <p>Aucune image avec catégorie trouvée dans la base de données.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Catégorie</th>
                    <th>Chemin</th>
                    <th>Externe</th>
                    <th>Uploadé par</th>
                    <th>Date</th>
                </tr>
                <?php foreach ($db_images as $image): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($image['id']); ?></td>
                        <td><?php echo htmlspecialchars($image['category']); ?></td>
                        <td><?php echo htmlspecialchars($image['image_path']); ?></td>
                        <td><?php echo $image['is_external'] ? 'Oui' : 'Non'; ?></td>
                        <td><?php echo htmlspecialchars($image['uploaded_by']); ?></td>
                        <td><?php echo htmlspecialchars($image['uploaded_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>Actions</h2>
        <p><a href="index.php">Voir la page d'accueil</a> | <a href="admin/image_library.php">Gérer les images</a> | <a href="init_index_images.php">Initialiser les catégories d'images</a></p>
    </div>
</body>
</html>
