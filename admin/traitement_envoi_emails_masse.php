<?php
session_start();
require '../php/db.php';
require_once '../php/utils/csrf_utils.php';
require_once '../php/utils/email_functions.php'; 
require_once '../php/utils/email_template.php';
require_once '../php/utils/logger.php';
require_once '../php/utils/app_settings.php'; // Pour NOM_APPLICATION, etc.

if (!isset($_SESSION['admin_id'])) {
    // Pas de message flash ici car on ne redirige pas vers une page affichant des flash de login
    http_response_code(403); // Accès refusé
    echo "Accès non autorisé.";
    exit();
}
$admin_id_for_log = $_SESSION['admin_id'] ?? null;
$redirect_form_page = 'envoyer_emails_masse.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . $redirect_form_page);
    exit();
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) {
    $_SESSION['flash_message'] = "Erreur de sécurité.";
    $_SESSION['flash_type'] = "danger";
    header("Location: " . $redirect_form_page);
    exit;
}

$destinataires_groupe = trim($_POST['destinataires_groupe'] ?? '');
$sujet = trim($_POST['sujet'] ?? '');
$message_html_brut_admin = trim($_POST['message'] ?? '');

$_SESSION['form_data_email_masse'] = $_POST;
$errors = [];
if (empty($destinataires_groupe)) $errors['destinataires_groupe'] = "Veuillez sélectionner un groupe de destinataires.";
if (empty($sujet)) $errors['sujet'] = "Le sujet de l'email est requis.";
if (empty($message_html_brut_admin)) $errors['message'] = "Le message de l'email est requis.";

if (!empty($errors)) {
    $_SESSION['form_errors_email_masse'] = $errors;
    $_SESSION['flash_message'] = "Veuillez corriger les erreurs de validation.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $redirect_form_page);
    exit();
}

$liste_emails_destinataires = [];
$sql_get_emails = "";
$type_user_for_log = $destinataires_groupe;

switch ($destinataires_groupe) {
    case 'tous_patients':
        $sql_get_emails = "SELECT id, email, prenom, nom FROM patients WHERE email IS NOT NULL AND email != ''";
        break;
    case 'tous_medecins':
        $sql_get_emails = "SELECT id, email, prenom, nom FROM medecins WHERE email IS NOT NULL AND email != ''";
        break;
    case 'medecins_valides':
        $sql_get_emails = "SELECT id, email, prenom, nom FROM medecins WHERE valide = 1 AND email IS NOT NULL AND email != ''";
        break;
    case 'medecins_attente':
        $sql_get_emails = "SELECT id, email, prenom, nom FROM medecins WHERE valide = 0 AND email IS NOT NULL AND email != ''";
        break;
    case 'tous_utilisateurs':
        $sql_get_emails = "(SELECT id, email, prenom, nom, 'patient' as type_user_col FROM patients WHERE email IS NOT NULL AND email != '')
                           UNION ALL
                           (SELECT id, email, prenom, nom, 'medecin' as type_user_col FROM medecins WHERE email IS NOT NULL AND email != '')";
        break;
    default:
        $_SESSION['flash_message'] = "Groupe de destinataires non valide.";
        $_SESSION['flash_type'] = "error";
        header("Location: " . $redirect_form_page);
        exit();
}

try {
    $stmt = $pdo->query($sql_get_emails);
    $destinataires = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur SQL récupération emails en masse pour le groupe '$destinataires_groupe': " . $e->getMessage());
    $_SESSION['flash_message'] = "Erreur lors de la récupération des destinataires pour le groupe '$destinataires_groupe'.";
    $_SESSION['flash_type'] = "error";
    header("Location: " . $redirect_form_page);
    exit();
}

if (empty($destinataires)) {
    $_SESSION['flash_message'] = "Aucun destinataire trouvé pour le groupe sélectionné: '$destinataires_groupe'.";
    $_SESSION['flash_type'] = "warning";
    header("Location: " . $redirect_form_page);
    exit();
}

$emails_envoyes = 0;
$emails_echoues = 0;
$destinataires_echoues_details = [];

