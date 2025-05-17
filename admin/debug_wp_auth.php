<?php
// Affichage forcé des erreurs PHP pour le debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include WordPress API functions
require_once 'includes/db_connect.php';
require_once 'includes/wp_api_connect.php';

// Style de base pour une meilleure lisibilité
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
    h1, h2 { color: #333; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .container { max-width: 1200px; margin: 0 auto; }
</style>';

echo '<div class="container">';
echo '<h1>Diagnostic de l\'authentification WordPress</h1>';

// Afficher les informations de configuration
echo '<h2>Configuration actuelle</h2>';
echo '<ul>';
echo '<li>URL de base de l\'API: ' . htmlspecialchars($wp_api_base_url) . '</li>';
echo '<li>Utilisateur WordPress: ' . htmlspecialchars($wp_user) . '</li>';
echo '<li>Mot de passe d\'application (masqué): ' . str_repeat('*', 5) . '</li>';
echo '</ul>';

// Tester la génération du token d'authentification
echo '<h2>Test du token d\'authentification</h2>';
$auth_token = get_wp_api_token();
if ($auth_token === false) {
    echo '<p class="error">Échec de la génération du token d\'authentification. Vérifiez vos identifiants.</p>';
} else {
    echo '<p class="success">Token d\'authentification généré avec succès.</p>';
    
    // Afficher le token partiellement masqué pour des raisons de sécurité
    $masked_token = substr($auth_token, 0, 10) . '...' . substr($auth_token, -10);
    echo '<p>Token (partiellement masqué): ' . htmlspecialchars($masked_token) . '</p>';
    
    // Décoder le token pour vérifier le format
    $decoded = base64_decode($auth_token);
    $parts = explode(':', $decoded);
    if (count($parts) === 2) {
        echo '<p class="success">Format du token correct: "username:password"</p>';
        echo '<p>Nom d\'utilisateur dans le token: ' . htmlspecialchars($parts[0]) . '</p>';
        echo '<p>Longueur du mot de passe dans le token: ' . strlen($parts[1]) . ' caractères</p>';
    } else {
        echo '<p class="error">Format du token incorrect. Le format attendu est "username:password"</p>';
    }
}

// Tester une requête GET simple (devrait fonctionner même avec des permissions limitées)
echo '<h2>Test de requête GET (lecture)</h2>';
$test_get = get_from_wordpress('posts', ['per_page' => 1]);
if ($test_get['success']) {
    echo '<p class="success">Requête GET réussie. Vous avez les permissions de lecture.</p>';
    if (isset($test_get['data']) && is_array($test_get['data']) && !empty($test_get['data'])) {
        echo '<p>Premier article récupéré: "' . htmlspecialchars($test_get['data'][0]['title']['rendered'] ?? 'Titre non disponible') . '"</p>';
    }
} else {
    echo '<p class="error">Échec de la requête GET. Erreur: ' . htmlspecialchars(print_r($test_get['error'], true)) . '</p>';
    echo '<p>Code HTTP: ' . ($test_get['status'] ?? 'N/A') . '</p>';
}

// Tester les permissions de l'utilisateur
echo '<h2>Test des permissions utilisateur</h2>';
$user_test = get_from_wordpress('users/me');
if ($user_test['success']) {
    echo '<p class="success">Récupération des informations utilisateur réussie.</p>';
    if (isset($user_test['data'])) {
        echo '<p>Nom d\'utilisateur: ' . htmlspecialchars($user_test['data']['name'] ?? 'N/A') . '</p>';
        echo '<p>Rôles: ' . htmlspecialchars(implode(', ', $user_test['data']['roles'] ?? ['N/A'])) . '</p>';
        
        // Vérifier si l'utilisateur a les droits d'administrateur
        $is_admin = in_array('administrator', $user_test['data']['roles'] ?? []);
        if ($is_admin) {
            echo '<p class="success">L\'utilisateur a les droits d\'administrateur, ce qui devrait être suffisant pour toutes les opérations.</p>';
        } else {
            echo '<p class="error">L\'utilisateur n\'a PAS les droits d\'administrateur. Cela peut limiter certaines opérations.</p>';
        }
        
        // Afficher les capacités de l'utilisateur si disponibles
        if (isset($user_test['data']['capabilities']) && is_array($user_test['data']['capabilities'])) {
            echo '<p>Capacités importantes:</p>';
            echo '<ul>';
            $key_capabilities = ['edit_posts', 'publish_posts', 'edit_published_posts', 'upload_files', 'edit_others_posts'];
            foreach ($key_capabilities as $cap) {
                $has_cap = isset($user_test['data']['capabilities'][$cap]) && $user_test['data']['capabilities'][$cap];
                $class = $has_cap ? 'success' : 'error';
                echo '<li class="' . $class . '">' . $cap . ': ' . ($has_cap ? 'Oui' : 'Non') . '</li>';
            }
            echo '</ul>';
        }
    }
} else {
    echo '<p class="error">Échec de la récupération des informations utilisateur. Erreur: ' . htmlspecialchars(print_r($user_test['error'], true)) . '</p>';
    echo '<p>Code HTTP: ' . ($user_test['status'] ?? 'N/A') . '</p>';
}

// Tester une requête POST simple (création d'un brouillon)
echo '<h2>Test de requête POST (écriture)</h2>';
$test_data = [
    'title' => 'Test d\'authentification - ' . date('Y-m-d H:i:s'),
    'content' => 'Ceci est un test pour vérifier les permissions d\'écriture.',
    'status' => 'draft' // Créer comme brouillon pour ne pas polluer le site
];
$test_post = send_to_wordpress('posts', $test_data);

if ($test_post['success']) {
    echo '<p class="success">Requête POST réussie. Vous avez les permissions d\'écriture.</p>';
    echo '<p>ID de l\'article créé: ' . ($test_post['data']['id'] ?? 'N/A') . '</p>';
    
    // Si l'article a été créé, essayer de le supprimer pour nettoyer
    if (isset($test_post['data']['id'])) {
        $delete_test = send_to_wordpress('posts/' . $test_post['data']['id'], [], 'DELETE');
        if ($delete_test['success']) {
            echo '<p class="info">L\'article de test a été supprimé avec succès.</p>';
        } else {
            echo '<p class="error">Impossible de supprimer l\'article de test. Erreur: ' . htmlspecialchars(print_r($delete_test['error'], true)) . '</p>';
        }
    }
} else {
    echo '<p class="error">Échec de la requête POST. Erreur: ' . htmlspecialchars(print_r($test_post['error'], true)) . '</p>';
    echo '<p>Code HTTP: ' . ($test_post['status'] ?? 'N/A') . '</p>';
    echo '<p>Méthode: ' . ($test_post['method'] ?? 'N/A') . '</p>';
    
    // Suggestions basées sur le code d'erreur
    if ($test_post['status'] == 401) {
        echo '<div class="info">';
        echo '<h3>Suggestions pour résoudre l\'erreur 401 (Non autorisé):</h3>';
        echo '<ol>';
        echo '<li>Vérifiez que le mot de passe d\'application est correct et n\'a pas expiré.</li>';
        echo '<li>Assurez-vous que le mot de passe d\'application a été créé avec les permissions suffisantes.</li>';
        echo '<li>Essayez de créer un nouveau mot de passe d\'application dans WordPress.</li>';
        echo '<li>Vérifiez que l\'utilisateur a les droits nécessaires pour créer des articles.</li>';
        echo '</ol>';
        echo '</div>';
    } elseif ($test_post['status'] == 403) {
        echo '<div class="info">';
        echo '<h3>Suggestions pour résoudre l\'erreur 403 (Interdit):</h3>';
        echo '<ol>';
        echo '<li>L\'utilisateur n\'a pas les permissions nécessaires pour créer des articles.</li>';
        echo '<li>Utilisez un compte avec des droits d\'administrateur.</li>';
        echo '<li>Vérifiez les paramètres de sécurité de WordPress qui pourraient bloquer les requêtes API.</li>';
        echo '</ol>';
        echo '</div>';
    }
}

// Instructions pour créer un nouveau mot de passe d'application
echo '<h2>Comment créer un nouveau mot de passe d\'application dans WordPress</h2>';
echo '<ol>';
echo '<li>Connectez-vous à votre administration WordPress.</li>';
echo '<li>Allez dans "Profil" ou "Votre profil" dans le menu utilisateur.</li>';
echo '<li>Faites défiler jusqu\'à la section "Mots de passe d\'application".</li>';
echo '<li>Donnez un nom à votre application (ex: "API Mystica Occulta").</li>';
echo '<li>Cliquez sur "Ajouter un mot de passe d\'application".</li>';
echo '<li>WordPress générera un mot de passe. Copiez-le.</li>';
echo '<li>Mettez à jour le fichier wp_api_connect.php avec ce nouveau mot de passe.</li>';
echo '</ol>';

echo '<p><a href="test_wp_api.php">Retour au test de l\'API WordPress</a></p>';
echo '<p><a href="blog.php">Retour à la gestion du blog</a></p>';
echo '</div>';
?>
