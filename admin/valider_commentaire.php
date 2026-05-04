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

// Page de redirection par défaut (liste des commentaires)
$default_redirect_page_val_com = 'gestion_commentaires.php';
$return_url_val_com = trim($_GET['return_url'] ?? '');

$redirect_page_val_com = $default_redirect_page_val_com; // Par défaut
if (!empty($return_url_val_com)) {
    $parsed_return_url_val = parse_url($return_url_val_com);
    // S'assurer que le chemin est bien gestion_commentaires.php
    if (isset($parsed_return_url_val['path']) && strpos(basename($parsed_return_url_val['path']), 'gestion_commentaires.php') !== false) {
        // Reconstruire l'URL relative sécurisée
        $redirect_page_val_com = basename($parsed_return_url_val['path']);
        if (isset($parsed_return_url_val['query'])) {
            $redirect_page_val_com .= '?' . $parsed_return_url_val['query'];
        }
    }
}


// --- SÉCURITÉ : RECOMMANDATION DE PASSER À POST ---
/*
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect_page_val_com);
    exit;
}
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $_SESSION['flash_message'] = "Erreur de sécurité. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $redirect_page_val_com);
    exit;
}
$commentaire_id_a_valider = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
*/

// Code actuel utilisant GET
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
    $_SESSION['flash_message'] = "ID de commentaire invalide ou manquant pour la validation.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $redirect_page_val_com);
    exit;
}
$commentaire_id_a_valider = (int)$_GET['id'];

try {
    // S'assurer que la table commentaires existe
    if (!$pdo->query("SHOW TABLES LIKE 'commentaires'")->rowCount() > 0) {
        throw new PDOException("La table 'commentaires' n'existe pas.");
    }

    $stmt_check_com_val = $pdo->prepare("SELECT nom, statut FROM commentaires WHERE id = ?");
    $stmt_check_com_val->execute([$commentaire_id_a_valider]);
    $commentaire_to_validate_data = $stmt_check_com_val->fetch(PDO::FETCH_ASSOC);

    if (!$commentaire_to_validate_data) {
        $_SESSION['flash_message'] = "Commentaire (ID: $commentaire_id_a_valider) introuvable.";
        $_SESSION['flash_type'] = "warning";
        header("Location: " . $redirect_page_val_com);
        exit;
    }

    if ($commentaire_to_validate_data['statut'] !== 'en attente') {
        $_SESSION['flash_message'] = "Ce commentaire (ID: $commentaire_id_a_valider) n'est pas en attente de validation (statut: " . htmlspecialchars(ucfirst($commentaire_to_validate_data['statut'])) . ").";
        $_SESSION['flash_type'] = "info";
        header("Location: " . $redirect_page_val_com);
        exit;
    }

    $stmt_update_com_status = $pdo->prepare("UPDATE commentaires SET statut = 'validé' WHERE id = ? AND statut = 'en attente'");
    
    if ($stmt_update_com_status->execute([$commentaire_id_a_valider])) {
        if ($stmt_update_com_status->rowCount() > 0) {
            $_SESSION['flash_message'] = "Commentaire de \"" . htmlspecialchars($commentaire_to_validate_data['nom']) . "\" (ID: $commentaire_id_a_valider) validé.";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Commentaire (ID: $commentaire_id_a_valider) non modifié (déjà validé ou statut changé).";
            $_SESSION['flash_type'] = "warning";
        }
    } else {
        $_SESSION['flash_message'] = "Erreur lors de la validation du commentaire.";
        $_SESSION['flash_type'] = "error";
    }

} catch (PDOException $e) {
    error_log("Erreur PDO dans valider_commentaire.php (ID: $commentaire_id_a_valider): " . $e->getMessage());
    $_SESSION['flash_message'] = "Erreur technique lors de la validation. " . $e->getMessage(); // Pour debug, à changer en prod
    $_SESSION['flash_type'] = "error";
}

header("Location: " . $redirect_page_val_com);
exit;
?>