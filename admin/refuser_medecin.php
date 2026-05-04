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

$default_redirect_page_ref_med = 'gestion_medecins.php'; 
$return_url_ref_med = trim($_POST['return_url'] ?? $default_redirect_page_ref_med);

if (!preg_match('/^(gestion_medecins\.php|dashboard_admin\.php)(\?.*)?$/', basename(parse_url($return_url_ref_med, PHP_URL_PATH)))) {
    $return_url_ref_med = $default_redirect_page_ref_med;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_message'] = "Action non autorisée (méthode GET).";
    $_SESSION['flash_type'] = "warning";
    header('Location: ' . $return_url_ref_med);
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $_SESSION['flash_message'] = "Erreur de sécurité. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $return_url_ref_med);
    exit;
}
$medecin_id_a_refuser = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);

if (!$medecin_id_a_refuser) {
    $_SESSION['flash_message'] = "ID de médecin invalide ou manquant pour le refus.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $return_url_ref_med);
    exit;
}

try {
    if (!$pdo->query("SHOW TABLES LIKE 'medecins'")->rowCount() > 0) {
        throw new PDOException("La table 'medecins' n'existe pas.");
    }

    $stmt_med_info_to_refuse = $pdo->prepare("SELECT nom, prenom, email, photo, document_justificatif, valide FROM medecins WHERE id = ?");
    $stmt_med_info_to_refuse->execute([$medecin_id_a_refuser]);
    $medecin_to_refuse_data = $stmt_med_info_to_refuse->fetch(PDO::FETCH_ASSOC);

    if (!$medecin_to_refuse_data) {
        $_SESSION['flash_message'] = "Médecin (ID: $medecin_id_a_refuser) introuvable.";
        $_SESSION['flash_type'] = "warning";
        header("Location: " . $return_url_ref_med);
        exit;
    }

    if ($medecin_to_refuse_data['valide'] != 0) {
        $_SESSION['flash_message'] = "Ce médecin (Dr. " . htmlspecialchars($medecin_to_refuse_data['prenom'] . ' ' . $medecin_to_refuse_data['nom']) . ") n'est pas en attente. Action non applicable.";
        $_SESSION['flash_type'] = "info"; 
        header("Location: " . $return_url_ref_med);
        exit;
    }

    $photo_path_db_to_delete_ref = $medecin_to_refuse_data['photo'];
    $document_path_db_to_delete_ref = $medecin_to_refuse_data['document_justificatif'];
    $nom_medecin_refuse_email_ref = "Dr. " . htmlspecialchars($medecin_to_refuse_data['prenom'] . ' ' . $medecin_to_refuse_data['nom']);
    $email_medecin_refuse = $medecin_to_refuse_data['email'];

    $stmt_delete_medecin_ref = $pdo->prepare("DELETE FROM medecins WHERE id = ? AND valide = 0");
    
    if ($stmt_delete_medecin_ref->execute([$medecin_id_a_refuser])) {
        if ($stmt_delete_medecin_ref->rowCount() > 0) {
            if (!empty($photo_path_db_to_delete_ref)) { $full_photo_path_del_ref = __DIR__ . '/../' . $photo_path_db_to_delete_ref; if (file_exists($full_photo_path_del_ref)) { @unlink($full_photo_path_del_ref); } }
            if (!empty($document_path_db_to_delete_ref)) { $full_document_path_del_ref = __DIR__ . '/../' . $document_path_db_to_delete_ref; if (file_exists($full_document_path_del_ref)) { @unlink($full_document_path_del_ref); } }
            
            log_action_application( $pdo, 'REFUS_INSCRIPTION_MEDECIN', "Demande d'inscription du médecin " . $nom_medecin_refuse_email_ref . " (ID: $medecin_id_a_refuser, Email: $email_medecin_refuse) refusée et données supprimées.", $medecin_id_a_refuser, 'medecin_demande', ['email_medecin' => $email_medecin_refuse] );
            $_SESSION['flash_message'] = "Demande de " . $nom_medecin_refuse_email_ref . " (ID: $medecin_id_a_refuser) refusée et données supprimées.";
            $_SESSION['flash_type'] = "success";

            if (function_exists('envoyer_email') && function_exists('get_email_html_layout') && !empty($email_medecin_refuse)) {
                $sujet_email_refus_med_notify = "Suite à votre demande d'inscription sur " . NOM_APPLICATION;
                $email_support_refus = defined('EMAIL_CONTACT_PRINCIPAL') ? EMAIL_CONTACT_PRINCIPAL : 'contact@example.com';
                $contenu_principal_email_refus = "<p>Bonjour " . $nom_medecin_refuse_email_ref . ",</p><p>Nous vous remercions pour l'intérêt que vous portez à la plateforme " . NOM_APPLICATION . ".</p><p>Après examen de votre demande d'inscription, nous sommes au regret de vous informer que nous ne pouvons y donner une suite favorable pour le moment.</p><p>Si vous souhaitez obtenir plus d'informations ou si vous pensez qu'il s'agit d'une erreur, n'hésitez pas à contacter notre support à l'adresse <a href='mailto:" . htmlspecialchars($email_support_refus) . "'>" . htmlspecialchars($email_support_refus) . "</a>.</p><p>Nous vous souhaitons bonne continuation dans vos activités professionnelles.</p><p>Cordialement,<br>L'équipe d'Administration " . NOM_APPLICATION . "</p>";
                $corps_html_email_refus_med_notify = get_email_html_layout($sujet_email_refus_med_notify, $contenu_principal_email_refus, NOM_APPLICATION);
                envoyer_email($email_medecin_refuse, $nom_medecin_refuse_email_ref, $sujet_email_refus_med_notify, $corps_html_email_refus_med_notify);
            }
        } else {
             $_SESSION['flash_message'] = "Médecin (ID: $medecin_id_a_refuser) non supprimé (statut modifié ou déjà supprimé ?).";
             $_SESSION['flash_type'] = "warning";
        }
    } else {
        $_SESSION['flash_message'] = "Erreur lors de la suppression du médecin de la BDD.";
        $_SESSION['flash_type'] = "error";
    }
} catch (PDOException $e) {
    error_log("Erreur PDO dans refuser_medecin.php (ID: $medecin_id_a_refuser): " . $e->getMessage());
    $_SESSION['flash_message'] = "Erreur technique lors du refus/suppression.";
    $_SESSION['flash_type'] = "error";
}
header("Location: " . $return_url_ref_med);
exit;
?>