$nom_expediteur_email_admin = defined('ADMIN_NAME_NOTIFICATIONS') ? ADMIN_NAME_NOTIFICATIONS : (defined('NOM_APPLICATION') ? NOM_APPLICATION . ' Admin' : 'Administration SANTE TV');
$email_expediteur_admin = defined('ADMIN_EMAIL_NOTIFICATIONS') ? ADMIN_EMAIL_NOTIFICATIONS : (defined('EMAIL_SYSTEM_FROM_ADDRESS') ? EMAIL_SYSTEM_FROM_ADDRESS : 'nepasrepondre@santetv.ma');
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$nom_application_email = defined('NOM_APPLICATION') ? NOM_APPLICATION : 'SANTE TV';


foreach ($destinataires as $destinataire) {
    if (empty($destinataire['email']) || !filter_var($destinataire['email'], FILTER_VALIDATE_EMAIL)) {
        $emails_echoues++;
        $destinataires_echoues_details[] = "Format email invalide pour ID " . ($destinataire['id'] ?? 'N/A') . " Email: " . ($destinataire['email'] ?? 'Vide');
        error_log("Email en masse: Format invalide pour " . ($destinataire['email'] ?? 'Vide'));
        continue;
    }

    $nom_complet_destinataire = trim(htmlspecialchars(($destinataire['prenom'] ?? '') . ' ' . ($destinataire['nom'] ?? 'Utilisateur')));
    $current_user_type = $destinataire['type_user_col'] ?? ($destinataires_groupe === 'tous_patients' ? 'patient' : ($destinataires_groupe === 'tous_medecins' || $destinataires_groupe === 'medecins_valides' || $destinataires_groupe === 'medecins_attente' ? 'medecin' : 'utilisateur'));
    
    if ($current_user_type === 'medecin' && strpos($nom_complet_destinataire, 'Dr. ') !== 0 && !empty(trim($nom_complet_destinataire))) {
        $nom_complet_destinataire = "Dr. " . $nom_complet_destinataire;
    }

    $message_personnalise_contenu = str_replace('%NOM_UTILISATEUR%', $nom_complet_destinataire, $message_html_brut_admin);
    $corps_html_final = get_email_html_layout($sujet, $message_personnalise_contenu, $nom_application_email);

    if (envoyer_email($destinataire['email'], $nom_complet_destinataire, $sujet, $corps_html_final, '', $email_expediteur_admin, $nom_expediteur_email_admin)) {
        $emails_envoyes++;
    } else {
        $emails_echoues++;
        $destinataires_echoues_details[] = $destinataire['email'];
        error_log("Email en masse: Échec de l'envoi à " . $destinataire['email']);
    }

    if (($emails_envoyes + $emails_echoues) % 20 == 0 && ($emails_envoyes + $emails_echoues) > 0) { 
        sleep(1); 
    }
}

$description_log = "Email en masse envoyé au groupe: '$type_user_for_log'. Sujet: '$sujet'. Envoyés: $emails_envoyes, Échoués: $emails_echoues.";
$details_log = ['groupe' => $type_user_for_log, 'sujet' => $sujet, 'envoyes' => $emails_envoyes, 'echoues' => $emails_echoues];
if (!empty($destinataires_echoues_details)) {
    $details_log['destinataires_echoues'] = $destinataires_echoues_details;
}

log_action_application(
    $pdo,
    'ENVOI_EMAIL_MASSE',
    $description_log,
    null, null,
    $details_log
);

unset($_SESSION['form_data_email_masse']);

if ($emails_echoues > 0) {
    $message_flash_final = "{$emails_envoyes} email(s) envoyé(s) avec succès. {$emails_echoues} email(s) en échec. ";
    if (count($destinataires_echoues_details) <= 5) { // Afficher quelques emails en échec si peu nombreux
        $message_flash_final .= "Échecs pour : " . implode(', ', array_map('htmlspecialchars', $destinataires_echoues_details)) . ". ";
    }
    $message_flash_final .= "Vérifiez les logs du serveur pour plus de détails.";
    $_SESSION['flash_message'] = $message_flash_final;
    $_SESSION['flash_type'] = "warning";
} else {
    $_SESSION['flash_message'] = "{$emails_envoyes} email(s) envoyé(s) avec succès à tous les destinataires du groupe choisi.";
    $_SESSION['flash_type'] = "success";
}

header("Location: " . $redirect_form_page);
exit();
?>