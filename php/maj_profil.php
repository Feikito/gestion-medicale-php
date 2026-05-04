<?php
// php/maj_profil.php
session_start();
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; // Ajouté pour la validation CSRF

// 1. Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type'])) {
    header('Location: ../pages/connexion.php'); // Rediriger vers la page de connexion dédiée
    exit;
}

$user_id = $_SESSION['utilisateur_id'];
$user_type = $_SESSION['type'];
$table_name = ($user_type === 'patient') ? 'patients' : 'medecins';

// Les pages de profil sont dans le même dossier php/
$redirect_page = ($user_type === 'patient') ? 'profil_patient.php' : 'profil_medecin.php';
// Le champ form_origin_profil dans les formulaires des pages de profil doit être ajusté en conséquence.
// Exemple pour profil_patient.php: <input type="hidden" name="form_origin_profil" value="profil_patient.php#infosPersonnellesSection">


// 2. Vérifier que la méthode est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $redirect_page"); 
    exit;
}

// --- VALIDATION CSRF ---
// form_origin_profil est envoyé par les formulaires de profil_patient.php et profil_medecin.php
$form_origin_from_post = $_POST['form_origin_profil'] ?? $redirect_page; 

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $_SESSION['flash_message'] = "Erreur de sécurité lors de la mise à jour du profil. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    // Stocker les données pour pré-remplissage si besoin (pour la page de profil)
    $session_data_key_profil = ($user_type === 'patient') ? 'form_data_maj_profil_patient' : 'form_data_maj_profil_med';
    $_SESSION[$session_data_key_profil] = $_POST; 
    header("Location: " . $form_origin_from_post); 
    exit;
}


// 3. Récupérer les informations actuelles de l'utilisateur
try {
    $stmt_current_info = $pdo->prepare("SELECT * FROM $table_name WHERE id = ?");
    $stmt_current_info->execute([$user_id]);
    $current_info = $stmt_current_info->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO récupération infos actuelles maj_profil.php (ID: $user_id): " . $e->getMessage());
    $_SESSION['flash_message'] = "Une erreur technique est survenue. Veuillez réessayer.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $form_origin_from_post);
    exit;
}

if (!$current_info) {
    $_SESSION['flash_message_login'] = "Erreur: Utilisateur introuvable. Veuillez vous reconnecter."; // Message pour la page de connexion
    $_SESSION['flash_type_login'] = "error";
    session_unset(); 
    session_destroy(); 
    header('Location: ../pages/connexion.php'); // Rediriger vers la page de connexion dédiée
    exit;
}

// 4. Récupérer et valider les données du formulaire
$nom = trim($_POST['nom'] ?? '');
$prenom = trim($_POST['prenom'] ?? '');
$email = trim(strtolower($_POST['email'] ?? '')); // Email en minuscules pour la comparaison
$adresse = trim($_POST['adresse'] ?? ''); 

// Clés de session pour les erreurs spécifiques au formulaire de mise à jour d'infos
$session_errors_key_info = ($user_type === 'patient') ? 'form_errors_maj_profil_patient' : 'form_errors_maj_profil_med';
$_SESSION[$session_errors_key_info] = [];
$errors_info = &$_SESSION[$session_errors_key_info];

if (empty($nom)) $errors_info['nom'] = "Le nom est requis.";
if (empty($prenom)) $errors_info['prenom'] = "Le prénom est requis.";
if (empty($email)) { 
    $errors_info['email'] = "L'email est requis.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors_info['email'] = "Format d'email invalide.";
}

// Vérifier l'unicité de l'email SI il a été modifié
if (strtolower($email) !== strtolower($current_info['email'])) {
    // Vérification globale de l'email (patients, medecins, admins)
    $stmt_check_email_global = $pdo->prepare(
        "(SELECT email FROM patients WHERE LOWER(email) = LOWER(?) AND id != IF(:user_type_check = 'patient', :user_id_check, -1))
         UNION 
         (SELECT email FROM medecins WHERE LOWER(email) = LOWER(?) AND id != IF(:user_type_check = 'medecin', :user_id_check, -1))
         UNION
         (SELECT email FROM admins WHERE LOWER(email) = LOWER(?))" // Pas d'exclusion pour les admins pour l'instant
    );
    $stmt_check_email_global->execute([
        $email, $user_type, $user_id, // Pour la condition IF dans la requête
        $email, $user_type, $user_id,
        $email
    ]);
    if ($stmt_check_email_global->fetch()) {
        $errors_info['email'] = "Cette adresse e-mail ('" . htmlspecialchars($_POST['email']) . "') est déjà utilisée par un autre compte.";
    }
}

