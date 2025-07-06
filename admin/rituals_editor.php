<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
// Fichier : rituals_editor.php
// Rôle : Éditeur de contenu avancé, avec l'interface personnalisée et la sauvegarde fiable.

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

require_once 'includes/db_connect.php';

if (!isset($pdo)) {
    die("Erreur critique : la connexion à la base de données a échoué.");
}

$ritual_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($ritual_id <= 0) {
    header("Location: rituals.php?message=" . urlencode("ID de rituel manquant ou invalide.") . "&type=error");
    exit;
}

$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_content'])) {
    $content = $_POST['content'] ?? '';
    $id_to_save = $_POST['ritual_id'] ?? 0;

    if ($id_to_save == $ritual_id) {
        try {
            $stmt = $pdo->prepare("UPDATE rituals SET content = :content, updated_at = NOW() WHERE id = :id");
            $stmt->execute(['content' => $content, 'id' => $ritual_id]);
            header("Location: rituals.php?action=edit&id=" . $ritual_id . "&message=" . urlencode("Contenu sauvegardé avec succès !"));
            exit;
        } catch (PDOException $e) {
            $error_message = "Erreur de base de données : " . $e->getMessage();
        }
    } else {
        $error_message = "Erreur de correspondance d'ID. Sauvegarde annulée.";
    }
}

