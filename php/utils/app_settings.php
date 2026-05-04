<?php
// php/utils/app_settings.php

if (session_status() == PHP_SESSION_NONE) { // Nécessaire si ce fichier est inclus avant session_start() ailleurs
     session_start(); // Attention: peut causer "headers already sent" si inclus trop tard.
                    // Il est préférable que session_start() soit appelé au tout début du script principal.
}


if (!isset($pdo) || !$pdo instanceof PDO) {
    if (file_exists(__DIR__ . '/../db.php')) {
        require_once __DIR__ . '/../db.php';
    } else {
        error_log("app_settings.php: Instance PDO non disponible et db.php introuvable.");
        // Définir des constantes par défaut si $pdo n'est pas disponible
        if (!defined('NOM_APPLICATION')) define('NOM_APPLICATION', 'SANTE TV (Config Défaut)');
        if (!defined('EMAIL_CONTACT_PRINCIPAL')) define('EMAIL_CONTACT_PRINCIPAL', 'contact.default@example.com');
        if (!defined('EMAIL_SYSTEM_FROM_ADDRESS')) define('EMAIL_SYSTEM_FROM_ADDRESS', 'noreply.default@example.com');
        if (!defined('ADMIN_EMAIL_NOTIFICATIONS')) define('ADMIN_EMAIL_NOTIFICATIONS', 'admin.default@example.com');
        if (!defined('NOMBRE_MEDECINS_ACCUEIL_DEFAULT')) define('NOMBRE_MEDECINS_ACCUEIL_DEFAULT', 4);
        if (!defined('ELEMENTS_PAR_PAGE_DOCTEURS_PUBLIC_DEFAULT')) define('ELEMENTS_PAR_PAGE_DOCTEURS_PUBLIC_DEFAULT', 6);
        if (!defined('ELEMENTS_PAR_PAGE_ADMIN_MEDECINS_DEFAULT')) define('ELEMENTS_PAR_PAGE_ADMIN_MEDECINS_DEFAULT', 10);
        if (!defined('ELEMENTS_PAR_PAGE_ADMIN_PATIENTS_DEFAULT')) define('ELEMENTS_PAR_PAGE_ADMIN_PATIENTS_DEFAULT', 15);
        if (!defined('ELEMENTS_PAR_PAGE_ADMIN_RDV_DEFAULT')) define('ELEMENTS_PAR_PAGE_ADMIN_RDV_DEFAULT', 15);
        if (!defined('ELEMENTS_PAR_PAGE_ADMIN_COMMENTAIRES_DEFAULT')) define('ELEMENTS_PAR_PAGE_ADMIN_COMMENTAIRES_DEFAULT', 15);
        if (!defined('ELEMENTS_PAR_PAGE_ADMIN_HISTORIQUE_DEFAULT')) define('ELEMENTS_PAR_PAGE_ADMIN_HISTORIQUE_DEFAULT', 20);
        if (!defined('MAINTENANCE_MODE_ENABLED')) define('MAINTENANCE_MODE_ENABLED', false);
        if (!defined('MAINTENANCE_MESSAGE_DEFAULT')) define('MAINTENANCE_MESSAGE_DEFAULT', 'Site en maintenance.');
        return;
    }
}

