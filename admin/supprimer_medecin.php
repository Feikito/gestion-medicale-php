<?php
session_start(); 
require '../php/db.php'; 
require_once '../php/utils/csrf_utils.php';
require_once '../php/utils/email_functions.php'; 
require_once '../php/utils/email_template.php';
require_once '../php/utils/logger.php';
require_once '../php/utils/app_settings.php';

if (!isset($_SESSION['admin_id'])) { 
    $_SESSION['flash_message_login'] = "Accès refusé.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/admin-login.php'); 
    exit;
}

$default_redirect_page_del_med = 'gestion_medecins.php'; 
$return_url_del_med = trim($_POST['return_url'] ?? $default_redirect_page_del_med);
// Valider le return_url pour qu'il reste dans le dossier admin
if (!preg_match('/^(gestion_medecins\.php|dashboard_admin\.php)(\?.*)?$/', basename($return_url_del_med))) {
    $return_url_del_med = $default_redirect_page_del_med;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $return_url_del_med);
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $_SESSION['flash_message'] = "Erreur de sécurité. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $return_url_del_med);
    exit;
}

$medecin_id_a_supprimer = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);

if (!$medecin_id_a_supprimer) {
    $_SESSION['flash_message'] = "ID de médecin invalide ou manquant pour la suppression.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $return_url_del_med);
    exit;
}

try {
    if (!$pdo->query("SHOW TABLES LIKE 'medecins'")->rowCount() > 0) {
        throw new PDOException("La table 'medecins' n'existe pas.");
    }

    $stmt_med_info_to_delete = $pdo->prepare(
        "SELECT nom, prenom, email, photo, document_justificatif 
         FROM medecins WHERE id = ?"
    );
    $stmt_med_info_to_delete->execute([$medecin_id_a_supprimer]);
    $medecin_to_delete_data = $stmt_med_info_to_delete->fetch(PDO::FETCH_ASSOC);

    if (!$medecin_to_delete_data) {
        $_SESSION['flash_message'] = "Médecin (ID: $medecin_id_a_supprimer) introuvable.";
        $_SESSION['flash_type'] = "warning";
        header("Location: " . $return_url_del_med);
        exit;
    }

    $photo_path_db_to_delete = $medecin_to_delete_data['photo'];
    $document_path_db_to_delete = $medecin_to_delete_data['document_justificatif'];
    $nom_medecin_supprime_email = "Dr. " . htmlspecialchars($medecin_to_delete_data['prenom'] . ' ' . $medecin_to_delete_data['nom']);
    $email_medecin_supprime = $medecin_to_delete_data['email'];

    // Suppression en cascade devrait gérer les rdv, disponibilités, exceptions, messages
    // Mais il est bon de le vérifier dans votre configuration de BDD (FOREIGN KEY ON DELETE CASCADE)
    $stmt_delete_medecin = $pdo->prepare("DELETE FROM medecins WHERE id = ?");
    
    if ($stmt_delete_medecin->execute([$medecin_id_a_supprimer])) {
        if ($stmt_delete_medecin->rowCount() > 0) {
            // Suppression des fichiers associés
            if (!empty($photo_path_db_to_delete)) {
                $full_photo_path_del = __DIR__ . '/../' . $photo_path_db_to_delete; 
                if (file_exists($full_photo_path_del)) { @unlink($full_photo_path_del); }
            }
            if (!empty($document_path_db_to_delete)) {
                $full_document_path_del = __DIR__ . '/../' . $document_path_db_to_delete; 
                if (file_exists($full_document_path_del)) { @unlink($full_document_path_del); }
            }
            
            log_action_application(
                $pdo,
                'SUPPRESSION_COMPTE_MEDECIN',
                "Le compte du médecin " . $nom_medecin_supprime_email . " (ID: $medecin_id_a_supprimer, Email: $email_medecin_supprime) a été supprimé définitivement.",
                $medecin_id_a_supprimer,
                'medecin',
                ['email_medecin' => $email_medecin_supprime]
            );

            $_SESSION['flash_message'] = "Compte du médecin " . $nom_medecin_supprime_email . " (ID: $medecin_id_a_supprimer) et toutes ses données associées ont été supprimés.";
            $_SESSION['flash_type'] = "success";

            if (function_exists('envoyer_email') && !empty($email_medecin_supprime)) {
                $sujet_email_suppression = "Notification de suppression de compte sur " . NOM_APPLICATION;
                $email_support_contact = defined('EMAIL_CONTACT_PRINCIPAL') ? EMAIL_CONTACT_PRINCIPAL : 'support@example.com';
                $contenu_principal_email_supp = "<p>Bonjour " . $nom_medecin_supprime_email . ",</p><p>Nous vous informons que votre compte médecin sur la plateforme " . NOM_APPLICATION . " a été supprimé par un administrateur.</p><p>Toutes vos données associées (profil, rendez-vous, disponibilités, messages) ont été définitivement effacées de nos systèmes.</p><p>Si vous pensez qu'il s'agit d'une erreur ou si vous avez des questions, veuillez contacter notre support à <a href='mailto:" . htmlspecialchars($email_support_contact) . "'>" . htmlspecialchars($email_support_contact) . "</a>.</p><p>Cordialement,<br>L'équipe d'Administration " . NOM_APPLICATION . "</p>";
                $corps_html_email_supp = get_email_html_layout($sujet_email_suppression, $contenu_principal_email_supp, NOM_APPLICATION);
                envoyer_email($email_medecin_supprime, $nom_medecin_supprime_email, $sujet_email_suppression, $corps_html_email_supp);
            }

        } else {
             $_SESSION['flash_message'] = "Médecin (ID: $medecin_id_a_supprimer) non supprimé (déjà supprimé ou erreur interne).";
             $_SESSION['flash_type'] = "warning";
        }
    } else {
        $_SESSION['flash_message'] = "Erreur lors de la suppression du médecin de la BDD.";
        $_SESSION['flash_type'] = "error";
    }

} catch (PDOException $e) {
    error_log("Erreur PDO dans supprimer_medecin.php (ID: $medecin_id_a_supprimer): " . $e->getMessage());
    $_SESSION['flash_message'] = "Erreur technique lors de la suppression du médecin.";
    $_SESSION['flash_type'] = "error";
}

header("Location: " . $return_url_del_med);
exit;
?>