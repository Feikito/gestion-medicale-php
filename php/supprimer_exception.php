<?php
session_start(); 
require 'db.php'; 
// Décommentez et utilisez si vous passez à la méthode POST
// require_once __DIR__ . '/utils/csrf_utils.php'; 

// 1. Sécurité : Vérifier médecin connecté et validé
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'medecin') {
    $_SESSION['flash_message_login'] = "Accès non autorisé.";
    $_SESSION['flash_type_login'] = "warning";
    header('Location: ../pages/connexion.php');
    exit;
}
$medecin_id_delete_exception = $_SESSION['utilisateur_id'];

$stmt_check_med_valid_del_exc = $pdo->prepare("SELECT valide FROM medecins WHERE id = ?");
$stmt_check_med_valid_del_exc->execute([$medecin_id_delete_exception]);
$medecin_data_valid_del_exc = $stmt_check_med_valid_del_exc->fetch();

if (!$medecin_data_valid_del_exc || $medecin_data_valid_del_exc['valide'] != 1) {
    $_SESSION['flash_message'] = "Votre compte médecin doit être validé pour gérer vos exceptions d'horaires.";
    $_SESSION['flash_type'] = "warning";
    header('Location: gestion_disponibilites_medecin.php'); 
    exit;
}

$redirect_page_after_delete_exc = 'gestion_disponibilites_medecin.php';

// --- SÉCURITÉ : RECOMMANDATION DE PASSER À POST ---
/*
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect_page_after_delete_exc . "#exceptionsHorairesSection");
    exit;
}
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $_SESSION['flash_message'] = "Erreur de sécurité. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $redirect_page_after_delete_exc . "#exceptionsHorairesSection");
    exit;
}
$exception_id_a_supprimer = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
*/

// Code actuel utilisant GET
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
    $_SESSION['flash_message'] = "ID d'exception invalide ou manquant pour la suppression.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $redirect_page_after_delete_exc . "#exceptionsHorairesSection"); 
    exit;
}
$exception_id_a_supprimer = (int)$_GET['id']; 

try {
    if (!$pdo->query("SHOW TABLES LIKE 'exceptions_horaires_medecin'")->rowCount() > 0) {
        throw new PDOException("La table 'exceptions_horaires_medecin' n'existe pas.");
    }

    $stmt_check_exc_owner = $pdo->prepare(
        "SELECT id FROM exceptions_horaires_medecin WHERE id = :id_exception AND medecin_id = :medecin_id"
    );
    $stmt_check_exc_owner->execute([
        ':id_exception' => $exception_id_a_supprimer,
        ':medecin_id' => $medecin_id_delete_exception
    ]);
    $exception_existe_pour_medecin = $stmt_check_exc_owner->fetch();

    if (!$exception_existe_pour_medecin) {
        $_SESSION['flash_message'] = "Exception d'horaire introuvable ou non autorisée (ID: $exception_id_a_supprimer).";
        $_SESSION['flash_type'] = "warning";
        header("Location: " . $redirect_page_after_delete_exc . "#exceptionsHorairesSection");
        exit;
    }

    $stmt_delete_exception = $pdo->prepare("DELETE FROM exceptions_horaires_medecin WHERE id = ? AND medecin_id = ?");
    
    if ($stmt_delete_exception->execute([$exception_id_a_supprimer, $medecin_id_delete_exception])) {
        if ($stmt_delete_exception->rowCount() > 0) {
            $_SESSION['flash_message'] = "L'exception d'horaire (ID: $exception_id_a_supprimer) a été supprimée.";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "L'exception d'horaire (ID: $exception_id_a_supprimer) n'a pas été trouvée ou était déjà supprimée.";
            $_SESSION['flash_type'] = "warning";
        }
    } else {
        $_SESSION['flash_message'] = "Erreur lors de la suppression de l'exception d'horaire.";
        $_SESSION['flash_type'] = "error";
    }

} catch (PDOException $e) {
    error_log("Erreur PDO dans supprimer_exception.php (ID: $exception_id_a_supprimer, Medecin ID: $medecin_id_delete_exception): " . $e->getMessage());
    $_SESSION['flash_message'] = "Erreur technique lors de la suppression. " . $e->getMessage(); // Pour debug, à changer en prod
    $_SESSION['flash_type'] = "error";
}

header("Location: " . $redirect_page_after_delete_exc . "#exceptionsHorairesSection");
exit;
?>