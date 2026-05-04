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
    header('Location: ../index.php'); // Ou une page d'erreur
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    $_SESSION['flash_message'] = "Erreur de sécurité.";
    $_SESSION['flash_type'] = "danger";
    // Rediriger vers la page d'où vient la requête
    $redirect_page = ($_SESSION['type'] === 'patient') ? 'messages_patient.php' : (($_SESSION['type'] === 'medecin') ? 'messages_medecin.php' : '../index.php');
    header('Location: ' . $redirect_page);
    exit;
}
// Invalider le token après usage pour les actions de suppression
invalidate_csrf_token(); 
// Il faudra en regénérer un sur la page cible ou s'assurer que le formulaire utilise le nouveau token de session.
// Pour simplifier, on peut juste le valider sans l'invalider pour cette action moins critique que login/paiement.
// Mais pour la suppression, il est bon de l'invalider.

$notification_id = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
$user_type_from_form = $_POST['user_type'] ?? ''; // patient ou medecin
$user_id = $_SESSION['utilisateur_id'];
$user_type_session = $_SESSION['type'];

if (!$notification_id || $user_type_from_form !== $user_type_session) {
    $_SESSION['flash_message'] = "Données invalides pour la suppression.";
    $_SESSION['flash_type'] = "error";
    $redirect_page = ($user_type_session === 'patient') ? 'messages_patient.php' : (($user_type_session === 'medecin') ? 'messages_medecin.php' : '../index.php');
    header('Location: ' . $redirect_page);
    exit;
}

$table_notifications = '';
$colonne_user_id = '';

if ($user_type_session === 'patient') {
    $table_notifications = 'notifications_patients';
    $colonne_user_id = 'patient_id';
    $redirect_page_success_fail = 'messages_patient.php';
} elseif ($user_type_session === 'medecin') {
    // Supposons une table notifications_medecins, sinon adaptez
    // Pour l'instant, les médecins n'ont pas de table de notification dédiée dans votre dump.
    // Ils voient les messages directement. Si vous voulez qu'ils suppriment des messages,
    // la logique serait dans "supprimer_message.php" et non ici.
    // Cette partie est donc un placeholder.
    $_SESSION['flash_message'] = "Fonctionnalité de suppression de notification non disponible pour les médecins pour le moment.";
    $_SESSION['flash_type'] = "info";
    header('Location: messages_medecin.php');
    exit;
    // $table_notifications = 'notifications_medecins'; // Si vous créez cette table
    // $colonne_user_id = 'medecin_id';
    // $redirect_page_success_fail = 'messages_medecin.php';
} else {
    $_SESSION['flash_message'] = "Type d'utilisateur non supporté pour cette action.";
    $_SESSION['flash_type'] = "error";
    header('Location: ../index.php');
    exit;
}

// Vérifier si la table existe avant de tenter de supprimer
$table_exists_check = $pdo->query("SHOW TABLES LIKE '$table_notifications'")->rowCount() > 0;
if (!$table_exists_check && $user_type_session === 'patient') { // Seule la table patient est gérée pour l'instant
    $_SESSION['flash_message'] = "Erreur: Le système de notification est actuellement indisponible.";
    $_SESSION['flash_type'] = "error";
    header('Location: ' . $redirect_page_success_fail);
    exit;
}


try {
    $stmt = $pdo->prepare("DELETE FROM $table_notifications WHERE id = :notification_id AND $colonne_user_id = :user_id");
    $stmt->execute([
        ':notification_id' => $notification_id,
        ':user_id' => $user_id
    ]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['flash_message'] = "Notification supprimée avec succès.";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Notification non trouvée ou vous n'êtes pas autorisé à la supprimer.";
        $_SESSION['flash_type'] = "warning";
    }
} catch (PDOException $e) {
    error_log("Erreur PDO suppression notification (User ID: $user_id, Notif ID: $notification_id): " . $e->getMessage());
    $_SESSION['flash_message'] = "Une erreur technique est survenue lors de la suppression.";
    $_SESSION['flash_type'] = "error";
}

header('Location: ' . $redirect_page_success_fail);
exit;
?>