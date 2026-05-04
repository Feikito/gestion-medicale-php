<?php
session_start(); 
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 
// require_once __DIR__ . '/utils/email_functions.php'; // Pas d'email à l'inscription patient pour le moment
// require_once __DIR__ . '/utils/email_template.php';
// require_once __DIR__ . '/utils/logger.php'; // Décommentez si vous voulez logger cette action

$form_origin_posted_patient = $_POST['form_origin'] ?? '../index.php#modal-inscription';

$form_origin = '../index.php#modal-inscription';
if (strpos($form_origin_posted_patient, '../pages/inscription_patient.php') === 0) {
    $form_origin = $form_origin_posted_patient;
} elseif (strpos($form_origin_posted_patient, 'index.php#modal-inscription') === 0) {
    $form_origin = '../' . $form_origin_posted_patient;
} elseif (strpos($form_origin_posted_patient, 'index.php') === 0) { 
     $form_origin = '../index.php' . (strpos($form_origin_posted_patient, '#') !== false ? substr($form_origin_posted_patient, strpos($form_origin_posted_patient, '#')) : '#modal-inscription');
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $form_origin); 
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $is_modal_origin_csrf_patient = (strpos($form_origin, '../index.php#modal-inscription') !== false);
    $flash_message_csrf_patient = "Erreur de sécurité lors de la soumission. Veuillez réessayer.";
    $flash_type_csrf_patient = "danger"; 

    if ($is_modal_origin_csrf_patient) {
        $_SESSION['flash_message'] = $flash_message_csrf_patient;
        $_SESSION['flash_type'] = $flash_type_csrf_patient; 
        $_SESSION['form_data_patient_modal'] = $_POST;
    } else {
        $_SESSION['flash_message_page'] = $flash_message_csrf_patient;
        $_SESSION['flash_type_page'] = $flash_type_csrf_patient;
        $_SESSION['form_data_patient_page'] = $_POST;
    }
    header("Location: " . $form_origin);
    exit;
}

$nom = trim($_POST['nom'] ?? '');
$prenom = trim($_POST['prenom'] ?? '');
$email = trim(strtolower($_POST['email'] ?? ''));
$adresse = trim($_POST['adresse'] ?? '');
$date_naissance_str = trim($_POST['date_naissance'] ?? '');
$sexe = trim($_POST['sexe'] ?? '');
$mot_de_passe = $_POST['mot_de_passe'] ?? ''; 
$confirm_mot_de_passe = $_POST['confirm_mot_de_passe'] ?? '';
$min_password_length = 8;

$is_modal_origin_patient_form_handling = (strpos($form_origin, '../index.php#modal-inscription') !== false);
$session_data_key_patient = $is_modal_origin_patient_form_handling ? 'form_data_patient_modal' : 'form_data_patient_page';
$session_errors_key_patient = $is_modal_origin_patient_form_handling ? 'form_errors_patient_modal' : 'form_errors_patient_page';

$_SESSION[$session_data_key_patient] = $_POST; 
$_SESSION[$session_errors_key_patient] = [];
$errors_insc_patient = &$_SESSION[$session_errors_key_patient]; 

if (empty($nom)) { $errors_insc_patient['nom'] = "Le nom est requis."; }
elseif (strlen($nom) > 100) { $errors_insc_patient['nom'] = "Le nom ne doit pas dépasser 100 caractères."; }

if (empty($prenom)) { $errors_insc_patient['prenom'] = "Le prénom est requis."; }
elseif (strlen($prenom) > 100) { $errors_insc_patient['prenom'] = "Le prénom ne doit pas dépasser 100 caractères."; }