if (!empty($errors_info)) {
    $_SESSION['flash_message'] = "Des erreurs ont été détectées dans le formulaire d'informations. Veuillez corriger.";
    $_SESSION['flash_type'] = "error";
    // Les données soumises seront pré-remplies via les clés de session sur la page de profil
    $session_data_key_info = ($user_type === 'patient') ? 'form_data_maj_profil_patient' : 'form_data_maj_profil_med';
    $_SESSION[$session_data_key_info] = $_POST; // Sauvegarder les données soumises
    header("Location: " . $form_origin_from_post);
    exit;
}

// 5. Gestion de la photo de profil
$photoPathInDb = $current_info['photo']; 

if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    $max_size = 2 * 1024 * 1024; 

    $file_tmp_name = $_FILES['photo']['tmp_name'];
    $file_size = $_FILES['photo']['size'];
    $file_original_name = basename($_FILES['photo']['name']);

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_tmp_name);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_mime_types)) {
        $_SESSION['flash_message'] = "Type de fichier photo non autorisé (JPG, PNG, GIF). Type: " . htmlspecialchars($mime_type);
        $_SESSION['flash_type'] = "error";
        header("Location: " . $form_origin_from_post); exit;
    }
    if ($file_size > $max_size) {
        $_SESSION['flash_message'] = "Photo trop volumineuse (max 2MB).";
        $_SESSION['flash_type'] = "error";
        header("Location: " . $form_origin_from_post); exit;
    }

    $uploadDirRelativeFromRoot = 'uploads/photos/'; 
    $uploadDirServerPath = __DIR__ . '/../' . $uploadDirRelativeFromRoot; 

    if (!is_dir($uploadDirServerPath)) {
        if (!mkdir($uploadDirServerPath, 0755, true)) {
            $_SESSION['flash_message'] = "Erreur création dossier d'uploads.";
            $_SESSION['flash_type'] = "error";
            error_log("Erreur mkdir maj_profil.php: " . $uploadDirServerPath);
            header("Location: " . $form_origin_from_post); exit;
        }
    }
    
    $extension = strtolower(pathinfo($file_original_name, PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        $_SESSION['flash_message'] = "Extension de fichier photo non autorisée.";
        $_SESSION['flash_type'] = "error";
        header("Location: " . $form_origin_from_post); exit;
    }

    $newPhotoName = uniqid('user_'.$user_id.'_', true) . '.' . $extension;
    $newPhotoPathInDb = $uploadDirRelativeFromRoot . $newPhotoName; 
    $newPhotoServerPath = $uploadDirServerPath . $newPhotoName;    

    if (move_uploaded_file($file_tmp_name, $newPhotoServerPath)) {
        $oldPhotoPathInDb = $current_info['photo'];
        if ($oldPhotoPathInDb && !empty($oldPhotoPathInDb) && $oldPhotoPathInDb !== $newPhotoPathInDb && file_exists(__DIR__ . '/../' . $oldPhotoPathInDb)) {
            @unlink(__DIR__ . '/../' . $oldPhotoPathInDb); // @ pour supprimer les erreurs si fichier déjà supprimé
        }
        $photoPathInDb = $newPhotoPathInDb; 
    } else {
        $_SESSION['flash_message'] = "Erreur lors du téléchargement de la nouvelle photo.";
        $_SESSION['flash_type'] = "error";
        // Ne pas bloquer, l'ancienne photo sera conservée
    }
} elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] != UPLOAD_ERR_NO_FILE && $_FILES['photo']['error'] != UPLOAD_ERR_OK) {
    $_SESSION['flash_message'] = "Erreur lors de l'upload de la photo (Code: ".$_FILES['photo']['error'].").";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $form_origin_from_post); exit;
}

// 6. Préparer les champs et paramètres pour UPDATE
$update_sql_parts = [];
$update_params = [];

$update_sql_parts[] = "nom = :nom"; $update_params[':nom'] = $nom;
$update_sql_parts[] = "prenom = :prenom"; $update_params[':prenom'] = $prenom;
$update_sql_parts[] = "email = :email_new"; $update_params[':email_new'] = $email; // Utiliser un placeholder différent
$update_sql_parts[] = "adresse = :adresse"; $update_params[':adresse'] = !empty($adresse) ? $adresse : null;
$update_sql_parts[] = "photo = :photo"; $update_params[':photo'] = $photoPathInDb;

