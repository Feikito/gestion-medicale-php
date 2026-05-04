<?php
session_start();
require '../php/db.php';
require_once '../php/utils/csrf_utils.php';
require_once '../php/utils/email_functions.php'; 
require_once '../php/utils/email_template.php';

if (!isset($_SESSION['admin_id'])) {
    // Pour les requêtes AJAX, retourner une erreur JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé.']);
        exit;
    }
    $_SESSION['flash_message_login'] = "Accès refusé.";
    $_SESSION['flash_type_login'] = "error";
    header('Location: ../pages/admin-login.php'); 
    exit;
}

$admin_id_for_log = $_SESSION['admin_id'] ?? null;
$redirect_form_page = 'envoyer_email_specifique.php';


// Gérer les requêtes AJAX pour la recherche d'utilisateurs
if (isset($_GET['action']) && $_GET['action'] === 'search_users') {
    header('Content-Type: application/json');
    $searchTerm = trim($_GET['term'] ?? '');
    $userType = trim($_GET['type'] ?? '');
    $results = [];

    if (strlen($searchTerm) >= 2 && in_array($userType, ['patient', 'medecin'])) {
        $table = ($userType === 'patient') ? 'patients' : 'medecins';
        $sql = "SELECT id, nom, prenom, email FROM $table 
                WHERE (CONCAT(prenom, ' ', nom) LIKE :term 
                OR CONCAT(nom, ' ', prenom) LIKE :term 
                OR email LIKE :term)
                AND email IS NOT NULL AND email != ''
                LIMIT 10";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':term' => "%$searchTerm%"]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur recherche utilisateur AJAX: " . $e->getMessage());
            // Ne pas envoyer d'erreur JSON détaillée au client
        }
    }
    echo json_encode($results);
    exit;
}


// Traitement du formulaire principal d'envoi d'email
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

$sujet = trim($_POST['sujet'] ?? '');
$message_html_brut_admin = trim($_POST['message'] ?? '');
$selected_user_ids_raw = $_POST['selected_user_ids'] ?? []; // Devrait être un tableau de chaînes "type:id"

$_SESSION['form_data_admin_email_spec'] = $_POST; // Sauvegarder pour pré-remplissage
$errors = [];
if (empty($sujet)) $errors['sujet'] = "Le sujet de l'email est requis.";
if (empty($message_html_brut_admin)) $errors['message'] = "Le message de l'email est requis.";
if (empty($selected_user_ids_raw) || !is_array($selected_user_ids_raw)) {
    $errors['selected_user_ids'] = "Aucun destinataire n'a été sélectionné.";
}

$destinataires_final = [];
if (is_array($selected_user_ids_raw)) {
    foreach ($selected_user_ids_raw as $user_entry) {
        if (is_string($user_entry) && strpos($user_entry, ':') !== false) {
            list($type, $id) = explode(':', $user_entry, 2);
            $id = filter_var($id, FILTER_VALIDATE_INT);
            if ($id && in_array($type, ['patient', 'medecin'])) {
                $destinataires_final[] = ['type' => $type, 'id' => $id];
            }
        }
    }
}
if (empty($destinataires_final) && empty($errors['selected_user_ids'])) {
     $errors['selected_user_ids'] = "Aucun destinataire valide sélectionné après vérification.";
}


if (!empty($errors)) {
    $_SESSION['form_errors_admin_email_spec'] = $errors;
    $_SESSION['flash_message'] = "Veuillez corriger les erreurs de validation.";
    $_SESSION['flash_type'] = "error";
    // Préparer les IDs sélectionnés pour les réafficher
    $temp_selected_ids_for_session = [];
    if(is_array($selected_user_ids_raw)){
        foreach($selected_user_ids_raw as $raw_id_entry){
            if (is_string($raw_id_entry) && strpos($raw_id_entry, ':') !== false) {
                 list($type, $id) = explode(':', $raw_id_entry, 2);
                 $temp_selected_ids_for_session[] = ['type' => $type, 'id' => $id]; // Non, il faut reconstruire les données initiales
            }
        }
    }
     $_SESSION['selected_users_ids_admin_email_spec'] = $destinataires_final; // Utiliser les IDs parsés et validés
    header("Location: " . $redirect_form_page);
    exit();
}


$emails_envoyes = 0;
$emails_echoues = 0;
$nom_expediteur_email_admin = defined('ADMIN_NAME_NOTIFICATIONS') ? ADMIN_NAME_NOTIFICATIONS : 'Administration SANTE TV';
$email_expediteur_admin = defined('ADMIN_EMAIL_NOTIFICATIONS') ? ADMIN_EMAIL_NOTIFICATIONS : EMAIL_SYSTEM_FROM_ADDRESS; 

foreach ($destinataires_final as $dest_info) {
    $table_fetch = ($dest_info['type'] === 'patient') ? 'patients' : 'medecins';
    try {
        $stmt_user = $pdo->prepare("SELECT email, prenom, nom FROM $table_fetch WHERE id = ? AND email IS NOT NULL AND email != ''");
        $stmt_user->execute([$dest_info['id']]);
        $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            $nom_complet_destinataire = trim(htmlspecialchars(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? 'Utilisateur')));
            if ($dest_info['type'] === 'medecin' && strpos($nom_complet_destinataire, 'Dr. ') !== 0) {
                $nom_complet_destinataire = "Dr. " . $nom_complet_destinataire;
            }

            $message_personnalise_contenu = str_replace('%NOM_UTILISATEUR%', $nom_complet_destinataire, $message_html_brut_admin);
            $corps_html_final = get_email_html_layout($sujet, $message_personnalise_contenu, "SANTE TV");

            if (envoyer_email($user['email'], $nom_complet_destinataire, $sujet, $corps_html_final, '', $email_expediteur_admin, $nom_expediteur_email_admin)) {
                $emails_envoyes++;
            } else {
                $emails_echoues++;
                error_log("Échec envoi à " . $user['email'] . " (ID: ".$dest_info['type']."-".$dest_info['id'].")");
            }
        } else {
            $emails_echoues++;
            error_log("Destinataire invalide ou email manquant pour ID: ".$dest_info['type']."-".$dest_info['id']);
        }
    } catch (PDOException $e) {
        $emails_echoues++;
        error_log("Erreur PDO envoi email spécifique à ID: ".$dest_info['type']."-".$dest_info['id']." : " . $e->getMessage());
    }
    if (($emails_envoyes + $emails_echoues) % 10 == 0) { sleep(1); }
}

unset($_SESSION['form_data_admin_email_spec'], $_SESSION['selected_users_ids_admin_email_spec']);


if ($emails_echoues > 0) {
    $_SESSION['flash_message'] = "{$emails_envoyes} email(s) envoyé(s) avec succès. {$emails_echoues} email(s) en échec. Vérifiez les logs du serveur pour plus de détails.";
    $_SESSION['flash_type'] = "warning";
} else {
    $_SESSION['flash_message'] = "{$emails_envoyes} email(s) envoyé(s) avec succès aux destinataires sélectionnés.";
    $_SESSION['flash_type'] = "success";
}

header("Location: " . $redirect_form_page);
exit();
?>