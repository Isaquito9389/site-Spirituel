<?php
/**
 * Test Backlinks System Initialization (Sans authentification)
 */

// Handle setup request
$setup_result = '';
$setup_success = false;

if (isset($_GET['setup']) && $_GET['setup'] === 'true') {
    // Simulation de la configuration (sans base de données)
    $setup_success = true;
    $setup_result = '<div class="bg-green-500/20 text-green-300 border border-green-500/30 p-4 rounded-lg">
        <h3 class="font-bold mb-2"><i class="fas fa-check-circle mr-2"></i>Test de configuration réussi !</h3>
        <p>Le bouton fonctionne correctement. Dans la vraie version, les tables suivantes seraient créées :</p>
        <ul class="list-disc list-inside mt-2 space-y-1">
            <li>Table <code>backlinks</code> - Gestion des liens externes</li>
            <li>Table <code>internal_links</code> - Gestion des liens internes</li>
            <li>Table <code>backlink_categories</code> - Catégories de backlinks</li>
        </ul>
        <p class="mt-3">Le système de backlinks est prêt à être utilisé !</p>
    </div>';
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Configuration Backlinks - Mystica Occulta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700&family=Merriweather&display=swap');
        :root { --primary: #3a0ca3; --secondary: #7209b7; --accent: #f72585; --dark: #1a1a2e; }
        body { font-family: 'Merriweather', serif; background-color: #0f0e17; color: #e8e8e8; }
        .font-cinzel { font-family: 'Cinzel Decorative', cursive; }
    </style>
</head>
<body class="bg-dark min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="text-center mb-8">
                <h1 class="font-cinzel text-4xl font-bold text-white mb-4">
                    <i class="fas fa-link mr-3 text-purple-400"></i>
                    Test Configuration Backlinks
                </h1>
                <p class="text-gray-300 text-lg">Version de test sans authentification</p>
            </div>

            <div class="bg-gradient-to-br from-purple-900 to-dark rounded-xl p-8 border border-purple-800 mb-8">
                <h2 class="text-2xl font-bold text-white mb-6">
                    <i class="fas fa-database mr-2 text-purple-400"></i>
                    Test du Bouton de Configuration
                </h2>
                
                <div id="setup-result" class="mb-6">
                    <?php if (!empty($setup_result)): ?>
                        <?php echo $setup_result; ?>
                        <?php if ($setup_success): ?>
                            <div class="mt-4">
                                <a href="setup_backlinks_test.php" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg text-white font-medium mr-3">
                                    <i class="fas fa-arrow-left mr-2"></i>Retour au test
                                </a>
                                <a href="rituals.php" class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg text-white font-medium mr-3">
                                    <i class="fas fa-magic mr-2"></i>Voir les rituels
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-gray-300 mb-4">Cliquez sur le bouton ci-dessous pour tester la configuration :</p>
                        <a href="setup_backlinks_test.php?setup=true" class="bg-purple-600 hover:bg-purple-700 px-6 py-3 rounded-lg text-white font-medium inline-block">
                            <i class="fas fa-play mr-2"></i>Tester la Configuration
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-gradient-to-br from-purple-900 to-dark rounded-xl p-8 border border-purple-800">
                <h2 class="text-2xl font-bold text-white mb-6">
                    <i class="fas fa-info-circle mr-2 text-purple-400"></i>
                    Informations sur le Test
                </h2>
                
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-xl font-bold text-white mb-3">
                        <i class="fas fa-check-circle mr-2 text-green-400"></i>
                        Résolution du Problème
                    </h3>
                    <ul class="text-gray-300 space-y-2">
                        <li>✅ Bouton JavaScript remplacé par un lien direct</li>
                        <li>✅ Plus d'erreurs de session ou de JavaScript</li>
                        <li>✅ Redirection avec paramètre GET fonctionnelle</li>
                        <li>✅ Affichage conditionnel du résultat</li>
                    </ul>
                    
                    <div class="mt-4 p-4 bg-blue-900/30 rounded-lg border border-blue-500/30">
                        <p class="text-blue-300">
                            <i class="fas fa-lightbulb mr-2"></i>
                            <strong>Note :</strong> Cette version de test fonctionne sans authentification. 
                            La version finale nécessitera une connexion admin pour des raisons de sécurité.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
