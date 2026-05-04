<?php
session_start();
require 'db.php';
require_once __DIR__ . '/utils/csrf_utils.php';
require_once __DIR__ . '/utils/email_functions.php';
require_once __DIR__ . '/utils/email_template.php';

if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'medecin') {
    $_SESSION['flash_message'] = "Accès non autorisé pour effectuer cette action.";
    $_SESSION['flash_type'] = "error";
    // Rediriger vers la page de connexion si le type n'est pas bon, ou vers l'espace médecin si déjà connecté mais pas médecin
    $redirect_fallback = isset($_SESSION['utilisateur_id']) ? 'espace_medecin.php' : '../pages/connexion.php';
    header('Location: ' . $redirect_fallback);
    exit;
}
$medecin_id = $_SESSION['utilisateur_id'];

// Récupérer l'email et le nom du médecin connecté pour le champ "Reply-To" et la signature
$medecin_email_reply_to = $_SESSION['medecin_email_for_reply'] ?? '';
$medecin_nom_reply_to = $_SESSION['medecin_nom_for_reply'] ?? 'Votre Médecin';

if (empty($medecin_email_reply_to)) { // Fallback si non défini en session
    try {
        $stmt_med_info = $pdo->prepare("SELECT email, prenom, nom FROM medecins WHERE id = ?");
        $stmt_med_info->execute([$medecin_id]);
        $med_data = $stmt_med_info->fetch();
        if ($med_data) {
            $medecin_email_reply_to = $med_data['email'];
            $medecin_nom_reply_to = "Dr. " . htmlspecialchars($med_data['prenom'] . ' ' . $med_data['nom']);
        } else {
            // Utiliser l'email système si l'email du médecin n'est pas trouvé
            $medecin_email_reply_to = defined('EMAIL_SYSTEM_FROM_ADDRESS') ? EMAIL_SYSTEM_FROM_ADDRESS : 'nepasrepondre@santetv.ma';
        }
    } catch (PDOException $e) {
        error_log("Erreur PDO récupération email médecin pour Reply-To (traitement_reponse_medecin): " . $e->getMessage());
        $medecin_email_reply_to = defined('EMAIL_SYSTEM_FROM_ADDRESS') ? EMAIL_SYSTEM_FROM_ADDRESS : 'nepasrepondre@santetv.ma';
    }
}

$redirect_page = 'messages_medecin.php'; // Page d'origine par défaut

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect_page);
    exit;
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'], true)) {
    $_SESSION['flash_message'] = "Erreur de sécurité lors de l'envoi de la réponse. Veuillez réessayer.";
    $_SESSION['flash_type'] = "danger";
    header('Location: ' . $redirect_page);
    exit;
}

$patient_email_destinataire = trim(filter_input(INPUT_POST, 'patient_email_destinataire', FILTER_SANITIZE_EMAIL));
$patient_id_destinataire = filter_input(INPUT_POST, 'patient_id_destinataire', FILTER_VALIDATE_INT); // Peut être utile pour logs ou futures fonctionnalités
$sujet = trim(strip_tags($_POST['sujet'] ?? 'Réponse à votre message'));
$message_html_medecin = $_POST['message'] ?? ''; // Contenu de TinyMCE est déjà HTML

$errors = [];
if (empty($patient_email_destinataire) || !filter_var($patient_email_destinataire, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "L'adresse e-mail du destinataire est invalide.";
}
if (empty($sujet)) {
    $errors[] = "Le sujet de la réponse est requis.";
}
if (empty($message_html_medecin)) {
    $errors[] = "Le contenu de la réponse ne peut pas être vide.";
} elseif (strlen($message_html_medecin) < 10 && strlen(strip_tags($message_html_medecin)) < 10) {
    $errors[] = "Votre réponse doit contenir au moins 10 caractères.";
} elseif (strlen($message_html_medecin) > 10000) { // Limite généreuse pour HTML
    $errors[] = "Votre réponse est trop longue.";
}


if (!empty($errors)) {
    $_SESSION['flash_message'] = "Erreurs dans le formulaire de réponse : <br>" . implode("<br>", $errors);
    $_SESSION['flash_type'] = "error";
    // Pourrait stocker les données en session pour pré-remplir si besoin, mais la modale se réouvrira vide par défaut.
    header('Location: ' . $redirect_page . '#modalRepondreEmail'); // Tenter de rouvrir la modale
    exit;
}

// Récupérer le nom du patient pour une salutation personnalisée
$nom_patient_destinataire = 'Patient(e)';
if ($patient_id_destinataire) {
    try {
        $stmt_patient_name = $pdo->prepare("SELECT prenom, nom FROM patients WHERE id = ?");
        $stmt_patient_name->execute([$patient_id_destinataire]);
        $patient_data_dest = $stmt_patient_name->fetch();
        if ($patient_data_dest) {
            $nom_patient_destinataire = trim(htmlspecialchars($patient_data_dest['prenom'] . ' ' . $patient_data_dest['nom']));
        }
    } catch (PDOException $e) {
        error_log("Erreur PDO récupération nom patient pour email (traitement_reponse_medecin): " . $e->getMessage());
    }
}


$contenu_principal_email = "
    <p>Bonjour " . $nom_patient_destinataire . ",</p>
    <p>Vous avez reçu une réponse de la part de <strong>" . htmlspecialchars($medecin_nom_reply_to) . "</strong> concernant votre message précédent sur la plateforme SANTE TV.</p>
    <p><strong>Sujet :</strong> " . htmlspecialchars($sujet) . "</p>
    <hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>
    <div style='padding:10px 0; font-size:1em; line-height:1.7; color:#333333;'>
        " . $message_html_medecin . " 
    </div>
    <hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>
    <p>Si vous souhaitez répondre à nouveau, veuillez vous connecter à votre espace patient sur SANTE TV et utiliser la fonction de messagerie, ou répondre directement à cet e-mail si votre client de messagerie le permet (l'adresse de réponse a été définie sur celle du médecin).</p>
    <p>Cordialement,<br>Le service de messagerie SANTE TV (pour le compte de " . htmlspecialchars($medecin_nom_reply_to) . ")</p>";

$corps_html_complet = get_email_html_layout("Réponse de " . htmlspecialchars($medecin_nom_reply_to) . " : " . htmlspecialchars($sujet), $contenu_principal_email, "SANTE TV");

if (envoyer_email($patient_email_destinataire, $nom_patient_destinataire, "Réponse de " . htmlspecialchars($medecin_nom_reply_to) . " : " . htmlspecialchars($sujet), $corps_html_complet, '', $medecin_email_reply_to, $medecin_nom_reply_to)) {
    $_SESSION['flash_message'] = "Votre réponse a été envoyée avec succès à " . htmlspecialchars($nom_patient_destinataire) . " (" . htmlspecialchars($patient_email_destinataire) . ").";
    $_SESSION['flash_type'] = "success";
} else {
    $_SESSION['flash_message'] = "Échec de l'envoi de votre réponse. Le message n'a pas pu être transmis. Veuillez réessayer ou contacter le support.";
    $_SESSION['flash_type'] = "error";
}

header('Location: ' . $redirect_page);
exit;
?>