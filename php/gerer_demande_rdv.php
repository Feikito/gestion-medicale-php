<?php
session_start(); 
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 
require_once __DIR__ . '/utils/email_functions.php'; 
require_once __DIR__ . '/utils/email_template.php';

if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'medecin') {
    $_SESSION['flash_message_login'] = "Accès non autorisé. Veuillez vous connecter en tant que médecin.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/connexion.php');
    exit;
}
$medecin_id_action_rdv = $_SESSION['utilisateur_id'];

$stmt_check_med_valid_rdv = $pdo->prepare("SELECT nom, prenom, email, valide FROM medecins WHERE id = ?");
$stmt_check_med_valid_rdv->execute([$medecin_id_action_rdv]);
$medecin_data_action_rdv = $stmt_check_med_valid_rdv->fetch(PDO::FETCH_ASSOC);

if (!$medecin_data_action_rdv || $medecin_data_action_rdv['valide'] != 1) {
    $_SESSION['flash_message'] = "Votre compte médecin doit être validé par un administrateur pour gérer les rendez-vous.";
    $_SESSION['flash_type'] = "warning";
    header('Location: espace_medecin.php'); 
    exit;
}
$nom_medecin_pour_email = "Dr. " . htmlspecialchars($medecin_data_action_rdv['prenom'] . ' ' . $medecin_data_action_rdv['nom']);

$default_redirect_page_action_rdv = 'mes_rendez_vous_medecin.php';
$return_url_action_rdv = trim($_GET['return_url'] ?? $default_redirect_page_action_rdv);
// Sécurisation basique de return_url pour éviter les redirections ouvertes
if (!preg_match('/^(mes_rendez_vous_medecin\.php|espace_medecin\.php)(\?.*)?$/', basename($return_url_action_rdv))) {
    $return_url_action_rdv = $default_redirect_page_action_rdv;
}


$idRdv_action = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT); 
$action_rdv = trim($_GET['action'] ?? ''); 
$motif_action_rdv = trim(strip_tags($_GET['motif'] ?? '')); 

if (!$idRdv_action) {
    $_SESSION['flash_message'] = "ID de rendez-vous invalide ou manquant.";
    $_SESSION['flash_type'] = "error";
    header('Location: ' . $return_url_action_rdv);
    exit;
}
if (!in_array($action_rdv, ['accepter', 'refuser'])) {
    $_SESSION['flash_message'] = "Action non valide spécifiée pour le rendez-vous.";
    $_SESSION['flash_type'] = "error";
    header('Location: ' . $return_url_action_rdv);
    exit;
}
if ($action_rdv === 'refuser' && empty($motif_action_rdv)) {
    $motif_action_rdv = "Demande non acceptée par le médecin."; 
}
if (!empty($motif_action_rdv) && strlen($motif_action_rdv) > 1000) {
    $_SESSION['flash_message'] = "Le motif fourni est trop long (max 1000 caractères).";
    $_SESSION['flash_type'] = "error";
    header('Location: ' . $return_url_action_rdv . (strpos($return_url_action_rdv, '?') ? '&' : '?') . 'error_motif_rdv_action=' . $idRdv_action . '&rdv_id_error=' . $idRdv_action);
    exit;
}

