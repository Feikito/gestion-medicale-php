<?php
session_start(); 
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 
require_once __DIR__ . '/utils/email_functions.php'; 
require_once __DIR__ . '/utils/email_template.php'; 
require_once __DIR__ . '/utils/app_settings.php'; // Pour NOM_APPLICATION, etc.
// require_once __DIR__ . '/utils/logger.php'; // Décommentez si vous voulez logger cette action

$form_origin_posted_med = $_POST['form_origin_medecin'] ?? '../index.php#modal-form';

$form_origin = '../index.php#modal-form'; 
if (strpos($form_origin_posted_med, '../pages/inscription_medecin.php') === 0) {
    $form_origin = $form_origin_posted_med;
} elseif (strpos($form_origin_posted_med, 'index.php#modal-form') === 0) {
    $form_origin = '../' . $form_origin_posted_med;
} elseif (strpos($form_origin_posted_med, 'index.php') === 0) { 
     $form_origin = '../index.php' . (strpos($form_origin_posted_med, '#') !== false ? substr($form_origin_posted_med, strpos($form_origin_posted_med, '#')) : '#modal-form');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $form_origin);
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $is_modal_origin_csrf_med = (strpos($form_origin, '../index.php#modal-form') !== false);
    $flash_message_csrf = "Erreur de sécurité. Veuillez réessayer.";
    $flash_type_csrf = "danger";
    if ($is_modal_origin_csrf_med) {
        $_SESSION['flash_message'] = $flash_message_csrf;
        $_SESSION['flash_type'] = $flash_type_csrf;
        $_SESSION['form_data_medecin_modal'] = $_POST;
    } else { 
        $_SESSION['flash_message_page'] = $flash_message_csrf;
        $_SESSION['flash_type_page'] = $flash_type_csrf;
        $_SESSION['form_data_medecin_page'] = $_POST;
    }
    header("Location: " . $form_origin);
    exit;
}

$nom = trim($_POST['nom'] ?? '');
$prenom = trim($_POST['prenom'] ?? '');
$specialite = trim($_POST['specialite'] ?? '');
$email = trim(strtolower($_POST['email'] ?? ''));
$telephone = trim($_POST['telephone'] ?? '');
$adresse = trim($_POST['adresse'] ?? '');
$mot_de_passe = $_POST['mot_de_passe'] ?? '';
$confirm_mot_de_passe = $_POST['confirmer_mot_de_passe'] ?? '';
$min_password_length = 8;

$is_modal_origin_med_form_handling = (strpos($form_origin, '../index.php#modal-form') !== false);
$session_data_key_med = $is_modal_origin_med_form_handling ? 'form_data_medecin_modal' : 'form_data_medecin_page';
$session_errors_key_med = $is_modal_origin_med_form_handling ? 'form_errors_medecin_modal' : 'form_errors_medecin_page';

$_SESSION[$session_data_key_med] = $_POST;
$_SESSION[$session_errors_key_med] = []; 
$errors_insc_med = &$_SESSION[$session_errors_key_med]; 

if (empty($nom)) $errors_insc_med['nom'] = "Le nom est requis."; elseif (strlen($nom) > 100) $errors_insc_med['nom'] = "Le nom ne doit pas dépasser 100 caractères.";
if (empty($prenom)) $errors_insc_med['prenom'] = "Le prénom est requis."; elseif (strlen($prenom) > 100) $errors_insc_med['prenom'] = "Le prénom ne doit pas dépasser 100 caractères.";
if (empty($specialite)) $errors_insc_med['specialite'] = "La spécialité est requise."; elseif (strlen($specialite) > 100) $errors_insc_med['specialite'] = "La spécialité ne doit pas dépasser 100 caractères.";

if (empty($email)) { $errors_insc_med['email'] = "L'email professionnel est requis."; }
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors_insc_med['email'] = "Format d'email invalide."; }
elseif (strlen($email) > 100) { $errors_insc_med['email'] = "L'email ne doit pas dépasser 100 caractères."; }
else {
    $stmt_check_email_global_med = $pdo->prepare(
        "(SELECT email FROM patients WHERE LOWER(email) = LOWER(?))
         UNION 
         (SELECT email FROM medecins WHERE LOWER(email) = LOWER(?))
         UNION
         (SELECT email FROM admins WHERE LOWER(email) = LOWER(?))"
    );
    $stmt_check_email_global_med->execute([$email, $email, $email]);
    if ($stmt_check_email_global_med->fetch()) {
        $errors_insc_med['email'] = "Cette adresse e-mail est déjà utilisée. <a href='../pages/connexion.php'>Se connecter?</a>";
    }
}