if (empty($email)) { 
    $errors_insc_patient['email'] = "L'adresse e-mail est requise."; 
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { 
    $errors_insc_patient['email'] = "Le format de l'adresse e-mail est invalide."; 
} elseif (strlen($email) > 100) {
    $errors_insc_patient['email'] = "L'email ne doit pas dépasser 100 caractères.";
} else {
    $stmt_check_email_global_patient = $pdo->prepare(
        "(SELECT email FROM patients WHERE LOWER(email) = LOWER(?))
         UNION 
         (SELECT email FROM medecins WHERE LOWER(email) = LOWER(?))
         UNION
         (SELECT email FROM admins WHERE LOWER(email) = LOWER(?))"
    );
    $stmt_check_email_global_patient->execute([$email, $email, $email]); 
    if ($stmt_check_email_global_patient->fetch()) {
        $login_page_url_patient = '../pages/connexion.php'; 
        $errors_insc_patient['email'] = "Cette adresse e-mail est déjà utilisée. <a href='{$login_page_url_patient}'>Se connecter?</a>";
    }
}

if (!empty($adresse) && strlen($adresse) > 255) {
    $errors_insc_patient['adresse'] = "L'adresse ne doit pas dépasser 255 caractères.";
}

if (empty($date_naissance_str)) { 
    $errors_insc_patient['date_naissance'] = "La date de naissance est requise."; 
} else {
    try {
        $date_naissance_obj_patient = new DateTime($date_naissance_str);
        $aujourdhui_patient = new DateTime('today');
        if ($date_naissance_obj_patient->format('Y-m-d') !== $date_naissance_str) {
            throw new Exception("Format de date interne invalide.");
        }
        if ($date_naissance_obj_patient >= $aujourdhui_patient) {
            $errors_insc_patient['date_naissance'] = "La date de naissance doit être antérieure à aujourd'hui.";
        }
        $age_min_date_patient = (new DateTime('today'))->modify('-120 years'); 
        $age_max_date_patient = (new DateTime('today'))->modify('-1 day'); 
        if ($date_naissance_obj_patient < $age_min_date_patient || $date_naissance_obj_patient > $age_max_date_patient) {
            $errors_insc_patient['date_naissance'] = "Veuillez entrer une date de naissance valide.";
        }
    } catch (Exception $e) {
        $errors_insc_patient['date_naissance'] = "Format de date de naissance invalide (AAAA-MM-JJ attendu).";
    }
}

$sexe_options_patient = ['Homme', 'Femme']; 
if (empty($sexe)) { 
    $errors_insc_patient['sexe'] = "Veuillez sélectionner votre sexe."; 
} elseif (!in_array($sexe, $sexe_options_patient)) {
    $errors_insc_patient['sexe'] = "La valeur pour le sexe est invalide.";
}

if (empty($mot_de_passe)) { 
    $errors_insc_patient['mot_de_passe'] = "Le mot de passe est requis."; 
} elseif (strlen($mot_de_passe) < $min_password_length) {
    $errors_insc_patient['mot_de_passe'] = "Votre mot de passe doit contenir au moins $min_password_length caractères.";
}
if (empty($confirm_mot_de_passe)) {
    $errors_insc_patient['confirm_mot_de_passe'] = "Veuillez confirmer votre mot de passe.";
} elseif ($mot_de_passe !== $confirm_mot_de_passe) { 
    $errors_insc_patient['confirm_mot_de_passe'] = "Les mots de passe saisis ne correspondent pas.";
}


if (!empty($errors_insc_patient)) {
    $flash_msg_insc_patient_final = "Votre formulaire d'inscription contient des erreurs. Veuillez vérifier les champs indiqués.";
    if ($is_modal_origin_patient_form_handling) {
        $_SESSION['flash_message'] = $flash_msg_insc_patient_final;
        $_SESSION['flash_type'] = "error";
    } else {
        $_SESSION['flash_message_page'] = $flash_msg_insc_patient_final;
        $_SESSION['flash_type_page'] = "error";
    }
    header("Location: " . $form_origin);
    exit;
}

$mot_de_passe_hashed_patient = password_hash($mot_de_passe, PASSWORD_DEFAULT);

try {
    // La colonne `created_at` sera gérée par `DEFAULT current_timestamp()`
    $sql_insert_patient = "INSERT INTO patients 
                             (nom, prenom, email, adresse, date_naissance, sexe, mot_de_passe) 
                           VALUES 
                             (:nom, :prenom, :email, :adresse, :date_naissance, :sexe, :mot_de_passe)";
    $stmt_insert_patient = $pdo->prepare($sql_insert_patient);
    
    $stmt_insert_patient->execute([
        ':nom' => $nom, 
        ':prenom' => $prenom, 
        ':email' => $email,
        ':adresse' => !empty($adresse) ? $adresse : null, 
        ':date_naissance' => $date_naissance_str, 
        ':sexe' => $sexe,
        ':mot_de_passe' => $mot_de_passe_hashed_patient
    ]);

    unset($_SESSION[$session_data_key_patient]);
    unset($_SESSION[$session_errors_key_patient]);

    // Journalisation (si vous l'implémentez)
    // if (function_exists('log_action_application')) {
    //     log_action_application($pdo, 'INSCRIPTION_PATIENT', "Nouveau patient inscrit: " . htmlspecialchars($prenom . ' ' . $nom) . " (Email: $email).", $pdo->lastInsertId(), 'patient', null, null);
    // }

    $_SESSION['flash_message_login'] = "Inscription réussie ! Vous pouvez maintenant vous connecter avec vos identifiants.";
    $_SESSION['flash_type_login'] = "success";
    header('Location: ../pages/connexion.php?email=' . urlencode($email) . '&inscription=succes_patient'); 
    exit;

} catch (PDOException $e) {
    error_log("Erreur PDO inscription patient (Email: $email): " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    $_SESSION[$session_data_key_patient] = $_POST; 

    $error_message_inscription_final = "Erreur technique lors de l'inscription. Veuillez réessayer plus tard.";
    if ($e->getCode() == 23000) { 
        $error_message_inscription_final = "Erreur: L'adresse e-mail '" . htmlspecialchars($email) . "' est déjà enregistrée.";
        $_SESSION[$session_errors_key_patient]['email'] = "Cette adresse e-mail est déjà utilisée. <a href='../pages/connexion.php'>Se connecter?</a>";
    } else {
         $_SESSION[$session_errors_key_patient]['_general'] = $error_message_inscription_final;
    }

    if ($is_modal_origin_patient_form_handling) {
        $_SESSION['flash_message'] = $_SESSION[$session_errors_key_patient]['email'] ?? $error_message_inscription_final;
        $_SESSION['flash_type'] = "error";
    } else {
        $_SESSION['flash_message_page'] = $_SESSION[$session_errors_key_patient]['email'] ?? $error_message_inscription_final;
        $_SESSION['flash_type_page'] = "error";
    }
    header("Location: " . $form_origin);
    exit;
}
?>