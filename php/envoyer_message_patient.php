<?php
session_start(); 
require 'db.php'; 
require_once __DIR__ . '/utils/csrf_utils.php'; 
require_once __DIR__ . '/utils/email_functions.php'; 
require_once __DIR__ . '/utils/email_template.php';

if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'patient') {
    $_SESSION['flash_message_login'] = "Vous devez être connecté en tant que patient pour envoyer un message.";
    $_SESSION['flash_type_login'] = "warning";
    header('Location: ../pages/connexion.php');
    exit;
}
$patient_id_env_msg = $_SESSION['utilisateur_id'];
$nom_patient_env_msg = $_SESSION['nom'] ?? 'Un patient'; 
$patient_email_reply_to = ''; // Sera récupéré de la BDD

// Récupérer l'email du patient pour le Reply-To
try {
    $stmt_get_patient_email = $pdo->prepare("SELECT email FROM patients WHERE id = ?");
    $stmt_get_patient_email->execute([$patient_id_env_msg]);
    $patient_email_data = $stmt_get_patient_email->fetch();
    if ($patient_email_data) {
        $patient_email_reply_to = $patient_email_data['email'];
    } else {
        // Gérer le cas où l'email du patient n'est pas trouvé, bien que peu probable s'il est connecté
        error_log("Impossible de récupérer l'email du patient ID: " . $patient_id_env_msg . " pour Reply-To.");
        // On pourrait utiliser l'EMAIL_SYSTEM_FROM_ADDRESS comme fallback pour Reply-To si nécessaire
    }
} catch (PDOException $e) {
    error_log("Erreur PDO récupération email patient pour Reply-To: " . $e->getMessage());
}


$form_origin_message_patient = $_POST['form_origin_message'] ?? 'dashboard_patient.php';
// S'assurer que le chemin de redirection est correct depuis le dossier php/
if (strpos($form_origin_message_patient, 'dashboard_patient.php') !== false) {
    $form_origin_message_patient = 'dashboard_patient.php'; // Reste dans le dossier php/
} else {
    // Fallback si l'origine n'est pas reconnue
    $form_origin_message_patient = 'dashboard_patient.php';
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $form_origin_message_patient); 
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) { 
    $_SESSION['flash_message'] = "Erreur de sécurité lors de l'envoi du message. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    $_SESSION['form_data_message_patient_dashboard'] = $_POST;
    header("Location: " . $form_origin_message_patient . "#formEnvoyerMessagePatientDashboard");
    exit;
}

$medecin_id_destinataire = filter_input(INPUT_POST, 'medecin_id', FILTER_VALIDATE_INT);
$sujet_patient_message = trim(strip_tags($_POST['sujet'] ?? ''));
$contenu_message_patient = trim(strip_tags($_POST['contenu'] ?? ''));

$_SESSION['form_errors_message_patient_dashboard'] = [];
$errors_msg_patient = &$_SESSION['form_errors_message_patient_dashboard'];
$medecin_dest_data = null; 

if (!$medecin_id_destinataire) {
    $errors_msg_patient['medecin_id'] = "Veuillez sélectionner un médecin destinataire valide.";
} else {
    $stmt_check_med_dest = $pdo->prepare("SELECT id, email, nom, prenom FROM medecins WHERE id = ? AND valide = 1");
    $stmt_check_med_dest->execute([$medecin_id_destinataire]);
    $medecin_dest_data = $stmt_check_med_dest->fetch(PDO::FETCH_ASSOC);
    if (!$medecin_dest_data) {
        $errors_msg_patient['medecin_id'] = "Le médecin destinataire sélectionné n'est pas valide ou n'existe pas.";
    }
}

if (empty($contenu_message_patient)) {
    $errors_msg_patient['contenu'] = "Veuillez écrire le contenu de votre message.";
} elseif (strlen($contenu_message_patient) < 5) { 
    $errors_msg_patient['contenu'] = "Votre message doit contenir au moins 5 caractères.";
} elseif (strlen($contenu_message_patient) > 2000) { 
    $errors_msg_patient['contenu'] = "Votre message est trop long (maximum 2000 caractères).";
}
if (!empty($sujet_patient_message) && strlen($sujet_patient_message) > 255) {
    $errors_msg_patient['sujet'] = "Le sujet est trop long (maximum 255 caractères).";
}


if (!empty($errors_msg_patient)) {
    $_SESSION['flash_message'] = "Votre message contient des erreurs. Veuillez vérifier les champs.";
    $_SESSION['flash_type'] = "error";
    $_SESSION['form_data_message_patient_dashboard'] = $_POST; 
    header("Location: " . $form_origin_message_patient . "#formEnvoyerMessagePatientDashboard"); 
    exit;
}

