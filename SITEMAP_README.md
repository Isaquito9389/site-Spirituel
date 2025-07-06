# 📍 Guide Complet du Sitemap - Site Spirituel

## 🎯 Vue d'ensemble

Ce système de sitemap professionnel a été conçu spécialement pour votre site spirituel. Il comprend :

- **sitemap.xml** : Sitemap statique de base
- **sitemap.php** : Sitemap dynamique généré en temps réel
- **update_sitemap.php** : Script de mise à jour automatique
- **robots.txt** : Configuration pour les moteurs de recherche
- **Configuration .htaccess** : Règles de redirection SEO

## 📁 Fichiers créés

### 1. `sitemap.xml`
Sitemap statique avec les pages principales et des exemples d'URLs dynamiques.

### 2. `sitemap.php`
Sitemap dynamique qui génère automatiquement les URLs depuis votre base de données :
- Pages principales (accueil, about, contact, etc.)
- Rituels publiés
- Articles de blog publiés
- Produits actifs
- Catégories de rituels

### 3. `update_sitemap.php`
Script de mise à jour qui :
- Génère un nouveau sitemap.xml
- Met à jour robots.txt avec la date
- Enregistre les logs dans `logs/sitemap_update.log`
- Affiche un rapport détaillé

### 4. `robots.txt`
Configuration complète pour :
- Autoriser l'indexation des pages importantes
- Bloquer les dossiers sensibles (admin, includes, logs)
- Référencer les sitemaps
- Optimiser pour les principaux moteurs de recherche

## ⚙️ Configuration initiale

### 1. Modifier l'URL du site

Dans **tous les fichiers**, remplacez `https://maitrespirituel.com` par votre vraie URL :

```php
// Dans sitemap.php et update_sitemap.php
$site_url = 'https://votre-vrai-domaine.com';
```

```xml
<!-- Dans sitemap.xml -->
<loc>https://votre-vrai-domaine.com/</loc>
```

```txt
# Dans robots.txt
Sitemap: https://votre-vrai-domaine.com/sitemap.xml
```

### 2. Vérifier la structure de base de données

Le système attend ces tables avec ces colonnes :

**Table `rituals`:**
- `slug` (VARCHAR)
- `status` (VARCHAR) - doit contenir 'published'
- `created_at` (DATETIME)
- `updated_at` (DATETIME)
- `category` (VARCHAR, optionnel)

**Table `blog_posts`:**
- `slug` (VARCHAR)
- `status` (VARCHAR) - doit contenir 'published'
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

**Table `products`:**
- `slug` (VARCHAR)
- `status` (VARCHAR) - doit contenir 'active'
- `created_at` (DATETIME)
- `updated_at` (DATETIME)

## 🚀 Utilisation

### Accès aux sitemaps

1. **Sitemap dynamique (recommandé)** : `https://maitrespirituel.com/sitemap.xml`
   - Redirige automatiquement vers sitemap.php
   - Toujours à jour avec la base de données

2. **Sitemap statique** : `https://maitrespirituel.com/sitemap.xml` (fichier direct)
   - Version fixe, nécessite mise à jour manuelle

### Mise à jour manuelle

Exécutez le script de mise à jour :

```bash
# Via ligne de commande
php update_sitemap.php

# Via navigateur
https://maitrespirituel.com/update_sitemap.php
```

### Automatisation avec Cron Job

Ajoutez cette ligne à votre crontab pour une mise à jour quotidienne :

```bash
# Mise à jour quotidienne à 2h du matin
0 2 * * * /usr/bin/php /chemin/vers/votre/site/update_sitemap.php

# Mise à jour hebdomadaire le dimanche à 3h
0 3 * * 0 /usr/bin/php /chemin/vers/votre/site/update_sitemap.php
```

## 📊 Structure du Sitemap

### Priorités définies

| Type de page | Priorité | Fréquence de mise à jour |
|--------------|----------|-------------------------|
| Page d'accueil | 1.0 | Hebdomadaire |
| Section Rituels | 0.9 | Hebdomadaire |
| About | 0.8 | Mensuelle |
| Blog | 0.8 | Quotidienne |
| Boutique/Produits | 0.8 | Hebdomadaire |
| Contact | 0.7 | Mensuelle |
| Rituels individuels | 0.7 | Mensuelle |
| Articles de blog | 0.6 | Mensuelle |
| Produits individuels | 0.6 | Hebdomadaire |
| Témoignages | 0.6 | Hebdomadaire |
| Catégories | 0.5 | Hebdomadaire |

### Format des URLs

Le sitemap génère ces types d'URLs :

