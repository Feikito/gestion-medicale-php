<?php
session_start(); 
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 
require_once __DIR__ . '/utils/email_functions.php'; 
require_once __DIR__ . '/utils/email_template.php'; 

$page_mot_de_passe_oublie_dediee_url = '../pages/mot_de_passe_oublie.php';
$page_accueil_modale_forgot_pass_url = '../index.php#open-modal-forgot-password';

$form_origin_posted_forgot_pass = $_POST['form_origin_forgot_password'] ?? ($_POST['form_origin_forgot_pass'] ?? null);
$form_origin_url_forgot_pass = $page_mot_de_passe_oublie_dediee_url; 

if ($form_origin_posted_forgot_pass) {
    if (strpos($form_origin_posted_forgot_pass, 'index.php#open-modal-forgot-password') !== false) {
        $form_origin_url_forgot_pass = $page_accueil_modale_forgot_pass_url;
    } 
    elseif (strpos($form_origin_posted_forgot_pass, '../pages/mot_de_passe_oublie.php') !== false) {
        $form_origin_url_forgot_pass = $page_mot_de_passe_oublie_dediee_url;
    }
} else {
    if (isset($_SERVER['HTTP_REFERER'])) {
        if (strpos($_SERVER['HTTP_REFERER'], 'index.php') !== false && strpos($_SERVER['HTTP_REFERER'], 'pages/') === false) {
            $form_origin_url_forgot_pass = $page_accueil_modale_forgot_pass_url;
        } elseif (strpos($_SERVER['HTTP_REFERER'], 'pages/mot_de_passe_oublie.php') !== false) {
            $form_origin_url_forgot_pass = $page_mot_de_passe_oublie_dediee_url;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Location: ' . $form_origin_url_forgot_pass);
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $is_modal_origin_req_pass_csrf = (strpos($form_origin_url_forgot_pass, '../index.php#open-modal-forgot-password') !== false);
    $flash_message_csrf = "Erreur de sécurité. Veuillez réessayer.";
    $flash_type_csrf = "danger";

    if ($is_modal_origin_req_pass_csrf) {
        $_SESSION['flash_message_forgot_pass_modal'] = $flash_message_csrf;
        $_SESSION['flash_type_forgot_pass_modal'] = $flash_type_csrf;
    } else { 
        $_SESSION['flash_message'] = $flash_message_csrf;
        $_SESSION['flash_type'] = $flash_type_csrf;
    }
    
    unset($_SESSION['form_data_forgot_pass_modal'], $_SESSION['form_errors_forgot_pass_modal']);
    unset($_SESSION['form_data_forgot_pass'], $_SESSION['form_errors_forgot_pass']);
    header("Location: " . $form_origin_url_forgot_pass);
    exit;
}

$email_forgot_pass_req = trim(strtolower($_POST['email'] ?? ''));
$user_type_forgot_pass_req = trim($_POST['type_utilisateur'] ?? ($_POST['user_type'] ?? ''));

$is_modal_origin_req_pass_form_handling = (strpos($form_origin_url_forgot_pass, '../index.php#open-modal-forgot-password') !== false);
$session_data_key_req_pass = $is_modal_origin_req_pass_form_handling ? 'form_data_forgot_pass_modal' : 'form_data_forgot_pass';
$session_errors_key_req_pass = $is_modal_origin_req_pass_form_handling ? 'form_errors_forgot_pass_modal' : 'form_errors_forgot_pass';

$_SESSION[$session_data_key_req_pass] = $_POST; 
$_SESSION[$session_errors_key_req_pass] = []; 
$errors_forgot_pass_req = &$_SESSION[$session_errors_key_req_pass]; 

if (empty($email_forgot_pass_req)) {
    $errors_forgot_pass_req['email'] = "L'adresse e-mail est requise.";
} elseif (!filter_var($email_forgot_pass_req, FILTER_VALIDATE_EMAIL)) {
    $errors_forgot_pass_req['email'] = "Le format de l'adresse e-mail est invalide.";
}
if (empty($user_type_forgot_pass_req) || !in_array($user_type_forgot_pass_req, ['patient', 'medecin', 'admin'])) {
    $errors_forgot_pass_req['type_utilisateur'] = "Veuillez sélectionner un type de compte valide.";
}

if (!empty($errors_forgot_pass_req)) {
    $flash_msg_validation_req_pass = "Veuillez corriger les erreurs indiquées dans le formulaire.";
    if ($is_modal_origin_req_pass_form_handling) {
        $_SESSION['flash_message_forgot_pass_modal'] = $flash_msg_validation_req_pass;
        $_SESSION['flash_type_forgot_pass_modal'] = "error";
    } else {
        $_SESSION['flash_message'] = $flash_msg_validation_req_pass;
        $_SESSION['flash_type'] = "error";
    }
    header("Location: " . $form_origin_url_forgot_pass);
    exit;
}

$table_to_check_forgot_pass = '';
$user_id_found_forgot_pass = null;
$user_name_for_email_display = 'Utilisateur'; 

if ($user_type_forgot_pass_req === 'patient') $table_to_check_forgot_pass = 'patients';
elseif ($user_type_forgot_pass_req === 'medecin') $table_to_check_forgot_pass = 'medecins';
elseif ($user_type_forgot_pass_req === 'admin') $table_to_check_forgot_pass = 'admins';

if (!empty($table_to_check_forgot_pass)) {
    try {
        $stmt_check_user_exists = $pdo->prepare("SELECT id, nom, prenom FROM $table_to_check_forgot_pass WHERE LOWER(email) = LOWER(?)");
        $stmt_check_user_exists->execute([$email_forgot_pass_req]);
        $user_db_data_forgot_pass = $stmt_check_user_exists->fetch(PDO::FETCH_ASSOC);

        if ($user_db_data_forgot_pass) {
            $user_id_found_forgot_pass = $user_db_data_forgot_pass['id'];
            $user_name_for_email_display = htmlspecialchars(trim(($user_db_data_forgot_pass['prenom'] ?? '') . ' ' . ($user_db_data_forgot_pass['nom'] ?? 'Utilisateur')));
            if ($user_type_forgot_pass_req === 'medecin' && !empty($user_name_for_email_display)) {
                $user_name_for_email_display = "Dr. " . $user_name_for_email_display;
            }
        }
    } catch (PDOException $e) {
        error_log("Erreur PDO vérification utilisateur pour reset mdp (request): " . $e->getMessage());
        $flash_msg_pdo_error = "Erreur technique lors de la vérification de votre compte. Veuillez réessayer.";
        if ($is_modal_origin_req_pass_form_handling) {
            $_SESSION['flash_message_forgot_pass_modal'] = $flash_msg_pdo_error;
            $_SESSION['flash_type_forgot_pass_modal'] = "error";
        } else {
            $_SESSION['flash_message'] = $flash_msg_pdo_error;
            $_SESSION['flash_type'] = "error";
        }
        header("Location: " . $form_origin_url_forgot_pass);
        exit;
    }
}

if (!$user_id_found_forgot_pass) {
    $flash_msg_no_user = "Si un compte est associé à l'adresse e-mail '" . htmlspecialchars($email_forgot_pass_req) . "' et au type de profil '" . htmlspecialchars(ucfirst($user_type_forgot_pass_req)) . "', un lien de réinitialisation de mot de passe a été envoyé.";
    if ($is_modal_origin_req_pass_form_handling) {
        $_SESSION['flash_message_forgot_pass_modal'] = $flash_msg_no_user;
        $_SESSION['flash_type_forgot_pass_modal'] = "success";
    } else {
        $_SESSION['flash_message'] = $flash_msg_no_user;
        $_SESSION['flash_type'] = "success";
    }
    unset($_SESSION[$session_data_key_req_pass]); 
    unset($_SESSION[$session_errors_key_req_pass]);
    header("Location: " . $form_origin_url_forgot_pass);
    exit;
}

try {
    $token_reset_pass = bin2hex(random_bytes(32)); 
    $expiration_reset_pass = (new DateTime('+1 hour'))->format('Y-m-d H:i:s'); 

    $stmt_delete_old_tokens = $pdo->prepare("DELETE FROM password_resets WHERE LOWER(email) = LOWER(?) AND user_type = ?");
    $stmt_delete_old_tokens->execute([$email_forgot_pass_req, $user_type_forgot_pass_req]);

    $stmt_insert_token_reset = $pdo->prepare(
        "INSERT INTO password_resets (email, user_type, token, expires_at, used) 
         VALUES (:email, :user_type, :token, :expires_at, 0)"
    );
    $stmt_insert_token_reset->execute([
        ':email' => $email_forgot_pass_req,
        ':user_type' => $user_type_forgot_pass_req,
        ':token' => $token_reset_pass, 
        ':expires_at' => $expiration_reset_pass
    ]);

} catch (Exception $e) { 
    error_log("Erreur génération/stockage token reset mdp (request): " . $e->getMessage());
    $flash_msg_token_error = "Une erreur technique est survenue lors de la préparation de la réinitialisation de votre mot de passe.";
    if ($is_modal_origin_req_pass_form_handling) {
        $_SESSION['flash_message_forgot_pass_modal'] = $flash_msg_token_error;
        $_SESSION['flash_type_forgot_pass_modal'] = "error";
    } else {
        $_SESSION['flash_message'] = $flash_msg_token_error;
        $_SESSION['flash_type'] = "error";
    }
    header("Location: " . $form_origin_url_forgot_pass);
    exit;
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host_name = $_SERVER['HTTP_HOST'];
$base_path = dirname(dirname($_SERVER['PHP_SELF'])); 
if (basename($base_path) === 'php') {
    $base_path = dirname($base_path);
}
if ($base_path === '.' || $base_path === '/' || $base_path === '\\') {
    $base_path = '';
}

$reset_link_url = $protocol . $host_name . $base_path . "/pages/reset_password_form.php?token=" . urlencode($token_reset_pass) . "&email=" . urlencode($email_forgot_pass_req) . "&type=" . urlencode($user_type_forgot_pass_req);

$sujet_email_reset_req = "Réinitialisation de votre mot de passe sur SANTE TV";

$contenu_principal_html_email = "
    <p>Bonjour " . $user_name_for_email_display . ",</p>
    <p>Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte SANTE TV associé à cette adresse e-mail.</p>
    <p>Si vous n'avez pas effectué cette demande, veuillez ignorer cet e-mail. Aucune modification ne sera apportée à votre compte.</p>
    <p>Sinon, pour choisir un nouveau mot de passe, veuillez cliquer sur le lien ci-dessous. Ce lien est valide pour <strong>1 heure</strong> :</p>
    <div class='button-container'>
        <a href='" . $reset_link_url . "' class='button'>Réinitialiser mon mot de passe</a>
    </div>
    <p>Si le bouton ne fonctionne pas, veuillez copier et coller le lien suivant dans la barre d'adresse de votre navigateur :</p>
    <p><a href='" . $reset_link_url . "'>" . $reset_link_url . "</a></p>
    <p>Pour des raisons de sécurité, ne partagez jamais ce lien.</p>
    <p>Cordialement,<br>L'équipe SANTE TV</p>";

$corps_html_email_reset_req = get_email_html_layout($sujet_email_reset_req, $contenu_principal_html_email, "SANTE TV");


if (function_exists('envoyer_email') && envoyer_email($email_forgot_pass_req, $user_name_for_email_display, $sujet_email_reset_req, $corps_html_email_reset_req)) {
$flash_msg_email_sent = "Un e-mail contenant les instructions pour réinitialiser votre mot de passe a été envoyé à " . htmlspecialchars($email_forgot_pass_req) .
". Veuillez consulter votre boîte de réception (et votre dossier de spam). Le lien expirera dans une heure.";    if ($is_modal_origin_req_pass_form_handling) {
        $_SESSION['flash_message_forgot_pass_modal'] = $flash_msg_email_sent;
        $_SESSION['flash_type_forgot_pass_modal'] = "success";
    } else {
        $_SESSION['flash_message'] = $flash_msg_email_sent;
        $_SESSION['flash_type'] = "success";
    }
    unset($_SESSION[$session_data_key_req_pass]); 
    unset($_SESSION[$session_errors_key_req_pass]);
} else {
    $flash_msg_email_fail = "Une erreur est survenue lors de l'envoi de l'e-mail de réinitialisation. Votre demande a été enregistrée, mais l'e-mail n'a pu être transmis. Veuillez réessayer plus tard ou contacter le support si le problème persiste.";
    if ($is_modal_origin_req_pass_form_handling) {
        $_SESSION['flash_message_forgot_pass_modal'] = $flash_msg_email_fail;
        $_SESSION['flash_type_forgot_pass_modal'] = "error";
    } else {
        $_SESSION['flash_message'] = $flash_msg_email_fail;
        $_SESSION['flash_type'] = "error";
    }
}

header("Location: " . $form_origin_url_forgot_pass);
exit;
?>