try {
    // S'assurer que la table messages existe et a les bonnes colonnes
    $table_messages_exists = $pdo->query("SHOW TABLES LIKE 'messages'")->rowCount() > 0;
    if (!$table_messages_exists) {
        throw new PDOException("La table 'messages' est manquante. L'envoi de message est impossible.");
    }
    // Vérifier si les colonnes nécessaires existent (ex: sujet_message)
    $message_cols = $pdo->query("DESCRIBE messages")->fetchAll(PDO::FETCH_COLUMN);
    $has_sujet_col = in_array('sujet_message', $message_cols);


    $sql_insert_message = "INSERT INTO messages (patient_id, destinataire_id, " . ($has_sujet_col ? "sujet_message, " : "") . "contenu, date_envoi, lu_par_medecin) 
                           VALUES (:patient_id, :destinataire_id, " . ($has_sujet_col ? ":sujet, " : "") . ":contenu, NOW(), 0)";
    $stmt_insert_message = $pdo->prepare($sql_insert_message);
    
    $params_insert_msg = [
        ':patient_id' => $patient_id_env_msg,
        ':destinataire_id' => $medecin_id_destinataire, 
        ':contenu' => $contenu_message_patient 
    ];
    if ($has_sujet_col) {
        $params_insert_msg[':sujet'] = !empty($sujet_patient_message) ? $sujet_patient_message : 'Message depuis la plateforme SANTE TV';
    }
    
    $stmt_insert_message->execute($params_insert_msg);
    $nouveau_message_id = $pdo->lastInsertId();


    if ($medecin_dest_data && function_exists('envoyer_email') && function_exists('get_email_html_layout')) {
        $nom_medecin_dest_email = "Dr. " . htmlspecialchars($medecin_dest_data['prenom'] . ' ' . $medecin_dest_data['nom']);
        $sujet_notif_medecin = !empty($sujet_patient_message) ? "Nouveau message: " . htmlspecialchars($sujet_patient_message) : "Nouveau message d'un patient sur SANTE TV";
        
        $contenu_principal_email = "
            <p>Bonjour " . $nom_medecin_dest_email . ",</p>
            <p>Vous avez reçu un nouveau message de la part de <strong>" . htmlspecialchars($nom_patient_env_msg) . "</strong> (Email: " . htmlspecialchars($patient_email_reply_to ?: 'Non fourni') . ") sur la plateforme SANTE TV.</p>";
        if(!empty($sujet_patient_message)){
             $contenu_principal_email .= "<p><strong>Sujet :</strong> " . htmlspecialchars($sujet_patient_message) . "</p>";
        }
        $contenu_principal_email .= "
            <p><strong>Message :</strong></p>
            <blockquote style='border-left: 3px solid #ccc; padding-left: 15px; margin-left: 0; font-style: italic;'>" 
            . nl2br(htmlspecialchars($contenu_message_patient)) . 
            "</blockquote>
            <p>Veuillez vous connecter à votre espace médecin pour consulter le message complet et y répondre si nécessaire.</p>
            <div class='button-container'>
                 <a href='". $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) ."/messages_medecin.php' class='button'>Accéder à ma messagerie</a>
            </div>
            <p>Cordialement,<br>L'équipe SANTE TV</p>";
            
        $corps_html_notif_medecin = get_email_html_layout($sujet_notif_medecin, $contenu_principal_email, "SANTE TV - Notification");
        
        envoyer_email(
            $medecin_dest_data['email'], 
            $nom_medecin_dest_email, 
            $sujet_notif_medecin, 
            $corps_html_notif_medecin, 
            '', 
            $patient_email_reply_to, 
            $nom_patient_env_msg     
        );
    }

    $_SESSION['flash_message'] = "Votre message a été envoyé avec succès au Dr. " . htmlspecialchars($medecin_dest_data['prenom'] . ' ' . $medecin_dest_data['nom'] ?? '') . " !";
    $_SESSION['flash_type'] = "success";
    unset($_SESSION['form_data_message_patient_dashboard']);
    unset($_SESSION['form_errors_message_patient_dashboard']);
    header("Location: " . $form_origin_message_patient . "#formEnvoyerMessagePatientDashboard"); 
    exit;

} catch (PDOException $e) {
    error_log("Erreur PDO envoyer_message_patient.php (Patient ID: $patient_id_env_msg, Medecin ID: $medecin_id_destinataire): " . $e->getMessage());
    $_SESSION['flash_message'] = "Une erreur technique est survenue lors de l'envoi de votre message. Veuillez réessayer.";
    $_SESSION['flash_type'] = "error";
    $_SESSION['form_data_message_patient_dashboard'] = $_POST;
    header("Location: " . $form_origin_message_patient . "#formEnvoyerMessagePatientDashboard");
    exit;
}
?>