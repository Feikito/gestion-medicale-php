<?php
session_start(); 
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 
require_once __DIR__ . '/utils/email_functions.php'; 
require_once __DIR__ . '/utils/email_template.php';

if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type'])) {
    $_SESSION['flash_message_login'] = "Vous devez être connecté pour effectuer cette action.";
    $_SESSION['flash_type_login'] = "warning";
    header('Location: ../pages/connexion.php');
    exit;
}
$user_id_annulant_rdv = $_SESSION['utilisateur_id'];
$user_type_annulant_rdv = $_SESSION['type'];

$default_redirect_page_rdv_cancel = '';
if ($user_type_annulant_rdv === 'patient') {
    $default_redirect_page_rdv_cancel = 'mes_rendez_vous_patient.php';
} elseif ($user_type_annulant_rdv === 'medecin') {
    $default_redirect_page_rdv_cancel = 'mes_rendez_vous_medecin.php';
} else {
    $_SESSION['flash_message'] = "Action non autorisée pour votre type de compte.";
    $_SESSION['flash_type'] = "error";
    header('Location: ../index.php'); 
    exit;
}
$return_url_cancel_rdv = trim($_GET['return_url'] ?? $default_redirect_page_rdv_cancel);
if (!preg_match('/^(mes_rendez_vous_(patient|medecin)\.php|espace_medecin\.php)(\?.*)?$/', basename($return_url_cancel_rdv))) {
    $return_url_cancel_rdv = $default_redirect_page_rdv_cancel;
}


$idRdv_to_cancel = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT); 
$motif_annulation_rdv = trim(strip_tags($_GET['motif'] ?? '')); 
$csrf_token_get = $_GET['csrf_token'] ?? '';

if (!validate_csrf_token($csrf_token_get)) { // Validation CSRF via GET pour cet exemple
    $_SESSION['flash_message'] = "Erreur de sécurité lors de l'annulation. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $return_url_cancel_rdv);
    exit;
}
// Invalider le token après une utilisation réussie pour une action sensible
invalidate_csrf_token();


$min_motif_length = 10; 
if (!$idRdv_to_cancel) {
    $_SESSION['flash_message'] = "ID de rendez-vous invalide ou manquant pour l'annulation.";
    $_SESSION['flash_type'] = "error";
    header('Location: ' . $return_url_cancel_rdv);
    exit;
}
if (empty($motif_annulation_rdv) || strlen($motif_annulation_rdv) < $min_motif_length) {
    $_SESSION['flash_message'] = "Un motif d'annulation d'au moins $min_motif_length caractères est requis.";
    $_SESSION['flash_type'] = "error";
    $error_param_key = ($user_type_annulant_rdv === 'patient') ? 'error_motif_rdv' : 'error_motif_rdv_action';
    header('Location: ' . $return_url_cancel_rdv . (strpos($return_url_cancel_rdv, '?') ? '&' : '?') . $error_param_key . '=' . $idRdv_to_cancel . '&rdv_id_error=' . $idRdv_to_cancel);
    exit;
}
if (strlen($motif_annulation_rdv) > 1000) { 
    $_SESSION['flash_message'] = "Le motif d'annulation est trop long (max 1000 caractères).";
    $_SESSION['flash_type'] = "error";
    $error_param_key = ($user_type_annulant_rdv === 'patient') ? 'error_motif_rdv' : 'error_motif_rdv_action';
    header('Location: ' . $return_url_cancel_rdv . (strpos($return_url_cancel_rdv, '?') ? '&' : '?') . $error_param_key . '=' . $idRdv_to_cancel . '&rdv_id_error=' . $idRdv_to_cancel);
    exit;
}

