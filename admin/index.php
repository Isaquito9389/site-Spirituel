<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

// Include database connection and authentication functions
require_once 'includes/db_connect.php';
require_once 'includes/auth_functions.php';
require_once 'includes/setup_database.php';

// Create default admin user if no users exist
create_default_admin();

// Process login form submission
$error_message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";
    
    // Authenticate user
    $auth_result = authenticate_user($username, $password);
    
    if ($auth_result['status']) {
        // Set session variables
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_user_id'] = $auth_result['user']['id'];
        $_SESSION['admin_user_role'] = $auth_result['user']['role'];
        
        // Redirect to dashboard
        header("Location: dashboard.php");
        exit;
    } else {
        $error_message = $auth_result['message'];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Mystica Occulta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    </style>
</head>
<body class="bg-dark min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-gradient-to-br from-purple-900 to-dark rounded-xl shadow-2xl overflow-hidden border border-purple-800 p-8">
        <div class="text-center mb-8">
            <div class="flex items-center justify-center space-x-2 mb-4">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-900 to-pink-600 flex items-center justify-center">
                    <i class="fas fa-eye text-white text-xl"></i>
                </div>
                <h1 class="font-cinzel text-2xl font-bold bg-gradient-to-r from-purple-400 to-pink-600 bg-clip-text text-transparent">MYSTICA OCCULTA</h1>
            </div>
            <h2 class="font-cinzel text-2xl font-bold text-white">Administration</h2>
            <div class="w-24 h-1 bg-gradient-to-r from-purple-500 to-pink-500 mx-auto mt-4"></div>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-900 bg-opacity-50 text-white p-4 rounded-lg mb-6">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-6">
            <div>
                <label for="username" class="block text-gray-300 mb-2">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" required class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="Entrez votre nom d'utilisateur">
            </div>
            
            <div>
                <label for="password" class="block text-gray-300 mb-2">Mot de passe</label>
                <input type="password" id="password" name="password" required class="w-full px-4 py-3 rounded-lg bg-dark border border-purple-800 focus:border-pink-500 focus:outline-none text-white transition duration-300" placeholder="Entrez votre mot de passe">
            </div>
            
            <button type="submit" class="w-full btn-magic px-6 py-4 rounded-full text-white font-bold text-lg">
                Se connecter <i class="fas fa-sign-in-alt ml-2"></i>
            </button>
        </form>
        
        <div class="mt-8 text-center">
            <a href="../index.html" class="text-gray-400 hover:text-pink-500 transition duration-300">
                <i class="fas fa-arrow-left mr-2"></i> Retour au site
            </a>
        </div>
    </div>
</body>
</html>
