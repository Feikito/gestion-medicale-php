<?php
session_start();
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php';
require_once __DIR__ . '/utils/email_functions.php'; 
require_once __DIR__ . '/utils/email_template.php';

$form_origin_reset_form = $_POST['form_origin_reset_pass'] ?? '../pages/mot_de_passe_oublie.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $form_origin_reset_form);
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) {
    $_SESSION['flash_message'] = "Erreur de sécurité. Veuillez réessayer de réinitialiser votre mot de passe.";
    $_SESSION['flash_type'] = "danger";
    $redirect_params_csrf = [];
    if(isset($_POST['token'])) $redirect_params_csrf['token'] = $_POST['token'];
    if(isset($_POST['email'])) $redirect_params_csrf['email'] = $_POST['email'];
    if(isset($_POST['user_type'])) $redirect_params_csrf['type'] = $_POST['user_type'];
    $form_origin_csrf = '../pages/reset_password_form.php' . (!empty($redirect_params_csrf) ? '?' . http_build_query($redirect_params_csrf) : '');
    header("Location: " . $form_origin_csrf);
    exit;
}

$token = $_POST['token'] ?? '';
$email = trim(strtolower($_POST['email'] ?? ''));
$user_type = $_POST['user_type'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_new_password = $_POST['confirm_new_password'] ?? '';
$min_password_length = 8;

$redirect_url_on_error = '../pages/reset_password_form.php?token=' . urlencode($token) . '&email=' . urlencode($email) . '&type=' . urlencode($user_type);

$_SESSION['form_errors_reset_pass'] = [];
$errors = &$_SESSION['form_errors_reset_pass'];

if (empty($token)) { $errors['_general'] = "Jeton de réinitialisation manquant ou invalide."; }
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['email'] = "Email invalide fourni."; }
if (empty($user_type) || !in_array($user_type, ['patient', 'medecin', 'admin'])) { $errors['user_type'] = "Type d'utilisateur invalide.";}
if (empty($new_password)) { $errors['new_password'] = "Le nouveau mot de passe est requis."; } 
elseif (strlen($new_password) < $min_password_length) { $errors['new_password'] = "Le nouveau mot de passe doit contenir au moins $min_password_length caractères.";}
if ($new_password !== $confirm_new_password) { $errors['confirm_new_password'] = "Les mots de passe ne correspondent pas.";}

if (!empty($errors)) {
    $_SESSION['flash_message'] = "Veuillez corriger les erreurs ci-dessous.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $redirect_url_on_error);
    exit;
}

try {
    $stmt_check_token = $pdo->prepare(
        "SELECT id, email, user_type FROM password_resets 
         WHERE token = :token AND LOWER(email) = LOWER(:email) AND user_type = :user_type AND expires_at > NOW() AND used = 0"
    );
    $stmt_check_token->execute([':token' => $token, ':email' => $email, ':user_type' => $user_type]);
    $reset_request = $stmt_check_token->fetch(PDO::FETCH_ASSOC);

    if (!$reset_request) {
        $_SESSION['flash_message'] = "Le lien de réinitialisation est invalide, a expiré ou a déjà été utilisé. Veuillez refaire une demande.";
        $_SESSION['flash_type'] = "error";
        header("Location: ../pages/mot_de_passe_oublie.php"); 
        exit;
    }

    $table_to_update = '';
    $user_name_for_email = 'Utilisateur';
    if ($user_type === 'patient') $table_to_update = 'patients';
    elseif ($user_type === 'medecin') $table_to_update = 'medecins';
    elseif ($user_type === 'admin') $table_to_update = 'admins';
    else { throw new Exception("Type d'utilisateur non géré pour la mise à jour du mot de passe."); }

    $stmt_get_name = $pdo->prepare("SELECT nom, prenom FROM $table_to_update WHERE LOWER(email) = LOWER(?)");
    $stmt_get_name->execute([$email]);
    $user_data_name = $stmt_get_name->fetch();
    if($user_data_name) {
        $user_name_for_email = htmlspecialchars(trim(($user_data_name['prenom'] ?? '') . ' ' . ($user_data_name['nom'] ?? 'Utilisateur')));
        if ($user_type === 'medecin' && !empty($user_name_for_email)) {
            $user_name_for_email = "Dr. " . $user_name_for_email;
        }
    }

    $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);

    $stmt_update_password = $pdo->prepare("UPDATE $table_to_update SET mot_de_passe = :new_password WHERE LOWER(email) = LOWER(:email)");
    $update_success = $stmt_update_password->execute([':new_password' => $new_password_hashed, ':email' => $email]);

    if ($update_success) {
        $stmt_mark_used = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = :id");
        $stmt_mark_used->execute([':id' => $reset_request['id']]);

        if (function_exists('envoyer_email') && function_exists('get_email_html_layout')) {
            $sujet_confirmation = "Confirmation de changement de mot de passe - SANTE TV";
            $contenu_principal_confirmation = "
                <p>Bonjour " . $user_name_for_email . ",</p>
                <p>Ceci est une confirmation que le mot de passe de votre compte SANTE TV associé à l'adresse e-mail (" . htmlspecialchars($email) . ") a été réinitialisé avec succès.</p>
                <p>Si vous n'avez pas effectué ce changement, veuillez contacter immédiatement notre support.</p>
                <p>Si vous avez effectué ce changement, vous pouvez ignorer cet e-mail.</p>
                <p>Cordialement,<br>L'équipe SANTE TV</p>";
            $corps_html_confirmation = get_email_html_layout($sujet_confirmation, $contenu_principal_confirmation, "SANTE TV - Alerte Sécurité");
            envoyer_email($email, $user_name_for_email, $sujet_confirmation, $corps_html_confirmation);
        }

        $_SESSION['flash_message_login'] = "Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.";
        $_SESSION['flash_type_login'] = "success";
        header('Location: ../pages/connexion.php?email=' . urlencode($email) . '&reset=succes');
        exit;
    } else {
        throw new PDOException("Échec de la mise à jour du mot de passe dans la base de données.");
    }

} catch (PDOException $e) {
    error_log("Erreur PDO lors de la réinitialisation du mot de passe (handle_password_reset.php): " . $e->getMessage());
    $_SESSION['flash_message'] = "Une erreur technique est survenue lors de la réinitialisation de votre mot de passe. Veuillez réessayer.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $redirect_url_on_error);
    exit;
} catch (Exception $e) {
    error_log("Erreur générale lors de la réinitialisation du mot de passe (handle_password_reset.php): " . $e->getMessage());
    $_SESSION['flash_message'] = "Une erreur inattendue est survenue. Veuillez réessayer.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $redirect_url_on_error);
    exit;
}
?>