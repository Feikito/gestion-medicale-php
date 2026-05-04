<?php
session_start(); 
require '../php/db.php'; 
require_once '../php/utils/email_functions.php'; 
require_once '../php/utils/email_template.php';
require_once '../php/utils/logger.php'; 
require_once '../php/utils/csrf_utils.php';
require_once '../php/utils/app_settings.php';

if (!isset($_SESSION['admin_id'])) { 
    $_SESSION['flash_message_login'] = "Accès refusé.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/admin-login.php'); 
    exit;
}

$default_redirect_page_inval_med = 'gestion_medecins.php'; 
$return_url_inval_med = trim($_POST['return_url'] ?? $default_redirect_page_inval_med);

if (!preg_match('/^(gestion_medecins\.php|voir_medecin\.php|dashboard_admin\.php)(\?.*)?$/', basename(parse_url($return_url_inval_med, PHP_URL_PATH)))) {
    $return_url_inval_med = $default_redirect_page_inval_med;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_message'] = "Action non autorisée (méthode GET).";
    $_SESSION['flash_type'] = "warning";
    header('Location: ' . $return_url_inval_med);
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $_SESSION['flash_message'] = "Erreur de sécurité. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $return_url_inval_med);
    exit;
}
$medecin_id_a_invalider = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);

if (!$medecin_id_a_invalider) {
    $_SESSION['flash_message'] = "ID de médecin invalide ou manquant pour l'invalidation.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $return_url_inval_med);
    exit;
}

try {
    if (!$pdo->query("SHOW TABLES LIKE 'medecins'")->rowCount() > 0) {
        throw new PDOException("La table 'medecins' n'existe pas.");
    }

    $stmt_check_med_inval = $pdo->prepare("SELECT email, nom, prenom, valide FROM medecins WHERE id = ?");
    $stmt_check_med_inval->execute([$medecin_id_a_invalider]);
    $medecin_to_invalidate_data = $stmt_check_med_inval->fetch(PDO::FETCH_ASSOC);

    if (!$medecin_to_invalidate_data) {
        $_SESSION['flash_message'] = "Médecin (ID: $medecin_id_a_invalider) introuvable.";
        $_SESSION['flash_type'] = "warning";
        header("Location: " . $return_url_inval_med);
        exit;
    }

    if ($medecin_to_invalidate_data['valide'] != 1) { 
        $_SESSION['flash_message'] = "Ce médecin (Dr. " . htmlspecialchars($medecin_to_invalidate_data['prenom'] . ' ' . $medecin_to_invalidate_data['nom']) . ") n'est pas actif. Action non applicable.";
        $_SESSION['flash_type'] = "info";
        header("Location: " . $return_url_inval_med);
        exit;
    }
    $nom_medecin_inval_email_notify = "Dr. " . htmlspecialchars($medecin_to_invalidate_data['prenom'] . ' ' . $medecin_to_invalidate_data['nom']);
    $email_medecin_inval = $medecin_to_invalidate_data['email'];

    $stmt_update_med_inval_status = $pdo->prepare("UPDATE medecins SET valide = 0 WHERE id = ? AND valide = 1");
    
    if ($stmt_update_med_inval_status->execute([$medecin_id_a_invalider])) {
        if ($stmt_update_med_inval_status->rowCount() > 0) {
            log_action_application( $pdo, 'INVALIDATION_COMPTE_MEDECIN', "Le compte du médecin " . $nom_medecin_inval_email_notify . " (ID: $medecin_id_a_invalider, Email: $email_medecin_inval) a été rendu inactif.", $medecin_id_a_invalider, 'medecin', ['email_medecin' => $email_medecin_inval] );
            $_SESSION['flash_message'] = "Compte du médecin " . $nom_medecin_inval_email_notify . " (ID: $medecin_id_a_invalider) rendu inactif.";
            $_SESSION['flash_type'] = "success";

            if (function_exists('envoyer_email') && function_exists('get_email_html_layout') && !empty($email_medecin_inval)) {
                $sujet_email_invalidation_med_notify = "Information importante concernant votre compte " . NOM_APPLICATION;
                $email_support_inval = defined('EMAIL_CONTACT_PRINCIPAL') ? EMAIL_CONTACT_PRINCIPAL : 'contact@example.com';
                $contenu_principal_email_inval = "<p>Bonjour " . $nom_medecin_inval_email_notify . ",</p><p>Nous vous informons que votre compte médecin sur la plateforme " . NOM_APPLICATION . " a été temporairement désactivé par un administrateur.</p><p>En conséquence, votre profil n'est plus visible publiquement et vous ne pourrez plus recevoir de nouvelles demandes de rendez-vous tant que votre compte reste inactif.</p><p>Vos rendez-vous déjà confirmés avec des patients restent planifiés. Nous vous conseillons de vérifier votre agenda et de contacter les patients concernés si des ajustements sont nécessaires de votre part.</p><p>Pour toute question, pour comprendre les raisons de cette désactivation ou pour discuter de la réactivation de votre compte, veuillez contacter notre équipe de support à l'adresse <a href='mailto:" . htmlspecialchars($email_support_inval) . "'>" . htmlspecialchars($email_support_inval) . "</a> en mentionnant votre ID de compte : {$medecin_id_a_invalider}.</p><p>Nous espérons pouvoir réactiver rapidement votre compte après clarification.</p><p>Cordialement,<br>L'équipe d'Administration " . NOM_APPLICATION . "</p>";
                $corps_html_email_invalidation_med_notify = get_email_html_layout($sujet_email_invalidation_med_notify, $contenu_principal_email_inval, NOM_APPLICATION);
                envoyer_email($email_medecin_inval, $nom_medecin_inval_email_notify, $sujet_email_invalidation_med_notify, $corps_html_email_invalidation_med_notify);
            }
        } else {
            $_SESSION['flash_message'] = "Statut du médecin (ID: $medecin_id_a_invalider) non modifié (déjà à jour ?).";
            $_SESSION['flash_type'] = "warning";
        }
    } else {
        $_SESSION['flash_message'] = "Erreur lors de la tentative d'invalidation du médecin.";
        $_SESSION['flash_type'] = "error";
    }
} catch (PDOException $e) {
    error_log("Erreur PDO dans invalider_medecin.php (ID: $medecin_id_a_invalider): " . $e->getMessage());
    $_SESSION['flash_message'] = "Erreur technique lors de l'invalidation."; 
    $_SESSION['flash_type'] = "error";
}
header("Location: " . $return_url_inval_med);
exit;
?>