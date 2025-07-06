// JavaScript complet pour la bibliothèque de vidéos

// Variables globales
const isPopup = window.opener && window.opener !== window;
let selectedVideoPath = null;
let selectedVideoType = null;

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    initializeUpload();
    initializeSearch();

    if (isPopup) {
        addPopupInfo();
    }
});

// Fonctions d'upload améliorées
function initializeUpload() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('video');
    const videoPreview = document.getElementById('videoPreview');
    const previewName = document.getElementById('previewName');
    const previewSize = document.getElementById('previewSize');
    const removePreview = document.getElementById('removePreview');

    // Drag & Drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const file = files[0];
            if (file.type.startsWith('video/')) {
                fileInput.files = files;
                showPreview(file);
            } else {
                showNotification('Veuillez sélectionner un fichier vidéo valide.', 'error');
            }
        }
    });

    // File input change
    fileInput.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            showPreview(e.target.files[0]);
        }
    });

    // Remove preview
    removePreview.addEventListener('click', function() {
        fileInput.value = '';
        videoPreview.classList.add('hidden');
        document.getElementById('video_url').value = '';
    });

    function showPreview(file) {
        if (file.size > 100 * 1024 * 1024) {
            showNotification('La vidéo est trop volumineuse. La taille maximum est de 100 Mo.', 'error');
            fileInput.value = '';
            return;
        }

        previewName.textContent = file.name;
        previewSize.textContent = formatFileSize(file.size);
        videoPreview.classList.remove('hidden');

        // Clear URL input when file is selected
        document.getElementById('video_url').value = '';
    }
}

// Fonction de recherche
function initializeSearch() {
    const searchInput = document.getElementById('searchVideos');
    const videoGrid = document.getElementById('videoGrid');
    const videoItems = videoGrid.querySelectorAll('.video-item');

    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();

        videoItems.forEach(item => {
            const videoName = item.dataset.name;
            if (videoName.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });
}

// Sélection de vidéo améliorée
function selectVideo(path, type, button) {
    // Gérer la sélection visuelle
    document.querySelectorAll('.video-item').forEach(item => {
        item.classList.remove('selected');
    });

    const videoItem = button.closest('.video-item');
    videoItem.classList.add('selected');
    selectedVideoPath = path;
    selectedVideoType = type;

    if (isPopup && window.opener) {
        // Mode popup - envoyer à la fenêtre parente
        window.opener.postMessage({
            type: 'videoSelected',
            videoPath: path,
            videoType: type
        }, window.location.origin);

        showNotification('Vidéo sélectionnée! Fermeture en cours...', 'success');
        setTimeout(() => {
            window.close();
        }, 1000);
    } else {
        // Mode normal - afficher confirmation
        showNotification('Vidéo sélectionnée: ' + path, 'success');

        // Mettre à jour un champ caché si disponible
        const hiddenField = document.getElementById('selected_video_path');
        if (hiddenField) {
            hiddenField.value = path;
        }
        const hiddenTypeField = document.getElementById('selected_video_type');
        if (hiddenTypeField) {
            hiddenTypeField.value = type;
        }
    }
}

// Copier le chemin de vidéo
function copyVideoPath(path) {
    navigator.clipboard.writeText(path).then(function() {
        showNotification('Chemin copié dans le presse-papiers!', 'success');
    }).catch(function(err) {
        // Fallback pour les navigateurs qui ne supportent pas clipboard API
        const textArea = document.createElement('textarea');
        textArea.value = path;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showNotification('Chemin copié dans le presse-papiers!', 'success');
    });
}

// Actualiser la galerie
function refreshGallery() {
    location.reload();
}

// Afficher les notifications
function showNotification(message, type) {
    // Supprimer les notifications existantes
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notif => notif.remove());

    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} mr-2"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;

    document.body.appendChild(notification);

    // Afficher la notification
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);

    // Masquer automatiquement après 5 secondes
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 300);
    }, 5000);
}

