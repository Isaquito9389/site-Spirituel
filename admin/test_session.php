<?php
session_start();

echo "<h1>Test de session d'administration</h1>";

echo "<h2>Informations de session</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "Session admin_logged_in: " . (isset($_SESSION['admin_logged_in']) ? ($_SESSION['admin_logged_in'] ? 'TRUE' : 'FALSE') : 'NON DÉFINI') . "<br>";
echo "Session admin_username: " . (isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'NON DÉFINI') . "<br>";

echo "<h2>Toutes les variables de session</h2>";
if (empty($_SESSION)) {
    echo "Aucune variable de session définie.<br>";
} else {
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
}

echo "<h2>Test de connexion rapide</h2>";
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo "<p style='color: red;'>Vous n'êtes pas connecté en tant qu'administrateur.</p>";
    echo "<p>Cela pourrait expliquer pourquoi l'upload ne fonctionne pas.</p>";
    echo "<a href='index.php'>Se connecter</a>";
} else {
    echo "<p style='color: green;'>Vous êtes connecté en tant qu'administrateur.</p>";
    echo "<a href='image_library.php'>Aller à la bibliothèque d'images</a>";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h2 { color: #333; }
</style>