try {
    $stmt_get_rdv_action = $pdo->prepare("
        SELECT rv.*, 
               p.email AS patient_email_rdv, p.nom AS patient_nom_rdv, p.prenom AS patient_prenom_rdv
        FROM rendez_vous rv
        JOIN patients p ON rv.patient_id = p.id
        WHERE rv.id = :idRdv AND rv.medecin_id = :medecin_id AND rv.statut = 'en attente'
    ");
    $stmt_get_rdv_action->execute([':idRdv' => $idRdv_action, ':medecin_id' => $medecin_id_action_rdv]);
    $rdv_data_action = $stmt_get_rdv_action->fetch(PDO::FETCH_ASSOC);

    if (!$rdv_data_action) {
        $_SESSION['flash_message'] = "Rendez-vous introuvable, déjà traité, ou vous n'êtes pas autorisé à effectuer cette action.";
        $_SESSION['flash_type'] = "warning";
        header('Location: ' . $return_url_action_rdv);
        exit;
    }

    $nouveau_statut_rdv = '';
    $email_sujet_patient = '';
    $contenu_principal_email_patient = '';
    $message_flash_succes = '';
    $params_update_action_rdv = [];

    $nom_patient_pour_email = htmlspecialchars($rdv_data_action['patient_prenom_rdv'] . ' ' . $rdv_data_action['patient_nom_rdv']);
    $date_rdv_format_email = date('d/m/Y', strtotime($rdv_data_action['date_rdv']));
    $heure_rdv_format_email = date('H:i', strtotime($rdv_data_action['heure_rdv']));

    if ($action_rdv === 'accepter') {
        $nouveau_statut_rdv = 'confirmé';
        $sql_update_action_rdv = "UPDATE rendez_vous SET statut = :nouveau_statut, vue_par_patient = 0, motif_annulation = NULL WHERE id = :idRdv";
        $params_update_action_rdv = [':nouveau_statut' => $nouveau_statut_rdv, ':idRdv' => $idRdv_action];
        
        $message_flash_succes = "Rendez-vous (ID: #$idRdv_action) confirmé avec succès. Le patient sera notifié.";
        $email_sujet_patient = "Confirmation de votre rendez-vous SANTE TV";
        $contenu_principal_email_patient = "
            <p>Bonjour " . $nom_patient_pour_email . ",</p>
            <p>Bonne nouvelle ! Votre demande de rendez-vous avec " . $nom_medecin_pour_email . " 
               pour le <strong>" . $date_rdv_format_email . " à " . $heure_rdv_format_email . "</strong> 
               est maintenant <strong>confirmée</strong>.</p>
            <p>Merci de vous présenter à l'heure. En cas d'empêchement, veuillez annuler via votre espace patient au moins 24h à l'avance.</p>
            <p>Cordialement,<br>L'équipe SANTE TV</p>";

    } elseif ($action_rdv === 'refuser') {
        $check_enum_refuse_action = $pdo->query("SHOW COLUMNS FROM rendez_vous LIKE 'statut'");
        $enum_def_refuse_action = $check_enum_refuse_action->fetch(PDO::FETCH_ASSOC);
        if ($enum_def_refuse_action && strpos($enum_def_refuse_action['Type'], "'refusé'") !== false) {
            $nouveau_statut_rdv = 'refusé';
        } else {
            $nouveau_statut_rdv = 'annulé'; 
        }
        
        $sql_update_action_rdv = "UPDATE rendez_vous SET statut = :nouveau_statut, motif_annulation = :motif, vue_par_patient = 0 WHERE id = :idRdv";
        $params_update_action_rdv = [
            ':nouveau_statut' => $nouveau_statut_rdv, 
            ':motif' => $motif_action_rdv, 
            ':idRdv' => $idRdv_action
        ];

        $message_flash_succes = "Rendez-vous (ID: #$idRdv_action) " . htmlspecialchars($nouveau_statut_rdv) . ". Le patient sera notifié.";
        $email_sujet_patient = "Information concernant votre demande de rendez-vous SANTE TV";
        $contenu_principal_email_patient = "
            <p>Bonjour " . $nom_patient_pour_email . ",</p>
            <p>Nous vous informons que votre demande de rendez-vous avec " . $nom_medecin_pour_email . " 
               pour le <strong>" . $date_rdv_format_email . " à " . $heure_rdv_format_email . "</strong> 
               n'a malheureusement pas pu être acceptée (statut : " . htmlspecialchars(ucfirst($nouveau_statut_rdv)) . ").</p>";
        if ($motif_action_rdv !== "Demande non acceptée par le médecin.") { 
             $contenu_principal_email_patient .= "<p>Motif fourni par le médecin :<br><em>" . nl2br(htmlspecialchars($motif_action_rdv)) . "</em></p>";
        }
        $contenu_principal_email_patient .= "
            <p>Nous vous invitons à rechercher un autre créneau ou un autre praticien si besoin sur notre plateforme.</p>
            <p>Cordialement,<br>L'équipe SANTE TV</p>";
    }

    if (!empty($sql_update_action_rdv)) {
        $stmt_update_action_rdv = $pdo->prepare($sql_update_action_rdv);
        if ($stmt_update_action_rdv->execute($params_update_action_rdv)) {
            $_SESSION['flash_message'] = $message_flash_succes;
            $_SESSION['flash_type'] = "success";

            if (!empty($rdv_data_action['patient_email_rdv']) && function_exists('envoyer_email') && function_exists('get_email_html_layout')) {
                $corps_html_email_patient = get_email_html_layout($email_sujet_patient, $contenu_principal_email_patient, "SANTE TV");
                envoyer_email(
                    $rdv_data_action['patient_email_rdv'], 
                    $nom_patient_pour_email, 
                    $email_sujet_patient, 
                    $corps_html_email_patient
                );
            }
            
            $notif_message_patient = ($action_rdv === 'accepter') ? 
                "Votre RDV du ".$date_rdv_format_email." à ".$heure_rdv_format_email." avec ".$nom_medecin_pour_email." a été confirmé." :
                "Votre demande de RDV du ".$date_rdv_format_email." à ".$heure_rdv_format_email." avec ".$nom_medecin_pour_email." a été ".($nouveau_statut_rdv === 'refusé' ? 'refusée' : 'annulée').". Motif: ".htmlspecialchars($motif_action_rdv);
            $notif_type = ($action_rdv === 'accepter') ? 'rdv_confirme' : ($nouveau_statut_rdv === 'refusé' ? 'rdv_refuse' : 'rdv_annule');
            
            $table_notif_patient_exists_action = $pdo->query("SHOW TABLES LIKE 'notifications_patients'")->rowCount() > 0;
            if($table_notif_patient_exists_action) {
                $stmt_add_notif = $pdo->prepare("INSERT INTO notifications_patients (patient_id, message, type_notification, details_rdv_id) VALUES (?, ?, ?, ?)");
                $stmt_add_notif->execute([$rdv_data_action['patient_id'], $notif_message_patient, $notif_type, $idRdv_action]);
            }

        } else {
            $_SESSION['flash_message'] = "Erreur lors de la mise à jour du statut du rendez-vous.";
            $_SESSION['flash_type'] = "error";
        }
    }

} catch (PDOException $e) {
    error_log("Erreur PDO gerer_demande_rdv.php (RDV ID: $idRdv_action): " . $e->getMessage());
    $_SESSION['flash_message'] = "Une erreur technique est survenue lors du traitement de la demande.";
    $_SESSION['flash_type'] = "error";
}

header('Location: ' . $return_url_action_rdv);
exit;
?>