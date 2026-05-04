<?php
session_start(); 

// Ce fichier est dans admin/
require '../php/db.php'; 
// Décommentez et utilisez si vous passez à la méthode POST
// require_once '../php/utils/csrf_utils.php'; 

// 1. Sécurité : Vérifier admin connecté
if (!isset($_SESSION['admin_id'])) { 
    $_SESSION['flash_message_login'] = "Accès refusé.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/admin-login.php'); 
    exit;
}

// Page de redirection par défaut (liste des patients)
$default_redirect_page_del_pat = 'gestion_patients.php';
$return_url_del_pat = trim($_GET['return_url'] ?? '');

$redirect_page_del_pat = $default_redirect_page_del_pat; // Par défaut
if (!empty($return_url_del_pat)) {
    $parsed_return_url_del_pat = parse_url($return_url_del_pat);
    // Si la suppression vient de la page de liste, on essaie de conserver ses paramètres.
    // Si elle vient de voir_patient.php, on redirige vers la liste par défaut.
    if (isset($parsed_return_url_del_pat['path']) && strpos(basename($parsed_return_url_del_pat['path']), 'gestion_patients.php') !== false) {
        $redirect_page_del_pat = basename($parsed_return_url_del_pat['path']);
        if (isset($parsed_return_url_del_pat['query'])) {
            $redirect_page_del_pat .= '?' . $parsed_return_url_del_pat['query'];
        }
    }
}

// --- SÉCURITÉ : RECOMMANDATION DE PASSER À POST ---
/*
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect_page_del_pat);
    exit;
}
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $_SESSION['flash_message'] = "Erreur de sécurité. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $redirect_page_del_pat);
    exit;
}
$patient_id_a_supprimer = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
*/

// Code actuel utilisant GET
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
    $_SESSION['flash_message'] = "ID de patient invalide ou manquant pour la suppression.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $redirect_page_del_pat);
    exit;
}
$patient_id_a_supprimer = (int)$_GET['id'];

try {
    // S'assurer que la table patients existe
    if (!$pdo->query("SHOW TABLES LIKE 'patients'")->rowCount() > 0) {
        throw new PDOException("La table 'patients' n'existe pas.");
    }

    $stmt_patient_info_del = $pdo->prepare("SELECT nom, prenom, photo FROM patients WHERE id = ?");
    $stmt_patient_info_del->execute([$patient_id_a_supprimer]);
    $patient_to_delete_data = $stmt_patient_info_del->fetch(PDO::FETCH_ASSOC);

    if (!$patient_to_delete_data) {
        $_SESSION['flash_message'] = "Patient (ID: $patient_id_a_supprimer) introuvable. Suppression impossible.";
        $_SESSION['flash_type'] = "warning";
        header("Location: " . $redirect_page_del_pat);
        exit;
    }
    $nom_patient_pour_message_del = htmlspecialchars($patient_to_delete_data['prenom'] . ' ' . $patient_to_delete_data['nom']);
    $photo_path_db_to_delete_pat = $patient_to_delete_data['photo'];

    // Supprimer l'entrée du patient (ON DELETE CASCADE gérera les RDV et messages)
    $stmt_delete_patient = $pdo->prepare("DELETE FROM patients WHERE id = ?");
    
    if ($stmt_delete_patient->execute([$patient_id_a_supprimer])) {
        if ($stmt_delete_patient->rowCount() > 0) {
            if (!empty($photo_path_db_to_delete_pat)) {
                $full_photo_path_to_delete_pat = __DIR__ . '/../' . $photo_path_db_to_delete_pat; 
                if (file_exists($full_photo_path_to_delete_pat)) {
                    @unlink($full_photo_path_to_delete_pat); 
                }
            }
            
            $_SESSION['flash_message'] = "Patient \"$nom_patient_pour_message_del\" (ID: $patient_id_a_supprimer) et données associées supprimés.";
            $_SESSION['flash_type'] = "success";
        } else {
             $_SESSION['flash_message'] = "Patient (ID: $patient_id_a_supprimer) non supprimé (déjà supprimé ?).";
             $_SESSION['flash_type'] = "warning";
        }
    } else {
        $_SESSION['flash_message'] = "Erreur lors de la suppression du patient de la BDD.";
        $_SESSION['flash_type'] = "error";
    }

} catch (PDOException $e) {
    error_log("Erreur PDO dans supprimer_patient.php (ID: $patient_id_a_supprimer): " . $e->getMessage());
    $_SESSION['flash_message'] = "Erreur technique lors de la suppression du patient. Des données liées peuvent exister. Détails: " . $e->getMessage(); // Pour debug, à changer en prod
    $_SESSION['flash_type'] = "error";
}

// Toujours rediriger vers la liste après une tentative de suppression
header("Location: gestion_patients.php"); 
exit;
?>