if (empty($telephone)) { $errors_insc_med['telephone'] = "Le numéro de téléphone est requis."; }
elseif (!preg_match('/^[0-9\s\-\+\(\)]{8,20}$/', $telephone)) { 
    $errors_insc_med['telephone'] = "Format de téléphone invalide (8-20 chiffres/espaces/tirets/plus).";
}

if (empty($adresse)) $errors_insc_med['adresse'] = "L'adresse du cabinet est requise."; elseif (strlen($adresse) > 255) $errors_insc_med['adresse'] = "L'adresse ne doit pas dépasser 255 caractères.";

if (empty($mot_de_passe)) { $errors_insc_med['mot_de_passe'] = "Le mot de passe est requis."; }
elseif (strlen($mot_de_passe) < $min_password_length) { $errors_insc_med['mot_de_passe'] = "Votre mot de passe doit contenir au moins $min_password_length caractères."; }
if (empty($confirm_mot_de_passe)) { $errors_insc_med['confirmer_mot_de_passe'] = "Veuillez confirmer votre mot de passe."; }
elseif ($mot_de_passe !== $confirm_mot_de_passe) { $errors_insc_med['confirmer_mot_de_passe'] = "Les mots de passe saisis ne correspondent pas."; }

$document_path_in_db_med = null;
$newDocServerPathMed = null; 
if (!isset($_FILES['documents']) || $_FILES['documents']['error'] == UPLOAD_ERR_NO_FILE) {
    $errors_insc_med['documents'] = "Un document justificatif est requis.";
} elseif ($_FILES['documents']['error'] != UPLOAD_ERR_OK) {
    $upload_errors_map = [
        UPLOAD_ERR_INI_SIZE => "Le fichier téléchargé excède la directive upload_max_filesize dans php.ini.",
        UPLOAD_ERR_FORM_SIZE => "Le fichier téléchargé excède la directive MAX_FILE_SIZE spécifiée dans le formulaire HTML.",
        UPLOAD_ERR_PARTIAL => "Le fichier n'a été que partiellement téléchargé.",
        UPLOAD_ERR_NO_TMP_DIR => "Manque un dossier temporaire.",
        UPLOAD_ERR_CANT_WRITE => "Échec de l'écriture du fichier sur le disque.",
        UPLOAD_ERR_EXTENSION => "Une extension PHP a arrêté l'envoi de fichier.",
    ];
    $errors_insc_med['documents'] = $upload_errors_map[$_FILES['documents']['error']] ?? "Erreur inconnue lors de l'upload (Code: " . $_FILES['documents']['error'] . ").";
} else {
    $allowed_mime_types_doc_med = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    $allowed_extensions_doc_med = ['pdf', 'jpg', 'jpeg', 'png'];
    $max_size_doc_med = 5 * 1024 * 1024; 

    $file_tmp_name_doc_med = $_FILES['documents']['tmp_name'];
    $file_size_doc_med = $_FILES['documents']['size'];
    $file_original_name_doc_med = basename($_FILES['documents']['name']); 
    
    $finfo_doc_med = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type_doc_med = finfo_file($finfo_doc_med, $file_tmp_name_doc_med);
    finfo_close($finfo_doc_med);
    $extension_doc_med = strtolower(pathinfo($file_original_name_doc_med, PATHINFO_EXTENSION));

    if (!in_array($mime_type_doc_med, $allowed_mime_types_doc_med) || !in_array($extension_doc_med, $allowed_extensions_doc_med)) {
        $errors_insc_med['documents'] = "Type de document non autorisé (PDF, JPG, PNG). Type détecté: " . htmlspecialchars($mime_type_doc_med);
    }
    if ($file_size_doc_med == 0) { $errors_insc_med['documents'] = "Le document soumis est vide."; }
    if ($file_size_doc_med > $max_size_doc_med) { $errors_insc_med['documents'] = "Document trop volumineux (max 5MB)."; }
    
    if (empty($errors_insc_med['documents'])) { 
        $uploadDirRelativeFromRootDocMed = 'uploads/documents_medecins/'; 
        $uploadDirServerPathDocMed = __DIR__ . '/../' . $uploadDirRelativeFromRootDocMed; 

        if (!is_dir($uploadDirServerPathDocMed)) {
            if (!mkdir($uploadDirServerPathDocMed, 0755, true)) {
                $errors_insc_med['_general'] = "Erreur serveur: Impossible de créer le dossier d'uploads.";
                error_log("Erreur mkdir pour documents médecins: " . $uploadDirServerPathDocMed);
            }
        }
        
        if (empty($errors_insc_med['_general']) && !is_writable($uploadDirServerPathDocMed)) {
             $errors_insc_med['_general'] = "Erreur serveur: Permissions d'écriture manquantes sur le dossier d'uploads des documents (" . $uploadDirServerPathDocMed . ").";
             error_log("Erreur de permission d'écriture pour documents médecins: " . $uploadDirServerPathDocMed);
        }

        if (empty($errors_insc_med['_general']) && empty($errors_insc_med['documents'])) {
            $safe_filename_prefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($nom . '_' . $prenom));
            $newDocNameMed = uniqid('doc_' . $safe_filename_prefix . '_', true) . '.' . $extension_doc_med;
            $document_path_in_db_med = $uploadDirRelativeFromRootDocMed . $newDocNameMed; 
            $newDocServerPathMed = $uploadDirServerPathDocMed . $newDocNameMed;     
        }
    }
}

