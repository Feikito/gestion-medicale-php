<?php
session_start(); 
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 
// require_once __DIR__ . '/utils/email_functions.php'; // Pour notifier l'utilisateur

// 1. Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || !in_array($_SESSION['type'], ['patient', 'medecin'])) {
    $_SESSION['flash_message_login'] = "Accès non autorisé. Veuillez vous connecter.";
    $_SESSION['flash_type_login'] = "warning";
    header('Location: ../pages/connexion.php'); // Rediriger vers la page de connexion dédiée
    exit;
}

$user_id_change_pass = $_SESSION['utilisateur_id'];
$user_type_change_pass = $_SESSION['type'];
$table_name_change_pass = ($user_type_change_pass === 'patient') ? 'patients' : 'medecins';

// Les pages de profil (profil_patient.php, profil_medecin.php) sont dans le dossier php/
$default_redirect_profil = ($user_type_change_pass === 'patient') ? 'profil_patient.php' : 'profil_medecin.php';
$form_origin_change_pass = $_POST['form_origin_pass'] ?? $default_redirect_profil; 

// 2. S'assurer que c'est une méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . $form_origin_change_pass); 
    exit;
}

// 3. VALIDATION CSRF
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $_SESSION['flash_message'] = "Erreur de sécurité lors du changement de mot de passe. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    $session_errors_key_change_pass = ($user_type_change_pass === 'patient') ? 'form_errors_change_pass_patient' : 'form_errors_change_pass_med';
    unset($_SESSION[$session_errors_key_change_pass]); // Nettoyer pour éviter confusion
    header("Location: " . $form_origin_change_pass);
    exit;
}

// 4. Récupération et validation des données
$ancien_mdp_soumis = $_POST['ancien_motdepasse'] ?? '';
$nouveau_mdp_soumis = $_POST['nouveau_motdepasse'] ?? '';
$confirmer_mdp_soumis = $_POST['confirmer_motdepasse'] ?? '';
$min_password_length_change = 8;

$session_errors_key_change_pass = ($user_type_change_pass === 'patient') ? 'form_errors_change_pass_patient' : 'form_errors_change_pass_med';
$_SESSION[$session_errors_key_change_pass] = []; 
$errors_change_pass = &$_SESSION[$session_errors_key_change_pass]; 

if (empty($ancien_mdp_soumis)) {
    $errors_change_pass['ancien_motdepasse'] = "L'ancien mot de passe est requis.";
}
if (empty($nouveau_mdp_soumis)) {
    $errors_change_pass['nouveau_motdepasse'] = "Le nouveau mot de passe est requis.";
} elseif (strlen($nouveau_mdp_soumis) < $min_password_length_change) {
    $errors_change_pass['nouveau_motdepasse'] = "Le nouveau mot de passe doit contenir au moins $min_password_length_change caractères.";
}
if (empty($confirmer_mdp_soumis)) {
    $errors_change_pass['confirmer_motdepasse'] = "Veuillez confirmer le nouveau mot de passe.";
} elseif ($nouveau_mdp_soumis !== $confirmer_mdp_soumis) {
    $errors_change_pass['confirmer_motdepasse'] = "Le nouveau mot de passe et sa confirmation ne correspondent pas.";
}
if (!empty($ancien_mdp_soumis) && !empty($nouveau_mdp_soumis) && $ancien_mdp_soumis === $nouveau_mdp_soumis) {
    $errors_change_pass['nouveau_motdepasse'] = "Le nouveau mot de passe doit être différent de l'ancien.";
}

if (!empty($errors_change_pass)) {
    $_SESSION['flash_message'] = "Erreurs dans le formulaire de changement de mot de passe. Veuillez corriger.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $form_origin_change_pass);
    exit;
}

// 5. Vérifier l'ancien mot de passe
try {
    $stmt_get_old_pass = $pdo->prepare("SELECT mot_de_passe FROM $table_name_change_pass WHERE id = ?");
    $stmt_get_old_pass->execute([$user_id_change_pass]);
    $user_data_pass = $stmt_get_old_pass->fetch(PDO::FETCH_ASSOC);

    if (!$user_data_pass || !password_verify($ancien_mdp_soumis, $user_data_pass['mot_de_passe'])) {
        $_SESSION['flash_message'] = "L'ancien mot de passe que vous avez saisi est incorrect.";
        $_SESSION['flash_type'] = "error";
        $errors_change_pass['ancien_motdepasse'] = "Ancien mot de passe incorrect.";
        $_SESSION[$session_errors_key_change_pass] = $errors_change_pass;
        header("Location: " . $form_origin_change_pass);
        exit;
    }

    // 6. Mettre à jour le mot de passe
    $nouveau_mdp_hashed_change = password_hash($nouveau_mdp_soumis, PASSWORD_DEFAULT);
    
    $stmt_update_pass = $pdo->prepare("UPDATE $table_name_change_pass SET mot_de_passe = ? WHERE id = ?");
    if ($stmt_update_pass->execute([$nouveau_mdp_hashed_change, $user_id_change_pass])) {
        unset($_SESSION[$session_errors_key_change_pass]); 

        $_SESSION['flash_message'] = "Votre mot de passe a été changé avec succès !";
        $_SESSION['flash_type'] = "success";
        
        // Optionnel: Notifier l'utilisateur par email
        // if (function_exists('envoyer_email') && isset($_SESSION['email_utilisateur_pour_notif'])) { // Assurez-vous que l'email est en session ou récupérez-le
        //     $sujet_notif = "Confirmation de changement de mot de passe - SANTE TV";
        //     $corps_notif = "<p>Bonjour " . htmlspecialchars($_SESSION['nom']) . ",</p><p>Votre mot de passe pour SANTE TV a été changé avec succès.</p>";
        //     envoyer_email($_SESSION['email_utilisateur_pour_notif'], $_SESSION['nom'], $sujet_notif, $corps_notif);
        // }

        header("Location: " . $form_origin_change_pass); 
        exit;
    } else {
        throw new PDOException("Erreur lors de l'exécution de la mise à jour du mot de passe.");
    }

} catch (PDOException $e) {
    error_log("Erreur PDO changer_motdepasse.php (User ID: $user_id_change_pass, Type: $user_type_change_pass): " . $e->getMessage());
    $_SESSION['flash_message'] = "Une erreur technique est survenue lors du changement de votre mot de passe. Veuillez réessayer.";
    $_SESSION['flash_type'] = "error";
    $_SESSION[$session_errors_key_change_pass]['_general'] = "Erreur serveur. Réessayez plus tard.";
    header("Location: " . $form_origin_change_pass);
    exit;
}
?>