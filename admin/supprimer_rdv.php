<?php
session_start(); 
require '../php/db.php'; 
require_once '../php/utils/logger.php'; // AJOUTÉ

if (!isset($_SESSION['admin_id'])) { 
    $_SESSION['flash_message_login'] = "Accès refusé.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/admin-login.php'); 
    exit;
}

$default_redirect_page_del_rdv = 'gestion_rdv.php';
$return_url_del_rdv = trim($_GET['return_url'] ?? $default_redirect_page_del_rdv);

$redirect_page_del_rdv = $default_redirect_page_del_rdv;
if (!empty($return_url_del_rdv)) {
    $parsed_return_url_del_rdv = parse_url($return_url_del_rdv);
    if (isset($parsed_return_url_del_rdv['path']) && basename($parsed_return_url_del_rdv['path']) === 'gestion_rdv.php') {
        $redirect_page_del_rdv = basename($parsed_return_url_del_rdv['path']);
        if (isset($parsed_return_url_del_rdv['query'])) {
            $redirect_page_del_rdv .= '?' . $parsed_return_url_del_rdv['query'];
        }
    }
}

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
    $_SESSION['flash_message'] = "ID de rendez-vous invalide ou manquant pour la suppression.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $redirect_page_del_rdv);
    exit;
}
$rdv_id_a_supprimer = (int)$_GET['id'];

try {
    if (!$pdo->query("SHOW TABLES LIKE 'rendez_vous'")->rowCount() > 0) {
        throw new PDOException("La table 'rendez_vous' n'existe pas.");
    }

    $stmt_get_rdv_info = $pdo->prepare("SELECT patient_id, medecin_id, date_rdv, heure_rdv FROM rendez_vous WHERE id = ?");
    $stmt_get_rdv_info->execute([$rdv_id_a_supprimer]);
    $rdv_info_for_log = $stmt_get_rdv_info->fetch(PDO::FETCH_ASSOC);

    if (!$rdv_info_for_log) {
        $_SESSION['flash_message'] = "Rendez-vous (ID: $rdv_id_a_supprimer) introuvable ou déjà supprimé.";
        $_SESSION['flash_type'] = "warning";
        header("Location: " . $redirect_page_del_rdv);
        exit;
    }

    $stmt_delete_rdv_admin = $pdo->prepare("DELETE FROM rendez_vous WHERE id = ?");
    
    if ($stmt_delete_rdv_admin->execute([$rdv_id_a_supprimer])) {
        if ($stmt_delete_rdv_admin->rowCount() > 0) {
            // Journalisation de l'action
            log_action_application(
                $pdo,
                'SUPPRESSION_RDV_ADMIN',
                "Le rendez-vous ID: #$rdv_id_a_supprimer (Patient ID: {$rdv_info_for_log['patient_id']}, Médecin ID: {$rdv_info_for_log['medecin_id']}, Date: {$rdv_info_for_log['date_rdv']} {$rdv_info_for_log['heure_rdv']}) a été supprimé par un administrateur.",
                $rdv_id_a_supprimer,
                'rendez_vous',
                $rdv_info_for_log // Stocker les infos du RDV supprimé
            );

            $_SESSION['flash_message'] = "Rendez-vous (ID: #$rdv_id_a_supprimer) supprimé.";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Rendez-vous (ID: $rdv_id_a_supprimer) non trouvé ou déjà supprimé.";
            $_SESSION['flash_type'] = "warning";
        }
    } else {
        $_SESSION['flash_message'] = "Erreur lors de la suppression du rendez-vous.";
        $_SESSION['flash_type'] = "error";
    }

} catch (PDOException $e) {
    error_log("Erreur PDO dans supprimer_rdv.php (ID: $rdv_id_a_supprimer): " . $e->getMessage());
    $_SESSION['flash_message'] = "Erreur technique lors de la suppression.";
    $_SESSION['flash_type'] = "error";
}

header("Location: " . $redirect_page_del_rdv);
exit;
?>