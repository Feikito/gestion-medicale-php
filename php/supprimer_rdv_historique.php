<?php
session_start();
require 'db.php';
require_once __DIR__ . '/utils/csrf_utils.php';

if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type'])) {
    $_SESSION['flash_message_login'] = "Accès non autorisé.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/connexion.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php'); 
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) {
    $_SESSION['flash_message'] = "Erreur de sécurité.";
    $_SESSION['flash_type'] = "danger";
    $redirect_page = ($_SESSION['type'] === 'patient') ? 'mes_rendez_vous_patient.php' : (($_SESSION['type'] === 'medecin') ? 'mes_rendez_vous_medecin.php' : '../index.php');
    header('Location: ' . $redirect_page);
    exit;
}

$rdv_id = filter_input(INPUT_POST, 'rdv_id', FILTER_VALIDATE_INT);
$user_type_from_form = $_POST['user_type'] ?? ''; 
$user_id = $_SESSION['utilisateur_id'];
$user_type_session = $_SESSION['type'];

$redirect_page_success_fail = '../index.php'; // Fallback
if ($user_type_session === 'patient') {
    $redirect_page_success_fail = 'mes_rendez_vous_patient.php';
} elseif ($user_type_session === 'medecin') {
    $redirect_page_success_fail = 'mes_rendez_vous_medecin.php';
}


if (!$rdv_id || $user_type_from_form !== $user_type_session || !in_array($user_type_session, ['patient', 'medecin'])) {
    $_SESSION['flash_message'] = "Données invalides pour la suppression du rendez-vous.";
    $_SESSION['flash_type'] = "error";
    header('Location: ' . $redirect_page_success_fail);
    exit;
}

try {
    // Vérifier d'abord que le RDV est bien passé et appartient à l'utilisateur
    $stmt_check = null;
    if ($user_type_session === 'patient') {
        $stmt_check = $pdo->prepare("SELECT id FROM rendez_vous WHERE id = :rdv_id AND patient_id = :user_id AND CONCAT(date_rdv, ' ', heure_rdv) < NOW()");
    } elseif ($user_type_session === 'medecin') {
        $stmt_check = $pdo->prepare("SELECT id FROM rendez_vous WHERE id = :rdv_id AND medecin_id = :user_id AND CONCAT(date_rdv, ' ', heure_rdv) < NOW()");
    }
    
    if ($stmt_check) {
        $stmt_check->execute([':rdv_id' => $rdv_id, ':user_id' => $user_id]);
        if (!$stmt_check->fetch()) {
            $_SESSION['flash_message'] = "Rendez-vous non trouvé, non expiré, ou vous n'êtes pas autorisé à le supprimer.";
            $_SESSION['flash_type'] = "warning";
            header('Location: ' . $redirect_page_success_fail);
            exit;
        }
    } else {
        throw new Exception("Erreur interne lors de la vérification du RDV.");
    }

    // Marquer comme supprimé pour l'utilisateur concerné
    $colonne_a_updater = ($user_type_session === 'patient') ? 'supprime_par_patient' : 'supprime_par_medecin';
    
    $sql_update = "UPDATE rendez_vous SET $colonne_a_updater = 1 WHERE id = :rdv_id AND $colonne_a_updater = 0";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([':rdv_id' => $rdv_id]);

    if ($stmt_update->rowCount() > 0) {
        $_SESSION['flash_message'] = "Le rendez-vous a été supprimé de votre historique.";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Le rendez-vous était déjà marqué comme supprimé ou une erreur est survenue.";
        $_SESSION['flash_type'] = "info";
    }

} catch (Exception $e) {
    error_log("Erreur suppression RDV historique (User ID: $user_id, RDV ID: $rdv_id): " . $e->getMessage());
    $_SESSION['flash_message'] = "Une erreur technique est survenue lors de la suppression.";
    $_SESSION['flash_type'] = "error";
}

header('Location: ' . $redirect_page_success_fail);
exit;
?>