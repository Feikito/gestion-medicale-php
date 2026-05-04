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
$default_redirect_page_del_com = 'gestion_commentaires.php';
$return_url_del_com = trim($_GET['return_url'] ?? '');

$redirect_page_del_com = $default_redirect_page_del_com; // Par défaut
if (!empty($return_url_del_com)) {
    $parsed_return_url_del = parse_url($return_url_del_com);
    if (isset($parsed_return_url_del['path']) && strpos(basename($parsed_return_url_del['path']), 'gestion_commentaires.php') !== false) {
        $redirect_page_del_com = basename($parsed_return_url_del['path']);
        if (isset($parsed_return_url_del['query'])) {
            $redirect_page_del_com .= '?' . $parsed_return_url_del['query'];
        }
    }
}

// --- SÉCURITÉ : RECOMMANDATION DE PASSER À POST ---
/*
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect_page_del_com);
    exit;
}
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $_SESSION['flash_message'] = "Erreur de sécurité. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $redirect_page_del_com);
    exit;
}
$commentaire_id_a_supprimer = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
*/

// Code actuel utilisant GET
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
    $_SESSION['flash_message'] = "ID de commentaire invalide ou manquant pour la suppression.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $redirect_page_del_com);
    exit;
}
$commentaire_id_a_supprimer = (int)$_GET['id'];

try {
    // S'assurer que la table commentaires existe
    if (!$pdo->query("SHOW TABLES LIKE 'commentaires'")->rowCount() > 0) {
        throw new PDOException("La table 'commentaires' n'existe pas.");
    }

    $stmt_check_com_del = $pdo->prepare("SELECT id, nom FROM commentaires WHERE id = ?");
    $stmt_check_com_del->execute([$commentaire_id_a_supprimer]);
    $commentaire_to_delete_data = $stmt_check_com_del->fetch(PDO::FETCH_ASSOC);

    if (!$commentaire_to_delete_data) {
        $_SESSION['flash_message'] = "Commentaire (ID: $commentaire_id_a_supprimer) introuvable ou déjà supprimé.";
        $_SESSION['flash_type'] = "warning";
        header("Location: " . $redirect_page_del_com);
        exit;
    }
    $nom_auteur_pour_message = htmlspecialchars($commentaire_to_delete_data['nom']);

    $stmt_delete_commentaire = $pdo->prepare("DELETE FROM commentaires WHERE id = ?");
    
    if ($stmt_delete_commentaire->execute([$commentaire_id_a_supprimer])) {
        if ($stmt_delete_commentaire->rowCount() > 0) {
            $_SESSION['flash_message'] = "Commentaire de \"$nom_auteur_pour_message\" (ID: $commentaire_id_a_supprimer) supprimé.";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Commentaire (ID: $commentaire_id_a_supprimer) non trouvé ou déjà supprimé.";
            $_SESSION['flash_type'] = "warning";
        }
    } else {
        $_SESSION['flash_message'] = "Erreur lors de la suppression du commentaire.";
        $_SESSION['flash_type'] = "error";
    }

} catch (PDOException $e) {
    error_log("Erreur PDO dans supprimer_commentaire.php (ID: $commentaire_id_a_supprimer): " . $e->getMessage());
    $_SESSION['flash_message'] = "Erreur technique lors de la suppression. " . $e->getMessage(); // Pour debug, à changer en prod
    $_SESSION['flash_type'] = "error";
}

header("Location: " . $redirect_page_del_com);
exit;
?>