try {
    $sql_check_rdv_cancel = "";
    $params_check_rdv_cancel = [':idRdv' => $idRdv_to_cancel, ':user_id' => $user_id_annulant_rdv];

    if ($user_type_annulant_rdv === 'patient') {
        $sql_check_rdv_cancel = "SELECT * FROM rendez_vous WHERE id = :idRdv AND patient_id = :user_id AND statut IN ('en attente', 'confirmé')";
    } elseif ($user_type_annulant_rdv === 'medecin') {
        $sql_check_rdv_cancel = "SELECT * FROM rendez_vous WHERE id = :idRdv AND medecin_id = :user_id AND statut IN ('en attente', 'confirmé')";
    }

    $stmt_check_rdv_cancel = $pdo->prepare($sql_check_rdv_cancel);
    $stmt_check_rdv_cancel->execute($params_check_rdv_cancel);
    $rdv_to_cancel_data = $stmt_check_rdv_cancel->fetch(PDO::FETCH_ASSOC);

    if (!$rdv_to_cancel_data) {
        $_SESSION['flash_message'] = "Rendez-vous introuvable, déjà annulé/traité, ou vous n'êtes pas autorisé à effectuer cette action.";
        $_SESSION['flash_type'] = "warning";
        header('Location: ' . $return_url_cancel_rdv);
        exit;
    }

    if ($user_type_annulant_rdv === 'patient') {
        $datetime_rdv_to_cancel = new DateTime($rdv_to_cancel_data['date_rdv'] . ' ' . $rdv_to_cancel_data['heure_rdv']);
        $maintenant_rdv_cancel = new DateTime();
        $delai_annulation_secondes = 24 * 60 * 60; 

        if (($datetime_rdv_to_cancel->getTimestamp() - $maintenant_rdv_cancel->getTimestamp()) < $delai_annulation_secondes) {
            $_SESSION['flash_message'] = "Vous ne pouvez pas annuler un rendez-vous moins de 24 heures à l'avance.";
            $_SESSION['flash_type'] = "error";
            header('Location: ' . $return_url_cancel_rdv);
            exit;
        }
    }

    $stmt_rdv_details_for_email = $pdo->prepare("
        SELECT rv.date_rdv, rv.heure_rdv, rv.patient_id AS patient_id_rdv, 
               p.email AS patient_email_for_notif, p.nom AS patient_nom_for_notif, p.prenom AS patient_prenom_for_notif,
               m.email AS medecin_email_for_notif, m.nom AS medecin_nom_for_notif, m.prenom AS medecin_prenom_for_notif
        FROM rendez_vous rv
        JOIN patients p ON rv.patient_id = p.id
        JOIN medecins m ON rv.medecin_id = m.id
        WHERE rv.id = ?
    ");
    $stmt_rdv_details_for_email->execute([$idRdv_to_cancel]);
    $rdv_info_for_email_notif = $stmt_rdv_details_for_email->fetch(PDO::FETCH_ASSOC);

    $sql_update_rdv_status = "";
    $params_update_rdv_cancel = []; 
    $nouveau_statut_final_pour_notif = 'annulé'; 

    if ($user_type_annulant_rdv === 'patient') {
        $sql_update_rdv_status = "UPDATE rendez_vous SET statut = 'annulé', motif_annulation = :motif, vue_par_medecin = 0, supprime_par_patient = 0 WHERE id = :idRdv AND patient_id = :user_id";
        $params_update_rdv_cancel = [
            ':motif' => $motif_annulation_rdv, 
            ':idRdv' => $idRdv_to_cancel, 
            ':user_id' => $user_id_annulant_rdv
        ];
    } elseif ($user_type_annulant_rdv === 'medecin') {
        $sql_update_rdv_status = "UPDATE rendez_vous SET statut = 'annulé', motif_annulation = :motif, vue_par_patient = 0, supprime_par_medecin = 0 WHERE id = :idRdv AND medecin_id = :user_id";
        $params_update_rdv_cancel = [
            ':motif' => $motif_annulation_rdv, 
            ':idRdv' => $idRdv_to_cancel, 
            ':user_id' => $user_id_annulant_rdv
        ];
    }

    if (!empty($sql_update_rdv_status)) {
        $stmt_update_rdv = $pdo->prepare($sql_update_rdv_status);
        $update_executed = $stmt_update_rdv->execute($params_update_rdv_cancel);

        if ($update_executed) {
            $_SESSION['flash_message'] = "Le rendez-vous (ID: #$idRdv_to_cancel) a été annulé avec succès.";
            $_SESSION['flash_type'] = "success";

            if ($rdv_info_for_email_notif && function_exists('envoyer_email') && function_exists('get_email_html_layout')) {
                $date_rdv_format = date('d/m/Y', strtotime($rdv_info_for_email_notif['date_rdv']));
                $heure_rdv_format = date('H:i', strtotime($rdv_info_for_email_notif['heure_rdv']));
                $nom_patient_format = htmlspecialchars($rdv_info_for_email_notif['patient_prenom_for_notif'] . ' ' . $rdv_info_for_email_notif['patient_nom_for_notif']);
                $nom_medecin_format = "Dr. " . htmlspecialchars($rdv_info_for_email_notif['medecin_prenom_for_notif'] . ' ' . $rdv_info_for_email_notif['medecin_nom_for_notif']);
                
                $email_destinataire = '';
                $nom_destinataire_email = '';
                $sujet_notification = '';
                $contenu_principal_email_notification = '';

                if ($user_type_annulant_rdv === 'patient') { 
                    $email_destinataire = $rdv_info_for_email_notif['medecin_email_for_notif'];
                    $nom_destinataire_email = $nom_medecin_format;
                    $sujet_notification = "SANTE TV: Annulation de RDV par Patient - " . $nom_patient_format;
                    $contenu_principal_email_notification = "<p>Bonjour " . $nom_medecin_format . ",</p>
                                        <p>Le rendez-vous avec le patient <strong>" . $nom_patient_format . "</strong> prévu le <strong>" . $date_rdv_format . " à " . $heure_rdv_format . "</strong> a été <strong>annulé</strong> par le patient.</p>
                                        <p>Motif fourni par le patient :<br><em>" . nl2br(htmlspecialchars($motif_annulation_rdv)) . "</em></p>
                                        <p>Votre planning a été mis à jour. Ce créneau est de nouveau disponible.</p>
                                        <p>Cordialement,<br>Le Système SANTE TV</p>";
                } else { 
                    $email_destinataire = $rdv_info_for_email_notif['patient_email_for_notif'];
                    $nom_destinataire_email = $nom_patient_format;
                    $sujet_notification = "SANTE TV: Annulation de votre rendez-vous du " . $date_rdv_format;
                    $contenu_principal_email_notification = "<p>Bonjour " . $nom_patient_format . ",</p>
                                        <p>Votre rendez-vous avec " . $nom_medecin_format . " prévu le <strong>" . $date_rdv_format . " à " . $heure_rdv_format . "</strong> a été <strong>annulé</strong> par le médecin.</p>
                                        <p>Motif fourni par le médecin :<br><em>" . nl2br(htmlspecialchars($motif_annulation_rdv)) . "</em></p>
                                        <p>Nous vous prions de nous excuser pour ce désagrément. Vous pouvez rechercher un autre créneau ou un autre praticien si besoin via la plateforme.</p>
                                        <div class='button-container'>
                                            <a href='". $protocol . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) ."/pages/docteurs.php' class='button'>Rechercher un autre RDV</a>
                                        </div>
                                        <p>Cordialement,<br>L'équipe SANTE TV</p>";
                    
                    $table_notif_patient_exists_cancel = $pdo->query("SHOW TABLES LIKE 'notifications_patients'")->rowCount() > 0;
                    if ($table_notif_patient_exists_cancel && isset($rdv_info_for_email_notif['patient_id_rdv'])) { 
                        $notif_message = "Votre RDV du " . $date_rdv_format . " à " . $heure_rdv_format . " avec " . $nom_medecin_format . " a été annulé. Motif: " . htmlspecialchars($motif_annulation_rdv);
                        $stmt_add_notif_pat = $pdo->prepare("INSERT INTO notifications_patients (patient_id, message, type_notification, details_rdv_id) VALUES (?, ?, ?, ?)");
                        $stmt_add_notif_pat->execute([$rdv_info_for_email_notif['patient_id_rdv'], $notif_message, 'rdv_annule', $idRdv_to_cancel]);
                    } 
                }
                
                if (!empty($email_destinataire)) {
                    $corps_html_email_notif = get_email_html_layout($sujet_notification, $contenu_principal_email_notification, "SANTE TV - Notification");
                    envoyer_email($email_destinataire, $nom_destinataire_email, $sujet_notification, $corps_html_email_notif);
                }
            }
        } else {
            $_SESSION['flash_message'] = "Erreur lors de la mise à jour du statut du rendez-vous.";
            $_SESSION['flash_type'] = "error";
        }
    } else { 
        $_SESSION['flash_message'] = "Erreur interne : Type d'utilisateur invalide pour l'annulation.";
        $_SESSION['flash_type'] = "error";
    }

} catch (PDOException $e) {
    error_log("Erreur PDO annuler_rdv.php (RDV ID: $idRdv_to_cancel): " . $e->getMessage());
    $_SESSION['flash_message'] = "Une erreur technique est survenue lors de l'annulation du rendez-vous.";
    $_SESSION['flash_type'] = "error";
} catch (Exception $e) {
    error_log("Erreur annuler_rdv.php (RDV ID: $idRdv_to_cancel): " . $e->getMessage());
    $_SESSION['flash_message'] = "Une erreur générale est survenue.";
    $_SESSION['flash_type'] = "error";
}

header('Location: ' . $return_url_cancel_rdv);
exit;
?>