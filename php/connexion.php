<?php
session_start(); 
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php';

$page_connexion_dediee_url = '../pages/connexion.php'; 
$page_accueil_modale_url = '../index.php#modal-connexion'; 

$form_origin_posted = $_POST['form_origin_connexion'] ?? null;
$form_origin_url = $page_connexion_dediee_url; 

if ($form_origin_posted) {
    if (strpos($form_origin_posted, 'index.php#') !== false) {
        $form_origin_url = '../' . $form_origin_posted;
    } 
    elseif (strpos($form_origin_posted, '../pages/connexion.php') !== false) {
        $form_origin_url = $form_origin_posted;
    }
    elseif ($form_origin_posted === 'index.php') {
         $form_origin_url = '../index.php#modal-connexion';
    }
} else {
    if (isset($_SERVER['HTTP_REFERER'])) {
        if (strpos($_SERVER['HTTP_REFERER'], 'index.php') !== false && strpos($_SERVER['HTTP_REFERER'], 'pages/') === false) {
            $form_origin_url = '../index.php#modal-connexion';
        } elseif (strpos($_SERVER['HTTP_REFERER'], 'pages/connexion.php') !== false) {
            $form_origin_url = '../pages/connexion.php';
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $page_connexion_dediee_url); 
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $is_modal_origin_csrf_connexion = (strpos($form_origin_url, '../index.php#modal-connexion') !== false);
    $flash_message_csrf_connexion = "Erreur de sécurité. Veuillez réessayer.";
    $flash_type_csrf_connexion = "danger";
    if ($is_modal_origin_csrf_connexion) {
        $_SESSION['flash_message'] = $flash_message_csrf_connexion; 
        $_SESSION['flash_type'] = $flash_type_csrf_connexion;
    } else { 
        $_SESSION['flash_message_page'] = $flash_message_csrf_connexion; 
        $_SESSION['flash_type_page'] = $flash_type_csrf_connexion;
    }
    $_SESSION[$is_modal_origin_csrf_connexion ? 'form_data_connexion_modal' : 'form_data_connexion_page'] = $_POST;
    header("Location: " . $form_origin_url); 
    exit;
}

$type_utilisateur = trim($_POST['type_utilisateur'] ?? '');
$email_soumis = trim($_POST['email'] ?? ''); 
$email_comparaison = strtolower($email_soumis); 
$mot_de_passe_soumis = $_POST['mot_de_passe'] ?? '';

$is_modal_origin_connexion_handling = (strpos($form_origin_url, '../index.php#modal-connexion') !== false);
$session_data_key_connexion = $is_modal_origin_connexion_handling ? 'form_data_connexion_modal' : 'form_data_connexion_page';
$session_errors_key_connexion = $is_modal_origin_connexion_handling ? 'form_errors_connexion_modal' : 'form_errors_connexion_page';

$_SESSION[$session_errors_key_connexion] = [];
$errors_connexion = &$_SESSION[$session_errors_key_connexion]; 

if (empty($type_utilisateur) || !in_array($type_utilisateur, ['patient', 'medecin'])) {
    $errors_connexion['type_utilisateur'] = "Veuillez sélectionner un type de profil.";
}
if (empty($email_soumis)) {
    $errors_connexion['email'] = "L'e-mail est requis.";
} elseif (!filter_var($email_soumis, FILTER_VALIDATE_EMAIL)) {
    $errors_connexion['email'] = "Format d'e-mail invalide.";
}
if (empty($mot_de_passe_soumis)) {
    $errors_connexion['mot_de_passe'] = "Le mot de passe est requis.";
}

if (!empty($errors_connexion)) {
    $flash_msg_validation_connexion = "Veuillez corriger les erreurs.";
    if ($is_modal_origin_connexion_handling) {
        $_SESSION['flash_message'] = $flash_msg_validation_connexion;
        $_SESSION['flash_type'] = "error";
    } else {
        $_SESSION['flash_message_page'] = $flash_msg_validation_connexion;
        $_SESSION['flash_type_page'] = "error";
    }
    $_SESSION[$session_data_key_connexion] = ['email' => $email_soumis, 'type_utilisateur' => $type_utilisateur];
    header("Location: " . $form_origin_url); 
    exit;
}

$table_a_verifier = '';
$champs_a_recuperer = 'id, nom, prenom, mot_de_passe'; 

if ($type_utilisateur === 'patient') {
    $table_a_verifier = 'patients';
} elseif ($type_utilisateur === 'medecin') {
    $table_a_verifier = 'medecins';
    $champs_a_recuperer .= ', valide'; 
}

try {
    if ($pdo->query("SHOW TABLES LIKE '$table_a_verifier'")->rowCount() == 0) {
        throw new PDOException("La table '$table_a_verifier' est introuvable.");
    }

    $sql_get_user = "SELECT $champs_a_recuperer FROM `$table_a_verifier` WHERE LOWER(email) = LOWER(?)";
    $stmt_get_user = $pdo->prepare($sql_get_user);
    $stmt_get_user->execute([$email_comparaison]);
    $utilisateur_db = $stmt_get_user->fetch(PDO::FETCH_ASSOC);

    $login_failed = false;
    $specific_error_message_connexion = null;
    $specific_error_type_connexion = "error"; 

    if ($utilisateur_db) {
        if (password_verify($mot_de_passe_soumis, $utilisateur_db['mot_de_passe'])) {
            if ($type_utilisateur === 'medecin' && (!isset($utilisateur_db['valide']) || $utilisateur_db['valide'] != 1)) {
                $specific_error_message_connexion = "Votre compte médecin est en attente de validation ou a été désactivé. Vous serez notifié une fois validé.";
                $specific_error_type_connexion = "warning"; 
                $login_failed = true;
            }

            if (!$login_failed) {
                unset($_SESSION[$session_data_key_connexion], $_SESSION[$session_errors_key_connexion]);
                if($is_modal_origin_connexion_handling) {
                    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
                } else {
                    unset($_SESSION['flash_message_page'], $_SESSION['flash_type_page']);
                }
                
                session_regenerate_id(true);

                $_SESSION['utilisateur_id'] = $utilisateur_db['id'];
                $_SESSION['nom'] = trim(htmlspecialchars(($utilisateur_db['prenom'] ?? '') . ' ' . ($utilisateur_db['nom'] ?? 'Utilisateur')));
                $_SESSION['type'] = $type_utilisateur;
                $_SESSION['last_activity'] = time();

                $redirect_target_on_success = '';
                if ($type_utilisateur === 'patient') {
                    $redirect_target_on_success = 'dashboard_patient.php'; 
                } elseif ($type_utilisateur === 'medecin') {
                    $redirect_target_on_success = 'espace_medecin.php'; 
                }
                
                $_SESSION['flash_message'] = "Connexion réussie ! Bienvenue " . $_SESSION['nom'] . ".";
                $_SESSION['flash_type'] = "success";
                header("Location: " . $redirect_target_on_success); 
                exit;
            }
        } else {
            $login_failed = true; 
        }
    } else {
        $login_failed = true; 
    }

    if ($login_failed) {
        $error_msg_to_display_connexion = $specific_error_message_connexion ?? "L'adresse e-mail ou le mot de passe est incorrect pour le type de profil sélectionné.";
        $error_type_to_display_connexion = $specific_error_message_connexion ? $specific_error_type_connexion : "error";

        if ($is_modal_origin_connexion_handling) {
            $_SESSION['flash_message'] = $error_msg_to_display_connexion; 
            $_SESSION['flash_type'] = $error_type_to_display_connexion;
        } else {
            $_SESSION['flash_message_page'] = $error_msg_to_display_connexion; 
            $_SESSION['flash_type_page'] = $error_type_to_display_connexion;
        }
        
        if (!$specific_error_message_connexion) { 
            $errors_connexion['_general'] = "Identifiants incorrects."; 
        }
        $_SESSION[$session_errors_key_connexion] = $errors_connexion;
        $_SESSION[$session_data_key_connexion] = ['email' => $email_soumis, 'type_utilisateur' => $type_utilisateur];
        header("Location: " . $form_origin_url); 
        exit;
    }

} catch (PDOException $e) {
    error_log("Erreur PDO connexion.php (Email: $email_soumis, Type: $type_utilisateur): " . $e->getMessage());
    $error_msg_pdo_connexion = "Une erreur technique est survenue. Veuillez réessayer.";
    if ($is_modal_origin_connexion_handling) {
        $_SESSION['flash_message'] = $error_msg_pdo_connexion;
        $_SESSION['flash_type'] = "error";
    } else {
        $_SESSION['flash_message_page'] = $error_msg_pdo_connexion;
        $_SESSION['flash_type_page'] = "error";
    }
    $_SESSION[$session_data_key_connexion] = ['email' => $email_soumis, 'type_utilisateur' => $type_utilisateur];
    header("Location: " . $form_origin_url); 
    exit;
}
?>