try {
    $app_settings_db = [];
    if ($pdo->query("SHOW TABLES LIKE 'parametres_application'")->rowCount() > 0) {
        $stmt_params_app = $pdo->query("SELECT nom_parametre, valeur_parametre FROM parametres_application");
        $app_settings_db = $stmt_params_app->fetchAll(PDO::FETCH_KEY_PAIR);
    } else {
        error_log("La table 'parametres_application' est manquante. Utilisation des valeurs par défaut pour les constantes.");
    }

    define('NOM_APPLICATION', $app_settings_db['NOM_APPLICATION'] ?? 'SANTE TV');
    define('EMAIL_CONTACT_PRINCIPAL', $app_settings_db['EMAIL_CONTACT_PRINCIPAL'] ?? 'contact@santetv.ma');
    define('EMAIL_SYSTEM_FROM_ADDRESS', $app_settings_db['EMAIL_SYSTEM_FROM'] ?? 'nepasrepondre@santetv.ma');
    define('ADMIN_EMAIL_NOTIFICATIONS', $app_settings_db['EMAIL_ADMIN_NOTIFICATIONS'] ?? 'admin@santetv.ma');
    define('NOMBRE_MEDECINS_ACCUEIL_DEFAULT', (int)($app_settings_db['NOMBRE_MEDECINS_ACCUEIL'] ?? 4));
    define('ELEMENTS_PAR_PAGE_DOCTEURS_PUBLIC_DEFAULT', (int)($app_settings_db['ELEMENTS_PAR_PAGE_DOCTEURS_PUBLIC'] ?? 6));
    define('ELEMENTS_PAR_PAGE_ADMIN_MEDECINS_DEFAULT', (int)($app_settings_db['ELEMENTS_PAR_PAGE_ADMIN_MEDECINS'] ?? 10));
    define('ELEMENTS_PAR_PAGE_ADMIN_PATIENTS_DEFAULT', (int)($app_settings_db['ELEMENTS_PAR_PAGE_ADMIN_PATIENTS'] ?? 15));
    define('ELEMENTS_PAR_PAGE_ADMIN_RDV_DEFAULT', (int)($app_settings_db['ELEMENTS_PAR_PAGE_ADMIN_RDV'] ?? 15));
    define('ELEMENTS_PAR_PAGE_ADMIN_COMMENTAIRES_DEFAULT', (int)($app_settings_db['ELEMENTS_PAR_PAGE_ADMIN_COMMENTAIRES'] ?? 15));
    define('ELEMENTS_PAR_PAGE_ADMIN_HISTORIQUE_DEFAULT', (int)($app_settings_db['ELEMENTS_PAR_PAGE_ADMIN_HISTORIQUE'] ?? 20));
    define('MAINTENANCE_MODE_ENABLED', (bool)($app_settings_db['MAINTENANCE_MODE'] ?? false));
    define('MAINTENANCE_MESSAGE_DEFAULT', $app_settings_db['MESSAGE_MAINTENANCE'] ?? 'Le site est actuellement en maintenance. Nous serons de retour bientôt.');

} catch (PDOException $e) {
    error_log("Erreur PDO chargement paramètres application: " . $e->getMessage());
    if (!defined('NOM_APPLICATION')) define('NOM_APPLICATION', 'SANTE TV (Erreur Config)');
    if (!defined('EMAIL_CONTACT_PRINCIPAL')) define('EMAIL_CONTACT_PRINCIPAL', 'support.error@example.com');
    if (!defined('EMAIL_SYSTEM_FROM_ADDRESS')) define('EMAIL_SYSTEM_FROM_ADDRESS', 'noreply.error@example.com');
    if (!defined('ADMIN_EMAIL_NOTIFICATIONS')) define('ADMIN_EMAIL_NOTIFICATIONS', 'admin.error@example.com');
    if (!defined('NOMBRE_MEDECINS_ACCUEIL_DEFAULT')) define('NOMBRE_MEDECINS_ACCUEIL_DEFAULT', 4);
    if (!defined('ELEMENTS_PAR_PAGE_DOCTEURS_PUBLIC_DEFAULT')) define('ELEMENTS_PAR_PAGE_DOCTEURS_PUBLIC_DEFAULT', 6);
    if (!defined('ELEMENTS_PAR_PAGE_ADMIN_MEDECINS_DEFAULT')) define('ELEMENTS_PAR_PAGE_ADMIN_MEDECINS_DEFAULT', 10);
    if (!defined('ELEMENTS_PAR_PAGE_ADMIN_PATIENTS_DEFAULT')) define('ELEMENTS_PAR_PAGE_ADMIN_PATIENTS_DEFAULT', 15);
    if (!defined('ELEMENTS_PAR_PAGE_ADMIN_RDV_DEFAULT')) define('ELEMENTS_PAR_PAGE_ADMIN_RDV_DEFAULT', 15);
    if (!defined('ELEMENTS_PAR_PAGE_ADMIN_COMMENTAIRES_DEFAULT')) define('ELEMENTS_PAR_PAGE_ADMIN_COMMENTAIRES_DEFAULT', 15);
    if (!defined('ELEMENTS_PAR_PAGE_ADMIN_HISTORIQUE_DEFAULT')) define('ELEMENTS_PAR_PAGE_ADMIN_HISTORIQUE_DEFAULT', 20);
    if (!defined('MAINTENANCE_MODE_ENABLED')) define('MAINTENANCE_MODE_ENABLED', false);
    if (!defined('MAINTENANCE_MESSAGE_DEFAULT')) define('MAINTENANCE_MESSAGE_DEFAULT', 'Site en maintenance (erreur config).');
}
?>