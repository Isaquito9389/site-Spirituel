<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Bouton Backlinks</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center">
    <div class="max-w-md mx-auto p-8 bg-gray-800 rounded-lg">
        <h1 class="text-2xl font-bold mb-6 text-center">Test du Bouton Backlinks</h1>
        
        <?php if (isset($_GET['setup']) && $_GET['setup'] === 'true'): ?>
            <div class="bg-green-500/20 text-green-300 border border-green-500/30 p-4 rounded-lg mb-4">
                <h3 class="font-bold mb-2"><i class="fas fa-check-circle mr-2"></i>Bouton fonctionnel !</h3>
                <p>Le bouton a bien redirigé vers cette page avec le paramètre setup=true</p>
            </div>
            <div class="text-center">
                <a href="test_backlinks_button.php" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg text-white font-medium">
                    <i class="fas fa-arrow-left mr-2"></i>Retour au test
                </a>
            </div>
        <?php else: ?>
            <p class="text-gray-300 mb-4 text-center">Cliquez sur le bouton pour tester la redirection :</p>
            <div class="text-center">
                <a href="test_backlinks_button.php?setup=true" class="bg-purple-600 hover:bg-purple-700 px-6 py-3 rounded-lg text-white font-medium inline-block">
                    <i class="fas fa-play mr-2"></i>Tester le bouton
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
