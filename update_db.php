<?php
/**
 * Script pour mettre à jour la base de données
 * Ce script exécute wp_db_update.php et affiche les résultats
 */

// Inclure le script de mise à jour
include_once 'admin/includes/wp_db_update.php';

// Rediriger vers l'admin après 5 secondes
echo '<script>
    setTimeout(function() {
        window.location.href = "admin/blog.php";
    }, 5000);
</script>';
echo '<p>Vous serez redirigé vers le panneau d\'administration dans 5 secondes...</p>';
?>
