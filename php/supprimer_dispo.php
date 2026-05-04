<?php
session_start(); 
require 'db.php'; 
// Décommentez et utilisez si vous passez à la méthode POST pour la suppression
// require_once __DIR__ . '/utils/csrf_utils.php'; 

// 1. Sécurité : Vérifier médecin connecté et validé
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'medecin') {
    $_SESSION['flash_message_login'] = "Accès non autorisé.";
    $_SESSION['flash_type_login'] = "warning";
    header('Location: ../pages/connexion.php');
    exit;
}
$medecin_id_delete_dispo = $_SESSION['utilisateur_id'];

$stmt_check_med_valid_del_dispo = $pdo->prepare("SELECT valide FROM medecins WHERE id = ?");
$stmt_check_med_valid_del_dispo->execute([$medecin_id_delete_dispo]);
$medecin_data_valid_del_dispo = $stmt_check_med_valid_del_dispo->fetch();

if (!$medecin_data_valid_del_dispo || $medecin_data_valid_del_dispo['valide'] != 1) {
    $_SESSION['flash_message'] = "Votre compte médecin doit être validé pour gérer vos disponibilités.";
    $_SESSION['flash_type'] = "warning";
    header('Location: gestion_disponibilites_medecin.php'); 
    exit;
}

$redirect_page_after_delete_dispo = 'gestion_disponibilites_medecin.php';

// --- SÉCURITÉ : RECOMMANDATION DE PASSER À POST ---
/*
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect_page_after_delete_dispo);
    exit;
}
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $_SESSION['flash_message'] = "Erreur de sécurité. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $redirect_page_after_delete_dispo);
    exit;
}
$dispo_id_a_supprimer = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
*/

// Code actuel utilisant GET
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
    $_SESSION['flash_message'] = "ID de plage horaire invalide ou manquant pour la suppression.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $redirect_page_after_delete_dispo);
    exit;
}
$dispo_id_a_supprimer = (int)$_GET['id'];


try {
    if (!$pdo->query("SHOW TABLES LIKE 'disponibilites_medecin'")->rowCount() > 0) {
        throw new PDOException("La table 'disponibilites_medecin' n'existe pas.");
    }

    $stmt_check_dispo_owner = $pdo->prepare(
        "SELECT id FROM disponibilites_medecin WHERE id = :id_dispo AND medecin_id = :medecin_id"
    );
    $stmt_check_dispo_owner->execute([
        ':id_dispo' => $dispo_id_a_supprimer,
        ':medecin_id' => $medecin_id_delete_dispo
    ]);
    $dispo_existe_pour_medecin = $stmt_check_dispo_owner->fetch();

    if (!$dispo_existe_pour_medecin) {
        $_SESSION['flash_message'] = "Plage horaire introuvable ou non autorisée (ID: $dispo_id_a_supprimer).";
        $_SESSION['flash_type'] = "warning";
        header("Location: " . $redirect_page_after_delete_dispo);
        exit;
    }

    $stmt_delete_dispo = $pdo->prepare("DELETE FROM disponibilites_medecin WHERE id = ? AND medecin_id = ?");
    
    if ($stmt_delete_dispo->execute([$dispo_id_a_supprimer, $medecin_id_delete_dispo])) {
        if ($stmt_delete_dispo->rowCount() > 0) {
            $_SESSION['flash_message'] = "La plage horaire régulière (ID: $dispo_id_a_supprimer) a été supprimée.";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "La plage horaire (ID: $dispo_id_a_supprimer) n'a pas été trouvée ou était déjà supprimée.";
            $_SESSION['flash_type'] = "warning";
        }
    } else {
        $_SESSION['flash_message'] = "Erreur lors de la suppression de la plage horaire.";
        $_SESSION['flash_type'] = "error";
    }

} catch (PDOException $e) {
    error_log("Erreur PDO dans supprimer_dispo.php (Dispo ID: $dispo_id_a_supprimer, Medecin ID: $medecin_id_delete_dispo): " . $e->getMessage());
    $_SESSION['flash_message'] = "Une erreur technique est survenue.";
    $_SESSION['flash_type'] = "error";
}

header("Location: " . $redirect_page_after_delete_dispo);
exit;
?>