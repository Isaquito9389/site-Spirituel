<?php
// Affichage des erreurs en mode développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure le fichier qui récupère les images du site
$site_images = require_once 'get_site_images.php';

// Afficher les informations sur l'image background_main
echo "<h2>Informations sur l'image background_main</h2>";
echo "<p>Chemin dans site_images: " . htmlspecialchars($site_images['background_main']) . "</p>";
echo "<p>L'image existe: " . (file_exists($site_images['background_main']) ? 'Oui' : 'Non') . "</p>";

// Tester le remplacement dans une chaîne de test
$test_html = "style=\"background-image: url('assets/images/background-main.jpg'); background-size: cover;\"";
$replacement = "url('" . $site_images['background_main'] . "')";
$replaced_html = str_replace("url('assets/images/background-main.jpg')", $replacement, $test_html);

echo "<h2>Test de remplacement</h2>";
echo "<p>HTML original: " . htmlspecialchars($test_html) . "</p>";
echo "<p>Remplacement: " . htmlspecialchars($replacement) . "</p>";
echo "<p>HTML remplacé: " . htmlspecialchars($replaced_html) . "</p>";

// Tester avec le contenu réel de index.html
$html_content = file_get_contents('index.html');
$replacements = [
    "url('assets/images/background-main.jpg')" => "url('" . $site_images['background_main'] . "')",
];

// Effectuer les remplacements
foreach ($replacements as $search => $replace) {
    $html_content = str_replace($search, $replace, $html_content);
}

// Vérifier si le remplacement a été effectué
$pattern = "/background-image: url\('([^']+)'\)/";
preg_match($pattern, $html_content, $matches);

echo "<h2>Vérification dans le contenu HTML complet</h2>";
if (isset($matches[1])) {
    echo "<p>URL de l'image trouvée dans le HTML: " . htmlspecialchars($matches[1]) . "</p>";
} else {
    echo "<p>Aucune URL d'image trouvée dans le HTML.</p>";
}

// Afficher l'image pour vérifier qu'elle est accessible
echo "<h2>Test d'affichage de l'image</h2>";
echo "<p>Voici l'image background_main:</p>";
echo "<img src='" . htmlspecialchars($site_images['background_main']) . "' style='max-width: 300px; border: 1px solid #ccc;'>";

// Vérifier les permissions du fichier
if (file_exists($site_images['background_main'])) {
    $perms = fileperms($site_images['background_main']);
    $perms_str = sprintf('%o', $perms);
    echo "<p>Permissions du fichier: " . $perms_str . "</p>";
}

// Afficher un lien vers index.php pour tester
echo "<p><a href='index.php' target='_blank'>Ouvrir index.php dans un nouvel onglet</a></p>";
?>
