# Instructions pour résoudre le problème d'upload d'images sur Hostinger

## Étapes de diagnostic à suivre

### 1. Mettre à jour la configuration de base de données

**IMPORTANT** : Vous devez d'abord mettre à jour le fichier `config.php` avec vos vraies informations de base de données Hostinger.

Dans le fichier `config.php`, remplacez ces lignes :
```php
if (!defined('DB_HOST')) define('DB_HOST', 'localhost'); // Généralement 'localhost' pour Hostinger
if (!defined('DB_NAME')) define('DB_NAME', 'VOTRE_NOM_DE_BASE_HOSTINGER'); // Remplacez par votre nom de base
if (!defined('DB_USER')) define('DB_USER', 'VOTRE_UTILISATEUR_HOSTINGER'); // Remplacez par votre utilisateur
if (!defined('DB_PASS')) define('DB_PASS', 'VOTRE_MOT_DE_PASSE_HOSTINGER'); // Remplacez par votre mot de passe
```

Par vos vraies informations Hostinger que vous pouvez trouver dans :
- **hPanel Hostinger** > **Bases de données** > **Gérer**

### 2. Tester la session d'administration

Accédez à : `https://votre-domaine.com/admin/test_session.php`

Ce script vous dira si vous êtes bien connecté en tant qu'administrateur. Si vous n'êtes pas connecté, cela explique pourquoi l'upload ne fonctionne pas.

### 3. Exécuter le diagnostic complet

Accédez à : `https://votre-domaine.com/admin/test_upload_debug.php`

Ce script va tester :
- ✅ Les extensions PHP nécessaires
- ✅ Les permissions des dossiers
- ✅ La connexion à la base de données
- ✅ L'upload d'un fichier test
- ✅ Les images existantes

### 4. Analyser les résultats

#### Si la connexion à la base de données échoue :
- Vérifiez vos informations de connexion dans `config.php`
- Contactez le support Hostinger si nécessaire

#### Si les permissions de dossier échouent :
- Le dossier `uploads/images/` doit exister et être accessible en écriture
- Permissions recommandées : 755 ou 775

#### Si l'upload de fichier échoue :
- Vérifiez les limites PHP (upload_max_filesize, post_max_size)
- Vérifiez l'espace disque disponible

### 5. Problèmes courants et solutions

#### Problème : "Vous n'êtes pas connecté en tant qu'administrateur"
**Solution** : Connectez-vous via `admin/index.php` avant d'essayer d'uploader

#### Problème : "Connexion à la base de données échouée"
**Solution** : Mettez à jour `config.php` avec les bonnes informations Hostinger

#### Problème : "Dossier uploads/images non accessible en écriture"
**Solution** : 
1. Créez le dossier s'il n'existe pas
2. Changez les permissions à 755 ou 775
3. Via FTP ou gestionnaire de fichiers Hostinger

#### Problème : "Upload réussi mais image non visible dans la galerie"
**Solution** : Problème de base de données - vérifiez que la table `image_library` existe

### 6. Commandes utiles pour Hostinger

Si vous avez accès SSH :
```bash
# Vérifier les permissions
ls -la uploads/images/

# Changer les permissions
chmod 755 uploads/images/

# Créer le dossier s'il n'existe pas
mkdir -p uploads/images/
```

### 7. Vérification finale

Une fois tout configuré :
1. Connectez-vous à l'admin : `admin/index.php`
2. Allez à la bibliothèque d'images : `admin/image_library.php`
3. Essayez d'uploader une image
4. Vérifiez que l'image apparaît dans la galerie

## Informations de contact Hostinger

Si vous avez besoin d'aide avec la configuration :
- **Support Hostinger** : Via hPanel > Support
- **Documentation** : https://support.hostinger.com/

## Nettoyage après diagnostic

Une fois le problème résolu, vous pouvez supprimer les fichiers de test :
- `admin/test_upload_debug.php`
- `admin/test_session.php`
- `INSTRUCTIONS_HOSTINGER.md`
- `GUIDE_RESOLUTION_UPLOAD.md`