if (!empty($errors_insc_med)) {
    $flash_msg_insc_med_final = $errors_insc_med['_general'] ?? "Votre formulaire contient des erreurs. Veuillez vérifier les champs.";
    if ($is_modal_origin_med_form_handling) {
        $_SESSION['flash_message'] = $flash_msg_insc_med_final;
        $_SESSION['flash_type'] = "error";
    } else {
        $_SESSION['flash_message_page'] = $flash_msg_insc_med_final;
        $_SESSION['flash_type_page'] = "error";
    }
    header("Location: " . $form_origin);
    exit;
}

if ($document_path_in_db_med && $newDocServerPathMed) {
    if (!move_uploaded_file($_FILES['documents']['tmp_name'], $newDocServerPathMed)) {
        $upload_error_msg_move = "Erreur technique lors du transfert du document justificatif. Vérifiez les permissions du dossier.";
        error_log("Échec de move_uploaded_file pour documents: de " . $_FILES['documents']['tmp_name'] . " vers " . $newDocServerPathMed);
        $_SESSION[$session_errors_key_med]['documents'] = "Échec du transfert du fichier.";
        if ($is_modal_origin_med_form_handling) {
            $_SESSION['flash_message'] = $upload_error_msg_move;
            $_SESSION['flash_type'] = "error";
        } else {
            $_SESSION['flash_message_page'] = $upload_error_msg_move;
            $_SESSION['flash_type_page'] = "error";
        }
        header("Location: " . $form_origin);
        exit;
    }
} elseif (empty($document_path_in_db_med) && empty($errors_insc_med['documents'])) { 
    $doc_missing_err_msg = "Le document justificatif est manquant ou une erreur est survenue avant son traitement.";
    $_SESSION[$session_errors_key_med]['documents'] = "Veuillez sélectionner un document valide.";
     if ($is_modal_origin_med_form_handling) {
        $_SESSION['flash_message'] = $doc_missing_err_msg;
        $_SESSION['flash_type'] = "error";
    } else {
        $_SESSION['flash_message_page'] = $doc_missing_err_msg;
        $_SESSION['flash_type_page'] = "error";
    }
    header("Location: " . $form_origin);
    exit;
}

$mot_de_passe_hashed_med = password_hash($mot_de_passe, PASSWORD_DEFAULT);

