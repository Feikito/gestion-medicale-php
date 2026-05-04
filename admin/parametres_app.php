<?php
session_start();
require '../php/db.php'; 
require_once '../php/utils/csrf_utils.php';
require_once '../php/utils/logger.php'; 

if (!isset($_SESSION['admin_id'])) { 
    $_SESSION['flash_message_login'] = "Accès refusé. Veuillez vous connecter.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/admin-login.php'); 
    exit;
}
$admin_id_params = $_SESSION['admin_id'];

function get_parametre_app_admin($nom_parametre, $pdo_conn) {
    try {
        if (!$pdo_conn instanceof PDO) { return null; }
        $stmt = $pdo_conn->prepare("SELECT valeur_parametre FROM parametres_application WHERE nom_parametre = ?");
        $stmt->execute([$nom_parametre]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : null;
    } catch (PDOException $e) {
        error_log("Erreur get_parametre_app_admin ($nom_parametre): " . $e->getMessage());
        return null;
    }
}

function update_parametre_app_admin($nom_parametre, $valeur_parametre, $pdo_conn) {
    try {
        if (!$pdo_conn instanceof PDO) { return false; }
        $stmt = $pdo_conn->prepare("UPDATE parametres_application SET valeur_parametre = ? WHERE nom_parametre = ?");
        return $stmt->execute([$valeur_parametre, $nom_parametre]);
    } catch (PDOException $e) {
        error_log("Erreur update_parametre_app_admin ($nom_parametre): " . $e->getMessage());
        return false;
    }
}

$flash_message_params_page = $_SESSION['flash_message'] ?? null;
$flash_type_params_page = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$table_params_exists = false; 
$parametres_actuels = [];   
$noms_parametres_a_charger = [
    'NOM_APPLICATION', 'EMAIL_CONTACT_PRINCIPAL', 'EMAIL_SYSTEM_FROM', 
    'EMAIL_ADMIN_NOTIFICATIONS', 'NOMBRE_MEDECINS_ACCUEIL', 
    'ELEMENTS_PAR_PAGE_DOCTEURS_PUBLIC', // Nouveau
    'ELEMENTS_PAR_PAGE_ADMIN_MEDECINS',    // Nouveau
    'ELEMENTS_PAR_PAGE_ADMIN_PATIENTS',    // Nouveau
    'ELEMENTS_PAR_PAGE_ADMIN_RDV',         // Nouveau
    'ELEMENTS_PAR_PAGE_ADMIN_COMMENTAIRES',// Nouveau
    'ELEMENTS_PAR_PAGE_ADMIN_HISTORIQUE',  // Nouveau
    'MAINTENANCE_MODE', 'MESSAGE_MAINTENANCE'
];
foreach ($noms_parametres_a_charger as $nom_param_init) {
    $parametres_actuels[$nom_param_init] = null;
}

if (isset($pdo)) {
    try {
        $stmt_check_table = $pdo->query("SHOW TABLES LIKE 'parametres_application'");
        $table_params_exists = $stmt_check_table->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erreur vérification table parametres_application: " . $e->getMessage());
        $flash_message_params_page = "Erreur base de données: Impossible de vérifier la table des paramètres.";
        $flash_type_params_page = "danger";
        $table_params_exists = false;
    }

    if ($table_params_exists) {
        foreach ($noms_parametres_a_charger as $nom_param) {
            $parametres_actuels[$nom_param] = get_parametre_app_admin($nom_param, $pdo);
        }
    } else {
        if (!$flash_message_params_page) { 
            $flash_message_params_page = "Erreur critique : La table des paramètres de l'application est manquante. Certaines fonctionnalités peuvent être limitées.";
            $flash_type_params_page = "danger";
        }
    }
} else {
    if (!$flash_message_params_page) {
        $flash_message_params_page = "Erreur critique : Connexion à la base de données impossible. Les paramètres ne peuvent être chargés.";
        $flash_type_params_page = "danger";
    }
    $table_params_exists = false; 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$table_params_exists || !isset($pdo)) {
        $_SESSION['flash_message'] = "Impossible de sauvegarder les paramètres : problème de base de données.";
        $_SESSION['flash_type'] = "danger";
    } elseif (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_message'] = "Erreur de sécurité. Veuillez réessayer.";
        $_SESSION['flash_type'] = "danger";
    } else {
        invalidate_csrf_token(); 
        $succes_total = true;
        $erreurs_maj = [];
        $changements_effectues = []; 

        foreach ($noms_parametres_a_charger as $nom_param) {
            if (isset($_POST[$nom_param]) || $nom_param === 'MAINTENANCE_MODE') { 
                $nouvelle_valeur = ($nom_param === 'MAINTENANCE_MODE') ? (isset($_POST['MAINTENANCE_MODE']) ? '1' : '0') : trim($_POST[$nom_param]);
                
                if (($nom_param === 'EMAIL_CONTACT_PRINCIPAL' || $nom_param === 'EMAIL_SYSTEM_FROM' || $nom_param === 'EMAIL_ADMIN_NOTIFICATIONS') && !empty($nouvelle_valeur) && !filter_var($nouvelle_valeur, FILTER_VALIDATE_EMAIL)) {
                    $erreurs_maj[] = "L'email pour '".str_replace('_', ' ', $nom_param)."' n'est pas valide.";
                    $succes_total = false; continue; 
                }
                if (strpos($nom_param, 'NOMBRE_') === 0 || strpos($nom_param, 'ELEMENTS_PAR_PAGE_') === 0) {
                    if (!empty($nouvelle_valeur) && (!filter_var($nouvelle_valeur, FILTER_VALIDATE_INT) || (int)$nouvelle_valeur < 1 || (int)$nouvelle_valeur > 50)) { // Limite ajustée
                        $erreurs_maj[] = "La valeur pour '".str_replace('_', ' ', $nom_param)."' doit être un nombre entre 1 et 50.";
                        $succes_total = false; continue;
                    }
                }

                if (($parametres_actuels[$nom_param] ?? null) !== $nouvelle_valeur) {
                    $changements_effectues[$nom_param] = ['ancienne_valeur' => ($parametres_actuels[$nom_param] ?? 'non défini'), 'nouvelle_valeur' => $nouvelle_valeur];
                }

                if (!update_parametre_app_admin($nom_param, $nouvelle_valeur, $pdo)) {
                    $succes_total = false;
                    $erreurs_maj[] = "Erreur lors de la mise à jour de '".str_replace('_', ' ', $nom_param)."'.";
                } else {
                    $parametres_actuels[$nom_param] = $nouvelle_valeur; 
                }
            }
        }

        if ($succes_total) {
            $_SESSION['flash_message'] = "Paramètres de l'application mis à jour avec succès.";
            $_SESSION['flash_type'] = "success";
            if (!empty($changements_effectues) && function_exists('log_action_application')) {
                log_action_application( $pdo, 'MODIF_PARAMETRE_APP', "Des paramètres de l'application ont été modifiés par l'admin ID: " . ($_SESSION['admin_id'] ?? 'N/A'), null, null, $changements_effectues );
            }
        } else {
            $_SESSION['flash_message'] = "Certains paramètres n'ont pas pu être mis à jour : <br>" . implode("<br>", $erreurs_maj);
            $_SESSION['flash_type'] = "error";
        }
    }
    header('Location: parametres_app.php');
    exit;
}
$csrf_token_params = generate_csrf_token();