if ($user_type === 'patient') {
    $date_naissance = $_POST['date_naissance'] ?? $current_info['date_naissance'];
    try {
        $date_obj = new DateTime($date_naissance);
        if ($date_obj->format('Y-m-d') !== $date_naissance || $date_obj >= new DateTime('today')) {
             $_SESSION['flash_message'] = "Date de naissance invalide.";
             $_SESSION['flash_type'] = "error"; header("Location: " . $form_origin_from_post); exit;
        }
    } catch (Exception $e) {
         $_SESSION['flash_message'] = "Format de date de naissance invalide.";
         $_SESSION['flash_type'] = "error"; header("Location: " . $form_origin_from_post); exit;
    }
    $update_sql_parts[] = "date_naissance = :date_naissance"; $update_params[':date_naissance'] = $date_naissance;

    $sexe = $_POST['sexe'] ?? $current_info['sexe'];
    if (!in_array($sexe, ['Homme', 'Femme'])) {
        $_SESSION['flash_message'] = "Valeur pour le sexe invalide.";
        $_SESSION['flash_type'] = "error"; header("Location: " . $form_origin_from_post); exit;
    }
    $update_sql_parts[] = "sexe = :sexe"; $update_params[':sexe'] = $sexe;
    
    $patient_colonnes = $pdo->query("DESCRIBE patients")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('telephone', $patient_colonnes)) {
        $telephone_patient = trim($_POST['telephone'] ?? ($current_info['telephone'] ?? null));
        $update_sql_parts[] = "telephone = :telephone_patient"; 
        $update_params[':telephone_patient'] = !empty($telephone_patient) ? $telephone_patient : null;
    }

} elseif ($user_type === 'medecin') {
    $telephone_med = trim($_POST['telephone'] ?? ($current_info['telephone'] ?? ''));
    $horaires = trim($_POST['horaires'] ?? ($current_info['horaires'] ?? null));
    
    $latitude_str = trim($_POST['latitude'] ?? '');
    $longitude_str = trim($_POST['longitude'] ?? '');

    $latitude = null; $longitude = null;
    if (!empty($latitude_str) && is_numeric($latitude_str) && $latitude_str >= -90 && $latitude_str <= 90) {
        $latitude = (float)$latitude_str;
    } elseif (empty($latitude_str) && array_key_exists('latitude', $_POST)) {
        $latitude = null;
    } else {
        $latitude = $current_info['latitude']; // Conserver l'ancienne si invalide ou non soumise
    }

    if (!empty($longitude_str) && is_numeric($longitude_str) && $longitude_str >= -180 && $longitude_str <= 180) {
        $longitude = (float)$longitude_str;
    } elseif (empty($longitude_str) && array_key_exists('longitude', $_POST)) {
        $longitude = null;
    } else {
        $longitude = $current_info['longitude'];
    }

    $update_sql_parts[] = "telephone = :telephone_med"; $update_params[':telephone_med'] = !empty($telephone_med) ? $telephone_med : null;
    $update_sql_parts[] = "horaires = :horaires"; $update_params[':horaires'] = $horaires;
    $update_sql_parts[] = "latitude = :latitude"; $update_params[':latitude'] = $latitude;
    $update_sql_parts[] = "longitude = :longitude"; $update_params[':longitude'] = $longitude;
}

$update_params[':id'] = $user_id; 

// 7. Exécuter la mise à jour
$sql_final_update = "UPDATE $table_name SET " . implode(', ', $update_sql_parts) . " WHERE id = :id";

try {
    $stmt_final_update = $pdo->prepare($sql_final_update);
    if ($stmt_final_update->execute($update_params)) {
        $_SESSION['flash_message'] = "Votre profil a été mis à jour avec succès.";
        $_SESSION['flash_type'] = "success";
        
        $nouveau_nom_session = ($user_type === 'medecin' ? 'Dr. ' : '') . htmlspecialchars($prenom) . ' ' . htmlspecialchars($nom);
        if (isset($_SESSION['nom']) && $_SESSION['nom'] !== $nouveau_nom_session) {
             $_SESSION['nom'] = $nouveau_nom_session; 
        }
    } else {
        throw new PDOException("Erreur lors de l'exécution de la mise à jour du profil.");
    }
} catch (PDOException $e) {
    error_log("Erreur MAJ profil ($user_type, ID $user_id): " . $e->getMessage());
    $error_message_db = "Une erreur technique est survenue lors de la mise à jour.";
    if ($e->getCode() == 23000 && stripos($e->getMessage(), 'email') !== false) { // Vérifier si c'est une contrainte unique sur l'email
        $error_message_db = "L'adresse e-mail '" . htmlspecialchars($_POST['email']) . "' est déjà utilisée.";
        // Remettre l'erreur spécifique sur le champ email pour la page de profil
        $_SESSION[$session_errors_key_info]['email'] = $error_message_db;
    }
    $_SESSION['flash_message'] = $error_message_db;
    $_SESSION['flash_type'] = "error";
    // Sauvegarder les données pour réaffichage
    $session_data_key_info_error = ($user_type === 'patient') ? 'form_data_maj_profil_patient' : 'form_data_maj_profil_med';
    $_SESSION[$session_data_key_info_error] = $_POST;
}

header("Location: " . $form_origin_from_post); // Utiliser l'origine postée
exit;
?>