try {
    $stmt = $pdo->prepare("SELECT id, title, content FROM rituals WHERE id = ?");
    $stmt->execute([$ritual_id]);
    $ritual = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ritual) {
        header("Location: rituals.php?message=" . urlencode("Rituel non trouvé.") . "&type=error");
        exit;
    }
} catch (PDOException $e) {
    die("Erreur de base de données lors de la récupération du rituel : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Éditeur: <?php echo htmlspecialchars($ritual['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700&family=Merriweather&display=swap');
        body { font-family: 'Merriweather', serif; background-color: #0f0e17; color: #e8e8e8; }
        .font-cinzel { font-family: 'Cinzel Decorative', cursive; }

        /* Styles pour la barre d'outils personnalisée */
        .editor-toolbar {
            background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
            border: 1px solid #3a0ca3;
            border-bottom: none;
            padding: 0.75rem;
        }
        .toolbar-btn, .toolbar-select {
            background-color: #3a0ca3;
            border: 1px solid #7209b7;
            color: #e8e8e8;
            transition: all 0.2s ease;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem; /* rounded-md */
        }
        .toolbar-btn:hover, .toolbar-select:hover {
            background-color: #7209b7;
            border-color: #f72585;
        }
        .toolbar-btn.active {
            background-color: #f72585;
            box-shadow: 0 0 10px rgba(247, 37, 133, 0.5);
        }

        /* Styles pour la zone d'édition */
        #editor {
            background: #0f0e17;
            border: 1px solid #3a0ca3;
            min-height: 700px;
            padding: 1.5rem;
            border-radius: 0 0 0.5rem 0.5rem; /* rounded-b-lg */
            outline: none;
            transition: border-color 0.2s ease;
            
            /* --- LE CSS QUI AMÉLIORE LE CONTENU --- */
            font-size: 18px;
            line-height: 1.7;
            color: #e0e0e0;
        }
        #editor:focus {
            border-color: #f72585;
        }
    </style>
</head>
<body class="min-h-screen">
    <header class="bg-dark bg-opacity-90 backdrop-blur-sm border-b border-purple-900 py-3 sticky top-0 z-20">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <a href="dashboard.php" class="flex items-center space-x-2">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-900 to-pink-600 flex items-center justify-center"><i class="fas fa-moon text-white text-xl"></i></div>
                <span class="font-cinzel text-2xl font-bold bg-gradient-to-r from-purple-400 to-pink-600 bg-clip-text text-transparent">MYSTICA OCCULTA</span>
            </a>
        </div>
    </header>

    <div class="container mx-auto px-4 py-8 max-w-5xl">
        <div class="flex justify-between items-center mb-6">
            <h1 class="font-cinzel text-3xl font-bold text-white">
                <i class="fas fa-feather-alt mr-3 text-green-400"></i>Éditeur de Contenu
            </h1>
            <a href="rituals.php?action=edit&id=<?php echo $ritual['id']; ?>" class="px-4 py-2 rounded-lg border border-purple-600 text-white inline-flex items-center hover:bg-purple-900 transition"><i class="fas fa-arrow-left mr-2"></i>Retour au rituel</a>
        </div>
        
        <div class="text-lg text-gray-300 mb-6">
            Modification du rituel : <strong class="text-white"><?php echo htmlspecialchars($ritual['title']); ?></strong>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="mb-6 p-4 rounded-lg bg-red-500/20 text-red-300 border border-red-500/30"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form id="editorForm" method="POST" action="rituals_editor.php?id=<?php echo $ritual['id']; ?>">
            <input type="hidden" name="ritual_id" value="<?php echo $ritual['id']; ?>">
            <textarea name="content" id="hiddenContent" class="hidden"></textarea>

            <div class="editor-toolbar flex flex-wrap gap-2 items-center">
                <select class="toolbar-select" onchange="formatText('formatBlock', this.value)">
                    <option value="p">Paragraphe</option><option value="h2">Titre 2</option><option value="h3">Titre 3</option><option value="h4">Titre 4</option>
                </select>
                <button type="button" class="toolbar-btn" onclick="formatText('bold')" title="Gras"><i class="fas fa-bold"></i></button>
                <button type="button" class="toolbar-btn" onclick="formatText('italic')" title="Italique"><i class="fas fa-italic"></i></button>
                <button type="button" class="toolbar-btn" onclick="formatText('underline')" title="Souligné"><i class="fas fa-underline"></i></button>
                <button type="button" class="toolbar-btn" onclick="formatText('justifyLeft')" title="Aligner à gauche"><i class="fas fa-align-left"></i></button>
                <button type="button" class="toolbar-btn" onclick="formatText('justifyCenter')" title="Centrer"><i class="fas fa-align-center"></i></button>
                <button type="button" class="toolbar-btn" onclick="formatText('justifyRight')" title="Aligner à droite"><i class="fas fa-align-right"></i></button>
                <button type="button" class="toolbar-btn" onclick="formatText('insertUnorderedList')" title="Liste à puces"><i class="fas fa-list-ul"></i></button>
                <button type="button" class="toolbar-btn" onclick="formatText('insertOrderedList')" title="Liste numérotée"><i class="fas fa-list-ol"></i></button>
                <button type="button" class="toolbar-btn" onclick="formatText('formatBlock', 'blockquote')" title="Citation"><i class="fas fa-quote-left"></i></button>
                <button type="button" class="toolbar-btn" onclick="createLink()" title="Créer un lien"><i class="fas fa-link"></i></button>
            </div>

            <div id="editor" contenteditable="true">
                <?php echo $ritual['content'] ?? '<p>Commencez à écrire votre texte ici...</p>'; ?>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" name="save_content" class="bg-purple-600 hover:bg-purple-700 px-8 py-3 rounded-lg text-white font-semibold shadow-lg hover:shadow-purple-500/30 transition text-lg">
                    <i class="fas fa-save mr-2"></i>Sauvegarder le Contenu
                </button>
            </div>
        </form>
    </div>

    <script>
        // Le JavaScript pour faire fonctionner la barre d'outils
        function formatText(command, value = null) {
            document.execCommand(command, false, value);
            document.getElementById('editor').focus();
        }

        function createLink() {
            const url = prompt("Entrez l'URL du lien :");
            if (url) {
                formatText('createLink', url);
            }
        }

        // Le "pont" entre l'éditeur visuel et le formulaire
        document.getElementById('editorForm').addEventListener('submit', function() {
            const editorContent = document.getElementById('editor').innerHTML;
            document.getElementById('hiddenContent').value = editorContent;
        });

        // Mettre à jour l'état des boutons (ex: 'gras' est actif)
        function updateToolbarStates() {
            const commands = ['bold', 'italic', 'underline'];
            commands.forEach(command => {
                const button = document.querySelector(`[onclick="formatText('${command}')"]`);
                if (button) {
                    if (document.queryCommandState(command)) {
                        button.classList.add('active');
                    } else {
                        button.classList.remove('active');
                    }
                }
            });
        }
        document.getElementById('editor').addEventListener('keyup', updateToolbarStates);
        document.getElementById('editor').addEventListener('mouseup', updateToolbarStates);
        document.addEventListener('selectionchange', updateToolbarStates);

    </script>
</body>
</html>