// Formater la taille de fichier
function formatFileSize(bytes) {
    if (bytes >= 1073741824) {
        return (bytes / 1073741824).toFixed(2) + ' GB';
    } else if (bytes >= 1048576) {
        return (bytes / 1048576).toFixed(2) + ' MB';
    } else if (bytes >= 1024) {
        return (bytes / 1024).toFixed(2) + ' KB';
    } else {
        return bytes + ' bytes';
    }
}

// Ajouter des informations pour le mode popup
function addPopupInfo() {
    const header = document.querySelector('main h1');
    if (header) {
        const selectionInfo = document.createElement('div');
        selectionInfo.className = 'mt-2 p-3 bg-yellow-900 border border-yellow-700 rounded-lg';
        selectionInfo.innerHTML = `
            <div class="flex items-center text-yellow-200">
                <i class="fas fa-info-circle mr-2"></i>
                <span>Mode sélection : cliquez sur le bouton de sélection d'une vidéo pour la choisir</span>
            </div>
        `;
        header.insertAdjacentElement('afterend', selectionInfo);
    }
}

// Gérer l'envoi du formulaire
document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.getElementById('uploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('video');
            const urlInput = document.getElementById('video_url');
            const submitBtn = document.getElementById('submitBtn');

            if (!fileInput.files.length && !urlInput.value.trim()) {
                e.preventDefault();
                showNotification('Veuillez sélectionner une vidéo à uploader ou fournir une URL de vidéo.', 'error');
                return;
            }

            // Désactiver le bouton de soumission pour éviter les doublons
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Upload en cours...';

            // Réactiver le bouton après un délai (au cas où l'upload échoue)
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-upload mr-2"></i> Ajouter à la bibliothèque';
            }, 10000);
        });
    }
});

// Validation en temps réel de l'URL
document.addEventListener('DOMContentLoaded', function() {
    const videoUrlInput = document.getElementById('video_url');
    if (videoUrlInput) {
        videoUrlInput.addEventListener('input', function(e) {
            const url = e.target.value.trim();
            const fileInput = document.getElementById('video');

            if (url) {
                // Clear file input when URL is entered
                fileInput.value = '';
                document.getElementById('videoPreview').classList.add('hidden');

                // Simple validation de l'URL
                try {
                    new URL(url);
                    e.target.classList.remove('border-red-500');
                    e.target.classList.add('border-green-500');
                } catch {
                    e.target.classList.remove('border-green-500');
                    e.target.classList.add('border-red-500');
                }
            } else {
                e.target.classList.remove('border-red-500', 'border-green-500');
            }
        });
    }
});

// Fonction pour supprimer une vidéo de manière sécurisée
function deleteVideo(videoId, videoName) {
    if (confirm('Êtes-vous sûr de vouloir supprimer la vidéo "' + videoName + '" ?')) {
        // Créer un formulaire caché pour la suppression
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'video_library.php';
        form.style.display = 'none';

        // Ajouter les champs nécessaires
        const actionField = document.createElement('input');
        actionField.type = 'hidden';
        actionField.name = 'action';
        actionField.value = 'delete';
        form.appendChild(actionField);

        const idField = document.createElement('input');
        idField.type = 'hidden';
        idField.name = 'id';
        idField.value = videoId;
        form.appendChild(idField);

        const csrfField = document.createElement('input');
        csrfField.type = 'hidden';
        csrfField.name = 'csrf_token';
        csrfField.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        form.appendChild(csrfField);

        // Ajouter le formulaire au DOM et le soumettre
        document.body.appendChild(form);
        form.submit();
    }
}

// Écouter les messages de la fenêtre parente (si applicable)
if (isPopup) {
    window.addEventListener('message', function(event) {
        if (event.origin !== window.location.origin) return;

        if (event.data.type === 'requestSelectedVideo') {
            if (selectedVideoPath) {
                event.source.postMessage({
                    type: 'videoSelected',
                    videoPath: selectedVideoPath,
                    videoType: selectedVideoType
                }, event.origin);
            }
        }
    });
}