$nb_med_att_nav_params = 0; $nb_com_att_nav_params = 0;
if (isset($pdo)) {
    if ($pdo->query("SHOW TABLES LIKE 'medecins'")->rowCount() > 0) {
        $nb_med_att_nav_params = (int)$pdo->query("SELECT COUNT(*) FROM medecins WHERE valide = 0")->fetchColumn();
    }
    if ($pdo->query("SHOW TABLES LIKE 'commentaires'")->rowCount() > 0) {
        $nb_com_att_nav_params = (int)$pdo->query("SELECT COUNT(*) FROM commentaires WHERE statut = 'en attente'")->fetchColumn();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres de l'Application - Admin SANTE TV</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="admin-gestion-page body-admin-parametres">

<header class="site-header admin-header">
    <div class="container">
        <div class="logo-branding"><a href="dashboard_admin.php" title="Tableau de Bord Admin"><img src="../assets/images/logo1.png" alt="SANTE TV Logo" id="logo-img"><span class="site-title">Admin SANTE TV</span></a></div>
        <button class="mobile-nav-toggle" id="mobile-nav-trigger" aria-label="Ouvrir le menu de navigation" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span></button>
        <nav class="main-navigation admin-navigation" id="main-nav">
             <ul>
                <li><a href="dashboard_admin.php" class="nav-link">Dashboard</a></li>
                <li><a href="gestion_medecins.php" class="nav-link">Médecins <?php if($nb_med_att_nav_params > 0): ?><span class="badge-notification"><?= $nb_med_att_nav_params ?></span><?php endif; ?></a></li>
                <li><a href="gestion_patients.php" class="nav-link">Patients</a></li>
                <li><a href="gestion_rdv.php" class="nav-link">Rendez-vous</a></li>
                <li><a href="parametres_app.php" class="nav-link active">Paramètres</a></li>
                <li><a href="historique_app.php" class="nav-link">Historique</a></li>
                <li><a href="deconnexion_admin.php" class="nav-link btn btn-sm btn-danger">Déconnexion</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="main-content admin-page-container section-padding"> 
    <div class="container">
        <div class="page-header">
            <h1 class="page-main-title"><i class="fas fa-cogs page-icon"></i> Paramètres Généraux de l'Application</h1>
            <p class="section-subtitle">Configurez les options globales de la plateforme SANTE TV.</p>
        </div>

        <?php if ($flash_message_params_page): ?>
            <div class="alert alert-<?= htmlspecialchars($flash_type_params_page) ?> alert-dismissible">
                <?= $flash_message_params_page ?>
                <button type="button" class="close-alert" data-dismiss="alert" aria-label="Fermer">×</button> 
            </div>
        <?php endif; ?>

        <?php if (!$table_params_exists): ?>
            <p class="info-message error-message"><i class="fas fa-exclamation-triangle icon-left"></i>Impossible de charger ou modifier les paramètres. La table de configuration est manquante ou une erreur de connexion est survenue.</p>
        <?php else: ?>
            <div class="card" style="padding: var(--spacing-xl);">
                <form action="parametres_app.php" method="POST" class="user-form">
                    <?= csrf_input_field() ?>
                    
                    <fieldset class="form-fieldset">
                        <legend class="fieldset-legend">Informations Générales</legend>
                        <div class="form-group">
                            <label for="NOM_APPLICATION">Nom de l'Application :</label>
                            <input type="text" name="NOM_APPLICATION" id="NOM_APPLICATION" class="form-control" 
                                   value="<?= htmlspecialchars($parametres_actuels['NOM_APPLICATION'] ?? 'SANTE TV') ?>" required>
                            <small class="form-note">Affiché dans les titres de page, emails, etc.</small>
                        </div>
                        <div class="form-group">
                            <label for="EMAIL_CONTACT_PRINCIPAL">Email de Contact Public :</label>
                            <input type="email" name="EMAIL_CONTACT_PRINCIPAL" id="EMAIL_CONTACT_PRINCIPAL" class="form-control"
                                   value="<?= htmlspecialchars($parametres_actuels['EMAIL_CONTACT_PRINCIPAL'] ?? 'contact@example.com') ?>" required>
                            <small class="form-note">Adresse e-mail affichée sur la page Contact.</small>
                        </div>
                    </fieldset>

                    <fieldset class="form-fieldset">
                        <legend class="fieldset-legend">Configuration des Emails</legend>
                        <div class="form-group">
                            <label for="EMAIL_SYSTEM_FROM">Email Expéditeur Système :</label>
                            <input type="email" name="EMAIL_SYSTEM_FROM" id="EMAIL_SYSTEM_FROM" class="form-control"
                                   value="<?= htmlspecialchars($parametres_actuels['EMAIL_SYSTEM_FROM'] ?? 'nepasrepondre@example.com') ?>" required>
                            <small class="form-note">Adresse utilisée pour les emails automatisés.</small>
                        </div>
                        <div class="form-group">
                            <label for="EMAIL_ADMIN_NOTIFICATIONS">Email pour Notifications Admin :</label>
                            <input type="email" name="EMAIL_ADMIN_NOTIFICATIONS" id="EMAIL_ADMIN_NOTIFICATIONS" class="form-control"
                                   value="<?= htmlspecialchars($parametres_actuels['EMAIL_ADMIN_NOTIFICATIONS'] ?? 'admin@example.com') ?>" required>
                            <small class="form-note">Les administrateurs recevront les alertes importantes à cette adresse.</small>
                        </div>
                    </fieldset>

                     <fieldset class="form-fieldset">
                        <legend class="fieldset-legend">Affichage et Pagination</legend>
                        <div class="form-group">
                            <label for="NOMBRE_MEDECINS_ACCUEIL">Nombre de Médecins "À la Une" (Accueil) :</label>
                            <input type="number" name="NOMBRE_MEDECINS_ACCUEIL" id="NOMBRE_MEDECINS_ACCUEIL" class="form-control"
                                   value="<?= htmlspecialchars($parametres_actuels['NOMBRE_MEDECINS_ACCUEIL'] ?? '4') ?>" min="1" max="12" step="1" required>
                            <small class="form-note">Médecins sur la page d'accueil (1-12).</small>
                        </div>
                        <div class="form-group">
                            <label for="ELEMENTS_PAR_PAGE_DOCTEURS_PUBLIC">Médecins par page (Liste publique) :</label>
                            <input type="number" name="ELEMENTS_PAR_PAGE_DOCTEURS_PUBLIC" id="ELEMENTS_PAR_PAGE_DOCTEURS_PUBLIC" class="form-control"
                                   value="<?= htmlspecialchars($parametres_actuels['ELEMENTS_PAR_PAGE_DOCTEURS_PUBLIC'] ?? '6') ?>" min="3" max="24" step="3" required>
                            <small class="form-note">Nombre de médecins par page sur la page "Nos Médecins" (3-24).</small>
                        </div>
                         <div class="form-group">
                            <label for="ELEMENTS_PAR_PAGE_ADMIN_MEDECINS">Médecins par page (Admin) :</label>
                            <input type="number" name="ELEMENTS_PAR_PAGE_ADMIN_MEDECINS" id="ELEMENTS_PAR_PAGE_ADMIN_MEDECINS" class="form-control"
                                   value="<?= htmlspecialchars($parametres_actuels['ELEMENTS_PAR_PAGE_ADMIN_MEDECINS'] ?? '10') ?>" min="5" max="50" step="1" required>
                        </div>
                        <div class="form-group">
                            <label for="ELEMENTS_PAR_PAGE_ADMIN_PATIENTS">Patients par page (Admin) :</label>
                            <input type="number" name="ELEMENTS_PAR_PAGE_ADMIN_PATIENTS" id="ELEMENTS_PAR_PAGE_ADMIN_PATIENTS" class="form-control"
                                   value="<?= htmlspecialchars($parametres_actuels['ELEMENTS_PAR_PAGE_ADMIN_PATIENTS'] ?? '15') ?>" min="5" max="50" step="1" required>
                        </div>
                         <div class="form-group">
                            <label for="ELEMENTS_PAR_PAGE_ADMIN_RDV">RDV par page (Admin) :</label>
                            <input type="number" name="ELEMENTS_PAR_PAGE_ADMIN_RDV" id="ELEMENTS_PAR_PAGE_ADMIN_RDV" class="form-control"
                                   value="<?= htmlspecialchars($parametres_actuels['ELEMENTS_PAR_PAGE_ADMIN_RDV'] ?? '15') ?>" min="5" max="50" step="1" required>
                        </div>
                        <div class="form-group">
                            <label for="ELEMENTS_PAR_PAGE_ADMIN_COMMENTAIRES">Commentaires par page (Admin) :</label>
                            <input type="number" name="ELEMENTS_PAR_PAGE_ADMIN_COMMENTAIRES" id="ELEMENTS_PAR_PAGE_ADMIN_COMMENTAIRES" class="form-control"
                                   value="<?= htmlspecialchars($parametres_actuels['ELEMENTS_PAR_PAGE_ADMIN_COMMENTAIRES'] ?? '15') ?>" min="5" max="50" step="1" required>
                        </div>
                         <div class="form-group">
                            <label for="ELEMENTS_PAR_PAGE_ADMIN_HISTORIQUE">Entrées d'historique par page (Admin) :</label>
                            <input type="number" name="ELEMENTS_PAR_PAGE_ADMIN_HISTORIQUE" id="ELEMENTS_PAR_PAGE_ADMIN_HISTORIQUE" class="form-control"
                                   value="<?= htmlspecialchars($parametres_actuels['ELEMENTS_PAR_PAGE_ADMIN_HISTORIQUE'] ?? '20') ?>" min="10" max="100" step="5" required>
                        </div>
                    </fieldset>

                    <fieldset class="form-fieldset">
                        <legend class="fieldset-legend">Mode Maintenance</legend>
                        <div class="form-group">
                             <label class="form-check-label" for="MAINTENANCE_MODE">
                                <input type="checkbox" name="MAINTENANCE_MODE" id="MAINTENANCE_MODE" class="form-check-input" value="1"
                                    <?= (isset($parametres_actuels['MAINTENANCE_MODE']) && $parametres_actuels['MAINTENANCE_MODE'] == '1') ? 'checked' : '' ?>>
                                Activer le mode maintenance
                            </label>
                            <small class="form-note">Si activé, seuls les administrateurs connectés pourront accéder au site. Les autres visiteurs verront le message de maintenance.</small>
                        </div>
                         <div class="form-group">
                            <label for="MESSAGE_MAINTENANCE">Message de Maintenance :</label>
                            <textarea name="MESSAGE_MAINTENANCE" id="MESSAGE_MAINTENANCE" class="form-control" rows="4"><?= htmlspecialchars($parametres_actuels['MESSAGE_MAINTENANCE'] ?? 'Site en maintenance.') ?></textarea>
                            <small class="form-note">Ce message sera affiché aux visiteurs si le mode maintenance est actif.</small>
                        </div>
                    </fieldset>

                    <div class="form-actions" style="margin-top: var(--spacing-xl);">
                        <button type="submit" class="btn primary-action btn-lg">
                            <i class="fas fa-save icon-left"></i>Enregistrer les Paramètres
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>

<footer class="site-footer admin-footer">
   <div class="container">
        <p class="copyright-text text-center">© <span id="footer-year"><?= date('Y') ?></span> SANTE TV - Espace Administration.</p>
    </div>
</footer>

<script src="../assets/js/script.js"></script> 
<style>
    .body-admin-parametres .page-main-title .page-icon { color: var(--color-neutral-dark); }
    .body-admin-parametres .card { border-top: 3px solid var(--color-neutral-dark); }
    .body-admin-parametres .btn.primary-action { background-color: var(--color-neutral-darkest); }
    .body-admin-parametres .btn.primary-action:hover { background-color: var(--color-neutral-dark); }

    .form-fieldset {
        border: 1px solid var(--color-border-subtle);
        padding: var(--spacing-lg);
        margin-bottom: var(--spacing-xl);
        border-radius: var(--border-radius-lg);
        background-color: #fdfdfd;
    }
    .fieldset-legend {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--color-brand-primary-dark);
        padding: 0 var(--spacing-md);
        margin-left: var(--spacing-sm);
    }
    .form-check-label {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        font-weight: normal;
        cursor: pointer;
    }
    .form-check-input {
        width: auto;
        height: auto;
        margin-right: 0.3em;
        transform: scale(1.2);
    }
</style>
</body>
</html>