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

// Page de redirection par défaut
$default_redirect_page_rej_com = 'gestion_commentaires.php';
$return_url_rej_com = trim($_GET['return_url'] ?? '');

$redirect_page_rej_com = $default_redirect_page_rej_com; // Par défaut
if (!empty($return_url_rej_com)) {
    $parsed_return_url_rej = parse_url($return_url_rej_com);
    if (isset($parsed_return_url_rej['path']) && strpos(basename($parsed_return_url_rej['path']), 'gestion_commentaires.php') !== false) {
        $redirect_page_rej_com = basename($parsed_return_url_rej['path']);
        if (isset($parsed_return_url_rej['query'])) {
            $redirect_page_rej_com .= '?' . $parsed_return_url_rej['query'];
        }
    }
}


// --- SÉCURITÉ : RECOMMANDATION DE PASSER À POST ---
/*
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect_page_rej_com);
    exit;
}
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $_SESSION['flash_message'] = "Erreur de sécurité. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $redirect_page_rej_com);
    exit;
}
$commentaire_id_a_rejeter = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
*/

// Code actuel utilisant GET
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
    $_SESSION['flash_message'] = "ID de commentaire invalide ou manquant pour le refus.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $redirect_page_rej_com);
    exit;
}
$commentaire_id_a_rejeter = (int)$_GET['id'];

try {
    // S'assurer que la table commentaires existe
    if (!$pdo->query("SHOW TABLES LIKE 'commentaires'")->rowCount() > 0) {
        throw new PDOException("La table 'commentaires' n'existe pas.");
    }

    $stmt_check_com_rej = $pdo->prepare("SELECT nom, statut FROM commentaires WHERE id = ?");
    $stmt_check_com_rej->execute([$commentaire_id_a_rejeter]);
    $commentaire_to_reject_data = $stmt_check_com_rej->fetch(PDO::FETCH_ASSOC);

    if (!$commentaire_to_reject_data) {
        $_SESSION['flash_message'] = "Commentaire (ID: $commentaire_id_a_rejeter) introuvable.";
        $_SESSION['flash_type'] = "warning";
        header("Location: " . $redirect_page_rej_com);
        exit;
    }

    if ($commentaire_to_reject_data['statut'] !== 'en attente') {
        $_SESSION['flash_message'] = "Ce commentaire (ID: $commentaire_id_a_rejeter) n'est pas en attente (statut: " . htmlspecialchars(ucfirst($commentaire_to_reject_data['statut'])) . "). Action de refus non applicable.";
        $_SESSION['flash_type'] = "info";
        header("Location: " . $redirect_page_rej_com);
        exit;
    }

    $stmt_update_com_status_rej = $pdo->prepare("UPDATE commentaires SET statut = 'refusé' WHERE id = ? AND statut = 'en attente'");
    
    if ($stmt_update_com_status_rej->execute([$commentaire_id_a_rejeter])) {
        if ($stmt_update_com_status_rej->rowCount() > 0) {
            $_SESSION['flash_message'] = "Commentaire de \"" . htmlspecialchars($commentaire_to_reject_data['nom']) . "\" (ID: $commentaire_id_a_rejeter) marqué comme refusé.";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Commentaire (ID: $commentaire_id_a_rejeter) non modifié (déjà traité ou statut changé).";
            $_SESSION['flash_type'] = "warning";
        }
    } else {
        $_SESSION['flash_message'] = "Erreur lors de la tentative de refus du commentaire.";
        $_SESSION['flash_type'] = "error";
    }

} catch (PDOException $e) {
    error_log("Erreur PDO dans rejeter_commentaire.php (ID: $commentaire_id_a_rejeter): " . $e->getMessage());
    $_SESSION['flash_message'] = "Erreur technique lors du refus. " . $e->getMessage(); // Pour debug, à changer en prod
    $_SESSION['flash_type'] = "error";
}

header("Location: " . $redirect_page_rej_com);
exit;
?>