try {
    // La colonne `created_at` est gérée par DEFAULT current_timestamp() dans la BDD
    $sql_insert_med = "INSERT INTO medecins 
                         (nom, prenom, specialite, email, telephone, adresse, mot_de_passe, document_justificatif, valide) 
                       VALUES 
                         (:nom, :prenom, :specialite, :email, :telephone, :adresse, :mot_de_passe, :document, 0)"; 
    $stmt_insert_med = $pdo->prepare($sql_insert_med);
    
    $stmt_insert_med->execute([
        ':nom' => $nom, 
        ':prenom' => $prenom, 
        ':specialite' => $specialite, 
        ':email' => $email, 
        ':telephone' => $telephone, 
        ':adresse' => $adresse,
        ':mot_de_passe' => $mot_de_passe_hashed_med, 
        ':document' => $document_path_in_db_med 
    ]);
    $nouveau_medecin_id = $pdo->lastInsertId();

    unset($_SESSION[$session_data_key_med]); 
    unset($_SESSION[$session_errors_key_med]);

    $admin_notif_email = defined('ADMIN_EMAIL_NOTIFICATIONS') ? ADMIN_EMAIL_NOTIFICATIONS : null;
    $admin_notif_name = defined('ADMIN_NAME_NOTIFICATIONS') ? ADMIN_NAME_NOTIFICATIONS : 'Administrateur '.(defined('NOM_APPLICATION') ? NOM_APPLICATION : 'SANTE TV');
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $base_path_notif = dirname(dirname($_SERVER['PHP_SELF'])); 
    if ($base_path_notif === '/' || $base_path_notif === '\\') $base_path_notif = '';

    if ($admin_notif_email && function_exists('envoyer_email') && function_exists('get_email_html_layout')) {
        $sujet_admin = "Nouvelle demande d'inscription médecin sur ".(defined('NOM_APPLICATION') ? NOM_APPLICATION : 'SANTE TV');
        $contenu_principal_admin = "<p>Bonjour " . $admin_notif_name . ",</p><p>Une nouvelle demande d'inscription de médecin a été soumise sur la plateforme ".(defined('NOM_APPLICATION') ? NOM_APPLICATION : 'SANTE TV')." et requiert votre attention.</p><p><strong>Détails du demandeur :</strong></p><ul><li><strong>Nom complet :</strong> Dr. " . htmlspecialchars($prenom . ' ' . $nom) . "</li><li><strong>Email :</strong> " . htmlspecialchars($email) . "</li><li><strong>Spécialité :</strong> " . htmlspecialchars($specialite) . "</li><li><strong>Téléphone :</strong> " . htmlspecialchars($telephone) . "</li><li><strong>Adresse Cabinet :</strong> " . htmlspecialchars($adresse) . "</li></ul><p>Veuillez vous connecter à l'espace d'administration pour examiner et valider cette demande (ID Médecin: {$nouveau_medecin_id}). Vous pourrez y consulter le document justificatif soumis.</p><div class='button-container'><a href='". $protocol . $host . $base_path_notif ."/admin/gestion_medecins.php?status=attente' class='button'>Accéder à la Gestion des Médecins</a></div><p>Cordialement,<br>Le Système ".(defined('NOM_APPLICATION') ? NOM_APPLICATION : 'SANTE TV')."</p>";
        $corps_html_admin = get_email_html_layout($sujet_admin, $contenu_principal_admin, (defined('NOM_APPLICATION') ? NOM_APPLICATION : 'SANTE TV')." - Notifications Admin");
        envoyer_email($admin_notif_email, $admin_notif_name, $sujet_admin, $corps_html_admin);
    }

    $_SESSION['flash_message'] = "Votre demande d'inscription a été envoyée avec succès ! Elle sera examinée par un administrateur et vous serez notifié(e) par e-mail de la décision.";
    $_SESSION['flash_type'] = "success";
    
    header('Location: ../index.php?inscription_med_feedback=succes'); 
    exit;

} catch (PDOException $e) {
    error_log("Erreur PDO inscription médecin (Email: $email): " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    if ($newDocServerPathMed && file_exists($newDocServerPathMed)) { 
        @unlink($newDocServerPathMed);
    }
    
    $_SESSION[$session_data_key_med] = $_POST; 
    $pdo_error_msg = "Erreur technique lors de l'inscription. Veuillez réessayer plus tard.";
    if ($e->getCode() == 23000) { 
        $pdo_error_msg = "Erreur: L'e-mail '" . htmlspecialchars($email) . "' est déjà utilisé.";
        $_SESSION[$session_errors_key_med]['email'] = "Cet e-mail est déjà utilisé. <a href='../pages/connexion.php'>Se connecter?</a>";
    } else {
        $_SESSION[$session_errors_key_med]['_general'] = $pdo_error_msg;
    }

    if ($is_modal_origin_med_form_handling) {
        $_SESSION['flash_message'] = $_SESSION[$session_errors_key_med]['email'] ?? $pdo_error_msg;
        $_SESSION['flash_type'] = "error";
    } else {
        $_SESSION['flash_message_page'] = $_SESSION[$session_errors_key_med]['email'] ?? $pdo_error_msg;
        $_SESSION['flash_type_page'] = "error";
    }
    header("Location: " . $form_origin);
    exit;
}
?>