<?php
// php/scripts/purge_expired_rdv.php
// Ce script est destiné à être exécuté par un cron job.
// Il ne devrait pas être accessible directement via le web.

// S'assurer que le script n'est pas exécuté via le web (sécurité de base)
if (php_sapi_name() !== 'cli' && substr(php_sapi_name(), 0, 3) !== 'cgi') {
    die("Accès non autorisé.");
}

require_once __DIR__ . '/../db.php'; // Ajustez le chemin vers db.php

error_log("Début du script de purge des rendez-vous expirés : " . date('Y-m-d H:i:s'));

try {
    // Option 1: Supprimer physiquement les RDV passés
    // Attention: Cette action est irréversible. Assurez-vous d'avoir des sauvegardes.
    // $stmt = $pdo->prepare("DELETE FROM rendez_vous WHERE CONCAT(date_rdv, ' ', heure_rdv) < NOW()");
    // $deleted_count = $stmt->execute() ? $stmt->rowCount() : 0;
    // error_log("Nombre de rendez-vous expirés supprimés : " . $deleted_count);

    // Option 2: Marquer les RDV comme "terminé" ou "archivé" (plus sûr, permet de garder un historique)
    // Assurez-vous d'avoir une colonne 'statut_final' ou similaire, ou d'étendre votre ENUM 'statut'.
    // Pour cet exemple, je vais supposer que vous changez le statut à 'terminé' (ajoutez 'terminé' à votre ENUM statut).
    $check_enum_termine = $pdo->query("SHOW COLUMNS FROM rendez_vous LIKE 'statut'");
    $enum_def_termine = $check_enum_termine->fetch(PDO::FETCH_ASSOC);
    if ($enum_def_termine && strpos($enum_def_termine['Type'], "'terminé'") !== false) {
        $stmt_archive = $pdo->prepare(
            "UPDATE rendez_vous 
             SET statut = 'terminé' 
             WHERE CONCAT(date_rdv, ' ', heure_rdv) < NOW() 
             AND statut IN ('confirmé', 'en attente')" // Seulement ceux qui n'ont pas été annulés/refusés/déjà terminés
        );
        $updated_count = $stmt_archive->execute() ? $stmt_archive->rowCount() : 0;
        error_log("Nombre de rendez-vous marqués comme 'terminé' : " . $updated_count);
    } else {
        error_log("Le statut 'terminé' n'existe pas dans l'ENUM de la table rendez_vous. L'archivage n'a pas été effectué.");
        // Si vous ne voulez pas ajouter 'terminé', vous pouvez les supprimer ou utiliser une autre logique.
        // Par exemple, si le statut est 'confirmé' et la date est passée, il est implicitement terminé.
        // Dans ce cas, il n'y a peut-être rien à faire ici sauf si vous voulez explicitement les supprimer.
        error_log("Aucune action de purge configurée pour les RDV passés (statut 'terminé' manquant).");
    }


} catch (PDOException $e) {
    error_log("Erreur PDO lors de la purge des rendez-vous expirés : " . $e->getMessage());
} catch (Exception $e) {
    error_log("Erreur générale lors de la purge des rendez-vous expirés : " . $e->getMessage());
}

error_log("Fin du script de purge des rendez-vous expirés : " . date('Y-m-d H:i:s'));
echo "Script de purge terminé.\n";
?>