```
https://maitrespirituel.com/                    (accueil)
https://maitrespirituel.com/about               (à propos)
https://maitrespirituel.com/ritual/slug-rituel  (rituels)
https://maitrespirituel.com/blog/slug-article   (blog)
https://maitrespirituel.com/product/slug-produit (produits)
https://maitrespirituel.com/rituals/category/nom-categorie (catégories)
```

## 🔧 Personnalisation

### Ajouter de nouvelles pages statiques

Dans `sitemap.php` et `update_sitemap.php`, modifiez le tableau `$static_pages` :

```php
$static_pages = [
    ['path' => 'about', 'priority' => '0.8', 'changefreq' => 'monthly'],
    ['path' => 'contact', 'priority' => '0.7', 'changefreq' => 'monthly'],
    // Ajoutez vos nouvelles pages ici
    ['path' => 'nouvelle-page', 'priority' => '0.6', 'changefreq' => 'weekly'],
];
```

### Modifier les priorités

Ajustez les valeurs de priorité selon l'importance de vos pages :
- **1.0** : Page la plus importante (accueil uniquement)
- **0.8-0.9** : Pages très importantes
- **0.6-0.7** : Pages importantes
- **0.4-0.5** : Pages secondaires
- **0.1-0.3** : Pages peu importantes

### Ajouter de nouveaux types de contenu

Pour ajouter un nouveau type de contenu (ex: événements) :

```php
// Dans sitemap.php et update_sitemap.php
$event_count = 0;
try {
    $stmt = $pdo->prepare("SELECT slug, updated_at, created_at FROM events WHERE status = 'published' ORDER BY created_at DESC");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($events as $event) {
        $lastmod = !empty($event['updated_at']) ? date('Y-m-d', strtotime($event['updated_at'])) : date('Y-m-d', strtotime($event['created_at']));
        echo "    <url>\n";
        echo "        <loc>" . formatUrl('event/' . xmlEscape($event['slug'])) . "</loc>\n";
        echo "        <lastmod>" . $lastmod . "</lastmod>\n";
        echo "        <changefreq>weekly</changefreq>\n";
        echo "        <priority>0.6</priority>\n";
        echo "    </url>\n\n";
        $event_count++;
    }
} catch (PDOException $e) {
    logMessage("Erreur événements: " . $e->getMessage());
}
```

## 📈 Soumission aux moteurs de recherche

### Google Search Console

1. Connectez-vous à [Google Search Console](https://search.google.com/search-console/)
2. Ajoutez votre site
3. Allez dans **Sitemaps**
4. Soumettez : `https://maitrespirituel.com/sitemap.xml`

### Bing Webmaster Tools

1. Connectez-vous à [Bing Webmaster Tools](https://www.bing.com/webmasters/)
2. Ajoutez votre site
3. Allez dans **Sitemaps**
4. Soumettez : `https://maitrespirituel.com/sitemap.xml`

### Autres moteurs

Le fichier robots.txt référence automatiquement votre sitemap pour tous les moteurs de recherche.

## 🔍 Vérification et tests

### Tester le sitemap

1. **Validation XML** : [XML Sitemap Validator](https://www.xml-sitemaps.com/validate-xml-sitemap.html)
2. **Test Google** : Google Search Console > Sitemaps
3. **Vérification manuelle** : Visitez `https://maitrespirituel.com/sitemap.xml`

### Vérifier robots.txt

Visitez : `https://maitrespirituel.com/robots.txt`

### Logs de débogage

Consultez les logs dans : `logs/sitemap_update.log`

## 🛠️ Dépannage

### Problèmes courants

**Erreur 500 sur sitemap.php :**
- Vérifiez la connexion à la base de données
- Consultez les logs d'erreur PHP
- Vérifiez les permissions de fichiers

**Sitemap vide :**
- Vérifiez que les tables existent
- Vérifiez les statuts ('published', 'active')
- Consultez `logs/sitemap_update.log`

**URLs incorrectes :**
- Vérifiez la variable `$site_url`
- Vérifiez les règles .htaccess
- Testez les URLs individuellement

### Support technique

Pour toute question technique :
1. Consultez les logs : `logs/sitemap_update.log`
2. Vérifiez la configuration de base de données
3. Testez les URLs manuellement

## 📝 Maintenance

### Tâches régulières

- **Hebdomadaire** : Vérifier les logs de mise à jour
- **Mensuel** : Valider le sitemap avec les outils Google
- **Trimestriel** : Réviser les priorités et fréquences

### Mises à jour

Quand mettre à jour le sitemap :
- Ajout de nouvelles pages
- Modification de la structure d'URL
- Ajout de nouveaux types de contenu
- Changement de domaine

---

*Sitemap professionnel créé le 07/01/2025 pour votre site spirituel* ✨
